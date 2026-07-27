<?php

/**
 * Servicio de la tabla ia_chat_contexto_cache.
 *
 * Guarda una "foto" del contexto global del jardín (JSON) por tenant, para que
 * el chat IA no tenga que recalcular los resúmenes del dashboard en cada
 * mensaje. Una sola fila por tenant (UNIQUE id_tenant): al guardar se hace upsert.
 *
 * La decisión de cuándo refrescar (TTL) y de qué contiene el JSON vive en IaChat;
 * aquí solo está el CRUD de la tabla. Las fechas se manejan en UTC (UTC_TIMESTAMP)
 * para que la vigencia no dependa de la zona horaria de sesión.
 */
class IaChatContextoCache
{
    /**
     * Retorna el JSON del contexto si existe una foto para el tenant cuya
     * antigüedad no supera $ttlMin minutos. Si $ttlMin <= 0 no se usa caché
     * (retorna null para forzar recálculo). Retorna null si no hay foto vigente.
     */
    public static function obtenerVigente($db, $ttlMin)
    {
        $ttlMin = (int) $ttlMin;
        if ($ttlMin <= 0) {
            return null;
        }

        $sentence = $db->prepare("SELECT contexto 
            FROM ia_chat_contexto_cache 
            WHERE id_tenant = :id_tenant 
              AND TIMESTAMPADD(MINUTE, :ttl, fecha_calculo) >= UTC_TIMESTAMP() 
            LIMIT 1");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':ttl', $ttlMin, PDO::PARAM_INT);
        $sentence->execute();
        $row = $sentence->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['contexto'] : null;
    }

    /**
     * Guarda (upsert) la foto de contexto del tenant y actualiza fecha_calculo.
     */
    public static function guardar($db, $contextoJson)
    {
        $sentence = $db->prepare("INSERT INTO ia_chat_contexto_cache (id, id_tenant, contexto, fecha_calculo) 
            VALUES (:id, :id_tenant, :contexto, UTC_TIMESTAMP()) 
            ON DUPLICATE KEY UPDATE contexto = VALUES(contexto), fecha_calculo = UTC_TIMESTAMP()");
        $id = Uuid::generar();
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':contexto', $contextoJson);
        $sentence->execute();
    }

    /**
     * Elimina la foto de contexto del tenant (fuerza recálculo en la próxima consulta).
     */
    public static function eliminar($db)
    {
        $sentence = $db->prepare("DELETE FROM ia_chat_contexto_cache WHERE id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }
}