<?php 
class Onces
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, referencia, valor, imagen, estado from onces where id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

}
