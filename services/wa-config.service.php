<?php
class WaConfig
{
    /**
     * Obtener todas las configuraciones de WA del tenant.
     */
    public static function getAll()
    {
        $db = Flight::db();
        $stmt = $db->prepare("SELECT clave, valor, descripcion FROM wa_config WHERE id_tenant = :id_tenant ORDER BY id");
        $stmt->execute(['id_tenant' => TenantContext::id()]);
        Flight::json($stmt->fetchAll());
    }

    /**
     * Obtener una configuración por clave.
     */
    public static function getByClave($clave)
    {
        $db = Flight::db();
        $stmt = $db->prepare("SELECT clave, valor, descripcion FROM wa_config WHERE clave = :clave AND id_tenant = :id_tenant LIMIT 1");
        $stmt->execute(['clave' => $clave, 'id_tenant' => TenantContext::id()]);
        $row = $stmt->fetch();
        
        if ($row) {
            Flight::json($row);
        } else {
            Flight::json(['error' => 'Clave no encontrada'], 404);
        }
    }

    /**
     * Actualizar una configuración por clave.
     */
    public static function update()
    {
        $db = Flight::db();

        $data = [];
        if (!empty($_POST)) {
            $data = $_POST;
        } elseif (Flight::request()->data) {
            foreach (Flight::request()->data as $key => $value) {
                $data[$key] = $value;
            }
        }

        $clave = $data['clave'] ?? null;
        $valor = $data['valor'] ?? null;

        if (!$clave) {
            Flight::json(['error' => 'clave es requerida'], 400);
            return;
        }

        $stmt = $db->prepare("
            UPDATE wa_config SET valor = :valor, fecha_actualizacion = NOW() 
            WHERE clave = :clave
            AND id_tenant = :id_tenant
        ");
        $stmt->execute(['valor' => $valor, 'clave' => $clave, 'id_tenant' => TenantContext::id()]);

        if ($stmt->rowCount() > 0) {
            Flight::json(['success' => true]);
        } else {
            Flight::json(['error' => 'Clave no encontrada o mismo valor'], 404);
        }
    }

    /**
     * Obtener etiqueta del remitente dado un id_persona.
     * Formato: [Sobrenombre - Cargo corto]
     */
    public static function getEtiquetaRemitente($id_persona)
    {
        $db = Flight::db();
        $stmt = $db->prepare("
            SELECT CONCAT('[', IFNULL(col.sobrenombre, p.primer_nombre), ' - ', IFNULL(car.nombre_corto, car.nombre), ']') AS etiqueta
            FROM personas p
            LEFT JOIN colaboradores col ON col.id_persona = p.id AND col.activo = 1
            LEFT JOIN cargos car ON col.id_cargo = car.id
            WHERE p.id = :id_persona
            AND p.id_tenant = :id_tenant
            LIMIT 1
        ");
        $stmt->execute(['id_persona' => $id_persona, 'id_tenant' => TenantContext::id()]);
        $row = $stmt->fetch();
        Flight::json(['etiqueta' => $row ? $row['etiqueta'] : null]);
    }
}