<?php
class ItemsMenu
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
                                SELECT im.*,
                                    p.nombre as nombre_porcion,
                                    COUNT(DISTINCT imi.id) as total_ingredientes,
                                    GROUP_CONCAT(DISTINCT prod.nombre ORDER BY prod.nombre SEPARATOR ', ') as ingredientes_nombres
                                FROM items_menu im
                                LEFT JOIN porciones p ON im.id_porcion = p.id
                                LEFT JOIN items_menu_ingredientes imi ON im.id = imi.id_item_menu
                                LEFT JOIN productos_alimentacion pa ON imi.id_producto_alimentacion = pa.id
                                LEFT JOIN productos prod ON pa.id_producto = prod.id
                                WHERE im.id_tenant = :id_tenant
                                GROUP BY im.id
                                ORDER BY im.nombre ASC
                            ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();

        // Obtener item con su porción
        $sentence = $db->prepare("
            SELECT im.*, p.nombre as nombre_porcion 
            FROM items_menu im
            LEFT JOIN porciones p ON im.id_porcion = p.id
            WHERE im.id = :id AND im.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $item = $sentence->fetch();

        if (!$item) {
            Flight::json(['error' => 'Item no encontrado'], 404);
            return;
        }

        // Obtener ingredientes del item con información completa
        $stmtIngredientes = $db->prepare("
            SELECT imi.*, 
                pa.id as id_producto_alimentacion,
                p.nombre as nombre_producto,
                p.descripcion as descripcion_producto,
                um.nombre as nombre_unidad,
                um.abreviatura as abreviatura_unidad
            FROM items_menu_ingredientes imi
            INNER JOIN productos_alimentacion pa ON imi.id_producto_alimentacion = pa.id
            INNER JOIN productos p ON pa.id_producto = p.id
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            WHERE imi.id_item_menu = :id_item AND imi.id_tenant = :id_tenant
            ORDER BY p.nombre
        ");
        $stmtIngredientes->bindParam(':id_item', $id);
        $stmtIngredientes->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtIngredientes->execute();
        $ingredientes = $stmtIngredientes->fetchAll();

        $item['ingredientes'] = $ingredientes;

        Flight::json($item);
    }

    public static function new()
    {
        $db = Flight::db();

        $nombre = Flight::request()->data['nombre'];
        $id_porcion = Flight::request()->data['id_porcion'];

        $idNew = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO items_menu (id, id_tenant, nombre, id_porcion)
            VALUES (:id, :id_tenant, :nombre, :id_porcion)
        ");

        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':id_porcion', $id_porcion);
        $sentence->execute();

        $id = $idNew;
        Flight::json(['id' => $id]);
    }

    public static function replace()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $id_porcion = Flight::request()->data['id_porcion'];

        $sentence = $db->prepare("
            UPDATE items_menu SET 
                nombre = :nombre,
                id_porcion = :id_porcion
            WHERE id = :id AND id_tenant = :id_tenant
        ");

        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':id_porcion', $id_porcion);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        try {
            $db->beginTransaction();

            // Verificar si el item está siendo usado en algún menú
            $checkStmt = $db->prepare("SELECT COUNT(*) as total FROM menu_x_items WHERE id_item_menu = :id AND id_tenant = :id_tenant");
            $checkStmt->bindParam(':id', $id);
            $checkStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkStmt->execute();
            $result = $checkStmt->fetch();

            if ($result['total'] > 0) {
                $db->rollBack();
                Flight::json(['error' => 'No se puede eliminar el item porque está siendo usado en menús'], 400);
                return;
            }

            // Eliminar ingredientes
            $stmt = $db->prepare("DELETE FROM items_menu_ingredientes WHERE id_item_menu = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            // Eliminar item
            $stmt = $db->prepare("DELETE FROM items_menu WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            $db->commit();
            Flight::json(['id' => $id, 'success' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            Flight::json(['error' => 'Error al eliminar el item: ' . $e->getMessage()], 500);
        }
    }

    // Gestión de ingredientes
    public static function getIngredientesByItem($id_item)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT imi.*, 
                p.nombre as nombre_producto,
                p.descripcion as descripcion_producto,
                um.nombre as nombre_unidad,
                um.abreviatura as abreviatura_unidad
            FROM items_menu_ingredientes imi
            INNER JOIN productos_alimentacion pa ON imi.id_producto_alimentacion = pa.id
            INNER JOIN productos p ON pa.id_producto = p.id
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            WHERE imi.id_item_menu = :id_item AND imi.id_tenant = :id_tenant
            ORDER BY p.nombre
        ");
        $sentence->bindParam(':id_item', $id_item);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function asignarIngredientes($id_item)
    {
        try {
            $db = Flight::db();
            $ingredientes = Flight::request()->data['ingredientes'] ?? [];

            $db->beginTransaction();

            // Eliminar ingredientes actuales
            $deleteStmt = $db->prepare("DELETE FROM items_menu_ingredientes WHERE id_item_menu = :id_item AND id_tenant = :id_tenant");
            $deleteStmt->bindParam(':id_item', $id_item);
            $deleteStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $deleteStmt->execute();

            // Insertar nuevos ingredientes
            if (!empty($ingredientes)) {
                $insertStmt = $db->prepare("
                    INSERT INTO items_menu_ingredientes 
                    (id_tenant, id_item_menu, id_producto_alimentacion, cantidad, es_opcional)
                    VALUES (:id_tenant, :id_item_menu, :id_producto_alimentacion, :cantidad, :es_opcional)
                ");
                $insertStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

                foreach ($ingredientes as $ingrediente) {
                    $insertStmt->bindParam(':id_item_menu', $id_item);
                    $insertStmt->bindParam(':id_producto_alimentacion', $ingrediente['id_producto_alimentacion']);
                    $insertStmt->bindParam(':cantidad', $ingrediente['cantidad']);
                    $es_opcional = $ingrediente['es_opcional'] ?? 0;
                    $insertStmt->bindParam(':es_opcional', $es_opcional);
                    $insertStmt->execute();
                }
            }

            $db->commit();
            self::getIngredientesByItem($id_item);
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Error en asignarIngredientes: ' . $e->getMessage());
            Flight::json(['error' => 'Error al asignar ingredientes: ' . $e->getMessage()], 500);
        }
    }

    // Obtener items disponibles (que no están en un menú específico)
    public static function getItemsDisponiblesParaMenu($id_menu)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT im.*, p.nombre as nombre_porcion
            FROM items_menu im
            LEFT JOIN porciones p ON im.id_porcion = p.id
            WHERE im.id NOT IN (
                SELECT id_item_menu 
                FROM menu_x_items 
                WHERE id_menu = :id_menu
            )
            AND im.id_tenant = :id_tenant
            ORDER BY im.nombre ASC
        ");
        $sentence->bindParam(':id_menu', $id_menu);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}
