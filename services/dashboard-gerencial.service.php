<?php
class DashboardGerencial
{
    /**
     * Configura la zona horaria de la sesión a Colombia (UTC-5)
     */
    private static function setTimeZone()
    {
        $db = Flight::db();
        $db->exec("SET time_zone = '-05:00'");
    }

    // =========================================================
    // RESUMEN GENERAL (CONTADORES)
    // =========================================================
    public static function getResumen()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
            $esHoy = ($fecha === date('Y-m-d'));

            $asistencia = self::calcularAsistencia($db, $fecha, $esHoy);
            $colaboradores = self::calcularColaboradores($db, $fecha, $esHoy);
            $alimentacion = self::calcularAlimentacion($db, $fecha, $asistencia['total_asistieron']);

            Flight::json([
                'fecha' => $fecha,
                'asistencia' => $asistencia,
                'colaboradores' => $colaboradores,
                'alimentacion' => $alimentacion
            ]);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // DETALLE ASISTENCIA ESTUDIANTES
    // =========================================================
    public static function getAsistenciaDetalle()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
            $fechaAyer = date('Y-m-d', strtotime($fecha . ' -1 day'));

            $sql = "SELECT 
                    ae.id AS id_asistencia,
                    e.id AS id_estudiante,
                    g.id AS id_grupo,
                    g.nombre AS nombre_grupo,
                    g.color,
                    g.icono,
                    g.orden AS orden_grupo,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    e.permanente,
                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                    ae.fecha_ingreso,
                    ae.fecha_salida,
                    CASE 
                        WHEN ae.id IS NOT NULL THEN TIME_FORMAT(TIME(ae.fecha_ingreso), '%H:%i')
                        ELSE NULL
                    END AS hora_ingreso,
                    CASE 
                        WHEN ae.fecha_salida IS NOT NULL THEN TIME_FORMAT(TIME(ae.fecha_salida), '%H:%i')
                        ELSE NULL
                    END AS hora_salida,
                    CASE
                        WHEN ae.id IS NULL THEN 'No asistió'
                        WHEN ae.fecha_salida IS NULL THEN 'En el jardín'
                        ELSE 'Salió'
                    END AS estado,
                    ds.hora_entrada AS hora_entrada_esperada,
                    ds.hora_salida AS hora_salida_esperada,
                    CASE
                        WHEN ae.id IS NOT NULL AND ds.hora_entrada IS NOT NULL
                             AND TIME(ae.fecha_ingreso) > ds.hora_entrada THEN 1
                        ELSE 0
                    END AS entrada_tarde,
                    CASE
                        WHEN ae.fecha_salida IS NOT NULL AND ds.hora_salida IS NOT NULL
                             AND TIME(ae.fecha_salida) > ds.hora_salida THEN 1
                        ELSE 0
                    END AS salida_tarde,
                    (
                        SELECT MAX(DATE(ae2.fecha_ingreso))
                        FROM asistencia_estudiantes ae2
                        WHERE ae2.id_estudiante = e.id
                        AND DATE(ae2.fecha_ingreso) < :fecha_ref
                    ) AS ultima_fecha_asistencia_raw
                FROM estudiantes e
                INNER JOIN personas p ON e.id_persona = p.id
                INNER JOIN estudiantes_x_grupos exg ON e.id = exg.id_estudiante AND exg.activo = 1
                INNER JOIN grupos g ON exg.id_grupo = g.id
                LEFT JOIN asistencia_estudiantes ae 
                    ON ae.id_estudiante = e.id 
                    AND DATE(ae.fecha_ingreso) = :fecha
                LEFT JOIN calendarios c ON c.fecha = :fecha_cal
                LEFT JOIN dias_semana ds ON ds.id = c.id_dia_semana
                WHERE e.activo = 1
                AND e.id_tenant = " . TenantContext::id() . "
                ORDER BY g.orden, g.nombre, p.primer_nombre, p.primer_apellido";

            $sentence = $db->prepare($sql);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindParam(':fecha_ref', $fecha);
            $sentence->bindParam(':fecha_cal', $fecha);
            $sentence->execute();
            $registros = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // Días hábiles desde la última asistencia para los ausentes
            $fechasUltimas = [];
            foreach ($registros as $r) {
                if ($r['estado'] === 'No asistió' && !empty($r['ultima_fecha_asistencia_raw'])) {
                    $fechasUltimas[$r['ultima_fecha_asistencia_raw']] = true;
                }
            }

            $mapDiasHabiles = [];
            foreach (array_keys($fechasUltimas) as $fIni) {
                $stmtD = $db->prepare("SELECT COUNT(*) AS total_habiles
                    FROM calendarios
                    WHERE dia_habil = 1
                      AND fecha > :ini
                      AND fecha < :fin");
                $stmtD->bindParam(':ini', $fIni);
                $stmtD->bindParam(':fin', $fecha);
                $stmtD->execute();
                $row = $stmtD->fetch(PDO::FETCH_ASSOC);
                $mapDiasHabiles[$fIni] = isset($row['total_habiles']) ? (int)$row['total_habiles'] : 0;
            }

            foreach ($registros as &$r) {
                $ultima = $r['ultima_fecha_asistencia_raw'];
                if ($r['estado'] !== 'No asistió') {
                    $r['ultima_asistencia'] = null;
                    $r['dias_habiles_ausente'] = null;
                } elseif (empty($ultima)) {
                    $r['ultima_asistencia'] = 'Nunca';
                    $r['dias_habiles_ausente'] = null;
                } else {
                    $r['ultima_asistencia'] = ($ultima === $fechaAyer) ? 'Ayer' : $ultima;
                    $r['dias_habiles_ausente'] = isset($mapDiasHabiles[$ultima]) ? $mapDiasHabiles[$ultima] : 0;
                }
            }
            unset($r);

            // Último recordatorio de asistencia por estudiante
            $idsEstudiantes = [];
            foreach ($registros as $r) {
                if (!empty($r['id_estudiante'])) {
                    $idsEstudiantes[] = $r['id_estudiante'];
                }
            }

            $recordatorios = [];
            if (count($idsEstudiantes) > 0) {
                $placeholders = implode(',', array_fill(0, count($idsEstudiantes), '?'));
                $sqlRec = "SELECT hra.id_estudiante, hra.tipo_recordatorio, hra.fecha_envio
                           FROM historial_recordatorios_asistencia hra
                           INNER JOIN (
                               SELECT id_estudiante, MAX(fecha_envio) AS max_fecha
                               FROM historial_recordatorios_asistencia
                               WHERE id_estudiante IN ($placeholders)
                               GROUP BY id_estudiante
                           ) ult ON hra.id_estudiante = ult.id_estudiante 
                                AND hra.fecha_envio = ult.max_fecha";
                $stmtR = $db->prepare($sqlRec);
                $stmtR->execute($idsEstudiantes);
                foreach ($stmtR->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $recordatorios[$row['id_estudiante']] = [
                        'tipo' => $row['tipo_recordatorio'],
                        'fecha_envio' => $row['fecha_envio']
                    ];
                }
            }

            foreach ($registros as &$r) {
                $idEst = !empty($r['id_estudiante']) ? $r['id_estudiante'] : null;
                if ($idEst !== null && isset($recordatorios[$idEst])) {
                    $r['recordatorio'] = $recordatorios[$idEst];
                } else {
                    $r['recordatorio'] = null;
                }
            }
            unset($r);

            Flight::json([
                'fecha' => $fecha,
                'total' => count($registros),
                'registros' => $registros
            ]);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // DETALLE COLABORADORES
    // =========================================================
    public static function getColaboradoresDetalle()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
            $esHoy = ($fecha === date('Y-m-d'));

            // Día de la semana ISO (1=Lunes ... 7=Domingo) — coincide con dias_semana.id
            $diaSemanaNumero = (int)date('N', strtotime($fecha));

            $sql = "SELECT 
                    c.id AS id_colaborador,
                    c.sobrenombre,
                    c.valida_ingreso_jornada,
                    c.valida_ingreso_descanso,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                    car.nombre AS nombre_cargo,
                    hc.hora_entrada AS hora_entrada_esperada,
                    hc.hora_salida AS hora_salida_esperada,
                    hc.hora_inicio_descanso AS hora_inicio_descanso_esperada,
                    hc.hora_fin_descanso AS hora_fin_descanso_esperada,
                    -- Registros del día
                    (SELECT TIME_FORMAT(ra.hora_registro, '%H:%i')
                        FROM registros_asistencia_colaboradores ra
                        INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
                        WHERE ra.id_colaborador = c.id AND ra.fecha = :fecha
                          AND tra.codigo = 'jornada_entrada'
                        ORDER BY ra.hora_registro ASC LIMIT 1) AS hora_entrada,
                    (SELECT era.codigo
                        FROM registros_asistencia_colaboradores ra
                        INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
                        LEFT JOIN estados_registro_asistencia era ON ra.id_estado = era.id
                        WHERE ra.id_colaborador = c.id AND ra.fecha = :fecha_1
                          AND tra.codigo = 'jornada_entrada'
                        ORDER BY ra.hora_registro ASC LIMIT 1) AS estado_entrada,
                    (SELECT TIME_FORMAT(ra.hora_registro, '%H:%i')
                        FROM registros_asistencia_colaboradores ra
                        INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
                        WHERE ra.id_colaborador = c.id AND ra.fecha = :fecha_2
                          AND tra.codigo = 'descanso_salida'
                        ORDER BY ra.hora_registro DESC LIMIT 1) AS hora_inicio_descanso,
                    (SELECT TIME_FORMAT(ra.hora_registro, '%H:%i')
                        FROM registros_asistencia_colaboradores ra
                        INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
                        WHERE ra.id_colaborador = c.id AND ra.fecha = :fecha_3
                          AND tra.codigo = 'descanso_regreso'
                        ORDER BY ra.hora_registro DESC LIMIT 1) AS hora_fin_descanso,
                    (SELECT TIME_FORMAT(ra.hora_registro, '%H:%i')
                        FROM registros_asistencia_colaboradores ra
                        INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
                        WHERE ra.id_colaborador = c.id AND ra.fecha = :fecha_4
                          AND tra.codigo = 'jornada_salida'
                        ORDER BY ra.hora_registro DESC LIMIT 1) AS hora_salida
                FROM colaboradores c
                INNER JOIN personas p ON c.id_persona = p.id
                LEFT JOIN cargos car ON c.id_cargo = car.id
                LEFT JOIN horarios_colaboradores hc 
                    ON hc.id_colaborador = c.id 
                    AND hc.dia_semana = :dia_semana
                    AND hc.activo = 1
                WHERE c.activo = 1
                  AND c.valida_ingreso_jornada = 1
                  AND c.id_tenant = " . TenantContext::id() . "
                ORDER BY p.primer_nombre, p.primer_apellido";

            $sentence = $db->prepare($sql);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindParam(':fecha_1', $fecha);
            $sentence->bindParam(':fecha_2', $fecha);
            $sentence->bindParam(':fecha_3', $fecha);
            $sentence->bindParam(':fecha_4', $fecha);
            $sentence->bindParam(':dia_semana', $diaSemanaNumero);
            $sentence->execute();
            $registros = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // Calcular estado final en PHP (más claro que anidar otro CASE enorme)
            foreach ($registros as &$r) {
                $r['estado'] = self::calcularEstadoColaborador($r, $esHoy);
                $r['entrada_tarde'] = ($r['estado_entrada'] === 'tarde') ? 1 : 0;
                $r['nombre_cargo'] = $r['nombre_cargo'] ?: 'Sin cargo';
            }
            unset($r);

            Flight::json([
                'fecha' => $fecha,
                'total' => count($registros),
                'registros' => $registros
            ]);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // HELPERS
    // =========================================================

    private static function calcularAsistencia($db, $fecha, $esHoy)
    {
        // Activos por grupo, separando permanentes y temporales.
        // Subconsulta DISTINCT para garantizar 1 fila por estudiante por grupo,
        // evitando duplicados si el estudiante está en varios grupos activos.
        $sqlActivos = "SELECT 
                g.id AS id_grupo,
                g.nombre AS nombre_grupo,
                g.color,
                g.icono,
                g.orden,
                SUM(CASE WHEN sub.permanente = 1 THEN 1 ELSE 0 END) AS activos_permanentes,
                SUM(CASE WHEN sub.permanente = 0 THEN 1 ELSE 0 END) AS activos_temporales,
                COUNT(*) AS total_activos
            FROM grupos g
            INNER JOIN (
                SELECT DISTINCT e.id, e.permanente, exg.id_grupo
                FROM estudiantes e
                INNER JOIN estudiantes_x_grupos exg ON e.id = exg.id_estudiante AND exg.activo = 1
                WHERE e.activo = 1 AND e.id_tenant = " . TenantContext::id() . "
            ) sub ON sub.id_grupo = g.id
            GROUP BY g.id, g.nombre, g.color, g.icono, g.orden
            ORDER BY g.orden, g.nombre";

        $s = $db->prepare($sqlActivos);
        $s->execute();
        $gruposActivos = $s->fetchAll(PDO::FETCH_ASSOC);

        // Asistieron hoy por grupo, separando permanentes y temporales.
        // Subconsulta DISTINCT para garantizar 1 fila por estudiante por grupo,
        // evitando duplicados si tiene varios registros de asistencia el mismo día.
        $sqlAsistieron = "SELECT 
                g.id AS id_grupo,
                SUM(CASE WHEN sub.permanente = 1 THEN 1 ELSE 0 END) AS asistieron_permanentes,
                SUM(CASE WHEN sub.permanente = 0 THEN 1 ELSE 0 END) AS asistieron_temporales,
                COUNT(*) AS total
            FROM grupos g
            INNER JOIN (
                SELECT DISTINCT e.id, e.permanente, exg.id_grupo
                FROM asistencia_estudiantes ae
                INNER JOIN estudiantes e ON ae.id_estudiante = e.id
                INNER JOIN estudiantes_x_grupos exg ON e.id = exg.id_estudiante AND exg.activo = 1
                WHERE DATE(ae.fecha_ingreso) = :fecha AND ae.id_tenant = " . TenantContext::id() . "
            ) sub ON sub.id_grupo = g.id
            GROUP BY g.id";

        $s2 = $db->prepare($sqlAsistieron);
        $s2->bindParam(':fecha', $fecha);
        $s2->execute();
        $asistieronRaw = $s2->fetchAll(PDO::FETCH_ASSOC);

        $mapAsistieron = [];
        foreach ($asistieronRaw as $row) {
            $mapAsistieron[$row['id_grupo']] = [
                'permanentes' => (int)$row['asistieron_permanentes'],
                'temporales' => (int)$row['asistieron_temporales'],
                'total' => (int)$row['total']
            ];
        }

        // Presentes ahora: solo cuenta para fecha=hoy (los que aún no salen)
        $mapPresentesAhora = [];
        if ($esHoy) {
            $sqlPresentes = "SELECT 
                    g.id AS id_grupo,
                    COUNT(DISTINCT ae.id_estudiante) AS total
                FROM asistencia_estudiantes ae
                INNER JOIN estudiantes e ON ae.id_estudiante = e.id
                INNER JOIN estudiantes_x_grupos exg ON e.id = exg.id_estudiante AND exg.activo = 1
                INNER JOIN grupos g ON exg.id_grupo = g.id
                WHERE DATE(ae.fecha_ingreso) = :fecha
                  AND ae.fecha_salida IS NULL
                  AND ae.id_tenant = " . TenantContext::id() . "
                GROUP BY g.id";

            $s3 = $db->prepare($sqlPresentes);
            $s3->bindParam(':fecha', $fecha);
            $s3->execute();
            $presentesRaw = $s3->fetchAll(PDO::FETCH_ASSOC);

            foreach ($presentesRaw as $row) {
                $mapPresentesAhora[$row['id_grupo']] = (int)$row['total'];
            }
        }

        $porGrupo = [];
        $totalActivos = 0;
        $totalActivosPermanentes = 0;
        $totalActivosTemporales = 0;
        $totalAsistieron = 0;
        $totalAsistieronPermanentes = 0;
        $totalAsistieronTemporales = 0;
        $totalPresentesAhora = 0;

        foreach ($gruposActivos as $grupo) {
            $idGrupo = $grupo['id_grupo'];
            $activosPerm = (int)$grupo['activos_permanentes'];
            $activosTemp = (int)$grupo['activos_temporales'];
            $activos = (int)$grupo['total_activos'];

            $asPerm = isset($mapAsistieron[$idGrupo]) ? $mapAsistieron[$idGrupo]['permanentes'] : 0;
            $asTemp = isset($mapAsistieron[$idGrupo]) ? $mapAsistieron[$idGrupo]['temporales'] : 0;
            $asistieron = isset($mapAsistieron[$idGrupo]) ? $mapAsistieron[$idGrupo]['total'] : 0;

            $presentesAhora = isset($mapPresentesAhora[$idGrupo]) ? $mapPresentesAhora[$idGrupo] : 0;

            $porcentaje = $activos > 0 ? round(($asistieron / $activos) * 100, 2) : 0;

            $porGrupo[] = [
                'id_grupo' => $idGrupo,
                'nombre_grupo' => $grupo['nombre_grupo'],
                'color' => $grupo['color'],
                'icono' => $grupo['icono'],
                'orden' => (int)$grupo['orden'],
                'asistieron' => $asistieron,
                'asistieron_permanentes' => $asPerm,
                'asistieron_temporales' => $asTemp,
                'presentes_ahora' => $presentesAhora,
                'total' => $activos,
                'total_permanentes' => $activosPerm,
                'total_temporales' => $activosTemp,
                'porcentaje' => $porcentaje
            ];

            $totalActivos += $activos;
            $totalActivosPermanentes += $activosPerm;
            $totalActivosTemporales += $activosTemp;
            $totalAsistieron += $asistieron;
            $totalAsistieronPermanentes += $asPerm;
            $totalAsistieronTemporales += $asTemp;
            $totalPresentesAhora += $presentesAhora;
        }

        $porcentajeGeneral = $totalActivos > 0
            ? round(($totalAsistieron / $totalActivos) * 100, 2)
            : 0;

        $porcentajePermanentes = $totalActivosPermanentes > 0
            ? round(($totalAsistieronPermanentes / $totalActivosPermanentes) * 100, 2)
            : 0;

        $porcentajeTemporales = $totalActivosTemporales > 0
            ? round(($totalAsistieronTemporales / $totalActivosTemporales) * 100, 2)
            : 0;

        $totalSalieron = $esHoy ? max(0, $totalAsistieron - $totalPresentesAhora) : 0;

        return [
            'total_asistieron' => $totalAsistieron,
            'total_asistieron_permanentes' => $totalAsistieronPermanentes,
            'total_asistieron_temporales' => $totalAsistieronTemporales,
            'total_presentes_ahora' => $totalPresentesAhora,
            'total_salieron' => $totalSalieron,
            'total_activos' => $totalActivos,
            'total_activos_permanentes' => $totalActivosPermanentes,
            'total_activos_temporales' => $totalActivosTemporales,
            'porcentaje' => $porcentajeGeneral,
            'porcentaje_permanentes' => $porcentajePermanentes,
            'porcentaje_temporales' => $porcentajeTemporales,
            'es_hoy' => $esHoy,
            'por_grupo' => $porGrupo
        ];
    }

    private static function calcularColaboradores($db, $fecha, $esHoy)
    {
        // Total colaboradores activos (solo los que deben validar ingreso de jornada)
        $sqlTotal = "SELECT COUNT(*) AS total
            FROM colaboradores c
            WHERE c.activo = 1 AND c.valida_ingreso_jornada = 1 AND c.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlTotal);
        $stmt->execute();
        $totalRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalActivos = (int)$totalRow['total'];

        // Agregados del día
        $sqlAg = "SELECT 
                -- Colaboradores que registraron entrada de jornada
                COUNT(DISTINCT CASE WHEN tra.codigo = 'jornada_entrada' THEN ra.id_colaborador END) AS ingresaron,
                -- Colaboradores que registraron salida de jornada
                COUNT(DISTINCT CASE WHEN tra.codigo = 'jornada_salida' THEN ra.id_colaborador END) AS salieron,
                -- Entradas tarde
                COUNT(DISTINCT CASE WHEN tra.codigo = 'jornada_entrada' AND era.codigo = 'tarde' THEN ra.id_colaborador END) AS tarde,
                -- Descansos iniciados
                COUNT(DISTINCT CASE WHEN tra.codigo = 'descanso_salida' THEN ra.id_colaborador END) AS descansos_iniciados,
                -- Descansos terminados
                COUNT(DISTINCT CASE WHEN tra.codigo = 'descanso_regreso' THEN ra.id_colaborador END) AS descansos_terminados
            FROM registros_asistencia_colaboradores ra
            INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
            LEFT JOIN estados_registro_asistencia era ON ra.id_estado = era.id
            INNER JOIN colaboradores c ON ra.id_colaborador = c.id
            WHERE ra.fecha = :fecha AND c.activo = 1 AND ra.id_tenant = " . TenantContext::id();
        $s = $db->prepare($sqlAg);
        $s->bindParam(':fecha', $fecha);
        $s->execute();
        $ag = $s->fetch(PDO::FETCH_ASSOC);

        $ingresaron = (int)$ag['ingresaron'];
        $salieron = (int)$ag['salieron'];
        $tarde = (int)$ag['tarde'];
        $enDescanso = max(0, (int)$ag['descansos_iniciados'] - (int)$ag['descansos_terminados']);

        // "Presentes ahora" (solo tiene sentido en el día actual):
        // = ingresaron - salieron
        $presentes = max(0, $ingresaron - $salieron);
        $noIngresaron = max(0, $totalActivos - $ingresaron);

        // Porcentaje de presentes según el día:
        // - hoy: presentes ahora / total activos
        // - pasado: ingresaron / total activos (histórico)
        $base = $esHoy ? $presentes : $ingresaron;
        $porcentaje = $totalActivos > 0 ? round(($base / $totalActivos) * 100, 2) : 0;

        return [
            'total_activos' => $totalActivos,
            'presentes' => $presentes,
            'ingresaron' => $ingresaron,
            'salieron' => $salieron,
            'en_descanso' => $enDescanso,
            'tarde' => $tarde,
            'no_ingresaron' => $noIngresaron,
            'porcentaje' => $porcentaje,
            'es_hoy' => $esHoy
        ];
    }

    /**
     * Calcula los KPIs de alimentación separando mensuales y diarios.
     * Por cada horario_alimentacion devuelve:
     *   - mensuales_servidos / mensuales_contratados (con %)
     *   - diarios_servidos
     * Mensuales: tienen contrato del mes → tienen denominador (contratados)
     * Diarios: solo existe el registro si se consumió → no tienen denominador útil
     */
    private static function calcularAlimentacion($db, $fecha, $totalAsistieron = 0)
    {
        $fechaObj = new DateTime($fecha);
        $anio = $fechaObj->format('Y');
        $mes = $fechaObj->format('m');

        // MENSUALES SERVIDOS por horario (contratos del mes cuyo estudiante asistió hoy)
        $sqlMensServidos = "SELECT 
                ha.id AS id_horario,
                ha.nombre AS nombre_horario,
                ha.orden AS orden_horario,
                COUNT(DISTINCT cpc.id) AS total
            FROM cuentas_por_cobrar cpc
            INNER JOIN estudiantes e ON cpc.id_persona = e.id_persona
            INNER JOIN productos_servicios ps ON cpc.id_producto_servicio = ps.id
            INNER JOIN horarios_alimentacion ha ON cpc.id_horario_alimentacion = ha.id
            INNER JOIN asistencia_estudiantes ae ON e.id = ae.id_estudiante
                AND DATE(ae.fecha_ingreso) = :fecha_ingreso
            WHERE ps.id_clasificacion_productos_servicios IN (SELECT cps.id FROM clasificacion_productos_servicios cps WHERE cps.codigo = 'ALIMENTACION' AND cps.id_tenant = ps.id_tenant)
              AND cpc.anulado = 0
              AND ps.id_periodicidad_cobro = 2
              AND YEAR(cpc.fecha) = :anio
              AND MONTH(cpc.fecha) = :mes
              AND cpc.id_tenant = " . TenantContext::id() . "
            GROUP BY ha.id, ha.nombre, ha.orden";

        $s = $db->prepare($sqlMensServidos);
        $s->bindValue(':fecha_ingreso', $fecha);
        $s->bindValue(':anio', $anio);
        $s->bindValue(':mes', $mes);
        $s->execute();
        $mensServidosRaw = $s->fetchAll(PDO::FETCH_ASSOC);

        // MENSUALES CONTRATADOS por horario (todos los contratos del mes)
        $sqlMensContratados = "SELECT 
                ha.id AS id_horario,
                ha.nombre AS nombre_horario,
                ha.orden AS orden_horario,
                COUNT(DISTINCT cpc.id) AS total
            FROM cuentas_por_cobrar cpc
            INNER JOIN productos_servicios ps ON cpc.id_producto_servicio = ps.id
            INNER JOIN horarios_alimentacion ha ON cpc.id_horario_alimentacion = ha.id
            WHERE ps.id_clasificacion_productos_servicios IN (SELECT cps.id FROM clasificacion_productos_servicios cps WHERE cps.codigo = 'ALIMENTACION' AND cps.id_tenant = ps.id_tenant)
              AND cpc.anulado = 0
              AND ps.id_periodicidad_cobro = 2
              AND YEAR(cpc.fecha) = :anio
              AND MONTH(cpc.fecha) = :mes
              AND cpc.id_tenant = " . TenantContext::id() . "
            GROUP BY ha.id, ha.nombre, ha.orden";

        $s2 = $db->prepare($sqlMensContratados);
        $s2->bindValue(':anio', $anio);
        $s2->bindValue(':mes', $mes);
        $s2->execute();
        $mensContratadosRaw = $s2->fetchAll(PDO::FETCH_ASSOC);

        // DIARIOS SERVIDOS por horario (CPC del día)
        $sqlDiariosServidos = "SELECT 
                ha.id AS id_horario,
                ha.nombre AS nombre_horario,
                ha.orden AS orden_horario,
                COUNT(DISTINCT cpc.id) AS total
            FROM cuentas_por_cobrar cpc
            INNER JOIN productos_servicios ps ON cpc.id_producto_servicio = ps.id
            INNER JOIN horarios_alimentacion ha ON cpc.id_horario_alimentacion = ha.id
            WHERE ps.id_clasificacion_productos_servicios IN (SELECT cps.id FROM clasificacion_productos_servicios cps WHERE cps.codigo = 'ALIMENTACION' AND cps.id_tenant = ps.id_tenant)
              AND cpc.anulado = 0
              AND ps.id_periodicidad_cobro = 3
              AND cpc.fecha = :fecha
              AND cpc.id_tenant = " . TenantContext::id() . "
            GROUP BY ha.id, ha.nombre, ha.orden";

        $s3 = $db->prepare($sqlDiariosServidos);
        $s3->bindValue(':fecha', $fecha);
        $s3->execute();
        $diariosServidosRaw = $s3->fetchAll(PDO::FETCH_ASSOC);

        // Consolidar por horario en un solo mapa
        $horarios = [];

        $registrarHorario = function (&$horarios, $id, $nombre, $orden) {
            if (!isset($horarios[$id])) {
                $horarios[$id] = [
                    'id_horario' => $id,
                    'nombre_horario' => $nombre,
                    'orden' => (int)$orden,
                    'mensuales_servidos' => 0,
                    'mensuales_contratados' => 0,
                    'diarios_servidos' => 0
                ];
            }
        };

        foreach ($mensContratadosRaw as $row) {
            $registrarHorario($horarios, $row['id_horario'], $row['nombre_horario'], $row['orden_horario']);
            $horarios[$row['id_horario']]['mensuales_contratados'] = (int)$row['total'];
        }
        foreach ($mensServidosRaw as $row) {
            $registrarHorario($horarios, $row['id_horario'], $row['nombre_horario'], $row['orden_horario']);
            $horarios[$row['id_horario']]['mensuales_servidos'] = (int)$row['total'];
        }
        foreach ($diariosServidosRaw as $row) {
            $registrarHorario($horarios, $row['id_horario'], $row['nombre_horario'], $row['orden_horario']);
            $horarios[$row['id_horario']]['diarios_servidos'] = (int)$row['total'];
        }

        $porHorario = array_values($horarios);

        // Totales generales y % por horario
        $totalMensServidos = 0;
        $totalMensContratados = 0;
        $totalDiariosServidos = 0;

        foreach ($porHorario as &$h) {
            $totalMensServidos += $h['mensuales_servidos'];
            $totalMensContratados += $h['mensuales_contratados'];
            $totalDiariosServidos += $h['diarios_servidos'];

            $h['porcentaje_mensuales'] = $h['mensuales_contratados'] > 0
                ? round(($h['mensuales_servidos'] / $h['mensuales_contratados']) * 100, 2)
                : 0;

            // % diarios = servidos vs estudiantes que asistieron hoy
            $h['porcentaje_diarios'] = $totalAsistieron > 0
                ? round(($h['diarios_servidos'] / $totalAsistieron) * 100, 2)
                : 0;

            $h['total_asistieron'] = $totalAsistieron;
        }
        unset($h);

        usort($porHorario, function ($a, $b) {
            if ($a['orden'] !== $b['orden']) return $a['orden'] - $b['orden'];
            return strcmp($a['nombre_horario'], $b['nombre_horario']);
        });

        $porcentajeMensuales = $totalMensContratados > 0
            ? round(($totalMensServidos / $totalMensContratados) * 100, 2)
            : 0;

        $porcentajeDiarios = $totalAsistieron > 0
            ? round(($totalDiariosServidos / $totalAsistieron) * 100, 2)
            : 0;

        return [
            'mensuales_servidos' => $totalMensServidos,
            'mensuales_contratados' => $totalMensContratados,
            'mensuales_porcentaje' => $porcentajeMensuales,
            'diarios_servidos' => $totalDiariosServidos,
            'diarios_porcentaje' => $porcentajeDiarios,
            'total_asistieron' => $totalAsistieron,
            'total_servicios' => $totalMensServidos + $totalDiariosServidos,
            'por_horario' => $porHorario
        ];
    }

    /**
     * Determina el estado actual del colaborador según sus registros del día.
     * Solo se invoca para colaboradores con valida_ingreso_jornada = 1
     * (los demás ya fueron filtrados en el query).
     *
     * Estados posibles:
     *   - 'No marcó'       → no tiene registro de entrada
     *   - 'En jornada'     → entró y no ha salido; tampoco está en descanso
     *   - 'En descanso'    → marcó inicio de descanso y no marcó regreso
     *   - 'Salió'          → registró salida de jornada
     * Para fechas pasadas se simplifica: 'Marcó entrada' / 'Salió' / 'No marcó'.
     */
    private static function calcularEstadoColaborador($r, $esHoy)
    {
        $entro = !empty($r['hora_entrada']);
        $salio = !empty($r['hora_salida']);
        $inicioDesc = !empty($r['hora_inicio_descanso']);
        $finDesc = !empty($r['hora_fin_descanso']);

        if (!$esHoy) {
            if (!$entro) return 'No marcó';
            if ($salio) return 'Salió';
            return 'Marcó entrada';
        }

        // Día actual
        if (!$entro) return 'No marcó';
        if ($salio) return 'Salió';
        if ($inicioDesc && !$finDesc) return 'En descanso';
        return 'En jornada';
    }

    // =========================================================
    // CARTERA (Financiero)
    // =========================================================
    public static function getCarteraResumen()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

            $resumen = self::calcularCartera($db, $fecha);

            Flight::json($resumen);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // RECAUDO (Financiero)
    // =========================================================
    public static function getRecaudoResumen()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

            $resumen = self::calcularRecaudo($db, $fecha);

            Flight::json($resumen);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista de pagos para el detalle de recaudo.
     * Params:
     *   - fecha: fecha global del dashboard
     *   - rango: 'hoy' | 'mes' | 'anio' (default: mes)
     */
    public static function getRecaudoDetalle()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
            $rango = isset($_GET['rango']) ? $_GET['rango'] : 'mes';

            $fechaObj = new DateTime($fecha);
            $anio = $fechaObj->format('Y');
            $mes = $fechaObj->format('m');

            // Construir filtro temporal según rango
            $whereFecha = '';
            $params = [];
            switch ($rango) {
                case 'hoy':
                    $whereFecha = "AND pr.fecha = :fecha";
                    $params[':fecha'] = $fecha;
                    break;
                case 'anio':
                    $whereFecha = "AND YEAR(pr.fecha) = :anio";
                    $params[':anio'] = $anio;
                    break;
                case 'mes':
                default:
                    $whereFecha = "AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes";
                    $params[':anio'] = $anio;
                    $params[':mes'] = $mes;
                    break;
            }

            $sql = "SELECT 
                    pr.id,
                    pr.fecha,
                    pr.valor_recibido,
                    pr.referencia_bancaria,
                    pr.observaciones,
                    pr.id_estudiante,
                    pr.id_colaborador,
                    pr.id_acudiente,
                    tp.id AS id_tipo_pago,
                    tp.nombre AS tipo_pago,
                    CASE
                        WHEN pr.id_estudiante IS NOT NULL THEN 'Estudiante'
                        WHEN pr.id_colaborador IS NOT NULL THEN 'Colaborador'
                        WHEN pr.id_acudiente IS NOT NULL THEN 'Acudiente'
                        ELSE 'Otro'
                    END AS tipo_persona,
                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_persona
                FROM pagos_recibidos pr
                INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
                LEFT JOIN estudiantes e ON pr.id_estudiante = e.id
                LEFT JOIN colaboradores c ON pr.id_colaborador = c.id
                LEFT JOIN acudientes a ON pr.id_acudiente = a.id
                LEFT JOIN personas p ON p.id = COALESCE(e.id_persona, c.id_persona, a.id_persona)
                WHERE (pr.anulado = 0 OR pr.anulado IS NULL)
                  AND pr.id_tenant = " . TenantContext::id() . "
                  $whereFecha
                ORDER BY pr.fecha DESC, pr.id DESC";

            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($registros as &$r) {
                $r['valor_recibido'] = (float)$r['valor_recibido'];
                $r['nombre_persona'] = $r['nombre_persona'] ?: 'Sin nombre';
            }
            unset($r);

            // Resumen por tipo de pago (mismo rango que la lista)
            $sqlResumen = "SELECT 
                    tp.id AS id_tipo_pago,
                    tp.nombre AS tipo_pago,
                    COUNT(*) AS cantidad,
                    COALESCE(SUM(pr.valor_recibido), 0) AS total
                FROM pagos_recibidos pr
                INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
                WHERE (pr.anulado = 0 OR pr.anulado IS NULL)
                  AND pr.id_tenant = " . TenantContext::id() . "
                  $whereFecha
                GROUP BY tp.id, tp.nombre
                ORDER BY total DESC";
            $stmtR = $db->prepare($sqlResumen);
            foreach ($params as $k => $v) $stmtR->bindValue($k, $v);
            $stmtR->execute();
            $resumenTipos = $stmtR->fetchAll(PDO::FETCH_ASSOC);
            foreach ($resumenTipos as &$t) {
                $t['cantidad'] = (int)$t['cantidad'];
                $t['total'] = (float)$t['total'];
            }
            unset($t);

            // También devolver lista de tipos de pago (para el filtro del front)
            $sqlTipos = "SELECT id, nombre FROM tipos_pagos WHERE es_ingreso = 1 AND id_tenant = " . TenantContext::id() . " ORDER BY nombre";
            $stmtT = $db->prepare($sqlTipos);
            $stmtT->execute();
            $tipos = $stmtT->fetchAll(PDO::FETCH_ASSOC);

            Flight::json([
                'fecha' => $fecha,
                'rango' => $rango,
                'total' => count($registros),
                'registros' => $registros,
                'resumen_tipos' => $resumenTipos,
                'tipos_pago' => $tipos
            ]);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista de personas con saldo pendiente. Trae a TODOS (sin limit por defecto).
     * Incluye estado activo/inactivo del estudiante o colaborador.
     */
    public static function getCarteraDetalle()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $sql = "SELECT 
                    p.id AS id_persona,
                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_persona,
                    p.numero_identificacion,
                    CASE
                        WHEN e.id IS NOT NULL THEN 'Estudiante'
                        WHEN col.id IS NOT NULL THEN 'Colaborador'
                        ELSE 'Otro'
                    END AS tipo_persona,
                    CASE
                        WHEN e.id IS NOT NULL THEN COALESCE(g.nombre, 'Sin grupo')
                        WHEN col.id IS NOT NULL THEN COALESCE(ca.nombre, 'Sin cargo')
                        ELSE 'Sin asignar'
                    END AS grupo_o_cargo,
                    CASE
                        WHEN e.id IS NOT NULL THEN COALESCE(e.activo, 0)
                        WHEN col.id IS NOT NULL THEN COALESCE(col.activo, 0)
                        ELSE 0
                    END AS activo,
                    e.id AS id_estudiante,
                    col.id AS id_colaborador,
                    COUNT(DISTINCT sub.id) AS cuentas_pendientes,
                    SUM(CASE WHEN sub.fecha < CURDATE() THEN 1 ELSE 0 END) AS cuentas_vencidas,
                    SUM(sub.saldo) AS saldo_pendiente,
                    SUM(CASE WHEN sub.fecha < CURDATE() THEN sub.saldo ELSE 0 END) AS saldo_vencido,
                    MAX(CASE WHEN sub.fecha < CURDATE() THEN DATEDIFF(CURDATE(), sub.fecha) ELSE 0 END) AS dias_max_vencido
                FROM personas p
                LEFT JOIN estudiantes e ON e.id_persona = p.id
                LEFT JOIN estudiantes_x_grupos eg ON eg.id_estudiante = e.id AND eg.activo = 1
                LEFT JOIN grupos g ON g.id = eg.id_grupo
                LEFT JOIN colaboradores col ON col.id_persona = p.id
                LEFT JOIN cargos ca ON ca.id = col.id_cargo
                INNER JOIN (
                    SELECT 
                        cpc.id,
                        cpc.id_persona,
                        cpc.fecha,
                        cpc.valor - COALESCE(SUM(
                            CASE WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado ELSE 0 END
                        ), 0) AS saldo
                    FROM cuentas_por_cobrar cpc
                    LEFT JOIN cuenta_pagada cp ON cp.id_cuenta_por_cobrar = cpc.id
                    LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                    WHERE (cpc.anulado = 0 OR cpc.anulado IS NULL)
                    AND cpc.id_tenant = " . TenantContext::id() . "
                    GROUP BY cpc.id, cpc.id_persona, cpc.fecha, cpc.valor
                    HAVING saldo > 0
                ) sub ON sub.id_persona = p.id
                WHERE p.id_tenant = " . TenantContext::id() . "
                GROUP BY p.id, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                         p.numero_identificacion, e.id, e.activo, g.nombre, col.id, col.activo, ca.nombre
                ORDER BY saldo_vencido DESC, saldo_pendiente DESC";

            $sentence = $db->prepare($sql);
            $sentence->execute();
            $registros = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // IDs de estudiantes morosos para buscar sus recordatorios
            $idsEstudiantes = [];
            foreach ($registros as $r) {
                if (!empty($r['id_estudiante'])) {
                    $idsEstudiantes[] = $r['id_estudiante'];
                }
            }

            // Mapa: id_estudiante => ultimo recordatorio (pago preferido, sino general)
            $recordatorios = [];
            if (count($idsEstudiantes) > 0) {
                $placeholders = implode(',', array_fill(0, count($idsEstudiantes), '?'));

                // Último recordatorio de pago por estudiante
                $sqlPago = "SELECT hp.id_estudiante, hp.tipo_recordatorio, hp.compromiso,
                                   hp.fecha_compromiso, hp.fecha_envio
                            FROM historial_recordatorios_pago hp
                            INNER JOIN (
                                SELECT id_estudiante, MAX(fecha_envio) AS max_fecha
                                FROM historial_recordatorios_pago
                                WHERE id_estudiante IN ($placeholders)
                                GROUP BY id_estudiante
                            ) ult ON hp.id_estudiante = ult.id_estudiante 
                                 AND hp.fecha_envio = ult.max_fecha";
                $stmtP = $db->prepare($sqlPago);
                $stmtP->execute($idsEstudiantes);
                foreach ($stmtP->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $recordatorios[$row['id_estudiante']] = [
                        'origen' => 'pago',
                        'tipo' => $row['tipo_recordatorio'],
                        'compromiso' => $row['compromiso'],
                        'fecha_compromiso' => $row['fecha_compromiso'],
                        'fecha_envio' => $row['fecha_envio']
                    ];
                }

                // Para estudiantes SIN recordatorio de pago, buscar el último general
                $sinPago = array_values(array_diff($idsEstudiantes, array_keys($recordatorios)));
                if (count($sinPago) > 0) {
                    $ph2 = implode(',', array_fill(0, count($sinPago), '?'));
                    $sqlGen = "SELECT hg.id_estudiante, hg.tipo_recordatorio, hg.compromiso,
                                      hg.fecha_compromiso, hg.fecha_envio
                               FROM historial_recordatorios_generales hg
                               INNER JOIN (
                                   SELECT id_estudiante, MAX(fecha_envio) AS max_fecha
                                   FROM historial_recordatorios_generales
                                   WHERE id_estudiante IN ($ph2)
                                   GROUP BY id_estudiante
                               ) ult ON hg.id_estudiante = ult.id_estudiante 
                                    AND hg.fecha_envio = ult.max_fecha";
                    $stmtG = $db->prepare($sqlGen);
                    $stmtG->execute($sinPago);
                    foreach ($stmtG->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $recordatorios[$row['id_estudiante']] = [
                            'origen' => 'general',
                            'tipo' => $row['tipo_recordatorio'],
                            'compromiso' => $row['compromiso'],
                            'fecha_compromiso' => $row['fecha_compromiso'],
                            'fecha_envio' => $row['fecha_envio']
                        ];
                    }
                }
            }

            foreach ($registros as &$r) {
                $r['saldo_pendiente'] = (float)$r['saldo_pendiente'];
                $r['saldo_vencido'] = (float)$r['saldo_vencido'];
                $r['cuentas_pendientes'] = (int)$r['cuentas_pendientes'];
                $r['cuentas_vencidas'] = (int)$r['cuentas_vencidas'];
                $r['dias_max_vencido'] = (int)$r['dias_max_vencido'];
                $r['activo'] = (int)$r['activo'];

                // Adjuntar último recordatorio si existe
                $idEst = !empty($r['id_estudiante']) ? $r['id_estudiante'] : null;
                if ($idEst !== null && isset($recordatorios[$idEst])) {
                    $rec = $recordatorios[$idEst];
                    $r['recordatorio'] = [
                        'origen' => $rec['origen'],
                        'tipo' => $rec['tipo'],
                        'fecha_envio' => $rec['fecha_envio'],
                        'compromiso' => $rec['compromiso'],
                        'fecha_compromiso' => $rec['fecha_compromiso']
                    ];
                } else {
                    $r['recordatorio'] = null;
                }
            }
            unset($r);

            Flight::json([
                'total' => count($registros),
                'registros' => $registros
            ]);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resumen de cartera:
     *   - saldo_pendiente, saldo_vencido (fecha < fecha global), %vencido
     *   - Saldo cartera mes / meses anteriores
     *   - Saldo por tipo de persona: estudiantes / colaboradores
     *   - Buckets de antigüedad: por vencer, 1-30, 31-60, 61-90, +90 días
     */
    private static function calcularCartera($db, $fecha)
    {
        $fechaObj = new DateTime($fecha);
        $anio = $fechaObj->format('Y');
        $mes = $fechaObj->format('m');
        $primerDiaMes = $fechaObj->format('Y-m-01');
        // Fecha del día anterior para calcular delta
        $fechaAyer = (clone $fechaObj)->modify('-1 day')->format('Y-m-d');

        // ------- 1) Estado actual de toda la cartera -------
        // Vencido = saldo > 0 AND c.fecha < fecha global (cualquier día anterior)
        $sqlEstado = "SELECT 
                SUM(c.valor) AS total_facturado,
                SUM(COALESCE(cp_sum.total_pagado, 0)) AS total_recaudado,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) AS saldo_pendiente,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 AND c.fecha < :fecha_ref 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) AS saldo_vencido,

                -- Buckets de antigüedad (saldo > 0 únicamente)
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                     AND c.fecha >= :f_por_vencer 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0) ELSE 0 
                END) AS saldo_por_vencer,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                     AND DATEDIFF(:f_30a, c.fecha) BETWEEN 1 AND 30 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0) ELSE 0 
                END) AS saldo_1_30,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                     AND DATEDIFF(:f_60a, c.fecha) BETWEEN 31 AND 60 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0) ELSE 0 
                END) AS saldo_31_60,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                     AND DATEDIFF(:f_90a, c.fecha) BETWEEN 61 AND 90 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0) ELSE 0 
                END) AS saldo_61_90,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                     AND DATEDIFF(:f_91a, c.fecha) > 90 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0) ELSE 0 
                END) AS saldo_mas_90
            FROM cuentas_por_cobrar c
            LEFT JOIN (
                SELECT 
                    cp.id_cuenta_por_cobrar,
                    SUM(CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL 
                        THEN cp.valor_aplicado 
                        ELSE 0 
                    END) AS total_pagado
                FROM cuenta_pagada cp
                LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                GROUP BY cp.id_cuenta_por_cobrar
            ) cp_sum ON c.id = cp_sum.id_cuenta_por_cobrar
            WHERE (c.anulado = 0 OR c.anulado IS NULL) AND c.id_tenant = " . TenantContext::id();

        $stmt = $db->prepare($sqlEstado);
        $stmt->bindParam(':fecha_ref', $fecha);
        $stmt->bindParam(':f_por_vencer', $fecha);
        $stmt->bindParam(':f_30a', $fecha);
        $stmt->bindParam(':f_60a', $fecha);
        $stmt->bindParam(':f_90a', $fecha);
        $stmt->bindParam(':f_91a', $fecha);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $saldoPendiente = (float)$row['saldo_pendiente'];
        $saldoVencido = (float)$row['saldo_vencido'];
        $porcentajeVencido = $saldoPendiente > 0
            ? round(($saldoVencido / $saldoPendiente) * 100, 2)
            : 0;

        // ------- Saldo vencido y pendiente de AYER (para delta) -------
        $sqlAyer = "SELECT 
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) AS saldo_pendiente,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 AND c.fecha < :fecha_ayer 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) AS saldo_vencido
            FROM cuentas_por_cobrar c
            LEFT JOIN (
                SELECT 
                    cp.id_cuenta_por_cobrar,
                    SUM(CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL 
                        THEN cp.valor_aplicado 
                        ELSE 0 
                    END) AS total_pagado
                FROM cuenta_pagada cp
                LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                  AND (pr.fecha <= :fecha_ayer2)
                GROUP BY cp.id_cuenta_por_cobrar
            ) cp_sum ON c.id = cp_sum.id_cuenta_por_cobrar
            WHERE (c.anulado = 0 OR c.anulado IS NULL)
              AND c.fecha <= :fecha_ayer3 AND c.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlAyer);
        $stmt->bindParam(':fecha_ayer', $fechaAyer);
        $stmt->bindParam(':fecha_ayer2', $fechaAyer);
        $stmt->bindParam(':fecha_ayer3', $fechaAyer);
        $stmt->execute();
        $rowAyer = $stmt->fetch(PDO::FETCH_ASSOC);

        $saldoVencidoAyer = (float)$rowAyer['saldo_vencido'];
        $deltaVencido = $saldoVencido - $saldoVencidoAyer;

        // Helper inline para % de cada bucket
        $pct = function ($v) use ($saldoPendiente) {
            return $saldoPendiente > 0 ? round(($v / $saldoPendiente) * 100, 2) : 0;
        };

        $buckets = [
            'por_vencer' => ['saldo' => (float)$row['saldo_por_vencer'], 'porcentaje' => $pct((float)$row['saldo_por_vencer'])],
            'd_1_30'     => ['saldo' => (float)$row['saldo_1_30'],     'porcentaje' => $pct((float)$row['saldo_1_30'])],
            'd_31_60'    => ['saldo' => (float)$row['saldo_31_60'],    'porcentaje' => $pct((float)$row['saldo_31_60'])],
            'd_61_90'    => ['saldo' => (float)$row['saldo_61_90'],    'porcentaje' => $pct((float)$row['saldo_61_90'])],
            'mas_90'     => ['saldo' => (float)$row['saldo_mas_90'],   'porcentaje' => $pct((float)$row['saldo_mas_90'])]
        ];

        // ------- 2) Saldo de cuentas del mes actual (solo del mes, NO futuros) -------
        $saldoMesActual = self::calcularSaldoCarteraBloque(
            $db,
            "YEAR(c.fecha) = :anio AND MONTH(c.fecha) = :mes",
            [':anio' => $anio, ':mes' => $mes]
        );

        // ------- 3) Saldo de cuentas de meses anteriores (solo del año global) -------
        $saldoMesesAnteriores = self::calcularSaldoCarteraBloque(
            $db,
            "c.fecha < :primer_dia AND YEAR(c.fecha) = :anio",
            [':primer_dia' => $primerDiaMes, ':anio' => $anio]
        );

        // ------- 4) Saldo por tipo de persona (solo del año global) -------
        $saldoEstudiantes = self::calcularSaldoCarteraBloque(
            $db,
            "YEAR(c.fecha) = :anio AND EXISTS (SELECT 1 FROM estudiantes e WHERE e.id_persona = c.id_persona)",
            [':anio' => $anio]
        );

        $saldoColaboradores = self::calcularSaldoCarteraBloque(
            $db,
            "YEAR(c.fecha) = :anio AND EXISTS (SELECT 1 FROM colaboradores col WHERE col.id_persona = c.id_persona)",
            [':anio' => $anio]
        );

        // ------- 5) Cuentas anuladas este mes -------
        $sqlAnuladasMes = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(valor), 0) AS total
            FROM cuentas_por_cobrar 
            WHERE anulado = 1 
              AND YEAR(fecha_anulacion) = :anio 
              AND MONTH(fecha_anulacion) = :mes AND id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlAnuladasMes);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->execute();
        $anuladasMes = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'fecha' => $fecha,
            'total_facturado' => (float)$row['total_facturado'],
            'total_recaudado' => (float)$row['total_recaudado'],
            'saldo_pendiente' => $saldoPendiente,
            'saldo_vencido' => $saldoVencido,
            'porcentaje_vencido' => $porcentajeVencido,
            'saldo_vencido_ayer' => $saldoVencidoAyer,
            'delta_vencido' => $deltaVencido,
            'saldo_mes_actual' => $saldoMesActual,
            'saldo_meses_anteriores' => $saldoMesesAnteriores,
            'saldo_estudiantes' => $saldoEstudiantes,
            'saldo_colaboradores' => $saldoColaboradores,
            'cuentas_anuladas_mes' => [
                'cantidad' => (int)$anuladasMes['cantidad'],
                'total' => (float)$anuladasMes['total']
            ],
            'buckets' => $buckets
        ];
    }

    /**
     * Helper: saldo pendiente de CPC filtradas por una condición arbitraria.
     */
    private static function calcularSaldoCarteraBloque($db, $whereExtra, $params)
    {
        $sql = "SELECT 
                COUNT(DISTINCT c.id) AS total_cuentas,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) AS saldo
            FROM cuentas_por_cobrar c
            LEFT JOIN (
                SELECT 
                    cp.id_cuenta_por_cobrar,
                    SUM(CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL 
                        THEN cp.valor_aplicado 
                        ELSE 0 
                    END) AS total_pagado
                FROM cuenta_pagada cp
                LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                GROUP BY cp.id_cuenta_por_cobrar
            ) cp_sum ON c.id = cp_sum.id_cuenta_por_cobrar
            WHERE (c.anulado = 0 OR c.anulado IS NULL) AND $whereExtra AND c.id_tenant = " . TenantContext::id();

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_cuentas' => (int)$r['total_cuentas'],
            'saldo' => (float)$r['saldo']
        ];
    }

    /**
     * Resumen de recaudo (movimientos):
     *   Datos grandes:
     *     - recaudado_mes: pagos con pr.fecha en el mes de la fecha global
     *     - recaudado_anio: pagos con pr.fecha en el año de la fecha global
     *   Sub-bloques:
     *     - recaudado_hoy: pr.fecha = fecha global
     *     - recaudado_mes_corriente: pagos del mes aplicados a CPC con fecha >= primer día del mes
     *     - recaudado_mes_anteriores: pagos del mes aplicados a CPC con fecha < primer día del mes
     *   Por tipo de persona:
     *     - mes_estudiantes / mes_colaboradores
     *
     * Filtros base: pr.anulado=0 AND tp.es_ingreso=1
     */
    private static function calcularRecaudo($db, $fecha)
    {
        $fechaObj = new DateTime($fecha);
        $anio = $fechaObj->format('Y');
        $mes = $fechaObj->format('m');
        $primerDiaMes = $fechaObj->format('Y-m-01');
        $fechaAyer = (clone $fechaObj)->modify('-1 day')->format('Y-m-d');

        // Recaudado hoy
        $sqlHoy = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) AND pr.fecha = :fecha AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlHoy);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->execute();
        $recaudadoHoy = $stmt->fetch(PDO::FETCH_ASSOC);

        // Registrado hoy (pagos digitados hoy, sin importar la fecha del comprobante)
        $sqlRegHoy = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) AND DATE(pr.fecha_registro) = :fecha AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlRegHoy);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->execute();
        $registradoHoy = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado en el mes
        $sqlMes = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) 
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlMes);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->execute();
        $recaudadoMes = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado en el mes hasta AYER (para delta)
        $sqlMesAyer = "SELECT 
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) 
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes
              AND pr.fecha <= :fecha_ayer AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlMesAyer);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->bindParam(':fecha_ayer', $fechaAyer);
        $stmt->execute();
        $recaudadoMesAyer = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalRecaudadoMesAyer = (float)$recaudadoMesAyer['total'];
        $deltaRecaudadoMes = (float)$recaudadoMes['total'] - $totalRecaudadoMesAyer;

        // Recaudado en el año
        $sqlAnio = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) 
              AND YEAR(pr.fecha) = :anio AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlAnio);
        $stmt->bindParam(':anio', $anio);
        $stmt->execute();
        $recaudadoAnio = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado del mes aplicado a CPC con fecha >= primer día del mes
        // (pagos del mes que cubren cuentas del mes corriente o futuras)
        $sqlMesCorriente = "SELECT 
                COALESCE(SUM(cp.valor_aplicado), 0) AS total
            FROM cuenta_pagada cp
            INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            INNER JOIN cuentas_por_cobrar c ON cp.id_cuenta_por_cobrar = c.id
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL)
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes
              AND c.fecha >= :primer_dia
              AND (c.anulado = 0 OR c.anulado IS NULL) AND cp.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlMesCorriente);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->bindParam(':primer_dia', $primerDiaMes);
        $stmt->execute();
        $mesCorriente = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado del mes aplicado a CPC con fecha < primer día del mes
        // (pagos del mes que cubren cartera vieja)
        $sqlMesAnteriores = "SELECT 
                COALESCE(SUM(cp.valor_aplicado), 0) AS total
            FROM cuenta_pagada cp
            INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            INNER JOIN cuentas_por_cobrar c ON cp.id_cuenta_por_cobrar = c.id
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL)
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes
              AND c.fecha < :primer_dia
              AND (c.anulado = 0 OR c.anulado IS NULL) AND cp.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlMesAnteriores);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->bindParam(':primer_dia', $primerDiaMes);
        $stmt->execute();
        $mesAnteriores = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado mes por tipo de persona: estudiantes
        $sqlMesEst = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) 
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes
              AND pr.id_estudiante IS NOT NULL AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlMesEst);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->execute();
        $mesEstudiantes = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado mes por tipo de persona: colaboradores
        $sqlMesCol = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) 
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes
              AND pr.id_colaborador IS NOT NULL AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlMesCol);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->execute();
        $mesColaboradores = $stmt->fetch(PDO::FETCH_ASSOC);

        // Pagos anulados este mes (por fecha_anulacion)
        $sqlAnulMes = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE pr.anulado = 1
              AND YEAR(pr.fecha_anulacion) = :anio 
              AND MONTH(pr.fecha_anulacion) = :mes AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlAnulMes);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->execute();
        $anuladosMes = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado del mes por tipo de pago
        $sqlPorTipo = "SELECT 
                tp.id AS id_tipo_pago,
                tp.nombre AS tipo_pago,
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) 
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes
              AND pr.id_tenant = " . TenantContext::id() . "
            GROUP BY tp.id, tp.nombre
            ORDER BY total DESC";
        $stmt = $db->prepare($sqlPorTipo);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->execute();
        $porTipoPago = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($porTipoPago as &$t) {
            $t['cantidad'] = (int)$t['cantidad'];
            $t['total'] = (float)$t['total'];
        }
        unset($t);

        return [
            'fecha' => $fecha,
            'recaudado_hoy' => [
                'cantidad' => (int)$recaudadoHoy['cantidad'],
                'total' => (float)$recaudadoHoy['total']
            ],
            'registrado_hoy' => [
                'cantidad' => (int)$registradoHoy['cantidad'],
                'total' => (float)$registradoHoy['total']
            ],
            'recaudado_mes' => [
                'cantidad' => (int)$recaudadoMes['cantidad'],
                'total' => (float)$recaudadoMes['total']
            ],
            'recaudado_mes_ayer' => $totalRecaudadoMesAyer,
            'delta_recaudado_mes' => $deltaRecaudadoMes,
            'recaudado_anio' => [
                'cantidad' => (int)$recaudadoAnio['cantidad'],
                'total' => (float)$recaudadoAnio['total']
            ],
            'recaudado_mes_corriente' => [
                'total' => (float)$mesCorriente['total']
            ],
            'recaudado_mes_anteriores' => [
                'total' => (float)$mesAnteriores['total']
            ],
            'mes_estudiantes' => [
                'cantidad' => (int)$mesEstudiantes['cantidad'],
                'total' => (float)$mesEstudiantes['total']
            ],
            'mes_colaboradores' => [
                'cantidad' => (int)$mesColaboradores['cantidad'],
                'total' => (float)$mesColaboradores['total']
            ],
            'anulados_mes' => [
                'cantidad' => (int)$anuladosMes['cantidad'],
                'total' => (float)$anuladosMes['total']
            ],
            'por_tipo_pago' => $porTipoPago
        ];
    }

    // =========================================================
    // MOVIMIENTOS FINANCIEROS
    // =========================================================
    // IDs hardcodeados de tipos_movimientos_financieros:
    //   ID 1 = Ingreso
    //   ID 2 = Gasto
    const TIPO_MOV_INGRESO = 'INGRESO';
    const TIPO_MOV_GASTO = 'GASTO';

    public static function getMovimientosResumen()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

            $resumen = self::calcularMovimientos($db, $fecha);

            Flight::json($resumen);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista de movimientos financieros para el detalle.
     * Params: fecha, rango (hoy|mes|anio), tipo (todos|ingresos|gastos),
     *         estado (todos|aprobados|pendientes|anulados)
     */
    public static function getMovimientosDetalle()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
            $rango = isset($_GET['rango']) ? $_GET['rango'] : 'mes';

            $fechaObj = new DateTime($fecha);
            $anio = $fechaObj->format('Y');
            $mes = $fechaObj->format('m');

            // Filtro temporal
            $whereFecha = '';
            $params = [];
            switch ($rango) {
                case 'hoy':
                    $whereFecha = "AND m.fecha = :fecha";
                    $params[':fecha'] = $fecha;
                    break;
                case 'anio':
                    $whereFecha = "AND YEAR(m.fecha) = :anio";
                    $params[':anio'] = $anio;
                    break;
                case 'mes':
                default:
                    $whereFecha = "AND YEAR(m.fecha) = :anio AND MONTH(m.fecha) = :mes";
                    $params[':anio'] = $anio;
                    $params[':mes'] = $mes;
                    break;
            }

            $sql = "SELECT 
                    m.id,
                    m.fecha,
                    m.valor,
                    m.detalle,
                    m.referencia_externa,
                    m.observaciones,
                    m.anulado,
                    m.fecha_aprobacion,
                    cf.id AS id_concepto,
                    cf.nombre AS concepto,
                    cmf.id AS id_categoria,
                    cmf.nombre AS categoria,
                    cmf.color AS color_categoria,
                    tmf.id AS id_tipo,
                    tmf.nombre AS tipo,
                    mpf.id AS id_medio_pago,
                    mpf.nombre AS medio_pago,
                    CASE 
                        WHEN m.anulado = 1 THEN 'Anulado'
                        WHEN m.fecha_aprobacion IS NULL THEN 'Pendiente'
                        ELSE 'Aprobado'
                    END AS estado
                FROM movimientos_financieros m
                INNER JOIN conceptos_financieros cf ON m.id_concepto_financiero = cf.id
                INNER JOIN categorias_movimientos_financieros cmf ON cf.id_categoria_movimiento_financiero = cmf.id
                INNER JOIN tipos_movimientos_financieros tmf ON cmf.id_tipo_movimiento = tmf.id
                LEFT JOIN medios_pago_financieros mpf ON m.id_medio_pago_financiero = mpf.id
                WHERE 1=1
                  AND m.id_tenant = " . TenantContext::id() . "
                  $whereFecha
                ORDER BY m.fecha DESC, m.id DESC";

            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($registros as &$r) {
                $r['valor'] = (float)$r['valor'];
                $r['anulado'] = (int)$r['anulado'];
            }
            unset($r);

            // Catálogos para filtros
            $sqlCat = "SELECT id, nombre, id_tipo_movimiento FROM categorias_movimientos_financieros WHERE id_tenant = " . TenantContext::id() . " ORDER BY nombre";
            $stmtC = $db->prepare($sqlCat);
            $stmtC->execute();
            $categorias = $stmtC->fetchAll(PDO::FETCH_ASSOC);

            $sqlMed = "SELECT id, nombre FROM medios_pago_financieros WHERE id_tenant = " . TenantContext::id() . " ORDER BY nombre";
            $stmtM = $db->prepare($sqlMed);
            $stmtM->execute();
            $medios = $stmtM->fetchAll(PDO::FETCH_ASSOC);

            Flight::json([
                'fecha' => $fecha,
                'rango' => $rango,
                'total' => count($registros),
                'registros' => $registros,
                'categorias' => $categorias,
                'medios_pago' => $medios
            ]);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resumen de movimientos financieros:
     *   - Ingresos / Gastos / Balance del mes y del año
     *   - Pendientes de aprobación
     *   - Top 1 categoría de gasto del mes
     *   - Top 1 concepto del mes (gasto)
     * Excluye anulados en todas las métricas.
     * Para ingresos/gastos del mes/año excluye también pendientes de aprobación.
     */
    private static function calcularMovimientos($db, $fecha)
    {
        $fechaObj = new DateTime($fecha);
        $anio = $fechaObj->format('Y');
        $mes = $fechaObj->format('m');
        $fechaAyer = (clone $fechaObj)->modify('-1 day')->format('Y-m-d');

        // Helper para totalizar (ingreso/gasto) por periodo
        $totalizar = function ($db, $idTipo, $periodo, $anio, $mes = null, $hastaFecha = null) {
            // 'mes' o 'anio' o 'mes_hasta' (acotado por hastaFecha)
            $extra = '';
            if ($periodo === 'mes_hasta') {
                $where = "YEAR(m.fecha) = :anio AND MONTH(m.fecha) = :mes AND m.fecha <= :hasta_fecha";
            } elseif ($periodo === 'mes') {
                $where = "YEAR(m.fecha) = :anio AND MONTH(m.fecha) = :mes";
            } else {
                $where = "YEAR(m.fecha) = :anio";
            }
            // Excluye anulados y pendientes
            $sql = "SELECT 
                    COUNT(*) AS cantidad,
                    COALESCE(SUM(m.valor), 0) AS total
                FROM movimientos_financieros m
                INNER JOIN conceptos_financieros cf ON m.id_concepto_financiero = cf.id
                INNER JOIN categorias_movimientos_financieros cmf ON cf.id_categoria_movimiento_financiero = cmf.id
                WHERE (m.anulado = 0 OR m.anulado IS NULL)
                  AND m.fecha_aprobacion IS NOT NULL
                  AND cmf.id_tipo_movimiento IN (SELECT tmf.id FROM tipos_movimientos_financieros tmf WHERE tmf.codigo = :id_tipo AND tmf.id_tenant = cmf.id_tenant)
                  AND $where AND m.id_tenant = " . TenantContext::id();
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_tipo', $idTipo, PDO::PARAM_STR);
            $stmt->bindValue(':anio', $anio);
            if ($periodo === 'mes' || $periodo === 'mes_hasta') $stmt->bindValue(':mes', $mes);
            if ($periodo === 'mes_hasta') $stmt->bindValue(':hasta_fecha', $hastaFecha);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        };

        $ingresosMes = $totalizar($db, self::TIPO_MOV_INGRESO, 'mes', $anio, $mes);
        $gastosMes   = $totalizar($db, self::TIPO_MOV_GASTO,   'mes', $anio, $mes);
        $ingresosAnio = $totalizar($db, self::TIPO_MOV_INGRESO, 'anio', $anio);
        $gastosAnio   = $totalizar($db, self::TIPO_MOV_GASTO,   'anio', $anio);

        // Hasta ayer (para deltas)
        $ingresosMesAyer = $totalizar($db, self::TIPO_MOV_INGRESO, 'mes_hasta', $anio, $mes, $fechaAyer);
        $gastosMesAyer   = $totalizar($db, self::TIPO_MOV_GASTO,   'mes_hasta', $anio, $mes, $fechaAyer);

        $balanceMes = (float)$ingresosMes['total'] - (float)$gastosMes['total'];
        $balanceAnio = (float)$ingresosAnio['total'] - (float)$gastosAnio['total'];

        $deltaIngresosMes = (float)$ingresosMes['total'] - (float)$ingresosMesAyer['total'];
        $deltaGastosMes   = (float)$gastosMes['total']   - (float)$gastosMesAyer['total'];

        // Pendientes de aprobación (no anulados, sin fecha_aprobacion)
        $sqlPend = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(m.valor), 0) AS total
            FROM movimientos_financieros m
            WHERE (m.anulado = 0 OR m.anulado IS NULL)
              AND m.fecha_aprobacion IS NULL AND m.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlPend);
        $stmt->execute();
        $pendientes = $stmt->fetch(PDO::FETCH_ASSOC);

        // Top 1 categoría de gasto del mes
        $sqlTopCat = "SELECT 
                cmf.id,
                cmf.nombre,
                cmf.color,
                COUNT(*) AS cantidad,
                COALESCE(SUM(m.valor), 0) AS total
            FROM movimientos_financieros m
            INNER JOIN conceptos_financieros cf ON m.id_concepto_financiero = cf.id
            INNER JOIN categorias_movimientos_financieros cmf ON cf.id_categoria_movimiento_financiero = cmf.id
            WHERE (m.anulado = 0 OR m.anulado IS NULL)
              AND m.fecha_aprobacion IS NOT NULL
              AND cmf.id_tipo_movimiento IN (SELECT tmf.id FROM tipos_movimientos_financieros tmf WHERE tmf.codigo = :id_tipo AND tmf.id_tenant = cmf.id_tenant)
              AND YEAR(m.fecha) = :anio AND MONTH(m.fecha) = :mes
              AND m.id_tenant = " . TenantContext::id() . "
            GROUP BY cmf.id, cmf.nombre, cmf.color
            ORDER BY total DESC
            LIMIT 1";
        $stmt = $db->prepare($sqlTopCat);
        $stmt->bindValue(':id_tipo', self::TIPO_MOV_GASTO, PDO::PARAM_STR);
        $stmt->bindValue(':anio', $anio);
        $stmt->bindValue(':mes', $mes);
        $stmt->execute();
        $topCategoria = $stmt->fetch(PDO::FETCH_ASSOC);

        // Top 1 concepto del mes (gasto)
        $sqlTopCon = "SELECT 
                cf.id,
                cf.nombre,
                cmf.nombre AS categoria,
                cmf.color AS color_categoria,
                COUNT(*) AS cantidad,
                COALESCE(SUM(m.valor), 0) AS total
            FROM movimientos_financieros m
            INNER JOIN conceptos_financieros cf ON m.id_concepto_financiero = cf.id
            INNER JOIN categorias_movimientos_financieros cmf ON cf.id_categoria_movimiento_financiero = cmf.id
            WHERE (m.anulado = 0 OR m.anulado IS NULL)
              AND m.fecha_aprobacion IS NOT NULL
              AND cmf.id_tipo_movimiento IN (SELECT tmf.id FROM tipos_movimientos_financieros tmf WHERE tmf.codigo = :id_tipo AND tmf.id_tenant = cmf.id_tenant)
              AND YEAR(m.fecha) = :anio AND MONTH(m.fecha) = :mes
              AND m.id_tenant = " . TenantContext::id() . "
            GROUP BY cf.id, cf.nombre, cmf.nombre, cmf.color
            ORDER BY total DESC
            LIMIT 1";
        $stmt = $db->prepare($sqlTopCon);
        $stmt->bindValue(':id_tipo', self::TIPO_MOV_GASTO, PDO::PARAM_STR);
        $stmt->bindValue(':anio', $anio);
        $stmt->bindValue(':mes', $mes);
        $stmt->execute();
        $topConcepto = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'fecha' => $fecha,
            'ingresos_mes' => [
                'cantidad' => (int)$ingresosMes['cantidad'],
                'total' => (float)$ingresosMes['total']
            ],
            'gastos_mes' => [
                'cantidad' => (int)$gastosMes['cantidad'],
                'total' => (float)$gastosMes['total']
            ],
            'delta_ingresos_mes' => $deltaIngresosMes,
            'delta_gastos_mes' => $deltaGastosMes,
            'balance_mes' => $balanceMes,
            'ingresos_anio' => [
                'cantidad' => (int)$ingresosAnio['cantidad'],
                'total' => (float)$ingresosAnio['total']
            ],
            'gastos_anio' => [
                'cantidad' => (int)$gastosAnio['cantidad'],
                'total' => (float)$gastosAnio['total']
            ],
            'balance_anio' => $balanceAnio,
            'pendientes_aprobacion' => [
                'cantidad' => (int)$pendientes['cantidad'],
                'total' => (float)$pendientes['total']
            ],
            'top_categoria_gasto' => $topCategoria ? [
                'id' => (int)$topCategoria['id'],
                'nombre' => $topCategoria['nombre'],
                'color' => $topCategoria['color'],
                'cantidad' => (int)$topCategoria['cantidad'],
                'total' => (float)$topCategoria['total']
            ] : null,
            'top_concepto_gasto' => $topConcepto ? [
                'id' => (int)$topConcepto['id'],
                'nombre' => $topConcepto['nombre'],
                'categoria' => $topConcepto['categoria'],
                'color_categoria' => $topConcepto['color_categoria'],
                'cantidad' => (int)$topConcepto['cantidad'],
                'total' => (float)$topConcepto['total']
            ] : null
        ];
    }
}