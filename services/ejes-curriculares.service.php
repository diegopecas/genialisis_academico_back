<?php
class EjesCurriculares
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM ejes_curriculares ORDER BY nombre ASC");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM ejes_curriculares WHERE id = :id");
        $sentence->bindParam(':id', $id);
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
            
            $sentence = $db->prepare("INSERT INTO ejes_curriculares (nombre, descripcion) VALUES (:nombre, :descripcion)");
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->execute();
            
            $id = $db->lastInsertId();
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
            
            $sentence = $db->prepare("UPDATE ejes_curriculares SET nombre = :nombre, descripcion = :descripcion WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
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
            
            $sentence = $db->prepare("DELETE FROM ejes_curriculares WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al eliminar eje curricular: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar el eje curricular'), 500);
        }
    }
}