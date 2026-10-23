<?php
/**
 * Tipos de afiliacion a la seguridad social del colaborador
 * (por la empresa, independiente, dependiente).
 * Catalogo global, sin id_tenant: es el mismo para todos los tenants.
 */
class TiposAfiliacionSeguridadSocial
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, codigo, nombre, descripcion FROM tipos_afiliacion_seguridad_social ORDER BY id");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}
