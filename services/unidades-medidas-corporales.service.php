<?php
class UnidadesMedidasCorporales
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("SELECT id, nombre, abreviatura FROM unidades_medidas_corporales WHERE id_tenant = :id_tenant ORDER BY nombre");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en UnidadesMedidasCorporales::getAll: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener unidades'], 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("SELECT id, nombre, abreviatura FROM unidades_medidas_corporales WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en UnidadesMedidasCorporales::getById: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener unidad'], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $nombre = $request->data->nombre;
            $abreviatura = $request->data->abreviatura;

            $idNew = Uuid::generar();
            $stmt = $db->prepare("INSERT INTO unidades_medidas_corporales (id, id_tenant, nombre, abreviatura) VALUES (:id, :id_tenant, :nombre, :abreviatura)");
            $stmt->bindValue(':id', $idNew);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':abreviatura', $abreviatura);
            $stmt->execute();
            $id = $idNew;
            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en UnidadesMedidasCorporales::new: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data->id;
            $nombre = $request->data->nombre;
            $abreviatura = $request->data->abreviatura;

            $stmt = $db->prepare("UPDATE unidades_medidas_corporales SET nombre = :nombre, abreviatura = :abreviatura WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':abreviatura', $abreviatura);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            self::getById($id);
        } catch (Exception $e) {
            error_log('Error en UnidadesMedidasCorporales::replace: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data->id;

            $stmt = $db->prepare("DELETE FROM unidades_medidas_corporales WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en UnidadesMedidasCorporales::delete: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}