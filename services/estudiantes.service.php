<?php
class Estudiantes
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.listado');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT e.id, e.id_persona, e.fecha_ingreso, e.activo, 
        e.alimentacion, e.permanente, e.telefono_emergencia, e.eps, e.anno,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        p.id_tipo_identificacion, ti.nombre tipo_identificacion,
        p.numero_identificacion, p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion 
        FROM estudiantes e 
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        WHERE e.id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT e.id, 
               e.id_persona, 
               e.fecha_ingreso, 
               e.activo, 
               e.alimentacion, 
               e.permanente,
               e.telefono_emergencia,
               e.eps,
               e.anno,
               p.primer_nombre, 
               p.segundo_nombre,
               p.primer_apellido, 
               p.segundo_apellido, 
               p.id_tipo_identificacion, 
               ti.nombre AS tipo_identificacion,
               p.numero_identificacion, 
               p.fecha_nacimiento, 
               TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
               p.id_genero, 
               g.nombre AS nombre_genero, 
               p.direccion,
               grp.id AS id_grupo, 
               grp.nombre AS nombre_grupo,
               CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) AS nombre_completo
        FROM estudiantes e 
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        LEFT JOIN estudiantes_x_grupos eg ON e.id = eg.id_estudiante AND eg.activo = 1
        LEFT JOIN grupos grp ON eg.id_grupo = grp.id
        WHERE e.id = :id AND e.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'estudiantes.administrar');

            $db = Flight::db();

            $id_persona = Flight::request()->data['id_persona'];
            $fecha_ingreso = Flight::request()->data['fecha_ingreso'];
            $alimentacion = isset(Flight::request()->data['alimentacion']) ? Flight::request()->data['alimentacion'] : 0;
            $permanente = isset(Flight::request()->data['permanente']) ? Flight::request()->data['permanente'] : 0;
            $telefono_emergencia = isset(Flight::request()->data['telefono_emergencia']) ? Flight::request()->data['telefono_emergencia'] : '';
            $eps = isset(Flight::request()->data['eps']) ? Flight::request()->data['eps'] : '';
            $anno = isset(Flight::request()->data['anno']) ? Flight::request()->data['anno'] : date('Y');

            error_log("Datos recibidos para crear estudiante: id_persona=$id_persona, fecha_ingreso=$fecha_ingreso, alimentacion=$alimentacion, permanente=$permanente, telefono_emergencia=$telefono_emergencia, eps=$eps, anno=$anno");

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO estudiantes(
            id,
            id_tenant,
            id_persona, 
            fecha_ingreso, 
            activo, 
            alimentacion, 
            permanente,
            telefono_emergencia, 
            eps, 
            anno
        ) VALUES (
            :id,
            :id_tenant,
            :id_persona, 
            :fecha_ingreso, 
            1, 
            :alimentacion, 
            :permanente,
            :telefono_emergencia, 
            :eps, 
            :anno
        )");

            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':fecha_ingreso', $fecha_ingreso);
            $sentence->bindParam(':alimentacion', $alimentacion);
            $sentence->bindParam(':permanente', $permanente);
            $sentence->bindParam(':telefono_emergencia', $telefono_emergencia);
            $sentence->bindParam(':eps', $eps);
            $sentence->bindParam(':anno', $anno);

            $sentence->execute();

            $id = $idNew;

            if ($id == 0) {
                error_log("Error: El ID insertado es 0. Verifica la ejecución del INSERT.");
                Flight::json(array('error' => 'No se pudo crear el estudiante. Intente de nuevo.'), 500);
                return;
            }

            error_log("ID estudiante insertado: $id");

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método new de estudiantes: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.administrar');

        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_persona = Flight::request()->data['id_persona'];
        $fecha_ingreso = Flight::request()->data['fecha_ingreso'];
        $activo = Flight::request()->data['activo'];
        $alimentacion = isset(Flight::request()->data['alimentacion']) ? Flight::request()->data['alimentacion'] : 0;
        $permanente = isset(Flight::request()->data['permanente']) ? Flight::request()->data['permanente'] : 0;
        $telefono_emergencia = isset(Flight::request()->data['telefono_emergencia']) ? Flight::request()->data['telefono_emergencia'] : '';
        $eps = isset(Flight::request()->data['eps']) ? Flight::request()->data['eps'] : '';
        $anno = isset(Flight::request()->data['anno']) ? Flight::request()->data['anno'] : date('Y');

        $sentence = $db->prepare("UPDATE estudiantes SET 
                                id_persona = :id_persona, 
                                fecha_ingreso = :fecha_ingreso, 
                                activo = :activo,
                                alimentacion = :alimentacion,
                                permanente = :permanente,
                                telefono_emergencia = :telefono_emergencia,
                                eps = :eps,
                                anno = :anno
                                WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindParam(':fecha_ingreso', $fecha_ingreso);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':alimentacion', $alimentacion);
        $sentence->bindParam(':permanente', $permanente);
        $sentence->bindParam(':telefono_emergencia', $telefono_emergencia);
        $sentence->bindParam(':eps', $eps);
        $sentence->bindParam(':anno', $anno);
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM estudiantes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function getByEstudiante($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exg.id, exg.anio, exg.id_estudiante, 
                                 p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
                                 exg.id_grupo, g.nombre AS nombre_grupo, e.activo, e.alimentacion,
                                 e.telefono_emergencia, e.eps, e.fecha_ingreso, e.anno
                                 FROM estudiantes_x_grupos exg
                                 INNER JOIN estudiantes e ON exg.id_estudiante = e.id 
                                 INNER JOIN personas p ON e.id_persona = p.id 
                                 INNER JOIN grupos g ON exg.id_grupo = g.id 
                                 WHERE exg.id_estudiante = :id AND exg.activo = 1 AND exg.id_tenant = :id_tenant
                                 ORDER BY g.orden");

        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exg.id, exg.anio, exg.id_estudiante, 
                                 p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
                                 exg.id_grupo, g.nombre nombre_grupo, e.activo, e.alimentacion,
                                 e.telefono_emergencia, e.eps, e.anno
                                 FROM estudiantes_x_grupos exg
                                 INNER JOIN estudiantes e ON exg.id_estudiante = e.id 
                                 INNER JOIN personas p ON e.id_persona = p.id 
                                 INNER JOIN grupos g ON exg.id_grupo = g.id 
                                 WHERE e.activo = 1 AND exg.activo = 1 AND exg.id_tenant = :id_tenant
                                 ORDER BY g.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function verificarDuplicados()
    {
        $db = Flight::db();
        $id_persona = Flight::request()->data['id_persona'];
        error_log("Datos recibidos para crear verificarDuplicados: id_persona=$id_persona");

        $sentence = $db->prepare("SELECT COUNT(*) as total FROM estudiantes WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();

        Flight::json(array('existe' => $response['total'] > 0));
    }

    public static function getReporteCompleto()
    {
        try {
            $db = Flight::db();

            /* ========================================================
               Variable: año académico actual
               ======================================================== */
            $db->exec("SET @anio_actual = (SELECT valor_texto FROM configuracion_global WHERE clave = 'anio_academico_actual' AND id_tenant = " . TenantContext::id() . " LIMIT 1)");

            /* ========================================================
               Tabla temporal: cobrado por persona, concepto y periodo
               clasif y categ se resuelven por CÓDIGO (estable ante el
               cambio de PK INT -> UUID de las tablas de catálogo).
               period sigue siendo entero (periodicidad_cobro no cambió).
               ======================================================== */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cobrado");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_cobrado AS
                SELECT 
                    cxc.id_persona,
                    cl.codigo AS clasif,
                    ps.id_periodicidad_cobro AS period,
                    cat.codigo AS categ,
                    SUM(CASE WHEN YEAR(cxc.fecha) = @anio_actual THEN cxc.valor ELSE 0 END) AS cobrado_actual,
                    SUM(CASE WHEN YEAR(cxc.fecha) < @anio_actual THEN cxc.valor ELSE 0 END) AS cobrado_anterior
                FROM cuentas_por_cobrar cxc
                INNER JOIN productos_servicios ps ON cxc.id_producto_servicio = ps.id
                LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios AND cl.id_tenant = ps.id_tenant
                LEFT JOIN categoria_productos_servicios cat ON cat.id = ps.id_categoria_productos_servicios AND cat.id_tenant = ps.id_tenant
                WHERE cxc.anulado = 0 AND cxc.id_tenant = " . TenantContext::id() . "
                GROUP BY cxc.id_persona, clasif, period, categ
            ");

            /* ========================================================
               Tabla temporal: pagado por persona, concepto y periodo
               ======================================================== */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_pagado");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_pagado AS
                SELECT 
                    cxc.id_persona,
                    cl.codigo AS clasif,
                    ps.id_periodicidad_cobro AS period,
                    cat.codigo AS categ,
                    SUM(CASE WHEN YEAR(cxc.fecha) = @anio_actual THEN cp.valor_aplicado ELSE 0 END) AS pagado_actual,
                    SUM(CASE WHEN YEAR(cxc.fecha) < @anio_actual THEN cp.valor_aplicado ELSE 0 END) AS pagado_anterior
                FROM cuenta_pagada cp
                INNER JOIN cuentas_por_cobrar cxc ON cp.id_cuenta_por_cobrar = cxc.id
                INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                INNER JOIN productos_servicios ps ON cxc.id_producto_servicio = ps.id
                LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios AND cl.id_tenant = ps.id_tenant
                LEFT JOIN categoria_productos_servicios cat ON cat.id = ps.id_categoria_productos_servicios AND cat.id_tenant = ps.id_tenant
                WHERE cxc.anulado = 0 AND pr.anulado = 0 AND cxc.id_tenant = " . TenantContext::id() . "
                GROUP BY cxc.id_persona, clasif, period, categ
            ");

            /* ========================================================
               Tabla temporal: pivoteo por persona con las 36 columnas
               Concepto = (clasif codigo, period entero, categ codigo)
               ======================================================== */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cartera");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_cartera AS
                SELECT 
                    id_persona,
                    /* MATRÍCULA - Año actual (ACADEMICO, period=1, MENSUAL) */
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) AS matricula_cobrado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS matricula_pagado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS matricula_saldo_actual,
                    /* MATRÍCULA - Años anteriores */
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) AS matricula_cobrado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS matricula_pagado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS matricula_saldo_anterior,

                    /* PENSIÓN - Año actual (ACADEMICO, period=2, MENSUAL) */
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) AS pension_cobrado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS pension_pagado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS pension_saldo_actual,
                    /* PENSIÓN - Años anteriores */
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) AS pension_cobrado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS pension_pagado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS pension_saldo_anterior,

                    /* ALMUERZO - Año actual (ALIMENTACION, period=2, MENSUAL) */
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) AS almuerzo_cobrado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS almuerzo_pagado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS almuerzo_saldo_actual,
                    /* ALMUERZO - Años anteriores */
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) AS almuerzo_cobrado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS almuerzo_pagado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS almuerzo_saldo_anterior,

                    /* ONCES - Año actual (ALIMENTACION, period=3, EXTRA) */
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) AS onces_cobrado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS onces_pagado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS onces_saldo_actual,
                    /* ONCES - Años anteriores */
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) AS onces_cobrado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS onces_pagado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS onces_saldo_anterior,

                    /* HORAS EXTRAS - Año actual (EXTRA_ACADEMICO, period=3, EXTRA) */
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) AS horas_extras_cobrado_actual,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS horas_extras_pagado_actual,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS horas_extras_saldo_actual,
                    /* HORAS EXTRAS - Años anteriores */
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) AS horas_extras_cobrado_anterior,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS horas_extras_pagado_anterior,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS horas_extras_saldo_anterior,

                    /* VESTUARIO - Año actual (VESTUARIO, period=4, EXTRA) */
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) AS vestuario_cobrado_actual,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS vestuario_pagado_actual,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS vestuario_saldo_actual,
                    /* VESTUARIO - Años anteriores */
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) AS vestuario_cobrado_anterior,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS vestuario_pagado_anterior,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS vestuario_saldo_anterior
                FROM (
                    SELECT id_persona, clasif, period, categ, cobrado_actual, cobrado_anterior, 0 AS pagado_actual, 0 AS pagado_anterior
                    FROM tmp_cobrado
                    UNION ALL
                    SELECT id_persona, clasif, period, categ, 0 AS cobrado_actual, 0 AS cobrado_anterior, pagado_actual, pagado_anterior
                    FROM tmp_pagado
                ) combined
                GROUP BY id_persona
            ");

            /* ========================================================
               Query principal con LEFT JOIN a tmp_cartera
               ======================================================== */
            $sentence = $db->prepare("
                SELECT 
                    e.id,
                    e.id AS id_estudiante,
                    e.id_persona,
                    e.fecha_ingreso,
                    e.activo,
                    e.alimentacion,
                    e.telefono_emergencia,
                    e.eps,
                    e.anno,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
                    p.id_tipo_identificacion,
                    ti.nombre AS tipo_identificacion,
                    p.numero_identificacion,
                    p.fecha_nacimiento,
                    TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                    p.id_genero,
                    g.nombre AS nombre_genero,
                    p.direccion,
                    IFNULL(grp.id, 0) AS id_grupo,
                    IFNULL(grp.nombre, 'Sin grupo') AS nombre_grupo,
                    CASE WHEN e.activo = 1 THEN 'Activo' ELSE 'Inactivo' END AS estado,
                    CASE WHEN e.alimentacion = 1 THEN 'Sí' ELSE 'No' END AS alimentacion_texto,
                    e.permanente,
                    CASE WHEN e.permanente = 1 THEN 'Sí' ELSE 'No' END AS permanente_texto,

                    /* Contrato */
                    (SELECT cm.id 
                        FROM contratos_matricula cm 
                        WHERE cm.id_estudiante = e.id 
                        AND cm.anio = @anio_actual
                        AND cm.activo = 1 
                        LIMIT 1) AS id_contrato,
                    CASE 
                        WHEN (SELECT cm2.id FROM contratos_matricula cm2 
                              WHERE cm2.id_estudiante = e.id AND cm2.anio = @anio_actual AND cm2.activo = 1 LIMIT 1) IS NULL THEN 'Sin contrato'
                        WHEN (SELECT cm3.firmado FROM contratos_matricula cm3 
                              WHERE cm3.id_estudiante = e.id AND cm3.anio = @anio_actual AND cm3.activo = 1 LIMIT 1) = 1 THEN 'Firmado'
                        ELSE 'Pendiente'
                    END AS estado_contrato,
                    IFNULL((SELECT cm4.valor_matricula FROM contratos_matricula cm4 
                        WHERE cm4.id_estudiante = e.id AND cm4.anio = @anio_actual AND cm4.activo = 1 LIMIT 1), 0) AS valor_matricula,
                    IFNULL((SELECT cm5.valor_pension FROM contratos_matricula cm5 
                        WHERE cm5.id_estudiante = e.id AND cm5.anio = @anio_actual AND cm5.activo = 1 LIMIT 1), 0) AS valor_pension,

                    /* Matrícula mes actual */
                    IFNULL((SELECT cmv.valor 
                        FROM contratos_matricula_valores cmv
                        INNER JOIN contratos_matricula cm6 ON cmv.id_contrato_matricula = cm6.id
                        INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
                        WHERE cm6.id_estudiante = e.id AND cm6.anio = @anio_actual AND cm6.activo = 1
                        AND ps.id_clasificacion_productos_servicios IN (SELECT cps.id FROM clasificacion_productos_servicios cps WHERE cps.codigo = 'ACADEMICO' AND cps.id_tenant = ps.id_tenant) AND ps.id_periodicidad_cobro = 1 AND ps.id_categoria_productos_servicios IN (SELECT cat.id FROM categoria_productos_servicios cat WHERE cat.codigo = 'MENSUAL' AND cat.id_tenant = ps.id_tenant)
                        AND MONTH(cmv.fecha) = MONTH(CURDATE()) AND YEAR(cmv.fecha) = YEAR(CURDATE())
                        LIMIT 1), 0) AS matricula_mes_actual,

                    /* Pensión mes actual */
                    IFNULL((SELECT cmv2.valor 
                        FROM contratos_matricula_valores cmv2
                        INNER JOIN contratos_matricula cm7 ON cmv2.id_contrato_matricula = cm7.id
                        INNER JOIN productos_servicios ps2 ON cmv2.id_producto_servicio = ps2.id
                        WHERE cm7.id_estudiante = e.id AND cm7.anio = @anio_actual AND cm7.activo = 1
                        AND ps2.id_clasificacion_productos_servicios IN (SELECT cps.id FROM clasificacion_productos_servicios cps WHERE cps.codigo = 'ACADEMICO' AND cps.id_tenant = ps2.id_tenant) AND ps2.id_periodicidad_cobro = 2 AND ps2.id_categoria_productos_servicios IN (SELECT cat.id FROM categoria_productos_servicios cat WHERE cat.codigo = 'MENSUAL' AND cat.id_tenant = ps2.id_tenant)
                        AND MONTH(cmv2.fecha) = MONTH(CURDATE()) AND YEAR(cmv2.fecha) = YEAR(CURDATE())
                        LIMIT 1), 0) AS pension_mes_actual,

                    /* 36 columnas de cartera desde tmp_cartera */
                    IFNULL(tc.matricula_cobrado_actual, 0) AS matricula_cobrado_actual,
                    IFNULL(tc.matricula_pagado_actual, 0) AS matricula_pagado_actual,
                    IFNULL(tc.matricula_saldo_actual, 0) AS matricula_saldo_actual,
                    IFNULL(tc.matricula_cobrado_anterior, 0) AS matricula_cobrado_anterior,
                    IFNULL(tc.matricula_pagado_anterior, 0) AS matricula_pagado_anterior,
                    IFNULL(tc.matricula_saldo_anterior, 0) AS matricula_saldo_anterior,

                    IFNULL(tc.pension_cobrado_actual, 0) AS pension_cobrado_actual,
                    IFNULL(tc.pension_pagado_actual, 0) AS pension_pagado_actual,
                    IFNULL(tc.pension_saldo_actual, 0) AS pension_saldo_actual,
                    IFNULL(tc.pension_cobrado_anterior, 0) AS pension_cobrado_anterior,
                    IFNULL(tc.pension_pagado_anterior, 0) AS pension_pagado_anterior,
                    IFNULL(tc.pension_saldo_anterior, 0) AS pension_saldo_anterior,

                    IFNULL(tc.almuerzo_cobrado_actual, 0) AS almuerzo_cobrado_actual,
                    IFNULL(tc.almuerzo_pagado_actual, 0) AS almuerzo_pagado_actual,
                    IFNULL(tc.almuerzo_saldo_actual, 0) AS almuerzo_saldo_actual,
                    IFNULL(tc.almuerzo_cobrado_anterior, 0) AS almuerzo_cobrado_anterior,
                    IFNULL(tc.almuerzo_pagado_anterior, 0) AS almuerzo_pagado_anterior,
                    IFNULL(tc.almuerzo_saldo_anterior, 0) AS almuerzo_saldo_anterior,

                    IFNULL(tc.onces_cobrado_actual, 0) AS onces_cobrado_actual,
                    IFNULL(tc.onces_pagado_actual, 0) AS onces_pagado_actual,
                    IFNULL(tc.onces_saldo_actual, 0) AS onces_saldo_actual,
                    IFNULL(tc.onces_cobrado_anterior, 0) AS onces_cobrado_anterior,
                    IFNULL(tc.onces_pagado_anterior, 0) AS onces_pagado_anterior,
                    IFNULL(tc.onces_saldo_anterior, 0) AS onces_saldo_anterior,

                    IFNULL(tc.horas_extras_cobrado_actual, 0) AS horas_extras_cobrado_actual,
                    IFNULL(tc.horas_extras_pagado_actual, 0) AS horas_extras_pagado_actual,
                    IFNULL(tc.horas_extras_saldo_actual, 0) AS horas_extras_saldo_actual,
                    IFNULL(tc.horas_extras_cobrado_anterior, 0) AS horas_extras_cobrado_anterior,
                    IFNULL(tc.horas_extras_pagado_anterior, 0) AS horas_extras_pagado_anterior,
                    IFNULL(tc.horas_extras_saldo_anterior, 0) AS horas_extras_saldo_anterior,

                    IFNULL(tc.vestuario_cobrado_actual, 0) AS vestuario_cobrado_actual,
                    IFNULL(tc.vestuario_pagado_actual, 0) AS vestuario_pagado_actual,
                    IFNULL(tc.vestuario_saldo_actual, 0) AS vestuario_saldo_actual,
                    IFNULL(tc.vestuario_cobrado_anterior, 0) AS vestuario_cobrado_anterior,
                    IFNULL(tc.vestuario_pagado_anterior, 0) AS vestuario_pagado_anterior,
                    IFNULL(tc.vestuario_saldo_anterior, 0) AS vestuario_saldo_anterior,

                    IFNULL((
                        SELECT GROUP_CONCAT(
                            CONCAT(
                                IFNULL(pa.primer_nombre, ''), ' ', 
                                IFNULL(pa.segundo_nombre, ''), ' ', 
                                IFNULL(pa.primer_apellido, ''), ' ', 
                                IFNULL(pa.segundo_apellido, ''), ' - ', 
                                ta.nombre
                            ) SEPARATOR '; '
                        )
                        FROM acudientes a
                        INNER JOIN personas pa ON a.id_persona = pa.id
                        INNER JOIN tipos_acudiente ta ON a.id_tipo_acudiente = ta.id
                        WHERE a.id_estudiante = e.id
                    ), 'Sin acudientes registrados') AS acudientes,

                    /* Documentos obligatorios pendientes */
                    IFNULL((
                        SELECT COUNT(*)
                        FROM tipos_personas_documentos tpd
                        INNER JOIN tipos_documentos td ON tpd.id_tipo_documento = td.id
                        INNER JOIN tipos_personas tp ON tp.id = tpd.id_tipo_persona AND tp.id_tenant = tpd.id_tenant
                        WHERE tp.codigo = 'estudiante'
                        AND tpd.id_tenant = " . TenantContext::id() . "
                        AND tpd.obligatorio = 1
                        AND td.activo = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM documentos_personas dp
                            WHERE dp.id_persona = e.id_persona
                            AND dp.id_tipo_documento = tpd.id_tipo_documento
                            AND dp.activo = 1
                        )
                    ), 0) AS docs_pendientes_cantidad,

                    IFNULL((
                        SELECT GROUP_CONCAT(td2.nombre ORDER BY tpd2.orden ASC SEPARATOR ', ')
                        FROM tipos_personas_documentos tpd2
                        INNER JOIN tipos_documentos td2 ON tpd2.id_tipo_documento = td2.id
                        INNER JOIN tipos_personas tp2 ON tp2.id = tpd2.id_tipo_persona AND tp2.id_tenant = tpd2.id_tenant
                        WHERE tp2.codigo = 'estudiante'
                        AND tpd2.id_tenant = " . TenantContext::id() . "
                        AND tpd2.obligatorio = 1
                        AND td2.activo = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM documentos_personas dp2
                            WHERE dp2.id_persona = e.id_persona
                            AND dp2.id_tipo_documento = tpd2.id_tipo_documento
                            AND dp2.activo = 1
                        )
                    ), '') AS docs_pendientes_detalle

                FROM estudiantes e
                INNER JOIN personas p ON e.id_persona = p.id
                INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
                LEFT JOIN generos g ON p.id_genero = g.id
                LEFT JOIN estudiantes_x_grupos eg ON e.id = eg.id_estudiante AND eg.activo = 1
                LEFT JOIN grupos grp ON eg.id_grupo = grp.id
                LEFT JOIN tmp_cartera tc ON tc.id_persona = e.id_persona
                WHERE e.id_tenant = :id_tenant
                ORDER BY grp.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido
            ");

            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            if (is_array($response)) {
                foreach ($response as &$row) {
                    if (isset($row['nombre_completo'])) {
                        $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
                    }
                    $row['telefono_emergencia'] = isset($row['telefono_emergencia']) && $row['telefono_emergencia'] ? $row['telefono_emergencia'] : '';
                    $row['eps'] = isset($row['eps']) && $row['eps'] ? $row['eps'] : '';
                    $row['direccion'] = isset($row['direccion']) && $row['direccion'] ? $row['direccion'] : '';
                }
            }

            /* Limpiar temporales */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cobrado");
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_pagado");
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cartera");

            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en getReporteCompleto: " . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener el reporte: ' . $e->getMessage()), 500);
        }
    }

    public static function actualizacionMasiva()
    {
        try {
            $db = Flight::db();
            $ids = Flight::request()->data['ids'];
            $campos = Flight::request()->data['campos'];

            if (empty($ids) || empty($campos)) {
                Flight::json(array('success' => false, 'message' => 'Datos incompletos'), 400);
                return;
            }

            $setClauses = [];
            $params = [];

            if (isset($campos['activo'])) {
                $setClauses[] = 'activo = ?';
                $params[] = $campos['activo'];
            }
            if (isset($campos['anno'])) {
                $setClauses[] = 'anno = ?';
                $params[] = $campos['anno'];
            }
            if (isset($campos['alimentacion'])) {
                $setClauses[] = 'alimentacion = ?';
                $params[] = $campos['alimentacion'];
            }
            if (isset($campos['permanente'])) {
                $setClauses[] = 'permanente = ?';
                $params[] = $campos['permanente'];
            }

            if (empty($setClauses)) {
                Flight::json(array('success' => false, 'message' => 'No hay campos para actualizar'), 400);
                return;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE estudiantes SET " . implode(', ', $setClauses) . " WHERE id IN ($placeholders) AND id_tenant = ?";

            $sentence = $db->prepare($sql);

            $paramIndex = 1;
            foreach ($params as $value) {
                $sentence->bindValue($paramIndex++, $value);
            }
            foreach ($ids as $id) {
                $sentence->bindValue($paramIndex++, $id, PDO::PARAM_STR);
            }
            $sentence->bindValue($paramIndex++, TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();
            $actualizados = $sentence->rowCount();

            Flight::json(array(
                'success' => true,
                'actualizados' => $actualizados,
                'message' => 'Actualización completada'
            ));

        } catch (Exception $e) {
            error_log("Error en actualizacionMasiva: " . $e->getMessage());
            Flight::json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    /**
     * Registro rápido desde módulo de asistencia.
     * Crea persona del niño (o reutiliza), estudiante, asigna grupo,
     * crea persona del acudiente (o reutiliza), crea acudiente.
     * NO registra asistencia — eso lo hace el flujo normal del módulo.
     */
    public static function registroRapido()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'estudiantes.administrar');

            $db = Flight::db();
            $db->beginTransaction();

            $data = Flight::request()->data;

            // === DATOS DEL NIÑO ===
            $nino_id_tipo_identificacion = $data['nino_id_tipo_identificacion'];
            $nino_numero_identificacion = $data['nino_numero_identificacion'];
            $nino_primer_nombre = $data['nino_primer_nombre'];
            $nino_primer_apellido = $data['nino_primer_apellido'];
            $nino_segundo_nombre = isset($data['nino_segundo_nombre']) ? $data['nino_segundo_nombre'] : null;
            $nino_segundo_apellido = isset($data['nino_segundo_apellido']) ? $data['nino_segundo_apellido'] : null;
            $nino_fecha_nacimiento = isset($data['nino_fecha_nacimiento']) ? $data['nino_fecha_nacimiento'] : null;
            $id_grupo = $data['id_grupo'];

            // === DATOS DEL ACUDIENTE ===
            $acud_id_tipo_identificacion = $data['acud_id_tipo_identificacion'];
            $acud_numero_identificacion = $data['acud_numero_identificacion'];
            $acud_primer_nombre = $data['acud_primer_nombre'];
            $acud_primer_apellido = $data['acud_primer_apellido'];
            $acud_segundo_nombre = isset($data['acud_segundo_nombre']) ? $data['acud_segundo_nombre'] : null;
            $acud_segundo_apellido = isset($data['acud_segundo_apellido']) ? $data['acud_segundo_apellido'] : null;
            $acud_telefono = isset($data['acud_telefono']) ? $data['acud_telefono'] : null;
            $id_tipo_acudiente = $data['id_tipo_acudiente'];

            // ============================================================
            // 1. PERSONA DEL NIÑO: buscar o crear
            // ============================================================
            $stmt = $db->prepare("SELECT id FROM personas WHERE id_tipo_identificacion = :tipo AND numero_identificacion = :numero AND id_tenant = :id_tenant");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':tipo', $nino_id_tipo_identificacion);
            $stmt->bindParam(':numero', $nino_numero_identificacion);
            $stmt->execute();
            $personaNino = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($personaNino) {
                $id_persona_nino = $personaNino['id'];
            } else {
                $idPersonaNino = Uuid::generar();
                $stmt = $db->prepare("INSERT INTO personas (id, id_tenant, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, id_tipo_identificacion, numero_identificacion, fecha_nacimiento, nacionalidad, ocupacion) 
                    VALUES (:id, :id_tenant, :primer_nombre, :segundo_nombre, :primer_apellido, :segundo_apellido, :id_tipo_identificacion, :numero_identificacion, :fecha_nacimiento, 'Colombiana', 'Estudiante')");
                $stmt->bindValue(':id', $idPersonaNino);
                $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmt->bindParam(':primer_nombre', $nino_primer_nombre);
                $stmt->bindParam(':segundo_nombre', $nino_segundo_nombre);
                $stmt->bindParam(':primer_apellido', $nino_primer_apellido);
                $stmt->bindParam(':segundo_apellido', $nino_segundo_apellido);
                $stmt->bindParam(':id_tipo_identificacion', $nino_id_tipo_identificacion);
                $stmt->bindParam(':numero_identificacion', $nino_numero_identificacion);
                $stmt->bindParam(':fecha_nacimiento', $nino_fecha_nacimiento);
                $stmt->execute();
                $id_persona_nino = $idPersonaNino;

                if ($id_persona_nino == 0) {
                    $db->rollBack();
                    Flight::json(array('error' => 'No se pudo crear la persona del niño'), 500);
                    return;
                }
            }

            // ============================================================
            // 2. ESTUDIANTE: verificar que no exista, crear
            // ============================================================
            $stmt = $db->prepare("SELECT id, activo FROM estudiantes WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_persona', $id_persona_nino);
            $stmt->execute();
            $estudianteExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            $estudiante_ya_existia = false;
            if ($estudianteExistente) {
                $id_estudiante = $estudianteExistente['id'];
                $estudiante_ya_existia = true;

                if ($estudianteExistente['activo'] == 0) {
                    $db->rollBack();
                    Flight::json(array('error' => 'Este estudiante existe pero está inactivo. Active el estudiante primero desde el módulo de estudiantes.'), 400);
                    return;
                }
            } else {
                $fecha_hoy = date('Y-m-d');
                $anno_actual = date('Y');
                $idEstudiante = Uuid::generar();
                $stmt = $db->prepare("INSERT INTO estudiantes (id, id_tenant, id_persona, fecha_ingreso, activo, alimentacion, permanente, telefono_emergencia, eps, anno) 
                    VALUES (:id, :id_tenant, :id_persona, :fecha_ingreso, 1, 0, 0, '', '', :anno)");
                $stmt->bindValue(':id', $idEstudiante);
                $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmt->bindParam(':id_persona', $id_persona_nino);
                $stmt->bindParam(':fecha_ingreso', $fecha_hoy);
                $stmt->bindParam(':anno', $anno_actual);
                $stmt->execute();
                $id_estudiante = $idEstudiante;

                if ($id_estudiante == 0) {
                    $db->rollBack();
                    Flight::json(array('error' => 'No se pudo crear el estudiante'), 500);
                    return;
                }
            }

            // ============================================================
            // 3. ASIGNAR GRUPO (si no tiene uno activo)
            // ============================================================
            $stmt = $db->prepare("SELECT id FROM estudiantes_x_grupos WHERE id_estudiante = :id_estudiante AND activo = 1 AND id_tenant = :id_tenant");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_estudiante', $id_estudiante);
            $stmt->execute();
            $grupoActual = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$grupoActual) {
                $anno_actual = date('Y');
                $stmt = $db->prepare("INSERT INTO estudiantes_x_grupos (id_tenant, id_estudiante, id_grupo, anio, activo) VALUES (:id_tenant, :id_estudiante, :id_grupo, :anio, 1)");
                $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmt->bindParam(':id_estudiante', $id_estudiante);
                $stmt->bindParam(':id_grupo', $id_grupo);
                $stmt->bindParam(':anio', $anno_actual);
                $stmt->execute();
            }

            // ============================================================
            // 4. PERSONA DEL ACUDIENTE: buscar o crear
            // ============================================================
            $stmt = $db->prepare("SELECT id FROM personas WHERE id_tipo_identificacion = :tipo AND numero_identificacion = :numero AND id_tenant = :id_tenant");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':tipo', $acud_id_tipo_identificacion);
            $stmt->bindParam(':numero', $acud_numero_identificacion);
            $stmt->execute();
            $personaAcud = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($personaAcud) {
                $id_persona_acudiente = $personaAcud['id'];
                if ($acud_telefono) {
                    $stmtTel = $db->prepare("UPDATE personas SET telefono = :telefono WHERE id = :id AND id_tenant = :id_tenant AND (telefono IS NULL OR telefono = '')");
                    $stmtTel->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtTel->bindParam(':telefono', $acud_telefono);
                    $stmtTel->bindParam(':id', $id_persona_acudiente);
                    $stmtTel->execute();
                }
            } else {
                $idPersonaAcud = Uuid::generar();
                $stmt = $db->prepare("INSERT INTO personas (id, id_tenant, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, id_tipo_identificacion, numero_identificacion, telefono, nacionalidad) 
                    VALUES (:id, :id_tenant, :primer_nombre, :segundo_nombre, :primer_apellido, :segundo_apellido, :id_tipo_identificacion, :numero_identificacion, :telefono, 'Colombiana')");
                $stmt->bindValue(':id', $idPersonaAcud);
                $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmt->bindParam(':primer_nombre', $acud_primer_nombre);
                $stmt->bindParam(':segundo_nombre', $acud_segundo_nombre);
                $stmt->bindParam(':primer_apellido', $acud_primer_apellido);
                $stmt->bindParam(':segundo_apellido', $acud_segundo_apellido);
                $stmt->bindParam(':id_tipo_identificacion', $acud_id_tipo_identificacion);
                $stmt->bindParam(':numero_identificacion', $acud_numero_identificacion);
                $stmt->bindParam(':telefono', $acud_telefono);
                $stmt->execute();
                $id_persona_acudiente = $idPersonaAcud;

                if ($id_persona_acudiente == 0) {
                    $db->rollBack();
                    Flight::json(array('error' => 'No se pudo crear la persona del acudiente'), 500);
                    return;
                }
            }

            // ============================================================
            // 5. ACUDIENTE: verificar duplicado y crear
            // ============================================================
            $stmt = $db->prepare("SELECT id FROM acudientes WHERE id_estudiante = :id_estudiante AND id_persona = :id_persona AND id_tipo_acudiente = :id_tipo_acudiente AND id_tenant = :id_tenant");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_estudiante', $id_estudiante);
            $stmt->bindParam(':id_persona', $id_persona_acudiente);
            $stmt->bindParam(':id_tipo_acudiente', $id_tipo_acudiente);
            $stmt->execute();
            $acudienteExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            $id_acudiente = 0;
            if ($acudienteExistente) {
                $id_acudiente = $acudienteExistente['id'];
            } else {
                $idAcudiente = Uuid::generar();
                $stmt = $db->prepare("INSERT INTO acudientes (id, id_tenant, id_estudiante, id_persona, id_tipo_acudiente, es_responsable_pago, autorizado_recoger, autorizado_sistema, activo) 
                    VALUES (:id, :id_tenant, :id_estudiante, :id_persona, :id_tipo_acudiente, 1, 1, 1, 1)");
                $stmt->bindValue(':id', $idAcudiente);
                $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmt->bindParam(':id_estudiante', $id_estudiante);
                $stmt->bindParam(':id_persona', $id_persona_acudiente);
                $stmt->bindParam(':id_tipo_acudiente', $id_tipo_acudiente);
                $stmt->execute();
                $id_acudiente = $idAcudiente;
            }

            $db->commit();

            Flight::json(array(
                'id_estudiante' => $id_estudiante,
                'id_persona_nino' => $id_persona_nino,
                'id_persona_acudiente' => $id_persona_acudiente,
                'id_acudiente' => $id_acudiente,
                'estudiante_ya_existia' => $estudiante_ya_existia,
                'nombre_estudiante' => trim($nino_primer_nombre . ' ' . $nino_primer_apellido)
            ));

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en registroRapido: " . $e->getMessage());
            Flight::json(array('error' => 'Error en registro rápido: ' . $e->getMessage()), 500);
        }
    }

    /**
     * Reporte para recordatorios generales.
     * Igual a getReporteCompleto pero agrega último recordatorio general
     * y devuelve acudientes desglosados con teléfono y correo.
     */
    public static function getReporteRecordatorios()
    {
        try {
            $db = Flight::db();

            /* Variable: año académico actual */
            $db->exec("SET @anio_actual = (SELECT valor_texto FROM configuracion_global WHERE clave = 'anio_academico_actual' AND id_tenant = " . TenantContext::id() . " LIMIT 1)");

            /* Tabla temporal: cobrado */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cobrado");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_cobrado AS
                SELECT 
                    cxc.id_persona,
                    cl.codigo AS clasif,
                    ps.id_periodicidad_cobro AS period,
                    cat.codigo AS categ,
                    SUM(CASE WHEN YEAR(cxc.fecha) = @anio_actual THEN cxc.valor ELSE 0 END) AS cobrado_actual,
                    SUM(CASE WHEN YEAR(cxc.fecha) < @anio_actual THEN cxc.valor ELSE 0 END) AS cobrado_anterior
                FROM cuentas_por_cobrar cxc
                INNER JOIN productos_servicios ps ON cxc.id_producto_servicio = ps.id
                LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios AND cl.id_tenant = ps.id_tenant
                LEFT JOIN categoria_productos_servicios cat ON cat.id = ps.id_categoria_productos_servicios AND cat.id_tenant = ps.id_tenant
                WHERE cxc.anulado = 0 AND cxc.id_tenant = " . TenantContext::id() . "
                GROUP BY cxc.id_persona, clasif, period, categ
            ");

            /* Tabla temporal: pagado */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_pagado");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_pagado AS
                SELECT 
                    cxc.id_persona,
                    cl.codigo AS clasif,
                    ps.id_periodicidad_cobro AS period,
                    cat.codigo AS categ,
                    SUM(CASE WHEN YEAR(cxc.fecha) = @anio_actual THEN cp.valor_aplicado ELSE 0 END) AS pagado_actual,
                    SUM(CASE WHEN YEAR(cxc.fecha) < @anio_actual THEN cp.valor_aplicado ELSE 0 END) AS pagado_anterior
                FROM cuenta_pagada cp
                INNER JOIN cuentas_por_cobrar cxc ON cp.id_cuenta_por_cobrar = cxc.id
                INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                INNER JOIN productos_servicios ps ON cxc.id_producto_servicio = ps.id
                LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios AND cl.id_tenant = ps.id_tenant
                LEFT JOIN categoria_productos_servicios cat ON cat.id = ps.id_categoria_productos_servicios AND cat.id_tenant = ps.id_tenant
                WHERE cxc.anulado = 0 AND pr.anulado = 0 AND cxc.id_tenant = " . TenantContext::id() . "
                GROUP BY cxc.id_persona, clasif, period, categ
            ");

            /* Tabla temporal: saldo vencido por concepto (fecha < hoy, saldo > 0) */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_vencido");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_vencido AS
                SELECT 
                    cxc.id_persona,
                    cl.codigo AS clasif,
                    ps.id_periodicidad_cobro AS period,
                    cat.codigo AS categ,
                    SUM(
                        cxc.valor - COALESCE((
                            SELECT SUM(cpx.valor_aplicado) 
                            FROM cuenta_pagada cpx 
                            INNER JOIN pagos_recibidos prx ON cpx.id_pago_recibido = prx.id
                            WHERE cpx.id_cuenta_por_cobrar = cxc.id AND prx.anulado = 0
                        ), 0)
                    ) AS saldo_vencido
                FROM cuentas_por_cobrar cxc
                INNER JOIN productos_servicios ps ON cxc.id_producto_servicio = ps.id
                LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios AND cl.id_tenant = ps.id_tenant
                LEFT JOIN categoria_productos_servicios cat ON cat.id = ps.id_categoria_productos_servicios AND cat.id_tenant = ps.id_tenant
                WHERE cxc.anulado = 0 AND cxc.id_tenant = " . TenantContext::id() . "
                AND cxc.fecha < CURDATE()
                AND (cxc.valor - COALESCE((
                    SELECT SUM(cpx2.valor_aplicado) 
                    FROM cuenta_pagada cpx2 
                    INNER JOIN pagos_recibidos prx2 ON cpx2.id_pago_recibido = prx2.id
                    WHERE cpx2.id_cuenta_por_cobrar = cxc.id AND prx2.anulado = 0
                ), 0)) > 0
                GROUP BY cxc.id_persona, clasif, period, categ
            ");

            /* Tabla temporal: pivoteo 36 columnas + 6 vencido */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cartera");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_cartera AS
                SELECT 
                    id_persona,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) AS matricula_cobrado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS matricula_pagado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS matricula_saldo_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) AS matricula_cobrado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS matricula_pagado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS matricula_saldo_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN saldo_vencido ELSE 0 END) AS matricula_vencido,

                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) AS pension_cobrado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS pension_pagado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS pension_saldo_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) AS pension_cobrado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS pension_pagado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS pension_saldo_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN saldo_vencido ELSE 0 END) AS pension_vencido,

                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) AS almuerzo_cobrado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS almuerzo_pagado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS almuerzo_saldo_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) AS almuerzo_cobrado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS almuerzo_pagado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS almuerzo_saldo_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN saldo_vencido ELSE 0 END) AS almuerzo_vencido,

                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) AS onces_cobrado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS onces_pagado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS onces_saldo_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) AS onces_cobrado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS onces_pagado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS onces_saldo_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN saldo_vencido ELSE 0 END) AS onces_vencido,

                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) AS horas_extras_cobrado_actual,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS horas_extras_pagado_actual,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS horas_extras_saldo_actual,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) AS horas_extras_cobrado_anterior,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS horas_extras_pagado_anterior,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS horas_extras_saldo_anterior,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN saldo_vencido ELSE 0 END) AS horas_extras_vencido,

                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) AS vestuario_cobrado_actual,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS vestuario_pagado_actual,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS vestuario_saldo_actual,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) AS vestuario_cobrado_anterior,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS vestuario_pagado_anterior,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS vestuario_saldo_anterior,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN saldo_vencido ELSE 0 END) AS vestuario_vencido
                FROM (
                    SELECT id_persona, clasif, period, categ, cobrado_actual, cobrado_anterior, 0 AS pagado_actual, 0 AS pagado_anterior, 0 AS saldo_vencido
                    FROM tmp_cobrado
                    UNION ALL
                    SELECT id_persona, clasif, period, categ, 0 AS cobrado_actual, 0 AS cobrado_anterior, pagado_actual, pagado_anterior, 0 AS saldo_vencido
                    FROM tmp_pagado
                    UNION ALL
                    SELECT id_persona, clasif, period, categ, 0 AS cobrado_actual, 0 AS cobrado_anterior, 0 AS pagado_actual, 0 AS pagado_anterior, saldo_vencido
                    FROM tmp_vencido
                ) combined
                GROUP BY id_persona
            ");

            /* Query principal: igual a getReporteCompleto + ultimo_recordatorio */
            $sentence = $db->prepare("
                SELECT 
                    e.id,
                    e.id AS id_estudiante,
                    e.id_persona,
                    e.fecha_ingreso,
                    e.activo,
                    e.alimentacion,
                    e.telefono_emergencia,
                    e.eps,
                    e.anno,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
                    p.id_tipo_identificacion,
                    ti.nombre AS tipo_identificacion,
                    p.numero_identificacion,
                    p.fecha_nacimiento,
                    TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                    p.id_genero,
                    g.nombre AS nombre_genero,
                    p.direccion,
                    IFNULL(grp.id, 0) AS id_grupo,
                    IFNULL(grp.nombre, 'Sin grupo') AS nombre_grupo,
                    CASE WHEN e.activo = 1 THEN 'Activo' ELSE 'Inactivo' END AS estado,
                    CASE WHEN e.alimentacion = 1 THEN 'Sí' ELSE 'No' END AS alimentacion_texto,

                    /* Contrato */
                    (SELECT cm.id 
                        FROM contratos_matricula cm 
                        WHERE cm.id_estudiante = e.id 
                        AND cm.anio = @anio_actual
                        AND cm.activo = 1 
                        LIMIT 1) AS id_contrato,
                    CASE 
                        WHEN (SELECT cm2.id FROM contratos_matricula cm2 
                              WHERE cm2.id_estudiante = e.id AND cm2.anio = @anio_actual AND cm2.activo = 1 LIMIT 1) IS NULL THEN 'Sin contrato'
                        WHEN (SELECT cm3.firmado FROM contratos_matricula cm3 
                              WHERE cm3.id_estudiante = e.id AND cm3.anio = @anio_actual AND cm3.activo = 1 LIMIT 1) = 1 THEN 'Firmado'
                        ELSE 'Pendiente'
                    END AS estado_contrato,
                    IFNULL((SELECT cm4.valor_matricula FROM contratos_matricula cm4 
                        WHERE cm4.id_estudiante = e.id AND cm4.anio = @anio_actual AND cm4.activo = 1 LIMIT 1), 0) AS valor_matricula,
                    IFNULL((SELECT cm5.valor_pension FROM contratos_matricula cm5 
                        WHERE cm5.id_estudiante = e.id AND cm5.anio = @anio_actual AND cm5.activo = 1 LIMIT 1), 0) AS valor_pension,

                    /* Matrícula mes actual */
                    IFNULL((SELECT cmv.valor 
                        FROM contratos_matricula_valores cmv
                        INNER JOIN contratos_matricula cm6 ON cmv.id_contrato_matricula = cm6.id
                        INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
                        WHERE cm6.id_estudiante = e.id AND cm6.anio = @anio_actual AND cm6.activo = 1
                        AND ps.id_clasificacion_productos_servicios IN (SELECT cps.id FROM clasificacion_productos_servicios cps WHERE cps.codigo = 'ACADEMICO' AND cps.id_tenant = ps.id_tenant) AND ps.id_periodicidad_cobro = 1 AND ps.id_categoria_productos_servicios IN (SELECT cat.id FROM categoria_productos_servicios cat WHERE cat.codigo = 'MENSUAL' AND cat.id_tenant = ps.id_tenant)
                        AND MONTH(cmv.fecha) = MONTH(CURDATE()) AND YEAR(cmv.fecha) = YEAR(CURDATE())
                        LIMIT 1), 0) AS matricula_mes_actual,

                    /* Pensión mes actual */
                    IFNULL((SELECT cmv2.valor 
                        FROM contratos_matricula_valores cmv2
                        INNER JOIN contratos_matricula cm7 ON cmv2.id_contrato_matricula = cm7.id
                        INNER JOIN productos_servicios ps2 ON cmv2.id_producto_servicio = ps2.id
                        WHERE cm7.id_estudiante = e.id AND cm7.anio = @anio_actual AND cm7.activo = 1
                        AND ps2.id_clasificacion_productos_servicios IN (SELECT cps.id FROM clasificacion_productos_servicios cps WHERE cps.codigo = 'ACADEMICO' AND cps.id_tenant = ps2.id_tenant) AND ps2.id_periodicidad_cobro = 2 AND ps2.id_categoria_productos_servicios IN (SELECT cat.id FROM categoria_productos_servicios cat WHERE cat.codigo = 'MENSUAL' AND cat.id_tenant = ps2.id_tenant)
                        AND MONTH(cmv2.fecha) = MONTH(CURDATE()) AND YEAR(cmv2.fecha) = YEAR(CURDATE())
                        LIMIT 1), 0) AS pension_mes_actual,

                    /* 36 columnas de cartera */
                    IFNULL(tc.matricula_cobrado_actual, 0) AS matricula_cobrado_actual,
                    IFNULL(tc.matricula_pagado_actual, 0) AS matricula_pagado_actual,
                    IFNULL(tc.matricula_saldo_actual, 0) AS matricula_saldo_actual,
                    IFNULL(tc.matricula_cobrado_anterior, 0) AS matricula_cobrado_anterior,
                    IFNULL(tc.matricula_pagado_anterior, 0) AS matricula_pagado_anterior,
                    IFNULL(tc.matricula_saldo_anterior, 0) AS matricula_saldo_anterior,
                    IFNULL(tc.pension_cobrado_actual, 0) AS pension_cobrado_actual,
                    IFNULL(tc.pension_pagado_actual, 0) AS pension_pagado_actual,
                    IFNULL(tc.pension_saldo_actual, 0) AS pension_saldo_actual,
                    IFNULL(tc.pension_cobrado_anterior, 0) AS pension_cobrado_anterior,
                    IFNULL(tc.pension_pagado_anterior, 0) AS pension_pagado_anterior,
                    IFNULL(tc.pension_saldo_anterior, 0) AS pension_saldo_anterior,
                    IFNULL(tc.almuerzo_cobrado_actual, 0) AS almuerzo_cobrado_actual,
                    IFNULL(tc.almuerzo_pagado_actual, 0) AS almuerzo_pagado_actual,
                    IFNULL(tc.almuerzo_saldo_actual, 0) AS almuerzo_saldo_actual,
                    IFNULL(tc.almuerzo_cobrado_anterior, 0) AS almuerzo_cobrado_anterior,
                    IFNULL(tc.almuerzo_pagado_anterior, 0) AS almuerzo_pagado_anterior,
                    IFNULL(tc.almuerzo_saldo_anterior, 0) AS almuerzo_saldo_anterior,
                    IFNULL(tc.onces_cobrado_actual, 0) AS onces_cobrado_actual,
                    IFNULL(tc.onces_pagado_actual, 0) AS onces_pagado_actual,
                    IFNULL(tc.onces_saldo_actual, 0) AS onces_saldo_actual,
                    IFNULL(tc.onces_cobrado_anterior, 0) AS onces_cobrado_anterior,
                    IFNULL(tc.onces_pagado_anterior, 0) AS onces_pagado_anterior,
                    IFNULL(tc.onces_saldo_anterior, 0) AS onces_saldo_anterior,
                    IFNULL(tc.horas_extras_cobrado_actual, 0) AS horas_extras_cobrado_actual,
                    IFNULL(tc.horas_extras_pagado_actual, 0) AS horas_extras_pagado_actual,
                    IFNULL(tc.horas_extras_saldo_actual, 0) AS horas_extras_saldo_actual,
                    IFNULL(tc.horas_extras_cobrado_anterior, 0) AS horas_extras_cobrado_anterior,
                    IFNULL(tc.horas_extras_pagado_anterior, 0) AS horas_extras_pagado_anterior,
                    IFNULL(tc.horas_extras_saldo_anterior, 0) AS horas_extras_saldo_anterior,
                    IFNULL(tc.vestuario_cobrado_actual, 0) AS vestuario_cobrado_actual,
                    IFNULL(tc.vestuario_pagado_actual, 0) AS vestuario_pagado_actual,
                    IFNULL(tc.vestuario_saldo_actual, 0) AS vestuario_saldo_actual,
                    IFNULL(tc.vestuario_cobrado_anterior, 0) AS vestuario_cobrado_anterior,
                    IFNULL(tc.vestuario_pagado_anterior, 0) AS vestuario_pagado_anterior,
                    IFNULL(tc.vestuario_saldo_anterior, 0) AS vestuario_saldo_anterior,

                    /* Saldo vencido por concepto */
                    IFNULL(tc.matricula_vencido, 0) AS matricula_vencido,
                    IFNULL(tc.pension_vencido, 0) AS pension_vencido,
                    IFNULL(tc.almuerzo_vencido, 0) AS almuerzo_vencido,
                    IFNULL(tc.onces_vencido, 0) AS onces_vencido,
                    IFNULL(tc.horas_extras_vencido, 0) AS horas_extras_vencido,
                    IFNULL(tc.vestuario_vencido, 0) AS vestuario_vencido,

                    /* Acudientes como texto */
                    IFNULL((
                        SELECT GROUP_CONCAT(
                            CONCAT(
                                IFNULL(pa.primer_nombre, ''), ' ', 
                                IFNULL(pa.segundo_nombre, ''), ' ', 
                                IFNULL(pa.primer_apellido, ''), ' ', 
                                IFNULL(pa.segundo_apellido, ''), ' - ', 
                                ta.nombre
                            ) SEPARATOR '; '
                        )
                        FROM acudientes a
                        INNER JOIN personas pa ON a.id_persona = pa.id
                        INNER JOIN tipos_acudiente ta ON a.id_tipo_acudiente = ta.id
                        WHERE a.id_estudiante = e.id
                    ), 'Sin acudientes registrados') AS acudientes,

                    /* Documentos pendientes */
                    IFNULL((
                        SELECT COUNT(*)
                        FROM tipos_personas_documentos tpd
                        INNER JOIN tipos_documentos td ON tpd.id_tipo_documento = td.id
                        INNER JOIN tipos_personas tp ON tp.id = tpd.id_tipo_persona AND tp.id_tenant = tpd.id_tenant
                        WHERE tp.codigo = 'estudiante'
                        AND tpd.id_tenant = " . TenantContext::id() . "
                        AND tpd.obligatorio = 1
                        AND td.activo = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM documentos_personas dp
                            WHERE dp.id_persona = e.id_persona
                            AND dp.id_tipo_documento = tpd.id_tipo_documento
                            AND dp.activo = 1
                        )
                    ), 0) AS docs_pendientes_cantidad,

                    IFNULL((
                        SELECT GROUP_CONCAT(td2.nombre ORDER BY tpd2.orden ASC SEPARATOR ', ')
                        FROM tipos_personas_documentos tpd2
                        INNER JOIN tipos_documentos td2 ON tpd2.id_tipo_documento = td2.id
                        INNER JOIN tipos_personas tp2 ON tp2.id = tpd2.id_tipo_persona AND tp2.id_tenant = tpd2.id_tenant
                        WHERE tp2.codigo = 'estudiante'
                        AND tpd2.id_tenant = " . TenantContext::id() . "
                        AND tpd2.obligatorio = 1
                        AND td2.activo = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM documentos_personas dp2
                            WHERE dp2.id_persona = e.id_persona
                            AND dp2.id_tipo_documento = tpd2.id_tipo_documento
                            AND dp2.activo = 1
                        )
                    ), '') AS docs_pendientes_detalle,

                    /* Último recordatorio general enviado */
                    (SELECT MAX(hrg.fecha_envio) 
                        FROM historial_recordatorios_generales hrg 
                        WHERE hrg.id_estudiante = e.id) AS ultimo_recordatorio

                FROM estudiantes e
                INNER JOIN personas p ON e.id_persona = p.id
                INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
                LEFT JOIN generos g ON p.id_genero = g.id
                LEFT JOIN estudiantes_x_grupos eg ON e.id = eg.id_estudiante AND eg.activo = 1
                LEFT JOIN grupos grp ON eg.id_grupo = grp.id
                LEFT JOIN tmp_cartera tc ON tc.id_persona = e.id_persona
                WHERE e.id_tenant = :id_tenant
                ORDER BY grp.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido
            ");

            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $estudiantes = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // Limpiar datos
            if (is_array($estudiantes)) {
                foreach ($estudiantes as &$row) {
                    if (isset($row['nombre_completo'])) {
                        $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
                    }
                    $row['telefono_emergencia'] = isset($row['telefono_emergencia']) && $row['telefono_emergencia'] ? $row['telefono_emergencia'] : '';
                    $row['eps'] = isset($row['eps']) && $row['eps'] ? $row['eps'] : '';
                    $row['direccion'] = isset($row['direccion']) && $row['direccion'] ? $row['direccion'] : '';
                }
            }

            // Acudientes desglosados con teléfono y correo
            $stmtAcud = $db->prepare("
                SELECT 
                    a.id AS id_acudiente,
                    a.id_estudiante,
                    e.id_persona AS id_persona,
                    TRIM(CONCAT(
                        IFNULL(pest.primer_nombre, ''), ' ', 
                        IFNULL(pest.segundo_nombre, ''), ' ', 
                        IFNULL(pest.primer_apellido, ''), ' ', 
                        IFNULL(pest.segundo_apellido, '')
                    )) AS nombre_estudiante,
                    a.id_tipo_acudiente,
                    ta.nombre AS nombre_tipo_acudiente,
                    a.id_persona AS id_persona_acudiente,
                    TRIM(CONCAT(
                        IFNULL(pa.primer_nombre, ''), ' ', 
                        IFNULL(pa.segundo_nombre, ''), ' ', 
                        IFNULL(pa.primer_apellido, ''), ' ', 
                        IFNULL(pa.segundo_apellido, '')
                    )) AS nombre_acudiente,
                    IFNULL(pa.telefono, '') AS telefono,
                    IFNULL(pa.correo_electronico, '') AS correo_electronico
                FROM acudientes a
                INNER JOIN estudiantes e ON a.id_estudiante = e.id
                INNER JOIN personas pest ON e.id_persona = pest.id
                INNER JOIN personas pa ON a.id_persona = pa.id
                INNER JOIN tipos_acudiente ta ON a.id_tipo_acudiente = ta.id
                WHERE a.activo = 1
                AND a.id_tenant = :id_tenant
                ORDER BY e.id, ta.nombre
            ");

            $stmtAcud->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtAcud->execute();
            $acudientes = $stmtAcud->fetchAll(PDO::FETCH_ASSOC);

            /* Limpiar temporales */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cobrado");
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_pagado");
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_vencido");
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cartera");

            Flight::json([
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'estudiantes' => $estudiantes,
                'acudientes' => $acudientes
            ]);
        } catch (Exception $e) {
            error_log("Error en getReporteRecordatorios: " . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener el reporte para recordatorios: ' . $e->getMessage()), 500);
        }
    }
}