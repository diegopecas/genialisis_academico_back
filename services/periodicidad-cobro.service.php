<?php 
class PeriodicidadCobro
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM periodicidad_cobro ORDER BY nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM periodicidad_cobro WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];
        $sentence = $db->prepare("INSERT INTO periodicidad_cobro(nombre) VALUES (:nombre)");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        $sentence = $db->prepare("UPDATE periodicidad_cobro SET nombre = :nombre WHERE id = :id");
        $sentence->bindParam(':id', $data['id']);
        $sentence->bindParam(':nombre', $data['nombre']);
        $sentence->execute();
        self::getById($data['id']);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM periodicidad_cobro WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
}