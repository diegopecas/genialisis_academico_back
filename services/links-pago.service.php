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
 * 4. Si la solicitud se cancela por no pago, el link queda anulado
 *    (anularDeSolicitud). Si despues alguien paga por el, el pago NO se
 *    registra solo: queda la nota en el link y le llega un aviso a la
 *    institucion para que lo revise.
 *
 * Cobros de cuentas por cobrar (cobro programado):
 * - Cualquier cuenta con saldo de un estudiante (una cuota, una pension) se
 *   puede cobrar con un link: a mano desde la pantalla Cobros programados
 *   (origen cobro_manual) o solo, con el cron cron/cobros-automaticos.php
 *   (origen cobro_programado), que cada dia arma un cobro por estudiante
 *   con las cuentas que vencen en los proximos dias. Lo prenden los
 *   parametros cobro_automatico_activo y cobro_automatico_dias_antes de
 *   configuracion_global.
 * - Una cuenta va en un solo cobro: si ya esta en un link no se vuelve a
 *   cobrar sola; desde la pantalla se reenvia el correo.
 * - Las cuentas ya vencidas y las de intereses de mora no salen solas.
 * - El pago se aplica al saldo de cada cuenta, de la mas antigua a la mas
 *   nueva.
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
    // Origen de los cobros que no salen de una aprobacion
    const ORIGENES_COBRO = array('cobro_programado', 'cobro_manual');

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
            (id, id_tenant, proveedor, origen, id_solicitud_inscripcion_publica, id_estudiante_x_curso_extra, id_estudiante,
             referencia, descripcion, valor, estado, correo_destino, id_usuario)
            VALUES
            (:id, :id_tenant, :proveedor, 'aprobacion', :id_solicitud, :id_inscripcion,
             (SELECT x.id_estudiante FROM estudiantes_x_cursos_extra x WHERE x.id = :id_inscripcion2 AND x.id_tenant = :id_tenant2),
             :referencia, :descripcion, :valor, 'pendiente', :correo, :id_usuario)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':proveedor', $proveedor);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant2', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_solicitud', $id_solicitud);
        $sentence->bindValue(':id_inscripcion', $id_inscripcion);
        $sentence->bindValue(':id_inscripcion2', $id_inscripcion);
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

    // ==================== COBROS DE CUENTAS POR COBRAR ====================

    /**
     * Registra un cobro en estado pendiente por el saldo de unas cuentas de
     * un estudiante. No llama a la pasarela ni abre transaccion; despues se
     * llama generarYEnviar.
     *
     * @param array $cuentas [ {id, saldo} ] con saldo mayor que cero
     * @param string $origen cobro_programado | cobro_manual
     * @return string id del link
     */
    public static function registrarCobro($db, $id_estudiante, $cuentas, $correo_destino, $origen, $id_usuario)
    {
        $id = Uuid::generar();
        $config = ConfiguracionPasarelaPago::paraUso($db);
        $proveedor = PasarelasPago::para($config) !== null ? $config['proveedor'] : 'transferencia';
        $valor = 0;
        foreach ($cuentas as $c) {
            $valor += round((float) $c['saldo'], 2);
        }
        $descripcion = mb_substr(self::nombreInstitucion() . ' · ' .
            (count($cuentas) === 1 && !empty($cuentas[0]['detalle']) ? $cuentas[0]['detalle'] : 'Pago de ' . count($cuentas) . ' cuentas'), 0, 100);

        $sentence = $db->prepare("INSERT INTO links_pago
            (id, id_tenant, proveedor, origen, id_estudiante, referencia, descripcion, valor, estado, correo_destino, id_usuario)
            VALUES
            (:id, :id_tenant, :proveedor, :origen, :id_estudiante, :referencia, :descripcion, :valor, 'pendiente', :correo, :id_usuario)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':proveedor', $proveedor);
        $sentence->bindValue(':origen', $origen);
        $sentence->bindValue(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':referencia', self::PREFIJO_REFERENCIA . $id);
        $sentence->bindValue(':descripcion', $descripcion);
        $sentence->bindValue(':valor', $valor);
        $sentence->bindValue(':correo', $correo_destino);
        $sentence->bindValue(':id_usuario', $id_usuario);
        $sentence->execute();

        $stmtDetalle = $db->prepare("INSERT INTO links_pago_detalle
            (id, id_tenant, id_link_pago, id_cuenta_por_cobrar, id_tarifa_curso_extra_detalle, valor_cobrado)
            VALUES (:id, :id_tenant, :id_link, :id_cuenta, NULL, :valor)");
        foreach ($cuentas as $c) {
            $stmtDetalle->bindValue(':id', Uuid::generar());
            $stmtDetalle->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtDetalle->bindValue(':id_link', $id);
            $stmtDetalle->bindValue(':id_cuenta', $c['id']);
            $stmtDetalle->bindValue(':valor', round((float) $c['saldo'], 2));
            $stmtDetalle->execute();
        }

        return $id;
    }

    /**
     * Cobro automatico del dia (lo llama cron/cobros-automaticos.php). Si
     * esta prendido, arma un cobro por estudiante con las cuentas con saldo
     * que vencen entre hoy y dentro de cobro_automatico_dias_antes dias y
     * que no estan en ningun cobro, y le manda el correo. Sin intereses de
     * mora ni cuentas vencidas.
     *
     * @return array {activo, dias, cobros, cuentas, enviados, sin_correo, errores}
     */
    public static function ejecutarCobrosAutomaticos($db)
    {
        $parametros = self::parametrosCobro($db);
        $resumen = array('activo' => $parametros['activo'], 'dias' => $parametros['dias'], 'cobros' => 0, 'cuentas' => 0,
            'enviados' => 0, 'sin_correo' => 0, 'errores' => 0);
        if (!$parametros['activo']) {
            return $resumen;
        }

        $hoy = date('Y-m-d');
        $hasta = date('Y-m-d', strtotime($hoy . ' +' . $parametros['dias'] . ' days'));
        $filas = self::cuentasCobrables($db, "x.saldo > 0 AND x.id_link IS NULL AND x.es_mora = 0 AND x.fecha BETWEEN :desde AND :hasta",
            array(':desde' => $hoy, ':hasta' => $hasta));

        // Un cobro por estudiante con todas sus cuentas de la ventana
        $porEstudiante = array();
        foreach ($filas as $f) {
            if (empty($f['correo_destino'])) {
                $resumen['sin_correo']++;
                error_log('LinksPago: cobro automatico sin correo para el estudiante ' . $f['id_estudiante'] . ' (cuenta ' . $f['id_cuenta'] . ').');
                continue;
            }
            $porEstudiante[$f['id_estudiante']]['correo'] = $f['correo_destino'];
            $porEstudiante[$f['id_estudiante']]['cuentas'][] = array('id' => $f['id_cuenta'], 'saldo' => $f['saldo'], 'detalle' => $f['detalle']);
        }

        foreach ($porEstudiante as $idEstudiante => $grupo) {
            try {
                $db->beginTransaction();
                $idLink = self::registrarCobro($db, $idEstudiante, $grupo['cuentas'], $grupo['correo'], 'cobro_programado', null);
                $db->commit();
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $resumen['errores']++;
                error_log('LinksPago: no se pudo armar el cobro automatico del estudiante ' . $idEstudiante . ': ' . $e->getMessage());
                continue;
            }
            $resumen['cobros']++;
            $resumen['cuentas'] += count($grupo['cuentas']);
            $resultado = self::generarYEnviar($db, $idLink);
            if (!empty($resultado['correo_enviado'])) {
                $resumen['enviados']++;
            } else {
                $resumen['errores']++;
            }
        }
        return $resumen;
    }

    /**
     * Cuentas por cobrar de estudiantes con su saldo, el correo al que se
     * cobran (el del acudiente responsable de pago o, si no hay, el del
     * estudiante) y el cobro mas reciente en el que van, filtradas por una
     * condicion sobre esas columnas (alias x).
     */
    private static function cuentasCobrables($db, $condicion, $parametros)
    {
        $sentence = $db->prepare("SELECT x.* FROM (
            SELECT c.id AS id_cuenta, c.detalle, c.fecha, c.valor, c.es_mora,
                   ROUND(c.valor - COALESCE(pg.aplicado, 0), 2) AS saldo,
                   e.id AS id_estudiante,
                   CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS estudiante,
                   COALESCE(
                       (SELECT TRIM(pa.correo_electronico)
                          FROM acudientes a
                          INNER JOIN personas pa ON pa.id = a.id_persona AND pa.id_tenant = a.id_tenant
                         WHERE a.id_estudiante = e.id AND a.es_responsable_pago = 1 AND a.activo = 1 AND a.id_tenant = c.id_tenant
                           AND pa.correo_electronico IS NOT NULL AND TRIM(pa.correo_electronico) <> ''
                         LIMIT 1),
                       NULLIF(TRIM(p.correo_electronico), '')) AS correo_destino,
                   lk.id AS id_link, lk.estado AS estado_link, lk.origen, lk.proveedor, lk.url,
                   lk.fecha_correo, lk.error_correo, lk.fecha_pago
              FROM cuentas_por_cobrar c
              INNER JOIN (SELECT id_persona, MIN(id) AS id FROM estudiantes WHERE id_tenant = :id_tenant_est GROUP BY id_persona) e
                      ON e.id_persona = c.id_persona
              INNER JOIN personas p ON p.id = c.id_persona AND p.id_tenant = c.id_tenant
              LEFT JOIN (SELECT cp.id_cuenta_por_cobrar,
                                SUM(CASE WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado ELSE 0 END) AS aplicado
                           FROM cuenta_pagada cp
                           LEFT JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
                          WHERE cp.id_tenant = :id_tenant_pagos
                          GROUP BY cp.id_cuenta_por_cobrar) pg ON pg.id_cuenta_por_cobrar = c.id
              LEFT JOIN links_pago lk ON lk.id = (
                       SELECT d.id_link_pago
                         FROM links_pago_detalle d
                         INNER JOIN links_pago l2 ON l2.id = d.id_link_pago AND l2.id_tenant = d.id_tenant
                        WHERE d.id_cuenta_por_cobrar = c.id AND d.id_tenant = c.id_tenant
                        ORDER BY l2.fecha_registro DESC
                        LIMIT 1)
             WHERE c.id_tenant = :id_tenant AND (c.anulado = 0 OR c.anulado IS NULL)
            ) x
            WHERE " . $condicion . "
            ORDER BY x.fecha, x.estudiante");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant_est', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant_pagos', TenantContext::id(), PDO::PARAM_INT);
        foreach ($parametros as $nombre => $valor) {
            $sentence->bindValue($nombre, $valor);
        }
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Parametros del cobro automatico en configuracion_global */
    private static function parametrosCobro($db)
    {
        $sentence = $db->prepare("SELECT clave, valor_texto, valor_numero FROM configuracion_global
            WHERE id_tenant = :id_tenant AND clave IN ('cobro_automatico_activo', 'cobro_automatico_dias_antes')");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $activo = false;
        $dias = null;
        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            if ($fila['clave'] === 'cobro_automatico_activo') {
                $texto = strtolower(trim((string) $fila['valor_texto']));
                $activo = $texto === 'true' || $texto === '1' || (int) $fila['valor_numero'] === 1;
            } elseif ($fila['valor_numero'] !== null && (int) $fila['valor_numero'] >= 0) {
                $dias = (int) $fila['valor_numero'];
            }
        }
        // Sin el numero de dias no se cobra solo
        if ($dias === null) {
            if ($activo) {
                error_log('LinksPago: cobro_automatico_activo esta prendido pero falta cobro_automatico_dias_antes; no se cobra.');
            }
            $activo = false;
        }
        return array('activo' => $activo, 'dias' => $dias);
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
        if (!empty($resultado['anulado'])) {
            Flight::json(array('id' => $id, 'estado' => 'anulado', 'estado_bold' => $estadoBold,
                'mensaje' => 'Bold tiene un pago de este link, pero el link está anulado y el pago no se registró. Si corresponde, regístralo desde los pagos del estudiante.'));
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

    // ==================== ANULACION ====================

    /**
     * Deja anulados los links de una solicitud que no se han pagado. Corre
     * dentro de la transaccion de quien cancela la solicitud.
     *
     * @return int cuantos links anulo
     */
    public static function anularDeSolicitud($db, $id_solicitud)
    {
        $sentence = $db->prepare("UPDATE links_pago SET estado = 'anulado'
            WHERE id_solicitud_inscripcion_publica = :id_solicitud AND id_tenant = :id_tenant
              AND estado <> 'pagado' AND estado <> 'anulado'");
        $sentence->bindValue(':id_solicitud', $id_solicitud);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->rowCount();
    }

    // ==================== PAGO ====================

    /**
     * Registra el pago del link: pago recibido aplicado a sus cuentas,
     * paquetes de sesiones de las lineas por paquete y el link en pagado. Si
     * el link ya estaba pagado no hace nada. Si el link esta anulado no
     * registra el pago: deja la nota en el link y avisa a la institucion
     * (una sola vez por pago).
     *
     * @param float|null $valor lo que reporta Bold; si no viene, el del link
     * @return array {ok} | {ok, ya_pagado} | {ok, anulado} | {error}
     */
    private static function registrarPago($db, $id_link, $id_transaccion, $valor, $origen)
    {
        try {
            $db->beginTransaction();

            // Los cobros de cuentas no tienen inscripcion: el estudiante va en el link
            $stmt = $db->prepare("SELECT lp.*, COALESCE(lp.id_estudiante, exce.id_estudiante) AS id_estudiante
                FROM links_pago lp
                LEFT JOIN estudiantes_x_cursos_extra exce ON exce.id = lp.id_estudiante_x_curso_extra AND exce.id_tenant = lp.id_tenant
                WHERE lp.id = :id AND lp.id_tenant = :id_tenant
                FOR UPDATE");
            $stmt->bindValue(':id', $id_link);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $link = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$link || empty($link['id_estudiante'])) {
                $db->rollBack();
                return array('error' => 'El link o su estudiante no existen.');
            }
            if ($link['estado'] === 'pagado') {
                $db->commit();
                return array('ok' => true, 'ya_pagado' => true);
            }
            if ($link['estado'] === 'anulado') {
                $valorAnulado = $valor !== null ? (float) $valor : (float) $link['valor'];
                $yaAvisado = $id_transaccion !== '' && strpos((string) $link['ultimo_error'], $id_transaccion) !== false;
                if (!$yaAvisado) {
                    $stmtNota = $db->prepare("UPDATE links_pago SET ultimo_error = :nota WHERE id = :id AND id_tenant = :id_tenant");
                    $stmtNota->bindValue(':nota', 'Llegó un pago por $' . number_format($valorAnulado, 0, ',', '.') .
                        ' con el link anulado (pago ' . $id_transaccion . ', ' . date('Y-m-d H:i') . '). ' .
                        'No se registró: revísalo y, si corresponde, regístralo desde los pagos del estudiante.');
                    $stmtNota->bindValue(':id', $id_link);
                    $stmtNota->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtNota->execute();
                }
                $db->commit();
                error_log('LinksPago: llego un pago (' . $origen . ') por el link anulado ' . $id_link . ' (pago ' . $id_transaccion . '); no se registro.');
                if (!$yaAvisado) {
                    self::avisarPagoLinkAnulado($db, $id_link, $id_transaccion, $valorAnulado);
                }
                return array('ok' => true, 'anulado' => true);
            }

            // Saldo de cada cuenta: si ya tenia un abono, solo se aplica lo que falta
            $stmtCuentas = $db->prepare("SELECT lpd.id_cuenta_por_cobrar, lpd.id_tarifa_curso_extra_detalle, c.valor,
                       ROUND(c.valor - COALESCE((SELECT SUM(cp.valor_aplicado)
                                                   FROM cuenta_pagada cp
                                                   LEFT JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
                                                  WHERE cp.id_cuenta_por_cobrar = c.id AND cp.id_tenant = c.id_tenant
                                                    AND (pr.anulado = 0 OR pr.anulado IS NULL)), 0), 2) AS saldo
                FROM links_pago_detalle lpd
                INNER JOIN cuentas_por_cobrar c ON c.id = lpd.id_cuenta_por_cobrar AND c.id_tenant = lpd.id_tenant
                WHERE lpd.id_link_pago = :id_link AND lpd.id_tenant = :id_tenant
                ORDER BY c.fecha, c.valor DESC");
            $stmtCuentas->bindValue(':id_link', $id_link);
            $stmtCuentas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtCuentas->execute();
            $lineas = $stmtCuentas->fetchAll(PDO::FETCH_ASSOC);

            $valorPagado = $valor !== null ? (float) $valor : (float) $link['valor'];

            // Se aplica al saldo de cada cuenta (la mas antigua primero) hasta donde alcance lo pagado
            $restante = $valorPagado;
            $cuentas = array();
            foreach ($lineas as $linea) {
                $aplicar = min(max(0, (float) $linea['saldo']), $restante);
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

            // Paquetes de sesiones de las lineas por paquete que quedaron pagadas
            // (solo en los cobros con inscripcion, es decir, de una aprobacion)
            if (!empty($link['id_estudiante_x_curso_extra'])) {
                PaquetesEstudiantesCursosExtra::crearPorCuentasPagadas($db, array_column($cuentas, 'id_cuenta_por_cobrar'),
                    'Pago con ' . ucfirst($link['proveedor']));
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

            // Avisos por correo, ya con el pago guardado: si fallan, el pago queda igual
            self::notificarPago($db, $id_link, $id_transaccion, $valorPagado);

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
     * GET /links-pago/cobros-programados?filtro=proximos|vencidas|todas
     * Lo de la pantalla Cobros programados: los parametros del cobro
     * automatico y las cuentas con su estado de cobro.
     * - proximos: con saldo y que vencen entre hoy y dentro de los dias del parametro
     * - vencidas: con saldo y fecha anterior a hoy
     * - todas: con saldo, mas las ya pagadas que se cobraron con un link de cobro
     */
    public static function cobrosProgramados()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $parametros = self::parametrosCobro($db);
        $dias = $parametros['dias'] !== null ? $parametros['dias'] : 0;
        $hoy = date('Y-m-d');
        $hasta = date('Y-m-d', strtotime($hoy . ' +' . $dias . ' days'));
        $filtro = Flight::request()->query['filtro'] ?? 'proximos';

        if ($filtro === 'vencidas') {
            $filas = self::cuentasCobrables($db, "x.saldo > 0 AND x.fecha < :hoy", array(':hoy' => $hoy));
        } elseif ($filtro === 'todas') {
            $filas = self::cuentasCobrables($db, "(x.saldo > 0 OR x.origen IN ('cobro_programado', 'cobro_manual'))", array());
        } else {
            $filas = self::cuentasCobrables($db, "x.saldo > 0 AND x.fecha BETWEEN :desde AND :hasta", array(':desde' => $hoy, ':hasta' => $hasta));
        }

        foreach ($filas as &$f) {
            $f['saldo'] = max(0, (float) $f['saldo']);
            $f['fecha_envio'] = null;
            if ($f['saldo'] <= 0 || $f['estado_link'] === 'pagado') {
                $f['estado_cobro'] = 'pagado';
            } elseif ($f['id_link'] !== null) {
                if ($f['estado_link'] !== 'activo') {
                    $f['estado_cobro'] = 'error';
                } elseif (empty($f['fecha_correo']) && !empty($f['error_correo'])) {
                    $f['estado_cobro'] = 'error_correo';
                } else {
                    $f['estado_cobro'] = 'enviado';
                }
            } elseif (empty($f['correo_destino'])) {
                $f['estado_cobro'] = 'sin_correo';
            } elseif ($f['fecha'] < $hoy) {
                $f['estado_cobro'] = 'vencida';
            } elseif ((int) $f['es_mora'] === 1) {
                $f['estado_cobro'] = 'mora';
            } elseif ($f['fecha'] <= $hasta) {
                $f['estado_cobro'] = 'por_enviar';
            } else {
                $f['estado_cobro'] = 'programado';
                $f['fecha_envio'] = date('Y-m-d', strtotime($f['fecha'] . ' -' . $dias . ' days'));
            }
            // Lo que se puede hacer con cada una en la pantalla
            $conSaldo = $f['saldo'] > 0;
            $f['puede_enviar'] = $conSaldo && $f['id_link'] === null && !empty($f['correo_destino']) ? 1 : 0;
            $f['puede_reenviar'] = $conSaldo && $f['estado_link'] === 'activo' ? 1 : 0;
            $f['puede_reintentar'] = $conSaldo && in_array($f['estado_link'], array('pendiente', 'error'), true) ? 1 : 0;
            $f['puede_copiar'] = $conSaldo && $f['estado_link'] === 'activo' && !empty($f['url']) ? 1 : 0;
        }
        unset($f);

        Flight::json(array(
            'activo' => $parametros['activo'],
            'dias_antes' => $parametros['dias'],
            'filas' => $filas
        ));
    }

    /**
     * PUT /links-pago/enviar-cobro  { id_cuenta }
     * Cobra ya una cuenta con saldo que no esta en ningun cobro: arma el
     * link (o los datos para transferir) y manda el correo.
     */
    public static function enviarCobro()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $idCuenta = Flight::request()->data['id_cuenta'] ?? null;
        if (!$idCuenta || !is_string($idCuenta)) {
            Flight::json(array('error' => 'Falta la cuenta por cobrar.'), 400);
            return;
        }

        $filas = self::cuentasCobrables($db, "x.id_cuenta = :id_cuenta", array(':id_cuenta' => $idCuenta));
        if (empty($filas)) {
            Flight::json(array('error' => 'La cuenta no existe, está anulada o no es de un estudiante.'), 404);
            return;
        }
        $f = $filas[0];
        if ((float) $f['saldo'] <= 0) {
            Flight::json(array('error' => 'La cuenta ya está pagada.'), 400);
            return;
        }
        if ($f['id_link'] !== null) {
            Flight::json(array('error' => 'La cuenta ya está en un cobro; reenvía ese correo.'), 400);
            return;
        }
        if (empty($f['correo_destino'])) {
            Flight::json(array('error' => 'Ni el acudiente responsable de pago ni el estudiante tienen correo.'), 400);
            return;
        }

        try {
            $db->beginTransaction();
            $idLink = self::registrarCobro($db, $f['id_estudiante'],
                array(array('id' => $f['id_cuenta'], 'saldo' => $f['saldo'], 'detalle' => $f['detalle'])),
                $f['correo_destino'], 'cobro_manual', $userData->id ?? null);
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('LinksPago: no se pudo armar el cobro de la cuenta ' . $idCuenta . ': ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo armar el cobro.'), 500);
            return;
        }
        Flight::json(self::generarYEnviar($db, $idLink));
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
            $esCobro = in_array($link['origen'], self::ORIGENES_COBRO, true);
            $resultado = ConfiguracionCorreo::enviar($db, array($link['correo_destino']),
                $esCobro ? 'Recordatorio de pago · ' . $datos['institucion'] : 'Tu inscripción a ' . $datos['curso'] . ' fue aprobada',
                $esCobro ? self::htmlCobro($datos, $link) : self::htmlCorreo($datos, $link));
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
     * los horarios asignados, las lineas que se cobran y, si hay planes de
     * cuotas, las cuotas que quedan para despues.
     */
    private static function datosCorreo($db, $link)
    {
        if (in_array($link['origen'], self::ORIGENES_COBRO, true)) {
            return self::datosCobro($db, $link);
        }
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
            'lineas' => $stmtLineas->fetchAll(PDO::FETCH_ASSOC),
            // Si algo se partio en cuotas, las que quedan para despues
            'cuotas' => PlanesPago::cuotasSiguientesDeLink($db, $link['id'])
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
        $bloquePago = self::bloqueComoPagar($d, $link, 'Cuando se confirme, el plan queda activo.');

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
            '<tr><td style="padding:8px 0;font-weight:bold">' . (!empty($d['cuotas']) ? 'Total de este pago' : 'Total') . '</td><td style="padding:8px 0;text-align:right;font-weight:bold">' . $pesos($link['valor']) . '</td></tr></table>' .
            self::bloqueCuotas($d, 'Las siguientes cuotas') .
            $bloquePago .
            '</div></div></body></html>';
    }

    /**
     * Boton de la pasarela (y las cuentas por si prefiere transferir), o
     * solo las cuentas para transferir.
     *
     * @param string $confirmacion lo que pasa cuando se confirma el pago en linea
     */
    private static function bloqueComoPagar($d, $link, $confirmacion)
    {
        $e = function ($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); };
        $cuentas = '';
        foreach (isset($d['cuentas']) ? $d['cuentas'] : array() as $c) {
            $cuentas .= '<div style="margin:0 0 10px;padding:10px 12px;border:1px solid #eee;border-radius:10px">' .
                '<strong>' . $e($c['nombre']) . '</strong><br>Cuenta ' . $e($c['numero_cuenta']) .
                (!empty($c['descripcion']) ? '<br><span style="font-size:13px;color:#555">' . $e($c['descripcion']) . '</span>' : '') .
                '</div>';
        }
        if ($link['proveedor'] === 'transferencia') {
            return '<p style="margin:22px 0 8px;font-weight:bold">Cómo pagar</p>' .
                ($cuentas !== '' ? '<p style="margin:0 0 10px">Haz la transferencia a cualquiera de estas cuentas:</p>' . $cuentas
                    : '<p style="margin:0 0 10px">Te enviamos los datos para el pago por WhatsApp.</p>') .
                '<p style="margin:12px 0 0;font-size:13px;color:#555">Cuando pagues, responde este correo con el comprobante y el nombre del participante. Si tienes alguna pregunta, también puedes responder este correo.</p>';
        }
        return '<p style="margin:22px 0;text-align:center"><a href="' . $e($link['url']) . '" style="display:inline-block;background:#1f8f3a;color:#fff;text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:bold">Pagar ahora</a></p>' .
            '<p style="margin:0;font-size:13px;color:#555">El pago se hace en la página segura de la pasarela. ' . $e($confirmacion) . ' Si tienes alguna pregunta, responde este correo.</p>' .
            ($cuentas !== ''
                ? '<p style="margin:22px 0 8px;font-weight:bold">¿Prefieres transferir?</p>' .
                  '<p style="margin:0 0 10px">También puedes pagar a cualquiera de estas cuentas y responder este correo con el comprobante y el nombre del participante:</p>' . $cuentas
                : '');
    }

    /**
     * Lo que va en el correo de un cobro de cuentas: a quien se saluda
     * (acudiente responsable de pago o el estudiante), el participante y las
     * cuentas con su fecha y lo que se cobra de cada una. Trae las mismas
     * claves que datosCorreo para que sirva en los avisos de pago.
     */
    private static function datosCobro($db, $link)
    {
        $stmt = $db->prepare("SELECT CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS participante, p.primer_nombre,
                   (SELECT pa.primer_nombre
                      FROM acudientes a
                      INNER JOIN personas pa ON pa.id = a.id_persona AND pa.id_tenant = a.id_tenant
                     WHERE a.id_estudiante = e.id AND a.es_responsable_pago = 1 AND a.activo = 1 AND a.id_tenant = e.id_tenant
                     LIMIT 1) AS acudiente
            FROM estudiantes e
            INNER JOIN personas p ON p.id = e.id_persona AND p.id_tenant = e.id_tenant
            WHERE e.id = :id_estudiante AND e.id_tenant = :id_tenant");
        $stmt->bindValue(':id_estudiante', $link['id_estudiante']);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: array();

        $stmtLineas = $db->prepare("SELECT c.detalle, c.fecha, COALESCE(lpd.valor_cobrado, c.valor) AS valor
            FROM links_pago_detalle lpd
            INNER JOIN cuentas_por_cobrar c ON c.id = lpd.id_cuenta_por_cobrar AND c.id_tenant = lpd.id_tenant
            WHERE lpd.id_link_pago = :id_link AND lpd.id_tenant = :id_tenant
            ORDER BY c.fecha");
        $stmtLineas->bindValue(':id_link', $link['id']);
        $stmtLineas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtLineas->execute();

        $saludo = !empty($fila['acudiente']) ? $fila['acudiente'] : (!empty($fila['primer_nombre']) ? $fila['primer_nombre'] : '');
        return array(
            'institucion' => self::nombreInstitucion(),
            'saludo' => $saludo,
            'participante' => isset($fila['participante']) ? $fila['participante'] : '',
            'curso' => '',
            'fecha_inicio_clases' => null,
            'horarios' => array(),
            'lineas' => $stmtLineas->fetchAll(PDO::FETCH_ASSOC),
            'cuotas' => array()
        );
    }

    /** Correo de un cobro de cuentas: cada cuenta con su fecha, el total y como pagar */
    private static function htmlCobro($d, $link)
    {
        $e = function ($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); };
        $pesos = function ($v) { return '$' . number_format((float) $v, 0, ',', '.'); };
        $meses = array(1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
            'septiembre', 'octubre', 'noviembre', 'diciembre');
        $hoy = date('Y-m-d');

        $filas = '';
        $vencida = false;
        foreach ($d['lineas'] as $l) {
            $t = strtotime($l['fecha']);
            $vencida = $vencida || $l['fecha'] < $hoy;
            $filas .= '<tr><td style="padding:6px 0;border-bottom:1px solid #eee">' . $e($l['detalle']) .
                '<br><span style="font-size:12px;color:#777">' . ($l['fecha'] < $hoy ? 'Venció el ' : 'Vence el ') .
                (int) date('j', $t) . ' de ' . $meses[(int) date('n', $t)] . ' de ' . date('Y', $t) . '</span></td>' .
                '<td style="padding:6px 0;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">' . $pesos($l['valor']) . '</td></tr>';
        }
        $saludo = $d['saludo'] !== '' ? 'Hola, ' . $e($d['saludo']) . '.' : 'Hola.';

        $cuerpo = '<p style="margin:0 0 10px">' . $saludo . '</p>' .
            '<p style="margin:0">' . ($vencida ? 'Te recordamos que está pendiente' : 'Te recordamos que se acerca') .
            ' el pago de <strong>' . $e($d['participante']) . '</strong>:</p>' .
            '<table style="width:100%;border-collapse:collapse;font-size:14px;margin-top:12px">' . $filas .
            '<tr><td style="padding:8px 0;font-weight:bold">Total</td><td style="padding:8px 0;text-align:right;font-weight:bold">' . $pesos($link['valor']) . '</td></tr></table>' .
            self::bloqueComoPagar($d, $link, 'Cuando se confirme, el pago queda registrado.');

        return self::plantillaCorreo($d['institucion'], $vencida ? 'Tienes un pago pendiente' : 'Tienes un pago próximo', $cuerpo);
    }

    // ==================== AVISOS DE PAGO RECIBIDO ====================

    /**
     * Correos de pago recibido: a la persona que pago (el correo del link) y
     * a la institucion (la direccion de respuesta del Servicio de correo).
     * Se llama despues de registrar el pago; si un correo no sale queda en el
     * log y no afecta el pago.
     */
    private static function notificarPago($db, $id_link, $id_transaccion, $valorPagado)
    {
        try {
            $link = self::obtener($db, $id_link);
            if (!$link) {
                return;
            }
            $datos = self::datosCorreo($db, $link);
            $datos['valor_pagado'] = $valorPagado;
            $datos['referencia_pago'] = $id_transaccion;
            $datos['fecha_pago'] = date('Y-m-d H:i');

            if (!empty($link['correo_destino'])) {
                $resultado = ConfiguracionCorreo::enviar($db, array($link['correo_destino']),
                    'Recibimos tu pago' . ($datos['curso'] !== '' ? ' de ' . $datos['curso'] : ''), self::htmlPagoPersona($datos));
                if ($resultado['error'] !== null) {
                    error_log('LinksPago: no salio el correo de pago recibido del link ' . $id_link . ': ' . $resultado['error']);
                }
            }

            $config = ConfiguracionCorreo::paraUso($db);
            $correoInstitucion = $config && !empty($config['responder_a']) ? $config['responder_a'] : null;
            if ($correoInstitucion) {
                $resultado = ConfiguracionCorreo::enviar($db, array($correoInstitucion),
                    'Llegó un pago: ' . $datos['participante'] . ($datos['curso'] !== '' ? ' · ' . $datos['curso'] : ''), self::htmlPagoInstitucion($datos, $link));
                if ($resultado['error'] !== null) {
                    error_log('LinksPago: no salio el aviso de pago a la institucion del link ' . $id_link . ': ' . $resultado['error']);
                }
            } else {
                error_log('LinksPago: no se avisa a la institucion del pago del link ' . $id_link . ': el Servicio de correo no tiene direccion de respuesta.');
            }
        } catch (Exception $e) {
            error_log('LinksPago: no se pudieron mandar los avisos del pago del link ' . $id_link . ': ' . $e->getMessage());
        }
    }

    /**
     * Aviso a la institucion de un pago que llego por un link anulado (la
     * solicitud se cancelo por no pago). Si el correo no sale queda en el log.
     */
    private static function avisarPagoLinkAnulado($db, $id_link, $id_transaccion, $valorPagado)
    {
        try {
            $link = self::obtener($db, $id_link);
            $config = ConfiguracionCorreo::paraUso($db);
            $correoInstitucion = $config && !empty($config['responder_a']) ? $config['responder_a'] : null;
            if (!$link || !$correoInstitucion) {
                error_log('LinksPago: no se avisa del pago por el link anulado ' . $id_link . ': el Servicio de correo no tiene direccion de respuesta.');
                return;
            }
            $datos = self::datosCorreo($db, $link);
            $datos['valor_pagado'] = $valorPagado;
            $datos['referencia_pago'] = $id_transaccion;
            $datos['fecha_pago'] = date('Y-m-d H:i');

            $e = function ($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); };
            $cuerpo = '<p style="margin:0">Llegó un pago de <strong>' . $e($datos['participante']) . '</strong>' .
                ($datos['curso'] !== '' ? ' por <strong>' . $e($datos['curso']) . '</strong>' : '') .
                ' con ' . $e(ucfirst($link['proveedor'])) . ', pero la solicitud se había cancelado por no pago y el link estaba anulado.</p>' .
                '<p style="margin:12px 0 0;padding:10px 12px;border-radius:8px;background:#fff4e5;border-left:4px solid #e67e22">' .
                '<strong>El pago no se registró en Genialisis.</strong> Revisa el caso y, si corresponde, vuelve a inscribir al estudiante y registra el pago desde sus pagos, o haz la devolución.</p>' .
                self::tablaPago($datos) .
                (!empty($link['correo_destino']) ? '<p style="margin:14px 0 0;font-size:13px;color:#555">Correo de quien pagó: ' . $e($link['correo_destino']) . '</p>' : '');

            $resultado = ConfiguracionCorreo::enviar($db, array($correoInstitucion),
                'Pago con un link anulado: ' . $datos['participante'], self::plantillaCorreo($datos['institucion'], 'Pago con un link anulado', $cuerpo));
            if ($resultado['error'] !== null) {
                error_log('LinksPago: no salio el aviso del pago por el link anulado ' . $id_link . ': ' . $resultado['error']);
            }
        } catch (Exception $e) {
            error_log('LinksPago: no se pudo avisar del pago por el link anulado ' . $id_link . ': ' . $e->getMessage());
        }
    }

    private static function htmlPagoPersona($d)
    {
        $e = function ($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); };
        $saludo = $d['saludo'] !== '' ? 'Hola, ' . $e($d['saludo']) . '.' : 'Hola.';

        $cuerpo = '<p style="margin:0 0 10px">' . $saludo . '</p>' .
            ($d['curso'] !== ''
                ? '<p style="margin:0">Recibimos el pago de la inscripción de <strong>' . $e($d['participante']) . '</strong> a <strong>' . $e($d['curso']) . '</strong>. ¡Gracias!</p>'
                : '<p style="margin:0">Recibimos el pago de <strong>' . $e($d['participante']) . '</strong>. ¡Gracias!</p>') .
            self::bloqueInicioClases($d) .
            self::tablaPago($d) .
            self::bloqueCuotas($d, 'Te recordamos las siguientes cuotas') .
            '<p style="margin:18px 0 0;font-size:13px;color:#555">Guarda este correo como constancia. Si tienes alguna pregunta, responde este correo.</p>';

        return self::plantillaCorreo($d['institucion'], 'Recibimos tu pago', $cuerpo);
    }

    private static function htmlPagoInstitucion($d, $link)
    {
        $e = function ($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); };

        $cuerpo = ($d['curso'] !== ''
                ? '<p style="margin:0">Llegó el pago de <strong>' . $e($d['participante']) . '</strong> por <strong>' . $e($d['curso']) . '</strong> con ' . $e(ucfirst($link['proveedor'])) . '. Ya quedó registrado en Genialisis y, si es un paquete de sesiones, quedó activo.</p>'
                : '<p style="margin:0">Llegó el pago de <strong>' . $e($d['participante']) . '</strong> con ' . $e(ucfirst($link['proveedor'])) . '. Ya quedó registrado en Genialisis.</p>') .
            self::bloqueInicioClases($d) .
            self::tablaPago($d) .
            (!empty($link['correo_destino']) ? '<p style="margin:14px 0 0;font-size:13px;color:#555">Correo de quien pagó: ' . $e($link['correo_destino']) . '</p>' : '');

        return self::plantillaCorreo($d['institucion'], 'Llegó un pago', $cuerpo);
    }

    // Lineas pagadas, total, fecha y referencia de la pasarela
    private static function tablaPago($d)
    {
        $e = function ($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); };
        $pesos = function ($v) { return '$' . number_format((float) $v, 0, ',', '.'); };

        $filas = '';
        foreach ($d['lineas'] as $l) {
            $filas .= '<tr><td style="padding:6px 0;border-bottom:1px solid #eee">' . $e($l['detalle']) . '</td>' .
                '<td style="padding:6px 0;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">' . $pesos($l['valor']) . '</td></tr>';
        }

        return '<p style="margin:18px 0 6px;font-weight:bold">Detalle del pago</p>' .
            '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $filas .
            '<tr><td style="padding:8px 0;font-weight:bold">Total pagado</td><td style="padding:8px 0;text-align:right;font-weight:bold">' . $pesos($d['valor_pagado']) . '</td></tr>' .
            '<tr><td style="padding:4px 0;color:#555">Fecha</td><td style="padding:4px 0;text-align:right;color:#555;white-space:nowrap">' . $e($d['fecha_pago']) . '</td></tr>' .
            ($d['referencia_pago'] !== '' ? '<tr><td style="padding:4px 0;color:#555">Referencia del pago</td><td style="padding:4px 0;text-align:right;color:#555">' . $e($d['referencia_pago']) . '</td></tr>' : '') .
            '</table>';
    }

    // Cuotas que quedan para despues (planes de cuotas), con su fecha y valor
    private static function bloqueCuotas($d, $titulo)
    {
        if (empty($d['cuotas'])) {
            return '';
        }
        $e = function ($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); };
        $pesos = function ($v) { return '$' . number_format((float) $v, 0, ',', '.'); };
        $meses = array(1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic');

        $filas = '';
        foreach ($d['cuotas'] as $c) {
            $t = strtotime($c['fecha_vencimiento']);
            $filas .= '<tr><td style="padding:6px 0;border-bottom:1px solid #eee">' . $e($c['detalle']) . '</td>' .
                '<td style="padding:6px 8px;border-bottom:1px solid #eee;white-space:nowrap;color:#555">' . (int) date('j', $t) . ' ' . $meses[(int) date('n', $t)] . ' ' . date('Y', $t) . '</td>' .
                '<td style="padding:6px 0;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">' . $pesos($c['valor']) . '</td></tr>';
        }
        return '<p style="margin:18px 0 6px;font-weight:bold">' . $e($titulo) . '</p>' .
            '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $filas . '</table>' .
            '<p style="margin:6px 0 0;font-size:13px;color:#555">Cada cuota se paga a más tardar en su fecha.</p>';
    }

    // Fecha de inicio de clases puesta en la aprobacion, en formato 3 de noviembre de 2026
    private static function bloqueInicioClases($d)
    {
        if (empty($d['fecha_inicio_clases'])) {
            return '';
        }
        $meses = array(1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
            'septiembre', 'octubre', 'noviembre', 'diciembre');
        $t = strtotime($d['fecha_inicio_clases']);
        return '<p style="margin:12px 0 0">Las clases empiezan el <strong>' .
            (int) date('j', $t) . ' de ' . $meses[(int) date('n', $t)] . ' de ' . date('Y', $t) . '</strong>.</p>';
    }

    // Marco comun de los correos de pago (el mismo del correo de aprobacion)
    private static function plantillaCorreo($institucion, $titulo, $cuerpo)
    {
        $e = function ($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); };
        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"></head>' .
            '<body style="margin:0;background:#f5f5f7;font-family:Arial,Helvetica,sans-serif;color:#222">' .
            '<div style="max-width:560px;margin:0 auto;padding:24px 16px">' .
            '<div style="background:#fff;border-radius:14px;padding:24px;border:1px solid #eee">' .
            '<p style="margin:0 0 4px;font-size:13px;color:#888">' . $e($institucion) . '</p>' .
            '<h1 style="margin:0 0 16px;font-size:21px">' . $e($titulo) . '</h1>' .
            $cuerpo .
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