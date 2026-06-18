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
            SELECT id, guid, id_galeria, id_subgaleria, url, alt, orden 
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
        $alt = isset($data['alt']) ? $data['alt'] : '';
        $orden = isset($data['orden']) ? $data['orden'] : 0;
        
        $idNew = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO galeria_imagenes (id, id_tenant, guid, id_galeria, id_subgaleria, url, alt, orden) 
            VALUES (:id, :id_tenant, :guid, :id_galeria, :id_subgaleria, :url, :alt, :orden)
        ");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':guid', $guid);
        $sentence->bindParam(':id_galeria', $id_galeria);
        $sentence->bindParam(':id_subgaleria', $id_subgaleria);
        $sentence->bindParam(':url', $url);
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
     * Servir imagen protegida por GUID
     * GET /galeria-imagenes/servir/{guid}?token=xxx&tenant=xxx&size=thumb
     */
    public static function servirImagen($guid)
    {
        // 1. Validar JWT (solo que el token sea válido)
        JWTService::requerirAutenticacion();
        
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