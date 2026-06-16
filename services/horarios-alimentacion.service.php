<?php 
class HorariosAlimentacion
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, nombre, orden FROM horarios_alimentacion ORDER BY orden");
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en HorariosAlimentacion->getAll(): ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener horarios de alimentación',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
    
    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, nombre, orden FROM horarios_alimentacion WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en HorariosAlimentacion->getById(): ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener el horario de alimentación',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            
            // Obtener los datos como un array asociativo
            $data = $request->data->getData();
            
            $sentence = $db->prepare("INSERT INTO horarios_alimentacion(nombre, orden) VALUES (:nombre, :orden)");
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':orden', $data['orden']);
            $sentence->execute();
            
            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en HorariosAlimentacion->new(): ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al crear horario de alimentación',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            
            // Obtener los datos como un array asociativo
            $data = $request->data->getData();
            
            $sentence = $db->prepare("UPDATE horarios_alimentacion SET nombre = :nombre, orden = :orden WHERE id = :id");
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':orden', $data['orden']);
            $sentence->bindParam(':id', $data['id']);
            $sentence->execute();
            
            // Verificar si se actualizó algún registro
            if ($sentence->rowCount() == 0) {
                Flight::json([
                    'error' => true,
                    'message' => 'No se encontró el registro con el ID especificado para actualizar'
                ], 404);
                return;
            }
            
            // Devolver el objeto actualizado
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log('Error en HorariosAlimentacion->replace(): ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al actualizar horario de alimentación',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            
            // Primero verificamos si el horario está siendo utilizado en cuentas por cobrar
            $checkUsage = $db->prepare("SELECT COUNT(*) as total FROM cuentas_por_cobrar WHERE id_horario_alimentacion = :id");
            $checkUsage->bindParam(':id', $id);
            $checkUsage->execute();
            $result = $checkUsage->fetch(PDO::FETCH_ASSOC);
            
            if ($result['total'] > 0) {
                Flight::json([
                    'error' => true,
                    'message' => 'No se puede eliminar el horario porque está siendo utilizado en cuentas por cobrar',
                    'registros_asociados' => $result['total']
                ], 400);
                return;
            }
            
            // Si no está siendo utilizado, procedemos a eliminarlo
            $sentence = $db->prepare("DELETE FROM horarios_alimentacion WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            
            // Verificar si se eliminó algún registro
            if ($sentence->rowCount() == 0) {
                Flight::json([
                    'error' => true,
                    'message' => 'No se encontró el registro con el ID especificado para eliminar'
                ], 404);
                return;
            }
            
            Flight::json(['id' => $id, 'deleted' => true]);
        } catch (Exception $e) {
            error_log('Error en HorariosAlimentacion->delete(): ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al eliminar horario de alimentación',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
    
    // Método adicional para obtener el orden máximo actual
    public static function getMaxOrden()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT COALESCE(MAX(orden), 0) as max_orden FROM horarios_alimentacion");
            $sentence->execute();
            $result = $sentence->fetch(PDO::FETCH_ASSOC);
            Flight::json(['max_orden' => $result['max_orden']]);
        } catch (Exception $e) {
            error_log('Error en HorariosAlimentacion->getMaxOrden(): ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener el orden máximo',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
}