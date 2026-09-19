<?php
class TiposCursosExtracurriculares
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tce.id, tce.nombre, tce.descripcion, tce.color, tce.orden, tce.activo,
            (SELECT COUNT(*) FROM cursos_extra ce
             WHERE ce.id_tipo_curso_extracurricular = tce.id AND ce.id_tenant = tce.id_tenant) AS total_cursos
            FROM tipos_cursos_extracurriculares tce
            WHERE tce.id_tenant = :id_tenant
            ORDER BY tce.orden, tce.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Solo los activos: es lo que se ofrece en los selectores del curso.
    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, descripcion, color, orden
            FROM tipos_cursos_extracurriculares
            WHERE id_tenant = :id_tenant
            AND activo = 1
            ORDER BY orden, nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, descripcion, color, orden, activo
            FROM tipos_cursos_extracurriculares
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $nombre = $data['nombre'];
        $descripcion = isset($data['descripcion']) ? $data['descripcion'] : null;
        $color = isset($data['color']) ? $data['color'] : null;
        $orden = isset($data['orden']) ? $data['orden'] : 0;
        $activo = isset($data['activo']) ? $data['activo'] : 1;

        $id = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO tipos_cursos_extracurriculares
                (id, id_tenant, nombre, descripcion, color, orden, activo)
            VALUES
                (:id, :id_tenant, :nombre, :descripcion, :color, :orden, :activo)
        ");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':color', $color);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id = $data['id'];
        $nombre = $data['nombre'];
        $descripcion = isset($data['descripcion']) ? $data['descripcion'] : null;
        $color = isset($data['color']) ? $data['color'] : null;
        $orden = isset($data['orden']) ? $data['orden'] : 0;
        $activo = isset($data['activo']) ? $data['activo'] : 1;

        $sentence = $db->prepare("
            UPDATE tipos_cursos_extracurriculares SET
                nombre = :nombre,
                descripcion = :descripcion,
                color = :color,
                orden = :orden,
                activo = :activo
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':color', $color);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    /**
     * Eliminar un tipo.
     *
     * Se bloquea si hay cursos usandolo: la FK lo rechazaria de todas formas,
     * pero asi el mensaje dice cuantos cursos hay que reasignar primero.
     */
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $verificar = $db->prepare("
            SELECT COUNT(*) AS total
            FROM cursos_extra
            WHERE id_tipo_curso_extracurricular = :id
            AND id_tenant = :id_tenant
        ");
        $verificar->bindParam(':id', $id);
        $verificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $verificar->execute();
        $fila = $verificar->fetch();

        if ($fila && (int) $fila['total'] > 0) {
            Flight::json(array(
                'error' => 'No se puede eliminar: hay ' . $fila['total'] .
                           ' curso(s) extracurricular(es) usando este tipo.'
            ), 400);
            return;
        }

        $sentence = $db->prepare("
            DELETE FROM tipos_cursos_extracurriculares
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}
