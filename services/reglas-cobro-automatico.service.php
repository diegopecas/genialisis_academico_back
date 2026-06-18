<?php
class ReglasCobroAutomatico
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT r.*, 
                       tec.nombre AS nombre_tipo_evento,
                       tec.descripcion AS descripcion_tipo_evento,
                       ps.nombre AS nombre_producto_servicio,
                       ps.valor_sugerido,
                       g.nombre AS nombre_grupo,
                       ds.nombre AS nombre_dia_semana,
                       c.nombre AS nombre_convenio_exime
                FROM reglas_cobro_automatico r
                INNER JOIN tipos_evento_cobro tec ON tec.id = r.id_tipo_evento
                INNER JOIN productos_servicios ps ON ps.id = r.id_producto_servicio
                LEFT JOIN grupos g ON g.id = r.id_grupo
                LEFT JOIN dias_semana ds ON ds.id = r.id_dia_semana
                LEFT JOIN convenios c ON c.id = r.id_convenio_exime
                WHERE r.id_tenant = :id_tenant
                ORDER BY r.id_tipo_evento, r.prioridad
            ");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en ReglasCobroAutomatico::getAll: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener reglas'], 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT r.*, 
                       tec.nombre AS nombre_tipo_evento,
                       ps.nombre AS nombre_producto_servicio,
                       ps.valor_sugerido,
                       g.nombre AS nombre_grupo,
                       ds.nombre AS nombre_dia_semana,
                       c.nombre AS nombre_convenio_exime
                FROM reglas_cobro_automatico r
                INNER JOIN tipos_evento_cobro tec ON tec.id = r.id_tipo_evento
                INNER JOIN productos_servicios ps ON ps.id = r.id_producto_servicio
                LEFT JOIN grupos g ON g.id = r.id_grupo
                LEFT JOIN dias_semana ds ON ds.id = r.id_dia_semana
                LEFT JOIN convenios c ON c.id = r.id_convenio_exime
                WHERE r.id = :id AND r.id_tenant = :id_tenant
            ");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetch(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en ReglasCobroAutomatico::getById: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener regla'], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $idNew = Uuid::generar();
            $stmt = $db->prepare("
                INSERT INTO reglas_cobro_automatico 
                (id, id_tenant, nombre, id_tipo_evento, id_producto_servicio, id_grupo, hora_desde, hora_hasta, 
                 id_dia_semana, id_convenio_exime, prioridad, activo)
                VALUES 
                (:id, :id_tenant, :nombre, :id_tipo_evento, :id_producto_servicio, :id_grupo, :hora_desde, :hora_hasta,
                 :id_dia_semana, :id_convenio_exime, :prioridad, :activo)
            ");
            $stmt->bindValue(':id', $idNew);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindParam(':id_tipo_evento', $data['id_tipo_evento']);
            $stmt->bindParam(':id_producto_servicio', $data['id_producto_servicio']);
            $stmt->bindParam(':id_grupo', $data['id_grupo']);
            $stmt->bindParam(':hora_desde', $data['hora_desde']);
            $stmt->bindParam(':hora_hasta', $data['hora_hasta']);
            $stmt->bindParam(':id_dia_semana', $data['id_dia_semana']);
            $stmt->bindParam(':id_convenio_exime', $data['id_convenio_exime']);
            $stmt->bindParam(':prioridad', $data['prioridad']);
            $stmt->bindParam(':activo', $data['activo']);
            $stmt->execute();

            $id = $idNew;
            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en ReglasCobroAutomatico::new: ' . $e->getMessage());
            Flight::json(['error' => 'Error al crear regla'], 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $stmt = $db->prepare("
                UPDATE reglas_cobro_automatico SET
                    nombre = :nombre,
                    id_tipo_evento = :id_tipo_evento,
                    id_producto_servicio = :id_producto_servicio,
                    id_grupo = :id_grupo,
                    hora_desde = :hora_desde,
                    hora_hasta = :hora_hasta,
                    id_dia_semana = :id_dia_semana,
                    id_convenio_exime = :id_convenio_exime,
                    prioridad = :prioridad,
                    activo = :activo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindParam(':id_tipo_evento', $data['id_tipo_evento']);
            $stmt->bindParam(':id_producto_servicio', $data['id_producto_servicio']);
            $stmt->bindParam(':id_grupo', $data['id_grupo']);
            $stmt->bindParam(':hora_desde', $data['hora_desde']);
            $stmt->bindParam(':hora_hasta', $data['hora_hasta']);
            $stmt->bindParam(':id_dia_semana', $data['id_dia_semana']);
            $stmt->bindParam(':id_convenio_exime', $data['id_convenio_exime']);
            $stmt->bindParam(':prioridad', $data['prioridad']);
            $stmt->bindParam(':activo', $data['activo']);
            $stmt->execute();

            Flight::json(['id' => $data['id']]);
        } catch (Exception $e) {
            error_log('Error en ReglasCobroAutomatico::replace: ' . $e->getMessage());
            Flight::json(['error' => 'Error al actualizar regla'], 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data['id'];

            $stmt = $db->prepare("DELETE FROM reglas_cobro_automatico WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en ReglasCobroAutomatico::delete: ' . $e->getMessage());
            Flight::json(['error' => 'Error al eliminar regla'], 500);
        }
    }
}