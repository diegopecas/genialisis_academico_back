<?php
class WaConversaciones
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT wconv.*, 
                   wc.numero_telefono,
                   wc.nombre_whatsapp,
                   COUNT(wm.id) as total_mensajes,
                   MAX(wm.fecha_creacion) as ultimo_mensaje
            FROM wa_conversaciones wconv
            INNER JOIN wa_contactos wc ON wconv.id_contacto = wc.id
            LEFT JOIN wa_mensajes wm ON wconv.id = wm.id_conversacion
            WHERE wconv.id_tenant = :id_tenant
            GROUP BY wconv.id
            ORDER BY wconv.fecha_creacion DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Lista para el frontend: conversaciones con preview del último mensaje,
     * conteo de no leídos, y estado de ventana de 24h.
     */
    public static function getAllConUltimoMensaje()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                wconv.id,
                wconv.activa,
                wconv.fecha_creacion,
                wconv.ventana_wa_inicio,
                wconv.ventana_wa_fin,
                wc.id AS id_contacto,
                wc.numero_telefono,
                wc.nombre_whatsapp,
                wc.id_persona,
                CASE 
                    WHEN p.id IS NOT NULL THEN CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.primer_apellido, ''))
                    ELSE wc.nombre_whatsapp
                END AS nombre_display,
                NULL AS info_acudiente,
                ult.contenido AS ultimo_mensaje_contenido,
                ult.tipo AS ultimo_mensaje_tipo,
                ult.direccion AS ultimo_mensaje_direccion,
                ult.fecha_creacion AS ultimo_mensaje_fecha,
                ult.estado AS ultimo_mensaje_estado,
                COALESCE(nr.no_leidos, 0) AS no_leidos
            FROM wa_conversaciones wconv
            INNER JOIN wa_contactos wc ON wconv.id_contacto = wc.id
            LEFT JOIN personas p ON wc.id_persona = p.id
            /* MAX(id) devolvia el UUID mayor alfabeticamente, no el mensaje mas reciente.
               Se ancla por MAX(fecha_creacion) y se desempata por el mayor id de esa fecha. */
            LEFT JOIN (
                SELECT wm1.id_conversacion, wm1.contenido, wm1.tipo, wm1.direccion, wm1.fecha_creacion, wm1.estado
                FROM wa_mensajes wm1
                INNER JOIN (
                    SELECT id_conversacion, MAX(fecha_creacion) AS max_fecha
                    FROM wa_mensajes
                    GROUP BY id_conversacion
                ) wm2 ON wm1.id_conversacion = wm2.id_conversacion
                     AND wm1.fecha_creacion = wm2.max_fecha
                INNER JOIN (
                    SELECT wmx.id_conversacion, wmx.fecha_creacion, MAX(wmx.id) AS max_id
                    FROM wa_mensajes wmx
                    GROUP BY wmx.id_conversacion, wmx.fecha_creacion
                ) wm3 ON wm1.id_conversacion = wm3.id_conversacion
                     AND wm1.fecha_creacion = wm3.fecha_creacion
                     AND wm1.id = wm3.max_id
            ) ult ON wconv.id = ult.id_conversacion
            LEFT JOIN (
                SELECT id_conversacion, COUNT(*) AS no_leidos
                FROM wa_mensajes
                WHERE direccion = 'entrada' AND respondido = 0
                GROUP BY id_conversacion
            ) nr ON wconv.id = nr.id_conversacion
            WHERE wconv.activa = 1
            AND wconv.id_tenant = :id_tenant
            ORDER BY ult.fecha_creacion DESC, wconv.fecha_creacion DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getActivas()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT wconv.*, wc.numero_telefono, wc.nombre_whatsapp
            FROM wa_conversaciones wconv
            INNER JOIN wa_contactos wc ON wconv.id_contacto = wc.id
            WHERE wconv.activa = 1 
            AND wconv.id_tenant = :id_tenant
            ORDER BY wconv.fecha_creacion DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT wconv.*, 
                   wc.numero_telefono, 
                   wc.nombre_whatsapp,
                   wc.id_persona,
                   CASE 
                       WHEN p.id IS NOT NULL THEN CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.primer_apellido, ''))
                       ELSE wc.nombre_whatsapp
                   END AS nombre_display
            FROM wa_conversaciones wconv
            INNER JOIN wa_contactos wc ON wconv.id_contacto = wc.id
            LEFT JOIN personas p ON wc.id_persona = p.id
            WHERE wconv.id = :id
            AND wconv.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetch());
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $id_contacto = Flight::request()->data['id_contacto'];

            $sentence = $db->prepare("
                INSERT INTO wa_conversaciones(
                    id,
                    id_tenant,
                    id_contacto,
                    activa
                ) VALUES (
                    :id,
                    :id_tenant,
                    :id_contacto,
                    1
                )
            ");

            $idConv = Uuid::generar();
            $sentence->bindValue(':id', $idConv);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_contacto', $id_contacto);
            $sentence->execute();

            Flight::json(array('id' => $idConv));
        } catch (Exception $e) {
            error_log("Error creando conversación WA: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function cerrar()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("UPDATE wa_conversaciones SET activa = 0 WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('success' => true));
    }

    /**
     * Conversación ÚNICA por contacto (sin expiración).
     */
    public static function getOrCreateActiva()
    {
        $db = Flight::db();
        $id_contacto = Flight::request()->data['id_contacto'];

        $sentence = $db->prepare("
            SELECT * FROM wa_conversaciones 
            WHERE id_contacto = :contacto 
            AND activa = 1
            AND id_tenant = :id_tenant
            /* id es UUID: ordenar por id no daba la conversacion mas reciente. */
            ORDER BY fecha_creacion DESC, id DESC 
            LIMIT 1
        ");
        $sentence->bindParam(':contacto', $id_contacto);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $conversacion = $sentence->fetch();

        if ($conversacion) {
            Flight::json($conversacion);
        } else {
            self::new();
        }
    }
}