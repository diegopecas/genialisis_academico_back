<?php
/*=============================================
SERVICIO - MOTOR DE AVISOS DE SOLICITUDES
Archivo: services/motor-solicitudes-avisos.service.php

Arma los tres avisos del modulo:

  1. Al aprobador, cuando entra una solicitud pendiente (institucional).
  2. Al responsable, X minutos antes de cada ocurrencia (institucional).
     Lo dispara el cron; la ocurrencia guarda la notificacion que se creo
     para que el barrido de los 5 minutos no repita el mismo aviso.
  3. Al acudiente, cuando se marca cumplida y cuando se rechaza la
     solicitud (portal de padres).

Los del jardin salen por notificaciones_colaboradores; los del papa por la
central de notificaciones que ya existe, calcando
notificaciones-asistencia.service.php.

No expone rutas. Nada de lo que pasa aqui puede tumbar la operacion que lo
llamo: todos los errores se atrapan y se dejan en el log.
=============================================*/

class MotorSolicitudesAvisos
{
    /** Categoria bajo la que viajan al portal de padres estos avisos. */
    const CODIGO_CATEGORIA = 'GENERAL';

    /**
     * Avisa a los aprobadores que hay una solicitud esperando.
     *
     * @param  PDO    $db
     * @param  string $idSolicitud
     * @return bool   true si se creo el aviso
     */
    public static function avisarPorAprobar(PDO $db, $idSolicitud)
    {
        try {
            $solicitud = self::obtenerSolicitud($db, $idSolicitud);

            if (!$solicitud) {
                return false;
            }

            $destinatarios = SolicitudesPersonas::listarPorRol($db, $idSolicitud, SolicitudesPersonas::ROL_APROBADOR);

            if (count($destinatarios) === 0) {
                error_log('[MotorSolicitudesAvisos] Solicitud ' . $idSolicitud . ' pendiente sin aprobadores configurados.');
                return false;
            }

            $titulo = 'Solicitud por aprobar: ' . $solicitud['tipo_nombre'];
            $cuerpo = $solicitud['estudiante_nombre'] . ' - ' . $solicitud['descripcion']
                    . self::textoVigencia($solicitud);

            $id = NotificacionesColaboradores::crear(
                $db,
                NotificacionesColaboradores::TIPO_POR_APROBAR,
                $titulo,
                $cuerpo,
                $idSolicitud,
                $destinatarios,
                null
            );

            return $id !== null;
        } catch (Exception $e) {
            error_log('[MotorSolicitudesAvisos::avisarPorAprobar] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Barrido de las ocurrencias que estan por vencerse. Lo llama el cron
     * cada 5 minutos.
     *
     * Entra una ocurrencia cuando: la solicitud esta autorizada, la
     * ocurrencia sigue pendiente, tiene hora, tiene minutos de anticipacion
     * configurados, ya se entro en la ventana de aviso y todavia no se le ha
     * creado la notificacion.
     *
     * Esa ultima condicion es la que evita que el aviso se repita cada 5
     * minutos: al crear la notificacion se guarda su id en la ocurrencia.
     *
     * @param  PDO    $db
     * @param  string $momento Fecha y hora de corte (AAAA-MM-DD HH:MM:SS)
     * @return array  Conteo de lo procesado
     */
    public static function avisarProximas(PDO $db, $momento = null)
    {
        $resultado = array('evaluadas' => 0, 'avisadas' => 0, 'sin_responsables' => 0);

        if ($momento === null) {
            $momento = date('Y-m-d H:i:s');
        }

        $fecha = substr($momento, 0, 10);

        try {
            $sentence = $db->prepare("SELECT o.id, o.fecha, o.hora_programada, o.id_solicitud,
                                             s.descripcion, s.id_estudiante,
                                             COALESCE(s.minutos_anticipacion, t.minutos_anticipacion) AS minutos,
                                             t.nombre AS tipo_nombre,
                                             TRIM(CONCAT(COALESCE(pes.primer_nombre, ''), ' ', COALESCE(pes.primer_apellido, ''))) AS estudiante_nombre
                                      FROM solicitudes_ocurrencias o
                                      INNER JOIN solicitudes s     ON s.id = o.id_solicitud
                                      INNER JOIN tipos_solicitud t ON t.id = s.id_tipo_solicitud
                                      INNER JOIN estudiantes est   ON est.id = s.id_estudiante
                                      INNER JOIN personas pes      ON pes.id = est.id_persona
                                      WHERE o.id_tenant = :id_tenant
                                        AND o.fecha = :fecha
                                        AND o.hora_programada IS NOT NULL
                                        AND o.id_estado = :pendiente
                                        AND o.id_notificacion_colaborador IS NULL
                                        AND s.id_estado = :autorizado
                                        AND COALESCE(s.minutos_anticipacion, t.minutos_anticipacion) IS NOT NULL
                                        AND TIMESTAMP(o.fecha, o.hora_programada) > :momento_ahora
                                        AND TIMESTAMP(o.fecha, o.hora_programada) <= DATE_ADD(:momento_ventana, INTERVAL COALESCE(s.minutos_anticipacion, t.minutos_anticipacion) MINUTE)
                                      ORDER BY o.hora_programada");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':fecha', $fecha);
            $sentence->bindValue(':pendiente', SolicitudesOcurrencias::ESTADO_PENDIENTE, PDO::PARAM_INT);
            $sentence->bindValue(':autorizado', Solicitudes::ESTADO_AUTORIZADO, PDO::PARAM_INT);
            $sentence->bindValue(':momento_ahora', $momento);
            $sentence->bindValue(':momento_ventana', $momento);
            $sentence->execute();

            $marcar = $db->prepare("UPDATE solicitudes_ocurrencias
                                    SET id_notificacion_colaborador = :id_notificacion
                                    WHERE id = :id AND id_tenant = :id_tenant");

            foreach ($sentence->fetchAll() as $ocurrencia) {
                $resultado['evaluadas']++;

                $destinatarios = SolicitudesPersonas::listarPorRol($db, $ocurrencia['id_solicitud'], SolicitudesPersonas::ROL_RESPONSABLE);

                if (count($destinatarios) === 0) {
                    // Sin responsables la solicitud la ven todos, pero no hay
                    // a quien avisarle en particular: sale en la agenda y ya.
                    $resultado['sin_responsables']++;
                    continue;
                }

                $titulo = $ocurrencia['tipo_nombre'] . ' a las ' . substr($ocurrencia['hora_programada'], 0, 5);
                $cuerpo = $ocurrencia['estudiante_nombre'] . ' - ' . $ocurrencia['descripcion'];

                $idNotificacion = NotificacionesColaboradores::crear(
                    $db,
                    NotificacionesColaboradores::TIPO_COMPROMISO_PROXIMO,
                    $titulo,
                    $cuerpo,
                    $ocurrencia['id'],
                    $destinatarios,
                    null
                );

                if ($idNotificacion === null) {
                    continue;
                }

                $marcar->bindValue(':id_notificacion', $idNotificacion);
                $marcar->bindValue(':id', $ocurrencia['id']);
                $marcar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $marcar->execute();

                $resultado['avisadas']++;
            }
        } catch (Exception $e) {
            error_log('[MotorSolicitudesAvisos::avisarProximas] ' . $e->getMessage());
        }

        return $resultado;
    }

    /**
     * Avisa al acudiente que la ocurrencia se cumplio, si el tipo lo pide.
     * Es lo que le cierra el ciclo al papa: no tiene que llamar a preguntar
     * si le dieron el remedio.
     *
     * @param  PDO    $db
     * @param  string $idOcurrencia
     * @param  string $idUsuario Quien marco la ocurrencia
     * @return bool
     */
    public static function avisarCumplida(PDO $db, $idOcurrencia, $idUsuario)
    {
        try {
            $ocurrencia = SolicitudesOcurrencias::obtener($db, $idOcurrencia);

            if (!$ocurrencia || (int)$ocurrencia['notifica_acudiente_cumplido'] !== 1) {
                return false;
            }

            $solicitud = self::obtenerSolicitud($db, $ocurrencia['id_solicitud']);

            if (!$solicitud) {
                return false;
            }

            $hora   = !empty($ocurrencia['hora_real']) ? substr($ocurrencia['hora_real'], 0, 5) : date('H:i');
            $titulo = $solicitud['tipo_nombre'] . ': cumplido';
            $cuerpo = $solicitud['estudiante_nombre'] . ' - ' . $solicitud['descripcion']
                    . '. Se registro a las ' . $hora . '.';

            $idNotificacion = self::notificarAcudiente($db, $solicitud, $titulo, $cuerpo, $idUsuario);

            if ($idNotificacion === null) {
                return false;
            }

            $sentence = $db->prepare("UPDATE solicitudes_ocurrencias
                                      SET id_notificacion = :id_notificacion
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id_notificacion', $idNotificacion);
            $sentence->bindValue(':id', $idOcurrencia);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            return true;
        } catch (Exception $e) {
            error_log('[MotorSolicitudesAvisos::avisarCumplida] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Avisa al acudiente que su solicitud fue rechazada, con el motivo. El
     * rechazo es terminal: para corregir tiene que crear una nueva, y por eso
     * el aviso es obligatorio.
     */
    public static function avisarRechazo(PDO $db, $idSolicitud, $motivo, $idUsuario)
    {
        try {
            $solicitud = self::obtenerSolicitud($db, $idSolicitud);

            if (!$solicitud) {
                return false;
            }

            $titulo = 'Solicitud rechazada: ' . $solicitud['tipo_nombre'];
            $cuerpo = $solicitud['estudiante_nombre'] . ' - ' . $solicitud['descripcion']
                    . '. Motivo: ' . $motivo
                    . '. Si necesita corregirla, cree una nueva solicitud.';

            return self::notificarAcudiente($db, $solicitud, $titulo, $cuerpo, $idUsuario) !== null;
        } catch (Exception $e) {
            error_log('[MotorSolicitudesAvisos::avisarRechazo] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Crea la notificacion en la central del portal de padres y dispara el
     * push. Solo le llega al acudiente de parte de quien va la solicitud, y
     * unicamente si su vinculo tiene ve_en_portal_padres = 1.
     *
     * @return string|null Id de la notificacion creada
     */
    private static function notificarAcudiente(PDO $db, $solicitud, $titulo, $cuerpo, $idUsuarioEnvio)
    {
        $categoria = self::obtenerCategoria($db);

        if (!$categoria) {
            error_log('[MotorSolicitudesAvisos] Sin categoria ' . self::CODIGO_CATEGORIA . ' en el tenant ' . TenantContext::id());
            return null;
        }

        $sentence = $db->prepare("SELECT a.id_estudiante, a.id_persona,
                                         (SELECT u.id FROM usuarios u
                                           WHERE u.id_persona = a.id_persona
                                             AND u.id_tenant = a.id_tenant
                                             AND u.activo = 1
                                             AND u.acceso_portal_padres = 1
                                           LIMIT 1) AS id_usuario
                                  FROM acudientes a
                                  WHERE a.id_estudiante = :id_estudiante
                                    AND a.id_persona = :id_persona
                                    AND a.id_tenant = :id_tenant
                                    AND a.activo = 1
                                    AND a.ve_en_portal_padres = 1
                                  LIMIT 1");
        $sentence->bindValue(':id_estudiante', $solicitud['id_estudiante']);
        $sentence->bindValue(':id_persona', $solicitud['id_persona_solicita']);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $destinatario = $sentence->fetch();

        if (!$destinatario) {
            return null;
        }

        $idNotificacion = Uuid::generar();

        // incluir_whatsapp en 0: son avisos automaticos y de alto volumen,
        // invitar a responder por WhatsApp en cada dosis le caeria encima al
        // jardin. Mismo criterio que las notificaciones de asistencia.
        $insertar = $db->prepare("INSERT INTO notificaciones
            (id, id_tenant, titulo, cuerpo, id_categoria, id_respuesta_tipo, id_plantilla,
             criterio_texto, incluir_whatsapp, whatsapp_numero, enviar_correo, id_usuario_envio)
            VALUES
            (:id, :id_tenant, :titulo, :cuerpo, :id_categoria, NULL, NULL,
             :criterio_texto, 0, NULL, 0, :id_usuario_envio)");
        $insertar->bindValue(':id', $idNotificacion);
        $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $insertar->bindValue(':titulo', $titulo);
        $insertar->bindValue(':cuerpo', $cuerpo);
        $insertar->bindValue(':id_categoria', $categoria);
        $insertar->bindValue(':criterio_texto', 'Solicitudes de los padres');
        $insertar->bindValue(':id_usuario_envio', $idUsuarioEnvio);
        $insertar->execute();

        $insertarDestinatario = $db->prepare("INSERT INTO notificaciones_destinatarios
            (id, id_tenant, id_notificacion, id_estudiante, id_persona, id_usuario)
            VALUES (:id, :id_tenant, :id_notificacion, :id_estudiante, :id_persona, :id_usuario)");
        $insertarDestinatario->bindValue(':id', Uuid::generar());
        $insertarDestinatario->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $insertarDestinatario->bindValue(':id_notificacion', $idNotificacion);
        $insertarDestinatario->bindValue(':id_estudiante', $destinatario['id_estudiante']);
        $insertarDestinatario->bindValue(':id_persona', $destinatario['id_persona']);
        $insertarDestinatario->bindValue(':id_usuario', $destinatario['id_usuario']);
        $insertarDestinatario->execute();

        if (!empty($destinatario['id_usuario'])) {
            $pushService = new PushNotificationService($db);
            $pushService->notificarAUsuarios(
                array($destinatario['id_usuario']),
                $titulo,
                self::generarPreview($cuerpo),
                array(
                    'id_notificacion' => $idNotificacion,
                    'tipo'            => 'notificacion'
                ),
                JWTService::PORTAL_PADRES
            );
        }

        return $idNotificacion;
    }

    /**
     * Datos de la solicitud que necesitan los textos de los avisos.
     */
    private static function obtenerSolicitud(PDO $db, $idSolicitud)
    {
        $sentence = $db->prepare("SELECT s.id, s.descripcion, s.id_estudiante, s.id_persona_solicita,
                                         s.fecha_inicio, s.fecha_fin,
                                         t.nombre AS tipo_nombre,
                                         TRIM(CONCAT(COALESCE(pes.primer_nombre, ''), ' ', COALESCE(pes.primer_apellido, ''))) AS estudiante_nombre
                                  FROM solicitudes s
                                  INNER JOIN tipos_solicitud t ON t.id = s.id_tipo_solicitud
                                  INNER JOIN estudiantes est   ON est.id = s.id_estudiante
                                  INNER JOIN personas pes      ON pes.id = est.id_persona
                                  WHERE s.id = :id AND s.id_tenant = :id_tenant");
        $sentence->bindValue(':id', $idSolicitud);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila : null;
    }

    private static function obtenerCategoria(PDO $db)
    {
        $sentence = $db->prepare("SELECT id FROM notificaciones_categorias
                                  WHERE id_tenant = :id_tenant AND codigo = :codigo AND activo = 1
                                  LIMIT 1");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':codigo', self::CODIGO_CATEGORIA);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila['id'] : null;
    }

    /**
     * Texto de vigencia para el cuerpo del aviso: un solo dia o un rango.
     */
    private static function textoVigencia($solicitud)
    {
        if (empty($solicitud['fecha_inicio'])) {
            return '';
        }

        if ($solicitud['fecha_inicio'] === $solicitud['fecha_fin']) {
            return ' (' . $solicitud['fecha_inicio'] . ')';
        }

        return ' (del ' . $solicitud['fecha_inicio'] . ' al ' . $solicitud['fecha_fin'] . ')';
    }

    private static function generarPreview($cuerpo)
    {
        $texto = trim(preg_replace('/\s+/u', ' ', strip_tags($cuerpo)));

        if (mb_strlen($texto, 'UTF-8') > 120) {
            $texto = mb_substr($texto, 0, 117, 'UTF-8') . '...';
        }

        return $texto;
    }
}
