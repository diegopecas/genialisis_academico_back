<?php
/*=============================================
SERVICIO - ESTADOS DE SOLICITUD
Archivo: services/estados-solicitud.service.php

Catalogo global del modulo de solicitudes: valores fijos, iguales para
todos los jardines, por eso la tabla no tiene id_tenant y este servicio
es de solo lectura. No hay CRUD a proposito.
=============================================*/

class EstadosSolicitud
{
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, color, orden FROM estados_solicitud ORDER BY orden, nombre");
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, color, orden FROM estados_solicitud WHERE id = :id");
        $sentence->bindValue(':id', $id, PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }
}
