<?php
class WaEnviarMensaje
{
    // Log dedicado para envío WA
    private static function waLog($msg)
    {
        $logFile = dirname(__DIR__) . '/webhook/wa-enviar.log';
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
    }
    
    // Obtener config de WhatsApp desde BD maestra
    private static function getConfig()
    {
        static $config = null;
        
        if ($config) return $config;
        
        require_once __DIR__ . '/../config/master.env.php';
        
        $dbMaster = new PDO(DB_MASTER_DSN, DB_MASTER_USERNAME, DB_MASTER_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $tenant = $_SERVER['HTTP_X_TENANT'] ?? '';
        $tenant = preg_replace('/[^a-z0-9\-_]/i', '', $tenant);
        
        $stmt = $dbMaster->prepare("
            SELECT twc.* 
            FROM tenants_whatsapp_config twc
            INNER JOIN tenants t ON twc.id_tenant = t.id
            WHERE t.codigo = :codigo AND twc.activo = 1
            LIMIT 1
        ");
        $stmt->execute(['codigo' => $tenant]);
        $config = $stmt->fetch();
        
        if (!$config) {
            throw new Exception('Configuración de WhatsApp no encontrada para este tenant');
        }
        
        return $config;
    }
    
    // Obtener código del tenant desde header
    private static function getTenantCodigo()
    {
        $tenant = $_SERVER['HTTP_X_TENANT'] ?? '';
        return preg_replace('/[^a-z0-9\-_]/i', '', $tenant);
    }
    
    // Llamar a la Graph API de Meta
    private static function callGraphAPI($endpoint, $payload, $config)
    {
        $url = "https://graph.facebook.com/{$config['api_version']}/{$config['phone_number_id']}/{$endpoint}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config['access_token'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }
    
    // Subir archivo multimedia a Meta Media API
    private static function uploadMediaToMeta($filePath, $mimeType, $config)
    {
        $url = "https://graph.facebook.com/v24.0/{$config['phone_number_id']}/media";
        
        $postFields = [
            'messaging_product' => 'whatsapp',
            'type' => $mimeType,
            'file' => new \CURLFile($filePath, $mimeType, basename($filePath))
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config['access_token']
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            self::waLog("ERROR cURL media: " . $curlError);
            return null;
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode == 200 && isset($data['id'])) {
            return $data['id'];
        }
        
        self::waLog("ERROR media (HTTP {$httpCode}): " . json_encode($data));
        return null;
    }
    
    /**
     * Buscar o crear conversación ÚNICA por contacto (sin expiración).
     */
    private static function resolverConversacion($db, $numeroDestino)
    {
        $stmt = $db->prepare("SELECT id FROM wa_contactos WHERE numero_telefono = :numero AND id_tenant = :id_tenant LIMIT 1");
        $stmt->execute(['numero' => $numeroDestino, 'id_tenant' => TenantContext::id()]);
        $contacto = $stmt->fetch();
        
        if (!$contacto) {
            $stmt = $db->prepare("INSERT INTO wa_contactos (id, id_tenant, numero_telefono, fecha_primera_interaccion) VALUES (:id, :id_tenant, :numero, NOW())");
            $idContactoNew = Uuid::generar();
            $stmt->execute(['id' => $idContactoNew, 'id_tenant' => TenantContext::id(), 'numero' => $numeroDestino]);
            $idContacto = $idContactoNew;
        } else {
            $idContacto = $contacto['id'];
        }
        
        $stmt = $db->prepare("
            SELECT id FROM wa_conversaciones 
            WHERE id_contacto = :contacto AND activa = 1 AND id_tenant = :id_tenant
            /* id es UUID: ordenar por id no daba la conversacion mas reciente. */
            ORDER BY fecha_creacion DESC, id DESC LIMIT 1
        ");
        $stmt->execute(['contacto' => $idContacto, 'id_tenant' => TenantContext::id()]);
        $conv = $stmt->fetch();
        
        if (!$conv) {
            $stmt = $db->prepare("
                INSERT INTO wa_conversaciones (id, id_tenant, id_contacto, activa, fecha_creacion) 
                VALUES (:id, :id_tenant, :contacto, 1, NOW())
            ");
            $idConvNew = Uuid::generar();
            $stmt->execute(['id' => $idConvNew, 'id_tenant' => TenantContext::id(), 'contacto' => $idContacto]);
            $idConversacion = $idConvNew;
        } else {
            $idConversacion = $conv['id'];
        }
        
        return $idConversacion;
    }

    /**
     * Verificar si la ventana de 24h está abierta para esta conversación.
     * Retorna: ['abierta' => bool, 'ventana_wa_fin' => string|null]
     */
    private static function verificarVentana($db, $idConversacion)
    {
        $stmt = $db->prepare("
            SELECT ventana_wa_fin 
            FROM wa_conversaciones 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $stmt->execute(['id' => $idConversacion, 'id_tenant' => TenantContext::id()]);
        $conv = $stmt->fetch();

        if (!$conv || !$conv['ventana_wa_fin']) {
            return ['abierta' => false, 'ventana_wa_fin' => null];
        }

        $abierta = strtotime($conv['ventana_wa_fin']) > time();
        return [
            'abierta' => $abierta,
            'ventana_wa_fin' => $conv['ventana_wa_fin']
        ];
    }

    /**
     * Verificar config de wa_config del tenant (clave-valor).
     */
    private static function getWaConfigTenant($db, $clave)
    {
        $stmt = $db->prepare("SELECT valor FROM wa_config WHERE clave = :clave AND id_tenant = :id_tenant LIMIT 1");
        $stmt->execute(['clave' => $clave, 'id_tenant' => TenantContext::id()]);
        $row = $stmt->fetch();
        return $row ? $row['valor'] : null;
    }
    
    /**
     * Guardar mensaje saliente en BD (ahora con id_persona_remitente).
     */
    private static function guardarMensajeSaliente($db, $idConversacion, $idMensajeWa, $tipo, $contenido, $urlMultimedia = null, $nombreArchivo = null, $tipoMime = null, $idPersonaRemitente = null)
    {
        $stmt = $db->prepare("
            INSERT INTO wa_mensajes (
                id, id_tenant,
                id_conversacion, id_mensaje_wa, direccion, tipo,
                contenido, url_multimedia, nombre_archivo, tipo_mime_multimedia,
                id_persona_remitente, timestamp_wa, estado, respondido
            ) VALUES (
                :id, :id_tenant,
                :id_conversacion, :id_mensaje_wa, 'salida', :tipo,
                :contenido, :url_multimedia, :nombre_archivo, :tipo_mime,
                :id_persona_remitente, :timestamp, 'enviado', 0
            )
        ");
        $idMsg = Uuid::generar();
        $stmt->execute([
            'id' => $idMsg,
            'id_tenant' => TenantContext::id(),
            'id_conversacion' => $idConversacion,
            'id_mensaje_wa' => $idMensajeWa,
            'tipo' => $tipo,
            'contenido' => $contenido,
            'url_multimedia' => $urlMultimedia,
            'nombre_archivo' => $nombreArchivo,
            'tipo_mime' => $tipoMime,
            'id_persona_remitente' => $idPersonaRemitente,
            'timestamp' => time()
        ]);
        
        $db->prepare("
            UPDATE wa_mensajes SET respondido = 1 
            WHERE id_conversacion = :id_conv AND direccion = 'entrada' AND respondido = 0 AND id_tenant = :id_tenant
        ")->execute(['id_conv' => $idConversacion, 'id_tenant' => TenantContext::id()]);
        
        return $idMsg;
    }
    
    // =============================================
    // ENVIAR TEXTO
    // =============================================
    public static function enviarTexto()
    {
        try {
            $db = Flight::db();
            $config = self::getConfig();
            
            $data = self::getData();
            $numeroDestino = $data['numero_destino'] ?? null;
            $mensaje = $data['mensaje'] ?? null;
            $idPersonaRemitente = $data['id_persona_remitente'] ?? null;
            
            if (!$numeroDestino || !$mensaje) {
                Flight::json(['error' => 'numero_destino y mensaje son requeridos'], 400);
                return;
            }
            
            $idConversacion = self::resolverConversacion($db, $numeroDestino);

            // Verificar ventana de 24h
            $ventana = self::verificarVentana($db, $idConversacion);
            if (!$ventana['abierta']) {
                $permitirFuera = self::getWaConfigTenant($db, 'permitir_enviar_mensajes_fuera_ventana');
                if ($permitirFuera !== '1') {
                    Flight::json([
                        'error' => 'ventana_cerrada',
                        'mensaje' => 'La ventana de conversación expiró. Se enviará un mensaje con costo para reabrir la conversación.',
                        'ventana_wa_fin' => $ventana['ventana_wa_fin']
                    ], 403);
                    return;
                }
            }
            
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $numeroDestino,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $mensaje
                ]
            ];
            
            $result = self::callGraphAPI('messages', $payload, $config);
            
            if ($result['http_code'] == 200 && isset($result['response']['messages'][0]['id'])) {
                $idMensajeWa = $result['response']['messages'][0]['id'];
                $idLocal = self::guardarMensajeSaliente(
                    $db, $idConversacion, $idMensajeWa, 'texto', $mensaje,
                    null, null, null, $idPersonaRemitente
                );
                
                Flight::json([
                    'success' => true,
                    'id' => $idLocal,
                    'id_mensaje_wa' => $idMensajeWa
                ]);
            } else {
                Flight::json([
                    'success' => false,
                    'error' => 'Error al enviar mensaje',
                    'detalle' => $result['response']
                ], 500);
            }
            
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
    
    // =============================================
    // ENVIAR IMAGEN
    // =============================================
    public static function enviarImagen()
    {
        try {
            $db = Flight::db();
            $config = self::getConfig();
            
            $data = self::getData();
            $numeroDestino = $data['numero_destino'] ?? null;
            $urlImagen = $data['url_imagen'] ?? null;
            $caption = $data['caption'] ?? '';
            $idPersonaRemitente = $data['id_persona_remitente'] ?? null;
            
            if (!$numeroDestino || !$urlImagen) {
                Flight::json(['error' => 'numero_destino y url_imagen son requeridos'], 400);
                return;
            }
            
            $idConversacion = self::resolverConversacion($db, $numeroDestino);
            
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $numeroDestino,
                'type' => 'image',
                'image' => [
                    'link' => $urlImagen,
                    'caption' => $caption
                ]
            ];
            
            $result = self::callGraphAPI('messages', $payload, $config);
            
            if ($result['http_code'] == 200 && isset($result['response']['messages'][0]['id'])) {
                $idMensajeWa = $result['response']['messages'][0]['id'];
                self::guardarMensajeSaliente(
                    $db, $idConversacion, $idMensajeWa, 'imagen', $caption ?: 'Imagen',
                    null, null, null, $idPersonaRemitente
                );
                
                Flight::json(['success' => true, 'id_mensaje_wa' => $idMensajeWa]);
            } else {
                Flight::json(['success' => false, 'error' => 'Error al enviar imagen', 'detalle' => $result['response']], 500);
            }
            
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
    
    // =============================================
    // ENVIAR DOCUMENTO
    // =============================================
    public static function enviarDocumento()
    {
        try {
            $db = Flight::db();
            $config = self::getConfig();
            
            $data = self::getData();
            $numeroDestino = $data['numero_destino'] ?? null;
            $urlDocumento = $data['url_documento'] ?? null;
            $nombreArchivo = $data['nombre_archivo'] ?? 'documento';
            $idPersonaRemitente = $data['id_persona_remitente'] ?? null;
            
            if (!$numeroDestino || !$urlDocumento) {
                Flight::json(['error' => 'numero_destino y url_documento son requeridos'], 400);
                return;
            }
            
            $idConversacion = self::resolverConversacion($db, $numeroDestino);
            
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $numeroDestino,
                'type' => 'document',
                'document' => [
                    'link' => $urlDocumento,
                    'filename' => $nombreArchivo
                ]
            ];
            
            $result = self::callGraphAPI('messages', $payload, $config);
            
            if ($result['http_code'] == 200 && isset($result['response']['messages'][0]['id'])) {
                $idMensajeWa = $result['response']['messages'][0]['id'];
                self::guardarMensajeSaliente(
                    $db, $idConversacion, $idMensajeWa, 'documento', $nombreArchivo,
                    null, null, null, $idPersonaRemitente
                );
                
                Flight::json(['success' => true, 'id_mensaje_wa' => $idMensajeWa]);
            } else {
                Flight::json(['success' => false, 'error' => 'Error al enviar documento', 'detalle' => $result['response']], 500);
            }
            
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
    
    // =============================================
    // ENVIAR ARCHIVO (Upload a Meta Media API + envío)
    // =============================================
    public static function enviarArchivo()
    {
        try {
            $db = Flight::db();
            $config = self::getConfig();
            
            $numeroDestino = $_POST['numero_destino'] ?? null;
            $caption = $_POST['caption'] ?? '';
            $idPersonaRemitente = $_POST['id_persona_remitente'] ?? null;
            
            if (!$numeroDestino) {
                Flight::json(['error' => 'numero_destino es requerido'], 400);
                return;
            }
            
            if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = isset($_FILES['archivo']) 
                    ? 'Error al subir archivo (código: ' . $_FILES['archivo']['error'] . ')' 
                    : 'No se recibió ningún archivo';
                self::waLog("ERROR: " . $errorMsg);
                Flight::json(['error' => $errorMsg], 400);
                return;
            }
            
            $archivo = $_FILES['archivo'];
            
            if ($archivo['size'] > 16 * 1024 * 1024) {
                Flight::json(['error' => 'El archivo no puede superar 16MB'], 400);
                return;
            }

            $idConversacion = self::resolverConversacion($db, $numeroDestino);

            // Verificar ventana de 24h
            $ventana = self::verificarVentana($db, $idConversacion);
            if (!$ventana['abierta']) {
                $permitirFuera = self::getWaConfigTenant($db, 'permitir_enviar_mensajes_fuera_ventana');
                if ($permitirFuera !== '1') {
                    Flight::json([
                        'error' => 'ventana_cerrada',
                        'mensaje' => 'La ventana de conversación expiró. Se enviará un mensaje con costo para reabrir la conversación.',
                        'ventana_wa_fin' => $ventana['ventana_wa_fin']
                    ], 403);
                    return;
                }
            }
            
            $tipoMime = $archivo['type'];
            $tipoMensaje = self::determinarTipoMensaje($tipoMime);
            
            // PASO 1: Subir a Meta Media API desde archivo temporal
            $archivoTmp = $archivo['tmp_name'];
            $mediaId = self::uploadMediaToMeta($archivoTmp, $tipoMime, $config);
            
            if (!$mediaId) {
                Flight::json(['error' => 'Error al subir archivo a WhatsApp'], 500);
                return;
            }
            
            // PASO 2: Guardar localmente (para mostrar en GENIALISIS)
            $codigoTenant = self::getTenantCodigo();
            $uploadPath = defined('UPLOAD_PATH') ? UPLOAD_PATH : dirname(__DIR__) . '/uploads';
            $carpetaMedia = "{$uploadPath}/{$codigoTenant}/wa-media/{$idConversacion}";
            
            if (!is_dir($carpetaMedia)) {
                if (!@mkdir($carpetaMedia, 0755, true)) {
                    self::waLog("ERROR mkdir: {$carpetaMedia}");
                }
            }
            
            $nombreOriginal = $archivo['name'];
            $nombreUnico = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreOriginal);
            $rutaCompleta = "{$carpetaMedia}/{$nombreUnico}";
            
            if (file_exists($archivoTmp)) {
                $bytesEscritos = file_put_contents($rutaCompleta, file_get_contents($archivoTmp));
                if ($bytesEscritos === false) {
                    self::waLog("ERROR guardando: {$rutaCompleta}");
                }
            } else {
                self::waLog("ERROR: tmp no existe post-upload Meta");
            }
            
            $rutaRelativa = "{$codigoTenant}/wa-media/{$idConversacion}/{$nombreUnico}";
            
            // PASO 3: Enviar mensaje a WhatsApp con media_id
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $numeroDestino,
                'type' => $tipoMensaje
            ];
            
            switch ($tipoMensaje) {
                case 'image':
                    $payload['image'] = ['id' => $mediaId];
                    if ($caption) $payload['image']['caption'] = $caption;
                    $tipoLocal = 'imagen';
                    $contenidoLocal = $caption ?: 'Imagen';
                    break;
                case 'video':
                    $payload['video'] = ['id' => $mediaId];
                    if ($caption) $payload['video']['caption'] = $caption;
                    $tipoLocal = 'video';
                    $contenidoLocal = $caption ?: 'Video';
                    break;
                case 'audio':
                    $payload['audio'] = ['id' => $mediaId];
                    $tipoLocal = 'audio';
                    $contenidoLocal = 'Audio';
                    break;
                default:
                    $payload['document'] = ['id' => $mediaId, 'filename' => $nombreOriginal];
                    $tipoLocal = 'documento';
                    $contenidoLocal = $nombreOriginal;
                    break;
            }
            
            $result = self::callGraphAPI('messages', $payload, $config);
            
            if ($result['http_code'] == 200 && isset($result['response']['messages'][0]['id'])) {
                $idMensajeWa = $result['response']['messages'][0]['id'];
                
                $idLocal = self::guardarMensajeSaliente(
                    $db, $idConversacion, $idMensajeWa, $tipoLocal, $contenidoLocal,
                    $rutaRelativa, $nombreOriginal, $tipoMime, $idPersonaRemitente
                );
                
                self::waLog("OK [{$tipoLocal}] {$nombreOriginal} -> WA:{$idMensajeWa}");
                
                Flight::json([
                    'success' => true,
                    'id' => $idLocal,
                    'id_mensaje_wa' => $idMensajeWa,
                    'tipo' => $tipoLocal,
                    'url_multimedia' => $rutaRelativa,
                    'nombre_archivo' => $nombreOriginal
                ]);
            } else {
                self::waLog("ERROR envío: " . json_encode($result['response']));
                Flight::json([
                    'success' => false,
                    'error' => 'Error al enviar archivo por WhatsApp',
                    'detalle' => $result['response']
                ], 500);
            }
            
        } catch (Exception $e) {
            self::waLog("EXCEPTION: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
    
    // Determinar tipo de mensaje de WhatsApp según MIME type
    private static function determinarTipoMensaje($tipoMime)
    {
        $tipoBase = trim(explode(';', $tipoMime)[0]);
        
        if (strpos($tipoBase, 'image/') === 0) return 'image';
        if (strpos($tipoBase, 'video/') === 0) return 'video';
        if (strpos($tipoBase, 'audio/') === 0) return 'audio';
        
        return 'document';
    }
    
    // =============================================
    // ENVIAR TEMPLATE
    // =============================================
    public static function enviarTemplate()
    {
        try {
            $db = Flight::db();
            $config = self::getConfig();
            
            $data = self::getData();
            $numeroDestino = $data['numero_destino'] ?? null;
            $templateName = $data['template_name'] ?? null;
            $languageCode = $data['language_code'] ?? 'es';
            $components = $data['components'] ?? [];
            $idPersonaRemitente = $data['id_persona_remitente'] ?? null;
            
            if (!$numeroDestino || !$templateName) {
                Flight::json(['error' => 'numero_destino y template_name son requeridos'], 400);
                return;
            }
            
            $idConversacion = self::resolverConversacion($db, $numeroDestino);
            
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $numeroDestino,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $languageCode]
                ]
            ];
            
            if (!empty($components)) {
                $payload['template']['components'] = $components;
            }
            
            $result = self::callGraphAPI('messages', $payload, $config);
            
            if ($result['http_code'] == 200 && isset($result['response']['messages'][0]['id'])) {
                $idMensajeWa = $result['response']['messages'][0]['id'];
                self::guardarMensajeSaliente(
                    $db, $idConversacion, $idMensajeWa, 'texto', "Template: {$templateName}",
                    null, null, null, $idPersonaRemitente
                );
                
                Flight::json(['success' => true, 'id_mensaje_wa' => $idMensajeWa]);
            } else {
                Flight::json(['success' => false, 'error' => 'Error al enviar template', 'detalle' => $result['response']], 500);
            }
            
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
    
    // Helper para obtener datos del request
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
        return $data;
    }
}