<?php
/**
 * Servicio para gestión de Tipos de Plantillas
 * Maneja las operaciones CRUD para tipos_plantillas
 */

class TiposPlantillas
{
    /**
     * Obtener todos los tipos de plantillas
     * GET /tipos-plantillas
     */
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT id, codigo, nombre, descripcion,
                       fecha_creacion, fecha_actualizacion
                FROM tipos_plantillas
                ORDER BY nombre
            ");
            
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
            
        } catch (Exception $e) {
            error_log("Error en TiposPlantillas::getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
    
    /**
     * Obtener tipo de plantilla por ID
     * GET /tipos-plantillas/:id
     */
    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT id, codigo, nombre, descripcion,
                       fecha_creacion, fecha_actualizacion
                FROM tipos_plantillas
                WHERE id = :id
            ");
            
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
            
        } catch (Exception $e) {
            error_log("Error en TiposPlantillas::getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
    
    /**
     * Obtener tipo de plantilla por código
     * GET /tipos-plantillas/codigo/:codigo
     */
    public static function getByCodigo($codigo)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT id, codigo, nombre, descripcion,
                       fecha_creacion, fecha_actualizacion
                FROM tipos_plantillas
                WHERE codigo = :codigo
            ");
            
            $sentence->bindParam(':codigo', $codigo);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
            
        } catch (Exception $e) {
            error_log("Error en TiposPlantillas::getByCodigo: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
    
    /**
     * Crear nuevo tipo de plantilla
     * POST /tipos-plantillas
     */
    public static function new()
    {
        try {
            $db = Flight::db();
            $codigo = Flight::request()->data['codigo'];
            $nombre = Flight::request()->data['nombre'];
            $descripcion = isset(Flight::request()->data['descripcion']) ? Flight::request()->data['descripcion'] : null;
            
            $sentence = $db->prepare("
                INSERT INTO tipos_plantillas (codigo, nombre, descripcion) 
                VALUES (:codigo, :nombre, :descripcion)
            ");
            
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            
            $sentence->execute();
            $id = $db->lastInsertId();
            
            Flight::json(array('id' => $id));
            
        } catch (Exception $e) {
            error_log("Error en TiposPlantillas::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
    
    /**
     * Actualizar tipo de plantilla
     * PUT /tipos-plantillas
     */
    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $codigo = Flight::request()->data['codigo'];
            $nombre = Flight::request()->data['nombre'];
            $descripcion = isset(Flight::request()->data['descripcion']) ? Flight::request()->data['descripcion'] : null;
            
            $sentence = $db->prepare("
                UPDATE tipos_plantillas 
                SET codigo = :codigo,
                    nombre = :nombre,
                    descripcion = :descripcion
                WHERE id = :id
            ");
            
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            
            $sentence->execute();
            
            Flight::json(array('id' => $id));
            
        } catch (Exception $e) {
            error_log("Error en TiposPlantillas::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
    
    /**
     * Eliminar tipo de plantilla
     * DELETE /tipos-plantillas
     */
    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            
            // Verificar que no tenga plantillas asociadas
            $sentenceCheck = $db->prepare("
                SELECT COUNT(*) as total 
                FROM plantillas 
                WHERE id_tipo_plantilla = :id
            ");
            $sentenceCheck->bindParam(':id', $id);
            $sentenceCheck->execute();
            $check = $sentenceCheck->fetch();
            
            if ($check['total'] > 0) {
                Flight::json(array('error' => 'No se puede eliminar el tipo porque tiene plantillas asociadas'), 400);
                return;
            }
            
            // Eliminar tipo
            $sentence = $db->prepare("DELETE FROM tipos_plantillas WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            
            Flight::json(array('id' => $id));
            
        } catch (Exception $e) {
            error_log("Error en TiposPlantillas::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}