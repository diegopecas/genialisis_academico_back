<?php
/**
 * Pasarela de pagos de cada tenant. Una fila por tenant.
 *
 * proveedor: 'bold' (links de pago con Bold) o 'ninguno' (sin pasarela: al
 * aprobar se manda el correo con las cuentas bancarias para transferir).
 * Sin fila, el tenant queda como 'ninguno'.
 *
 * Las llaves se guardan cifradas (CifradoSecretos) y nunca salen al front:
 * la pantalla solo sabe si hay una guardada y sus ultimos 4 caracteres.
 * Para cambiar una llave se escribe de nuevo; si llega vacia, se conserva
 * la que estaba.
 */
class ConfiguracionPasarelaPago
{
    const PROVEEDORES = array('ninguno', 'bold');

    /**
     * GET /configuracion-pasarela-pago
     * La configuracion del tenant sin las llaves. Arreglo vacio si no existe.
     */
    public static function getByTenant()
    {
        $db = Flight::db();
        $fila = self::fila($db);
        if (!$fila) {
            Flight::json(array());
            return;
        }
        Flight::json(array(self::paraPantalla($fila)));
    }

    /**
     * PUT /configuracion-pasarela-pago
     * Crea o actualiza la fila del tenant. Las llaves solo se cambian si
     * llegan con valor.
     */
    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $proveedor = isset($data['proveedor']) ? (string) $data['proveedor'] : 'ninguno';
            if (!in_array($proveedor, self::PROVEEDORES, true)) {
                Flight::json(array('error' => 'Proveedor no válido.'), 400);
                return;
            }

            $urlApi = self::texto($data, 'url_api', 300);
            $urlRetorno = self::texto($data, 'url_retorno', 300);
            foreach (array('url_api' => $urlApi, 'url_retorno' => $urlRetorno) as $campo => $url) {
                if ($url !== null && !filter_var($url, FILTER_VALIDATE_URL)) {
                    Flight::json(array('error' => 'La ' . ($campo === 'url_api' ? 'URL del API' : 'URL de retorno') . ' no es válida.'), 400);
                    return;
                }
            }

            $vigencia = isset($data['link_vigencia_dias']) && $data['link_vigencia_dias'] !== '' && $data['link_vigencia_dias'] !== null
                ? (int) $data['link_vigencia_dias'] : null;
            if ($vigencia !== null && $vigencia <= 0) {
                $vigencia = null;
            }
            $idTipoPago = self::texto($data, 'id_tipo_pago', 36);
            $modoPruebas = !empty($data['modo_pruebas']) ? 1 : 0;

            // Las llaves llegan en claro solo cuando se escriben de nuevo
            $llavePublica = self::texto($data, 'llave_publica', 500);
            $llaveSecreta = self::texto($data, 'llave_secreta', 500);

            $actual = self::fila($db);
            $publicaGuardada = $llavePublica !== null ? CifradoSecretos::cifrar($llavePublica) : ($actual ? $actual['llave_publica'] : null);
            $secretaGuardada = $llaveSecreta !== null ? CifradoSecretos::cifrar($llaveSecreta) : ($actual ? $actual['llave_secreta'] : null);

            if ($proveedor === 'bold') {
                if ($urlApi === null) {
                    Flight::json(array('error' => 'Falta la URL del API de Bold.'), 400);
                    return;
                }
                if ($publicaGuardada === null) {
                    Flight::json(array('error' => 'Falta la llave de identidad de Bold.'), 400);
                    return;
                }
                if ($secretaGuardada === null && !$modoPruebas) {
                    Flight::json(array('error' => 'Falta la llave secreta de Bold (sin ella no se pueden validar los avisos de pago).'), 400);
                    return;
                }
            }

            $sentence = $db->prepare("INSERT INTO configuracion_pasarela_pago
                (id, id_tenant, proveedor, url_api, llave_publica, llave_secreta, modo_pruebas,
                 id_tipo_pago, link_vigencia_dias, url_retorno, id_usuario_actualizo)
                VALUES
                (:id, :id_tenant, :proveedor, :url_api, :llave_publica, :llave_secreta, :modo_pruebas,
                 :id_tipo_pago, :vigencia, :url_retorno, :id_usuario)
                ON DUPLICATE KEY UPDATE
                 proveedor = VALUES(proveedor), url_api = VALUES(url_api),
                 llave_publica = VALUES(llave_publica), llave_secreta = VALUES(llave_secreta),
                 modo_pruebas = VALUES(modo_pruebas), id_tipo_pago = VALUES(id_tipo_pago),
                 link_vigencia_dias = VALUES(link_vigencia_dias), url_retorno = VALUES(url_retorno),
                 id_usuario_actualizo = VALUES(id_usuario_actualizo)");
            $sentence->bindValue(':id', Uuid::generar());
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':proveedor', $proveedor);
            $sentence->bindValue(':url_api', $urlApi);
            $sentence->bindValue(':llave_publica', $publicaGuardada);
            $sentence->bindValue(':llave_secreta', $secretaGuardada);
            $sentence->bindValue(':modo_pruebas', $modoPruebas, PDO::PARAM_INT);
            $sentence->bindValue(':id_tipo_pago', $idTipoPago);
            $sentence->bindValue(':vigencia', $vigencia, $vigencia === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $sentence->bindValue(':url_retorno', $urlRetorno);
            $sentence->bindValue(':id_usuario', $userData->id ?? null);
            $sentence->execute();

            Flight::json(array(self::paraPantalla(self::fila($db))));
        } catch (Exception $e) {
            error_log('Error al guardar la configuracion de la pasarela de pagos: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo guardar la configuración de la pasarela de pagos.'), 500);
        }
    }

    /**
     * Configuracion para usar en el back, con las llaves descifradas. Nunca
     * se manda al front.
     *
     * @return array {proveedor, url_api, llave_publica, llave_secreta, modo_pruebas,
     *                id_tipo_pago, link_vigencia_dias, url_retorno}
     */
    public static function paraUso($db)
    {
        $fila = self::fila($db);
        if (!$fila) {
            return array('proveedor' => 'ninguno', 'url_api' => null, 'llave_publica' => null, 'llave_secreta' => null,
                'modo_pruebas' => true, 'id_tipo_pago' => null, 'link_vigencia_dias' => null, 'url_retorno' => null);
        }
        return array(
            'proveedor' => $fila['proveedor'],
            'url_api' => $fila['url_api'],
            'llave_publica' => CifradoSecretos::descifrar($fila['llave_publica']),
            'llave_secreta' => CifradoSecretos::descifrar($fila['llave_secreta']),
            'modo_pruebas' => (int) $fila['modo_pruebas'] === 1,
            'id_tipo_pago' => $fila['id_tipo_pago'],
            'link_vigencia_dias' => $fila['link_vigencia_dias'] !== null ? (int) $fila['link_vigencia_dias'] : null,
            'url_retorno' => $fila['url_retorno']
        );
    }

    private static function fila($db)
    {
        $stmt = $db->prepare("SELECT * FROM configuracion_pasarela_pago WHERE id_tenant = :id_tenant LIMIT 1");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /** La fila sin llaves: solo si estan guardadas y sus ultimos 4 caracteres */
    private static function paraPantalla($fila)
    {
        return array(
            'proveedor' => $fila['proveedor'],
            'url_api' => $fila['url_api'],
            'modo_pruebas' => (int) $fila['modo_pruebas'],
            'id_tipo_pago' => $fila['id_tipo_pago'],
            'link_vigencia_dias' => $fila['link_vigencia_dias'],
            'url_retorno' => $fila['url_retorno'],
            'tiene_llave_publica' => $fila['llave_publica'] !== null ? 1 : 0,
            'tiene_llave_secreta' => $fila['llave_secreta'] !== null ? 1 : 0,
            'llave_publica_final' => CifradoSecretos::final4($fila['llave_publica']),
            'llave_secreta_final' => CifradoSecretos::final4($fila['llave_secreta']),
            'fecha_actualizacion' => $fila['fecha_actualizacion']
        );
    }

    /** Texto recortado de la peticion, o null si viene vacio */
    private static function texto($data, $clave, $largo)
    {
        if (!isset($data[$clave]) || $data[$clave] === null || !is_scalar($data[$clave])) {
            return null;
        }
        $valor = trim((string) $data[$clave]);
        return $valor === '' ? null : mb_substr($valor, 0, $largo);
    }
}
