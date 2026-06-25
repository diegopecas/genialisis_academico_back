<?php

/**
 * Instagram
 *
 * Integración con la API de Instagram (Instagram Login) para publicar
 * imágenes de una galería como carrusel en la cuenta del tenant.
 *
 * - Token de larga duración (60 días) almacenado en la tabla instagram_config.
 * - Refresco PEREZOSO: se renueva cuando le quedan pocos días, sin cron.
 * - Las imágenes privadas se normalizan a 1080x1080 (fondo difuminado, sin
 *   recortar caras), se dejan en una carpeta temporal y se exponen vía una
 *   URL pública firmada (HMAC + expiración) que Meta descarga. Se borran al
 *   terminar.
 *
 * Todas las llamadas usan el host graph.instagram.com (path del token IGAA...).
 */
class Instagram
{
    // Host de la API para tokens de Instagram Login.
    private static $apiBase = 'https://graph.instagram.com';

    // Lado del lienzo cuadrado de salida (px).
    private static $lienzo = 1080;

    // Días restantes a partir de los cuales se intenta refrescar el token.
    private static $umbralRefrescoDias = 10;

    // Validez (segundos) de la URL temporal firmada que se entrega a Meta.
    private static $urlTtlSegundos = 600;

    // =====================================================================
    // RUTAS DE FILE SYSTEM (mismo patrón que GaleriaImagenes)
    // =====================================================================

    private static function getBasePath($tenant)
    {
        return __DIR__ . '/../galeria_privada/' . $tenant . '/';
    }

    private static function getTmpPath($tenant)
    {
        return __DIR__ . '/../galeria_privada/tmp/' . $tenant . '/';
    }

    // =====================================================================
    // ENDPOINTS PÚBLICOS (rutas con tenant + JWT)
    // =====================================================================

    /**
     * Estado de la conexión de Instagram del tenant.
     * Aprovecha para refrescar el token de forma perezosa si está por vencer.
     */
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

        // Refresco perezoso (best-effort; si falla, no rompe el estado).
        try {
            $config = self::refrescarSiNecesario($config);
        } catch (Exception $e) {
            error_log('Instagram refresco perezoso (estado): ' . $e->getMessage());
        }

        $diasRestantes = self::diasRestantes($config['token_expira_en']);

        Flight::json([
            'configurado' => true,
            'ig_user_id' => $config['ig_user_id'],
            'token_expira_en' => $config['token_expira_en'],
            'dias_restantes' => $diasRestantes
        ]);
    }

    /**
     * Publica un carrusel (o imagen única) en Instagram a partir de imágenes
     * seleccionadas de una galería.
     *
     * Body esperado (JSON):
     *   id_galeria : string (UUID de la galería)
     *   ids        : string[] (UUID de las imágenes, 1..10)
     *   caption    : string (texto del post; por defecto la descripción)
     */
    public static function publicar()
    {
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

        // 1. Configuración + token vigente.
        $config = self::obtenerConfig();
        if (!$config) {
            Flight::json(['error' => 'No hay una cuenta de Instagram configurada para este tenant.'], 400);
            return;
        }
        try {
            $config = self::refrescarSiNecesario($config);
        } catch (Exception $e) {
            error_log('Instagram refresco perezoso (publicar): ' . $e->getMessage());
        }
        $token = $config['access_token'];
        $igUserId = $config['ig_user_id'];

        // 2. Cargar imágenes seleccionadas (validando galería + tenant).
        $imagenes = self::cargarImagenesSeleccionadas($idGaleria, $ids);
        if (count($imagenes) === 0) {
            Flight::json(['error' => 'Las imágenes indicadas no pertenecen a la galería.'], 400);
            return;
        }

        $tenant = TenantContext::codigo();
        $idPublicacion = Uuid::generar();
        self::registrarPublicacion($idPublicacion, $idGaleria, $caption, count($imagenes), 'pendiente');

        $temporales = [];
        try {
            // 3. Normalizar cada imagen a JPEG 1080x1080 con fondo difuminado.
            foreach ($imagenes as $img) {
                $origen = self::getBasePath($tenant) . str_replace(['../', '..\\', '..'], '', $img['url']);
                if (!file_exists($origen)) {
                    throw new Exception('Archivo no encontrado: ' . $img['url']);
                }

                $nombreTmp = bin2hex(random_bytes(16)) . '.jpg';
                $rutaTmp = self::getTmpPath($tenant) . $nombreTmp;
                self::normalizarImagenJpeg($origen, $rutaTmp);

                $temporales[] = [
                    'nombre' => $nombreTmp,
                    'ruta' => $rutaTmp,
                    'url' => self::urlTemporalFirmada($tenant, $nombreTmp)
                ];
            }

            // 4. Publicar (carrusel si hay 2+, imagen única si hay 1).
            if (count($temporales) === 1) {
                $creationId = self::crearContenedorImagen($igUserId, $token, $temporales[0]['url'], $caption, false);
            } else {
                $hijos = [];
                foreach ($temporales as $t) {
                    $hijos[] = self::crearContenedorImagen($igUserId, $token, $t['url'], '', true);
                }
                $creationId = self::crearContenedorCarrusel($igUserId, $token, $hijos, $caption);
            }

            // 5. Esperar a que el contenedor esté FINISHED y publicar.
            self::esperarContenedorListo($creationId, $token);
            $mediaId = self::publicarContenedor($igUserId, $token, $creationId);

            // 6. Permalink (best-effort) y registro de éxito.
            $permalink = self::obtenerPermalink($mediaId, $token);
            self::actualizarPublicacion($idPublicacion, 'publicada', $creationId, $mediaId, null);

            Flight::json([
                'success' => true,
                'media_id' => $mediaId,
                'permalink' => $permalink,
                'imagenes' => count($temporales)
            ]);
        } catch (Exception $e) {
            self::actualizarPublicacion($idPublicacion, 'error', null, null, $e->getMessage());
            Flight::json(['error' => 'No se pudo publicar: ' . $e->getMessage()], 500);
        } finally {
            // 7. Borrar temporales SIEMPRE (privacidad de los niños).
            foreach ($temporales as $t) {
                if (isset($t['ruta']) && file_exists($t['ruta'])) {
                    @unlink($t['ruta']);
                }
            }
        }
    }

    /**
     * Refresco manual del token (por si se quiere forzar o agendar un cron).
     */
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

    // =====================================================================
    // SERVIR IMAGEN TEMPORAL (ruta pública, sin JWT, validada por HMAC)
    // Llamada desde el bloque público de index.php: /ig-media/{tenant}/{file}
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
    // CONFIG / TOKEN
    // =====================================================================

    private static function obtenerConfig()
    {
        $db = Flight::db();
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

        $db = Flight::db();
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
        $db = Flight::db();

        // Placeholders parametrizados para el IN (evita inyección).
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

    private static function registrarPublicacion($id, $idGaleria, $caption, $cantidad, $estado)
    {
        $db = Flight::db();
        $stmt = $db->prepare("
            INSERT INTO instagram_publicaciones
                (id, id_tenant, id_galeria, caption, cantidad_imagenes, estado)
            VALUES
                (:id, :id_tenant, :id_galeria, :caption, :cantidad, :estado)
        ");
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':id_galeria', $idGaleria);
        $stmt->bindValue(':caption', $caption);
        $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $estado);
        $stmt->execute();
    }

    private static function actualizarPublicacion($id, $estado, $containerId, $mediaId, $error)
    {
        $db = Flight::db();
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

    // =====================================================================
    // LLAMADAS A LA GRAPH API (graph.instagram.com)
    // =====================================================================

    private static function crearContenedorImagen($igUserId, $token, $imageUrl, $caption, $esItemCarrusel)
    {
        $payload = [
            'image_url' => $imageUrl,
            'access_token' => $token
        ];
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
        // Para imágenes suele estar listo de inmediato; si no, intentamos publicar igual.
        return true;
    }

    private static function publicarContenedor($igUserId, $token, $creationId)
    {
        $resp = self::httpPost(self::$apiBase . '/' . $igUserId . '/media_publish', [
            'creation_id' => $creationId,
            'access_token' => $token
        ]);
        if (!isset($resp['id'])) {
            throw new Exception(self::mensajeError($resp, 'publicar contenedor'));
        }
        return $resp['id'];
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
    // NORMALIZACIÓN DE IMAGEN (GD): 1080x1080, fondo difuminado, sin recortar
    // =====================================================================

    private static function normalizarImagenJpeg($origen, $destino)
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
        $lado = self::$lienzo;

        $lienzo = imagecreatetruecolor($lado, $lado);

        // --- Fondo: la misma imagen escalada a "cover" y difuminada ---
        $fondo = self::escalarCover($src, $anchoOrig, $altoOrig, $lado);
        for ($i = 0; $i < 12; $i++) {
            imagefilter($fondo, IMG_FILTER_GAUSSIAN_BLUR);
        }
        // Oscurecer un poco el fondo para que destaque la foto.
        imagefilter($fondo, IMG_FILTER_BRIGHTNESS, -25);
        imagecopy($lienzo, $fondo, 0, 0, 0, 0, $lado, $lado);
        imagedestroy($fondo);

        // --- Primer plano: imagen completa "contain", centrada ---
        $ratio = min($lado / $anchoOrig, $lado / $altoOrig);
        $nuevoAncho = max(1, (int)round($anchoOrig * $ratio));
        $nuevoAlto = max(1, (int)round($altoOrig * $ratio));
        $dstX = (int)(($lado - $nuevoAncho) / 2);
        $dstY = (int)(($lado - $nuevoAlto) / 2);

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

    /**
     * Escala la imagen para cubrir un cuadrado de $lado (recortando lo que
     * sobra). Se usa solo para el FONDO difuminado, no para la foto visible.
     */
    private static function escalarCover($src, $anchoOrig, $altoOrig, $lado)
    {
        $ratio = max($lado / $anchoOrig, $lado / $altoOrig);
        $escAncho = (int)ceil($anchoOrig * $ratio);
        $escAlto = (int)ceil($altoOrig * $ratio);

        $temp = imagecreatetruecolor($escAncho, $escAlto);
        imagecopyresampled($temp, $src, 0, 0, 0, 0, $escAncho, $escAlto, $anchoOrig, $altoOrig);

        $cuadrado = imagecreatetruecolor($lado, $lado);
        $srcX = (int)(($escAncho - $lado) / 2);
        $srcY = (int)(($escAlto - $lado) / 2);
        imagecopy($cuadrado, $temp, 0, 0, $srcX, $srcY, $lado, $lado);

        imagedestroy($temp);
        return $cuadrado;
    }
}