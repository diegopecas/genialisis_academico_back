<?php
class TiposActividadesColaboradores
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT tac.*, ca.nombre as nombre_categoria 
            FROM tipos_actividades_colaboradores tac
            INNER JOIN categorias_actividades ca ON tac.id_categoria = ca.id
            WHERE tac.activo = 1 
            ORDER BY ca.nombre, tac.nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT tac.*, ca.nombre as nombre_categoria 
            FROM tipos_actividades_colaboradores tac
            INNER JOIN categorias_actividades ca ON tac.id_categoria = ca.id
            WHERE tac.id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByCategoria($id_categoria)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM tipos_actividades_colaboradores 
            WHERE id_categoria = :id_categoria AND activo = 1 
            ORDER BY nombre");
        $sentence->bindParam(':id_categoria', $id_categoria);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}