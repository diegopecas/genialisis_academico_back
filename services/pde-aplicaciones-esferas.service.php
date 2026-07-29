<?php

class PdeAplicacionesEsferas
{
    public static function getByAplicacion($id_aplicacion)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT ae.id, ae.id_aplicacion, ae.id_esfera, ae.id_rango_techo, ae.meses_base,
                   ae.puntos_obtenidos, ae.puntos_posibles, ae.edad_desarrollo_meses, ae.indice, ae.semaforo,
                   e.nombre AS nombre_esfera, e.abreviatura AS abreviatura_esfera,
                   r.nombre AS nombre_rango_techo, r.edad_meses_inicio, r.edad_meses_fin
            FROM pde_aplicaciones_esferas ae
            INNER JOIN esferas_desarrollo e ON ae.id_esfera = e.id
            LEFT JOIN pde_rangos_edad r ON ae.id_rango_techo = r.id
            WHERE ae.id_aplicacion = :id_aplicacion AND ae.id_tenant = :id_tenant
            ORDER BY e.nombre
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
            SELECT ae.id, ae.id_aplicacion, ae.id_esfera, ae.id_rango_techo, ae.meses_base,
                   ae.puntos_obtenidos, ae.puntos_posibles, ae.edad_desarrollo_meses, ae.indice, ae.semaforo,
                   e.nombre AS nombre_esfera
            FROM pde_aplicaciones_esferas ae
            INNER JOIN esferas_desarrollo e ON ae.id_esfera = e.id
            WHERE ae.id = :id AND ae.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Evolucion de una esfera a lo largo de las aplicaciones finalizadas de un estudiante.
    public static function getHistorialEstudiante($id_estudiante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT a.id AS id_aplicacion, a.fecha_aplicacion, a.edad_meses,
                   ae.id_esfera, e.nombre AS nombre_esfera, e.abreviatura AS abreviatura_esfera,
                   ae.edad_desarrollo_meses, ae.indice, ae.semaforo
            FROM pde_aplicaciones_esferas ae
            INNER JOIN pde_aplicaciones a ON ae.id_aplicacion = a.id
            INNER JOIN esferas_desarrollo e ON ae.id_esfera = e.id
            WHERE a.id_estudiante = :id_estudiante
              AND a.activo = 1
              AND a.estado = 'finalizada'
              AND a.id_tenant = :id_tenant
            ORDER BY a.fecha_aplicacion, e.nombre
        ");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}
