<?php 
class DireccionesPersonas
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select dp.id, dp.id_persona, dp.direccion, dp.id_ciudad, c.nombre nombre_ciudad, c.id_departamento, d.nombre nombre_departamento, d.id_pais, p.nombre nombre_pais, dp.descripcion, dp.activa 
        from direcciones_personas dp 
        inner join ciudades c on dp.id_ciudad = c.id
        inner join departamentos d on c.id_departamento = d.id 
        inner join paises p on d.id_pais = p.id");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select dp.id, dp.id_persona, dp.direccion, dp.id_ciudad, c.nombre nombre_ciudad, c.id_departamento, d.nombre nombre_departamento, d.id_pais, p.nombre nombre_pais, dp.descripcion, dp.activa 
        from direcciones_personas dp 
        inner join ciudades c on dp.id_ciudad = c.id
        inner join departamentos d on c.id_departamento = d.id 
        inner join paises p on d.id_pais = p.id
        where dp.id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdPersona($id_persona)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select dp.id, dp.id_persona, dp.direccion, dp.id_ciudad, c.nombre nombre_ciudad, c.id_departamento, d.nombre nombre_departamento, d.id_pais, p.nombre nombre_pais, dp.descripcion, dp.activa 
        from direcciones_personas dp 
        inner join ciudades c on dp.id_ciudad = c.id
        inner join departamentos d on c.id_departamento = d.id 
        inner join paises p on d.id_pais = p.id
        where dp.id_persona = :id_persona");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_persona = Flight::request()->data['id_persona'];
        $direccion = Flight::request()->data['direccion'];
        $id_ciudad = Flight::request()->data['id_ciudad'];
        $descripcion = Flight::request()->data['descripcion'];
        $activa = Flight::request()->data['activa'];
        $sentence = $db->prepare("insert into direcciones_personas(id_persona, direccion, id_ciudad, descripcion, activa) values (:id_persona, :direccion, :id_ciudad, :descripcion, :activa)");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindParam(':direccion', $direccion);
        $sentence->bindParam(':id_ciudad', $id_ciudad);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':activa', $activa);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_persona = Flight::request()->data['id_persona'];
        $direccion = Flight::request()->data['direccion'];
        $id_ciudad = Flight::request()->data['id_ciudad'];
        $descripcion = Flight::request()->data['descripcion'];
        $activa = Flight::request()->data['activa'];
        $sentence = $db->prepare("update direcciones_personas set id_persona = :id_persona, direccion = :direccion, id_ciudad = :id_ciudad, descripcion = :descripcion, activa = :activa where id = :id");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindParam(':direccion', $direccion);
        $sentence->bindParam(':id_ciudad', $id_ciudad);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':activa', $activa);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        // Flight::json(array('id' => $id));
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from direcciones_personas where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        // Flight::json(array('id' => $id));
        self::getById($id);
    }
    
}
