<?php 
class Onces
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, referencia, valor, imagen, estado from onces");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

}
