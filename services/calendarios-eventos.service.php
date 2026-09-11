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
            WHERE ce.id_tenant = :id_tenant
            ORDER BY ce.fecha, ce.hora_inicio
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
            AND ce.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
            AND ce.id_tenant = :id_tenant
            ORDER BY ce.fecha, ce.hora_inicio
        ");
        $sentence->bindParam(':fecha_inicio', $fecha_inicio);
        $sentence->bindParam(':fecha_fin', $fecha_fin);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
            $hora_inicio = self::normalizarHora($request->data->hora_inicio ?? null);
            $hora_fin = self::normalizarHora($request->data->hora_fin ?? null);

            $errorHoras = self::validarHoras($hora_inicio, $hora_fin);
            if ($errorHoras !== null) {
                Flight::json(array('error' => $errorHoras), 400);
                return;
            }

            $sentence = $db->prepare("
                INSERT INTO calendarios_eventos (id, id_tenant, fecha, hora_inicio, hora_fin, id_tipo_evento_calendario, descripcion) 
                VALUES (:id, :id_tenant, :fecha, :hora_inicio, :hora_fin, :id_tipo, :descripcion)
            ");
            $idEvento = Uuid::generar();
            $sentence->bindValue(':id', $idEvento);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindValue(':hora_inicio', $hora_inicio, $hora_inicio === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $sentence->bindValue(':hora_fin', $hora_fin, $hora_fin === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $sentence->bindParam(':id_tipo', $id_tipo_evento_calendario);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->execute();

            Flight::json(array('id' => $idEvento));
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
            $hora_inicio = self::normalizarHora($request->data->hora_inicio ?? null);
            $hora_fin = self::normalizarHora($request->data->hora_fin ?? null);

            $errorHoras = self::validarHoras($hora_inicio, $hora_fin);
            if ($errorHoras !== null) {
                Flight::json(array('error' => $errorHoras), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE calendarios_eventos SET 
                    fecha = :fecha,
                    hora_inicio = :hora_inicio,
                    hora_fin = :hora_fin,
                    id_tipo_evento_calendario = :id_tipo,
                    descripcion = :descripcion
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindValue(':hora_inicio', $hora_inicio, $hora_inicio === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $sentence->bindValue(':hora_fin', $hora_fin, $hora_fin === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
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

            $sentence = $db->prepare("DELETE FROM calendarios_eventos WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en CalendariosEventos::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Deja la hora en formato HH:MM:SS o null (evento de todo el día).
     * Acepta HH:MM y HH:MM:SS; cualquier otro valor se toma como sin hora.
     */
    private static function normalizarHora($hora)
    {
        if ($hora === null) {
            return null;
        }
        $hora = trim((string) $hora);
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
            return $hora . ':00';
        }
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $hora)) {
            return $hora;
        }
        return null;
    }

    /**
     * La hora de fin exige hora de inicio y debe ser posterior a ella.
     * Devuelve el mensaje de error o null si todo está bien.
     */
    private static function validarHoras($hora_inicio, $hora_fin)
    {
        if ($hora_fin !== null && $hora_inicio === null) {
            return 'Para poner hora de fin primero indica la hora de inicio';
        }
        if ($hora_inicio !== null && $hora_fin !== null && $hora_fin <= $hora_inicio) {
            return 'La hora de fin debe ser posterior a la hora de inicio';
        }
        return null;
    }
}