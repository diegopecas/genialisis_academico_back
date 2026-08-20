<?php
class UtilesDiarios
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, descripcion, icono, orden, activo FROM utiles_diarios WHERE id_tenant = :id_tenant ORDER BY orden, nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Útiles que aplican a un grupo: los que no tienen ninguna restricción
     * registrada en utiles_diarios_grupos (aplican a todos) más los que
     * están asociados a ese grupo en particular.
     */
    public static function getByGrupo($id_grupo)
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT e.id, e.nombre, e.descripcion, e.icono, e.orden
                                  FROM utiles_diarios e
                                  WHERE e.id_tenant = :id_tenant
                                  AND e.activo = 1
                                  AND (
                                      NOT EXISTS (SELECT 1 FROM utiles_diarios_grupos g WHERE g.id_util_diario = e.id)
                                      OR EXISTS (SELECT 1 FROM utiles_diarios_grupos g WHERE g.id_util_diario = e.id AND g.id_grupo = :id_grupo)
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
        $sentence = $db->prepare("SELECT id, nombre, descripcion, icono, orden, activo FROM utiles_diarios WHERE id = :id AND id_tenant = :id_tenant");
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
        $sentence = $db->prepare("INSERT INTO utiles_diarios (id, id_tenant, nombre, descripcion, icono, orden, activo) VALUES (:id, :id_tenant, :nombre, :descripcion, :icono, :orden, :activo)");
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

        $sentence = $db->prepare("UPDATE utiles_diarios SET nombre = :nombre, descripcion = :descripcion, icono = :icono, orden = :orden, activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
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

        // La FK contra utiles_diarios_registro es RESTRICT a propósito: si el
        // útil ya tiene historial no se puede borrar, porque el histórico
        // quedaría con filas sin nombre. Para sacarlo de la grilla se usa el
        // campo activo.
        $sentence = $db->prepare("SELECT COUNT(*) AS usos FROM utiles_diarios_registro WHERE id_util_diario = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        if ($fila && $fila['usos'] > 0) {
            Flight::json(array('error' => 'El útil ya tiene registros y no se puede eliminar. Desactívelo si no quiere que siga apareciendo.'), 400);
            return;
        }

        $sentence = $db->prepare("DELETE FROM utiles_diarios WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
}
