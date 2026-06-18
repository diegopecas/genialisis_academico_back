<?php 
class ValoresParametrosCalificaciones
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_parametros_calificaciones, valor_cuantitativo, valor_cualitativo, icono FROM valores_parametros_calificaciones WHERE id_tenant = ? ORDER BY id_parametros_calificaciones, valor_cuantitativo ASC");
        $sentence->execute([TenantContext::id()]);
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_parametros_calificaciones, valor_cuantitativo, valor_cualitativo, icono FROM valores_parametros_calificaciones WHERE id = ? AND id_tenant = ?");
        $sentence->execute([$id, TenantContext::id()]);
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function getByParametro($idParametro)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_parametros_calificaciones, valor_cuantitativo, valor_cualitativo, icono FROM valores_parametros_calificaciones WHERE id_parametros_calificaciones = ? AND id_tenant = ? ORDER BY valor_cuantitativo ASC");
        $sentence->execute([$idParametro, TenantContext::id()]);
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $idNew = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO valores_parametros_calificaciones (id, id_tenant, id_parametros_calificaciones, valor_cuantitativo, valor_cualitativo, icono) VALUES (?, ?, ?, ?, ?, ?)");
        $sentence->execute([
            $idNew,
            TenantContext::id(),
            $data->id_parametros_calificaciones,
            $data->valor_cuantitativo,
            $data->valor_cualitativo,
            $data->icono
        ]);
        
        $lastId = $idNew;
        Flight::json(['id' => $lastId, 'message' => 'Valor creado exitosamente']);
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $sentence = $db->prepare("UPDATE valores_parametros_calificaciones SET id_parametros_calificaciones = ?, valor_cuantitativo = ?, valor_cualitativo = ?, icono = ? WHERE id = ? AND id_tenant = ?");
        $sentence->execute([
            $data->id_parametros_calificaciones,
            $data->valor_cuantitativo,
            $data->valor_cualitativo,
            $data->icono,
            $data->id,
            TenantContext::id()
        ]);
        
        Flight::json(['message' => 'Valor actualizado exitosamente']);
    }

    public static function delete()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $sentence = $db->prepare("DELETE FROM valores_parametros_calificaciones WHERE id = ? AND id_tenant = ?");
        $sentence->execute([$data->id, TenantContext::id()]);
        
        Flight::json(['message' => 'Valor eliminado exitosamente']);
    }
    
}