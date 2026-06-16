<?php 
class CasasDocentes
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select *, (select IFNULL(sum(valor),0) from puntos_casas_docentes where id_casa_docente = cd.id) puntos from casas_docentes cd");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select * from casas_docentes where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function restarPuntosEntregar()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $valor = Flight::request()->data['valor'];
        $sentence = $db->prepare("update casas_docentes set puntos_entregar = puntos_entregar - :valor where id = :id");
        $sentence->bindParam(':valor', $valor);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        // Flight::json(array('id' => $id));
        self::getById($id);
    }

    public static function restarPuntosQuitar()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $valor = Flight::request()->data['valor'];
        
        $sentence = $db->prepare("update casas_docentes set puntos_quitar = puntos_quitar - :valor where id = :id");
        $sentence->bindParam(':valor', $valor);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        // Flight::json(array('id' => $id));
        self::getById($id);
    }
    
}
