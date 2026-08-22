<?php
/*=============================================
SERVICIO - HORARIOS DE LA SOLICITUD
Archivo: services/solicitudes-horarios.service.php

Plantilla de horas de una solicitud: 14:00, 16:00, 18:00. Es lo que pidio
el acudiente, no lo que pasa cada dia; el dia a dia vive en
solicitudes_ocurrencias.

Se mantiene aparte de las ocurrencias para poder regenerar el rango sin
tener que deducir las horas mirando las ocurrencias futuras.
=============================================*/

class SolicitudesHorarios
{
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_solicitud, hora, orden
                                  FROM solicitudes_horarios
                                  WHERE id_tenant = :id_tenant
                                  ORDER BY id_solicitud, orden, hora");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_solicitud, hora, orden
                                  FROM solicitudes_horarios
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getBySolicitud($id_solicitud)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_solicitud, hora, orden
                                  FROM solicitudes_horarios
                                  WHERE id_solicitud = :id_solicitud AND id_tenant = :id_tenant
                                  ORDER BY orden, hora");
        $sentence->bindParam(':id_solicitud', $id_solicitud);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function new()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $idSolicitud = isset(Flight::request()->data['id_solicitud']) ? Flight::request()->data['id_solicitud'] : null;
        $hora        = isset(Flight::request()->data['hora']) ? Flight::request()->data['hora'] : null;
        $orden       = isset(Flight::request()->data['orden']) ? (int)Flight::request()->data['orden'] : 0;

        if (empty($idSolicitud) || empty($hora)) {
            Flight::json(array('error' => 'La solicitud y la hora son obligatorias'), 400);
            return;
        }

        $id = self::insertar($db, $idSolicitud, $hora, $orden);

        if ($id === null) {
            Flight::json(array('error' => 'Esa hora ya esta registrada en la solicitud'), 400);
            return;
        }

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id    = Flight::request()->data['id'];
        $hora  = isset(Flight::request()->data['hora']) ? Flight::request()->data['hora'] : null;
        $orden = isset(Flight::request()->data['orden']) ? (int)Flight::request()->data['orden'] : 0;

        if (empty($hora)) {
            Flight::json(array('error' => 'La hora es obligatoria'), 400);
            return;
        }

        $sentence = $db->prepare("UPDATE solicitudes_horarios
                                  SET hora = :hora, orden = :orden
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':hora', $hora);
        $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("DELETE FROM solicitudes_horarios WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Insercion interna, sin responder al cliente. La usa el guardado de la
     * solicitud, que crea todas las horas dentro de la misma transaccion.
     *
     * @param  PDO    $db
     * @param  string $idSolicitud
     * @param  string $hora  Formato HH:MM o HH:MM:SS
     * @param  int    $orden
     * @return string|null   Id creado, o null si la hora ya existia
     */
    public static function insertar(PDO $db, $idSolicitud, $hora, $orden = 0)
    {
        $hora = self::normalizarHora($hora);

        if ($hora === null) {
            return null;
        }

        $sentence = $db->prepare("SELECT COUNT(*) AS repetidos
                                  FROM solicitudes_horarios
                                  WHERE id_solicitud = :id_solicitud AND hora = :hora");
        $sentence->bindParam(':id_solicitud', $idSolicitud);
        $sentence->bindParam(':hora', $hora);
        $sentence->execute();
        $fila = $sentence->fetch();

        if ($fila && $fila['repetidos'] > 0) {
            return null;
        }

        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO solicitudes_horarios
            (id, id_tenant, id_solicitud, hora, orden)
            VALUES (:id, :id_tenant, :id_solicitud, :hora, :orden)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_solicitud', $idSolicitud);
        $sentence->bindParam(':hora', $hora);
        $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
        $sentence->execute();

        return $id;
    }

    /**
     * Horas de una solicitud como arreglo simple. Lectura interna para la
     * generacion de ocurrencias.
     *
     * @param  PDO    $db
     * @param  string $idSolicitud
     * @return array  Lista de horas en formato HH:MM:SS
     */
    public static function listar(PDO $db, $idSolicitud)
    {
        $sentence = $db->prepare("SELECT hora FROM solicitudes_horarios
                                  WHERE id_solicitud = :id_solicitud
                                  ORDER BY orden, hora");
        $sentence->bindParam(':id_solicitud', $idSolicitud);
        $sentence->execute();

        $horas = array();
        foreach ($sentence->fetchAll() as $fila) {
            $horas[] = $fila['hora'];
        }

        return $horas;
    }

    /**
     * Borra todas las horas de una solicitud. La usa el reemplazo en bloque
     * cuando el acudiente cambia las horas de un tratamiento.
     */
    public static function borrarPorSolicitud(PDO $db, $idSolicitud)
    {
        $sentence = $db->prepare("DELETE FROM solicitudes_horarios
                                  WHERE id_solicitud = :id_solicitud AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_solicitud', $idSolicitud);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    /**
     * Deja la hora en HH:MM:SS. Devuelve null si el formato no sirve, para
     * que el llamador decida si es un error o simplemente se ignora.
     */
    private static function normalizarHora($hora)
    {
        $hora = trim((string)$hora);

        if (preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            $hora .= ':00';
        }

        if (!preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $hora)) {
            return null;
        }

        $partes = explode(':', $hora);

        if ((int)$partes[0] > 23 || (int)$partes[1] > 59 || (int)$partes[2] > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', (int)$partes[0], (int)$partes[1], (int)$partes[2]);
    }
}
