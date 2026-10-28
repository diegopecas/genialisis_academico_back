<?php
/**
 * Pasarelas de pago para los links de pago (LinksPago).
 *
 * LinksPago no le habla directo a ningun proveedor: le pide a
 * PasarelasPago::para() la pasarela que tenga configurada el tenant
 * (configuracion_pasarela_pago) y usa la interfaz ProveedorPasarelaPago.
 * Para agregar otro proveedor (Wompi, Mercado Pago...) se escribe su clase
 * aqui con la misma interfaz y se agrega en para() y en
 * ConfiguracionPasarelaPago::PROVEEDORES.
 *
 * La interfaz y las clases van en el mismo archivo a proposito: los
 * servicios se cargan en orden alfabetico y una clase que implementa una
 * interfaz de otro archivo que todavia no se ha cargado revienta.
 */
interface ProveedorPasarelaPago
{
    /**
     * Crea un link de valor cerrado.
     *
     * @param array $link fila de links_pago (referencia, descripcion, valor, correo_destino)
     * @param int|null $vencimiento marca de tiempo (segundos) en que vence, o null si no vence
     * @return array {id_externo, url, error}: error es null si se creo
     */
    public function crearLink($link, $vencimiento);

    /**
     * Consulta un link.
     *
     * @return array {pagado (bool), estado (texto del proveedor), id_transaccion, valor, error}
     */
    public function consultarLink($idExterno);

    /**
     * Valida la firma de un aviso de pago.
     *
     * @return bool|null true si es valida, false si no, null si falta la llave
     */
    public function firmaValida($cuerpo, $firma);
}

class PasarelasPago
{
    /**
     * La pasarela del tenant, o null si no tiene (proveedor 'ninguno').
     *
     * @param array $config ConfiguracionPasarelaPago::paraUso()
     * @return ProveedorPasarelaPago|null
     */
    public static function para($config)
    {
        if ($config['proveedor'] === 'bold') {
            return new PasarelaBold($config);
        }
        return null;
    }
}

/**
 * Links de pago de Bold (API de links: /online/link/v1). La llave de
 * identidad va en el header Authorization: x-api-key. Los avisos traen la
 * firma x-bold-signature (HMAC-SHA256 del cuerpo en base64, en hexadecimal)
 * con la llave secreta; en modo pruebas Bold firma con llave vacia.
 */
class PasarelaBold implements ProveedorPasarelaPago
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function crearLink($link, $vencimiento)
    {
        $cuerpo = array(
            'amount_type' => 'CLOSE',
            'amount' => array(
                'currency' => 'COP',
                'total_amount' => (int) round((float) $link['valor']),
                'tip_amount' => 0,
                'taxes' => array()
            ),
            'reference' => $link['referencia'],
            'description' => mb_strlen($link['descripcion']) >= 2 ? $link['descripcion'] : 'Pago de inscripción'
        );
        if (!empty($link['correo_destino'])) {
            $cuerpo['payer_email'] = $link['correo_destino'];
        }
        if (!empty($this->config['url_retorno'])) {
            $cuerpo['callback_url'] = $this->config['url_retorno'];
        }
        if ($vencimiento !== null) {
            // Bold pide la fecha en nanosegundos desde 1970
            $cuerpo['expiration_date'] = $vencimiento * 1000000000;
        }

        $respuesta = $this->llamar('POST', '/online/link/v1', $cuerpo);
        $payload = isset($respuesta['cuerpo']['payload']) ? $respuesta['cuerpo']['payload'] : null;
        if ($respuesta['error'] !== null || empty($payload['url'])) {
            return array('id_externo' => null, 'url' => null,
                'error' => $respuesta['error'] !== null ? $respuesta['error'] : 'Bold no devolvió el link.');
        }
        return array(
            'id_externo' => isset($payload['payment_link']) ? (string) $payload['payment_link'] : null,
            'url' => (string) $payload['url'],
            'error' => null
        );
    }

    public function consultarLink($idExterno)
    {
        $respuesta = $this->llamar('GET', '/online/link/v1/' . rawurlencode($idExterno), null);
        if ($respuesta['error'] !== null) {
            return array('pagado' => false, 'estado' => null, 'id_transaccion' => null, 'valor' => null, 'error' => $respuesta['error']);
        }
        $datos = isset($respuesta['cuerpo']['payload']) ? $respuesta['cuerpo']['payload'] : $respuesta['cuerpo'];
        $estado = isset($datos['status']) ? $datos['status'] : null;
        return array(
            'pagado' => $estado === 'PAID',
            'estado' => $estado,
            'id_transaccion' => !empty($datos['transaction_id']) ? (string) $datos['transaction_id'] : null,
            'valor' => isset($datos['total']) ? (float) $datos['total'] : null,
            'error' => null
        );
    }

    public function firmaValida($cuerpo, $firma)
    {
        $llave = $this->config['modo_pruebas'] ? '' : $this->config['llave_secreta'];
        if ($llave === null) {
            return null;
        }
        $esperada = hash_hmac('sha256', base64_encode((string) $cuerpo), $llave);
        return $firma !== '' && hash_equals($esperada, (string) $firma);
    }

    /**
     * Llamada al API de Bold con la llave de identidad.
     *
     * @return array {cuerpo, error}: error es null si respondio bien
     */
    private function llamar($metodo, $ruta, $cuerpo)
    {
        if (empty($this->config['url_api']) || empty($this->config['llave_publica'])) {
            return array('cuerpo' => null,
                'error' => 'Bold no está configurado (falta la URL del API o la llave de identidad en Configuración > Pasarela de pagos).');
        }

        $ch = curl_init(rtrim($this->config['url_api'], '/') . $ruta);
        $opciones = array(
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => array('Authorization: x-api-key ' . $this->config['llave_publica'], 'Content-Type: application/json'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30
        );
        if ($cuerpo !== null) {
            $opciones[CURLOPT_POSTFIELDS] = json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opciones);
        $crudo = curl_exec($ch);
        $codigoHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorCurl = curl_error($ch);
        curl_close($ch);

        $respuesta = is_string($crudo) ? json_decode($crudo, true) : null;
        if ($codigoHttp >= 200 && $codigoHttp < 300 && is_array($respuesta) && empty($respuesta['errors'])) {
            return array('cuerpo' => $respuesta, 'error' => null);
        }

        $detalle = $errorCurl !== '' ? $errorCurl : '';
        if (is_array($respuesta) && !empty($respuesta['errors'])) {
            $detalle = json_encode($respuesta['errors'], JSON_UNESCAPED_UNICODE);
        } elseif ($detalle === '' && is_string($crudo)) {
            $detalle = mb_substr($crudo, 0, 300);
        }
        return array('cuerpo' => $respuesta, 'error' => trim($detalle . ' (HTTP ' . $codigoHttp . ')'));
    }
}
