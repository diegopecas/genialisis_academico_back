<?php 
class OncesPersonas
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_persona, id_onces, fecha, estado from onces_personas");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_persona, id_onces, fecha, estado from onces_personas where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdPersona($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_persona, id_onces, fecha, estado from onces_personas where id_persona = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function new()
    {
        $db = Flight::db();
        $id_persona = Flight::request()->data['id_persona'];
        $id_onces = Flight::request()->data['id_onces'];
        $sentence = $db->prepare("insert into onces_personas(id_persona, id_onces) values (:id_persona, :id_onces)");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindParam(':id_onces', $id_onces);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

}
