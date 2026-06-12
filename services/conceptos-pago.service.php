<?php 
class ConceptosPago
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, descripcion, valor_defecto, estado from conceptos_pago");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
}
