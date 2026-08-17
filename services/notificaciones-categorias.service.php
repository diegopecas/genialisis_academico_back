<?php
class NotificacionesCategorias
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, codigo, nombre, icono, color, orden, activo FROM notificaciones_categorias WHERE id_tenant = :id_tenant ORDER BY orden, nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, codigo, nombre, icono, color, orden FROM notificaciones_categorias WHERE activo = 1 AND id_tenant = :id_tenant ORDER BY orden, nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM notificaciones_categorias WHERE id = :id AND id_tenant = :id_tenant");
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
            $codigo = Flight::request()->data['codigo'] ?? null;
            $nombre = Flight::request()->data['nombre'] ?? null;
            $icono  = Flight::request()->data['icono'] ?? null;
            $color  = Flight::request()->data['color'] ?? null;
            $orden  = Flight::request()->data['orden'] ?? 0;

            if (!$codigo || !$nombre) {
                Flight::json(array('error' => 'El código y el nombre son obligatorios'), 400);
                return;
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO notificaciones_categorias (id, id_tenant, codigo, nombre, icono, color, orden) VALUES (:id, :id_tenant, :codigo, :nombre, :icono, :color, :orden)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':color', $color);
            $sentence->bindParam(':orden', $orden);
            $sentence->execute();

            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en NotificacionesCategorias::new: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear la categoría'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id     = Flight::request()->data['id'] ?? null;
            $codigo = Flight::request()->data['codigo'] ?? null;
            $nombre = Flight::request()->data['nombre'] ?? null;
            $icono  = Flight::request()->data['icono'] ?? null;
            $color  = Flight::request()->data['color'] ?? null;
            $orden  = Flight::request()->data['orden'] ?? 0;
            $activo = Flight::request()->data['activo'] ?? 1;

            if (!$id || !$codigo || !$nombre) {
                Flight::json(array('error' => 'ID, código y nombre son obligatorios'), 400);
                return;
            }

            $sentence = $db->prepare("UPDATE notificaciones_categorias SET codigo = :codigo, nombre = :nombre, icono = :icono, color = :color, orden = :orden, activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':color', $color);
            $sentence->bindParam(':orden', $orden);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en NotificacionesCategorias::replace: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar la categoría'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            // No se borra si ya fue usada: se conserva la trazabilidad de las
            // notificaciones historicas que la referencian.
            $verificar = $db->prepare("SELECT COUNT(*) AS usos FROM notificaciones WHERE id_categoria = :id AND id_tenant = :id_tenant");
            $verificar->bindParam(':id', $id);
            $verificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verificar->execute();
            $usos = $verificar->fetch();

            if ($usos && (int)$usos['usos'] > 0) {
                Flight::json(array('error' => 'La categoría tiene notificaciones asociadas. Desactívela en lugar de eliminarla.'), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM notificaciones_categorias WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en NotificacionesCategorias::delete: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar la categoría'), 500);
        }
    }
}
