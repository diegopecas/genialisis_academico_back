<?php
/*=============================================
SERVICIO - NOTIFICACIONES A COLABORADORES
Archivo: services/notificaciones-colaboradores.service.php

Bandeja del portal institucional. Tablas propias, no las de
notificaciones: esas son del portal de padres, las escribe una persona y
llevan adjuntos y botones de respuesta.

Estas las genera el sistema y lo que si necesitan, y las otras no, es
saber a que registro llevan al hacer clic. Eso es id_referencia: el tipo
dice a que tabla apunta.

id_usuario_envio queda desde ya para cuando un directivo escriba una a
mano: NULL significa generada por el sistema.
=============================================*/

class NotificacionesColaboradores
{
    const TIPO_POR_APROBAR         = 1;
    const TIPO_COMPROMISO_PROXIMO  = 2;

    /**
     * Listado general del tenant. Es la vista de seguimiento, no la bandeja
     * personal: para eso esta getMisNotificaciones en el servicio de
     * destinatarios.
     */
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT n.id, n.titulo, n.cuerpo, n.id_tipo_notificacion_colaborador,
                                         n.id_referencia, n.id_usuario_envio, n.fecha_envio, n.activo,
                                         t.nombre AS tipo_nombre,
                                         t.icono  AS tipo_icono,
                                         (SELECT COUNT(*) FROM notificaciones_colaboradores_destinatarios d
                                           WHERE d.id_notificacion_colaborador = n.id) AS total_destinatarios,
                                         (SELECT COUNT(*) FROM notificaciones_colaboradores_destinatarios d
                                           WHERE d.id_notificacion_colaborador = n.id AND d.fecha_lectura IS NOT NULL) AS total_leidas
                                  FROM notificaciones_colaboradores n
                                  INNER JOIN tipos_notificacion_colaborador t ON t.id = n.id_tipo_notificacion_colaborador
                                  WHERE n.id_tenant = :id_tenant AND n.activo = 1
                                  ORDER BY n.fecha_envio DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT n.id, n.titulo, n.cuerpo, n.id_tipo_notificacion_colaborador,
                                         n.id_referencia, n.id_usuario_envio, n.fecha_envio, n.activo,
                                         t.nombre AS tipo_nombre,
                                         t.icono  AS tipo_icono
                                  FROM notificaciones_colaboradores n
                                  INNER JOIN tipos_notificacion_colaborador t ON t.id = n.id_tipo_notificacion_colaborador
                                  WHERE n.id = :id AND n.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Crea la notificacion a mano desde el portal institucional. Los avisos
     * automaticos del modulo de solicitudes no pasan por aqui: los arma
     * MotorSolicitudesAvisos con crear().
     */
    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $titulo        = isset(Flight::request()->data['titulo']) ? trim(Flight::request()->data['titulo']) : '';
        $cuerpo        = isset(Flight::request()->data['cuerpo']) ? trim(Flight::request()->data['cuerpo']) : '';
        $idTipo        = isset(Flight::request()->data['id_tipo_notificacion_colaborador']) ? (int)Flight::request()->data['id_tipo_notificacion_colaborador'] : null;
        $idReferencia  = isset(Flight::request()->data['id_referencia']) && Flight::request()->data['id_referencia'] !== '' ? Flight::request()->data['id_referencia'] : null;
        $colaboradores = isset(Flight::request()->data['colaboradores']) && is_array(Flight::request()->data['colaboradores']) ? Flight::request()->data['colaboradores'] : array();

        if ($titulo === '' || $cuerpo === '' || empty($idTipo)) {
            Flight::json(array('error' => 'El titulo, el cuerpo y el tipo son obligatorios'), 400);
            return;
        }

        if (count($colaboradores) === 0) {
            Flight::json(array('error' => 'Debe indicar al menos un destinatario'), 400);
            return;
        }

        $destinatarios = array();
        foreach ($colaboradores as $idColaborador) {
            $destinatarios[] = array('id_colaborador' => $idColaborador, 'id_usuario' => null);
        }

        $id = self::crear($db, $idTipo, $titulo, $cuerpo, $idReferencia, $destinatarios, isset($userData->id) ? $userData->id : null);

        if ($id === null) {
            Flight::json(array('error' => 'No se pudo crear la notificacion'), 500);
            return;
        }

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id     = Flight::request()->data['id'];
        $titulo = isset(Flight::request()->data['titulo']) ? trim(Flight::request()->data['titulo']) : '';
        $cuerpo = isset(Flight::request()->data['cuerpo']) ? trim(Flight::request()->data['cuerpo']) : '';

        if ($titulo === '' || $cuerpo === '') {
            Flight::json(array('error' => 'El titulo y el cuerpo son obligatorios'), 400);
            return;
        }

        // No se recalculan destinatarios: las lecturas ya registradas se
        // refieren a este aviso y volver a resolver la lista las invalidaria.
        $sentence = $db->prepare("UPDATE notificaciones_colaboradores
                                  SET titulo = :titulo, cuerpo = :cuerpo
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':titulo', $titulo);
        $sentence->bindValue(':cuerpo', $cuerpo);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    /**
     * Baja logica. No se borra fisicamente porque las ocurrencias apuntan a
     * la notificacion para saber que ya se aviso.
     */
    public static function delete()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("UPDATE notificaciones_colaboradores SET activo = 0
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Crea la notificacion, sus destinatarios y dispara el push al portal
     * institucional. Es el punto por el que entran los avisos automaticos.
     *
     * El push nunca tumba la creacion: si falla, el aviso queda igual en la
     * bandeja.
     *
     * @param  PDO    $db
     * @param  int    $idTipo        Constante TIPO_* de esta clase
     * @param  string $titulo
     * @param  string $cuerpo
     * @param  string $idReferencia  Registro que la origino (solicitud u ocurrencia)
     * @param  array  $destinatarios Filas con id_colaborador e id_usuario
     * @param  string $idUsuarioEnvio NULL cuando la genera el sistema
     * @return string|null Id de la notificacion creada, o null si fallo
     */
    public static function crear(PDO $db, $idTipo, $titulo, $cuerpo, $idReferencia, array $destinatarios, $idUsuarioEnvio = null)
    {
        if (count($destinatarios) === 0) {
            return null;
        }

        try {
            $id = Uuid::generar();

            $sentence = $db->prepare("INSERT INTO notificaciones_colaboradores
                (id, id_tenant, titulo, cuerpo, id_tipo_notificacion_colaborador,
                 id_referencia, id_usuario_envio)
                VALUES
                (:id, :id_tenant, :titulo, :cuerpo, :id_tipo, :id_referencia, :id_usuario_envio)");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':titulo', $titulo);
            $sentence->bindValue(':cuerpo', $cuerpo);
            $sentence->bindValue(':id_tipo', $idTipo, PDO::PARAM_INT);
            $sentence->bindValue(':id_referencia', $idReferencia, $idReferencia === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $sentence->bindValue(':id_usuario_envio', $idUsuarioEnvio);
            $sentence->execute();

            $idsUsuarios = NotificacionesColaboradoresDestinatarios::insertarLote($db, $id, $destinatarios);

            if (count($idsUsuarios) > 0) {
                $pushService = new PushNotificationService($db);
                $pushService->notificarAUsuarios(
                    $idsUsuarios,
                    $titulo,
                    self::generarPreview($cuerpo),
                    array(
                        'id_notificacion' => $id,
                        'tipo'            => 'notificacion_colaborador'
                    ),
                    JWTService::PORTAL_INSTITUCIONAL
                );
            }

            return $id;
        } catch (Exception $e) {
            error_log('[NotificacionesColaboradores::crear] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Recorta el cuerpo para el texto que viaja en el push: el payload tiene
     * un limite de unos 4 KB y el sistema operativo trunca el aviso de todas
     * formas.
     */
    private static function generarPreview($cuerpo)
    {
        $texto = trim(preg_replace('/\s+/u', ' ', strip_tags($cuerpo)));

        if (mb_strlen($texto, 'UTF-8') > 120) {
            $texto = mb_substr($texto, 0, 117, 'UTF-8') . '...';
        }

        return $texto;
    }
}
