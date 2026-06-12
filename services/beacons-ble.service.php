<?php
class BeaconsBle
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT b.*,
                   CONCAT(p.primer_nombre, ' ', p.primer_apellido) as nombre_estudiante
            FROM beacons_ble b
            LEFT JOIN estudiantes e ON b.id_estudiante = e.id
            LEFT JOIN personas p ON e.id_persona = p.id
            ORDER BY b.nombre ASC
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT b.*,
                   CONCAT(p.primer_nombre, ' ', p.primer_apellido) as nombre_estudiante
            FROM beacons_ble b
            LEFT JOIN estudiantes e ON b.id_estudiante = e.id
            LEFT JOIN personas p ON e.id_persona = p.id
            WHERE b.activo = 1
            ORDER BY b.nombre ASC
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT b.*,
                   CONCAT(p.primer_nombre, ' ', p.primer_apellido) as nombre_estudiante
            FROM beacons_ble b
            LEFT JOIN estudiantes e ON b.id_estudiante = e.id
            LEFT JOIN personas p ON e.id_persona = p.id
            WHERE b.id = :id
        ");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function create()
    {
        $db = Flight::db();

        $mac_address = strtolower(Flight::request()->data['mac_address']);
        $nombre = Flight::request()->data['nombre'] ?? null;
        $id_estudiante = Flight::request()->data['id_estudiante'] ?? null;
        $uuid_ibeacon = Flight::request()->data['uuid_ibeacon'] ?? null;

        $check = $db->prepare("SELECT id FROM beacons_ble WHERE mac_address = :mac");
        $check->bindParam(':mac', $mac_address);
        $check->execute();
        if ($check->fetch()) {
            Flight::json(array('error' => 'Ya existe un beacon con esa dirección MAC'), 400);
            return;
        }

        $sentence = $db->prepare("
            INSERT INTO beacons_ble(
                mac_address, nombre, id_estudiante, uuid_ibeacon, activo, fecha_registro
            ) VALUES (
                :mac_address, :nombre, :id_estudiante, :uuid_ibeacon, 1, NOW()
            )
        ");

        $sentence->bindParam(':mac_address', $mac_address);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':uuid_ibeacon', $uuid_ibeacon);
        $sentence->execute();

        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function update()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $mac_address = strtolower(Flight::request()->data['mac_address']);
        $nombre = Flight::request()->data['nombre'] ?? null;
        $id_estudiante = Flight::request()->data['id_estudiante'] ?? null;
        $uuid_ibeacon = Flight::request()->data['uuid_ibeacon'] ?? null;
        $activo = Flight::request()->data['activo'] ?? 1;

        $check = $db->prepare("SELECT id FROM beacons_ble WHERE mac_address = :mac AND id != :id");
        $check->bindParam(':mac', $mac_address);
        $check->bindParam(':id', $id);
        $check->execute();
        if ($check->fetch()) {
            Flight::json(array('error' => 'Ya existe otro beacon con esa dirección MAC'), 400);
            return;
        }

        $sentence = $db->prepare("
            UPDATE beacons_ble SET 
                mac_address = :mac_address,
                nombre = :nombre,
                id_estudiante = :id_estudiante,
                uuid_ibeacon = :uuid_ibeacon,
                activo = :activo
            WHERE id = :id
        ");

        $sentence->bindParam(':mac_address', $mac_address);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':uuid_ibeacon', $uuid_ibeacon);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("UPDATE beacons_ble SET activo = 0 WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}