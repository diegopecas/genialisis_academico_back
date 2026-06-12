<?php
class TiposProcesosLimpieza
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT * FROM tipos_proceso_limpieza 
            WHERE activo = 1 
            ORDER BY nombre ASC
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT * FROM tipos_proceso_limpieza 
            WHERE id = :id
        ");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}