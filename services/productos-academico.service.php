<?php
class ProductosAcademico
{
    public static function getAll()
    {
        $db = Flight::db();

        $sentence = $db->prepare("
            SELECT pa.*, 
                p.nombre AS nombre_producto,
                p.descripcion,
                p.stock_actual,
                p.precio_unitario,
                tpa.nombre AS tipo_academico,
                um.nombre AS nombre_unidad,
                um.abreviatura AS abreviatura_unidad
            FROM productos_academico pa
            INNER JOIN productos p ON pa.id_producto = p.id
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            LEFT JOIN tipos_producto_academico tpa ON pa.id_tipo_producto_academico = tpa.id
            WHERE pa.id_tenant = :id_tenant
            ORDER BY pa.id DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $productos = $sentence->fetchAll();

        // Obtener los grados asignados a cada producto
        $stmtGrados = $db->prepare("
            SELECT g.nombre 
            FROM productos_academico_x_grados pag
            INNER JOIN grados g ON g.id = pag.id_grado
            WHERE pag.id_producto_academico = :id_producto AND pag.id_tenant = :id_tenant
            ORDER BY g.orden ASC
        ");
        $stmtGrados->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

        foreach ($productos as &$producto) {
            $stmtGrados->bindParam(':id_producto', $producto['id']);
            $stmtGrados->execute();
            $grados = $stmtGrados->fetchAll(PDO::FETCH_COLUMN);
            $producto['grados'] = !empty($grados) ? implode('|', $grados) : '';
        }

        Flight::json($productos);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT pa.*, 
                p.nombre AS nombre_producto,
                p.descripcion,
                tpa.nombre AS tipo_academico,
                um.nombre AS nombre_unidad,
                um.abreviatura AS abreviatura_unidad
            FROM productos_academico pa
            INNER JOIN productos p ON pa.id_producto = p.id
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            LEFT JOIN tipos_producto_academico tpa ON pa.id_tipo_producto_academico = tpa.id
            WHERE pa.id = :id AND pa.id_tenant = :id_tenant
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
        $id_tipo_producto_academico = Flight::request()->data['id_tipo_producto_academico'];
        $es_consumible = Flight::request()->data['es_consumible'] ?? 0;
        $vida_util_estimada_dias = Flight::request()->data['vida_util_estimada_dias'];
        $edad_minima_meses = Flight::request()->data['edad_minima_meses'];
        $edad_maxima_meses = Flight::request()->data['edad_maxima_meses'];

        // Convertir vacíos a null
        $vida_util_estimada_dias = ($vida_util_estimada_dias !== '' && $vida_util_estimada_dias !== null) ? $vida_util_estimada_dias : null;
        $edad_minima_meses = ($edad_minima_meses !== '' && $edad_minima_meses !== null) ? $edad_minima_meses : null;
        $edad_maxima_meses = ($edad_maxima_meses !== '' && $edad_maxima_meses !== null) ? $edad_maxima_meses : null;

        $idNew = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO productos_academico(
            id,
            id_tenant,
            id_producto,
            id_tipo_producto_academico,
            es_consumible,
            vida_util_estimada_dias,
            edad_minima_meses,
            edad_maxima_meses
        ) VALUES (
            :id,
            :id_tenant,
            :id_producto,
            :id_tipo_producto_academico,
            :es_consumible,
            :vida_util_estimada_dias,
            :edad_minima_meses,
            :edad_maxima_meses
        )");

        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_producto', $id_producto);
        $sentence->bindParam(':id_tipo_producto_academico', $id_tipo_producto_academico);
        $sentence->bindParam(':es_consumible', $es_consumible);
        $sentence->bindParam(':vida_util_estimada_dias', $vida_util_estimada_dias);
        $sentence->bindParam(':edad_minima_meses', $edad_minima_meses);
        $sentence->bindParam(':edad_maxima_meses', $edad_maxima_meses);
        $sentence->execute();

        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $id_producto = Flight::request()->data['id_producto'];
        $id_tipo_producto_academico = Flight::request()->data['id_tipo_producto_academico'];
        $es_consumible = Flight::request()->data['es_consumible'] ?? 0;
        $vida_util_estimada_dias = Flight::request()->data['vida_util_estimada_dias'];
        $edad_minima_meses = Flight::request()->data['edad_minima_meses'];
        $edad_maxima_meses = Flight::request()->data['edad_maxima_meses'];

        $vida_util_estimada_dias = ($vida_util_estimada_dias !== '' && $vida_util_estimada_dias !== null) ? $vida_util_estimada_dias : null;
        $edad_minima_meses = ($edad_minima_meses !== '' && $edad_minima_meses !== null) ? $edad_minima_meses : null;
        $edad_maxima_meses = ($edad_maxima_meses !== '' && $edad_maxima_meses !== null) ? $edad_maxima_meses : null;

        $sentence = $db->prepare("UPDATE productos_academico SET 
            id_producto = :id_producto,
            id_tipo_producto_academico = :id_tipo_producto_academico,
            es_consumible = :es_consumible,
            vida_util_estimada_dias = :vida_util_estimada_dias,
            edad_minima_meses = :edad_minima_meses,
            edad_maxima_meses = :edad_maxima_meses
            WHERE id = :id AND id_tenant = :id_tenant");

        $sentence->bindParam(':id_producto', $id_producto);
        $sentence->bindParam(':id_tipo_producto_academico', $id_tipo_producto_academico);
        $sentence->bindParam(':es_consumible', $es_consumible);
        $sentence->bindParam(':vida_util_estimada_dias', $vida_util_estimada_dias);
        $sentence->bindParam(':edad_minima_meses', $edad_minima_meses);
        $sentence->bindParam(':edad_maxima_meses', $edad_maxima_meses);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        // Los grados se eliminan en cascada por la FK
        $sentence = $db->prepare("DELETE FROM productos_academico WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function getProductosDisponiblesParaAcademico()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT p.*, 
                u.nombre AS nombre_unidad, 
                u.abreviatura AS abreviatura_unidad, 
                tp.nombre AS tipo_producto_nombre
            FROM productos p
            LEFT JOIN unidades_medida u ON p.id_unidad_medida = u.id
            LEFT JOIN tipos_producto tp ON p.id_tipo_producto = tp.id
            WHERE p.id_tipo_producto = 4 
            AND p.activo = 1
            AND p.id_tenant = :id_tenant
            AND p.id NOT IN (
                SELECT id_producto FROM productos_academico
            )
            ORDER BY p.nombre ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getGradosByProducto($id_producto_academico)
    {
        try {
            $db = Flight::db();

            $sentence = $db->prepare("
                SELECT 
                    g.id,
                    g.nombre,
                    g.descripcion,
                    g.orden,
                    CASE 
                        WHEN pag.id IS NOT NULL THEN 1 
                        ELSE 0 
                    END as asignado
                FROM grados g
                LEFT JOIN productos_academico_x_grados pag 
                    ON pag.id_grado = g.id 
                    AND pag.id_producto_academico = :id_producto
                WHERE g.id_tenant = :id_tenant
                ORDER BY g.orden ASC
            ");

            $sentence->bindParam(':id_producto', $id_producto_academico);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getGradosByProducto: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener grados'], 500);
        }
    }

    public static function asignarGrados($id_producto_academico)
    {
        try {
            $db = Flight::db();
            $grados = Flight::request()->data['grados'] ?? [];

            $db->beginTransaction();

            // Eliminar grados actuales
            $deleteStmt = $db->prepare("
                DELETE FROM productos_academico_x_grados 
                WHERE id_producto_academico = :id_producto AND id_tenant = :id_tenant
            ");
            $deleteStmt->bindParam(':id_producto', $id_producto_academico);
            $deleteStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $deleteStmt->execute();

            // Insertar nuevos grados
            if (!empty($grados)) {
                $insertStmt = $db->prepare("
                    INSERT INTO productos_academico_x_grados 
                    (id_tenant, id_producto_academico, id_grado) 
                    VALUES (:id_tenant, :id_producto, :id_grado)
                ");
                $insertStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

                foreach ($grados as $id_grado) {
                    $insertStmt->bindParam(':id_producto', $id_producto_academico);
                    $insertStmt->bindParam(':id_grado', $id_grado);
                    $insertStmt->execute();
                }
            }

            $db->commit();

            self::getGradosByProducto($id_producto_academico);
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Error en asignarGrados: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al asignar grados'], 500);
        }
    }
}