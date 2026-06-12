<?php 
class TiposPuntos
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select * from tipos_puntos");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
}
