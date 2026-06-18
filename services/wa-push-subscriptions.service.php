<?php
class WaPushSubscriptions
{
    // =============================================
    // REGISTRAR SUSCRIPCIÓN PUSH
    // =============================================
    public static function registrar()
    {
        try {
            $db = Flight::db();
            $data = self::getData();

            $idUsuario = $data['id_usuario'] ?? null;
            $endpoint  = $data['endpoint'] ?? null;
            $p256dh    = $data['p256dh'] ?? null;
            $auth      = $data['auth'] ?? null;

            if (!$idUsuario || !$endpoint || !$p256dh || !$auth) {
                Flight::json(['error' => 'Faltan campos requeridos'], 400);
                return;
            }

            // Upsert: si el endpoint ya existe, actualizar; si no, insertar
            $stmt = $db->prepare("
                INSERT INTO wa_push_subscriptions (id, id_tenant, id_usuario, endpoint, p256dh, auth, activo)
                VALUES (:id, :id_tenant, :id_usuario, :endpoint, :p256dh, :auth, 1)
                ON DUPLICATE KEY UPDATE
                    id_usuario = VALUES(id_usuario),
                    p256dh = VALUES(p256dh),
                    auth = VALUES(auth),
                    activo = 1,
                    fecha_actualizacion = NOW()
            ");
            $stmt->execute([
                'id'         => Uuid::generar(),
                'id_tenant'  => TenantContext::id(),
                'id_usuario' => $idUsuario,
                'endpoint'   => $endpoint,
                'p256dh'     => $p256dh,
                'auth'       => $auth,
            ]);

            Flight::json(['success' => true]);

        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    // =============================================
    // ELIMINAR SUSCRIPCIÓN PUSH (logout)
    // =============================================
    public static function eliminar()
    {
        try {
            $db = Flight::db();
            $data = self::getData();

            $endpoint = $data['endpoint'] ?? null;

            if (!$endpoint) {
                Flight::json(['error' => 'endpoint requerido'], 400);
                return;
            }

            $stmt = $db->prepare("DELETE FROM wa_push_subscriptions WHERE endpoint = :endpoint AND id_tenant = :id_tenant");
            $stmt->execute(['endpoint' => $endpoint, 'id_tenant' => TenantContext::id()]);

            Flight::json(['success' => true]);

        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    // Helper para obtener datos del request
    private static function getData()
    {
        $data = [];
        if (!empty($_POST)) {
            $data = $_POST;
        } elseif (Flight::request()->data) {
            foreach (Flight::request()->data as $key => $value) {
                $data[$key] = $value;
            }
        }
        // También leer JSON body
        $jsonBody = json_decode(Flight::request()->getBody(), true);
        if ($jsonBody) {
            $data = array_merge($data, $jsonBody);
        }
        return $data;
    }
}