<?php
class UploadHelper
{
    /**
     * Obtiene el tenant actual del header HTTP
     * @return string Tenant sanitizado o 'default'
     */
    public static function getTenant()
    {
        $tenant = isset($_SERVER['HTTP_X_TENANT']) ? $_SERVER['HTTP_X_TENANT'] : 'default';
        return preg_replace('/[^a-z0-9\-_]/i', '', $tenant);
    }

    /**
     * Genera la ruta base de uploads para el tenant actual
     * @param string $subfolder Subcarpeta específica (productos, fotos, documentos_personas)
     * @return string Ruta absoluta del directorio
     */
    public static function getUploadPath($subfolder = '')
    {
        $tenant = self::getTenant();
        $basePath = __DIR__ . '/../uploads/' . $tenant . '/';
        
        if (!empty($subfolder)) {
            $basePath .= $subfolder . '/';
        }
        
        return $basePath;
    }

    /**
     * Genera la ruta relativa para guardar en BD
     * @param string $subfolder Subcarpeta específica
     * @param string $filename Nombre del archivo
     * @return string Ruta relativa desde el root
     */
    public static function getRelativePath($subfolder, $filename)
    {
        $tenant = self::getTenant();
        return 'uploads/' . $tenant . '/' . $subfolder . '/' . $filename;
    }

    /**
     * Crea el directorio si no existe
     * @param string $path Ruta del directorio a crear
     * @return bool True si se creó o ya existe
     */
    public static function ensureDirectoryExists($path)
    {
        if (!file_exists($path)) {
            return mkdir($path, 0777, true);
        }
        return true;
    }

    /**
     * Elimina un archivo del sistema
     * @param string $relativePath Ruta relativa del archivo
     * @return bool True si se eliminó correctamente
     */
    public static function deleteFile($relativePath)
    {
        $fullPath = __DIR__ . '/../' . $relativePath;
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        
        return true; // No error si el archivo no existe
    }

    /**
     * Elimina un archivo por nombre (busca en tenant actual)
     * @param string $subfolder Subcarpeta donde buscar
     * @param string $filename Nombre del archivo
     * @return bool True si se eliminó correctamente
     */
    public static function deleteFileByName($subfolder, $filename)
    {
        $uploadDir = self::getUploadPath($subfolder);
        $filePath = $uploadDir . basename($filename);
        
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        
        return true;
    }

    /**
     * Obtiene la ruta completa de un archivo
     * @param string $relativePath Ruta relativa del archivo
     * @return string Ruta absoluta
     */
    public static function getFullPath($relativePath)
    {
        return __DIR__ . '/../' . $relativePath;
    }
}