<?php
class Sprints
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT s.id, s.anio, s.numero_sprint, s.nombre_sprint, 
            s.fecha_inicial, s.fecha_final, s.total_dias_habiles, 
            s.id_corte_academico, ca.nombre AS nombre_corte_academico, 
            s.actual, s.es_evaluacion
        FROM sprints s 
        LEFT OUTER JOIN cortes_academicos ca ON s.id_corte_academico = ca.id
        WHERE s.id_tenant = :id_tenant
        ORDER BY s.anio DESC, s.numero_sprint DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT s.*, ca.nombre AS nombre_corte_academico
        FROM sprints s
        LEFT OUTER JOIN cortes_academicos ca ON s.id_corte_academico = ca.id
        WHERE s.id = :id AND s.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $anio = Flight::request()->data['anio'];
            $numero_sprint = Flight::request()->data['numero_sprint'];
            $nombre_sprint = Flight::request()->data['nombre_sprint'];
            $fecha_inicial = Flight::request()->data['fecha_inicial'];
            $fecha_final = Flight::request()->data['fecha_final'];
            $total_dias_habiles = Flight::request()->data['total_dias_habiles'];
            $id_corte_academico = Flight::request()->data['id_corte_academico'];
            $actual = Flight::request()->data['actual'] ? 1 : 0;
            $es_evaluacion = Flight::request()->data['es_evaluacion'] ? 1 : 0;

            error_log("Datos recibidos para crear sprint: anio=$anio, numero=$numero_sprint, nombre=$nombre_sprint");

            if ($actual) {
                $updateSentence = $db->prepare("UPDATE sprints SET actual = 0 WHERE actual = 1 AND id_tenant = :id_tenant");
                $updateSentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $updateSentence->execute();
                error_log("Sprints actuales desmarcados");
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO sprints(
                id, id_tenant,
                anio, numero_sprint, nombre_sprint, fecha_inicial, fecha_final,
                total_dias_habiles, id_corte_academico, actual, es_evaluacion
            ) VALUES (
                :id, :id_tenant,
                :anio, :numero_sprint, :nombre_sprint, :fecha_inicial, :fecha_final,
                :total_dias_habiles, :id_corte_academico, :actual, :es_evaluacion
            )");

            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':anio', $anio);
            $sentence->bindParam(':numero_sprint', $numero_sprint);
            $sentence->bindParam(':nombre_sprint', $nombre_sprint);
            $sentence->bindParam(':fecha_inicial', $fecha_inicial);
            $sentence->bindParam(':fecha_final', $fecha_final);
            $sentence->bindParam(':total_dias_habiles', $total_dias_habiles);
            $sentence->bindParam(':id_corte_academico', $id_corte_academico);
            $sentence->bindParam(':actual', $actual);
            $sentence->bindParam(':es_evaluacion', $es_evaluacion);

            $sentence->execute();

            $id = $idNew;
            error_log("Sprint creado con ID: $id");

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al crear sprint: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear el sprint'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();

            $id = Flight::request()->data['id'];
            $anio = Flight::request()->data['anio'];
            $numero_sprint = Flight::request()->data['numero_sprint'];
            $nombre_sprint = Flight::request()->data['nombre_sprint'];
            $fecha_inicial = Flight::request()->data['fecha_inicial'];
            $fecha_final = Flight::request()->data['fecha_final'];
            $total_dias_habiles = Flight::request()->data['total_dias_habiles'];
            $id_corte_academico = Flight::request()->data['id_corte_academico'];
            $actual = Flight::request()->data['actual'] ? 1 : 0;
            $es_evaluacion = Flight::request()->data['es_evaluacion'] ? 1 : 0;

            error_log("Actualizando sprint ID: $id");

            if (!$id || !$anio || !$numero_sprint || !$nombre_sprint || !$fecha_inicial || !$fecha_final || !$id_corte_academico) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            if ($actual) {
                $updateSentence = $db->prepare("UPDATE sprints SET actual = 0 WHERE actual = 1 AND id != :id AND id_tenant = :id_tenant");
                $updateSentence->bindParam(':id', $id);
                $updateSentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $updateSentence->execute();
                error_log("Otros sprints actuales desmarcados");
            }

            $sentence = $db->prepare("UPDATE sprints SET 
                anio = :anio,
                numero_sprint = :numero_sprint,
                nombre_sprint = :nombre_sprint,
                fecha_inicial = :fecha_inicial,
                fecha_final = :fecha_final,
                total_dias_habiles = :total_dias_habiles,
                id_corte_academico = :id_corte_academico,
                actual = :actual,
                es_evaluacion = :es_evaluacion
                WHERE id = :id AND id_tenant = :id_tenant");

            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':anio', $anio);
            $sentence->bindParam(':numero_sprint', $numero_sprint);
            $sentence->bindParam(':nombre_sprint', $nombre_sprint);
            $sentence->bindParam(':fecha_inicial', $fecha_inicial);
            $sentence->bindParam(':fecha_final', $fecha_final);
            $sentence->bindParam(':total_dias_habiles', $total_dias_habiles);
            $sentence->bindParam(':id_corte_academico', $id_corte_academico);
            $sentence->bindParam(':actual', $actual);
            $sentence->bindParam(':es_evaluacion', $es_evaluacion);

            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el sprint con el ID especificado'), 404);
                return;
            }

            error_log("Sprint actualizado: $id");

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al actualizar sprint: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar el sprint'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            error_log("Eliminando sprint ID: $id");

            $checkSentence = $db->prepare("SELECT COUNT(*) as total FROM tareas_x_sprints WHERE id_sprint = :id AND id_tenant = :id_tenant");
            $checkSentence->bindParam(':id', $id);
            $checkSentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkSentence->execute();
            $result = $checkSentence->fetch();

            if ($result['total'] > 0) {
                Flight::json(array('error' => 'No se puede eliminar el sprint porque tiene tareas asociadas'), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM sprints WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el sprint con el ID especificado'), 404);
                return;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al eliminar sprint: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar el sprint'), 500);
        }
    }

    public static function getActual()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT s.*, ca.nombre AS nombre_corte_academico
        FROM sprints s
        LEFT OUTER JOIN cortes_academicos ca ON s.id_corte_academico = ca.id
        WHERE s.actual = 1 AND s.id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByAnio($anio)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT s.*, ca.nombre AS nombre_corte_academico
        FROM sprints s
        LEFT OUTER JOIN cortes_academicos ca ON s.id_corte_academico = ca.id
        WHERE s.anio = :anio AND s.id_tenant = :id_tenant
        ORDER BY s.numero_sprint");
        $sentence->bindParam(':anio', $anio);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Retorna el sprint actual y los sprints anteriores del mismo año institucional,
    // ordenados del más reciente al más antiguo
    public static function getActualYAnteriores()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT s.*, ca.nombre AS nombre_corte_academico
        FROM sprints s
        LEFT OUTER JOIN cortes_academicos ca ON s.id_corte_academico = ca.id
        WHERE s.anio = (SELECT anio FROM sprints WHERE actual = 1 AND id_tenant = :id_tenant_a LIMIT 1)
          AND s.numero_sprint <= (SELECT numero_sprint FROM sprints WHERE actual = 1 AND id_tenant = :id_tenant_n LIMIT 1)
          AND s.id_tenant = :id_tenant
        ORDER BY s.numero_sprint DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant_a', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant_n', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByCorteAcademico($id_corte_academico)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT s.*, ca.nombre AS nombre_corte_academico
        FROM sprints s
        LEFT OUTER JOIN cortes_academicos ca ON s.id_corte_academico = ca.id
        WHERE s.id_corte_academico = :id_corte_academico AND s.id_tenant = :id_tenant
        ORDER BY s.anio DESC, s.numero_sprint DESC");
        $sentence->bindParam(':id_corte_academico', $id_corte_academico);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getEvaluaciones()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT s.*, ca.nombre AS nombre_corte_academico
        FROM sprints s
        LEFT OUTER JOIN cortes_academicos ca ON s.id_corte_academico = ca.id
        WHERE s.es_evaluacion = 1 AND s.id_tenant = :id_tenant
        ORDER BY s.anio DESC, s.numero_sprint DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getObtenerTodoConEstadisticas()
    {
        $db = Flight::db();

        $sentenceActual = $db->prepare("SELECT id, fecha_inicial FROM sprints WHERE actual = 1 AND id_tenant = :id_tenant LIMIT 1");
        $sentenceActual->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentenceActual->execute();
        $sprintActual = $sentenceActual->fetch();

        $fechaInicialActual = $sprintActual ? $sprintActual['fecha_inicial'] : null;

        $sql = "
        SELECT 
            s.id,
            s.anio,
            s.numero_sprint,
            s.nombre_sprint,
            s.fecha_inicial,
            s.fecha_final,
            s.total_dias_habiles,
            s.id_corte_academico,
            s.actual,
            s.es_evaluacion,
            ca.nombre AS nombre_corte_academico,
            COALESCE(COUNT(txs.id), 0) as total_tareas,
            COALESCE(SUM(CASE WHEN txs.id_estado_tarea = 2 THEN 1 ELSE 0 END), 0) as tareas_ejecutadas,
            COALESCE(SUM(CASE WHEN txs.id_estado_tarea = 1 THEN 1 ELSE 0 END), 0) as tareas_pendientes,
            COALESCE(SUM(CASE WHEN txs.id_estado_tarea = 3 THEN 1 ELSE 0 END), 0) as tareas_canceladas
        FROM sprints s 
        LEFT OUTER JOIN cortes_academicos ca ON s.id_corte_academico = ca.id
        LEFT JOIN tareas_x_sprints txs ON s.id = txs.id_sprint
        WHERE s.id_tenant = :id_tenant
        GROUP BY s.id, s.anio, s.numero_sprint, s.nombre_sprint, s.fecha_inicial, 
                 s.fecha_final, s.total_dias_habiles, s.id_corte_academico, 
                 s.actual, s.es_evaluacion, ca.nombre
        ORDER BY s.anio DESC, s.numero_sprint DESC
    ";

        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$row) {
            $row['id'] = (string) $row['id'];
            $row['anio'] = (int) $row['anio'];
            $row['numero_sprint'] = (int) $row['numero_sprint'];
            $row['total_dias_habiles'] = (int) $row['total_dias_habiles'];
            $row['id_corte_academico'] = (string) $row['id_corte_academico'];
            $row['actual'] = (int) $row['actual'];
            $row['es_evaluacion'] = (int) $row['es_evaluacion'];
            $row['total_tareas'] = (int) $row['total_tareas'];
            $row['tareas_ejecutadas'] = (int) $row['tareas_ejecutadas'];
            $row['tareas_pendientes'] = (int) $row['tareas_pendientes'];
            $row['tareas_canceladas'] = (int) $row['tareas_canceladas'];

            if ($row['total_tareas'] > 0) {
                $row['porcentaje_completado'] = round(($row['tareas_ejecutadas'] / $row['total_tareas']) * 100, 2);
            } else {
                $row['porcentaje_completado'] = 0;
            }

            if ($row['actual'] == 1) {
                $row['estado_sprint'] = 'En ejecución';
                $row['estado_clase'] = 'badge-actual';
            } else if ($fechaInicialActual && $row['fecha_inicial'] < $fechaInicialActual) {
                $row['estado_sprint'] = 'Ejecutado';
                $row['estado_clase'] = 'badge-success';
            } else {
                $row['estado_sprint'] = 'Pendiente';
                $row['estado_clase'] = 'badge-warning';
            }

            if ($row['porcentaje_completado'] >= 80) {
                $row['progreso_clase'] = 'bg-success';
            } else if ($row['porcentaje_completado'] >= 50) {
                $row['progreso_clase'] = 'bg-warning';
            } else if ($row['porcentaje_completado'] > 0) {
                $row['progreso_clase'] = 'bg-danger';
            } else {
                $row['progreso_clase'] = 'bg-secondary';
            }
        }

        Flight::json($response);
    }

    public static function verificarSolapamiento()
    {
        $db = Flight::db();

        $fecha_inicial = $_GET['fecha_inicial'] ?? null;
        $fecha_final = $_GET['fecha_final'] ?? null;
        $id_excluir = $_GET['id_excluir'] ?? null;

        if (!$fecha_inicial || !$fecha_final) {
            Flight::json(['error' => 'Fechas requeridas'], 400);
            return;
        }

        $sql = "SELECT id, nombre_sprint, fecha_inicial, fecha_final 
                FROM sprints 
                WHERE ((fecha_inicial <= :fecha_final AND fecha_final >= :fecha_inicial))
                AND id_tenant = :id_tenant";

        $params = [
            ':fecha_inicial' => $fecha_inicial,
            ':fecha_final' => $fecha_final,
            ':id_tenant' => TenantContext::id()
        ];

        if ($id_excluir) {
            $sql .= " AND id != :id_excluir";
            $params[':id_excluir'] = $id_excluir;
        }

        try {
            $sentence = $db->prepare($sql);
            $sentence->execute($params);
            $sprints = $sentence->fetchAll();

            Flight::json($sprints);
        } catch (PDOException $e) {
            error_log("Error verificando solapamiento: " . $e->getMessage());
            Flight::json(['error' => 'Error al verificar solapamiento'], 500);
        }
    }

    public static function verificarNumeroUnico()
    {
        $db = Flight::db();

        $anio = $_GET['anio'] ?? null;
        $numero_sprint = $_GET['numero_sprint'] ?? null;
        $id_excluir = $_GET['id_excluir'] ?? null;

        if (!$anio || !$numero_sprint) {
            Flight::json(['error' => 'Año y número de sprint requeridos'], 400);
            return;
        }

        $sql = "SELECT COUNT(*) as total 
                FROM sprints 
                WHERE anio = :anio AND numero_sprint = :numero_sprint AND id_tenant = :id_tenant";

        $params = [
            ':anio' => $anio,
            ':numero_sprint' => $numero_sprint,
            ':id_tenant' => TenantContext::id()
        ];

        if ($id_excluir) {
            $sql .= " AND id != :id_excluir";
            $params[':id_excluir'] = $id_excluir;
        }

        try {
            $sentence = $db->prepare($sql);
            $sentence->execute($params);
            $result = $sentence->fetch();

            Flight::json(['existe' => $result['total'] > 0]);
        } catch (PDOException $e) {
            error_log("Error verificando número único: " . $e->getMessage());
            Flight::json(['error' => 'Error al verificar número único'], 500);
        }
    }

    public static function verificarSprintEvaluacion()
    {
        $db = Flight::db();

        $id_corte_academico = $_GET['id_corte_academico'] ?? null;
        $id_excluir = $_GET['id_excluir'] ?? null;

        if (!$id_corte_academico) {
            Flight::json(['error' => 'Corte académico requerido'], 400);
            return;
        }

        $sql = "SELECT COUNT(*) as total 
                FROM sprints 
                WHERE id_corte_academico = :id_corte_academico 
                AND es_evaluacion = 1
                AND id_tenant = :id_tenant";

        $params = [':id_corte_academico' => $id_corte_academico, ':id_tenant' => TenantContext::id()];

        if ($id_excluir) {
            $sql .= " AND id != :id_excluir";
            $params[':id_excluir'] = $id_excluir;
        }

        try {
            $sentence = $db->prepare($sql);
            $sentence->execute($params);
            $result = $sentence->fetch();

            Flight::json(['existe' => $result['total'] > 0]);
        } catch (PDOException $e) {
            error_log("Error verificando sprint evaluación: " . $e->getMessage());
            Flight::json(['error' => 'Error al verificar sprint evaluación'], 500);
        }
    }

    public static function getSprintsPorAnio($anio)
    {
        $db = Flight::db();

        $sql = "SELECT * FROM sprints WHERE anio = :anio AND id_tenant = :id_tenant ORDER BY numero_sprint";

        try {
            $sentence = $db->prepare($sql);
            $sentence->execute([':anio' => $anio, ':id_tenant' => TenantContext::id()]);
            $sprints = $sentence->fetchAll();

            Flight::json($sprints);
        } catch (PDOException $e) {
            error_log("Error obteniendo sprints por año: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener sprints por año'], 500);
        }
    }

    public static function desactivarSprintsActuales()
    {
        $db = Flight::db();

        $input = json_decode(Flight::request()->getBody(), true);
        $id_excluir = $input['id_excluir'] ?? null;

        $sql = "UPDATE sprints SET actual = 0 WHERE actual = 1 AND id_tenant = :id_tenant";
        $params = [':id_tenant' => TenantContext::id()];

        if ($id_excluir) {
            $sql .= " AND id != :id_excluir";
            $params[':id_excluir'] = $id_excluir;
        }

        try {
            $sentence = $db->prepare($sql);
            $sentence->execute($params);
            $count = $sentence->rowCount();

            error_log("Sprints actuales desactivados: $count");

            Flight::json(['actualizados' => $count]);
        } catch (PDOException $e) {
            error_log("Error desactivando sprints actuales: " . $e->getMessage());
            Flight::json(['error' => 'Error al desactivar sprints actuales'], 500);
        }
    }

    /**
     * Análisis de tiempo disponible vs usado para un sprint.
     * Ahora usa tareas_x_sprints.id_grupo directamente.
     */
    public static function getAnalisisTiempoSprint($id_sprint)
    {
        $db = Flight::db();

        try {
            $sprintQuery = $db->prepare("
                SELECT s.*, ca.nombre as nombre_corte 
                FROM sprints s
                LEFT JOIN cortes_academicos ca ON s.id_corte_academico = ca.id
                WHERE s.id = :id_sprint AND s.id_tenant = :id_tenant
            ");
            $sprintQuery->bindParam(':id_sprint', $id_sprint);
            $sprintQuery->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sprintQuery->execute();
            $sprint = $sprintQuery->fetch();

            if (!$sprint) {
                Flight::json(['error' => 'Sprint no encontrado'], 404);
                return;
            }

            $diasQuery = $db->prepare("
                SELECT id_dia_semana, total_dias
                FROM dias_x_sprint
                WHERE id_sprint = :id_sprint AND id_tenant = :id_tenant
            ");
            $diasQuery->bindParam(':id_sprint', $id_sprint);
            $diasQuery->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $diasQuery->execute();
            $diasSprint = $diasQuery->fetchAll();

            $diasMap = [];
            foreach ($diasSprint as $dia) {
                $diasMap[$dia['id_dia_semana']] = $dia['total_dias'];
            }

            // Análisis por grupo y área usando horarios (tabla horarios tiene id_grupo)
            // y tareas_x_sprints.id_grupo directo para el tiempo usado
            $sql = "
                SELECT 
                    g.id as id_grupo,
                    g.nombre as nombre_grupo,
                    aa.id as id_area,
                    aa.nombre as nombre_area,
                    -- Tiempo disponible según horarios
                    COALESCE((
                        SELECT SUM(h.total_minutos * COALESCE(dxs.total_dias, 0))
                        FROM horarios h
                        LEFT JOIN dias_x_sprint dxs ON h.id_dia_semana = dxs.id_dia_semana 
                            AND dxs.id_sprint = :id_sprint_horarios
                        WHERE h.id_grupo = g.id 
                            AND h.id_area_academica = aa.id
                    ), 0) as minutos_disponibles,
                    -- Tiempo usado: ahora directo desde tareas_x_sprints
                    COALESCE((
                        SELECT SUM(act.minutos_duracion)
                        FROM tareas_x_sprints txs
                        INNER JOIN actividades_academicas act ON txs.id_actividad_academica = act.id
                        WHERE txs.id_sprint = :id_sprint_tareas
                            AND txs.id_grupo = g.id
                            AND txs.id_area_academica = aa.id
                    ), 0) as minutos_usados,
                    -- Cantidad de actividades
                    COALESCE((
                        SELECT COUNT(DISTINCT txs.id)
                        FROM tareas_x_sprints txs
                        WHERE txs.id_sprint = :id_sprint_count
                            AND txs.id_grupo = g.id
                            AND txs.id_area_academica = aa.id
                    ), 0) as cantidad_actividades
                FROM grupos g
                CROSS JOIN areas_academicas aa
                WHERE EXISTS (
                    SELECT 1 FROM horarios h 
                    WHERE h.id_grupo = g.id AND h.id_area_academica = aa.id
                )
                AND g.id_tenant = :id_tenant_g
                AND aa.id_tenant = :id_tenant_a
                ORDER BY g.nombre, aa.nombre
            ";

            $sentence = $db->prepare($sql);
            $sentence->bindValue(':id_tenant_g', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant_a', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_sprint_horarios', $id_sprint);
            $sentence->bindParam(':id_sprint_tareas', $id_sprint);
            $sentence->bindParam(':id_sprint_count', $id_sprint);
            $sentence->execute();

            $analisis = $sentence->fetchAll();

            $resultado = [
                'sprint' => [
                    'id' => $sprint['id'],
                    'nombre' => $sprint['nombre_sprint'],
                    'fecha_inicial' => $sprint['fecha_inicial'],
                    'fecha_final' => $sprint['fecha_final'],
                    'total_dias_habiles' => $sprint['total_dias_habiles']
                ],
                'analisis_por_grupo_area' => [],
                'resumen' => [
                    'total_minutos_disponibles' => 0,
                    'total_minutos_usados' => 0,
                    'total_actividades' => 0,
                    'grupos_excedidos' => []
                ]
            ];

            foreach ($analisis as $item) {
                $porcentaje_usado = $item['minutos_disponibles'] > 0
                    ? round(($item['minutos_usados'] / $item['minutos_disponibles']) * 100, 2)
                    : 0;

                $excedido = $item['minutos_usados'] > $item['minutos_disponibles'];

                $resultado['analisis_por_grupo_area'][] = [
                    'id_grupo' => $item['id_grupo'],
                    'nombre_grupo' => $item['nombre_grupo'],
                    'id_area' => $item['id_area'],
                    'nombre_area' => $item['nombre_area'],
                    'minutos_disponibles' => (int) $item['minutos_disponibles'],
                    'minutos_usados' => (int) $item['minutos_usados'],
                    'minutos_restantes' => (int) $item['minutos_disponibles'] - (int) $item['minutos_usados'],
                    'porcentaje_usado' => $porcentaje_usado,
                    'cantidad_actividades' => (int) $item['cantidad_actividades'],
                    'excedido' => $excedido,
                    'horas_disponibles' => round($item['minutos_disponibles'] / 60, 2),
                    'horas_usadas' => round($item['minutos_usados'] / 60, 2)
                ];

                $resultado['resumen']['total_minutos_disponibles'] += $item['minutos_disponibles'];
                $resultado['resumen']['total_minutos_usados'] += $item['minutos_usados'];
                $resultado['resumen']['total_actividades'] += $item['cantidad_actividades'];

                if ($excedido) {
                    $resultado['resumen']['grupos_excedidos'][] =
                        $item['nombre_grupo'] . ' - ' . $item['nombre_area'];
                }
            }

            $resultado['resumen']['porcentaje_general'] = $resultado['resumen']['total_minutos_disponibles'] > 0
                ? round(($resultado['resumen']['total_minutos_usados'] /
                    $resultado['resumen']['total_minutos_disponibles']) * 100, 2)
                : 0;

            $resultado['resumen']['horas_disponibles_total'] =
                round($resultado['resumen']['total_minutos_disponibles'] / 60, 2);
            $resultado['resumen']['horas_usadas_total'] =
                round($resultado['resumen']['total_minutos_usados'] / 60, 2);

            Flight::json($resultado);
        } catch (Exception $e) {
            error_log("Error en análisis de tiempo: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener análisis de tiempo'], 500);
        }
    }

    /**
     * Valida si se puede agregar una actividad sin exceder el tiempo.
     * Ahora recibe id_grupo e id_area_academica directamente.
     */
    public static function validarActividadEnSprint($id_sprint, $id_actividad)
    {
        $db = Flight::db();

        try {
            // Obtener id_grupo e id_area desde query params
            $id_grupo = $_GET['id_grupo'] ?? null;
            $id_area = $_GET['id_area'] ?? null;

            // Obtener información de la actividad
            $actQuery = $db->prepare("
                SELECT id, titulo, minutos_duracion
                FROM actividades_academicas
                WHERE id = :id_actividad AND id_tenant = :id_tenant
            ");
            $actQuery->bindParam(':id_actividad', $id_actividad);
            $actQuery->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $actQuery->execute();
            $actividad = $actQuery->fetch();

            if (!$actividad) {
                Flight::json(['error' => 'Actividad no encontrada'], 404);
                return;
            }

            $validacion = [
                'actividad' => [
                    'id' => $actividad['id'],
                    'titulo' => $actividad['titulo'],
                    'minutos_duracion' => (int) $actividad['minutos_duracion']
                ],
                'validaciones' => [],
                'puede_agregar' => true,
                'mensajes' => []
            ];

            // Si se especificaron grupo y área, validar solo esa combinación
            if ($id_grupo && $id_area) {
                $sql = "
                    SELECT 
                        COALESCE((
                            SELECT SUM(h.total_minutos * COALESCE(dxs.total_dias, 0))
                            FROM horarios h
                            LEFT JOIN dias_x_sprint dxs ON h.id_dia_semana = dxs.id_dia_semana 
                                AND dxs.id_sprint = :id_sprint
                            WHERE h.id_grupo = :id_grupo 
                                AND h.id_area_academica = :id_area
                        ), 0) as minutos_disponibles,
                        COALESCE((
                            SELECT SUM(act.minutos_duracion)
                            FROM tareas_x_sprints txs
                            INNER JOIN actividades_academicas act ON txs.id_actividad_academica = act.id
                            WHERE txs.id_sprint = :id_sprint2
                                AND txs.id_grupo = :id_grupo2
                                AND txs.id_area_academica = :id_area2
                        ), 0) as minutos_usados,
                        g.nombre as nombre_grupo,
                        aa.nombre as nombre_area
                    FROM grupos g, areas_academicas aa
                    WHERE g.id = :id_grupo3 AND aa.id = :id_area3
                    AND g.id_tenant = :id_tenant_g
                    AND aa.id_tenant = :id_tenant_a
                ";

                $stmt = $db->prepare($sql);
                $stmt->bindValue(':id_tenant_g', TenantContext::id(), PDO::PARAM_INT);
                $stmt->bindValue(':id_tenant_a', TenantContext::id(), PDO::PARAM_INT);
                $stmt->bindParam(':id_sprint', $id_sprint);
                $stmt->bindParam(':id_grupo', $id_grupo);
                $stmt->bindParam(':id_area', $id_area);
                $stmt->bindParam(':id_sprint2', $id_sprint);
                $stmt->bindParam(':id_grupo2', $id_grupo);
                $stmt->bindParam(':id_area2', $id_area);
                $stmt->bindParam(':id_grupo3', $id_grupo);
                $stmt->bindParam(':id_area3', $id_area);
                $stmt->execute();
                $resultado = $stmt->fetch();

                $minutos_disponibles = (int) $resultado['minutos_disponibles'];
                $minutos_usados = (int) $resultado['minutos_usados'];
                $minutos_despues = $minutos_usados + $actividad['minutos_duracion'];
                $excederia = $minutos_despues > $minutos_disponibles;

                $validacion['validaciones'][] = [
                    'grupo' => $resultado['nombre_grupo'],
                    'area' => $resultado['nombre_area'],
                    'minutos_disponibles' => $minutos_disponibles,
                    'minutos_usados_actual' => $minutos_usados,
                    'minutos_actividad' => (int) $actividad['minutos_duracion'],
                    'minutos_usados_despues' => $minutos_despues,
                    'minutos_restantes_actual' => $minutos_disponibles - $minutos_usados,
                    'minutos_restantes_despues' => $minutos_disponibles - $minutos_despues,
                    'excederia' => $excederia,
                    'porcentaje_actual' => $minutos_disponibles > 0
                        ? round(($minutos_usados / $minutos_disponibles) * 100, 2) : 0,
                    'porcentaje_despues' => $minutos_disponibles > 0
                        ? round(($minutos_despues / $minutos_disponibles) * 100, 2) : 0
                ];

                if ($excederia) {
                    $validacion['puede_agregar'] = false;
                    $validacion['mensajes'][] = sprintf(
                        "%s - %s: Excedería el tiempo en %d minutos",
                        $resultado['nombre_grupo'],
                        $resultado['nombre_area'],
                        abs($minutos_disponibles - $minutos_despues)
                    );
                }
            }

            Flight::json($validacion);
        } catch (Exception $e) {
            error_log("Error validando actividad: " . $e->getMessage());
            Flight::json(['error' => 'Error al validar actividad'], 500);
        }
    }

    public static function finalizarSprint($id_sprint)
    {
        try {
            $db = Flight::db();

            $db->beginTransaction();

            $updateSprint = $db->prepare("UPDATE sprints SET actual = 0 WHERE id = :id_sprint AND id_tenant = :id_tenant");
            $updateSprint->bindParam(':id_sprint', $id_sprint);
            $updateSprint->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $updateSprint->execute();

            $countQuery = $db->prepare("SELECT COUNT(*) as total FROM tareas_x_sprints 
                                    WHERE id_sprint = :id_sprint AND id_estado_tarea = 1 AND id_tenant = :id_tenant");
            $countQuery->bindParam(':id_sprint', $id_sprint);
            $countQuery->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $countQuery->execute();
            $result = $countQuery->fetch();
            $totalCanceladas = $result['total'];

            $fecha = date('d/m/Y H:i:s');
            $nuevaObservacion = "[{$fecha}] Sistema: Cancelación automática por finalización del sprint";

            $updateTareas = $db->prepare("UPDATE tareas_x_sprints 
                                      SET id_estado_tarea = 3,
                                          observaciones = CASE 
                                              WHEN observaciones IS NULL OR observaciones = '' 
                                              THEN :nueva_observacion
                                              ELSE CONCAT(observaciones, '\n', :nueva_observacion2)
                                          END,
                                          fecha_cambio_estado = NOW()
                                      WHERE id_sprint = :id_sprint 
                                      AND id_estado_tarea = 1
                                      AND id_tenant = :id_tenant");

            $updateTareas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $updateTareas->bindParam(':nueva_observacion', $nuevaObservacion);
            $updateTareas->bindParam(':nueva_observacion2', $nuevaObservacion);
            $updateTareas->bindParam(':id_sprint', $id_sprint);
            $updateTareas->execute();

            $db->commit();

            Flight::json([
                'success' => true,
                'mensaje' => 'Sprint finalizado correctamente',
                'tareas_canceladas' => $totalCanceladas
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error al finalizar sprint: " . $e->getMessage());
            Flight::json(['error' => 'Error al finalizar el sprint: ' . $e->getMessage()], 500);
        }
    }

    public static function getAnalisisCoberturaCurricular()
    {
        try {
            $db = Flight::db();

            $id_grupo = $_GET['id_grupo'] ?? null;
            $id_corte = $_GET['id_corte'] ?? null;
            $id_area = $_GET['id_area'] ?? null;
            $id_esfera = $_GET['id_esfera'] ?? null;

            if (!$id_grupo || !$id_corte) {
                Flight::json(["error" => "id_grupo e id_corte son requeridos"], 400);
                return;
            }

            $stmtGrados = $db->prepare("SELECT gxg.id_grado FROM grados_x_grupo gxg WHERE gxg.id_grupo = :id_grupo AND gxg.id_tenant = :id_tenant");
            $stmtGrados->bindParam(':id_grupo', $id_grupo);
            $stmtGrados->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtGrados->execute();
            $grados = $stmtGrados->fetchAll(PDO::FETCH_COLUMN);

            if (empty($grados)) {
                Flight::json([
                    "resumen" => ["total_logros" => 0, "logros_cubiertos" => 0, "logros_sin_cobertura" => 0, "porcentaje_cobertura" => 0, "total_actividades" => 0],
                    "por_area" => [], "por_esfera" => [], "logros" => [], "materiales_consolidados" => []
                ]);
                return;
            }

            $gradoPlaceholders = [];
            $gradoParams = [];
            foreach ($grados as $i => $grado) {
                $key = ":grado_$i";
                $gradoPlaceholders[] = $key;
                $gradoParams[$key] = $grado;
            }
            $gradoIn = implode(',', $gradoPlaceholders);

            $sql = "
                SELECT 
                    l.id AS id_logro, l.nombre AS nombre_logro,
                    l.id_area_academica, aa_cat.nombre AS nombre_area,
                    l.id_esfera_desarrollo, ed.nombre AS nombre_esfera,
                    l.id_eje_curricular, ec.nombre AS nombre_eje,
                    l.id_competencia_cognitiva, cc.nombre AS nombre_competencia,
                    l.id_grado, gr.nombre AS nombre_grado,
                    l.id_corte_academico, ca.nombre AS nombre_corte,
                    COUNT(DISTINCT txs.id) AS cantidad_actividades_programadas,
                    COUNT(DISTINCT aa.id) AS cantidad_actividades_unicas,
                    GROUP_CONCAT(DISTINCT CONCAT(aa.id, '::', aa.titulo, '::', COALESCE(aa.materiales,''), '::', COALESCE(aa.minutos_duracion,0), '::', COALESCE(aa.nivel_uno,''), '::', COALESCE(aa.nivel_dos,''), '::', COALESCE(et.nombre,'Pendiente'), '::', COALESCE(aa.descripcion,'')) ORDER BY aa.titulo SEPARATOR '||||') AS detalle_actividades
                FROM logros l
                INNER JOIN grados gr ON l.id_grado = gr.id
                INNER JOIN areas_academicas aa_cat ON l.id_area_academica = aa_cat.id
                LEFT JOIN esferas_desarrollo ed ON l.id_esfera_desarrollo = ed.id
                LEFT JOIN ejes_curriculares ec ON l.id_eje_curricular = ec.id
                LEFT JOIN competencias_cognitivas cc ON l.id_competencia_cognitiva = cc.id
                INNER JOIN cortes_academicos ca ON l.id_corte_academico = ca.id
                LEFT JOIN indicadores_logros il ON l.id = il.id_logro
                LEFT JOIN actividades_academicas_x_indicadores_logros aaxil ON il.id = aaxil.id_indicador_logro
                LEFT JOIN actividades_academicas aa ON aaxil.id_actividad_academica = aa.id
                LEFT JOIN tareas_x_sprints txs ON aa.id = txs.id_actividad_academica 
                    AND txs.id_grupo = :id_grupo_tareas
                    AND txs.id_sprint IN (SELECT id FROM sprints WHERE id_corte_academico = :id_corte_sprints)
                LEFT JOIN estados_tareas et ON txs.id_estado_tarea = et.id
                WHERE l.id_grado IN ($gradoIn)
                AND l.id_corte_academico = :id_corte
                AND l.id_tenant = :id_tenant
            ";

            $params = $gradoParams;
            $params[':id_grupo_tareas'] = $id_grupo;
            $params[':id_corte_sprints'] = $id_corte;
            $params[':id_corte'] = $id_corte;
            $params[':id_tenant'] = TenantContext::id();

            if ($id_area) {
                $sql .= " AND l.id_area_academica = :id_area";
                $params[':id_area'] = $id_area;
            }
            if ($id_esfera) {
                $sql .= " AND l.id_esfera_desarrollo = :id_esfera";
                $params[':id_esfera'] = $id_esfera;
            }

            $sql .= " GROUP BY l.id ORDER BY aa_cat.nombre, gr.nombre, l.nombre";

            $sentence = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $sentence->bindValue($key, $value);
            }
            $sentence->execute();
            $logrosRaw = $sentence->fetchAll(PDO::FETCH_ASSOC);

            $logros = [];
            $porArea = [];
            $porEsfera = [];
            $materialesGlobal = [];
            $totalActividades = 0;
            $logrosCubiertos = 0;

            foreach ($logrosRaw as $row) {
                $cubierto = (int)$row['cantidad_actividades_programadas'] > 0;
                if ($cubierto) $logrosCubiertos++;
                $totalActividades += (int)$row['cantidad_actividades_programadas'];

                $actividades = [];
                if (!empty($row['detalle_actividades'])) {
                    $partes = explode('||||', $row['detalle_actividades']);
                    foreach ($partes as $parte) {
                        $campos = explode('::', $parte);
                        if (count($campos) >= 8) {
                            $actividades[] = [
                                "id" => $campos[0], "titulo" => $campos[1], "materiales" => $campos[2],
                                "minutos_duracion" => (int)$campos[3], "nivel_uno" => $campos[4],
                                "nivel_dos" => $campos[5], "estado" => $campos[6], "descripcion" => $campos[7]
                            ];
                            if (!empty($campos[2])) {
                                $mats = preg_split('/[,;\/\-\n]+/', $campos[2]);
                                foreach ($mats as $mat) {
                                    $mat = trim(mb_strtolower($mat));
                                    if (strlen($mat) > 1) {
                                        if (!isset($materialesGlobal[$mat])) $materialesGlobal[$mat] = 0;
                                        $materialesGlobal[$mat]++;
                                    }
                                }
                            }
                        }
                    }
                }

                // Calcular minutos del logro
                $minutosLogro = 0;
                foreach ($actividades as $act) {
                    $minutosLogro += (int)$act['minutos_duracion'];
                }

                $logros[] = [
                    "id" => $row['id_logro'], "nombre" => $row['nombre_logro'],
                    "nombre_area" => $row['nombre_area'], "id_area" => $row['id_area_academica'],
                    "nombre_esfera" => $row['nombre_esfera'], "id_esfera" => $row['id_esfera_desarrollo'],
                    "nombre_eje" => $row['nombre_eje'], "nombre_competencia" => $row['nombre_competencia'],
                    "nombre_grado" => $row['nombre_grado'], "nombre_corte" => $row['nombre_corte'],
                    "cubierto" => $cubierto, "cantidad_actividades" => (int)$row['cantidad_actividades_programadas'],
                    "actividades_unicas" => (int)$row['cantidad_actividades_unicas'],
                    "total_minutos" => $minutosLogro,
                    "actividades" => $actividades
                ];

                $idArea = $row['id_area_academica'];
                if (!isset($porArea[$idArea])) {
                    $porArea[$idArea] = ["id_area" => $idArea, "nombre" => $row['nombre_area'], "total_logros" => 0, "cubiertos" => 0];
                }
                $porArea[$idArea]['total_logros']++;
                if ($cubierto) $porArea[$idArea]['cubiertos']++;

                $idEsfera = $row['id_esfera_desarrollo'];
                if (!empty($idEsfera)) {
                    if (!isset($porEsfera[$idEsfera])) {
                        $porEsfera[$idEsfera] = ["id_esfera" => $idEsfera, "nombre" => $row['nombre_esfera'], "total_logros" => 0, "cubiertos" => 0];
                    }
                    $porEsfera[$idEsfera]['total_logros']++;
                    if ($cubierto) $porEsfera[$idEsfera]['cubiertos']++;
                }
            }

            $totalLogros = count($logrosRaw);

            // Calcular total de minutos global y porcentaje de aporte por logro
            $totalMinutosGlobal = 0;
            foreach ($logros as $logro) {
                $totalMinutosGlobal += $logro['total_minutos'];
            }
            foreach ($logros as &$logro) {
                $logro['porcentaje_actividades'] = $totalActividades > 0 ? round(($logro['cantidad_actividades'] / $totalActividades) * 100, 1) : 0;
                $logro['porcentaje_minutos'] = $totalMinutosGlobal > 0 ? round(($logro['total_minutos'] / $totalMinutosGlobal) * 100, 1) : 0;
            }

            foreach ($porArea as &$area) {
                $area['porcentaje'] = $area['total_logros'] > 0 ? round(($area['cubiertos'] / $area['total_logros']) * 100) : 0;
            }
            foreach ($porEsfera as &$esfera) {
                $esfera['porcentaje'] = $esfera['total_logros'] > 0 ? round(($esfera['cubiertos'] / $esfera['total_logros']) * 100) : 0;
            }

            arsort($materialesGlobal);
            $materialesConsolidados = [];
            foreach ($materialesGlobal as $material => $frecuencia) {
                $materialesConsolidados[] = ["nombre" => $material, "frecuencia" => $frecuencia];
            }

            Flight::json([
                "resumen" => [
                    "total_logros" => $totalLogros, "logros_cubiertos" => $logrosCubiertos,
                    "logros_sin_cobertura" => $totalLogros - $logrosCubiertos,
                    "porcentaje_cobertura" => $totalLogros > 0 ? round(($logrosCubiertos / $totalLogros) * 100) : 0,
                    "total_actividades" => $totalActividades,
                    "total_minutos" => $totalMinutosGlobal
                ],
                "por_area" => array_values($porArea),
                "por_esfera" => array_values($porEsfera),
                "logros" => $logros,
                "materiales_consolidados" => $materialesConsolidados
            ]);
        } catch (PDOException $e) {
            error_log("Error en Sprints::getAnalisisCoberturaCurricular: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

}