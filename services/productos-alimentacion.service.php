<?php
class ProductosAlimentacion
{
    public static function getAll()
    {
        $db = Flight::db();

        // Primero obtener todos los productos de alimentación con unidad de medida
        $sentence = $db->prepare("
            SELECT pa.*, 
                p.nombre AS nombre_producto,
                p.descripcion,
                tpa.nombre AS tipo_alimentacion,
                um.nombre AS nombre_unidad,
                um.abreviatura AS abreviatura_unidad,
                p.stock_actual
            FROM productos_alimentacion pa
            INNER JOIN productos p ON pa.id_producto = p.id
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            LEFT JOIN tipos_producto_alimentacion tpa ON pa.id_tipo_producto_alimentacion = tpa.id
            WHERE pa.id_tenant = :id_tenant
            ORDER BY pa.id DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $productos = $sentence->fetchAll();

        // Luego obtener las clasificaciones para cada producto
        $stmtClasif = $db->prepare("
            SELECT cpa.nombre 
            FROM productos_alimentacion_clasificaciones pac
            INNER JOIN clasificacion_productos_alimentacion cpa ON cpa.id = pac.id_clasificacion_productos_alimentacion
            WHERE pac.id_producto_alimentacion = :id_producto
            AND pac.id_tenant = :id_tenant
            ORDER BY cpa.nombre
        ");
        $stmtClasif->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

        // Agregar las clasificaciones a cada producto
        foreach ($productos as &$producto) {
            $stmtClasif->bindParam(':id_producto', $producto['id']);
            $stmtClasif->execute();
            $clasificaciones = $stmtClasif->fetchAll(PDO::FETCH_COLUMN);

            // Unir las clasificaciones con |
            $producto['clasificaciones'] = !empty($clasificaciones) ? implode('|', $clasificaciones) : '';

            // Log para debugging
            if (!empty($producto['clasificaciones'])) {
                error_log("Producto ID " . $producto['id'] . " (" . $producto['nombre_producto'] . ") - Clasificaciones: " . $producto['clasificaciones']);
            }
        }

        Flight::json($productos);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT pa.*, 
                p.nombre nombre_producto,
                p.descripcion,
                tpa.nombre as tipo_alimentacion,
                um.nombre AS nombre_unidad,
                um.abreviatura AS abreviatura_unidad
            FROM productos_alimentacion pa
            INNER JOIN productos p ON pa.id_producto = p.id
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            LEFT JOIN tipos_producto_alimentacion tpa ON pa.id_tipo_producto_alimentacion = tpa.id
            WHERE pa.id = :id
            AND pa.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();

        $id_producto = Flight::request()->data['id_producto'];
        $id_tipo_producto_alimentacion = Flight::request()->data['id_tipo_producto_alimentacion'];
        $dias_vida_util = Flight::request()->data['dias_vida_util'] ?? 0;

        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO productos_alimentacion(
            id,
            id_tenant,
            id_producto,
            id_tipo_producto_alimentacion,
            dias_vida_util
        ) VALUES (
            :id,
            :id_tenant,
            :id_producto,
            :id_tipo_producto_alimentacion,
            :dias_vida_util
        )");

        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_producto', $id_producto);
        $sentence->bindParam(':id_tipo_producto_alimentacion', $id_tipo_producto_alimentacion);
        $sentence->bindParam(':dias_vida_util', $dias_vida_util);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $id_producto = Flight::request()->data['id_producto'];
        $id_tipo_producto_alimentacion = Flight::request()->data['id_tipo_producto_alimentacion'];
        $dias_vida_util = Flight::request()->data['dias_vida_util'] ?? 0;

        $sentence = $db->prepare("UPDATE productos_alimentacion SET 
            id_producto = :id_producto,
            id_tipo_producto_alimentacion = :id_tipo_producto_alimentacion,
            dias_vida_util = :dias_vida_util
            WHERE id = :id
            AND id_tenant = :id_tenant");

        $sentence->bindParam(':id_producto', $id_producto);
        $sentence->bindParam(':id_tipo_producto_alimentacion', $id_tipo_producto_alimentacion);
        $sentence->bindParam(':dias_vida_util', $dias_vida_util);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM productos_alimentacion WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function getProductosDisponiblesParaAlimentacion()
    {
        error_log("getProductosDisponiblesParaAlimentacion");
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT p.*, u.nombre AS nombre_unidad, u.abreviatura abreviatura_unidad, tp.nombre tipo_producto_nombre
            FROM productos p
            LEFT JOIN unidades_medida u ON p.id_unidad_medida = u.id
            LEFT JOIN tipos_producto tp ON p.id_tipo_producto = tp.id AND tp.id_tenant = p.id_tenant
            WHERE tp.codigo = 'ALIMENTACION' 
            AND p.activo = 1
            AND p.id_tenant = :id_tenant
            AND p.id NOT IN (
                SELECT id_producto FROM productos_alimentacion WHERE id_tenant = :id_tenant_sub
            )
            ORDER BY p.nombre ASC
        ");
        $sentence->bindValue(':id_tenant_sub', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getClasificacionesByProducto($id_producto_alimentacion)
    {
        try {
            $db = Flight::db();

            // Obtener todas las clasificaciones y marcar las que están asociadas al producto
            $sentence = $db->prepare("
                SELECT 
                    cpa.id,
                    cpa.nombre,
                    CASE 
                        WHEN pac.id IS NOT NULL THEN 1 
                        ELSE 0 
                    END as asignada
                FROM clasificacion_productos_alimentacion cpa
                LEFT JOIN productos_alimentacion_clasificaciones pac 
                    ON pac.id_clasificacion_productos_alimentacion = cpa.id 
                    AND pac.id_producto_alimentacion = :id_producto
                WHERE cpa.id_tenant = :id_tenant
                ORDER BY cpa.nombre ASC
            ");

            $sentence->bindParam(':id_producto', $id_producto_alimentacion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getClasificacionesByProducto: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener clasificaciones'], 500);
        }
    }

    public static function asignarClasificaciones($id_producto_alimentacion)
    {
        try {
            $db = Flight::db();
            $clasificaciones = Flight::request()->data['clasificaciones'] ?? [];

            // Iniciar transacción
            $db->beginTransaction();

            // Primero eliminar todas las clasificaciones actuales
            $deleteStmt = $db->prepare("
                DELETE FROM productos_alimentacion_clasificaciones 
                WHERE id_producto_alimentacion = :id_producto
                AND id_tenant = :id_tenant
            ");
            $deleteStmt->bindParam(':id_producto', $id_producto_alimentacion);
            $deleteStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $deleteStmt->execute();

            // Insertar las nuevas clasificaciones
            if (!empty($clasificaciones)) {
                $insertStmt = $db->prepare("
                    INSERT INTO productos_alimentacion_clasificaciones 
                    (id, id_tenant, id_producto_alimentacion, id_clasificacion_productos_alimentacion) 
                    VALUES (:id, :id_tenant, :id_producto, :id_clasificacion)
                ");

                foreach ($clasificaciones as $id_clasificacion) {
                    $idPac = Uuid::generar();
                    $insertStmt->bindValue(':id', $idPac);
                    $insertStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $insertStmt->bindParam(':id_producto', $id_producto_alimentacion);
                    $insertStmt->bindParam(':id_clasificacion', $id_clasificacion);
                    $insertStmt->execute();
                }
            }

            // Confirmar transacción
            $db->commit();

            // Retornar las clasificaciones actualizadas
            self::getClasificacionesByProducto($id_producto_alimentacion);
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Error en asignarClasificaciones: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al asignar clasificaciones'], 500);
        }
    }

    public static function eliminarClasificacion($id_producto_alimentacion, $id_clasificacion)
    {
        try {
            $db = Flight::db();

            $sentence = $db->prepare("
                DELETE FROM productos_alimentacion_clasificaciones 
                WHERE id_producto_alimentacion = :id_producto 
                AND id_clasificacion_productos_alimentacion = :id_clasificacion
                AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id_producto', $id_producto_alimentacion);
            $sentence->bindParam(':id_clasificacion', $id_clasificacion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(['success' => true, 'message' => 'Clasificación eliminada']);
        } catch (Exception $e) {
            error_log('Error en eliminarClasificacion: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al eliminar clasificación'], 500);
        }
    }

    // Modificar el método getAll existente para incluir las clasificaciones
    public static function getAllConClasificaciones()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT pa.*, 
                p.nombre nombre_producto,
                p.descripcion,
                tpa.nombre as tipo_alimentacion,
                um.nombre AS nombre_unidad,
                um.abreviatura AS abreviatura_unidad,
                GROUP_CONCAT(cpa.nombre SEPARATOR ', ') as clasificaciones
            FROM productos_alimentacion pa
            INNER JOIN productos p ON pa.id_producto = p.id
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            LEFT JOIN tipos_producto_alimentacion tpa ON pa.id_tipo_producto_alimentacion = tpa.id
            LEFT JOIN productos_alimentacion_clasificaciones pac ON pac.id_producto_alimentacion = pa.id
            LEFT JOIN clasificacion_productos_alimentacion cpa ON cpa.id = pac.id_clasificacion_productos_alimentacion
            WHERE pa.id_tenant = :id_tenant
            GROUP BY pa.id
            ORDER BY pa.id DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getProductosPorClasificacionConStock($id_clasificacion)
    {
        try {
            $db = Flight::db();

            $sentence = $db->prepare("
                SELECT 
                    p.id,
                    p.nombre,
                    p.descripcion,
                    p.stock_actual,
                    p.stock_minimo,
                    p.precio_unitario,
                    p.id_unidad_medida,
                    um.nombre AS nombre_unidad,
                    um.abreviatura AS abreviatura_unidad,
                    pa.id AS id_producto_alimentacion,
                    pa.dias_vida_util,
                    tpa.nombre AS tipo_alimentacion,
                    -- Obtener todas las clasificaciones del producto
                    GROUP_CONCAT(DISTINCT cpa.nombre SEPARATOR ', ') AS clasificaciones
                FROM productos p
                INNER JOIN productos_alimentacion pa ON pa.id_producto = p.id
                INNER JOIN productos_alimentacion_clasificaciones pac ON pac.id_producto_alimentacion = pa.id
                INNER JOIN clasificacion_productos_alimentacion cpa ON cpa.id = pac.id_clasificacion_productos_alimentacion
                LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
                LEFT JOIN tipos_producto_alimentacion tpa ON pa.id_tipo_producto_alimentacion = tpa.id
                WHERE p.stock_actual > 0 
                    AND p.activo = 1
                    AND p.id_tenant = :id_tenant
                    AND pac.id_clasificacion_productos_alimentacion = :id_clasificacion
                GROUP BY p.id
                ORDER BY p.nombre ASC
            ");

            $sentence->bindParam(':id_clasificacion', $id_clasificacion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getProductosPorClasificacionConStock: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener productos por clasificación'], 500);
        }
    }
    public static function validarStockMultiple()
    {
        try {
            $db = Flight::db();

            // Obtener los datos del request
            $data = Flight::request()->data;
            $productos = $data['productos'] ?? [];

            if (empty($productos)) {
                Flight::json([
                    'error' => true,
                    'message' => 'No se enviaron productos para validar'
                ], 400);
                return;
            }

            $productos_insuficientes = [];
            $productos_validados = [];

            // Construir array de IDs para consulta
            $ids = array_map(function ($item) {
                return $item['id_producto'];
            }, $productos);

            // Crear placeholders para la consulta IN
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            // Consultar todos los productos de una vez
            $sentence = $db->prepare("
            SELECT 
                p.id,
                p.nombre,
                p.descripcion,
                p.stock_actual,
                p.stock_minimo,
                p.precio_unitario,
                um.nombre AS nombre_unidad,
                um.abreviatura AS abreviatura_unidad
            FROM productos p
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            WHERE p.id IN ($placeholders)
            AND p.id_tenant = ?
        ");

            $sentence->execute(array_merge($ids, [TenantContext::id()]));
            $productos_db = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // Crear un mapa de productos por ID para búsqueda rápida
            $productos_map = [];
            foreach ($productos_db as $prod) {
                $productos_map[$prod['id']] = $prod;
            }

            // Validar cada producto solicitado
            foreach ($productos as $item) {
                $id_producto = $item['id_producto'];
                $cantidad_necesaria = floatval($item['cantidad_necesaria']);

                if (isset($productos_map[$id_producto])) {
                    $producto = $productos_map[$id_producto];
                    $stock_actual = floatval($producto['stock_actual']);

                    // Agregar a la lista de validados
                    $producto_validado = [
                        'id_producto' => $producto['id'],
                        'nombre' => $producto['nombre'],
                        'cantidad_necesaria' => $cantidad_necesaria,
                        'stock_actual' => $stock_actual,
                        'stock_suficiente' => $stock_actual >= $cantidad_necesaria,
                        'precio_unitario' => floatval($producto['precio_unitario']),
                        'abreviatura_unidad' => $producto['abreviatura_unidad']
                    ];

                    $productos_validados[] = $producto_validado;

                    // Si el stock es insuficiente, agregar a la lista
                    if ($stock_actual < $cantidad_necesaria) {
                        $productos_insuficientes[] = $producto_validado;
                    }
                } else {
                    // Producto no encontrado
                    $producto_no_encontrado = [
                        'id_producto' => $id_producto,
                        'nombre' => "Producto ID: {$id_producto} (no encontrado)",
                        'cantidad_necesaria' => $cantidad_necesaria,
                        'stock_actual' => 0,
                        'stock_suficiente' => false,
                        'precio_unitario' => 0,
                        'abreviatura_unidad' => 'UND'
                    ];

                    $productos_validados[] = $producto_no_encontrado;
                    $productos_insuficientes[] = $producto_no_encontrado;
                }
            }

            // Retornar resultado
            Flight::json([
                'validacion_exitosa' => empty($productos_insuficientes),
                'productos_insuficientes' => $productos_insuficientes,
                'productos_validados' => $productos_validados,
                'total_productos_validados' => count($productos_validados),
                'total_insuficientes' => count($productos_insuficientes),
                'mensaje' => empty($productos_insuficientes)
                    ? 'Todos los productos tienen stock suficiente'
                    : 'Hay ' . count($productos_insuficientes) . ' producto(s) con stock insuficiente'
            ]);
        } catch (Exception $e) {
            error_log('Error en validarStockMultiple: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al validar stock: ' . $e->getMessage()
            ], 500);
        }
    }
}