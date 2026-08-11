<?php
/**
 * Liquidacion diaria de intereses de mora. Se ejecuta por CLI desde el cron
 * de cPanel, UN PROCESO POR TENANT.
 *
 *   php /ruta/back/cron/mora-diaria.php lumen
 *   php /ruta/back/cron/mora-diaria.php lumen 2026-08-09   (recalcular un dia)
 *
 * Se corre un proceso por tenant porque los archivos config/tenants/*.env.php
 * usan define(): dos tenants en el mismo proceso chocarian con las constantes
 * ya definidas.
 *
 * No pasa por index.php ni por el router, asi que no exige JWT ni X-Tenant:
 * el contexto se fija aqui a partir del argumento de linea de comandos.
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
    fwrite(STDERR, "Uso: php mora-diaria.php <tenant> [fecha_corte AAAA-MM-DD]\n");
    exit(1);
}

$tenant = preg_replace('/[^a-z0-9\-_]/i', '', $argv[1]);
if (empty($tenant)) {
    fwrite(STDERR, "Tenant invalido.\n");
    exit(1);
}

$fechaCorte = isset($argv[2]) ? $argv[2] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaCorte)) {
    fwrite(STDERR, "Fecha de corte invalida. Formato esperado: AAAA-MM-DD\n");
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
require_once $raiz . '/services/tipos-mora.service.php';
require_once $raiz . '/services/mora-configuracion.service.php';
require_once $raiz . '/services/mora-exenciones.service.php';
require_once $raiz . '/services/mora-causaciones.service.php';
require_once $raiz . '/services/mora-ejecuciones.service.php';
require_once $raiz . '/services/motor-mora.service.php';

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
    $resultado = MotorMora::ejecutar($db, $fechaCorte, 'CRON', null);

    $mensaje = sprintf(
        "[%s] tenant=%s corte=%s evaluadas=%d con_mora=%d total=%s%s",
        $marca,
        $tenant,
        $fechaCorte,
        $resultado['cuentas_evaluadas'],
        $resultado['cuentas_con_mora'],
        number_format($resultado['valor_total_causado'], 2, '.', ''),
        !empty($resultado['mensaje']) ? ' aviso=' . $resultado['mensaje'] : ''
    );

    echo $mensaje . "\n";
    exit(0);
} catch (Exception $e) {
    $mensaje = sprintf("[%s] tenant=%s corte=%s ERROR: %s", $marca, $tenant, $fechaCorte, $e->getMessage());

    fwrite(STDERR, $mensaje . "\n");
    error_log($mensaje);
    exit(1);
}
