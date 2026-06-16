<?php 
class TiposNecesidadesEspeciales
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT id, nombre, icono, orden 
                FROM tipos_necesidades_especiales 
                WHERE activo = 1 
                ORDER BY orden, nombre
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en tipos_necesidades_especiales getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
    
    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT id, nombre, icono, orden 
                FROM tipos_necesidades_especiales 
                WHERE id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en tipos_necesidades_especiales getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;
            
            $sentence = $db->prepare("
                INSERT INTO tipos_necesidades_especiales(nombre, icono, orden) 
                VALUES (:nombre, :icono, :orden)
            ");
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':icono', $data['icono']);
            $sentence->bindParam(':orden', $data['orden']);
            $sentence->execute();
            
            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_necesidades_especiales new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;
            
            $sentence = $db->prepare("
                UPDATE tipos_necesidades_especiales 
                SET nombre = :nombre, icono = :icono, orden = :orden 
                WHERE id = :id
            ");
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':icono', $data['icono']);
            $sentence->bindParam(':orden', $data['orden']);
            $sentence->bindParam(':id', $data['id']);
            $sentence->execute();
            
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en tipos_necesidades_especiales replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            
            $sentence = $db->prepare("DELETE FROM tipos_necesidades_especiales WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_necesidades_especiales delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}