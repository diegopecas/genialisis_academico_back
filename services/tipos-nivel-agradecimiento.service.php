<?php
class TiposNivelAgradecimiento
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM tipos_nivel_agradecimiento WHERE activo = 1 ORDER BY id");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM tipos_nivel_agradecimiento WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $nombre = Flight::request()->data['nombre'];
            $codigo = Flight::request()->data['codigo'];
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $sentence = $db->prepare("INSERT INTO tipos_nivel_agradecimiento (nombre, codigo, activo) VALUES (:nombre, :codigo, :activo)");
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_nivel_agradecimiento new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $nombre = Flight::request()->data['nombre'];
            $codigo = Flight::request()->data['codigo'];
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $sentence = $db->prepare("UPDATE tipos_nivel_agradecimiento SET nombre = :nombre, codigo = :codigo, activo = :activo WHERE id = :id");
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en tipos_nivel_agradecimiento replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM tipos_nivel_agradecimiento WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_nivel_agradecimiento delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}