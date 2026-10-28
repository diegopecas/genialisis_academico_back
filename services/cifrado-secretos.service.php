<?php
/**
 * Cifrado de los secretos que se guardan en la base (llaves de pasarelas de
 * pago, del servicio de correo, etc.).
 *
 * AES-256-GCM con la llave maestra LLAVE_CIFRADO_SECRETOS, que vive en
 * config/cifrado.env.php (fuera de la base y del repositorio). El texto
 * cifrado queda como "v1:" + base64(iv de 12 bytes + tag de 16 bytes +
 * texto cifrado). El prefijo permite cambiar el formato mas adelante sin
 * romper lo que ya esta guardado.
 *
 * Cada base usa su propia llave maestra: config y config_qa (las dos van a
 * g_fundadores_qa) comparten la misma; prod y demo tienen la suya. Si se
 * restaura una base en otro ambiente, los secretos no se pueden leer y hay
 * que volver a escribirlos desde la pantalla.
 */
class CifradoSecretos
{
    const PREFIJO = 'v1:';

    /**
     * @param string $texto
     * @return string texto cifrado listo para guardar
     * @throws Exception si no hay llave maestra
     */
    public static function cifrar($texto)
    {
        $llave = self::llave();
        if ($llave === null) {
            throw new Exception('No está configurada la llave de cifrado (config/cifrado.env.php).');
        }

        $iv = random_bytes(12);
        $tag = '';
        $cifrado = openssl_encrypt((string) $texto, 'aes-256-gcm', $llave, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cifrado === false) {
            throw new Exception('No se pudo cifrar el secreto.');
        }
        return self::PREFIJO . base64_encode($iv . $tag . $cifrado);
    }

    /**
     * @param string|null $guardado lo que esta en la base
     * @return string|null el secreto, o null si esta vacio o no se puede leer
     *                     (otra llave maestra, texto danado)
     */
    public static function descifrar($guardado)
    {
        if ($guardado === null || $guardado === '') {
            return null;
        }
        if (strpos($guardado, self::PREFIJO) !== 0) {
            error_log('CifradoSecretos: el secreto guardado no tiene el formato esperado.');
            return null;
        }
        $llave = self::llave();
        if ($llave === null) {
            error_log('CifradoSecretos: no está configurada la llave de cifrado (config/cifrado.env.php).');
            return null;
        }

        $crudo = base64_decode(substr($guardado, strlen(self::PREFIJO)), true);
        if ($crudo === false || strlen($crudo) < 29) {
            error_log('CifradoSecretos: el secreto guardado está dañado.');
            return null;
        }
        $iv = substr($crudo, 0, 12);
        $tag = substr($crudo, 12, 16);
        $texto = openssl_decrypt(substr($crudo, 28), 'aes-256-gcm', $llave, OPENSSL_RAW_DATA, $iv, $tag);
        if ($texto === false) {
            error_log('CifradoSecretos: no se pudo descifrar (la llave maestra no corresponde a esta base).');
            return null;
        }
        return $texto;
    }

    /** Ultimos 4 caracteres, para mostrar en pantalla que hay una llave guardada */
    public static function final4($guardado)
    {
        $texto = self::descifrar($guardado);
        return $texto !== null && $texto !== '' ? substr($texto, -4) : null;
    }

    /** Llave maestra de 32 bytes, o null si no esta configurada */
    private static function llave()
    {
        if (!defined('LLAVE_CIFRADO_SECRETOS') && defined('CONFIG_DIR') && file_exists(CONFIG_DIR . '/cifrado.env.php')) {
            require_once CONFIG_DIR . '/cifrado.env.php';
        }
        if (!defined('LLAVE_CIFRADO_SECRETOS') || LLAVE_CIFRADO_SECRETOS === '') {
            return null;
        }
        $llave = base64_decode(LLAVE_CIFRADO_SECRETOS, true);
        return $llave !== false && strlen($llave) === 32 ? $llave : null;
    }
}
