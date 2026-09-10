<?php
class InformesItems
{
    /**
     * Filas propias del informe: participación familiar, secciones
     * informativas. No van en la malla curricular a propósito.
     */
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT i.id, i.id_seccion, i.texto, i.orden, i.activo,
                   s.nombre AS nombre_seccion
            FROM informes_items i
            INNER JOIN informes_secciones s ON i.id_seccion = s.id
            WHERE i.id_tenant = :id_tenant
            ORDER BY s.orden, i.orden");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, id_seccion, texto, orden, activo
            FROM informes_items
            WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getBySeccion($id_seccion)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, id_seccion, texto, orden, activo
            FROM informes_items
            WHERE id_seccion = :id_seccion AND id_tenant = :id_tenant
            ORDER BY orden");
        $sentence->bindParam(':id_seccion', $id_seccion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();

        $id_seccion = Flight::request()->data['id_seccion'];
        $texto = Flight::request()->data['texto'];
        $orden = Flight::request()->data['orden'] ?? 0;
        $activo = Flight::request()->data['activo'] ?? 1;

        if ($id_seccion === null || $id_seccion === '' || $texto === null || $texto === '') {
            Flight::json(array('error' => 'La sección y el texto son obligatorios'), 400);
            return;
        }

        $sentence = $db->prepare("INSERT INTO informes_items(
                id, id_tenant, id_seccion, texto, orden, activo
            ) VALUES (
                :id, :id_tenant, :id_seccion, :texto, :orden, :activo
            )");

        $idNew = Uuid::generar();
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_seccion', $id_seccion);
        $sentence->bindParam(':texto', $texto);
        $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
        $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $idNew));
    }

    public static function replace()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $id_seccion = Flight::request()->data['id_seccion'];
        $texto = Flight::request()->data['texto'];
        $orden = Flight::request()->data['orden'] ?? 0;
        $activo = Flight::request()->data['activo'] ?? 1;

        if ($id_seccion === null || $id_seccion === '' || $texto === null || $texto === '') {
            Flight::json(array('error' => 'La sección y el texto son obligatorios'), 400);
            return;
        }

        $sentence = $db->prepare("UPDATE informes_items SET
                id_seccion = :id_seccion,
                texto = :texto,
                orden = :orden,
                activo = :activo
            WHERE id = :id AND id_tenant = :id_tenant");

        $sentence->bindParam(':id_seccion', $id_seccion);
        $sentence->bindParam(':texto', $texto);
        $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
        $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM informes_items WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
}
