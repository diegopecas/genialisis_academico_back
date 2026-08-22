<?php
/**
 * Aviso previo de los compromisos del dia. Se ejecuta por CLI desde el cron
 * de cPanel cada 5 minutos, UN PROCESO POR TENANT.
 *
 *   php /ruta/back/cron/solicitudes-avisos.php lumen
 *   php /ruta/back/cron/solicitudes-avisos.php lumen "2026-08-21 13:40:00"   (probar un momento puntual)
 *
 * Se corre un proceso por tenant porque los archivos config/tenants/*.env.php
 * usan define(): dos tenants en el mismo proceso chocarian con las constantes
 * ya definidas.
 *
 * No pasa por index.php ni por el router, asi que no exige JWT ni X-Tenant:
 * el contexto se fija aqui a partir del argumento de linea de comandos. Por
 * eso tampoco hace falta una ruta publica protegida por token.
 *
 * Cada ocurrencia guarda la notificacion que se le creo, de modo que el
 * barrido de los 5 minutos no repite el mismo aviso.
 */

// Solo CLI: si alguien lo pide por web, se corta.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por linea de comandos.');
}

// El CLI no hereda el php.ini del sitio: la zona horaria se fija aqui.
date_default_timezone_set('America/Bogota');

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);

$raiz = dirname(__DIR__);

// -------------------------------------------------------------------
// Argumentos
// -------------------------------------------------------------------
if ($argc < 2) {
    fwrite(STDERR, "Uso: php solicitudes-avisos.php <tenant> [momento 'AAAA-MM-DD HH:MM:SS']\n");
    exit(1);
}

$tenant = preg_replace('/[^a-z0-9\-_]/i', '', $argv[1]);
if (empty($tenant)) {
    fwrite(STDERR, "Tenant invalido.\n");
    exit(1);
}

$momento = isset($argv[2]) ? $argv[2] : date('Y-m-d H:i:s');
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $momento)) {
    fwrite(STDERR, "Momento invalido. Formato esperado: 'AAAA-MM-DD HH:MM:SS'\n");
    exit(1);
}

// -------------------------------------------------------------------
// Configuracion del tenant
// -------------------------------------------------------------------
$configFile = $raiz . "/config/tenants/{$tenant}.env.php";

if (!file_exists($configFile)) {
    fwrite(STDERR, "No existe configuracion para el tenant: {$tenant}\n");
    exit(1);
}

require $raiz . '/flight/Flight.php';
require_once $configFile;
require_once $raiz . '/services/tenant-context.service.php';

TenantContext::setCodigo($tenant);

// Servicios necesarios. Se cargan explicitamente y no por glob para que el
// cron no arrastre dependencias del entorno web.
require_once $raiz . '/services/uuid.service.php';
require_once $raiz . '/services/jwt.service.php';
require_once $raiz . '/services/push-notification.service.php';
require_once $raiz . '/services/tipos-solicitud.service.php';
require_once $raiz . '/services/tipos-solicitud-cargos.service.php';
require_once $raiz . '/services/solicitudes.service.php';
require_once $raiz . '/services/solicitudes-personas.service.php';
require_once $raiz . '/services/solicitudes-horarios.service.php';
require_once $raiz . '/services/solicitudes-ocurrencias.service.php';
require_once $raiz . '/services/notificaciones-colaboradores.service.php';
require_once $raiz . '/services/notificaciones-colaboradores-destinatarios.service.php';
require_once $raiz . '/services/motor-solicitudes-avisos.service.php';

// -------------------------------------------------------------------
// Conexion
// -------------------------------------------------------------------
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; SET time_zone = '-05:00';",
    PDO::ATTR_STRINGIFY_FETCHES => false,
    PDO::ATTR_EMULATE_PREPARES => false
];

Flight::register('db', 'PDO', array(DB_DSN, DB_USERNAME, DB_PASSWORD, $options));

// -------------------------------------------------------------------
// Ejecucion
// -------------------------------------------------------------------
$marca = date('Y-m-d H:i:s');

try {
    $db = Flight::db();
    $resultado = MotorSolicitudesAvisos::avisarProximas($db, $momento);

    $mensaje = sprintf(
        "[%s] tenant=%s momento=%s evaluadas=%d avisadas=%d sin_responsables=%d",
        $marca,
        $tenant,
        $momento,
        $resultado['evaluadas'],
        $resultado['avisadas'],
        $resultado['sin_responsables']
    );

    echo $mensaje . "\n";
    exit(0);
} catch (Exception $e) {
    $mensaje = sprintf("[%s] tenant=%s momento=%s ERROR: %s", $marca, $tenant, $momento, $e->getMessage());

    fwrite(STDERR, $mensaje . "\n");
    error_log($mensaje);
    exit(1);
}
