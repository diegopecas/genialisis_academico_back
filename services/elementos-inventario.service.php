<?php
class ElementosInventario
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, descripcion, icono, orden, activo FROM elementos_inventario WHERE id_tenant = :id_tenant ORDER BY orden, nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Elementos que aplican a un grupo: los que no tienen ninguna restricción
     * registrada en elementos_inventario_grupos (aplican a todos) más los que
     * están asociados a ese grupo en particular.
     */
    public static function getByGrupo($id_grupo)
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT e.id, e.nombre, e.descripcion, e.icono, e.orden
                                  FROM elementos_inventario e
                                  WHERE e.id_tenant = :id_tenant
                                  AND e.activo = 1
                                  AND (
                                      NOT EXISTS (SELECT 1 FROM elementos_inventario_grupos g WHERE g.id_elemento_inventario = e.id)
                                      OR EXISTS (SELECT 1 FROM elementos_inventario_grupos g WHERE g.id_elemento_inventario = e.id AND g.id_grupo = :id_grupo)
                                  )
                                  ORDER BY e.orden, e.nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, descripcion, icono, orden, activo FROM elementos_inventario WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $nombre = Flight::request()->data['nombre'];
        $descripcion = isset(Flight::request()->data['descripcion']) ? Flight::request()->data['descripcion'] : null;
        $icono = isset(Flight::request()->data['icono']) ? Flight::request()->data['icono'] : null;
        $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
        $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

        if (empty($nombre) || trim($nombre) === '') {
            Flight::json(array('error' => 'El nombre es obligatorio'), 400);
            return;
        }

        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO elementos_inventario (id, id_tenant, nombre, descripcion, icono, orden, activo) VALUES (:id, :id_tenant, :nombre, :descripcion, :icono, :orden, :activo)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $descripcion = isset(Flight::request()->data['descripcion']) ? Flight::request()->data['descripcion'] : null;
        $icono = isset(Flight::request()->data['icono']) ? Flight::request()->data['icono'] : null;
        $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
        $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

        if (empty($nombre) || trim($nombre) === '') {
            Flight::json(array('error' => 'El nombre es obligatorio'), 400);
            return;
        }

        $sentence = $db->prepare("UPDATE elementos_inventario SET nombre = :nombre, descripcion = :descripcion, icono = :icono, orden = :orden, activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        // La FK contra inventario_diario es RESTRICT a propósito: si el
        // elemento ya tiene historial no se puede borrar, porque el histórico
        // quedaría con filas sin nombre. Para sacarlo de la grilla se usa el
        // campo activo.
        $sentence = $db->prepare("SELECT COUNT(*) AS usos FROM inventario_diario WHERE id_elemento_inventario = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        if ($fila && $fila['usos'] > 0) {
            Flight::json(array('error' => 'El elemento ya tiene registros de inventario y no se puede eliminar. Desactívelo si no quiere que siga apareciendo.'), 400);
            return;
        }

        $sentence = $db->prepare("DELETE FROM elementos_inventario WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
}
