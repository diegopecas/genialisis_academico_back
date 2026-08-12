-- =====================================================================================
-- Genialisis - sp_ia_contexto_personal (contexto para el chat con IA)
--
-- Sin cláusula DEFINER: MySQL lo deja a nombre del usuario que ejecute este script,
-- por eso hay que correrlo con un usuario admin que no se vaya a eliminar.
-- SQL SECURITY INVOKER: corre con los permisos de quien lo llama; el usuario de la
-- aplicación solo necesita SELECT sobre las tablas y EXECUTE sobre la rutina.
--
-- Seleccionar antes la base de datos del tenant. EJECUTAR COMO SCRIPT COMPLETO
-- (en DBeaver: Alt+X), porque el cuerpo lleva DELIMITER.
-- =====================================================================================

DROP PROCEDURE IF EXISTS `sp_ia_contexto_personal`;
DELIMITER //
CREATE PROCEDURE `sp_ia_contexto_personal`(
    IN p_ids_estudiantes TEXT,
    IN p_ids_grupos TEXT,
    IN p_id_tenant INT
)
SQL SECURITY INVOKER
BEGIN
    DECLARE v_contexto LONGTEXT DEFAULT '';
    DECLARE v_id_est CHAR(36);
    DECLARE v_nombre_est VARCHAR(500);
    DECLARE v_edad INT;
    DECLARE v_grupo VARCHAR(100);
    DECLARE v_eps VARCHAR(100);
    DECLARE v_rh VARCHAR(3);
    DECLARE v_tel_emergencia VARCHAR(100);
    DECLARE v_fecha_ingreso DATE;
    DECLARE v_alimentacion VARCHAR(20);
    DECLARE v_cantidad INT DEFAULT 0;
    DECLARE v_done INT DEFAULT 0;

    DECLARE cur_est CURSOR FOR
        SELECT DISTINCT e.id,
            CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
            TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
            g.nombre AS grupo,
            p.rh,
            e.eps,
            e.telefono_emergencia,
            e.fecha_ingreso,
            CASE e.alimentacion WHEN 1 THEN 'Sí' ELSE 'No' END AS alimentacion
        FROM estudiantes e
        INNER JOIN personas p ON e.id_persona = p.id
        LEFT JOIN estudiantes_x_grupos eg ON e.id = eg.id_estudiante AND eg.activo = 1
        LEFT JOIN grupos g ON eg.id_grupo = g.id
        WHERE e.activo = 1 AND e.id_tenant = p_id_tenant
        AND (
            (p_ids_estudiantes IS NOT NULL AND p_ids_estudiantes != '' AND FIND_IN_SET(e.id, p_ids_estudiantes))
            OR
            (p_ids_grupos IS NOT NULL AND p_ids_grupos != '' AND FIND_IN_SET(eg.id_grupo, p_ids_grupos))
        )
        ORDER BY g.orden, p.primer_nombre;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    SELECT COUNT(DISTINCT e.id) INTO v_cantidad
    FROM estudiantes e
    LEFT JOIN estudiantes_x_grupos eg ON e.id = eg.id_estudiante AND eg.activo = 1
    WHERE e.activo = 1 AND e.id_tenant = p_id_tenant
    AND (
        (p_ids_estudiantes IS NOT NULL AND p_ids_estudiantes != '' AND FIND_IN_SET(e.id, p_ids_estudiantes))
        OR
        (p_ids_grupos IS NOT NULL AND p_ids_grupos != '' AND FIND_IN_SET(eg.id_grupo, p_ids_grupos))
    );

    IF v_cantidad = 0 THEN
        SELECT 'Sin estudiantes asociados.' AS contexto;
    ELSE
        SET v_contexto = CONCAT('\nDATOS PERSONALES DE ESTUDIANTES (', v_cantidad, ' estudiante(s)):\n');

        OPEN cur_est;
        read_loop: LOOP
            FETCH cur_est INTO v_id_est, v_nombre_est, v_edad, v_grupo, v_rh, v_eps, v_tel_emergencia, v_fecha_ingreso, v_alimentacion;
            IF v_done THEN LEAVE read_loop; END IF;

            SET v_contexto = CONCAT(v_contexto,
                '\n- ', IFNULL(v_nombre_est, 'Sin nombre'),
                ' | Edad: ', IFNULL(v_edad, '?'), ' años',
                ' | Grupo: ', IFNULL(v_grupo, 'Sin grupo'),
                ' | EPS: ', IFNULL(v_eps, 'N/R'),
                ' | RH: ', IFNULL(v_rh, 'N/R'),
                ' | Tel emergencia: ', IFNULL(v_tel_emergencia, 'N/R'),
                ' | Ingreso: ', IFNULL(v_fecha_ingreso, 'N/R'),
                ' | Alimentación: ', v_alimentacion, '\n'
            );

            BEGIN
                DECLARE v_done2 INT DEFAULT 0;
                DECLARE v_nombre_acu VARCHAR(500);
                DECLARE v_tipo_acu VARCHAR(100);
                DECLARE v_tel_acu VARCHAR(100);
                DECLARE v_correo_acu VARCHAR(500);
                DECLARE v_responsable_pago VARCHAR(5);

                DECLARE cur_acu CURSOR FOR
                    SELECT
                        CONCAT_WS(' ', pa.primer_nombre, pa.primer_apellido) AS nombre_acudiente,
                        ta.nombre AS tipo_acudiente,
                        pa.telefono,
                        pa.correo_electronico,
                        CASE a.es_responsable_pago WHEN 1 THEN 'Sí' ELSE 'No' END AS responsable_pago
                    FROM acudientes a
                    INNER JOIN personas pa ON a.id_persona = pa.id
                    INNER JOIN tipos_acudiente ta ON a.id_tipo_acudiente = ta.id
                    WHERE a.id_estudiante = v_id_est AND a.activo = 1 AND a.id_tenant = p_id_tenant;

                DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done2 = 1;

                OPEN cur_acu;
                acu_loop: LOOP
                    FETCH cur_acu INTO v_nombre_acu, v_tipo_acu, v_tel_acu, v_correo_acu, v_responsable_pago;
                    IF v_done2 THEN LEAVE acu_loop; END IF;

                    SET v_contexto = CONCAT(v_contexto,
                        '  Acudiente: ', IFNULL(v_nombre_acu, 'N/R'),
                        ' (', IFNULL(v_tipo_acu, ''), ')',
                        ' | Tel: ', IFNULL(v_tel_acu, 'N/R'),
                        ' | Email: ', IFNULL(v_correo_acu, 'N/R'),
                        ' | Responsable pago: ', v_responsable_pago, '\n'
                    );
                END LOOP;
                CLOSE cur_acu;
            END;

        END LOOP;
        CLOSE cur_est;

        SELECT v_contexto AS contexto;
    END IF;
END
//
DELIMITER ;

-- =====================================================================================
-- VERIFICACIÓN
-- =====================================================================================
SELECT ROUTINE_NAME, ROUTINE_TYPE, DEFINER, SECURITY_TYPE
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'sp_ia_contexto_personal';

-- Permiso de ejecución para la aplicación (ajustar el usuario):
-- GRANT EXECUTE ON PROCEDURE sp_ia_contexto_personal TO 'usuario_app'@'%';
