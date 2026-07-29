<?php

class PdeRangosEdad
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, edad_meses_inicio, edad_meses_fin, orden, activo
            FROM pde_rangos_edad
            WHERE id_tenant = :id_tenant
            ORDER BY orden, edad_meses_inicio
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getAllList()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, edad_meses_inicio, edad_meses_fin, orden
            FROM pde_rangos_edad
            WHERE id_tenant = :id_tenant AND activo = 1
            ORDER BY orden, edad_meses_inicio
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
            SELECT id, nombre, edad_meses_inicio, edad_meses_fin, orden, activo
            FROM pde_rangos_edad
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Rango que corresponde a una edad en meses. Devuelve arreglo vacio si la edad esta fuera de cobertura.
    public static function getByEdad($edad_meses)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, edad_meses_inicio, edad_meses_fin, orden
            FROM pde_rangos_edad
            WHERE id_tenant = :id_tenant
              AND activo = 1
              AND :edad_meses >= edad_meses_inicio
              AND :edad_meses_fin < edad_meses_fin
            ORDER BY orden
            LIMIT 1
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':edad_meses', $edad_meses);
        $sentence->bindParam(':edad_meses_fin', $edad_meses);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];
        $edad_meses_inicio = Flight::request()->data['edad_meses_inicio'];
        $edad_meses_fin = Flight::request()->data['edad_meses_fin'];
        $orden = Flight::request()->data['orden'] ?? 0;
        $activo = Flight::request()->data['activo'] ?? 1;

        $idNew = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO pde_rangos_edad (id, id_tenant, nombre, edad_meses_inicio, edad_meses_fin, orden, activo)
            VALUES (:id, :id_tenant, :nombre, :edad_meses_inicio, :edad_meses_fin, :orden, :activo)
        ");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':edad_meses_inicio', $edad_meses_inicio);
        $sentence->bindParam(':edad_meses_fin', $edad_meses_fin);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->execute();

        Flight::json(array('id' => $idNew));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $edad_meses_inicio = Flight::request()->data['edad_meses_inicio'];
        $edad_meses_fin = Flight::request()->data['edad_meses_fin'];
        $orden = Flight::request()->data['orden'] ?? 0;
        $activo = Flight::request()->data['activo'] ?? 1;

        $sentence = $db->prepare("
            UPDATE pde_rangos_edad
            SET nombre = :nombre,
                edad_meses_inicio = :edad_meses_inicio,
                edad_meses_fin = :edad_meses_fin,
                orden = :orden,
                activo = :activo
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':edad_meses_inicio', $edad_meses_inicio);
        $sentence->bindParam(':edad_meses_fin', $edad_meses_fin);
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
            SELECT COUNT(*) AS total FROM pde_items
            WHERE id_rango_edad = :id AND id_tenant = :id_tenant
        ");
        $sentUso->bindParam(':id', $id);
        $sentUso->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentUso->execute();
        $uso = $sentUso->fetch();

        if ((int)$uso['total'] > 0) {
            Flight::json(array('error' => 'El rango tiene items asociados y no se puede eliminar'), 409);
            return;
        }

        $sentence = $db->prepare("DELETE FROM pde_rangos_edad WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}
