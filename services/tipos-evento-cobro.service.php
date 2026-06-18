<?php
class TiposEventoCobro
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("SELECT * FROM tipos_evento_cobro WHERE activo = 1 AND id_tenant = :id_tenant ORDER BY id");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en TiposEventoCobro::getAll: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener tipos de evento'], 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("SELECT * FROM tipos_evento_cobro WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetch(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en TiposEventoCobro::getById: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener tipo de evento'], 500);
        }
    }
}