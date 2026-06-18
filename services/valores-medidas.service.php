<?php
class ValoresMedidas
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT vm.id, vm.id_medida, vm.valor_numerico, vm.etiqueta, vm.orden, vm.activo,
                       m.nombre AS nombre_medida
                FROM valores_medidas vm
                INNER JOIN medidas m ON m.id = vm.id_medida
                WHERE vm.id_tenant = :id_tenant
                ORDER BY vm.id_medida, vm.orden
            ");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en ValoresMedidas::getAll: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener valores'], 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("SELECT id, id_medida, valor_numerico, etiqueta, orden, activo FROM valores_medidas WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en ValoresMedidas::getById: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener valor'], 500);
        }
    }

    public static function getByMedida($id_medida)
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("SELECT id, id_medida, valor_numerico, etiqueta, orden, activo FROM valores_medidas WHERE id_medida = :id_medida AND id_tenant = :id_tenant ORDER BY orden");
            $stmt->bindParam(':id_medida', $id_medida);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en ValoresMedidas::getByMedida: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener valores de la medida'], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id_medida = $request->data->id_medida;
            $valor_numerico = $request->data->valor_numerico;
            $etiqueta = $request->data->etiqueta;
            $orden = isset($request->data->orden) ? $request->data->orden : 0;
            $activo = isset($request->data->activo) ? $request->data->activo : 1;

            $idNew = Uuid::generar();
            $stmt = $db->prepare("INSERT INTO valores_medidas (id, id_tenant, id_medida, valor_numerico, etiqueta, orden, activo) VALUES (:id, :id_tenant, :id_medida, :valor_numerico, :etiqueta, :orden, :activo)");
            $stmt->bindValue(':id', $idNew);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_medida', $id_medida);
            $stmt->bindParam(':valor_numerico', $valor_numerico);
            $stmt->bindParam(':etiqueta', $etiqueta);
            $stmt->bindParam(':orden', $orden);
            $stmt->bindParam(':activo', $activo);
            $stmt->execute();
            $id = $idNew;
            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en ValoresMedidas::new: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data->id;
            $id_medida = $request->data->id_medida;
            $valor_numerico = $request->data->valor_numerico;
            $etiqueta = $request->data->etiqueta;
            $orden = isset($request->data->orden) ? $request->data->orden : 0;
            $activo = isset($request->data->activo) ? $request->data->activo : 1;

            $stmt = $db->prepare("UPDATE valores_medidas SET id_medida = :id_medida, valor_numerico = :valor_numerico, etiqueta = :etiqueta, orden = :orden, activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':id_medida', $id_medida);
            $stmt->bindParam(':valor_numerico', $valor_numerico);
            $stmt->bindParam(':etiqueta', $etiqueta);
            $stmt->bindParam(':orden', $orden);
            $stmt->bindParam(':activo', $activo);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            self::getById($id);
        } catch (Exception $e) {
            error_log('Error en ValoresMedidas::replace: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data->id;

            $stmt = $db->prepare("DELETE FROM valores_medidas WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en ValoresMedidas::delete: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}