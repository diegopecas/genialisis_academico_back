<?php 
class TelefonosPersonas
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_persona, telefono, activo from telefonos_personas");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_persona, telefono, activo from telefonos_personas where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdPersona($id_persona)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_persona, telefono, activo 
        from telefonos_personas 
        where id_persona = :id_persona");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_persona = Flight::request()->data['id_persona'];
        $telefono = Flight::request()->data['telefono'];
        $activo = Flight::request()->data['activo'];
        $sentence = $db->prepare("insert into telefonos_personas(id_persona, telefono, activo) values (:id_persona, :telefono, :activo)");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindParam(':telefono', $telefono);
        $sentence->bindParam(':activo', $activo);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_persona = Flight::request()->data['id_persona'];
        $telefono = Flight::request()->data['telefono'];
        $activo = Flight::request()->data['activo'];
        $sentence = $db->prepare("update telefonos_personas set id_persona = :id_persona, telefono = :telefono, activo = :activo where id = :id");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindParam(':telefono', $telefono);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from telefonos_personas where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }
    
}
