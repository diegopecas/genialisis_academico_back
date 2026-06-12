<?php
class TiposMovimientosProductosAlimentacion
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    id, 
                    nombre, 
                    entrada, 
                    salida
                FROM 
                    tipos_movimientos_productos_alimentacion
                ORDER BY 
                    nombre
            ");
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAll tipos_movimientos_productos_alimentacion: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener los tipos de movimientos de productos de alimentación',
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
                    nombre, 
                    entrada, 
                    salida
                FROM 
                    tipos_movimientos_productos_alimentacion
                WHERE 
                    id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getById tipos_movimientos_productos_alimentacion: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener el tipo de movimiento de producto de alimentación',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getTipoEntrada()
    {
        try {
            error_log('INICIANDO getTipoEntrada');
            $db = Flight::db();
            $sentence = $db->prepare("
            SELECT 
                id, 
                nombre, 
                entrada, 
                salida
            FROM 
                tipos_movimientos_productos_alimentacion
            WHERE 
                entrada = 1
            ORDER BY 
                nombre
        ");
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getTipoEntrada tipos_movimientos_productos_alimentacion: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener los tipos de movimientos de entrada',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
    public static function getTipoSalida()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    id, 
                    nombre, 
                    entrada, 
                    salida
                FROM 
                    tipos_movimientos_productos_alimentacion
                WHERE 
                    salida = 1
                ORDER BY 
                    nombre
            ");
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getTipoSalida tipos_movimientos_productos_alimentacion: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener los tipos de movimientos de salida',
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

            // Validar que entrada y salida sean mutuamente excluyentes
            if (isset($data['entrada']) && isset($data['salida']) && $data['entrada'] == 1 && $data['salida'] == 1) {
                Flight::json(array(
                    'error' => 'Un tipo de movimiento no puede ser entrada y salida a la vez',
                    'mensaje' => 'Debe elegir si el movimiento es de entrada o de salida'
                ), 400);
                return;
            }

            $sql = "INSERT INTO tipos_movimientos_productos_alimentacion (
                nombre, entrada, salida
            ) VALUES (
                :nombre, :entrada, :salida
            )";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindParam(':entrada', $data['entrada']);
            $stmt->bindParam(':salida', $data['salida']);
            $stmt->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en tipos_movimientos_productos_alimentacion->new(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al crear tipo de movimiento de producto de alimentación'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            // Validar que entrada y salida sean mutuamente excluyentes
            if (isset($data['entrada']) && isset($data['salida']) && $data['entrada'] == 1 && $data['salida'] == 1) {
                Flight::json(array(
                    'error' => 'Un tipo de movimiento no puede ser entrada y salida a la vez',
                    'mensaje' => 'Debe elegir si el movimiento es de entrada o de salida'
                ), 400);
                return;
            }

            $sql = "UPDATE tipos_movimientos_productos_alimentacion SET
                nombre = :nombre,
                entrada = :entrada,
                salida = :salida
                WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindParam(':entrada', $data['entrada']);
            $stmt->bindParam(':salida', $data['salida']);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el registro con el ID especificado para actualizar'), 404);
                return;
            }

            Flight::json(array('id' => $data['id']));
        } catch (Exception $e) {
            error_log('Error en tipos_movimientos_productos_alimentacion->replace(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar tipo de movimiento de producto de alimentación'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            // Primero verificamos si el tipo de movimiento tiene registros asociados
            $checkStmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM registro_productos_alimentacion 
                WHERE id_tipo_movimiento_productos_alimentacion = :id
            ");
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();

            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

            // Si el tipo de movimiento tiene registros asociados, no permitimos la eliminación
            if ($result && $result['count'] > 0) {
                Flight::json(array(
                    'error' => 'No se puede eliminar un tipo de movimiento con registros asociados',
                    'mensaje' => 'Este tipo de movimiento tiene registros en el inventario y no puede ser eliminado'
                ), 400);
                return;
            }

            // Si no tiene registros asociados, procedemos con la eliminación
            $stmt = $db->prepare("DELETE FROM tipos_movimientos_productos_alimentacion WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en tipos_movimientos_productos_alimentacion->delete(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al borrar tipo de movimiento de producto de alimentación'), 500);
        }
    }
}
