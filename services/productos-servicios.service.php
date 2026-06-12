<?php
class ProductosServicios
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT ps.*, 
               cp.nombre AS nombre_categoria, 
               cl.nombre AS nombre_clasificacion, 
               pc.nombre AS nombre_periodicidad,
               ha.nombre AS nombre_horario_alimentacion
        FROM productos_servicios ps
        LEFT JOIN categoria_productos_servicios cp ON cp.id = ps.id_categoria_productos_servicios
        LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
        LEFT JOIN periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
        LEFT JOIN horarios_alimentacion ha ON ha.id = ps.id_horario_alimentacion_sugerido
        ORDER BY ps.nombre
    ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT ps.*, 
               cp.nombre AS nombre_categoria, 
               cl.nombre AS nombre_clasificacion, 
               pc.nombre AS nombre_periodicidad,
               ha.nombre AS nombre_horario_alimentacion
        FROM productos_servicios ps
        LEFT JOIN categoria_productos_servicios cp ON cp.id = ps.id_categoria_productos_servicios
        LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
        LEFT JOIN periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
        LEFT JOIN horarios_alimentacion ha ON ha.id = ps.id_horario_alimentacion_sugerido
        WHERE ps.id = :id
        ORDER BY ps.nombre
    ");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function getByClasificacion($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT ps.*, 
               cp.nombre AS nombre_categoria, 
               cl.nombre AS nombre_clasificacion, 
               pc.nombre AS nombre_periodicidad,
               ha.nombre AS nombre_horario_alimentacion
        FROM productos_servicios ps
        LEFT JOIN categoria_productos_servicios cp ON cp.id = ps.id_categoria_productos_servicios
        LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
        LEFT JOIN periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
        LEFT JOIN horarios_alimentacion ha ON ha.id = ps.id_horario_alimentacion_sugerido
        WHERE ps.id_clasificacion_productos_servicios = :id
        ORDER BY ps.nombre
    ");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getCatalogoDisponibles()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT ps.id,
                   ps.nombre,
                   ps.detalles,
                   ps.id_clasificacion_productos_servicios,
                   ps.id_categoria_productos_servicios,
                   ps.id_periodicidad_cobro,
                   ps.valor_sugerido,
                   ps.id_horario_alimentacion_sugerido,
                   cl.nombre AS nombre_clasificacion,
                   cl.icono AS icono_clasificacion,
                   cp.nombre AS nombre_categoria,
                   pc.nombre AS nombre_periodicidad,
                   ha.nombre AS nombre_horario_alimentacion
            FROM productos_servicios ps
            LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
            LEFT JOIN categoria_productos_servicios cp ON cp.id = ps.id_categoria_productos_servicios
            LEFT JOIN periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
            LEFT JOIN horarios_alimentacion ha ON ha.id = ps.id_horario_alimentacion_sugerido
            WHERE ps.disponible = 1
            ORDER BY cl.nombre, ps.nombre
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            
            $nombre = $request->data->nombre;
            $detalles = $request->data->detalles;
            $id_clasificacion = $request->data->id_clasificacion_productos_servicios;
            $id_categoria = $request->data->id_categoria_productos_servicios;
            $id_periodicidad = $request->data->id_periodicidad_cobro;
            $valor_sugerido = $request->data->valor_sugerido;
            $disponible = $request->data->disponible;
            $anio = $request->data->anio;
            $id_horario = $request->data->id_horario_alimentacion_sugerido;

            $sentence = $db->prepare("INSERT INTO productos_servicios(nombre, detalles, id_clasificacion_productos_servicios, id_categoria_productos_servicios, id_periodicidad_cobro, valor_sugerido, disponible, anio, id_horario_alimentacion_sugerido) 
            VALUES (:nombre, :detalles, :id_clas, :id_cat, :id_period, :valor, :disponible, :anio, :id_horario)");

            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':detalles', $detalles);
            $sentence->bindParam(':id_clas', $id_clasificacion);
            $sentence->bindParam(':id_cat', $id_categoria);
            $sentence->bindParam(':id_period', $id_periodicidad);
            $sentence->bindParam(':valor', $valor_sugerido);
            $sentence->bindParam(':disponible', $disponible);
            $sentence->bindParam(':anio', $anio);
            $sentence->bindParam(':id_horario', $id_horario);

            $sentence->execute();
            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en ProductosServicios::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            
            $id = $request->data->id;
            $nombre = $request->data->nombre;
            $detalles = $request->data->detalles;
            $id_clasificacion = $request->data->id_clasificacion_productos_servicios;
            $id_categoria = $request->data->id_categoria_productos_servicios;
            $id_periodicidad = $request->data->id_periodicidad_cobro;
            $valor_sugerido = $request->data->valor_sugerido;
            $disponible = $request->data->disponible;
            $anio = $request->data->anio;
            $id_horario = $request->data->id_horario_alimentacion_sugerido;

            $sentence = $db->prepare("UPDATE productos_servicios SET 
                nombre = :nombre,
                detalles = :detalles,
                id_clasificacion_productos_servicios = :id_clas,
                id_categoria_productos_servicios = :id_cat,
                id_periodicidad_cobro = :id_period,
                valor_sugerido = :valor,
                disponible = :disponible,
                anio = :anio,
                id_horario_alimentacion_sugerido = :id_horario
                WHERE id = :id");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':detalles', $detalles);
            $sentence->bindParam(':id_clas', $id_clasificacion);
            $sentence->bindParam(':id_cat', $id_categoria);
            $sentence->bindParam(':id_period', $id_periodicidad);
            $sentence->bindParam(':valor', $valor_sugerido);
            $sentence->bindParam(':disponible', $disponible);
            $sentence->bindParam(':anio', $anio);
            $sentence->bindParam(':id_horario', $id_horario);

            $sentence->execute();
            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en ProductosServicios::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data->id;
            
            $sentence = $db->prepare("DELETE FROM productos_servicios WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en ProductosServicios::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}