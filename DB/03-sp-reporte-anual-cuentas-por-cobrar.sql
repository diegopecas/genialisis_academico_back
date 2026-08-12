-- =====================================================================================
-- Genialisis - sp_reporte_anual_cuentas_por_cobrar (reporte anual de cuentas por cobrar)
--
-- Sin cláusula DEFINER: MySQL lo deja a nombre del usuario que ejecute este script,
-- por eso hay que correrlo con un usuario admin que no se vaya a eliminar.
-- SQL SECURITY INVOKER: corre con los permisos de quien lo llama; el usuario de la
-- aplicación solo necesita SELECT sobre las tablas y EXECUTE sobre la rutina.
--
-- Seleccionar antes la base de datos del tenant. EJECUTAR COMO SCRIPT COMPLETO
-- (en DBeaver: Alt+X), porque el cuerpo lleva DELIMITER.
-- =====================================================================================

DROP PROCEDURE IF EXISTS `sp_reporte_anual_cuentas_por_cobrar`;
DELIMITER //
CREATE PROCEDURE `sp_reporte_anual_cuentas_por_cobrar`(IN p_anio INT, IN p_id_tenant INT)
SQL SECURITY INVOKER
BEGIN
    DECLARE v_fecha_inicial DATE;
    DECLARE v_fecha_final DATE;
    DECLARE done INT DEFAULT FALSE;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    SET v_fecha_inicial = CONCAT(p_anio, '-01-01');
    SET v_fecha_final = CONCAT(p_anio, '-12-31');

    DROP TEMPORARY TABLE IF EXISTS reporte_estudiantes;
    CREATE TEMPORARY TABLE reporte_estudiantes (
        id_persona CHAR(36) PRIMARY KEY,
        id_estudiante CHAR(36) DEFAULT NULL,
        id_colaborador CHAR(36) DEFAULT NULL,
        nombre_estudiante VARCHAR(255),
        numero_identificacion VARCHAR(50),
        grupo_estudiante VARCHAR(100),
        tipo_persona VARCHAR(20) DEFAULT 'Estudiante',
        activo INT DEFAULT 1,
        INDEX idx_estudiante (id_estudiante),
        INDEX idx_colaborador (id_colaborador),
        INDEX idx_tipo (tipo_persona)
    );

    DROP TEMPORARY TABLE IF EXISTS reporte_valores;
    CREATE TEMPORARY TABLE reporte_valores (
        id_persona CHAR(36),
        tipo_valor VARCHAR(255),
        valor DECIMAL(15,2) DEFAULT 0,
        mes INT DEFAULT NULL,
        INDEX idx_persona_tipo (id_persona, tipo_valor),
        INDEX idx_tipo (tipo_valor),
        INDEX idx_mes (mes)
    );

    DROP TEMPORARY TABLE IF EXISTS reporte_clasificaciones;
    CREATE TEMPORARY TABLE reporte_clasificaciones (
        id_clasificacion CHAR(36),
        nombre_clasificacion VARCHAR(100),
        total_cobrado DECIMAL(15,2) DEFAULT 0,
        total_pagado DECIMAL(15,2) DEFAULT 0,
        total_cobrado_anulado DECIMAL(15,2) DEFAULT 0,
        total_pagado_anulado DECIMAL(15,2) DEFAULT 0,
        saldo_total DECIMAL(15,2) DEFAULT 0,
        saldo_vencido DECIMAL(15,2) DEFAULT 0,
        saldo_pendiente DECIMAL(15,2) DEFAULT 0,
        total_cobrado_a_este_mes DECIMAL(15,2) DEFAULT 0,
        total_cobrado_futuro DECIMAL(15,2) DEFAULT 0
    );

    DROP TEMPORARY TABLE IF EXISTS reporte_pagos_diarios;
    CREATE TEMPORARY TABLE reporte_pagos_diarios (
        fecha DATE,
        dia INT,
        mes INT,
        nombre_mes VARCHAR(20),
        anio INT,
        id_tipo_pago CHAR(36),
        nombre_tipo_pago VARCHAR(100),
        grupo_cartera VARCHAR(50) DEFAULT NULL,
        id_estudiante CHAR(36) DEFAULT NULL,
        id_colaborador CHAR(36) DEFAULT NULL,
        id_persona CHAR(36) DEFAULT NULL,
        nombre_estudiante VARCHAR(255) DEFAULT NULL,
        tipo_persona VARCHAR(20) DEFAULT NULL,
        total_cobrado DECIMAL(15,2) DEFAULT 0,
        cantidad_cobros INT DEFAULT 0,
        total_pagado DECIMAL(15,2) DEFAULT 0,
        total_recibido DECIMAL(15,2) DEFAULT 0,
        cantidad_pagos INT DEFAULT 0,
        KEY idx_fecha_tipo (fecha, id_tipo_pago),
        KEY idx_estudiante (id_estudiante),
        KEY idx_colaborador (id_colaborador),
        KEY idx_persona (id_persona)
    );

    DROP TEMPORARY TABLE IF EXISTS estudiante_clasificacion;
    CREATE TEMPORARY TABLE estudiante_clasificacion (
        id_persona CHAR(36),
        id_clasificacion CHAR(36),
        nombre_clasificacion VARCHAR(100),
        total_cobrado DECIMAL(15,2) DEFAULT 0,
        total_pagado DECIMAL(15,2) DEFAULT 0,
        total_cobrado_a_este_mes DECIMAL(15,2) DEFAULT 0,
        total_cobrado_futuro DECIMAL(15,2) DEFAULT 0,
        KEY idx_persona_clasificacion (id_persona, id_clasificacion)
    );

    DROP TEMPORARY TABLE IF EXISTS estudiante_producto;
    CREATE TEMPORARY TABLE estudiante_producto (
        id_persona CHAR(36),
        id_producto CHAR(36),
        nombre_producto VARCHAR(255),
        id_clasificacion CHAR(36),
        nombre_clasificacion VARCHAR(100),
        total_cobrado DECIMAL(15,2) DEFAULT 0,
        total_pagado DECIMAL(15,2) DEFAULT 0,
        total_cobrado_a_este_mes DECIMAL(15,2) DEFAULT 0,
        total_cobrado_futuro DECIMAL(15,2) DEFAULT 0,
        KEY idx_persona_producto (id_persona, id_producto)
    );

    DROP TEMPORARY TABLE IF EXISTS reporte_productos;
    CREATE TEMPORARY TABLE reporte_productos (
        id_producto CHAR(36),
        nombre_producto VARCHAR(255),
        id_clasificacion CHAR(36),
        nombre_clasificacion VARCHAR(100),
        total_cobrado DECIMAL(15,2) DEFAULT 0,
        total_pagado DECIMAL(15,2) DEFAULT 0,
        total_cobrado_anulado DECIMAL(15,2) DEFAULT 0,
        total_pagado_anulado DECIMAL(15,2) DEFAULT 0,
        saldo_total DECIMAL(15,2) DEFAULT 0,
        saldo_vencido DECIMAL(15,2) DEFAULT 0,
        saldo_pendiente DECIMAL(15,2) DEFAULT 0,
        total_cobrado_a_este_mes DECIMAL(15,2) DEFAULT 0,
        total_cobrado_futuro DECIMAL(15,2) DEFAULT 0,
        cantidad_estudiantes INT DEFAULT 0,
        INDEX idx_producto (id_producto),
        INDEX idx_clasificacion (id_clasificacion)
    );

    DROP TEMPORARY TABLE IF EXISTS reporte_anulados;
    CREATE TEMPORARY TABLE reporte_anulados (
        fecha_anulacion DATE,
        dia INT,
        mes INT,
        nombre_mes VARCHAR(20),
        anio INT,
        tipo_movimiento VARCHAR(20),
        id_tipo_pago CHAR(36) DEFAULT NULL,
        nombre_tipo_pago VARCHAR(100),
        grupo_cartera VARCHAR(50) DEFAULT NULL,
        fecha_original DATE,
        id_estudiante CHAR(36) DEFAULT NULL,
        id_colaborador CHAR(36) DEFAULT NULL,
        id_persona CHAR(36) DEFAULT NULL,
        nombre_estudiante VARCHAR(255) DEFAULT NULL,
        tipo_persona VARCHAR(20) DEFAULT NULL,
        id_producto_servicio CHAR(36) DEFAULT NULL,
        nombre_producto VARCHAR(255) DEFAULT NULL,
        id_clasificacion CHAR(36) DEFAULT NULL,
        nombre_clasificacion VARCHAR(100) DEFAULT NULL,
        valor_anulado DECIMAL(15,2) DEFAULT 0,
        cantidad_anulaciones INT DEFAULT 1,
        id_usuario_anulacion CHAR(36) DEFAULT NULL,
        nombre_usuario_anulacion VARCHAR(255) DEFAULT NULL,
        KEY idx_fecha_anulacion (fecha_anulacion, id_tipo_pago),
        KEY idx_tipo (tipo_movimiento),
        KEY idx_estudiante (id_estudiante),
        KEY idx_colaborador (id_colaborador),
        KEY idx_persona (id_persona)
    );

    INSERT INTO reporte_pagos_diarios (fecha, dia, mes, nombre_mes, anio, id_tipo_pago, nombre_tipo_pago,
                                       id_estudiante, id_colaborador, id_persona, nombre_estudiante, tipo_persona,
                                       total_cobrado, cantidad_cobros, total_pagado, total_recibido, cantidad_pagos)
    SELECT
        c.fecha,
        DAY(c.fecha) as dia,
        MONTH(c.fecha) as mes,
        CASE MONTH(c.fecha)
            WHEN 1 THEN 'Enero' WHEN 2 THEN 'Febrero' WHEN 3 THEN 'Marzo'
            WHEN 4 THEN 'Abril' WHEN 5 THEN 'Mayo' WHEN 6 THEN 'Junio'
            WHEN 7 THEN 'Julio' WHEN 8 THEN 'Agosto' WHEN 9 THEN 'Septiembre'
            WHEN 10 THEN 'Octubre' WHEN 11 THEN 'Noviembre' WHEN 12 THEN 'Diciembre'
        END as nombre_mes,
        YEAR(c.fecha) as anio,
        '0' as id_tipo_pago,
        'COBROS TOTALES' as nombre_tipo_pago,
        e.id as id_estudiante,
        col.id as id_colaborador,
        c.id_persona,
        CONCAT(
            COALESCE(p.primer_nombre, ''), ' ',
            COALESCE(p.segundo_nombre, ''), ' ',
            COALESCE(p.primer_apellido, ''), ' ',
            COALESCE(p.segundo_apellido, '')
        ) as nombre_estudiante,
        CASE
            WHEN e.id IS NOT NULL THEN 'Estudiante'
            WHEN col.id IS NOT NULL THEN 'Colaborador'
            ELSE 'Otro'
        END as tipo_persona,
        SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN c.valor ELSE 0 END) as total_cobrado,
        COUNT(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN 1 ELSE NULL END) as cantidad_cobros,
        0 as total_pagado,
        0 as total_recibido,
        0 as cantidad_pagos
    FROM cuentas_por_cobrar c
    LEFT JOIN estudiantes e ON e.id_persona = c.id_persona
    LEFT JOIN colaboradores col ON col.id_persona = c.id_persona
    LEFT JOIN personas p ON p.id = c.id_persona
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.fecha, e.id, col.id, c.id_persona, nombre_estudiante, tipo_persona;

    INSERT INTO reporte_pagos_diarios (fecha, dia, mes, nombre_mes, anio, id_tipo_pago, nombre_tipo_pago,
        id_estudiante, id_colaborador, id_persona, nombre_estudiante, tipo_persona,
        total_cobrado, cantidad_cobros, total_pagado, total_recibido, cantidad_pagos)
    SELECT
        datos.fecha, datos.dia, datos.mes, datos.nombre_mes, datos.anio,
        datos.id_tipo_pago, datos.nombre_tipo_pago,
        datos.id_estudiante, datos.id_colaborador, datos.id_persona, datos.nombre_estudiante, datos.tipo_persona,
        0 as total_cobrado, 0 as cantidad_cobros,
        datos.total_pagado, datos.total_recibido, datos.cantidad_pagos
    FROM (
        SELECT
            pr.fecha,
            DAY(pr.fecha) as dia,
            MONTH(pr.fecha) as mes,
            CASE MONTH(pr.fecha)
                WHEN 1 THEN 'Enero' WHEN 2 THEN 'Febrero' WHEN 3 THEN 'Marzo'
                WHEN 4 THEN 'Abril' WHEN 5 THEN 'Mayo' WHEN 6 THEN 'Junio'
                WHEN 7 THEN 'Julio' WHEN 8 THEN 'Agosto' WHEN 9 THEN 'Septiembre'
                WHEN 10 THEN 'Octubre' WHEN 11 THEN 'Noviembre' WHEN 12 THEN 'Diciembre'
            END as nombre_mes,
            YEAR(pr.fecha) as anio,
            COALESCE(pr.id_tipo_pago, '0') as id_tipo_pago,
            COALESCE(tp.nombre, 'Sin tipo') as nombre_tipo_pago,
            COALESCE(pr.id_estudiante, '0') as id_estudiante,
            COALESCE(pr.id_colaborador, '0') as id_colaborador,
            COALESCE(
                CASE
                    WHEN pr.id_estudiante IS NOT NULL THEN e.id_persona
                    WHEN pr.id_colaborador IS NOT NULL THEN col.id_persona
                    ELSE '0'
                END, '0'
            ) as id_persona,
            COALESCE(
                CASE
                    WHEN pr.id_estudiante IS NOT NULL THEN CONCAT(
                        COALESCE(pe.primer_nombre, ''), ' ', COALESCE(pe.segundo_nombre, ''), ' ',
                        COALESCE(pe.primer_apellido, ''), ' ', COALESCE(pe.segundo_apellido, ''))
                    WHEN pr.id_colaborador IS NOT NULL THEN CONCAT(
                        COALESCE(pc.primer_nombre, ''), ' ', COALESCE(pc.segundo_nombre, ''), ' ',
                        COALESCE(pc.primer_apellido, ''), ' ', COALESCE(pc.segundo_apellido, ''))
                    ELSE 'Sin persona'
                END, 'Sin persona'
            ) as nombre_estudiante,
            CASE
                WHEN pr.id_estudiante IS NOT NULL THEN 'Estudiante'
                WHEN pr.id_colaborador IS NOT NULL THEN 'Colaborador'
                ELSE 'Otro'
            END as tipo_persona,
            SUM(pr.valor_recibido) as total_recibido,
            SUM(COALESCE((
                SELECT SUM(cp2.valor_aplicado)
                FROM cuenta_pagada cp2
                INNER JOIN cuentas_por_cobrar c2 ON c2.id = cp2.id_cuenta_por_cobrar
                WHERE cp2.id_pago_recibido = pr.id
                AND (c2.anulado = 0 OR c2.anulado IS NULL)
            ), 0)) as total_pagado,
            COUNT(DISTINCT pr.id) as cantidad_pagos
        FROM pagos_recibidos pr
        LEFT JOIN tipos_pagos tp ON tp.id = pr.id_tipo_pago
        LEFT JOIN estudiantes e ON e.id = pr.id_estudiante
        LEFT JOIN personas pe ON pe.id = e.id_persona
        LEFT JOIN colaboradores col ON col.id = pr.id_colaborador
        LEFT JOIN personas pc ON pc.id = col.id_persona
        WHERE pr.fecha >= v_fecha_inicial
            AND pr.fecha <= v_fecha_final AND pr.id_tenant = p_id_tenant
            AND (pr.anulado = 0 OR pr.anulado IS NULL)
        GROUP BY
            pr.fecha,
            COALESCE(pr.id_tipo_pago, '0'),
            COALESCE(tp.nombre, 'Sin tipo'),
            id_estudiante, id_colaborador, id_persona, nombre_estudiante, tipo_persona
    ) datos;

    INSERT INTO reporte_estudiantes (id_persona, id_estudiante, id_colaborador, nombre_estudiante, numero_identificacion, grupo_estudiante, tipo_persona, activo)
    SELECT DISTINCT
        p.id,
        e.id,
        NULL,
        CONCAT(
            COALESCE(p.primer_nombre, ''), ' ',
            COALESCE(p.segundo_nombre, ''), ' ',
            COALESCE(p.primer_apellido, ''), ' ',
            COALESCE(p.segundo_apellido, '')
        ),
        p.numero_identificacion,
        COALESCE(g.nombre, 'Sin grupo'),
        'Estudiante',
        COALESCE(e.activo, 0)
    FROM personas p
    INNER JOIN estudiantes e ON e.id_persona = p.id
    LEFT JOIN estudiantes_x_grupos eg ON eg.id_estudiante = e.id AND eg.activo = 1
    LEFT JOIN grupos g ON g.id = eg.id_grupo
    WHERE EXISTS (
        SELECT 1 FROM cuentas_por_cobrar c
        WHERE c.id_persona = p.id
        AND c.fecha >= v_fecha_inicial
        AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    );

    INSERT INTO reporte_estudiantes (id_persona, id_estudiante, id_colaborador, nombre_estudiante, numero_identificacion, grupo_estudiante, tipo_persona, activo)
    SELECT DISTINCT
        p.id,
        NULL,
        col.id,
        CONCAT(
            COALESCE(p.primer_nombre, ''), ' ',
            COALESCE(p.segundo_nombre, ''), ' ',
            COALESCE(p.primer_apellido, ''), ' ',
            COALESCE(p.segundo_apellido, '')
        ),
        p.numero_identificacion,
        COALESCE(ca.nombre, 'Sin cargo'),
        'Colaborador',
        COALESCE(col.activo, 0)
    FROM personas p
    INNER JOIN colaboradores col ON col.id_persona = p.id
    LEFT JOIN cargos ca ON ca.id = col.id_cargo
    WHERE EXISTS (
        SELECT 1 FROM cuentas_por_cobrar c
        WHERE c.id_persona = p.id
        AND c.fecha >= v_fecha_inicial
        AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    )
    AND p.id NOT IN (SELECT id_persona FROM reporte_estudiantes);

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        'Total Cobrado' as tipo_valor,
        SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN c.valor ELSE 0 END) as valor,
        NULL as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        'Total Cobrado Anulado' as tipo_valor,
        SUM(CASE WHEN c.anulado = 1 THEN c.valor ELSE 0 END) as valor,
        NULL as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        'Total Pagado' as tipo_valor,
        SUM(CASE
            WHEN (c.anulado = 0 OR c.anulado IS NULL) AND (pr.anulado = 0 OR pr.anulado IS NULL)
            THEN cp.valor_aplicado
            ELSE 0
        END) as valor,
        NULL as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    LEFT JOIN cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
    LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        'Total Pagado Anulado' as tipo_valor,
        SUM(CASE
            WHEN c.anulado = 1 OR pr.anulado = 1
            THEN cp.valor_aplicado
            ELSE 0
        END) as valor,
        NULL as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    LEFT JOIN cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
    LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        CONCAT('Clasificacion_', cps.id, '_', cps.nombre) as tipo_valor,
        SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN c.valor ELSE 0 END) as valor,
        NULL as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
    INNER JOIN clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona, cps.id, cps.nombre;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        CONCAT('Clasificacion_AEsteMes_', cps.id, '_', cps.nombre) as tipo_valor,
        SUM(CASE
            WHEN (c.anulado = 0 OR c.anulado IS NULL) AND MONTH(c.fecha) <= MONTH(CURDATE())
            THEN c.valor ELSE 0
        END) as valor,
        NULL as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
    INNER JOIN clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona, cps.id, cps.nombre;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        CONCAT('Clasificacion_Futuro_', cps.id, '_', cps.nombre) as tipo_valor,
        SUM(CASE
            WHEN (c.anulado = 0 OR c.anulado IS NULL) AND MONTH(c.fecha) > MONTH(CURDATE())
            THEN c.valor ELSE 0
        END) as valor,
        NULL as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
    INNER JOIN clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona, cps.id, cps.nombre;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        CONCAT('Clasificacion_Pagado_', cps.id, '_', cps.nombre) as tipo_valor,
        SUM(CASE
            WHEN (c.anulado = 0 OR c.anulado IS NULL) AND (pr.anulado = 0 OR pr.anulado IS NULL)
            THEN cp.valor_aplicado ELSE 0
        END) as valor,
        NULL as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
    INNER JOIN clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
    LEFT JOIN cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
    LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona, cps.id, cps.nombre;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        CONCAT('Producto_', ps.id, '_', ps.nombre) as tipo_valor,
        SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN c.valor ELSE 0 END) as valor,
        NULL as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona, ps.id, ps.nombre;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        CONCAT('Producto_Pagado_', ps.id, '_', ps.nombre) as tipo_valor,
        SUM(CASE
            WHEN (c.anulado = 0 OR c.anulado IS NULL) AND (pr.anulado = 0 OR pr.anulado IS NULL)
            THEN cp.valor_aplicado ELSE 0
        END) as valor,
        NULL as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
    LEFT JOIN cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
    LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona, ps.id, ps.nombre;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        CASE MONTH(c.fecha)
            WHEN 1 THEN 'Cobrado Enero' WHEN 2 THEN 'Cobrado Febrero' WHEN 3 THEN 'Cobrado Marzo'
            WHEN 4 THEN 'Cobrado Abril' WHEN 5 THEN 'Cobrado Mayo' WHEN 6 THEN 'Cobrado Junio'
            WHEN 7 THEN 'Cobrado Julio' WHEN 8 THEN 'Cobrado Agosto' WHEN 9 THEN 'Cobrado Septiembre'
            WHEN 10 THEN 'Cobrado Octubre' WHEN 11 THEN 'Cobrado Noviembre' WHEN 12 THEN 'Cobrado Diciembre'
        END as tipo_valor,
        SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN c.valor ELSE 0 END) as valor,
        MONTH(c.fecha) as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona, MONTH(c.fecha);

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        CASE MONTH(c.fecha)
            WHEN 1 THEN 'Cobrado Anulado Enero' WHEN 2 THEN 'Cobrado Anulado Febrero' WHEN 3 THEN 'Cobrado Anulado Marzo'
            WHEN 4 THEN 'Cobrado Anulado Abril' WHEN 5 THEN 'Cobrado Anulado Mayo' WHEN 6 THEN 'Cobrado Anulado Junio'
            WHEN 7 THEN 'Cobrado Anulado Julio' WHEN 8 THEN 'Cobrado Anulado Agosto' WHEN 9 THEN 'Cobrado Anulado Septiembre'
            WHEN 10 THEN 'Cobrado Anulado Octubre' WHEN 11 THEN 'Cobrado Anulado Noviembre' WHEN 12 THEN 'Cobrado Anulado Diciembre'
        END as tipo_valor,
        SUM(CASE WHEN c.anulado = 1 THEN c.valor ELSE 0 END) as valor,
        MONTH(c.fecha) as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona, MONTH(c.fecha);

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        CASE MONTH(pr.fecha)
            WHEN 1 THEN 'Pagado Enero' WHEN 2 THEN 'Pagado Febrero' WHEN 3 THEN 'Pagado Marzo'
            WHEN 4 THEN 'Pagado Abril' WHEN 5 THEN 'Pagado Mayo' WHEN 6 THEN 'Pagado Junio'
            WHEN 7 THEN 'Pagado Julio' WHEN 8 THEN 'Pagado Agosto' WHEN 9 THEN 'Pagado Septiembre'
            WHEN 10 THEN 'Pagado Octubre' WHEN 11 THEN 'Pagado Noviembre' WHEN 12 THEN 'Pagado Diciembre'
        END as tipo_valor,
        SUM(CASE
            WHEN (pr.anulado = 0 OR pr.anulado IS NULL) AND (c.anulado = 0 OR c.anulado IS NULL)
            THEN cp.valor_aplicado ELSE 0
        END) as valor,
        MONTH(pr.fecha) as mes
    FROM cuenta_pagada cp
    INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
    INNER JOIN cuentas_por_cobrar c ON c.id = cp.id_cuenta_por_cobrar
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE pr.fecha >= v_fecha_inicial AND pr.fecha <= v_fecha_final AND pr.id_tenant = p_id_tenant
    GROUP BY c.id_persona, MONTH(pr.fecha);

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        CASE MONTH(pr.fecha)
            WHEN 1 THEN 'Pagado Anulado Enero' WHEN 2 THEN 'Pagado Anulado Febrero' WHEN 3 THEN 'Pagado Anulado Marzo'
            WHEN 4 THEN 'Pagado Anulado Abril' WHEN 5 THEN 'Pagado Anulado Mayo' WHEN 6 THEN 'Pagado Anulado Junio'
            WHEN 7 THEN 'Pagado Anulado Julio' WHEN 8 THEN 'Pagado Anulado Agosto' WHEN 9 THEN 'Pagado Anulado Septiembre'
            WHEN 10 THEN 'Pagado Anulado Octubre' WHEN 11 THEN 'Pagado Anulado Noviembre' WHEN 12 THEN 'Pagado Anulado Diciembre'
        END as tipo_valor,
        SUM(CASE
            WHEN pr.anulado = 1 OR c.anulado = 1
            THEN cp.valor_aplicado ELSE 0
        END) as valor,
        MONTH(pr.fecha) as mes
    FROM cuenta_pagada cp
    INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
    INNER JOIN cuentas_por_cobrar c ON c.id = cp.id_cuenta_por_cobrar
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE pr.fecha >= v_fecha_inicial AND pr.fecha <= v_fecha_final AND pr.id_tenant = p_id_tenant
    GROUP BY c.id_persona, MONTH(pr.fecha);

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        cobros.id_persona,
        CASE cobros.mes
            WHEN 1 THEN 'Saldo Enero' WHEN 2 THEN 'Saldo Febrero' WHEN 3 THEN 'Saldo Marzo'
            WHEN 4 THEN 'Saldo Abril' WHEN 5 THEN 'Saldo Mayo' WHEN 6 THEN 'Saldo Junio'
            WHEN 7 THEN 'Saldo Julio' WHEN 8 THEN 'Saldo Agosto' WHEN 9 THEN 'Saldo Septiembre'
            WHEN 10 THEN 'Saldo Octubre' WHEN 11 THEN 'Saldo Noviembre' WHEN 12 THEN 'Saldo Diciembre'
        END as tipo_valor,
        cobros.total_cobrado_mes - COALESCE(pagos.total_pagado, 0) as valor,
        cobros.mes
    FROM (
        SELECT
            c.id_persona,
            MONTH(c.fecha) as mes,
            SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN c.valor ELSE 0 END) as total_cobrado_mes
        FROM cuentas_por_cobrar c
        INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
        WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
        GROUP BY c.id_persona, MONTH(c.fecha)
    ) cobros
    LEFT JOIN (
        SELECT
            c.id_persona,
            MONTH(c.fecha) as mes_cuenta,
            SUM(CASE
                WHEN (c.anulado = 0 OR c.anulado IS NULL) AND (pr.anulado = 0 OR pr.anulado IS NULL)
                THEN cp.valor_aplicado ELSE 0
            END) as total_pagado
        FROM cuentas_por_cobrar c
        INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
        LEFT JOIN cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
        LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
        WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
        GROUP BY c.id_persona, MONTH(c.fecha)
    ) pagos ON cobros.id_persona = pagos.id_persona AND cobros.mes = pagos.mes_cuenta;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        re.id_persona,
        CONCAT('Pago Tipo ', tp.nombre) as tipo_valor,
        SUM(pr.valor_recibido) as valor,
        NULL as mes
    FROM pagos_recibidos pr
    INNER JOIN reporte_estudiantes re ON re.id_estudiante = pr.id_estudiante
    INNER JOIN tipos_pagos tp ON tp.id = pr.id_tipo_pago
    WHERE pr.fecha >= v_fecha_inicial AND pr.fecha <= v_fecha_final AND pr.id_tenant = p_id_tenant
    AND (pr.anulado = 0 OR pr.anulado IS NULL)
    GROUP BY re.id_persona, pr.id_tipo_pago, tp.nombre;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        re.id_persona,
        CONCAT('Pago Tipo ', tp.nombre) as tipo_valor,
        SUM(pr.valor_recibido) as valor,
        NULL as mes
    FROM pagos_recibidos pr
    INNER JOIN reporte_estudiantes re ON re.id_colaborador = pr.id_colaborador
    INNER JOIN tipos_pagos tp ON tp.id = pr.id_tipo_pago
    WHERE pr.fecha >= v_fecha_inicial AND pr.fecha <= v_fecha_final AND pr.id_tenant = p_id_tenant
    AND (pr.anulado = 0 OR pr.anulado IS NULL)
    AND re.tipo_persona = 'Colaborador'
    GROUP BY re.id_persona, pr.id_tipo_pago, tp.nombre;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        rv1.id_persona,
        'Saldo Total' as tipo_valor,
        rv1.valor - COALESCE(rv2.valor, 0) as valor,
        NULL as mes
    FROM reporte_valores rv1
    LEFT JOIN reporte_valores rv2 ON
        rv1.id_persona = rv2.id_persona AND
        rv2.tipo_valor = 'Total Pagado'
    WHERE rv1.tipo_valor = 'Total Cobrado';

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        'Saldo Vencido' as tipo_valor,
        SUM(c.valor - COALESCE((
            SELECT SUM(cp.valor_aplicado)
            FROM cuenta_pagada cp
            LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            WHERE cp.id_cuenta_por_cobrar = c.id
            AND (pr.anulado = 0 OR pr.anulado IS NULL)
        ), 0)) as valor,
        NULL as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    AND (c.anulado = 0 OR c.anulado IS NULL)
    AND c.fecha < CURDATE()
    AND c.valor > COALESCE((
        SELECT SUM(cp.valor_aplicado)
        FROM cuenta_pagada cp
        LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
        WHERE cp.id_cuenta_por_cobrar = c.id
        AND (pr.anulado = 0 OR pr.anulado IS NULL)
    ), 0)
    GROUP BY c.id_persona;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT
        c.id_persona,
        'Saldo Pendiente' as tipo_valor,
        SUM(c.valor - COALESCE((
            SELECT SUM(cp.valor_aplicado)
            FROM cuenta_pagada cp
            LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            WHERE cp.id_cuenta_por_cobrar = c.id
            AND (pr.anulado = 0 OR pr.anulado IS NULL)
        ), 0)) as valor,
        NULL as mes
    FROM cuentas_por_cobrar c
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    AND (c.anulado = 0 OR c.anulado IS NULL)
    AND c.fecha >= CURDATE()
    AND c.valor > COALESCE((
        SELECT SUM(cp.valor_aplicado)
        FROM cuenta_pagada cp
        LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
        WHERE cp.id_cuenta_por_cobrar = c.id
        AND (pr.anulado = 0 OR pr.anulado IS NULL)
    ), 0)
    GROUP BY c.id_persona;

    INSERT INTO reporte_clasificaciones (
        id_clasificacion, nombre_clasificacion, total_cobrado, total_pagado,
        total_cobrado_anulado, total_pagado_anulado, saldo_total, saldo_vencido,
        saldo_pendiente, total_cobrado_a_este_mes, total_cobrado_futuro
    )
    SELECT
        cps.id, cps.nombre,
        SUM(cuenta_datos.valor_cobrado) as total_cobrado,
        SUM(cuenta_datos.valor_pagado) as total_pagado,
        SUM(cuenta_datos.valor_cobrado_anulado) as total_cobrado_anulado,
        SUM(cuenta_datos.valor_pagado_anulado) as total_pagado_anulado,
        SUM(cuenta_datos.valor_cobrado) - SUM(cuenta_datos.valor_pagado) as saldo_total,
        SUM(cuenta_datos.saldo_vencido) as saldo_vencido,
        SUM(cuenta_datos.saldo_pendiente) as saldo_pendiente,
        SUM(cuenta_datos.cobrado_a_este_mes) as total_cobrado_a_este_mes,
        SUM(cuenta_datos.cobrado_futuro) as total_cobrado_futuro
    FROM (
        SELECT
            c.id as cuenta_id,
            ps.id_clasificacion_productos_servicios,
            CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN c.valor ELSE 0 END as valor_cobrado,
            CASE WHEN c.anulado = 1 THEN c.valor ELSE 0 END as valor_cobrado_anulado,
            CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) AND MONTH(c.fecha) <= MONTH(CURDATE()) THEN c.valor ELSE 0 END as cobrado_a_este_mes,
            CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) AND MONTH(c.fecha) > MONTH(CURDATE()) THEN c.valor ELSE 0 END as cobrado_futuro,
            COALESCE((
                SELECT SUM(cp.valor_aplicado)
                FROM cuenta_pagada cp
                LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                WHERE cp.id_cuenta_por_cobrar = c.id
                AND (pr.anulado = 0 OR pr.anulado IS NULL)
                AND (c.anulado = 0 OR c.anulado IS NULL)
            ), 0) as valor_pagado,
            COALESCE((
                SELECT SUM(cp.valor_aplicado)
                FROM cuenta_pagada cp
                LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                WHERE cp.id_cuenta_por_cobrar = c.id
                AND (pr.anulado = 1 OR c.anulado = 1)
            ), 0) as valor_pagado_anulado,
            CASE
                WHEN (c.anulado = 0 OR c.anulado IS NULL) AND c.fecha < CURDATE()
                THEN c.valor - COALESCE((
                    SELECT SUM(cp.valor_aplicado)
                    FROM cuenta_pagada cp LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                    WHERE cp.id_cuenta_por_cobrar = c.id AND (pr.anulado = 0 OR pr.anulado IS NULL)
                ), 0)
                ELSE 0
            END as saldo_vencido,
            CASE
                WHEN (c.anulado = 0 OR c.anulado IS NULL) AND c.fecha >= CURDATE()
                THEN c.valor - COALESCE((
                    SELECT SUM(cp.valor_aplicado)
                    FROM cuenta_pagada cp LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                    WHERE cp.id_cuenta_por_cobrar = c.id AND (pr.anulado = 0 OR pr.anulado IS NULL)
                ), 0)
                ELSE 0
            END as saldo_pendiente
        FROM cuentas_por_cobrar c
        INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
        WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    ) cuenta_datos
    INNER JOIN clasificacion_productos_servicios cps ON cps.id = cuenta_datos.id_clasificacion_productos_servicios
    GROUP BY cps.id, cps.nombre
    HAVING total_cobrado > 0 OR total_pagado > 0;

    INSERT INTO reporte_productos (
        id_producto, nombre_producto, id_clasificacion, nombre_clasificacion,
        total_cobrado, total_pagado, total_cobrado_anulado, total_pagado_anulado,
        saldo_total, saldo_vencido, saldo_pendiente, total_cobrado_a_este_mes,
        total_cobrado_futuro, cantidad_estudiantes
    )
    SELECT
        ps.id, ps.nombre, cps.id, cps.nombre,
        SUM(cuenta_datos.valor_cobrado) as total_cobrado,
        SUM(cuenta_datos.valor_pagado) as total_pagado,
        SUM(cuenta_datos.valor_cobrado_anulado) as total_cobrado_anulado,
        SUM(cuenta_datos.valor_pagado_anulado) as total_pagado_anulado,
        SUM(cuenta_datos.valor_cobrado) - SUM(cuenta_datos.valor_pagado) as saldo_total,
        SUM(cuenta_datos.saldo_vencido) as saldo_vencido,
        SUM(cuenta_datos.saldo_pendiente) as saldo_pendiente,
        SUM(cuenta_datos.cobrado_a_este_mes) as total_cobrado_a_este_mes,
        SUM(cuenta_datos.cobrado_futuro) as total_cobrado_futuro,
        COUNT(DISTINCT cuenta_datos.id_persona) as cantidad_estudiantes
    FROM (
        SELECT
            c.id as cuenta_id, c.id_persona, c.id_producto_servicio,
            CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN c.valor ELSE 0 END as valor_cobrado,
            CASE WHEN c.anulado = 1 THEN c.valor ELSE 0 END as valor_cobrado_anulado,
            CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) AND MONTH(c.fecha) <= MONTH(CURDATE()) THEN c.valor ELSE 0 END as cobrado_a_este_mes,
            CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) AND MONTH(c.fecha) > MONTH(CURDATE()) THEN c.valor ELSE 0 END as cobrado_futuro,
            COALESCE((SELECT SUM(cp.valor_aplicado) FROM cuenta_pagada cp LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id WHERE cp.id_cuenta_por_cobrar = c.id AND (pr.anulado = 0 OR pr.anulado IS NULL) AND (c.anulado = 0 OR c.anulado IS NULL)), 0) as valor_pagado,
            COALESCE((SELECT SUM(cp.valor_aplicado) FROM cuenta_pagada cp LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id WHERE cp.id_cuenta_por_cobrar = c.id AND (pr.anulado = 1 OR c.anulado = 1)), 0) as valor_pagado_anulado,
            CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) AND c.fecha < CURDATE() THEN c.valor - COALESCE((SELECT SUM(cp.valor_aplicado) FROM cuenta_pagada cp LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id WHERE cp.id_cuenta_por_cobrar = c.id AND (pr.anulado = 0 OR pr.anulado IS NULL)), 0) ELSE 0 END as saldo_vencido,
            CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) AND c.fecha >= CURDATE() THEN c.valor - COALESCE((SELECT SUM(cp.valor_aplicado) FROM cuenta_pagada cp LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id WHERE cp.id_cuenta_por_cobrar = c.id AND (pr.anulado = 0 OR pr.anulado IS NULL)), 0) ELSE 0 END as saldo_pendiente
        FROM cuentas_por_cobrar c
        WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    ) cuenta_datos
    INNER JOIN productos_servicios ps ON ps.id = cuenta_datos.id_producto_servicio
    INNER JOIN clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
    GROUP BY ps.id, ps.nombre, cps.id, cps.nombre
    HAVING total_cobrado > 0 OR total_pagado > 0;

    INSERT INTO estudiante_clasificacion (id_persona, id_clasificacion, nombre_clasificacion, total_cobrado, total_pagado, total_cobrado_a_este_mes, total_cobrado_futuro)
    SELECT
        c.id_persona, cps.id, cps.nombre,
        SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN c.valor ELSE 0 END) as total_cobrado,
        SUM(COALESCE((SELECT SUM(cp.valor_aplicado) FROM cuenta_pagada cp LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id WHERE cp.id_cuenta_por_cobrar = c.id AND (pr.anulado = 0 OR pr.anulado IS NULL)), 0)) as total_pagado,
        SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) AND MONTH(c.fecha) <= MONTH(CURDATE()) THEN c.valor ELSE 0 END) as total_cobrado_a_este_mes,
        SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) AND MONTH(c.fecha) > MONTH(CURDATE()) THEN c.valor ELSE 0 END) as total_cobrado_futuro
    FROM cuentas_por_cobrar c
    INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
    INNER JOIN clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona, cps.id, cps.nombre;

    INSERT INTO estudiante_producto (id_persona, id_producto, nombre_producto, id_clasificacion, nombre_clasificacion, total_cobrado, total_pagado, total_cobrado_a_este_mes, total_cobrado_futuro)
    SELECT
        c.id_persona, ps.id, ps.nombre, cps.id, cps.nombre,
        SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN c.valor ELSE 0 END) as total_cobrado,
        SUM(COALESCE((SELECT SUM(cp.valor_aplicado) FROM cuenta_pagada cp LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id WHERE cp.id_cuenta_por_cobrar = c.id AND (pr.anulado = 0 OR pr.anulado IS NULL)), 0)) as total_pagado,
        SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) AND MONTH(c.fecha) <= MONTH(CURDATE()) THEN c.valor ELSE 0 END) as total_cobrado_a_este_mes,
        SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) AND MONTH(c.fecha) > MONTH(CURDATE()) THEN c.valor ELSE 0 END) as total_cobrado_futuro
    FROM cuentas_por_cobrar c
    INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
    INNER JOIN clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    GROUP BY c.id_persona, ps.id, ps.nombre, cps.id, cps.nombre;

    INSERT INTO reporte_anulados (
        fecha_anulacion, dia, mes, nombre_mes, anio, tipo_movimiento,
        id_tipo_pago, nombre_tipo_pago, fecha_original,
        id_estudiante, id_colaborador, id_persona, nombre_estudiante, tipo_persona,
        id_producto_servicio, nombre_producto, id_clasificacion, nombre_clasificacion,
        valor_anulado, cantidad_anulaciones, id_usuario_anulacion, nombre_usuario_anulacion
    )
    SELECT
        DATE(c.fecha_anulacion) as fecha_anulacion,
        DAY(c.fecha_anulacion) as dia,
        MONTH(c.fecha_anulacion) as mes,
        CASE MONTH(c.fecha_anulacion)
            WHEN 1 THEN 'Enero' WHEN 2 THEN 'Febrero' WHEN 3 THEN 'Marzo'
            WHEN 4 THEN 'Abril' WHEN 5 THEN 'Mayo' WHEN 6 THEN 'Junio'
            WHEN 7 THEN 'Julio' WHEN 8 THEN 'Agosto' WHEN 9 THEN 'Septiembre'
            WHEN 10 THEN 'Octubre' WHEN 11 THEN 'Noviembre' WHEN 12 THEN 'Diciembre'
        END as nombre_mes,
        YEAR(c.fecha_anulacion) as anio,
        'COBRO' as tipo_movimiento,
        '0' as id_tipo_pago,
        'COBROS ANULADOS' as nombre_tipo_pago,
        c.fecha as fecha_original,
        e.id as id_estudiante,
        col.id as id_colaborador,
        c.id_persona,
        CONCAT(COALESCE(p.primer_nombre, ''), ' ', COALESCE(p.segundo_nombre, ''), ' ',
               COALESCE(p.primer_apellido, ''), ' ', COALESCE(p.segundo_apellido, '')) as nombre_estudiante,
        CASE WHEN e.id IS NOT NULL THEN 'Estudiante' WHEN col.id IS NOT NULL THEN 'Colaborador' ELSE 'Otro' END as tipo_persona,
        c.id_producto_servicio,
        ps.nombre as nombre_producto,
        ps.id_clasificacion_productos_servicios as id_clasificacion,
        cps.nombre as nombre_clasificacion,
        c.valor as valor_anulado,
        1 as cantidad_anulaciones,
        c.id_usuario_anulacion,
        CONCAT(COALESCE(pu.primer_nombre, ''), ' ', COALESCE(pu.segundo_nombre, ''), ' ',
               COALESCE(pu.primer_apellido, ''), ' ', COALESCE(pu.segundo_apellido, '')) as nombre_usuario_anulacion
    FROM cuentas_por_cobrar c
    LEFT JOIN estudiantes e ON e.id_persona = c.id_persona
    LEFT JOIN colaboradores col ON col.id_persona = c.id_persona
    LEFT JOIN personas p ON p.id = c.id_persona
    LEFT JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
    LEFT JOIN clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
    LEFT JOIN usuarios ua ON ua.id = c.id_usuario_anulacion
    LEFT JOIN personas pu ON pu.id = ua.id_persona
    WHERE c.anulado = 1
        AND YEAR(c.fecha_anulacion) = p_anio AND c.id_tenant = p_id_tenant
        AND c.fecha_anulacion IS NOT NULL;

    INSERT INTO reporte_anulados (
        fecha_anulacion, dia, mes, nombre_mes, anio, tipo_movimiento,
        id_tipo_pago, nombre_tipo_pago, fecha_original,
        id_estudiante, id_colaborador, id_persona, nombre_estudiante, tipo_persona,
        id_producto_servicio, nombre_producto, id_clasificacion, nombre_clasificacion,
        valor_anulado, cantidad_anulaciones, id_usuario_anulacion, nombre_usuario_anulacion
    )
    SELECT
        DATE(pr.fecha_anulacion) as fecha_anulacion,
        DAY(pr.fecha_anulacion) as dia,
        MONTH(pr.fecha_anulacion) as mes,
        CASE MONTH(pr.fecha_anulacion)
            WHEN 1 THEN 'Enero' WHEN 2 THEN 'Febrero' WHEN 3 THEN 'Marzo'
            WHEN 4 THEN 'Abril' WHEN 5 THEN 'Mayo' WHEN 6 THEN 'Junio'
            WHEN 7 THEN 'Julio' WHEN 8 THEN 'Agosto' WHEN 9 THEN 'Septiembre'
            WHEN 10 THEN 'Octubre' WHEN 11 THEN 'Noviembre' WHEN 12 THEN 'Diciembre'
        END as nombre_mes,
        YEAR(pr.fecha_anulacion) as anio,
        'PAGO' as tipo_movimiento,
        COALESCE(pr.id_tipo_pago, '0') as id_tipo_pago,
        COALESCE(tp.nombre, 'Sin tipo') as nombre_tipo_pago,
        pr.fecha as fecha_original,
        pr.id_estudiante,
        pr.id_colaborador,
        COALESCE(e.id_persona, col.id_persona, '0') as id_persona,
        CASE
            WHEN pr.id_estudiante IS NOT NULL THEN CONCAT(COALESCE(pe.primer_nombre, ''), ' ', COALESCE(pe.segundo_nombre, ''), ' ', COALESCE(pe.primer_apellido, ''), ' ', COALESCE(pe.segundo_apellido, ''))
            WHEN pr.id_colaborador IS NOT NULL THEN CONCAT(COALESCE(pc.primer_nombre, ''), ' ', COALESCE(pc.segundo_nombre, ''), ' ', COALESCE(pc.primer_apellido, ''), ' ', COALESCE(pc.segundo_apellido, ''))
            ELSE 'Sin persona'
        END as nombre_estudiante,
        CASE
            WHEN pr.id_estudiante IS NOT NULL THEN 'Estudiante'
            WHEN pr.id_colaborador IS NOT NULL THEN 'Colaborador'
            ELSE 'Otro'
        END as tipo_persona,
        NULL as id_producto_servicio,
        NULL as nombre_producto,
        NULL as id_clasificacion,
        NULL as nombre_clasificacion,
        pr.valor_recibido as valor_anulado,
        1 as cantidad_anulaciones,
        pr.id_usuario_anulacion,
        CONCAT(COALESCE(pua.primer_nombre, ''), ' ', COALESCE(pua.segundo_nombre, ''), ' ',
               COALESCE(pua.primer_apellido, ''), ' ', COALESCE(pua.segundo_apellido, '')) as nombre_usuario_anulacion
    FROM pagos_recibidos pr
    LEFT JOIN tipos_pagos tp ON tp.id = pr.id_tipo_pago
    LEFT JOIN estudiantes e ON e.id = pr.id_estudiante
    LEFT JOIN personas pe ON pe.id = e.id_persona
    LEFT JOIN colaboradores col ON col.id = pr.id_colaborador
    LEFT JOIN personas pc ON pc.id = col.id_persona
    LEFT JOIN usuarios ua ON ua.id = pr.id_usuario_anulacion
    LEFT JOIN personas pua ON pua.id = ua.id_persona
    WHERE pr.anulado = 1
        AND YEAR(pr.fecha_anulacion) = p_anio AND pr.id_tenant = p_id_tenant
        AND pr.fecha_anulacion IS NOT NULL;

    -- Poblar grupo_cartera en filas de movimiento (no aplica a centinela '0' = TOTALES)
    UPDATE reporte_pagos_diarios rpd JOIN tipos_pagos tp ON tp.id = rpd.id_tipo_pago AND tp.id_tenant = p_id_tenant SET rpd.grupo_cartera = tp.grupo_cartera;
    UPDATE reporte_anulados ra JOIN tipos_pagos tp ON tp.id = ra.id_tipo_pago AND tp.id_tenant = p_id_tenant SET ra.grupo_cartera = tp.grupo_cartera;

    -- =====================================================
    -- RETORNAR RESULTADOS (8 conjuntos, misma estructura)
    -- =====================================================
    SELECT * FROM reporte_estudiantes ORDER BY tipo_persona, nombre_estudiante;
    SELECT * FROM reporte_valores ORDER BY id_persona, tipo_valor, mes;
    SELECT * FROM reporte_clasificaciones ORDER BY nombre_clasificacion;
    SELECT * FROM reporte_pagos_diarios ORDER BY fecha, id_tipo_pago;
    SELECT * FROM estudiante_clasificacion ORDER BY id_persona, id_clasificacion;
    SELECT * FROM reporte_productos ORDER BY id_clasificacion, nombre_producto;
    SELECT * FROM estudiante_producto ORDER BY id_persona, id_producto;
    SELECT * FROM reporte_anulados ORDER BY fecha_anulacion DESC, tipo_movimiento, id_tipo_pago;

    DROP TEMPORARY TABLE IF EXISTS reporte_estudiantes;
    DROP TEMPORARY TABLE IF EXISTS reporte_valores;
    DROP TEMPORARY TABLE IF EXISTS reporte_clasificaciones;
    DROP TEMPORARY TABLE IF EXISTS reporte_pagos_diarios;
    DROP TEMPORARY TABLE IF EXISTS estudiante_clasificacion;
    DROP TEMPORARY TABLE IF EXISTS reporte_productos;
    DROP TEMPORARY TABLE IF EXISTS estudiante_producto;
    DROP TEMPORARY TABLE IF EXISTS reporte_anulados;

END
//
DELIMITER ;

-- =====================================================================================
-- VERIFICACIÓN
-- =====================================================================================
SELECT ROUTINE_NAME, ROUTINE_TYPE, DEFINER, SECURITY_TYPE
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'sp_reporte_anual_cuentas_por_cobrar';

-- Permiso de ejecución para la aplicación (ajustar el usuario):
-- GRANT EXECUTE ON PROCEDURE sp_reporte_anual_cuentas_por_cobrar TO 'usuario_app'@'%';
