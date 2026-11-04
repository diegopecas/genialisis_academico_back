<?php
/**
 * Planes de pago en cuotas (planes_pago): un cobro partido en cuotas, con
 * recargo opcional. Cada cuota es una cuenta por cobrar con su fecha de
 * vencimiento (PlanesPagoCuotas).
 *
 * Reglas:
 * - La primera cuota se cobra de inmediato: su fecha es hoy y va en el link
 *   de pago junto con lo que se cobra de contado. Las demas quedan como
 *   cuentas por cobrar con su fecha.
 * - Recargo: ninguno, por porcentaje (sobre lo que se cobra) o por valor
 *   total (se escribe el nuevo total, nunca menor que lo que se cobra).
 * - Las cuotas tienen que sumar el total y las fechas ir en orden.
 * - Cada cuota guarda como valor de tarifa la parte proporcional de la
 *   tarifa, asi el descuento (si lo hubo) queda repartido en las cuotas.
 *
 * Hoy los crea la aprobacion de solicitudes del portal; la solicitud y la
 * inscripcion son opcionales para poder usarlo desde otros cobros.
 */
class PlanesPago
{
    const TIPOS_RECARGO = array('ninguno', 'porcentaje', 'valor');
    const PERIODICIDADES = array('mensual', 'fechas');
    const MAXIMO_CUOTAS = 60;

    // ==================== DESDE LA APROBACION ====================

    /**
     * Revisa el plan que llega de la pantalla y lo deja listo para crear.
     * Recalcula el total con el recargo y exige que las cuotas lo sumen. La
     * fecha de la primera cuota siempre es hoy.
     *
     * @param array $datos {periodicidad, tipo_recargo, porcentaje_recargo, valor_total, cuotas: [{numero, fecha, valor}]}
     * @param float $valor_cobrar lo que se cobra antes del recargo
     * @param string $nombre nombre de la linea, para los mensajes
     * @return array {periodicidad, tipo_recargo, porcentaje_recargo, valor_total, valor_recargo, cuotas} | {error}
     */
    public static function validar($datos, $valor_cobrar, $nombre)
    {
        if (!is_array($datos)) {
            return array('error' => 'El plan de cuotas de ' . $nombre . ' no es válido.');
        }
        $valorCobrar = round((float) $valor_cobrar, 2);
        if ($valorCobrar <= 0) {
            return array('error' => 'No se puede partir en cuotas ' . $nombre . ' porque su valor es cero.');
        }

        $periodicidad = isset($datos['periodicidad']) ? (string) $datos['periodicidad'] : '';
        if (!in_array($periodicidad, self::PERIODICIDADES, true)) {
            return array('error' => 'Escoge si las cuotas de ' . $nombre . ' son mensuales o en fechas específicas.');
        }

        // Total con el recargo
        $tipoRecargo = isset($datos['tipo_recargo']) ? (string) $datos['tipo_recargo'] : 'ninguno';
        if (!in_array($tipoRecargo, self::TIPOS_RECARGO, true)) {
            return array('error' => 'El recargo de ' . $nombre . ' no es válido.');
        }
        $porcentaje = null;
        if ($tipoRecargo === 'porcentaje') {
            if (!isset($datos['porcentaje_recargo']) || !is_numeric($datos['porcentaje_recargo'])
                || (float) $datos['porcentaje_recargo'] <= 0 || (float) $datos['porcentaje_recargo'] > 100) {
                return array('error' => 'El porcentaje de recargo de ' . $nombre . ' tiene que ser mayor que 0 y hasta 100.');
            }
            $porcentaje = round((float) $datos['porcentaje_recargo'], 3);
            $total = round($valorCobrar * (1 + $porcentaje / 100), 0);
        } elseif ($tipoRecargo === 'valor') {
            if (!isset($datos['valor_total']) || !is_numeric($datos['valor_total'])) {
                return array('error' => 'Falta el valor total con recargo de ' . $nombre . '.');
            }
            $total = round((float) $datos['valor_total'], 2);
            if ($total < $valorCobrar) {
                return array('error' => 'El valor total con recargo de ' . $nombre . ' no puede ser menor que lo que se cobra.');
            }
        } else {
            $total = $valorCobrar;
        }

        // Cuotas
        $lista = isset($datos['cuotas']) && is_array($datos['cuotas']) ? array_values($datos['cuotas']) : array();
        $cantidad = count($lista);
        if ($cantidad < 2 || $cantidad > self::MAXIMO_CUOTAS) {
            return array('error' => 'El plan de ' . $nombre . ' tiene que tener entre 2 y ' . self::MAXIMO_CUOTAS . ' cuotas.');
        }

        $hoy = date('Y-m-d');
        $cuotas = array();
        $suma = 0;
        $fechaAnterior = null;
        foreach ($lista as $i => $c) {
            $numero = $i + 1;
            if (!is_array($c) || !isset($c['valor']) || !is_numeric($c['valor']) || (float) $c['valor'] <= 0) {
                return array('error' => 'La cuota ' . $numero . ' de ' . $nombre . ' tiene que tener un valor mayor que cero.');
            }
            // La primera se cobra de inmediato
            $fecha = $numero === 1 ? $hoy : (isset($c['fecha']) ? (string) $c['fecha'] : '');
            $f = DateTime::createFromFormat('Y-m-d', $fecha);
            if (!$f || $f->format('Y-m-d') !== $fecha) {
                return array('error' => 'Falta la fecha de la cuota ' . $numero . ' de ' . $nombre . '.');
            }
            if ($fechaAnterior !== null && $fecha <= $fechaAnterior) {
                return array('error' => 'La fecha de la cuota ' . $numero . ' de ' . $nombre . ' tiene que ser posterior a la de la cuota ' . ($numero - 1) . '.');
            }
            $valor = round((float) $c['valor'], 2);
            $cuotas[] = array('numero' => $numero, 'fecha' => $fecha, 'valor' => $valor);
            $suma += $valor;
            $fechaAnterior = $fecha;
        }

        if (abs(round($suma, 2) - $total) >= 0.01) {
            return array('error' => 'Las cuotas de ' . $nombre . ' suman $' . number_format($suma, 0, ',', '.') .
                ' y el total es $' . number_format($total, 0, ',', '.') . '.');
        }

        return array(
            'periodicidad' => $periodicidad,
            'tipo_recargo' => $tipoRecargo,
            'porcentaje_recargo' => $porcentaje,
            'valor_total' => $total,
            'valor_recargo' => round($total - $valorCobrar, 2),
            'cuotas' => $cuotas
        );
    }

    /**
     * Crea el plan de una linea de la tarifa de un curso: una cuenta por
     * cobrar por cuota (amarrada a la inscripcion) y el plan con sus cuotas.
     * No abre transaccion: corre dentro de la aprobacion.
     *
     * @param array $linea {id, id_producto_servicio, nombre_producto}
     * @param array $plan el que devuelve validar()
     * @return array {id_plan, id_cuenta_primera, valor_primera}
     */
    public static function crearDeLineaCurso($db, $id_solicitud, $id_inscripcion, $id_persona, $linea, $nombre_curso,
        $valor_tarifa, $valor_cobrar, $motivo_descuento, $plan, $id_usuario)
    {
        $idPlan = Uuid::generar();
        $numeroCuotas = count($plan['cuotas']);
        $partesTarifa = self::partesTarifa($plan['cuotas'], (float) $valor_tarifa, $plan['valor_total']);
        $motivo = $motivo_descuento !== null && $motivo_descuento !== '' ? $motivo_descuento : null;

        $sentence = $db->prepare("INSERT INTO planes_pago
            (id, id_tenant, id_solicitud_inscripcion_publica, id_estudiante_x_curso_extra, id_persona,
             id_producto_servicio, id_tarifa_curso_extra_detalle, descripcion, valor_base, valor_cobrar,
             motivo_descuento, tipo_recargo, porcentaje_recargo, valor_recargo, valor_total, numero_cuotas,
             periodicidad, id_usuario)
            VALUES
            (:id, :id_tenant, :id_solicitud, :id_inscripcion, :id_persona,
             :id_producto, :id_tarifa_detalle, :descripcion, :valor_base, :valor_cobrar,
             :motivo, :tipo_recargo, :porcentaje, :valor_recargo, :valor_total, :numero_cuotas,
             :periodicidad, :id_usuario)");
        $sentence->bindValue(':id', $idPlan);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_solicitud', $id_solicitud);
        $sentence->bindValue(':id_inscripcion', $id_inscripcion);
        $sentence->bindValue(':id_persona', $id_persona);
        $sentence->bindValue(':id_producto', $linea['id_producto_servicio']);
        $sentence->bindValue(':id_tarifa_detalle', $linea['id']);
        $sentence->bindValue(':descripcion', mb_substr($nombre_curso . ' · ' . $linea['nombre_producto'], 0, 300));
        $sentence->bindValue(':valor_base', $valor_tarifa);
        $sentence->bindValue(':valor_cobrar', $valor_cobrar);
        $sentence->bindValue(':motivo', $motivo);
        $sentence->bindValue(':tipo_recargo', $plan['tipo_recargo']);
        $sentence->bindValue(':porcentaje', $plan['porcentaje_recargo']);
        $sentence->bindValue(':valor_recargo', $plan['valor_recargo']);
        $sentence->bindValue(':valor_total', $plan['valor_total']);
        $sentence->bindValue(':numero_cuotas', $numeroCuotas, PDO::PARAM_INT);
        $sentence->bindValue(':periodicidad', $plan['periodicidad']);
        $sentence->bindValue(':id_usuario', $id_usuario);
        $sentence->execute();

        $primera = null;
        foreach ($plan['cuotas'] as $i => $cuota) {
            $idCuenta = CuentasPorCobrar::crearCuentaCursoExtra(
                $db,
                $id_inscripcion,
                $id_persona,
                $linea['id_producto_servicio'],
                $cuota['fecha'],
                $cuota['valor'],
                $nombre_curso . ' · ' . $linea['nombre_producto'] . ' · Cuota ' . $cuota['numero'] . ' de ' . $numeroCuotas,
                $id_usuario,
                $partesTarifa[$i],
                $partesTarifa[$i] > $cuota['valor'] ? $motivo : null
            );
            PlanesPagoCuotas::registrar($db, $idPlan, $cuota['numero'], $cuota['fecha'], $cuota['valor'], $idCuenta);
            if ($primera === null) {
                $primera = array('id_cuenta' => $idCuenta, 'valor' => $cuota['valor']);
            }
        }

        return array('id_plan' => $idPlan, 'id_cuenta_primera' => $primera['id_cuenta'], 'valor_primera' => $primera['valor']);
    }

    /**
     * Parte de la tarifa que le toca a cada cuota, en proporcion a su valor,
     * en pesos enteros y la ultima con el redondeo. Si no hubo descuento
     * (total igual o mayor que la tarifa) ninguna parte queda por encima de
     * su cuota, para que no aparezca un descuento que no existe.
     *
     * @return array un valor por cuota, en el mismo orden
     */
    private static function partesTarifa($cuotas, $tarifa, $total)
    {
        $partes = array();
        $acumulado = 0;
        $ultima = count($cuotas) - 1;
        foreach ($cuotas as $i => $cuota) {
            $parte = $i === $ultima ? round($tarifa - $acumulado, 2)
                : ($total > 0 ? round($tarifa * $cuota['valor'] / $total, 0) : 0);
            if ($total >= $tarifa && $parte > $cuota['valor']) {
                $parte = $cuota['valor'];
            }
            $partes[] = max(0, $parte);
            $acumulado += $parte;
        }
        return $partes;
    }

    // ==================== PARA EL CORREO Y EL PDF ====================

    /**
     * Cuotas que quedan para despues de un link de pago: las de los planes
     * cuya primera cuota va en ese link. Van en el correo y en el PDF.
     *
     * @return array [{detalle, fecha_vencimiento, valor}]
     */
    public static function cuotasSiguientesDeLink($db, $id_link)
    {
        $sentence = $db->prepare("SELECT c.detalle, pc.fecha_vencimiento, pc.valor
            FROM planes_pago_cuotas pc
            INNER JOIN cuentas_por_cobrar c ON c.id = pc.id_cuenta_por_cobrar AND c.id_tenant = pc.id_tenant
            WHERE pc.id_tenant = :id_tenant AND pc.numero > 1
              AND (c.anulado = 0 OR c.anulado IS NULL)
              AND pc.id_plan_pago IN (
                  SELECT p1.id_plan_pago
                    FROM planes_pago_cuotas p1
                    INNER JOIN links_pago_detalle d ON d.id_cuenta_por_cobrar = p1.id_cuenta_por_cobrar AND d.id_tenant = p1.id_tenant
                   WHERE d.id_link_pago = :id_link AND d.id_tenant = :id_tenant2 AND p1.numero = 1)
            ORDER BY pc.fecha_vencimiento, pc.id_plan_pago, pc.numero");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant2', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_link', $id_link);
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== ENDPOINTS ====================

    /**
     * GET /planes-pago/solicitud/@id_solicitud
     * Planes de cuotas que se crearon al aprobar la solicitud, con sus cuotas
     * (fecha, valor, saldo y estado).
     */
    public static function getBySolicitud($id_solicitud)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, descripcion, valor_base, valor_cobrar, motivo_descuento, tipo_recargo,
                   porcentaje_recargo, valor_recargo, valor_total, numero_cuotas, periodicidad, fecha_registro
            FROM planes_pago
            WHERE id_solicitud_inscripcion_publica = :id_solicitud AND id_tenant = :id_tenant
            ORDER BY fecha_registro, descripcion");
        $sentence->bindValue(':id_solicitud', $id_solicitud);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $planes = $sentence->fetchAll(PDO::FETCH_ASSOC);
        foreach ($planes as &$plan) {
            $plan['cuotas'] = PlanesPagoCuotas::listarDePlan($db, $plan['id']);
        }
        unset($plan);
        Flight::json($planes);
    }
}
