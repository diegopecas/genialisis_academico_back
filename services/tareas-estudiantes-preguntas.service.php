<?php
/*=============================================
SERVICIO - TAREAS ESTUDIANTES PREGUNTAS (foro)
Archivo: services/tareas-estudiantes-preguntas.service.php

Foro de la tarea. Una pregunta es una fila con id_pregunta_padre NULL y sus
respuestas cuelgan de ella. Quien ve que:

- Jardin (permiso del modulo): todo.
- Acudiente: las preguntas publicas de la tarea y las privadas que hizo el
  mismo para ese estudiante, con sus respuestas.

Quien responde:
- Cualquiera con el permiso del modulo.
- Otro acudiente, solo en preguntas publicas y si la tarea tiene activada
  permite_respuestas_acudientes. En su propia pregunta el acudiente siempre
  puede volver a escribir.

Cada pregunta nueva de un acudiente avisa a los responsables de la tarea.
=============================================*/

class TareasEstudiantesPreguntas
{
    const LARGO_MAXIMO = 2000;

    /**
     * Hilo de la tarea. En el portal de padres se exige ?id_estudiante=.
     */
    public static function getByTarea($id_tarea)
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $idEstudiante = Flight::request()->query['id_estudiante'] ?? null;

        if (!TareasEstudiantes::esDestinatario($db, $userData, $id_tarea, $idEstudiante)) {
            Flight::json(array('error' => 'No tienes acceso a esta tarea', 'code' => 'FORBIDDEN'), 403);
            return;
        }

        $esPadres = TareasEstudiantes::esPortalPadres($userData);

        $sql = "
            SELECT q.id,
                   q.id_pregunta_padre,
                   q.id_estudiante,
                   q.id_persona_autor,
                   q.tipo_autor,
                   q.es_publica,
                   q.texto,
                   q.fecha,
                   TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.primer_apellido)) AS nombre_autor,
                   TRIM(CONCAT_WS(' ', pe.primer_nombre, pe.primer_apellido)) AS nombre_estudiante
            FROM tareas_estudiantes_preguntas q
            INNER JOIN personas pa ON pa.id = q.id_persona_autor
            LEFT JOIN estudiantes e ON e.id = q.id_estudiante
            LEFT JOIN personas pe ON pe.id = e.id_persona
            LEFT JOIN tareas_estudiantes_preguntas raiz ON raiz.id = COALESCE(q.id_pregunta_padre, q.id)
            WHERE q.id_tarea_estudiante = :id_tarea
              AND q.id_tenant = :id_tenant
              AND q.activo = 1
        ";

        if ($esPadres) {
            // La visibilidad la manda la pregunta raiz: sus respuestas se ven
            // o no junto con ella.
            $sql .= " AND (raiz.es_publica = 1
                           OR (raiz.id_persona_autor = :id_persona AND raiz.id_estudiante = :id_estudiante))";
        }

        $sql .= " ORDER BY q.fecha";

        $sentence = $db->prepare($sql);
        $sentence->bindParam(':id_tarea', $id_tarea);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        if ($esPadres) {
            $sentence->bindValue(':id_persona', $userData->id_persona ?? null);
            $sentence->bindValue(':id_estudiante', $idEstudiante);
        }
        $sentence->execute();
        $filas = $sentence->fetchAll();

        Flight::json(self::armarHilos($filas, $esPadres ? ($userData->id_persona ?? null) : null));
    }

    /**
     * Crea una pregunta o una respuesta.
     * Body: id_tarea_estudiante, texto, id_pregunta_padre (para responder),
     * es_publica (solo preguntas) e id_estudiante (portal de padres).
     */
    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            $db = Flight::db();

            $idTarea = Flight::request()->data['id_tarea_estudiante'] ?? null;
            $texto = trim((string) (Flight::request()->data['texto'] ?? ''));
            $idPadre = Flight::request()->data['id_pregunta_padre'] ?? null;
            $esPublica = !empty(Flight::request()->data['es_publica']) ? 1 : 0;
            $idEstudiante = Flight::request()->data['id_estudiante'] ?? null;

            if (!$idTarea || $texto === '') {
                Flight::json(array('error' => 'Escribe el mensaje'), 400);
                return;
            }

            if (mb_strlen($texto, 'UTF-8') > self::LARGO_MAXIMO) {
                Flight::json(array('error' => 'El mensaje no puede superar ' . self::LARGO_MAXIMO . ' caracteres'), 400);
                return;
            }

            if (!TareasEstudiantes::esDestinatario($db, $userData, $idTarea, $idEstudiante)) {
                Flight::json(array('error' => 'No tienes acceso a esta tarea', 'code' => 'FORBIDDEN'), 403);
                return;
            }

            $esPadres = TareasEstudiantes::esPortalPadres($userData);
            $idPersona = $userData->id_persona ?? null;

            if (!$idPersona) {
                Flight::json(array('error' => 'No se pudo identificar a quien escribe'), 400);
                return;
            }

            $tarea = TareasEstudiantes::obtenerTarea($db, $idTarea);

            if ($idPadre) {
                $raiz = self::obtenerPregunta($db, $idPadre, $idTarea);
                if (!$raiz || !empty($raiz['id_pregunta_padre'])) {
                    Flight::json(array('error' => 'La pregunta no existe'), 404);
                    return;
                }

                if ($esPadres) {
                    $esSuya = $raiz['id_persona_autor'] === $idPersona && $raiz['id_estudiante'] === $idEstudiante;
                    $puedeResponderPublica = (int) $raiz['es_publica'] === 1
                        && (int) $tarea['permite_respuestas_acudientes'] === 1;
                    if (!$esSuya && !$puedeResponderPublica) {
                        Flight::json(array('error' => 'No puedes responder esta pregunta'), 403);
                        return;
                    }
                }
                // La respuesta hereda la visibilidad de su pregunta.
                $esPublica = (int) $raiz['es_publica'];
            } elseif (!$esPadres) {
                // El jardin responde; no abre preguntas.
                Flight::json(array('error' => 'Solo se puede responder una pregunta existente'), 400);
                return;
            }

            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO tareas_estudiantes_preguntas
                    (id, id_tenant, id_tarea_estudiante, id_pregunta_padre, id_estudiante,
                     id_persona_autor, tipo_autor, es_publica, texto)
                VALUES
                    (:id, :id_tenant, :id_tarea, :id_padre, :id_estudiante,
                     :id_persona, :tipo_autor, :es_publica, :texto)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_tarea', $idTarea);
            $sentence->bindValue(':id_padre', $idPadre ?: null);
            $sentence->bindValue(':id_estudiante', $esPadres ? $idEstudiante : null);
            $sentence->bindValue(':id_persona', $idPersona);
            $sentence->bindValue(':tipo_autor', $esPadres ? 'acudiente' : 'jardin');
            $sentence->bindValue(':es_publica', $esPublica, PDO::PARAM_INT);
            $sentence->bindValue(':texto', $texto);
            $sentence->execute();

            // Solo las preguntas nuevas de acudientes avisan a los responsables.
            if ($esPadres && !$idPadre) {
                self::avisarResponsables($db, $tarea, $texto, $idEstudiante);
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en TareasEstudiantesPreguntas::new: " . $e->getMessage());
            Flight::json(array('error' => 'Error al guardar el mensaje'), 500);
        }
    }

    /**
     * Desactiva un mensaje. El jardin puede quitar cualquiera; el acudiente
     * solo los suyos. Quitar una pregunta oculta tambien sus respuestas,
     * porque en el hilo cuelgan de ella.
     */
    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(array('error' => 'ID es obligatorio'), 400);
                return;
            }

            $sentence = $db->prepare("SELECT id, id_persona_autor FROM tareas_estudiantes_preguntas WHERE id = :id AND id_tenant = :id_tenant AND activo = 1");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $mensaje = $sentence->fetch();

            if (!$mensaje) {
                Flight::json(array('error' => 'Mensaje no encontrado'), 404);
                return;
            }

            $esPadres = TareasEstudiantes::esPortalPadres($userData);
            $puede = $esPadres
                ? $mensaje['id_persona_autor'] === ($userData->id_persona ?? null)
                : PermisosService::tiene($userData, TareasEstudiantes::PERMISO);

            if (!$puede) {
                Flight::json(array('error' => 'No puedes eliminar este mensaje'), 403);
                return;
            }

            $borrar = $db->prepare("UPDATE tareas_estudiantes_preguntas SET activo = 0
                                    WHERE id_tenant = :id_tenant AND (id = :id OR id_pregunta_padre = :id2)");
            $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrar->bindParam(':id', $id);
            $borrar->bindParam(':id2', $id);
            $borrar->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en TareasEstudiantesPreguntas::delete: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar el mensaje'), 500);
        }
    }

    private static function obtenerPregunta(PDO $db, $id, $idTarea)
    {
        $sentence = $db->prepare("SELECT * FROM tareas_estudiantes_preguntas
                                  WHERE id = :id AND id_tarea_estudiante = :id_tarea AND id_tenant = :id_tenant AND activo = 1");
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':id_tarea', $idTarea);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ?: null;
    }

    /**
     * Agrupa las filas en preguntas con sus respuestas. 'es_mio' le dice al
     * portal de padres que puede borrar ese mensaje.
     */
    private static function armarHilos(array $filas, $idPersonaActual)
    {
        $preguntas = array();
        $respuestas = array();

        foreach ($filas as $fila) {
            $fila['es_mio'] = $idPersonaActual !== null && $fila['id_persona_autor'] === $idPersonaActual;
            if (empty($fila['id_pregunta_padre'])) {
                $fila['respuestas'] = array();
                $preguntas[$fila['id']] = $fila;
            } else {
                $respuestas[] = $fila;
            }
        }

        foreach ($respuestas as $respuesta) {
            if (isset($preguntas[$respuesta['id_pregunta_padre']])) {
                $preguntas[$respuesta['id_pregunta_padre']]['respuestas'][] = $respuesta;
            }
        }

        return array_values($preguntas);
    }

    /**
     * Alerta a los responsables de la tarea. Si no hay responsables no avisa
     * a nadie: la pregunta queda igual en el foro.
     */
    private static function avisarResponsables(PDO $db, $tarea, $texto, $idEstudiante)
    {
        try {
            $destinatarios = TareasEstudiantesResponsables::destinatariosAlerta($db, $tarea['id']);
            if (count($destinatarios) === 0) {
                return;
            }

            $nombre = $db->prepare("SELECT TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS nombre
                                    FROM estudiantes e INNER JOIN personas p ON p.id = e.id_persona
                                    WHERE e.id = :id AND e.id_tenant = :id_tenant");
            $nombre->bindValue(':id', $idEstudiante);
            $nombre->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $nombre->execute();
            $nombreEstudiante = $nombre->fetchColumn() ?: 'un estudiante';

            NotificacionesColaboradores::crear(
                $db,
                NotificacionesColaboradores::TIPO_PREGUNTA_TAREA,
                'Pregunta en la tarea: ' . $tarea['titulo'],
                'El acudiente de ' . $nombreEstudiante . ' preguntó: ' . $texto,
                $tarea['id'],
                $destinatarios
            );
        } catch (Exception $e) {
            error_log("TareasEstudiantesPreguntas::avisarResponsables: " . $e->getMessage());
        }
    }
}
