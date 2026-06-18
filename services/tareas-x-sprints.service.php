<?php
class TareasXSprints
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
            txs.id,
            txs.id_sprint,
            txs.id_actividad_academica,
            txs.id_grupo,
            txs.id_area_academica,
            txs.id_estado_tarea,
            txs.id_docente,
            txs.fecha_ejecucion,
            txs.fecha_registro,
            txs.id_docente_inicia,
            txs.fecha_ejecucion_inicia,
            s.nombre_sprint,
            aa.titulo as titulo_actividad,
            et.nombre as nombre_estado,
            g.nombre as nombre_grupo,
            ar.nombre as nombre_area
        FROM tareas_x_sprints txs
        LEFT JOIN sprints s ON txs.id_sprint = s.id
        LEFT JOIN actividades_academicas aa ON txs.id_actividad_academica = aa.id
        LEFT JOIN estados_tareas et ON txs.id_estado_tarea = et.id
        LEFT JOIN grupos g ON txs.id_grupo = g.id
        LEFT JOIN areas_academicas ar ON txs.id_area_academica = ar.id
        WHERE txs.id_tenant = :id_tenant
        ORDER BY txs.id DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_sprint, id_actividad_academica, id_grupo, id_area_academica,
            id_estado_tarea, observaciones, id_docente, fecha_ejecucion, fecha_registro, id_docente_inicia, fecha_ejecucion_inicia,
            id_horario, dia_semana_horario, hora_inicial_horario, hora_final_horario
            FROM tareas_x_sprints WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getBySprintId($id_sprint)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                txs.id,
                txs.id_sprint,
                txs.id_actividad_academica,
                txs.id_grupo,
                txs.id_area_academica,
                txs.id_estado_tarea,
                txs.id_docente,
                txs.fecha_ejecucion,
                txs.fecha_registro,
                txs.id_docente_inicia,
                txs.fecha_ejecucion_inicia,
                aa.titulo as titulo_actividad,
                aa.minutos_duracion,
                et.nombre as nombre_estado,
                g.nombre as nombre_grupo,
                ar.nombre as nombre_area,
                CONCAT(p.primer_nombre, ' ', p.primer_apellido) as nombre_docente
            FROM tareas_x_sprints txs
            LEFT JOIN actividades_academicas aa ON txs.id_actividad_academica = aa.id
            LEFT JOIN estados_tareas et ON txs.id_estado_tarea = et.id
            LEFT JOIN grupos g ON txs.id_grupo = g.id
            LEFT JOIN areas_academicas ar ON txs.id_area_academica = ar.id
            LEFT JOIN docentes d ON txs.id_docente = d.id
            LEFT JOIN personas p ON d.id_persona = p.id
            WHERE txs.id_sprint = :id_sprint AND txs.id_tenant = :id_tenant
            ORDER BY txs.id
        ");
        $sentence->bindParam(':id_sprint', $id_sprint);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getBySprintIdDetallado($id_sprint)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                txs.id,
                txs.observaciones,
                txs.fecha_cambio_estado,
                txs.id_usuario_cambio_estado,
                CONCAT(pu.primer_nombre, ' ', pu.primer_apellido) as nombre_usuario_cambio,
                txs.id_sprint,
                txs.id_actividad_academica,
                txs.id_grupo,
                txs.id_area_academica,
                txs.id_estado_tarea,  
                txs.id_docente,
                txs.fecha_ejecucion,
                txs.fecha_registro,
                txs.id_docente_inicia,
                txs.fecha_ejecucion_inicia,
                aa.titulo as titulo_actividad,
                aa.descripcion as descripcion_actividad,
                aa.minutos_duracion,
                aa.nivel_uno,
                aa.nivel_dos,
                et.nombre as nombre_estado,
                CONCAT(p.primer_nombre, ' ', p.primer_apellido) as nombre_docente,
                g.nombre as nombre_grupo,
                ar.nombre as nombre_area,
                GROUP_CONCAT(DISTINCT ed.nombre ORDER BY ed.nombre) as esferas,
                GROUP_CONCAT(DISTINCT il.nombre ORDER BY il.nombre) as indicadores_logro
            FROM tareas_x_sprints txs
            LEFT JOIN usuarios u ON txs.id_usuario_cambio_estado = u.id
            LEFT JOIN personas pu ON u.id_persona = pu.id
            LEFT JOIN actividades_academicas aa ON txs.id_actividad_academica = aa.id
            LEFT JOIN estados_tareas et ON txs.id_estado_tarea = et.id
            LEFT JOIN docentes d ON txs.id_docente = d.id
            LEFT JOIN personas p ON d.id_persona = p.id
            LEFT JOIN grupos g ON txs.id_grupo = g.id
            LEFT JOIN areas_academicas ar ON txs.id_area_academica = ar.id
            LEFT JOIN actividades_academicas_x_indicadores_logros aaxil ON aa.id = aaxil.id_actividad_academica
            LEFT JOIN indicadores_logros il ON aaxil.id_indicador_logro = il.id
            LEFT JOIN logros l ON il.id_logro = l.id
            LEFT JOIN esferas_desarrollo ed ON l.id_esfera_desarrollo = ed.id
            WHERE txs.id_sprint = :id_sprint AND txs.id_tenant = :id_tenant
            GROUP BY txs.id
            ORDER BY txs.id
        ");
        $sentence->bindParam(':id_sprint', $id_sprint);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByActividadId($id_actividad)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
            txs.*,
            s.nombre_sprint,
            et.nombre as nombre_estado,
            g.nombre as nombre_grupo,
            ar.nombre as nombre_area
        FROM tareas_x_sprints txs
        LEFT JOIN sprints s ON txs.id_sprint = s.id
        LEFT JOIN estados_tareas et ON txs.id_estado_tarea = et.id
        LEFT JOIN grupos g ON txs.id_grupo = g.id
        LEFT JOIN areas_academicas ar ON txs.id_area_academica = ar.id
        WHERE txs.id_actividad_academica = :id_actividad AND txs.id_tenant = :id_tenant
        ORDER BY txs.id");
        $sentence->bindParam(':id_actividad', $id_actividad);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $id_sprint = Flight::request()->data['id_sprint'];
            $id_actividad_academica = Flight::request()->data['id_actividad_academica'];
            $id_grupo = Flight::request()->data['id_grupo'] ?? null;
            $id_area_academica = Flight::request()->data['id_area_academica'] ?? null;
            $id_estado_tarea = Flight::request()->data['id_estado_tarea'] ?? 1;
            $id_docente = Flight::request()->data['id_docente'] ?? null;
            $fecha_ejecucion = Flight::request()->data['fecha_ejecucion'] ?? null;
            $fecha_registro = Flight::request()->data['fecha_registro'] ?? date('Y-m-d H:i:s');
            $orden_ejecucion = Flight::request()->data['orden_ejecucion'] ?? null;

            error_log("Creando tarea: sprint=$id_sprint, actividad=$id_actividad_academica, grupo=$id_grupo, area=$id_area_academica");

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO tareas_x_sprints (
                id, id_tenant,
                id_sprint, id_actividad_academica, id_grupo, id_area_academica,
                id_estado_tarea, id_docente, fecha_ejecucion, fecha_registro, orden_ejecucion
            ) VALUES (
                :id, :id_tenant,
                :id_sprint, :id_actividad_academica, :id_grupo, :id_area_academica,
                :id_estado_tarea, :id_docente, :fecha_ejecucion, :fecha_registro, :orden_ejecucion
            )");

            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_sprint', $id_sprint);
            $sentence->bindParam(':id_actividad_academica', $id_actividad_academica);
            $sentence->bindParam(':id_grupo', $id_grupo);
            $sentence->bindParam(':id_area_academica', $id_area_academica);
            $sentence->bindParam(':id_estado_tarea', $id_estado_tarea);
            $sentence->bindParam(':id_docente', $id_docente);
            $sentence->bindParam(':fecha_ejecucion', $fecha_ejecucion);
            $sentence->bindParam(':fecha_registro', $fecha_registro);
            $sentence->bindParam(':orden_ejecucion', $orden_ejecucion);

            $sentence->execute();
            $id = $idNew;

            error_log("Tarea creada con ID: $id");
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al crear tarea: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear la tarea: ' . $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_docente = Flight::request()->data['id_docente'];

        // Obtener grupo de la tarea para calcular estudiantes
        $stmtTarea = $db->prepare("SELECT id_grupo FROM tareas_x_sprints WHERE id = :id AND id_tenant = :id_tenant");
        $stmtTarea->bindParam(':id', $id);
        $stmtTarea->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtTarea->execute();
        $tarea = $stmtTarea->fetch(PDO::FETCH_ASSOC);

        $totalGrupo = 0;
        $totalCalificados = 0;

        if ($tarea) {
            // Contar estudiantes activos en el grupo
            $stmtGrupo = $db->prepare("
                SELECT COUNT(*) as total 
                FROM estudiantes_x_grupos exg
                INNER JOIN estudiantes e ON exg.id_estudiante = e.id
                WHERE exg.id_grupo = :id_grupo 
                AND exg.activo = 1 
                AND e.activo = 1
                AND exg.id_tenant = :id_tenant
            ");
            $stmtGrupo->bindParam(':id_grupo', $tarea['id_grupo']);
            $stmtGrupo->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtGrupo->execute();
            $resGrupo = $stmtGrupo->fetch(PDO::FETCH_ASSOC);
            $totalGrupo = $resGrupo['total'] ?? 0;

            // Contar estudiantes calificados en esta tarea
            $stmtCal = $db->prepare("
                SELECT COUNT(DISTINCT id_estudiante) as total 
                FROM calificaciones 
                WHERE id_tarea_x_sprint = :id AND id_tenant = :id_tenant
            ");
            $stmtCal->bindParam(':id', $id);
            $stmtCal->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtCal->execute();
            $resCal = $stmtCal->fetch(PDO::FETCH_ASSOC);
            $totalCalificados = $resCal['total'] ?? 0;
        }

        $sentence = $db->prepare("UPDATE tareas_x_sprints SET 
            id_docente = :id_docente, 
            fecha_ejecucion = CONVERT_TZ(NOW(), '+00:00', '+02:00'), 
            fecha_registro = CONVERT_TZ(current_timestamp(), '+00:00', '+02:00'), 
            id_estado_tarea = 2,
            total_estudiantes_grupo = :total_grupo,
            total_estudiantes_calificados = :total_calificados
            WHERE id = :id AND id_tenant = :id_tenant");

        $sentence->bindParam(':id_docente', $id_docente);
        $sentence->bindParam(':total_grupo', $totalGrupo);
        $sentence->bindParam(':total_calificados', $totalCalificados);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    public static function iniciar()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_docente = Flight::request()->data['id_docente'];
        $id_horario = Flight::request()->data['id_horario'] ?? null;
        $dia_semana_horario = Flight::request()->data['dia_semana_horario'] ?? null;
        $hora_inicial_horario = Flight::request()->data['hora_inicial_horario'] ?? null;
        $hora_final_horario = Flight::request()->data['hora_final_horario'] ?? null;
        $user_agent = Flight::request()->data['user_agent'] ?? null;
        $huella_dispositivo = Flight::request()->data['huella_dispositivo'] ?? null;

        $sentence = $db->prepare("UPDATE tareas_x_sprints SET 
            id_docente_inicia = :id_docente, 
            fecha_ejecucion_inicia = CONVERT_TZ(NOW(), '+00:00', '+02:00'),
            id_horario = :id_horario,
            dia_semana_horario = :dia_semana_horario,
            hora_inicial_horario = :hora_inicial_horario,
            hora_final_horario = :hora_final_horario,
            user_agent = :user_agent,
            huella_dispositivo = :huella_dispositivo
            WHERE id = :id AND id_tenant = :id_tenant");

        $sentence->bindParam(':id_docente', $id_docente);
        $sentence->bindParam(':id_horario', $id_horario);
        $sentence->bindParam(':dia_semana_horario', $dia_semana_horario);
        $sentence->bindParam(':hora_inicial_horario', $hora_inicial_horario);
        $sentence->bindParam(':hora_final_horario', $hora_final_horario);
        $sentence->bindParam(':user_agent', $user_agent);
        $sentence->bindParam(':huella_dispositivo', $huella_dispositivo);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            error_log("Eliminando tarea ID: $id");

            // Verificar si tiene calificaciones asociadas
            $checkSentence = $db->prepare("SELECT COUNT(*) as total FROM calificaciones WHERE id_tarea_x_sprint = :id AND id_tenant = :id_tenant");
            $checkSentence->bindParam(':id', $id);
            $checkSentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkSentence->execute();
            $result = $checkSentence->fetch();

            if ($result['total'] > 0) {
                Flight::json(array('error' => 'No se puede eliminar la tarea porque tiene calificaciones asociadas'), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM tareas_x_sprints WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró la tarea con el ID especificado'), 404);
                return;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al eliminar tarea: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar la tarea'), 500);
        }
    }

    public static function getEstadisticasSprint($id_sprint)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
            COUNT(*) as total_tareas,
            SUM(CASE WHEN id_estado_tarea = 2 THEN 1 ELSE 0 END) as tareas_ejecutadas,
            SUM(CASE WHEN id_estado_tarea = 1 THEN 1 ELSE 0 END) as tareas_pendientes,
            SUM(CASE WHEN id_estado_tarea = 3 THEN 1 ELSE 0 END) as tareas_canceladas
        FROM tareas_x_sprints
        WHERE id_sprint = :id_sprint AND id_tenant = :id_tenant");

        $sentence->bindParam(':id_sprint', $id_sprint);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();

        if ($response['total_tareas'] > 0) {
            $response['porcentaje_completado'] = round(($response['tareas_ejecutadas'] / $response['total_tareas']) * 100, 2);
        } else {
            $response['porcentaje_completado'] = 0;
        }

        Flight::json($response);
    }

    public static function getResumenClasesPorGrupo($id_grupo)
    {
        try {
            $db = Flight::db();
            $id_sprint = $_GET['id_sprint'] ?? null;
            if (!$id_sprint) {
                $sprintQuery = $db->prepare("SELECT id FROM sprints WHERE actual = 1 AND id_tenant = :id_tenant LIMIT 1");
                $sprintQuery->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sprintQuery->execute();
                $sprintActual = $sprintQuery->fetch();
                $id_sprint = $sprintActual ? $sprintActual['id'] : null;
            }
            $sql = "SELECT 
            g.id as id_grupo, g.nombre as nombre_grupo,
            COUNT(DISTINCT txs.id) as total_clases,
            COUNT(DISTINCT CASE WHEN txs.id_docente_inicia IS NOT NULL AND txs.fecha_ejecucion_inicia IS NOT NULL AND txs.fecha_ejecucion IS NOT NULL THEN txs.id END) as clases_completas,
            COUNT(DISTINCT CASE WHEN txs.fecha_ejecucion_inicia IS NOT NULL AND txs.fecha_ejecucion IS NOT NULL AND TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) < 30 THEN txs.id END) as clases_muy_cortas,
            COUNT(DISTINCT CASE WHEN txs.fecha_ejecucion_inicia IS NOT NULL AND txs.fecha_ejecucion IS NOT NULL AND TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) > 90 THEN txs.id END) as clases_muy_largas,
            AVG(CASE WHEN txs.fecha_ejecucion_inicia IS NOT NULL AND txs.fecha_ejecucion IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) END) as promedio_duracion_minutos,
            COUNT(DISTINCT txs.id_docente) as cantidad_docentes
            FROM grupos g
            INNER JOIN tareas_x_sprints txs ON g.id = txs.id_grupo
            WHERE g.id = :id_grupo AND txs.id_sprint = :id_sprint AND g.id_tenant = :id_tenant
            GROUP BY g.id, g.nombre";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_grupo', $id_grupo);
            $stmt->bindParam(':id_sprint', $id_sprint);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($resultado) {
                $resultado['porcentaje_clases_completas'] = $resultado['total_clases'] > 0 ? round(($resultado['clases_completas'] / $resultado['total_clases']) * 100, 2) : 0;
                $clases_duracion_normal = $resultado['clases_completas'] - $resultado['clases_muy_cortas'] - $resultado['clases_muy_largas'];
                $resultado['porcentaje_clases_duracion_normal'] = $resultado['clases_completas'] > 0 ? round(($clases_duracion_normal / $resultado['clases_completas']) * 100, 2) : 0;
                $resultado['promedio_duracion_minutos'] = round($resultado['promedio_duracion_minutos'] ?? 0, 2);
                $resultado['id_sprint'] = $id_sprint;
            }
            Flight::json($resultado);
        } catch (Exception $e) {
            error_log("Error en getResumenClasesPorGrupo: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener resumen de clases'], 500);
        }
    }

    public static function getResumenClasesTodosGrupos()
    {
        try {
            $db = Flight::db();
            $id_sprint = $_GET['id_sprint'] ?? null;
            if (!$id_sprint) {
                $sprintQuery = $db->prepare("SELECT id FROM sprints WHERE actual = 1 AND id_tenant = :id_tenant LIMIT 1");
                $sprintQuery->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sprintQuery->execute();
                $sprintActual = $sprintQuery->fetch();
                $id_sprint = $sprintActual ? $sprintActual['id'] : null;
            }
            $sql = "SELECT 
            g.id as id_grupo, g.nombre as nombre_grupo, g.icono, g.color,
            COUNT(DISTINCT txs.id) as total_clases,
            COUNT(DISTINCT CASE WHEN txs.id_docente_inicia IS NOT NULL AND txs.fecha_ejecucion_inicia IS NOT NULL AND txs.fecha_ejecucion IS NOT NULL THEN txs.id END) as clases_completas,
            AVG(CASE WHEN txs.fecha_ejecucion_inicia IS NOT NULL AND txs.fecha_ejecucion IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) END) as promedio_duracion_minutos
            FROM grupos g
            LEFT JOIN tareas_x_sprints txs ON g.id = txs.id_grupo AND txs.id_sprint = :id_sprint
            WHERE g.calificable = 1 AND g.id_tenant = :id_tenant
            GROUP BY g.id, g.nombre, g.icono, g.color
            ORDER BY g.orden";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_sprint', $id_sprint);
            $stmt->execute();
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($resultados);
        } catch (Exception $e) {
            error_log("Error en getResumenClasesTodosGrupos: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener resumen de clases'], 500);
        }
    }

    public static function cambiarEstado()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $id_estado_tarea = Flight::request()->data['id_estado_tarea'];
            $observaciones = Flight::request()->data['observaciones'] ?? null;
            $id_usuario_cambio_estado = Flight::request()->data['id_usuario_cambio_estado'] ?? null;

            $sentence = $db->prepare("UPDATE tareas_x_sprints SET 
            id_estado_tarea = :id_estado_tarea,
            observaciones = :observaciones,
            id_usuario_cambio_estado = :id_usuario_cambio_estado,
            fecha_cambio_estado = NOW()
            WHERE id = :id AND id_tenant = :id_tenant");

            $sentence->bindParam(':id_estado_tarea', $id_estado_tarea);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':id_usuario_cambio_estado', $id_usuario_cambio_estado);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró la tarea con el ID especificado'), 404);
                return;
            }

            error_log("Estado de tarea actualizado por usuario ID: $id_usuario_cambio_estado");
            Flight::json(array('id' => $id, 'mensaje' => 'Estado actualizado correctamente'));
        } catch (Exception $e) {
            error_log("Error al cambiar estado de tarea: " . $e->getMessage());
            Flight::json(array('error' => 'Error al cambiar el estado de la tarea'), 500);
        }
    }

    /**
     * Obtener tareas de un sprint para importación con detalle completo de actividad
     */
    public static function getTareasParaImportar($id_sprint)
    {
        try {
            $db = Flight::db();
            $id_grupo = $_GET['id_grupo'] ?? null;
            $id_area_academica = $_GET['id_area_academica'] ?? null;
            $solo_no_ejecutadas = $_GET['solo_no_ejecutadas'] ?? null;

            $sql = "SELECT 
                txs.id,
                txs.id_sprint,
                txs.id_actividad_academica,
                txs.id_grupo,
                txs.id_area_academica,
                txs.id_estado_tarea,
                aa.titulo as titulo_actividad,
                aa.descripcion as descripcion_actividad,
                aa.minutos_duracion,
                aa.materiales,
                aa.nivel_uno,
                aa.nivel_dos,
                aa.id_tipo_actividad_academica,
                ta.nombre as nombre_tipo_actividad,
                et.nombre as nombre_estado,
                g.nombre as nombre_grupo,
                ar.nombre as nombre_area,
                s.nombre_sprint,
                s.numero_sprint
            FROM tareas_x_sprints txs
            INNER JOIN actividades_academicas aa ON txs.id_actividad_academica = aa.id
            LEFT JOIN tipos_actividades_academicas ta ON aa.id_tipo_actividad_academica = ta.id
            INNER JOIN estados_tareas et ON txs.id_estado_tarea = et.id
            INNER JOIN grupos g ON txs.id_grupo = g.id
            INNER JOIN areas_academicas ar ON txs.id_area_academica = ar.id
            INNER JOIN sprints s ON txs.id_sprint = s.id
            WHERE txs.id_sprint = :id_sprint AND txs.id_tenant = :id_tenant";

            $params = [':id_sprint' => $id_sprint, ':id_tenant' => TenantContext::id()];

            if ($id_grupo) {
                $sql .= " AND txs.id_grupo = :id_grupo";
                $params[':id_grupo'] = $id_grupo;
            }
            if ($id_area_academica) {
                $sql .= " AND txs.id_area_academica = :id_area_academica";
                $params[':id_area_academica'] = $id_area_academica;
            }
            if ($solo_no_ejecutadas == '1') {
                $sql .= " AND txs.id_estado_tarea != 2";
            }

            $sql .= " ORDER BY g.nombre, ar.nombre, aa.titulo";

            $sentence = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $sentence->bindValue($key, $value);
            }
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en getTareasParaImportar: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener tareas para importar'], 500);
        }
    }

    /**
     * Importar tareas masivamente en una sola transacción
     */
    public static function importarMasivo()
    {
        try {
            $db = Flight::db();
            $id_sprint_destino = Flight::request()->data['id_sprint_destino'];
            $tareas = Flight::request()->data['tareas'];

            if (!$id_sprint_destino || !$tareas || !is_array($tareas) || count($tareas) === 0) {
                Flight::json(['error' => 'Datos incompletos para importación'], 400);
                return;
            }

            error_log("Importación masiva: sprint_destino=$id_sprint_destino, tareas=" . count($tareas));

            $db->beginTransaction();

            $sentence = $db->prepare("INSERT INTO tareas_x_sprints (
                id_tenant, id_sprint, id_actividad_academica, id_grupo, id_area_academica,
                id_estado_tarea, fecha_registro
            ) VALUES (
                :id_tenant, :id_sprint, :id_actividad_academica, :id_grupo, :id_area_academica,
                1, NOW()
            )");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $importadas = 0;
            $errores = [];

            foreach ($tareas as $tarea) {
                try {
                    $sentence->bindValue(':id_sprint', $id_sprint_destino);
                    $sentence->bindValue(':id_actividad_academica', $tarea['id_actividad_academica']);
                    $sentence->bindValue(':id_grupo', $tarea['id_grupo']);
                    $sentence->bindValue(':id_area_academica', $tarea['id_area_academica']);
                    $sentence->execute();
                    $importadas++;
                } catch (Exception $e) {
                    $errores[] = "Actividad {$tarea['id_actividad_academica']}: " . $e->getMessage();
                    error_log("Error importando tarea: " . $e->getMessage());
                }
            }

            $db->commit();

            Flight::json([
                'importadas' => $importadas,
                'total_solicitadas' => count($tareas),
                'errores' => $errores
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en importación masiva: " . $e->getMessage());
            Flight::json(['error' => 'Error en importación masiva: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener tareas de un sprint filtradas por grupo y área, ordenadas por orden_ejecucion
     */
    public static function getBySprintGrupoArea($id_sprint, $id_grupo, $id_area)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    txs.id,
                    txs.id_sprint,
                    txs.id_actividad_academica,
                    txs.id_grupo,
                    txs.id_area_academica,
                    txs.id_estado_tarea,
                    txs.orden_ejecucion,
                    txs.observaciones,
                    txs.id_docente,
                    txs.fecha_ejecucion,
                    txs.fecha_registro,
                    aa.titulo as titulo_actividad,
                    aa.descripcion as descripcion_actividad,
                    aa.minutos_duracion,
                    aa.nivel_uno,
                    aa.nivel_dos,
                    et.nombre as nombre_estado,
                    g.nombre as nombre_grupo,
                    ar.nombre as nombre_area,
                    CONCAT(p.primer_nombre, ' ', p.primer_apellido) as nombre_docente
                FROM tareas_x_sprints txs
                INNER JOIN actividades_academicas aa ON txs.id_actividad_academica = aa.id
                INNER JOIN estados_tareas et ON txs.id_estado_tarea = et.id
                LEFT JOIN grupos g ON txs.id_grupo = g.id
                LEFT JOIN areas_academicas ar ON txs.id_area_academica = ar.id
                LEFT JOIN docentes d ON txs.id_docente = d.id
                LEFT JOIN personas p ON d.id_persona = p.id
                WHERE txs.id_sprint = :id_sprint
                AND txs.id_grupo = :id_grupo
                AND txs.id_area_academica = :id_area
                AND txs.id_tenant = :id_tenant
                ORDER BY txs.orden_ejecucion ASC, txs.id ASC
            ");

            $sentence->bindParam(':id_sprint', $id_sprint);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_grupo', $id_grupo);
            $sentence->bindParam(':id_area', $id_area);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en getBySprintGrupoArea: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener tareas por sprint/grupo/área'], 500);
        }
    }

    /**
     * Actualizar el orden de ejecución de múltiples tareas
     * Recibe un array de objetos con id y orden_ejecucion
     */
    public static function actualizarOrden()
    {
        try {
            $db = Flight::db();
            $ordenes = Flight::request()->data['ordenes'];

            if (!$ordenes || !is_array($ordenes) || count($ordenes) === 0) {
                Flight::json(['error' => 'Datos de orden incompletos'], 400);
                return;
            }

            $db->beginTransaction();

            $sentence = $db->prepare("UPDATE tareas_x_sprints SET orden_ejecucion = :orden WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $actualizadas = 0;
            foreach ($ordenes as $item) {
                $sentence->bindValue(':orden', $item['orden_ejecucion']);
                $sentence->bindValue(':id', $item['id']);
                $sentence->execute();
                $actualizadas++;
            }

            $db->commit();

            Flight::json([
                'actualizadas' => $actualizadas,
                'mensaje' => 'Orden actualizado correctamente'
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en actualizarOrden: " . $e->getMessage());
            Flight::json(['error' => 'Error al actualizar el orden'], 500);
        }
    }

    /**
     * Actualizar orden de ejecución y duración de actividades en una sola transacción.
     * Recibe un array de objetos con: id (tarea_x_sprint), orden_ejecucion, id_actividad_academica, minutos_duracion
     */
    public static function actualizarOrdenYDuracion()
    {
        try {
            $db = Flight::db();
            $tareas = Flight::request()->data['tareas'];

            if (!$tareas || !is_array($tareas) || count($tareas) === 0) {
                Flight::json(['error' => 'Datos incompletos'], 400);
                return;
            }

            $db->beginTransaction();

            $stmtOrden = $db->prepare("UPDATE tareas_x_sprints SET orden_ejecucion = :orden WHERE id = :id AND id_tenant = :id_tenant");
            $stmtDuracion = $db->prepare("UPDATE actividades_academicas SET minutos_duracion = :minutos WHERE id = :id AND id_tenant = :id_tenant");
            $stmtOrden->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtDuracion->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $ordenesActualizados = 0;
            $duracionesActualizadas = 0;

            foreach ($tareas as $item) {
                $stmtOrden->bindValue(':orden', $item['orden_ejecucion']);
                $stmtOrden->bindValue(':id', $item['id']);
                $stmtOrden->execute();
                $ordenesActualizados++;

                if (isset($item['id_actividad_academica']) && isset($item['minutos_duracion'])) {
                    $stmtDuracion->bindValue(':minutos', $item['minutos_duracion']);
                    $stmtDuracion->bindValue(':id', $item['id_actividad_academica']);
                    $stmtDuracion->execute();
                    $duracionesActualizadas++;
                }
            }

            $db->commit();

            Flight::json([
                'ordenes_actualizados' => $ordenesActualizados,
                'duraciones_actualizadas' => $duracionesActualizadas,
                'mensaje' => 'Orden y duración actualizados correctamente'
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en actualizarOrdenYDuracion: " . $e->getMessage());
            Flight::json(['error' => 'Error al actualizar orden y duración'], 500);
        }
    }

    /**
     * Actualizar la observación general de una tarea
     */
    public static function actualizarObservacion()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $observaciones = Flight::request()->data['observaciones'];

            $sentence = $db->prepare("UPDATE tareas_x_sprints SET observaciones = :observaciones WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id, 'mensaje' => 'Observación actualizada'));
        } catch (Exception $e) {
            error_log("Error en actualizarObservacion: " . $e->getMessage());
            Flight::json(['error' => 'Error al actualizar observación'], 500);
        }
    }

    /**
     * Sincronizar tareas de un sprint/grupo/área en una sola transacción.
     * Estrategia: comparar estado actual vs deseado.
     * Las tareas ejecutadas (estado 2) se preservan siempre.
     * Las pendientes se eliminan y se recrean según el estado final deseado.
     */
    public static function sincronizar()
    {
        try {
            $db = Flight::db();
            $id_sprint = Flight::request()->data['id_sprint'];
            $id_grupo = Flight::request()->data['id_grupo'];
            $id_area_academica = Flight::request()->data['id_area_academica'];
            $tareas = Flight::request()->data['tareas'];

            if (!$id_sprint || !$id_grupo || !$id_area_academica || !is_array($tareas)) {
                Flight::json(['error' => 'Datos incompletos para sincronización'], 400);
                return;
            }

            error_log("Sincronización: sprint=$id_sprint, grupo=$id_grupo, area=$id_area_academica, tareas=" . count($tareas));

            $db->beginTransaction();

            // 1. Obtener tareas actuales en BD
            $stmtActuales = $db->prepare("
                SELECT id, id_actividad_academica, orden_ejecucion, id_estado_tarea
                FROM tareas_x_sprints
                WHERE id_sprint = :id_sprint AND id_grupo = :id_grupo AND id_area_academica = :id_area AND id_tenant = :id_tenant
                ORDER BY orden_ejecucion ASC, id ASC
            ");
            $stmtActuales->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtActuales->bindParam(':id_sprint', $id_sprint);
            $stmtActuales->bindParam(':id_grupo', $id_grupo);
            $stmtActuales->bindParam(':id_area', $id_area_academica);
            $stmtActuales->execute();
            $actuales = $stmtActuales->fetchAll(PDO::FETCH_ASSOC);

            // 2. Separar ejecutadas (estado 2) de no ejecutadas
            $ejecutadas = [];
            $noEjecutadas = [];
            foreach ($actuales as $actual) {
                if ($actual['id_estado_tarea'] == 2) {
                    $ejecutadas[] = $actual;
                } else {
                    $noEjecutadas[] = $actual;
                }
            }

            // 3. Verificar calificaciones antes de eliminar
            $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM calificaciones WHERE id_tarea_x_sprint = :id AND id_tenant = :id_tenant");
            $stmtEliminar = $db->prepare("DELETE FROM tareas_x_sprints WHERE id = :id AND id_tenant = :id_tenant");
            $stmtCheck->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtEliminar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $eliminadas = 0;
            foreach ($noEjecutadas as $tarea) {
                $stmtCheck->bindValue(':id', $tarea['id']);
                $stmtCheck->execute();
                $check = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($check['total'] == 0) {
                    $stmtEliminar->bindValue(':id', $tarea['id']);
                    $stmtEliminar->execute();
                    $eliminadas++;
                } else {
                    error_log("No se puede eliminar tarea ID {$tarea['id']}: tiene calificaciones");
                }
            }

            // 4. Determinar qué tareas deseadas ya están cubiertas por las ejecutadas
            // Construir lista de id_actividad de ejecutadas disponibles para emparejar
            $ejecutadasDisponibles = [];
            foreach ($ejecutadas as $ej) {
                $ejecutadasDisponibles[] = $ej;
            }

            $stmtCrear = $db->prepare("INSERT INTO tareas_x_sprints (
                id_tenant, id_sprint, id_actividad_academica, id_grupo, id_area_academica,
                id_estado_tarea, fecha_registro, orden_ejecucion
            ) VALUES (
                :id_tenant, :id_sprint, :id_actividad, :id_grupo, :id_area,
                1, NOW(), :orden
            )");
            $stmtCrear->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $stmtOrden = $db->prepare("UPDATE tareas_x_sprints SET orden_ejecucion = :orden WHERE id = :id AND id_tenant = :id_tenant");
            $stmtOrden->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $creadas = 0;
            $ordenesActualizados = 0;

            foreach ($tareas as $tarea) {
                $idActividad = $tarea['id_actividad_academica'];
                $orden = $tarea['orden_ejecucion'];

                // Buscar si alguna ejecutada cubre esta actividad
                $encontrada = false;
                foreach ($ejecutadasDisponibles as $key => $ej) {
                    if ($ej['id_actividad_academica'] == $idActividad) {
                        // Actualizar orden de la ejecutada
                        if ($ej['orden_ejecucion'] != $orden) {
                            $stmtOrden->bindValue(':orden', $orden);
                            $stmtOrden->bindValue(':id', $ej['id']);
                            $stmtOrden->execute();
                            $ordenesActualizados++;
                        }
                        // Remover de disponibles para no reutilizar
                        unset($ejecutadasDisponibles[$key]);
                        $encontrada = true;
                        break;
                    }
                }

                if (!$encontrada) {
                    // Crear nueva tarea
                    $stmtCrear->bindValue(':id_sprint', $id_sprint);
                    $stmtCrear->bindValue(':id_actividad', $idActividad);
                    $stmtCrear->bindValue(':id_grupo', $id_grupo);
                    $stmtCrear->bindValue(':id_area', $id_area_academica);
                    $stmtCrear->bindValue(':orden', $orden);
                    $stmtCrear->execute();
                    $creadas++;
                }
            }

            $db->commit();

            Flight::json([
                'creadas' => $creadas,
                'eliminadas' => $eliminadas,
                'ordenes_actualizados' => $ordenesActualizados,
                'total_final' => count($tareas),
                'ejecutadas_preservadas' => count($ejecutadas),
                'mensaje' => 'Sincronización completada'
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en sincronizar: " . $e->getMessage());
            Flight::json(['error' => 'Error en sincronización: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reporte de ejecución de tareas con detalle de horario, huella y tardanza
     */
    public static function getReporteEjecucionTareas()
    {
        try {
            $db = Flight::db();
            $anio = $_GET['anio'] ?? null;
            $id_sprint = $_GET['id_sprint'] ?? null;

            $sql = "
                SELECT 
                    txs.id,
                    txs.id_sprint,
                    s.nombre_sprint,
                    s.numero_sprint,
                    YEAR(s.fecha_inicial) as anio_sprint,
                    txs.id_grupo,
                    g.nombre as nombre_grupo,
                    txs.id_area_academica,
                    ar.nombre as nombre_area,
                    aa.titulo as titulo_actividad,
                    aa.descripcion as descripcion_actividad,
                    aa.materiales as materiales_actividad,
                    amb.nombre as nombre_ambiente,
                    txs.observaciones as observacion_general,
                    et.nombre as nombre_estado,
                    txs.id_estado_tarea,
                    txs.es_tarea_adicional,
                    txs.fecha_registro,
                    DATE(txs.fecha_ejecucion_inicia) as fecha_ejecucion,
                    txs.fecha_ejecucion_inicia,
                    txs.fecha_ejecucion as fecha_finalizacion,
                    CONCAT(pd.primer_nombre, ' ', pd.primer_apellido) as nombre_docente_inicia,
                    txs.id_docente_inicia,
                    CONCAT(pd2.primer_nombre, ' ', pd2.primer_apellido) as nombre_docente_finaliza,
                    txs.id_horario,
                    txs.dia_semana_horario,
                    txs.hora_inicial_horario,
                    txs.hora_final_horario,
                    txs.user_agent,
                    txs.huella_dispositivo,
                    txs.total_estudiantes_grupo,
                    txs.total_estudiantes_calificados,
                    CASE 
                        WHEN txs.fecha_ejecucion_inicia IS NOT NULL AND txs.fecha_ejecucion IS NOT NULL 
                        THEN TIMESTAMPDIFF(MINUTE, txs.fecha_ejecucion_inicia, txs.fecha_ejecucion) 
                        ELSE NULL 
                    END as duracion_real_minutos,
                    CASE 
                        WHEN txs.fecha_ejecucion_inicia IS NOT NULL AND txs.hora_inicial_horario IS NOT NULL 
                        THEN TIMESTAMPDIFF(MINUTE, 
                            CONCAT(DATE(txs.fecha_ejecucion_inicia), ' ', txs.hora_inicial_horario),
                            txs.fecha_ejecucion_inicia
                        )
                        ELSE NULL 
                    END as tardanza_minutos
                FROM tareas_x_sprints txs
                INNER JOIN sprints s ON txs.id_sprint = s.id
                INNER JOIN actividades_academicas aa ON txs.id_actividad_academica = aa.id
                INNER JOIN estados_tareas et ON txs.id_estado_tarea = et.id
                LEFT JOIN grupos g ON txs.id_grupo = g.id
                LEFT JOIN areas_academicas ar ON txs.id_area_academica = ar.id
                LEFT JOIN ambientes amb ON aa.id_ambiente = amb.id
                LEFT JOIN docentes d ON txs.id_docente_inicia = d.id
                LEFT JOIN personas pd ON d.id_persona = pd.id
                LEFT JOIN docentes d2 ON txs.id_docente = d2.id
                LEFT JOIN personas pd2 ON d2.id_persona = pd2.id
                WHERE txs.id_tenant = :id_tenant
            ";

            $params = [':id_tenant' => TenantContext::id()];

            if ($id_sprint) {
                $sql .= " AND txs.id_sprint = :id_sprint";
                $params[':id_sprint'] = $id_sprint;
            } else if ($anio) {
                $sql .= " AND YEAR(s.fecha_inicial) = :anio";
                $params[':anio'] = $anio;
            }

            $sql .= " ORDER BY s.numero_sprint DESC, txs.fecha_ejecucion_inicia DESC, txs.id DESC";

            $sentence = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $sentence->bindValue($key, $value);
            }
            $sentence->execute();
            $registros = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // Detectar cambio de huella por docente
            $huellasPorDocente = [];
            foreach ($registros as &$reg) {
                $idDoc = $reg['id_docente_inicia'];
                $huella = $reg['huella_dispositivo'];
                $reg['cambio_dispositivo'] = 0;
                $reg['dispositivo_de'] = null;

                if ($idDoc && $huella) {
                    if (isset($huellasPorDocente[$idDoc])) {
                        if ($huellasPorDocente[$idDoc] !== $huella) {
                            $reg['cambio_dispositivo'] = 1;
                            foreach ($registros as $otro) {
                                if ($otro['id_docente_inicia'] != $idDoc 
                                    && $otro['huella_dispositivo'] === $huella 
                                    && $otro['nombre_docente_inicia']) {
                                    $reg['dispositivo_de'] = $otro['nombre_docente_inicia'];
                                    break;
                                }
                            }
                        }
                    }
                    $huellasPorDocente[$idDoc] = $huella;
                }
            }
            unset($reg);

            // Estadísticas
            $total = count($registros);
            $ejecutadas = 0;
            $pendientes = 0;
            $adicionales = 0;
            $conTardanza = 0;
            $cambioDispositivo = 0;

            foreach ($registros as $r) {
                if ($r['id_estado_tarea'] == 2) $ejecutadas++;
                if ($r['id_estado_tarea'] == 1) $pendientes++;
                if ($r['es_tarea_adicional'] == 1) $adicionales++;
                if ($r['tardanza_minutos'] !== null && $r['tardanza_minutos'] > 0) $conTardanza++;
                if ($r['cambio_dispositivo'] == 1) $cambioDispositivo++;
            }

            // Obtener IDs de tareas para consultar calificaciones
            $idsTareas = array_column($registros, 'id');

            // Calificaciones en crudo agrupadas por tarea
            $calificacionesPorTarea = [];
            if (!empty($idsTareas)) {
                $placeholders = implode(',', array_fill(0, count($idsTareas), '?'));
                $sqlCal = "
                    SELECT c.id_tarea_x_sprint, c.id_estudiante, c.id_parametro_calificacion, 
                           c.id_valor_parametro_calificacion, vpc.valor_cuantitativo, vpc.valor_cualitativo, vpc.icono
                    FROM calificaciones c
                    INNER JOIN valores_parametros_calificaciones vpc ON c.id_valor_parametro_calificacion = vpc.id
                    WHERE c.id_tarea_x_sprint IN ($placeholders)
                    AND c.id_tenant = ?
                ";
                $stmtCal = $db->prepare($sqlCal);
                foreach ($idsTareas as $idx => $idT) {
                    $stmtCal->bindValue($idx + 1, $idT);
                }
                $stmtCal->bindValue(count($idsTareas) + 1, TenantContext::id(), PDO::PARAM_INT);
                $stmtCal->execute();
                $calRows = $stmtCal->fetchAll(PDO::FETCH_ASSOC);

                foreach ($calRows as $cal) {
                    $idTarea = $cal['id_tarea_x_sprint'];
                    if (!isset($calificacionesPorTarea[$idTarea])) {
                        $calificacionesPorTarea[$idTarea] = [];
                    }
                    $calificacionesPorTarea[$idTarea][] = $cal;
                }
            }

            // Parámetros de calificación con sus valores
            $stmtParams = $db->prepare("
                SELECT pc.id, pc.nombre, vpc.id as id_valor, vpc.valor_cuantitativo, vpc.valor_cualitativo, vpc.icono
                FROM parametros_calificaciones pc
                INNER JOIN valores_parametros_calificaciones vpc ON pc.id = vpc.id_parametros_calificaciones
                WHERE pc.id_tenant = :id_tenant
                ORDER BY pc.id, vpc.valor_cuantitativo
            ");
            $stmtParams->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtParams->execute();
            $paramRows = $stmtParams->fetchAll(PDO::FETCH_ASSOC);

            $parametros = [];
            foreach ($paramRows as $pr) {
                $pid = $pr['id'];
                if (!isset($parametros[$pid])) {
                    $parametros[$pid] = [
                        'id' => $pid,
                        'nombre' => $pr['nombre'],
                        'valores' => []
                    ];
                }
                $parametros[$pid]['valores'][] = [
                    'id' => $pr['id_valor'],
                    'valor_cuantitativo' => (int)$pr['valor_cuantitativo'],
                    'valor_cualitativo' => $pr['valor_cualitativo'],
                    'icono' => $pr['icono']
                ];
            }

            Flight::json([
                'registros' => $registros,
                'calificaciones_por_tarea' => $calificacionesPorTarea,
                'parametros' => array_values($parametros),
                'estadisticas' => [
                    'total' => $total,
                    'ejecutadas' => $ejecutadas,
                    'pendientes' => $pendientes,
                    'adicionales' => $adicionales,
                    'con_tardanza' => $conTardanza,
                    'cambio_dispositivo' => $cambioDispositivo
                ]
            ]);
        } catch (Exception $e) {
            error_log("Error en getReporteEjecucionTareas: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener reporte: ' . $e->getMessage()], 500);
        }
    }
}