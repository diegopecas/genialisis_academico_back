<?php 
class TiposEventoCalendario
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select * from tipos_evento_calendario");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
}
