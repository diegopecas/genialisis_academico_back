<?php
/*=============================================
SERVICIO - ROLES DE PERSONA EN LA SOLICITUD
Archivo: services/roles-solicitud-persona.service.php

Catalogo global del modulo de solicitudes: valores fijos, iguales para
todos los jardines, por eso la tabla no tiene id_tenant y este servicio
es de solo lectura. No hay CRUD a proposito.
=============================================*/

class RolesSolicitudPersona
{
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, orden FROM roles_solicitud_persona ORDER BY orden, nombre");
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, orden FROM roles_solicitud_persona WHERE id = :id");
        $sentence->bindValue(':id', $id, PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }
}
