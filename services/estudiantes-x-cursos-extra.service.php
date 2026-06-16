<?php 
class EstudiantesXCursosExtra
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exce.id, exce.id_estudiante, exce.id_curso_extra, exce.fecha_inscripcion, exce.anio, exce.activo,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        ce.nombre AS nombre_curso
        FROM estudiantes_x_cursos_extra exce
        INNER JOIN estudiantes e ON exce.id_estudiante = e.id
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN cursos_extra ce ON exce.id_curso_extra = ce.id
        ORDER BY p.primer_apellido, p.primer_nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exce.id, exce.id_estudiante, exce.id_curso_extra, exce.fecha_inscripcion, exce.anio, exce.activo,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        ce.nombre AS nombre_curso
        FROM estudiantes_x_cursos_extra exce
        INNER JOIN estudiantes e ON exce.id_estudiante = e.id
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN cursos_extra ce ON exce.id_curso_extra = ce.id
        WHERE exce.id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByCurso($id_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exce.id, exce.id_estudiante, exce.id_curso_extra, exce.fecha_inscripcion, exce.anio, exce.activo,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo
        FROM estudiantes_x_cursos_extra exce
        INNER JOIN estudiantes e ON exce.id_estudiante = e.id
        INNER JOIN personas p ON e.id_persona = p.id
        WHERE exce.id_curso_extra = :id_curso_extra
        ORDER BY p.primer_apellido, p.primer_nombre");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByEstudiante($id_estudiante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exce.id, exce.id_estudiante, exce.id_curso_extra, exce.fecha_inscripcion, exce.anio, exce.activo,
        ce.nombre AS nombre_curso
        FROM estudiantes_x_cursos_extra exce
        INNER JOIN cursos_extra ce ON exce.id_curso_extra = ce.id
        WHERE exce.id_estudiante = :id_estudiante
        ORDER BY exce.anio DESC, ce.nombre");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_estudiante = Flight::request()->data['id_estudiante'];
        $id_curso_extra = Flight::request()->data['id_curso_extra'];
        $fecha_inscripcion = Flight::request()->data['fecha_inscripcion'];
        $anio = Flight::request()->data['anio'];

        $sentence = $db->prepare("INSERT INTO estudiantes_x_cursos_extra(id_estudiante, id_curso_extra, fecha_inscripcion, anio, activo) 
        VALUES (:id_estudiante, :id_curso_extra, :fecha_inscripcion, :anio, 1)");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindParam(':fecha_inscripcion', $fecha_inscripcion);
        $sentence->bindParam(':anio', $anio, PDO::PARAM_INT);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $activo = Flight::request()->data['activo'];

        $sentence = $db->prepare("UPDATE estudiantes_x_cursos_extra SET activo = :activo WHERE id = :id");
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM estudiantes_x_cursos_extra WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

    // Anula la inscripcion del estudiante al curso extracurricular en una sola transaccion:
    // anula las cuentas por cobrar asociadas que no tengan pagos aplicados, conserva las que
    // si tienen pagos (devolviendolas para informar al usuario) y marca la inscripcion como
    // inactiva. La FK con cuentas_cobrar_x_curso_extra se preserva por trazabilidad.
    public static function anular()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        try {
            $db->beginTransaction();

            $stmtCuentas = $db->prepare("
                SELECT ccxce.id, ccxce.id_cuenta_por_cobrar, cpc.valor, cpc.anulado,
                ps.nombre AS nombre_producto, cpc.fecha,
                COALESCE(SUM(cp.valor_aplicado), 0) AS valor_pagado
                FROM cuentas_cobrar_x_curso_extra ccxce
                INNER JOIN cuentas_por_cobrar cpc ON ccxce.id_cuenta_por_cobrar = cpc.id
                INNER JOIN productos_servicios ps ON cpc.id_producto_servicio = ps.id
                LEFT JOIN cuenta_pagada cp ON cpc.id = cp.id_cuenta_por_cobrar
                WHERE ccxce.id_estudiante_x_curso_extra = :id_inscripcion
                GROUP BY ccxce.id, cpc.id, ps.nombre
            ");
            $stmtCuentas->bindParam(':id_inscripcion', $id);
            $stmtCuentas->execute();
            $cuentas = $stmtCuentas->fetchAll(PDO::FETCH_ASSOC);

            $anuladas = 0;
            $conPagos = [];

            $stmtAnularCuenta = $db->prepare("
                UPDATE cuentas_por_cobrar SET anulado = 1, fecha_anulacion = NOW() 
                WHERE id = :id AND (anulado = 0 OR anulado IS NULL)
            ");

            foreach ($cuentas as $cuenta) {
                if ($cuenta['anulado'] == 1) continue;

                if ($cuenta['valor_pagado'] > 0) {
                    $conPagos[] = [
                        'nombre_producto' => $cuenta['nombre_producto'],
                        'fecha' => $cuenta['fecha'],
                        'valor' => $cuenta['valor'],
                        'valor_pagado' => $cuenta['valor_pagado']
                    ];
                } else {
                    $stmtAnularCuenta->bindParam(':id', $cuenta['id_cuenta_por_cobrar']);
                    $stmtAnularCuenta->execute();
                    $anuladas++;
                }
            }

            $stmtAnularInscripcion = $db->prepare("UPDATE estudiantes_x_cursos_extra SET activo = 0 WHERE id = :id");
            $stmtAnularInscripcion->bindParam(':id', $id);
            $stmtAnularInscripcion->execute();

            $db->commit();

            Flight::json(array(
                'success' => true,
                'anuladas' => $anuladas,
                'con_pagos' => $conPagos,
                'total_cuentas' => count($cuentas)
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en anular inscripcion curso extra: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

}