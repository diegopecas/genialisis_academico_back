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
                CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) as nombre_estudiante
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
    /**
     * Anida a cada estudiante sus calificaciones de la tarea.
     *
     * Se extrajo de getVistaTarea para que la vista por grupo y la vista por
     * curso extracurricular compartan exactamente la misma logica: lo unico
     * que cambia entre las dos es de donde salen los estudiantes.
     *
     * Se devuelve UNA sola calificacion por estudiante y parametro: la mas
     * reciente. La tabla puede tener duplicados de cuando el front insertaba en
     * vez de actualizar, y si se devolvieran todos, la pantalla mostraria
     * cualquiera de ellos segun como los entregue el motor. Con el NOT EXISTS
     * se descarta toda fila que tenga otra mas nueva para la misma combinacion.
     */
    private static function anidarCalificaciones($db, $estudiantes, $id_tarea_sprint)
    {
        $sqlCalificaciones = "SELECT 
            c.id,
            c.id_estudiante,
            c.id_parametro_calificacion,
            c.id_valor_parametro_calificacion
        FROM calificaciones c
        WHERE c.id_tarea_x_sprint = :id_tarea_sprint AND c.id_tenant = :id_tenant
          AND NOT EXISTS (
                SELECT 1 FROM calificaciones c2
                WHERE c2.id_tarea_x_sprint = c.id_tarea_x_sprint
                  AND c2.id_estudiante = c.id_estudiante
                  AND c2.id_parametro_calificacion = c.id_parametro_calificacion
                  AND c2.id_tenant = c.id_tenant
                  AND (c2.fecha_registro > c.fecha_registro
                       OR (c2.fecha_registro = c.fecha_registro AND c2.id > c.id))
          )
        ORDER BY c.fecha_registro ASC, c.id ASC";

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
            // Se ordena por fecha ascendente a proposito: el front recorre y
            // se queda con la coincidencia que encuentre, asi que si hubiera
            // duplicados la ultima en llegar es la que termina mostrandose
            $calificacionesPorEstudiante[$idEst][] = [
                'id' => $cal['id'],
                'id_parametro_calificacion' => $cal['id_parametro_calificacion'],
                'id_valor_parametro_calificacion' => $cal['id_valor_parametro_calificacion']
            ];
        }

        foreach ($estudiantes as &$est) {
            $idEst = $est['id_estudiante'];
            $est['calificaciones'] = isset($calificacionesPorEstudiante[$idEst])
                ? $calificacionesPorEstudiante[$idEst]
                : [];
            $est['presente'] = (int) $est['presente'];
        }
        unset($est);

        return $estudiantes;
    }

    /**
     * Vista de calificacion de una clase de curso extracurricular.
     *
     * Hermano de getVistaTarea: devuelve exactamente la misma estructura, pero
     * los estudiantes salen de la inscripcion al curso y no del grupo. Va
     * aparte y no como parametro opcional de getVistaTarea para no cambiarle la
     * firma al metodo que ya usa la pantalla de calificacion regular.
     */
    public static function getVistaTareaCursoExtra($id_curso_extra, $id_tarea_sprint)
    {
        try {
            $db = Flight::db();
            $db->exec("SET time_zone = '-05:00'");

            // 1. Estudiantes inscritos y activos en el curso + asistencia hoy + observacion de la tarea
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
                txse.observacion,
                -- Nivel del estudiante dentro del curso: es contra lo que
                -- apuntan los logros que le aplican en la clase.
                exce.id_nivel,
                niv.nombre as nombre_nivel,
                niv.orden as orden_nivel
            FROM estudiantes_x_cursos_extra exce
            INNER JOIN estudiantes e ON exce.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            LEFT JOIN niveles_area_academica niv ON exce.id_nivel = niv.id
            LEFT JOIN asistencia_estudiantes ae 
                ON ae.id_estudiante = e.id 
                AND DATE(ae.fecha_ingreso) = CURDATE() 
                AND ae.fecha_salida IS NULL
            LEFT JOIN tareas_x_sprints_x_estudiante txse
                ON txse.id_estudiante = e.id
                AND txse.id_tarea_x_sprint = :id_tarea_sprint
            WHERE exce.id_curso_extra = :id_curso_extra
              AND exce.activo = 1
              AND e.activo = 1
              AND exce.id_tenant = :id_tenant
            ORDER BY p.primer_nombre, p.primer_apellido";

            $stmt = $db->prepare($sqlEstudiantes);
            $stmt->bindParam(':id_curso_extra', $id_curso_extra);
            $stmt->bindParam(':id_tarea_sprint', $id_tarea_sprint);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Calificaciones de la tarea, anidadas a cada estudiante
            $estudiantes = self::anidarCalificaciones($db, $estudiantes, $id_tarea_sprint);

            Flight::json($estudiantes);
        } catch (Exception $e) {
            error_log('Error en getVistaTareaCursoExtra(): ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener vista de tarea del curso extracurricular'], 500);
        }
    }

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

            // 2. Calificaciones de la tarea, anidadas a cada estudiante
            $estudiantes = self::anidarCalificaciones($db, $estudiantes, $id_tarea_sprint);

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

        // Un estudiante solo puede tener una calificación por parámetro en cada
        // tarea. Si ya existe se actualiza el valor en vez de insertar otra fila,
        // así un doble clic o una llamada repetida no genera duplicados.
        $sentence = $db->prepare("SELECT id FROM calificaciones
                                  WHERE id_tarea_x_sprint = :id_tarea_x_sprint
                                    AND id_estudiante = :id_estudiante
                                    AND id_parametro_calificacion = :id_parametro_calificacion
                                    AND id_tenant = :id_tenant
                                  LIMIT 1");
        $sentence->bindParam(':id_tarea_x_sprint', $id_tarea_x_sprint);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_parametro_calificacion', $id_parametro_calificacion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $existente = $sentence->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            $sentence = $db->prepare("UPDATE calificaciones
                                      SET id_valor_parametro_calificacion = :id_valor
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_valor', $id_valor_parametro_calificacion);
            $sentence->bindValue(':id', $existente['id']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $existente['id']));
            return;
        }

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

    /**
     * Califica en lote a varios estudiantes con el mismo valor para un parámetro.
     *
     * Solo llena los vacíos: si el estudiante ya tiene calificación registrada
     * para ese parámetro en esa tarea, se respeta y no se sobreescribe.
     * Además crea el registro en tareas_x_sprints_x_estudiante si no existe,
     * igual que hace el flujo individual.
     *
     * Espera en el body:
     *   id_tarea_x_sprint, id_parametro_calificacion,
     *   id_valor_parametro_calificacion, estudiantes[] (ids)
     *
     * Devuelve las calificaciones creadas y los estudiantes que se omitieron
     * por ya tener nota, para que el front actualice la vista sin recargar.
     */
    public static function calificarLote()
    {
        $db = Flight::db();
        try {
            $id_tarea_x_sprint = Flight::request()->data['id_tarea_x_sprint'];
            $id_parametro_calificacion = Flight::request()->data['id_parametro_calificacion'];
            $id_valor_parametro_calificacion = Flight::request()->data['id_valor_parametro_calificacion'];
            $estudiantes = Flight::request()->data['estudiantes'];

            if (!$id_tarea_x_sprint || !$id_parametro_calificacion || !$id_valor_parametro_calificacion) {
                Flight::json(array('error' => 'Faltan datos para calificar en lote'), 400);
                return;
            }

            if (!is_array($estudiantes) || count($estudiantes) === 0) {
                Flight::json(array('creadas' => [], 'omitidas' => []));
                return;
            }

            $idTenant = TenantContext::id();

            // Calificaciones ya existentes para ese parámetro y esa tarea
            $sentence = $db->prepare("SELECT id, id_estudiante
                                      FROM calificaciones
                                      WHERE id_tarea_x_sprint = :id_tarea_x_sprint
                                        AND id_parametro_calificacion = :id_parametro_calificacion
                                        AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_tarea_x_sprint', $id_tarea_x_sprint);
            $sentence->bindParam(':id_parametro_calificacion', $id_parametro_calificacion);
            $sentence->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $sentence->execute();

            $existentes = [];
            foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $existentes[$fila['id_estudiante']] = $fila['id'];
            }

            // Registros de tareas_x_sprints_x_estudiante que ya existen
            $sentence = $db->prepare("SELECT id, id_estudiante
                                      FROM tareas_x_sprints_x_estudiante
                                      WHERE id_tarea_x_sprint = :id_tarea_x_sprint
                                        AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_tarea_x_sprint', $id_tarea_x_sprint);
            $sentence->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $sentence->execute();

            $tareasEstudiante = [];
            foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $tareasEstudiante[$fila['id_estudiante']] = $fila['id'];
            }

            $insertCalificacion = $db->prepare("INSERT INTO calificaciones(
                    id, id_tenant, id_tarea_x_sprint, id_estudiante,
                    id_parametro_calificacion, id_valor_parametro_calificacion
                ) VALUES (
                    :id, :id_tenant, :id_tarea_x_sprint, :id_estudiante,
                    :id_parametro_calificacion, :id_valor_parametro_calificacion
                )");

            $insertTareaEstudiante = $db->prepare("INSERT INTO tareas_x_sprints_x_estudiante(
                    id, id_tenant, id_tarea_x_sprint, id_estudiante
                ) VALUES (
                    :id, :id_tenant, :id_tarea_x_sprint, :id_estudiante
                )");

            $creadas = [];
            $omitidas = [];

            $db->beginTransaction();

            foreach ($estudiantes as $id_estudiante) {
                if (isset($existentes[$id_estudiante])) {
                    // Ya tenía nota: se respeta
                    $omitidas[] = array(
                        'id_estudiante' => $id_estudiante,
                        'id_calificacion' => $existentes[$id_estudiante]
                    );
                    continue;
                }

                $idNew = Uuid::generar();
                $insertCalificacion->bindValue(':id', $idNew);
                $insertCalificacion->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $insertCalificacion->bindParam(':id_tarea_x_sprint', $id_tarea_x_sprint);
                $insertCalificacion->bindParam(':id_estudiante', $id_estudiante);
                $insertCalificacion->bindParam(':id_parametro_calificacion', $id_parametro_calificacion);
                $insertCalificacion->bindParam(':id_valor_parametro_calificacion', $id_valor_parametro_calificacion);
                $insertCalificacion->execute();

                $idTareaEstudiante = isset($tareasEstudiante[$id_estudiante])
                    ? $tareasEstudiante[$id_estudiante]
                    : null;

                if ($idTareaEstudiante === null) {
                    $idTareaEstudiante = Uuid::generar();
                    $insertTareaEstudiante->bindValue(':id', $idTareaEstudiante);
                    $insertTareaEstudiante->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $insertTareaEstudiante->bindParam(':id_tarea_x_sprint', $id_tarea_x_sprint);
                    $insertTareaEstudiante->bindParam(':id_estudiante', $id_estudiante);
                    $insertTareaEstudiante->execute();
                    $tareasEstudiante[$id_estudiante] = $idTareaEstudiante;
                }

                $creadas[] = array(
                    'id_estudiante' => $id_estudiante,
                    'id_calificacion' => $idNew,
                    'id_tarea_estudiante' => $idTareaEstudiante
                );
            }

            $db->commit();

            Flight::json(array(
                'creadas' => $creadas,
                'omitidas' => $omitidas
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en calificarLote: " . $e->getMessage());
            Flight::json(array('error' => 'Error al calificar en lote: ' . $e->getMessage()), 500);
        }
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
                    CONCAT_WS(' ',
                        p.primer_nombre,
                        p.segundo_nombre,
                        p.primer_apellido,
                        p.segundo_apellido
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
                    CONCAT_WS(' ',
                        pd.primer_nombre,
                        pd.segundo_nombre,
                        pd.primer_apellido,
                        pd.segundo_apellido
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
                if ((int)$a['orden'] != (int)$b['orden']) {
                    return (int)$a['orden'] - (int)$b['orden'];
                }

                $cmp = strcmp($a['nombre_completo_estudiante'], $b['nombre_completo_estudiante']);
                if ($cmp != 0) {
                    return $cmp;
                }

                $cmp = strcmp($a['area_academica_nombre'], $b['area_academica_nombre']);
                if ($cmp != 0) {
                    return $cmp;
                }

                /* id_tarea_x_sprint es UUID (CHAR(36)) tras la migracion INT -> UUID.
                   Restar dos strings lanza TypeError en PHP 8; se compara como texto. */
                return strcmp($a['id_tarea_x_sprint'], $b['id_tarea_x_sprint']);
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
    /**
     * Reporte de calificaciones por actividad entre dos fechas
     * (query: fecha_inicio, fecha_fin en formato YYYY-MM-DD).
     *
     * Actividades: todas las de grupo y de cursos extracurriculares ejecutadas
     * en el rango, más las pendientes que ya tengan alguna calificación. La
     * fecha que se compara es la de ejecución; si no la tiene, la de inicio
     * del sprint.
     * Estudiantes de cada actividad:
     *  - De grupo: los que estaban en el grupo en esa fecha, según las fechas
     *    de estudiantes_x_grupos. Una fila inactiva y sin fecha_fin no se toma
     *    por fecha (no se sabe hasta cuándo estuvo).
     *  - Extracurricular: la misma lógica de la lista de actividades
     *    (inscritos activos en el curso), limitada a los que ya estaban
     *    inscritos en esa fecha.
     *  - En los dos casos se suma cualquiera que tenga una calificación en la
     *    actividad, aunque ya se haya cambiado de grupo, retirado o
     *    desinscrito.
     * De cada estudiante, actividad y parámetro se toma la calificación más
     * reciente, igual que en anidarCalificaciones, por los duplicados viejos.
     *
     * Devuelve { parametros: [{id, nombre}], filas: [...] }. Cada fila trae en
     * 'calificaciones' un mapa id_parametro => {valor_cuantitativo, valor_cualitativo}.
     */
    public static function getReporteCalificacionesActividades()
    {
        try {
            $fechaInicio = Flight::request()->query['fecha_inicio'] ?? '';
            $fechaFin = Flight::request()->query['fecha_fin'] ?? '';
            $formato = '/^\d{4}-\d{2}-\d{2}$/';
            if (!preg_match($formato, $fechaInicio) || !preg_match($formato, $fechaFin)) {
                Flight::json(['error' => 'Selecciona la fecha inicial y la final'], 400);
                return;
            }
            if ($fechaInicio > $fechaFin) {
                Flight::json(['error' => 'La fecha inicial no puede ser mayor que la final'], 400);
                return;
            }

            $db = Flight::db();
            $idTenant = TenantContext::id();

            // Condición de las actividades que entran al reporte. Se repite en
            // varias consultas con un sufijo en los parámetros, porque PDO con
            // prepares nativos no deja repetir un mismo nombre.
            $condicionActividades = function ($sufijo) {
                return "txs.id_tenant = :id_tenant_$sufijo
                  AND (txs.id_estado_tarea = 2
                       OR EXISTS (SELECT 1 FROM calificaciones cx
                                  WHERE cx.id_tarea_x_sprint = txs.id
                                    AND cx.id_tenant = txs.id_tenant))
                  AND DATE(COALESCE(txs.fecha_ejecucion, s.fecha_inicial)) BETWEEN :fecha_inicio_$sufijo AND :fecha_fin_$sufijo";
            };
            $enlazarCondicion = function ($stmt, $sufijo) use ($idTenant, $fechaInicio, $fechaFin) {
                $stmt->bindValue(":id_tenant_$sufijo", $idTenant, PDO::PARAM_INT);
                $stmt->bindValue(":fecha_inicio_$sufijo", $fechaInicio);
                $stmt->bindValue(":fecha_fin_$sufijo", $fechaFin);
            };

            // 1. Parámetros de calificación del jardín (definen las columnas)
            $stmt = $db->prepare("SELECT id, nombre
                FROM parametros_calificaciones
                WHERE id_tenant = :id_tenant
                ORDER BY nombre");
            $stmt->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $stmt->execute();
            $parametros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Actividades del rango (de grupo y extracurriculares)
            $stmt = $db->prepare("SELECT
                    txs.id AS id_tarea_x_sprint,
                    txs.fecha_ejecucion,
                    s.nombre_sprint,
                    aa.titulo AS titulo_actividad,
                    ar.nombre AS nombre_area,
                    txs.id_curso_extra,
                    COALESCE(g.nombre, ce.nombre) AS nombre_grupo_curso,
                    -- Quien la ejecutó: el docente que la inició y, si no quedó, el que la finalizó
                    COALESCE(
                        NULLIF(TRIM(CONCAT_WS(' ', pdi.primer_nombre, pdi.primer_apellido)), ''),
                        NULLIF(TRIM(CONCAT_WS(' ', pdf.primer_nombre, pdf.primer_apellido)), '')
                    ) AS ejecutada_por
                FROM tareas_x_sprints txs
                INNER JOIN sprints s ON s.id = txs.id_sprint
                INNER JOIN actividades_academicas aa ON aa.id = txs.id_actividad_academica
                LEFT JOIN grupos g ON g.id = txs.id_grupo
                LEFT JOIN cursos_extra ce ON ce.id = txs.id_curso_extra
                LEFT JOIN areas_academicas ar ON ar.id = COALESCE(txs.id_area_academica, ce.id_area_academica)
                LEFT JOIN docentes di ON di.id = txs.id_docente_inicia
                LEFT JOIN personas pdi ON pdi.id = di.id_persona
                LEFT JOIN docentes df ON df.id = txs.id_docente
                LEFT JOIN personas pdf ON pdf.id = df.id_persona
                WHERE " . $condicionActividades('act') . "
                ORDER BY DATE(COALESCE(txs.fecha_ejecucion, s.fecha_inicial)), txs.fecha_ejecucion, aa.titulo");
            $enlazarCondicion($stmt, 'act');
            $stmt->execute();
            $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Estudiantes de cada actividad
            $stmt = $db->prepare("SELECT
                    x.id_tarea_x_sprint,
                    e.id AS id_estudiante,
                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_estudiante
                FROM (
                    -- De grupo: los que estaban en el grupo ese día
                    SELECT txs.id AS id_tarea_x_sprint, exg.id_estudiante
                    FROM tareas_x_sprints txs
                    INNER JOIN sprints s ON s.id = txs.id_sprint
                    INNER JOIN estudiantes_x_grupos exg
                        ON exg.id_grupo = txs.id_grupo
                       AND exg.id_tenant = txs.id_tenant
                       AND exg.fecha_inicio <= DATE(COALESCE(txs.fecha_ejecucion, s.fecha_inicial))
                       AND (exg.fecha_fin IS NULL OR exg.fecha_fin >= DATE(COALESCE(txs.fecha_ejecucion, s.fecha_inicial)))
                       AND (exg.activo = 1 OR exg.fecha_fin IS NOT NULL)
                    WHERE " . $condicionActividades('grp') . "
                    UNION
                    -- Extracurricular: inscritos activos, como en la lista de actividades
                    SELECT txs.id AS id_tarea_x_sprint, exce.id_estudiante
                    FROM tareas_x_sprints txs
                    INNER JOIN sprints s ON s.id = txs.id_sprint
                    INNER JOIN estudiantes_x_cursos_extra exce
                        ON exce.id_curso_extra = txs.id_curso_extra
                       AND exce.id_tenant = txs.id_tenant
                       AND exce.activo = 1
                       AND exce.fecha_inscripcion <= DATE(COALESCE(txs.fecha_ejecucion, s.fecha_inicial))
                    INNER JOIN estudiantes ee ON ee.id = exce.id_estudiante AND ee.activo = 1
                    WHERE " . $condicionActividades('ext') . "
                    UNION
                    -- Cualquiera que tenga calificación en la actividad
                    SELECT c.id_tarea_x_sprint, c.id_estudiante
                    FROM calificaciones c
                    INNER JOIN tareas_x_sprints txs ON txs.id = c.id_tarea_x_sprint
                    INNER JOIN sprints s ON s.id = txs.id_sprint
                    WHERE c.id_tenant = txs.id_tenant
                      AND " . $condicionActividades('cal') . "
                ) x
                INNER JOIN estudiantes e ON e.id = x.id_estudiante
                INNER JOIN personas p ON p.id = e.id_persona
                ORDER BY p.primer_nombre, p.primer_apellido");
            $enlazarCondicion($stmt, 'grp');
            $enlazarCondicion($stmt, 'ext');
            $enlazarCondicion($stmt, 'cal');
            $stmt->execute();

            // Estudiantes en orden alfabético y, para cada uno, sus actividades
            $estudiantes = [];
            $actividadesPorEstudiante = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $registro) {
                $idEstudiante = $registro['id_estudiante'];
                if (!isset($estudiantes[$idEstudiante])) {
                    $estudiantes[$idEstudiante] = $registro['nombre_estudiante'];
                }
                $actividadesPorEstudiante[$idEstudiante][$registro['id_tarea_x_sprint']] = true;
            }

            // 4. Calificaciones de esas actividades (la más reciente por combinación)
            $stmt = $db->prepare("SELECT
                    c.id_tarea_x_sprint,
                    c.id_estudiante,
                    c.id_parametro_calificacion,
                    v.valor_cuantitativo,
                    v.valor_cualitativo
                FROM calificaciones c
                INNER JOIN tareas_x_sprints txs ON txs.id = c.id_tarea_x_sprint
                INNER JOIN sprints s ON s.id = txs.id_sprint
                INNER JOIN valores_parametros_calificaciones v ON v.id = c.id_valor_parametro_calificacion
                WHERE c.id_tenant = txs.id_tenant
                  AND " . $condicionActividades('val') . "
                  AND NOT EXISTS (
                        SELECT 1 FROM calificaciones c2
                        WHERE c2.id_tarea_x_sprint = c.id_tarea_x_sprint
                          AND c2.id_estudiante = c.id_estudiante
                          AND c2.id_parametro_calificacion = c.id_parametro_calificacion
                          AND c2.id_tenant = c.id_tenant
                          AND (c2.fecha_registro > c.fecha_registro
                               OR (c2.fecha_registro = c.fecha_registro AND c2.id > c.id))
                  )");
            $enlazarCondicion($stmt, 'val');
            $stmt->execute();

            $calificaciones = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $cal) {
                $calificaciones[$cal['id_tarea_x_sprint']][$cal['id_estudiante']][$cal['id_parametro_calificacion']] = [
                    'valor_cuantitativo' => $cal['valor_cuantitativo'],
                    'valor_cualitativo' => $cal['valor_cualitativo']
                ];
            }

            // 5. Una fila por estudiante y actividad en la que estuvo
            $filas = [];
            foreach ($estudiantes as $idEstudiante => $nombreEstudiante) {
                foreach ($actividades as $actividad) {
                    $idTarea = $actividad['id_tarea_x_sprint'];
                    if (!isset($actividadesPorEstudiante[$idEstudiante][$idTarea])) {
                        continue;
                    }
                    $filas[] = [
                        'id' => $idTarea . '_' . $idEstudiante,
                        'id_estudiante' => $idEstudiante,
                        'nombre_estudiante' => $nombreEstudiante,
                        'id_tarea_x_sprint' => $idTarea,
                        'tipo' => $actividad['id_curso_extra'] ? 'Extracurricular' : 'Grupo',
                        'nombre_grupo_curso' => $actividad['nombre_grupo_curso'],
                        'nombre_sprint' => $actividad['nombre_sprint'],
                        'fecha_ejecucion' => $actividad['fecha_ejecucion'],
                        'nombre_area' => $actividad['nombre_area'],
                        'titulo_actividad' => $actividad['titulo_actividad'],
                        'ejecutada_por' => $actividad['ejecutada_por'],
                        'calificaciones' => $calificaciones[$idTarea][$idEstudiante] ?? new stdClass()
                    ];
                }
            }

            Flight::json([
                'parametros' => $parametros,
                'filas' => $filas
            ]);
        } catch (Exception $e) {
            error_log('Error en getReporteCalificacionesActividades(): ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener el reporte de calificaciones por actividad'], 500);
        }
    }

    // ===================================================================
    // CALIFICACION RAPIDA
    // Pantalla de Operaciones > Actividades: por fecha y grupo se califica
    // a los estudiantes que tuvieron asistencia ese dia, en las franjas del
    // horario (o en areas agregadas fuera del horario). Solo trabaja sobre
    // el sprint actual y solo para grupos, no para cursos extracurriculares.
    // ===================================================================

    /** Sprint actual del tenant, o null si no hay ninguno marcado. */
    private static function sprintActualRapida($db)
    {
        $stmt = $db->prepare("SELECT id, nombre_sprint, fecha_inicial, fecha_final
            FROM sprints
            WHERE actual = 1 AND id_tenant = :id_tenant
            LIMIT 1");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $sprint = $stmt->fetch(PDO::FETCH_ASSOC);
        return $sprint ?: null;
    }

    /**
     * Valida que la fecha tenga formato YYYY-MM-DD, que este dentro del sprint
     * actual y que no sea futura. Devuelve el mensaje de error o null.
     */
    private static function validarFechaRapida($fecha, $sprint)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fecha) || !strtotime($fecha)) {
            return 'Selecciona una fecha valida';
        }
        if ($fecha < $sprint['fecha_inicial'] || $fecha > $sprint['fecha_final']) {
            return 'La fecha debe estar dentro del sprint actual (' . $sprint['fecha_inicial'] . ' a ' . $sprint['fecha_final'] . ')';
        }
        if ($fecha > date('Y-m-d')) {
            return 'No se puede calificar una fecha futura';
        }
        return null;
    }

    /** Hora en formato HH:mm o HH:mm:ss; se devuelve como HH:mm:ss o null si no sirve. */
    private static function normalizarHoraRapida($hora)
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', trim((string) $hora), $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }
        return sprintf('%02d:%02d:00', $h, $min);
    }

    /**
     * Vista de la calificacion rapida de un grupo en una fecha (?fecha=YYYY-MM-DD).
     *
     * Devuelve en una sola llamada:
     *  - sprint: el sprint actual (la fecha debe caer dentro de el).
     *  - estudiantes: activos del grupo con algun registro de asistencia ese
     *    dia, hayan salido o no.
     *  - franjas: las del horario del grupo para el dia de la semana de la
     *    fecha, ordenadas por hora, con el docente asignado al area en el grupo.
     *  - tareas: las del grupo en el sprint actual que estan pendientes, mas
     *    las que se ejecutaron (o se iniciaron) en esa fecha, para poder
     *    corregirlas. es_del_dia marca estas ultimas.
     *  - calificaciones: la mas reciente por estudiante, tarea y parametro de
     *    las tareas del dia (por los duplicados viejos, igual que en
     *    anidarCalificaciones).
     *  - participaciones: los registros de tareas_x_sprints_x_estudiante de las
     *    tareas del dia, para saber a quien se le asigno la actividad aunque
     *    no tenga nota.
     */
    public static function getVistaCalificacionRapida($id_grupo)
    {
        try {
            $db = Flight::db();
            $idTenant = TenantContext::id();
            $fecha = Flight::request()->query['fecha'] ?? '';

            $sprint = self::sprintActualRapida($db);
            if (!$sprint) {
                Flight::json(['error' => 'No hay un sprint actual configurado'], 400);
                return;
            }

            $errorFecha = self::validarFechaRapida($fecha, $sprint);
            if ($errorFecha) {
                Flight::json(['error' => $errorFecha, 'sprint' => $sprint], 400);
                return;
            }

            // 1. Estudiantes activos del grupo con asistencia en la fecha
            $stmt = $db->prepare("SELECT
                    e.id AS id_estudiante,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    (SELECT MIN(ae.fecha_ingreso) FROM asistencia_estudiantes ae
                      WHERE ae.id_estudiante = e.id AND ae.id_tenant = exg.id_tenant
                        AND DATE(ae.fecha_ingreso) = :fecha_ing) AS fecha_ingreso,
                    (SELECT MAX(ae.fecha_salida) FROM asistencia_estudiantes ae
                      WHERE ae.id_estudiante = e.id AND ae.id_tenant = exg.id_tenant
                        AND DATE(ae.fecha_ingreso) = :fecha_sal) AS fecha_salida
                FROM estudiantes_x_grupos exg
                INNER JOIN estudiantes e ON e.id = exg.id_estudiante
                INNER JOIN personas p ON p.id = e.id_persona
                WHERE exg.id_grupo = :id_grupo
                  AND exg.activo = 1
                  AND e.activo = 1
                  AND exg.id_tenant = :id_tenant
                  AND EXISTS (SELECT 1 FROM asistencia_estudiantes ae
                               WHERE ae.id_estudiante = e.id
                                 AND ae.id_tenant = exg.id_tenant
                                 AND DATE(ae.fecha_ingreso) = :fecha_exi)
                ORDER BY p.primer_nombre, p.primer_apellido");
            $stmt->bindValue(':fecha_ing', $fecha);
            $stmt->bindValue(':fecha_sal', $fecha);
            $stmt->bindValue(':fecha_exi', $fecha);
            $stmt->bindValue(':id_grupo', $id_grupo);
            $stmt->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $stmt->execute();
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Franjas del horario para el dia de la semana (1 = lunes ... 7 = domingo)
            $diaSemana = (int) date('N', strtotime($fecha));
            $stmt = $db->prepare("SELECT
                    h.id AS id_horario,
                    h.id_area_academica,
                    a.nombre AS nombre_area,
                    a.color AS color_area,
                    a.icono AS icono_area,
                    h.nombre_franja,
                    h.hora_inicial,
                    h.hora_final,
                    h.total_minutos,
                    (SELECT axg.id_docente FROM area_academica_x_grupo axg
                      WHERE axg.id_area_academica = h.id_area_academica
                        AND axg.id_grupo = h.id_grupo
                        AND axg.id_tenant = h.id_tenant
                      LIMIT 1) AS id_docente_area
                FROM horarios h
                INNER JOIN areas_academicas a ON a.id = h.id_area_academica
                WHERE h.id_grupo = :id_grupo
                  AND h.id_dia_semana = :dia
                  AND h.id_tenant = :id_tenant
                ORDER BY h.hora_inicial, h.hora_final");
            $stmt->bindValue(':id_grupo', $id_grupo);
            $stmt->bindValue(':dia', $diaSemana, PDO::PARAM_INT);
            $stmt->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $stmt->execute();
            $franjas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Tareas del grupo en el sprint actual: pendientes y las del dia.
            // La fecha del dia es la de ejecucion o, si aun no se finalizo, la de inicio.
            $stmt = $db->prepare("SELECT
                    txs.id AS id_tarea_x_sprint,
                    txs.id_area_academica,
                    txs.id_estado_tarea,
                    txs.orden_ejecucion,
                    txs.id_docente,
                    txs.id_docente_inicia,
                    txs.id_horario,
                    txs.hora_inicial_horario,
                    txs.hora_final_horario,
                    txs.fecha_ejecucion,
                    txs.fecha_ejecucion_inicia,
                    aa.id AS id_actividad_academica,
                    aa.titulo,
                    aa.minutos_duracion,
                    CASE WHEN DATE(COALESCE(txs.fecha_ejecucion, txs.fecha_ejecucion_inicia)) = :fecha_flag
                         THEN 1 ELSE 0 END AS es_del_dia
                FROM tareas_x_sprints txs
                INNER JOIN actividades_academicas aa ON aa.id = txs.id_actividad_academica
                WHERE txs.id_grupo = :id_grupo
                  AND txs.id_sprint = :id_sprint
                  AND txs.id_tenant = :id_tenant
                  AND (txs.id_estado_tarea = 1
                       OR (txs.id_estado_tarea = 2
                           AND DATE(COALESCE(txs.fecha_ejecucion, txs.fecha_ejecucion_inicia)) = :fecha_ejec))
                ORDER BY txs.orden_ejecucion ASC, aa.titulo ASC");
            $stmt->bindValue(':fecha_flag', $fecha);
            $stmt->bindValue(':fecha_ejec', $fecha);
            $stmt->bindValue(':id_grupo', $id_grupo);
            $stmt->bindValue(':id_sprint', $sprint['id']);
            $stmt->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $stmt->execute();
            $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $idsTareasDia = [];
            foreach ($tareas as &$tarea) {
                $tarea['id_estado_tarea'] = (int) $tarea['id_estado_tarea'];
                $tarea['es_del_dia'] = (int) $tarea['es_del_dia'];
                if ($tarea['es_del_dia'] === 1) {
                    $idsTareasDia[] = $tarea['id_tarea_x_sprint'];
                }
            }
            unset($tarea);

            // 4. Calificaciones y participaciones de las tareas del dia
            $calificaciones = [];
            $participaciones = [];
            if (!empty($idsTareasDia)) {
                $marcas = implode(',', array_fill(0, count($idsTareasDia), '?'));

                $stmt = $db->prepare("SELECT
                        c.id,
                        c.id_tarea_x_sprint,
                        c.id_estudiante,
                        c.id_parametro_calificacion,
                        c.id_valor_parametro_calificacion
                    FROM calificaciones c
                    WHERE c.id_tarea_x_sprint IN ($marcas)
                      AND c.id_tenant = ?
                      AND NOT EXISTS (
                            SELECT 1 FROM calificaciones c2
                            WHERE c2.id_tarea_x_sprint = c.id_tarea_x_sprint
                              AND c2.id_estudiante = c.id_estudiante
                              AND c2.id_parametro_calificacion = c.id_parametro_calificacion
                              AND c2.id_tenant = c.id_tenant
                              AND (c2.fecha_registro > c.fecha_registro
                                   OR (c2.fecha_registro = c.fecha_registro AND c2.id > c.id))
                      )");
                $stmt->execute(array_merge($idsTareasDia, [$idTenant]));
                $calificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $db->prepare("SELECT id, id_tarea_x_sprint, id_estudiante
                    FROM tareas_x_sprints_x_estudiante
                    WHERE id_tarea_x_sprint IN ($marcas)
                      AND id_tenant = ?");
                $stmt->execute(array_merge($idsTareasDia, [$idTenant]));
                $participaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            Flight::json([
                'sprint' => $sprint,
                'fecha' => $fecha,
                'dia_semana' => $diaSemana,
                'estudiantes' => $estudiantes,
                'franjas' => $franjas,
                'tareas' => $tareas,
                'calificaciones' => $calificaciones,
                'participaciones' => $participaciones
            ]);
        } catch (Exception $e) {
            error_log('Error en getVistaCalificacionRapida(): ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener la vista de calificacion rapida'], 500);
        }
    }

    /**
     * Guarda en bloque la calificacion rapida de un grupo en una fecha.
     *
     * Espera en el body:
     *   fecha, id_grupo,
     *   franjas[]: { id_horario (null si es un area fuera del horario),
     *                id_area_academica, hora_inicial, hora_final, id_docente,
     *                celdas[]: { id_estudiante, id_tarea_x_sprint (null si se quito),
     *                            id_tarea_anterior (la que tenia al cargar, o null),
     *                            calificaciones[]: { id_parametro_calificacion,
     *                                                id_valor_parametro_calificacion } } }
     *
     * Por cada celda:
     *  - Si el estudiante tenia otra actividad en la franja y se la cambiaron o
     *    se la quitaron, se borran sus calificaciones de la anterior (y su
     *    registro en tareas_x_sprints_x_estudiante si no tiene observacion).
     *  - Si tiene actividad, se crea su registro en tareas_x_sprints_x_estudiante
     *    si no existe y cada calificacion se actualiza (la mas reciente) o se crea.
     * Cada actividad usada queda Ejecutada, con la fecha escogida y la hora
     *   inicial de la franja como fecha de ejecucion, el docente de la franja y
     *   los datos del horario.
     *
     * Todo va en una transaccion: si algo falla no queda nada a medias.
     */
    public static function guardarCalificacionRapida()
    {
        $db = Flight::db();
        try {
            $idTenant = TenantContext::id();
            $data = Flight::request()->data;
            $fecha = $data['fecha'] ?? '';
            $id_grupo = $data['id_grupo'] ?? '';
            $franjas = $data['franjas'] ?? [];

            if (!$id_grupo || !is_array($franjas) || count($franjas) === 0) {
                Flight::json(['error' => 'No hay nada para guardar'], 400);
                return;
            }

            $sprint = self::sprintActualRapida($db);
            if (!$sprint) {
                Flight::json(['error' => 'No hay un sprint actual configurado'], 400);
                return;
            }

            $errorFecha = self::validarFechaRapida($fecha, $sprint);
            if ($errorFecha) {
                Flight::json(['error' => $errorFecha], 400);
                return;
            }

            // --- Recolectar ids para validar todo de una sola vez ---
            $idsTareas = [];
            $idsEstudiantes = [];
            $idsDocentes = [];
            $idsValores = [];
            $tareaEnFranja = [];

            foreach ($franjas as $i => $franja) {
                $numero = $i + 1;
                if (empty($franja['id_area_academica'])) {
                    Flight::json(['error' => "La franja $numero no tiene area"], 400);
                    return;
                }
                $horaInicial = self::normalizarHoraRapida($franja['hora_inicial'] ?? '');
                $horaFinal = self::normalizarHoraRapida($franja['hora_final'] ?? '');
                if (!$horaInicial || !$horaFinal || $horaFinal <= $horaInicial) {
                    Flight::json(['error' => "La franja $numero no tiene una hora inicial y final validas"], 400);
                    return;
                }
                $franjas[$i]['hora_inicial'] = $horaInicial;
                $franjas[$i]['hora_final'] = $horaFinal;

                $usaTarea = false;
                foreach (($franja['celdas'] ?? []) as $celda) {
                    if (empty($celda['id_estudiante'])) {
                        continue;
                    }
                    $idsEstudiantes[$celda['id_estudiante']] = true;
                    if (!empty($celda['id_tarea_x_sprint'])) {
                        $usaTarea = true;
                        $idTarea = $celda['id_tarea_x_sprint'];
                        $idsTareas[$idTarea] = true;
                        // Una actividad no puede quedar en dos franjas: la
                        // fecha y hora de ejecucion son una sola.
                        if (isset($tareaEnFranja[$idTarea]) && $tareaEnFranja[$idTarea] !== $i) {
                            Flight::json(['error' => 'Una misma actividad quedo en dos franjas. Deja cada actividad en una sola franja.'], 400);
                            return;
                        }
                        $tareaEnFranja[$idTarea] = $i;
                        foreach (($celda['calificaciones'] ?? []) as $cal) {
                            if (!empty($cal['id_valor_parametro_calificacion'])) {
                                $idsValores[$cal['id_valor_parametro_calificacion']] = true;
                            }
                        }
                    }
                    if (!empty($celda['id_tarea_anterior'])) {
                        $idsTareas[$celda['id_tarea_anterior']] = true;
                    }
                }

                if ($usaTarea) {
                    if (empty($franja['id_docente'])) {
                        Flight::json(['error' => "Selecciona el docente de la franja $numero"], 400);
                        return;
                    }
                    $idsDocentes[$franja['id_docente']] = true;
                }
            }

            // Tareas: deben ser del grupo, del sprint actual y no estar canceladas
            $tareasValidas = [];
            if (!empty($idsTareas)) {
                $lista = array_keys($idsTareas);
                $marcas = implode(',', array_fill(0, count($lista), '?'));
                $stmt = $db->prepare("SELECT id, id_area_academica
                    FROM tareas_x_sprints
                    WHERE id IN ($marcas)
                      AND id_grupo = ?
                      AND id_sprint = ?
                      AND id_estado_tarea IN (1, 2)
                      AND id_tenant = ?");
                $stmt->execute(array_merge($lista, [$id_grupo, $sprint['id'], $idTenant]));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                    $tareasValidas[$fila['id']] = $fila['id_area_academica'];
                }
                if (count($tareasValidas) !== count($lista)) {
                    Flight::json(['error' => 'Alguna actividad ya no esta disponible en el sprint actual. Recarga la pantalla.'], 400);
                    return;
                }
            }
            // La actividad tiene que ser del area de la franja
            foreach ($tareaEnFranja as $idTarea => $i) {
                if ($tareasValidas[$idTarea] != $franjas[$i]['id_area_academica']) {
                    Flight::json(['error' => 'Hay una actividad que no corresponde al area de su franja. Recarga la pantalla.'], 400);
                    return;
                }
            }

            // Estudiantes: activos en el grupo
            if (!empty($idsEstudiantes)) {
                $lista = array_keys($idsEstudiantes);
                $marcas = implode(',', array_fill(0, count($lista), '?'));
                $stmt = $db->prepare("SELECT COUNT(DISTINCT exg.id_estudiante) AS total
                    FROM estudiantes_x_grupos exg
                    WHERE exg.id_estudiante IN ($marcas)
                      AND exg.id_grupo = ?
                      AND exg.activo = 1
                      AND exg.id_tenant = ?");
                $stmt->execute(array_merge($lista, [$id_grupo, $idTenant]));
                $fila = $stmt->fetch(PDO::FETCH_ASSOC);
                if ((int) $fila['total'] !== count($lista)) {
                    Flight::json(['error' => 'Algun estudiante ya no esta activo en el grupo. Recarga la pantalla.'], 400);
                    return;
                }
            }

            // Docentes del tenant
            if (!empty($idsDocentes)) {
                $lista = array_keys($idsDocentes);
                $marcas = implode(',', array_fill(0, count($lista), '?'));
                $stmt = $db->prepare("SELECT COUNT(*) AS total FROM docentes WHERE id IN ($marcas) AND id_tenant = ?");
                $stmt->execute(array_merge($lista, [$idTenant]));
                $fila = $stmt->fetch(PDO::FETCH_ASSOC);
                if ((int) $fila['total'] !== count($lista)) {
                    Flight::json(['error' => 'Algun docente seleccionado no existe. Recarga la pantalla.'], 400);
                    return;
                }
            }

            // Valores: deben existir y pertenecer al parametro con el que llegan
            $parametroDelValor = [];
            if (!empty($idsValores)) {
                $lista = array_keys($idsValores);
                $marcas = implode(',', array_fill(0, count($lista), '?'));
                $stmt = $db->prepare("SELECT id, id_parametros_calificaciones
                    FROM valores_parametros_calificaciones
                    WHERE id IN ($marcas) AND id_tenant = ?");
                $stmt->execute(array_merge($lista, [$idTenant]));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                    $parametroDelValor[$fila['id']] = $fila['id_parametros_calificaciones'];
                }
            }

            // --- Estado actual de lo que se va a tocar ---
            $calificacionesActuales = [];
            $participacionesActuales = [];
            if (!empty($idsTareas)) {
                $lista = array_keys($idsTareas);
                $marcas = implode(',', array_fill(0, count($lista), '?'));

                // La mas reciente por combinacion es la que se actualiza
                $stmt = $db->prepare("SELECT c.id, c.id_tarea_x_sprint, c.id_estudiante,
                        c.id_parametro_calificacion, c.id_valor_parametro_calificacion
                    FROM calificaciones c
                    WHERE c.id_tarea_x_sprint IN ($marcas)
                      AND c.id_tenant = ?
                      AND NOT EXISTS (
                            SELECT 1 FROM calificaciones c2
                            WHERE c2.id_tarea_x_sprint = c.id_tarea_x_sprint
                              AND c2.id_estudiante = c.id_estudiante
                              AND c2.id_parametro_calificacion = c.id_parametro_calificacion
                              AND c2.id_tenant = c.id_tenant
                              AND (c2.fecha_registro > c.fecha_registro
                                   OR (c2.fecha_registro = c.fecha_registro AND c2.id > c.id))
                      )");
                $stmt->execute(array_merge($lista, [$idTenant]));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                    $calificacionesActuales[$fila['id_tarea_x_sprint']][$fila['id_estudiante']][$fila['id_parametro_calificacion']] = $fila;
                }

                $stmt = $db->prepare("SELECT id, id_tarea_x_sprint, id_estudiante
                    FROM tareas_x_sprints_x_estudiante
                    WHERE id_tarea_x_sprint IN ($marcas) AND id_tenant = ?");
                $stmt->execute(array_merge($lista, [$idTenant]));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                    $participacionesActuales[$fila['id_tarea_x_sprint']][$fila['id_estudiante']] = $fila['id'];
                }
            }

            $borrarCalificaciones = $db->prepare("DELETE FROM calificaciones
                WHERE id_tarea_x_sprint = :id_tarea AND id_estudiante = :id_estudiante AND id_tenant = :id_tenant");
            $borrarParticipacion = $db->prepare("DELETE FROM tareas_x_sprints_x_estudiante
                WHERE id_tarea_x_sprint = :id_tarea AND id_estudiante = :id_estudiante AND id_tenant = :id_tenant
                  AND (observacion IS NULL OR TRIM(observacion) = '')");
            $insertarParticipacion = $db->prepare("INSERT INTO tareas_x_sprints_x_estudiante
                (id, id_tenant, id_tarea_x_sprint, id_estudiante)
                VALUES (:id, :id_tenant, :id_tarea, :id_estudiante)");
            $insertarCalificacion = $db->prepare("INSERT INTO calificaciones
                (id, id_tenant, id_tarea_x_sprint, id_estudiante, id_parametro_calificacion, id_valor_parametro_calificacion)
                VALUES (:id, :id_tenant, :id_tarea, :id_estudiante, :id_parametro, :id_valor)");
            $actualizarCalificacion = $db->prepare("UPDATE calificaciones
                SET id_valor_parametro_calificacion = :id_valor
                WHERE id = :id AND id_tenant = :id_tenant");

            $creadas = 0;
            $actualizadas = 0;
            $eliminadas = 0;

            // Lo que queda asignado despues de guardar. Sirve para no borrar lo
            // de una actividad que el estudiante sigue teniendo en otra celda.
            $asignados = [];
            foreach ($franjas as $franja) {
                foreach (($franja['celdas'] ?? []) as $celda) {
                    if (!empty($celda['id_estudiante']) && !empty($celda['id_tarea_x_sprint'])) {
                        $asignados[$celda['id_tarea_x_sprint']][$celda['id_estudiante']] = true;
                    }
                }
            }

            $db->beginTransaction();

            // Primera pasada: se quita lo de las actividades que se cambiaron o
            // se retiraron. Va antes de grabar para que el orden de las franjas
            // no haga borrar algo que se acaba de crear.
            foreach ($franjas as $franja) {
                foreach (($franja['celdas'] ?? []) as $celda) {
                    $idEstudiante = $celda['id_estudiante'] ?? '';
                    $idTarea = $celda['id_tarea_x_sprint'] ?? null;
                    $idAnterior = $celda['id_tarea_anterior'] ?? null;

                    if ($idEstudiante && $idAnterior && $idAnterior !== $idTarea
                        && !isset($asignados[$idAnterior][$idEstudiante])) {
                        $borrarCalificaciones->bindValue(':id_tarea', $idAnterior);
                        $borrarCalificaciones->bindValue(':id_estudiante', $idEstudiante);
                        $borrarCalificaciones->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                        $borrarCalificaciones->execute();
                        $eliminadas += $borrarCalificaciones->rowCount();

                        $borrarParticipacion->bindValue(':id_tarea', $idAnterior);
                        $borrarParticipacion->bindValue(':id_estudiante', $idEstudiante);
                        $borrarParticipacion->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                        $borrarParticipacion->execute();

                        unset($calificacionesActuales[$idAnterior][$idEstudiante]);
                        unset($participacionesActuales[$idAnterior][$idEstudiante]);
                    }
                }
            }

            // Segunda pasada: participacion y calificaciones de lo asignado
            foreach ($franjas as $franja) {
                foreach (($franja['celdas'] ?? []) as $celda) {
                    $idEstudiante = $celda['id_estudiante'] ?? '';
                    $idTarea = $celda['id_tarea_x_sprint'] ?? null;
                    if (!$idEstudiante || !$idTarea) {
                        continue;
                    }

                    if (!isset($participacionesActuales[$idTarea][$idEstudiante])) {
                        $idParticipacion = Uuid::generar();
                        $insertarParticipacion->bindValue(':id', $idParticipacion);
                        $insertarParticipacion->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                        $insertarParticipacion->bindValue(':id_tarea', $idTarea);
                        $insertarParticipacion->bindValue(':id_estudiante', $idEstudiante);
                        $insertarParticipacion->execute();
                        $participacionesActuales[$idTarea][$idEstudiante] = $idParticipacion;
                    }

                    foreach (($celda['calificaciones'] ?? []) as $cal) {
                        $idParametro = $cal['id_parametro_calificacion'] ?? '';
                        $idValor = $cal['id_valor_parametro_calificacion'] ?? '';
                        if (!$idParametro || !$idValor) {
                            continue;
                        }
                        if (!isset($parametroDelValor[$idValor]) || $parametroDelValor[$idValor] != $idParametro) {
                            throw new InvalidArgumentException('Hay una calificacion con un valor que no corresponde a su parametro. Recarga la pantalla.');
                        }

                        $actual = $calificacionesActuales[$idTarea][$idEstudiante][$idParametro] ?? null;
                        if ($actual) {
                            if ($actual['id_valor_parametro_calificacion'] != $idValor) {
                                $actualizarCalificacion->bindValue(':id_valor', $idValor);
                                $actualizarCalificacion->bindValue(':id', $actual['id']);
                                $actualizarCalificacion->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                                $actualizarCalificacion->execute();
                                $actualizadas++;
                            }
                        } else {
                            $idNew = Uuid::generar();
                            $insertarCalificacion->bindValue(':id', $idNew);
                            $insertarCalificacion->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                            $insertarCalificacion->bindValue(':id_tarea', $idTarea);
                            $insertarCalificacion->bindValue(':id_estudiante', $idEstudiante);
                            $insertarCalificacion->bindValue(':id_parametro', $idParametro);
                            $insertarCalificacion->bindValue(':id_valor', $idValor);
                            $insertarCalificacion->execute();
                            $calificacionesActuales[$idTarea][$idEstudiante][$idParametro] = [
                                'id' => $idNew,
                                'id_valor_parametro_calificacion' => $idValor
                            ];
                            $creadas++;
                        }
                    }
                }
            }

            // --- Las actividades usadas quedan ejecutadas en la fecha y franja ---
            $tareasEjecutadas = 0;
            if (!empty($tareaEnFranja)) {
                $stmtTotalGrupo = $db->prepare("SELECT COUNT(*) AS total
                    FROM estudiantes_x_grupos exg
                    INNER JOIN estudiantes e ON exg.id_estudiante = e.id
                    WHERE exg.id_grupo = :id_grupo AND exg.activo = 1 AND e.activo = 1
                      AND exg.id_tenant = :id_tenant");
                $stmtTotalGrupo->bindValue(':id_grupo', $id_grupo);
                $stmtTotalGrupo->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $stmtTotalGrupo->execute();
                $totalGrupo = (int) ($stmtTotalGrupo->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

                $stmtCalificados = $db->prepare("SELECT COUNT(DISTINCT id_estudiante) AS total
                    FROM calificaciones WHERE id_tarea_x_sprint = :id AND id_tenant = :id_tenant");

                $diaSemana = (int) date('N', strtotime($fecha));
                $ejecutar = $db->prepare("UPDATE tareas_x_sprints SET
                        id_estado_tarea = 2,
                        id_docente = :id_docente,
                        id_docente_inicia = :id_docente_inicia,
                        fecha_ejecucion = :fecha_ejecucion,
                        fecha_ejecucion_inicia = :fecha_ejecucion_inicia,
                        fecha_registro = CONVERT_TZ(current_timestamp(), '+00:00', '+02:00'),
                        id_horario = :id_horario,
                        dia_semana_horario = :dia_semana,
                        hora_inicial_horario = :hora_inicial,
                        hora_final_horario = :hora_final,
                        total_estudiantes_grupo = :total_grupo,
                        total_estudiantes_calificados = :total_calificados
                    WHERE id = :id AND id_tenant = :id_tenant");

                foreach ($tareaEnFranja as $idTarea => $i) {
                    $franja = $franjas[$i];
                    $fechaEjecucion = $fecha . ' ' . $franja['hora_inicial'];

                    $stmtCalificados->bindValue(':id', $idTarea);
                    $stmtCalificados->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $stmtCalificados->execute();
                    $totalCalificados = (int) ($stmtCalificados->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

                    $ejecutar->bindValue(':id_docente', $franja['id_docente']);
                    $ejecutar->bindValue(':id_docente_inicia', $franja['id_docente']);
                    $ejecutar->bindValue(':fecha_ejecucion', $fechaEjecucion);
                    $ejecutar->bindValue(':fecha_ejecucion_inicia', $fechaEjecucion);
                    $ejecutar->bindValue(':id_horario', !empty($franja['id_horario']) ? $franja['id_horario'] : null);
                    $ejecutar->bindValue(':dia_semana', $diaSemana, PDO::PARAM_INT);
                    $ejecutar->bindValue(':hora_inicial', $franja['hora_inicial']);
                    $ejecutar->bindValue(':hora_final', $franja['hora_final']);
                    $ejecutar->bindValue(':total_grupo', $totalGrupo, PDO::PARAM_INT);
                    $ejecutar->bindValue(':total_calificados', $totalCalificados, PDO::PARAM_INT);
                    $ejecutar->bindValue(':id', $idTarea);
                    $ejecutar->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $ejecutar->execute();
                    $tareasEjecutadas++;
                }
            }

            $db->commit();

            Flight::json([
                'actividades_ejecutadas' => $tareasEjecutadas,
                'calificaciones_creadas' => $creadas,
                'calificaciones_actualizadas' => $actualizadas,
                'calificaciones_eliminadas' => $eliminadas
            ]);
        } catch (InvalidArgumentException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Flight::json(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en guardarCalificacionRapida(): ' . $e->getMessage());
            Flight::json(['error' => 'Error al guardar la calificacion rapida: ' . $e->getMessage()], 500);
        }
    }

}