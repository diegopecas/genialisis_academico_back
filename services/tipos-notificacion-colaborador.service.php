<?php
/*=============================================
SERVICIO - TIPOS DE NOTIFICACION A COLABORADORES
Archivo: services/tipos-notificacion-colaborador.service.php

Catalogo global del modulo de solicitudes: valores fijos, iguales para
todos los jardines, por eso la tabla no tiene id_tenant y este servicio
es de solo lectura. No hay CRUD a proposito.
=============================================*/

class TiposNotificacionColaborador
{
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, icono, orden FROM tipos_notificacion_colaborador ORDER BY orden, nombre");
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, icono, orden FROM tipos_notificacion_colaborador WHERE id = :id");
        $sentence->bindValue(':id', $id, PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }
}
