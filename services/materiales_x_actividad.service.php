<?php
class MaterialesXActividad
{
    public static function getByActividad($id_actividad)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                mxa.id,
                mxa.id_actividad_academica,
                mxa.id_producto,
                mxa.nombre_material,
                mxa.cantidad,
                p.nombre AS nombre_producto,
                p.descripcion AS descripcion_producto,
                p.imagen AS imagen_producto
            FROM materiales_x_actividad mxa
            LEFT JOIN productos p ON mxa.id_producto = p.id
            WHERE mxa.id_actividad_academica = :id_actividad
            ORDER BY mxa.nombre_material
        ");
        $sentence->bindParam(':id_actividad', $id_actividad);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $id_actividad_academica = Flight::request()->data['id_actividad_academica'];
            $id_producto = Flight::request()->data['id_producto'] ?? null;
            $nombre_material = Flight::request()->data['nombre_material'];
            $cantidad = Flight::request()->data['cantidad'] ?? 1;

            if (!$id_actividad_academica || !$nombre_material) {
                Flight::json(array('error' => 'Actividad y nombre del material son obligatorios'), 400);
                return;
            }

            $sentence = $db->prepare("INSERT INTO materiales_x_actividad 
                (id_actividad_academica, id_producto, nombre_material, cantidad) 
                VALUES (:id_actividad, :id_producto, :nombre_material, :cantidad)");
            $sentence->bindParam(':id_actividad', $id_actividad_academica);
            $sentence->bindParam(':id_producto', $id_producto);
            $sentence->bindParam(':nombre_material', $nombre_material);
            $sentence->bindParam(':cantidad', $cantidad);
            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en MaterialesXActividad::new: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear el material'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM materiales_x_actividad WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en MaterialesXActividad::delete: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar el material'), 500);
        }
    }

    public static function deleteByActividad($id_actividad)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("DELETE FROM materiales_x_actividad WHERE id_actividad_academica = :id_actividad");
            $sentence->bindParam(':id_actividad', $id_actividad);
            $sentence->execute();

            Flight::json(array('eliminados' => $sentence->rowCount()));
        } catch (Exception $e) {
            error_log("Error en MaterialesXActividad::deleteByActividad: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar materiales'), 500);
        }
    }

    /**
     * Obtiene productos académicos filtrados por grado y activos.
     * Retorna los productos con su nombre desde la tabla productos.
     */
    public static function getProductosPorGrado($id_grado)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT DISTINCT
                p.id,
                p.nombre,
                p.descripcion,
                p.imagen,
                pa.es_consumible,
                tpa.nombre AS tipo_producto_academico
            FROM productos_academico_x_grados paxg
            INNER JOIN productos_academico pa ON paxg.id_producto_academico = pa.id
            INNER JOIN productos p ON pa.id_producto = p.id
            LEFT JOIN tipos_producto_academico tpa ON pa.id_tipo_producto_academico = tpa.id
            WHERE paxg.id_grado = :id_grado
            AND p.activo = 1
            ORDER BY p.nombre
        ");
        $sentence->bindParam(':id_grado', $id_grado);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Obtiene productos académicos filtrados por grupo (via grados_x_grupo) y activos.
     */
    public static function getProductosPorGrupo($id_grupo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT DISTINCT
                p.id,
                p.nombre,
                p.descripcion,
                p.imagen,
                pa.es_consumible,
                tpa.nombre AS tipo_producto_academico
            FROM grados_x_grupo gxg
            INNER JOIN productos_academico_x_grados paxg ON gxg.id_grado = paxg.id_grado
            INNER JOIN productos_academico pa ON paxg.id_producto_academico = pa.id
            INNER JOIN productos p ON pa.id_producto = p.id
            LEFT JOIN tipos_producto_academico tpa ON pa.id_tipo_producto_academico = tpa.id
            WHERE gxg.id_grupo = :id_grupo
            AND p.activo = 1
            ORDER BY p.nombre
        ");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}