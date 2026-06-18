<?php
class Ambientes
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, icono, activo FROM ambientes WHERE id_tenant = :id_tenant ORDER BY nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, icono FROM ambientes WHERE activo = 1 AND id_tenant = :id_tenant ORDER BY nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM ambientes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $nombre = Flight::request()->data['nombre'];
            $icono = Flight::request()->data['icono'] ?? null;

            if (!$nombre) {
                Flight::json(array('error' => 'El nombre es obligatorio'), 400);
                return;
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO ambientes (id, id_tenant, nombre, icono) VALUES (:id, :id_tenant, :nombre, :icono)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':icono', $icono);
            $sentence->execute();

            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en Ambientes::new: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear el ambiente'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $nombre = Flight::request()->data['nombre'];
            $icono = Flight::request()->data['icono'] ?? null;
            $activo = Flight::request()->data['activo'] ?? 1;

            if (!$id || !$nombre) {
                Flight::json(array('error' => 'ID y nombre son obligatorios'), 400);
                return;
            }

            $sentence = $db->prepare("UPDATE ambientes SET nombre = :nombre, icono = :icono, activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en Ambientes::replace: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar el ambiente'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM ambientes WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en Ambientes::delete: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar el ambiente'), 500);
        }
    }
}