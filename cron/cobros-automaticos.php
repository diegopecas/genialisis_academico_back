<?php
/**
 * Cobro automatico de las cuentas por cobrar. Se ejecuta por CLI desde el
 * cron de cPanel, una vez al dia, UN PROCESO POR TENANT.
 *
 *   php /ruta/back/cron/cobros-automaticos.php sport_tennis
 *
 * Si el tenant tiene prendido cobro_automatico_activo, arma un cobro por
 * estudiante con las cuentas con saldo que vencen entre hoy y dentro de
 * cobro_automatico_dias_antes dias y que no estan en ningun cobro, y le
 * manda el correo con el link de pago (o con las cuentas para transferir
 * si el tenant no tiene pasarela). Ver LinksPago::ejecutarCobrosAutomaticos.
 *
 * Una cuenta que ya esta en un cobro no se vuelve a cobrar, asi que
 * correrlo dos veces el mismo dia no duplica correos.
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

// Carpeta de configuracion segun entorno (define CONFIG_DIR). En el cron de
// cPanel no hay GENIALISIS_ENV, asi que usa config/.
require_once $raiz . '/config-path.php';

// -------------------------------------------------------------------
// Argumentos
// -------------------------------------------------------------------
if ($argc < 2) {
    fwrite(STDERR, "Uso: php cobros-automaticos.php <tenant>\n");
    exit(1);
}

$tenant = preg_replace('/[^a-z0-9\-_]/i', '', $argv[1]);
if (empty($tenant)) {
    fwrite(STDERR, "Tenant invalido.\n");
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
require_once CONFIG_DIR . '/master.env.php';
require_once $raiz . '/services/tenant-context.service.php';

TenantContext::setCodigo($tenant);

// Servicios necesarios. Se cargan explicitamente y no por glob para que el
// cron no arrastre dependencias del entorno web.
require_once $raiz . '/services/uuid.service.php';
require_once $raiz . '/services/cifrado-secretos.service.php';
require_once $raiz . '/services/configuracion-correo.service.php';
require_once $raiz . '/services/configuracion-pasarela-pago.service.php';
require_once $raiz . '/services/pasarelas-pago.service.php';
require_once $raiz . '/services/planes-pago.service.php';
require_once $raiz . '/services/links-pago.service.php';

// -------------------------------------------------------------------
// Conexiones (la master da el nombre de la institucion para el correo)
// -------------------------------------------------------------------
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; SET time_zone = '-05:00';",
    PDO::ATTR_STRINGIFY_FETCHES => false,
    PDO::ATTR_EMULATE_PREPARES => false
];

Flight::register('db', 'PDO', array(DB_DSN, DB_USERNAME, DB_PASSWORD, $options));
Flight::register('db_master', 'PDO', array(DB_MASTER_DSN, DB_MASTER_USERNAME, DB_MASTER_PASSWORD, $options));

// -------------------------------------------------------------------
// Ejecucion
// -------------------------------------------------------------------
$marca = date('Y-m-d H:i:s');

try {
    $db = Flight::db();
    $r = LinksPago::ejecutarCobrosAutomaticos($db);

    if (!$r['activo']) {
        echo sprintf("[%s] tenant=%s cobro automatico apagado\n", $marca, $tenant);
        exit(0);
    }

    echo sprintf(
        "[%s] tenant=%s dias=%d cobros=%d cuentas=%d enviados=%d sin_correo=%d errores=%d\n",
        $marca,
        $tenant,
        $r['dias'],
        $r['cobros'],
        $r['cuentas'],
        $r['enviados'],
        $r['sin_correo'],
        $r['errores']
    );
    exit($r['errores'] > 0 ? 1 : 0);
} catch (Exception $e) {
    $mensaje = sprintf("[%s] tenant=%s ERROR: %s", $marca, $tenant, $e->getMessage());

    fwrite(STDERR, $mensaje . "\n");
    error_log($mensaje);
    exit(1);
}
