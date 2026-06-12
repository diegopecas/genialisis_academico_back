<?php
class AuthMaster
{
    private static function getDbMaster()
    {
        static $db = null;
        if ($db === null) {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            $db = new PDO(DB_MASTER_DSN, DB_MASTER_USERNAME, DB_MASTER_PASSWORD, $options);
        }
        return $db;
    }

    public static function preLogin()
    {
        try {
            $usuario = Flight::request()->data['usuario'] ?? null;

            if (empty($usuario)) {
                Flight::json(['error' => true, 'message' => 'Usuario es requerido'], 400);
                return;
            }

            $db = self::getDbMaster();
            
            $sql = "SELECT t.id, t.codigo, t.nombre, t.logo_url
                    FROM usuarios_tenants ut
                    INNER JOIN tenants t ON ut.id_tenant = t.id
                    WHERE ut.usuario = :usuario
                    AND t.activo = 1
                    ORDER BY t.nombre";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':usuario', $usuario);
            $stmt->execute();
            $tenants = $stmt->fetchAll();

            Flight::json([
                'success' => true,
                'usuario' => $usuario,
                'tenants' => $tenants,
                'cantidad' => count($tenants)
            ]);

        } catch (Exception $e) {
            error_log("Error en AuthMaster::preLogin: " . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error interno del servidor'], 500);
        }
    }
}