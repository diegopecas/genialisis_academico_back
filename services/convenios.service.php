<?php
class Convenios
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT c.*, ps.nombre AS nombre_producto_servicio
                FROM convenios c
                INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
                WHERE c.id_tenant = :id_tenant
                ORDER BY c.nombre
            ");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en Convenios::getAll: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener convenios'], 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT c.*, ps.nombre AS nombre_producto_servicio
                FROM convenios c
                INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
                WHERE c.id = :id
                AND c.id_tenant = :id_tenant
            ");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetch(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en Convenios::getById: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener convenio'], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $id = Uuid::generar();
            $stmt = $db->prepare("
                INSERT INTO convenios (id, id_tenant, nombre, descripcion, id_producto_servicio, activo)
                VALUES (:id, :id_tenant, :nombre, :descripcion, :id_producto_servicio, :activo)
            ");
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindParam(':descripcion', $data['descripcion']);
            $stmt->bindParam(':id_producto_servicio', $data['id_producto_servicio']);
            $stmt->bindParam(':activo', $data['activo']);
            $stmt->execute();

            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en Convenios::new: ' . $e->getMessage());
            Flight::json(['error' => 'Error al crear convenio'], 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $stmt = $db->prepare("
                UPDATE convenios SET
                    nombre = :nombre,
                    descripcion = :descripcion,
                    id_producto_servicio = :id_producto_servicio,
                    activo = :activo
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindParam(':descripcion', $data['descripcion']);
            $stmt->bindParam(':id_producto_servicio', $data['id_producto_servicio']);
            $stmt->bindParam(':activo', $data['activo']);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            Flight::json(['id' => $data['id']]);
        } catch (Exception $e) {
            error_log('Error en Convenios::replace: ' . $e->getMessage());
            Flight::json(['error' => 'Error al actualizar convenio'], 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data['id'];

            $stmt = $db->prepare("DELETE FROM convenios WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en Convenios::delete: ' . $e->getMessage());
            Flight::json(['error' => 'Error al eliminar convenio'], 500);
        }
    }
}