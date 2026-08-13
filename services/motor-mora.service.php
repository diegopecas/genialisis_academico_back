<?php
/**
 * Motor de liquidacion de intereses de mora.
 *
 * MODELO DETERMINISTICO: la mora no se acumula dia a dia sumando sobre lo
 * anterior. En cada corte se recalcula COMPLETA a partir de la cuenta, sus
 * abonos (con la fecha REAL del pago, no la de registro) y las exenciones
 * vigentes. El resultado sobreescribe el anterior.
 *
 * La consecuencia practica es que un pago registrado tarde pero fechado antes
 * del vencimiento hace desaparecer la mora sin necesidad de asientos de
 * reversion: el calculo simplemente vuelve a dar cero.
 *
 * UNICA EXCEPCION: la mora ya PAGADA es un piso. Si de una cuenta ya se
 * abonaron intereses, el recalculo nunca deja el causado por debajo de ese
 * valor, para no dejar abonos aplicados contra algo inexistente.
 *
 * Parametros congelados: cada cuenta por cobrar guarda su propio tipo, tasa y
 * recargo (copiados de mora_configuracion al crearse), de modo que cambiar la
 * tarifa de un producto no altera cuentas ya emitidas.
 */
class MotorMora
{
    /** Base de dias usada para pasar la tasa mensual a diaria. */
    const DIAS_MES = 30;

    // =================================================================
    // ENDPOINTS
    // =================================================================

    /**
     * Ejecuta la liquidacion del dia. Origen MANUAL cuando se dispara desde
     * la aplicacion.
     */
    public static function liquidar()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $data = Flight::request()->data->getData();
            $fechaCorte = !empty($data['fecha_corte']) ? $data['fecha_corte'] : date('Y-m-d');
            $origen = !empty($data['origen']) ? $data['origen'] : 'MANUAL';
            $idUsuario = isset($data['id_usuario']) ? $data['id_usuario'] : null;

            $resultado = self::ejecutar(Flight::db(), $fechaCorte, $origen, $idUsuario);

            Flight::json($resultado);
        } catch (Exception $e) {
            error_log('Error en motor_mora liquidar: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al liquidar la mora', 'detalles' => $e->getMessage()), 500);
        }
    }

    /**
     * Liquida solo si hoy todavia no se ha corrido. Es el respaldo perezoso
     * para cuando el cron no corre: se puede llamar al entrar al modulo de
     * cartera sin costo si ya existe el corte del dia.
     */
    public static function liquidarSiHaceFalta()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $fechaCorte = date('Y-m-d');

            if (MoraEjecuciones::existeCorteExitoso($db, $fechaCorte)) {
                Flight::json(array(
                    'ejecutado' => false,
                    'fecha_corte' => $fechaCorte,
                    'mensaje' => 'La mora del dia ya estaba liquidada'
                ));
                return;
            }

            $data = Flight::request()->data->getData();
            $idUsuario = isset($data['id_usuario']) ? $data['id_usuario'] : null;

            $resultado = self::ejecutar($db, $fechaCorte, 'PEREZOSO', $idUsuario);
            $resultado['ejecutado'] = true;

            Flight::json($resultado);
        } catch (Exception $e) {
            error_log('Error en motor_mora liquidarSiHaceFalta: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al liquidar la mora', 'detalles' => $e->getMessage()), 500);
        }
    }

    /**
     * Simula la mora de una persona a una fecha dada, sin persistir nada.
     * Sirve para mostrar el detalle al acudiente o previsualizar antes de
     * recibir un pago.
     */
    public static function simularPorPersona($id_persona)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $fechaCorte = Flight::request()->query['fecha_corte'];
            if (empty($fechaCorte)) {
                $fechaCorte = date('Y-m-d');
            }

            $fechaArranque = self::obtenerFechaArranque($db);
            if ($fechaArranque === null) {
                Flight::json(array(
                    'fecha_corte' => $fechaCorte,
                    'total_mora' => 0,
                    'cuentas' => array(),
                    'mensaje' => 'No hay fecha de arranque de mora configurada'
                ));
                return;
            }

            $cuentas = self::obtenerCuentas($db, $fechaCorte, $id_persona);
            $exenciones = MoraExenciones::obtenerActivasPorPersona($db, $id_persona);
            $exencionesIndexadas = array($id_persona => $exenciones);

            $detalle = array();
            $total = 0;

            foreach ($cuentas as $cuenta) {
                $abonos = self::obtenerAbonos($db, $cuenta['id']);
                $calculo = self::calcularCuenta($cuenta, $abonos, $fechaCorte, $fechaArranque, $exencionesIndexadas);

                if ($calculo['valor_causado'] > 0) {
                    $detalle[] = array(
                        'id_cuenta_por_cobrar' => $cuenta['id'],
                        'fecha_vencimiento'    => $cuenta['fecha'],
                        'detalle'              => $cuenta['detalle'],
                        'nombre_producto'      => $cuenta['nombre_producto'],
                        'valor_cuenta'         => (float) $cuenta['valor'],
                        'base_calculo'         => $calculo['base_calculo'],
                        'dias_mora'            => $calculo['dias_mora'],
                        'periodos_recargo'     => $calculo['periodos_recargo'],
                        'valor_causado'        => $calculo['valor_causado'],
                        'valor_pagado'         => $calculo['valor_pagado'],
                        'saldo_mora'           => $calculo['valor_causado'] - $calculo['valor_pagado'],
                        'exento'               => $calculo['exento']
                    );
                    $total += $calculo['valor_causado'] - $calculo['valor_pagado'];
                }
            }

            Flight::json(array(
                'fecha_corte' => $fechaCorte,
                'total_mora'  => $total,
                'cuentas'     => $detalle
            ));
        } catch (Exception $e) {
            error_log('Error en motor_mora simularPorPersona: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al simular la mora', 'detalles' => $e->getMessage()), 500);
        }
    }

    // =================================================================
    // EJECUCION
    // =================================================================

    /**
     * Corre la liquidacion completa del tenant activo y persiste resultados.
     * No responde HTTP: la usan tanto el endpoint como el cron.
     *
     * @param PDO         $db
     * @param string      $fechaCorte  Y-m-d
     * @param string      $origen      CRON, MANUAL, PEREZOSO
     * @param string|null $idUsuario
     * @return array  Resumen de la corrida
     */
    public static function ejecutar($db, $fechaCorte, $origen, $idUsuario = null)
    {
        $inicio = microtime(true);
        $idEjecucion = MoraEjecuciones::iniciar($db, $fechaCorte, $origen, $idUsuario);

        try {
            $fechaArranque = self::obtenerFechaArranque($db);

            if ($fechaArranque === null) {
                $resumen = array(
                    'fecha_corte'         => $fechaCorte,
                    'cuentas_evaluadas'   => 0,
                    'cuentas_con_mora'    => 0,
                    'valor_total_causado' => 0,
                    'mensaje'             => 'No hay fecha de arranque de mora configurada (configuracion_global.mora_fecha_arranque)'
                );
                MoraEjecuciones::finalizar($db, $idEjecucion, $resumen, 'OK', $resumen['mensaje'], microtime(true) - $inicio);
                return $resumen;
            }

            $cuentas = self::obtenerCuentas($db, $fechaCorte, null);
            $exenciones = MoraExenciones::obtenerActivasIndexadas($db);

            $evaluadas = 0;
            $conMora = 0;
            $totalCausado = 0;

            $db->beginTransaction();

            foreach ($cuentas as $cuenta) {
                $evaluadas++;

                $abonos = self::obtenerAbonos($db, $cuenta['id']);
                $calculo = self::calcularCuenta($cuenta, $abonos, $fechaCorte, $fechaArranque, $exenciones);

                $idCuentaMora = self::materializarCuentaMora($db, $cuenta, $calculo, $fechaCorte);
                $calculo['id_cuenta_mora'] = $idCuentaMora;
                MoraCausaciones::registrarCorte($db, $cuenta, $calculo, $fechaCorte);

                if ($calculo['valor_causado'] > 0) {
                    $conMora++;
                    $totalCausado += $calculo['valor_causado'];
                }
            }

            $db->commit();

            $resumen = array(
                'fecha_corte'         => $fechaCorte,
                'cuentas_evaluadas'   => $evaluadas,
                'cuentas_con_mora'    => $conMora,
                'valor_total_causado' => $totalCausado,
                'mensaje'             => null
            );

            MoraEjecuciones::finalizar($db, $idEjecucion, $resumen, 'OK', null, microtime(true) - $inicio);

            return $resumen;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $resumen = array(
                'fecha_corte'         => $fechaCorte,
                'cuentas_evaluadas'   => 0,
                'cuentas_con_mora'    => 0,
                'valor_total_causado' => 0,
                'mensaje'             => $e->getMessage()
            );

            MoraEjecuciones::finalizar($db, $idEjecucion, $resumen, 'ERROR', $e->getMessage(), microtime(true) - $inicio);

            throw $e;
        }
    }

    // =================================================================
    // CALCULO
    // =================================================================

    /**
     * Liquida una cuenta a la fecha de corte. Funcion pura: no toca la BD.
     *
     * @param array  $cuenta         Fila de cuentas_por_cobrar con parametros de mora
     * @param array  $abonos         [['fecha' => Y-m-d, 'valor' => float], ...] a capital
     * @param string $fechaCorte     Y-m-d
     * @param string $fechaArranque  Y-m-d
     * @param array  $exenciones     Indexadas por id_persona
     * @return array
     */
    public static function calcularCuenta($cuenta, $abonos, $fechaCorte, $fechaArranque, $exenciones)
    {
        $moraPagada = isset($cuenta['mora_pagada']) ? (float) $cuenta['mora_pagada'] : 0.0;

        $resultado = array(
            'id_tipo_mora'       => isset($cuenta['id_tipo_mora']) ? $cuenta['id_tipo_mora'] : null,
            'base_calculo'       => 0.0,
            'dias_mora'          => 0,
            'porcentaje_mensual' => isset($cuenta['porcentaje_mora_mensual']) ? $cuenta['porcentaje_mora_mensual'] : null,
            'valor_recargo'      => isset($cuenta['valor_recargo_mora']) ? $cuenta['valor_recargo_mora'] : null,
            'periodos_recargo'   => 0,
            'valor_causado'      => 0.0,
            'valor_pagado'       => $moraPagada,
            'exento'             => 0
        );

        // Cuenta anulada o sin configuracion de mora: no causa nada, salvo el
        // piso de lo que ya se hubiera pagado.
        if (!empty($cuenta['anulado']) || empty($cuenta['id_tipo_mora'])) {
            $resultado['valor_causado'] = $moraPagada;
            return $resultado;
        }

        // La causacion empieza el dia siguiente al vencimiento, y nunca antes
        // de la fecha de arranque configurada.
        $inicio = self::sumarDias($cuenta['fecha'], 1);
        if ($inicio < $fechaArranque) {
            $inicio = $fechaArranque;
        }

        if ($inicio > $fechaCorte) {
            $resultado['valor_causado'] = $moraPagada;
            return $resultado;
        }

        $codigoTipo = isset($cuenta['codigo_tipo_mora']) ? $cuenta['codigo_tipo_mora'] : null;
        $valorCuenta = (float) $cuenta['valor'];

        $exentoAlgunDia = false;
        $causado = 0.0;
        $diasMora = 0;
        $periodos = 0;
        $recargoAplicado = false;

        $tasaDiaria = 0.0;
        if ($codigoTipo === 'PORCENTAJE') {
            $tasaDiaria = ((float) $cuenta['porcentaje_mora_mensual'] / 100) / self::DIAS_MES;
        }

        $valorRecargo = ($codigoTipo === 'RECARGO_FIJO') ? (float) $cuenta['valor_recargo_mora'] : 0.0;
        $acumulable = !empty($cuenta['mora_acumulable']);

        $dia = $inicio;
        while ($dia <= $fechaCorte) {
            $saldo = self::saldoALaFecha($valorCuenta, $abonos, $dia);

            if ($saldo <= 0) {
                // Cuenta al dia ese dia: no corre mora. Si se vuelve a atrasar
                // (no aplica aqui porque los abonos no se devuelven) el ciclo
                // simplemente vuelve a contar.
                $dia = self::sumarDias($dia, 1);
                continue;
            }

            $exentoHoy = self::estaExento($cuenta, $dia, $exenciones);
            if ($exentoHoy) {
                $exentoAlgunDia = true;
                $dia = self::sumarDias($dia, 1);
                continue;
            }

            $diasMora++;

            if ($codigoTipo === 'PORCENTAJE') {
                $causado += $saldo * $tasaDiaria;
            } elseif ($codigoTipo === 'RECARGO_FIJO') {
                // Primer recargo: el primer dia con saldo despues del vencimiento.
                if (!$recargoAplicado) {
                    $causado += $valorRecargo;
                    $periodos++;
                    $recargoAplicado = true;
                } elseif ($acumulable && self::esPrimeroDeMes($dia)) {
                    // Acumulable: un recargo mas cada 1ro de mes calendario
                    // mientras siga debiendo.
                    $causado += $valorRecargo;
                    $periodos++;
                }
            }

            $dia = self::sumarDias($dia, 1);
        }

        $causado = round($causado);

        // Piso: la mora ya pagada nunca se puede borrar.
        if ($causado < $moraPagada) {
            $causado = $moraPagada;
        }

        $resultado['base_calculo']     = self::saldoALaFecha($valorCuenta, $abonos, $fechaCorte);
        $resultado['dias_mora']        = $diasMora;
        $resultado['periodos_recargo'] = $periodos;
        $resultado['valor_causado']    = $causado;
        $resultado['exento']           = $exentoAlgunDia ? 1 : 0;

        return $resultado;
    }

    /**
     * Saldo de capital de la cuenta a una fecha dada, descontando los abonos
     * cuya fecha REAL de pago sea menor o igual a esa fecha.
     */
    private static function saldoALaFecha($valorCuenta, $abonos, $fecha)
    {
        $aplicado = 0.0;

        foreach ($abonos as $abono) {
            if ($abono['fecha'] <= $fecha) {
                $aplicado += (float) $abono['valor'];
            }
        }

        $saldo = $valorCuenta - $aplicado;

        return $saldo > 0 ? $saldo : 0.0;
    }

    /**
     * Indica si la cuenta esta exenta de mora ese dia. Una exencion sin
     * producto aplica a todos; sin fecha_hasta es indefinida.
     */
    private static function estaExento($cuenta, $dia, $exenciones)
    {
        $idPersona = $cuenta['id_persona'];

        if (!isset($exenciones[$idPersona])) {
            return false;
        }

        foreach ($exenciones[$idPersona] as $exencion) {
            $aplicaProducto = empty($exencion['id_producto_servicio'])
                || $exencion['id_producto_servicio'] === $cuenta['id_producto_servicio'];

            if (!$aplicaProducto) {
                continue;
            }

            if ($dia < $exencion['fecha_desde']) {
                continue;
            }

            if (!empty($exencion['fecha_hasta']) && $dia > $exencion['fecha_hasta']) {
                continue;
            }

            return true;
        }

        return false;
    }

    // =================================================================
    // CONSULTAS DE APOYO
    // =================================================================

    /**
     * Cuentas candidatas a causar mora: vencidas antes del corte, con
     * configuracion de mora congelada. Se incluyen tambien las que ya no
     * tienen saldo, porque hay que refrescar su registro (por ejemplo, si un
     * pago retroactivo debe dejar la mora en cero).
     *
     * @param PDO         $db
     * @param string      $fechaCorte
     * @param string|null $id_persona  Filtra a una sola persona (simulacion)
     * @return array
     */
    private static function obtenerCuentas($db, $fechaCorte, $id_persona = null)
    {
        $filtroPersona = $id_persona !== null ? ' AND c.id_persona = :id_persona ' : '';

        $sentence = $db->prepare("
            SELECT
                c.id,
                c.id_persona,
                c.id_producto_servicio,
                c.fecha,
                c.valor,
                c.detalle,
                c.anulado,
                c.id_tipo_mora,
                c.valor_recargo_mora,
                c.porcentaje_mora_mensual,
                c.mora_acumulable,
                tm.codigo AS codigo_tipo_mora,
                ps.nombre AS nombre_producto,
                c.id_usuario,
                COALESCE((
                    SELECT SUM(cp.valor_aplicado)
                    FROM cuenta_pagada cp
                    INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                    INNER JOIN cuentas_por_cobrar cm ON cp.id_cuenta_por_cobrar = cm.id
                    WHERE cm.id_cuenta_origen = c.id
                      AND (pr.anulado = 0 OR pr.anulado IS NULL)
                ), 0) AS mora_pagada
            FROM cuentas_por_cobrar c
            LEFT JOIN tipos_mora tm ON c.id_tipo_mora = tm.id
            LEFT JOIN productos_servicios ps ON c.id_producto_servicio = ps.id
            WHERE c.id_tenant = :id_tenant
              AND c.fecha < :fecha_corte
              AND (c.anulado = 0 OR c.anulado IS NULL)
              AND c.id_tipo_mora IS NOT NULL
              AND c.es_mora = 0
              {$filtroPersona}
            ORDER BY c.fecha
        ");

        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':fecha_corte', $fechaCorte);
        if ($id_persona !== null) {
            $sentence->bindParam(':id_persona', $id_persona);
        }
        $sentence->execute();

        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Abonos a CAPITAL de una cuenta, con la fecha real del pago. Se ignoran
     * los pagos anulados.
     *
     * La mora se cobra en su propia cuenta por cobrar, asi que aqui solo
     * entran los abonos al capital de la cuenta original.
     */
    private static function obtenerAbonos($db, $id_cuenta_por_cobrar)
    {
        $sentence = $db->prepare("
            SELECT DATE(pr.fecha) AS fecha, cp.valor_aplicado AS valor
            FROM cuenta_pagada cp
            INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            WHERE cp.id_cuenta_por_cobrar = :id_cuenta_por_cobrar
              AND cp.id_tenant = :id_tenant
              AND (pr.anulado = 0 OR pr.anulado IS NULL)
            ORDER BY pr.fecha
        ");
        $sentence->bindParam(':id_cuenta_por_cobrar', $id_cuenta_por_cobrar);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Materializa el resultado del calculo como una CUENTA POR COBRAR de mora.
     *
     * - Si corresponde mora y no existe la cuenta, la crea.
     * - Si ya existe, le actualiza el valor (el saldo que ve el acudiente es
     *   valor - abonos, asi que si ya habia pagado parte solo ve la diferencia).
     * - Si la mora quedo en cero, anula la cuenta de mora, salvo que ya tenga
     *   abonos aplicados: en ese caso la deja en lo pagado, para no dejar un
     *   pago apuntando a una cuenta anulada.
     *
     * La cuenta de mora lleva la MISMA FECHA de la cuenta que la origino, para
     * que en el estado de cuenta queden juntas y la fecha no se mueva sola.
     *
     * @return string|null id de la cuenta de mora, o null si no hay
     */
    private static function materializarCuentaMora($db, $cuenta, $calculo, $fechaCorte)
    {
        $valorCausado = (float) $calculo['valor_causado'];
        $cuentaMora = self::obtenerCuentaMora($db, $cuenta['id']);

        // Sin mora que cobrar
        if ($valorCausado <= 0) {
            if ($cuentaMora !== null) {
                $abonado = (float) $cuentaMora['valor_abonado'];

                if ($abonado > 0) {
                    // Ya le abonaron: se deja en lo pagado en vez de anular.
                    self::actualizarValorCuentaMora($db, $cuentaMora['id'], $abonado);
                    return $cuentaMora['id'];
                }

                self::anularCuentaMora($db, $cuentaMora['id']);
            }
            return null;
        }

        $idProductoMora = self::obtenerProductoMora($db, $cuenta['id_producto_servicio']);
        if ($idProductoMora === null) {
            // El producto cobra mora pero no tiene producto de mora asociado:
            // no se puede crear la cuenta. Se registra y se sigue con las demas.
            error_log('Mora: el producto ' . $cuenta['id_producto_servicio'] . ' no tiene producto de mora asociado');
            return null;
        }

        if ($cuentaMora !== null) {
            if ((float) $cuentaMora['valor'] !== $valorCausado || (int) $cuentaMora['anulado'] === 1) {
                self::actualizarValorCuentaMora($db, $cuentaMora['id'], $valorCausado);
            }
            return $cuentaMora['id'];
        }

        return self::crearCuentaMora($db, $cuenta, $idProductoMora, $valorCausado);
    }

    /**
     * Cuenta de mora ya generada para una cuenta vencida, con lo que se le
     * haya abonado. Incluye las anuladas, para reactivarlas si vuelve a haber
     * mora en vez de crear otra.
     */
    private static function obtenerCuentaMora($db, $idCuentaOrigen)
    {
        $sentence = $db->prepare("
            SELECT
                c.id,
                c.valor,
                c.anulado,
                COALESCE((
                    SELECT SUM(cp.valor_aplicado)
                    FROM cuenta_pagada cp
                    INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                    WHERE cp.id_cuenta_por_cobrar = c.id
                      AND (pr.anulado = 0 OR pr.anulado IS NULL)
                ), 0) AS valor_abonado
            FROM cuentas_por_cobrar c
            WHERE c.id_cuenta_origen = :id_cuenta_origen
              AND c.id_tenant = :id_tenant
            LIMIT 1
        ");
        $sentence->bindParam(':id_cuenta_origen', $idCuentaOrigen);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);

        return $fila ? $fila : null;
    }

    /** Producto bajo el cual nacen las cuentas de mora de un producto dado. */
    private static function obtenerProductoMora($db, $idProductoServicio)
    {
        $sentence = $db->prepare("
            SELECT id_producto_mora
            FROM mora_configuracion
            WHERE id_producto_servicio = :id_producto_servicio
              AND id_tenant = :id_tenant
              AND activo = 1
            LIMIT 1
        ");
        $sentence->bindParam(':id_producto_servicio', $idProductoServicio);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);

        return ($fila && !empty($fila['id_producto_mora'])) ? $fila['id_producto_mora'] : null;
    }

    private static function crearCuentaMora($db, $cuenta, $idProductoMora, $valor)
    {
        $id = Uuid::generar();
        $detalle = 'Intereses de mora - ' . (!empty($cuenta['detalle']) ? $cuenta['detalle'] : $cuenta['nombre_producto']);

        $sentence = $db->prepare("
            INSERT INTO cuentas_por_cobrar
                (id, id_tenant, id_producto_servicio, id_persona, fecha, valor, detalle, id_usuario,
                 anulado, fecha_anulacion, id_usuario_anulacion, id_horario_alimentacion,
                 id_tipo_mora, valor_recargo_mora, porcentaje_mora_mensual, mora_acumulable,
                 es_mora, id_cuenta_origen)
            VALUES
                (:id, :id_tenant, :id_producto_servicio, :id_persona, :fecha, :valor, :detalle, :id_usuario,
                 0, NULL, NULL, NULL,
                 NULL, NULL, NULL, 0,
                 1, :id_cuenta_origen)
        ");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_producto_servicio', $idProductoMora);
        $sentence->bindValue(':id_persona', $cuenta['id_persona']);
        $sentence->bindValue(':fecha', $cuenta['fecha']);
        $sentence->bindValue(':valor', $valor);
        $sentence->bindValue(':detalle', substr($detalle, 0, 255));
        $sentence->bindValue(':id_usuario', $cuenta['id_usuario']);
        $sentence->bindValue(':id_cuenta_origen', $cuenta['id']);
        $sentence->execute();

        return $id;
    }

    /** Actualiza el valor y reactiva la cuenta si estaba anulada. */
    private static function actualizarValorCuentaMora($db, $idCuentaMora, $valor)
    {
        $sentence = $db->prepare("
            UPDATE cuentas_por_cobrar
            SET valor = :valor,
                anulado = 0,
                fecha_anulacion = NULL,
                id_usuario_anulacion = NULL
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindValue(':valor', $valor);
        $sentence->bindValue(':id', $idCuentaMora);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    private static function anularCuentaMora($db, $idCuentaMora)
    {
        $sentence = $db->prepare("
            UPDATE cuentas_por_cobrar
            SET anulado = 1, fecha_anulacion = :fecha_anulacion
            WHERE id = :id AND id_tenant = :id_tenant AND anulado = 0
        ");
        $sentence->bindValue(':fecha_anulacion', date('Y-m-d H:i:s'));
        $sentence->bindValue(':id', $idCuentaMora);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    /**
     * Fecha desde la cual se causa mora. Sin ella el motor no causa nada:
     * evita que al prender el modulo aparezca mora de todo el historico.
     *
     * @return string|null Y-m-d
     */
    public static function obtenerFechaArranque($db)
    {
        $sentence = $db->prepare("
            SELECT valor_fecha, valor_texto
            FROM configuracion_global
            WHERE clave = 'mora_fecha_arranque' AND id_tenant = :id_tenant
            LIMIT 1
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        if (!empty($fila['valor_fecha'])) {
            return $fila['valor_fecha'];
        }

        if (!empty($fila['valor_texto'])) {
            return $fila['valor_texto'];
        }

        return null;
    }

    // =================================================================
    // FECHAS
    // =================================================================

    private static function sumarDias($fecha, $dias)
    {
        return date('Y-m-d', strtotime($fecha . ' +' . $dias . ' day'));
    }

    private static function esPrimeroDeMes($fecha)
    {
        return (int) date('j', strtotime($fecha)) === 1;
    }
}
