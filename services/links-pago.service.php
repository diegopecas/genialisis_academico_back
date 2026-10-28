<?php
/**
 * Cobros que se le mandan a la persona al aprobar una solicitud de
 * inscripcion del portal web: un link de pago de la pasarela del tenant o,
 * si no tiene pasarela, los datos de las cuentas para transferir.
 *
 * Flujo:
 * 1. La aprobacion crea las cuentas por cobrar y registra el cobro en estado
 *    pendiente (registrar), dentro de su transaccion. El proveedor queda
 *    'bold' si el tenant tiene Bold configurado, o 'transferencia' si no.
 * 2. Despues del commit se crea el link en la pasarela (si aplica) y se
 *    envia el correo (generarYEnviar): con el boton de pago, o con las
 *    cuentas bancarias. Si algo falla queda el error y se reintenta desde la
 *    solicitud.
 * 3. Con pasarela, cuando la persona paga llega el aviso (webhookBold) o se
 *    consulta a mano (verificar), y se registra el pago recibido aplicado a
 *    las cuentas y, si el plan es por sesiones, el paquete. Un mismo pago
 *    nunca se registra dos veces. Por transferencia, el pago se registra a
 *    mano desde los pagos del estudiante.
 *
 * Configuracion en la base (ya no en el .env.php):
 * - configuracion_pasarela_pago: proveedor, URL del API, llaves cifradas,
 *   modo pruebas, tipo de pago con que se registran, vigencia del link y
 *   URL de retorno (ConfiguracionPasarelaPago).
 * - configuracion_correo: servicio de correo (ConfiguracionCorreo).
 * - medios_pago_financieros con numero de cuenta: las cuentas que salen en
 *   el correo y el PDF de transferencia.
 */
class LinksPago
{
    const PREFIJO_REFERENCIA = 'GEN-';

    // ==================== DESDE LA APROBACION ====================

    /**
     * Registra el cobro en estado pendiente con las cuentas que cubre. No
     * llama a la pasarela ni abre transaccion: corre dentro de la aprobacion.
     * El proveedor sale de la configuracion del tenant: 'bold' o, sin
     * pasarela, 'transferencia'.
     *
     * @param array $detalle [ {id_cuenta_por_cobrar, id_tarifa_curso_extra_detalle} ]
     * @return string id del link
     */
    public static function registrar($db, $id_solicitud, $id_inscripcion, $nombre_curso, $valor, $correo_destino, $id_usuario, $detalle)
    {
        $id = Uuid::generar();
        $descripcion = mb_substr(self::nombreInstitucion() . ' · ' . $nombre_curso, 0, 100);
        $config = ConfiguracionPasarelaPago::paraUso($db);
        $proveedor = PasarelasPago::para($config) !== null ? $config['proveedor'] : 'transferencia';

        $sentence = $db->prepare("INSERT INTO links_pago
            (id, id_tenant, proveedor, id_solicitud_inscripcion_publica, id_estudiante_x_curso_extra,
             referencia, descripcion, valor, estado, correo_destino, id_usuario)
            VALUES
            (:id, :id_tenant, :proveedor, :id_solicitud, :id_inscripcion,
             :referencia, :descripcion, :valor, 'pendiente', :correo, :id_usuario)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':proveedor', $proveedor);
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
     * Crea el link en la pasarela (si todavia no existe) y envia el correo.
     * Por transferencia no hay link: el cobro queda activo y se manda el
     * correo con las cuentas. Nunca lanza: lo que falle queda en el link.
     *
     * @return array id, proveedor, estado, url, correo_enviado, error, error_correo
     */
    public static function generarYEnviar($db, $id_link)
    {
        $link = self::obtener($db, $id_link);
        if (!$link) {
            return array('estado' => 'error', 'error' => 'El link no existe.');
        }

        if (in_array($link['estado'], array('pendiente', 'error'), true)) {
            if ($link['proveedor'] === 'transferencia') {
                self::activarTransferencia($db, $link);
            } else {
                self::crearEnPasarela($db, $link);
            }
            $link = self::obtener($db, $id_link);
        }

        $correoEnviado = false;
        if ($link['estado'] === 'activo') {
            $correoEnviado = self::enviarCorreo($db, $link);
            $link = self::obtener($db, $id_link);
        }

        return array(
            'id' => $link['id'],
            'proveedor' => $link['proveedor'],
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
        // saldo_pendiente: lo que falta por pagar de las cuentas del cobro.
        // Por transferencia es la unica forma de saber si ya pagaron, porque
        // el pago se registra a mano en los pagos del estudiante.
        $sentence = $db->prepare("SELECT lp.id, lp.proveedor, lp.referencia, lp.descripcion, lp.valor, lp.estado, lp.id_link_externo, lp.url,
                   lp.fecha_vencimiento, lp.ultimo_error, lp.id_transaccion_externa, lp.id_pago_recibido, lp.fecha_pago,
                   lp.correo_destino, lp.fecha_correo, lp.error_correo, lp.fecha_registro,
                   (SELECT COALESCE(SUM(c.valor - COALESCE(pg.aplicado, 0)), 0)
                      FROM links_pago_detalle d
                      INNER JOIN cuentas_por_cobrar c ON c.id = d.id_cuenta_por_cobrar AND c.id_tenant = d.id_tenant
                      LEFT JOIN (SELECT cp.id_cuenta_por_cobrar,
                                        SUM(CASE WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado ELSE 0 END) AS aplicado
                                   FROM cuenta_pagada cp
                                   LEFT JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
                                  WHERE cp.id_tenant = :id_tenant_pagos
                                  GROUP BY cp.id_cuenta_por_cobrar) pg ON pg.id_cuenta_por_cobrar = c.id
                      WHERE d.id_link_pago = lp.id AND (c.anulado = 0 OR c.anulado IS NULL) AND d.id_tenant = lp.id_tenant
                   ) AS saldo_pendiente
            FROM links_pago lp
            WHERE lp.id_solicitud_inscripcion_publica = :id_solicitud AND lp.id_tenant = :id_tenant
            ORDER BY lp.fecha_registro DESC");
        $sentence->bindParam(':id_solicitud', $id_solicitud);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant_pagos', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * PUT /links-pago/generar  { id }
     * Reintenta crear el link en la pasarela (si quedo pendiente o con error)
     * y envia el correo.
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
            Flight::json(array('error' => 'El cobro ya está creado.'), 400);
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
            Flight::json(array('error' => 'Solo se reenvía el correo de un cobro activo (sin pagar).'), 400);
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
     * Consulta el link en la pasarela. Si ya esta pagado y el pago no se ha
     * registrado (por ejemplo, porque el aviso no llego), lo registra.
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
        if ($link['proveedor'] === 'transferencia') {
            Flight::json(array('error' => 'Los pagos por transferencia se registran desde los pagos del estudiante.'), 400);
            return;
        }
        if (empty($link['id_link_externo'])) {
            Flight::json(array('error' => 'El link todavía no está creado en la pasarela.'), 400);
            return;
        }
        $pasarela = PasarelasPago::para(ConfiguracionPasarelaPago::paraUso($db));
        if ($pasarela === null) {
            Flight::json(array('error' => 'No hay pasarela de pagos configurada (Configuración > Pasarela de pagos).'), 400);
            return;
        }

        $consulta = $pasarela->consultarLink($link['id_link_externo']);
        if ($consulta['error'] !== null) {
            Flight::json(array('error' => 'No se pudo consultar la pasarela: ' . $consulta['error']), 502);
            return;
        }

        $estadoBold = $consulta['estado'];
        if (!$consulta['pagado']) {
            Flight::json(array('id' => $id, 'estado' => $link['estado'], 'estado_bold' => $estadoBold,
                'mensaje' => 'La pasarela todavía no tiene el pago (estado ' . ($estadoBold ?: 'desconocido') . ').'));
            return;
        }

        $idTransaccion = $consulta['id_transaccion'] !== null ? $consulta['id_transaccion'] : $link['id_link_externo'];
        $valor = $consulta['valor'] !== null ? $consulta['valor'] : (float) $link['valor'];
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
     * x-bold-signature con la llave secreta de la pasarela del tenant
     * (PasarelaBold::firmaValida). Bold espera un 200 en menos de 2
     * segundos; si no lo recibe, reintenta.
     */
    public static function webhookBold()
    {
        $cuerpo = (string) Flight::request()->getBody();
        $firma = isset($_SERVER['HTTP_X_BOLD_SIGNATURE']) ? (string) $_SERVER['HTTP_X_BOLD_SIGNATURE'] : '';

        $db = Flight::db();
        $config = ConfiguracionPasarelaPago::paraUso($db);
        $pasarela = $config['proveedor'] === 'bold' ? PasarelasPago::para($config) : null;
        $valida = $pasarela !== null ? $pasarela->firmaValida($cuerpo, $firma) : null;
        if ($valida === null) {
            error_log('LinksPago: llego un aviso de Bold pero el tenant no tiene Bold configurado con su llave secreta.');
            Flight::json(array('error' => 'Bold no está configurado.'), 503);
            return;
        }
        if (!$valida) {
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

            $stmt = $db->prepare("SELECT lp.*, exce.id_estudiante, exce.id_curso_extra, ce.fecha_inicio AS curso_fecha_inicio,
                       s.fecha_inicio_clases
                FROM links_pago lp
                INNER JOIN estudiantes_x_cursos_extra exce ON exce.id = lp.id_estudiante_x_curso_extra AND exce.id_tenant = lp.id_tenant
                INNER JOIN cursos_extra ce ON ce.id = exce.id_curso_extra AND ce.id_tenant = lp.id_tenant
                LEFT JOIN solicitudes_inscripcion_publica s ON s.id = lp.id_solicitud_inscripcion_publica AND s.id_tenant = lp.id_tenant
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

            $idTipoPago = ConfiguracionPasarelaPago::paraUso($db)['id_tipo_pago'];
            if (!$idTipoPago) {
                error_log('LinksPago: la pasarela no tiene tipo de pago configurado; el pago queda sin tipo.');
            }

            $idPago = PagosRecibidos::registrarDesdePasarela($db, array(
                'fecha' => date('Y-m-d H:i:s'),
                'id_estudiante' => $link['id_estudiante'],
                'id_acudiente' => $acudiente ? $acudiente['id'] : null,
                'id_tipo_pago' => $idTipoPago ?: null,
                'valor_recibido' => $valorPagado,
                'observaciones' => 'Pago con ' . ucfirst($link['proveedor']) . ' (' . $origen . ') · ' . $link['descripcion'] .
                    ($link['id_link_externo'] ? ' · ' . $link['id_link_externo'] : ''),
                'referencia_bancaria' => mb_substr($id_transaccion, 0, 100)
            ), $cuentas);

            // Paquete de sesiones por cada linea de tipo paquete.
            // Arranca en la fecha de inicio de clases que se puso al aprobar;
            // si ya paso (pagaron tarde), arranca el dia del pago.
            // Sin esa fecha: un curso sin fecha de inicio es permanente y el
            // paquete arranca hoy.
            if (!empty($link['fecha_inicio_clases'])) {
                $fechaInicio = max(date('Y-m-d'), $link['fecha_inicio_clases']);
            } else {
                $fechaInicio = $link['curso_fecha_inicio'] !== null ? max(date('Y-m-d'), $link['curso_fecha_inicio']) : date('Y-m-d');
            }
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
                        'Pago con ' . ucfirst($link['proveedor'])
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

    // ==================== PASARELA ====================

    /**
     * Crea el link de valor cerrado en la pasarela del tenant y deja el
     * resultado en el link.
     */
    private static function crearEnPasarela($db, $link)
    {
        $config = ConfiguracionPasarelaPago::paraUso($db);
        $pasarela = PasarelasPago::para($config);

        $vencimiento = null;
        $resultado = array('error' => 'No hay pasarela de pagos configurada (Configuración > Pasarela de pagos).');
        if ($pasarela !== null) {
            $marca = !empty($config['link_vigencia_dias']) ? time() + (int) $config['link_vigencia_dias'] * 86400 : null;
            $vencimiento = $marca !== null ? date('Y-m-d H:i:s', $marca) : null;
            $resultado = $pasarela->crearLink($link, $marca);
        }

        if ($resultado['error'] !== null) {
            error_log('LinksPago: no se creo el link ' . $link['id'] . ' en la pasarela: ' . $resultado['error']);
            $stmt = $db->prepare("UPDATE links_pago SET estado = 'error', ultimo_error = :error WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindValue(':error', mb_substr($resultado['error'], 0, 2000));
            $stmt->bindValue(':id', $link['id']);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            return;
        }

        $stmt = $db->prepare("UPDATE links_pago SET estado = 'activo', id_link_externo = :id_externo, url = :url,
                fecha_vencimiento = :vencimiento, ultimo_error = NULL
            WHERE id = :id AND id_tenant = :id_tenant");
        $stmt->bindValue(':id_externo', $resultado['id_externo']);
        $stmt->bindValue(':url', $resultado['url']);
        $stmt->bindValue(':vencimiento', $vencimiento);
        $stmt->bindValue(':id', $link['id']);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
    }

    /** Por transferencia no hay link que crear: el cobro queda activo */
    private static function activarTransferencia($db, $link)
    {
        $stmt = $db->prepare("UPDATE links_pago SET estado = 'activo', ultimo_error = NULL WHERE id = :id AND id_tenant = :id_tenant");
        $stmt->bindValue(':id', $link['id']);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * GET /links-pago/datos-pago/@id
     * Lo que va en el PDF de pago: lo mismo del correo (curso, participante,
     * fecha de inicio, horarios, lineas, total), las cuentas bancarias y, con
     * pasarela, el link para pagar en linea.
     */
    public static function datosPago($id)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $link = self::obtener($db, $id);
        if (!$link) {
            Flight::json(array('error' => 'El cobro no existe.'), 404);
            return;
        }
        $datos = self::datosCorreo($db, $link);
        $datos['valor'] = (float) $link['valor'];
        $datos['referencia'] = $link['referencia'];
        // Con pasarela, el link para pagar en linea (si sigue sin pagar)
        $datos['url'] = $link['proveedor'] !== 'transferencia' && $link['estado'] === 'activo' ? $link['url'] : null;
        $datos['cuentas'] = self::cuentasBancarias($db);
        Flight::json($datos);
    }

    /**
     * Cuentas para transferir: los medios de pago del tenant que tienen
     * numero de cuenta, en el orden en que se muestran.
     */
    private static function cuentasBancarias($db)
    {
        $stmt = $db->prepare("SELECT nombre, numero_cuenta, descripcion
            FROM medios_pago_financieros
            WHERE id_tenant = :id_tenant AND numero_cuenta IS NOT NULL AND TRIM(numero_cuenta) <> ''
            ORDER BY orden, nombre");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== CORREO ====================

    /**
     * Correo a la persona con los horarios asignados, lo que va a pagar y el
     * boton de pago, o las cuentas para transferir. Sale por el servicio de
     * correo del tenant (ConfiguracionCorreo). Deja la fecha o el error en
     * el link.
     *
     * @return bool true si salio
     */
    private static function enviarCorreo($db, $link)
    {
        $error = null;
        if (empty($link['correo_destino'])) {
            $error = 'La solicitud no tiene correo.';
        }

        if ($error === null) {
            $datos = self::datosCorreo($db, $link);
            // Las cuentas van siempre: con pasarela, por si prefiere transferir
            $datos['cuentas'] = self::cuentasBancarias($db);
            $resultado = ConfiguracionCorreo::enviar($db, array($link['correo_destino']),
                'Tu inscripción a ' . $datos['curso'] . ' fue aprobada', self::htmlCorreo($datos, $link));
            $error = $resultado['error'];
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
                   s.acu_primer_nombre, s.est_primer_nombre, s.fecha_inicio_clases
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
            'fecha_inicio_clases' => !empty($fila['fecha_inicio_clases']) ? $fila['fecha_inicio_clases'] : null,
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

        // Como pagar: boton de la pasarela (y las cuentas por si prefiere
        // transferir), o solo las cuentas
        $cuentas = '';
        foreach (isset($d['cuentas']) ? $d['cuentas'] : array() as $c) {
            $cuentas .= '<div style="margin:0 0 10px;padding:10px 12px;border:1px solid #eee;border-radius:10px">' .
                '<strong>' . $e($c['nombre']) . '</strong><br>Cuenta ' . $e($c['numero_cuenta']) .
                (!empty($c['descripcion']) ? '<br><span style="font-size:13px;color:#555">' . $e($c['descripcion']) . '</span>' : '') .
                '</div>';
        }
        if ($link['proveedor'] === 'transferencia') {
            $bloquePago = '<p style="margin:22px 0 8px;font-weight:bold">Cómo pagar</p>' .
                ($cuentas !== '' ? '<p style="margin:0 0 10px">Haz la transferencia a cualquiera de estas cuentas:</p>' . $cuentas
                    : '<p style="margin:0 0 10px">Te enviamos los datos para el pago por WhatsApp.</p>') .
                '<p style="margin:12px 0 0;font-size:13px;color:#555">Cuando pagues, responde este correo con el comprobante y el nombre del participante. Si tienes alguna pregunta, también puedes responder este correo.</p>';
        } else {
            $bloquePago = '<p style="margin:22px 0;text-align:center"><a href="' . $e($link['url']) . '" style="display:inline-block;background:#1f8f3a;color:#fff;text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:bold">Pagar ahora</a></p>' .
                '<p style="margin:0;font-size:13px;color:#555">El pago se hace en la página segura de la pasarela. Cuando se confirme, el plan queda activo. Si tienes alguna pregunta, responde este correo.</p>' .
                ($cuentas !== ''
                    ? '<p style="margin:22px 0 8px;font-weight:bold">¿Prefieres transferir?</p>' .
                      '<p style="margin:0 0 10px">También puedes pagar a cualquiera de estas cuentas y responder este correo con el comprobante y el nombre del participante:</p>' . $cuentas
                    : '');
        }

        // Fecha de inicio de clases puesta en la aprobacion, en formato 3 de noviembre de 2026
        $bloqueInicio = '';
        if (!empty($d['fecha_inicio_clases'])) {
            $meses = array(1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
                'septiembre', 'octubre', 'noviembre', 'diciembre');
            $t = strtotime($d['fecha_inicio_clases']);
            $bloqueInicio = '<p style="margin:12px 0 0">Las clases empiezan el <strong>' .
                (int) date('j', $t) . ' de ' . $meses[(int) date('n', $t)] . ' de ' . date('Y', $t) . '</strong>.</p>';
        }

        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"></head>' .
            '<body style="margin:0;background:#f5f5f7;font-family:Arial,Helvetica,sans-serif;color:#222">' .
            '<div style="max-width:560px;margin:0 auto;padding:24px 16px">' .
            '<div style="background:#fff;border-radius:14px;padding:24px;border:1px solid #eee">' .
            '<p style="margin:0 0 4px;font-size:13px;color:#888">' . $e($d['institucion']) . '</p>' .
            '<h1 style="margin:0 0 16px;font-size:21px">Tu inscripción fue aprobada</h1>' .
            '<p style="margin:0 0 10px">' . $saludo . '</p>' .
            '<p style="margin:0">Aprobamos la inscripción de <strong>' . $e($d['participante']) . '</strong> a <strong>' . $e($d['curso']) . '</strong> y ya le separamos el cupo.</p>' .
            $bloqueInicio .
            $bloqueHorarios .
            '<p style="margin:18px 0 6px;font-weight:bold">Lo que vas a pagar</p>' .
            '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $filas .
            '<tr><td style="padding:8px 0;font-weight:bold">Total</td><td style="padding:8px 0;text-align:right;font-weight:bold">' . $pesos($link['valor']) . '</td></tr></table>' .
            $bloquePago .
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
}
