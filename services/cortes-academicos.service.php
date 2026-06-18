<?php
class CortesAcademicos
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, orden, fecha_inicio, fecha_fin FROM cortes_academicos WHERE id_tenant = :id_tenant ORDER BY orden ASC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, orden, fecha_inicio, fecha_fin FROM cortes_academicos WHERE id = :id AND id_tenant = :id_tenant");
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
            $orden = Flight::request()->data['orden'];
            $fecha_inicio = Flight::request()->data['fecha_inicio'] ?? null;
            $fecha_fin = Flight::request()->data['fecha_fin'] ?? null;
            
            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO cortes_academicos (id, id_tenant, nombre, orden, fecha_inicio, fecha_fin) VALUES (:id, :id_tenant, :nombre, :orden, :fecha_inicio, :fecha_fin)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':orden', $orden, PDO::PARAM_INT);
            $sentence->bindParam(':fecha_inicio', $fecha_inicio);
            $sentence->bindParam(':fecha_fin', $fecha_fin);
            $sentence->execute();
            
            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al crear corte académico: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear el corte académico'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $nombre = Flight::request()->data['nombre'];
            $orden = Flight::request()->data['orden'];
            $fecha_inicio = Flight::request()->data['fecha_inicio'] ?? null;
            $fecha_fin = Flight::request()->data['fecha_fin'] ?? null;
            
            if (!$id || !$nombre) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            // Verificar que el registro exista antes de actualizar
            $check = $db->prepare("SELECT id FROM cortes_academicos WHERE id = :id AND id_tenant = :id_tenant");
            $check->bindParam(':id', $id);
            $check->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $check->execute();
            if ($check->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el corte académico con el ID especificado'), 404);
                return;
            }
            
            $sentence = $db->prepare("UPDATE cortes_academicos SET nombre = :nombre, orden = :orden, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':orden', $orden, PDO::PARAM_INT);
            $sentence->bindParam(':fecha_inicio', $fecha_inicio);
            $sentence->bindParam(':fecha_fin', $fecha_fin);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al actualizar corte académico: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar el corte académico'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            
            $sentence = $db->prepare("DELETE FROM cortes_academicos WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al eliminar corte académico: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar el corte académico'), 500);
        }
    }
}