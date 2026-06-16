<?php 
class AreasAcademicas
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono, color from areas_academicas order by nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getAllList()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono from areas_academicas order by nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono, color from areas_academicas where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];
        $icono = Flight::request()->data['icono'];
        $color = Flight::request()->data['color'] ?? '#FFFFFF';
        
        $sentence = $db->prepare("insert into areas_academicas(nombre, icono, color) values (:nombre, :icono, :color)");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':color', $color);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $icono = Flight::request()->data['icono'];
        $color = Flight::request()->data['color'] ?? '#FFFFFF';
        
        $sentence = $db->prepare("update areas_academicas set nombre = :nombre, icono = :icono, color = :color where id = :id");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':color', $color);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from areas_academicas where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }
    
    public static function getByGrupo($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            select g.id id_grupo, g.nombre nombre_grupo, a.id id_area_academica, a.nombre nombre_area_academica, axg.id_docente, a.icono, a.color
            from area_academica_x_grupo axg 
            inner join areas_academicas a on axg.id_area_academica = a.id
            inner join grupos g on axg.id_grupo = g.id 
            where g.id = :id
            ");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getDisponiblesPorGrupo($id_grupo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, icono, color
            FROM areas_academicas 
            WHERE id NOT IN (
                SELECT id_area_academica 
                FROM area_academica_x_grupo 
                WHERE id_grupo = :id_grupo
            )
            ORDER BY nombre
        ");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}