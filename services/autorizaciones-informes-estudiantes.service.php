<?php
/**
 * Autorizacion de informes de calificaciones por estudiante.
 *
 * El acudiente ve el informe de un corte solo si se cumplen dos cosas: el sprint
 * marcado como sprint_informe de ese corte esta finalizado, y el estudiante tiene
 * autorizacion. Antes bastaba con que la fecha final del sprint hubiera pasado.
 *
 * La autorizacion se guarda por corte academico, no por sprint: si cambian cual
 * sprint produce el informe, lo ya autorizado se conserva.
 */
class AutorizacionesInformesEstudiantes
{
    /**
     * Anios que ya tienen cortes con sprint de informe. Alimenta el combo de la
     * pantalla, para no ofrecer anios vacios.
     */
    public static function getAnios()
    {
        JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT DISTINCT s.anio
                FROM sprints s
                WHERE s.id_tenant = :id_tenant
                ORDER BY s.anio DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json($sentence->fetchAll(PDO::FETCH_COLUMN));
        } catch (Exception $e) {
            error_log('Error en getAnios de autorizaciones de informes: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener los años disponibles'
            ], 500);
        }
    }

    /**
     * Maestro: cortes academicos del anio con su sprint de informe, si esta
     * finalizado y cuantos estudiantes van autorizados.
     *
     * Un corte sin sprint de informe marcado sale igual, con id_sprint_informe
     * en null, para que se vea en la pantalla que falta marcarlo.
     */
    public static function getCortes($anio)
    {
        JWTService::requerirAutenticacion();

        try {
            if (!is_numeric($anio)) {
                Flight::json(['error' => true, 'message' => 'Año inválido'], 400);
                return;
            }

            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    ca.id AS id_corte_academico,
                    ca.nombre AS nombre_corte,
                    ca.orden,
                    ca.fecha_inicio,
                    ca.fecha_fin,
                    si.id AS id_sprint_informe,
                    si.nombre_sprint,
                    si.fecha_final AS fecha_final_sprint,
                    COALESCE(si.finalizado, 0) AS sprint_finalizado,
                    (
                        -- Mismo criterio que getEstudiantesCorte: activos, con
                        -- su grupo activo mas reciente y solo grupos calificables.
                        SELECT COUNT(*)
                        FROM estudiantes e
                        INNER JOIN estudiantes_x_grupos exg
                            ON exg.id = (
                                SELECT x.id
                                FROM estudiantes_x_grupos x
                                WHERE x.id_estudiante = e.id
                                    AND x.id_tenant = e.id_tenant
                                    AND x.activo = 1
                                ORDER BY x.anio DESC
                                LIMIT 1
                            )
                        INNER JOIN grupos g
                            ON g.id = exg.id_grupo
                            AND g.calificable = 1
                        WHERE e.id_tenant = ca.id_tenant
                            AND e.activo = 1
                    ) AS total_estudiantes,
                    (
                        SELECT COUNT(*)
                        FROM autorizaciones_informes_estudiantes a
                        WHERE a.id_tenant = ca.id_tenant
                            AND a.id_corte_academico = ca.id
                            AND a.autorizado = 1
                    ) AS total_autorizados
                FROM cortes_academicos ca
                LEFT JOIN sprints si
                    ON si.id_corte_academico = ca.id
                    AND si.id_tenant = ca.id_tenant
                    AND si.sprint_informe = 1
                WHERE ca.id_tenant = :id_tenant
                    AND (
                        si.anio = :anio
                        OR (si.id IS NULL AND YEAR(ca.fecha_inicio) = :anio_corte)
                    )
                ORDER BY ca.orden, ca.fecha_inicio
            ");

            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':anio', $anio, PDO::PARAM_INT);
            $sentence->bindValue(':anio_corte', $anio, PDO::PARAM_INT);
            $sentence->execute();

            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en getCortes de autorizaciones de informes: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener los cortes académicos'
            ], 500);
        }
    }

    /**
     * Detalle: estudiantes activos del anio con su autorizacion para ese corte y
     * su saldo vencido.
     *
     * Saldo vencido = cuentas por cobrar no anuladas con fecha anterior a hoy
     * que todavia tienen residuo, descontando los pagos no anulados. Es el mismo
     * criterio del detalle de pendientes del reporte de cartera.
     *
     * Devuelve a todos los estudiantes activos, tengan o no fila de autorizacion;
     * los que no la tienen salen con autorizado = 0.
     */
    public static function getEstudiantesCorte($idCorte, $anio)
    {
        JWTService::requerirAutenticacion();

        try {
            if (!is_numeric($anio)) {
                Flight::json(['error' => true, 'message' => 'Año inválido'], 400);
                return;
            }

            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    e.id AS id_estudiante,
                    p.id AS id_persona,
                    CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_estudiante,
                    p.numero_identificacion,
                    g.id AS id_grupo,
                    g.nombre AS nombre_grupo,
                    g.color AS color_grupo,
                    COALESCE(a.autorizado, 0) AS autorizado,
                    a.fecha_autorizacion,
                    COALESCE(v.saldo_vencido, 0) AS saldo_vencido,
                    COALESCE(v.cuentas_vencidas, 0) AS cuentas_vencidas,
                    COALESCE(v.dias_vencido_max, 0) AS dias_vencido_max
                FROM estudiantes e
                INNER JOIN personas p ON p.id = e.id_persona
                -- El grupo es aquel en el que el estudiante esta activo en el
                -- ano mas reciente. Solo entran los de grupos calificables,
                -- porque son los unicos que producen informe.
                INNER JOIN estudiantes_x_grupos exg
                    ON exg.id = (
                        SELECT x.id
                        FROM estudiantes_x_grupos x
                        WHERE x.id_estudiante = e.id
                            AND x.id_tenant = e.id_tenant
                            AND x.activo = 1
                        ORDER BY x.anio DESC
                        LIMIT 1
                    )
                INNER JOIN grupos g
                    ON g.id = exg.id_grupo
                    AND g.calificable = 1
                LEFT JOIN autorizaciones_informes_estudiantes a
                    ON a.id_estudiante = e.id
                    AND a.id_tenant = e.id_tenant
                    AND a.id_corte_academico = :id_corte
                LEFT JOIN (
                    SELECT
                        c.id_persona,
                        SUM(ROUND(c.valor - COALESCE(ap.total_aplicado, 0), 2)) AS saldo_vencido,
                        COUNT(*) AS cuentas_vencidas,
                        -- Dias del cobro vencido mas viejo, para poder filtrar
                        -- por antiguedad de la cartera.
                        MAX(DATEDIFF(CURDATE(), c.fecha)) AS dias_vencido_max
                    FROM cuentas_por_cobrar c
                    LEFT JOIN (
                        SELECT cp.id_cuenta_por_cobrar, SUM(cp.valor_aplicado) AS total_aplicado
                        FROM cuenta_pagada cp
                        INNER JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
                        WHERE cp.id_tenant = :id_tenant_cp
                            AND (pr.anulado = 0 OR pr.anulado IS NULL)
                        GROUP BY cp.id_cuenta_por_cobrar
                    ) ap ON ap.id_cuenta_por_cobrar = c.id
                    WHERE c.id_tenant = :id_tenant_cxc
                        AND (c.anulado = 0 OR c.anulado IS NULL)
                        AND c.fecha < CURDATE()
                        AND ROUND(c.valor - COALESCE(ap.total_aplicado, 0), 2) > 0
                    GROUP BY c.id_persona
                ) v ON v.id_persona = p.id
                WHERE e.id_tenant = :id_tenant
                    AND e.activo = 1
                ORDER BY g.nombre, p.primer_apellido, p.primer_nombre
            ");

            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant_cp', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant_cxc', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_corte', $idCorte);
            $sentence->execute();

            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en getEstudiantesCorte: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener los estudiantes del corte'
            ], 500);
        }
    }

    /**
     * Conceptos vencidos de un estudiante, para el detalle que se abre al hacer
     * clic en el chip de saldo. Mismo criterio que getEstudiantesCorte.
     */
    public static function getConceptosVencidos($idEstudiante)
    {
        JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    c.id AS id_cuenta_por_cobrar,
                    c.fecha AS fecha_cuenta,
                    c.detalle AS detalle_cuenta,
                    c.valor AS valor_cuenta,
                    c.es_mora,
                    COALESCE(ap.total_aplicado, 0) AS valor_abonado,
                    ROUND(c.valor - COALESCE(ap.total_aplicado, 0), 2) AS saldo_pendiente,
                    ps.nombre AS nombre_producto,
                    cps.nombre AS nombre_clasificacion,
                    DATEDIFF(CURDATE(), c.fecha) AS dias_vencido
                FROM cuentas_por_cobrar c
                INNER JOIN estudiantes e ON e.id_persona = c.id_persona AND e.id_tenant = c.id_tenant
                INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
                LEFT JOIN clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
                LEFT JOIN (
                    SELECT cp.id_cuenta_por_cobrar, SUM(cp.valor_aplicado) AS total_aplicado
                    FROM cuenta_pagada cp
                    INNER JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
                    WHERE cp.id_tenant = :id_tenant_cp
                        AND (pr.anulado = 0 OR pr.anulado IS NULL)
                    GROUP BY cp.id_cuenta_por_cobrar
                ) ap ON ap.id_cuenta_por_cobrar = c.id
                WHERE c.id_tenant = :id_tenant
                    AND e.id = :id_estudiante
                    AND (c.anulado = 0 OR c.anulado IS NULL)
                    AND c.fecha < CURDATE()
                    AND ROUND(c.valor - COALESCE(ap.total_aplicado, 0), 2) > 0
                ORDER BY c.fecha, ps.nombre
            ");

            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant_cp', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_estudiante', $idEstudiante);
            $sentence->execute();

            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en getConceptosVencidos: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener los conceptos vencidos'
            ], 500);
        }
    }

    /**
     * Sprints cuyo informe puede ver el acudiente de un estudiante.
     *
     * Los dos candados juntos: el sprint es el sprint_informe de su corte y esta
     * finalizado, y el estudiante tiene autorizacion para ese corte. Reemplaza a
     * la regla vieja del portal, que solo miraba si la fecha final ya habia pasado.
     *
     * Devuelve la misma forma que ya usaba el portal (id, anio, nombre_sprint,
     * fecha_final, corte) para no cambiar lo que arma la pantalla.
     */
    public static function getSprintsPublicables($idEstudiante)
    {
        JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    s.id,
                    s.anio,
                    s.numero_sprint,
                    s.nombre_sprint,
                    s.fecha_inicial,
                    s.fecha_final,
                    s.id_corte_academico,
                    ca.nombre AS nombre_corte_academico,
                    a.fecha_autorizacion
                FROM sprints s
                INNER JOIN cortes_academicos ca
                    ON ca.id = s.id_corte_academico
                    AND ca.id_tenant = s.id_tenant
                INNER JOIN autorizaciones_informes_estudiantes a
                    ON a.id_corte_academico = s.id_corte_academico
                    AND a.id_tenant = s.id_tenant
                    AND a.autorizado = 1
                WHERE s.id_tenant = :id_tenant
                    AND s.sprint_informe = 1
                    AND s.finalizado = 1
                    AND a.id_estudiante = :id_estudiante
                ORDER BY s.anio DESC, ca.orden DESC
            ");

            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_estudiante', $idEstudiante);
            $sentence->execute();

            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en getSprintsPublicables: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener los informes disponibles'
            ], 500);
        }
    }

    /**
     * Guardado en lote de las autorizaciones de un corte.
     *
     * Espera: id_corte_academico, anio y estudiantes[] con id_estudiante y
     * autorizado. Se resuelve con un upsert sobre el indice unico de tenant,
     * corte y estudiante, para no tener que consultar antes cuales existen.
     *
     * La fecha y el usuario solo se guardan cuando queda autorizado; al quitar
     * la autorizacion se limpian, para no dejar un rastro que sugiera lo
     * contrario de lo que dice la fila.
     */
    public static function guardarLote()
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db();

        try {
            $idCorte = Flight::request()->data['id_corte_academico'] ?? null;
            $anio = Flight::request()->data['anio'] ?? null;
            $estudiantes = Flight::request()->data['estudiantes'] ?? [];

            if (!$idCorte || !$anio) {
                Flight::json([
                    'error' => true,
                    'message' => 'Faltan el corte académico o el año'
                ], 400);
                return;
            }

            $db->beginTransaction();

            $sentence = $db->prepare("
                INSERT INTO autorizaciones_informes_estudiantes
                    (id, id_tenant, anio, id_corte_academico, id_estudiante,
                     autorizado, fecha_autorizacion, id_usuario_autoriza)
                VALUES
                    (:id, :id_tenant, :anio, :id_corte, :id_estudiante,
                     :autorizado, :fecha, :id_usuario)
                ON DUPLICATE KEY UPDATE
                    anio = VALUES(anio),
                    autorizado = VALUES(autorizado),
                    fecha_autorizacion = VALUES(fecha_autorizacion),
                    id_usuario_autoriza = VALUES(id_usuario_autoriza)
            ");

            $totalAutorizados = 0;

            foreach ($estudiantes as $item) {
                $idEstudiante = $item['id_estudiante'] ?? null;
                if (!$idEstudiante) continue;

                $autorizado = !empty($item['autorizado']) ? 1 : 0;
                $fecha = $autorizado ? date('Y-m-d H:i:s') : null;
                $idUsuario = $autorizado ? $userData->id : null;

                $sentence->bindValue(':id', Uuid::generar());
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindValue(':anio', $anio, PDO::PARAM_INT);
                $sentence->bindValue(':id_corte', $idCorte);
                $sentence->bindValue(':id_estudiante', $idEstudiante);
                $sentence->bindValue(':autorizado', $autorizado, PDO::PARAM_INT);
                $sentence->bindValue(':fecha', $fecha);
                $sentence->bindValue(':id_usuario', $idUsuario);
                $sentence->execute();

                if ($autorizado) $totalAutorizados++;
            }

            $db->commit();

            Flight::json([
                'success' => true,
                'mensaje' => 'Autorizaciones guardadas correctamente',
                'total_procesados' => count($estudiantes),
                'total_autorizados' => $totalAutorizados
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en guardarLote de autorizaciones de informes: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al guardar las autorizaciones',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
}