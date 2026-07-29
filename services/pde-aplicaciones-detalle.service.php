<?php

class PdeAplicacionesDetalle
{
    public static function getByAplicacion($id_aplicacion)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT d.id, d.id_aplicacion, d.id_item, d.puntaje, d.asumido,
                   i.id_esfera, i.subarea, i.id_rango_edad, i.numero_item, i.descripcion, i.instrucciones,
                   i.materiales, i.puntaje_maximo, i.orden,
                   e.nombre AS nombre_esfera, e.abreviatura AS abreviatura_esfera,
                   r.nombre AS nombre_rango, r.orden AS orden_rango,
                   r.edad_meses_inicio, r.edad_meses_fin
            FROM pde_aplicaciones_detalle d
            INNER JOIN pde_items i ON d.id_item = i.id
            INNER JOIN esferas_desarrollo e ON i.id_esfera = e.id
            INNER JOIN pde_rangos_edad r ON i.id_rango_edad = r.id
            WHERE d.id_aplicacion = :id_aplicacion AND d.id_tenant = :id_tenant
            ORDER BY r.orden, e.nombre, i.orden
        ");
        $sentence->bindParam(':id_aplicacion', $id_aplicacion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Solo lo que la docente aplico de verdad, excluyendo lo que se dio por logrado.
    public static function getAplicadosByAplicacion($id_aplicacion)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT d.id, d.id_item, d.puntaje, d.asumido,
                   i.id_esfera, i.subarea, i.id_rango_edad, i.numero_item, i.descripcion, i.puntaje_maximo, i.orden,
                   e.nombre AS nombre_esfera,
                   r.nombre AS nombre_rango, r.orden AS orden_rango
            FROM pde_aplicaciones_detalle d
            INNER JOIN pde_items i ON d.id_item = i.id
            INNER JOIN esferas_desarrollo e ON i.id_esfera = e.id
            INNER JOIN pde_rangos_edad r ON i.id_rango_edad = r.id
            WHERE d.id_aplicacion = :id_aplicacion
              AND d.asumido = 0
              AND d.id_tenant = :id_tenant
            ORDER BY r.orden, e.nombre, i.orden
        ");
        $sentence->bindParam(':id_aplicacion', $id_aplicacion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT d.id, d.id_aplicacion, d.id_item, d.puntaje, d.asumido,
                   i.descripcion, i.puntaje_maximo
            FROM pde_aplicaciones_detalle d
            INNER JOIN pde_items i ON d.id_item = i.id
            WHERE d.id = :id AND d.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}
