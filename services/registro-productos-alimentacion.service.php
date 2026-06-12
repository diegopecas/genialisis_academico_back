<?php
class RegistroProductosAlimentacion
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    rpa.id, 
                    rpa.id_producto_alimentacion, 
                    rpa.fecha, 
                    rpa.cantidad, 
                    rpa.valor, 
                    rpa.valor_total, 
                    rpa.observaciones, 
                    rpa.id_tipo_movimiento_productos_alimentacion,
                    rpa.id_usuario_registro,
                    rpa.fecha_registro,
                    rpa.id_usuario_contable,
                    rpa.fecha_contabilizacion,
                    pa.nombre AS nombre_producto,
                    tmpa.nombre AS nombre_tipo_movimiento,
                    tmpa.entrada AS es_entrada,
                    tmpa.salida AS es_salida,
                    CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) AS nombre_usuario
                FROM 
                    registro_productos_alimentacion rpa
                INNER JOIN 
                    productos_alimentacion pa ON pa.id = rpa.id_producto_alimentacion
                INNER JOIN 
                    tipos_movimientos_productos_alimentacion tmpa ON tmpa.id = rpa.id_tipo_movimiento_productos_alimentacion
                INNER JOIN 
                    usuarios u ON u.id = rpa.id_usuario_registro
                INNER JOIN 
                    personas p ON p.id = u.id_persona
                ORDER BY 
                    rpa.fecha DESC, rpa.id DESC
            ");
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            // Log del error en el servidor
            error_log('Error en getAll registro_productos_alimentacion: ' . $e->getMessage());

            // Respuesta con error para el cliente
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener los registros de productos de alimentación',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getById($id)
    {
        error_log('getById: ' . $id);
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    rpa.id, 
                    rpa.id_producto_alimentacion, 
                    rpa.fecha, 
                    rpa.cantidad, 
                    rpa.valor, 
                    rpa.valor_total, 
                    rpa.observaciones, 
                    rpa.id_tipo_movimiento_productos_alimentacion,
                    rpa.id_usuario_registro,
                    rpa.fecha_registro,
                    rpa.id_usuario_contable,
                    rpa.fecha_contabilizacion,
                    pa.nombre AS nombre_producto,
                    tmpa.nombre AS nombre_tipo_movimiento,
                    tmpa.entrada AS es_entrada,
                    tmpa.salida AS es_salida,
                    CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) AS nombre_usuario
                FROM 
                    registro_productos_alimentacion rpa
                INNER JOIN 
                    productos_alimentacion pa ON pa.id = rpa.id_producto_alimentacion
                INNER JOIN 
                    tipos_movimientos_productos_alimentacion tmpa ON tmpa.id = rpa.id_tipo_movimiento_productos_alimentacion
                INNER JOIN 
                    usuarios u ON u.id = rpa.id_usuario_registro
                INNER JOIN 
                    personas p ON p.id = u.id_persona
                WHERE 
                    rpa.id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getById registro_productos_alimentacion: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener el registro de producto de alimentación',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getByProducto($id_producto)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    rpa.id, 
                    rpa.id_producto_alimentacion, 
                    rpa.fecha, 
                    rpa.cantidad, 
                    rpa.valor, 
                    rpa.valor_total, 
                    rpa.observaciones, 
                    rpa.id_tipo_movimiento_productos_alimentacion,
                    rpa.id_usuario_registro,
                    rpa.fecha_registro,
                    rpa.id_usuario_contable,
                    rpa.fecha_contabilizacion,
                    pa.nombre AS nombre_producto,
                    tmpa.nombre AS nombre_tipo_movimiento,
                    tmpa.entrada AS es_entrada,
                    tmpa.salida AS es_salida,
                    CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) AS nombre_usuario
                FROM 
                    registro_productos_alimentacion rpa
                INNER JOIN 
                    productos_alimentacion pa ON pa.id = rpa.id_producto_alimentacion
                INNER JOIN 
                    tipos_movimientos_productos_alimentacion tmpa ON tmpa.id = rpa.id_tipo_movimiento_productos_alimentacion
                INNER JOIN 
                    usuarios u ON u.id = rpa.id_usuario_registro
                INNER JOIN 
                    personas p ON p.id = u.id_persona
                WHERE 
                    rpa.id_producto_alimentacion = :id_producto
                ORDER BY 
                    rpa.fecha DESC, rpa.id DESC
            ");
            $sentence->bindParam(':id_producto', $id_producto);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getByProducto registro_productos_alimentacion: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener registros de productos de alimentación por producto',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getByPeriodo($fecha_inicio, $fecha_fin)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    rpa.id, 
                    rpa.id_producto_alimentacion, 
                    rpa.fecha, 
                    rpa.cantidad, 
                    rpa.valor, 
                    rpa.valor_total, 
                    rpa.observaciones, 
                    rpa.id_tipo_movimiento_productos_alimentacion,
                    rpa.id_usuario_registro,
                    rpa.fecha_registro,
                    rpa.id_usuario_contable,
                    rpa.fecha_contabilizacion,
                    pa.nombre AS nombre_producto,
                    tmpa.nombre AS nombre_tipo_movimiento,
                    tmpa.entrada AS es_entrada,
                    tmpa.salida AS es_salida,
                    CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) AS nombre_usuario
                FROM 
                    registro_productos_alimentacion rpa
                INNER JOIN 
                    productos_alimentacion pa ON pa.id = rpa.id_producto_alimentacion
                INNER JOIN 
                    tipos_movimientos_productos_alimentacion tmpa ON tmpa.id = rpa.id_tipo_movimiento_productos_alimentacion
                INNER JOIN 
                    usuarios u ON u.id = rpa.id_usuario_registro
                INNER JOIN 
                    personas p ON p.id = u.id_persona
                WHERE 
                    rpa.fecha BETWEEN :fecha_inicio AND :fecha_fin
                ORDER BY 
                    rpa.fecha DESC, rpa.id DESC
            ");
            $sentence->bindParam(':fecha_inicio', $fecha_inicio);
            $sentence->bindParam(':fecha_fin', $fecha_fin);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getByPeriodo registro_productos_alimentacion: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener registros de productos de alimentación por periodo',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getResumen()
    {
        try {
            $db = Flight::db();
            // Obtener resumen del inventario actual (saldo total) por producto
            $sentence = $db->prepare("
                SELECT 
                    pa.id,
                    pa.nombre,
                    COALESCE(SUM(
                        CASE 
                            WHEN tmpa.entrada = 1 THEN rpa.cantidad 
                            ELSE -rpa.cantidad 
                        END
                    ), 0) AS cantidad_actual,
                    COALESCE(SUM(
                        CASE 
                            WHEN tmpa.entrada = 1 THEN rpa.valor_total 
                            ELSE -rpa.valor_total 
                        END
                    ), 0) AS valor_total_actual
                FROM 
                    productos_alimentacion pa
                LEFT JOIN 
                    registro_productos_alimentacion rpa ON pa.id = rpa.id_producto_alimentacion
                LEFT JOIN 
                    tipos_movimientos_productos_alimentacion tmpa ON tmpa.id = rpa.id_tipo_movimiento_productos_alimentacion
                GROUP BY 
                    pa.id, pa.nombre
                ORDER BY 
                    pa.nombre
            ");
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getResumen registro_productos_alimentacion: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener resumen de inventario',
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

            // Calcular el valor_total
            $valor_total = $data['cantidad'] * $data['valor'];

            // Fecha actual para el registro
            $fecha_registro = date('Y-m-d H:i:s');

            $sql = "INSERT INTO registro_productos_alimentacion (
                id_producto_alimentacion, fecha, cantidad, valor, valor_total, 
                observaciones, id_tipo_movimiento_productos_alimentacion, 
                id_usuario_registro, fecha_registro, id_usuario_contable, fecha_contabilizacion
            ) VALUES (
                :id_producto_alimentacion, :fecha, :cantidad, :valor, :valor_total, 
                :observaciones, :id_tipo_movimiento_productos_alimentacion, 
                :id_usuario_registro, :fecha_registro, NULL, NULL
            )";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id_producto_alimentacion', $data['id_producto_alimentacion']);
            $stmt->bindParam(':fecha', $data['fecha']);
            $stmt->bindParam(':cantidad', $data['cantidad']);
            $stmt->bindParam(':valor', $data['valor']);
            $stmt->bindParam(':valor_total', $valor_total);
            $stmt->bindParam(':observaciones', $data['observaciones']);
            $stmt->bindParam(':id_tipo_movimiento_productos_alimentacion', $data['id_tipo_movimiento_productos_alimentacion']);
            $stmt->bindParam(':id_usuario_registro', $data['id_usuario_registro']);
            $stmt->bindParam(':fecha_registro', $fecha_registro);
            $stmt->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en registro_productos_alimentacion->new(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al crear registro de producto de alimentación'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData(); // Asegura que sea un array asociativo

            // Calcular el valor_total
            $valor_total = $data['cantidad'] * $data['valor'];

            $sql = "UPDATE registro_productos_alimentacion SET
                id_producto_alimentacion = :id_producto_alimentacion,
                fecha = :fecha,
                cantidad = :cantidad,
                valor = :valor,
                valor_total = :valor_total,
                observaciones = :observaciones,
                id_tipo_movimiento_productos_alimentacion = :id_tipo_movimiento_productos_alimentacion
                WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindParam(':id_producto_alimentacion', $data['id_producto_alimentacion']);
            $stmt->bindParam(':fecha', $data['fecha']);
            $stmt->bindParam(':cantidad', $data['cantidad']);
            $stmt->bindParam(':valor', $data['valor']);
            $stmt->bindParam(':valor_total', $valor_total);
            $stmt->bindParam(':observaciones', $data['observaciones']);
            $stmt->bindParam(':id_tipo_movimiento_productos_alimentacion', $data['id_tipo_movimiento_productos_alimentacion']);
            $stmt->execute();

            // Verificar si se actualizó algún registro
            if ($stmt->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el registro con el ID especificado para actualizar'), 404);
                return;
            }

            // Escribir el ID actualizado en el log
            error_log("ID actualizado: " . $data['id']);

            // Devolver el ID como respuesta
            Flight::json(array('id' => $data['id']));
        } catch (Exception $e) {
            error_log('Error en registro_productos_alimentacion->replace(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar registro de producto de alimentación'), 500);
        }
    }

    public static function contabilizar()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            // Verificar que existan todos los datos necesarios
            if (!isset($data['id']) || !isset($data['id_usuario_contable'])) {
                Flight::json(array('error' => 'Faltan datos requeridos (id, id_usuario_contable)'), 400);
                return;
            }

            // Obtener la fecha actual para el registro de contabilización
            $fechaActual = date('Y-m-d H:i:s');

            $sql = "UPDATE registro_productos_alimentacion SET
                id_usuario_contable = :id_usuario_contable,
                fecha_contabilizacion = :fecha_contabilizacion
                WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindParam(':fecha_contabilizacion', $fechaActual);
            $stmt->bindParam(':id_usuario_contable', $data['id_usuario_contable']);
            $stmt->execute();

            // Verificar si se actualizó algún registro
            if ($stmt->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el registro con el ID especificado para contabilizar'), 404);
                return;
            }

            // Devolver el ID como respuesta
            Flight::json(array(
                'id' => $data['id'],
                'fecha_contabilizacion' => $fechaActual,
                'id_usuario_contable' => $data['id_usuario_contable']
            ));
        } catch (Exception $e) {
            error_log('Error en registro_productos_alimentacion->contabilizar(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al contabilizar registro de producto de alimentación'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            
            // Primero verificamos si el registro ya está contabilizado
            $checkStmt = $db->prepare("SELECT fecha_contabilizacion FROM registro_productos_alimentacion WHERE id = :id");
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Si el registro está contabilizado, no permitimos la eliminación
            if ($result && $result['fecha_contabilizacion'] !== null) {
                Flight::json(array(
                    'error' => 'No se puede eliminar un registro contabilizado',
                    'mensaje' => 'Este registro ya ha sido contabilizado y no puede ser eliminado'
                ), 400);
                return;
            }
            
            // Si no está contabilizado, procedemos con la eliminación
            $stmt = $db->prepare("DELETE FROM registro_productos_alimentacion WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en registro_productos_alimentacion->delete(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al borrar registro_productos_alimentacion'), 500);
        }
    }
}