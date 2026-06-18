<?php
class Calificaciones
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_tarea_x_sprint, id_estudiante, id_parametro_calificacion, id_valor_parametro_calificacion from calificaciones where id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_tarea_x_sprint, id_estudiante, id_parametro_calificacion, id_valor_parametro_calificacion from calificaciones where id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByTareaSprint($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT c.id, c.id_tarea_x_sprint, c.id_estudiante, c.id_parametro_calificacion, c.id_valor_parametro_calificacion,
                CONCAT(p.primer_nombre, ' ', p.primer_apellido) as nombre_estudiante
            FROM calificaciones c
            INNER JOIN estudiantes e ON c.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            WHERE c.id_tarea_x_sprint = :id AND c.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }

    /**
     * Devuelve la vista consolidada para calificar una tarea:
     * - Todos los estudiantes activos del grupo (presentes y ausentes)
     * - Flag presente (1 si tiene asistencia hoy sin salida, 0 si no)
     * - Observación e id de tareas_x_sprints_x_estudiante (si existe)
     * - Calificaciones existentes anidadas por estudiante
     *
     * Hace 2 consultas en total (estudiantes + calificaciones).
     */
    public static function getVistaTarea($id_grupo, $id_tarea_sprint)
    {
        try {
            $db = Flight::db();
            $db->exec("SET time_zone = '-05:00'");

            // 1. Estudiantes activos del grupo + asistencia hoy + observación de la tarea
            $sqlEstudiantes = "SELECT 
                e.id as id_estudiante,
                p.primer_nombre,
                p.segundo_nombre,
                p.primer_apellido,
                p.segundo_apellido,
                CASE 
                    WHEN ae.id IS NOT NULL THEN 1
                    ELSE 0
                END as presente,
                txse.id as id_tarea_estudiante,
                txse.observacion
            FROM estudiantes_x_grupos exg
            INNER JOIN estudiantes e ON exg.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            LEFT JOIN asistencia_estudiantes ae 
                ON ae.id_estudiante = e.id 
                AND DATE(ae.fecha_ingreso) = CURDATE() 
                AND ae.fecha_salida IS NULL
            LEFT JOIN tareas_x_sprints_x_estudiante txse
                ON txse.id_estudiante = e.id
                AND txse.id_tarea_x_sprint = :id_tarea_sprint
            WHERE exg.id_grupo = :id_grupo
              AND exg.activo = 1
              AND e.activo = 1
              AND exg.id_tenant = :id_tenant
            ORDER BY p.primer_nombre, p.primer_apellido";

            $stmt = $db->prepare($sqlEstudiantes);
            $stmt->bindParam(':id_grupo', $id_grupo);
            $stmt->bindParam(':id_tarea_sprint', $id_tarea_sprint);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Calificaciones de la tarea
            $sqlCalificaciones = "SELECT 
                id,
                id_estudiante,
                id_parametro_calificacion,
                id_valor_parametro_calificacion
            FROM calificaciones
            WHERE id_tarea_x_sprint = :id_tarea_sprint AND id_tenant = :id_tenant";

            $stmt = $db->prepare($sqlCalificaciones);
            $stmt->bindParam(':id_tarea_sprint', $id_tarea_sprint);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $calificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar calificaciones por id_estudiante
            $calificacionesPorEstudiante = [];
            foreach ($calificaciones as $cal) {
                $idEst = $cal['id_estudiante'];
                if (!isset($calificacionesPorEstudiante[$idEst])) {
                    $calificacionesPorEstudiante[$idEst] = [];
                }
                $calificacionesPorEstudiante[$idEst][] = [
                    'id' => $cal['id'],
                    'id_parametro_calificacion' => $cal['id_parametro_calificacion'],
                    'id_valor_parametro_calificacion' => $cal['id_valor_parametro_calificacion']
                ];
            }

            // Anidar calificaciones a cada estudiante
            foreach ($estudiantes as &$est) {
                $idEst = $est['id_estudiante'];
                $est['calificaciones'] = isset($calificacionesPorEstudiante[$idEst])
                    ? $calificacionesPorEstudiante[$idEst]
                    : [];
                $est['presente'] = (int) $est['presente'];
            }
            unset($est);

            Flight::json($estudiantes);
        } catch (Exception $e) {
            error_log('Error en getVistaTarea(): ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener vista de tarea'], 500);
        }
    }

    public static function consultarCalificacionesTareasSprintEstudiante($id_estudiante, $id_sprint)
    {
        $db = Flight::db();

        // Llamar al procedimiento almacenado
        $sql = "CALL consultar_calificaciones_tareas_sprint_estudiante(:estudiante_id, :sprint_id)";
        $sentence = $db->prepare($sql);
        $sentence->bindParam(':estudiante_id', $id_estudiante);
        $sentence->bindParam(':sprint_id', $id_sprint);
        $sentence->execute();

        // Obtener los resultados del procedimiento
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);

        // Cerrar el cursor para permitir futuras consultas
        $sentence->closeCursor();

        Flight::json($response);
    }


    public static function consultarCalificacionesTareasSprintEstudiantes($id_sprint)
    {
        try {
            $db = Flight::db();
            error_log("Valor de id_sprint recibido: " . $id_sprint);
            // Obtener la lista de estudiantes activos y sus grupos
            $sql = "SELECT eg.id_estudiante, p.numero_identificacion, p.primer_nombre, p.primer_apellido, 
                           g.id AS id_grupo, g.nombre AS nombre_grupo, g.orden
                    FROM estudiantes_x_grupos eg
                    JOIN estudiantes e ON eg.id_estudiante = e.id
                    JOIN personas p ON e.id_persona = p.id
                    JOIN grupos g ON eg.id_grupo = g.id
                    WHERE eg.activo = 1
                    AND eg.id_tenant = :id_tenant
                    ORDER BY g.orden, p.primer_nombre, p.primer_apellido";

            $sentence = $db->prepare($sql);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $estudiantes = $sentence->fetchAll(PDO::FETCH_ASSOC);
            $sentence->closeCursor();

            $reporte = [];

            $proc = $db->prepare("CALL consultar_calificaciones_tareas_sprint_estudiante(:estudiante_id, :sprint_id)");

            foreach ($estudiantes as $estudiante) {
                $proc->bindParam(':estudiante_id', $estudiante['id_estudiante']);
                $proc->bindParam(':sprint_id', $id_sprint);
                $proc->execute();
                $calificaciones = $proc->fetchAll(PDO::FETCH_ASSOC);
                $proc->closeCursor();

                if (!$calificaciones) {
                    continue;
                }

                foreach ($calificaciones as $calificacion) {
                    $reporte[] = [
                        "id_estudiante" => $estudiante['id_estudiante'],
                        "numero_identificacion" => $estudiante['numero_identificacion'],
                        "primer_nombre" => $estudiante['primer_nombre'],
                        "primer_apellido" => $estudiante['primer_apellido'],
                        "id_grupo" => $estudiante['id_grupo'],
                        "nombre_grupo" => $estudiante['nombre_grupo'],
                        "orden" => $estudiante['orden'],
                        "id_tarea" => $calificacion['id_tarea'],
                        "id_sprint" => $calificacion['id_sprint'],
                        "es_sprint_actual" => $calificacion['es_sprint_actual'],
                        "nombre_sprint" => $calificacion['nombre_sprint'],
                        "nombre_esfera_desarrollo" => $calificacion['nombre_esfera_desarrollo'],
                        "id_area_academica" => $calificacion['id_area_academica'],
                        "area_academica" => $calificacion['area_academica'],
                        "indicador_logro_nombre" => $calificacion['indicador_logro_nombre'],
                        "actividad_academica_titulo" => $calificacion['actividad_academica_titulo'],
                        "minutos_duracion" => $calificacion['minutos_duracion'],
                        "id_estado_tarea" => $calificacion['id_estado_tarea'],
                        "estado_tarea_nombre" => $calificacion['estado_tarea_nombre'],
                        "id_docente" => $calificacion['id_docente'],
                        "fecha_ejecucion" => $calificacion['fecha_ejecucion'],
                        "fecha_registro" => $calificacion['fecha_registro'],
                        "id_docente_inicia" => $calificacion['id_docente_inicia'],
                        "fecha_ejecucion_inicia" => $calificacion['fecha_ejecucion_inicia'],
                        "parametro_calificacion" => $calificacion['parametro_calificacion'],
                        "valor_cuantitativo" => $calificacion['valor_cuantitativo'],
                        "valor_cualitativo" => $calificacion['valor_cualitativo'],
                        "color" => $calificacion['color'],
                        "docente_nombre_completo" => $calificacion['docente_nombre_completo'],
                        "estado_tarea_estudiante" => $calificacion['estado_tarea_estudiante']
                    ];
                }
            }

            Flight::json($reporte);
        } catch (PDOException $e) {
            Flight::json(["error" => $e->getMessage()]);
        }
    }



    public static function consultarCalificacionesPDMXEstudiante($id_estudiante)
    {
        $db = Flight::db();
        $sql = "SELECT * FROM reporte_calificacion_pdm where id_estudiante = :id_estudiante AND id_tenant = :id_tenant ORDER BY nivel,area, codigo_logro";
        $sentence = $db->prepare($sql);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }


    public static function ConsultarCalificacionesPDMXEstudiantes()
    {
        $db = Flight::db();
        $sql = "SELECT * FROM reporte_calificacion_pdm WHERE id_tenant = :id_tenant ORDER BY nivel,area, codigo_logro";
        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }


    public static function new()
    {
        $db = Flight::db();
        $id_tarea_x_sprint = Flight::request()->data['id_tarea_x_sprint'];
        $id_estudiante = Flight::request()->data['id_estudiante'];
        $id_parametro_calificacion = Flight::request()->data['id_parametro_calificacion'];
        $id_valor_parametro_calificacion = Flight::request()->data['id_valor_parametro_calificacion'];
        $idNew = Uuid::generar();
        $sentence = $db->prepare("insert into calificaciones(id, id_tenant, id_tarea_x_sprint, id_estudiante, id_parametro_calificacion, id_valor_parametro_calificacion) values (:id, :id_tenant, :id_tarea_x_sprint, :id_estudiante, :id_parametro_calificacion, :id_valor_parametro_calificacion)");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_tarea_x_sprint', $id_tarea_x_sprint);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_parametro_calificacion', $id_parametro_calificacion);
        $sentence->bindParam(':id_valor_parametro_calificacion', $id_valor_parametro_calificacion);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_valor_parametro_calificacion = Flight::request()->data['id_valor_parametro_calificacion'];
        $sentence = $db->prepare("update calificaciones set id_valor_parametro_calificacion = :id_valor_parametro_calificacion where id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_valor_parametro_calificacion', $id_valor_parametro_calificacion);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from calificaciones where id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }
    public static function obtenerCalificacionesPorSprintEstudiantes($id_sprint, $id_estudiante = null)
    {
        try {
            $db = Flight::db();

            $id_grupo = null;
            if (isset($_GET['id_grupo'])) {
                $id_grupo = $_GET['id_grupo'];
            }

            $db->query("SET session wait_timeout=28800");
            $db->query("SET session interactive_timeout=28800");

            $sql_base = "SELECT 
                c.id,
                c.id_tarea_x_sprint,
                c.id_estudiante,
                c.id_parametro_calificacion,
                c.id_valor_parametro_calificacion,
                txs.id_sprint,
                txs.id_actividad_academica,
                txs.id_estado_tarea,
                txs.id_docente,
                txs.fecha_ejecucion,
                txs.fecha_registro,
                txs.id_docente_inicia,
                txs.fecha_ejecucion_inicia
            FROM 
                calificaciones c
            JOIN tareas_x_sprints txs ON c.id_tarea_x_sprint = txs.id
            WHERE 
                txs.id_sprint = :id_sprint
                AND c.id_tenant = :id_tenant";

            if ($id_estudiante !== null) {
                $sql_base .= " AND c.id_estudiante = :id_estudiante";
            }
            $sentence = $db->prepare($sql_base);
            $sentence->bindParam(':id_sprint', $id_sprint);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            if ($id_estudiante !== null) {
                $sentence->bindParam(':id_estudiante', $id_estudiante);
            }
            $sentence->execute();
            $resultados_base = $sentence->fetchAll(PDO::FETCH_ASSOC);

            $calificaciones = [];
            $ids_tareas = [];
            $ids_estudiantes = [];
            $ids_parametros = [];
            $ids_valores_parametros = [];
            $ids_estados_tareas = [];
            $ids_docentes = [];
            $ids_actividades = [];

            foreach ($resultados_base as $resultado) {
                $id_calificacion = $resultado['id'];

                $calificaciones[$id_calificacion] = [
                    "id" => $id_calificacion,
                    "id_tarea_x_sprint" => $resultado['id_tarea_x_sprint'],
                    "id_estudiante" => $resultado['id_estudiante'],
                    "id_sprint" => $resultado['id_sprint'],
                    "id_parametro_calificacion" => $resultado['id_parametro_calificacion'],
                    "id_valor_parametro_calificacion" => $resultado['id_valor_parametro_calificacion'],
                    "id_actividad_academica" => $resultado['id_actividad_academica'],
                    "id_estado_tarea" => $resultado['id_estado_tarea'],
                    "id_docente" => $resultado['id_docente'],
                    "fecha_ejecucion" => $resultado['fecha_ejecucion'],
                    "fecha_registro" => $resultado['fecha_registro'],
                    "id_docente_inicia" => $resultado['id_docente_inicia'],
                    "fecha_ejecucion_inicia" => $resultado['fecha_ejecucion_inicia']
                ];

                $ids_tareas[] = $resultado['id_tarea_x_sprint'];
                $ids_estudiantes[] = $resultado['id_estudiante'];
                $ids_parametros[] = $resultado['id_parametro_calificacion'];
                $ids_valores_parametros[] = $resultado['id_valor_parametro_calificacion'];
                $ids_estados_tareas[] = $resultado['id_estado_tarea'];
                $ids_docentes[] = $resultado['id_docente'];
                $ids_actividades[] = $resultado['id_actividad_academica'];
            }

            $ids_tareas = array_unique($ids_tareas);
            $ids_estudiantes = array_unique($ids_estudiantes);
            $ids_parametros = array_unique($ids_parametros);
            $ids_valores_parametros = array_unique($ids_valores_parametros);
            $ids_estados_tareas = array_unique($ids_estados_tareas);
            $ids_docentes = array_unique($ids_docentes);
            $ids_actividades = array_unique($ids_actividades);

            $sql_sprint = "SELECT id, nombre_sprint, actual as es_sprint_actual FROM sprints WHERE id = :id_sprint AND id_tenant = :id_tenant";
            $sentence = $db->prepare($sql_sprint);
            $sentence->bindParam(':id_sprint', $id_sprint);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $sprint = $sentence->fetch(PDO::FETCH_ASSOC);

            if (!empty($ids_estudiantes)) {
                $placeholders = implode(',', array_fill(0, count($ids_estudiantes), '?'));
                $sql_estudiantes = "SELECT 
                    e.id,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    CONCAT(
                        p.primer_nombre, 
                        IF(p.segundo_nombre IS NOT NULL AND p.segundo_nombre != '', CONCAT(' ', p.segundo_nombre), ''),
                        ' ',
                        p.primer_apellido,
                        IF(p.segundo_apellido IS NOT NULL AND p.segundo_apellido != '', CONCAT(' ', p.segundo_apellido), '')
                    ) as nombre_completo_estudiante,
                    p.numero_identificacion,
                    eg.id_grupo,
                    g.nombre as nombre_grupo,
                    g.orden
                FROM 
                    estudiantes e
                JOIN personas p ON e.id_persona = p.id
                JOIN estudiantes_x_grupos eg ON e.id = eg.id_estudiante
                JOIN grupos g ON eg.id_grupo = g.id
                WHERE 
                    e.id IN ($placeholders)
                    AND eg.activo = 1
                    AND e.id_tenant = ?";

                if ($id_grupo !== null) {
                    $sql_estudiantes .= " AND eg.id_grupo = ?";
                }

                $sentence = $db->prepare($sql_estudiantes);
                $i = 1;
                foreach ($ids_estudiantes as $id) {
                    $sentence->bindValue($i++, $id);
                }
                $sentence->bindValue($i++, TenantContext::id());

                if ($id_grupo !== null) {
                    $sentence->bindValue($i, $id_grupo);
                }

                $sentence->execute();
                $info_estudiantes = [];
                while ($row = $sentence->fetch(PDO::FETCH_ASSOC)) {
                    $info_estudiantes[$row['id']] = $row;
                }
            }

            if (!empty($ids_actividades)) {
                $placeholders = implode(',', array_fill(0, count($ids_actividades), '?'));
                $sql_actividades = "SELECT 
                    aa.id,
                    aa.titulo as actividad_academica_titulo,
                    aa.minutos_duracion,
                    aail.id_indicador_logro,
                    il.nombre as descripcion_indicador_logro,
                    l.id as id_logro,
                    l.nombre as logro_nombre,
                    l.id_area_academica,
                    aa2.nombre as area_academica_nombre,
                    l.id_esfera_desarrollo,
                    ed.nombre as esfera_desarrollo_nombre,
                    l.id_corte_academico,
                    ca.nombre as corte_academico_nombre
                FROM 
                    actividades_academicas aa
                JOIN actividades_academicas_x_indicadores_logros aail ON aa.id = aail.id_actividad_academica
                JOIN indicadores_logros il ON aail.id_indicador_logro = il.id
                JOIN logros l ON il.id_logro = l.id
                JOIN areas_academicas aa2 ON l.id_area_academica = aa2.id
                JOIN esferas_desarrollo ed ON l.id_esfera_desarrollo = ed.id
                JOIN cortes_academicos ca ON l.id_corte_academico = ca.id
                WHERE 
                    aa.id IN ($placeholders)
                    AND aa.id_tenant = ?";

                $sentence = $db->prepare($sql_actividades);
                $i = 1;
                foreach ($ids_actividades as $id) {
                    $sentence->bindValue($i++, $id);
                }
                $sentence->bindValue($i++, TenantContext::id());
                $sentence->execute();
                $info_actividades = [];
                while ($row = $sentence->fetch(PDO::FETCH_ASSOC)) {
                    $info_actividades[$row['id']] = $row;
                }
            }

            if (!empty($ids_parametros)) {
                $placeholders = implode(',', array_fill(0, count($ids_parametros), '?'));
                $sql_parametros = "SELECT id, nombre as parametro_calificacion FROM parametros_calificaciones WHERE id IN ($placeholders) AND id_tenant = ?";
                $sentence = $db->prepare($sql_parametros);
                $i = 1;
                foreach ($ids_parametros as $id) {
                    $sentence->bindValue($i++, $id);
                }
                $sentence->bindValue($i++, TenantContext::id());
                $sentence->execute();
                $info_parametros = [];
                while ($row = $sentence->fetch(PDO::FETCH_ASSOC)) {
                    $info_parametros[$row['id']] = $row;
                }
            }

            if (!empty($ids_valores_parametros)) {
                $placeholders = implode(',', array_fill(0, count($ids_valores_parametros), '?'));
                $sql_valores = "SELECT id, valor_cuantitativo, valor_cualitativo FROM valores_parametros_calificaciones WHERE id IN ($placeholders) AND id_tenant = ?";
                $sentence = $db->prepare($sql_valores);
                $i = 1;
                foreach ($ids_valores_parametros as $id) {
                    $sentence->bindValue($i++, $id);
                }
                $sentence->bindValue($i++, TenantContext::id());
                $sentence->execute();
                $info_valores = [];
                while ($row = $sentence->fetch(PDO::FETCH_ASSOC)) {
                    $info_valores[$row['id']] = $row;
                }
            }

            if (!empty($ids_estados_tareas)) {
                $placeholders = implode(',', array_fill(0, count($ids_estados_tareas), '?'));
                $sql_estados = "SELECT id, nombre as estado_tarea_nombre FROM estados_tareas WHERE id IN ($placeholders)";
                $sentence = $db->prepare($sql_estados);
                $i = 1;
                foreach ($ids_estados_tareas as $id) {
                    $sentence->bindValue($i++, $id);
                }
                $sentence->execute();
                $info_estados = [];
                while ($row = $sentence->fetch(PDO::FETCH_ASSOC)) {
                    $info_estados[$row['id']] = $row;
                }
            }

            if (!empty($ids_docentes)) {
                $placeholders = implode(',', array_fill(0, count($ids_docentes), '?'));
                $sql_docentes = "SELECT 
                    d.id,
                    pd.primer_nombre as docente_nombre,
                    pd.segundo_nombre as docente_segundo_nombre,
                    pd.primer_apellido as docente_apellido,
                    pd.segundo_apellido as docente_segundo_apellido,
                    CONCAT(
                        pd.primer_nombre, 
                        IF(pd.segundo_nombre IS NOT NULL AND pd.segundo_nombre != '', CONCAT(' ', pd.segundo_nombre), ''),
                        ' ',
                        pd.primer_apellido,
                        IF(pd.segundo_apellido IS NOT NULL AND pd.segundo_apellido != '', CONCAT(' ', pd.segundo_apellido), '')
                    ) as nombre_completo_docente
                FROM 
                    docentes d
                JOIN personas pd ON d.id_persona = pd.id
                WHERE 
                    d.id IN ($placeholders)
                    AND d.id_tenant = ?";

                $sentence = $db->prepare($sql_docentes);
                $i = 1;
                foreach ($ids_docentes as $id) {
                    $sentence->bindValue($i++, $id);
                }
                $sentence->bindValue($i++, TenantContext::id());
                $sentence->execute();
                $info_docentes = [];
                while ($row = $sentence->fetch(PDO::FETCH_ASSOC)) {
                    $info_docentes[$row['id']] = $row;
                }
            }

            $resultado_final = [];
            foreach ($calificaciones as $id_calificacion => $calificacion) {
                $id_estudiante = $calificacion['id_estudiante'];

                if (!isset($info_estudiantes[$id_estudiante])) {
                    continue;
                }

                $id_actividad = $calificacion['id_actividad_academica'];
                $id_parametro = $calificacion['id_parametro_calificacion'];
                $id_valor = $calificacion['id_valor_parametro_calificacion'];
                $id_estado = $calificacion['id_estado_tarea'];
                $id_docente = $calificacion['id_docente'];

                $datos_completos = [
                    "id" => $calificacion['id'],
                    "id_tarea_x_sprint" => $calificacion['id_tarea_x_sprint'],
                    "id_estudiante" => $id_estudiante,
                    "id_sprint" => $calificacion['id_sprint'],
                    "nombre_sprint" => $sprint['nombre_sprint'] ?? '',
                    "es_sprint_actual" => $sprint['es_sprint_actual'] ?? 0,

                    "nombre_completo_estudiante" => $info_estudiantes[$id_estudiante]['nombre_completo_estudiante'] ?? '',
                    "numero_identificacion" => $info_estudiantes[$id_estudiante]['numero_identificacion'] ?? '',
                    "id_grupo" => $info_estudiantes[$id_estudiante]['id_grupo'] ?? null,
                    "nombre_grupo" => $info_estudiantes[$id_estudiante]['nombre_grupo'] ?? '',
                    "orden" => $info_estudiantes[$id_estudiante]['orden'] ?? 0,

                    "actividad_academica_titulo" => $info_actividades[$id_actividad]['actividad_academica_titulo'] ?? '',
                    "minutos_duracion" => $info_actividades[$id_actividad]['minutos_duracion'] ?? 0,
                    "id_area_academica" => $info_actividades[$id_actividad]['id_area_academica'] ?? null,
                    "area_academica_nombre" => $info_actividades[$id_actividad]['area_academica_nombre'] ?? '',
                    "id_esfera_desarrollo" => $info_actividades[$id_actividad]['id_esfera_desarrollo'] ?? null,
                    "esfera_desarrollo_nombre" => $info_actividades[$id_actividad]['esfera_desarrollo_nombre'] ?? '',
                    "id_corte_academico" => $info_actividades[$id_actividad]['id_corte_academico'] ?? null,
                    "corte_academico_nombre" => $info_actividades[$id_actividad]['corte_academico_nombre'] ?? '',
                    "id_logro" => $info_actividades[$id_actividad]['id_logro'] ?? null,
                    "logro_nombre" => $info_actividades[$id_actividad]['logro_nombre'] ?? '',
                    "id_indicador_logro" => $info_actividades[$id_actividad]['id_indicador_logro'] ?? null,
                    "descripcion_indicador_logro" => $info_actividades[$id_actividad]['descripcion_indicador_logro'] ?? '',

                    "id_parametro_calificacion" => $id_parametro,
                    "parametro_calificacion" => $info_parametros[$id_parametro]['parametro_calificacion'] ?? '',
                    "valor_cuantitativo" => $info_valores[$id_valor]['valor_cuantitativo'] ?? 0,
                    "valor_cualitativo" => $info_valores[$id_valor]['valor_cualitativo'] ?? '',

                    "id_estado_tarea" => $id_estado,
                    "estado_tarea_nombre" => $info_estados[$id_estado]['estado_tarea_nombre'] ?? '',
                    "estado_tarea_estudiante" => $id_estado,

                    "id_docente" => $id_docente,
                    "nombre_completo_docente" => $info_docentes[$id_docente]['nombre_completo_docente'] ?? '',

                    "fecha_ejecucion" => $calificacion['fecha_ejecucion'],
                    "fecha_registro" => $calificacion['fecha_registro'],
                    "id_docente_inicia" => $calificacion['id_docente_inicia'],
                    "fecha_ejecucion_inicia" => $calificacion['fecha_ejecucion_inicia']
                ];

                $resultado_final[] = $datos_completos;
            }

            usort($resultado_final, function ($a, $b) {
                if ($a['orden'] != $b['orden']) {
                    return $a['orden'] - $b['orden'];
                }

                $cmp = strcmp($a['nombre_completo_estudiante'], $b['nombre_completo_estudiante']);
                if ($cmp != 0) {
                    return $cmp;
                }

                $cmp = strcmp($a['area_academica_nombre'], $b['area_academica_nombre']);
                if ($cmp != 0) {
                    return $cmp;
                }

                return $a['id_tarea_x_sprint'] - $b['id_tarea_x_sprint'];
            });

            Flight::json($resultado_final);
        } catch (PDOException $e) {
            error_log("Error en obtenerCalificacionesPorSprintEstudiantes: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }


    public static function obtenerCalificacionesEstudianteDetalle($id_sprint, $id_estudiante)
    {
        self::obtenerCalificacionesPorSprintEstudiantes($id_sprint, $id_estudiante);
    }

}