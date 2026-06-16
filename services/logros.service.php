<?php
class Logros
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT
            l.*,
            gr.nombre AS nombre_grado,
            aa.nombre AS nombre_area_academica,
            ejc.nombre AS nombre_eje_curricular,
            esd.nombre AS nombre_esfera_desarrollo,
            ccg.nombre AS nombre_competencia_cognitiva,
            estb.nombre AS nombre_estandar_basico,
            cac.nombre AS nombre_corte_academico
        FROM logros l
        LEFT JOIN grados gr
            ON l.id_grado = gr.id
        LEFT JOIN areas_academicas aa
            ON l.id_area_academica = aa.id
        LEFT JOIN ejes_curriculares ejc
            ON l.id_eje_curricular = ejc.id
        LEFT JOIN esferas_desarrollo esd
            ON l.id_esfera_desarrollo = esd.id
        LEFT JOIN competencias_cognitivas ccg
            ON l.id_competencia_cognitiva = ccg.id
        LEFT JOIN estandares_basicos estb
            ON l.id_estandar_basico = estb.id
        LEFT JOIN cortes_academicos cac
            ON l.id_corte_academico = cac.id
        ORDER BY l.id DESC
    ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }


    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM logros WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            // Obtener datos de la solicitud
            $id_grado = Flight::request()->data['id_grado'];
            $id_area_academica = Flight::request()->data['id_area_academica'];
            $id_esfera_desarrollo = Flight::request()->data['id_esfera_desarrollo'];
            $id_eje_curricular = Flight::request()->data['id_eje_curricular'];
            $id_competencia_cognitiva = Flight::request()->data['id_competencia_cognitiva'];
            $id_estandar_basico = Flight::request()->data['id_estandar_basico'];
            $id_corte_academico = Flight::request()->data['id_corte_academico'];
            $nombre = Flight::request()->data['nombre'];

            // Log para depuración
            error_log("Datos recibidos para crear logro: grado=$id_grado, area=$id_area_academica, nombre=$nombre");

            $sentence = $db->prepare("INSERT INTO logros(
                id_grado, id_area_academica, id_esfera_desarrollo, 
                id_eje_curricular, id_competencia_cognitiva, id_estandar_basico, 
                id_corte_academico, nombre
            ) VALUES (
                :id_grado, :id_area_academica, :id_esfera_desarrollo,
                :id_eje_curricular, :id_competencia_cognitiva, :id_estandar_basico,
                :id_corte_academico, :nombre
            )");

            // Vincular parámetros
            $sentence->bindParam(':id_grado', $id_grado);
            $sentence->bindParam(':id_area_academica', $id_area_academica);
            $sentence->bindParam(':id_esfera_desarrollo', $id_esfera_desarrollo);
            $sentence->bindParam(':id_eje_curricular', $id_eje_curricular);
            $sentence->bindParam(':id_competencia_cognitiva', $id_competencia_cognitiva);
            $sentence->bindParam(':id_estandar_basico', $id_estandar_basico);
            $sentence->bindParam(':id_corte_academico', $id_corte_academico);
            $sentence->bindParam(':nombre', $nombre);

            // Ejecutar
            $sentence->execute();

            // Obtener ID insertado
            $id = $db->lastInsertId();

            error_log("Logro creado con ID: $id");

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al crear logro: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear el logro'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();

            // Obtener datos
            $id = Flight::request()->data['id'];
            $id_grado = Flight::request()->data['id_grado'];
            $id_area_academica = Flight::request()->data['id_area_academica'];
            $id_esfera_desarrollo = Flight::request()->data['id_esfera_desarrollo'];
            $id_eje_curricular = Flight::request()->data['id_eje_curricular'];
            $id_competencia_cognitiva = Flight::request()->data['id_competencia_cognitiva'];
            $id_estandar_basico = Flight::request()->data['id_estandar_basico'];
            $id_corte_academico = Flight::request()->data['id_corte_academico'];
            $nombre = Flight::request()->data['nombre'];

            error_log("Actualizando logro ID: $id");

            // Validar datos requeridos
            if (!$id || !$id_grado || !$id_area_academica || !$nombre) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            // Verificar que el registro exista antes de actualizar
            $check = $db->prepare("SELECT id FROM logros WHERE id = :id");
            $check->bindParam(':id', $id);
            $check->execute();
            if ($check->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el logro con el ID especificado'), 404);
                return;
            }

            $sentence = $db->prepare("UPDATE logros SET 
                id_grado = :id_grado,
                id_area_academica = :id_area_academica,
                id_esfera_desarrollo = :id_esfera_desarrollo,
                id_eje_curricular = :id_eje_curricular,
                id_competencia_cognitiva = :id_competencia_cognitiva,
                id_estandar_basico = :id_estandar_basico,
                id_corte_academico = :id_corte_academico,
                nombre = :nombre
                WHERE id = :id");

            // Vincular parámetros
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_grado', $id_grado);
            $sentence->bindParam(':id_area_academica', $id_area_academica);
            $sentence->bindParam(':id_esfera_desarrollo', $id_esfera_desarrollo);
            $sentence->bindParam(':id_eje_curricular', $id_eje_curricular);
            $sentence->bindParam(':id_competencia_cognitiva', $id_competencia_cognitiva);
            $sentence->bindParam(':id_estandar_basico', $id_estandar_basico);
            $sentence->bindParam(':id_corte_academico', $id_corte_academico);
            $sentence->bindParam(':nombre', $nombre);

            // Ejecutar
            $sentence->execute();

            error_log("Logro actualizado: $id");

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al actualizar logro: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar el logro'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            error_log("Eliminando logro ID: $id");

            $sentence = $db->prepare("DELETE FROM logros WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al eliminar logro: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar el logro'), 500);
        }
    }

    public static function getIndicadoresLogrosByLogro($id_logro)
    {
        error_log("Obteniendo indicadores de logro para logro ID: $id_logro");

        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, nombre as descripcion
                                  FROM indicadores_logros
                                  WHERE id_logro = :id_logro
                                  ORDER BY id");

        $sentence->bindParam(':id_logro', $id_logro);
        $sentence->execute();

        $response = $sentence->fetchAll();

        error_log("Indicadores encontrados: " . count($response));

        Flight::json($response);
    }

    // Métodos adicionales para filtrar por diferentes criterios
    public static function getByGrupo($id_grupo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT l.*, g.nombre as nombre_grupo, aa.nombre as nombre_area_academica
                                  FROM logros l
                                  LEFT JOIN grupos g ON l.id_grupo = g.id
                                  LEFT JOIN areas_academicas aa ON l.id_area_academica = aa.id
                                  WHERE l.id_grupo = :id_grupo
                                  ORDER BY l.orden");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByAreaAcademica($id_area_academica)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT l.*, gr.nombre as nombre_grado, aa.nombre as nombre_area_academica
                                  FROM logros l
                                  LEFT JOIN grados gr ON l.id_grado = gr.id
                                  LEFT JOIN areas_academicas aa ON l.id_area_academica = aa.id
                                  WHERE l.id_area_academica = :id_area_academica
                                  ORDER BY l.orden");
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByGrupoAndArea($id_grupo, $id_area_academica)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT l.*, g.nombre as nombre_grupo, aa.nombre as nombre_area_academica
                                  FROM logros l
                                  LEFT JOIN grupos g ON l.id_grupo = g.id
                                  LEFT JOIN areas_academicas aa ON l.id_area_academica = aa.id
                                  WHERE l.id_grupo = :id_grupo 
                                  AND l.id_area_academica = :id_area_academica
                                  ORDER BY l.orden");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Logros con indicadores agrupados para un grupo y área.
     * Pasa por grados_x_grupo para la relación grupo→grado→logro.
     */
    public static function getByGrupoAreaConIndicadores($id_grupo, $id_area_academica)
    {
        $db = Flight::db();

        $sentence = $db->prepare("
            SELECT 
                l.id AS logro_id,
                l.nombre AS logro_nombre,
                ed.nombre AS esfera_nombre,
                il.id AS indicador_id,
                il.nombre AS indicador_nombre
            FROM logros l
            INNER JOIN indicadores_logros il ON l.id = il.id_logro
            INNER JOIN grados_x_grupo gxg ON l.id_grado = gxg.id_grado
            LEFT JOIN esferas_desarrollo ed ON l.id_esfera_desarrollo = ed.id
            WHERE gxg.id_grupo = :id_grupo
            AND l.id_area_academica = :id_area_academica
            ORDER BY l.nombre, il.nombre
        ");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->execute();
        $rows = $sentence->fetchAll(PDO::FETCH_ASSOC);

        $logrosAgrupados = [];
        foreach ($rows as $row) {
            $lid = $row['logro_id'];
            if (!isset($logrosAgrupados[$lid])) {
                $logrosAgrupados[$lid] = [
                    'id' => (int)$lid,
                    'nombre' => $row['logro_nombre'],
                    'esfera' => $row['esfera_nombre'],
                    'indicadores' => []
                ];
            }
            $logrosAgrupados[$lid]['indicadores'][] = [
                'id' => (int)$row['indicador_id'],
                'nombre' => $row['indicador_nombre']
            ];
        }

        Flight::json(array_values($logrosAgrupados));
    }

    public static function getByCorteAcademico($id_corte_academico)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT 
            l.id,
            l.nombre,
            l.descripcion,
            l.id_grado,
            l.id_area_academica,
            l.id_esfera_desarrollo,
            l.id_corte_academico,
            gr.nombre as nombre_grado,
            aa.nombre as nombre_area,
            ed.nombre as nombre_esfera,
            GROUP_CONCAT(il.id) as ids_indicadores,
            GROUP_CONCAT(il.nombre SEPARATOR '|') as nombres_indicadores
        FROM logros l
        LEFT JOIN grados gr ON l.id_grado = gr.id
        LEFT JOIN areas_academicas aa ON l.id_area_academica = aa.id
        LEFT JOIN esferas_desarrollo ed ON l.id_esfera_desarrollo = ed.id
        LEFT JOIN indicadores_logros il ON l.id = il.id_logro
        WHERE l.id_corte_academico = :id_corte_academico
        GROUP BY l.id
        ORDER BY l.nombre
    ");
        $sentence->bindParam(':id_corte_academico', $id_corte_academico);
        $sentence->execute();
        $response = $sentence->fetchAll();

        // Procesar los indicadores
        foreach ($response as &$logro) {
            if ($logro['ids_indicadores']) {
                $ids = explode(',', $logro['ids_indicadores']);
                $nombres = explode('|', $logro['nombres_indicadores']);
                $logro['indicadores'] = [];
                for ($i = 0; $i < count($ids); $i++) {
                    $logro['indicadores'][] = [
                        'id' => $ids[$i],
                        'nombre' => $nombres[$i] ?? ''
                    ];
                }
            } else {
                $logro['indicadores'] = [];
            }
            unset($logro['ids_indicadores']);
            unset($logro['nombres_indicadores']);
        }

        Flight::json($response);
    }

    /**
     * Obtiene el análisis de logros para un sprint específico
     * Muestra TODOS los logros del corte con el conteo de actividades en ese sprint
     */
    public static function getAnalisisLogrosParaSprint($id_sprint)
    {
        $db = Flight::db();

        try {
            // Primero obtener la información del sprint
            $sprintQuery = $db->prepare("SELECT id_corte_academico FROM sprints WHERE id = :id_sprint");
            $sprintQuery->bindParam(':id_sprint', $id_sprint);
            $sprintQuery->execute();
            $sprint = $sprintQuery->fetch();

            if (!$sprint) {
                Flight::json(['error' => 'Sprint no encontrado'], 404);
                return;
            }

            $id_corte = $sprint['id_corte_academico'];

            // Query principal con GROUP BY mejorado
            $sql = "
        SELECT 
            MIN(l.id) as id,
            l.nombre,
            MIN(l.id_grado) as id_grado,
            MIN(l.id_area_academica) as id_area_academica,
            MIN(gr.nombre) as nombre_grado,
            MIN(aa.nombre) as nombre_area,
            MIN(ed.nombre) as nombre_esfera,
            COALESCE(SUM(DISTINCT actividades_sprint.total_actividades), 0) as cantidad_actividades,
            GROUP_CONCAT(DISTINCT actividades_sprint.actividades_ids) as actividades_ids
        FROM logros l
        LEFT JOIN grados gr ON l.id_grado = gr.id
        LEFT JOIN areas_academicas aa ON l.id_area_academica = aa.id
        LEFT JOIN esferas_desarrollo ed ON l.id_esfera_desarrollo = ed.id
        LEFT JOIN (
            SELECT 
                il.id_logro,
                l2.nombre as nombre_logro,
                COUNT(DISTINCT txs.id_actividad_academica) as total_actividades,
                GROUP_CONCAT(DISTINCT txs.id_actividad_academica) as actividades_ids
            FROM tareas_x_sprints txs
            INNER JOIN actividades_academicas_x_indicadores_logros aaxil 
                ON txs.id_actividad_academica = aaxil.id_actividad_academica
            INNER JOIN indicadores_logros il 
                ON aaxil.id_indicador_logro = il.id
            INNER JOIN logros l2 ON il.id_logro = l2.id
            WHERE txs.id_sprint = :id_sprint
            GROUP BY l2.nombre, il.id_logro
        ) actividades_sprint ON l.nombre = actividades_sprint.nombre_logro
        WHERE l.id_corte_academico = :id_corte
        GROUP BY l.nombre
        ORDER BY l.nombre
        ";

            $sentence = $db->prepare($sql);
            $sentence->bindParam(':id_sprint', $id_sprint);
            $sentence->bindParam(':id_corte', $id_corte);
            $sentence->execute();

            $logros = $sentence->fetchAll();

            // Formatear los IDs de actividades como array
            foreach ($logros as &$logro) {
                if ($logro['actividades_ids']) {
                    $logro['actividades_ids'] = explode(',', $logro['actividades_ids']);
                } else {
                    $logro['actividades_ids'] = [];
                }
            }

            // Agregar metadata del análisis
            $totalLogros = count($logros);
            $logrosAtendidos = array_filter($logros, function ($l) {
                return $l['cantidad_actividades'] > 0;
            });

            Flight::json([
                'sprint_id' => $id_sprint,
                'corte_id' => $id_corte,
                'total_logros' => $totalLogros,
                'logros_atendidos' => count($logrosAtendidos),
                'porcentaje_cobertura' => $totalLogros > 0 ?
                    round((count($logrosAtendidos) / $totalLogros) * 100, 2) : 0,
                'logros' => $logros
            ]);
        } catch (Exception $e) {
            error_log("Error en análisis de logros para sprint: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener análisis de logros'], 500);
        }
    }

    /**
     * Obtiene el análisis de logros para todo el corte académico
     * Cuenta todas las veces que se asigna una actividad (si aparece en 3 sprints, cuenta 3)
     */
    public static function getAnalisisLogrosParaCorte($id_corte_academico)
    {
        $db = Flight::db();

        try {
            // Query principal: obtener TODOS los logros del corte con conteo total de actividades
            $sql = "
        SELECT 
            l.id,
            l.nombre,
            l.id_grado,
            l.id_area_academica,
            gr.nombre as nombre_grado,
            aa.nombre as nombre_area,
            ed.nombre as nombre_esfera,
            COALESCE(actividades_corte.total_actividades, 0) as cantidad_actividades,
            COALESCE(actividades_corte.total_tareas, 0) as total_tareas,
            COALESCE(actividades_corte.sprints_count, 0) as sprints_involucrados,
            COALESCE(actividades_corte.actividades_unicas, 0) as actividades_unicas
        FROM logros l
        LEFT JOIN grados gr ON l.id_grado = gr.id
        LEFT JOIN areas_academicas aa ON l.id_area_academica = aa.id
        LEFT JOIN esferas_desarrollo ed ON l.id_esfera_desarrollo = ed.id
        LEFT JOIN (
            SELECT 
                il.id_logro,
                COUNT(txs.id_actividad_academica) as total_actividades,
                COUNT(DISTINCT txs.id_actividad_academica) as actividades_unicas,
                COUNT(DISTINCT txs.id) as total_tareas,
                COUNT(DISTINCT s.id) as sprints_count
            FROM tareas_x_sprints txs
            INNER JOIN sprints s ON txs.id_sprint = s.id
            INNER JOIN actividades_academicas_x_indicadores_logros aaxil 
                ON txs.id_actividad_academica = aaxil.id_actividad_academica
            INNER JOIN indicadores_logros il 
                ON aaxil.id_indicador_logro = il.id
            WHERE s.id_corte_academico = :id_corte_sub
            GROUP BY il.id_logro
        ) actividades_corte ON l.id = actividades_corte.id_logro
        WHERE l.id_corte_academico = :id_corte_main
        ORDER BY l.nombre
        ";

            $sentence = $db->prepare($sql);
            $sentence->bindParam(':id_corte_sub', $id_corte_academico);
            $sentence->bindParam(':id_corte_main', $id_corte_academico);
            $sentence->execute();

            $logros = $sentence->fetchAll();

            // Agregar metadata del análisis
            $totalLogros = count($logros);
            $logrosAtendidos = array_filter($logros, function ($l) {
                return $l['cantidad_actividades'] > 0;
            });

            // Calcular estadísticas adicionales
            $totalActividades = array_sum(array_column($logros, 'cantidad_actividades'));
            $actividadesPromedio = $totalLogros > 0 ?
                round($totalActividades / $totalLogros, 2) : 0;

            Flight::json([
                'corte_id' => $id_corte_academico,
                'total_logros' => $totalLogros,
                'logros_atendidos' => count($logrosAtendidos),
                'porcentaje_cobertura' => $totalLogros > 0 ?
                    round((count($logrosAtendidos) / $totalLogros) * 100, 2) : 0,
                'total_actividades_asignadas' => $totalActividades,
                'promedio_actividades_por_logro' => $actividadesPromedio,
                'logros' => $logros
            ]);
        } catch (Exception $e) {
            error_log("Error en análisis de logros para corte: " . $e->getMessage());
            error_log("SQL Error: " . $e->getTraceAsString());
            Flight::json(['error' => 'Error al obtener análisis de logros: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Obtiene el detalle de actividades para un logro específico en un sprint
     */
    public static function getActividadesDeLogroEnSprint($id_logro, $id_sprint)
    {
        $db = Flight::db();

        try {
            $sql = "
        SELECT DISTINCT
            aa.id,
            aa.titulo,
            aa.minutos_duracion,
            ta.nombre as tipo_actividad,
            txs.id_estado_tarea,
            et.nombre as estado_tarea,
            txs.fecha_ejecucion
        FROM actividades_academicas aa
        INNER JOIN tareas_x_sprints txs ON aa.id = txs.id_actividad_academica
        INNER JOIN actividades_academicas_x_indicadores_logros aaxil ON aa.id = aaxil.id_actividad_academica
        INNER JOIN indicadores_logros il ON aaxil.id_indicador_logro = il.id
        LEFT JOIN tipos_actividades_academicas ta ON aa.id_tipo_actividad_academica = ta.id
        LEFT JOIN estados_tareas et ON txs.id_estado_tarea = et.id
        WHERE il.id_logro = :id_logro 
        AND txs.id_sprint = :id_sprint
        ORDER BY aa.titulo
        ";

            $sentence = $db->prepare($sql);
            $sentence->bindParam(':id_logro', $id_logro);
            $sentence->bindParam(':id_sprint', $id_sprint);
            $sentence->execute();

            $actividades = $sentence->fetchAll();

            Flight::json([
                'logro_id' => $id_logro,
                'sprint_id' => $id_sprint,
                'total_actividades' => count($actividades),
                'actividades' => $actividades
            ]);
        } catch (Exception $e) {
            error_log("Error al obtener actividades del logro: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener actividades'], 500);
        }
    }

    /**
     * Obtiene el análisis de atención por áreas para un sprint
     */
    public static function getAnalisisAreasPorSprint($id_sprint)
    {
        $db = Flight::db();

        try {
            // Primero obtener la información del sprint
            $sprintQuery = $db->prepare("SELECT id_corte_academico FROM sprints WHERE id = :id_sprint");
            $sprintQuery->bindParam(':id_sprint', $id_sprint);
            $sprintQuery->execute();
            $sprint = $sprintQuery->fetch();

            if (!$sprint) {
                Flight::json(['error' => 'Sprint no encontrado'], 404);
                return;
            }

            $id_corte = $sprint['id_corte_academico'];

            // Query para agrupar por áreas
            $sql = "
        SELECT 
            aa.id as id_area,
            aa.nombre as nombre_area,
            COUNT(DISTINCT l.id) as total_logros,
            COUNT(DISTINCT CASE WHEN actividades_sprint.total_actividades > 0 THEN l.id END) as logros_atendidos,
            COALESCE(SUM(actividades_sprint.total_actividades), 0) as total_actividades
        FROM areas_academicas aa
        INNER JOIN logros l ON aa.id = l.id_area_academica
        LEFT JOIN (
            SELECT 
                il.id_logro,
                COUNT(DISTINCT txs.id_actividad_academica) as total_actividades
            FROM tareas_x_sprints txs
            INNER JOIN actividades_academicas_x_indicadores_logros aaxil 
                ON txs.id_actividad_academica = aaxil.id_actividad_academica
            INNER JOIN indicadores_logros il 
                ON aaxil.id_indicador_logro = il.id
            WHERE txs.id_sprint = :id_sprint_sub
            GROUP BY il.id_logro
        ) actividades_sprint ON l.id = actividades_sprint.id_logro
        WHERE l.id_corte_academico = :id_corte
        GROUP BY aa.id, aa.nombre
        ORDER BY aa.nombre
        ";

            $sentence = $db->prepare($sql);
            $sentence->bindParam(':id_sprint_sub', $id_sprint);
            $sentence->bindParam(':id_corte', $id_corte);
            $sentence->execute();

            $areas = $sentence->fetchAll();

            // Calcular totales
            $totalLogros = array_sum(array_column($areas, 'total_logros'));
            $logrosAtendidos = array_sum(array_column($areas, 'logros_atendidos'));

            Flight::json([
                'sprint_id' => $id_sprint,
                'corte_id' => $id_corte,
                'total_logros' => $totalLogros,
                'total_logros_atendidos' => $logrosAtendidos,
                'porcentaje_cobertura' => $totalLogros > 0 ?
                    round(($logrosAtendidos / $totalLogros) * 100, 2) : 0,
                'areas' => $areas
            ]);
        } catch (Exception $e) {
            error_log("Error en análisis de áreas para sprint: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener análisis de áreas'], 500);
        }
    }

    /**
     * Obtiene el análisis de atención por áreas para todo el corte
     */
    public static function getAnalisisAreasPorCorte($id_corte_academico)
    {
        $db = Flight::db();

        try {
            $sql = "
        SELECT 
            aa.id as id_area,
            aa.nombre as nombre_area,
            COUNT(DISTINCT l.id) as total_logros,
            COUNT(DISTINCT CASE WHEN actividades_corte.total_actividades > 0 THEN l.id END) as logros_atendidos,
            COALESCE(SUM(actividades_corte.total_actividades), 0) as total_actividades,
            COALESCE(SUM(actividades_corte.actividades_unicas), 0) as actividades_unicas
        FROM areas_academicas aa
        INNER JOIN logros l ON aa.id = l.id_area_academica
        LEFT JOIN (
            SELECT 
                il.id_logro,
                COUNT(txs.id_actividad_academica) as total_actividades,
                COUNT(DISTINCT txs.id_actividad_academica) as actividades_unicas
            FROM tareas_x_sprints txs
            INNER JOIN sprints s ON txs.id_sprint = s.id
            INNER JOIN actividades_academicas_x_indicadores_logros aaxil 
                ON txs.id_actividad_academica = aaxil.id_actividad_academica
            INNER JOIN indicadores_logros il 
                ON aaxil.id_indicador_logro = il.id
            WHERE s.id_corte_academico = :id_corte
            GROUP BY il.id_logro
        ) actividades_corte ON l.id = actividades_corte.id_logro
        WHERE l.id_corte_academico = :id_corte_main
        GROUP BY aa.id, aa.nombre
        ORDER BY aa.nombre
        ";

            $sentence = $db->prepare($sql);
            $sentence->bindParam(':id_corte', $id_corte_academico);
            $sentence->bindParam(':id_corte_main', $id_corte_academico);
            $sentence->execute();

            $areas = $sentence->fetchAll();

            // Calcular totales
            $totalLogros = array_sum(array_column($areas, 'total_logros'));
            $logrosAtendidos = array_sum(array_column($areas, 'logros_atendidos'));
            $totalActividades = array_sum(array_column($areas, 'total_actividades'));

            Flight::json([
                'corte_id' => $id_corte_academico,
                'total_logros' => $totalLogros,
                'total_logros_atendidos' => $logrosAtendidos,
                'porcentaje_cobertura' => $totalLogros > 0 ?
                    round(($logrosAtendidos / $totalLogros) * 100, 2) : 0,
                'total_actividades_asignadas' => $totalActividades,
                'areas' => $areas
            ]);
        } catch (Exception $e) {
            error_log("Error en análisis de áreas para corte: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener análisis de áreas'], 500);
        }
    }

    /**
     * Mapa completo de logros → indicadores → actividades para un corte y área.
     * Incluye conteo de tareas en el corte completo y en un sprint específico.
     * Un solo request para alimentar las barras de indicadores en frontend.
     */
    public static function getMapaLogrosActividades($id_corte, $id_area)
    {
        $db = Flight::db();

        try {
            $id_sprint = $_GET['id_sprint'] ?? null;
            $id_grupo = $_GET['id_grupo'] ?? null;

            $sql = "
                SELECT 
                    l.id AS id_logro,
                    l.nombre AS nombre_logro,
                    l.id_area_academica,
                    aa_cat.nombre AS nombre_area,
                    ed.nombre AS nombre_esfera,
                    il.id AS id_indicador,
                    il.nombre AS nombre_indicador,
                    aaxil.id_actividad_academica,
                    act.titulo AS titulo_actividad,
                    act.descripcion AS descripcion_actividad,
                    act.minutos_duracion
                FROM logros l
                INNER JOIN areas_academicas aa_cat ON l.id_area_academica = aa_cat.id
                LEFT JOIN esferas_desarrollo ed ON l.id_esfera_desarrollo = ed.id
                INNER JOIN indicadores_logros il ON l.id = il.id_logro
                LEFT JOIN actividades_academicas_x_indicadores_logros aaxil ON il.id = aaxil.id_indicador_logro
                LEFT JOIN actividades_academicas act ON aaxil.id_actividad_academica = act.id
                WHERE l.id_corte_academico = :id_corte
                AND l.id_area_academica = :id_area
            ";

            // Filtrar por grados del grupo si se especifica
            if ($id_grupo) {
                $sql .= " AND l.id_grado IN (SELECT gxg.id_grado FROM grados_x_grupo gxg WHERE gxg.id_grupo = :id_grupo)";
            }

            $sql .= " ORDER BY l.nombre, il.nombre, act.titulo";

            $sentence = $db->prepare($sql);
            $sentence->bindParam(':id_corte', $id_corte);
            $sentence->bindParam(':id_area', $id_area);
            if ($id_grupo) {
                $sentence->bindParam(':id_grupo', $id_grupo);
            }
            $sentence->execute();
            $rows = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // Contar tareas por actividad en el corte completo
            $tareasCorteMap = [];
            $sqlTareasCorte = "
                SELECT txs.id_actividad_academica, COUNT(*) as total
                FROM tareas_x_sprints txs
                INNER JOIN sprints s ON txs.id_sprint = s.id
                WHERE s.id_corte_academico = :id_corte
            ";
            if ($id_grupo) {
                $sqlTareasCorte .= " AND txs.id_grupo = :id_grupo";
            }
            if ($id_area) {
                $sqlTareasCorte .= " AND txs.id_area_academica = :id_area";
            }
            $sqlTareasCorte .= " GROUP BY txs.id_actividad_academica";

            $stmtCorte = $db->prepare($sqlTareasCorte);
            $stmtCorte->bindParam(':id_corte', $id_corte);
            if ($id_grupo) $stmtCorte->bindParam(':id_grupo', $id_grupo);
            if ($id_area) $stmtCorte->bindParam(':id_area', $id_area);
            $stmtCorte->execute();
            foreach ($stmtCorte->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $tareasCorteMap[$row['id_actividad_academica']] = (int)$row['total'];
            }

            // Contar tareas por actividad en el sprint específico
            $tareasSprintMap = [];
            if ($id_sprint) {
                $sqlTareasSprint = "
                    SELECT txs.id_actividad_academica, COUNT(*) as total
                    FROM tareas_x_sprints txs
                    WHERE txs.id_sprint = :id_sprint
                ";
                if ($id_grupo) $sqlTareasSprint .= " AND txs.id_grupo = :id_grupo";
                if ($id_area) $sqlTareasSprint .= " AND txs.id_area_academica = :id_area";
                $sqlTareasSprint .= " GROUP BY txs.id_actividad_academica";

                $stmtSprint = $db->prepare($sqlTareasSprint);
                $stmtSprint->bindParam(':id_sprint', $id_sprint);
                if ($id_grupo) $stmtSprint->bindParam(':id_grupo', $id_grupo);
                if ($id_area) $stmtSprint->bindParam(':id_area', $id_area);
                $stmtSprint->execute();
                foreach ($stmtSprint->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $tareasSprintMap[$row['id_actividad_academica']] = (int)$row['total'];
                }
            }

            // Estructurar respuesta agrupada por logro
            $logrosMap = [];
            foreach ($rows as $row) {
                $idLogro = $row['id_logro'];

                if (!isset($logrosMap[$idLogro])) {
                    $logrosMap[$idLogro] = [
                        'id' => (int)$idLogro,
                        'nombre' => $row['nombre_logro'],
                        'nombre_area' => $row['nombre_area'],
                        'nombre_esfera' => $row['nombre_esfera'],
                        'indicadores' => [],
                        'actividades_ids' => [],
                        'tareas_corte' => 0,
                        'tareas_sprint' => 0
                    ];
                }

                // Agregar indicador si no existe
                $idIndicador = $row['id_indicador'];
                $indicadorExists = false;
                foreach ($logrosMap[$idLogro]['indicadores'] as $ind) {
                    if ($ind['id'] == $idIndicador) { $indicadorExists = true; break; }
                }
                if (!$indicadorExists) {
                    $logrosMap[$idLogro]['indicadores'][] = [
                        'id' => (int)$idIndicador,
                        'nombre' => $row['nombre_indicador']
                    ];
                }

                // Agregar actividad vinculada si existe
                if ($row['id_actividad_academica']) {
                    $idAct = (int)$row['id_actividad_academica'];
                    if (!in_array($idAct, $logrosMap[$idLogro]['actividades_ids'])) {
                        $logrosMap[$idLogro]['actividades_ids'][] = $idAct;
                    }

                    // Sumar tareas del corte y sprint para este logro
                    $logrosMap[$idLogro]['tareas_corte'] += $tareasCorteMap[$idAct] ?? 0;
                    $logrosMap[$idLogro]['tareas_sprint'] += $tareasSprintMap[$idAct] ?? 0;
                }
            }

            $logros = array_values($logrosMap);
            $totalLogros = count($logros);
            $atendidosCorte = count(array_filter($logros, function($l) { return $l['tareas_corte'] > 0; }));
            $atendidosSprint = count(array_filter($logros, function($l) { return $l['tareas_sprint'] > 0; }));

            Flight::json([
                'id_corte' => $id_corte,
                'id_area' => $id_area,
                'id_grupo' => $id_grupo,
                'id_sprint' => $id_sprint,
                'total_logros' => $totalLogros,
                'atendidos_corte' => $atendidosCorte,
                'atendidos_sprint' => $atendidosSprint,
                'logros' => $logros
            ]);
        } catch (Exception $e) {
            error_log("Error en getMapaLogrosActividades: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener mapa de logros: ' . $e->getMessage()], 500);
        }
    }
}