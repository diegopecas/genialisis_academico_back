<?php
class DiasXSprint
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
            dxs.id,
            dxs.id_sprint,
            dxs.id_dia_semana,
            dxs.total_dias,
            s.nombre_sprint,
            ds.nombre as nombre_dia
        FROM dias_x_sprint dxs
        LEFT JOIN sprints s ON dxs.id_sprint = s.id
        LEFT JOIN dias_semana ds ON dxs.id_dia_semana = ds.id
        ORDER BY dxs.id_sprint, dxs.id_dia_semana");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM dias_x_sprint WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getBySprintId($id_sprint)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
            dxs.id,
            dxs.id_sprint,
            dxs.id_dia_semana,
            dxs.total_dias,
            ds.nombre as nombre_dia
        FROM dias_x_sprint dxs
        LEFT JOIN dias_semana ds ON dxs.id_dia_semana = ds.id
        WHERE dxs.id_sprint = :id_sprint
        ORDER BY dxs.id_dia_semana");
        $sentence->bindParam(':id_sprint', $id_sprint);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $id_sprint = Flight::request()->data['id_sprint'];
            $id_dia_semana = Flight::request()->data['id_dia_semana'];
            $total_dias = Flight::request()->data['total_dias'];

            error_log("Creando días por sprint: sprint=$id_sprint, dia=$id_dia_semana, total=$total_dias");

            // Verificar si ya existe
            $checkSentence = $db->prepare("SELECT id FROM dias_x_sprint 
                WHERE id_sprint = :id_sprint AND id_dia_semana = :id_dia_semana");
            $checkSentence->bindParam(':id_sprint', $id_sprint);
            $checkSentence->bindParam(':id_dia_semana', $id_dia_semana);
            $checkSentence->execute();
            $existing = $checkSentence->fetch();

            if ($existing) {
                // Actualizar si ya existe
                $sentence = $db->prepare("UPDATE dias_x_sprint SET total_dias = :total_dias 
                    WHERE id_sprint = :id_sprint AND id_dia_semana = :id_dia_semana");
                $sentence->bindParam(':total_dias', $total_dias);
                $sentence->bindParam(':id_sprint', $id_sprint);
                $sentence->bindParam(':id_dia_semana', $id_dia_semana);
                $sentence->execute();
                $id = $existing['id'];
            } else {
                // Crear nuevo
                $sentence = $db->prepare("INSERT INTO dias_x_sprint (id_sprint, id_dia_semana, total_dias) 
                    VALUES (:id_sprint, :id_dia_semana, :total_dias)");
                $sentence->bindParam(':id_sprint', $id_sprint);
                $sentence->bindParam(':id_dia_semana', $id_dia_semana);
                $sentence->bindParam(':total_dias', $total_dias);
                $sentence->execute();
                $id = $db->lastInsertId();
            }

            error_log("Días por sprint guardado con ID: $id");
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al crear días por sprint: " . $e->getMessage());
            Flight::json(array('error' => 'Error al guardar días por sprint'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $total_dias = Flight::request()->data['total_dias'];

            error_log("Actualizando días por sprint ID: $id");

            $sentence = $db->prepare("UPDATE dias_x_sprint SET total_dias = :total_dias WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':total_dias', $total_dias);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el registro con el ID especificado'), 404);
                return;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al actualizar días por sprint: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar días por sprint'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            error_log("Eliminando días por sprint ID: $id");

            $sentence = $db->prepare("DELETE FROM dias_x_sprint WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al eliminar días por sprint: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar días por sprint'), 500);
        }
    }

    // Método para calcular días hábiles de un sprint
    public static function calcularDiasHabiles($fecha_inicial, $fecha_final)
    {
        try {
            $db = Flight::db();
            
            // Obtener todos los días entre las fechas que sean tipo 1 (día hábil)
            $sentence = $db->prepare("SELECT 
                COUNT(*) as dias_habiles,
                id_dia_semana,
                COUNT(*) as cantidad
            FROM calendarios
            WHERE fecha BETWEEN :fecha_inicial AND :fecha_final
                AND id_tipo_dia = 1
            GROUP BY id_dia_semana");
            
            $sentence->bindParam(':fecha_inicial', $fecha_inicial);
            $sentence->bindParam(':fecha_final', $fecha_final);
            $sentence->execute();
            
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error al calcular días hábiles: " . $e->getMessage());
            Flight::json(array('error' => 'Error al calcular días hábiles'), 500);
        }
    }
    // Eliminar todos los días de un sprint
    public static function eliminarPorSprint($id_sprint)
    {
        $db = Flight::db();
        
        try {
            error_log("Eliminando días del sprint ID: $id_sprint");
            
            $sentence = $db->prepare("DELETE FROM dias_x_sprint WHERE id_sprint = :id_sprint");
            $sentence->bindParam(':id_sprint', $id_sprint);
            $sentence->execute();
            
            $count = $sentence->rowCount();
            error_log("Días eliminados: $count");
            
            Flight::json(['eliminados' => $count]);
        } catch (Exception $e) {
            error_log("Error al eliminar días del sprint: " . $e->getMessage());
            Flight::json(['error' => 'Error al eliminar días del sprint'], 500);
        }
    }
}