<?php
/**
 * Líneas de WhatsApp del tenant.
 *
 * Cada fila de tenants_whatsapp_config (base master) es una línea: un número
 * de WhatsApp que el jardín conectó, con el nombre con que lo reconoce
 * (Cartera, Académico, Admisiones...). Un tenant puede tener varias.
 *
 * Esta clase es el único punto que resuelve la configuración de Meta de una
 * línea (token, phone_number_id, WABA); la usan el envío de mensajes y las
 * plantillas. Las conversaciones del tenant guardan la línea en
 * wa_conversaciones.id_linea.
 */
class WaLineas
{
    /**
     * Conexión a BD master.
     */
    private static function getMasterDb()
    {
        require_once CONFIG_DIR . '/master.env.php';

        return new PDO(DB_MASTER_DSN, DB_MASTER_USERNAME, DB_MASTER_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
    }

    /**
     * GET /wa-lineas
     * Todas las líneas del tenant (conectadas y desconectadas), sin tokens.
     * Las desconectadas se devuelven para poder mostrar el nombre de la línea
     * en las conversaciones viejas.
     */
    public static function getAll()
    {
        try {
            $stmt = self::getMasterDb()->prepare("
                SELECT
                    twc.id,
                    twc.nombre,
                    twc.phone_number_id,
                    twc.display_phone_number,
                    twc.wa_business_account_id,
                    twc.es_coexistence,
                    twc.estado_onboarding,
                    twc.historial_sincronizado,
                    twc.historial_sync_fecha,
                    twc.fecha_onboarding,
                    twc.fecha_ultima_actividad,
                    twc.activo
                FROM tenants_whatsapp_config twc
                INNER JOIN tenants t ON t.id = twc.id_tenant
                WHERE t.codigo = :codigo
                ORDER BY twc.activo DESC, twc.nombre, twc.id
            ");
            $stmt->execute(['codigo' => TenantContext::codigo()]);
            Flight::json($stmt->fetchAll());
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /wa-lineas/nombre
     * Recibe id y nombre. Cambia el nombre de una línea del tenant.
     */
    public static function updateNombre()
    {
        try {
            $id = Flight::request()->data['id'] ?? null;
            $nombre = trim((string) (Flight::request()->data['nombre'] ?? ''));

            if (!$id || $nombre === '') {
                Flight::json(['error' => 'La línea y el nombre son requeridos'], 400);
                return;
            }
            if (mb_strlen($nombre) > 60) {
                Flight::json(['error' => 'El nombre puede tener máximo 60 caracteres'], 400);
                return;
            }

            $stmt = self::getMasterDb()->prepare("
                UPDATE tenants_whatsapp_config twc
                INNER JOIN tenants t ON t.id = twc.id_tenant
                SET twc.nombre = :nombre
                WHERE twc.id = :id AND t.codigo = :codigo
            ");
            $stmt->execute(['nombre' => $nombre, 'id' => $id, 'codigo' => TenantContext::codigo()]);

            Flight::json(['success' => true]);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Configuración de Meta de una línea conectada del tenant.
     * Si no llega la línea y el tenant tiene una sola conectada, se usa esa;
     * con varias, la línea es obligatoria.
     *
     * @param  int|string|null $idLinea tenants_whatsapp_config.id
     * @return array Fila completa de tenants_whatsapp_config
     * @throws Exception Si la línea no es del tenant, no está conectada o falta
     */
    public static function configActiva($idLinea = null)
    {
        $sql = "
            SELECT twc.*
            FROM tenants_whatsapp_config twc
            INNER JOIN tenants t ON twc.id_tenant = t.id
            WHERE t.codigo = :codigo AND twc.activo = 1
        ";
        $params = ['codigo' => TenantContext::codigo()];
        if ($idLinea !== null && $idLinea !== '') {
            $sql .= " AND twc.id = :id";
            $params['id'] = $idLinea;
        }

        $stmt = self::getMasterDb()->prepare($sql);
        $stmt->execute($params);
        $lineas = $stmt->fetchAll();

        if (count($lineas) === 0) {
            throw new Exception($idLinea
                ? 'La línea de WhatsApp no está conectada'
                : 'Configuración de WhatsApp no encontrada para este tenant');
        }
        if (count($lineas) > 1) {
            throw new Exception('El jardín tiene varias líneas de WhatsApp, escoge por cuál enviar');
        }

        return $lineas[0];
    }
}
