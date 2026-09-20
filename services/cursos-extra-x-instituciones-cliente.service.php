<?php
/**
 * Convenios entre un curso extracurricular y las instituciones cliente a
 * las que se les presta ese curso.
 *
 * El horario, el lugar y el cupo siguen siendo del curso y se comparten
 * entre todos sus convenios. Lo unico propio del convenio es
 * `paga_institucion`, que decide a nombre de quien se emite la cuenta
 * por cobrar de los estudiantes que entran por ahi.
 *
 * La tarifa del convenio no vive aqui: vive en `tarifas_cursos_extra`
 * con `id_institucion_cliente` diligenciado.
 */
class CursosExtraXInstitucionesCliente
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT cxic.id, cxic.id_curso_extra, cxic.id_institucion_cliente,
        cxic.paga_institucion, cxic.observaciones, cxic.activo, cxic.fecha_registro,
        ce.nombre AS nombre_curso, ce.anio,
        tin.nombre AS nombre_tipo_institucion,
        CASE
            WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
            ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                       IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
        END AS nombre_institucion
        FROM cursos_extra_x_instituciones_cliente cxic
        INNER JOIN cursos_extra ce ON cxic.id_curso_extra = ce.id
        INNER JOIN instituciones_cliente ic ON cxic.id_institucion_cliente = ic.id
        INNER JOIN personas p ON ic.id_persona = p.id AND p.id_tenant = ic.id_tenant
        INNER JOIN tipos_institucion tin ON ic.id_tipo_institucion = tin.id
        WHERE cxic.id_tenant = :id_tenant
        ORDER BY ce.nombre, nombre_institucion");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT cxic.id, cxic.id_curso_extra, cxic.id_institucion_cliente,
        cxic.paga_institucion, cxic.observaciones, cxic.activo, cxic.fecha_registro,
        ce.nombre AS nombre_curso, ce.anio,
        tin.nombre AS nombre_tipo_institucion,
        CASE
            WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
            ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                       IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
        END AS nombre_institucion
        FROM cursos_extra_x_instituciones_cliente cxic
        INNER JOIN cursos_extra ce ON cxic.id_curso_extra = ce.id
        INNER JOIN instituciones_cliente ic ON cxic.id_institucion_cliente = ic.id
        INNER JOIN personas p ON ic.id_persona = p.id AND p.id_tenant = ic.id_tenant
        INNER JOIN tipos_institucion tin ON ic.id_tipo_institucion = tin.id
        WHERE cxic.id = :id AND cxic.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Convenios de un curso. Lo consume el tab de Clientes del formulario
    // del curso y tambien el selector de convenio al inscribir estudiantes.
    public static function getByCurso($id_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT cxic.id, cxic.id_curso_extra, cxic.id_institucion_cliente,
        cxic.paga_institucion, cxic.observaciones, cxic.activo, cxic.fecha_registro,
        tin.nombre AS nombre_tipo_institucion,
        p.numero_identificacion,
        CASE
            WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
            ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                       IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
        END AS nombre_institucion
        FROM cursos_extra_x_instituciones_cliente cxic
        INNER JOIN instituciones_cliente ic ON cxic.id_institucion_cliente = ic.id
        INNER JOIN personas p ON ic.id_persona = p.id AND p.id_tenant = ic.id_tenant
        INNER JOIN tipos_institucion tin ON ic.id_tipo_institucion = tin.id
        WHERE cxic.id_curso_extra = :id_curso_extra AND cxic.id_tenant = :id_tenant
        ORDER BY nombre_institucion");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Cursos a los que esta asociada una institucion.
    public static function getByInstitucion($id_institucion_cliente)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT cxic.id, cxic.id_curso_extra, cxic.id_institucion_cliente,
        cxic.paga_institucion, cxic.observaciones, cxic.activo, cxic.fecha_registro,
        ce.nombre AS nombre_curso, ce.anio, ce.fecha_inicio, ce.fecha_fin, ce.activo AS curso_activo
        FROM cursos_extra_x_instituciones_cliente cxic
        INNER JOIN cursos_extra ce ON cxic.id_curso_extra = ce.id
        WHERE cxic.id_institucion_cliente = :id_institucion_cliente AND cxic.id_tenant = :id_tenant
        ORDER BY ce.anio DESC, ce.nombre");
        $sentence->bindParam(':id_institucion_cliente', $id_institucion_cliente);
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

            $id_curso_extra = isset($data['id_curso_extra']) ? $data['id_curso_extra'] : null;
            $id_institucion_cliente = isset($data['id_institucion_cliente']) ? $data['id_institucion_cliente'] : null;
            $paga_institucion = isset($data['paga_institucion']) ? (int) $data['paga_institucion'] : 0;
            $observaciones = isset($data['observaciones']) ? $data['observaciones'] : null;

            if (!$id_curso_extra || !$id_institucion_cliente) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            // El indice unico ya lo impide, pero reventaria con un error de
            // base. Aqui sale un mensaje que se entiende.
            $verif = $db->prepare("SELECT id FROM cursos_extra_x_instituciones_cliente
                                   WHERE id_curso_extra = :id_curso_extra
                                     AND id_institucion_cliente = :id_institucion_cliente
                                     AND id_tenant = :id_tenant LIMIT 1");
            $verif->bindParam(':id_curso_extra', $id_curso_extra);
            $verif->bindParam(':id_institucion_cliente', $id_institucion_cliente);
            $verif->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verif->execute();

            if ($verif->fetch()) {
                Flight::json(array('error' => 'Esta institución ya está asociada al curso.'), 400);
                return;
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO cursos_extra_x_instituciones_cliente(
                id, id_tenant, id_curso_extra, id_institucion_cliente,
                paga_institucion, observaciones, activo, fecha_registro
            ) VALUES (
                :id, :id_tenant, :id_curso_extra, :id_institucion_cliente,
                :paga_institucion, :observaciones, 1, NOW()
            )");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_curso_extra', $id_curso_extra);
            $sentence->bindParam(':id_institucion_cliente', $id_institucion_cliente);
            $sentence->bindValue(':paga_institucion', $paga_institucion, PDO::PARAM_INT);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->execute();

            Flight::json(array('id' => $idNew));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método new de cursos extra x instituciones cliente: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $id = isset($data['id']) ? $data['id'] : null;
            $paga_institucion = isset($data['paga_institucion']) ? (int) $data['paga_institucion'] : 0;
            $observaciones = isset($data['observaciones']) ? $data['observaciones'] : null;
            $activo = isset($data['activo']) ? (int) $data['activo'] : 1;

            if (!$id) {
                Flight::json(array('error' => 'Falta el ID del convenio'), 400);
                return;
            }

            // El curso y la institucion no se cambian en edicion: para eso se
            // elimina el convenio y se crea otro, porque cambiarlos moveria las
            // tarifas y las inscripciones que ya cuelgan de el.
            $sentence = $db->prepare("UPDATE cursos_extra_x_instituciones_cliente SET
                                    paga_institucion = :paga_institucion,
                                    observaciones = :observaciones,
                                    activo = :activo
                                    WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':paga_institucion', $paga_institucion, PDO::PARAM_INT);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en la ejecución del método replace de cursos extra x instituciones cliente: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al actualizar el convenio.'), 500);
        }
    }

    /**
     * Elimina el convenio. No se puede si ya hay estudiantes inscritos al
     * curso por ese convenio, porque la inscripcion guarda de donde salio la
     * tarifa y a nombre de quien se emitio la cuenta.
     */
    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            if (!$id) {
                Flight::json(array('error' => 'Falta el ID del convenio a eliminar'), 400);
                return;
            }

            $stmtConvenio = $db->prepare("SELECT id_curso_extra, id_institucion_cliente
                                          FROM cursos_extra_x_instituciones_cliente
                                          WHERE id = :id AND id_tenant = :id_tenant");
            $stmtConvenio->bindParam(':id', $id);
            $stmtConvenio->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtConvenio->execute();
            $convenio = $stmtConvenio->fetch(PDO::FETCH_ASSOC);

            if (!$convenio) {
                Flight::json(array('error' => 'No se encontró el convenio'), 404);
                return;
            }

            $stmtInscritos = $db->prepare("SELECT COUNT(*) AS total
                                           FROM estudiantes_x_cursos_extra
                                           WHERE id_curso_extra = :id_curso_extra
                                             AND id_institucion_cliente = :id_institucion_cliente
                                             AND id_tenant = :id_tenant");
            $stmtInscritos->bindParam(':id_curso_extra', $convenio['id_curso_extra']);
            $stmtInscritos->bindParam(':id_institucion_cliente', $convenio['id_institucion_cliente']);
            $stmtInscritos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtInscritos->execute();
            $inscritos = $stmtInscritos->fetch(PDO::FETCH_ASSOC);

            if ($inscritos && (int) $inscritos['total'] > 0) {
                Flight::json(array(
                    'error' => 'No se puede eliminar: hay ' . $inscritos['total'] . ' estudiante(s) inscrito(s) por este convenio. Desactívelo en lugar de eliminarlo.'
                ), 400);
                return;
            }

            // La tarifa del convenio si se borra con el, porque sin convenio no
            // tiene a quien aplicarse.
            $stmtTarifas = $db->prepare("DELETE FROM tarifas_cursos_extra
                                         WHERE id_curso_extra = :id_curso_extra
                                           AND id_institucion_cliente = :id_institucion_cliente
                                           AND id_tenant = :id_tenant");
            $stmtTarifas->bindParam(':id_curso_extra', $convenio['id_curso_extra']);
            $stmtTarifas->bindParam(':id_institucion_cliente', $convenio['id_institucion_cliente']);
            $stmtTarifas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtTarifas->execute();

            $sentence = $db->prepare("DELETE FROM cursos_extra_x_instituciones_cliente WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método delete de cursos extra x instituciones cliente: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al eliminar el convenio.'), 500);
        }
    }

    /**
     * Instituciones cliente activas que todavia no estan asociadas al curso.
     * Alimenta el selector del tab de Clientes.
     */
    public static function getDisponibles($id_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ic.id, ic.id_tipo_institucion,
        tin.nombre AS nombre_tipo_institucion,
        p.numero_identificacion,
        CASE
            WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
            ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                       IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
        END AS nombre_institucion
        FROM instituciones_cliente ic
        INNER JOIN personas p ON ic.id_persona = p.id AND p.id_tenant = ic.id_tenant
        INNER JOIN tipos_institucion tin ON ic.id_tipo_institucion = tin.id
        WHERE ic.activo = 1
          AND ic.id_tenant = :id_tenant
          AND NOT EXISTS (
              SELECT 1 FROM cursos_extra_x_instituciones_cliente cxic
              WHERE cxic.id_institucion_cliente = ic.id
                AND cxic.id_curso_extra = :id_curso_extra
                AND cxic.id_tenant = ic.id_tenant
          )
        ORDER BY nombre_institucion");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}
