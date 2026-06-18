<?php
class TiposProductoAcademico
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT * FROM tipos_producto_academico 
            WHERE activo = 1 
            AND id_tenant = :id_tenant
            ORDER BY nombre ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}