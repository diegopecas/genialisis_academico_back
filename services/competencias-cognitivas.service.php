<?php
class CompetenciasCognitivas
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM competencias_cognitivas WHERE id_tenant = :id_tenant ORDER BY nombre ASC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM competencias_cognitivas WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $nombre = Flight::request()->data['nombre'];
            $descripcion = Flight::request()->data['descripcion'];
            
            error_log("Creando competencia cognitiva: nombre=$nombre");
            
            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO competencias_cognitivas (id, id_tenant, nombre, descripcion) VALUES (:id, :id_tenant, :nombre, :descripcion)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->execute();
            
            $id = $idNew;
            error_log("Competencia cognitiva creada con ID: $id");
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al crear competencia cognitiva: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear la competencia cognitiva'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $nombre = Flight::request()->data['nombre'];
            $descripcion = Flight::request()->data['descripcion'];
            
            error_log("Actualizando competencia cognitiva ID: $id");
            
            if (!$id || !$nombre) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }
            
            $sentence = $db->prepare("UPDATE competencias_cognitivas SET nombre = :nombre, descripcion = :descripcion WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            
            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró la competencia cognitiva con el ID especificado'), 404);
                return;
            }
            
            error_log("Competencia cognitiva actualizada: $id");
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al actualizar competencia cognitiva: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar la competencia cognitiva'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            
            error_log("Eliminando competencia cognitiva ID: $id");
            
            $sentence = $db->prepare("DELETE FROM competencias_cognitivas WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al eliminar competencia cognitiva: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar la competencia cognitiva'), 500);
        }
    }
}