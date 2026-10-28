<?php
/**
 * Servicio de correo de cada tenant (correo.genialisis.com). Una fila por
 * tenant. Lo usan los correos que manda Genialisis, por ahora el de la
 * aprobacion de las solicitudes con el link de pago o los datos para
 * transferir.
 *
 * La API key se guarda cifrada (CifradoSecretos) y nunca sale al front: la
 * pantalla solo sabe si hay una guardada y sus ultimos 4 caracteres. Si llega
 * vacia, se conserva la que estaba.
 */
class ConfiguracionCorreo
{
    /**
     * GET /configuracion-correo
     * La configuracion del tenant sin la API key. Arreglo vacio si no existe.
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
     * PUT /configuracion-correo
     * Crea o actualiza la fila del tenant. La API key solo se cambia si llega
     * con valor.
     */
    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $urlApi = self::texto($data, 'url_api', 300);
            if ($urlApi !== null && !filter_var($urlApi, FILTER_VALIDATE_URL)) {
                Flight::json(array('error' => 'La URL del servicio de correo no es válida.'), 400);
                return;
            }
            $responderA = self::texto($data, 'responder_a', 150);
            if ($responderA !== null && !filter_var($responderA, FILTER_VALIDATE_EMAIL)) {
                Flight::json(array('error' => 'El correo para las respuestas no es válido.'), 400);
                return;
            }
            $activo = !empty($data['activo']) ? 1 : 0;

            $apiKey = self::texto($data, 'api_key', 500);
            $actual = self::fila($db);
            $apiKeyGuardada = $apiKey !== null ? CifradoSecretos::cifrar($apiKey) : ($actual ? $actual['api_key'] : null);

            if ($activo && ($urlApi === null || $apiKeyGuardada === null)) {
                Flight::json(array('error' => 'Para activar el correo faltan la URL del servicio o la API key.'), 400);
                return;
            }

            $sentence = $db->prepare("INSERT INTO configuracion_correo
                (id, id_tenant, url_api, api_key, responder_a, activo, id_usuario_actualizo)
                VALUES (:id, :id_tenant, :url_api, :api_key, :responder_a, :activo, :id_usuario)
                ON DUPLICATE KEY UPDATE
                 url_api = VALUES(url_api), api_key = VALUES(api_key), responder_a = VALUES(responder_a),
                 activo = VALUES(activo), id_usuario_actualizo = VALUES(id_usuario_actualizo)");
            $sentence->bindValue(':id', Uuid::generar());
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':url_api', $urlApi);
            $sentence->bindValue(':api_key', $apiKeyGuardada);
            $sentence->bindValue(':responder_a', $responderA);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindValue(':id_usuario', $userData->id ?? null);
            $sentence->execute();

            Flight::json(array(self::paraPantalla(self::fila($db))));
        } catch (Exception $e) {
            error_log('Error al guardar la configuracion del correo: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo guardar la configuración del correo.'), 500);
        }
    }

    /**
     * POST /configuracion-correo/probar  { para }
     * Manda un correo de prueba con la configuracion guardada.
     */
    public static function probar()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $para = isset(Flight::request()->data['para']) ? trim((string) Flight::request()->data['para']) : '';
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            Flight::json(array('error' => 'Escribe un correo válido para la prueba.'), 400);
            return;
        }
        $resultado = self::enviar($db, array($para), 'Correo de prueba de Genialisis',
            '<p>Este es un correo de prueba. Si te llegó, el servicio de correo de la institución está bien configurado.</p>');
        if ($resultado['error'] !== null) {
            Flight::json(array('error' => $resultado['error']), 502);
            return;
        }
        Flight::json(array('ok' => true));
    }

    /**
     * Manda un correo con la configuracion del tenant. Nunca lanza.
     *
     * @param array $para correos de destino
     * @return array {error}: null si salio
     */
    public static function enviar($db, $para, $asunto, $html)
    {
        $config = self::paraUso($db);
        if ($config === null) {
            return array('error' => 'El servicio de correo no está configurado o está apagado (Configuración > Servicio de correo).');
        }

        $cuerpo = array('para' => array_values($para), 'asunto' => $asunto, 'html' => $html);
        if (!empty($config['responder_a'])) {
            $cuerpo['responder_a'] = $config['responder_a'];
        }

        $ch = curl_init($config['url_api']);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => array('X-API-Key: ' . $config['api_key'], 'Content-Type: application/json'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 40
        ));
        $crudo = curl_exec($ch);
        $codigoHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorCurl = curl_error($ch);
        curl_close($ch);

        $respuesta = is_string($crudo) ? json_decode($crudo, true) : null;
        if ($codigoHttp === 200 && !empty($respuesta['ok'])) {
            return array('error' => null);
        }
        $codigo = isset($respuesta['error']['codigo']) ? $respuesta['error']['codigo'] : 'SIN_RESPUESTA';
        $mensaje = isset($respuesta['error']['mensaje']) ? $respuesta['error']['mensaje'] : $errorCurl;
        return array('error' => '[' . $codigo . '] ' . $mensaje . ' (HTTP ' . $codigoHttp . ')');
    }

    /**
     * Configuracion para usar en el back, con la API key descifrada, o null
     * si no existe, esta apagada o le falta algo.
     *
     * @return array|null {url_api, api_key, responder_a}
     */
    public static function paraUso($db)
    {
        $fila = self::fila($db);
        if (!$fila || (int) $fila['activo'] !== 1 || empty($fila['url_api'])) {
            return null;
        }
        $apiKey = CifradoSecretos::descifrar($fila['api_key']);
        if ($apiKey === null || $apiKey === '') {
            return null;
        }
        return array('url_api' => $fila['url_api'], 'api_key' => $apiKey, 'responder_a' => $fila['responder_a']);
    }

    private static function fila($db)
    {
        $stmt = $db->prepare("SELECT * FROM configuracion_correo WHERE id_tenant = :id_tenant LIMIT 1");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    private static function paraPantalla($fila)
    {
        return array(
            'url_api' => $fila['url_api'],
            'responder_a' => $fila['responder_a'],
            'activo' => (int) $fila['activo'],
            'tiene_api_key' => $fila['api_key'] !== null ? 1 : 0,
            'api_key_final' => CifradoSecretos::final4($fila['api_key']),
            'fecha_actualizacion' => $fila['fecha_actualizacion']
        );
    }

    private static function texto($data, $clave, $largo)
    {
        if (!isset($data[$clave]) || $data[$clave] === null || !is_scalar($data[$clave])) {
            return null;
        }
        $valor = trim((string) $data[$clave]);
        return $valor === '' ? null : mb_substr($valor, 0, $largo);
    }
}
