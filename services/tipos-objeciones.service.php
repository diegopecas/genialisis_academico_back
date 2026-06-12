<?php
class TiposObjeciones
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM tipos_objeciones WHERE activo = 1 ORDER BY orden");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM tipos_objeciones WHERE id = :id");
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
            $descripcion = isset(Flight::request()->data['descripcion']) ? Flight::request()->data['descripcion'] : null;
            $estrategia = isset(Flight::request()->data['estrategia']) ? Flight::request()->data['estrategia'] : null;
            $role_plays = isset(Flight::request()->data['role_plays']) ? Flight::request()->data['role_plays'] : null;
            $respuestas_rapidas = isset(Flight::request()->data['respuestas_rapidas']) ? Flight::request()->data['respuestas_rapidas'] : null;
            $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $sentence = $db->prepare("INSERT INTO tipos_objeciones (nombre, descripcion, estrategia, role_plays, respuestas_rapidas, orden, activo) VALUES (:nombre, :descripcion, :estrategia, :role_plays, :respuestas_rapidas, :orden, :activo)");
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindParam(':estrategia', $estrategia);
            $sentence->bindParam(':role_plays', $role_plays);
            $sentence->bindParam(':respuestas_rapidas', $respuestas_rapidas);
            $sentence->bindParam(':orden', $orden);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_objeciones new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $nombre = Flight::request()->data['nombre'];
            $descripcion = isset(Flight::request()->data['descripcion']) ? Flight::request()->data['descripcion'] : null;
            $estrategia = isset(Flight::request()->data['estrategia']) ? Flight::request()->data['estrategia'] : null;
            $role_plays = isset(Flight::request()->data['role_plays']) ? Flight::request()->data['role_plays'] : null;
            $respuestas_rapidas = isset(Flight::request()->data['respuestas_rapidas']) ? Flight::request()->data['respuestas_rapidas'] : null;
            $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $sentence = $db->prepare("UPDATE tipos_objeciones SET nombre = :nombre, descripcion = :descripcion, estrategia = :estrategia, role_plays = :role_plays, respuestas_rapidas = :respuestas_rapidas, orden = :orden, activo = :activo WHERE id = :id");
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindParam(':estrategia', $estrategia);
            $sentence->bindParam(':role_plays', $role_plays);
            $sentence->bindParam(':respuestas_rapidas', $respuestas_rapidas);
            $sentence->bindParam(':orden', $orden);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en tipos_objeciones replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM tipos_objeciones WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_objeciones delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}