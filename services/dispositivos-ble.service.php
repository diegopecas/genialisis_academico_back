<?php
class DispositivosBle
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT d.*, af.nombre as nombre_area
            FROM dispositivos_ble d
            INNER JOIN areas_fisicas af ON d.id_area_fisica = af.id
            WHERE d.id_tenant = :id_tenant
            ORDER BY af.nombre ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT d.*, af.nombre as nombre_area
            FROM dispositivos_ble d
            INNER JOIN areas_fisicas af ON d.id_area_fisica = af.id
            WHERE d.activo = 1
            AND d.id_tenant = :id_tenant
            ORDER BY af.nombre ASC
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
            SELECT d.*, af.nombre as nombre_area
            FROM dispositivos_ble d
            INNER JOIN areas_fisicas af ON d.id_area_fisica = af.id
            WHERE d.id = :id
            AND d.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function create()
    {
        $db = Flight::db();

        $nombre = Flight::request()->data['nombre'];
        $id_area_fisica = Flight::request()->data['id_area_fisica'];
        $mac_address = Flight::request()->data['mac_address'] ?? null;
        $firmware_version = Flight::request()->data['firmware_version'] ?? null;

        $api_key = bin2hex(random_bytes(32));

        $sentence = $db->prepare("
            INSERT INTO dispositivos_ble(
                id, id_tenant, nombre, id_area_fisica, mac_address, api_key, firmware_version, activo, fecha_registro
            ) VALUES (
                :id, :id_tenant, :nombre, :id_area_fisica, :mac_address, :api_key, :firmware_version, 1, NOW()
            )
        ");

        $idDispositivo = Uuid::generar();
        $sentence->bindValue(':id', $idDispositivo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':id_area_fisica', $id_area_fisica);
        $sentence->bindParam(':mac_address', $mac_address);
        $sentence->bindParam(':api_key', $api_key);
        $sentence->bindParam(':firmware_version', $firmware_version);
        $sentence->execute();

        Flight::json(array('id' => $idDispositivo, 'api_key' => $api_key));
    }

    public static function update()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $id_area_fisica = Flight::request()->data['id_area_fisica'];
        $mac_address = Flight::request()->data['mac_address'] ?? null;
        $firmware_version = Flight::request()->data['firmware_version'] ?? null;
        $activo = Flight::request()->data['activo'] ?? 1;

        $sentence = $db->prepare("
            UPDATE dispositivos_ble SET 
                nombre = :nombre,
                id_area_fisica = :id_area_fisica,
                mac_address = :mac_address,
                firmware_version = :firmware_version,
                activo = :activo
            WHERE id = :id AND id_tenant = :id_tenant
        ");

        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':id_area_fisica', $id_area_fisica);
        $sentence->bindParam(':mac_address', $mac_address);
        $sentence->bindParam(':firmware_version', $firmware_version);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("UPDATE dispositivos_ble SET activo = 0 WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function regenerarApiKey($id)
    {
        $db = Flight::db();
        $api_key = bin2hex(random_bytes(32));

        $sentence = $db->prepare("UPDATE dispositivos_ble SET api_key = :api_key WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':api_key', $api_key);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id, 'api_key' => $api_key));
    }
}