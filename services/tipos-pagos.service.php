<?php 
class TiposPagos
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, visible_portal_padres from tipos_pagos");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, visible_portal_padres from tipos_pagos where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];        
        $sentence = $db->prepare("insert into tipos_pagos(nombre) values (:nombre)");
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
        $sentence = $db->prepare("update tipos_pagos set nombre = :nombre where id = :id");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from tipos_pagos where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }
    
}