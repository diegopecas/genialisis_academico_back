<?php
class CalendariosEventos
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT ce.*, 
                   tec.nombre AS tipo_evento_nombre,
                   tec.icono AS tipo_evento_icono
            FROM calendarios_eventos ce
            LEFT JOIN tipos_evento_calendario tec ON tec.id = ce.id_tipo_evento_calendario
            ORDER BY ce.fecha
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT ce.*, 
                   tec.nombre AS tipo_evento_nombre,
                   tec.icono AS tipo_evento_icono
            FROM calendarios_eventos ce
            LEFT JOIN tipos_evento_calendario tec ON tec.id = ce.id_tipo_evento_calendario
            WHERE ce.id = :id
        ");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function getByMes($anio, $mes)
    {
        $db = Flight::db();
        $fecha_inicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fecha_fin = date('Y-m-t', strtotime($fecha_inicio));

        $sentence = $db->prepare("
            SELECT ce.*, 
                   tec.nombre AS tipo_evento_nombre,
                   tec.icono AS tipo_evento_icono
            FROM calendarios_eventos ce
            LEFT JOIN tipos_evento_calendario tec ON tec.id = ce.id_tipo_evento_calendario
            WHERE ce.fecha BETWEEN :fecha_inicio AND :fecha_fin
            ORDER BY ce.fecha
        ");
        $sentence->bindParam(':fecha_inicio', $fecha_inicio);
        $sentence->bindParam(':fecha_fin', $fecha_fin);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();

            $fecha = $request->data->fecha;
            $id_tipo_evento_calendario = $request->data->id_tipo_evento_calendario;
            $descripcion = $request->data->descripcion;

            $sentence = $db->prepare("
                INSERT INTO calendarios_eventos (fecha, id_tipo_evento_calendario, descripcion) 
                VALUES (:fecha, :id_tipo, :descripcion)
            ");
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindParam(':id_tipo', $id_tipo_evento_calendario);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en CalendariosEventos::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();

            $id = $request->data->id;
            $fecha = $request->data->fecha;
            $id_tipo_evento_calendario = $request->data->id_tipo_evento_calendario;
            $descripcion = $request->data->descripcion;

            $sentence = $db->prepare("
                UPDATE calendarios_eventos SET 
                    fecha = :fecha,
                    id_tipo_evento_calendario = :id_tipo,
                    descripcion = :descripcion
                WHERE id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindParam(':id_tipo', $id_tipo_evento_calendario);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en CalendariosEventos::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data->id;

            $sentence = $db->prepare("DELETE FROM calendarios_eventos WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en CalendariosEventos::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}