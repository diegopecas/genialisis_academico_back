<?php
class GaleriaImagenes
{
    private static $mimeTypes = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp'
    ];

    // Calidad al reescribir un JPEG rotado.
    private static $jpegQuality = 90;

    private static $sizes = [
        'thumb' => [
            'width' => 300,
            'height' => 300,
            'quality' => 60
        ],
        'medium' => [
            'width' => 800,
            'height' => 800,
            'quality' => 80
        ],
        'large' => [
            'width' => 1200,
            'height' => 1200,
            'quality' => 85
        ]
    ];

    private static function getTenantFromRequest()
    {
        if (isset($_GET['tenant']) && !empty($_GET['tenant'])) {
            return preg_replace('/[^a-z0-9\-_]/i', '', $_GET['tenant']);
        }
        
        if (isset($_SERVER['HTTP_X_TENANT'])) {
            return preg_replace('/[^a-z0-9\-_]/i', '', $_SERVER['HTTP_X_TENANT']);
        }
        
        return 'default';
    }

    private static function getBasePath($tenant = null)
    {
        if ($tenant === null) {
            $tenant = self::getTenantFromRequest();
        }
        return __DIR__ . '/../galeria_privada/' . $tenant . '/';
    }

    private static function getCachePath($tenant = null)
    {
        if ($tenant === null) {
            $tenant = self::getTenantFromRequest();
        }
        return __DIR__ . '/../galeria_privada/cache/' . $tenant . '/';
    }

    private static function generarGUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Eliminar archivo físico y su caché
     */
    private static function eliminarArchivoFisico($url, $idImagen)
    {
        $basePath = self::getBasePath();
        $cachePath = self::getCachePath();
        
        // Limpiar la URL de caracteres peligrosos
        $url = str_replace(['../', '..\\', '..'], '', $url);
        
        // Eliminar archivo original
        $archivoOriginal = $basePath . $url;
        if (file_exists($archivoOriginal)) {
            unlink($archivoOriginal);
        }
        
        // Eliminar archivos de caché (thumb, medium, large)
        self::eliminarCacheImagen($idImagen);
    }

    /**
     * Borra las versiones redimensionadas de una imagen (thumb, medium, large).
     *
     * Se regeneran solas en el siguiente request. Es obligatorio llamarla cada
     * vez que cambian los píxeles del original: enviarArchivoRedimensionado()
     * reusa el archivo de caché si existe, sin comparar fechas, así que un
     * caché viejo se queda para siempre.
     */
    private static function eliminarCacheImagen($idImagen)
    {
        $cachePath = self::getCachePath();
        foreach (self::$sizes as $size => $config) {
            $cacheFile = $cachePath . $size . '/' . $idImagen . '_' . $size . '.jpg';
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        }
    }

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT gi.id, gi.guid, gi.id_galeria, gi.id_subgaleria, gi.url, gi.alt, gi.orden,
                   g.nombre as galeria_nombre,
                   s.nombre as subgaleria_nombre
            FROM galeria_imagenes gi
            INNER JOIN galerias g ON gi.id_galeria = g.id
            LEFT JOIN subgalerias s ON gi.id_subgaleria = s.id
            WHERE gi.id_tenant = :id_tenant
            ORDER BY gi.id_galeria, gi.id_subgaleria, gi.orden
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByGaleria($id_galeria)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, guid, id_galeria, id_subgaleria, url, tipo_media, alt, orden 
            FROM galeria_imagenes 
            WHERE id_galeria = :id_galeria 
            AND id_tenant = :id_tenant
            ORDER BY id_subgaleria, orden
        ");
        $sentence->bindParam(':id_galeria', $id_galeria);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getBySubgaleria($id_subgaleria)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, guid, id_galeria, id_subgaleria, url, alt, orden 
            FROM galeria_imagenes 
            WHERE id_subgaleria = :id_subgaleria 
            AND id_tenant = :id_tenant
            ORDER BY orden
        ");
        $sentence->bindParam(':id_subgaleria', $id_subgaleria);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getGeneralesByGaleria($id_galeria)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, guid, id_galeria, url, alt, orden 
            FROM galeria_imagenes 
            WHERE id_galeria = :id_galeria 
            AND id_subgaleria IS NULL 
            AND id_tenant = :id_tenant
            ORDER BY orden
        ");
        $sentence->bindParam(':id_galeria', $id_galeria);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, guid, id_galeria, id_subgaleria, url, alt, orden 
            FROM galeria_imagenes 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $guid = self::generarGUID();
        $id_galeria = isset($data['id_galeria']) ? $data['id_galeria'] : null;
        $id_subgaleria = isset($data['id_subgaleria']) && $data['id_subgaleria'] ? $data['id_subgaleria'] : null;
        $url = isset($data['url']) ? $data['url'] : '';
        $tipo_media = isset($data['tipo_media']) && $data['tipo_media'] ? $data['tipo_media'] : 'imagen';
        $alt = isset($data['alt']) ? $data['alt'] : '';
        $orden = isset($data['orden']) ? $data['orden'] : 0;
        
        $idNew = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO galeria_imagenes (id, id_tenant, guid, id_galeria, id_subgaleria, url, tipo_media, alt, orden) 
            VALUES (:id, :id_tenant, :guid, :id_galeria, :id_subgaleria, :url, :tipo_media, :alt, :orden)
        ");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':guid', $guid);
        $sentence->bindParam(':id_galeria', $id_galeria);
        $sentence->bindParam(':id_subgaleria', $id_subgaleria);
        $sentence->bindParam(':url', $url);
        $sentence->bindParam(':tipo_media', $tipo_media);
        $sentence->bindParam(':alt', $alt);
        $sentence->bindParam(':orden', $orden);
        $sentence->execute();
        
        $id = $idNew;
        Flight::json(['id' => $id, 'guid' => $guid]);
    }

    public static function newBulk()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        $imagenes = $data['imagenes'];
        
        $insertedIds = [];
        
        $sentence = $db->prepare("
            INSERT INTO galeria_imagenes (id, id_tenant, guid, id_galeria, id_subgaleria, url, alt, orden) 
            VALUES (:id, :id_tenant, :guid, :id_galeria, :id_subgaleria, :url, :alt, :orden)
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        
        foreach ($imagenes as $img) {
            $guid = self::generarGUID();
            $id_subgaleria = isset($img['id_subgaleria']) && $img['id_subgaleria'] ? $img['id_subgaleria'] : null;
            
            $idNew = Uuid::generar();
            $sentence->bindValue(':id', $idNew);
            $sentence->bindParam(':guid', $guid);
            $sentence->bindParam(':id_galeria', $img['id_galeria']);
            $sentence->bindParam(':id_subgaleria', $id_subgaleria);
            $sentence->bindParam(':url', $img['url']);
            $sentence->bindParam(':alt', $img['alt']);
            $sentence->bindParam(':orden', $img['orden']);
            $sentence->execute();
            
            $insertedIds[] = ['id' => $idNew, 'guid' => $guid];
        }
        
        Flight::json(['inserted' => count($insertedIds), 'items' => $insertedIds]);
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $id_subgaleria = isset($data['id_subgaleria']) && $data['id_subgaleria'] ? $data['id_subgaleria'] : null;
        
        $sentence = $db->prepare("
            UPDATE galeria_imagenes 
            SET id_galeria = :id_galeria, 
                id_subgaleria = :id_subgaleria, 
                url = :url, 
                alt = :alt, 
                orden = :orden 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $data['id']);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_galeria', $data['id_galeria']);
        $sentence->bindParam(':id_subgaleria', $id_subgaleria);
        $sentence->bindParam(':url', $data['url']);
        $sentence->bindParam(':alt', $data['alt']);
        $sentence->bindParam(':orden', $data['orden']);
        $sentence->execute();
        
        self::getById($data['id']);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        
        // 1. Obtener la información de la imagen antes de eliminar
        $stmt = $db->prepare("SELECT id, url FROM galeria_imagenes WHERE id = :id AND id_tenant = :id_tenant");
        $stmt->bindParam(':id', $id);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $imagen = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($imagen) {
            // 2. Eliminar archivo físico y caché
            self::eliminarArchivoFisico($imagen['url'], $imagen['id']);
            
            // 3. Eliminar registro de la BD
            $sentence = $db->prepare("DELETE FROM galeria_imagenes WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            
            Flight::json(['deleted' => true, 'id' => $id, 'archivo_eliminado' => true]);
        } else {
            Flight::json(['deleted' => false, 'id' => $id, 'error' => 'Imagen no encontrada']);
        }
    }

    /**
     * Rota las imágenes indicadas y borra su caché.
     *
     * POST /galeria-imagenes/rotar  { ids: [...], grados: 90|180|270 }
     *
     * Gira los píxeles del original en disco. No hay columna de rotación a
     * propósito: si el ángulo viviera en la BD habría que aplicarlo en todos
     * los consumidores (galería, portal de padres, normalización a Instagram) y
     * al que se le olvide muestra la foto torcida en silencio. Con el archivo
     * derecho, todos coinciden sin saber nada.
     *
     * Los videos se ignoran: GD no los procesa.
     */
    public static function rotar()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $ids = isset($data['ids']) ? $data['ids'] : [];
        $grados = isset($data['grados']) ? (int)$data['grados'] : 90;

        if (!is_array($ids) || count($ids) === 0) {
            Flight::json(['error' => 'No se recibieron imágenes para rotar'], 400);
            return;
        }

        if (!in_array($grados, [90, 180, 270], true)) {
            Flight::json(['error' => 'Los grados deben ser 90, 180 o 270'], 400);
            return;
        }

        if (!function_exists('imagerotate')) {
            Flight::json(['error' => 'La extensión GD no está disponible en el servidor'], 500);
            return;
        }

        // Traer solo las imágenes del tenant que existen y no son video.
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("
            SELECT id, url, tipo_media
            FROM galeria_imagenes
            WHERE id IN ({$placeholders}) AND id_tenant = ?
        ");
        $params = array_values($ids);
        $params[] = TenantContext::id();
        $stmt->execute($params);
        $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($imagenes) === 0) {
            Flight::json(['error' => 'No se encontraron las imágenes indicadas'], 404);
            return;
        }

        $rotadas = [];
        $errores = [];

        foreach ($imagenes as $imagen) {
            if ($imagen['tipo_media'] === 'video') {
                $errores[] = ['id' => $imagen['id'], 'error' => 'Los videos no se pueden rotar'];
                continue;
            }

            $resultado = self::rotarArchivo($imagen['url'], $grados);
            if ($resultado === true) {
                self::eliminarCacheImagen($imagen['id']);
                $rotadas[] = $imagen['id'];
            } else {
                $errores[] = ['id' => $imagen['id'], 'error' => $resultado];
            }
        }

        // Éxito parcial: se informa qué se rotó y qué no, sin ocultar los fallos.
        Flight::json([
            'success' => count($rotadas) > 0,
            'rotadas' => $rotadas,
            'total_rotadas' => count($rotadas),
            'errores' => $errores
        ]);
    }

    /**
     * Gira el archivo físico en disco y lo reescribe en su mismo formato.
     *
     * @param string $rutaRelativa
     * @param int    $grados  90, 180 o 270 en sentido horario
     * @return true|string  true si salió bien, o el mensaje de error
     */
    private static function rotarArchivo($rutaRelativa, $grados)
    {
        $rutaRelativa = str_replace(['../', '..\\', '..'], '', $rutaRelativa);
        $rutaCompleta = self::getBasePath() . $rutaRelativa;

        if (!file_exists($rutaCompleta)) {
            return 'Archivo no encontrado';
        }

        $info = @getimagesize($rutaCompleta);
        if (!$info) {
            return 'El archivo no es una imagen válida';
        }

        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                $img = @imagecreatefromjpeg($rutaCompleta);
                break;
            case IMAGETYPE_PNG:
                $img = @imagecreatefrompng($rutaCompleta);
                break;
            case IMAGETYPE_GIF:
                $img = @imagecreatefromgif($rutaCompleta);
                break;
            case IMAGETYPE_WEBP:
                $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($rutaCompleta) : false;
                break;
            default:
                return 'Formato de imagen no soportado';
        }

        if (!$img) {
            return 'No se pudo abrir la imagen';
        }

        // imagerotate gira antihorario; los grados que llegan son horarios.
        $rotada = @imagerotate($img, -$grados, 0);
        if (!$rotada) {
            imagedestroy($img);
            return 'No se pudo rotar la imagen';
        }

        // PNG, GIF y WEBP pueden traer transparencia: al rotar aparecen zonas
        // nuevas que hay que dejar transparentes en vez de negras.
        if (in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            imagealphablending($rotada, false);
            imagesavealpha($rotada, true);
        }

        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                $ok = imagejpeg($rotada, $rutaCompleta, self::$jpegQuality);
                break;
            case IMAGETYPE_PNG:
                $ok = imagepng($rotada, $rutaCompleta);
                break;
            case IMAGETYPE_GIF:
                $ok = imagegif($rotada, $rutaCompleta);
                break;
            case IMAGETYPE_WEBP:
                $ok = imagewebp($rotada, $rutaCompleta, self::$jpegQuality);
                break;
            default:
                $ok = false;
        }

        imagedestroy($img);
        imagedestroy($rotada);

        if (!$ok) {
            return 'No se pudo guardar la imagen rotada';
        }

        // El ETag de enviarArchivo() se calcula con filemtime, así que al
        // reescribir el archivo cambia solo y el navegador revalida.
        clearstatcache(true, $rutaCompleta);
        return true;
    }

    public static function deleteByGaleria($id_galeria)
    {
        $db = Flight::db();
        
        // 1. Obtener todas las imágenes de la galería
        $stmt = $db->prepare("SELECT id, url FROM galeria_imagenes WHERE id_galeria = :id_galeria AND id_tenant = :id_tenant");
        $stmt->bindParam(':id_galeria', $id_galeria);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. Eliminar archivos físicos y caché de cada imagen
        foreach ($imagenes as $imagen) {
            self::eliminarArchivoFisico($imagen['url'], $imagen['id']);
        }
        
        // 3. Eliminar registros de la BD
        $sentence = $db->prepare("DELETE FROM galeria_imagenes WHERE id_galeria = :id_galeria AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_galeria', $id_galeria);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        
        Flight::json([
            'deleted' => true, 
            'id_galeria' => $id_galeria, 
            'imagenes_eliminadas' => count($imagenes)
        ]);
    }

    public static function deleteBySubgaleria($id_subgaleria)
    {
        $db = Flight::db();
        
        // 1. Obtener todas las imágenes de la subgalería
        $stmt = $db->prepare("SELECT id, url FROM galeria_imagenes WHERE id_subgaleria = :id_subgaleria AND id_tenant = :id_tenant");
        $stmt->bindParam(':id_subgaleria', $id_subgaleria);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. Eliminar archivos físicos y caché de cada imagen
        foreach ($imagenes as $imagen) {
            self::eliminarArchivoFisico($imagen['url'], $imagen['id']);
        }
        
        // 3. Eliminar registros de la BD
        $sentence = $db->prepare("DELETE FROM galeria_imagenes WHERE id_subgaleria = :id_subgaleria AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_subgaleria', $id_subgaleria);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        
        Flight::json([
            'deleted' => true, 
            'id_subgaleria' => $id_subgaleria,
            'imagenes_eliminadas' => count($imagenes)
        ]);
    }

    // =====================================================
    // SERVIR IMÁGENES PROTEGIDAS - POR GUID
    // =====================================================

    /**
     * Emite un token efímero para ver imagenes del tenant.
     * GET /galeria-imagenes/token
     *
     * La sesion ya fue validada por el hook central. El token se emite con
     * alcance de tenant (no por imagen) porque las URLs de <img src=""> se
     * arman de forma sincrona en el front: pedir un token por imagen
     * significaria una peticion por cada miniatura de la galeria.
     *
     * Alcance del token: solo sirve para servir imagenes de este tenant y
     * caduca en pocos minutos, a diferencia del token de sesion que daba
     * acceso a todo el sistema durante 24 horas.
     */
    public static function generarTokenImagenes()
    {
        try {
            $token = JWTService::generarTokenRecurso(
                'imagen',
                null,
                self::getTenantFromRequest()
            );

            Flight::json(array(
                'token'      => $token,
                'expira_en'  => JWTService::getExpiracionTokenRecurso()
            ));
        } catch (Exception $e) {
            error_log("Error en GaleriaImagenes::generarTokenImagenes: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Servir imagen protegida por GUID
     * GET /galeria-imagenes/servir/{guid}?token=xxx&tenant=xxx&size=thumb
     */
    public static function servirImagen($guid)
    {
        // 1. Autenticacion. Se aceptan dos formas:
        //    - ?token= : token efímero de imagenes (necesario en <img src="">,
        //      que no puede enviar el header Authorization).
        //    - sin ?token= : token de sesion por header (descarga blob via
        //      HttpClient, donde el token no necesita ir en la URL).
        if (isset($_GET['token'])) {
            JWTService::requerirTokenRecurso('imagen', $guid, self::getTenantFromRequest());
        } else {
            JWTService::requerirAutenticacion();
        }
        
        $db = Flight::db();
        
        // 2. Obtener la imagen por GUID
        $stmt = $db->prepare("
            SELECT gi.id, gi.guid, gi.url, gi.alt
            FROM galeria_imagenes gi
            INNER JOIN galerias g ON gi.id_galeria = g.id
            WHERE gi.guid = :guid AND g.activo = 1 AND gi.id_tenant = :id_tenant
        ");
        $stmt->bindParam(':guid', $guid, PDO::PARAM_STR);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $imagen = $stmt->fetch(PDO::FETCH_OBJ);
        
        if (!$imagen) {
            Flight::halt(404, json_encode(['error' => 'Imagen no encontrada']));
            return;
        }
        
        // 3. Obtener el tamaño solicitado
        $size = isset($_GET['size']) ? $_GET['size'] : 'original';
        
        // 4. Servir el archivo
        if ($size === 'original' || !isset(self::$sizes[$size])) {
            self::enviarArchivo($imagen->url);
        } else {
            self::enviarArchivoRedimensionado($imagen->id, $imagen->url, $size);
        }
    }

    private static function enviarArchivo($rutaRelativa)
    {
        $rutaRelativa = str_replace(['../', '..\\', '..'], '', $rutaRelativa);
        $rutaCompleta = self::getBasePath() . $rutaRelativa;
        
        if (!file_exists($rutaCompleta)) {
            Flight::halt(404, json_encode(['error' => 'Archivo no encontrado']));
            return;
        }
        
        $extension = strtolower(pathinfo($rutaCompleta, PATHINFO_EXTENSION));
        
        if (!isset(self::$mimeTypes[$extension])) {
            Flight::halt(400, json_encode(['error' => 'Tipo de archivo no permitido']));
            return;
        }
        
        $mimeType = self::$mimeTypes[$extension];
        $fileSize = filesize($rutaCompleta);
        $lastModified = filemtime($rutaCompleta);
        $etag = md5($rutaCompleta . $lastModified);
        
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: private, max-age=86400');
        header('ETag: "' . $etag . '"');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $clientEtag = trim($_SERVER['HTTP_IF_NONE_MATCH'], '"');
            if ($clientEtag === $etag) {
                http_response_code(304);
                exit;
            }
        }
        
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $clientModified = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            if ($clientModified >= $lastModified) {
                http_response_code(304);
                exit;
            }
        }
        
        readfile($rutaCompleta);
        exit;
    }

    private static function enviarArchivoRedimensionado($idImagen, $rutaRelativa, $size)
    {
        $rutaRelativa = str_replace(['../', '..\\', '..'], '', $rutaRelativa);
        $rutaOriginal = self::getBasePath() . $rutaRelativa;
        
        if (!file_exists($rutaOriginal)) {
            Flight::halt(404, json_encode(['error' => 'Archivo no encontrado']));
            return;
        }
        
        $extension = strtolower(pathinfo($rutaOriginal, PATHINFO_EXTENSION));
        
        if (!isset(self::$mimeTypes[$extension])) {
            Flight::halt(400, json_encode(['error' => 'Tipo de archivo no permitido']));
            return;
        }
        
        $cacheDir = self::getCachePath() . $size . '/';
        $cacheFile = $cacheDir . $idImagen . '_' . $size . '.jpg';
        
        if (!file_exists($cacheFile)) {
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            
            $generated = self::generarThumbnail(
                $rutaOriginal,
                $cacheFile,
                self::$sizes[$size]['width'],
                self::$sizes[$size]['height'],
                self::$sizes[$size]['quality']
            );
            
            if (!$generated) {
                self::enviarArchivo($rutaRelativa);
                return;
            }
        }
        
        $mimeType = 'image/jpeg';
        $fileSize = filesize($cacheFile);
        $lastModified = filemtime($cacheFile);
        $etag = md5($cacheFile . $lastModified);
        
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: private, max-age=604800');
        header('ETag: "' . $etag . '"');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $clientEtag = trim($_SERVER['HTTP_IF_NONE_MATCH'], '"');
            if ($clientEtag === $etag) {
                http_response_code(304);
                exit;
            }
        }
        
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $clientModified = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            if ($clientModified >= $lastModified) {
                http_response_code(304);
                exit;
            }
        }
        
        readfile($cacheFile);
        exit;
    }

    private static function generarThumbnail($rutaOriginal, $rutaDestino, $maxWidth, $maxHeight, $quality)
    {
        if (!extension_loaded('gd')) {
            return false;
        }
        
        $imageInfo = getimagesize($rutaOriginal);
        if (!$imageInfo) {
            return false;
        }
        
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($rutaOriginal);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($rutaOriginal);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($rutaOriginal);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($rutaOriginal);
                break;
            default:
                return false;
        }
        
        if (!$sourceImage) {
            return false;
        }
        
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        
        if ($ratio < 1) {
            $newWidth = (int)($originalWidth * $ratio);
            $newHeight = (int)($originalHeight * $ratio);
        } else {
            $newWidth = $originalWidth;
            $newHeight = $originalHeight;
        }
        
        $destImage = imagecreatetruecolor($newWidth, $newHeight);
        
        if ($mimeType === 'image/png') {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
            $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
            imagefilledrectangle($destImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        imagecopyresampled(
            $destImage,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );
        
        $result = imagejpeg($destImage, $rutaDestino, $quality);
        
        imagedestroy($sourceImage);
        imagedestroy($destImage);
        
        return $result;
    }

    public static function limpiarCache()
    {
        $cacheDir = self::getCachePath();
        
        if (!is_dir($cacheDir)) {
            Flight::json(['message' => 'No hay caché que limpiar']);
            return;
        }
        
        $count = 0;
        
        foreach (self::$sizes as $size => $config) {
            $sizeDir = $cacheDir . $size . '/';
            if (is_dir($sizeDir)) {
                $files = glob($sizeDir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                        $count++;
                    }
                }
            }
        }
        
        Flight::json(['message' => "Se eliminaron $count archivos de caché"]);
    }
    /**
     * Eliminar múltiples imágenes a la vez
     * Recibe: { "ids": [1, 2, 3, ...] }
     */
    public static function deleteBulk()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        if (!isset($data['ids']) || !is_array($data['ids']) || count($data['ids']) === 0) {
            Flight::json(['deleted' => false, 'error' => 'No se proporcionaron IDs válidos'], 400);
            return;
        }
        
        $ids = $data['ids']; // IDs UUID: el binding parametrizado evita inyeccion
        $eliminados = 0;
        $errores = [];
        
        foreach ($ids as $id) {
            // 1. Obtener la información de la imagen
            $stmt = $db->prepare("SELECT id, url FROM galeria_imagenes WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $imagen = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($imagen) {
                // 2. Eliminar archivo físico y caché
                self::eliminarArchivoFisico($imagen['url'], $imagen['id']);
                
                // 3. Eliminar registro de la BD
                $sentence = $db->prepare("DELETE FROM galeria_imagenes WHERE id = :id AND id_tenant = :id_tenant");
                $sentence->bindParam(':id', $id);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();
                
                $eliminados++;
            } else {
                $errores[] = $id;
            }
        }
        
        Flight::json([
            'deleted' => true,
            'total_solicitados' => count($ids),
            'eliminados' => $eliminados,
            'errores' => $errores
        ]);
    }
}