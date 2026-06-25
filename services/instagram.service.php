<?php

/**
 * Instagram
 *
 * Publica imágenes de una galería en la cuenta de Instagram del tenant:
 *   - Carrusel/imagen en el feed -> publicar()
 *   - Historias (una por imagen) -> publicarHistoria()
 *
 * Notas de robustez aprendidas en producción:
 * - El proceso es largo (normalización GD + subidas a Meta). MySQL cierra la
 *   conexión por inactividad ("server has gone away"). Por eso este servicio
 *   usa su PROPIA conexión PDO con ping/reconexión (self::db()).
 * - Las historias tienen rate limit; se publican con pausa entre cada una y
 *   reintentos con espera.
 *
 * Todas las llamadas a Meta usan graph.instagram.com (token IGAA...).
 */
class Instagram
{
    private static $apiBase = 'https://graph.instagram.com';

    // Dimensiones de salida.
    private static $feedLado = 1080;       // feed: 1:1
    private static $storyAncho = 1080;     // historia: 9:16
    private static $storyAlto = 1920;

    private static $umbralRefrescoDias = 10;
    private static $urlTtlSegundos = 600;

    // Historias: pausa entre cada una y reintentos del publish (rate limit).
    private static $pausaEntreHistorias = 5;   // segundos
    private static $reintentosPublish = 3;
    private static $esperaReintento = 6;        // segundos (se multiplica por intento)

    // Conexión propia (con reconexión).
    private static $pdo = null;

    // =====================================================================
    // CONEXIÓN PROPIA CON RECONEXIÓN (evita "MySQL server has gone away")
    // =====================================================================

    private static function db()
    {
        if (self::$pdo === null) {
            self::$pdo = self::nuevaConexion();
            return self::$pdo;
        }
        // Ping: si la conexión murió por inactividad, reconectar.
        try {
            self::$pdo->query('SELECT 1');
        } catch (Exception $e) {
            self::log('Reconectando a BD tras: ' . $e->getMessage());
            self::$pdo = self::nuevaConexion();
        }
        return self::$pdo;
    }

    private static function nuevaConexion()
    {
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, $opts);
        try {
            $pdo->exec("SET time_zone = '-05:00'");
        } catch (Exception $e) {
            // no crítico
        }
        return $pdo;
    }

    // =====================================================================
    // RUTAS DE FILE SYSTEM
    // =====================================================================

    private static function getBasePath($tenant)
    {
        return __DIR__ . '/../galeria_privada/' . $tenant . '/';
    }

    private static function getTmpPath($tenant)
    {
        return __DIR__ . '/../galeria_privada/tmp/' . $tenant . '/';
    }

    private static function log($mensaje)
    {
        error_log('[INSTAGRAM] ' . $mensaje);
    }

    // =====================================================================
    // ENDPOINTS PÚBLICOS (rutas con tenant + JWT)
    // =====================================================================

    public static function getEstado()
    {
        $config = self::obtenerConfig();

        if (!$config) {
            Flight::json([
                'configurado' => false,
                'mensaje' => 'No hay una cuenta de Instagram configurada para este tenant.'
            ]);
            return;
        }

        try {
            $config = self::refrescarSiNecesario($config);
        } catch (Exception $e) {
            self::log('Refresco perezoso (estado) falló: ' . $e->getMessage());
        }

        Flight::json([
            'configurado' => true,
            'ig_user_id' => $config['ig_user_id'],
            'token_expira_en' => $config['token_expira_en'],
            'dias_restantes' => self::diasRestantes($config['token_expira_en'])
        ]);
    }

    /**
     * Devuelve, por galería, qué imágenes se han publicado y en qué tipos.
     * Respuesta: { "<id_imagen>": ["feed","historia"], ... }
     */
    public static function imagenesPublicadas($idGaleria)
    {
        $db = self::db();
        $stmt = $db->prepare("
            SELECT DISTINCT id_imagen, tipo
            FROM instagram_publicacion_imagenes
            WHERE id_tenant = :id_tenant AND id_galeria = :id_galeria
        ");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':id_galeria', $idGaleria);
        $stmt->execute();
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mapa = [];
        foreach ($filas as $f) {
            $idImg = $f['id_imagen'];
            if (!isset($mapa[$idImg])) {
                $mapa[$idImg] = [];
            }
            if (!in_array($f['tipo'], $mapa[$idImg], true)) {
                $mapa[$idImg][] = $f['tipo'];
            }
        }
        Flight::json($mapa);
    }

    /**
     * Publica un carrusel (o imagen única) en el FEED.
     * Body (JSON): id_galeria, ids[] (1..10), caption
     */
    public static function publicar()
    {
        @set_time_limit(0);

        $data = Flight::request()->data;
        $idGaleria = isset($data['id_galeria']) ? $data['id_galeria'] : null;
        $ids = isset($data['ids']) ? $data['ids'] : [];
        $caption = isset($data['caption']) ? trim($data['caption']) : '';

        if (!$idGaleria || !is_array($ids) || count($ids) === 0) {
            Flight::json(['error' => 'Se requiere id_galeria y al menos una imagen.'], 400);
            return;
        }
        if (count($ids) > 10) {
            Flight::json(['error' => 'Instagram permite máximo 10 imágenes por carrusel.'], 400);
            return;
        }

        self::log('Inicio publicación FEED. galeria=' . $idGaleria . ' imagenes=' . count($ids));

        $ctx = self::prepararContexto($idGaleria, $ids);
        if (isset($ctx['error'])) {
            Flight::json(['error' => $ctx['error']], $ctx['code']);
            return;
        }
        $token = $ctx['token'];
        $igUserId = $ctx['ig_user_id'];
        $imagenes = $ctx['imagenes'];
        $tenant = $ctx['tenant'];

        $idPublicacion = Uuid::generar();
        self::registrarPublicacion($idPublicacion, $idGaleria, $caption, count($imagenes), 'feed', 'pendiente');
        self::registrarShutdown($idPublicacion);

        $temporales = [];
        try {
            foreach ($imagenes as $img) {
                $temporales[] = self::prepararTemporal($tenant, $img, self::$feedLado, self::$feedLado);
            }
            self::log('Imágenes normalizadas (feed): ' . count($temporales));

            if (count($temporales) === 1) {
                $creationId = self::crearContenedorImagen($igUserId, $token, $temporales[0]['url'], $caption, false, null);
            } else {
                $hijos = [];
                foreach ($temporales as $t) {
                    $hijos[] = self::crearContenedorImagen($igUserId, $token, $t['url'], '', true, null);
                }
                $creationId = self::crearContenedorCarrusel($igUserId, $token, $hijos, $caption);
            }

            self::esperarContenedorListo($creationId, $token);
            $mediaId = self::publicarConReintento($igUserId, $token, $creationId);
            $permalink = self::obtenerPermalink($mediaId, $token);

            // Reconecta si la BD se cayó durante las subidas, y marca publicada.
            self::actualizarPublicacion($idPublicacion, 'publicada', $creationId, $mediaId, null);
            foreach ($imagenes as $img) {
                self::registrarImagenPublicada($idPublicacion, $idGaleria, $img['id'], 'feed');
            }
            self::log('FEED publicado. media_id=' . $mediaId);

            Flight::json([
                'success' => true,
                'tipo' => 'feed',
                'media_id' => $mediaId,
                'permalink' => $permalink,
                'imagenes' => count($temporales)
            ]);
        } catch (Exception $e) {
            self::log('ERROR FEED id=' . $idPublicacion . ': ' . $e->getMessage());
            self::actualizarPublicacion($idPublicacion, 'error', null, null, $e->getMessage());
            Flight::json(['error' => 'No se pudo publicar: ' . $e->getMessage()], 500);
        } finally {
            self::limpiarTemporales($temporales);
        }
    }

    /**
     * Publica HISTORIAS: una por cada imagen seleccionada (sin tope de 10).
     * Body (JSON): id_galeria, ids[]
     */
    public static function publicarHistoria()
    {
        @set_time_limit(0);

        $data = Flight::request()->data;
        $idGaleria = isset($data['id_galeria']) ? $data['id_galeria'] : null;
        $ids = isset($data['ids']) ? $data['ids'] : [];

        if (!$idGaleria || !is_array($ids) || count($ids) === 0) {
            Flight::json(['error' => 'Se requiere id_galeria y al menos una imagen.'], 400);
            return;
        }

        self::log('Inicio publicación HISTORIA. galeria=' . $idGaleria . ' imagenes=' . count($ids));

        $ctx = self::prepararContexto($idGaleria, $ids);
        if (isset($ctx['error'])) {
            Flight::json(['error' => $ctx['error']], $ctx['code']);
            return;
        }
        $token = $ctx['token'];
        $igUserId = $ctx['ig_user_id'];
        $imagenes = $ctx['imagenes'];
        $tenant = $ctx['tenant'];

        $idPublicacion = Uuid::generar();
        self::registrarPublicacion($idPublicacion, $idGaleria, '', count($imagenes), 'historia', 'pendiente');
        self::registrarShutdown($idPublicacion);

        $temporales = [];
        $publicadas = [];
        try {
            $primera = true;
            foreach ($imagenes as $img) {
                // Pausa entre historias para no chocar con el rate limit de IG.
                if (!$primera) {
                    sleep(self::$pausaEntreHistorias);
                }
                $primera = false;

                $t = self::prepararTemporal($tenant, $img, self::$storyAncho, self::$storyAlto);
                $temporales[] = $t;

                $creationId = self::crearContenedorImagen($igUserId, $token, $t['url'], '', false, 'STORIES');
                self::esperarContenedorListo($creationId, $token);
                $mediaId = self::publicarConReintento($igUserId, $token, $creationId);

                $publicadas[] = $mediaId;
                self::registrarImagenPublicada($idPublicacion, $idGaleria, $img['id'], 'historia');
                self::log('Historia publicada. media_id=' . $mediaId);
            }

            $primerMediaId = count($publicadas) > 0 ? $publicadas[0] : null;
            self::actualizarPublicacion($idPublicacion, 'publicada', null, $primerMediaId, null);

            Flight::json([
                'success' => true,
                'tipo' => 'historia',
                'historias_publicadas' => count($publicadas),
                'media_ids' => $publicadas
            ]);
        } catch (Exception $e) {
            self::log('ERROR HISTORIA id=' . $idPublicacion . ': ' . $e->getMessage());
            $detalle = $e->getMessage();
            if (count($publicadas) > 0) {
                $detalle = 'Se publicaron ' . count($publicadas) . ' de ' . count($imagenes)
                    . ' historias antes del error: ' . $detalle;
            }
            $estadoFinal = count($publicadas) > 0 ? 'publicada' : 'error';
            self::actualizarPublicacion($idPublicacion, $estadoFinal, null,
                (count($publicadas) > 0 ? $publicadas[0] : null), $detalle);

            // Si publicó algunas, lo informamos como éxito parcial.
            if (count($publicadas) > 0) {
                Flight::json([
                    'success' => true,
                    'tipo' => 'historia',
                    'historias_publicadas' => count($publicadas),
                    'parcial' => true,
                    'detalle' => $detalle
                ]);
            } else {
                Flight::json(['error' => 'No se pudo completar: ' . $detalle], 500);
            }
        } finally {
            self::limpiarTemporales($temporales);
        }
    }

    public static function refrescarTokenManual()
    {
        $config = self::obtenerConfig();
        if (!$config) {
            Flight::json(['error' => 'No hay configuración de Instagram para este tenant.'], 400);
            return;
        }
        try {
            $config = self::refrescarToken($config);
            Flight::json([
                'success' => true,
                'token_expira_en' => $config['token_expira_en'],
                'dias_restantes' => self::diasRestantes($config['token_expira_en'])
            ]);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function registrarCorteAbrupto($idPublicacion, $err)
    {
        self::log('Corte abrupto id=' . $idPublicacion . ': ' . $err['message']
            . ' en ' . $err['file'] . ':' . $err['line']);
        try {
            self::marcarErrorSiPendiente(
                $idPublicacion,
                'Proceso interrumpido (posible timeout): ' . $err['message']
            );
        } catch (Exception $e) {
            self::log('No se pudo registrar el corte abrupto: ' . $e->getMessage());
        }
    }

    // =====================================================================
    // SERVIR IMAGEN TEMPORAL (ruta pública, sin JWT, validada por HMAC)
    // =====================================================================
    public static function servirTemporal($tenant, $file)
    {
        $tenant = preg_replace('/[^a-z0-9\-_]/i', '', $tenant);
        $file = basename($file);

        if (!preg_match('/^[a-f0-9]{32}\.jpg$/i', $file)) {
            Flight::halt(404, 'No encontrado');
            return;
        }

        $exp = isset($_GET['exp']) ? (int)$_GET['exp'] : 0;
        $sig = isset($_GET['sig']) ? $_GET['sig'] : '';

        if ($exp < time()) {
            Flight::halt(410, 'Enlace expirado');
            return;
        }

        $esperado = self::firmar($tenant . '|' . $file . '|' . $exp);
        if (!hash_equals($esperado, $sig)) {
            Flight::halt(403, 'Firma inválida');
            return;
        }

        $ruta = self::getTmpPath($tenant) . $file;
        if (!file_exists($ruta)) {
            Flight::halt(404, 'No encontrado');
            return;
        }

        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, no-store');
        readfile($ruta);
        exit;
    }

    // =====================================================================
    // CONTEXTO COMÚN
    // =====================================================================

    private static function prepararContexto($idGaleria, $ids)
    {
        $config = self::obtenerConfig();
        if (!$config) {
            return ['error' => 'No hay una cuenta de Instagram configurada para este tenant.', 'code' => 400];
        }
        try {
            $config = self::refrescarSiNecesario($config);
        } catch (Exception $e) {
            self::log('Refresco perezoso falló: ' . $e->getMessage());
        }

        $imagenes = self::cargarImagenesSeleccionadas($idGaleria, $ids);
        if (count($imagenes) === 0) {
            return ['error' => 'Las imágenes indicadas no pertenecen a la galería.', 'code' => 400];
        }

        return [
            'token' => $config['access_token'],
            'ig_user_id' => $config['ig_user_id'],
            'imagenes' => $imagenes,
            'tenant' => TenantContext::codigo()
        ];
    }

    private static function registrarShutdown($idPublicacion)
    {
        register_shutdown_function(function () use ($idPublicacion) {
            $err = error_get_last();
            if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Instagram::registrarCorteAbrupto($idPublicacion, $err);
            }
        });
    }

    private static function prepararTemporal($tenant, $img, $wDest, $hDest)
    {
        $origen = self::getBasePath($tenant) . str_replace(['../', '..\\', '..'], '', $img['url']);
        if (!file_exists($origen)) {
            throw new Exception('Archivo no encontrado: ' . $img['url']);
        }
        $nombreTmp = bin2hex(random_bytes(16)) . '.jpg';
        $rutaTmp = self::getTmpPath($tenant) . $nombreTmp;
        self::normalizarImagen($origen, $rutaTmp, $wDest, $hDest);

        return [
            'nombre' => $nombreTmp,
            'ruta' => $rutaTmp,
            'url' => self::urlTemporalFirmada($tenant, $nombreTmp)
        ];
    }

    private static function limpiarTemporales($temporales)
    {
        foreach ($temporales as $t) {
            if (isset($t['ruta']) && file_exists($t['ruta'])) {
                @unlink($t['ruta']);
            }
        }
    }

    // =====================================================================
    // CONFIG / TOKEN
    // =====================================================================

    private static function obtenerConfig()
    {
        $db = self::db();
        $stmt = $db->prepare("
            SELECT id, ig_user_id, access_token, token_expira_en
            FROM instagram_config
            WHERE id_tenant = :id_tenant AND activo = 1
            LIMIT 1
        ");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function refrescarSiNecesario($config)
    {
        $dias = self::diasRestantes($config['token_expira_en']);
        if ($dias !== null && $dias <= self::$umbralRefrescoDias) {
            self::log('Token con ' . $dias . ' días restantes; refrescando.');
            return self::refrescarToken($config);
        }
        return $config;
    }

    private static function refrescarToken($config)
    {
        $resp = self::httpGet(self::$apiBase . '/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $config['access_token']
        ]);

        if (!isset($resp['access_token']) || !isset($resp['expires_in'])) {
            $msg = isset($resp['error']['message']) ? $resp['error']['message'] : 'respuesta inesperada';
            throw new Exception('No se pudo refrescar el token: ' . $msg);
        }

        $nuevoToken = $resp['access_token'];
        $expiraEn = date('Y-m-d H:i:s', time() + (int)$resp['expires_in']);

        $db = self::db();
        $stmt = $db->prepare("
            UPDATE instagram_config
            SET access_token = :token, token_expira_en = :expira
            WHERE id_tenant = :id_tenant
        ");
        $stmt->bindValue(':token', $nuevoToken);
        $stmt->bindValue(':expira', $expiraEn);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();

        $config['access_token'] = $nuevoToken;
        $config['token_expira_en'] = $expiraEn;
        return $config;
    }

    private static function diasRestantes($fechaExpira)
    {
        if (empty($fechaExpira)) {
            return null;
        }
        $segundos = strtotime($fechaExpira) - time();
        return (int)floor($segundos / 86400);
    }

    // =====================================================================
    // IMÁGENES (BD)
    // =====================================================================

    private static function cargarImagenesSeleccionadas($idGaleria, $ids)
    {
        $db = self::db();

        $marcadores = [];
        $params = [
            ':id_galeria' => $idGaleria,
            ':id_tenant' => TenantContext::id()
        ];
        foreach (array_values($ids) as $i => $idImg) {
            $key = ':id' . $i;
            $marcadores[] = $key;
            $params[$key] = $idImg;
        }

        $sql = "
            SELECT id, guid, url, alt, orden
            FROM galeria_imagenes
            WHERE id_galeria = :id_galeria
            AND id_tenant = :id_tenant
            AND id IN (" . implode(',', $marcadores) . ")
            ORDER BY orden
        ";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            if ($k === ':id_tenant') {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v);
            }
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================================
    // PUBLICACIONES (BD - historial)
    // =====================================================================

    private static function registrarPublicacion($id, $idGaleria, $caption, $cantidad, $tipo, $estado)
    {
        $db = self::db();
        $stmt = $db->prepare("
            INSERT INTO instagram_publicaciones
                (id, id_tenant, id_galeria, caption, cantidad_imagenes, tipo, estado)
            VALUES
                (:id, :id_tenant, :id_galeria, :caption, :cantidad, :tipo, :estado)
        ");
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':id_galeria', $idGaleria);
        $stmt->bindValue(':caption', $caption);
        $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':estado', $estado);
        $stmt->execute();
    }

    private static function actualizarPublicacion($id, $estado, $containerId, $mediaId, $error)
    {
        $db = self::db();
        $stmt = $db->prepare("
            UPDATE instagram_publicaciones
            SET estado = :estado,
                ig_container_id = :container,
                ig_media_id = :media,
                error_detalle = :error
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $stmt->bindValue(':estado', $estado);
        $stmt->bindValue(':container', $containerId);
        $stmt->bindValue(':media', $mediaId);
        $stmt->bindValue(':error', $error);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
    }

    private static function registrarImagenPublicada($idPublicacion, $idGaleria, $idImagen, $tipo)
    {
        $db = self::db();
        $stmt = $db->prepare("
            INSERT INTO instagram_publicacion_imagenes
                (id, id_tenant, id_publicacion, id_galeria, id_imagen, tipo)
            VALUES
                (:id, :id_tenant, :id_publicacion, :id_galeria, :id_imagen, :tipo)
        ");
        $stmt->bindValue(':id', Uuid::generar());
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':id_publicacion', $idPublicacion);
        $stmt->bindValue(':id_galeria', $idGaleria);
        $stmt->bindValue(':id_imagen', $idImagen);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->execute();
    }

    private static function marcarErrorSiPendiente($id, $error)
    {
        $db = self::db();
        $stmt = $db->prepare("
            UPDATE instagram_publicaciones
            SET estado = 'error', error_detalle = :error
            WHERE id = :id AND id_tenant = :id_tenant AND estado = 'pendiente'
        ");
        $stmt->bindValue(':error', $error);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
    }

    // =====================================================================
    // LLAMADAS A LA GRAPH API (graph.instagram.com)
    // =====================================================================

    private static function crearContenedorImagen($igUserId, $token, $imageUrl, $caption, $esItemCarrusel, $mediaType)
    {
        $payload = [
            'image_url' => $imageUrl,
            'access_token' => $token
        ];
        if ($mediaType !== null) {
            $payload['media_type'] = $mediaType;
        }
        if ($esItemCarrusel) {
            $payload['is_carousel_item'] = 'true';
        }
        if ($caption !== '') {
            $payload['caption'] = $caption;
        }

        $resp = self::httpPost(self::$apiBase . '/' . $igUserId . '/media', $payload);
        if (!isset($resp['id'])) {
            throw new Exception(self::mensajeError($resp, 'crear contenedor de imagen'));
        }
        return $resp['id'];
    }

    private static function crearContenedorCarrusel($igUserId, $token, $hijos, $caption)
    {
        $payload = [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $hijos),
            'access_token' => $token
        ];
        if ($caption !== '') {
            $payload['caption'] = $caption;
        }

        $resp = self::httpPost(self::$apiBase . '/' . $igUserId . '/media', $payload);
        if (!isset($resp['id'])) {
            throw new Exception(self::mensajeError($resp, 'crear contenedor de carrusel'));
        }
        return $resp['id'];
    }

    private static function esperarContenedorListo($creationId, $token, $intentos = 8, $esperaSegundos = 2)
    {
        for ($i = 0; $i < $intentos; $i++) {
            $resp = self::httpGet(self::$apiBase . '/' . $creationId, [
                'fields' => 'status_code',
                'access_token' => $token
            ]);
            $estado = isset($resp['status_code']) ? $resp['status_code'] : null;

            if ($estado === 'FINISHED') {
                return true;
            }
            if ($estado === 'ERROR' || $estado === 'EXPIRED') {
                throw new Exception('El contenedor quedó en estado ' . $estado);
            }
            sleep($esperaSegundos);
        }
        return true;
    }

    /**
     * Publica el contenedor con reintentos. Instagram (sobre todo en historias)
     * devuelve errores transitorios por rate limit; reintentamos con espera.
     */
    private static function publicarConReintento($igUserId, $token, $creationId)
    {
        $ultimoError = null;
        for ($intento = 1; $intento <= self::$reintentosPublish; $intento++) {
            try {
                $resp = self::httpPost(self::$apiBase . '/' . $igUserId . '/media_publish', [
                    'creation_id' => $creationId,
                    'access_token' => $token
                ]);
                if (isset($resp['id'])) {
                    return $resp['id'];
                }
                $ultimoError = self::mensajeError($resp, 'publicar contenedor');
            } catch (Exception $e) {
                $ultimoError = $e->getMessage();
            }

            self::log('Reintento publish ' . $intento . '/' . self::$reintentosPublish . ': ' . $ultimoError);
            if ($intento < self::$reintentosPublish) {
                sleep(self::$esperaReintento * $intento);
            }
        }
        throw new Exception($ultimoError ?: 'No se pudo publicar el contenedor.');
    }

    private static function obtenerPermalink($mediaId, $token)
    {
        try {
            $resp = self::httpGet(self::$apiBase . '/' . $mediaId, [
                'fields' => 'permalink',
                'access_token' => $token
            ]);
            return isset($resp['permalink']) ? $resp['permalink'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private static function mensajeError($resp, $contexto)
    {
        if (isset($resp['error']['message'])) {
            return $contexto . ': ' . $resp['error']['message'];
        }
        return $contexto . ': respuesta inesperada de la API.';
    }

    // =====================================================================
    // HTTP (cURL)
    // =====================================================================

    private static function httpGet($url, $params)
    {
        $full = $url . '?' . http_build_query($params);
        $ch = curl_init($full);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception('Error de red: ' . $err);
        }
        curl_close($ch);
        $json = json_decode($body, true);
        return is_array($json) ? $json : [];
    }

    private static function httpPost($url, $params)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception('Error de red: ' . $err);
        }
        curl_close($ch);
        $json = json_decode($body, true);
        return is_array($json) ? $json : [];
    }

    // =====================================================================
    // URL TEMPORAL FIRMADA
    // =====================================================================

    private static function urlTemporalFirmada($tenant, $nombreArchivo)
    {
        $exp = time() + self::$urlTtlSegundos;
        $sig = self::firmar($tenant . '|' . $nombreArchivo . '|' . $exp);

        $base = self::baseUrlPublica();
        return $base . '/ig-media/' . rawurlencode($tenant) . '/' . rawurlencode($nombreArchivo)
            . '?exp=' . $exp . '&sig=' . $sig;
    }

    private static function firmar($payload)
    {
        if (!defined('IG_MEDIA_SIGN_SECRET')) {
            throw new Exception('Falta definir IG_MEDIA_SIGN_SECRET en master.env.php');
        }
        return hash_hmac('sha256', $payload, IG_MEDIA_SIGN_SECRET);
    }

    private static function baseUrlPublica()
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'api.genialisis.com';
        return $scheme . '://' . $host;
    }

    // =====================================================================
    // NORMALIZACIÓN DE IMAGEN (GD): fondo difuminado, foto completa sin recorte
    // =====================================================================

    private static function normalizarImagen($origen, $destino, $wDest, $hDest)
    {
        if (!extension_loaded('gd')) {
            throw new Exception('La extensión GD no está disponible en el servidor.');
        }

        $dir = dirname($destino);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $info = getimagesize($origen);
        if (!$info) {
            throw new Exception('Imagen ilegible: ' . basename($origen));
        }

        $src = self::crearDesdeArchivo($origen, $info['mime']);
        if (!$src) {
            throw new Exception('Formato no soportado: ' . basename($origen));
        }

        $anchoOrig = imagesx($src);
        $altoOrig = imagesy($src);

        $lienzo = imagecreatetruecolor($wDest, $hDest);

        // Fondo: la misma imagen escalada a "cover" y difuminada.
        $fondo = self::escalarCover($src, $anchoOrig, $altoOrig, $wDest, $hDest);
        for ($i = 0; $i < 12; $i++) {
            imagefilter($fondo, IMG_FILTER_GAUSSIAN_BLUR);
        }
        imagefilter($fondo, IMG_FILTER_BRIGHTNESS, -25);
        imagecopy($lienzo, $fondo, 0, 0, 0, 0, $wDest, $hDest);
        imagedestroy($fondo);

        // Primer plano: imagen completa "contain", centrada.
        $ratio = min($wDest / $anchoOrig, $hDest / $altoOrig);
        $nuevoAncho = max(1, (int)round($anchoOrig * $ratio));
        $nuevoAlto = max(1, (int)round($altoOrig * $ratio));
        $dstX = (int)(($wDest - $nuevoAncho) / 2);
        $dstY = (int)(($hDest - $nuevoAlto) / 2);

        imagecopyresampled(
            $lienzo, $src,
            $dstX, $dstY, 0, 0,
            $nuevoAncho, $nuevoAlto,
            $anchoOrig, $altoOrig
        );

        imagejpeg($lienzo, $destino, 88);

        imagedestroy($src);
        imagedestroy($lienzo);
    }

    private static function crearDesdeArchivo($ruta, $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
                return imagecreatefromjpeg($ruta);
            case 'image/png':
                return imagecreatefrompng($ruta);
            case 'image/gif':
                return imagecreatefromgif($ruta);
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($ruta) : false;
            default:
                return false;
        }
    }

    private static function escalarCover($src, $anchoOrig, $altoOrig, $wDest, $hDest)
    {
        $ratio = max($wDest / $anchoOrig, $hDest / $altoOrig);
        $escAncho = (int)ceil($anchoOrig * $ratio);
        $escAlto = (int)ceil($altoOrig * $ratio);

        $temp = imagecreatetruecolor($escAncho, $escAlto);
        imagecopyresampled($temp, $src, 0, 0, 0, 0, $escAncho, $escAlto, $anchoOrig, $altoOrig);

        $rect = imagecreatetruecolor($wDest, $hDest);
        $srcX = (int)(($escAncho - $wDest) / 2);
        $srcY = (int)(($escAlto - $hDest) / 2);
        imagecopy($rect, $temp, 0, 0, $srcX, $srcY, $wDest, $hDest);

        imagedestroy($temp);
        return $rect;
    }
}