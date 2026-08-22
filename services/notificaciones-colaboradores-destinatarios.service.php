<?php
/*=============================================
SERVICIO - DESTINATARIOS DE NOTIFICACIONES A COLABORADORES
Archivo: services/notificaciones-colaboradores-destinatarios.service.php

Una fila por colaborador que recibe el aviso, con su estado de lectura.

El id_usuario puede venir en NULL: el destinatario se registra igual y el
aviso le queda visible el dia que le creen credenciales. El push, en
cambio, solo alcanza a los que ya tienen usuario.
=============================================*/

class NotificacionesColaboradoresDestinatarios
{
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_notificacion_colaborador, id_colaborador, id_usuario, fecha_lectura
                                  FROM notificaciones_colaboradores_destinatarios
                                  WHERE id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_notificacion_colaborador, id_colaborador, id_usuario, fecha_lectura
                                  FROM notificaciones_colaboradores_destinatarios
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * A quienes se envio un aviso y quien ya lo abrio.
     */
    public static function getByNotificacion($id_notificacion)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT d.id, d.id_colaborador, d.id_usuario, d.fecha_lectura,
                                         TRIM(CONCAT(COALESCE(p.primer_nombre, ''), ' ', COALESCE(p.primer_apellido, ''))) AS colaborador_nombre
                                  FROM notificaciones_colaboradores_destinatarios d
                                  INNER JOIN colaboradores col ON col.id = d.id_colaborador
                                  INNER JOIN personas p ON p.id = col.id_persona
                                  WHERE d.id_notificacion_colaborador = :id_notificacion
                                    AND d.id_tenant = :id_tenant
                                  ORDER BY p.primer_apellido, p.primer_nombre");
        $sentence->bindParam(':id_notificacion', $id_notificacion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Bandeja del colaborador que esta consultando.
     */
    public static function getMisNotificaciones()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $idUsuario = isset($userData->id) ? $userData->id : null;

        if (!$idUsuario) {
            Flight::json(array('error' => 'No se pudo identificar el usuario'), 401);
            return;
        }

        $sentence = $db->prepare("SELECT d.id AS id_destinatario,
                                         d.fecha_lectura,
                                         n.id AS id_notificacion,
                                         n.titulo,
                                         n.cuerpo,
                                         n.id_referencia,
                                         n.fecha_envio,
                                         n.id_tipo_notificacion_colaborador,
                                         t.nombre AS tipo_nombre,
                                         t.icono  AS tipo_icono
                                  FROM notificaciones_colaboradores_destinatarios d
                                  INNER JOIN notificaciones_colaboradores n ON n.id = d.id_notificacion_colaborador
                                  INNER JOIN tipos_notificacion_colaborador t ON t.id = n.id_tipo_notificacion_colaborador
                                  WHERE d.id_usuario = :id_usuario
                                    AND d.id_tenant = :id_tenant
                                    AND n.activo = 1
                                  ORDER BY n.fecha_envio DESC");
        $sentence->bindParam(':id_usuario', $idUsuario);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Conteo de avisos sin abrir. Es lo que alimenta el indicador del menu.
     */
    public static function getNoLeidas()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $idUsuario = isset($userData->id) ? $userData->id : null;

        if (!$idUsuario) {
            Flight::json(array('total' => 0));
            return;
        }

        $sentence = $db->prepare("SELECT COUNT(*) AS total
                                  FROM notificaciones_colaboradores_destinatarios d
                                  INNER JOIN notificaciones_colaboradores n ON n.id = d.id_notificacion_colaborador
                                  WHERE d.id_usuario = :id_usuario
                                    AND d.id_tenant = :id_tenant
                                    AND d.fecha_lectura IS NULL
                                    AND n.activo = 1");
        $sentence->bindParam(':id_usuario', $idUsuario);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        Flight::json(array('total' => $fila ? (int)$fila['total'] : 0));
    }

    /**
     * Marca el aviso como leido. Solo el propio destinatario puede hacerlo,
     * por eso la condicion incluye su id_usuario.
     */
    public static function marcarLeida()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id        = isset(Flight::request()->data['id']) ? Flight::request()->data['id'] : null;
        $idUsuario = isset($userData->id) ? $userData->id : null;

        if (empty($id) || empty($idUsuario)) {
            Flight::json(array('error' => 'Falta el destinatario'), 400);
            return;
        }

        $sentence = $db->prepare("UPDATE notificaciones_colaboradores_destinatarios
                                  SET fecha_lectura = :fecha_lectura
                                  WHERE id = :id
                                    AND id_usuario = :id_usuario
                                    AND id_tenant = :id_tenant
                                    AND fecha_lectura IS NULL");
        $sentence->bindValue(':fecha_lectura', date('Y-m-d H:i:s'));
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':id_usuario', $idUsuario);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function new()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $idNotificacion = isset(Flight::request()->data['id_notificacion_colaborador']) ? Flight::request()->data['id_notificacion_colaborador'] : null;
        $idColaborador  = isset(Flight::request()->data['id_colaborador']) ? Flight::request()->data['id_colaborador'] : null;

        if (empty($idNotificacion) || empty($idColaborador)) {
            Flight::json(array('error' => 'La notificacion y el colaborador son obligatorios'), 400);
            return;
        }

        $insertados = self::insertarLote($db, $idNotificacion, array(
            array('id_colaborador' => $idColaborador, 'id_usuario' => null)
        ));

        Flight::json(array('id_notificacion_colaborador' => $idNotificacion, 'usuarios_con_push' => count($insertados)));
    }

    public static function replace()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id        = Flight::request()->data['id'];
        $idUsuario = isset(Flight::request()->data['id_usuario']) && Flight::request()->data['id_usuario'] !== '' ? Flight::request()->data['id_usuario'] : null;

        $sentence = $db->prepare("UPDATE notificaciones_colaboradores_destinatarios
                                  SET id_usuario = :id_usuario
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_usuario', $idUsuario);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("DELETE FROM notificaciones_colaboradores_destinatarios
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Inserta los destinatarios de un aviso resolviendo el usuario
     * institucional de cada colaborador cuando no viene dado.
     *
     * @param  PDO    $db
     * @param  string $idNotificacion
     * @param  array  $destinatarios Filas con id_colaborador y, opcionalmente, id_usuario
     * @return array  Ids de usuario a los que se les puede mandar push
     */
    public static function insertarLote(PDO $db, $idNotificacion, array $destinatarios)
    {
        $insertar = $db->prepare("INSERT IGNORE INTO notificaciones_colaboradores_destinatarios
            (id, id_tenant, id_notificacion_colaborador, id_colaborador, id_usuario)
            VALUES (:id, :id_tenant, :id_notificacion, :id_colaborador, :id_usuario)");

        $buscarUsuario = $db->prepare("SELECT u.id
                                       FROM colaboradores col
                                       INNER JOIN usuarios u ON u.id_persona = col.id_persona
                                                            AND u.id_tenant = col.id_tenant
                                                            AND u.activo = 1
                                                            AND u.acceso_institucional = 1
                                       WHERE col.id = :id_colaborador AND col.id_tenant = :id_tenant
                                       LIMIT 1");

        $idsUsuarios = array();

        foreach ($destinatarios as $destinatario) {
            if (empty($destinatario['id_colaborador'])) {
                continue;
            }

            $idUsuario = isset($destinatario['id_usuario']) ? $destinatario['id_usuario'] : null;

            if (empty($idUsuario)) {
                $buscarUsuario->bindValue(':id_colaborador', $destinatario['id_colaborador']);
                $buscarUsuario->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $buscarUsuario->execute();
                $fila = $buscarUsuario->fetch();
                $idUsuario = $fila ? $fila['id'] : null;
            }

            $insertar->bindValue(':id', Uuid::generar());
            $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $insertar->bindValue(':id_notificacion', $idNotificacion);
            $insertar->bindValue(':id_colaborador', $destinatario['id_colaborador']);
            $insertar->bindValue(':id_usuario', $idUsuario);
            $insertar->execute();

            if (!empty($idUsuario)) {
                $idsUsuarios[$idUsuario] = true;
            }
        }

        return array_keys($idsUsuarios);
    }
}
