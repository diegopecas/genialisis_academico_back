<?php
class EstadosActividades
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM estados_actividades WHERE activo = 1 ORDER BY id");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM estados_actividades WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}