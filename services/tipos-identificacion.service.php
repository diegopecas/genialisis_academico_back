<?php 
class TiposIdentificacion
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select * from tipos_identificacion");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
}
