<?php 
class RolesColaborador
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, descripcion, activo 
        FROM roles_colaborador 
        ORDER BY nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, descripcion, activo 
        FROM roles_colaborador 
        WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        
        Flight::json($response);
    }
}