<?php
class Porciones
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT * FROM porciones 
            ORDER BY nombre ASC
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM porciones WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        
        $nombre = Flight::request()->data['nombre'];
        $activo = Flight::request()->data['activo'] ?? 1;
        
        $sentence = $db->prepare("
            INSERT INTO porciones (nombre, activo)
            VALUES (:nombre, :activo)
        ");
        
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':activo', $activo);
        $sentence->execute();
        
        $id = $db->lastInsertId();
        Flight::json(['id' => $id]);
    }

    public static function replace()
    {
        $db = Flight::db();
        
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $activo = Flight::request()->data['activo'] ?? 1;
        
        $sentence = $db->prepare("
            UPDATE porciones SET 
                nombre = :nombre,
                activo = :activo
            WHERE id = :id
        ");
        
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        
        try {
            // Verificar si la porción está siendo usada
            $checkStmt = $db->prepare("
                SELECT COUNT(*) as total 
                FROM items_menu_ingredientes 
                WHERE id_porcion = :id
            ");
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            $result = $checkStmt->fetch();
            
            if ($result['total'] > 0) {
                Flight::json(['error' => 'No se puede eliminar la porción porque está siendo usada en recetas'], 400);
                return;
            }
            
            $sentence = $db->prepare("DELETE FROM porciones WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            
            Flight::json(['id' => $id, 'success' => true]);
            
        } catch (Exception $e) {
            Flight::json(['error' => 'Error al eliminar la porción: ' . $e->getMessage()], 500);
        }
    }

    public static function getActivas()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT * FROM porciones 
            WHERE activo = 1
            ORDER BY nombre ASC
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}