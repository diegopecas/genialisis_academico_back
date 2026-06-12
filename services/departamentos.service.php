<?php 
class Departamentos
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select d.id, d.nombre, d.id_pais, p.nombre nombre_pais 
        from departamentos d
        inner join paises p on d.id_pais = p.id");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select d.id, d.nombre, d.id_pais, p.nombre nombre_pais 
        from departamentos d
        inner join paises p on d.id_pais = p.id
        where d.id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdPais($id_pais)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select d.id, d.nombre, d.id_pais, p.nombre nombre_pais 
        from departamentos d
        inner join paises p on d.id_pais = p.id
        where d.id_pais = :id_pais");
        $sentence->bindParam(':id_pais', $id_pais);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
}
