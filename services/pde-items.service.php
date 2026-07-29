<?php

class PdeItems
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT i.id, i.id_esfera, i.subarea, i.id_rango_edad, i.numero_item, i.descripcion,
                   i.instrucciones, i.materiales, i.puntaje_maximo, i.orden, i.activo,
                   e.nombre AS nombre_esfera, e.abreviatura AS abreviatura_esfera,
                   r.nombre AS nombre_rango, r.edad_meses_inicio, r.edad_meses_fin, r.orden AS orden_rango
            FROM pde_items i
            INNER JOIN esferas_desarrollo e ON i.id_esfera = e.id
            INNER JOIN pde_rangos_edad r ON i.id_rango_edad = r.id
            WHERE i.id_tenant = :id_tenant
            ORDER BY r.orden, e.nombre, i.orden
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT i.id, i.id_esfera, i.subarea, i.id_rango_edad, i.numero_item, i.descripcion,
                   i.instrucciones, i.materiales, i.puntaje_maximo, i.orden, i.activo,
                   e.nombre AS nombre_esfera,
                   r.nombre AS nombre_rango
            FROM pde_items i
            INNER JOIN esferas_desarrollo e ON i.id_esfera = e.id
            INNER JOIN pde_rangos_edad r ON i.id_rango_edad = r.id
            WHERE i.id = :id AND i.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByRango($id_rango)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT i.id, i.id_esfera, i.subarea, i.id_rango_edad, i.numero_item, i.descripcion,
                   i.instrucciones, i.materiales, i.puntaje_maximo, i.orden,
                   e.nombre AS nombre_esfera, e.abreviatura AS abreviatura_esfera
            FROM pde_items i
            INNER JOIN esferas_desarrollo e ON i.id_esfera = e.id
            WHERE i.id_rango_edad = :id_rango AND i.id_tenant = :id_tenant AND i.activo = 1
            ORDER BY e.nombre, i.orden
        ");
        $sentence->bindParam(':id_rango', $id_rango);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByEsfera($id_esfera)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT i.id, i.id_esfera, i.subarea, i.id_rango_edad, i.numero_item, i.descripcion,
                   i.instrucciones, i.materiales, i.puntaje_maximo, i.orden,
                   r.nombre AS nombre_rango, r.orden AS orden_rango
            FROM pde_items i
            INNER JOIN pde_rangos_edad r ON i.id_rango_edad = r.id
            WHERE i.id_esfera = :id_esfera AND i.id_tenant = :id_tenant AND i.activo = 1
            ORDER BY r.orden, i.orden
        ");
        $sentence->bindParam(':id_esfera', $id_esfera);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Items de un rango y una esfera, que es la unidad que la docente aplica de una sola vez.
    public static function getByRangoEsfera($id_rango, $id_esfera)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT i.id, i.id_esfera, i.subarea, i.id_rango_edad, i.numero_item, i.descripcion,
                   i.instrucciones, i.materiales, i.puntaje_maximo, i.orden
            FROM pde_items i
            WHERE i.id_rango_edad = :id_rango
              AND i.id_esfera = :id_esfera
              AND i.id_tenant = :id_tenant
              AND i.activo = 1
            ORDER BY i.orden
        ");
        $sentence->bindParam(':id_rango', $id_rango);
        $sentence->bindParam(':id_esfera', $id_esfera);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Esferas que tienen items cargados. Es lo que define el alcance real del instrumento,
    // independiente de cuantas esferas existan en el catalogo general.
    public static function getEsferasConItems()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT DISTINCT e.id, e.nombre, e.abreviatura
            FROM pde_items i
            INNER JOIN esferas_desarrollo e ON i.id_esfera = e.id
            WHERE i.id_tenant = :id_tenant AND i.activo = 1
            ORDER BY e.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_esfera = Flight::request()->data['id_esfera'];
        $subarea = Flight::request()->data['subarea'] ?? null;
        $id_rango_edad = Flight::request()->data['id_rango_edad'];
        $numero_item = Flight::request()->data['numero_item'];
        $descripcion = Flight::request()->data['descripcion'];
        $instrucciones = Flight::request()->data['instrucciones'] ?? null;
        $materiales = Flight::request()->data['materiales'] ?? null;
        $puntaje_maximo = Flight::request()->data['puntaje_maximo'];
        $orden = Flight::request()->data['orden'] ?? 0;
        $activo = Flight::request()->data['activo'] ?? 1;

        $idNew = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO pde_items (
                id, id_tenant, id_esfera, subarea, id_rango_edad, numero_item, descripcion,
                instrucciones, materiales, puntaje_maximo, orden, activo
            ) VALUES (
                :id, :id_tenant, :id_esfera, :subarea, :id_rango_edad, :numero_item, :descripcion,
                :instrucciones, :materiales, :puntaje_maximo, :orden, :activo
            )
        ");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_esfera', $id_esfera);
        $sentence->bindParam(':subarea', $subarea);
        $sentence->bindParam(':id_rango_edad', $id_rango_edad);
        $sentence->bindParam(':numero_item', $numero_item);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':instrucciones', $instrucciones);
        $sentence->bindParam(':materiales', $materiales);
        $sentence->bindParam(':puntaje_maximo', $puntaje_maximo);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->execute();

        Flight::json(array('id' => $idNew));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_esfera = Flight::request()->data['id_esfera'];
        $subarea = Flight::request()->data['subarea'] ?? null;
        $id_rango_edad = Flight::request()->data['id_rango_edad'];
        $numero_item = Flight::request()->data['numero_item'];
        $descripcion = Flight::request()->data['descripcion'];
        $instrucciones = Flight::request()->data['instrucciones'] ?? null;
        $materiales = Flight::request()->data['materiales'] ?? null;
        $puntaje_maximo = Flight::request()->data['puntaje_maximo'];
        $orden = Flight::request()->data['orden'] ?? 0;
        $activo = Flight::request()->data['activo'] ?? 1;

        $sentence = $db->prepare("
            UPDATE pde_items
            SET id_esfera = :id_esfera,
                subarea = :subarea,
                id_rango_edad = :id_rango_edad,
                numero_item = :numero_item,
                descripcion = :descripcion,
                instrucciones = :instrucciones,
                materiales = :materiales,
                puntaje_maximo = :puntaje_maximo,
                orden = :orden,
                activo = :activo
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_esfera', $id_esfera);
        $sentence->bindParam(':subarea', $subarea);
        $sentence->bindParam(':id_rango_edad', $id_rango_edad);
        $sentence->bindParam(':numero_item', $numero_item);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':instrucciones', $instrucciones);
        $sentence->bindParam(':materiales', $materiales);
        $sentence->bindParam(':puntaje_maximo', $puntaje_maximo);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentUso = $db->prepare("
            SELECT COUNT(*) AS total FROM pde_aplicaciones_detalle
            WHERE id_item = :id AND id_tenant = :id_tenant
        ");
        $sentUso->bindParam(':id', $id);
        $sentUso->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentUso->execute();
        $uso = $sentUso->fetch();

        if ((int)$uso['total'] > 0) {
            Flight::json(array('error' => 'El item ya fue usado en aplicaciones y no se puede eliminar; desactivelo'), 409);
            return;
        }

        $sentence = $db->prepare("DELETE FROM pde_items WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}
