<?php 
class ParametrosCalificaciones
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM parametros_calificaciones ORDER BY nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM parametros_calificaciones WHERE id = ?");
        $sentence->execute([$id]);
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $sentence = $db->prepare("INSERT INTO parametros_calificaciones (nombre) VALUES (?)");
        $sentence->execute([
            $data->nombre
        ]);
        
        $lastId = $db->lastInsertId();
        Flight::json(['id' => $lastId, 'message' => 'Parámetro creado exitosamente']);
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $sentence = $db->prepare("UPDATE parametros_calificaciones SET nombre = ? WHERE id = ?");
        $sentence->execute([
            $data->nombre,
            $data->id
        ]);
        
        Flight::json(['message' => 'Parámetro actualizado exitosamente']);
    }

    public static function delete()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        // Primero eliminar los valores asociados
        $sentence = $db->prepare("DELETE FROM valores_parametros_calificaciones WHERE id_parametros_calificaciones = ?");
        $sentence->execute([$data->id]);
        
        // Luego eliminar el parámetro
        $sentence = $db->prepare("DELETE FROM parametros_calificaciones WHERE id = ?");
        $sentence->execute([$data->id]);
        
        Flight::json(['message' => 'Parámetro eliminado exitosamente']);
    }
    
}