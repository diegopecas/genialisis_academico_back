<?php 
class ClasificacionProductosServicios
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono from clasificacion_productos_servicios");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono from clasificacion_productos_servicios where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];        
        $sentence = $db->prepare("insert into clasificacion_productos_servicios(nombre, icono) values (:nombre)");
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
        $sentence = $db->prepare("update clasificacion_productos_servicios set nombre = :nombre, icono = :icono where id = :id");
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
        $sentence = $db->prepare("delete from clasificacion_productos_servicios where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        // Flight::json(array('id' => $id));
        self::getById($id);
    }
    
}
