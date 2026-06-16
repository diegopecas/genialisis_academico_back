<?php 
class Grupos
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono, color, calificable, orden from grupos order by orden");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono, color, calificable, orden from grupos where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];
        $icono = Flight::request()->data['icono'];
        $color = Flight::request()->data['color'];
        $calificable = Flight::request()->data['calificable'];
        $orden = Flight::request()->data['orden'];
        
        $sentence = $db->prepare("insert into grupos(nombre, icono, color, calificable, orden) values (:nombre, :icono, :color, :calificable, :orden)");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':color', $color);
        $sentence->bindParam(':calificable', $calificable);
        $sentence->bindParam(':orden', $orden);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $icono = Flight::request()->data['icono'];
        $color = Flight::request()->data['color'];
        $calificable = Flight::request()->data['calificable'];
        $orden = Flight::request()->data['orden'];
        
        $sentence = $db->prepare("update grupos set nombre = :nombre, icono = :icono, color = :color, calificable = :calificable, orden = :orden where id = :id");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':color', $color);
        $sentence->bindParam(':calificable', $calificable);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from grupos where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }
    
}