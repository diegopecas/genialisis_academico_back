<?php 
class Ciudades
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select c.id, c.nombre, c.id_departamento, d.nombre nombre_departamento 
        from ciudades c
        inner join departamentos d on c.id_departamento = d.id");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select c.id, c.nombre, c.id_departamento, d.nombre nombre_departamento 
        from ciudades c
        inner join departamentos d on c.id_departamento = d.id
        where c.id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdDepartamento($id_departamento)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select c.id, c.nombre, c.id_departamento, d.nombre nombre_departamento 
        from ciudades c
        inner join departamentos d on c.id_departamento = d.id
        where c.id_departamento = :id_departamento");
        $sentence->bindParam(':id_departamento', $id_departamento);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
}
