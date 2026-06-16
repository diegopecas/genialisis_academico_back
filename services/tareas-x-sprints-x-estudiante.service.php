<?php
class TareasXSprintsXEstudiante
{
    public static function getByTareaSprint($id_tarea_x_sprint)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT txse.id, txse.id_tarea_x_sprint, txse.id_estudiante, txse.observacion
            FROM tareas_x_sprints_x_estudiante txse
            WHERE txse.id_tarea_x_sprint = :id_tarea_x_sprint
        ");
        $sentence->bindParam(':id_tarea_x_sprint', $id_tarea_x_sprint);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }

    public static function crear()
    {
        try {
            $db = Flight::db();
            $id_tarea_x_sprint = Flight::request()->data['id_tarea_x_sprint'];
            $id_estudiante = Flight::request()->data['id_estudiante'];

            // Verificar si ya existe
            $check = $db->prepare("
                SELECT id FROM tareas_x_sprints_x_estudiante 
                WHERE id_tarea_x_sprint = :id_tarea AND id_estudiante = :id_est
            ");
            $check->bindParam(':id_tarea', $id_tarea_x_sprint);
            $check->bindParam(':id_est', $id_estudiante);
            $check->execute();
            $existe = $check->fetch(PDO::FETCH_ASSOC);

            if ($existe) {
                Flight::json(array('id' => $existe['id']));
                return;
            }

            $sentence = $db->prepare("
                INSERT INTO tareas_x_sprints_x_estudiante (id_tarea_x_sprint, id_estudiante)
                VALUES (:id_tarea_x_sprint, :id_estudiante)
            ");
            $sentence->bindParam(':id_tarea_x_sprint', $id_tarea_x_sprint);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en TareasXSprintsXEstudiante::crear: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear registro: ' . $e->getMessage()), 500);
        }
    }

    public static function actualizarObservacion()
    {
        try {
            $db = Flight::db();
            $id_tarea_x_sprint = Flight::request()->data['id_tarea_x_sprint'];
            $id_estudiante = Flight::request()->data['id_estudiante'];
            $observacion = Flight::request()->data['observacion'] ?? null;

            // Verificar si existe el registro
            $check = $db->prepare("
                SELECT id FROM tareas_x_sprints_x_estudiante 
                WHERE id_tarea_x_sprint = :id_tarea AND id_estudiante = :id_est
            ");
            $check->bindParam(':id_tarea', $id_tarea_x_sprint);
            $check->bindParam(':id_est', $id_estudiante);
            $check->execute();
            $existe = $check->fetch(PDO::FETCH_ASSOC);

            if ($existe) {
                // Actualizar observación
                $sentence = $db->prepare("
                    UPDATE tareas_x_sprints_x_estudiante 
                    SET observacion = :observacion 
                    WHERE id = :id
                ");
                $sentence->bindParam(':observacion', $observacion);
                $sentence->bindParam(':id', $existe['id']);
                $sentence->execute();
                Flight::json(array('id' => $existe['id']));
            } else {
                // Crear registro con observación
                $sentence = $db->prepare("
                    INSERT INTO tareas_x_sprints_x_estudiante (id_tarea_x_sprint, id_estudiante, observacion)
                    VALUES (:id_tarea_x_sprint, :id_estudiante, :observacion)
                ");
                $sentence->bindParam(':id_tarea_x_sprint', $id_tarea_x_sprint);
                $sentence->bindParam(':id_estudiante', $id_estudiante);
                $sentence->bindParam(':observacion', $observacion);
                $sentence->execute();
                $id = $db->lastInsertId();
                Flight::json(array('id' => $id));
            }
        } catch (Exception $e) {
            error_log("Error en TareasXSprintsXEstudiante::actualizarObservacion: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar observación: ' . $e->getMessage()), 500);
        }
    }
}