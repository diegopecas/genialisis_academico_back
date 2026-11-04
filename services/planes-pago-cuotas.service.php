<?php
/**
 * Cuotas de los planes de pago (planes_pago_cuotas). Cada cuota es una
 * cuenta por cobrar con su fecha de vencimiento; lo pagado y el saldo salen
 * de los pagos aplicados a esa cuenta (cuenta_pagada), asi que una cuota se
 * paga como cualquier cuenta: con el link de pago o en los pagos del
 * estudiante.
 *
 * La logica del plan (validar, repartir, crear las cuentas) esta en
 * PlanesPago.
 */
class PlanesPagoCuotas
{
    /**
     * Guarda una cuota. Corre dentro de la transaccion de quien crea el plan.
     *
     * @return string id de la cuota
     */
    public static function registrar($db, $id_plan_pago, $numero, $fecha_vencimiento, $valor, $id_cuenta_por_cobrar)
    {
        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO planes_pago_cuotas
            (id, id_tenant, id_plan_pago, numero, fecha_vencimiento, valor, id_cuenta_por_cobrar)
            VALUES (:id, :id_tenant, :id_plan, :numero, :fecha, :valor, :id_cuenta)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_plan', $id_plan_pago);
        $sentence->bindValue(':numero', (int) $numero, PDO::PARAM_INT);
        $sentence->bindValue(':fecha', $fecha_vencimiento);
        $sentence->bindValue(':valor', $valor);
        $sentence->bindValue(':id_cuenta', $id_cuenta_por_cobrar);
        $sentence->execute();
        return $id;
    }

    /**
     * Cuotas de un plan con lo pagado, el saldo y el estado:
     * pagada, pendiente, vencida (pasó la fecha sin pagar) o anulada (se anuló
     * su cuenta).
     *
     * @return array
     */
    public static function listarDePlan($db, $id_plan_pago)
    {
        $sentence = $db->prepare("SELECT pc.id, pc.numero, pc.fecha_vencimiento, pc.valor, pc.id_cuenta_por_cobrar,
                   c.detalle, c.anulado,
                   COALESCE((SELECT SUM(cp.valor_aplicado)
                               FROM cuenta_pagada cp
                               LEFT JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
                              WHERE cp.id_cuenta_por_cobrar = pc.id_cuenta_por_cobrar AND cp.id_tenant = pc.id_tenant
                                AND (pr.anulado = 0 OR pr.anulado IS NULL)), 0) AS pagado
            FROM planes_pago_cuotas pc
            INNER JOIN cuentas_por_cobrar c ON c.id = pc.id_cuenta_por_cobrar AND c.id_tenant = pc.id_tenant
            WHERE pc.id_plan_pago = :id_plan AND pc.id_tenant = :id_tenant
            ORDER BY pc.numero");
        $sentence->bindValue(':id_plan', $id_plan_pago);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $hoy = date('Y-m-d');
        $cuotas = array();
        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $saldo = round((float) $fila['valor'] - (float) $fila['pagado'], 2);
            if ((int) $fila['anulado'] === 1) {
                $estado = 'anulada';
            } elseif ($saldo <= 0) {
                $estado = 'pagada';
            } elseif ($fila['fecha_vencimiento'] < $hoy) {
                $estado = 'vencida';
            } else {
                $estado = 'pendiente';
            }
            $cuotas[] = array(
                'id' => $fila['id'],
                'numero' => (int) $fila['numero'],
                'fecha_vencimiento' => $fila['fecha_vencimiento'],
                'valor' => (float) $fila['valor'],
                'pagado' => (float) $fila['pagado'],
                'saldo' => max(0, $saldo),
                'estado' => $estado,
                'detalle' => $fila['detalle'],
                'id_cuenta_por_cobrar' => $fila['id_cuenta_por_cobrar']
            );
        }
        return $cuotas;
    }
}
