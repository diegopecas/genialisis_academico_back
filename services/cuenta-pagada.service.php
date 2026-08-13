<?php
class CuentaPagada
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_cuenta_por_cobrar, id_pago_recibido, valor_aplicado, valor_aplicado_mora, fecha from cuenta_pagada where id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_cuenta_por_cobrar, id_pago_recibido, valor_aplicado, valor_aplicado_mora, fecha from cuenta_pagada where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByPagoRecibido($id_pago_recibido)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                cp.id,
                cp.id_cuenta_por_cobrar,
                cp.id_pago_recibido,
                cp.valor_aplicado,
                cp.valor_aplicado_mora,
                cp.fecha,
                cpc.fecha AS fecha_cobro,
                ps.id AS id_producto_servicio,
                ps.nombre AS nombre_producto_servicio
            FROM cuenta_pagada cp
            INNER JOIN cuentas_por_cobrar cpc ON cp.id_cuenta_por_cobrar = cpc.id
            inner join productos_servicios ps on ps.id = cpc.id_producto_servicio
            WHERE cp.id_pago_recibido = :id_pago_recibido
            AND cp.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_pago_recibido', $id_pago_recibido);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Aplicaciones de pago de todo un anio, para el tab "Pagos Estudiantes" del
     * reporte de cartera. Se trae todo en una sola llamada y el front lo indexa
     * por persona y mes, para no ir al backend en cada clic de la matriz.
     *
     * El mes que se devuelve es el de la CUENTA POR COBRAR (el mes de la pension),
     * no el de la fecha del pago: asi es como el jardin lleva su Excel y asi es
     * como sp_reporte_anual_cuentas_por_cobrar calcula 'Saldo {Mes}', de modo que
     * el total del detalle cuadra exactamente con el valor de la celda.
     *
     * Se excluyen cuentas y pagos anulados, igual que el SP.
     */
    public static function getAplicacionesAnio($anio)
    {
        JWTService::requerirAutenticacion();

        try {
            if (!is_numeric($anio) || $anio < 2000 || $anio > 2100) {
                Flight::json([
                    'error' => true,
                    'message' => 'Anio invalido'
                ], 400);
                return;
            }

            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    c.id_persona,
                    MONTH(c.fecha) AS mes_cuenta,
                    c.fecha AS fecha_cuenta,
                    c.id AS id_cuenta_por_cobrar,
                    c.detalle AS detalle_cuenta,
                    cp.valor_aplicado,
                    pr.id AS id_pago_recibido,
                    pr.fecha AS fecha_pago,
                    pr.referencia_bancaria,
                    tp.nombre AS tipo_pago,
                    ps.id AS id_producto_servicio,
                    ps.nombre AS nombre_producto,
                    cps.nombre AS nombre_clasificacion
                FROM cuenta_pagada cp
                INNER JOIN cuentas_por_cobrar c ON c.id = cp.id_cuenta_por_cobrar
                INNER JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
                INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
                LEFT JOIN clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
                LEFT JOIN tipos_pagos tp ON tp.id = pr.id_tipo_pago
                WHERE cp.id_tenant = :id_tenant_cp
                    AND c.id_tenant = :id_tenant
                    AND YEAR(c.fecha) = :anio
                    AND (c.anulado = 0 OR c.anulado IS NULL)
                    AND (pr.anulado = 0 OR pr.anulado IS NULL)
                ORDER BY c.id_persona, MONTH(c.fecha), pr.fecha, ps.nombre
            ");

            $sentence->bindValue(':id_tenant_cp', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':anio', $anio, PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAplicacionesAnio: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener las aplicaciones de pago del anio',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getByCuentaPorCobrar($id_cuenta_por_cobrar)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                cp.id,
                cp.id_cuenta_por_cobrar,
                cp.id_pago_recibido,
                cp.valor_aplicado,
                cp.valor_aplicado_mora,
                cp.fecha AS fecha_aplicacion,
                pr.fecha AS fecha_pago,
                pr.referencia_bancaria,
                pr.anulado,
                tp.nombre AS tipo_pago
            FROM 
                cuenta_pagada cp
            INNER JOIN 
                pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            LEFT JOIN 
                tipos_pagos tp ON pr.id_tipo_pago = tp.id
            WHERE 
                cp.id_cuenta_por_cobrar = :id_cuenta_por_cobrar
                AND cp.id_tenant = :id_tenant
            ORDER BY
                pr.fecha DESC
        ");
        $sentence->bindParam(':id_cuenta_por_cobrar', $id_cuenta_por_cobrar);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_cuenta_por_cobrar = Flight::request()->data['id_cuenta_por_cobrar'];
        $id_pago_recibido = Flight::request()->data['id_pago_recibido'];
        $valor_aplicado = Flight::request()->data['valor_aplicado'];
        $fecha = Flight::request()->data['fecha'];
        // Parte del abono imputada a intereses. Si no viene es 0, de modo que
        // los consumidores que no manejan mora siguen funcionando igual.
        $valor_aplicado_mora = isset(Flight::request()->data['valor_aplicado_mora']) ? Flight::request()->data['valor_aplicado_mora'] : 0;

        $id = Uuid::generar();
        $sentence = $db->prepare("insert into cuenta_pagada(id, id_tenant, id_cuenta_por_cobrar, id_pago_recibido, valor_aplicado, valor_aplicado_mora, fecha) values (:id, :id_tenant, :id_cuenta_por_cobrar, :id_pago_recibido, :valor_aplicado, :valor_aplicado_mora, :fecha)");

        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_cuenta_por_cobrar', $id_cuenta_por_cobrar);
        $sentence->bindParam(':id_pago_recibido', $id_pago_recibido);
        $sentence->bindParam(':valor_aplicado', $valor_aplicado);
        $sentence->bindParam(':valor_aplicado_mora', $valor_aplicado_mora);
        $sentence->bindParam(':fecha', $fecha);

        $sentence->execute();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_cuenta_por_cobrar = Flight::request()->data['id_cuenta_por_cobrar'];
        $id_pago_recibido = Flight::request()->data['id_pago_recibido'];
        $valor_aplicado = Flight::request()->data['valor_aplicado'];
        $fecha = Flight::request()->data['fecha'];

        $valor_aplicado_mora = isset(Flight::request()->data['valor_aplicado_mora']) ? Flight::request()->data['valor_aplicado_mora'] : 0;

        $sentence = $db->prepare("update cuenta_pagada set id_cuenta_por_cobrar = :id_cuenta_por_cobrar, id_pago_recibido = :id_pago_recibido, valor_aplicado = :valor_aplicado, valor_aplicado_mora = :valor_aplicado_mora, fecha = :fecha where id = :id and id_tenant = :id_tenant");

        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':id_cuenta_por_cobrar', $id_cuenta_por_cobrar);
        $sentence->bindParam(':id_pago_recibido', $id_pago_recibido);
        $sentence->bindParam(':valor_aplicado', $valor_aplicado);
        $sentence->bindParam(':valor_aplicado_mora', $valor_aplicado_mora);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from cuenta_pagada where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
    // Agregar este método en cuenta-pagada.service.php

    public static function createBatch()
    {
        try {
            $db = Flight::db();

            // Obtener el array de cuentas del body
            $cuentas = Flight::request()->data['cuentas'];
            $id_pago_recibido = Flight::request()->data['id_pago_recibido'];

            if (empty($cuentas) || !is_array($cuentas)) {
                Flight::json(['error' => 'No se proporcionaron cuentas válidas'], 400);
                return;
            }

            // Iniciar transacción
            $db->beginTransaction();

            $resultados = [];
            $errores = 0;

            try {
                // Preparar la consulta una sola vez
                $query = "INSERT INTO cuenta_pagada (id, id_tenant, id_cuenta_por_cobrar, id_pago_recibido, valor_aplicado, valor_aplicado_mora, fecha) 
                     VALUES (:id, :id_tenant, :id_cuenta_por_cobrar, :id_pago_recibido, :valor_aplicado, :valor_aplicado_mora, :fecha)";
                $sentence = $db->prepare($query);

                // Insertar cada cuenta
                foreach ($cuentas as $cuenta) {
                    try {
                        $idCp = Uuid::generar();
                        $sentence->bindValue(':id', $idCp);
                        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                        $sentence->bindParam(':id_cuenta_por_cobrar', $cuenta['id_cuenta_por_cobrar']);
                        $sentence->bindParam(':id_pago_recibido', $id_pago_recibido);
                        $sentence->bindParam(':valor_aplicado', $cuenta['valor_aplicado']);
                        $valorMora = isset($cuenta['valor_aplicado_mora']) ? $cuenta['valor_aplicado_mora'] : 0;
                        $sentence->bindValue(':valor_aplicado_mora', $valorMora);
                        $sentence->bindParam(':fecha', $cuenta['fecha']);

                        $sentence->execute();

                        $resultados[] = [
                            'id' => $idCp,
                            'id_cuenta_por_cobrar' => $cuenta['id_cuenta_por_cobrar'],
                            'valor_aplicado' => $cuenta['valor_aplicado'],
                            'valor_aplicado_mora' => $valorMora,
                            'success' => true
                        ];
                    } catch (Exception $e) {
                        $errores++;
                        $resultados[] = [
                            'id_cuenta_por_cobrar' => $cuenta['id_cuenta_por_cobrar'],
                            'error' => $e->getMessage(),
                            'success' => false
                        ];
                    }
                }

                // Si hubo errores, hacer rollback
                if ($errores > 0) {
                    $db->rollBack();
                    Flight::json([
                        'error' => true,
                        'message' => "Se encontraron $errores errores al procesar las cuentas",
                        'detalles' => $resultados
                    ], 400);
                } else {
                    // Si todo salió bien, confirmar la transacción
                    $db->commit();
                    Flight::json([
                        'success' => true,
                        'message' => count($resultados) . ' cuentas aplicadas correctamente',
                        'resultados' => $resultados
                    ]);
                }
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            error_log('Error en createBatch cuenta_pagada: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al procesar las cuentas pagadas',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
}