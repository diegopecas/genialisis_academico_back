<?php
class TiposProductoAcademico
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT * FROM tipos_producto_academico 
            WHERE activo = 1 
            ORDER BY nombre ASC
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}