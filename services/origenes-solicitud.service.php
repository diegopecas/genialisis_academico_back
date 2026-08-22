<?php
/*=============================================
SERVICIO - ORIGENES DE SOLICITUD
Archivo: services/origenes-solicitud.service.php

Catalogo global del modulo de solicitudes: valores fijos, iguales para
todos los jardines, por eso la tabla no tiene id_tenant y este servicio
es de solo lectura. No hay CRUD a proposito.
=============================================*/

class OrigenesSolicitud
{
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, orden FROM origenes_solicitud ORDER BY orden, nombre");
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, orden FROM origenes_solicitud WHERE id = :id");
        $sentence->bindValue(':id', $id, PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }
}
