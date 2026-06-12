<?php
class WaOnboarding
{
    /**
     * Conexión a BD master.
     */
    private static function getMasterDb()
    {
        require_once __DIR__ . '/../config/master.env.php';

        return new PDO(DB_MASTER_DSN, DB_MASTER_USERNAME, DB_MASTER_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
    }

    /**
     * Resolver tenant a partir del header X-Tenant.
     */
    private static function getTenantId($dbMaster)
    {
        $codigo = $_SERVER['HTTP_X_TENANT'] ?? '';
        $codigo = preg_replace('/[^a-z0-9\-_]/i', '', $codigo);

        if (!$codigo) {
            throw new Exception('Tenant no especificado');
        }

        $stmt = $dbMaster->prepare("SELECT id FROM tenants WHERE codigo = :codigo LIMIT 1");
        $stmt->execute(['codigo' => $codigo]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new Exception('Tenant no encontrado');
        }

        return (int)$row['id'];
    }

    /**
     * Llamada genérica a la Graph API de Meta.
     */
    private static function callGraphAPI($method, $url, $payload = null, $accessToken = '')
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = ['Content-Type: application/json'];
        if ($accessToken) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }

    /**
     * Parsear datos del request.
     */
    private static function getData()
    {
        $data = [];
        if (!empty($_POST)) {
            $data = $_POST;
        } elseif (Flight::request()->data) {
            foreach (Flight::request()->data as $key => $value) {
                $data[$key] = $value;
            }
        }
        $jsonBody = json_decode(Flight::request()->getBody(), true);
        if ($jsonBody) {
            $data = array_merge($data, $jsonBody);
        }
        return $data;
    }

    /**
     * GET /wa-onboarding/estado
     * Retorna el estado actual del onboarding del tenant.
     */
    public static function getEstado()
    {
        try {
            $dbMaster = self::getMasterDb();
            $idTenant = self::getTenantId($dbMaster);

            $stmt = $dbMaster->prepare("
                SELECT 
                    id_tenant,
                    app_id,
                    phone_number_id,
                    wa_business_account_id,
                    business_id,
                    es_coexistence,
                    estado_onboarding,
                    historial_sincronizado,
                    historial_sync_fecha,
                    contactos_sincronizados,
                    contactos_sync_fecha,
                    fecha_onboarding,
                    fecha_ultima_actividad,
                    mensaje_error,
                    display_phone_number,
                    api_version,
                    activo
                FROM tenants_whatsapp_config 
                WHERE id_tenant = :id_tenant 
                LIMIT 1
            ");
            $stmt->execute(['id_tenant' => $idTenant]);
            $config = $stmt->fetch();

            if (!$config) {
                Flight::json([
                    'estado_onboarding' => 'pending',
                    'es_coexistence' => 0,
                    'activo' => 0
                ]);
                return;
            }

            Flight::json($config);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /wa-onboarding/procesar
     * Recibe code + phone_number_id + waba_id + business_id desde el frontend.
     * Intercambia code por token, suscribe webhooks, guarda config.
     */
    public static function procesarOnboarding()
    {
        try {
            $data = self::getData();

            $code = $data['code'] ?? null;
            $phoneNumberId = $data['phone_number_id'] ?? null;
            $wabaId = $data['waba_id'] ?? null;
            $businessId = $data['business_id'] ?? null;
            $esCoexistence = isset($data['es_coexistence']) ? (int)(bool)$data['es_coexistence'] : 1;

            if (!$code || !$phoneNumberId || !$wabaId) {
                Flight::json(['error' => 'code, phone_number_id y waba_id son requeridos'], 400);
                return;
            }

            $dbMaster = self::getMasterDb();
            $idTenant = self::getTenantId($dbMaster);

            // 1. Intercambiar code por access_token
            $tokenUrl = 'https://graph.facebook.com/' . META_GRAPH_VERSION . '/oauth/access_token'
                . '?client_id=' . urlencode(META_APP_ID)
                . '&client_secret=' . urlencode(META_APP_SECRET)
                . '&code=' . urlencode($code);

            $tokenResult = self::callGraphAPI('GET', $tokenUrl);

            if ($tokenResult['http_code'] !== 200 || !isset($tokenResult['response']['access_token'])) {
                Flight::json([
                    'error' => 'No se pudo intercambiar el código por un token',
                    'detalle' => $tokenResult['response']
                ], 500);
                return;
            }

            $accessToken = $tokenResult['response']['access_token'];

            // 2. Suscribir la app a la WABA para recibir webhooks.
            // Usamos App Access Token (APP_ID|APP_SECRET) porque el user access token
            // del code exchange no tiene scope sobre WABAs Coexistence recién creadas.
            $appAccessToken = META_APP_ID . '|' . META_APP_SECRET;
            $subscribeUrl = 'https://graph.facebook.com/' . META_GRAPH_VERSION . '/' . $wabaId . '/subscribed_apps';
            $subscribeResult = self::callGraphAPI('POST', $subscribeUrl, [], $appAccessToken);

            if ($subscribeResult['http_code'] !== 200) {
                Flight::json([
                    'error' => 'No se pudo suscribir la app a la WABA',
                    'detalle' => $subscribeResult['response']
                ], 500);
                return;
            }

            // 3. Obtener display_phone_number desde Graph API
            $displayPhone = null;
            $phoneInfoUrl = 'https://graph.facebook.com/' . META_GRAPH_VERSION . '/' . $phoneNumberId
                . '?fields=display_phone_number,verified_name';
            $phoneInfo = self::callGraphAPI('GET', $phoneInfoUrl, null, $accessToken);
            if ($phoneInfo['http_code'] === 200) {
                $displayPhone = $phoneInfo['response']['display_phone_number'] ?? null;
            }

            // 4. Generar verify_token único para el webhook
            $verifyToken = bin2hex(random_bytes(16));

            // 5. INSERT/UPDATE en tenants_whatsapp_config
            $stmt = $dbMaster->prepare("
                SELECT id FROM tenants_whatsapp_config WHERE id_tenant = :id_tenant LIMIT 1
            ");
            $stmt->execute(['id_tenant' => $idTenant]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $dbMaster->prepare("
                    UPDATE tenants_whatsapp_config SET
                        app_id = :app_id,
                        phone_number_id = :phone_number_id,
                        wa_business_account_id = :waba_id,
                        business_id = :business_id,
                        config_id_usado = :config_id,
                        es_coexistence = :es_coexistence,
                        estado_onboarding = 'active',
                        fecha_onboarding = NOW(),
                        fecha_ultima_actividad = NOW(),
                        mensaje_error = NULL,
                        display_phone_number = :display_phone_number,
                        access_token = :access_token,
                        app_secret = :app_secret,
                        api_version = :api_version,
                        activo = 1
                    WHERE id_tenant = :id_tenant
                ");
                $stmt->execute([
                    'app_id' => META_APP_ID,
                    'phone_number_id' => $phoneNumberId,
                    'waba_id' => $wabaId,
                    'business_id' => $businessId,
                    'config_id' => META_CONFIG_ID,
                    'es_coexistence' => $esCoexistence,
                    'display_phone_number' => $displayPhone,
                    'access_token' => $accessToken,
                    'app_secret' => META_APP_SECRET,
                    'api_version' => META_GRAPH_VERSION,
                    'id_tenant' => $idTenant
                ]);
            } else {
                $stmt = $dbMaster->prepare("
                    INSERT INTO tenants_whatsapp_config (
                        id_tenant, app_id, phone_number_id, wa_business_account_id,
                        business_id, config_id_usado, es_coexistence, estado_onboarding,
                        fecha_onboarding, fecha_ultima_actividad, display_phone_number,
                        access_token, verify_token, app_secret, api_version, activo
                    ) VALUES (
                        :id_tenant, :app_id, :phone_number_id, :waba_id,
                        :business_id, :config_id, :es_coexistence, 'active',
                        NOW(), NOW(), :display_phone_number,
                        :access_token, :verify_token, :app_secret, :api_version, 1
                    )
                ");
                $stmt->execute([
                    'id_tenant' => $idTenant,
                    'app_id' => META_APP_ID,
                    'phone_number_id' => $phoneNumberId,
                    'waba_id' => $wabaId,
                    'business_id' => $businessId,
                    'config_id' => META_CONFIG_ID,
                    'es_coexistence' => $esCoexistence,
                    'display_phone_number' => $displayPhone,
                    'access_token' => $accessToken,
                    'verify_token' => $verifyToken,
                    'app_secret' => META_APP_SECRET,
                    'api_version' => META_GRAPH_VERSION
                ]);
            }

            Flight::json([
                'success' => true,
                'phone_number_id' => $phoneNumberId,
                'wa_business_account_id' => $wabaId,
                'display_phone_number' => $displayPhone,
                'es_coexistence' => $esCoexistence,
                'estado_onboarding' => 'active'
            ]);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /wa-onboarding/desconectar
     * Marca el onboarding como offboarded y desactiva el registro.
     */
    public static function desconectar()
    {
        try {
            $dbMaster = self::getMasterDb();
            $idTenant = self::getTenantId($dbMaster);

            $stmt = $dbMaster->prepare("
                UPDATE tenants_whatsapp_config SET
                    estado_onboarding = 'offboarded',
                    activo = 0,
                    fecha_ultima_actividad = NOW()
                WHERE id_tenant = :id_tenant
            ");
            $stmt->execute(['id_tenant' => $idTenant]);

            if ($stmt->rowCount() > 0) {
                Flight::json(['success' => true]);
            } else {
                Flight::json(['error' => 'No se encontró configuración para desconectar'], 404);
            }
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}