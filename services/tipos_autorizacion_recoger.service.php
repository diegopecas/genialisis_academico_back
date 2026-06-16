<?php
class TiposAutorizacionRecoger
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM tipos_autorizacion_recoger ORDER BY id ASC");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM tipos_autorizacion_recoger WHERE id = :id");
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

            $sentence = $db->prepare("INSERT INTO tipos_autorizacion_recoger (nombre) VALUES (:nombre)");
            $sentence->bindParam(':nombre', $nombre);
            $sentence->execute();
            $id = $db->lastInsertId();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en TiposAutorizacionRecoger::new: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $nombre = Flight::request()->data['nombre'];

            $sentence = $db->prepare("UPDATE tipos_autorizacion_recoger SET nombre = :nombre WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en TiposAutorizacionRecoger::replace: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function delete($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("DELETE FROM tipos_autorizacion_recoger WHERE id = :id");
            $sentence->bindParam(':id', $id, PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() > 0) {
                Flight::json(["success" => true, "message" => "Registro eliminado correctamente"]);
            } else {
                Flight::json(["success" => false, "message" => "No se encontró el registro"], 404);
            }
        } catch (Exception $e) {
            Flight::json(["success" => false, "error" => $e->getMessage()], 500);
        }
    }
}