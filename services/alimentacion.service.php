<?php
class Alimentacion
{
    public static function getByFecha()
    {
        try {
            $request = Flight::request();
            $data = $request->data->getData();

            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            $fecha = isset($data['fecha']) ? $data['fecha'] : date('Y-m-d');

            $fecha_obj = new DateTime($fecha);
            $anio = $fecha_obj->format('Y');
            $mes = $fecha_obj->format('m');

            $db = Flight::db();

            // Query 1: Estudiantes PRESENTES (asistieron, hayan salido o no)
            // Si asistieron, califican para "Presente". El campo ya_salio
            // permite distinguir quién sigue en el jardín de quién ya se fue.
            $sqlPresentes = "SELECT 
                        cpc.id, 
                        cpc.id_persona,
                        CONCAT(
                            COALESCE(p.primer_nombre, ''), ' ', 
                            COALESCE(p.segundo_nombre, ''), ' ', 
                            COALESCE(p.primer_apellido, ''), ' ', 
                            COALESCE(p.segundo_apellido, '')
                        ) AS nombre_estudiante,
                        g.id AS id_grupo,
                        g.nombre AS nombre_grupo,
                        ha.id AS id_horario,
                        ha.nombre AS nombre_horario,
                        ps.id AS id_producto,
                        ps.nombre AS nombre_producto,
                        COALESCE(ps.detalles, '') AS detalle_producto,
                        CONCAT(
                            COALESCE(pu.primer_nombre, ''), ' ', 
                            COALESCE(pu.segundo_nombre, ''), ' ', 
                            COALESCE(pu.primer_apellido, ''), ' ', 
                            COALESCE(pu.segundo_apellido, ''),
                            ' - ',
                            DATE_FORMAT(cpc.fecha_generado, '%d/%m/%Y %H:%i')
                        ) AS nombre_registro,
                        ae.fecha_ingreso,
                        ae.fecha_salida,
                        TIME(ae.fecha_ingreso) AS hora_ingreso,
                        CASE WHEN ae.fecha_salida IS NOT NULL THEN TIME(ae.fecha_salida) ELSE NULL END AS hora_salida,
                        CASE WHEN ae.fecha_salida IS NOT NULL THEN 1 ELSE 0 END AS ya_salio,
                        1 AS presente
                    FROM cuentas_por_cobrar cpc
                    INNER JOIN personas p ON cpc.id_persona = p.id
                    INNER JOIN estudiantes e ON p.id = e.id_persona
                    INNER JOIN estudiantes_x_grupos exg ON e.id = exg.id_estudiante AND exg.activo = 1
                    INNER JOIN grupos g ON exg.id_grupo = g.id
                    INNER JOIN productos_servicios ps ON cpc.id_producto_servicio = ps.id
                    INNER JOIN periodicidad_cobro pc ON ps.id_periodicidad_cobro = pc.id
                    INNER JOIN horarios_alimentacion ha ON cpc.id_horario_alimentacion = ha.id
                    INNER JOIN usuarios u ON cpc.id_usuario = u.id
                    INNER JOIN personas pu ON u.id_persona = pu.id
                    INNER JOIN asistencia_estudiantes ae ON e.id = ae.id_estudiante 
                        AND DATE(ae.fecha_ingreso) = :fecha_ingreso
                    WHERE ps.id_clasificacion_productos_servicios = 3 
                    AND cpc.anulado = 0
                    AND cpc.id_tenant = :id_tenant
                    AND (
                        (pc.id = 3 AND cpc.fecha = :fecha_cpc) 
                        OR (pc.id = 2 AND YEAR(cpc.fecha) = :anio AND MONTH(cpc.fecha) = :mes)
                    )
                    ORDER BY g.orden, ha.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido";

            $stmtPresentes = $db->prepare($sqlPresentes);
            $stmtPresentes->bindValue(':fecha_ingreso', $fecha);
            $stmtPresentes->bindValue(':fecha_cpc', $fecha);
            $stmtPresentes->bindValue(':anio', $anio);
            $stmtPresentes->bindValue(':mes', $mes);
            $stmtPresentes->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtPresentes->execute();
            $presentes = $stmtPresentes->fetchAll(PDO::FETCH_ASSOC);

            // Query 2: Estudiantes AUSENTES (solo mensuales que no asistieron en absoluto ese día)
            $sqlAusentes = "SELECT 
                        cpc.id, 
                        cpc.id_persona,
                        CONCAT(
                            COALESCE(p.primer_nombre, ''), ' ', 
                            COALESCE(p.segundo_nombre, ''), ' ', 
                            COALESCE(p.primer_apellido, ''), ' ', 
                            COALESCE(p.segundo_apellido, '')
                        ) AS nombre_estudiante,
                        g.id AS id_grupo,
                        g.nombre AS nombre_grupo,
                        ha.id AS id_horario,
                        ha.nombre AS nombre_horario,
                        ps.id AS id_producto,
                        ps.nombre AS nombre_producto,
                        COALESCE(ps.detalles, '') AS detalle_producto,
                        CONCAT(
                            COALESCE(pu.primer_nombre, ''), ' ', 
                            COALESCE(pu.segundo_nombre, ''), ' ', 
                            COALESCE(pu.primer_apellido, ''), ' ', 
                            COALESCE(pu.segundo_apellido, ''),
                            ' - ',
                            DATE_FORMAT(cpc.fecha_generado, '%d/%m/%Y %H:%i')
                        ) AS nombre_registro,
                        NULL AS fecha_ingreso,
                        NULL AS fecha_salida,
                        NULL AS hora_ingreso,
                        NULL AS hora_salida,
                        0 AS ya_salio,
                        0 AS presente
                    FROM cuentas_por_cobrar cpc
                    INNER JOIN personas p ON cpc.id_persona = p.id
                    INNER JOIN estudiantes e ON p.id = e.id_persona
                    INNER JOIN estudiantes_x_grupos exg ON e.id = exg.id_estudiante AND exg.activo = 1
                    INNER JOIN grupos g ON exg.id_grupo = g.id
                    INNER JOIN productos_servicios ps ON cpc.id_producto_servicio = ps.id
                    INNER JOIN periodicidad_cobro pc ON ps.id_periodicidad_cobro = pc.id
                    INNER JOIN horarios_alimentacion ha ON cpc.id_horario_alimentacion = ha.id
                    INNER JOIN usuarios u ON cpc.id_usuario = u.id
                    INNER JOIN personas pu ON u.id_persona = pu.id
                    WHERE ps.id_clasificacion_productos_servicios = 3 
                    AND cpc.anulado = 0
                    AND cpc.id_tenant = :id_tenant
                    AND pc.id = 2
                    AND YEAR(cpc.fecha) = :anio 
                    AND MONTH(cpc.fecha) = :mes
                    AND NOT EXISTS (
                        SELECT 1 FROM asistencia_estudiantes ae2
                        WHERE ae2.id_estudiante = e.id
                        AND DATE(ae2.fecha_ingreso) = :fecha_ingreso
                    )
                    ORDER BY g.orden, ha.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido";

            $stmtAusentes = $db->prepare($sqlAusentes);
            $stmtAusentes->bindValue(':fecha_ingreso', $fecha);
            $stmtAusentes->bindValue(':anio', $anio);
            $stmtAusentes->bindValue(':mes', $mes);
            $stmtAusentes->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtAusentes->execute();
            $ausentes = $stmtAusentes->fetchAll(PDO::FETCH_ASSOC);

            // Unir ambos resultados
            $response = array_merge($presentes, $ausentes);

            // Limpiar datos
            foreach ($response as &$item) {
                $item['nombre_estudiante'] = trim(preg_replace('/\s+/', ' ', $item['nombre_estudiante']));
                $item['nombre_registro'] = trim(preg_replace('/\s+/', ' ', $item['nombre_registro']));
                $item['id_grupo'] = (string) $item['id_grupo'];
                $item['id_horario'] = (string) $item['id_horario'];
                $item['id_producto'] = (string) $item['id_producto'];
                $item['presente'] = (int) $item['presente'];
                $item['ya_salio'] = (int) $item['ya_salio'];

                if (!empty($item['fecha_ingreso'])) {
                    $fecha_ingreso_obj = new DateTime($item['fecha_ingreso']);
                    $item['fecha_ingreso_formateada'] = $fecha_ingreso_obj->format('Y-m-d H:i:s');
                }
            }

            Flight::json($response);

        } catch (Exception $e) {
            error_log("Error en Alimentacion::getByFecha: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}