<?php
/**
 * Envío diario de los reportes por correo a los colaboradores. Se ejecuta
 * por CLI desde el cron de cPanel, UN PROCESO POR TENANT.
 *
 *   php /ruta/back/cron/reportes-correo.php lumen
 *   php /ruta/back/cron/reportes-correo.php lumen 2026-10-28   (reenviar un dia)
 *
 * Se corre un proceso por tenant porque los archivos config/tenants/*.env.php
 * usan define(): dos tenants en el mismo proceso chocarian con las constantes
 * ya definidas.
 *
 * No pasa por index.php ni por el router, asi que no exige JWT ni X-Tenant:
 * el contexto se fija aqui a partir del argumento de linea de comandos.
 *
 * Un reporte que ya salio bien ese dia para el mismo colaborador no se
 * vuelve a enviar, asi que correrlo dos veces no duplica correos.
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

// Carpeta de configuracion segun entorno (define CONFIG_DIR). En el cron de
// cPanel no hay GENIALISIS_ENV, asi que usa config/.
require_once $raiz . '/config-path.php';

// -------------------------------------------------------------------
// Argumentos
// -------------------------------------------------------------------
if ($argc < 2) {
    fwrite(STDERR, "Uso: php reportes-correo.php <tenant> [fecha AAAA-MM-DD]\n");
    exit(1);
}

$tenant = preg_replace('/[^a-z0-9\-_]/i', '', $argv[1]);
if (empty($tenant)) {
    fwrite(STDERR, "Tenant invalido.\n");
    exit(1);
}

$fecha = isset($argv[2]) ? $argv[2] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    fwrite(STDERR, "Fecha invalida. Formato esperado: AAAA-MM-DD\n");
    exit(1);
}

// -------------------------------------------------------------------
// Configuracion del tenant
// -------------------------------------------------------------------
$configFile = CONFIG_DIR . "/tenants/{$tenant}.env.php";

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
require_once $raiz . '/services/cifrado-secretos.service.php';
require_once $raiz . '/services/configuracion-correo.service.php';
require_once $raiz . '/services/dashboard-gerencial.service.php';
require_once $raiz . '/services/motor-reportes-correo.service.php';

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
    $resultado = MotorReportesCorreo::ejecutar($db, $fecha);

    $mensaje = sprintf(
        "[%s] tenant=%s fecha=%s evaluados=%d enviados=%d fallidos=%d omitidos=%d",
        $marca,
        $tenant,
        $fecha,
        $resultado['evaluados'],
        $resultado['enviados'],
        $resultado['fallidos'],
        $resultado['omitidos']
    );

    echo $mensaje . "\n";
    exit(0);
} catch (Exception $e) {
    $mensaje = sprintf("[%s] tenant=%s fecha=%s ERROR: %s", $marca, $tenant, $fecha, $e->getMessage());

    fwrite(STDERR, $mensaje . "\n");
    error_log($mensaje);
    exit(1);
}
