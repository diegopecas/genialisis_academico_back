<?php
class TiposDatosMedicos
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, icono, orden, activo FROM tipos_datos_medicos ORDER BY orden, nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, icono, orden, activo FROM tipos_datos_medicos WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $nombre = Flight::request()->data['nombre'];
        $icono = isset(Flight::request()->data['icono']) ? Flight::request()->data['icono'] : null;
        $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
        $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

        $sentence = $db->prepare("INSERT INTO tipos_datos_medicos (nombre, icono, orden, activo) VALUES (:nombre, :icono, :orden, :activo)");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->execute();

        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $icono = isset(Flight::request()->data['icono']) ? Flight::request()->data['icono'] : null;
        $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
        $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

        $sentence = $db->prepare("UPDATE tipos_datos_medicos SET nombre = :nombre, icono = :icono, orden = :orden, activo = :activo WHERE id = :id");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM tipos_datos_medicos WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
}