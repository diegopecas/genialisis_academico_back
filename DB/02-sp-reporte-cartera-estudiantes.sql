-- =====================================================================================
-- Genialisis - sp_reporte_cartera_estudiantes (cartera por estudiante)
--
-- Sin cláusula DEFINER: MySQL lo deja a nombre del usuario que ejecute este script,
-- por eso hay que correrlo con un usuario admin que no se vaya a eliminar.
-- SQL SECURITY INVOKER: corre con los permisos de quien lo llama; el usuario de la
-- aplicación solo necesita SELECT sobre las tablas y EXECUTE sobre la rutina.
--
-- Seleccionar antes la base de datos del tenant. EJECUTAR COMO SCRIPT COMPLETO
-- (en DBeaver: Alt+X), porque el cuerpo lleva DELIMITER.
-- =====================================================================================

DROP PROCEDURE IF EXISTS `sp_reporte_cartera_estudiantes`;
DELIMITER //
CREATE PROCEDURE `sp_reporte_cartera_estudiantes`(
    IN p_anio INT,
    IN p_id_estudiante CHAR(36),
    IN p_id_tenant INT
)
SQL SECURITY INVOKER
BEGIN
    DECLARE v_fecha_inicial DATE;
    DECLARE v_fecha_final DATE;

    SET v_fecha_inicial = CONCAT(p_anio, '-01-01');
    SET v_fecha_final = CONCAT(p_anio, '-12-31');

    DROP TEMPORARY TABLE IF EXISTS reporte_estudiantes;
    CREATE TEMPORARY TABLE reporte_estudiantes (
        id_persona CHAR(36) PRIMARY KEY,
        id_estudiante CHAR(36) DEFAULT NULL,
        nombre_estudiante VARCHAR(255),
        numero_identificacion VARCHAR(50),
        grupo_estudiante VARCHAR(100),
        activo INT DEFAULT 1,
        ultimo_recordatorio DATETIME DEFAULT NULL,
        INDEX idx_estudiante (id_estudiante)
    );

    DROP TEMPORARY TABLE IF EXISTS reporte_valores;
    CREATE TEMPORARY TABLE reporte_valores (
        id_persona CHAR(36),
        tipo_valor VARCHAR(100),
        valor DECIMAL(15,2) DEFAULT 0,
        mes INT DEFAULT NULL,
        INDEX idx_persona_tipo (id_persona, tipo_valor),
        INDEX idx_tipo (tipo_valor),
        INDEX idx_mes (mes)
    );

    INSERT INTO reporte_estudiantes (id_persona, id_estudiante, nombre_estudiante, numero_identificacion, grupo_estudiante, activo, ultimo_recordatorio)
    SELECT DISTINCT
        p.id,
        e.id,
        TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)),
        p.numero_identificacion,
        COALESCE(g.nombre, 'Sin grupo'),
        COALESCE(e.activo, 0),
        (SELECT MAX(hrp.fecha_envio) FROM historial_recordatorios_pago hrp WHERE hrp.id_estudiante = e.id)
    FROM personas p
    INNER JOIN estudiantes e ON e.id_persona = p.id
    LEFT JOIN estudiantes_x_grupos eg ON eg.id_estudiante = e.id AND eg.activo = 1
    LEFT JOIN grupos g ON g.id = eg.id_grupo
    WHERE EXISTS (
        SELECT 1 FROM cuentas_por_cobrar c
        WHERE c.id_persona = p.id
        AND c.fecha >= v_fecha_inicial
        AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant
    )
    AND (p_id_estudiante IS NULL OR e.id = p_id_estudiante);

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT c.id_persona, 'Total Cobrado', SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN c.valor ELSE 0 END), NULL
    FROM cuentas_por_cobrar c INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant GROUP BY c.id_persona;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT c.id_persona, 'Total Pagado',
        SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) AND (pr.anulado = 0 OR pr.anulado IS NULL) THEN cp.valor_aplicado ELSE 0 END), NULL
    FROM cuentas_por_cobrar c INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    LEFT JOIN cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant GROUP BY c.id_persona;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT c.id_persona,
        CASE MONTH(c.fecha) WHEN 1 THEN 'Cobrado Enero' WHEN 2 THEN 'Cobrado Febrero' WHEN 3 THEN 'Cobrado Marzo'
            WHEN 4 THEN 'Cobrado Abril' WHEN 5 THEN 'Cobrado Mayo' WHEN 6 THEN 'Cobrado Junio'
            WHEN 7 THEN 'Cobrado Julio' WHEN 8 THEN 'Cobrado Agosto' WHEN 9 THEN 'Cobrado Septiembre'
            WHEN 10 THEN 'Cobrado Octubre' WHEN 11 THEN 'Cobrado Noviembre' WHEN 12 THEN 'Cobrado Diciembre' END,
        SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN c.valor ELSE 0 END), MONTH(c.fecha)
    FROM cuentas_por_cobrar c INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant GROUP BY c.id_persona, MONTH(c.fecha);

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT c.id_persona,
        CASE MONTH(pr.fecha) WHEN 1 THEN 'Pagado Enero' WHEN 2 THEN 'Pagado Febrero' WHEN 3 THEN 'Pagado Marzo'
            WHEN 4 THEN 'Pagado Abril' WHEN 5 THEN 'Pagado Mayo' WHEN 6 THEN 'Pagado Junio'
            WHEN 7 THEN 'Pagado Julio' WHEN 8 THEN 'Pagado Agosto' WHEN 9 THEN 'Pagado Septiembre'
            WHEN 10 THEN 'Pagado Octubre' WHEN 11 THEN 'Pagado Noviembre' WHEN 12 THEN 'Pagado Diciembre' END,
        SUM(CASE WHEN (pr.anulado = 0 OR pr.anulado IS NULL) AND (c.anulado = 0 OR c.anulado IS NULL) THEN cp.valor_aplicado ELSE 0 END), MONTH(pr.fecha)
    FROM cuenta_pagada cp INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
    INNER JOIN cuentas_por_cobrar c ON c.id = cp.id_cuenta_por_cobrar
    INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE pr.fecha >= v_fecha_inicial AND pr.fecha <= v_fecha_final AND pr.id_tenant = p_id_tenant GROUP BY c.id_persona, MONTH(pr.fecha);

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT cobros.id_persona,
        CASE cobros.mes WHEN 1 THEN 'Saldo Enero' WHEN 2 THEN 'Saldo Febrero' WHEN 3 THEN 'Saldo Marzo'
            WHEN 4 THEN 'Saldo Abril' WHEN 5 THEN 'Saldo Mayo' WHEN 6 THEN 'Saldo Junio'
            WHEN 7 THEN 'Saldo Julio' WHEN 8 THEN 'Saldo Agosto' WHEN 9 THEN 'Saldo Septiembre'
            WHEN 10 THEN 'Saldo Octubre' WHEN 11 THEN 'Saldo Noviembre' WHEN 12 THEN 'Saldo Diciembre' END,
        cobros.total_cobrado_mes - COALESCE(pagos.total_pagado, 0), cobros.mes
    FROM (
        SELECT c.id_persona, MONTH(c.fecha) as mes,
            SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) THEN c.valor ELSE 0 END) as total_cobrado_mes
        FROM cuentas_por_cobrar c INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
        WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant GROUP BY c.id_persona, MONTH(c.fecha)
    ) cobros
    LEFT JOIN (
        SELECT c.id_persona, MONTH(c.fecha) as mes_cuenta,
            SUM(CASE WHEN (c.anulado = 0 OR c.anulado IS NULL) AND (pr.anulado = 0 OR pr.anulado IS NULL) THEN cp.valor_aplicado ELSE 0 END) as total_pagado
        FROM cuentas_por_cobrar c INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
        LEFT JOIN cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
        WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant GROUP BY c.id_persona, MONTH(c.fecha)
    ) pagos ON cobros.id_persona = pagos.id_persona AND cobros.mes = pagos.mes_cuenta;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT rv1.id_persona, 'Saldo Total', rv1.valor - COALESCE(rv2.valor, 0), NULL
    FROM reporte_valores rv1 LEFT JOIN reporte_valores rv2 ON rv1.id_persona = rv2.id_persona AND rv2.tipo_valor = 'Total Pagado'
    WHERE rv1.tipo_valor = 'Total Cobrado';

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT c.id_persona, 'Saldo Vencido',
        SUM(c.valor - COALESCE((SELECT SUM(cp.valor_aplicado) FROM cuenta_pagada cp LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            WHERE cp.id_cuenta_por_cobrar = c.id AND (pr.anulado = 0 OR pr.anulado IS NULL)), 0)), NULL
    FROM cuentas_por_cobrar c INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant AND (c.anulado = 0 OR c.anulado IS NULL) AND c.fecha < CURDATE()
    AND c.valor > COALESCE((SELECT SUM(cp.valor_aplicado) FROM cuenta_pagada cp LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
        WHERE cp.id_cuenta_por_cobrar = c.id AND (pr.anulado = 0 OR pr.anulado IS NULL)), 0)
    GROUP BY c.id_persona;

    INSERT INTO reporte_valores (id_persona, tipo_valor, valor, mes)
    SELECT c.id_persona, 'Saldo Pendiente',
        SUM(c.valor - COALESCE((SELECT SUM(cp.valor_aplicado) FROM cuenta_pagada cp LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            WHERE cp.id_cuenta_por_cobrar = c.id AND (pr.anulado = 0 OR pr.anulado IS NULL)), 0)), NULL
    FROM cuentas_por_cobrar c INNER JOIN reporte_estudiantes re ON re.id_persona = c.id_persona
    WHERE c.fecha >= v_fecha_inicial AND c.fecha <= v_fecha_final AND c.id_tenant = p_id_tenant AND (c.anulado = 0 OR c.anulado IS NULL) AND c.fecha >= CURDATE()
    AND c.valor > COALESCE((SELECT SUM(cp.valor_aplicado) FROM cuenta_pagada cp LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
        WHERE cp.id_cuenta_por_cobrar = c.id AND (pr.anulado = 0 OR pr.anulado IS NULL)), 0)
    GROUP BY c.id_persona;

    SELECT * FROM reporte_estudiantes ORDER BY nombre_estudiante;
    SELECT * FROM reporte_valores ORDER BY id_persona, tipo_valor, mes;

    SELECT re.id_persona, re.id_estudiante, re.nombre_estudiante,
        a.id AS id_acudiente, a.id_tipo_acudiente, ta.nombre AS nombre_tipo_acudiente,
        pa.id AS id_persona_acudiente,
        TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.segundo_nombre, pa.primer_apellido, pa.segundo_apellido)) AS nombre_acudiente,
        pa.telefono, pa.correo_electronico
    FROM reporte_estudiantes re
    INNER JOIN estudiantes e ON e.id = re.id_estudiante
    INNER JOIN acudientes a ON a.id_estudiante = e.id AND a.es_responsable_pago = 1 AND a.activo = 1 AND a.id_tenant = p_id_tenant
    INNER JOIN tipos_acudiente ta ON ta.id = a.id_tipo_acudiente
    INNER JOIN personas pa ON pa.id = a.id_persona
    ORDER BY re.nombre_estudiante, ta.nombre;

    DROP TEMPORARY TABLE IF EXISTS reporte_estudiantes;
    DROP TEMPORARY TABLE IF EXISTS reporte_valores;

END
//
DELIMITER ;

-- =====================================================================================
-- VERIFICACIÓN
-- =====================================================================================
SELECT ROUTINE_NAME, ROUTINE_TYPE, DEFINER, SECURITY_TYPE
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'sp_reporte_cartera_estudiantes';

-- Permiso de ejecución para la aplicación (ajustar el usuario):
-- GRANT EXECUTE ON PROCEDURE sp_reporte_cartera_estudiantes TO 'usuario_app'@'%';
