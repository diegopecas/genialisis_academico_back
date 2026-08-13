<?php
/**
 * Liquidaciones de mora por cuenta y fecha de corte. Es la trazabilidad del
 * motor: deja constancia de con que base, tasa y dias se calculo cada cuenta
 * en cada corrida.
 *
 * Solo lectura desde la aplicacion; los registros los escribe MotorMora.
 */
class MoraCausaciones
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $fechaCorte = Flight::request()->query['fecha_corte'];
            if (empty($fechaCorte)) {
                $fechaCorte = date('Y-m-d');
            }

            $sentence = $db->prepare("
                SELECT
                    mc.id,
                    mc.id_cuenta_por_cobrar,
                    mc.fecha_corte,
                    mc.id_tipo_mora,
                    mc.base_calculo,
                    mc.dias_mora,
                    mc.porcentaje_mensual,
                    mc.valor_recargo,
                    mc.periodos_recargo,
                    mc.valor_causado,
                    mc.valor_pagado,
                    mc.exento,
                    c.fecha AS fecha_vencimiento,
                    c.valor AS valor_cuenta,
                    c.detalle,
                    c.id_persona,
                    tm.codigo AS codigo_tipo_mora,
                    tm.nombre AS nombre_tipo_mora,
                    ps.nombre AS nombre_producto_servicio,
                    CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_persona
                FROM mora_causaciones mc
                INNER JOIN cuentas_por_cobrar c ON mc.id_cuenta_por_cobrar = c.id
                LEFT JOIN tipos_mora tm ON mc.id_tipo_mora = tm.id
                LEFT JOIN productos_servicios ps ON c.id_producto_servicio = ps.id
                LEFT JOIN personas p ON c.id_persona = p.id
                WHERE mc.id_tenant = :id_tenant
                  AND mc.fecha_corte = :fecha_corte
                  AND mc.valor_causado > 0
                ORDER BY mc.valor_causado DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':fecha_corte', $fechaCorte);
            $sentence->execute();

            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en mora_causaciones getAll: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener las causaciones de mora'), 500);
        }
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    mc.id,
                    mc.id_cuenta_por_cobrar,
                    mc.fecha_corte,
                    mc.id_tipo_mora,
                    mc.base_calculo,
                    mc.dias_mora,
                    mc.porcentaje_mensual,
                    mc.valor_recargo,
                    mc.periodos_recargo,
                    mc.valor_causado,
                    mc.valor_pagado,
                    mc.exento,
                    tm.codigo AS codigo_tipo_mora,
                    tm.nombre AS nombre_tipo_mora
                FROM mora_causaciones mc
                LEFT JOIN tipos_mora tm ON mc.id_tipo_mora = tm.id
                WHERE mc.id = :id AND mc.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en mora_causaciones getById: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener la causacion de mora'), 500);
        }
    }

    /**
     * Historico de cortes de una cuenta: permite ver como evoluciono su mora
     * dia a dia y por que cambio.
     */
    public static function getByCuentaPorCobrar($id_cuenta_por_cobrar)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    mc.id,
                    mc.fecha_corte,
                    mc.base_calculo,
                    mc.dias_mora,
                    mc.porcentaje_mensual,
                    mc.valor_recargo,
                    mc.periodos_recargo,
                    mc.valor_causado,
                    mc.valor_pagado,
                    mc.exento,
                    tm.codigo AS codigo_tipo_mora
                FROM mora_causaciones mc
                LEFT JOIN tipos_mora tm ON mc.id_tipo_mora = tm.id
                WHERE mc.id_cuenta_por_cobrar = :id_cuenta_por_cobrar
                  AND mc.id_tenant = :id_tenant
                ORDER BY mc.fecha_corte DESC
            ");
            $sentence->bindParam(':id_cuenta_por_cobrar', $id_cuenta_por_cobrar);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en mora_causaciones getByCuentaPorCobrar: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener el historico de mora de la cuenta'), 500);
        }
    }

    /**
     * Mora vigente de una persona al ultimo corte, con su saldo pendiente.
     */
    public static function getByPersona($id_persona)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    cm.id AS id_cuenta_mora,
                    cm.id_cuenta_origen AS id_cuenta_por_cobrar,
                    cm.fecha AS fecha_vencimiento,
                    cm.valor AS mora_causada,
                    cm.detalle,
                    co.valor AS valor_cuenta,
                    ps.nombre AS nombre_producto_servicio,
                    COALESCE((
                        SELECT SUM(cp.valor_aplicado)
                        FROM cuenta_pagada cp
                        INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                        WHERE cp.id_cuenta_por_cobrar = cm.id
                          AND (pr.anulado = 0 OR pr.anulado IS NULL)
                    ), 0) AS mora_pagada
                FROM cuentas_por_cobrar cm
                LEFT JOIN cuentas_por_cobrar co ON cm.id_cuenta_origen = co.id
                LEFT JOIN productos_servicios ps ON co.id_producto_servicio = ps.id
                WHERE cm.id_persona = :id_persona
                  AND cm.id_tenant = :id_tenant
                  AND cm.es_mora = 1
                  AND (cm.anulado = 0 OR cm.anulado IS NULL)
                  AND cm.valor > 0
                ORDER BY cm.fecha
            ");
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en mora_causaciones getByPersona: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener la mora de la persona'), 500);
        }
    }

    /**
     * Escribe (o reescribe) el registro del corte de una cuenta. Uso interno
     * del motor: no responde HTTP.
     *
     * @param PDO    $db
     * @param array  $cuenta
     * @param array  $calculo     Salida de MotorMora::calcularCuenta()
     * @param string $fechaCorte
     * @return void
     */
    public static function registrarCorte($db, $cuenta, $calculo, $fechaCorte)
    {
        $sentence = $db->prepare("
            INSERT INTO mora_causaciones
                (id, id_tenant, id_cuenta_por_cobrar, fecha_corte, id_tipo_mora, base_calculo,
                 dias_mora, porcentaje_mensual, valor_recargo, periodos_recargo, valor_causado,
                 valor_pagado, exento, id_cuenta_mora)
            VALUES
                (:id, :id_tenant, :id_cuenta_por_cobrar, :fecha_corte, :id_tipo_mora, :base_calculo,
                 :dias_mora, :porcentaje_mensual, :valor_recargo, :periodos_recargo, :valor_causado,
                 :valor_pagado, :exento, :id_cuenta_mora)
            ON DUPLICATE KEY UPDATE
                id_cuenta_mora = VALUES(id_cuenta_mora),
                id_tipo_mora = VALUES(id_tipo_mora),
                base_calculo = VALUES(base_calculo),
                dias_mora = VALUES(dias_mora),
                porcentaje_mensual = VALUES(porcentaje_mensual),
                valor_recargo = VALUES(valor_recargo),
                periodos_recargo = VALUES(periodos_recargo),
                valor_causado = VALUES(valor_causado),
                valor_pagado = VALUES(valor_pagado),
                exento = VALUES(exento)
        ");

        $sentence->bindValue(':id', Uuid::generar());
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_cuenta_por_cobrar', $cuenta['id']);
        $sentence->bindValue(':fecha_corte', $fechaCorte);
        $sentence->bindValue(':id_tipo_mora', $calculo['id_tipo_mora']);
        $sentence->bindValue(':base_calculo', $calculo['base_calculo']);
        $sentence->bindValue(':dias_mora', $calculo['dias_mora'], PDO::PARAM_INT);
        $sentence->bindValue(':porcentaje_mensual', $calculo['porcentaje_mensual']);
        $sentence->bindValue(':valor_recargo', $calculo['valor_recargo']);
        $sentence->bindValue(':periodos_recargo', $calculo['periodos_recargo'], PDO::PARAM_INT);
        $sentence->bindValue(':valor_causado', $calculo['valor_causado']);
        $sentence->bindValue(':valor_pagado', $calculo['valor_pagado']);
        $sentence->bindValue(':exento', $calculo['exento'], PDO::PARAM_INT);
        $sentence->bindValue(':id_cuenta_mora', isset($calculo['id_cuenta_mora']) ? $calculo['id_cuenta_mora'] : null);
        $sentence->execute();
    }
}
