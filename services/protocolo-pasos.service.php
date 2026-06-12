<?php
class ProtocoloPasos
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM protocolo_pasos WHERE activo = 1 ORDER BY orden");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM protocolo_pasos WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $numero_paso = Flight::request()->data['numero_paso'];
            $nombre = Flight::request()->data['nombre'];
            $descripcion = isset(Flight::request()->data['descripcion']) ? Flight::request()->data['descripcion'] : null;
            $objetivo = isset(Flight::request()->data['objetivo']) ? Flight::request()->data['objetivo'] : null;
            $script_sugerido = isset(Flight::request()->data['script_sugerido']) ? Flight::request()->data['script_sugerido'] : null;
            $checklist_items = isset(Flight::request()->data['checklist_items']) ? Flight::request()->data['checklist_items'] : null;
            $consejos = isset(Flight::request()->data['consejos']) ? Flight::request()->data['consejos'] : null;
            $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $sentence = $db->prepare("INSERT INTO protocolo_pasos (numero_paso, nombre, descripcion, objetivo, script_sugerido, checklist_items, consejos, orden, activo) VALUES (:numero_paso, :nombre, :descripcion, :objetivo, :script_sugerido, :checklist_items, :consejos, :orden, :activo)");
            $sentence->bindParam(':numero_paso', $numero_paso);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindParam(':objetivo', $objetivo);
            $sentence->bindParam(':script_sugerido', $script_sugerido);
            $sentence->bindParam(':checklist_items', $checklist_items);
            $sentence->bindParam(':consejos', $consejos);
            $sentence->bindParam(':orden', $orden);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en protocolo_pasos new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $numero_paso = Flight::request()->data['numero_paso'];
            $nombre = Flight::request()->data['nombre'];
            $descripcion = isset(Flight::request()->data['descripcion']) ? Flight::request()->data['descripcion'] : null;
            $objetivo = isset(Flight::request()->data['objetivo']) ? Flight::request()->data['objetivo'] : null;
            $script_sugerido = isset(Flight::request()->data['script_sugerido']) ? Flight::request()->data['script_sugerido'] : null;
            $checklist_items = isset(Flight::request()->data['checklist_items']) ? Flight::request()->data['checklist_items'] : null;
            $consejos = isset(Flight::request()->data['consejos']) ? Flight::request()->data['consejos'] : null;
            $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $sentence = $db->prepare("UPDATE protocolo_pasos SET numero_paso = :numero_paso, nombre = :nombre, descripcion = :descripcion, objetivo = :objetivo, script_sugerido = :script_sugerido, checklist_items = :checklist_items, consejos = :consejos, orden = :orden, activo = :activo WHERE id = :id");
            $sentence->bindParam(':numero_paso', $numero_paso);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindParam(':objetivo', $objetivo);
            $sentence->bindParam(':script_sugerido', $script_sugerido);
            $sentence->bindParam(':checklist_items', $checklist_items);
            $sentence->bindParam(':consejos', $consejos);
            $sentence->bindParam(':orden', $orden);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en protocolo_pasos replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM protocolo_pasos WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en protocolo_pasos delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}