<?php
/**
 * Entidades de seguridad social (AFP, EPS, ARL y cajas de compensacion).
 * Catalogo global, sin id_tenant: es el mismo para todos los tenants.
 */
class EntidadesSeguridadSocial
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT e.id, e.nombre, e.id_tipo_entidad, t.codigo codigo_tipo_entidad, t.nombre nombre_tipo_entidad, e.activo
            FROM entidades_seguridad_social e
            INNER JOIN tipos_entidad_seguridad_social t ON e.id_tipo_entidad = t.id
            WHERE e.activo = 1
            ORDER BY t.id, e.nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Entidades de un tipo, por su codigo: AFP, EPS, ARL o CCF.
     */
    public static function getByTipo($codigo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT e.id, e.nombre, e.id_tipo_entidad, t.codigo codigo_tipo_entidad, t.nombre nombre_tipo_entidad, e.activo
            FROM entidades_seguridad_social e
            INNER JOIN tipos_entidad_seguridad_social t ON e.id_tipo_entidad = t.id
            WHERE t.codigo = :codigo AND e.activo = 1
            ORDER BY e.nombre");
        $sentence->bindParam(':codigo', $codigo);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}
