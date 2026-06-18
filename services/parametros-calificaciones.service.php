<?php 
class ParametrosCalificaciones
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM parametros_calificaciones WHERE id_tenant = ? ORDER BY nombre");
        $sentence->execute([TenantContext::id()]);
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM parametros_calificaciones WHERE id = ? AND id_tenant = ?");
        $sentence->execute([$id, TenantContext::id()]);
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $idNew = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO parametros_calificaciones (id, id_tenant, nombre) VALUES (?, ?, ?)");
        $sentence->execute([
            $idNew,
            TenantContext::id(),
            $data->nombre
        ]);
        
        $lastId = $idNew;
        Flight::json(['id' => $lastId, 'message' => 'Parámetro creado exitosamente']);
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $sentence = $db->prepare("UPDATE parametros_calificaciones SET nombre = ? WHERE id = ? AND id_tenant = ?");
        $sentence->execute([
            $data->nombre,
            $data->id,
            TenantContext::id()
        ]);
        
        Flight::json(['message' => 'Parámetro actualizado exitosamente']);
    }

    public static function delete()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        // Primero eliminar los valores asociados
        $sentence = $db->prepare("DELETE FROM valores_parametros_calificaciones WHERE id_parametros_calificaciones = ? AND id_tenant = ?");
        $sentence->execute([$data->id, TenantContext::id()]);
        
        // Luego eliminar el parámetro
        $sentence = $db->prepare("DELETE FROM parametros_calificaciones WHERE id = ? AND id_tenant = ?");
        $sentence->execute([$data->id, TenantContext::id()]);
        
        Flight::json(['message' => 'Parámetro eliminado exitosamente']);
    }
    
}