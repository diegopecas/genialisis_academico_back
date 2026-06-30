<?php
class Upload
{
    private static $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private static $maxSize = 5242880; // 5MB

    // Videos permitidos para la galería (publicación como Reel sin reprocesar).
    private static $allowedVideoTypes = ['video/mp4', 'video/quicktime'];

    public static function uploadProductImage()
    {
        try {
            // Obtener directorio de uploads por tenant
            $uploadDir = UploadHelper::getUploadPath('productos');
            
            // Crear directorio si no existe
            UploadHelper::ensureDirectoryExists($uploadDir);

            // Verificar si se recibió archivo
            if (!isset($_FILES['imagen'])) {
                Flight::json(['error' => 'No se recibió ningún archivo'], 400);
                return;
            }

            $file = $_FILES['imagen'];

            // Verificar errores de upload (incluye exceso de tamaño a nivel PHP)
            if ($file['error'] !== UPLOAD_ERR_OK) {
                Flight::json(['error' => 'Error al cargar el archivo'], 400);
                return;
            }

            // Verificar tipo de archivo
            $fileType = mime_content_type($file['tmp_name']);
            if (!in_array($fileType, self::$allowedTypes)) {
                Flight::json(['error' => 'Tipo de archivo no permitido. Solo se permiten imágenes JPG, PNG, GIF y WEBP'], 400);
                return;
            }

            // Verificar tamaño
            if ($file['size'] > self::$maxSize) {
                Flight::json(['error' => 'El archivo excede el tamaño máximo permitido (5MB)'], 400);
                return;
            }

            // Obtener ID del producto si existe
            $idProducto = isset($_POST['id_producto']) ? $_POST['id_producto'] : 'temp';
            
            // Generar nombre único
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $timestamp = time();
            $fileName = 'producto_' . $idProducto . '_' . $timestamp . '.' . $extension;
            
            $uploadPath = $uploadDir . $fileName;

            // Mover archivo
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Retornar ruta relativa para guardar en BD
                $relativePath = UploadHelper::getRelativePath('productos', $fileName);
                
                Flight::json([
                    'success' => true,
                    'filename' => $fileName,
                    'path' => $relativePath
                ]);
            } else {
                Flight::json(['error' => 'Error al guardar el archivo'], 500);
            }

        } catch (Exception $e) {
            error_log("Error en uploadProductImage: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function deleteProductImage()
    {
        try {
            $fileName = Flight::request()->data['filename'];
            
            if (!$fileName) {
                Flight::json(['error' => 'Nombre de archivo no proporcionado'], 400);
                return;
            }

            // Usar helper para eliminar archivo del tenant actual
            if (UploadHelper::deleteFileByName('productos', $fileName)) {
                Flight::json(['success' => true]);
            } else {
                Flight::json(['error' => 'No se pudo eliminar el archivo'], 500);
            }

        } catch (Exception $e) {
            error_log("Error en deleteProductImage: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function getProductImage($filename)
    {
        try {
            $uploadDir = UploadHelper::getUploadPath('productos');
            $filePath = $uploadDir . basename($filename);
            
            if (!file_exists($filePath)) {
                Flight::response()->status(404);
                return;
            }

            $fileType = mime_content_type($filePath);
            
            Flight::response()->header('Content-Type', $fileType);
            Flight::response()->header('Content-Length', filesize($filePath));
            Flight::response()->header('Cache-Control', 'public, max-age=31536000');
            
            readfile($filePath);
            
        } catch (Exception $e) {
            error_log("Error en getProductImage: " . $e->getMessage());
            Flight::response()->status(500);
        }
    }

    /**
     * Lee el tamaño máximo de video (MB) desde configuracion_global.
     * Si no está configurado, usa un valor por defecto conservador.
     */
    private static function getMaxVideoBytes()
    {
        $defaultMb = 32;
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT valor_numero
                FROM configuracion_global
                WHERE clave = 'galeria_video_max_mb' AND id_tenant = :id_tenant
                LIMIT 1
            ");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['valor_numero'] !== null && (float)$row['valor_numero'] > 0) {
                $defaultMb = (float)$row['valor_numero'];
            }
        } catch (Exception $e) {
            error_log("getMaxVideoBytes: no se pudo leer config, uso default. " . $e->getMessage());
        }
        return (int)round($defaultMb * 1024 * 1024);
    }

    /**
     * Subir imagen O video de galería.
     * - Imágenes: JPG/PNG/GIF/WEBP, máx 10MB.
     * - Videos: MP4/MOV, máx galeria_video_max_mb (configuracion_global).
     * Devuelve además 'tipo_media' ('imagen' | 'video') para que el front lo guarde.
     */
    public static function uploadGaleriaImagen()
    {
        try {
            // Obtener tenant del header
            $tenant = isset($_SERVER['HTTP_X_TENANT']) ? $_SERVER['HTTP_X_TENANT'] : 'default';
            $tenant = preg_replace('/[^a-z0-9\-_]/i', '', $tenant);
            
            // Obtener ID de galería
            $idGaleria = isset($_POST['id_galeria']) ? $_POST['id_galeria'] : 'temp';
            
            // Directorio: galeria_privada/{tenant}/{id_galeria}/
            $baseDir = __DIR__ . '/../galeria_privada/' . $tenant . '/' . $idGaleria . '/';
            
            // Crear directorio si no existe
            if (!file_exists($baseDir)) {
                mkdir($baseDir, 0755, true);
            }

            // Si el POST superó post_max_size, PHP descarta el cuerpo entero:
            // $_FILES y $_POST llegan vacíos. Lo detectamos para dar un mensaje útil
            // en vez del genérico "No se recibió ningún archivo".
            $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
            if ($contentLength > 0 && empty($_FILES) && empty($_POST)) {
                $postMax = ini_get('post_max_size');
                Flight::json(['error' => 'El archivo supera el límite del servidor (post_max_size = ' . $postMax . '). Usa un archivo más pequeño o aumenta el límite del hosting.'], 400);
                return;
            }

            // Verificar si se recibió archivo
            if (!isset($_FILES['imagen'])) {
                Flight::json(['error' => 'No se recibió ningún archivo'], 400);
                return;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                    Flight::json(['error' => 'El archivo excede el tamaño permitido por el servidor. Revisa el límite del hosting o reduce el archivo.'], 400);
                } else {
                    Flight::json(['error' => 'Error al cargar el archivo'], 400);
                }
                return;
            }

            // Detectar tipo real del archivo
            $fileType = mime_content_type($file['tmp_name']);
            $esImagen = in_array($fileType, self::$allowedTypes);
            $esVideo = in_array($fileType, self::$allowedVideoTypes);

            if (!$esImagen && !$esVideo) {
                Flight::json(['error' => 'Tipo de archivo no permitido. Imágenes: JPG, PNG, GIF, WEBP. Videos: MP4, MOV.'], 400);
                return;
            }

            // Validar tamaño según tipo
            if ($esImagen) {
                $maxSizeGaleria = 10485760; // 10MB para imágenes
                if ($file['size'] > $maxSizeGaleria) {
                    Flight::json(['error' => 'La imagen excede el tamaño máximo permitido (10MB)'], 400);
                    return;
                }
                $tipoMedia = 'imagen';
            } else {
                $maxVideoBytes = self::getMaxVideoBytes();
                if ($file['size'] > $maxVideoBytes) {
                    $mb = round($maxVideoBytes / 1048576);
                    Flight::json(['error' => 'El video excede el tamaño máximo permitido (' . $mb . 'MB)'], 400);
                    return;
                }
                $tipoMedia = 'video';
            }
            
            // Generar nombre único (sin el id_galeria porque ya está en la carpeta)
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $timestamp = time();
            $random = substr(md5(uniqid()), 0, 8);
            $fileName = $timestamp . '_' . $random . '.' . $extension;
            
            $uploadPath = $baseDir . $fileName;

            // Mover archivo
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Retornar ruta relativa: {id_galeria}/{filename}
                $relativePath = $idGaleria . '/' . $fileName;
                
                Flight::json([
                    'success' => true,
                    'filename' => $fileName,
                    'ruta' => $relativePath,
                    'tipo_media' => $tipoMedia
                ]);
            } else {
                Flight::json(['error' => 'Error al guardar el archivo'], 500);
            }

        } catch (Exception $e) {
            error_log("Error en uploadGaleriaImagen: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}