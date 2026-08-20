<?php
class NotificacionesDestinatarios
{
    /**
     * Detalle de a quienes se envio una notificacion, con su estado de
     * lectura y respuesta. Es la pantalla de seguimiento del jardin: aqui
     * ve a quien llamar por telefono.
     */
    public static function getByNotificacion($id_notificacion)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT
                d.id,
                d.id_estudiante,
                d.id_persona,
                d.id_usuario,
                d.fecha_lectura,
                d.id_respuesta_opcion,
                d.fecha_respuesta,
                o.etiqueta          AS respuesta_etiqueta,
                o.codigo            AS respuesta_codigo,
                pe.primer_nombre    AS acudiente_primer_nombre,
                pe.segundo_nombre   AS acudiente_segundo_nombre,
                pe.primer_apellido  AS acudiente_primer_apellido,
                pe.segundo_apellido AS acudiente_segundo_apellido,
                pes.primer_nombre   AS estudiante_primer_nombre,
                pes.primer_apellido AS estudiante_primer_apellido
            FROM notificaciones_destinatarios d
            INNER JOIN personas pe    ON pe.id = d.id_persona
            INNER JOIN estudiantes e  ON e.id = d.id_estudiante
            INNER JOIN personas pes   ON pes.id = e.id_persona
            LEFT JOIN notificaciones_respuestas_opciones o ON o.id = d.id_respuesta_opcion
            WHERE d.id_notificacion = :id_notificacion
              AND d.id_tenant = :id_tenant
            ORDER BY pes.primer_apellido, pes.primer_nombre, pe.primer_apellido
        ");
        $sentence->bindParam(':id_notificacion', $id_notificacion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Bandeja del acudiente. Devuelve una fila por notificacion-estudiante,
     * porque un acudiente con dos hijos puede recibir la misma circular por
     * cada uno y necesita saber por cual le llega.
     */
    public static function getMisNotificaciones()
    {
        try {
            $db = Flight::db();
            $userData = JWTService::requerirAutenticacion();
            $idUsuario = $userData->id ?? null;

            if (!$idUsuario) {
                Flight::json(array('error' => 'No se pudo identificar el usuario'), 401);
                return;
            }

            $sentence = $db->prepare("
                SELECT
                    d.id                AS id_destinatario,
                    d.id_estudiante,
                    d.fecha_lectura,
                    d.id_respuesta_opcion,
                    d.fecha_respuesta,
                    n.id                AS id_notificacion,
                    n.titulo,
                    n.cuerpo,
                    n.criterio_texto,
                    n.incluir_whatsapp,
                    n.whatsapp_numero,
                    n.fecha_envio,
                    n.id_respuesta_tipo,
                    c.nombre            AS categoria_nombre,
                    c.icono             AS categoria_icono,
                    c.color             AS categoria_color,
                    pes.primer_nombre   AS estudiante_primer_nombre,
                    pes.primer_apellido AS estudiante_primer_apellido,
                    (SELECT COUNT(*) FROM notificaciones_adjuntos a WHERE a.id_notificacion = n.id AND a.activo = 1) AS total_adjuntos
                FROM notificaciones_destinatarios d
                INNER JOIN notificaciones n            ON n.id = d.id_notificacion
                INNER JOIN notificaciones_categorias c ON c.id = n.id_categoria
                INNER JOIN estudiantes e               ON e.id = d.id_estudiante
                INNER JOIN personas pes                ON pes.id = e.id_persona
                WHERE d.id_usuario = :id_usuario
                  AND d.id_tenant = :id_tenant
                  AND n.activo = 1
                ORDER BY n.fecha_envio DESC
            ");
            $sentence->bindParam(':id_usuario', $idUsuario);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en NotificacionesDestinatarios::getMisNotificaciones: " . $e->getMessage());
            Flight::json(array('error' => 'Error al consultar las notificaciones'), 500);
        }
    }

    /**
     * Contador para la campanita del portal de acudientes.
     */
    public static function getNoLeidas()
    {
        try {
            $db = Flight::db();
            $userData = JWTService::requerirAutenticacion();
            $idUsuario = $userData->id ?? null;

            if (!$idUsuario) {
                Flight::json(array('error' => 'No se pudo identificar el usuario'), 401);
                return;
            }

            $sentence = $db->prepare("
                SELECT COUNT(*) AS no_leidas
                FROM notificaciones_destinatarios d
                INNER JOIN notificaciones n ON n.id = d.id_notificacion
                WHERE d.id_usuario = :id_usuario
                  AND d.id_tenant = :id_tenant
                  AND d.fecha_lectura IS NULL
                  AND n.activo = 1
            ");
            $sentence->bindParam(':id_usuario', $idUsuario);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetch();

            Flight::json(array('no_leidas' => (int)($response['no_leidas'] ?? 0)));
        } catch (Exception $e) {
            error_log("Error en NotificacionesDestinatarios::getNoLeidas: " . $e->getMessage());
            Flight::json(array('error' => 'Error al consultar las notificaciones sin leer'), 500);
        }
    }

    /**
     * Marca como leida. Solo la primera vez: la fecha original del acuse no
     * se sobreescribe si el acudiente vuelve a abrir el mensaje.
     */
    public static function marcarLeida()
    {
        try {
            $db = Flight::db();
            $userData = JWTService::requerirAutenticacion();
            $idUsuario = $userData->id ?? null;
            $idDestinatario = Flight::request()->data['id_destinatario'] ?? null;

            if (!$idUsuario) {
                Flight::json(array('error' => 'No se pudo identificar el usuario'), 401);
                return;
            }

            if (!$idDestinatario) {
                Flight::json(array('error' => 'id_destinatario es obligatorio'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE notificaciones_destinatarios
                SET fecha_lectura = NOW()
                WHERE id = :id
                  AND id_usuario = :id_usuario
                  AND id_tenant = :id_tenant
                  AND fecha_lectura IS NULL
            ");
            $sentence->bindParam(':id', $idDestinatario);
            $sentence->bindParam(':id_usuario', $idUsuario);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $idDestinatario));
        } catch (Exception $e) {
            error_log("Error en NotificacionesDestinatarios::marcarLeida: " . $e->getMessage());
            Flight::json(array('error' => 'Error al marcar la notificación como leída'), 500);
        }
    }

    /**
     * Registra la respuesta de un clic del acudiente.
     *
     * Valida que la opcion pertenezca al juego de respuestas de esa misma
     * notificacion: sin esa comprobacion, un cliente manipulado podria
     * responder con una opcion de otra circular.
     */
    public static function responder()
    {
        try {
            $db = Flight::db();
            $userData = JWTService::requerirAutenticacion();
            $idUsuario = $userData->id ?? null;
            $idDestinatario = Flight::request()->data['id_destinatario'] ?? null;
            $idOpcion = Flight::request()->data['id_respuesta_opcion'] ?? null;

            if (!$idUsuario) {
                Flight::json(array('error' => 'No se pudo identificar el usuario'), 401);
                return;
            }

            if (!$idDestinatario || !$idOpcion) {
                Flight::json(array('error' => 'id_destinatario e id_respuesta_opcion son obligatorios'), 400);
                return;
            }

            $validar = $db->prepare("
                SELECT d.id
                FROM notificaciones_destinatarios d
                INNER JOIN notificaciones n ON n.id = d.id_notificacion
                INNER JOIN notificaciones_respuestas_opciones o
                        ON o.id_respuesta_tipo = n.id_respuesta_tipo
                       AND o.id = :id_respuesta_opcion
                       AND o.activo = 1
                WHERE d.id = :id
                  AND d.id_usuario = :id_usuario
                  AND d.id_tenant = :id_tenant
                  AND n.activo = 1
            ");
            $validar->bindParam(':id_respuesta_opcion', $idOpcion);
            $validar->bindParam(':id', $idDestinatario);
            $validar->bindParam(':id_usuario', $idUsuario);
            $validar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $validar->execute();

            if (!$validar->fetch()) {
                Flight::json(array('error' => 'La opción de respuesta no corresponde a esta notificación'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE notificaciones_destinatarios
                SET id_respuesta_opcion = :id_respuesta_opcion,
                    fecha_respuesta = NOW(),
                    fecha_lectura = COALESCE(fecha_lectura, NOW())
                WHERE id = :id
                  AND id_usuario = :id_usuario
                  AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id_respuesta_opcion', $idOpcion);
            $sentence->bindParam(':id', $idDestinatario);
            $sentence->bindParam(':id_usuario', $idUsuario);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $idDestinatario));
        } catch (Exception $e) {
            error_log("Error en NotificacionesDestinatarios::responder: " . $e->getMessage());
            Flight::json(array('error' => 'Error al registrar la respuesta'), 500);
        }
    }

    /**
     * Resumen agregado de una notificacion, para el tablero del jardin.
     */
    public static function getResumen($id_notificacion)
    {
        try {
            $db = Flight::db();

            $totales = $db->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN fecha_lectura IS NOT NULL THEN 1 ELSE 0 END) AS leidas,
                    SUM(CASE WHEN id_respuesta_opcion IS NOT NULL THEN 1 ELSE 0 END) AS respondidas
                FROM notificaciones_destinatarios
                WHERE id_notificacion = :id_notificacion AND id_tenant = :id_tenant
            ");
            $totales->bindParam(':id_notificacion', $id_notificacion);
            $totales->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $totales->execute();
            $resumen = $totales->fetch();

            $porOpcion = $db->prepare("
                SELECT o.id, o.codigo, o.etiqueta, COUNT(d.id) AS cantidad
                FROM notificaciones_respuestas_opciones o
                LEFT JOIN notificaciones_destinatarios d
                       ON d.id_respuesta_opcion = o.id
                      AND d.id_notificacion = :id_notificacion
                INNER JOIN notificaciones n
                       ON n.id_respuesta_tipo = o.id_respuesta_tipo
                      AND n.id = :id_notificacion_filtro
                WHERE o.activo = 1
                GROUP BY o.id, o.codigo, o.etiqueta, o.orden
                ORDER BY o.orden, o.etiqueta
            ");
            $porOpcion->bindParam(':id_notificacion', $id_notificacion);
            $porOpcion->bindParam(':id_notificacion_filtro', $id_notificacion);
            $porOpcion->execute();

            Flight::json(array(
                'total'       => (int)($resumen['total'] ?? 0),
                'leidas'      => (int)($resumen['leidas'] ?? 0),
                'respondidas' => (int)($resumen['respondidas'] ?? 0),
                'por_opcion'  => $porOpcion->fetchAll(),
            ));
        } catch (Exception $e) {
            error_log("Error en NotificacionesDestinatarios::getResumen: " . $e->getMessage());
            Flight::json(array('error' => 'Error al consultar el resumen'), 500);
        }
    }
}