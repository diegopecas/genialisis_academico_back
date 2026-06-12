<?php
class TiposPersonas
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, codigo, nombre, descripcion, activo
            FROM tipos_personas
            WHERE activo = 1
            ORDER BY id
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}