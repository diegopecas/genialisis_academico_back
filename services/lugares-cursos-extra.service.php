<?php
class LugaresCursosExtra
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT lce.id, lce.nombre, lce.direccion, lce.telefono, lce.contacto,
            lce.observaciones, lce.activo,
            (SELECT COUNT(*) FROM cursos_extra ce
             WHERE ce.id_lugar_curso_extra = lce.id AND ce.id_tenant = lce.id_tenant) AS total_cursos
            FROM lugares_cursos_extra lce
            WHERE lce.id_tenant = :id_tenant
            ORDER BY lce.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Solo los activos: es lo que se ofrece en el selector del curso.
    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, direccion, telefono, contacto
            FROM lugares_cursos_extra
            WHERE id_tenant = :id_tenant
            AND activo = 1
            ORDER BY nombre
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
            SELECT id, nombre, direccion, telefono, contacto, observaciones, activo
            FROM lugares_cursos_extra
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
        $direccion = isset($data['direccion']) ? $data['direccion'] : null;
        $telefono = isset($data['telefono']) ? $data['telefono'] : null;
        $contacto = isset($data['contacto']) ? $data['contacto'] : null;
        $observaciones = isset($data['observaciones']) ? $data['observaciones'] : null;
        $activo = isset($data['activo']) ? $data['activo'] : 1;

        $id = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO lugares_cursos_extra
                (id, id_tenant, nombre, direccion, telefono, contacto, observaciones, activo)
            VALUES
                (:id, :id_tenant, :nombre, :direccion, :telefono, :contacto, :observaciones, :activo)
        ");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':direccion', $direccion);
        $sentence->bindParam(':telefono', $telefono);
        $sentence->bindParam(':contacto', $contacto);
        $sentence->bindParam(':observaciones', $observaciones);
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
        $direccion = isset($data['direccion']) ? $data['direccion'] : null;
        $telefono = isset($data['telefono']) ? $data['telefono'] : null;
        $contacto = isset($data['contacto']) ? $data['contacto'] : null;
        $observaciones = isset($data['observaciones']) ? $data['observaciones'] : null;
        $activo = isset($data['activo']) ? $data['activo'] : 1;

        $sentence = $db->prepare("
            UPDATE lugares_cursos_extra SET
                nombre = :nombre,
                direccion = :direccion,
                telefono = :telefono,
                contacto = :contacto,
                observaciones = :observaciones,
                activo = :activo
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':direccion', $direccion);
        $sentence->bindParam(':telefono', $telefono);
        $sentence->bindParam(':contacto', $contacto);
        $sentence->bindParam(':observaciones', $observaciones);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    /**
     * Eliminar un lugar.
     *
     * Se bloquea si hay cursos usandolo, para que el mensaje diga cuantos hay
     * que reasignar antes en lugar de devolver el error crudo de la FK.
     */
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $verificar = $db->prepare("
            SELECT COUNT(*) AS total
            FROM cursos_extra
            WHERE id_lugar_curso_extra = :id
            AND id_tenant = :id_tenant
        ");
        $verificar->bindParam(':id', $id);
        $verificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $verificar->execute();
        $fila = $verificar->fetch();

        if ($fila && (int) $fila['total'] > 0) {
            Flight::json(array(
                'error' => 'No se puede eliminar: hay ' . $fila['total'] .
                           ' curso(s) extracurricular(es) usando este lugar.'
            ), 400);
            return;
        }

        $sentence = $db->prepare("
            DELETE FROM lugares_cursos_extra
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}
