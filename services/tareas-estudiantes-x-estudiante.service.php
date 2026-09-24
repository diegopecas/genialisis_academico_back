<?php
/*=============================================
SERVICIO - TAREAS ESTUDIANTES X ESTUDIANTE
Archivo: services/tareas-estudiantes-x-estudiante.service.php

Una fila por niño de la tarea. El acudiente la marca como enviada y el
jardin la califica o la marca como no entregada.

La calificacion usa el parametro que el jardin tiene configurado para los
informes (informes_configuracion.id_parametro_evaluacion), que es su
"Valoracion". Al calificar se copia el texto y el color del valor elegido:
si despues el jardin edita la escala, la nota vieja se sigue viendo igual.
=============================================*/

class TareasEstudiantesXEstudiante
{
    // 'enviada' solo se acepta para guardar la observacion sin cambiar el
    // estado que dejo el acudiente; el jardin no la pone desde cero.
    const ESTADOS_JARDIN = array('pendiente', 'enviada', 'no_entregada', 'calificada');

    /**
     * Valores de la escala de valoracion del jardin, para el selector de la
     * pantalla de calificacion.
     */
    public static function getEscala()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, TareasEstudiantes::PERMISO);

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT v.id, v.valor_cuantitativo, v.valor_cualitativo, v.icono, v.color, v.orden
            FROM informes_configuracion ic
            INNER JOIN valores_parametros_calificaciones v
                    ON v.id_parametros_calificaciones = ic.id_parametro_evaluacion
                   AND v.id_tenant = ic.id_tenant
            WHERE ic.id_tenant = :id_tenant
            ORDER BY v.orden, v.valor_cuantitativo DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Califica, marca como no entregada o devuelve a pendiente la tarea de
     * un niño. Recibe id (de la fila), estado, id_valor_parametro_calificacion
     * y observacion.
     */
    public static function calificar()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, TareasEstudiantes::PERMISO);

            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;
            $estado = Flight::request()->data['estado'] ?? null;
            $idValor = Flight::request()->data['id_valor_parametro_calificacion'] ?? null;
            $observacion = Flight::request()->data['observacion'] ?? null;

            if (!$id || !in_array($estado, self::ESTADOS_JARDIN, true)) {
                Flight::json(array('error' => 'El registro y un estado válido son obligatorios'), 400);
                return;
            }

            $texto = null;
            $color = null;

            if ($estado === 'calificada') {
                if (!$idValor) {
                    Flight::json(array('error' => 'Seleccione la valoración'), 400);
                    return;
                }
                $valor = $db->prepare("SELECT valor_cualitativo, color FROM valores_parametros_calificaciones WHERE id = :id AND id_tenant = :id_tenant");
                $valor->bindParam(':id', $idValor);
                $valor->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $valor->execute();
                $fila = $valor->fetch();
                if (!$fila) {
                    Flight::json(array('error' => 'La valoración no existe'), 400);
                    return;
                }
                $texto = $fila['valor_cualitativo'];
                $color = $fila['color'];
            } else {
                $idValor = null;
            }

            // Si vuelve a pendiente se quita la calificacion. La entrega que
            // haya marcado el acudiente se conserva.
            $sentence = $db->prepare("
                UPDATE tareas_estudiantes_x_estudiante
                SET estado = :estado,
                    id_valor_parametro_calificacion = :id_valor,
                    valoracion_texto = :texto,
                    valoracion_color = :color,
                    observacion = :observacion,
                    fecha_calificacion = IF(:es_calificada = 1, NOW(), NULL),
                    id_usuario_califico = IF(:es_calificada2 = 1, :id_usuario, NULL)
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $esCalificada = $estado === 'calificada' ? 1 : 0;
            $sentence->bindValue(':estado', $estado);
            $sentence->bindValue(':id_valor', $idValor);
            $sentence->bindValue(':texto', $texto);
            $sentence->bindValue(':color', $color);
            $sentence->bindValue(':observacion', $observacion);
            $sentence->bindValue(':es_calificada', $esCalificada, PDO::PARAM_INT);
            $sentence->bindValue(':es_calificada2', $esCalificada, PDO::PARAM_INT);
            $sentence->bindValue(':id_usuario', $userData->id ?? null);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en TareasEstudiantesXEstudiante::calificar: " . $e->getMessage());
            Flight::json(array('error' => 'Error al calificar la tarea'), 500);
        }
    }

    /**
     * El acudiente marca que el niño ya hizo la tarea. Solo se puede mientras
     * este pendiente o enviada: si el jardin ya la califico o la marco como no
     * entregada, la marca del acudiente no cambia nada.
     */
    public static function marcarEnviada()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            $db = Flight::db();

            $idTarea = Flight::request()->data['id_tarea_estudiante'] ?? null;
            $idEstudiante = Flight::request()->data['id_estudiante'] ?? null;

            if (!$idTarea || !$idEstudiante) {
                Flight::json(array('error' => 'La tarea y el estudiante son obligatorios'), 400);
                return;
            }

            if (!TareasEstudiantes::esPortalPadres($userData)
                || !TareasEstudiantes::esDestinatario($db, $userData, $idTarea, $idEstudiante)) {
                Flight::json(array('error' => 'No tienes acceso a esta tarea', 'code' => 'FORBIDDEN'), 403);
                return;
            }

            $sentence = $db->prepare("
                UPDATE tareas_estudiantes_x_estudiante
                SET estado = 'enviada',
                    fecha_envio_acudiente = NOW(),
                    id_persona_envio = :id_persona
                WHERE id_tarea_estudiante = :id_tarea
                  AND id_estudiante = :id_estudiante
                  AND id_tenant = :id_tenant
                  AND estado = 'pendiente'
            ");
            $sentence->bindValue(':id_persona', $userData->id_persona ?? null);
            $sentence->bindParam(':id_tarea', $idTarea);
            $sentence->bindParam(':id_estudiante', $idEstudiante);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() === 0) {
                Flight::json(array('error' => 'La tarea ya no está pendiente'), 400);
                return;
            }

            Flight::json(array('id_tarea_estudiante' => $idTarea, 'id_estudiante' => $idEstudiante));
        } catch (Exception $e) {
            error_log("Error en TareasEstudiantesXEstudiante::marcarEnviada: " . $e->getMessage());
            Flight::json(array('error' => 'Error al marcar la tarea como enviada'), 500);
        }
    }
}
