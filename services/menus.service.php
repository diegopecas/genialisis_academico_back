<?php
class Menus
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
                                    SELECT m.*,
                                        cm.nombre AS nombre_clasificacion_menu,
                                        COUNT(DISTINCT mxi.id) as total_items,
                                        COUNT(DISTINCT mxps.id) as total_productos,
                                        COUNT(DISTINCT mm.id) as total_minutas,
                                        GROUP_CONCAT(DISTINCT im.nombre ORDER BY im.nombre SEPARATOR ', ') as items_nombres,
                                        GROUP_CONCAT(DISTINCT ps.nombre ORDER BY ps.nombre SEPARATOR ', ') as productos_nombres,
                                        GROUP_CONCAT(DISTINCT CONCAT('S', mm.semana, '-D', mm.dia) ORDER BY mm.semana, mm.dia SEPARATOR ', ') as minutas_asignadas
                                    FROM menus m
                                    LEFT JOIN menu_x_items mxi ON m.id = mxi.id_menu
                                    LEFT JOIN items_menu im ON mxi.id_item_menu = im.id
                                    LEFT JOIN menu_x_productos_servicios mxps ON m.id = mxps.id_menu
                                    LEFT JOIN productos_servicios ps ON mxps.id_producto_servicio = ps.id
                                    LEFT JOIN clasificacion_menus cm ON cm.id = m.id_clasificacion_menu
                                    LEFT JOIN menu_minutas mm ON m.id = mm.id_menu
                                    GROUP BY m.id
                                    ORDER BY m.nombre ASC
                                ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();

        $sentence = $db->prepare("
            SELECT m.*, 
                   cm.nombre AS nombre_clasificacion_menu
            FROM menus m
            LEFT JOIN clasificacion_menus cm ON cm.id = m.id_clasificacion_menu
            WHERE m.id = :id
        ");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $menu = $sentence->fetch();

        if (!$menu) {
            Flight::json(['error' => 'Menú no encontrado'], 404);
            return;
        }

        $stmtItems = $db->prepare("
            SELECT mxi.*, im.nombre as nombre_item
            FROM menu_x_items mxi
            INNER JOIN items_menu im ON mxi.id_item_menu = im.id
            WHERE mxi.id_menu = :id_menu
            ORDER BY mxi.id
        ");
        $stmtItems->bindParam(':id_menu', $id);
        $stmtItems->execute();
        $items = $stmtItems->fetchAll();

        $menu['items'] = $items;

        Flight::json($menu);
    }

    public static function new()
    {
        $db = Flight::db();

        $nombre = Flight::request()->data['nombre'];
        $descripcion = Flight::request()->data['descripcion'] ?? '';
        $activo = Flight::request()->data['activo'] ?? 1;
        $id_clasificacion = Flight::request()->data['id_clasificacion_menu'] ?? null;

        $sentence = $db->prepare("
            INSERT INTO menus (nombre, descripcion, activo, id_clasificacion_menu)
            VALUES (:nombre, :descripcion, :activo, :id_clasificacion)
        ");

        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id_clasificacion', $id_clasificacion);
        $sentence->execute();

        $id = $db->lastInsertId();
        Flight::json(['id' => $id]);
    }

    public static function replace()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $descripcion = Flight::request()->data['descripcion'] ?? '';
        $activo = Flight::request()->data['activo'] ?? 1;
        $id_clasificacion = Flight::request()->data['id_clasificacion_menu'] ?? null;

        $sentence = $db->prepare("
            UPDATE menus SET 
                nombre = :nombre,
                descripcion = :descripcion,
                activo = :activo,
                id_clasificacion_menu = :id_clasificacion
            WHERE id = :id
        ");

        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id_clasificacion', $id_clasificacion);
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("DELETE FROM menu_x_items WHERE id_menu = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            $stmt = $db->prepare("DELETE FROM menu_x_productos_servicios WHERE id_menu = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            $stmt = $db->prepare("DELETE FROM menus WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            $db->commit();
            Flight::json(['id' => $id, 'success' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            Flight::json(['error' => 'Error al eliminar el menú: ' . $e->getMessage()], 500);
        }
    }

    // Gestión de items del menú
    public static function getItemsByMenu($id_menu)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
                                SELECT mxi.*, 
                                    im.nombre as nombre_item, 
                                    im.id_porcion,
                                    p.nombre as nombre_porcion,
                                    GROUP_CONCAT(DISTINCT prod.nombre ORDER BY prod.nombre SEPARATOR ', ') as ingredientes_nombres
                                FROM menu_x_items mxi
                                INNER JOIN items_menu im ON mxi.id_item_menu = im.id
                                LEFT JOIN porciones p ON im.id_porcion = p.id
                                LEFT JOIN items_menu_ingredientes imi ON im.id = imi.id_item_menu
                                LEFT JOIN productos_alimentacion pa ON imi.id_producto_alimentacion = pa.id
                                LEFT JOIN productos prod ON pa.id_producto = prod.id
                                WHERE mxi.id_menu = :id_menu
                                GROUP BY mxi.id
                                ORDER BY mxi.id
                            ");
        $sentence->bindParam(':id_menu', $id_menu);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function asignarItems($id_menu)
    {
        try {
            $db = Flight::db();
            $items = Flight::request()->data['items'] ?? [];

            $db->beginTransaction();

            $deleteStmt = $db->prepare("DELETE FROM menu_x_items WHERE id_menu = :id_menu");
            $deleteStmt->bindParam(':id_menu', $id_menu);
            $deleteStmt->execute();

            if (!empty($items)) {
                $insertStmt = $db->prepare("
                    INSERT INTO menu_x_items (id_menu, id_item_menu, es_opcional)
                    VALUES (:id_menu, :id_item_menu, :es_opcional)
                ");

                foreach ($items as $item) {
                    $insertStmt->bindParam(':id_menu', $id_menu);
                    $insertStmt->bindParam(':id_item_menu', $item['id_item_menu']);
                    $es_opcional = $item['es_opcional'] ?? 0;
                    $insertStmt->bindParam(':es_opcional', $es_opcional);
                    $insertStmt->execute();
                }
            }

            $db->commit();
            self::getItemsByMenu($id_menu);
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Error en asignarItems: ' . $e->getMessage());
            Flight::json(['error' => 'Error al asignar items'], 500);
        }
    }

    // Gestión de productos/servicios del menú
    public static function getProductosServiciosByMenu($id_menu)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT mxps.*,
                   ps.nombre AS nombre_producto_servicio,
                   ps.detalles,
                   ps.valor_sugerido,
                   ps.id_clasificacion_productos_servicios,
                   cl.nombre AS nombre_clasificacion,
                   cp.nombre AS nombre_categoria,
                   pc.nombre AS nombre_periodicidad,
                   ha.nombre AS nombre_horario_alimentacion
            FROM menu_x_productos_servicios mxps
            INNER JOIN productos_servicios ps ON mxps.id_producto_servicio = ps.id
            LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
            LEFT JOIN categoria_productos_servicios cp ON cp.id = ps.id_categoria_productos_servicios
            LEFT JOIN periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
            LEFT JOIN horarios_alimentacion ha ON ha.id = ps.id_horario_alimentacion_sugerido
            WHERE mxps.id_menu = :id_menu
            ORDER BY ps.nombre
        ");
        $sentence->bindParam(':id_menu', $id_menu);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function asignarProductosServicios($id_menu)
    {
        try {
            $db = Flight::db();
            $productos = Flight::request()->data['productos'] ?? [];

            $db->beginTransaction();

            $deleteStmt = $db->prepare("DELETE FROM menu_x_productos_servicios WHERE id_menu = :id_menu");
            $deleteStmt->bindParam(':id_menu', $id_menu);
            $deleteStmt->execute();

            if (!empty($productos)) {
                $insertStmt = $db->prepare("
                    INSERT INTO menu_x_productos_servicios (id_menu, id_producto_servicio)
                    VALUES (:id_menu, :id_producto_servicio)
                ");

                foreach ($productos as $producto) {
                    $insertStmt->bindParam(':id_menu', $id_menu);
                    $insertStmt->bindParam(':id_producto_servicio', $producto['id_producto_servicio']);
                    $insertStmt->execute();
                }
            }

            $db->commit();
            self::getProductosServiciosByMenu($id_menu);
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Error en asignarProductosServicios: ' . $e->getMessage());
            Flight::json(['error' => 'Error al asignar productos/servicios'], 500);
        }
    }
}