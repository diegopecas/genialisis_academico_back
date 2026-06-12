<?php
class TiposProductoMobiliario
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM tipos_producto_mobiliario WHERE activo = 1 ORDER BY nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}