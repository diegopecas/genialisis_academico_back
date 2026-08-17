<?php
class NotificacionesRespuestasTipos
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, codigo, nombre, activo FROM notificaciones_respuestas_tipos WHERE id_tenant = :id_tenant ORDER BY nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Devuelve los juegos activos con sus opciones anidadas. Es lo que
     * consume el formulario de creacion de notificaciones para pintar los
     * botones sin hacer una segunda llamada por cada juego.
     */
    public static function getActivosConOpciones()
    {
        $db = Flight::db();

        $sentence = $db->prepare("SELECT id, codigo, nombre FROM notificaciones_respuestas_tipos WHERE activo = 1 AND id_tenant = :id_tenant ORDER BY nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $tipos = $sentence->fetchAll();

        $opcionesSentence = $db->prepare("SELECT id, id_respuesta_tipo, codigo, etiqueta, orden FROM notificaciones_respuestas_opciones WHERE activo = 1 AND id_respuesta_tipo = :id_respuesta_tipo ORDER BY orden, etiqueta");

        foreach ($tipos as &$tipo) {
            $opcionesSentence->bindValue(':id_respuesta_tipo', $tipo['id']);
            $opcionesSentence->execute();
            $tipo['opciones'] = $opcionesSentence->fetchAll();
        }

        Flight::json($tipos);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM notificaciones_respuestas_tipos WHERE id = :id AND id_tenant = :id_tenant");
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

            if (!$codigo || !$nombre) {
                Flight::json(array('error' => 'El código y el nombre son obligatorios'), 400);
                return;
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO notificaciones_respuestas_tipos (id, id_tenant, codigo, nombre) VALUES (:id, :id_tenant, :codigo, :nombre)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->execute();

            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en NotificacionesRespuestasTipos::new: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear el tipo de respuesta'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id     = Flight::request()->data['id'] ?? null;
            $codigo = Flight::request()->data['codigo'] ?? null;
            $nombre = Flight::request()->data['nombre'] ?? null;
            $activo = Flight::request()->data['activo'] ?? 1;

            if (!$id || !$codigo || !$nombre) {
                Flight::json(array('error' => 'ID, código y nombre son obligatorios'), 400);
                return;
            }

            $sentence = $db->prepare("UPDATE notificaciones_respuestas_tipos SET codigo = :codigo, nombre = :nombre, activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en NotificacionesRespuestasTipos::replace: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar el tipo de respuesta'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            $verificar = $db->prepare("SELECT COUNT(*) AS usos FROM notificaciones WHERE id_respuesta_tipo = :id AND id_tenant = :id_tenant");
            $verificar->bindParam(':id', $id);
            $verificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verificar->execute();
            $usos = $verificar->fetch();

            if ($usos && (int)$usos['usos'] > 0) {
                Flight::json(array('error' => 'El tipo de respuesta tiene notificaciones asociadas. Desactívelo en lugar de eliminarlo.'), 400);
                return;
            }

            $db->beginTransaction();

            $borrarOpciones = $db->prepare("DELETE FROM notificaciones_respuestas_opciones WHERE id_respuesta_tipo = :id AND id_tenant = :id_tenant");
            $borrarOpciones->bindParam(':id', $id);
            $borrarOpciones->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrarOpciones->execute();

            $sentence = $db->prepare("DELETE FROM notificaciones_respuestas_tipos WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $db->commit();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            if (Flight::db()->inTransaction()) {
                Flight::db()->rollBack();
            }
            error_log("Error en NotificacionesRespuestasTipos::delete: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar el tipo de respuesta'), 500);
        }
    }
}
