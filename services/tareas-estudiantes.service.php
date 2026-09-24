<?php
/*=============================================
SERVICIO - TAREAS ESTUDIANTES
Archivo: services/tareas-estudiantes.service.php

Tarea para la casa que el jardin asigna y el acudiente ve en el portal de
padres. Es independiente de los sprints (tareas_x_sprints).

La tarea queda en borrador hasta publicarla: solo las publicadas se ven en
el portal de padres, en la agenda y en el calendario. Publicar (y editar una
ya publicada) avisa a los acudientes con una notificacion de categoria
TAREA, que es la que dispara el push.

Aqui vive la logica de la tabla principal: destinatarios (una fila por
estudiante en tareas_estudiantes_x_estudiante), responsables, publicacion,
vistas del portal de padres y las fuentes para agenda y calendario.
=============================================*/

class TareasEstudiantes
{
    const PERMISO = 'estudiantes.tareas';
    const PERMISO_PADRES = 'padres.tareas.ver';
    const CODIGO_CATEGORIA_NOTIFICACION = 'TAREA';

    // =====================================================================
    // PORTAL INSTITUCIONAL
    // =====================================================================

    /**
     * Listado con el avance de cada tarea: cuantos niños tiene, cuantos la
     * enviaron, cuantos estan calificados y cuantas preguntas hay.
     */
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT t.id,
                   t.titulo,
                   t.descripcion,
                   t.id_area_academica,
                   aa.nombre AS area_nombre,
                   aa.color  AS area_color,
                   t.fecha_asignacion,
                   t.fecha_entrega,
                   t.criterio_texto,
                   t.permite_respuestas_acudientes,
                   t.publicada,
                   t.fecha_publicacion,
                   t.fecha_creacion,
                   uc.usuario AS usuario_creo,
                   (SELECT COUNT(*) FROM tareas_estudiantes_x_estudiante x
                     WHERE x.id_tarea_estudiante = t.id AND x.id_tenant = t.id_tenant) AS total_estudiantes,
                   (SELECT COUNT(*) FROM tareas_estudiantes_x_estudiante x
                     WHERE x.id_tarea_estudiante = t.id AND x.id_tenant = t.id_tenant AND x.estado = 'enviada') AS total_enviadas,
                   (SELECT COUNT(*) FROM tareas_estudiantes_x_estudiante x
                     WHERE x.id_tarea_estudiante = t.id AND x.id_tenant = t.id_tenant AND x.estado = 'calificada') AS total_calificadas,
                   (SELECT COUNT(*) FROM tareas_estudiantes_preguntas p
                     WHERE p.id_tarea_estudiante = t.id AND p.id_tenant = t.id_tenant
                       AND p.activo = 1 AND p.id_pregunta_padre IS NULL) AS total_preguntas
            FROM tareas_estudiantes t
            LEFT JOIN areas_academicas aa ON aa.id = t.id_area_academica AND aa.id_tenant = t.id_tenant
            LEFT JOIN usuarios uc ON uc.id = t.id_usuario_creo
            WHERE t.id_tenant = :id_tenant
              AND t.activo = 1
            ORDER BY t.fecha_asignacion DESC, t.fecha_creacion DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Detalle para el formulario: la tarea, sus adjuntos, los estudiantes
     * asignados y los responsables.
     */
    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);

        $db = Flight::db();
        $tarea = self::obtenerTarea($db, $id);

        if (!$tarea) {
            Flight::json(array('error' => 'Tarea no encontrada'), 404);
            return;
        }

        $tarea['adjuntos'] = TareasEstudiantesAdjuntos::listar($db, $id);

        $estudiantes = $db->prepare("
            SELECT x.id_estudiante
            FROM tareas_estudiantes_x_estudiante x
            WHERE x.id_tarea_estudiante = :id AND x.id_tenant = :id_tenant
        ");
        $estudiantes->bindParam(':id', $id);
        $estudiantes->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $estudiantes->execute();
        $tarea['estudiantes'] = array_column($estudiantes->fetchAll(), 'id_estudiante');

        $tarea['responsables'] = TareasEstudiantesResponsables::idsPorTarea($db, $id);

        Flight::json($tarea);
    }

    /**
     * Crea la tarea en borrador con sus estudiantes y responsables.
     * No avisa a nadie: eso lo hace publicar().
     */
    public static function new()
    {
        $db = Flight::db();

        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, self::PERMISO);

            $datos = self::leerDatos();
            $error = self::validarDatos($datos);
            if ($error) {
                Flight::json(array('error' => $error), 400);
                return;
            }

            $db->beginTransaction();

            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO tareas_estudiantes
                    (id, id_tenant, titulo, descripcion, id_area_academica, fecha_asignacion, fecha_entrega,
                     criterio_texto, permite_respuestas_acudientes, id_usuario_creo)
                VALUES
                    (:id, :id_tenant, :titulo, :descripcion, :id_area_academica, :fecha_asignacion, :fecha_entrega,
                     :criterio_texto, :permite_respuestas_acudientes, :id_usuario_creo)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            self::bindDatos($sentence, $datos);
            $sentence->bindValue(':id_usuario_creo', $userData->id ?? null);
            $sentence->execute();

            self::sincronizarEstudiantes($db, $id, $datos['estudiantes']);
            TareasEstudiantesResponsables::sincronizar($db, $id, $datos['responsables']);

            $db->commit();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en TareasEstudiantes::new: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear la tarea'), 500);
        }
    }

    /**
     * Actualiza la tarea. Los destinatarios se pueden cambiar: los nuevos
     * entran en pendiente y los que se quitan se borran con su estado y
     * calificacion. Si la tarea ya estaba publicada se vuelve a avisar.
     */
    public static function replace()
    {
        $db = Flight::db();

        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, self::PERMISO);

            $id = Flight::request()->data['id'] ?? null;
            if (!$id) {
                Flight::json(array('error' => 'ID es obligatorio'), 400);
                return;
            }

            $tarea = self::obtenerTarea($db, $id);
            if (!$tarea) {
                Flight::json(array('error' => 'Tarea no encontrada'), 404);
                return;
            }

            $datos = self::leerDatos();
            $error = self::validarDatos($datos);
            if ($error) {
                Flight::json(array('error' => $error), 400);
                return;
            }

            $db->beginTransaction();

            $sentence = $db->prepare("
                UPDATE tareas_estudiantes
                SET titulo = :titulo,
                    descripcion = :descripcion,
                    id_area_academica = :id_area_academica,
                    fecha_asignacion = :fecha_asignacion,
                    fecha_entrega = :fecha_entrega,
                    criterio_texto = :criterio_texto,
                    permite_respuestas_acudientes = :permite_respuestas_acudientes,
                    id_usuario_modifico = :id_usuario_modifico,
                    fecha_modificacion = NOW()
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            self::bindDatos($sentence, $datos);
            $sentence->bindValue(':id_usuario_modifico', $userData->id ?? null);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::sincronizarEstudiantes($db, $id, $datos['estudiantes']);
            TareasEstudiantesResponsables::sincronizar($db, $id, $datos['responsables']);

            $db->commit();

            $aviso = null;
            if ((int) $tarea['publicada'] === 1) {
                $aviso = self::avisarAcudientes($db, $id, $userData->id ?? null, true);
            }

            Flight::json(array('id' => $id, 'aviso' => $aviso));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en TareasEstudiantes::replace: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar la tarea'), 500);
        }
    }

    /**
     * Eliminar desactiva la tarea: deja de verse en el portal de padres, la
     * agenda y el calendario, pero no se pierde lo registrado.
     */
    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, self::PERMISO);

            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(array('error' => 'ID es obligatorio'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE tareas_estudiantes
                SET activo = 0, id_usuario_modifico = :id_usuario, fecha_modificacion = NOW()
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindValue(':id_usuario', $userData->id ?? null);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en TareasEstudiantes::delete: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar la tarea'), 500);
        }
    }

    /**
     * Publica la tarea y avisa a los acudientes de los estudiantes asignados.
     */
    public static function publicar()
    {
        $db = Flight::db();

        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, self::PERMISO);

            $id = Flight::request()->data['id'] ?? null;
            if (!$id) {
                Flight::json(array('error' => 'ID es obligatorio'), 400);
                return;
            }

            $tarea = self::obtenerTarea($db, $id);
            if (!$tarea) {
                Flight::json(array('error' => 'Tarea no encontrada'), 404);
                return;
            }

            if ((int) $tarea['publicada'] === 1) {
                Flight::json(array('error' => 'La tarea ya está publicada'), 400);
                return;
            }

            if ((int) $tarea['total_estudiantes'] === 0) {
                Flight::json(array('error' => 'La tarea no tiene estudiantes asignados'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE tareas_estudiantes
                SET publicada = 1, fecha_publicacion = NOW(), id_usuario_publico = :id_usuario
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindValue(':id_usuario', $userData->id ?? null);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $aviso = self::avisarAcudientes($db, $id, $userData->id ?? null, false);

            Flight::json(array('id' => $id, 'aviso' => $aviso));
        } catch (Exception $e) {
            error_log("Error en TareasEstudiantes::publicar: " . $e->getMessage());
            Flight::json(array('error' => 'Error al publicar la tarea'), 500);
        }
    }

    /**
     * Pantalla de calificacion: los niños de la tarea con su estado, la
     * entrega del acudiente y la calificacion.
     */
    public static function getEstudiantes($id)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT x.id,
                   x.id_estudiante,
                   x.estado,
                   x.fecha_envio_acudiente,
                   x.id_valor_parametro_calificacion,
                   x.valoracion_texto,
                   x.valoracion_color,
                   x.observacion,
                   x.fecha_calificacion,
                   TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_estudiante,
                   TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.primer_apellido)) AS nombre_acudiente_envio,
                   (SELECT g.nombre
                      FROM estudiantes_x_grupos exg
                      INNER JOIN grupos g ON g.id = exg.id_grupo
                     WHERE exg.id_estudiante = x.id_estudiante AND exg.id_tenant = x.id_tenant AND exg.activo = 1
                     LIMIT 1) AS nombre_grupo
            FROM tareas_estudiantes_x_estudiante x
            INNER JOIN estudiantes e ON e.id = x.id_estudiante
            INNER JOIN personas p ON p.id = e.id_persona
            LEFT JOIN personas pa ON pa.id = x.id_persona_envio
            WHERE x.id_tarea_estudiante = :id AND x.id_tenant = :id_tenant
            ORDER BY p.primer_apellido, p.primer_nombre
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    // =====================================================================
    // PORTAL DE PADRES
    // =====================================================================

    /**
     * Tareas publicadas de un estudiante del acudiente, de la mas reciente a
     * la mas antigua.
     */
    public static function getPorEstudiante($id_estudiante)
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        if (!self::puedeVerEstudiante($db, $userData, $id_estudiante)) {
            Flight::json(array('error' => 'No tienes acceso a la información de este estudiante', 'code' => 'FORBIDDEN'), 403);
            return;
        }

        $sentence = $db->prepare("
            SELECT t.id,
                   t.titulo,
                   t.descripcion,
                   aa.nombre AS area_nombre,
                   aa.color  AS area_color,
                   t.fecha_asignacion,
                   t.fecha_entrega,
                   t.permite_respuestas_acudientes,
                   x.estado,
                   x.fecha_envio_acudiente,
                   x.valoracion_texto,
                   x.valoracion_color,
                   x.observacion,
                   x.fecha_calificacion,
                   (SELECT COUNT(*) FROM tareas_estudiantes_adjuntos ad
                     WHERE ad.id_tarea_estudiante = t.id AND ad.id_tenant = t.id_tenant AND ad.activo = 1) AS total_adjuntos
            FROM tareas_estudiantes_x_estudiante x
            INNER JOIN tareas_estudiantes t ON t.id = x.id_tarea_estudiante
            LEFT JOIN areas_academicas aa ON aa.id = t.id_area_academica AND aa.id_tenant = t.id_tenant
            WHERE x.id_tenant = :id_tenant
              AND x.id_estudiante = :id_estudiante
              AND t.activo = 1
              AND t.publicada = 1
            ORDER BY t.fecha_asignacion DESC, t.fecha_publicacion DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Detalle de una tarea para el acudiente, con sus adjuntos. El foro se
     * consulta aparte en el servicio de preguntas.
     */
    public static function getDetallePadres($id, $id_estudiante)
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        if (!self::esDestinatario($db, $userData, $id, $id_estudiante)) {
            Flight::json(array('error' => 'No tienes acceso a esta tarea', 'code' => 'FORBIDDEN'), 403);
            return;
        }

        $sentence = $db->prepare("
            SELECT t.id,
                   t.titulo,
                   t.descripcion,
                   aa.nombre AS area_nombre,
                   aa.color  AS area_color,
                   t.fecha_asignacion,
                   t.fecha_entrega,
                   t.permite_respuestas_acudientes,
                   x.estado,
                   x.fecha_envio_acudiente,
                   x.valoracion_texto,
                   x.valoracion_color,
                   x.observacion,
                   x.fecha_calificacion
            FROM tareas_estudiantes t
            INNER JOIN tareas_estudiantes_x_estudiante x
                    ON x.id_tarea_estudiante = t.id AND x.id_estudiante = :id_estudiante
            LEFT JOIN areas_academicas aa ON aa.id = t.id_area_academica AND aa.id_tenant = t.id_tenant
            WHERE t.id = :id AND t.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $tarea = $sentence->fetch();

        if (!$tarea) {
            Flight::json(array('error' => 'Tarea no encontrada'), 404);
            return;
        }

        $tarea['adjuntos'] = TareasEstudiantesAdjuntos::listar($db, $id);

        Flight::json($tarea);
    }

    /**
     * Tareas que se entregan en el rango para todos los estudiantes del
     * acudiente, para el calendario del portal de padres. Se consultan en
     * linea: no se guardan en calendarios_eventos, que es general del jardin.
     * Lo llama Calendarios::getCalendarioMes; en el institucional o sin el
     * permiso de tareas devuelve vacio.
     *
     * @return array Filas con id, titulo, fecha_entrega, area_nombre, estado,
     *               id_estudiante y nombre_estudiante
     */
    public static function calendarioDelAcudiente(PDO $db, $userData, $desde, $hasta)
    {
        if (!self::esPortalPadres($userData) || empty($userData->id_persona)
            || !PermisosService::tiene($userData, self::PERMISO_PADRES)) {
            return array();
        }

        $sentence = $db->prepare("
            SELECT t.id,
                   t.titulo,
                   t.fecha_entrega,
                   aa.nombre AS area_nombre,
                   x.estado,
                   x.id_estudiante,
                   TRIM(CONCAT_WS(' ', pe.primer_nombre, pe.primer_apellido)) AS nombre_estudiante
            FROM acudientes a
            INNER JOIN tareas_estudiantes_x_estudiante x
                    ON x.id_estudiante = a.id_estudiante AND x.id_tenant = a.id_tenant
            INNER JOIN tareas_estudiantes t ON t.id = x.id_tarea_estudiante
            INNER JOIN estudiantes e ON e.id = x.id_estudiante
            INNER JOIN personas pe ON pe.id = e.id_persona
            LEFT JOIN areas_academicas aa ON aa.id = t.id_area_academica AND aa.id_tenant = t.id_tenant
            WHERE a.id_tenant = :id_tenant
              AND a.id_persona = :id_persona
              AND a.activo = 1
              AND a.ve_en_portal_padres = 1
              AND t.activo = 1
              AND t.publicada = 1
              AND t.fecha_entrega BETWEEN :desde AND :hasta
            ORDER BY t.fecha_entrega, pe.primer_nombre, t.titulo
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_persona', $userData->id_persona);
        $sentence->bindValue(':desde', $desde);
        $sentence->bindValue(':hasta', $hasta);
        $sentence->execute();
        return $sentence->fetchAll();
    }

    // =====================================================================
    // FUENTE PARA MI AGENDA
    // =====================================================================

    /**
     * Eventos de tareas del estudiante en una fecha: la tarea el dia de su
     * asignacion y, si ya se califico, el dia de la calificacion. Solo
     * tareas publicadas. Lo llama MiAgenda::fuenteTareas.
     *
     * @return array Filas con 'tipo' = 'asignada' | 'calificada'
     */
    public static function eventosDelDia(PDO $db, $id_estudiante, $fecha)
    {
        $sentence = $db->prepare("
            SELECT 'asignada' AS tipo,
                   t.id, t.titulo, t.descripcion, t.fecha_entrega,
                   IF(DATE(t.fecha_publicacion) = :fecha3, t.fecha_publicacion, NULL) AS fecha_hora,
                   aa.nombre AS area_nombre,
                   x.estado, x.valoracion_texto, x.valoracion_color, x.observacion
            FROM tareas_estudiantes_x_estudiante x
            INNER JOIN tareas_estudiantes t ON t.id = x.id_tarea_estudiante
            LEFT JOIN areas_academicas aa ON aa.id = t.id_area_academica AND aa.id_tenant = t.id_tenant
            WHERE x.id_tenant = :id_tenant1
              AND x.id_estudiante = :id_estudiante1
              AND t.activo = 1 AND t.publicada = 1
              AND t.fecha_asignacion = :fecha1
            UNION ALL
            SELECT 'calificada' AS tipo,
                   t.id, t.titulo, t.descripcion, t.fecha_entrega,
                   x.fecha_calificacion AS fecha_hora,
                   aa.nombre AS area_nombre,
                   x.estado, x.valoracion_texto, x.valoracion_color, x.observacion
            FROM tareas_estudiantes_x_estudiante x
            INNER JOIN tareas_estudiantes t ON t.id = x.id_tarea_estudiante
            LEFT JOIN areas_academicas aa ON aa.id = t.id_area_academica AND aa.id_tenant = t.id_tenant
            WHERE x.id_tenant = :id_tenant2
              AND x.id_estudiante = :id_estudiante2
              AND t.activo = 1 AND t.publicada = 1
              AND x.estado = 'calificada'
              AND DATE(x.fecha_calificacion) = :fecha2
        ");
        $sentence->bindValue(':id_tenant1', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant2', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_estudiante1', $id_estudiante);
        $sentence->bindValue(':id_estudiante2', $id_estudiante);
        $sentence->bindValue(':fecha1', $fecha);
        $sentence->bindValue(':fecha2', $fecha);
        $sentence->bindValue(':fecha3', $fecha);
        $sentence->execute();
        return $sentence->fetchAll();
    }

    // =====================================================================
    // APOYO PARA LOS SERVICIOS DE LAS TABLAS HIJAS
    // =====================================================================

    /**
     * El usuario puede ver la tarea de ese estudiante. En el institucional
     * exige el permiso del modulo; en el portal de padres, que el estudiante
     * sea suyo, que este asignado a la tarea y que la tarea este publicada.
     */
    public static function esDestinatario(PDO $db, $userData, $id_tarea, $id_estudiante)
    {
        if (!self::esPortalPadres($userData)) {
            return PermisosService::tiene($userData, self::PERMISO);
        }

        if (!self::puedeVerEstudiante($db, $userData, $id_estudiante)) {
            return false;
        }

        $sentence = $db->prepare("
            SELECT 1
            FROM tareas_estudiantes_x_estudiante x
            INNER JOIN tareas_estudiantes t ON t.id = x.id_tarea_estudiante
            WHERE x.id_tarea_estudiante = :id_tarea
              AND x.id_estudiante = :id_estudiante
              AND x.id_tenant = :id_tenant
              AND t.activo = 1 AND t.publicada = 1
            LIMIT 1
        ");
        $sentence->bindParam(':id_tarea', $id_tarea);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        return (bool) $sentence->fetch();
    }

    public static function esPortalPadres($userData)
    {
        return isset($userData->portal) && $userData->portal === JWTService::PORTAL_PADRES;
    }

    /**
     * Tarea activa del tenant con el conteo de estudiantes, o null.
     */
    public static function obtenerTarea(PDO $db, $id)
    {
        $sentence = $db->prepare("
            SELECT t.*,
                   aa.nombre AS area_nombre,
                   (SELECT COUNT(*) FROM tareas_estudiantes_x_estudiante x
                     WHERE x.id_tarea_estudiante = t.id AND x.id_tenant = t.id_tenant) AS total_estudiantes
            FROM tareas_estudiantes t
            LEFT JOIN areas_academicas aa ON aa.id = t.id_area_academica AND aa.id_tenant = t.id_tenant
            WHERE t.id = :id AND t.id_tenant = :id_tenant AND t.activo = 1
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ?: null;
    }

    // =====================================================================
    // PRIVADOS
    // =====================================================================

    private static function leerDatos()
    {
        $data = Flight::request()->data;
        $estudiantes = $data['estudiantes'] ?? array();
        $responsables = $data['responsables'] ?? array();

        return array(
            'titulo'            => trim((string) ($data['titulo'] ?? '')),
            'descripcion'       => $data['descripcion'] ?? null,
            'id_area_academica' => !empty($data['id_area_academica']) ? $data['id_area_academica'] : null,
            'fecha_asignacion'  => $data['fecha_asignacion'] ?? null,
            'fecha_entrega'     => $data['fecha_entrega'] ?? null,
            'criterio_texto'    => $data['criterio_texto'] ?? null,
            'permite_respuestas_acudientes' => !empty($data['permite_respuestas_acudientes']) ? 1 : 0,
            'estudiantes'       => is_array($estudiantes) ? array_values(array_unique(array_filter($estudiantes))) : array(),
            'responsables'      => is_array($responsables) ? array_values(array_unique(array_filter($responsables))) : array(),
        );
    }

    private static function validarDatos(array $datos)
    {
        if ($datos['titulo'] === '') {
            return 'El título es obligatorio';
        }
        if (!self::fechaValida($datos['fecha_asignacion']) || !self::fechaValida($datos['fecha_entrega'])) {
            return 'La fecha de asignación y la de entrega son obligatorias';
        }
        if ($datos['fecha_entrega'] < $datos['fecha_asignacion']) {
            return 'La fecha de entrega no puede ser anterior a la de asignación';
        }
        if (count($datos['estudiantes']) === 0) {
            return 'Debe seleccionar al menos un estudiante';
        }
        return null;
    }

    private static function bindDatos($sentence, array $datos)
    {
        $sentence->bindValue(':titulo', $datos['titulo']);
        $sentence->bindValue(':descripcion', $datos['descripcion']);
        $sentence->bindValue(':id_area_academica', $datos['id_area_academica']);
        $sentence->bindValue(':fecha_asignacion', $datos['fecha_asignacion']);
        $sentence->bindValue(':fecha_entrega', $datos['fecha_entrega']);
        $sentence->bindValue(':criterio_texto', $datos['criterio_texto']);
        $sentence->bindValue(':permite_respuestas_acudientes', $datos['permite_respuestas_acudientes'], PDO::PARAM_INT);
    }

    /**
     * Deja en la tarea exactamente los estudiantes recibidos. Solo se aceptan
     * estudiantes activos del tenant, para que un cliente manipulado no meta
     * ids ajenos.
     */
    private static function sincronizarEstudiantes(PDO $db, $idTarea, array $estudiantes)
    {
        $validos = array();
        if (count($estudiantes) > 0) {
            $marcadores = implode(',', array_fill(0, count($estudiantes), '?'));
            $consulta = $db->prepare("SELECT id FROM estudiantes WHERE id_tenant = ? AND id IN ($marcadores)");
            $consulta->execute(array_merge(array(TenantContext::id()), $estudiantes));
            $validos = array_column($consulta->fetchAll(), 'id');
        }

        $actuales = $db->prepare("SELECT id_estudiante FROM tareas_estudiantes_x_estudiante WHERE id_tarea_estudiante = :id AND id_tenant = :id_tenant");
        $actuales->bindParam(':id', $idTarea);
        $actuales->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $actuales->execute();
        $existentes = array_column($actuales->fetchAll(), 'id_estudiante');

        $quitar = array_diff($existentes, $validos);
        $agregar = array_diff($validos, $existentes);

        if (count($quitar) > 0) {
            $marcadores = implode(',', array_fill(0, count($quitar), '?'));
            $borrar = $db->prepare("DELETE FROM tareas_estudiantes_x_estudiante
                                     WHERE id_tenant = ? AND id_tarea_estudiante = ? AND id_estudiante IN ($marcadores)");
            $borrar->execute(array_merge(array(TenantContext::id(), $idTarea), array_values($quitar)));
        }

        if (count($agregar) > 0) {
            $insertar = $db->prepare("INSERT INTO tareas_estudiantes_x_estudiante (id, id_tenant, id_tarea_estudiante, id_estudiante)
                                      VALUES (:id, :id_tenant, :id_tarea, :id_estudiante)");
            foreach ($agregar as $idEstudiante) {
                $insertar->bindValue(':id', Uuid::generar());
                $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $insertar->bindValue(':id_tarea', $idTarea);
                $insertar->bindValue(':id_estudiante', $idEstudiante);
                $insertar->execute();
            }
        }
    }

    /**
     * Crea una notificacion de categoria TAREA para los acudientes de los
     * estudiantes asignados, que es la que dispara el push y deja el aviso en
     * su bandeja. Nunca tumba la operacion: si falla, queda en el log.
     *
     * @return array|null Resumen del envio
     */
    private static function avisarAcudientes(PDO $db, $idTarea, $idUsuario, $esActualizacion)
    {
        try {
            $tarea = self::obtenerTarea($db, $idTarea);
            if (!$tarea) {
                return null;
            }

            $categoria = $db->prepare("SELECT id FROM notificaciones_categorias WHERE id_tenant = :id_tenant AND codigo = :codigo AND activo = 1 LIMIT 1");
            $categoria->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $categoria->bindValue(':codigo', self::CODIGO_CATEGORIA_NOTIFICACION);
            $categoria->execute();
            $idCategoria = $categoria->fetchColumn();

            if (!$idCategoria) {
                error_log("TareasEstudiantes: el tenant no tiene la categoria de notificacion TAREA");
                return null;
            }

            $estudiantes = $db->prepare("SELECT id_estudiante FROM tareas_estudiantes_x_estudiante WHERE id_tarea_estudiante = :id AND id_tenant = :id_tenant");
            $estudiantes->bindParam(':id', $idTarea);
            $estudiantes->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $estudiantes->execute();
            $ids = array_column($estudiantes->fetchAll(), 'id_estudiante');

            $titulo = ($esActualizacion ? 'Tarea actualizada: ' : 'Nueva tarea: ') . $tarea['titulo'];
            $cuerpo = 'Fecha de entrega: ' . self::fechaEnTexto($tarea['fecha_entrega']) . '.';
            if (!empty($tarea['descripcion'])) {
                $cuerpo .= ' ' . trim(strip_tags($tarea['descripcion']));
            }

            return Notificaciones::crearDesdeSistema(
                $db, $titulo, $cuerpo, $idCategoria, $tarea['criterio_texto'], $ids, $idUsuario,
                array('tipo' => 'tarea', 'id_tarea' => $idTarea)
            );
        } catch (Exception $e) {
            error_log("TareasEstudiantes::avisarAcudientes: " . $e->getMessage());
            return null;
        }
    }

    private static function puedeVerEstudiante(PDO $db, $userData, $id_estudiante)
    {
        if (!self::esPortalPadres($userData)) {
            return PermisosService::tiene($userData, self::PERMISO);
        }
        if (empty($userData->id_persona)) {
            return false;
        }
        return Acudientes::esEstudianteDelAcudiente($db, $userData->id_persona, $id_estudiante);
    }

    private static function fechaValida($fecha)
    {
        if (!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return false;
        }
        list($anio, $mes, $dia) = array_map('intval', explode('-', $fecha));
        return checkdate($mes, $dia, $anio);
    }

    private static function fechaEnTexto($fecha)
    {
        $meses = array('enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                       'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
        $marca = strtotime($fecha);
        if (!$marca) {
            return (string) $fecha;
        }
        return (int) date('j', $marca) . ' de ' . $meses[(int) date('n', $marca) - 1];
    }
}
