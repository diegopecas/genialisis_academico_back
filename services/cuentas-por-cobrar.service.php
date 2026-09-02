<?php
class CuentasPorCobrar
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    c.id, 
                    c.id_producto_servicio, 
                    c.id_persona, 
                    c.fecha, 
                    c.valor, 
                    c.detalle, 
                    c.id_usuario,
                    c.anulado,
                    c.fecha_anulacion,
                    c.id_usuario_anulacion,
                    c.id_horario_alimentacion,
                    COALESCE(SUM(cp.valor_aplicado), 0) AS valor_pagado,
                    c.valor - COALESCE(SUM(cp.valor_aplicado), 0) AS saldo
                FROM 
                    cuentas_por_cobrar c
                LEFT JOIN 
                    cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
                WHERE c.id_tenant = :id_tenant
                GROUP BY 
                    c.id, c.id_producto_servicio, c.id_persona, c.fecha, c.valor, c.detalle, c.id_usuario,
                    c.anulado, c.fecha_anulacion, c.id_usuario_anulacion, c.id_horario_alimentacion
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAll cuentas_por_cobrar: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener las cuentas por cobrar',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();

        error_log('getById: ' . $id);
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
            SELECT 
                c.id, 
                c.id_producto_servicio, 
                c.id_persona, 
                c.fecha, 
                c.valor, 
                c.detalle, 
                c.id_usuario,
                c.anulado,
                c.fecha_anulacion,
                c.id_usuario_anulacion,
                c.id_horario_alimentacion,
                COALESCE(SUM(
                    CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                        ELSE 0 
                    END
                ), 0) AS valor_pagado,
                c.valor - COALESCE(SUM(
                    CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                        ELSE 0 
                    END
                ), 0) AS saldo
            FROM 
                cuentas_por_cobrar c
            LEFT JOIN 
                cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
            LEFT JOIN 
                pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            WHERE 
                c.id = :id AND c.id_tenant = :id_tenant
            GROUP BY 
                c.id, c.id_producto_servicio, c.id_persona, c.fecha, c.valor, c.detalle, c.id_usuario,
                c.anulado, c.fecha_anulacion, c.id_usuario_anulacion, c.id_horario_alimentacion
        ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getById cuentas_por_cobrar: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener la cuenta por cobrar',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getByPersona($id)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
        SELECT 
            cpc.id,
            cpc.fecha,
            cpc.valor,
            cpc.id_usuario_anulacion,
            cpc.id_horario_alimentacion,
            ha.nombre AS nombre_horario_alimentacion,
            COALESCE(SUM(
                CASE 
                    WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                    ELSE 0 
                END
            ), 0) AS valor_pagado,
            cpc.valor - COALESCE(SUM(
                CASE 
                    WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                    ELSE 0 
                END
            ), 0) AS saldo,
            cpc.detalle,
            ps.nombre AS nombre_producto_servicio,
            ps.id_periodicidad_cobro,
            pc.nombre AS periodicidad_cobro_nombre,
            ps.id_clasificacion_productos_servicios,
            cps.nombre AS nombre_clasificacion,
            cps.codigo AS clasificacion_codigo,
            CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_usuario
        FROM 
            cuentas_por_cobrar cpc
        INNER JOIN 
            productos_servicios ps ON ps.id = cpc.id_producto_servicio
        INNER JOIN 
            clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
        INNER JOIN 
            usuarios u ON u.id = cpc.id_usuario
        INNER JOIN 
            personas p ON p.id = u.id_persona
        LEFT JOIN 
            periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
        LEFT JOIN 
            horarios_alimentacion ha ON ha.id = cpc.id_horario_alimentacion
        LEFT JOIN 
            cuenta_pagada cp ON cpc.id = cp.id_cuenta_por_cobrar
        LEFT JOIN 
            pagos_recibidos pr ON cp.id_pago_recibido = pr.id
        WHERE 
            cpc.id_persona = :id
            AND (cpc.anulado = 0 OR cpc.anulado IS NULL)
            AND cpc.id_tenant = :id_tenant
        GROUP BY 
            cpc.id, cpc.fecha, cpc.valor, cpc.detalle, ps.nombre, cps.nombre, cps.codigo, 
            p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
            cpc.anulado, cpc.fecha_anulacion, cpc.id_usuario_anulacion, cpc.id_horario_alimentacion,
            ha.id, ha.nombre, ps.id_periodicidad_cobro, pc.id, pc.nombre
        ORDER BY 
            cpc.fecha, cps.nombre, ps.nombre 
    ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getByPersona cuentas_por_cobrar: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener cuentas por cobrar de la persona',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $request = Flight::request();

            $data = $request->data->getData();

            // Parametros de mora del producto, congelados en la cuenta: un
            // cambio de tarifa posterior no debe alterar cuentas ya emitidas.
            $mora = self::parametrosMoraProducto($db, $data['id_producto_servicio']);

            $sql = "INSERT INTO cuentas_por_cobrar (
                id, id_tenant, id_producto_servicio, id_persona, fecha, valor, detalle, id_usuario, 
                anulado, fecha_anulacion, id_usuario_anulacion, id_horario_alimentacion,
                id_tipo_mora, valor_recargo_mora, porcentaje_mora_mensual, mora_acumulable
            ) VALUES (
                :id, :id_tenant, :id_producto_servicio, :id_persona, :fecha, :valor, :detalle, :id_usuario,
                0, NULL, NULL, :id_horario_alimentacion,
                :id_tipo_mora, :valor_recargo_mora, :porcentaje_mora_mensual, :mora_acumulable
            )";

            $stmt = $db->prepare($sql);
            $idCxcNew = Uuid::generar();
            $stmt->bindValue(':id', $idCxcNew);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_producto_servicio', $data['id_producto_servicio']);
            $stmt->bindParam(':id_persona', $data['id_persona']);
            $stmt->bindParam(':fecha', $data['fecha']);
            $stmt->bindParam(':valor', $data['valor']);
            $stmt->bindParam(':detalle', $data['detalle']);
            $stmt->bindParam(':id_usuario', $data['id_usuario']);
            $stmt->bindParam(':id_horario_alimentacion', $data['id_horario_alimentacion']);
            $stmt->bindValue(':id_tipo_mora', $mora['id_tipo_mora']);
            $stmt->bindValue(':valor_recargo_mora', $mora['valor_recargo_mora']);
            $stmt->bindValue(':porcentaje_mora_mensual', $mora['porcentaje_mora_mensual']);
            $stmt->bindValue(':mora_acumulable', $mora['mora_acumulable'], PDO::PARAM_INT);
            $stmt->execute();

            $id = $idCxcNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en cuentas_por_cobrar->new(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al crear cuenta por cobrar'), 500);
        }
    }

    /**
     * Parametros de mora vigentes de un producto, listos para copiarse a la
     * cuenta por cobrar. Si el producto no cobra mora, devuelve todo en NULL
     * y la cuenta simplemente no causa intereses.
     *
     * @param PDO    $db
     * @param string $id_producto_servicio
     * @return array
     */
    private static function parametrosMoraProducto($db, $id_producto_servicio)
    {
        $vacio = array(
            'id_tipo_mora'            => null,
            'valor_recargo_mora'      => null,
            'porcentaje_mora_mensual' => null,
            'mora_acumulable'         => 0
        );

        if (!class_exists('MoraConfiguracion')) {
            return $vacio;
        }

        $config = MoraConfiguracion::obtenerVigente($db, $id_producto_servicio);

        if (!$config) {
            return $vacio;
        }

        return array(
            'id_tipo_mora'            => $config['id_tipo_mora'],
            'valor_recargo_mora'      => $config['valor_recargo'],
            'porcentaje_mora_mensual' => $config['porcentaje_mensual'],
            'mora_acumulable'         => (int) $config['recargo_acumulable']
        );
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $sql = "UPDATE cuentas_por_cobrar SET
                id_producto_servicio = :id_producto_servicio,
                id_persona = :id_persona,
                fecha = :fecha,
                valor = :valor,
                detalle = :detalle,
                id_usuario = :id_usuario,
                id_horario_alimentacion = :id_horario_alimentacion";

            if (isset($data['anulado'])) {
                $sql .= ", anulado = :anulado";
            }
            if (isset($data['fecha_anulacion'])) {
                $sql .= ", fecha_anulacion = :fecha_anulacion";
            }
            if (isset($data['id_usuario_anulacion'])) {
                $sql .= ", id_usuario_anulacion = :id_usuario_anulacion";
            }

            $sql .= " WHERE id = :id AND id_tenant = :id_tenant";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindParam(':id_producto_servicio', $data['id_producto_servicio']);
            $stmt->bindParam(':id_persona', $data['id_persona']);
            $stmt->bindParam(':fecha', $data['fecha']);
            $stmt->bindParam(':valor', $data['valor']);
            $stmt->bindParam(':detalle', $data['detalle']);
            $stmt->bindParam(':id_usuario', $data['id_usuario']);
            $stmt->bindParam(':id_horario_alimentacion', $data['id_horario_alimentacion']);

            if (isset($data['anulado'])) {
                $stmt->bindParam(':anulado', $data['anulado']);
            }
            if (isset($data['fecha_anulacion'])) {
                $stmt->bindParam(':fecha_anulacion', $data['fecha_anulacion']);
            }
            if (isset($data['id_usuario_anulacion'])) {
                $stmt->bindParam(':id_usuario_anulacion', $data['id_usuario_anulacion']);
            }

            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el registro con el ID especificado para actualizar'), 404);
                return;
            }

            error_log("ID actualizado: " . $data['id']);
            Flight::json(array('id' => $data['id']));
        } catch (Exception $e) {
            error_log('Error en cuentas_por_cobrar->replace(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar cuenta por cobrar'), 500);
        }
    }

    public static function anular()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            if (!isset($data['id']) || !isset($data['id_usuario_anulacion'])) {
                Flight::json(array('error' => 'Faltan datos requeridos (id, id_usuario_anulacion)'), 400);
                return;
            }

            $fechaActual = date('Y-m-d H:i:s');

            $sql = "UPDATE cuentas_por_cobrar SET
                anulado = 1,
                fecha_anulacion = :fecha_anulacion,
                id_usuario_anulacion = :id_usuario_anulacion
                WHERE id = :id AND id_tenant = :id_tenant";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindParam(':fecha_anulacion', $fechaActual);
            $stmt->bindParam(':id_usuario_anulacion', $data['id_usuario_anulacion']);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el registro con el ID especificado para anular'), 404);
                return;
            }

            Flight::json(array(
                'id' => $data['id'],
                'anulado' => 1,
                'fecha_anulacion' => $fechaActual,
                'id_usuario_anulacion' => $data['id_usuario_anulacion']
            ));
        } catch (Exception $e) {
            error_log('Error en cuentas_por_cobrar->anular(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al anular cuenta por cobrar'), 500);
        }
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $stmt = $db->prepare("DELETE FROM cuentas_por_cobrar WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en cuentas_por_cobrar->delete(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al borrar cuentas_por_cobrar'), 500);
        }
    }

    public static function verificarDuplicados()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $sql = "SELECT 
                    c.id, 
                    c.fecha, 
                    c.valor,
                    c.id_horario_alimentacion,
                    COALESCE(SUM(cp.valor_aplicado), 0) AS valor_pagado,
                    c.valor - COALESCE(SUM(cp.valor_aplicado), 0) AS saldo
                FROM 
                    cuentas_por_cobrar c
                LEFT JOIN 
                    cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
                WHERE 
                    c.id_producto_servicio = :id_producto_servicio 
                    AND c.id_persona = :id_persona 
                    AND c.fecha = :fecha 
                    AND c.valor = :valor
                    AND c.id_horario_alimentacion = :id_horario_alimentacion
                    AND (c.anulado = 0 OR c.anulado IS NULL)
                    AND c.id_tenant = :id_tenant
                GROUP BY 
                    c.id, c.fecha, c.valor, c.id_horario_alimentacion";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id_producto_servicio', $data['id_producto_servicio']);
            $stmt->bindParam(':id_persona', $data['id_persona']);
            $stmt->bindParam(':fecha', $data['fecha']);
            $stmt->bindParam(':valor', $data['valor']);
            $stmt->bindParam(':id_horario_alimentacion', $data['id_horario_alimentacion']);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            $registrosDuplicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json([
                'duplicados' => $registrosDuplicados,
                'cantidad' => count($registrosDuplicados)
            ]);
        } catch (Exception $e) {
            error_log('Error en cuentas_por_cobrar->verificarDuplicados(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al verificar duplicados'), 500);
        }
    }

    public static function getAllConDetalle()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    c.id, 
                    c.id_producto_servicio, 
                    c.id_persona, 
                    c.fecha, 
                    c.valor, 
                    c.detalle, 
                    c.id_usuario,
                    c.anulado,
                    c.fecha_anulacion,
                    c.id_usuario_anulacion,
                    c.id_horario_alimentacion,
                    COALESCE(SUM(
                        CASE 
                            WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                            ELSE 0 
                        END
                    ), 0) AS valor_pagado,
                    c.valor - COALESCE(SUM(
                        CASE 
                            WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                            ELSE 0 
                        END
                    ), 0) AS saldo,
                    ps.nombre AS nombre_producto_servicio,
                    ps.id_clasificacion_productos_servicios,
                    cps.nombre AS nombre_clasificacion,
                    CONCAT(
                        COALESCE(p.primer_nombre, ''), ' ',
                        COALESCE(p.segundo_nombre, ''), ' ',
                        COALESCE(p.primer_apellido, ''), ' ',
                        COALESCE(p.segundo_apellido, '')
                    ) AS nombre_persona,
                    p.numero_identificacion,
                    e.id AS id_estudiante,
                    eg.id_grupo,
                    g.nombre AS nombre_grupo,
                    CONCAT(
                        COALESCE(pu.primer_nombre, ''), ' ',
                        COALESCE(pu.segundo_nombre, ''), ' ',
                        COALESCE(pu.primer_apellido, ''), ' ',
                        COALESCE(pu.segundo_apellido, '')
                    ) AS nombre_usuario
                FROM 
                    cuentas_por_cobrar c
                LEFT JOIN 
                    cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
                LEFT JOIN 
                    pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                LEFT JOIN
                    productos_servicios ps ON ps.id = c.id_producto_servicio
                LEFT JOIN
                    clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
                LEFT JOIN
                    personas p ON p.id = c.id_persona
                LEFT JOIN
                    estudiantes e ON e.id_persona = p.id
                LEFT JOIN
                    estudiantes_x_grupos eg ON eg.id_estudiante = e.id AND eg.activo = 1
                LEFT JOIN
                    grupos g ON g.id = eg.id_grupo
                LEFT JOIN
                    usuarios u ON u.id = c.id_usuario
                LEFT JOIN
                    personas pu ON pu.id = u.id_persona
                WHERE
                    (c.anulado = 0 OR c.anulado IS NULL)
                    AND c.id_tenant = :id_tenant
                GROUP BY 
                    c.id, c.id_producto_servicio, c.id_persona, c.fecha, c.valor, 
                    c.detalle, c.id_usuario, c.anulado, c.fecha_anulacion, 
                    c.id_usuario_anulacion, c.id_horario_alimentacion,
                    ps.nombre, ps.id_clasificacion_productos_servicios, cps.nombre,
                    p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                    p.numero_identificacion, e.id, eg.id_grupo, g.nombre,
                    pu.primer_nombre, pu.segundo_nombre, pu.primer_apellido, pu.segundo_apellido
                ORDER BY 
                    c.fecha DESC, p.primer_apellido, p.primer_nombre
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAllConDetalle cuentas_por_cobrar: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener las cuentas por cobrar con detalle',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getResumenCartera()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            $sentence = $db->prepare("
                SELECT 
                    COUNT(DISTINCT c.id) as total_cuentas,
                    SUM(c.valor) as total_cobrado,
                    SUM(CASE WHEN c.saldo <= 0 THEN 1 ELSE 0 END) as cuentas_pagadas,
                    SUM(CASE WHEN c.saldo > 0 THEN 1 ELSE 0 END) as cuentas_pendientes,
                    SUM(CASE 
                        WHEN c.saldo > 0 AND DATEDIFF(CURDATE(), c.fecha) > 30 
                        THEN 1 ELSE 0 
                    END) as cuentas_vencidas,
                    SUM(COALESCE(cp_sum.total_pagado, 0)) as total_recaudado,
                    SUM(CASE 
                        WHEN c.saldo > 0 
                        THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                        ELSE 0 
                    END) as saldo_pendiente,
                    SUM(CASE 
                        WHEN c.saldo > 0 AND DATEDIFF(CURDATE(), c.fecha) > 30 
                        THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                        ELSE 0 
                    END) as saldo_vencido
                FROM 
                    cuentas_por_cobrar c
                LEFT JOIN (
                    SELECT 
                        cp.id_cuenta_por_cobrar,
                        SUM(CASE 
                            WHEN pr.anulado = 0 OR pr.anulado IS NULL 
                            THEN cp.valor_aplicado 
                            ELSE 0 
                        END) as total_pagado
                    FROM cuenta_pagada cp
                    LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                    GROUP BY cp.id_cuenta_por_cobrar
                ) cp_sum ON c.id = cp_sum.id_cuenta_por_cobrar
                WHERE 
                    (c.anulado = 0 OR c.anulado IS NULL)
                    AND c.id_tenant = :id_tenant
            ");

            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resumen = $sentence->fetch();

            $resumen['porcentaje_recaudo'] = $resumen['total_cobrado'] > 0
                ? round(($resumen['total_recaudado'] / $resumen['total_cobrado']) * 100, 2)
                : 0;

            Flight::json($resumen);
        } catch (Exception $e) {
            error_log('Error en getResumenCartera: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener el resumen de cartera',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getReporteAnual($anio)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            if (!is_numeric($anio) || $anio < 2000 || $anio > 2100) {
                Flight::json([
                    'error' => true,
                    'message' => 'Año inválido'
                ], 400);
                return;
            }

            $stmt = $db->prepare("CALL sp_reporte_anual_cuentas_por_cobrar(:anio, :id_tenant)");
            $stmt->bindParam(':anio', $anio, PDO::PARAM_INT);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            $reporteEstudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $reporteValores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $reporteClasificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $reportePagosDiarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $estudianteClasificacion = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $reporteProductos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $estudianteProducto = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $reporteAnulados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json([
                'anio' => $anio,
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'reporte_estudiantes' => $reporteEstudiantes,
                'reporte_valores' => $reporteValores,
                'reporte_clasificaciones' => $reporteClasificaciones,
                'reporte_pagos_diarios' => $reportePagosDiarios,
                'estudiante_clasificacion' => $estudianteClasificacion,
                'reporte_productos' => $reporteProductos,
                'estudiante_producto' => $estudianteProducto,
                'reporte_anulados' => $reporteAnulados
            ]);
        } catch (Exception $e) {
            error_log('Error en getReporteAnual: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al generar el reporte anual',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getAllConDetalleAnual($anio)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            $sentence = $db->prepare("
            SELECT 
                c.id, 
                c.id_producto_servicio, 
                c.id_persona, 
                c.fecha, 
                c.valor, 
                c.detalle, 
                c.id_usuario,
                c.anulado,
                c.fecha_anulacion,
                c.id_usuario_anulacion,
                c.id_horario_alimentacion,
                COALESCE(SUM(
                    CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                        ELSE 0 
                    END
                ), 0) AS valor_pagado,
                c.valor - COALESCE(SUM(
                    CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                        ELSE 0 
                    END
                ), 0) AS saldo,
                ps.nombre AS nombre_producto_servicio,
                ps.id_clasificacion_productos_servicios,
                cps.nombre AS nombre_clasificacion,
                CONCAT(
                    COALESCE(p.primer_nombre, ''), ' ',
                    COALESCE(p.segundo_nombre, ''), ' ',
                    COALESCE(p.primer_apellido, ''), ' ',
                    COALESCE(p.segundo_apellido, '')
                ) AS nombre_persona,
                p.numero_identificacion,
                e.id AS id_estudiante,
                eg.id_grupo,
                g.nombre AS nombre_grupo,
                CONCAT(
                    COALESCE(pu.primer_nombre, ''), ' ',
                    COALESCE(pu.segundo_nombre, ''), ' ',
                    COALESCE(pu.primer_apellido, ''), ' ',
                    COALESCE(pu.segundo_apellido, '')
                ) AS nombre_usuario
            FROM 
                cuentas_por_cobrar c
            LEFT JOIN 
                cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
            LEFT JOIN 
                pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            LEFT JOIN
                productos_servicios ps ON ps.id = c.id_producto_servicio
            LEFT JOIN
                clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
            LEFT JOIN
                personas p ON p.id = c.id_persona
            LEFT JOIN
                estudiantes e ON e.id_persona = p.id
            LEFT JOIN
                estudiantes_x_grupos eg ON eg.id_estudiante = e.id AND eg.activo = 1
            LEFT JOIN
                grupos g ON g.id = eg.id_grupo
            LEFT JOIN
                usuarios u ON u.id = c.id_usuario
            LEFT JOIN
                personas pu ON pu.id = u.id_persona
            WHERE
                YEAR(c.fecha) = :anio
                AND (c.anulado = 0 OR c.anulado IS NULL)
                AND c.id_tenant = :id_tenant
            GROUP BY 
                c.id, c.id_producto_servicio, c.id_persona, c.fecha, c.valor, 
                c.detalle, c.id_usuario, c.anulado, c.fecha_anulacion, 
                c.id_usuario_anulacion, c.id_horario_alimentacion,
                ps.nombre, ps.id_clasificacion_productos_servicios, cps.nombre,
                p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                p.numero_identificacion, e.id, eg.id_grupo, g.nombre,
                pu.primer_nombre, pu.segundo_nombre, pu.primer_apellido, pu.segundo_apellido
            ORDER BY 
                c.fecha DESC, p.primer_apellido, p.primer_nombre
        ");

            $sentence->bindParam(':anio', $anio);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAllConDetalleAnual cuentas_por_cobrar: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener las cuentas por cobrar con detalle',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getResumenCarteraAnual($anio)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            $sentence = $db->prepare("
            SELECT 
                COUNT(DISTINCT c.id) as total_cuentas,
                SUM(c.valor) as total_cobrado,
                SUM(CASE WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) <= 0 THEN 1 ELSE 0 END) as cuentas_pagadas,
                SUM(CASE WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 THEN 1 ELSE 0 END) as cuentas_pendientes,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 AND DATEDIFF(CURDATE(), c.fecha) > 30 
                    THEN 1 ELSE 0 
                END) as cuentas_vencidas,
                SUM(COALESCE(cp_sum.total_pagado, 0)) as total_recaudado,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) as saldo_pendiente,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 AND DATEDIFF(CURDATE(), c.fecha) > 30 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) as saldo_vencido
            FROM 
                cuentas_por_cobrar c
            LEFT JOIN (
                SELECT 
                    cp.id_cuenta_por_cobrar,
                    SUM(CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL 
                        THEN cp.valor_aplicado 
                        ELSE 0 
                    END) as total_pagado
                FROM cuenta_pagada cp
                LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                GROUP BY cp.id_cuenta_por_cobrar
            ) cp_sum ON c.id = cp_sum.id_cuenta_por_cobrar
            WHERE 
                YEAR(c.fecha) = :anio
                AND (c.anulado = 0 OR c.anulado IS NULL)
                AND c.id_tenant = :id_tenant
        ");

            $sentence->bindParam(':anio', $anio);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resumen = $sentence->fetch();

            $resumen['porcentaje_recaudo'] = $resumen['total_cobrado'] > 0
                ? round(($resumen['total_recaudado'] / $resumen['total_cobrado']) * 100, 2)
                : 0;

            $resumen['anio'] = $anio;

            Flight::json($resumen);
        } catch (Exception $e) {
            error_log('Error en getResumenCarteraAnual: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener el resumen de cartera anual',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getByMultipleIds()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            $ids = Flight::request()->data['ids'];

            if (empty($ids) || !is_array($ids)) {
                Flight::json(['error' => 'No se proporcionaron IDs válidos'], 400);
                return;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $query = "
            SELECT 
                cpc.id,
                cpc.id_producto_servicio,
                cpc.id_persona,
                cpc.fecha,
                cpc.valor,
                cpc.detalle,
                cpc.id_horario_alimentacion,
                cpc.fecha_generado,
                cpc.id_usuario,
                cpc.anulado,
                cpc.fecha_anulacion,
                cpc.id_usuario_anulacion,
                ps.nombre AS nombre_producto_servicio,
                ps.id_categoria_productos_servicios,
                cps.nombre AS nombre_categoria,
                CONCAT(p.primer_nombre, ' ', COALESCE(p.segundo_nombre, ''), ' ', 
                       p.primer_apellido, ' ', COALESCE(p.segundo_apellido, '')) AS nombre_persona,
                COALESCE((SELECT SUM(cp.valor_aplicado) 
                         FROM cuenta_pagada cp 
                         WHERE cp.id_cuenta_por_cobrar = cpc.id), 0) AS valor_pagado,
                (cpc.valor - COALESCE((SELECT SUM(cp.valor_aplicado) 
                                      FROM cuenta_pagada cp 
                                      WHERE cp.id_cuenta_por_cobrar = cpc.id), 0)) AS saldo
            FROM 
                cuentas_por_cobrar cpc
            LEFT JOIN 
                productos_servicios ps ON cpc.id_producto_servicio = ps.id
            LEFT JOIN 
                categoria_productos_servicios cps ON ps.id_categoria_productos_servicios = cps.id
            LEFT JOIN 
                personas p ON cpc.id_persona = p.id
            WHERE 
                cpc.id IN ($placeholders) AND cpc.id_tenant = ?
            ORDER BY 
                cpc.fecha ASC
        ";

            $sentence = $db->prepare($query);
            $sentence->execute(array_merge($ids, [TenantContext::id()]));
            $response = $sentence->fetchAll();

            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getByMultipleIds: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener las cuentas por cobrar',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera cuentas por cobrar a partir de los valores de un contrato de matrícula.
     */
    public static function generarDesdeContrato()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            $id_contrato = Flight::request()->data['id_contrato'];
            $id_usuario = Flight::request()->data['id_usuario'];

            $stmtContrato = $db->prepare("
                SELECT cm.id, cm.id_estudiante, cm.anio, e.id_persona
                FROM contratos_matricula cm
                INNER JOIN estudiantes e ON cm.id_estudiante = e.id
                WHERE cm.id = :id_contrato AND cm.id_tenant = :id_tenant
            ");
            $stmtContrato->bindParam(':id_contrato', $id_contrato);
            $stmtContrato->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtContrato->execute();
            $contrato = $stmtContrato->fetch(PDO::FETCH_ASSOC);

            if (!$contrato) {
                Flight::json(array('error' => 'Contrato no encontrado'), 404);
                return;
            }

            $id_persona = $contrato['id_persona'];

            $stmtValores = $db->prepare("
                SELECT cmv.id, cmv.id_producto_servicio, cmv.fecha, cmv.valor,
                       ps.nombre AS nombre_producto,
                       ps.id_periodicidad_cobro
                FROM contratos_matricula_valores cmv
                INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
                WHERE cmv.id_contrato_matricula = :id_contrato AND cmv.id_tenant = :id_tenant
                ORDER BY cmv.fecha, ps.id_periodicidad_cobro
            ");
            $stmtValores->bindParam(':id_contrato', $id_contrato);
            $stmtValores->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtValores->execute();
            $valores = $stmtValores->fetchAll(PDO::FETCH_ASSOC);

            if (empty($valores)) {
                Flight::json(array('error' => 'El contrato no tiene valores generados'), 400);
                return;
            }

            $stmtVerificar = $db->prepare("
                SELECT COUNT(*) AS cantidad
                FROM cuentas_por_cobrar
                WHERE id_persona = :id_persona
                  AND id_producto_servicio = :id_producto_servicio
                  AND fecha = :fecha
                  AND (anulado = 0 OR anulado IS NULL)
                  AND id_tenant = :id_tenant
            ");

            $duplicados = [];
            foreach ($valores as $valor) {
                $stmtVerificar->bindParam(':id_persona', $id_persona);
                $stmtVerificar->bindParam(':id_producto_servicio', $valor['id_producto_servicio']);
                $stmtVerificar->bindParam(':fecha', $valor['fecha']);
                $stmtVerificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtVerificar->execute();
                $resultado = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

                if ($resultado['cantidad'] > 0) {
                    $duplicados[] = [
                        'nombre_producto' => $valor['nombre_producto'],
                        'fecha' => $valor['fecha']
                    ];
                }
            }

            if (!empty($duplicados)) {
                Flight::json(array(
                    'duplicados' => $duplicados,
                    'mensaje' => 'Ya existen cuentas por cobrar para algunos conceptos. Debe generarlas manualmente.'
                ));
                return;
            }

            $db->beginTransaction();

            $stmtInsert = $db->prepare("
                INSERT INTO cuentas_por_cobrar 
                (id, id_tenant, id_producto_servicio, id_persona, fecha, valor, detalle, id_usuario, 
                 anulado, fecha_anulacion, id_usuario_anulacion, id_horario_alimentacion,
                 id_tipo_mora, valor_recargo_mora, porcentaje_mora_mensual, mora_acumulable)
                VALUES 
                (:id, :id_tenant, :id_producto_servicio, :id_persona, :fecha, :valor, :detalle, :id_usuario,
                 0, NULL, NULL, NULL,
                 :id_tipo_mora, :valor_recargo_mora, :porcentaje_mora_mensual, :mora_acumulable)
            ");

            $cuentasCreadas = 0;
            $totalMatricula = 0;
            $totalPension = 0;

            $nombresMeses = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            ];

            foreach ($valores as $valor) {
                $mesNum = (int) date('n', strtotime($valor['fecha']));
                $anioFecha = date('Y', strtotime($valor['fecha']));
                $nombreMes = $nombresMeses[$mesNum];

                $tipoConcepto = ($valor['id_periodicidad_cobro'] == 1) ? 'Matrícula' : 'Pensión';
                $detalle = "Generado automáticamente - Contrato #{$id_contrato} - {$tipoConcepto} {$nombreMes} {$anioFecha}";

                $idCxc = Uuid::generar();
                $mora = self::parametrosMoraProducto($db, $valor['id_producto_servicio']);
                $stmtInsert->bindValue(':id', $idCxc);
                $stmtInsert->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtInsert->bindParam(':id_producto_servicio', $valor['id_producto_servicio']);
                $stmtInsert->bindParam(':id_persona', $id_persona);
                $stmtInsert->bindParam(':fecha', $valor['fecha']);
                $stmtInsert->bindParam(':valor', $valor['valor']);
                $stmtInsert->bindParam(':detalle', $detalle);
                $stmtInsert->bindParam(':id_usuario', $id_usuario);
                $stmtInsert->bindValue(':id_tipo_mora', $mora['id_tipo_mora']);
                $stmtInsert->bindValue(':valor_recargo_mora', $mora['valor_recargo_mora']);
                $stmtInsert->bindValue(':porcentaje_mora_mensual', $mora['porcentaje_mora_mensual']);
                $stmtInsert->bindValue(':mora_acumulable', $mora['mora_acumulable'], PDO::PARAM_INT);
                $stmtInsert->execute();

                $cuentasCreadas++;

                if ($valor['id_periodicidad_cobro'] == 1) {
                    $totalMatricula += $valor['valor'];
                } else {
                    $totalPension += $valor['valor'];
                }
            }

            $db->commit();

            Flight::json(array(
                'success' => true,
                'cuentas_creadas' => $cuentasCreadas,
                'total_matricula' => $totalMatricula,
                'total_pension' => $totalPension,
                'total_general' => $totalMatricula + $totalPension
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en CuentasPorCobrar::generarDesdeContrato: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Reporte de cartera de estudiantes con acudientes responsables de pago.
     */
    public static function getReporteCarteraEstudiantes($anio, $idEstudiante = null)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            if (!is_numeric($anio) || $anio < 2000 || $anio > 2100) {
                Flight::json([
                    'error' => true,
                    'message' => 'Año inválido'
                ], 400);
                return;
            }

            $idEst = ($idEstudiante !== null && $idEstudiante !== 'null') ? $idEstudiante : null;

            $stmt = $db->prepare("CALL sp_reporte_cartera_estudiantes(:anio, :id_estudiante, :id_tenant)");
            $stmt->bindParam(':anio', $anio, PDO::PARAM_INT);
            $stmt->bindParam(':id_estudiante', $idEst, PDO::PARAM_STR);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            $reporteEstudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();

            $reporteValores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();

            $acudientesPago = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json([
                'anio' => $anio,
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'reporte_estudiantes' => $reporteEstudiantes,
                'reporte_valores' => $reporteValores,
                'acudientes_pago' => $acudientesPago
            ]);
        } catch (Exception $e) {
            error_log('Error en getReporteCarteraEstudiantes: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al generar el reporte de cartera de estudiantes',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cuentas por cobrar del año que aún tienen saldo, para el detalle de la
     * matriz de "Saldos Estudiantes" / "Saldos Colaboradores" del reporte de
     * cartera. Se trae todo el año en una sola llamada y el front lo indexa por
     * persona y mes, para que el clic en una celda no vaya al backend.
     *
     * El mes que se devuelve es el de la cuenta por cobrar (el mes de la pensión),
     * igual que como sp_reporte_anual_cuentas_por_cobrar calcula 'Saldo {Mes}',
     * de modo que el total del detalle cuadre con el valor de la celda.
     *
     * Se excluyen cuentas anuladas y aplicaciones de pagos anulados, igual que el SP.
     * Las cuentas de mora (es_mora = 1) se incluyen como un concepto más.
     */
    public static function getPendientesAnio($anio)
    {
        JWTService::requerirAutenticacion();

        try {
            if (!is_numeric($anio) || $anio < 2000 || $anio > 2100) {
                Flight::json([
                    'error' => true,
                    'message' => 'Año inválido'
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
                    c.valor AS valor_cuenta,
                    c.es_mora,
                    COALESCE(ap.total_aplicado, 0) AS valor_abonado,
                    ROUND(c.valor - COALESCE(ap.total_aplicado, 0), 2) AS saldo_pendiente,
                    ps.id AS id_producto_servicio,
                    ps.nombre AS nombre_producto,
                    cps.nombre AS nombre_clasificacion
                FROM cuentas_por_cobrar c
                INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
                LEFT JOIN clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
                LEFT JOIN (
                    SELECT
                        cp.id_cuenta_por_cobrar,
                        SUM(cp.valor_aplicado) AS total_aplicado
                    FROM cuenta_pagada cp
                    INNER JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
                    INNER JOIN cuentas_por_cobrar c2 ON c2.id = cp.id_cuenta_por_cobrar
                    WHERE cp.id_tenant = :id_tenant_cp
                        AND c2.id_tenant = :id_tenant_c2
                        AND YEAR(c2.fecha) = :anio_aplicaciones
                        AND (pr.anulado = 0 OR pr.anulado IS NULL)
                    GROUP BY cp.id_cuenta_por_cobrar
                ) ap ON ap.id_cuenta_por_cobrar = c.id
                WHERE c.id_tenant = :id_tenant
                    AND YEAR(c.fecha) = :anio
                    AND (c.anulado = 0 OR c.anulado IS NULL)
                    AND ROUND(c.valor - COALESCE(ap.total_aplicado, 0), 2) > 0
                ORDER BY c.id_persona, MONTH(c.fecha), c.fecha, ps.nombre
            ");

            $sentence->bindValue(':id_tenant_cp', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant_c2', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':anio_aplicaciones', $anio, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':anio', $anio, PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);

            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getPendientesAnio: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener las cuentas pendientes del año',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte desagregado de cobros por año. Incluye estudiantes y colaboradores.
     * Retorna cada registro individual con tipo_persona y grupo_o_cargo derivados.
     */
    public static function getReporteCobrosAnual($anio)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            if (!is_numeric($anio) || $anio < 2000 || $anio > 2100) {
                Flight::json([
                    'error' => true,
                    'message' => 'Año inválido'
                ], 400);
                return;
            }

            $sentence = $db->prepare("
                SELECT 
                    c.id, 
                    c.id_producto_servicio, 
                    c.id_persona, 
                    c.fecha, 
                    c.valor, 
                    c.detalle, 
                    c.id_usuario,
                    c.anulado,
                    COALESCE(SUM(
                        CASE 
                            WHEN (pr.anulado = 0 OR pr.anulado IS NULL) THEN cp.valor_aplicado 
                            ELSE 0 
                        END
                    ), 0) AS valor_pagado,
                    c.valor - COALESCE(SUM(
                        CASE 
                            WHEN (pr.anulado = 0 OR pr.anulado IS NULL) THEN cp.valor_aplicado 
                            ELSE 0 
                        END
                    ), 0) AS saldo,
                    ps.nombre AS nombre_producto_servicio,
                    ps.id_clasificacion_productos_servicios,
                    cps.nombre AS nombre_clasificacion,
                    TRIM(CONCAT(
                        COALESCE(p.primer_nombre, ''), ' ',
                        COALESCE(p.segundo_nombre, ''), ' ',
                        COALESCE(p.primer_apellido, ''), ' ',
                        COALESCE(p.segundo_apellido, '')
                    )) AS nombre_persona,
                    p.numero_identificacion,
                    CASE 
                        WHEN e.id IS NOT NULL THEN 'Estudiante'
                        WHEN col.id IS NOT NULL THEN 'Colaborador'
                        ELSE 'Otro'
                    END AS tipo_persona,
                    CASE 
                        WHEN e.id IS NOT NULL THEN COALESCE(g.nombre, 'Sin grupo')
                        WHEN col.id IS NOT NULL THEN COALESCE(ca.nombre, 'Sin cargo')
                        ELSE 'Sin asignar'
                    END AS grupo_o_cargo,
                    e.id AS id_estudiante,
                    col.id AS id_colaborador
                FROM 
                    cuentas_por_cobrar c
                LEFT JOIN 
                    cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
                LEFT JOIN 
                    pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                LEFT JOIN
                    productos_servicios ps ON ps.id = c.id_producto_servicio
                LEFT JOIN
                    clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
                LEFT JOIN
                    personas p ON p.id = c.id_persona
                LEFT JOIN
                    estudiantes e ON e.id_persona = p.id
                LEFT JOIN
                    estudiantes_x_grupos eg ON eg.id_estudiante = e.id AND eg.activo = 1
                LEFT JOIN
                    grupos g ON g.id = eg.id_grupo
                LEFT JOIN
                    colaboradores col ON col.id_persona = p.id
                LEFT JOIN
                    cargos ca ON ca.id = col.id_cargo
                WHERE
                    YEAR(c.fecha) = :anio
                    AND (c.anulado = 0 OR c.anulado IS NULL)
                    AND c.id_tenant = :id_tenant
                GROUP BY 
                    c.id, c.id_producto_servicio, c.id_persona, c.fecha, c.valor, 
                    c.detalle, c.id_usuario, c.anulado,
                    ps.nombre, ps.id_clasificacion_productos_servicios, cps.nombre,
                    p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                    p.numero_identificacion, e.id, eg.id_grupo, g.nombre,
                    col.id, ca.nombre
                ORDER BY 
                    c.fecha DESC, p.primer_apellido, p.primer_nombre
            ");

            $sentence->bindParam(':anio', $anio, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getReporteCobrosAnual: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener el reporte de cobros',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }


    public static function generarDesdeCursoExtra()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $body = Flight::request()->getBody();
            $data = json_decode($body, true);

            error_log("generarDesdeCursoExtra - body recibido: " . $body);
            error_log("generarDesdeCursoExtra - data decodificado: " . print_r($data, true));

            $id_usuario = isset($data['id_usuario']) ? $data['id_usuario'] : null;
            $id_curso_extra = isset($data['id_curso_extra']) ? $data['id_curso_extra'] : null;
            $valores = isset($data['valores']) ? $data['valores'] : [];
            $inscripciones = isset($data['inscripciones']) ? $data['inscripciones'] : null;

            if (empty($valores)) {
                Flight::json(array('error' => 'No hay valores para generar'), 400);
                return;
            }

            $db->beginTransaction();

            $stmtVerificar = $db->prepare("
                SELECT COUNT(*) AS cantidad
                FROM cuentas_por_cobrar
                WHERE id_persona = :id_persona
                  AND id_producto_servicio = :id_producto_servicio
                  AND fecha = :fecha
                  AND (anulado = 0 OR anulado IS NULL)
                  AND id_tenant = :id_tenant
            ");

            $stmtInsertCuenta = $db->prepare("
                INSERT INTO cuentas_por_cobrar 
                (id, id_tenant, id_producto_servicio, id_persona, fecha, valor, detalle, id_usuario, 
                 anulado, fecha_anulacion, id_usuario_anulacion, id_horario_alimentacion,
                 id_tipo_mora, valor_recargo_mora, porcentaje_mora_mensual, mora_acumulable)
                VALUES 
                (:id, :id_tenant, :id_producto_servicio, :id_persona, :fecha, :valor, :detalle, :id_usuario,
                 0, NULL, NULL, NULL,
                 :id_tipo_mora, :valor_recargo_mora, :porcentaje_mora_mensual, :mora_acumulable)
            ");

            $stmtInsertRelacion = $db->prepare("
                INSERT INTO cuentas_cobrar_x_curso_extra 
                (id, id_tenant, id_estudiante_x_curso_extra, id_cuenta_por_cobrar, fecha_registro)
                VALUES (:id, :id_tenant, :id_inscripcion, :id_cuenta, NOW())
            ");

            $stmtPersona = $db->prepare("
                SELECT e.id_persona FROM estudiantes e WHERE e.id = :id_estudiante AND e.id_tenant = :id_tenant
            ");

            $cuentasCreadas = 0;
            $totalMatricula = 0;
            $totalPension = 0;
            $totalUnico = 0;
            $duplicadosGlobal = [];

            // Si viene un solo estudiante (desde crear-curso-extra-estudiante)
            if (empty($inscripciones)) {
                $id_persona = isset($data['id_persona']) ? $data['id_persona'] : null;
                $id_inscripcion = isset($data['id_inscripcion']) ? $data['id_inscripcion'] : null;
                
                if (!$id_persona || !$id_inscripcion) {
                    $db->rollBack();
                    Flight::json(array('error' => 'Faltan datos: id_persona o id_inscripcion'), 400);
                    return;
                }
                
                $inscripciones = [['id_inscripcion' => $id_inscripcion, 'id_persona' => $id_persona]];
            }

            foreach ($inscripciones as $inscripcion) {
                $idInscripcion = $inscripcion['id_inscripcion'];
                $idPersonaEst = isset($inscripcion['id_persona']) ? $inscripcion['id_persona'] : null;

                if (!$idPersonaEst && isset($inscripcion['id_estudiante'])) {
                    $stmtPersona->bindParam(':id_estudiante', $inscripcion['id_estudiante']);
                    $stmtPersona->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtPersona->execute();
                    $personaResult = $stmtPersona->fetch(PDO::FETCH_ASSOC);
                    $idPersonaEst = $personaResult ? $personaResult['id_persona'] : null;
                }

                if (!$idPersonaEst) continue;

                $duplicados = [];
                foreach ($valores as $valor) {
                    $stmtVerificar->bindParam(':id_persona', $idPersonaEst);
                    $stmtVerificar->bindParam(':id_producto_servicio', $valor['id_producto_servicio']);
                    $stmtVerificar->bindParam(':fecha', $valor['fecha']);
                    $stmtVerificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtVerificar->execute();
                    $resultado = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

                    if ($resultado['cantidad'] > 0) {
                        $duplicados[] = [
                            'nombre_producto' => $valor['nombre_producto'] ?? 'Producto',
                            'fecha' => $valor['fecha']
                        ];
                    }
                }

                if (!empty($duplicados)) {
                    $duplicadosGlobal[$idInscripcion] = $duplicados;
                    continue;
                }

                foreach ($valores as $valor) {
                    $detalle = $valor['detalle'] ?? "Curso Extra #{$id_curso_extra}";

                    $idCxc2 = Uuid::generar();
                    $stmtInsertCuenta->bindValue(':id', $idCxc2);
                    $stmtInsertCuenta->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtInsertCuenta->bindParam(':id_producto_servicio', $valor['id_producto_servicio']);
                    $stmtInsertCuenta->bindParam(':id_persona', $idPersonaEst);
                    $stmtInsertCuenta->bindParam(':fecha', $valor['fecha']);
                    $stmtInsertCuenta->bindParam(':valor', $valor['valor']);
                    $stmtInsertCuenta->bindParam(':detalle', $detalle);
                    $stmtInsertCuenta->bindParam(':id_usuario', $id_usuario);
                    $moraCursoExtra = self::parametrosMoraProducto($db, $valor['id_producto_servicio']);
                    $stmtInsertCuenta->bindValue(':id_tipo_mora', $moraCursoExtra['id_tipo_mora']);
                    $stmtInsertCuenta->bindValue(':valor_recargo_mora', $moraCursoExtra['valor_recargo_mora']);
                    $stmtInsertCuenta->bindValue(':porcentaje_mora_mensual', $moraCursoExtra['porcentaje_mora_mensual']);
                    $stmtInsertCuenta->bindValue(':mora_acumulable', $moraCursoExtra['mora_acumulable'], PDO::PARAM_INT);
                    $stmtInsertCuenta->execute();

                    $idCuenta = $idCxc2;

                    $idRel = Uuid::generar();
                    $stmtInsertRelacion->bindValue(':id', $idRel);
                    $stmtInsertRelacion->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtInsertRelacion->bindParam(':id_inscripcion', $idInscripcion);
                    $stmtInsertRelacion->bindParam(':id_cuenta', $idCuenta);
                    $stmtInsertRelacion->execute();

                    $cuentasCreadas++;

                    $tipo = $valor['tipo'] ?? 'unico';
                    if ($tipo === 'matricula') {
                        $totalMatricula += $valor['valor'];
                    } else if ($tipo === 'pension') {
                        $totalPension += $valor['valor'];
                    } else {
                        $totalUnico += $valor['valor'];
                    }
                }
            }

            $db->commit();

            $response = array(
                'success' => true,
                'cuentas_creadas' => $cuentasCreadas,
                'total_matricula' => $totalMatricula,
                'total_pension' => $totalPension,
                'total_unico' => $totalUnico,
                'total_general' => $totalMatricula + $totalPension + $totalUnico
            );

            if (!empty($duplicadosGlobal)) {
                $response['duplicados_parciales'] = $duplicadosGlobal;
            }

            Flight::json($response);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en CuentasPorCobrar::generarDesdeCursoExtra: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Datos base de la pantalla de Registro Rapido de Cobros: el listado de
     * estudiantes con grupo y estado. Los productos salen del catalogo
     * (productos-servicios) y la asistencia del servicio de asistencia, por eso
     * aqui solo viajan los estudiantes.
     *
     * Se traen activos e inactivos: la pantalla filtra por estado y a veces hay
     * que cobrarle a un estudiante ya retirado.
     */
    public static function getDatosCobrosRapido()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            $stmtEstudiantes = $db->prepare("
                SELECT DISTINCT
                    e.id AS id_estudiante,
                    e.id_persona,
                    CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                           IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_estudiante,
                    p.numero_identificacion,
                    IFNULL(g.nombre, 'Sin grupo') AS grupo_estudiante,
                    e.activo
                FROM estudiantes e
                INNER JOIN personas p ON e.id_persona = p.id
                LEFT JOIN estudiantes_x_grupos eg ON e.id = eg.id_estudiante AND eg.activo = 1
                LEFT JOIN grupos g ON eg.id_grupo = g.id
                WHERE e.id_tenant = :id_tenant
                ORDER BY g.nombre, p.primer_nombre, p.primer_apellido
            ");
            $stmtEstudiantes->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtEstudiantes->execute();
            $estudiantes = $stmtEstudiantes->fetchAll(PDO::FETCH_ASSOC);

            // Los nombres se arman por concatenacion y quedan con espacios dobles
            // cuando falta el segundo nombre o el segundo apellido.
            foreach ($estudiantes as &$est) {
                $est['nombre_estudiante'] = trim(preg_replace('/\s+/', ' ', $est['nombre_estudiante']));
                $est['activo'] = (int) $est['activo'];
            }
            unset($est);

            Flight::json(array('estudiantes' => $estudiantes));
        } catch (Exception $e) {
            error_log('Error en CuentasPorCobrar::getDatosCobrosRapido(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener los datos para el registro rapido de cobros'), 500);
        }
    }

    /**
     * Generacion masiva de cuentas por cobrar: un mismo producto para varios
     * estudiantes, repetido en un rango de meses del anio.
     *
     * Cada estudiante llega con su propio valor y su propio detalle porque la
     * pantalla permite ajustarlos fila por fila sobre el valor y el detalle
     * generales.
     *
     * Las cuentas ya existentes (mismo estudiante, mismo producto, misma fecha,
     * no anuladas) NO se vuelven a crear: se omiten y se devuelven en
     * fechas_omitidas para que la pantalla las muestre en la fila.
     *
     * Entrada esperada:
     *   id_producto_servicio, anio, mes_inicial, mes_final, dia, id_usuario,
     *   estudiantes: [ { id_estudiante, id_persona, valor, detalle } ]
     */
    public static function generarMasivo()
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db();

        try {
            $data = Flight::request()->data->getData();

            $id_producto_servicio = isset($data['id_producto_servicio']) ? $data['id_producto_servicio'] : null;
            $anio                 = isset($data['anio']) ? (int) $data['anio'] : 0;
            $mes_inicial          = isset($data['mes_inicial']) ? (int) $data['mes_inicial'] : 0;
            $mes_final            = isset($data['mes_final']) ? (int) $data['mes_final'] : 0;
            $dia                  = isset($data['dia']) ? (int) $data['dia'] : 0;
            $id_usuario           = isset($data['id_usuario']) ? $data['id_usuario'] : null;
            $estudiantes          = isset($data['estudiantes']) ? $data['estudiantes'] : array();

            if (!$id_producto_servicio) {
                Flight::json(array('error' => 'Debe seleccionar un producto o servicio'), 400);
                return;
            }
            if ($anio < 2000 || $anio > 2100) {
                Flight::json(array('error' => 'El año no es valido'), 400);
                return;
            }
            if ($mes_inicial < 1 || $mes_inicial > 12 || $mes_final < 1 || $mes_final > 12) {
                Flight::json(array('error' => 'Los meses deben estar entre 1 y 12'), 400);
                return;
            }
            if ($mes_inicial > $mes_final) {
                Flight::json(array('error' => 'El mes inicial no puede ser posterior al mes final'), 400);
                return;
            }
            if ($dia < 1 || $dia > 31) {
                Flight::json(array('error' => 'El dia debe estar entre 1 y 31'), 400);
                return;
            }
            if (empty($estudiantes) || !is_array($estudiantes)) {
                Flight::json(array('error' => 'Debe seleccionar al menos un estudiante'), 400);
                return;
            }
            if (!$id_usuario) {
                Flight::json(array('error' => 'No se recibio el usuario que registra'), 400);
                return;
            }

            // El producto debe existir en el tenant: de lo contrario se estarian
            // creando cuentas colgando de un producto de otro jardin.
            $stmtProducto = $db->prepare("
                SELECT id, nombre
                FROM productos_servicios
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $stmtProducto->bindParam(':id', $id_producto_servicio);
            $stmtProducto->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtProducto->execute();
            $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);

            if (!$producto) {
                Flight::json(array('error' => 'El producto o servicio no existe'), 404);
                return;
            }

            // Fechas del rango. Si el dia no existe en el mes (31 en febrero) se
            // ajusta al ultimo dia de ese mes en vez de correrse al mes siguiente.
            $fechas = array();
            for ($mes = $mes_inicial; $mes <= $mes_final; $mes++) {
                $ultimoDia = (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));
                $diaMes = min($dia, $ultimoDia);
                $fechas[] = sprintf('%04d-%02d-%02d', $anio, $mes, $diaMes);
            }

            $mora = self::parametrosMoraProducto($db, $id_producto_servicio);

            $stmtExiste = $db->prepare("
                SELECT COUNT(*) AS cantidad
                FROM cuentas_por_cobrar
                WHERE id_persona = :id_persona
                  AND id_producto_servicio = :id_producto_servicio
                  AND fecha = :fecha
                  AND (anulado = 0 OR anulado IS NULL)
                  AND id_tenant = :id_tenant
            ");

            $stmtInsert = $db->prepare("
                INSERT INTO cuentas_por_cobrar
                (id, id_tenant, id_producto_servicio, id_persona, fecha, valor, detalle, id_usuario,
                 anulado, fecha_anulacion, id_usuario_anulacion, id_horario_alimentacion,
                 id_tipo_mora, valor_recargo_mora, porcentaje_mora_mensual, mora_acumulable)
                VALUES
                (:id, :id_tenant, :id_producto_servicio, :id_persona, :fecha, :valor, :detalle, :id_usuario,
                 0, NULL, NULL, NULL,
                 :id_tipo_mora, :valor_recargo_mora, :porcentaje_mora_mensual, :mora_acumulable)
            ");

            $db->beginTransaction();

            $resultados     = array();
            $totalCreadas   = 0;
            $totalOmitidas  = 0;
            $totalValor     = 0;

            foreach ($estudiantes as $estudiante) {
                $id_estudiante = isset($estudiante['id_estudiante']) ? $estudiante['id_estudiante'] : null;
                $id_persona    = isset($estudiante['id_persona']) ? $estudiante['id_persona'] : null;
                $valor         = isset($estudiante['valor']) ? (float) $estudiante['valor'] : 0;
                $detalle       = isset($estudiante['detalle']) ? $estudiante['detalle'] : '';

                if (!$id_persona || $valor <= 0) {
                    $resultados[] = array(
                        'id_estudiante'   => $id_estudiante,
                        'creadas'         => 0,
                        'omitidas'        => 0,
                        'fechas_omitidas' => array(),
                        'error'           => 'Estudiante sin persona asociada o con valor invalido'
                    );
                    continue;
                }

                $creadasEstudiante  = 0;
                $fechasOmitidas     = array();

                foreach ($fechas as $fecha) {
                    $stmtExiste->bindParam(':id_persona', $id_persona);
                    $stmtExiste->bindParam(':id_producto_servicio', $id_producto_servicio);
                    $stmtExiste->bindParam(':fecha', $fecha);
                    $stmtExiste->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtExiste->execute();
                    $existente = $stmtExiste->fetch(PDO::FETCH_ASSOC);

                    if ($existente && (int) $existente['cantidad'] > 0) {
                        $fechasOmitidas[] = $fecha;
                        $totalOmitidas++;
                        continue;
                    }

                    $idCxc = Uuid::generar();
                    $stmtInsert->bindValue(':id', $idCxc);
                    $stmtInsert->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtInsert->bindParam(':id_producto_servicio', $id_producto_servicio);
                    $stmtInsert->bindParam(':id_persona', $id_persona);
                    $stmtInsert->bindParam(':fecha', $fecha);
                    $stmtInsert->bindValue(':valor', $valor);
                    $stmtInsert->bindValue(':detalle', $detalle);
                    $stmtInsert->bindParam(':id_usuario', $id_usuario);
                    $stmtInsert->bindValue(':id_tipo_mora', $mora['id_tipo_mora']);
                    $stmtInsert->bindValue(':valor_recargo_mora', $mora['valor_recargo_mora']);
                    $stmtInsert->bindValue(':porcentaje_mora_mensual', $mora['porcentaje_mora_mensual']);
                    $stmtInsert->bindValue(':mora_acumulable', $mora['mora_acumulable'], PDO::PARAM_INT);
                    $stmtInsert->execute();

                    $creadasEstudiante++;
                    $totalCreadas++;
                    $totalValor += $valor;
                }

                $resultados[] = array(
                    'id_estudiante'   => $id_estudiante,
                    'creadas'         => $creadasEstudiante,
                    'omitidas'        => count($fechasOmitidas),
                    'fechas_omitidas' => $fechasOmitidas
                );
            }

            $db->commit();

            Flight::json(array(
                'success'          => true,
                'nombre_producto'  => $producto['nombre'],
                'fechas'           => $fechas,
                'total_creadas'    => $totalCreadas,
                'total_omitidas'   => $totalOmitidas,
                'total_valor'      => $totalValor,
                'resultados'       => $resultados
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en CuentasPorCobrar::generarMasivo(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al generar las cuentas por cobrar'), 500);
        }
    }

    /**
     * Cuentas por cobrar candidatas a anulacion masiva.
     *
     * Filtra por rango de fechas y, opcionalmente, por producto. El grupo y la
     * busqueda por estudiante se resuelven en la pantalla sobre lo que devuelve
     * esta consulta, que es mas rapido que ir al servidor por cada filtro.
     *
     * Devuelve el total pagado de cada cuenta, contando solo los pagos que no
     * esten anulados: una cuenta cuyo unico pago fue anulado vuelve a quedar
     * libre y si se puede anular.
     *
     * Entrada esperada: fecha_inicial, fecha_final, id_producto_servicio (opcional)
     */
    public static function buscarParaAnular()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            $fecha_inicial        = isset($data['fecha_inicial']) ? $data['fecha_inicial'] : null;
            $fecha_final          = isset($data['fecha_final']) ? $data['fecha_final'] : null;
            $id_producto_servicio = isset($data['id_producto_servicio']) && $data['id_producto_servicio']
                ? $data['id_producto_servicio']
                : null;

            if (!$fecha_inicial || !$fecha_final) {
                Flight::json(array('error' => 'Debe indicar la fecha inicial y la fecha final'), 400);
                return;
            }

            if ($fecha_inicial > $fecha_final) {
                Flight::json(array('error' => 'La fecha inicial no puede ser posterior a la fecha final'), 400);
                return;
            }

            $filtroProducto = $id_producto_servicio
                ? ' AND c.id_producto_servicio = :id_producto_servicio '
                : '';

            $sql = "
                SELECT
                    c.id,
                    c.id_persona,
                    c.id_producto_servicio,
                    c.fecha,
                    c.valor,
                    c.detalle,
                    ps.nombre AS nombre_producto_servicio,
                    e.id AS id_estudiante,
                    TRIM(CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                                IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))) AS nombre_estudiante,
                    p.numero_identificacion,
                    IFNULL(g.nombre, 'Sin grupo') AS grupo_estudiante,
                    COALESCE(SUM(
                        CASE
                            WHEN cp.id IS NOT NULL AND (pr.anulado = 0 OR pr.anulado IS NULL)
                            THEN cp.valor_aplicado
                            ELSE 0
                        END
                    ), 0) AS total_pagado
                FROM cuentas_por_cobrar c
                INNER JOIN personas p ON p.id = c.id_persona
                INNER JOIN estudiantes e ON e.id_persona = c.id_persona
                LEFT JOIN estudiantes_x_grupos eg ON e.id = eg.id_estudiante AND eg.activo = 1
                LEFT JOIN grupos g ON eg.id_grupo = g.id
                LEFT JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
                LEFT JOIN cuenta_pagada cp ON cp.id_cuenta_por_cobrar = c.id
                LEFT JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
                WHERE (c.anulado = 0 OR c.anulado IS NULL)
                  AND c.fecha BETWEEN :fecha_inicial AND :fecha_final
                  AND c.id_tenant = :id_tenant
                  $filtroProducto
                GROUP BY c.id, c.id_persona, c.id_producto_servicio, c.fecha, c.valor, c.detalle,
                         ps.nombre, e.id, p.primer_nombre, p.segundo_nombre, p.primer_apellido,
                         p.segundo_apellido, p.numero_identificacion, g.nombre
                ORDER BY g.nombre, nombre_estudiante, c.fecha
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':fecha_inicial', $fecha_inicial);
            $stmt->bindParam(':fecha_final', $fecha_final);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            if ($id_producto_servicio) {
                $stmt->bindParam(':id_producto_servicio', $id_producto_servicio);
            }
            $stmt->execute();
            $cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // La pantalla necesita saber de una cuales cuentas estan bloqueadas
            // por tener pago aplicado, para pintarlas y no dejarlas marcar.
            foreach ($cuentas as &$cuenta) {
                $cuenta['valor']        = (float) $cuenta['valor'];
                $cuenta['total_pagado'] = (float) $cuenta['total_pagado'];
                $cuenta['tiene_pago']   = $cuenta['total_pagado'] > 0 ? 1 : 0;
            }
            unset($cuenta);

            Flight::json(array('cuentas' => $cuentas));
        } catch (Exception $e) {
            error_log('Error en CuentasPorCobrar::buscarParaAnular(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al buscar las cuentas por cobrar'), 500);
        }
    }

    /**
     * Anulacion masiva de cuentas por cobrar.
     *
     * Una cuenta con pagos aplicados NO se anula aqui: hay que devolver o anular
     * el pago primero, y eso se hace en el modulo de pagos. La validacion se
     * repite en el servidor y no solo en la pantalla, para que un id que llegue
     * de otro lado tampoco pueda saltarsela.
     *
     * Entrada esperada: ids (array), id_usuario_anulacion
     */
    public static function anularMasivo()
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db();

        try {
            $data = Flight::request()->data->getData();

            $ids                  = isset($data['ids']) ? $data['ids'] : array();
            $id_usuario_anulacion = isset($data['id_usuario_anulacion']) ? $data['id_usuario_anulacion'] : null;

            if (empty($ids) || !is_array($ids)) {
                Flight::json(array('error' => 'Debe seleccionar al menos una cuenta por cobrar'), 400);
                return;
            }

            if (!$id_usuario_anulacion) {
                Flight::json(array('error' => 'No se recibio el usuario que anula'), 400);
                return;
            }

            $fechaActual = date('Y-m-d H:i:s');

            $stmtPagos = $db->prepare("
                SELECT COALESCE(SUM(
                    CASE
                        WHEN (pr.anulado = 0 OR pr.anulado IS NULL) THEN cp.valor_aplicado
                        ELSE 0
                    END
                ), 0) AS total_pagado
                FROM cuenta_pagada cp
                LEFT JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
                WHERE cp.id_cuenta_por_cobrar = :id
                  AND cp.id_tenant = :id_tenant
            ");

            $stmtAnular = $db->prepare("
                UPDATE cuentas_por_cobrar SET
                    anulado = 1,
                    fecha_anulacion = :fecha_anulacion,
                    id_usuario_anulacion = :id_usuario_anulacion
                WHERE id = :id
                  AND id_tenant = :id_tenant
                  AND (anulado = 0 OR anulado IS NULL)
            ");

            $db->beginTransaction();

            $anuladas    = array();
            $omitidas    = array();

            foreach ($ids as $id) {
                $stmtPagos->bindParam(':id', $id);
                $stmtPagos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtPagos->execute();
                $pagos = $stmtPagos->fetch(PDO::FETCH_ASSOC);

                if ($pagos && (float) $pagos['total_pagado'] > 0) {
                    $omitidas[] = array(
                        'id'     => $id,
                        'motivo' => 'La cuenta tiene pagos aplicados'
                    );
                    continue;
                }

                $stmtAnular->bindParam(':id', $id);
                $stmtAnular->bindParam(':fecha_anulacion', $fechaActual);
                $stmtAnular->bindParam(':id_usuario_anulacion', $id_usuario_anulacion);
                $stmtAnular->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtAnular->execute();

                if ($stmtAnular->rowCount() > 0) {
                    $anuladas[] = $id;
                } else {
                    $omitidas[] = array(
                        'id'     => $id,
                        'motivo' => 'La cuenta no existe o ya estaba anulada'
                    );
                }
            }

            $db->commit();

            Flight::json(array(
                'success'         => true,
                'total_anuladas'  => count($anuladas),
                'total_omitidas'  => count($omitidas),
                'anuladas'        => $anuladas,
                'omitidas'        => $omitidas,
                'fecha_anulacion' => $fechaActual
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en CuentasPorCobrar::anularMasivo(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al anular las cuentas por cobrar'), 500);
        }
    }
}