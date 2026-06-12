<?php
class TiposPrestamos
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM tipos_prestamos ORDER BY nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM tipos_prestamos WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];

        $sentence = $db->prepare("INSERT INTO tipos_prestamos(nombre) VALUES (:nombre)");
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

        $sentence = $db->prepare("UPDATE tipos_prestamos SET nombre = :nombre WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM tipos_prestamos WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
}