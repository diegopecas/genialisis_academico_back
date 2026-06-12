<?php
class ClasificacionProductosAlimentacion
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    id, 
                    nombre
                FROM 
                    clasificacion_productos_alimentacion
                ORDER BY 
                    nombre
            ");
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAll clasificacion_productos_alimentacion: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener las clasificaciones de productos de alimentación',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    id, 
                    nombre
                FROM 
                    clasificacion_productos_alimentacion
                WHERE 
                    id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getById clasificacion_productos_alimentacion: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener la clasificación de producto de alimentación',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $sql = "INSERT INTO clasificacion_productos_alimentacion (
                nombre
            ) VALUES (
                :nombre
            )";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en clasificacion_productos_alimentacion->new(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al crear clasificación de producto de alimentación'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $sql = "UPDATE clasificacion_productos_alimentacion SET
                nombre = :nombre
                WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el registro con el ID especificado para actualizar'), 404);
                return;
            }

            Flight::json(array('id' => $data['id']));
        } catch (Exception $e) {
            error_log('Error en clasificacion_productos_alimentacion->replace(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar clasificación de producto de alimentación'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            
            // Primero verificamos si la clasificación tiene productos asociados
            $checkStmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM productos_alimentacion 
                WHERE id_clasificacion_productos_alimentacion = :id
            ");
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Si la clasificación tiene productos asociados, no permitimos la eliminación
            if ($result && $result['count'] > 0) {
                Flight::json(array(
                    'error' => 'No se puede eliminar una clasificación con productos asociados',
                    'mensaje' => 'Esta clasificación tiene productos asociados y no puede ser eliminada'
                ), 400);
                return;
            }
            
            // Si no tiene productos asociados, procedemos con la eliminación
            $stmt = $db->prepare("DELETE FROM clasificacion_productos_alimentacion WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en clasificacion_productos_alimentacion->delete(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al borrar clasificación de producto de alimentación'), 500);
        }
    }
}