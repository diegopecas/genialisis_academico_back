<?php 
class EstadosTareas
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM estados_tareas ORDER BY id");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM estados_tareas WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];
        
        $sentence = $db->prepare("INSERT INTO estados_tareas (nombre) VALUES (:nombre)");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->execute();
        
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        
        $sentence = $db->prepare("UPDATE estados_tareas SET nombre = :nombre WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->execute();
        
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        
        // Verificar si el estado está siendo usado en tareas
        $checkSentence = $db->prepare("SELECT COUNT(*) as total FROM tareas_x_sprints WHERE id_estado_tarea = :id");
        $checkSentence->bindParam(':id', $id);
        $checkSentence->execute();
        $result = $checkSentence->fetch();
        
        if ($result['total'] > 0) {
            Flight::json(array('error' => 'No se puede eliminar el estado porque está siendo usado en tareas'), 400);
            return;
        }
        
        $sentence = $db->prepare("DELETE FROM estados_tareas WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        
        Flight::json(array('id' => $id));
    }
}