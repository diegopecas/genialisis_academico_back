<?php
class Auditoria
{
    /**
     * Obtiene el resumen completo de todos los grupos en una sola llamada
     */
    public static function getResumenCompleto()
    {
        try {
            $db = Flight::db();

            // Obtener parametros
            $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-1 month'));
            $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
            $id_sprint = $_GET['id_sprint'] ?? null;

            // Obtener grupos calificables
            $gruposQuery = $db->prepare("SELECT id, nombre, icono, color FROM grupos WHERE calificable = 1 AND id_tenant = :id_tenant ORDER BY orden");
            $gruposQuery->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $gruposQuery->execute();
            $grupos = $gruposQuery->fetchAll(PDO::FETCH_ASSOC);

            $resultado = [];

            foreach ($grupos as $grupo) {
                $resumenGrupo = [
                    'id_grupo' => $grupo['id'],
                    'nombre_grupo' => $grupo['nombre'],
                    'icono' => $grupo['icono'],
                    'color' => $grupo['color'],
                    'medidas' => self::obtenerResumenMedidas($db, $grupo['id'], $fecha_inicio, $fecha_fin),
                    'asistencia' => self::obtenerResumenAsistencia($db, $grupo['id'], $fecha_inicio, $fecha_fin),
                    'clases' => self::obtenerResumenClases($db, $grupo['id'], $id_sprint)
                ];

                $resultado[] = $resumenGrupo;
            }

            Flight::json($resultado);
        } catch (Exception $e) {
            error_log("Error en getResumenCompleto: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener resumen completo'], 500);
        }
    }

    private static function obtenerResumenMedidas($db, $id_grupo, $fecha_inicio, $fecha_fin)
    {
        $sql = "SELECT 
                -- Total de semanas en el periodo
                CEIL(DATEDIFF(:fecha_fin1, :fecha_inicio1) / 7) as total_semanas,
                -- Semanas con al menos un registro de peso
                COUNT(DISTINCT CASE 
                    WHEN mxe.id_medida IN (SELECT m.id FROM medidas m WHERE m.codigo = 'PESO' AND m.id_tenant = mxe.id_tenant) 
                    THEN CONCAT(YEAR(mxe.fecha), '-', WEEK(mxe.fecha))
                END) as semanas_con_peso,
                -- Semanas con al menos un registro de talla
                COUNT(DISTINCT CASE 
                    WHEN mxe.id_medida IN (SELECT m.id FROM medidas m WHERE m.codigo = 'TALLA' AND m.id_tenant = mxe.id_tenant) 
                    THEN CONCAT(YEAR(mxe.fecha), '-', WEEK(mxe.fecha))
                END) as semanas_con_talla,
                -- Total de registros (para verificar que hay datos)
                COUNT(mxe.id) as total_registros,
                -- IDs de registros para poder consultarlos después
                GROUP_CONCAT(DISTINCT mxe.id) as ids_registros
            FROM estudiantes_x_grupos eg
            INNER JOIN estudiantes e ON eg.id_estudiante = e.id AND e.activo = 1
            LEFT JOIN medidas_x_estudiantes mxe ON eg.id_estudiante = mxe.id_estudiante 
                AND mxe.fecha BETWEEN :fecha_inicio2 AND :fecha_fin2
            WHERE eg.id_grupo = :id_grupo AND eg.activo = 1 AND eg.id_tenant = :id_tenant";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_grupo', $id_grupo);
        $stmt->bindParam(':fecha_inicio1', $fecha_inicio);
        $stmt->bindParam(':fecha_fin1', $fecha_fin);
        $stmt->bindParam(':fecha_inicio2', $fecha_inicio);
        $stmt->bindParam(':fecha_fin2', $fecha_fin);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($resultado && $resultado['total_semanas'] > 0) {
            // Porcentaje de cumplimiento basado en semanas con registros
            $resultado['porcentaje_cumplimiento_peso'] = round(($resultado['semanas_con_peso'] / $resultado['total_semanas']) * 100, 2);
            $resultado['porcentaje_cumplimiento_talla'] = round(($resultado['semanas_con_talla'] / $resultado['total_semanas']) * 100, 2);
            $resultado['porcentaje_cumplimiento_general'] = round((($resultado['semanas_con_peso'] + $resultado['semanas_con_talla']) / (2 * $resultado['total_semanas'])) * 100, 2);

            // Agregar información de período
            $resultado['fecha_inicio'] = $fecha_inicio;
            $resultado['fecha_fin'] = $fecha_fin;
        }

        return $resultado;
    }

    private static function obtenerResumenAsistencia($db, $id_grupo, $fecha_inicio, $fecha_fin)
    {
        // Calcular dias habiles directamente con PHP
        $fecha_actual = new DateTime($fecha_inicio);
        $fecha_final = new DateTime($fecha_fin);
        $dias_habiles = 0;

        while ($fecha_actual <= $fecha_final) {
            $dia_semana = $fecha_actual->format('N'); // 1=Lunes, 7=Domingo
            if ($dia_semana >= 1 && $dia_semana <= 5) { // Lunes a Viernes
                $dias_habiles++;
            }
            $fecha_actual->add(new DateInterval('P1D'));
        }

        // Ahora obtener las estadisticas de asistencia
        $sql = "SELECT 
                -- Total de estudiantes activos en el grupo
                COUNT(DISTINCT eg.id_estudiante) as total_estudiantes,
                -- Dias con al menos una asistencia
                COUNT(DISTINCT DATE(ae.fecha_ingreso)) as dias_con_asistencia,
                -- Total de asistencias
                COUNT(DISTINCT ae.id) as total_asistencias,
                -- Promedio de tiempo de estancia (en minutos)
                AVG(CASE 
                    WHEN ae.fecha_salida IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, ae.fecha_ingreso, ae.fecha_salida)
                END) as promedio_minutos_estancia,
                -- Registros sospechosos (menos de 5 minutos)
                COUNT(DISTINCT CASE 
                    WHEN ae.fecha_salida IS NOT NULL 
                    AND TIMESTAMPDIFF(MINUTE, ae.fecha_ingreso, ae.fecha_salida) < 5
                    THEN ae.id 
                END) as registros_sospechosos
            FROM estudiantes_x_grupos eg
            LEFT JOIN asistencia_estudiantes ae ON eg.id_estudiante = ae.id_estudiante
                AND DATE(ae.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
            WHERE eg.id_grupo = :id_grupo
            AND eg.activo = 1
            AND eg.id_tenant = :id_tenant
            GROUP BY eg.id_grupo";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_grupo', $id_grupo);
        $stmt->bindParam(':fecha_inicio', $fecha_inicio);
        $stmt->bindParam(':fecha_fin', $fecha_fin);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($resultado) {
            // Agregar dias habiles al resultado
            $resultado['dias_habiles'] = $dias_habiles;

            // Promedio de asistencia diaria
            $asistencias_esperadas = $dias_habiles * $resultado['total_estudiantes'];
            $resultado['promedio_asistencia'] = $asistencias_esperadas > 0
                ? round(($resultado['total_asistencias'] / $asistencias_esperadas) * 100, 2)
                : 0;

            // Convertir minutos a horas
            $resultado['promedio_horas_estancia'] = $resultado['promedio_minutos_estancia']
                ? round($resultado['promedio_minutos_estancia'] / 60, 2)
                : 0;

            // Porcentaje de registros sospechosos
            $resultado['porcentaje_registros_sospechosos'] = $resultado['total_asistencias'] > 0
                ? round(($resultado['registros_sospechosos'] / $resultado['total_asistencias']) * 100, 2)
                : 0;
        }

        return $resultado;
    }

    private static function obtenerResumenClases($db, $id_grupo, $id_sprint)
    {
        if (!$id_sprint) {
            $sprintQuery = $db->prepare("SELECT id FROM sprints WHERE actual = 1 AND id_tenant = :id_tenant LIMIT 1");
            $sprintQuery->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sprintQuery->execute();
            $sprintActual = $sprintQuery->fetch();
            $id_sprint = $sprintActual ? $sprintActual['id'] : null;
        }

        if (!$id_sprint) return null;

        $sql = "SELECT 
            COUNT(DISTINCT txs.id) as total_clases,
            -- Clases ejecutadas (con fecha de inicio y fin)
            COUNT(DISTINCT CASE 
                WHEN txs.fecha_ejecucion_inicia IS NOT NULL 
                AND txs.fecha_ejecucion IS NOT NULL
                THEN txs.id 
            END) as clases_ejecutadas,
            -- Clases muy cortas (menos de 10 minutos)
            COUNT(DISTINCT CASE 
                WHEN txs.fecha_ejecucion_inicia IS NOT NULL 
                AND txs.fecha_ejecucion IS NOT NULL
                AND TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) < 10
                THEN txs.id 
            END) as clases_muy_cortas,
            -- Clases cortas (10-30 minutos)
            COUNT(DISTINCT CASE 
                WHEN txs.fecha_ejecucion_inicia IS NOT NULL 
                AND txs.fecha_ejecucion IS NOT NULL
                AND TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) >= 10
                AND TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) < 30
                THEN txs.id 
            END) as clases_cortas,
            -- Clases normales (30-90 minutos)
            COUNT(DISTINCT CASE 
                WHEN txs.fecha_ejecucion_inicia IS NOT NULL 
                AND txs.fecha_ejecucion IS NOT NULL
                AND TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) >= 30
                AND TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) <= 90
                THEN txs.id 
            END) as clases_normales,
            -- Clases muy largas (mas de 90 minutos)
            COUNT(DISTINCT CASE 
                WHEN txs.fecha_ejecucion_inicia IS NOT NULL 
                AND txs.fecha_ejecucion IS NOT NULL
                AND TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) > 90
                THEN txs.id 
            END) as clases_muy_largas,
            -- Promedio de duracion
            AVG(CASE 
                WHEN txs.fecha_ejecucion_inicia IS NOT NULL 
                AND txs.fecha_ejecucion IS NOT NULL
                THEN TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion)
            END) as promedio_duracion_minutos
        FROM logros l
        INNER JOIN grados_x_grupo gxg ON l.id_grado = gxg.id_grado
        INNER JOIN indicadores_logros il ON l.id = il.id_logro
        INNER JOIN actividades_academicas_x_indicadores_logros aaxil ON il.id = aaxil.id_indicador_logro
        INNER JOIN actividades_academicas aa ON aaxil.id_actividad_academica = aa.id
        INNER JOIN tareas_x_sprints txs ON aa.id = txs.id_actividad_academica
        WHERE gxg.id_grupo = :id_grupo
        AND txs.id_sprint = :id_sprint
        AND txs.id_tenant = :id_tenant";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_grupo', $id_grupo);
        $stmt->bindParam(':id_sprint', $id_sprint);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($resultado && $resultado['clases_ejecutadas'] > 0) {
            // Calcular porcentajes sobre clases ejecutadas
            $resultado['porcentaje_muy_cortas'] = round(($resultado['clases_muy_cortas'] / $resultado['clases_ejecutadas']) * 100, 2);
            $resultado['porcentaje_cortas'] = round(($resultado['clases_cortas'] / $resultado['clases_ejecutadas']) * 100, 2);
            $resultado['porcentaje_normales'] = round(($resultado['clases_normales'] / $resultado['clases_ejecutadas']) * 100, 2);
            $resultado['porcentaje_muy_largas'] = round(($resultado['clases_muy_largas'] / $resultado['clases_ejecutadas']) * 100, 2);

            // Porcentaje de ejecucion
            $resultado['porcentaje_ejecucion'] = round(($resultado['clases_ejecutadas'] / $resultado['total_clases']) * 100, 2);

            // Convertir duracion promedio a formato legible
            $resultado['promedio_duracion_minutos'] = round($resultado['promedio_duracion_minutos'] ?? 0, 2);
        }

        return $resultado;
    }

    /**
     * Obtiene el detalle de medidas para un grupo en un período
     */
    public static function getDetalleMedidas()
    {
        try {
            $db = Flight::db();

            $id_grupo = $_GET['id_grupo'] ?? null;
            $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-1 month'));
            $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

            if (!$id_grupo) {
                Flight::json(['error' => 'ID de grupo requerido'], 400);
                return;
            }

            $sql = "SELECT 
            mxe.id,
            p.primer_nombre,
            p.segundo_nombre,
            p.primer_apellido,
            p.segundo_apellido,
            CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) as estudiante,
            m.nombre as medida,
            mxe.valor,
            mxe.fecha,
            DATE_FORMAT(mxe.fecha, '%d/%m/%Y') as fecha_formateada,
            CONCAT('Semana ', WEEK(mxe.fecha)) as semana,
            CONCAT(pu.primer_nombre, ' ', pu.primer_apellido) as registrado_por
        FROM medidas_x_estudiantes mxe
        INNER JOIN estudiantes e ON mxe.id_estudiante = e.id
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN estudiantes_x_grupos eg ON e.id = eg.id_estudiante
        INNER JOIN medidas m ON mxe.id_medida = m.id
        LEFT JOIN usuarios u ON mxe.id_usuario = u.id
        LEFT JOIN personas pu ON u.id_persona = pu.id
        WHERE eg.id_grupo = :id_grupo
        AND eg.activo = 1
        AND e.activo = 1
        AND mxe.fecha BETWEEN :fecha_inicio AND :fecha_fin
        AND mxe.id_tenant = :id_tenant
        ORDER BY mxe.fecha DESC, p.primer_apellido, p.primer_nombre";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id_grupo', $id_grupo);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json($registros);
        } catch (Exception $e) {
            error_log("Error en getDetalleMedidas: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener detalle de medidas'], 500);
        }
    }

    /**
     * Obtiene el detalle de asistencia para un grupo en un período
     */
    public static function getDetalleAsistencia()
    {
        try {
            $db = Flight::db();

            $id_grupo = $_GET['id_grupo'] ?? null;
            $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-1 month'));
            $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

            if (!$id_grupo) {
                Flight::json(['error' => 'ID de grupo requerido'], 400);
                return;
            }

            $sql = "SELECT 
            ae.id,
            p.primer_nombre,
            p.segundo_nombre,
            p.primer_apellido,
            p.segundo_apellido,
            CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) as estudiante,
            DATE_FORMAT(ae.fecha_ingreso, '%d/%m/%Y') as fecha,
            DATE_FORMAT(ae.fecha_ingreso, '%h:%i %p') as hora_entrada,
            DATE_FORMAT(ae.fecha_salida, '%h:%i %p') as hora_salida,
            CASE 
                WHEN ae.fecha_salida IS NULL THEN 'Sin salida'
                ELSE CONCAT(ROUND(TIMESTAMPDIFF(MINUTE, ae.fecha_ingreso, ae.fecha_salida) / 60, 1), ' horas')
            END as tiempo_estancia,
            CASE 
                WHEN ae.fecha_salida IS NOT NULL 
                AND TIMESTAMPDIFF(MINUTE, ae.fecha_ingreso, ae.fecha_salida) < 5
                THEN 'Sospechoso'
                ELSE 'Normal'
            END as estado,
            CONCAT(pu1.primer_nombre, ' ', pu1.primer_apellido) as registrado_entrada,
            CONCAT(pu2.primer_nombre, ' ', pu2.primer_apellido) as registrado_salida
        FROM asistencia_estudiantes ae
        INNER JOIN estudiantes e ON ae.id_estudiante = e.id
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN estudiantes_x_grupos eg ON e.id = eg.id_estudiante
        LEFT JOIN usuarios u1 ON ae.id_usuario_ingreso = u1.id
        LEFT JOIN personas pu1 ON u1.id_persona = pu1.id
        LEFT JOIN usuarios u2 ON ae.id_usuario_salida = u2.id
        LEFT JOIN personas pu2 ON u2.id_persona = pu2.id
        WHERE eg.id_grupo = :id_grupo
        AND eg.activo = 1
        AND DATE(ae.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
        AND ae.id_tenant = :id_tenant
        ORDER BY ae.fecha_ingreso DESC";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id_grupo', $id_grupo);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json($registros);
        } catch (Exception $e) {
            error_log("Error en getDetalleAsistencia: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener detalle de asistencia'], 500);
        }
    }

    /**
     * Obtiene el detalle de clases para un grupo en un sprint
     */
    public static function getDetalleClases()
    {
        try {
            $db = Flight::db();

            $id_grupo = $_GET['id_grupo'] ?? null;
            $id_sprint = $_GET['id_sprint'] ?? null;

            if (!$id_grupo) {
                Flight::json(['error' => 'ID de grupo requerido'], 400);
                return;
            }

            if (!$id_sprint) {
                $sprintQuery = $db->prepare("SELECT id FROM sprints WHERE actual = 1 AND id_tenant = :id_tenant LIMIT 1");
                $sprintQuery->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sprintQuery->execute();
                $sprintActual = $sprintQuery->fetch();
                $id_sprint = $sprintActual ? $sprintActual['id'] : null;
            }

            $sql = "SELECT 
            txs.id,
            aa.titulo as actividad,
            il.nombre as indicador,
            DATE_FORMAT(txs.fecha_ejecucion_inicia, '%d/%m/%Y') as fecha,
            DATE_FORMAT(txs.fecha_ejecucion_inicia, '%h:%i %p') as hora_inicio,
            DATE_FORMAT(txs.fecha_ejecucion, '%h:%i %p') as hora_fin,
            CASE 
                WHEN txs.fecha_ejecucion_inicia IS NULL OR txs.fecha_ejecucion IS NULL 
                THEN 'No ejecutada'
                ELSE CONCAT(ROUND(TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion), 0), ' min')
            END as duracion,
            CASE 
                WHEN txs.fecha_ejecucion_inicia IS NULL OR txs.fecha_ejecucion IS NULL 
                THEN 'No ejecutada'
                WHEN TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) < 10
                THEN 'Muy corta'
                WHEN TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) < 30
                THEN 'Corta'
                WHEN TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) <= 90
                THEN 'Normal'
                ELSE 'Muy larga'
            END as categoria,
            CONCAT(pd1.primer_nombre, ' ', pd1.primer_apellido) as docente,
            CONCAT(pd2.primer_nombre, ' ', pd2.primer_apellido) as docente_finaliza
        FROM tareas_x_sprints txs
        INNER JOIN actividades_academicas aa ON txs.id_actividad_academica = aa.id
        INNER JOIN actividades_academicas_x_indicadores_logros aaxil ON aa.id = aaxil.id_actividad_academica
        INNER JOIN indicadores_logros il ON aaxil.id_indicador_logro = il.id
        INNER JOIN logros l ON il.id_logro = l.id
        INNER JOIN grados_x_grupo gxg ON l.id_grado = gxg.id_grado
        LEFT JOIN usuarios d1 ON txs.id_docente = d1.id
        LEFT JOIN personas pd1 ON d1.id_persona = pd1.id
        LEFT JOIN usuarios d2 ON txs.id_docente_inicia = d2.id
        LEFT JOIN personas pd2 ON d2.id_persona = pd2.id
        WHERE gxg.id_grupo = :id_grupo
        AND txs.id_sprint = :id_sprint
        AND txs.id_tenant = :id_tenant
        ORDER BY txs.fecha_ejecucion_inicia DESC";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id_grupo', $id_grupo);
            $stmt->bindParam(':id_sprint', $id_sprint);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json($registros);
        } catch (Exception $e) {
            error_log("Error en getDetalleClases: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener detalle de clases'], 500);
        }
    }
}