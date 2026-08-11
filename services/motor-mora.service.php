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

                MoraCausaciones::registrarCorte($db, $cuenta, $calculo, $fechaCorte);
                self::actualizarCuenta($db, $cuenta['id'], $calculo['valor_causado'], $fechaCorte);

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
                c.mora_causada,
                tm.codigo AS codigo_tipo_mora,
                ps.nombre AS nombre_producto,
                COALESCE((
                    SELECT SUM(cp.valor_aplicado_mora)
                    FROM cuenta_pagada cp
                    INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                    WHERE cp.id_cuenta_por_cobrar = c.id
                      AND (pr.anulado = 0 OR pr.anulado IS NULL)
                ), 0) AS mora_pagada
            FROM cuentas_por_cobrar c
            LEFT JOIN tipos_mora tm ON c.id_tipo_mora = tm.id
            LEFT JOIN productos_servicios ps ON c.id_producto_servicio = ps.id
            WHERE c.id_tenant = :id_tenant
              AND c.fecha < :fecha_corte
              AND (c.anulado = 0 OR c.anulado IS NULL)
              AND c.id_tipo_mora IS NOT NULL
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
     * valor_aplicado sigue siendo solo capital; la parte imputada a intereses
     * viaja aparte en valor_aplicado_mora y no entra aqui.
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
     * Deja en la cuenta el ultimo valor liquidado y la fecha del corte.
     */
    private static function actualizarCuenta($db, $id_cuenta, $valorCausado, $fechaCorte)
    {
        $sentence = $db->prepare("
            UPDATE cuentas_por_cobrar
            SET mora_causada = :mora_causada,
                fecha_calculo_mora = :fecha_calculo_mora
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindValue(':mora_causada', $valorCausado);
        $sentence->bindParam(':fecha_calculo_mora', $fechaCorte);
        $sentence->bindParam(':id', $id_cuenta);
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
