<?php
/**
 * Estudiantes que pertenecen a una institucion cliente.
 *
 * Es una relacion con vigencia y no un atributo del estudiante: un nino
 * puede venir del Colegio X este anio y del Y el siguiente, y el
 * historico tiene que quedar. Mismo patron de `estudiantes_x_grupos`.
 *
 * Esta pertenencia sirve para filtrar y para reportes. Lo que decide la
 * tarifa y a nombre de quien se emite la cuenta es
 * `estudiantes_x_cursos_extra.id_institucion_cliente`, que se congela en
 * la inscripcion.
 */
class EstudiantesXInstitucionesCliente
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exic.id, exic.id_estudiante, exic.id_institucion_cliente,
        exic.anio, exic.activo, exic.fecha_inicio, exic.fecha_fin,
        CONCAT(IFNULL(pe.primer_nombre, ''), ' ', IFNULL(pe.segundo_nombre, ''), ' ',
               IFNULL(pe.primer_apellido, ''), ' ', IFNULL(pe.segundo_apellido, '')) AS nombre_completo,
        pe.numero_identificacion,
        CASE
            WHEN pi.razon_social IS NOT NULL AND pi.razon_social != '' THEN pi.razon_social
            ELSE CONCAT(IFNULL(pi.primer_nombre, ''), ' ', IFNULL(pi.primer_apellido, ''))
        END AS nombre_institucion
        FROM estudiantes_x_instituciones_cliente exic
        INNER JOIN estudiantes e ON exic.id_estudiante = e.id
        INNER JOIN personas pe ON e.id_persona = pe.id AND pe.id_tenant = e.id_tenant
        INNER JOIN instituciones_cliente ic ON exic.id_institucion_cliente = ic.id
        INNER JOIN personas pi ON ic.id_persona = pi.id AND pi.id_tenant = ic.id_tenant
        WHERE exic.id_tenant = :id_tenant
        ORDER BY exic.anio DESC, pe.primer_apellido, pe.primer_nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exic.id, exic.id_estudiante, exic.id_institucion_cliente,
        exic.anio, exic.activo, exic.fecha_inicio, exic.fecha_fin,
        CONCAT(IFNULL(pe.primer_nombre, ''), ' ', IFNULL(pe.segundo_nombre, ''), ' ',
               IFNULL(pe.primer_apellido, ''), ' ', IFNULL(pe.segundo_apellido, '')) AS nombre_completo,
        pe.numero_identificacion
        FROM estudiantes_x_instituciones_cliente exic
        INNER JOIN estudiantes e ON exic.id_estudiante = e.id
        INNER JOIN personas pe ON e.id_persona = pe.id AND pe.id_tenant = e.id_tenant
        WHERE exic.id = :id AND exic.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Estudiantes de una institucion. Lo consume el tab de Estudiantes del
    // formulario de institucion cliente.
    public static function getByInstitucion($id_institucion_cliente)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exic.id, exic.id_estudiante, exic.id_institucion_cliente,
        exic.anio, exic.activo, exic.fecha_inicio, exic.fecha_fin,
        CONCAT(IFNULL(pe.primer_nombre, ''), ' ', IFNULL(pe.segundo_nombre, ''), ' ',
               IFNULL(pe.primer_apellido, ''), ' ', IFNULL(pe.segundo_apellido, '')) AS nombre_completo,
        pe.numero_identificacion, ti.nombre AS tipo_identificacion
        FROM estudiantes_x_instituciones_cliente exic
        INNER JOIN estudiantes e ON exic.id_estudiante = e.id
        INNER JOIN personas pe ON e.id_persona = pe.id AND pe.id_tenant = e.id_tenant
        INNER JOIN tipos_identificacion ti ON pe.id_tipo_identificacion = ti.id
        WHERE exic.id_institucion_cliente = :id_institucion_cliente AND exic.id_tenant = :id_tenant
        ORDER BY exic.anio DESC, pe.primer_apellido, pe.primer_nombre");
        $sentence->bindParam(':id_institucion_cliente', $id_institucion_cliente);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Instituciones a las que ha pertenecido un estudiante.
    public static function getByEstudiante($id_estudiante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exic.id, exic.id_estudiante, exic.id_institucion_cliente,
        exic.anio, exic.activo, exic.fecha_inicio, exic.fecha_fin,
        CASE
            WHEN pi.razon_social IS NOT NULL AND pi.razon_social != '' THEN pi.razon_social
            ELSE CONCAT(IFNULL(pi.primer_nombre, ''), ' ', IFNULL(pi.primer_apellido, ''))
        END AS nombre_institucion
        FROM estudiantes_x_instituciones_cliente exic
        INNER JOIN instituciones_cliente ic ON exic.id_institucion_cliente = ic.id
        INNER JOIN personas pi ON ic.id_persona = pi.id AND pi.id_tenant = ic.id_tenant
        WHERE exic.id_estudiante = :id_estudiante AND exic.id_tenant = :id_tenant
        ORDER BY exic.anio DESC, nombre_institucion");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $id_estudiante = isset($data['id_estudiante']) ? $data['id_estudiante'] : null;
            $id_institucion_cliente = isset($data['id_institucion_cliente']) ? $data['id_institucion_cliente'] : null;
            $anio = isset($data['anio']) ? (int) $data['anio'] : (int) date('Y');
            $fecha_inicio = !empty($data['fecha_inicio']) ? $data['fecha_inicio'] : date('Y-m-d');
            $fecha_fin = !empty($data['fecha_fin']) ? $data['fecha_fin'] : null;

            if (!$id_estudiante || !$id_institucion_cliente) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            // El indice unico ya lo impide, pero reventaria con un error de
            // base. Aqui sale un mensaje que se entiende.
            $verif = $db->prepare("SELECT id FROM estudiantes_x_instituciones_cliente
                                   WHERE id_estudiante = :id_estudiante
                                     AND id_institucion_cliente = :id_institucion_cliente
                                     AND anio = :anio
                                     AND id_tenant = :id_tenant LIMIT 1");
            $verif->bindParam(':id_estudiante', $id_estudiante);
            $verif->bindParam(':id_institucion_cliente', $id_institucion_cliente);
            $verif->bindValue(':anio', $anio, PDO::PARAM_INT);
            $verif->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verif->execute();

            if ($verif->fetch()) {
                Flight::json(array('error' => 'El estudiante ya está registrado en esta institución para el año ' . $anio . '.'), 400);
                return;
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO estudiantes_x_instituciones_cliente(
                id, id_tenant, id_estudiante, id_institucion_cliente, anio, activo, fecha_inicio, fecha_fin
            ) VALUES (
                :id, :id_tenant, :id_estudiante, :id_institucion_cliente, :anio, 1, :fecha_inicio, :fecha_fin
            )");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindParam(':id_institucion_cliente', $id_institucion_cliente);
            $sentence->bindValue(':anio', $anio, PDO::PARAM_INT);
            $sentence->bindParam(':fecha_inicio', $fecha_inicio);
            $sentence->bindValue(':fecha_fin', $fecha_fin);
            $sentence->execute();

            Flight::json(array('id' => $idNew));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método new de estudiantes x instituciones cliente: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $id = isset($data['id']) ? $data['id'] : null;
            $activo = isset($data['activo']) ? (int) $data['activo'] : 1;
            $fecha_fin = !empty($data['fecha_fin']) ? $data['fecha_fin'] : null;

            if (!$id) {
                Flight::json(array('error' => 'Falta el ID del registro'), 400);
                return;
            }

            // El estudiante, la institucion y el anio no se cambian en edicion:
            // para mover un nino de colegio se cierra este registro y se crea
            // otro, que es justo lo que conserva el historico.
            $sentence = $db->prepare("UPDATE estudiantes_x_instituciones_cliente SET
                                    activo = :activo,
                                    fecha_fin = :fecha_fin
                                    WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindValue(':fecha_fin', $fecha_fin);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en la ejecución del método replace de estudiantes x instituciones cliente: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al actualizar el registro.'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            if (!$id) {
                Flight::json(array('error' => 'Falta el ID del registro a eliminar'), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM estudiantes_x_instituciones_cliente WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el registro con el ID especificado'), 404);
                return;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método delete de estudiantes x instituciones cliente: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al eliminar el registro.'), 500);
        }
    }

    /**
     * Estudiantes activos del tenant que todavia no estan en esa institucion
     * para el anio dado. Alimenta el selector del tab de Estudiantes.
     */
    public static function getDisponibles($id_institucion_cliente, $anio)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT e.id, e.id_persona,
        CONCAT(IFNULL(pe.primer_nombre, ''), ' ', IFNULL(pe.segundo_nombre, ''), ' ',
               IFNULL(pe.primer_apellido, ''), ' ', IFNULL(pe.segundo_apellido, '')) AS nombre_completo,
        pe.numero_identificacion, ti.nombre AS tipo_identificacion
        FROM estudiantes e
        INNER JOIN personas pe ON e.id_persona = pe.id AND pe.id_tenant = e.id_tenant
        INNER JOIN tipos_identificacion ti ON pe.id_tipo_identificacion = ti.id
        WHERE e.activo = 1
          AND e.id_tenant = :id_tenant
          AND NOT EXISTS (
              SELECT 1 FROM estudiantes_x_instituciones_cliente exic
              WHERE exic.id_estudiante = e.id
                AND exic.id_institucion_cliente = :id_institucion_cliente
                AND exic.anio = :anio
                AND exic.id_tenant = e.id_tenant
          )
        ORDER BY pe.primer_apellido, pe.primer_nombre");
        $sentence->bindParam(':id_institucion_cliente', $id_institucion_cliente);
        $sentence->bindValue(':anio', $anio, PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}
