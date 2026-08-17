<?php
class NotificacionesRespuestasOpciones
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_respuesta_tipo, codigo, etiqueta, orden, activo FROM notificaciones_respuestas_opciones WHERE id_tenant = :id_tenant ORDER BY id_respuesta_tipo, orden");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByTipo($id_respuesta_tipo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_respuesta_tipo, codigo, etiqueta, orden, activo FROM notificaciones_respuestas_opciones WHERE id_respuesta_tipo = :id_respuesta_tipo AND id_tenant = :id_tenant ORDER BY orden, etiqueta");
        $sentence->bindParam(':id_respuesta_tipo', $id_respuesta_tipo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM notificaciones_respuestas_opciones WHERE id = :id AND id_tenant = :id_tenant");
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
            $idRespuestaTipo = Flight::request()->data['id_respuesta_tipo'] ?? null;
            $codigo   = Flight::request()->data['codigo'] ?? null;
            $etiqueta = Flight::request()->data['etiqueta'] ?? null;
            $orden    = Flight::request()->data['orden'] ?? 0;

            if (!$idRespuestaTipo || !$codigo || !$etiqueta) {
                Flight::json(array('error' => 'El tipo, el código y la etiqueta son obligatorios'), 400);
                return;
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO notificaciones_respuestas_opciones (id, id_tenant, id_respuesta_tipo, codigo, etiqueta, orden) VALUES (:id, :id_tenant, :id_respuesta_tipo, :codigo, :etiqueta, :orden)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_respuesta_tipo', $idRespuestaTipo);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':etiqueta', $etiqueta);
            $sentence->bindParam(':orden', $orden);
            $sentence->execute();

            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en NotificacionesRespuestasOpciones::new: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear la opción de respuesta'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id       = Flight::request()->data['id'] ?? null;
            $codigo   = Flight::request()->data['codigo'] ?? null;
            $etiqueta = Flight::request()->data['etiqueta'] ?? null;
            $orden    = Flight::request()->data['orden'] ?? 0;
            $activo   = Flight::request()->data['activo'] ?? 1;

            if (!$id || !$codigo || !$etiqueta) {
                Flight::json(array('error' => 'ID, código y etiqueta son obligatorios'), 400);
                return;
            }

            $sentence = $db->prepare("UPDATE notificaciones_respuestas_opciones SET codigo = :codigo, etiqueta = :etiqueta, orden = :orden, activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':etiqueta', $etiqueta);
            $sentence->bindParam(':orden', $orden);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en NotificacionesRespuestasOpciones::replace: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar la opción de respuesta'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            // Si algun acudiente ya contesto con esta opcion, no se borra:
            // se perderia el sentido de la respuesta historica.
            $verificar = $db->prepare("SELECT COUNT(*) AS usos FROM notificaciones_destinatarios WHERE id_respuesta_opcion = :id AND id_tenant = :id_tenant");
            $verificar->bindParam(':id', $id);
            $verificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verificar->execute();
            $usos = $verificar->fetch();

            if ($usos && (int)$usos['usos'] > 0) {
                Flight::json(array('error' => 'La opción ya fue usada en respuestas. Desactívela en lugar de eliminarla.'), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM notificaciones_respuestas_opciones WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en NotificacionesRespuestasOpciones::delete: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar la opción de respuesta'), 500);
        }
    }
}
