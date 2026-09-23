<?php 
class EstudiantesXCursosExtra
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exce.id, exce.id_estudiante, exce.id_curso_extra, exce.fecha_inscripcion, exce.anio, exce.activo, exce.id_institucion_cliente, exce.id_nivel, niv.nombre AS nombre_nivel,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        ce.nombre AS nombre_curso
        FROM estudiantes_x_cursos_extra exce
        INNER JOIN estudiantes e ON exce.id_estudiante = e.id
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN cursos_extra ce ON exce.id_curso_extra = ce.id
        LEFT JOIN niveles_area_academica niv ON exce.id_nivel = niv.id
        WHERE exce.id_tenant = :id_tenant
        ORDER BY p.primer_apellido, p.primer_nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exce.id, exce.id_estudiante, exce.id_curso_extra, exce.fecha_inscripcion, exce.anio, exce.activo, exce.id_institucion_cliente, exce.id_nivel, niv.nombre AS nombre_nivel,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        ce.nombre AS nombre_curso
        FROM estudiantes_x_cursos_extra exce
        INNER JOIN estudiantes e ON exce.id_estudiante = e.id
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN cursos_extra ce ON exce.id_curso_extra = ce.id
        LEFT JOIN niveles_area_academica niv ON exce.id_nivel = niv.id
        WHERE exce.id = :id AND exce.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByCurso($id_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exce.id, exce.id_estudiante, exce.id_curso_extra, exce.fecha_inscripcion, exce.anio, exce.activo, exce.id_institucion_cliente, exce.id_nivel, niv.nombre AS nombre_nivel,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        CASE
            WHEN pi.razon_social IS NOT NULL AND pi.razon_social != '' THEN pi.razon_social
            ELSE CONCAT(IFNULL(pi.primer_nombre, ''), ' ', IFNULL(pi.primer_apellido, ''))
        END AS nombre_institucion
        FROM estudiantes_x_cursos_extra exce
        INNER JOIN estudiantes e ON exce.id_estudiante = e.id
        INNER JOIN personas p ON e.id_persona = p.id
        LEFT JOIN instituciones_cliente ic ON exce.id_institucion_cliente = ic.id
        LEFT JOIN niveles_area_academica niv ON exce.id_nivel = niv.id
        LEFT JOIN personas pi ON ic.id_persona = pi.id AND pi.id_tenant = ic.id_tenant
        WHERE exce.id_curso_extra = :id_curso_extra AND exce.id_tenant = :id_tenant
        ORDER BY p.primer_apellido, p.primer_nombre");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByEstudiante($id_estudiante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exce.id, exce.id_estudiante, exce.id_curso_extra, exce.fecha_inscripcion, exce.anio, exce.activo, exce.id_institucion_cliente, exce.id_nivel, niv.nombre AS nombre_nivel,
        ce.nombre AS nombre_curso
        FROM estudiantes_x_cursos_extra exce
        INNER JOIN cursos_extra ce ON exce.id_curso_extra = ce.id
        LEFT JOIN niveles_area_academica niv ON exce.id_nivel = niv.id
        WHERE exce.id_estudiante = :id_estudiante AND exce.id_tenant = :id_tenant
        ORDER BY exce.anio DESC, ce.nombre");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Inscribe un estudiante al curso.
     *
     * Valida contra la configuracion del curso antes de insertar. Las tres
     * validaciones son opcionales por diseno: fecha_limite_inscripcion,
     * edad_minima_meses y edad_maxima_meses solo bloquean si estan diligenciadas.
     * El cupo solo bloquea cuando el curso no permite sobrecupo.
     */
    public static function new()
    {
        $db = Flight::db();
        $id_estudiante = Flight::request()->data['id_estudiante'];
        $id_curso_extra = Flight::request()->data['id_curso_extra'];
        $fecha_inscripcion = Flight::request()->data['fecha_inscripcion'];
        $anio = Flight::request()->data['anio'];
        // Convenio por el que entra el estudiante. Opcional: sin el, la
        // inscripcion es particular y se cobra con la tarifa interna, que es el
        // comportamiento que ya existia. Se congela aqui porque es lo que decide
        // la tarifa y a nombre de quien se emite la cuenta, y la pertenencia del
        // nino a un colegio puede cambiar despues.
        $id_institucion_cliente = isset(Flight::request()->data['id_institucion_cliente'])
            ? Flight::request()->data['id_institucion_cliente'] : null;
        if (empty($id_institucion_cliente)) {
            $id_institucion_cliente = null;
        }

        // Nivel del estudiante dentro del curso. Va en la inscripcion y no en el
        // curso para que pueda subir de nivel sin cambiar de curso. Opcional:
        // un curso sin malla por niveles no lo necesita.
        $id_nivel = isset(Flight::request()->data['id_nivel'])
            ? Flight::request()->data['id_nivel'] : null;
        if (empty($id_nivel)) {
            $id_nivel = null;
        }

        $error = self::validarInscripcion($db, $id_estudiante, $id_curso_extra, $fecha_inscripcion, $id_institucion_cliente);
        if ($error !== null) {
            Flight::json(array('error' => $error), 400);
            return;
        }

        $idNew = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO estudiantes_x_cursos_extra(id, id_tenant, id_estudiante, id_curso_extra, fecha_inscripcion, anio, activo, id_institucion_cliente, id_nivel) 
        VALUES (:id, :id_tenant, :id_estudiante, :id_curso_extra, :fecha_inscripcion, :anio, 1, :id_institucion_cliente, :id_nivel)");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindParam(':fecha_inscripcion', $fecha_inscripcion);
        $sentence->bindParam(':anio', $anio, PDO::PARAM_INT);
        $sentence->bindValue(':id_institucion_cliente', $id_institucion_cliente);
        $sentence->bindValue(':id_nivel', $id_nivel);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    /**
     * Devuelve el mensaje de error si la inscripcion no procede, o null si esta bien.
     *
     * Se valida tambien en el back y no solo en la pantalla porque la inscripcion
     * masiva dispara una peticion por estudiante y el cupo puede agotarse entre una
     * y otra.
     */
    private static function validarInscripcion($db, $id_estudiante, $id_curso_extra, $fecha_inscripcion, $id_institucion_cliente = null)
    {
        // El convenio tiene que existir, estar activo y ser de este curso. No se
        // valida que el nino pertenezca a esa institucion: se puede inscribir a
        // alguien por un convenio aunque no este en su listado.
        if (!empty($id_institucion_cliente)) {
            $stmtConvenio = $db->prepare("
                SELECT activo FROM cursos_extra_x_instituciones_cliente
                WHERE id_curso_extra = :id_curso_extra
                  AND id_institucion_cliente = :id_institucion_cliente
                  AND id_tenant = :id_tenant
            ");
            $stmtConvenio->bindParam(':id_curso_extra', $id_curso_extra);
            $stmtConvenio->bindParam(':id_institucion_cliente', $id_institucion_cliente);
            $stmtConvenio->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtConvenio->execute();
            $convenio = $stmtConvenio->fetch(PDO::FETCH_ASSOC);

            if (!$convenio) {
                return 'La institución seleccionada no tiene convenio con este curso.';
            }
            if (empty($convenio['activo'])) {
                return 'El convenio con esa institución está inactivo.';
            }
        }

        $stmtCurso = $db->prepare("
            SELECT nombre, cupo_maximo, permite_sobrecupo, fecha_limite_inscripcion,
            edad_minima_meses, edad_maxima_meses
            FROM cursos_extra
            WHERE id = :id_curso_extra AND id_tenant = :id_tenant
        ");
        $stmtCurso->bindParam(':id_curso_extra', $id_curso_extra);
        $stmtCurso->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtCurso->execute();
        $curso = $stmtCurso->fetch(PDO::FETCH_ASSOC);

        if (!$curso) {
            return 'El curso extracurricular no existe.';
        }

        // Fecha limite de inscripcion: nula no valida.
        if (!empty($curso['fecha_limite_inscripcion']) && !empty($fecha_inscripcion)) {
            if ($fecha_inscripcion > $curso['fecha_limite_inscripcion']) {
                return 'Las inscripciones al curso cerraron el ' . $curso['fecha_limite_inscripcion'] . '.';
            }
        }

        // Rango de edad: cada extremo es opcional y se mide a la fecha de inscripcion.
        if (!empty($curso['edad_minima_meses']) || !empty($curso['edad_maxima_meses'])) {
            $stmtEdad = $db->prepare("
                SELECT TIMESTAMPDIFF(MONTH, p.fecha_nacimiento, :fecha_inscripcion) AS edad_meses,
                CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.primer_apellido, '')) AS nombre
                FROM estudiantes e
                INNER JOIN personas p ON e.id_persona = p.id
                WHERE e.id = :id_estudiante AND e.id_tenant = :id_tenant
            ");
            $stmtEdad->bindParam(':fecha_inscripcion', $fecha_inscripcion);
            $stmtEdad->bindParam(':id_estudiante', $id_estudiante);
            $stmtEdad->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtEdad->execute();
            $estudiante = $stmtEdad->fetch(PDO::FETCH_ASSOC);

            // Sin fecha de nacimiento no se puede calcular la edad: se deja pasar
            // en lugar de bloquear por un dato que falta en la ficha del estudiante.
            if ($estudiante && $estudiante['edad_meses'] !== null) {
                $edad = (int) $estudiante['edad_meses'];
                $nombre = trim($estudiante['nombre']);

                if (!empty($curso['edad_minima_meses']) && $edad < (int) $curso['edad_minima_meses']) {
                    return $nombre . ' tiene ' . $edad . ' meses y el curso es desde ' .
                           $curso['edad_minima_meses'] . ' meses.';
                }
                if (!empty($curso['edad_maxima_meses']) && $edad > (int) $curso['edad_maxima_meses']) {
                    return $nombre . ' tiene ' . $edad . ' meses y el curso es hasta ' .
                           $curso['edad_maxima_meses'] . ' meses.';
                }
            }
        }

        // Cupo: solo bloquea si el curso no permite sobrecupo.
        if (!empty($curso['cupo_maximo']) && empty($curso['permite_sobrecupo'])) {
            $stmtCupo = $db->prepare("
                SELECT COUNT(*) AS inscritos
                FROM estudiantes_x_cursos_extra
                WHERE id_curso_extra = :id_curso_extra AND activo = 1 AND id_tenant = :id_tenant
            ");
            $stmtCupo->bindParam(':id_curso_extra', $id_curso_extra);
            $stmtCupo->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtCupo->execute();
            $fila = $stmtCupo->fetch(PDO::FETCH_ASSOC);

            if ($fila && (int) $fila['inscritos'] >= (int) $curso['cupo_maximo']) {
                return 'El curso ' . $curso['nombre'] . ' ya alcanzó su cupo máximo de ' .
                       $curso['cupo_maximo'] . ' y no permite sobrecupo.';
            }
        }

        return null;
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $activo = Flight::request()->data['activo'];

        $sentence = $db->prepare("UPDATE estudiantes_x_cursos_extra SET activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM estudiantes_x_cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
                WHERE ccxce.id_estudiante_x_curso_extra = :id_inscripcion AND ccxce.id_tenant = :id_tenant
                GROUP BY ccxce.id, cpc.id, ps.nombre
            ");
            $stmtCuentas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtCuentas->bindParam(':id_inscripcion', $id);
            $stmtCuentas->execute();
            $cuentas = $stmtCuentas->fetchAll(PDO::FETCH_ASSOC);

            $anuladas = 0;
            $conPagos = [];

            $stmtAnularCuenta = $db->prepare("
                UPDATE cuentas_por_cobrar SET anulado = 1, fecha_anulacion = NOW() 
                WHERE id = :id AND (anulado = 0 OR anulado IS NULL) AND id_tenant = :id_tenant
            ");
            $stmtAnularCuenta->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

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

            $stmtAnularInscripcion = $db->prepare("UPDATE estudiantes_x_cursos_extra SET activo = 0 WHERE id = :id AND id_tenant = :id_tenant");
            $stmtAnularInscripcion->bindParam(':id', $id);
            $stmtAnularInscripcion->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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