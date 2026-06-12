<?php 
class MotivosRetiro
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM motivos_retiro ORDER BY nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM motivos_retiro WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}