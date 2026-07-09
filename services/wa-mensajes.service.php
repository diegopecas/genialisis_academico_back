<?php
class WaMensajes
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT wm.*, wc.numero_telefono, wc.nombre_whatsapp
            FROM wa_mensajes wm
            INNER JOIN wa_conversaciones wconv ON wm.id_conversacion = wconv.id
            INNER JOIN wa_contactos wc ON wconv.id_contacto = wc.id
            WHERE wm.id_tenant = :id_tenant
            ORDER BY wm.fecha_creacion DESC
            LIMIT 100
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Últimos 30 mensajes de una conversación (carga inicial).
     * La etiqueta del remitente ya viene incluida en el contenido.
     */
    public static function getByConversacion($id_conversacion)
    {
        $db = Flight::db();
        /* El id es UUID: ordenar por id ya no equivale a orden cronologico.
           Se ordena por fecha_creacion, con el id como desempate estable. */
        $sentence = $db->prepare("
            SELECT * FROM (
                SELECT * FROM wa_mensajes 
                WHERE id_conversacion = :id_conversacion 
                AND id_tenant = :id_tenant
                ORDER BY fecha_creacion DESC, id DESC 
                LIMIT 30
            ) sub
            ORDER BY sub.fecha_creacion ASC, sub.id ASC
        ");
        $sentence->bindParam(':id_conversacion', $id_conversacion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Paginación hacia atrás: mensajes anteriores a un ID dado.
     */
    public static function getAnteriores($id_conversacion, $antes_de_id)
    {
        $db = Flight::db();

        /* El id es UUID: 'id < :antes_de_id' comparaba alfabeticamente, no por antiguedad.
           Se resuelve el cursor a su fecha_creacion y se compara por (fecha_creacion, id),
           asi no se pierden ni se repiten mensajes creados en el mismo segundo.
           El contrato de la ruta no cambia: el front sigue enviando un id. */
        $ancla = $db->prepare("
            SELECT fecha_creacion FROM wa_mensajes 
            WHERE id = :antes_de_id AND id_tenant = :id_tenant
        ");
        $ancla->execute([
            'antes_de_id' => $antes_de_id,
            'id_tenant' => TenantContext::id()
        ]);
        $fecha_ancla = $ancla->fetchColumn();

        if ($fecha_ancla === false) {
            Flight::json([]);
            return;
        }

        $sentence = $db->prepare("
            SELECT * FROM (
                SELECT * FROM wa_mensajes 
                WHERE id_conversacion = :id_conversacion 
                AND (
                    fecha_creacion < :fecha_ancla
                    OR (fecha_creacion = :fecha_ancla_eq AND id < :antes_de_id)
                )
                AND id_tenant = :id_tenant
                ORDER BY fecha_creacion DESC, id DESC 
                LIMIT 30
            ) sub
            ORDER BY sub.fecha_creacion ASC, sub.id ASC
        ");
        $sentence->execute([
            'id_conversacion' => $id_conversacion,
            'fecha_ancla' => $fecha_ancla,
            'fecha_ancla_eq' => $fecha_ancla,
            'antes_de_id' => $antes_de_id,
            'id_tenant' => TenantContext::id()
        ]);
        Flight::json($sentence->fetchAll());
    }

    /**
     * Mensajes nuevos desde un ID (polling).
     */
    public static function getNuevosDesde($id_conversacion, $desde_id)
    {
        $db = Flight::db();

        /* Mismo criterio que getAnteriores: el id UUID no sirve como cursor cronologico. */
        $ancla = $db->prepare("
            SELECT fecha_creacion FROM wa_mensajes 
            WHERE id = :desde_id AND id_tenant = :id_tenant
        ");
        $ancla->execute([
            'desde_id' => $desde_id,
            'id_tenant' => TenantContext::id()
        ]);
        $fecha_ancla = $ancla->fetchColumn();

        /* Si el cursor no existe (primera carga o id invalido) se devuelve la conversacion completa,
           que es el comportamiento que tenia el codigo original cuando recibia 0. */
        if ($fecha_ancla === false) {
            $sentence = $db->prepare("
                SELECT * FROM wa_mensajes 
                WHERE id_conversacion = :id_conversacion 
                AND id_tenant = :id_tenant
                ORDER BY fecha_creacion ASC, id ASC
            ");
            $sentence->execute([
                'id_conversacion' => $id_conversacion,
                'id_tenant' => TenantContext::id()
            ]);
            Flight::json($sentence->fetchAll());
            return;
        }

        $sentence = $db->prepare("
            SELECT * FROM wa_mensajes 
            WHERE id_conversacion = :id_conversacion 
            AND (
                fecha_creacion > :fecha_ancla
                OR (fecha_creacion = :fecha_ancla_eq AND id > :desde_id)
            )
            AND id_tenant = :id_tenant
            ORDER BY fecha_creacion ASC, id ASC
        ");
        $sentence->execute([
            'id_conversacion' => $id_conversacion,
            'fecha_ancla' => $fecha_ancla,
            'fecha_ancla_eq' => $fecha_ancla,
            'desde_id' => $desde_id,
            'id_tenant' => TenantContext::id()
        ]);
        Flight::json($sentence->fetchAll());
    }

    public static function getNoLeidos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                COUNT(*) AS total_no_leidos,
                COUNT(DISTINCT wm.id_conversacion) AS conversaciones_con_no_leidos
            FROM wa_mensajes wm
            INNER JOIN wa_conversaciones wconv ON wm.id_conversacion = wconv.id
            WHERE wm.direccion = 'entrada' 
            AND wm.respondido = 0
            AND wconv.activa = 1
            AND wm.id_tenant = :id_tenant
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetch());
    }

    public static function getSinResponder()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT wm.*, wc.numero_telefono, wc.nombre_whatsapp, wconv.id as id_conversacion
            FROM wa_mensajes wm
            INNER JOIN wa_conversaciones wconv ON wm.id_conversacion = wconv.id
            INNER JOIN wa_contactos wc ON wconv.id_contacto = wc.id
            WHERE wm.direccion = 'entrada' 
            AND wm.respondido = 0
            AND wconv.activa = 1
            AND wm.id_tenant = :id_tenant
            ORDER BY wm.fecha_creacion ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            
            $data = [];
            if (!empty($_POST)) {
                $data = $_POST;
            } elseif (Flight::request()->data) {
                foreach (Flight::request()->data as $key => $value) {
                    $data[$key] = $value;
                }
            }
            
            if (empty($data)) {
                throw new Exception("No se recibieron datos");
            }
            
            $idMsg = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO wa_mensajes(
                    id, id_tenant, id_conversacion, id_mensaje_wa, direccion, tipo,
                    contenido, id_multimedia, tipo_mime_multimedia,
                    nombre_archivo, etiquetas, timestamp_wa, estado, respondido
                ) VALUES (
                    :id, :id_tenant, :id_conversacion, :id_mensaje_wa, :direccion, :tipo,
                    :contenido, :id_multimedia, :tipo_mime_multimedia,
                    :nombre_archivo, :etiquetas, :timestamp_wa, :estado, :respondido
                )
            ");
            
            $sentence->execute([
                'id' => $idMsg,
                'id_tenant' => TenantContext::id(),
                'id_conversacion' => $data['id_conversacion'] ?? null,
                'id_mensaje_wa' => $data['id_mensaje_wa'] ?? null,
                'direccion' => $data['direccion'] ?? 'entrada',
                'tipo' => $data['tipo'] ?? 'texto',
                'contenido' => $data['contenido'] ?? '',
                'id_multimedia' => $data['id_multimedia'] ?? null,
                'tipo_mime_multimedia' => $data['tipo_mime_multimedia'] ?? null,
                'nombre_archivo' => $data['nombre_archivo'] ?? null,
                'etiquetas' => isset($data['etiquetas']) ? json_encode($data['etiquetas']) : null,
                'timestamp_wa' => $data['timestamp_wa'] ?? time(),
                'estado' => $data['estado'] ?? 'enviado',
                'respondido' => $data['respondido'] ?? 0
            ]);
            
            Flight::json(['id' => $idMsg]);
            
        } catch (Exception $e) {
            error_log("Error creando mensaje WA: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function updateEstado()
    {
        $db = Flight::db();
        
        $data = [];
        if (!empty($_POST)) {
            $data = $_POST;
        } elseif (Flight::request()->data) {
            foreach (Flight::request()->data as $key => $value) {
                $data[$key] = $value;
            }
        }
        
        $stmt = $db->prepare("UPDATE wa_mensajes SET estado = :estado WHERE id_mensaje_wa = :id_mensaje_wa AND id_tenant = :id_tenant");
        $stmt->execute([
            'estado' => $data['estado'] ?? null,
            'id_mensaje_wa' => $data['id_mensaje_wa'] ?? null,
            'id_tenant' => TenantContext::id()
        ]);
        
        Flight::json(['success' => true]);
    }

    public static function marcarRespondido()
    {
        $db = Flight::db();
        
        $data = [];
        if (!empty($_POST)) {
            $data = $_POST;
        } elseif (Flight::request()->data) {
            foreach (Flight::request()->data as $key => $value) {
                $data[$key] = $value;
            }
        }
        
        $id = $data['id'] ?? null;
        $stmt = $db->prepare("UPDATE wa_mensajes SET respondido = 1 WHERE id = :id AND id_tenant = :id_tenant");
        $stmt->execute(['id' => $id, 'id_tenant' => TenantContext::id()]);
        
        Flight::json(['success' => true]);
    }

    public static function marcarConversacionLeida($id_conversacion)
    {
        $db = Flight::db();
        
        $stmt = $db->prepare("
            UPDATE wa_mensajes 
            SET respondido = 1 
            WHERE id_conversacion = :id_conv 
            AND direccion = 'entrada' 
            AND respondido = 0
            AND id_tenant = :id_tenant
        ");
        $stmt->execute(['id_conv' => $id_conversacion, 'id_tenant' => TenantContext::id()]);
        
        Flight::json(['success' => true, 'actualizados' => $stmt->rowCount()]);
    }

    public static function updateEtiquetas()
    {
        $db = Flight::db();
        
        $data = [];
        if (!empty($_POST)) {
            $data = $_POST;
        } elseif (Flight::request()->data) {
            foreach (Flight::request()->data as $key => $value) {
                $data[$key] = $value;
            }
        }
        
        $id = $data['id'] ?? null;
        $etiquetas = json_encode($data['etiquetas'] ?? []);
        
        $stmt = $db->prepare("UPDATE wa_mensajes SET etiquetas = :etiquetas WHERE id = :id AND id_tenant = :id_tenant");
        $stmt->execute(['etiquetas' => $etiquetas, 'id' => $id, 'id_tenant' => TenantContext::id()]);
        
        Flight::json(['success' => true]);
    }
}