<?php 
class Paises
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre from paises");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
}
