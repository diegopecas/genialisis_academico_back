<?php
class AsistenciaEstudiantes
{
    /**
     * Configura la zona horaria de la sesión a Colombia (UTC-5)
     */
    private static function setTimeZone() {
        $db = Flight::db();
        $db->exec("SET time_zone = '-05:00'");
    }

    public static function getAll()
    {
        self::setTimeZone();
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_estudiante, fecha_ingreso, fecha_salida, observacion_ingreso, observacion_salida from asistencia_estudiantes where id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        self::setTimeZone();
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_estudiante, fecha_ingreso, fecha_salida, observacion_ingreso, observacion_salida from asistencia_estudiantes where id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdEstudiante($id_estudiante)
    {
        self::setTimeZone();
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_estudiante, fecha_ingreso, fecha_salida, observacion_ingreso, observacion_salida from asistencia_estudiantes where id_estudiante = :id_estudiante AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getIngresosHoy()
    {
        self::setTimeZone();
        $db = Flight::db();
        $sentence = $db->prepare("select ae.id, ae.id_estudiante, ae.fecha_ingreso, ae.fecha_salida, ae.observacion_ingreso, ae.observacion_salida, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido 
        from asistencia_estudiantes ae
        inner join estudiantes e on ae.id_estudiante = e.id
        inner join personas p on e.id_persona = p.id
        where DATE(ae.fecha_ingreso) = CURDATE() and ae.id_tenant = :id_tenant
        order by p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getSalidasHoy()
    {
        self::setTimeZone();
        $db = Flight::db();
        $sentence = $db->prepare("select ae.id, ae.id_estudiante, ae.fecha_ingreso, ae.fecha_salida, ae.observacion_ingreso, ae.observacion_salida, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido 
        from asistencia_estudiantes ae
        inner join estudiantes e on ae.id_estudiante = e.id
        inner join personas p on e.id_persona = p.id
        where DATE(ae.fecha_salida) = CURDATE() and ae.id_tenant = :id_tenant
        order by p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getNoIngresosHoy()
    {
        self::setTimeZone();
        $db = Flight::db();
        $sentence = $db->prepare("select e.id, e.id_persona, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, g.nombre nombre_grupo, g.icono, g.color  
        from estudiantes e
        inner join personas p on e.id_persona = p.id
        inner join estudiantes_x_grupos exg on e.id = exg.id_estudiante
        inner join grupos g on exg.id_grupo = g.id 
        where e.id not in (select ae.id_estudiante
        from asistencia_estudiantes ae 
        where DATE(ae.fecha_ingreso) = CURDATE()
        and ae.fecha_salida is null
        and ae.id_tenant = :id_tenant_sub)
        and exg.activo = 1
        and e.activo = 1
        and e.id_tenant = :id_tenant
        order by p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant_sub', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getNoSalidasHoy()
    {
        self::setTimeZone();
        $db = Flight::db();
        $sentence = $db->prepare("select ae.id, e.id_persona, ae.id_estudiante, ae.fecha_ingreso, ae.fecha_salida, ae.observacion_ingreso, ae.observacion_salida, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, g.nombre nombre_grupo, g.icono, g.color 
        from asistencia_estudiantes ae
        inner join estudiantes e on ae.id_estudiante = e.id
        inner join personas p on e.id_persona = p.id
        inner join estudiantes_x_grupos exg on e.id = exg.id_estudiante
        inner join grupos g on exg.id_grupo = g.id 
        where DATE(ae.fecha_ingreso) = CURDATE()
        and ae.fecha_salida is null
        and exg.activo = 1
        and ae.id_tenant = :id_tenant
        order by p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        self::setTimeZone();
        $db = Flight::db();
        $id_estudiante = Flight::request()->data['id_estudiante'];
        $observacion = Flight::request()->data['observacion'];
        $id_usuario_ingreso = Flight::request()->data['id_usuario'];
        $idNew = Uuid::generar();
        $sentence = $db->prepare("insert into asistencia_estudiantes(id, id_tenant, id_estudiante, fecha_ingreso, observacion_ingreso, id_usuario_ingreso) values (:id, :id_tenant, :id_estudiante, NOW(), :observacion, :id_usuario_ingreso)");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':observacion', $observacion);
        $sentence->bindParam(':id_usuario_ingreso', $id_usuario_ingreso);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        self::setTimeZone();
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $observacion = Flight::request()->data['observacion'];
        $id_usuario_salida = Flight::request()->data['id_usuario'];
        $sentence = $db->prepare("update asistencia_estudiantes set fecha_salida = NOW(), observacion_salida = :observacion, id_usuario_salida = :id_usuario_salida where id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':observacion', $observacion);
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':id_usuario_salida', $id_usuario_salida);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        self::setTimeZone();
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from asistencia_estudiantes where id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }
    
    public static function verificarAsistenciaEstudiante()
    {
        try {
            self::setTimeZone();
            $db = Flight::db();
            $request = Flight::request();

            $data = $request->data->getData();
            $id_estudiante = $data['id_estudiante'];
            $fecha = isset($data['fecha']) ? $data['fecha'] : date('Y-m-d');

            $sql = "SELECT ae.id, ae.fecha_ingreso, ae.fecha_salida, 
                ae.observacion_ingreso, ae.observacion_salida, 
                p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido 
                FROM asistencia_estudiantes ae
                INNER JOIN estudiantes e ON ae.id_estudiante = e.id
                INNER JOIN personas p ON e.id_persona = p.id
                WHERE ae.id_estudiante = :id_estudiante 
                AND DATE(ae.fecha_ingreso) = :fecha
                AND ae.id_tenant = :id_tenant";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_estudiante', $id_estudiante);
            $stmt->bindParam(':fecha', $fecha);
            $stmt->execute();

            $asistencia = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json([
                'id_estudiante' => $id_estudiante,
                'fecha' => $fecha,
                'tiene_asistencia' => count($asistencia) > 0,
                'registros' => $asistencia
            ]);
        } catch (Exception $e) {
            error_log('Error en verificarAsistenciaEstudiante(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al verificar asistencia del estudiante'), 500);
        }
    }
    
    public static function getAsistenciaMensual()
    {
        try {
            self::setTimeZone();
            $db = Flight::db();
            $request = Flight::request();

            $data = $request->data->getData();
            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            if (empty($data) || !isset($data['id_estudiante'])) {
                Flight::json(['error' => 'Datos inválidos o falta id_estudiante'], 400);
                return;
            }

            $id_estudiante = $data['id_estudiante'];
            $anio = isset($data['anio']) ? $data['anio'] : date('Y');
            $mes = isset($data['mes']) ? $data['mes'] : date('m');
            $mes = str_pad($mes, 2, '0', STR_PAD_LEFT);

            $fecha_inicio = "$anio-$mes-01";
            $fecha_fin = date('Y-m-t', strtotime($fecha_inicio));

            $sql = "SELECT ae.id, ae.id_estudiante, ae.fecha_ingreso, ae.fecha_salida, 
        ae.observacion_ingreso, ae.observacion_salida, 
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
        c.id_tipo_dia,
        ds.hora_entrada,
        ds.hora_salida,
        CASE c.id_tipo_dia
            WHEN 1 THEN 'Laboral'
            WHEN 2 THEN 'Festivo'
            WHEN 3 THEN 'Especial'
            WHEN 4 THEN 'Fin de semana'
            ELSE 'No definido'
        END as tipo_dia_nombre,
        CASE 
            WHEN TIME(ae.fecha_ingreso) > ds.hora_entrada THEN 'Sí'
            ELSE 'No'
        END as entrada_tarde,
        CASE 
            WHEN ae.fecha_salida IS NOT NULL AND TIME(ae.fecha_salida) > ds.hora_salida THEN 'Sí'
            ELSE 'No'
        END as salida_tarde
        FROM asistencia_estudiantes ae
        INNER JOIN estudiantes e ON ae.id_estudiante = e.id
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN calendarios c ON DATE(ae.fecha_ingreso) = c.fecha
        INNER JOIN dias_semana ds ON c.id_dia_semana = ds.id
        WHERE ae.id_estudiante = :id_estudiante 
        AND DATE(ae.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
        AND ae.id_tenant = :id_tenant
        AND c.id_tipo_dia IN (1, 3)
        ORDER BY ae.fecha_ingreso";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_estudiante', $id_estudiante);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->execute();

            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $estadisticas = self::calcularEstadisticasAsistencia($id_estudiante, $fecha_inicio, $fecha_fin);

            Flight::json([
                'id_estudiante' => $id_estudiante,
                'anio' => $anio,
                'mes' => $mes,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'registros' => $registros,
                'estadisticas' => $estadisticas
            ]);
        } catch (Exception $e) {
            error_log('Error en getAsistenciaMensual(): ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener asistencia mensual'], 500);
        }
    }

    private static function calcularEstadisticasAsistencia($id_estudiante, $fecha_inicio, $fecha_fin)
    {
        $db = Flight::db();

        $dias_habiles = self::contarDiasHabilesReales($fecha_inicio, $fecha_fin);

        $sql_asistencia = "
        SELECT 
            COUNT(DISTINCT DATE(ae.fecha_ingreso)) AS dias_asistidos,
            SUM(CASE 
                WHEN TIME(ae.fecha_ingreso) > ds.hora_entrada THEN 1 
                ELSE 0 
            END) AS entradas_tarde,
            SUM(CASE 
                WHEN ae.fecha_salida IS NOT NULL AND TIME(ae.fecha_salida) > ds.hora_salida THEN 1
                ELSE 0 
            END) AS salidas_tarde
        FROM asistencia_estudiantes ae
        INNER JOIN calendarios c ON DATE(ae.fecha_ingreso) = c.fecha
        INNER JOIN dias_semana ds ON c.id_dia_semana = ds.id
        WHERE ae.id_estudiante = :id_estudiante
        AND DATE(ae.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
        AND ae.id_tenant = :id_tenant
        AND c.id_tipo_dia IN (1, 3)";

        $stmt_asistencia = $db->prepare($sql_asistencia);
        $stmt_asistencia->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt_asistencia->bindParam(':id_estudiante', $id_estudiante);
        $stmt_asistencia->bindParam(':fecha_inicio', $fecha_inicio);
        $stmt_asistencia->bindParam(':fecha_fin', $fecha_fin);
        $stmt_asistencia->execute();
        $datos_asistencia = $stmt_asistencia->fetch(PDO::FETCH_ASSOC);

        $dias_asistidos = $datos_asistencia['dias_asistidos'] ?? 0;
        $entradas_tarde = $datos_asistencia['entradas_tarde'] ?? 0;
        $salidas_tarde = $datos_asistencia['salidas_tarde'] ?? 0;
        $dias_ausentes = $dias_habiles - $dias_asistidos;
        $porcentaje_asistencia = $dias_habiles > 0 ? ($dias_asistidos / $dias_habiles) * 100 : 0;

        return [
            'dias_habiles' => $dias_habiles,
            'dias_asistidos' => $dias_asistidos,
            'dias_ausentes' => $dias_ausentes,
            'entradas_tarde' => $entradas_tarde,
            'salidas_tarde' => $salidas_tarde,
            'porcentaje_asistencia' => round($porcentaje_asistencia, 2)
        ];
    }

    public static function getResumenAsistenciaPorGrupo($id_grupo)
    {
        try {
            self::setTimeZone();
            $db = Flight::db();

            $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-1 month'));
            $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

            $sql = "SELECT 
            g.id as id_grupo,
            g.nombre as nombre_grupo,
            COUNT(DISTINCT ae.id) as total_registros,
            COUNT(DISTINCT CASE 
                WHEN ae.fecha_salida IS NOT NULL 
                THEN ae.id 
            END) as registros_completos,
            COUNT(DISTINCT CASE 
                WHEN ae.fecha_salida IS NOT NULL 
                AND TIMESTAMPDIFF(MINUTE, ae.fecha_ingreso, ae.fecha_salida) < 5
                THEN ae.id 
            END) as registros_sospechosos,
            AVG(CASE 
                WHEN ae.fecha_salida IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, ae.fecha_ingreso, ae.fecha_salida)
            END) as promedio_minutos_estancia,
            COUNT(DISTINCT ae.id_usuario_ingreso) as cantidad_usuarios_registradores
        FROM grupos g
        INNER JOIN estudiantes_x_grupos eg ON g.id = eg.id_grupo AND eg.activo = 1
        INNER JOIN asistencia_estudiantes ae ON eg.id_estudiante = ae.id_estudiante
        WHERE g.id = :id_grupo
        AND DATE(ae.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
        AND g.id_tenant = :id_tenant
        GROUP BY g.id, g.nombre";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id_grupo', $id_grupo);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                $resultado['porcentaje_registros_completos'] = $resultado['total_registros'] > 0
                    ? round(($resultado['registros_completos'] / $resultado['total_registros']) * 100, 2)
                    : 0;

                $resultado['porcentaje_registros_sospechosos'] = $resultado['registros_completos'] > 0
                    ? round(($resultado['registros_sospechosos'] / $resultado['registros_completos']) * 100, 2)
                    : 0;

                $resultado['promedio_horas_estancia'] = $resultado['promedio_minutos_estancia']
                    ? round($resultado['promedio_minutos_estancia'] / 60, 2)
                    : 0;
            }

            Flight::json($resultado);
        } catch (Exception $e) {
            error_log("Error en getResumenAsistenciaPorGrupo: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener resumen de asistencia'], 500);
        }
    }

    public static function getReporteAsistenciaPorFecha()
    {
        try {
            self::setTimeZone();
            $db = Flight::db();
            $request = Flight::request();

            $data = $request->data->getData();
            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            $fecha_inicio = isset($data['fecha_inicio']) ? $data['fecha_inicio'] : date('Y-m-d');
            $fecha_fin = isset($data['fecha_fin']) ? $data['fecha_fin'] : date('Y-m-d');

            $sql = "SELECT 
            e.id as id_estudiante,
            p.primer_nombre,
            p.segundo_nombre,
            p.primer_apellido,
            p.segundo_apellido,
            CONCAT(p.primer_nombre, ' ', COALESCE(p.segundo_nombre, ''), ' ', p.primer_apellido, ' ', COALESCE(p.segundo_apellido, '')) as nombre_completo,
            g.nombre as nombre_grupo,
            g.color as color_grupo,
            g.icono as icono_grupo,
            
            ae.id as id_asistencia,
            ae.fecha_ingreso,
            ae.fecha_salida,
            ae.observacion_ingreso,
            ae.observacion_salida,
            DATE(ae.fecha_ingreso) as fecha,
            ds.nombre as nombre_dia,
            
            ds.hora_entrada,
            ds.hora_salida,
            
            CASE 
                WHEN ae.id IS NOT NULL THEN TIME_FORMAT(TIME(ae.fecha_ingreso), '%H:%i')
                ELSE NULL
            END as hora_ingreso,
            
            CASE 
                WHEN ae.fecha_salida IS NOT NULL THEN TIME_FORMAT(TIME(ae.fecha_salida), '%H:%i')
                ELSE NULL
            END as hora_salida_real,
            
            CASE 
                WHEN ae.id IS NULL THEN 0
                WHEN ae.fecha_salida IS NOT NULL THEN 
                    ROUND(TIMESTAMPDIFF(MINUTE, ae.fecha_ingreso, ae.fecha_salida) / 60, 2)
                ELSE 
                    CASE 
                        WHEN TIME(ae.fecha_ingreso) <= ds.hora_salida THEN
                            ROUND(TIMESTAMPDIFF(MINUTE, ae.fecha_ingreso, CONCAT(DATE(ae.fecha_ingreso), ' ', ds.hora_salida)) / 60, 2)
                        ELSE 0
                    END
            END as horas_estadia,
            
            CASE 
                WHEN ae.fecha_salida IS NOT NULL AND TIME(ae.fecha_salida) > ds.hora_salida THEN 
                    ROUND(TIMESTAMPDIFF(MINUTE, CONCAT(DATE(ae.fecha_salida), ' ', ds.hora_salida), ae.fecha_salida) / 60, 2)
                ELSE 0
            END as horas_extras,
            
            CASE 
                WHEN TIME(ae.fecha_ingreso) > ds.hora_entrada THEN 'Sí'
                ELSE 'No'
            END as entrada_tarde,
            
            CASE 
                WHEN ae.fecha_salida IS NOT NULL AND TIME(ae.fecha_salida) > ds.hora_salida THEN 'Sí'
                ELSE 'No'
            END as salida_tarde,
            
            CASE 
                WHEN ae.id IS NULL THEN 'Ausente'
                WHEN ae.fecha_salida IS NULL THEN 'En el jardín'
                ELSE 'Salió'
            END as estado_actual,
            
            CASE 
                WHEN ae.id_usuario_ingreso IS NOT NULL THEN 
                    CONCAT(p_ui.primer_nombre, ' ', COALESCE(p_ui.segundo_nombre, ''), ' ', p_ui.primer_apellido, ' ', COALESCE(p_ui.segundo_apellido, ''))
                ELSE NULL
            END as usuario_ingreso,
            
            CASE 
                WHEN ae.id_usuario_salida IS NOT NULL THEN 
                    CONCAT(p_us.primer_nombre, ' ', COALESCE(p_us.segundo_nombre, ''), ' ', p_us.primer_apellido, ' ', COALESCE(p_us.segundo_apellido, ''))
                ELSE NULL
            END as usuario_salida,
            
            COALESCE((SELECT SUM(cpc.valor)
                FROM cobros_automaticos_historial cah
                INNER JOIN cuentas_por_cobrar cpc ON cah.id_cuenta_por_cobrar = cpc.id
                WHERE cah.id_asistencia_estudiante = ae.id
                AND (cpc.anulado = 0 OR cpc.anulado IS NULL)
            ), 0) as valor_cobros
            
        FROM estudiantes e
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN estudiantes_x_grupos exg ON e.id = exg.id_estudiante AND exg.activo = 1
        INNER JOIN grupos g ON exg.id_grupo = g.id
        LEFT JOIN asistencia_estudiantes ae ON e.id = ae.id_estudiante 
            AND DATE(ae.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
        LEFT JOIN calendarios c ON DATE(ae.fecha_ingreso) = c.fecha
        LEFT JOIN dias_semana ds ON c.id_dia_semana = ds.id
        LEFT JOIN usuarios u_ing ON ae.id_usuario_ingreso = u_ing.id
        LEFT JOIN personas p_ui ON u_ing.id_persona = p_ui.id
        LEFT JOIN usuarios u_sal ON ae.id_usuario_salida = u_sal.id
        LEFT JOIN personas p_us ON u_sal.id_persona = p_us.id
        WHERE e.activo = 1 AND e.id_tenant = :id_tenant";

            $sql .= " ORDER BY 
            DATE(ae.fecha_ingreso) DESC,
            CASE 
                WHEN ae.id IS NULL THEN 1
                ELSE 0
            END,
            g.nombre, 
            p.primer_nombre, 
            p.segundo_nombre, 
            p.primer_apellido";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->execute();

            $asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($asistencias as &$asistencia) {
                $asistencia['hora_ingreso'] = $asistencia['hora_ingreso'] ?: 'Sin registrar';
                $asistencia['hora_salida_real'] = $asistencia['hora_salida_real'] ?: 'Sin registrar';
                $asistencia['observacion_ingreso'] = $asistencia['observacion_ingreso'] ?: '';
                $asistencia['observacion_salida'] = $asistencia['observacion_salida'] ?: '';
                $asistencia['horas_extras'] = $asistencia['horas_extras'] ?: 0;
                $asistencia['usuario_ingreso'] = $asistencia['usuario_ingreso'] ?: '';
                $asistencia['usuario_salida'] = $asistencia['usuario_salida'] ?: '';
                $asistencia['valor_cobros'] = $asistencia['valor_cobros'] ?: 0;
            }

            $estadisticas = self::calcularEstadisticasRangoFechas($fecha_inicio, $fecha_fin);

            Flight::json([
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'total_registros' => count($asistencias),
                'asistencias' => $asistencias,
                'estadisticas' => $estadisticas
            ]);
        } catch (Exception $e) {
            error_log('Error en getReporteAsistenciaPorFecha(): ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener reporte de asistencia'], 500);
        }
    }

    private static function calcularEstadisticasRangoFechas($fecha_inicio, $fecha_fin)
    {
        $db = Flight::db();

        $sql = "SELECT 
                COUNT(DISTINCT DATE(ae.fecha_ingreso)) as dias_con_asistencia,
                COUNT(*) as total_registros,
                COUNT(CASE WHEN ae.id IS NOT NULL THEN 1 END) as total_asistencias,
                COUNT(CASE WHEN ae.fecha_salida IS NOT NULL THEN 1 END) as con_salida,
                COUNT(CASE WHEN ae.fecha_salida IS NULL AND ae.id IS NOT NULL THEN 1 END) as sin_salida,
                COUNT(CASE WHEN ae.id IS NOT NULL AND TIME(ae.fecha_ingreso) > ds.hora_entrada THEN 1 END) as entradas_tarde,
                COUNT(CASE WHEN ae.fecha_salida IS NOT NULL AND TIME(ae.fecha_salida) > ds.hora_salida THEN 1 END) as salidas_tarde,
                
                SUM(CASE 
                    WHEN ae.fecha_salida IS NOT NULL AND TIME(ae.fecha_salida) > ds.hora_salida THEN 
                        TIMESTAMPDIFF(MINUTE, CONCAT(DATE(ae.fecha_salida), ' ', ds.hora_salida), ae.fecha_salida) / 60
                    ELSE 0
                END) as total_horas_extras,
                
                ROUND(AVG(CASE 
                    WHEN ae.fecha_salida IS NOT NULL AND TIME(ae.fecha_salida) > ds.hora_salida THEN 
                        TIMESTAMPDIFF(MINUTE, CONCAT(DATE(ae.fecha_salida), ' ', ds.hora_salida), ae.fecha_salida) / 60
                    ELSE NULL
                END), 2) as promedio_horas_extras_por_salida_tarde,
                
                ROUND(AVG(
                    CASE 
                        WHEN ae.fecha_salida IS NOT NULL THEN 
                            TIMESTAMPDIFF(MINUTE, ae.fecha_ingreso, ae.fecha_salida) / 60
                        ELSE 
                            CASE 
                                WHEN ae.id IS NOT NULL AND TIME(ae.fecha_ingreso) <= ds.hora_salida THEN
                                    TIMESTAMPDIFF(MINUTE, ae.fecha_ingreso, CONCAT(DATE(ae.fecha_ingreso), ' ', ds.hora_salida)) / 60
                                ELSE NULL
                            END
                    END
                ), 2) as promedio_horas_estadia,
                
                TIME_FORMAT(AVG(CASE WHEN ae.id IS NOT NULL THEN TIME(ae.fecha_ingreso) END), '%H:%i') as promedio_hora_ingreso,
                TIME_FORMAT(AVG(CASE WHEN ae.fecha_salida IS NOT NULL THEN TIME(ae.fecha_salida) END), '%H:%i') as promedio_hora_salida
                
                FROM asistencia_estudiantes ae
                INNER JOIN calendarios c ON DATE(ae.fecha_ingreso) = c.fecha
                INNER JOIN dias_semana ds ON c.id_dia_semana = ds.id
                WHERE DATE(ae.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
                AND ae.id_tenant = :id_tenant
                AND c.id_tipo_dia IN (1, 3)";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindParam(':fecha_inicio', $fecha_inicio);
        $stmt->bindParam(':fecha_fin', $fecha_fin);
        $stmt->execute();

        $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

        $estadisticas['dias_habiles_periodo'] = self::contarDiasHabilesReales($fecha_inicio, $fecha_fin);

        return $estadisticas;
    }

    public static function getReporteIndicadoresAsistencia()
    {
        try {
            self::setTimeZone();
            $db = Flight::db();

            $fecha_referencia = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

            $fecha_inicio_semana = date('Y-m-d', strtotime('monday this week', strtotime($fecha_referencia)));
            $fecha_inicio_mes = date('Y-m-01', strtotime($fecha_referencia));
            $fecha_30_dias_atras = date('Y-m-d', strtotime('-30 days', strtotime($fecha_referencia)));

            $sql = "SELECT 
            e.id as id_estudiante,
            p.primer_nombre,
            p.segundo_nombre,
            p.primer_apellido,
            p.segundo_apellido,
            CONCAT(p.primer_nombre, ' ', COALESCE(p.segundo_nombre, ''), ' ', p.primer_apellido, ' ', COALESCE(p.segundo_apellido, '')) as nombre_completo,
            g.nombre as nombre_grupo,
            g.color as color_grupo,
            e.fecha_ingreso as fecha_ingreso_estudiante,
            
            CASE 
                WHEN EXISTS (SELECT 1 FROM asistencia_estudiantes ae_ref WHERE ae_ref.id_estudiante = e.id AND DATE(ae_ref.fecha_ingreso) = :fecha_referencia) 
                THEN 'Presente'
                ELSE 'Ausente'
            END as estado_hoy,
            
            COALESCE((SELECT COUNT(DISTINCT DATE(ae_sem.fecha_ingreso))
                FROM asistencia_estudiantes ae_sem 
                INNER JOIN calendarios c_sem ON DATE(ae_sem.fecha_ingreso) = c_sem.fecha
                WHERE ae_sem.id_estudiante = e.id 
                AND DATE(ae_sem.fecha_ingreso) BETWEEN :fecha_inicio_semana AND :fecha_referencia2
                AND c_sem.id_tipo_dia IN (1, 3)
            ), 0) as asistencias_semana_actual,
            
            COALESCE((SELECT COUNT(DISTINCT DATE(ae_mes.fecha_ingreso))
                FROM asistencia_estudiantes ae_mes 
                INNER JOIN calendarios c_mes ON DATE(ae_mes.fecha_ingreso) = c_mes.fecha
                WHERE ae_mes.id_estudiante = e.id 
                AND DATE(ae_mes.fecha_ingreso) BETWEEN :fecha_inicio_mes AND :fecha_referencia3
                AND c_mes.id_tipo_dia IN (1, 3)
            ), 0) as asistencias_mes_actual,
            
            COALESCE((SELECT COUNT(DISTINCT DATE(ae_total.fecha_ingreso))
                FROM asistencia_estudiantes ae_total 
                INNER JOIN calendarios c_total ON DATE(ae_total.fecha_ingreso) = c_total.fecha
                WHERE ae_total.id_estudiante = e.id
                AND DATE(ae_total.fecha_ingreso) <= :fecha_referencia4
                AND c_total.id_tipo_dia IN (1, 3)
            ), 0) as total_asistencias,
            
            (SELECT MAX(DATE(ae_ultima.fecha_ingreso))
                FROM asistencia_estudiantes ae_ultima 
                WHERE ae_ultima.id_estudiante = e.id
                AND DATE(ae_ultima.fecha_ingreso) <= :fecha_referencia5
            ) as ultima_asistencia,
            
            COALESCE((SELECT ROUND(AVG(
                CASE 
                    WHEN ae_prom.fecha_salida IS NOT NULL THEN 
                        TIMESTAMPDIFF(MINUTE, ae_prom.fecha_ingreso, ae_prom.fecha_salida) / 60
                    ELSE 
                        CASE 
                            WHEN TIME(ae_prom.fecha_ingreso) <= ds_prom.hora_salida THEN
                                TIMESTAMPDIFF(MINUTE, ae_prom.fecha_ingreso, CONCAT(DATE(ae_prom.fecha_ingreso), ' ', ds_prom.hora_salida)) / 60
                            ELSE 0
                        END
                END
            ), 2)
                FROM asistencia_estudiantes ae_prom 
                INNER JOIN calendarios c_prom ON DATE(ae_prom.fecha_ingreso) = c_prom.fecha
                INNER JOIN dias_semana ds_prom ON c_prom.id_dia_semana = ds_prom.id
                WHERE ae_prom.id_estudiante = e.id 
                AND DATE(ae_prom.fecha_ingreso) >= :fecha_30_dias_atras
                AND DATE(ae_prom.fecha_ingreso) <= :fecha_referencia6
                AND c_prom.id_tipo_dia IN (1, 3)
            ), 0) as promedio_horas_permanencia
            
        FROM estudiantes e
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN estudiantes_x_grupos exg ON e.id = exg.id_estudiante AND exg.activo = 1
        INNER JOIN grupos g ON exg.id_grupo = g.id
        WHERE e.activo = 1 AND e.id_tenant = :id_tenant
        ORDER BY g.nombre, p.primer_nombre, p.segundo_nombre, p.primer_apellido";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':fecha_referencia', $fecha_referencia);
            $stmt->bindParam(':fecha_referencia2', $fecha_referencia);
            $stmt->bindParam(':fecha_referencia3', $fecha_referencia);
            $stmt->bindParam(':fecha_referencia4', $fecha_referencia);
            $stmt->bindParam(':fecha_referencia5', $fecha_referencia);
            $stmt->bindParam(':fecha_referencia6', $fecha_referencia);
            $stmt->bindParam(':fecha_inicio_semana', $fecha_inicio_semana);
            $stmt->bindParam(':fecha_inicio_mes', $fecha_inicio_mes);
            $stmt->bindParam(':fecha_30_dias_atras', $fecha_30_dias_atras);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            $indicadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $dias_habiles_semana = self::contarDiasHabilesReales($fecha_inicio_semana, $fecha_referencia);
            $dias_habiles_mes = self::contarDiasHabilesReales($fecha_inicio_mes, $fecha_referencia);

            foreach ($indicadores as &$indicador) {
                $indicador['dias_consecutivos_ausencia'] = self::calcularDiasConsecutivosAusencia(
                    $indicador['id_estudiante'],
                    $fecha_referencia,
                    $indicador['estado_hoy'] === 'Presente'
                );

                $indicador['porcentaje_asistencia_semana'] = $dias_habiles_semana > 0
                    ? round(($indicador['asistencias_semana_actual'] / $dias_habiles_semana) * 100, 1)
                    : 0;

                $indicador['porcentaje_asistencia_mes'] = $dias_habiles_mes > 0
                    ? round(($indicador['asistencias_mes_actual'] / $dias_habiles_mes) * 100, 1)
                    : 0;

                $indicador['clasificacion_riesgo'] = self::clasificarRiesgoAsistencia($indicador);
            }

            Flight::json([
                'fecha_consulta' => $fecha_referencia,
                'fecha_inicio_semana' => $fecha_inicio_semana,
                'fecha_inicio_mes' => $fecha_inicio_mes,
                'dias_habiles_semana' => $dias_habiles_semana,
                'dias_habiles_mes' => $dias_habiles_mes,
                'total_estudiantes' => count($indicadores),
                'indicadores' => $indicadores
            ]);
        } catch (Exception $e) {
            error_log('Error en getReporteIndicadoresAsistencia(): ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener indicadores de asistencia'], 500);
        }
    }

    private static function calcularDiasConsecutivosAusencia($id_estudiante, $fecha_referencia, $asistio_hoy)
    {
        if ($asistio_hoy) {
            return 0;
        }

        $db = Flight::db();

        $sql = "SELECT MAX(DATE(fecha_ingreso)) as ultima_fecha
            FROM asistencia_estudiantes 
            WHERE id_estudiante = :id_estudiante 
            AND DATE(fecha_ingreso) < :fecha_referencia
            AND id_tenant = :id_tenant";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindParam(':id_estudiante', $id_estudiante);
        $stmt->bindParam(':fecha_referencia', $fecha_referencia);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $ultima_fecha = $resultado['ultima_fecha'];

        if (!$ultima_fecha) {
            $sql_fecha_ingreso = "SELECT fecha_ingreso FROM estudiantes WHERE id = :id_estudiante AND id_tenant = :id_tenant";
            $stmt = $db->prepare($sql_fecha_ingreso);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_estudiante', $id_estudiante);
            $stmt->execute();
            $fecha_ingreso_estudiante = $stmt->fetch(PDO::FETCH_ASSOC)['fecha_ingreso'];
            $fecha_inicio = date('Y-m-d', strtotime($fecha_ingreso_estudiante . ' +1 day'));
        } else {
            $fecha_inicio = date('Y-m-d', strtotime($ultima_fecha . ' +1 day'));
        }

        return self::contarDiasHabilesReales($fecha_inicio, $fecha_referencia);
    }
    
    private static function calcularEstadisticasDiarias($fecha)
    {
        $db = Flight::db();

        $sql_tipo_dia = "SELECT c.id_tipo_dia, ds.hora_entrada, ds.hora_salida 
                     FROM calendarios c
                     INNER JOIN dias_semana ds ON c.id_dia_semana = ds.id
                     WHERE c.fecha = :fecha";
        $stmt_tipo = $db->prepare($sql_tipo_dia);
        $stmt_tipo->bindParam(':fecha', $fecha);
        $stmt_tipo->execute();
        $info_dia = $stmt_tipo->fetch(PDO::FETCH_ASSOC);

        $es_dia_habil = $info_dia && ($info_dia['id_tipo_dia'] == 1 || $info_dia['id_tipo_dia'] == 3);

        $hora_entrada = $info_dia['hora_entrada'] ?? '08:00:00';
        $hora_salida = $info_dia['hora_salida'] ?? '18:00:00';

        $sql = "SELECT 
        COUNT(*) as total_asistencias,
        COUNT(CASE WHEN ae.fecha_salida IS NOT NULL THEN 1 END) as con_salida,
        COUNT(CASE WHEN ae.fecha_salida IS NULL THEN 1 END) as sin_salida,
        COUNT(CASE WHEN TIME(ae.fecha_ingreso) > :hora_entrada THEN 1 END) as entradas_tarde,
        COUNT(CASE WHEN ae.fecha_salida IS NOT NULL AND TIME(ae.fecha_salida) > :hora_salida THEN 1 END) as salidas_tarde,
        ROUND(AVG(
            CASE 
                WHEN ae.fecha_salida IS NOT NULL THEN 
                    TIMESTAMPDIFF(MINUTE, ae.fecha_ingreso, ae.fecha_salida) / 60
                ELSE 
                    CASE 
                        WHEN TIME(ae.fecha_ingreso) <= :hora_salida_calculo THEN
                            TIMESTAMPDIFF(MINUTE, ae.fecha_ingreso, CONCAT(DATE(ae.fecha_ingreso), ' ', :hora_salida_calculo2)) / 60
                        ELSE 0
                    END
            END
        ), 2) as promedio_horas_estadia,
        TIME_FORMAT(AVG(TIME(ae.fecha_ingreso)), '%H:%i') as promedio_hora_ingreso,
        TIME_FORMAT(AVG(TIME(ae.fecha_salida)), '%H:%i') as promedio_hora_salida
    FROM asistencia_estudiantes ae
    WHERE DATE(ae.fecha_ingreso) = :fecha AND ae.id_tenant = :id_tenant";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora_entrada', $hora_entrada);
        $stmt->bindParam(':hora_salida', $hora_salida);
        $stmt->bindParam(':hora_salida_calculo', $hora_salida);
        $stmt->bindParam(':hora_salida_calculo2', $hora_salida);
        $stmt->execute();

        $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

        $estadisticas['es_dia_habil'] = $es_dia_habil;
        $estadisticas['hora_entrada_esperada'] = $hora_entrada;
        $estadisticas['hora_salida_esperada'] = $hora_salida;
        $estadisticas['tipo_dia_nombre'] = $es_dia_habil ?
            ($info_dia['id_tipo_dia'] == 1 ? 'Día Laboral' : 'Día Especial') : ($info_dia ?
                ($info_dia['id_tipo_dia'] == 2 ? 'Día Festivo' : 'Fin de Semana')
                : 'Día no definido');

        return $estadisticas;
    }

    private static function clasificarRiesgoAsistencia($indicador)
    {
        $dias_ausencia = $indicador['dias_consecutivos_ausencia'];
        $porcentaje_mes = $indicador['porcentaje_asistencia_mes'];

        if ($dias_ausencia >= 5 || $porcentaje_mes < 50) {
            return 'Alto';
        } elseif ($dias_ausencia >= 3 || $porcentaje_mes < 75) {
            return 'Medio';
        } else {
            return 'Bajo';
        }
    }
    
    private static function contarDiasHabilesReales($fecha_inicio, $fecha_fin)
    {
        $db = Flight::db();

        $sql = "SELECT COUNT(*) as total_dias_habiles
            FROM calendarios 
            WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin
            AND id_tipo_dia IN (1, 3)";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':fecha_inicio', $fecha_inicio);
        $stmt->bindParam(':fecha_fin', $fecha_fin);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado['total_dias_habiles'] ?? 0;
    }

    private static function contarAsistenciasEnDiasHabiles($id_estudiante, $fecha_inicio, $fecha_fin)
    {
        $db = Flight::db();

        $sql = "SELECT COUNT(DISTINCT DATE(ae.fecha_ingreso)) as total_asistencias
            FROM asistencia_estudiantes ae
            INNER JOIN calendarios c ON DATE(ae.fecha_ingreso) = c.fecha
            WHERE ae.id_estudiante = :id_estudiante
            AND DATE(ae.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
            AND c.id_tipo_dia = 1
            AND ae.id_tenant = :id_tenant";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindParam(':id_estudiante', $id_estudiante);
        $stmt->bindParam(':fecha_inicio', $fecha_inicio);
        $stmt->bindParam(':fecha_fin', $fecha_fin);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado['total_asistencias'] ?? 0;
    }
    
    public static function getEstudiantesPorFecha($fecha)
    {
        try {
            self::setTimeZone();
            $db = Flight::db();
            
            $sql = "SELECT DISTINCT
                ae.id_estudiante,
                e.id_persona,
                p.primer_nombre,
                p.segundo_nombre,
                p.primer_apellido,
                p.segundo_apellido,
                g.nombre as nombre_grupo,
                g.icono,
                g.color
            FROM asistencia_estudiantes ae
            INNER JOIN estudiantes e ON ae.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            INNER JOIN estudiantes_x_grupos exg ON e.id = exg.id_estudiante AND exg.activo = 1
            INNER JOIN grupos g ON exg.id_grupo = g.id
            WHERE DATE(ae.fecha_ingreso) = :fecha
            AND e.activo = 1
            AND ae.id_tenant = :id_tenant
            ORDER BY g.nombre, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':fecha', $fecha);
            $stmt->execute();

            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json($estudiantes);

        } catch (Exception $e) {
            error_log('Error en getEstudiantesPorFecha: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener estudiantes por fecha'], 500);
        }
    }

    /**
     * Obtiene indicadores de asistencia + acudientes con teléfono en una sola llamada.
     * Usado por el módulo de Seguimiento de Asistencia.
     */
    public static function getSeguimientoAsistencia()
    {
        try {
            self::setTimeZone();
            $db = Flight::db();

            $fecha_referencia = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

            $fecha_inicio_semana = date('Y-m-d', strtotime('monday this week', strtotime($fecha_referencia)));
            $fecha_inicio_mes = date('Y-m-01', strtotime($fecha_referencia));
            $fecha_30_dias_atras = date('Y-m-d', strtotime('-30 days', strtotime($fecha_referencia)));

            $sql = "SELECT 
            e.id as id_estudiante,
            e.id_persona,
            p.primer_nombre,
            p.segundo_nombre,
            p.primer_apellido,
            p.segundo_apellido,
            p.id_genero,
            CONCAT(p.primer_nombre, ' ', COALESCE(p.segundo_nombre, ''), ' ', p.primer_apellido, ' ', COALESCE(p.segundo_apellido, '')) as nombre_completo,
            g.nombre as nombre_grupo,
            g.color as color_grupo,
            e.fecha_ingreso as fecha_ingreso_estudiante,
            
            CASE 
                WHEN EXISTS (SELECT 1 FROM asistencia_estudiantes ae_ref WHERE ae_ref.id_estudiante = e.id AND DATE(ae_ref.fecha_ingreso) = :fecha_referencia) 
                THEN 'Presente'
                ELSE 'Ausente'
            END as estado_hoy,
            
            COALESCE((SELECT COUNT(DISTINCT DATE(ae_sem.fecha_ingreso))
                FROM asistencia_estudiantes ae_sem 
                INNER JOIN calendarios c_sem ON DATE(ae_sem.fecha_ingreso) = c_sem.fecha
                WHERE ae_sem.id_estudiante = e.id 
                AND DATE(ae_sem.fecha_ingreso) BETWEEN :fecha_inicio_semana AND :fecha_referencia2
                AND c_sem.id_tipo_dia IN (1, 3)
            ), 0) as asistencias_semana_actual,
            
            COALESCE((SELECT COUNT(DISTINCT DATE(ae_mes.fecha_ingreso))
                FROM asistencia_estudiantes ae_mes 
                INNER JOIN calendarios c_mes ON DATE(ae_mes.fecha_ingreso) = c_mes.fecha
                WHERE ae_mes.id_estudiante = e.id 
                AND DATE(ae_mes.fecha_ingreso) BETWEEN :fecha_inicio_mes AND :fecha_referencia3
                AND c_mes.id_tipo_dia IN (1, 3)
            ), 0) as asistencias_mes_actual,
            
            COALESCE((SELECT COUNT(DISTINCT DATE(ae_total.fecha_ingreso))
                FROM asistencia_estudiantes ae_total 
                INNER JOIN calendarios c_total ON DATE(ae_total.fecha_ingreso) = c_total.fecha
                WHERE ae_total.id_estudiante = e.id
                AND DATE(ae_total.fecha_ingreso) <= :fecha_referencia4
                AND c_total.id_tipo_dia IN (1, 3)
            ), 0) as total_asistencias,
            
            (SELECT MAX(DATE(ae_ultima.fecha_ingreso))
                FROM asistencia_estudiantes ae_ultima 
                WHERE ae_ultima.id_estudiante = e.id
                AND DATE(ae_ultima.fecha_ingreso) <= :fecha_referencia5
            ) as ultima_asistencia,
            
            COALESCE((SELECT ROUND(AVG(
                CASE 
                    WHEN ae_prom.fecha_salida IS NOT NULL THEN 
                        TIMESTAMPDIFF(MINUTE, ae_prom.fecha_ingreso, ae_prom.fecha_salida) / 60
                    ELSE 
                        CASE 
                            WHEN TIME(ae_prom.fecha_ingreso) <= ds_prom.hora_salida THEN
                                TIMESTAMPDIFF(MINUTE, ae_prom.fecha_ingreso, CONCAT(DATE(ae_prom.fecha_ingreso), ' ', ds_prom.hora_salida)) / 60
                            ELSE 0
                        END
                END
            ), 2)
                FROM asistencia_estudiantes ae_prom 
                INNER JOIN calendarios c_prom ON DATE(ae_prom.fecha_ingreso) = c_prom.fecha
                INNER JOIN dias_semana ds_prom ON c_prom.id_dia_semana = ds_prom.id
                WHERE ae_prom.id_estudiante = e.id 
                AND DATE(ae_prom.fecha_ingreso) >= :fecha_30_dias_atras
                AND DATE(ae_prom.fecha_ingreso) <= :fecha_referencia6
                AND c_prom.id_tipo_dia IN (1, 3)
            ), 0) as promedio_horas_permanencia
            
        FROM estudiantes e
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN estudiantes_x_grupos exg ON e.id = exg.id_estudiante AND exg.activo = 1
        INNER JOIN grupos g ON exg.id_grupo = g.id
        WHERE e.activo = 1 AND e.id_tenant = :id_tenant
        ORDER BY g.nombre, p.primer_nombre, p.segundo_nombre, p.primer_apellido";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':fecha_referencia', $fecha_referencia);
            $stmt->bindParam(':fecha_referencia2', $fecha_referencia);
            $stmt->bindParam(':fecha_referencia3', $fecha_referencia);
            $stmt->bindParam(':fecha_referencia4', $fecha_referencia);
            $stmt->bindParam(':fecha_referencia5', $fecha_referencia);
            $stmt->bindParam(':fecha_referencia6', $fecha_referencia);
            $stmt->bindParam(':fecha_inicio_semana', $fecha_inicio_semana);
            $stmt->bindParam(':fecha_inicio_mes', $fecha_inicio_mes);
            $stmt->bindParam(':fecha_30_dias_atras', $fecha_30_dias_atras);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            $indicadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $dias_habiles_semana = self::contarDiasHabilesReales($fecha_inicio_semana, $fecha_referencia);
            $dias_habiles_mes = self::contarDiasHabilesReales($fecha_inicio_mes, $fecha_referencia);

            foreach ($indicadores as &$indicador) {
                $indicador['dias_consecutivos_ausencia'] = self::calcularDiasConsecutivosAusencia(
                    $indicador['id_estudiante'],
                    $fecha_referencia,
                    $indicador['estado_hoy'] === 'Presente'
                );

                $indicador['porcentaje_asistencia_semana'] = $dias_habiles_semana > 0
                    ? round(($indicador['asistencias_semana_actual'] / $dias_habiles_semana) * 100, 1)
                    : 0;

                $indicador['porcentaje_asistencia_mes'] = $dias_habiles_mes > 0
                    ? round(($indicador['asistencias_mes_actual'] / $dias_habiles_mes) * 100, 1)
                    : 0;

                $indicador['clasificacion_riesgo'] = self::clasificarRiesgoAsistencia($indicador);
            }

            // Acudientes con teléfono para todos los estudiantes activos
            $sql_acudientes = "SELECT 
                e.id as id_estudiante,
                e.id_persona,
                a.id as id_acudiente,
                a.id_tipo_acudiente,
                ta.nombre as nombre_tipo_acudiente,
                pa.id as id_persona_acudiente,
                TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.segundo_nombre, pa.primer_apellido, pa.segundo_apellido)) as nombre_acudiente,
                pa.telefono,
                pa.correo_electronico
            FROM estudiantes e
            INNER JOIN acudientes a ON a.id_estudiante = e.id
            INNER JOIN tipos_acudiente ta ON ta.id = a.id_tipo_acudiente
            INNER JOIN personas pa ON pa.id = a.id_persona
            WHERE e.activo = 1
            AND e.id_tenant = :id_tenant
            ORDER BY e.id, ta.nombre";

            $stmt_acu = $db->prepare($sql_acudientes);
            $stmt_acu->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt_acu->execute();
            $acudientes = $stmt_acu->fetchAll(PDO::FETCH_ASSOC);

            // Último recordatorio de asistencia por estudiante
            $sql_ultimo_recordatorio = "SELECT 
                id_estudiante, 
                MAX(fecha_envio) as ultimo_recordatorio
            FROM historial_recordatorios_asistencia
            WHERE id_tenant = :id_tenant
            GROUP BY id_estudiante";

            $stmt_ultimo = $db->prepare($sql_ultimo_recordatorio);
            $stmt_ultimo->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt_ultimo->execute();
            $ultimos_recordatorios = $stmt_ultimo->fetchAll(PDO::FETCH_ASSOC);

            $map_recordatorios = [];
            foreach ($ultimos_recordatorios as $rec) {
                $map_recordatorios[$rec['id_estudiante']] = $rec['ultimo_recordatorio'];
            }

            foreach ($indicadores as &$indicador) {
                $indicador['ultimo_recordatorio'] = isset($map_recordatorios[$indicador['id_estudiante']])
                    ? $map_recordatorios[$indicador['id_estudiante']]
                    : null;
            }

            Flight::json([
                'fecha_consulta' => $fecha_referencia,
                'fecha_inicio_semana' => $fecha_inicio_semana,
                'fecha_inicio_mes' => $fecha_inicio_mes,
                'dias_habiles_semana' => $dias_habiles_semana,
                'dias_habiles_mes' => $dias_habiles_mes,
                'total_estudiantes' => count($indicadores),
                'indicadores' => $indicadores,
                'acudientes' => $acudientes
            ]);
        } catch (Exception $e) {
            error_log('Error en getSeguimientoAsistencia(): ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener seguimiento de asistencia'], 500);
        }
    }
}