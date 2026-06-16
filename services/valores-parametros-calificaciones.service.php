<?php 
class ValoresParametrosCalificaciones
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_parametros_calificaciones, valor_cuantitativo, valor_cualitativo, icono FROM valores_parametros_calificaciones ORDER BY id_parametros_calificaciones, valor_cuantitativo ASC");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_parametros_calificaciones, valor_cuantitativo, valor_cualitativo, icono FROM valores_parametros_calificaciones WHERE id = ?");
        $sentence->execute([$id]);
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function getByParametro($idParametro)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_parametros_calificaciones, valor_cuantitativo, valor_cualitativo, icono FROM valores_parametros_calificaciones WHERE id_parametros_calificaciones = ? ORDER BY valor_cuantitativo ASC");
        $sentence->execute([$idParametro]);
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $sentence = $db->prepare("INSERT INTO valores_parametros_calificaciones (id_parametros_calificaciones, valor_cuantitativo, valor_cualitativo, icono) VALUES (?, ?, ?, ?)");
        $sentence->execute([
            $data->id_parametros_calificaciones,
            $data->valor_cuantitativo,
            $data->valor_cualitativo,
            $data->icono
        ]);
        
        $lastId = $db->lastInsertId();
        Flight::json(['id' => $lastId, 'message' => 'Valor creado exitosamente']);
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $sentence = $db->prepare("UPDATE valores_parametros_calificaciones SET id_parametros_calificaciones = ?, valor_cuantitativo = ?, valor_cualitativo = ?, icono = ? WHERE id = ?");
        $sentence->execute([
            $data->id_parametros_calificaciones,
            $data->valor_cuantitativo,
            $data->valor_cualitativo,
            $data->icono,
            $data->id
        ]);
        
        Flight::json(['message' => 'Valor actualizado exitosamente']);
    }

    public static function delete()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $sentence = $db->prepare("DELETE FROM valores_parametros_calificaciones WHERE id = ?");
        $sentence->execute([$data->id]);
        
        Flight::json(['message' => 'Valor eliminado exitosamente']);
    }
    
}