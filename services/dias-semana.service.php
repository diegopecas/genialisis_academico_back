<?php 
class DiasSemana
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select * from dias_semana");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
}
