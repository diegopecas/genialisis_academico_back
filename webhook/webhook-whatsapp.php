<?php
/*=============================================
WEBHOOK PARA WHATSAPP BUSINESS API
Jardín Infantil - Recepción y almacenamiento de mensajes
=============================================*/

// Configuración de errores
ini_set("display_errors", 1);
ini_set("log_errors", 1);
ini_set("error_log", __DIR__."/webhook_errors.log");

// Zona horaria
date_default_timezone_set("America/Bogota");

// Ajustar paths según tu estructura
// Si este archivo está en /webhook/ y los otros en /services/, /config/, etc.
$base_path = dirname(__DIR__); // Esto sube un nivel desde /webhook/

// Inicializar Flight y BD
require_once $base_path . "/index.php"; // O donde tengas la inicialización de Flight

// Cargar servicios
require_once $base_path . "/services/wa-contactos.service.php";
require_once $base_path . "/services/wa-conversaciones.service.php";
require_once $base_path . "/services/wa-mensajes.service.php";

// Token de verificación (cambiar en producción)
define('WEBHOOK_VERIFY_TOKEN', '1234abcd');

/*=============================================
VERIFICACIÓN DEL WEBHOOK (GET)
=============================================*/
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["hub_verify_token"])) {
    
    if ($_GET["hub_verify_token"] === WEBHOOK_VERIFY_TOKEN) {
        echo $_GET["hub_challenge"];
        exit;
    } else {
        http_response_code(403);
        echo "Token inválido";
        exit;
    }
}

/*=============================================
RECEPCIÓN DE MENSAJES (POST)
=============================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Capturar el JSON recibido
    $input = file_get_contents('php://input');
    
    // Decodificar JSON
    $webhook = json_decode($input, true);
    
    // Validar estructura
    if (!isset($webhook['entry'][0]['changes'][0]['value'])) {
        http_response_code(200);
        exit;
    }
    
    $value = $webhook['entry'][0]['changes'][0]['value'];
    
    try {
        
        // Procesar mensaje entrante
        if (isset($value['messages'])) {
            procesarMensajeEntrante($value);
        }
        
        // Procesar actualización de estado
        if (isset($value['statuses'])) {
            procesarEstadoMensaje($value['statuses'][0]);
        }
        
        http_response_code(200);
        echo "OK";
        
    } catch (Exception $e) {
        error_log("Error en webhook: " . $e->getMessage());
        http_response_code(200);
    }
}

/*=============================================
PROCESAR MENSAJE ENTRANTE
=============================================*/
function procesarMensajeEntrante($value) {
    
    $message = $value['messages'][0];
    $contact = $value['contacts'][0] ?? [];
    
    // Datos básicos
    $numero_telefono = $message['from'];
    $nombre_whatsapp = $contact['profile']['name'] ?? null;
    $id_mensaje_wa = $message['id'];
    $timestamp_wa = $message['timestamp'];
    
    // 1. Buscar o crear contacto
    $_POST = [
        'numero_telefono' => $numero_telefono,
        'nombre_whatsapp' => $nombre_whatsapp
    ];
    Flight::request()->init();
    
    ob_start();
    WaContactos::findOrCreate();
    $contacto_json = ob_get_clean();
    $contacto = json_decode($contacto_json, true);
    
    // 2. Buscar o crear conversación
    $_POST = [
        'id_contacto' => $contacto['id']
    ];
    Flight::request()->init();
    
    ob_start();
    WaConversaciones::getOrCreateActiva();
    $conversacion_json = ob_get_clean();
    $conversacion = json_decode($conversacion_json, true);
    
    // 3. Procesar contenido
    $datosContenido = procesarContenidoMensaje($message);
    
    // 4. Guardar mensaje
    $_POST = [
        'id_conversacion' => $conversacion['id'],
        'id_mensaje_wa' => $id_mensaje_wa,
        'direccion' => 'entrada',
        'tipo' => $datosContenido['tipo'],
        'contenido' => $datosContenido['contenido'],
        'id_multimedia' => $datosContenido['id_multimedia'] ?? null,
        'tipo_mime_multimedia' => $datosContenido['tipo_mime'] ?? null,
        'nombre_archivo' => $datosContenido['nombre_archivo'] ?? null,
        'timestamp_wa' => $timestamp_wa,
        'estado' => 'recibido',
        'respondido' => 0
    ];
    Flight::request()->init();
    
    ob_start();
    WaMensajes::new();
    ob_end_clean();
}

/*=============================================
PROCESAR CONTENIDO DEL MENSAJE
=============================================*/
function procesarContenidoMensaje($message) {
    
    $resultado = [];
    
    // Texto
    if (isset($message['text'])) {
        $resultado = [
            'tipo' => 'texto',
            'contenido' => $message['text']['body']
        ];
    }
    // Imagen
    elseif (isset($message['image'])) {
        $resultado = [
            'tipo' => 'imagen',
            'contenido' => $message['image']['caption'] ?? 'Imagen',
            'id_multimedia' => $message['image']['id'],
            'tipo_mime' => $message['image']['mime_type']
        ];
    }
    // Audio
    elseif (isset($message['audio'])) {
        $resultado = [
            'tipo' => 'audio',
            'contenido' => 'Nota de voz',
            'id_multimedia' => $message['audio']['id'],
            'tipo_mime' => $message['audio']['mime_type']
        ];
    }
    // Video
    elseif (isset($message['video'])) {
        $resultado = [
            'tipo' => 'video',
            'contenido' => $message['video']['caption'] ?? 'Video',
            'id_multimedia' => $message['video']['id'],
            'tipo_mime' => $message['video']['mime_type']
        ];
    }
    // Documento
    elseif (isset($message['document'])) {
        $resultado = [
            'tipo' => 'documento',
            'contenido' => $message['document']['filename'] ?? 'Documento',
            'id_multimedia' => $message['document']['id'],
            'tipo_mime' => $message['document']['mime_type'],
            'nombre_archivo' => $message['document']['filename'] ?? null
        ];
    }
    
    return $resultado;
}

/*=============================================
PROCESAR ESTADO DE MENSAJE
=============================================*/
function procesarEstadoMensaje($status) {
    
    // Mapeo de estados
    $estados = [
        'sent' => 'enviado',
        'delivered' => 'entregado',
        'read' => 'leido',
        'failed' => 'fallido'
    ];
    
    $_POST = [
        'id_mensaje_wa' => $status['id'],
        'estado' => $estados[$status['status']] ?? 'enviado'
    ];
    Flight::request()->init();
    
    WaMensajes::updateEstado();
}
?>