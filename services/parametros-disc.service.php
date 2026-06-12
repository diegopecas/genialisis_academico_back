<?php
class ParametrosDisc
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM parametros_disc WHERE activo = 1 ORDER BY categoria, orden");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM parametros_disc WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByCategoria($categoria)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM parametros_disc WHERE categoria = :categoria AND activo = 1 ORDER BY orden");
        $sentence->bindParam(':categoria', $categoria);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByPerfil($perfil)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM parametros_disc WHERE perfil_asociado = :perfil AND activo = 1 ORDER BY categoria, orden");
        $sentence->bindParam(':perfil', $perfil);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $categoria = Flight::request()->data['categoria'];
            $descripcion = Flight::request()->data['descripcion'];
            $perfil_asociado = Flight::request()->data['perfil_asociado'];
            $peso = isset(Flight::request()->data['peso']) ? Flight::request()->data['peso'] : 1;
            $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $sentence = $db->prepare("INSERT INTO parametros_disc (categoria, descripcion, perfil_asociado, peso, orden, activo) VALUES (:categoria, :descripcion, :perfil_asociado, :peso, :orden, :activo)");
            $sentence->bindParam(':categoria', $categoria);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindParam(':perfil_asociado', $perfil_asociado);
            $sentence->bindParam(':peso', $peso);
            $sentence->bindParam(':orden', $orden);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en parametros_disc new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $categoria = Flight::request()->data['categoria'];
            $descripcion = Flight::request()->data['descripcion'];
            $perfil_asociado = Flight::request()->data['perfil_asociado'];
            $peso = isset(Flight::request()->data['peso']) ? Flight::request()->data['peso'] : 1;
            $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $sentence = $db->prepare("UPDATE parametros_disc SET categoria = :categoria, descripcion = :descripcion, perfil_asociado = :perfil_asociado, peso = :peso, orden = :orden, activo = :activo WHERE id = :id");
            $sentence->bindParam(':categoria', $categoria);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindParam(':perfil_asociado', $perfil_asociado);
            $sentence->bindParam(':peso', $peso);
            $sentence->bindParam(':orden', $orden);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en parametros_disc replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM parametros_disc WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en parametros_disc delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}