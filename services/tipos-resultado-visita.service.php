<?php
class TiposResultadoVisita
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM tipos_resultado_visita WHERE activo = 1 AND id_tenant = :id_tenant ORDER BY id");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM tipos_resultado_visita WHERE id = :id AND id_tenant = :id_tenant");
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
            $codigo = Flight::request()->data['codigo'];
            $es_exitoso = isset(Flight::request()->data['es_exitoso']) ? Flight::request()->data['es_exitoso'] : 0;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $id = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO tipos_resultado_visita (id, id_tenant, nombre, codigo, es_exitoso, activo) VALUES (:id, :id_tenant, :nombre, :codigo, :es_exitoso, :activo)");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':es_exitoso', $es_exitoso);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_resultado_visita new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $nombre = Flight::request()->data['nombre'];
            $codigo = Flight::request()->data['codigo'];
            $es_exitoso = isset(Flight::request()->data['es_exitoso']) ? Flight::request()->data['es_exitoso'] : 0;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $sentence = $db->prepare("UPDATE tipos_resultado_visita SET nombre = :nombre, codigo = :codigo, es_exitoso = :es_exitoso, activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':es_exitoso', $es_exitoso);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en tipos_resultado_visita replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM tipos_resultado_visita WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_resultado_visita delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}