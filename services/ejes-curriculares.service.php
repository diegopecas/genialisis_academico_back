<?php
class EjesCurriculares
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM ejes_curriculares WHERE id_tenant = :id_tenant ORDER BY nombre ASC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM ejes_curriculares WHERE id = :id AND id_tenant = :id_tenant");
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
            
            error_log("Creando eje curricular: nombre=$nombre");
            
            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO ejes_curriculares (id, id_tenant, nombre, descripcion) VALUES (:id, :id_tenant, :nombre, :descripcion)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->execute();
            
            $id = $idNew;
            error_log("Eje curricular creado con ID: $id");
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al crear eje curricular: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear el eje curricular'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $nombre = Flight::request()->data['nombre'];
            $descripcion = Flight::request()->data['descripcion'];
            
            error_log("Actualizando eje curricular ID: $id");
            
            if (!$id || !$nombre) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }
            
            $sentence = $db->prepare("UPDATE ejes_curriculares SET nombre = :nombre, descripcion = :descripcion WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            
            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el eje curricular con el ID especificado'), 404);
                return;
            }
            
            error_log("Eje curricular actualizado: $id");
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al actualizar eje curricular: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar el eje curricular'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            
            error_log("Eliminando eje curricular ID: $id");
            
            $sentence = $db->prepare("DELETE FROM ejes_curriculares WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al eliminar eje curricular: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar el eje curricular'), 500);
        }
    }
}