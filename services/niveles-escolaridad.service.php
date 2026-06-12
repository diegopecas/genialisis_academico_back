<?php 
class NivelesEscolaridad
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre from niveles_escolaridad");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre from niveles_escolaridad where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];        
        $sentence = $db->prepare("insert into niveles_escolaridad(nombre) values (:nombre)");
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
        $sentence = $db->prepare("update niveles_escolaridad set nombre = :nombre where id = :id");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        // Flight::json(array('id' => $id));
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from niveles_escolaridad where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        // Flight::json(array('id' => $id));
        self::getById($id);
    }
    
}
