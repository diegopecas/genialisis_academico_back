<?php
class Notificaciones
{
    /**
     * Listado para el portal institucional, con el resumen de acuses para
     * que el jardin vea de un vistazo cuantos leyeron y cuantos respondieron.
     */
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT
                n.id,
                n.titulo,
                n.cuerpo,
                n.id_categoria,
                c.nombre  AS categoria_nombre,
                c.icono   AS categoria_icono,
                c.color   AS categoria_color,
                n.id_respuesta_tipo,
                rt.nombre AS respuesta_tipo_nombre,
                n.criterio_texto,
                n.incluir_whatsapp,
                n.whatsapp_numero,
                n.enviar_correo,
                n.fecha_envio,
                n.id_usuario_envio,
                u.usuario AS usuario_envio,
                n.activo,
                (SELECT COUNT(*) FROM notificaciones_destinatarios d WHERE d.id_notificacion = n.id) AS total_destinatarios,
                (SELECT COUNT(*) FROM notificaciones_destinatarios d WHERE d.id_notificacion = n.id AND d.fecha_lectura IS NOT NULL) AS total_leidas,
                (SELECT COUNT(*) FROM notificaciones_destinatarios d WHERE d.id_notificacion = n.id AND d.id_respuesta_opcion IS NOT NULL) AS total_respondidas,
                (SELECT COUNT(*) FROM notificaciones_adjuntos a WHERE a.id_notificacion = n.id AND a.activo = 1) AS total_adjuntos
            FROM notificaciones n
            INNER JOIN notificaciones_categorias c ON c.id = n.id_categoria
            LEFT JOIN notificaciones_respuestas_tipos rt ON rt.id = n.id_respuesta_tipo
            LEFT JOIN usuarios u ON u.id = n.id_usuario_envio
            WHERE n.id_tenant = :id_tenant
            ORDER BY n.fecha_envio DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Detalle de una notificacion con sus adjuntos y las opciones de
     * respuesta disponibles.
     */
    public static function getById($id)
    {
        $db = Flight::db();

        $sentence = $db->prepare("
            SELECT
                n.*,
                c.nombre  AS categoria_nombre,
                c.icono   AS categoria_icono,
                c.color   AS categoria_color,
                rt.nombre AS respuesta_tipo_nombre,
                rt.codigo AS respuesta_tipo_codigo,
                u.usuario AS usuario_envio
            FROM notificaciones n
            INNER JOIN notificaciones_categorias c ON c.id = n.id_categoria
            LEFT JOIN notificaciones_respuestas_tipos rt ON rt.id = n.id_respuesta_tipo
            LEFT JOIN usuarios u ON u.id = n.id_usuario_envio
            WHERE n.id = :id AND n.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $notificacion = $sentence->fetch();

        if (!$notificacion) {
            Flight::json(array('error' => 'Notificación no encontrada'), 404);
            return;
        }

        $adjuntos = $db->prepare("SELECT id, nombre_archivo, tamanio_bytes, fecha_subida FROM notificaciones_adjuntos WHERE id_notificacion = :id AND activo = 1 ORDER BY fecha_subida");
        $adjuntos->bindParam(':id', $id);
        $adjuntos->execute();
        $notificacion['adjuntos'] = $adjuntos->fetchAll();

        $notificacion['opciones'] = array();
        if (!empty($notificacion['id_respuesta_tipo'])) {
            $opciones = $db->prepare("SELECT id, codigo, etiqueta, orden FROM notificaciones_respuestas_opciones WHERE id_respuesta_tipo = :id_respuesta_tipo AND activo = 1 ORDER BY orden, etiqueta");
            $opciones->bindValue(':id_respuesta_tipo', $notificacion['id_respuesta_tipo']);
            $opciones->execute();
            $notificacion['opciones'] = $opciones->fetchAll();
        }

        Flight::json($notificacion);
    }

    /**
     * Crea la notificacion, materializa los destinatarios y dispara el push.
     *
     * Recibe una lista de id_estudiante ya resuelta por el front: asi el
     * criterio de seleccion (grupo, extracurricular, todos, seleccion manual)
     * puede crecer sin tocar esta tabla ni este metodo. El texto descriptivo
     * del criterio llega aparte en criterio_texto.
     *
     * Opcionalmente puede recibir 'destinatarios', una lista de pares
     * id_estudiante + id_persona, para excluir acudientes puntuales de los
     * estudiantes elegidos. Si no llega, se notifica a todos los acudientes
     * habilitados de esos estudiantes.
     *
     * El envio del push nunca tumba la creacion: si falla, la notificacion
     * queda igual disponible en el portal de acudientes.
     */
    public static function new()
    {
        $db = Flight::db();

        try {
            $userData = JWTService::requerirAutenticacion();
            $idUsuarioEnvio = $userData->id ?? null;

            if (!$idUsuarioEnvio) {
                Flight::json(array('error' => 'No se pudo identificar el usuario que envía'), 401);
                return;
            }

            $titulo          = Flight::request()->data['titulo'] ?? null;
            $cuerpo          = Flight::request()->data['cuerpo'] ?? null;
            $idCategoria     = Flight::request()->data['id_categoria'] ?? null;
            $idRespuestaTipo = Flight::request()->data['id_respuesta_tipo'] ?? null;
            $criterioTexto   = Flight::request()->data['criterio_texto'] ?? null;
            $incluirWhatsapp = Flight::request()->data['incluir_whatsapp'] ?? 1;
            $whatsappNumero  = Flight::request()->data['whatsapp_numero'] ?? null;
            $enviarCorreo    = Flight::request()->data['enviar_correo'] ?? 0;
            $estudiantes     = Flight::request()->data['estudiantes'] ?? array();
            $seleccionados   = Flight::request()->data['destinatarios'] ?? array();
            $idPlantilla     = Flight::request()->data['id_plantilla'] ?? null;

            if (!$titulo || !$cuerpo || !$idCategoria) {
                Flight::json(array('error' => 'El título, el cuerpo y la categoría son obligatorios'), 400);
                return;
            }

            if (!is_array($estudiantes) || count($estudiantes) === 0) {
                Flight::json(array('error' => 'Debe seleccionar al menos un estudiante destinatario'), 400);
                return;
            }

            if (empty($idRespuestaTipo)) {
                $idRespuestaTipo = null;
            }

            $db->beginTransaction();

            $idNotificacion = Uuid::generar();
            $insertar = $db->prepare("
                INSERT INTO notificaciones
                    (id, id_tenant, titulo, cuerpo, id_categoria, id_respuesta_tipo, id_plantilla, criterio_texto,
                     incluir_whatsapp, whatsapp_numero, enviar_correo, id_usuario_envio)
                VALUES
                    (:id, :id_tenant, :titulo, :cuerpo, :id_categoria, :id_respuesta_tipo, :id_plantilla, :criterio_texto,
                     :incluir_whatsapp, :whatsapp_numero, :enviar_correo, :id_usuario_envio)
            ");
            $insertar->bindValue(':id', $idNotificacion);
            $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $insertar->bindParam(':titulo', $titulo);
            $insertar->bindParam(':cuerpo', $cuerpo);
            $insertar->bindParam(':id_categoria', $idCategoria);
            $insertar->bindValue(':id_respuesta_tipo', $idRespuestaTipo);
            $insertar->bindValue(':id_plantilla', empty($idPlantilla) ? null : $idPlantilla);
            $insertar->bindParam(':criterio_texto', $criterioTexto);
            $insertar->bindValue(':incluir_whatsapp', $incluirWhatsapp ? 1 : 0, PDO::PARAM_INT);
            $insertar->bindParam(':whatsapp_numero', $whatsappNumero);
            $insertar->bindValue(':enviar_correo', $enviarCorreo ? 1 : 0, PDO::PARAM_INT);
            $insertar->bindParam(':id_usuario_envio', $idUsuarioEnvio);
            $insertar->execute();

            // Siempre se resuelve contra la base, aunque el front mande la
            // lista ya escogida: la seleccion del cliente solo puede recortar
            // ese conjunto, nunca ampliarlo. Sin esto, un cliente manipulado
            // podria notificar a personas que no son acudientes del estudiante.
            $destinatarios = self::resolverDestinatarios($db, $estudiantes);

            if (count($destinatarios) === 0) {
                $db->rollBack();
                Flight::json(array('error' => 'Los estudiantes seleccionados no tienen acudientes habilitados en el portal de padres'), 400);
                return;
            }

            if (is_array($seleccionados) && count($seleccionados) > 0) {
                $permitidos = array();
                foreach ($seleccionados as $seleccionado) {
                    $idEstudiante = $seleccionado['id_estudiante'] ?? null;
                    $idPersona    = $seleccionado['id_persona'] ?? null;
                    if ($idEstudiante && $idPersona) {
                        $permitidos[$idEstudiante . '|' . $idPersona] = true;
                    }
                }

                $destinatarios = array_values(array_filter($destinatarios, function ($destinatario) use ($permitidos) {
                    return isset($permitidos[$destinatario['id_estudiante'] . '|' . $destinatario['id_persona']]);
                }));

                if (count($destinatarios) === 0) {
                    $db->rollBack();
                    Flight::json(array('error' => 'Ninguno de los acudientes seleccionados es válido para los estudiantes indicados'), 400);
                    return;
                }
            }

            $insertarDestinatario = $db->prepare("
                INSERT INTO notificaciones_destinatarios
                    (id, id_tenant, id_notificacion, id_estudiante, id_persona, id_usuario)
                VALUES
                    (:id, :id_tenant, :id_notificacion, :id_estudiante, :id_persona, :id_usuario)
            ");

            $idsUsuarios = array();

            foreach ($destinatarios as $destinatario) {
                $insertarDestinatario->bindValue(':id', Uuid::generar());
                $insertarDestinatario->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $insertarDestinatario->bindValue(':id_notificacion', $idNotificacion);
                $insertarDestinatario->bindValue(':id_estudiante', $destinatario['id_estudiante']);
                $insertarDestinatario->bindValue(':id_persona', $destinatario['id_persona']);
                $insertarDestinatario->bindValue(':id_usuario', $destinatario['id_usuario']);
                $insertarDestinatario->execute();

                if (!empty($destinatario['id_usuario'])) {
                    $idsUsuarios[$destinatario['id_usuario']] = true;
                }
            }

            $db->commit();

            NotificacionesPlantillas::registrarUso($db, $idPlantilla);

            $resultadoPush = array('enviadas' => 0, 'sin_suscripcion' => 0);

            if (count($idsUsuarios) > 0) {
                // El aviso del push lleva el texto sin resolver: el payload es
                // uno solo para todos los destinatarios, y personalizarlo
                // obligaria a un envio por familia. Las variables se ven
                // resueltas al abrir la notificacion en el portal.
                $pushService = new PushNotificationService($db);
                $resultadoPush = $pushService->notificarAUsuarios(
                    array_keys($idsUsuarios),
                    self::limpiarVariables($titulo),
                    self::generarPreview(self::limpiarVariables($cuerpo)),
                    array(
                        'id_notificacion' => $idNotificacion,
                        'tipo'            => 'notificacion',
                    ),
                    JWTService::PORTAL_PADRES
                );
            }

            Flight::json(array(
                'id'                  => $idNotificacion,
                'total_destinatarios' => count($destinatarios),
                'push'                => $resultadoPush,
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en Notificaciones::new: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear la notificación'), 500);
        }
    }

    /**
     * Permite corregir el contenido de una notificacion ya enviada. No
     * recalcula destinatarios: los acuses y respuestas ya registrados se
     * refieren a este mensaje y volver a resolver la lista los invalidaria.
     */
    public static function replace()
    {
        try {
            $db = Flight::db();
            $id              = Flight::request()->data['id'] ?? null;
            $titulo          = Flight::request()->data['titulo'] ?? null;
            $cuerpo          = Flight::request()->data['cuerpo'] ?? null;
            $idCategoria     = Flight::request()->data['id_categoria'] ?? null;
            $criterioTexto   = Flight::request()->data['criterio_texto'] ?? null;
            $incluirWhatsapp = Flight::request()->data['incluir_whatsapp'] ?? 1;
            $whatsappNumero  = Flight::request()->data['whatsapp_numero'] ?? null;
            $activo          = Flight::request()->data['activo'] ?? 1;

            if (!$id || !$titulo || !$cuerpo || !$idCategoria) {
                Flight::json(array('error' => 'ID, título, cuerpo y categoría son obligatorios'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE notificaciones
                SET titulo = :titulo,
                    cuerpo = :cuerpo,
                    id_categoria = :id_categoria,
                    criterio_texto = :criterio_texto,
                    incluir_whatsapp = :incluir_whatsapp,
                    whatsapp_numero = :whatsapp_numero,
                    activo = :activo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':titulo', $titulo);
            $sentence->bindParam(':cuerpo', $cuerpo);
            $sentence->bindParam(':id_categoria', $idCategoria);
            $sentence->bindParam(':criterio_texto', $criterioTexto);
            $sentence->bindValue(':incluir_whatsapp', $incluirWhatsapp ? 1 : 0, PDO::PARAM_INT);
            $sentence->bindParam(':whatsapp_numero', $whatsappNumero);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en Notificaciones::replace: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar la notificación'), 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();

        try {
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(array('error' => 'ID es obligatorio'), 400);
                return;
            }

            $db->beginTransaction();

            $borrarDestinatarios = $db->prepare("DELETE FROM notificaciones_destinatarios WHERE id_notificacion = :id AND id_tenant = :id_tenant");
            $borrarDestinatarios->bindParam(':id', $id);
            $borrarDestinatarios->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrarDestinatarios->execute();

            // Los archivos fisicos se borran desde el servicio de adjuntos
            // antes de llegar aqui; esto limpia solo los registros huerfanos.
            $borrarAdjuntos = $db->prepare("DELETE FROM notificaciones_adjuntos WHERE id_notificacion = :id AND id_tenant = :id_tenant");
            $borrarAdjuntos->bindParam(':id', $id);
            $borrarAdjuntos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrarAdjuntos->execute();

            $sentence = $db->prepare("DELETE FROM notificaciones WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $db->commit();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en Notificaciones::delete: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar la notificación'), 500);
        }
    }

    /**
     * Vista previa de a quienes llegaria la notificacion, sin crearla.
     * Le sirve al formulario para mostrar el conteo antes de enviar.
     */
    public static function previsualizarDestinatarios()
    {
        try {
            $db = Flight::db();
            $estudiantes = Flight::request()->data['estudiantes'] ?? array();

            if (!is_array($estudiantes) || count($estudiantes) === 0) {
                Flight::json(array('total_destinatarios' => 0, 'total_estudiantes' => 0, 'destinatarios' => array()));
                return;
            }

            $destinatarios = self::resolverDestinatarios($db, $estudiantes);

            $estudiantesAlcanzados = array();
            foreach ($destinatarios as $destinatario) {
                $estudiantesAlcanzados[$destinatario['id_estudiante']] = true;
            }

            Flight::json(array(
                'total_destinatarios' => count($destinatarios),
                'total_estudiantes'   => count($estudiantesAlcanzados),
                'destinatarios'       => $destinatarios,
            ));
        } catch (Exception $e) {
            error_log("Error en Notificaciones::previsualizarDestinatarios: " . $e->getMessage());
            Flight::json(array('error' => 'Error al previsualizar los destinatarios'), 500);
        }
    }

    /**
     * Traduce una lista de estudiantes a los acudientes que deben recibir la
     * notificacion.
     *
     * Solo entran los vinculos activos con ve_en_portal_padres = 1: esa
     * bandera es la que decide si ese acudiente ve a ese estudiante en el
     * portal, y notificar por fuera de ella seria mostrarle datos de un
     * estudiante que no le corresponde.
     *
     * El usuario puede venir nulo si la persona aun no tiene credenciales:
     * el destinatario se registra igual y quedara visible cuando se le cree
     * el usuario.
     *
     * @param  PDO   $db
     * @param  array $estudiantes Lista de id_estudiante
     * @return array Filas con id_estudiante, id_persona, id_usuario y nombres
     */
    private static function resolverDestinatarios(PDO $db, array $estudiantes)
    {
        $estudiantes = array_values(array_unique(array_filter($estudiantes)));

        if (count($estudiantes) === 0) {
            return array();
        }

        $marcadores = implode(',', array_fill(0, count($estudiantes), '?'));

        $sql = "
            SELECT DISTINCT
                a.id_estudiante,
                a.id_persona,
                u.id AS id_usuario,
                a.id_tipo_acudiente,
                ta.nombre           AS tipo_acudiente_nombre,
                ta.icono            AS tipo_acudiente_icono,
                pe.primer_nombre    AS acudiente_primer_nombre,
                pe.segundo_nombre   AS acudiente_segundo_nombre,
                pe.primer_apellido  AS acudiente_primer_apellido,
                pe.segundo_apellido AS acudiente_segundo_apellido,
                pes.primer_nombre   AS estudiante_primer_nombre,
                pes.segundo_nombre  AS estudiante_segundo_nombre,
                pes.primer_apellido AS estudiante_primer_apellido,
                pes.segundo_apellido AS estudiante_segundo_apellido
            FROM acudientes a
            INNER JOIN estudiantes e ON e.id = a.id_estudiante
            INNER JOIN personas pe   ON pe.id = a.id_persona
            INNER JOIN personas pes  ON pes.id = e.id_persona
            LEFT JOIN tipos_acudiente ta ON ta.id = a.id_tipo_acudiente
            LEFT JOIN usuarios u
                   ON u.id_persona = a.id_persona
                  AND u.id_tenant = a.id_tenant
                  AND u.activo = 1
                  AND u.acceso_portal_padres = 1
            WHERE a.id_tenant = ?
              AND a.activo = 1
              AND a.ve_en_portal_padres = 1
              AND a.id_estudiante IN ($marcadores)
            ORDER BY pes.primer_apellido, pes.primer_nombre, ta.nombre
        ";

        $parametros = array_merge(array(TenantContext::id()), $estudiantes);

        $sentence = $db->prepare($sql);
        $sentence->execute($parametros);

        return $sentence->fetchAll();
    }

    /**
     * Recorta el cuerpo para el texto que viaja en el push. El payload web
     * push tiene un limite de unos 4 KB y ademas el sistema operativo trunca
     * el aviso, asi que no tiene sentido mandar el mensaje completo.
     */
    private static function generarPreview($cuerpo)
    {
        $texto = trim(strip_tags($cuerpo));
        $texto = preg_replace('/\s+/u', ' ', $texto);

        if (mb_strlen($texto, 'UTF-8') > 120) {
            $texto = mb_substr($texto, 0, 117, 'UTF-8') . '...';
        }

        return $texto;
    }

    /**
     * Quita los marcadores de variable del texto que viaja en el push.
     *
     * En el aviso del sistema operativo no se pueden resolver (es un unico
     * payload para todas las familias), y mostrar "{nombre_estudiante}" en
     * crudo se ve peor que no mostrar nada.
     */
    private static function limpiarVariables($texto)
    {
        $sinMarcadores = preg_replace('/\s*\{[a-z0-9_]+\}/i', '', (string)$texto);
        return trim(preg_replace('/\s{2,}/', ' ', $sinMarcadores));
    }
}