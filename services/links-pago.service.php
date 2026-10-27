<?php
/**
 * Links de pago que se le mandan a la persona al aprobar una solicitud de
 * inscripcion del portal web (por ahora con Bold).
 *
 * Flujo:
 * 1. La aprobacion crea las cuentas por cobrar y registra el link en estado
 *    pendiente (registrar), dentro de su transaccion.
 * 2. Despues del commit se crea el link en Bold y se envia el correo con el
 *    boton de pago (generarYEnviar). Si algo falla queda el error en el link
 *    y se reintenta desde la solicitud.
 * 3. Cuando la persona paga, Bold avisa al webhook (webhookBold) o se
 *    consulta a mano (verificar). Se registra el pago recibido aplicado a las
 *    cuentas del link y, si el plan es por sesiones, el paquete. Un mismo
 *    pago nunca se registra dos veces.
 *
 * Configuracion en el .env.php del tenant:
 * - BOLD_API_URL, BOLD_API_KEY (llave de identidad), BOLD_SECRET_KEY (llave
 *   secreta para validar la firma del webhook), BOLD_MODO_PRUEBAS (true: la
 *   firma de pruebas se valida con llave vacia, como lo hace Bold) y
 *   BOLD_CALLBACK_URL (opcional: a donde vuelve la persona despues de pagar).
 * - CORREO_API_URL, CORREO_API_KEY y CORREO_RESPONDER_A (opcional): el
 *   servicio de correo de Genialisis (correo.genialisis.com).
 *
 * Parametros en configuracion_global: bold_id_tipo_pago (tipo de pago con el
 * que se registran) y bold_link_vigencia_dias (vacio = el link no vence).
 */
class LinksPago
{
    const PREFIJO_REFERENCIA = 'GEN-';

    // ==================== DESDE LA APROBACION ====================

    /**
     * Registra el link en estado pendiente con las cuentas que cubre. No
     * llama a Bold ni abre transaccion: corre dentro de la aprobacion.
     *
     * @param array $detalle [ {id_cuenta_por_cobrar, id_tarifa_curso_extra_detalle} ]
     * @return string id del link
     */
    public static function registrar($db, $id_solicitud, $id_inscripcion, $nombre_curso, $valor, $correo_destino, $id_usuario, $detalle)
    {
        $id = Uuid::generar();
        $descripcion = mb_substr(self::nombreInstitucion() . ' · ' . $nombre_curso, 0, 100);

        $sentence = $db->prepare("INSERT INTO links_pago
            (id, id_tenant, proveedor, id_solicitud_inscripcion_publica, id_estudiante_x_curso_extra,
             referencia, descripcion, valor, estado, correo_destino, id_usuario)
            VALUES
            (:id, :id_tenant, 'bold', :id_solicitud, :id_inscripcion,
             :referencia, :descripcion, :valor, 'pendiente', :correo, :id_usuario)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_solicitud', $id_solicitud);
        $sentence->bindValue(':id_inscripcion', $id_inscripcion);
        $sentence->bindValue(':referencia', self::PREFIJO_REFERENCIA . $id);
        $sentence->bindValue(':descripcion', $descripcion);
        $sentence->bindValue(':valor', $valor);
        $sentence->bindValue(':correo', $correo_destino);
        $sentence->bindValue(':id_usuario', $id_usuario);
        $sentence->execute();

        $stmtDetalle = $db->prepare("INSERT INTO links_pago_detalle
            (id, id_tenant, id_link_pago, id_cuenta_por_cobrar, id_tarifa_curso_extra_detalle)
            VALUES (:id, :id_tenant, :id_link, :id_cuenta, :id_tarifa_detalle)");
        foreach ($detalle as $linea) {
            $stmtDetalle->bindValue(':id', Uuid::generar());
            $stmtDetalle->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtDetalle->bindValue(':id_link', $id);
            $stmtDetalle->bindValue(':id_cuenta', $linea['id_cuenta_por_cobrar']);
            $stmtDetalle->bindValue(':id_tarifa_detalle', $linea['id_tarifa_curso_extra_detalle']);
            $stmtDetalle->execute();
        }

        return $id;
    }

    /**
     * Crea el link en Bold (si todavia no existe) y envia el correo. Nunca
     * lanza: lo que falle queda en el link.
     *
     * @return array estado, url, correo_enviado, error, error_correo
     */
    public static function generarYEnviar($db, $id_link)
    {
        $link = self::obtener($db, $id_link);
        if (!$link) {
            return array('estado' => 'error', 'error' => 'El link no existe.');
        }

        if (in_array($link['estado'], array('pendiente', 'error'), true)) {
            self::crearEnBold($db, $link);
            $link = self::obtener($db, $id_link);
        }

        $correoEnviado = false;
        if ($link['estado'] === 'activo') {
            $correoEnviado = self::enviarCorreo($db, $link);
            $link = self::obtener($db, $id_link);
        }

        return array(
            'id' => $link['id'],
            'estado' => $link['estado'],
            'url' => $link['url'],
            'correo_enviado' => $correoEnviado,
            'error' => $link['ultimo_error'],
            'error_correo' => $link['error_correo']
        );
    }

    // ==================== ENDPOINTS ====================

    /**
     * GET /links-pago/solicitud/@id_solicitud
     * Links de una solicitud, el mas reciente primero.
     */
    public static function getBySolicitud($id_solicitud)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, proveedor, referencia, descripcion, valor, estado, id_link_externo, url,
                   fecha_vencimiento, ultimo_error, id_transaccion_externa, id_pago_recibido, fecha_pago,
                   correo_destino, fecha_correo, error_correo, fecha_registro
            FROM links_pago
            WHERE id_solicitud_inscripcion_publica = :id_solicitud AND id_tenant = :id_tenant
            ORDER BY fecha_registro DESC");
        $sentence->bindParam(':id_solicitud', $id_solicitud);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * PUT /links-pago/generar  { id }
     * Reintenta crear el link en Bold (si quedo pendiente o con error) y
     * envia el correo.
     */
    public static function generar()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $link = $id ? self::obtener($db, $id) : null;
        if (!$link) {
            Flight::json(array('error' => 'El link no existe.'), 404);
            return;
        }
        if (!in_array($link['estado'], array('pendiente', 'error'), true)) {
            Flight::json(array('error' => 'El link ya está creado en Bold.'), 400);
            return;
        }
        Flight::json(self::generarYEnviar($db, $id));
    }

    /**
     * PUT /links-pago/reenviar-correo  { id }
     */
    public static function reenviarCorreo()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $link = $id ? self::obtener($db, $id) : null;
        if (!$link) {
            Flight::json(array('error' => 'El link no existe.'), 404);
            return;
        }
        if ($link['estado'] !== 'activo') {
            Flight::json(array('error' => 'Solo se reenvía el correo de un link activo (sin pagar).'), 400);
            return;
        }
        $enviado = self::enviarCorreo($db, $link);
        $link = self::obtener($db, $id);
        if (!$enviado) {
            Flight::json(array('error' => 'No salió el correo: ' . $link['error_correo']), 502);
            return;
        }
        Flight::json(array('id' => $id, 'correo_enviado' => true));
    }

    /**
     * PUT /links-pago/verificar  { id }
     * Consulta el link en Bold. Si ya esta pagado y el pago no se ha
     * registrado (por ejemplo, porque el aviso de Bold no llego), lo registra.
     */
    public static function verificar()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $link = $id ? self::obtener($db, $id) : null;
        if (!$link) {
            Flight::json(array('error' => 'El link no existe.'), 404);
            return;
        }
        if ($link['estado'] === 'pagado') {
            Flight::json(array('id' => $id, 'estado' => 'pagado', 'mensaje' => 'El pago ya estaba registrado.'));
            return;
        }
        if (empty($link['id_link_externo'])) {
            Flight::json(array('error' => 'El link todavía no está creado en Bold.'), 400);
            return;
        }

        $respuesta = self::llamarBold('GET', '/online/link/v1/' . rawurlencode($link['id_link_externo']), null);
        if ($respuesta['error'] !== null) {
            Flight::json(array('error' => 'No se pudo consultar Bold: ' . $respuesta['error']), 502);
            return;
        }

        $datos = isset($respuesta['cuerpo']['payload']) ? $respuesta['cuerpo']['payload'] : $respuesta['cuerpo'];
        $estadoBold = isset($datos['status']) ? $datos['status'] : null;
        if ($estadoBold !== 'PAID') {
            Flight::json(array('id' => $id, 'estado' => $link['estado'], 'estado_bold' => $estadoBold,
                'mensaje' => 'Bold todavía no tiene el pago (estado ' . ($estadoBold ?: 'desconocido') . ').'));
            return;
        }

        $idTransaccion = !empty($datos['transaction_id']) ? (string) $datos['transaction_id'] : $link['id_link_externo'];
        $valor = isset($datos['total']) ? (float) $datos['total'] : (float) $link['valor'];
        $resultado = self::registrarPago($db, $id, $idTransaccion, $valor, 'consulta');
        if (isset($resultado['error'])) {
            Flight::json(array('error' => $resultado['error']), 500);
            return;
        }
        Flight::json(array('id' => $id, 'estado' => 'pagado', 'estado_bold' => $estadoBold,
            'mensaje' => !empty($resultado['ya_pagado']) ? 'El pago ya estaba registrado.' : 'Pago registrado.'));
    }

    /**
     * POST /pagos-bold/webhook?tenant=codigo
     * Aviso de Bold. Es publico (sin token): se valida la firma
     * x-bold-signature (HMAC-SHA256 del cuerpo en base64, en hexadecimal) con
     * la llave secreta. Bold espera un 200 en menos de 2 segundos; si no lo
     * recibe, reintenta.
     */
    public static function webhookBold()
    {
        $cuerpo = (string) Flight::request()->getBody();
        $firma = isset($_SERVER['HTTP_X_BOLD_SIGNATURE']) ? (string) $_SERVER['HTTP_X_BOLD_SIGNATURE'] : '';

        $llave = (defined('BOLD_MODO_PRUEBAS') && BOLD_MODO_PRUEBAS) ? '' : (defined('BOLD_SECRET_KEY') ? (string) BOLD_SECRET_KEY : null);
        if ($llave === null) {
            error_log('LinksPago: llego un aviso de Bold pero BOLD_SECRET_KEY no esta configurada.');
            Flight::json(array('error' => 'Bold no está configurado.'), 503);
            return;
        }
        $esperada = hash_hmac('sha256', base64_encode($cuerpo), $llave);
        if ($firma === '' || !hash_equals($esperada, $firma)) {
            error_log('LinksPago: aviso de Bold con firma invalida.');
            Flight::json(array('error' => 'Firma inválida.'), 401);
            return;
        }

        $evento = json_decode($cuerpo, true);
        if (!is_array($evento)) {
            Flight::json(array('ok' => true));
            return;
        }

        $tipo = isset($evento['type']) ? $evento['type'] : '';
        $datos = isset($evento['data']) && is_array($evento['data']) ? $evento['data'] : array();
        $referencia = isset($datos['metadata']['reference']) ? (string) $datos['metadata']['reference'] : '';
        $idTransaccion = isset($datos['payment_id']) ? (string) $datos['payment_id'] : (isset($evento['subject']) ? (string) $evento['subject'] : '');

        if ($tipo !== 'SALE_APPROVED') {
            // Rechazos y anulaciones solo quedan en el log: el link sigue
            // activo para que la persona vuelva a intentar.
            error_log('LinksPago: aviso de Bold ' . $tipo . ' (referencia ' . $referencia . ', pago ' . $idTransaccion . ').');
            Flight::json(array('ok' => true));
            return;
        }

        $db = Flight::db();
        $link = null;
        if ($referencia !== '') {
            $stmt = $db->prepare("SELECT id FROM links_pago WHERE referencia = :referencia AND id_tenant = :id_tenant");
            $stmt->bindValue(':referencia', $referencia);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $link = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$link) {
            error_log('LinksPago: pago aprobado de Bold sin link conocido (referencia ' . $referencia . ', pago ' . $idTransaccion . ').');
            Flight::json(array('ok' => true));
            return;
        }

        $valor = isset($datos['amount']['total']) ? (float) $datos['amount']['total'] : null;
        $resultado = self::registrarPago($db, $link['id'], $idTransaccion, $valor, 'webhook');
        if (isset($resultado['error'])) {
            // Sin 200 Bold reintenta mas tarde
            Flight::json(array('error' => $resultado['error']), 500);
            return;
        }
        Flight::json(array('ok' => true));
    }

    // ==================== PAGO ====================

    /**
     * Registra el pago del link: pago recibido aplicado a sus cuentas,
     * paquetes de sesiones de las lineas por paquete y el link en pagado. Si
     * el link ya estaba pagado no hace nada.
     *
     * @param float|null $valor lo que reporta Bold; si no viene, el del link
     * @return array {ok} | {ok, ya_pagado} | {error}
     */
    private static function registrarPago($db, $id_link, $id_transaccion, $valor, $origen)
    {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT lp.*, exce.id_estudiante, exce.id_curso_extra, ce.fecha_inicio AS curso_fecha_inicio
                FROM links_pago lp
                INNER JOIN estudiantes_x_cursos_extra exce ON exce.id = lp.id_estudiante_x_curso_extra AND exce.id_tenant = lp.id_tenant
                INNER JOIN cursos_extra ce ON ce.id = exce.id_curso_extra AND ce.id_tenant = lp.id_tenant
                WHERE lp.id = :id AND lp.id_tenant = :id_tenant
                FOR UPDATE");
            $stmt->bindValue(':id', $id_link);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $link = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$link) {
                $db->rollBack();
                return array('error' => 'El link o su inscripción no existen.');
            }
            if ($link['estado'] === 'pagado') {
                $db->commit();
                return array('ok' => true, 'ya_pagado' => true);
            }

            $stmtCuentas = $db->prepare("SELECT lpd.id_cuenta_por_cobrar, lpd.id_tarifa_curso_extra_detalle, c.valor
                FROM links_pago_detalle lpd
                INNER JOIN cuentas_por_cobrar c ON c.id = lpd.id_cuenta_por_cobrar AND c.id_tenant = lpd.id_tenant
                WHERE lpd.id_link_pago = :id_link AND lpd.id_tenant = :id_tenant
                ORDER BY c.valor DESC");
            $stmtCuentas->bindValue(':id_link', $id_link);
            $stmtCuentas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtCuentas->execute();
            $lineas = $stmtCuentas->fetchAll(PDO::FETCH_ASSOC);

            $valorPagado = $valor !== null ? (float) $valor : (float) $link['valor'];

            // Se aplica a cada cuenta hasta donde alcance lo pagado
            $restante = $valorPagado;
            $cuentas = array();
            foreach ($lineas as $linea) {
                $aplicar = min((float) $linea['valor'], $restante);
                if ($aplicar <= 0) {
                    break;
                }
                $cuentas[] = array('id_cuenta_por_cobrar' => $linea['id_cuenta_por_cobrar'], 'valor_aplicado' => $aplicar);
                $restante -= $aplicar;
            }

            $stmtAcudiente = $db->prepare("SELECT id FROM acudientes
                WHERE id_estudiante = :id_estudiante AND es_responsable_pago = 1 AND activo = 1 AND id_tenant = :id_tenant
                LIMIT 1");
            $stmtAcudiente->bindValue(':id_estudiante', $link['id_estudiante']);
            $stmtAcudiente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtAcudiente->execute();
            $acudiente = $stmtAcudiente->fetch(PDO::FETCH_ASSOC);

            $idTipoPago = self::parametro($db, 'bold_id_tipo_pago');
            if (!$idTipoPago) {
                error_log('LinksPago: falta el parametro bold_id_tipo_pago; el pago queda sin tipo.');
            }

            $idPago = PagosRecibidos::registrarDesdePasarela($db, array(
                'fecha' => date('Y-m-d H:i:s'),
                'id_estudiante' => $link['id_estudiante'],
                'id_acudiente' => $acudiente ? $acudiente['id'] : null,
                'id_tipo_pago' => $idTipoPago ?: null,
                'valor_recibido' => $valorPagado,
                'observaciones' => 'Pago con Bold (' . $origen . ') · ' . $link['descripcion'] .
                    ($link['id_link_externo'] ? ' · ' . $link['id_link_externo'] : ''),
                'referencia_bancaria' => mb_substr($id_transaccion, 0, 100)
            ), $cuentas);

            // Paquete de sesiones por cada linea de tipo paquete
            // Un curso sin fecha de inicio es permanente: el paquete arranca hoy.
            $fechaInicio = $link['curso_fecha_inicio'] !== null ? max(date('Y-m-d'), $link['curso_fecha_inicio']) : date('Y-m-d');
            $stmtLinea = $db->prepare("SELECT d.numero_sesiones, d.vigencia_dias, tcp.codigo AS codigo_tipo_cobro
                FROM tarifas_cursos_extra_detalle d
                INNER JOIN productos_servicios ps ON ps.id = d.id_producto_servicio AND ps.id_tenant = d.id_tenant
                LEFT JOIN tipos_cobro_producto tcp ON tcp.id = ps.id_tipo_cobro
                WHERE d.id = :id AND d.id_tenant = :id_tenant");
            foreach ($lineas as $linea) {
                if (empty($linea['id_tarifa_curso_extra_detalle'])) {
                    continue;
                }
                $stmtLinea->bindValue(':id', $linea['id_tarifa_curso_extra_detalle']);
                $stmtLinea->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtLinea->execute();
                $tarifa = $stmtLinea->fetch(PDO::FETCH_ASSOC);
                if ($tarifa && $tarifa['codigo_tipo_cobro'] === 'PAQUETE' && (int) $tarifa['numero_sesiones'] > 0) {
                    PaquetesEstudiantesCursosExtra::crearDesdeTarifa(
                        $db,
                        $link['id_estudiante_x_curso_extra'],
                        $tarifa['numero_sesiones'],
                        $tarifa['vigencia_dias'],
                        $fechaInicio,
                        $linea['id_cuenta_por_cobrar'],
                        'Pago con Bold'
                    );
                }
            }

            $stmtPagado = $db->prepare("UPDATE links_pago SET
                    estado = 'pagado', id_transaccion_externa = :id_transaccion, id_pago_recibido = :id_pago,
                    fecha_pago = NOW(), ultimo_error = NULL
                WHERE id = :id AND id_tenant = :id_tenant");
            $stmtPagado->bindValue(':id_transaccion', mb_substr($id_transaccion, 0, 60));
            $stmtPagado->bindValue(':id_pago', $idPago);
            $stmtPagado->bindValue(':id', $id_link);
            $stmtPagado->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtPagado->execute();

            $db->commit();
            return array('ok' => true);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('LinksPago: no se pudo registrar el pago del link ' . $id_link . ': ' . $e->getMessage());
            return array('error' => 'No se pudo registrar el pago.');
        }
    }

    // ==================== BOLD ====================

    /**
     * Crea el link de valor cerrado en Bold y deja el resultado en el link.
     */
    private static function crearEnBold($db, $link)
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
        if (defined('BOLD_CALLBACK_URL') && BOLD_CALLBACK_URL !== '') {
            $cuerpo['callback_url'] = BOLD_CALLBACK_URL;
        }
        $vencimiento = null;
        $dias = self::parametroNumero($db, 'bold_link_vigencia_dias');
        if ($dias !== null && $dias > 0) {
            $marca = time() + (int) $dias * 86400;
            $vencimiento = date('Y-m-d H:i:s', $marca);
            // Bold pide la fecha en nanosegundos desde 1970
            $cuerpo['expiration_date'] = $marca * 1000000000;
        }

        $respuesta = self::llamarBold('POST', '/online/link/v1', $cuerpo);
        $payload = isset($respuesta['cuerpo']['payload']) ? $respuesta['cuerpo']['payload'] : null;
        if ($respuesta['error'] !== null || empty($payload['url'])) {
            $error = $respuesta['error'] !== null ? $respuesta['error'] : 'Bold no devolvió el link.';
            error_log('LinksPago: no se creo el link ' . $link['id'] . ' en Bold: ' . $error);
            $stmt = $db->prepare("UPDATE links_pago SET estado = 'error', ultimo_error = :error WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindValue(':error', mb_substr($error, 0, 2000));
            $stmt->bindValue(':id', $link['id']);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            return;
        }

        $stmt = $db->prepare("UPDATE links_pago SET estado = 'activo', id_link_externo = :id_externo, url = :url,
                fecha_vencimiento = :vencimiento, ultimo_error = NULL
            WHERE id = :id AND id_tenant = :id_tenant");
        $stmt->bindValue(':id_externo', isset($payload['payment_link']) ? (string) $payload['payment_link'] : null);
        $stmt->bindValue(':url', (string) $payload['url']);
        $stmt->bindValue(':vencimiento', $vencimiento);
        $stmt->bindValue(':id', $link['id']);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Llamada al API de Bold con la llave de identidad.
     *
     * @return array {cuerpo, error}: error es null si respondio bien
     */
    private static function llamarBold($metodo, $ruta, $cuerpo)
    {
        if (!defined('BOLD_API_URL') || BOLD_API_URL === '' || !defined('BOLD_API_KEY') || BOLD_API_KEY === '') {
            return array('cuerpo' => null, 'error' => 'Bold no está configurado (BOLD_API_URL o BOLD_API_KEY).');
        }

        $ch = curl_init(rtrim(BOLD_API_URL, '/') . $ruta);
        $opciones = array(
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => array('Authorization: x-api-key ' . BOLD_API_KEY, 'Content-Type: application/json'),
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

    // ==================== CORREO ====================

    /**
     * Correo a la persona con los horarios asignados, lo que va a pagar y el
     * boton de pago. Deja la fecha o el error en el link.
     *
     * @return bool true si salio
     */
    private static function enviarCorreo($db, $link)
    {
        $error = null;
        if (empty($link['correo_destino'])) {
            $error = 'La solicitud no tiene correo.';
        } elseif (!defined('CORREO_API_URL') || CORREO_API_URL === '' || !defined('CORREO_API_KEY') || CORREO_API_KEY === '') {
            $error = 'El servicio de correo no está configurado (CORREO_API_URL o CORREO_API_KEY).';
        }

        if ($error === null) {
            $datos = self::datosCorreo($db, $link);
            $cuerpo = array(
                'para' => array($link['correo_destino']),
                'asunto' => 'Tu inscripción a ' . $datos['curso'] . ' fue aprobada',
                'html' => self::htmlCorreo($datos, $link)
            );
            if (defined('CORREO_RESPONDER_A') && filter_var(CORREO_RESPONDER_A, FILTER_VALIDATE_EMAIL)) {
                $cuerpo['responder_a'] = CORREO_RESPONDER_A;
            }

            $ch = curl_init(CORREO_API_URL);
            curl_setopt_array($ch, array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => array('X-API-Key: ' . CORREO_API_KEY, 'Content-Type: application/json'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 40
            ));
            $crudo = curl_exec($ch);
            $codigoHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errorCurl = curl_error($ch);
            curl_close($ch);

            $respuesta = is_string($crudo) ? json_decode($crudo, true) : null;
            if (!($codigoHttp === 200 && !empty($respuesta['ok']))) {
                $codigo = isset($respuesta['error']['codigo']) ? $respuesta['error']['codigo'] : 'SIN_RESPUESTA';
                $mensaje = isset($respuesta['error']['mensaje']) ? $respuesta['error']['mensaje'] : $errorCurl;
                $error = '[' . $codigo . '] ' . $mensaje . ' (HTTP ' . $codigoHttp . ')';
            }
        }

        if ($error !== null) {
            error_log('LinksPago: no salio el correo del link ' . $link['id'] . ': ' . $error);
            $stmt = $db->prepare("UPDATE links_pago SET error_correo = :error WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindValue(':error', mb_substr($error, 0, 2000));
        } else {
            $stmt = $db->prepare("UPDATE links_pago SET fecha_correo = NOW(), error_correo = NULL WHERE id = :id AND id_tenant = :id_tenant");
        }
        $stmt->bindValue(':id', $link['id']);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();

        return $error === null;
    }

    /**
     * Lo que va en el correo: a quien se saluda, el participante, el curso,
     * los horarios asignados y las lineas que se cobran.
     */
    private static function datosCorreo($db, $link)
    {
        $stmt = $db->prepare("SELECT ce.nombre AS curso,
                   TRIM(CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.primer_apellido, ''))) AS participante,
                   s.acu_primer_nombre, s.est_primer_nombre
            FROM estudiantes_x_cursos_extra exce
            INNER JOIN cursos_extra ce ON ce.id = exce.id_curso_extra AND ce.id_tenant = exce.id_tenant
            INNER JOIN estudiantes e ON e.id = exce.id_estudiante AND e.id_tenant = exce.id_tenant
            INNER JOIN personas p ON p.id = e.id_persona AND p.id_tenant = exce.id_tenant
            LEFT JOIN solicitudes_inscripcion_publica s ON s.id = :id_solicitud AND s.id_tenant = exce.id_tenant
            WHERE exce.id = :id_inscripcion AND exce.id_tenant = :id_tenant");
        $stmt->bindValue(':id_solicitud', $link['id_solicitud_inscripcion_publica']);
        $stmt->bindValue(':id_inscripcion', $link['id_estudiante_x_curso_extra']);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: array();

        $stmtHorarios = $db->prepare("SELECT ds.nombre AS dia, hce.hora_inicial, hce.hora_final
            FROM reservas_fijas_cursos_extra rf
            INNER JOIN horarios_cursos_extra hce ON hce.id = rf.id_horario_curso_extra AND hce.id_tenant = rf.id_tenant
            INNER JOIN dias_semana ds ON ds.id = hce.id_dia_semana
            WHERE rf.id_estudiante_x_curso_extra = :id_inscripcion AND rf.activo = 1
              AND (rf.fecha_hasta IS NULL OR rf.fecha_hasta >= CURDATE()) AND rf.id_tenant = :id_tenant
            ORDER BY hce.id_dia_semana, hce.hora_inicial");
        $stmtHorarios->bindValue(':id_inscripcion', $link['id_estudiante_x_curso_extra']);
        $stmtHorarios->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtHorarios->execute();

        $stmtLineas = $db->prepare("SELECT c.detalle, c.valor
            FROM links_pago_detalle lpd
            INNER JOIN cuentas_por_cobrar c ON c.id = lpd.id_cuenta_por_cobrar AND c.id_tenant = lpd.id_tenant
            WHERE lpd.id_link_pago = :id_link AND lpd.id_tenant = :id_tenant");
        $stmtLineas->bindValue(':id_link', $link['id']);
        $stmtLineas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtLineas->execute();

        $saludo = !empty($fila['acu_primer_nombre']) ? $fila['acu_primer_nombre']
            : (!empty($fila['est_primer_nombre']) ? $fila['est_primer_nombre'] : '');

        return array(
            'institucion' => self::nombreInstitucion(),
            'saludo' => $saludo,
            'participante' => isset($fila['participante']) ? $fila['participante'] : '',
            'curso' => isset($fila['curso']) ? $fila['curso'] : '',
            'horarios' => $stmtHorarios->fetchAll(PDO::FETCH_ASSOC),
            'lineas' => $stmtLineas->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    private static function htmlCorreo($d, $link)
    {
        $e = function ($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); };
        $pesos = function ($v) { return '$' . number_format((float) $v, 0, ',', '.'); };

        $horarios = '';
        foreach ($d['horarios'] as $h) {
            $horarios .= '<li style="margin:2px 0">' . $e($h['dia']) . ' de ' . $e(substr($h['hora_inicial'], 0, 5)) .
                ' a ' . $e(substr($h['hora_final'], 0, 5)) . '</li>';
        }
        $bloqueHorarios = $horarios !== ''
            ? '<p style="margin:18px 0 6px;font-weight:bold">Horarios asignados</p><ul style="margin:0;padding-left:20px">' . $horarios . '</ul>'
            : '<p style="margin:18px 0 0">Te confirmamos el horario por WhatsApp.</p>';

        $filas = '';
        foreach ($d['lineas'] as $l) {
            $filas .= '<tr><td style="padding:6px 0;border-bottom:1px solid #eee">' . $e($l['detalle']) . '</td>' .
                '<td style="padding:6px 0;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">' . $pesos($l['valor']) . '</td></tr>';
        }

        $saludo = $d['saludo'] !== '' ? 'Hola, ' . $e($d['saludo']) . '.' : 'Hola.';

        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"></head>' .
            '<body style="margin:0;background:#f5f5f7;font-family:Arial,Helvetica,sans-serif;color:#222">' .
            '<div style="max-width:560px;margin:0 auto;padding:24px 16px">' .
            '<div style="background:#fff;border-radius:14px;padding:24px;border:1px solid #eee">' .
            '<p style="margin:0 0 4px;font-size:13px;color:#888">' . $e($d['institucion']) . '</p>' .
            '<h1 style="margin:0 0 16px;font-size:21px">Tu inscripción fue aprobada</h1>' .
            '<p style="margin:0 0 10px">' . $saludo . '</p>' .
            '<p style="margin:0">Aprobamos la inscripción de <strong>' . $e($d['participante']) . '</strong> a <strong>' . $e($d['curso']) . '</strong> y ya le separamos el cupo.</p>' .
            $bloqueHorarios .
            '<p style="margin:18px 0 6px;font-weight:bold">Lo que vas a pagar</p>' .
            '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $filas .
            '<tr><td style="padding:8px 0;font-weight:bold">Total</td><td style="padding:8px 0;text-align:right;font-weight:bold">' . $pesos($link['valor']) . '</td></tr></table>' .
            '<p style="margin:22px 0;text-align:center"><a href="' . $e($link['url']) . '" style="display:inline-block;background:#1f8f3a;color:#fff;text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:bold">Pagar ahora</a></p>' .
            '<p style="margin:0;font-size:13px;color:#555">El pago se hace en la página segura de Bold. Cuando se confirme, el plan queda activo. Si tienes alguna pregunta, responde este correo.</p>' .
            '</div></div></body></html>';
    }

    // ==================== APOYO ====================

    private static function obtener($db, $id)
    {
        $stmt = $db->prepare("SELECT * FROM links_pago WHERE id = :id AND id_tenant = :id_tenant");
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /** Nombre de la institucion en la base maestra (tenants.nombre) */
    private static function nombreInstitucion()
    {
        try {
            $stmt = Flight::db_master()->prepare("SELECT nombre FROM tenants WHERE codigo = :codigo LIMIT 1");
            $stmt->bindValue(':codigo', TenantContext::codigo());
            $stmt->execute();
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            return $fila ? $fila['nombre'] : TenantContext::codigo();
        } catch (Exception $e) {
            return TenantContext::codigo();
        }
    }

    /** Valor de texto de un parametro de configuracion_global, o null */
    private static function parametro($db, $clave)
    {
        $stmt = $db->prepare("SELECT valor_texto FROM configuracion_global WHERE clave = :clave AND id_tenant = :id_tenant");
        $stmt->bindValue(':clave', $clave);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila && $fila['valor_texto'] !== null && trim($fila['valor_texto']) !== '' ? trim($fila['valor_texto']) : null;
    }

    /** Valor numerico de un parametro de configuracion_global, o null */
    private static function parametroNumero($db, $clave)
    {
        $stmt = $db->prepare("SELECT valor_numero FROM configuracion_global WHERE clave = :clave AND id_tenant = :id_tenant");
        $stmt->bindValue(':clave', $clave);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila && $fila['valor_numero'] !== null ? (float) $fila['valor_numero'] : null;
    }
}
