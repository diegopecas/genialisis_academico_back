<?php 
class Grados
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, descripcion, orden from grados order by orden asc");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, descripcion, orden from grados where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];
        $descripcion = Flight::request()->data['descripcion'];
        $orden = Flight::request()->data['orden'];
        $sentence = $db->prepare("insert into grados(nombre, descripcion, orden) values (:nombre, :descripcion, :orden)");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':orden', $orden, PDO::PARAM_INT);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $descripcion = Flight::request()->data['descripcion'];
        $orden = Flight::request()->data['orden'];
        $sentence = $db->prepare("update grados set nombre = :nombre, descripcion = :descripcion, orden = :orden where id = :id");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':orden', $orden, PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from grados where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }
    
    // Obtener grados disponibles (no asociados) para un grupo
    public static function getDisponiblesPorGrupo($id_grupo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre 
            FROM grados 
            WHERE id NOT IN (
                SELECT id_grado 
                FROM grados_x_grupo 
                WHERE id_grupo = :id_grupo
            )
            ORDER BY orden ASC
        ");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
}