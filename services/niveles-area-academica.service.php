<?php
class NivelesAreaAcademica
{
    /**
     * Niveles de un área. Es la consulta que alimenta el tab de niveles
     * dentro del área académica.
     */
    public static function getByArea($id_area_academica)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT n.id, n.id_area_academica, n.nombre, n.descripcion, n.orden, n.activo,
            (SELECT COUNT(*) FROM logros l
             WHERE l.id_nivel = n.id AND l.id_tenant = n.id_tenant) AS total_logros
            FROM niveles_area_academica n
            WHERE n.id_area_academica = :id_area_academica
            AND n.id_tenant = :id_tenant
            ORDER BY n.orden, n.nombre
        ");
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /** Solo los activos: es lo que se ofrece al inscribir o al crear un logro. */
    public static function getActivosByArea($id_area_academica)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, id_area_academica, nombre, descripcion, orden
            FROM niveles_area_academica
            WHERE id_area_academica = :id_area_academica
            AND activo = 1
            AND id_tenant = :id_tenant
            ORDER BY orden, nombre
        ");
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Niveles del área de un curso extracurricular.
     *
     * Atajo para la pantalla de inscripción, que conoce el curso pero no su
     * área. Evita tener que consultar el curso primero.
     */
    public static function getByCursoExtra($id_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT n.id, n.id_area_academica, n.nombre, n.descripcion, n.orden
            FROM niveles_area_academica n
            INNER JOIN cursos_extra ce ON ce.id_area_academica = n.id_area_academica
            WHERE ce.id = :id_curso_extra
            AND n.activo = 1
            AND n.id_tenant = :id_tenant
            ORDER BY n.orden, n.nombre
        ");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, id_area_academica, nombre, descripcion, orden, activo
            FROM niveles_area_academica
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

        $id_area_academica = $data['id_area_academica'];
        $nombre = $data['nombre'];
        $descripcion = isset($data['descripcion']) ? $data['descripcion'] : null;
        $orden = isset($data['orden']) ? $data['orden'] : 0;
        $activo = isset($data['activo']) ? $data['activo'] : 1;

        $id = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO niveles_area_academica
                (id, id_tenant, id_area_academica, nombre, descripcion, orden, activo)
            VALUES
                (:id, :id_tenant, :id_area_academica, :nombre, :descripcion, :orden, :activo)
        ");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
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
        $orden = isset($data['orden']) ? $data['orden'] : 0;
        $activo = isset($data['activo']) ? $data['activo'] : 1;

        $sentence = $db->prepare("
            UPDATE niveles_area_academica SET
                nombre = :nombre,
                descripcion = :descripcion,
                orden = :orden,
                activo = :activo
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    /**
     * Eliminar un nivel.
     *
     * Se bloquea si hay logros o inscripciones usándolo. La FK lo rechazaría
     * igual, pero así el mensaje dice cuántos hay que reasignar antes.
     */
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $verificar = $db->prepare("
            SELECT
              (SELECT COUNT(*) FROM logros WHERE id_nivel = :id1 AND id_tenant = :t1) AS logros,
              (SELECT COUNT(*) FROM estudiantes_x_cursos_extra WHERE id_nivel = :id2 AND id_tenant = :t2) AS inscripciones
        ");
        $verificar->bindParam(':id1', $id);
        $verificar->bindParam(':id2', $id);
        $verificar->bindValue(':t1', TenantContext::id(), PDO::PARAM_INT);
        $verificar->bindValue(':t2', TenantContext::id(), PDO::PARAM_INT);
        $verificar->execute();
        $fila = $verificar->fetch(PDO::FETCH_ASSOC);

        if ($fila && ((int) $fila['logros'] > 0 || (int) $fila['inscripciones'] > 0)) {
            Flight::json(array(
                'error' => 'No se puede eliminar: el nivel tiene ' . $fila['logros'] .
                           ' logro(s) y ' . $fila['inscripciones'] . ' inscripción(es) asociadas.'
            ), 400);
            return;
        }

        $sentence = $db->prepare("
            DELETE FROM niveles_area_academica
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}
