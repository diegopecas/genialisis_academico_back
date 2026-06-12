<?php
class Upload
{
    private static $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private static $maxSize = 5242880; // 5MB

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

            // Verificar errores de upload
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
     * Subir imagen de galería
     */
    public static function uploadGaleriaImagen()
    {
        try {
            // Obtener tenant del header
            $tenant = isset($_SERVER['HTTP_X_TENANT']) ? $_SERVER['HTTP_X_TENANT'] : 'default';
            
            // Obtener ID de galería
            $idGaleria = isset($_POST['id_galeria']) ? $_POST['id_galeria'] : 'temp';
            
            // Directorio: galeria_privada/{tenant}/{id_galeria}/
            $baseDir = __DIR__ . '/../galeria_privada/' . $tenant . '/' . $idGaleria . '/';
            
            // Crear directorio si no existe
            if (!file_exists($baseDir)) {
                mkdir($baseDir, 0755, true);
            }

            // Verificar si se recibió archivo
            if (!isset($_FILES['imagen'])) {
                Flight::json(['error' => 'No se recibió ningún archivo'], 400);
                return;
            }

            $file = $_FILES['imagen'];

            // Verificar errores de upload
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

            // Verificar tamaño (10MB para galerías)
            $maxSizeGaleria = 10485760; // 10MB
            if ($file['size'] > $maxSizeGaleria) {
                Flight::json(['error' => 'El archivo excede el tamaño máximo permitido (10MB)'], 400);
                return;
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
                    'ruta' => $relativePath
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