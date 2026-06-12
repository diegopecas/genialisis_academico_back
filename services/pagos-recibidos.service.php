<?php
class PagosRecibidos
{

    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
            SELECT 
                pr.id, 
                pr.fecha, 
                pr.id_estudiante,
                pr.id_colaborador,
                pr.id_acudiente, 
                a.id_estudiante as acudiente_id_estudiante,
                CONCAT(p.primer_nombre, ' ', COALESCE(p.segundo_nombre, ''), ' ', 
                       p.primer_apellido, ' ', COALESCE(p.segundo_apellido, '')) AS nombre_acudiente,
                ta.nombre AS tipo_acudiente,
                CONCAT(pe.primer_nombre, ' ', COALESCE(pe.segundo_nombre, ''), ' ', 
                       pe.primer_apellido, ' ', COALESCE(pe.segundo_apellido, '')) AS nombre_estudiante,
                CONCAT(pc.primer_nombre, ' ', COALESCE(pc.segundo_nombre, ''), ' ', 
                       pc.primer_apellido, ' ', COALESCE(pc.segundo_apellido, '')) AS nombre_colaborador,
                pr.id_tipo_pago, 
                tp.nombre AS tipo_pago,
                pr.valor_recibido, 
                COALESCE(SUM(cp.valor_aplicado), 0) AS valor_aplicado_cuentas,
                (pr.valor_recibido - COALESCE(SUM(cp.valor_aplicado), 0)) AS saldo,
                pr.observaciones, 
                pr.referencia_bancaria, 
                pr.fecha_registro, 
                pr.id_usuario_registro,
                u.usuario AS nombre_usuario_registro,
                CONCAT(p_ur.primer_nombre, ' ', COALESCE(p_ur.segundo_nombre, ''), ' ', 
                       p_ur.primer_apellido, ' ', COALESCE(p_ur.segundo_apellido, '')) AS nombre_completo_usuario_registro,
                pr.fecha_contabilizacion, 
                pr.id_usuario_contable,
                uc.usuario AS nombre_usuario_contable,
                CONCAT(p_uc.primer_nombre, ' ', COALESCE(p_uc.segundo_nombre, ''), ' ', 
                       p_uc.primer_apellido, ' ', COALESCE(p_uc.segundo_apellido, '')) AS nombre_completo_usuario_contable,
                pr.anulado,
                pr.fecha_anulacion,
                pr.id_usuario_anulacion,
                ua.usuario AS nombre_usuario_anulacion,
                CONCAT(p_ua.primer_nombre, ' ', COALESCE(p_ua.segundo_nombre, ''), ' ', 
                       p_ua.primer_apellido, ' ', COALESCE(p_ua.segundo_apellido, '')) AS nombre_completo_usuario_anulacion
            FROM 
                pagos_recibidos pr
            LEFT JOIN 
                acudientes a ON pr.id_acudiente = a.id
            LEFT JOIN 
                personas p ON a.id_persona = p.id
            LEFT JOIN 
                tipos_acudiente ta ON a.id_tipo_acudiente = ta.id
            LEFT JOIN 
                estudiantes e ON pr.id_estudiante = e.id
            LEFT JOIN 
                personas pe ON e.id_persona = pe.id
            LEFT JOIN 
                colaboradores c ON pr.id_colaborador = c.id
            LEFT JOIN 
                personas pc ON c.id_persona = pc.id
            LEFT JOIN 
                tipos_pagos tp ON pr.id_tipo_pago = tp.id
            LEFT JOIN 
                usuarios u ON pr.id_usuario_registro = u.id
            LEFT JOIN 
                personas p_ur ON u.id_persona = p_ur.id
            LEFT JOIN 
                usuarios uc ON pr.id_usuario_contable = uc.id
            LEFT JOIN 
                personas p_uc ON uc.id_persona = p_uc.id
            LEFT JOIN 
                usuarios ua ON pr.id_usuario_anulacion = ua.id
            LEFT JOIN 
                personas p_ua ON ua.id_persona = p_ua.id
            LEFT JOIN 
                cuenta_pagada cp ON pr.id = cp.id_pago_recibido
            GROUP BY 
                pr.id, pr.fecha, pr.id_estudiante, pr.id_colaborador, pr.id_acudiente, 
                a.id_estudiante, p.primer_nombre, p.segundo_nombre,
                p.primer_apellido, p.segundo_apellido, ta.nombre,
                pe.primer_nombre, pe.segundo_nombre, pe.primer_apellido, pe.segundo_apellido,
                pc.primer_nombre, pc.segundo_nombre, pc.primer_apellido, pc.segundo_apellido,
                pr.id_tipo_pago, tp.nombre,
                pr.valor_recibido, pr.observaciones, pr.referencia_bancaria, 
                pr.fecha_registro, pr.id_usuario_registro, u.usuario, p_ur.primer_nombre, p_ur.segundo_nombre, 
                p_ur.primer_apellido, p_ur.segundo_apellido, pr.fecha_contabilizacion, pr.id_usuario_contable, 
                uc.usuario, p_uc.primer_nombre, p_uc.segundo_nombre, p_uc.primer_apellido, p_uc.segundo_apellido,
                pr.anulado, pr.fecha_anulacion, pr.id_usuario_anulacion, ua.usuario, p_ua.primer_nombre, 
                p_ua.segundo_nombre, p_ua.primer_apellido, p_ua.segundo_apellido
            ORDER BY pr.fecha DESC
        ");
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAll pagos_recibidos: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener los pagos recibidos',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT 
            pr.id, 
            pr.fecha, 
            pr.id_estudiante,
            pr.id_colaborador,
            pr.id_acudiente, 
            pr.id_tipo_pago, 
            pr.valor_recibido, 
            COALESCE(SUM(cp.valor_aplicado), 0) AS valor_aplicado_cuentas,
            (pr.valor_recibido - COALESCE(SUM(cp.valor_aplicado), 0)) AS saldo,
            pr.observaciones, 
            pr.referencia_bancaria, 
            pr.fecha_registro, 
            pr.id_usuario_registro, 
            pr.fecha_contabilizacion, 
            pr.id_usuario_contable,
            pr.anulado,
            pr.fecha_anulacion,
            pr.id_usuario_anulacion,
            CONCAT(p_ur.primer_nombre, ' ', COALESCE(p_ur.segundo_nombre, ''), ' ', 
                    p_ur.primer_apellido, ' ', COALESCE(p_ur.segundo_apellido, '')) AS nombre_completo_usuario_registro,
            CONCAT(p_uc.primer_nombre, ' ', COALESCE(p_uc.segundo_nombre, ''), ' ', 
                    p_uc.primer_apellido, ' ', COALESCE(p_uc.segundo_apellido, '')) AS nombre_completo_usuario_contable,
            CONCAT(p_ua.primer_nombre, ' ', COALESCE(p_ua.segundo_nombre, ''), ' ', 
                    p_ua.primer_apellido, ' ', COALESCE(p_ua.segundo_apellido, '')) AS nombre_completo_usuario_anulacion
        FROM 
            pagos_recibidos pr
        LEFT JOIN 
            cuenta_pagada cp ON pr.id = cp.id_pago_recibido
        LEFT JOIN 
            usuarios u ON pr.id_usuario_registro = u.id
        LEFT JOIN 
            personas p_ur ON u.id_persona = p_ur.id
        LEFT JOIN 
            usuarios uc ON pr.id_usuario_contable = uc.id
        LEFT JOIN 
            personas p_uc ON uc.id_persona = p_uc.id
        LEFT JOIN 
            usuarios ua ON pr.id_usuario_anulacion = ua.id
        LEFT JOIN 
            personas p_ua ON ua.id_persona = p_ua.id
        WHERE 
            pr.id = :id
        GROUP BY 
            pr.id, pr.fecha, pr.id_estudiante, pr.id_colaborador, pr.id_acudiente, pr.id_tipo_pago, pr.valor_recibido,
            pr.observaciones, pr.referencia_bancaria, pr.fecha_registro,
            pr.id_usuario_registro, u.usuario, p_ur.primer_nombre, p_ur.segundo_nombre, 
            p_ur.primer_apellido, p_ur.segundo_apellido, pr.fecha_contabilizacion, pr.id_usuario_contable, 
            uc.usuario, p_uc.primer_nombre, p_uc.segundo_nombre, p_uc.primer_apellido, p_uc.segundo_apellido,
            pr.anulado, pr.fecha_anulacion, pr.id_usuario_anulacion, ua.usuario, p_ua.primer_nombre, 
            p_ua.segundo_nombre, p_ua.primer_apellido, p_ua.segundo_apellido
    ");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    public static function getByEstudiante($idEstudiante)
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                    pr.id, 
                    pr.fecha, 
                    pr.id_acudiente, 
                    a.id_estudiante,
                    CONCAT(p.primer_nombre, ' ', COALESCE(p.segundo_nombre, ''), ' ', 
                           p.primer_apellido, ' ', COALESCE(p.segundo_apellido, '')) AS nombre_acudiente,
                    ta.nombre AS tipo_acudiente,
                    pr.id_tipo_pago, 
                    tp.nombre AS tipo_pago,
                    pr.valor_recibido, 
                    COALESCE(SUM(cp.valor_aplicado), 0) AS valor_aplicado_cuentas,
                    (pr.valor_recibido - COALESCE(SUM(cp.valor_aplicado), 0)) AS saldo,
                    pr.observaciones, 
                    pr.referencia_bancaria, 
                    pr.fecha_registro, 
                    pr.id_usuario_registro,
                    u.usuario AS nombre_usuario_registro,
                    CONCAT(p_ur.primer_nombre, ' ', COALESCE(p_ur.segundo_nombre, ''), ' ', 
                           p_ur.primer_apellido, ' ', COALESCE(p_ur.segundo_apellido, '')) AS nombre_completo_usuario_registro,
                    pr.fecha_contabilizacion, 
                    pr.id_usuario_contable,
                    uc.usuario AS nombre_usuario_contable,
                    CONCAT(p_uc.primer_nombre, ' ', COALESCE(p_uc.segundo_nombre, ''), ' ', 
                           p_uc.primer_apellido, ' ', COALESCE(p_uc.segundo_apellido, '')) AS nombre_completo_usuario_contable,
                    pr.anulado,
                    pr.fecha_anulacion,
                    pr.id_usuario_anulacion,
                    ua.usuario AS nombre_usuario_anulacion,
                    CONCAT(p_ua.primer_nombre, ' ', COALESCE(p_ua.segundo_nombre, ''), ' ', 
                           p_ua.primer_apellido, ' ', COALESCE(p_ua.segundo_apellido, '')) AS nombre_completo_usuario_anulacion
                FROM 
                    pagos_recibidos pr
                LEFT JOIN 
                    acudientes a ON pr.id_acudiente = a.id
                LEFT JOIN 
                    personas p ON a.id_persona = p.id
                LEFT JOIN 
                    tipos_acudiente ta ON a.id_tipo_acudiente = ta.id
                LEFT JOIN 
                    tipos_pagos tp ON pr.id_tipo_pago = tp.id
                LEFT JOIN 
                    usuarios u ON pr.id_usuario_registro = u.id
                LEFT JOIN 
                    personas p_ur ON u.id_persona = p_ur.id
                LEFT JOIN 
                    usuarios uc ON pr.id_usuario_contable = uc.id
                LEFT JOIN 
                    personas p_uc ON uc.id_persona = p_uc.id
                LEFT JOIN 
                    usuarios ua ON pr.id_usuario_anulacion = ua.id
                LEFT JOIN 
                    personas p_ua ON ua.id_persona = p_ua.id
                LEFT JOIN 
                    cuenta_pagada cp ON pr.id = cp.id_pago_recibido
                WHERE 
                    pr.id_estudiante = :id
                GROUP BY 
                    pr.id, pr.fecha, pr.id_acudiente, a.id_estudiante, p.primer_nombre, p.segundo_nombre,
                    p.primer_apellido, p.segundo_apellido, ta.nombre, pr.id_tipo_pago, tp.nombre,
                    pr.valor_recibido, pr.observaciones, pr.referencia_bancaria, 
                    pr.fecha_registro, pr.id_usuario_registro, u.usuario, p_ur.primer_nombre, p_ur.segundo_nombre, 
                    p_ur.primer_apellido, p_ur.segundo_apellido, pr.fecha_contabilizacion, pr.id_usuario_contable, 
                    uc.usuario, p_uc.primer_nombre, p_uc.segundo_nombre, p_uc.primer_apellido, p_uc.segundo_apellido,
                    pr.anulado, pr.fecha_anulacion, pr.id_usuario_anulacion, ua.usuario, p_ua.primer_nombre, 
                    p_ua.segundo_nombre, p_ua.primer_apellido, p_ua.segundo_apellido
                order by pr.fecha desc, pr.id desc
        ");

        $sentence->bindParam(':id', $idEstudiante);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    public static function getByColaborador($idColaborador)
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT 
            pr.id, 
            pr.fecha, 
            pr.id_colaborador,
            pr.id_acudiente, 
            CONCAT(pc.primer_nombre, ' ', COALESCE(pc.segundo_nombre, ''), ' ', 
                   pc.primer_apellido, ' ', COALESCE(pc.segundo_apellido, '')) AS nombre_colaborador,
            pr.id_tipo_pago, 
            tp.nombre AS tipo_pago,
            pr.valor_recibido, 
            COALESCE(SUM(cp.valor_aplicado), 0) AS valor_aplicado_cuentas,
            (pr.valor_recibido - COALESCE(SUM(cp.valor_aplicado), 0)) AS saldo,
            pr.observaciones, 
            pr.referencia_bancaria, 
            pr.fecha_registro, 
            pr.id_usuario_registro,
            u.usuario AS nombre_usuario_registro,
            CONCAT(p_ur.primer_nombre, ' ', COALESCE(p_ur.segundo_nombre, ''), ' ', 
                   p_ur.primer_apellido, ' ', COALESCE(p_ur.segundo_apellido, '')) AS nombre_completo_usuario_registro,
            pr.fecha_contabilizacion, 
            pr.id_usuario_contable,
            uc.usuario AS nombre_usuario_contable,
            CONCAT(p_uc.primer_nombre, ' ', COALESCE(p_uc.segundo_nombre, ''), ' ', 
                   p_uc.primer_apellido, ' ', COALESCE(p_uc.segundo_apellido, '')) AS nombre_completo_usuario_contable,
            pr.anulado,
            pr.fecha_anulacion,
            pr.id_usuario_anulacion,
            ua.usuario AS nombre_usuario_anulacion,
            CONCAT(p_ua.primer_nombre, ' ', COALESCE(p_ua.segundo_nombre, ''), ' ', 
                   p_ua.primer_apellido, ' ', COALESCE(p_ua.segundo_apellido, '')) AS nombre_completo_usuario_anulacion
        FROM 
            pagos_recibidos pr
        LEFT JOIN 
            colaboradores c ON pr.id_colaborador = c.id
        LEFT JOIN 
            personas pc ON c.id_persona = pc.id
        LEFT JOIN 
            tipos_pagos tp ON pr.id_tipo_pago = tp.id
        LEFT JOIN 
            usuarios u ON pr.id_usuario_registro = u.id
        LEFT JOIN 
            personas p_ur ON u.id_persona = p_ur.id
        LEFT JOIN 
            usuarios uc ON pr.id_usuario_contable = uc.id
        LEFT JOIN 
            personas p_uc ON uc.id_persona = p_uc.id
        LEFT JOIN 
            usuarios ua ON pr.id_usuario_anulacion = ua.id
        LEFT JOIN 
            personas p_ua ON ua.id_persona = p_ua.id
        LEFT JOIN 
            cuenta_pagada cp ON pr.id = cp.id_pago_recibido
        WHERE 
            pr.id_colaborador = :id
        GROUP BY 
            pr.id, pr.fecha, pr.id_colaborador, pr.id_acudiente, pc.primer_nombre, pc.segundo_nombre,
            pc.primer_apellido, pc.segundo_apellido, pr.id_tipo_pago, tp.nombre,
            pr.valor_recibido, pr.observaciones, pr.referencia_bancaria, 
            pr.fecha_registro, pr.id_usuario_registro, u.usuario, p_ur.primer_nombre, p_ur.segundo_nombre, 
            p_ur.primer_apellido, p_ur.segundo_apellido, pr.fecha_contabilizacion, pr.id_usuario_contable, 
            uc.usuario, p_uc.primer_nombre, p_uc.segundo_nombre, p_uc.primer_apellido, p_uc.segundo_apellido,
            pr.anulado, pr.fecha_anulacion, pr.id_usuario_anulacion, ua.usuario, p_ua.primer_nombre, 
            p_ua.segundo_nombre, p_ua.primer_apellido, p_ua.segundo_apellido
        ORDER BY pr.fecha DESC, pr.id DESC
    ");

        $sentence->bindParam(':id', $idColaborador);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            // Capturar datos de la solicitud
            $fecha = Flight::request()->data['fecha'];
            $id_estudiante = isset(Flight::request()->data['id_estudiante']) ? Flight::request()->data['id_estudiante'] : null;
            $id_colaborador = isset(Flight::request()->data['id_colaborador']) ? Flight::request()->data['id_colaborador'] : null;
            $id_acudiente = Flight::request()->data['id_acudiente'];
            $id_tipo_pago = Flight::request()->data['id_tipo_pago'];
            $valor_recibido = Flight::request()->data['valor_recibido'];
            $observaciones = Flight::request()->data['observaciones'];
            $referencia_bancaria = Flight::request()->data['referencia_bancaria'];
            $fecha_registro = Flight::request()->data['fecha_registro'];
            $id_usuario_registro = Flight::request()->data['id_usuario_registro'];
            $fecha_contabilizacion = Flight::request()->data['fecha_contabilizacion'];
            $id_usuario_contable = Flight::request()->data['id_usuario_contable'];

            // Validar que solo uno de los dos esté presente
            if (($id_estudiante !== null && $id_colaborador !== null) ||
                ($id_estudiante === null && $id_colaborador === null)
            ) {
                throw new Exception('Debe especificar id_estudiante o id_colaborador, pero no ambos');
            }

            $query = "INSERT INTO pagos_recibidos(fecha, id_estudiante, id_colaborador, id_acudiente, id_tipo_pago, valor_recibido, observaciones, referencia_bancaria, fecha_registro, id_usuario_registro, fecha_contabilizacion, id_usuario_contable) 
                 VALUES (:fecha, :id_estudiante, :id_colaborador, :id_acudiente, :id_tipo_pago, :valor_recibido, :observaciones, :referencia_bancaria, :fecha_registro, :id_usuario_registro, :fecha_contabilizacion, :id_usuario_contable)";

            $sentence = $db->prepare($query);

            // Vincular parámetros
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindParam(':id_colaborador', $id_colaborador);
            $sentence->bindParam(':id_acudiente', $id_acudiente);
            $sentence->bindParam(':id_tipo_pago', $id_tipo_pago);
            $sentence->bindParam(':valor_recibido', $valor_recibido);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':referencia_bancaria', $referencia_bancaria);
            $sentence->bindParam(':fecha_registro', $fecha_registro);
            $sentence->bindParam(':id_usuario_registro', $id_usuario_registro);
            $sentence->bindParam(':fecha_contabilizacion', $fecha_contabilizacion);
            $sentence->bindParam(':id_usuario_contable', $id_usuario_contable);

            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (PDOException $e) {
            error_log("Error PDO en new(): " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        } catch (Exception $e) {
            error_log("Error general en new(): " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $fecha = Flight::request()->data['fecha'];
            $id_estudiante = isset(Flight::request()->data['id_estudiante']) ? Flight::request()->data['id_estudiante'] : null;
            $id_colaborador = isset(Flight::request()->data['id_colaborador']) ? Flight::request()->data['id_colaborador'] : null;
            $id_acudiente = Flight::request()->data['id_acudiente'];
            $id_tipo_pago = Flight::request()->data['id_tipo_pago'];
            $valor_recibido = Flight::request()->data['valor_recibido'];
            $observaciones = Flight::request()->data['observaciones'];
            $referencia_bancaria = Flight::request()->data['referencia_bancaria'];
            $fecha_registro = Flight::request()->data['fecha_registro'];
            $id_usuario_registro = Flight::request()->data['id_usuario_registro'];
            $fecha_contabilizacion = Flight::request()->data['fecha_contabilizacion'];
            $id_usuario_contable = Flight::request()->data['id_usuario_contable'];

            // Validar que solo uno de los dos esté presente
            if (($id_estudiante !== null && $id_colaborador !== null) ||
                ($id_estudiante === null && $id_colaborador === null)
            ) {
                throw new Exception('Debe especificar id_estudiante o id_colaborador, pero no ambos');
            }

            $sentence = $db->prepare("UPDATE pagos_recibidos SET fecha = :fecha, id_estudiante = :id_estudiante, id_colaborador = :id_colaborador, id_acudiente = :id_acudiente, id_tipo_pago = :id_tipo_pago, valor_recibido = :valor_recibido, observaciones = :observaciones, referencia_bancaria = :referencia_bancaria, fecha_registro = :fecha_registro, id_usuario_registro = :id_usuario_registro, fecha_contabilizacion = :fecha_contabilizacion, id_usuario_contable = :id_usuario_contable WHERE id = :id");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindParam(':id_colaborador', $id_colaborador);
            $sentence->bindParam(':id_acudiente', $id_acudiente);
            $sentence->bindParam(':id_tipo_pago', $id_tipo_pago);
            $sentence->bindParam(':valor_recibido', $valor_recibido);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':referencia_bancaria', $referencia_bancaria);
            $sentence->bindParam(':fecha_registro', $fecha_registro);
            $sentence->bindParam(':id_usuario_registro', $id_usuario_registro);
            $sentence->bindParam(':fecha_contabilizacion', $fecha_contabilizacion);
            $sentence->bindParam(':id_usuario_contable', $id_usuario_contable);

            $sentence->execute();
            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en replace(): " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM pagos_recibidos WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
    public static function anular()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $id_usuario_anulacion = Flight::request()->data['id_usuario_anulacion'];
            $observaciones_anulacion = isset(Flight::request()->data['observaciones_anulacion']) ?
                Flight::request()->data['observaciones_anulacion'] :
                "Pago anulado";

            // Primero actualizamos el registro para marcarlo como anulado
            $sentence = $db->prepare("
                UPDATE pagos_recibidos 
                SET anulado = 1, 
                    fecha_anulacion = NOW(), 
                    id_usuario_anulacion = :id_usuario_anulacion,
                    observaciones = CONCAT(observaciones, ' | ANULADO: ', :observaciones_anulacion)
                WHERE id = :id
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_usuario_anulacion', $id_usuario_anulacion);
            $sentence->bindParam(':observaciones_anulacion', $observaciones_anulacion);
            $sentence->execute();

            // Desasociar reportes de pago vinculados a este pago anulado
            $stmtReporte = $db->prepare("
                UPDATE reportes_pago 
                SET id_pago_recibido = NULL, 
                    estado = 'pendiente', 
                    fecha_asociacion = NULL 
                WHERE id_pago_recibido = :id_pago
            ");
            $stmtReporte->bindParam(':id_pago', $id);
            $stmtReporte->execute();

            // Devolvemos el registro actualizado
            self::getById($id);
        } catch (Exception $e) {
            // Log del error en el servidor
            error_log('Error en anular pago_recibido: ' . $e->getMessage());

            // Respuesta con error para el cliente
            Flight::json([
                'error' => true,
                'message' => 'Error al anular el pago recibido',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function contabilizar()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $id_usuario_contable = Flight::request()->data['id_usuario_contable'];
            $fecha_contabilizacion = isset(Flight::request()->data['fecha_contabilizacion']) ?
                Flight::request()->data['fecha_contabilizacion'] :
                date('Y-m-d H:i:s'); // Formato completo con hora
            $observaciones_contabilizacion = isset(Flight::request()->data['observaciones_contabilizacion']) ?
                Flight::request()->data['observaciones_contabilizacion'] :
                "Pago contabilizado";

            // Actualizamos el registro para marcarlo como contabilizado
            $sentence = $db->prepare("
                UPDATE pagos_recibidos 
                SET fecha_contabilizacion = :fecha_contabilizacion, 
                    id_usuario_contable = :id_usuario_contable,
                    observaciones = CONCAT(observaciones, ' | CONTABILIZADO: ', :observaciones_contabilizacion)
                WHERE id = :id
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':fecha_contabilizacion', $fecha_contabilizacion);
            $sentence->bindParam(':id_usuario_contable', $id_usuario_contable);
            $sentence->bindParam(':observaciones_contabilizacion', $observaciones_contabilizacion);
            $sentence->execute();

            // Devolvemos el registro actualizado
            self::getById($id);
        } catch (Exception $e) {
            // Log del error en el servidor
            error_log('Error en contabilizar pago_recibido: ' . $e->getMessage());

            // Respuesta con error para el cliente
            Flight::json([
                'error' => true,
                'message' => 'Error al contabilizar el pago recibido',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
    public static function obtenerDatosComprobante($id_pago_recibido)
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db();

        // 1. Obtener los datos básicos del pago
        $sentencePago = $db->prepare("
            SELECT 
                pr.id, 
                pr.fecha, 
                pr.id_estudiante,
                pr.id_acudiente, 
                pr.id_tipo_pago, 
                pr.valor_recibido, 
                pr.observaciones, 
                pr.referencia_bancaria, 
                pr.fecha_registro, 
                pr.id_usuario_registro, 
                pr.fecha_contabilizacion, 
                pr.id_usuario_contable,
                pr.anulado,
                pr.fecha_anulacion,
                pr.id_usuario_anulacion,
                (pr.valor_recibido - COALESCE((SELECT SUM(valor_aplicado) FROM cuenta_pagada WHERE id_pago_recibido = pr.id), 0)) AS saldo,
                tp.nombre AS tipo_pago_nombre,
                CONCAT(p_ur.primer_nombre, ' ', COALESCE(p_ur.segundo_nombre, ''), ' ', 
                    p_ur.primer_apellido, ' ', COALESCE(p_ur.segundo_apellido, '')) AS nombre_completo_usuario_registro,
                CONCAT(p_uc.primer_nombre, ' ', COALESCE(p_uc.segundo_nombre, ''), ' ', 
                    p_uc.primer_apellido, ' ', COALESCE(p_uc.segundo_apellido, '')) AS nombre_completo_usuario_contable
            FROM 
                pagos_recibidos pr
            LEFT JOIN 
                tipos_pagos tp ON pr.id_tipo_pago = tp.id
            LEFT JOIN 
                usuarios u ON pr.id_usuario_registro = u.id
            LEFT JOIN 
                personas p_ur ON u.id_persona = p_ur.id
            LEFT JOIN 
                usuarios uc ON pr.id_usuario_contable = uc.id
            LEFT JOIN 
                personas p_uc ON uc.id_persona = p_uc.id
            WHERE 
                pr.id = :id
        ");
        $sentencePago->bindParam(':id', $id_pago_recibido);
        $sentencePago->execute();
        $pago = $sentencePago->fetch(PDO::FETCH_ASSOC);

        if (!$pago) {
            return Flight::json(['error' => true, 'message' => 'Pago no encontrado'], 404);
        }

        // 2. Obtener los datos del estudiante
        $sentenceEstudiante = $db->prepare("
            SELECT 
                e.id,
                CONCAT(p.primer_nombre, ' ', 
                    COALESCE(p.segundo_nombre, ''), ' ', 
                    p.primer_apellido, ' ', 
                    COALESCE(p.segundo_apellido, '')) AS nombre,
                p.numero_identificacion AS documento,
                COALESCE((SELECT g.nombre FROM grupos g 
                          JOIN estudiantes_x_grupos exg ON g.id = exg.id_grupo 
                          WHERE exg.id_estudiante = e.id AND exg.activo = 1 
                          ORDER BY exg.anio DESC LIMIT 1), 'Sin grupo asignado') AS grado
            FROM 
                estudiantes e
            JOIN 
                personas p ON e.id_persona = p.id
            WHERE 
                e.id = :id_estudiante
        ");
        $sentenceEstudiante->bindParam(':id_estudiante', $pago['id_estudiante']);
        $sentenceEstudiante->execute();
        $estudiante = $sentenceEstudiante->fetch(PDO::FETCH_ASSOC);

        // 3. Obtener los datos del acudiente (Corregido el nombre de la tabla a tipos_acudiente)
        $sentenceAcudiente = $db->prepare("
            SELECT 
                a.id,
                CONCAT(p.primer_nombre, ' ', 
                    COALESCE(p.segundo_nombre, ''), ' ', 
                    p.primer_apellido, ' ', 
                    COALESCE(p.segundo_apellido, '')) AS nombre,
                p.numero_identificacion AS documento,
                ta.nombre AS tipo_acudiente
            FROM 
                acudientes a
            JOIN 
                personas p ON a.id_persona = p.id
            LEFT JOIN
                tipos_acudiente ta ON a.id_tipo_acudiente = ta.id
            WHERE 
                a.id = :id_acudiente
        ");
        $sentenceAcudiente->bindParam(':id_acudiente', $pago['id_acudiente']);
        $sentenceAcudiente->execute();
        $acudiente = $sentenceAcudiente->fetch(PDO::FETCH_ASSOC);

        // 4. Obtener las cuentas aplicadas con este pago
        $sentenceCuentas = $db->prepare("
            SELECT 
                cp.id,
                cp.id_cuenta_por_cobrar,
                cp.valor_aplicado,
                cpc.valor,
                cpc.detalle,
                cpc.fecha as fecha_cuenta,
                ps.nombre AS nombre_producto_servicio,
                (cpc.valor - COALESCE((
                    SELECT SUM(cp2.valor_aplicado) 
                    FROM cuenta_pagada cp2 
                    INNER JOIN pagos_recibidos pr2 ON cp2.id_pago_recibido = pr2.id 
                        AND (pr2.anulado = 0 OR pr2.anulado IS NULL)
                    WHERE cp2.id_cuenta_por_cobrar = cpc.id
                      AND cp2.id_pago_recibido != :id_pago_excluir
                ), 0)) AS saldo_antes_pago,
                (cpc.valor - COALESCE((
                    SELECT SUM(cp3.valor_aplicado) 
                    FROM cuenta_pagada cp3 
                    INNER JOIN pagos_recibidos pr3 ON cp3.id_pago_recibido = pr3.id 
                        AND (pr3.anulado = 0 OR pr3.anulado IS NULL)
                    WHERE cp3.id_cuenta_por_cobrar = cpc.id
                ), 0)) AS saldo_actual_cuenta
            FROM 
                cuenta_pagada cp
            JOIN 
                cuentas_por_cobrar cpc ON cp.id_cuenta_por_cobrar = cpc.id
            LEFT JOIN
                productos_servicios ps ON cpc.id_producto_servicio = ps.id
            WHERE 
                cp.id_pago_recibido = :id_pago
            ORDER BY
                cpc.fecha ASC, cp.id
        ");
        $sentenceCuentas->bindParam(':id_pago', $id_pago_recibido);
        $sentenceCuentas->bindParam(':id_pago_excluir', $id_pago_recibido);
        $sentenceCuentas->execute();
        $cuentasAplicadas = $sentenceCuentas->fetchAll(PDO::FETCH_ASSOC);

        // 5. Obtener información del tipo de pago
        $sentenceTipoPago = $db->prepare("
            SELECT 
                id,
                nombre
            FROM 
                tipos_pagos
            WHERE 
                id = :id_tipo_pago
        ");
        $sentenceTipoPago->bindParam(':id_tipo_pago', $pago['id_tipo_pago']);
        $sentenceTipoPago->execute();
        $tipoPago = $sentenceTipoPago->fetch(PDO::FETCH_ASSOC);

        // Construir la respuesta completa
        $respuesta = [
            'pago' => $pago,
            'estudiante' => $estudiante,
            'acudiente' => $acudiente,
            'tipoPago' => $tipoPago
        ];

        // Añadir las cuentas aplicadas al objeto de pago
        $pago['cuentas_aplicadas'] = $cuentasAplicadas;
        $respuesta['pago'] = $pago;

        return Flight::json($respuesta);
    }
    public static function obtenerDatosComprobanteColaborador($id_pago_recibido)
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db();

        // 1. Obtener los datos básicos del pago
        $sentencePago = $db->prepare("
                SELECT 
                    pr.id, 
                    pr.fecha, 
                    pr.id_colaborador,
                    pr.id_tipo_pago, 
                    pr.valor_recibido, 
                    pr.observaciones, 
                    pr.referencia_bancaria, 
                    pr.fecha_registro, 
                    pr.id_usuario_registro, 
                    pr.fecha_contabilizacion, 
                    pr.id_usuario_contable,
                    pr.anulado,
                    pr.fecha_anulacion,
                    pr.id_usuario_anulacion,
                    (pr.valor_recibido - COALESCE((SELECT SUM(valor_aplicado) FROM cuenta_pagada WHERE id_pago_recibido = pr.id), 0)) AS saldo,
                    tp.nombre AS tipo_pago_nombre,
                    CONCAT(p_ur.primer_nombre, ' ', COALESCE(p_ur.segundo_nombre, ''), ' ', 
                        p_ur.primer_apellido, ' ', COALESCE(p_ur.segundo_apellido, '')) AS nombre_completo_usuario_registro,
                    CONCAT(p_uc.primer_nombre, ' ', COALESCE(p_uc.segundo_nombre, ''), ' ', 
                        p_uc.primer_apellido, ' ', COALESCE(p_uc.segundo_apellido, '')) AS nombre_completo_usuario_contable
                FROM 
                    pagos_recibidos pr
                LEFT JOIN 
                    tipos_pagos tp ON pr.id_tipo_pago = tp.id
                LEFT JOIN 
                    usuarios u ON pr.id_usuario_registro = u.id
                LEFT JOIN 
                    personas p_ur ON u.id_persona = p_ur.id
                LEFT JOIN 
                    usuarios uc ON pr.id_usuario_contable = uc.id
                LEFT JOIN 
                    personas p_uc ON uc.id_persona = p_uc.id
                WHERE 
                    pr.id = :id
            ");
        $sentencePago->bindParam(':id', $id_pago_recibido);
        $sentencePago->execute();
        $pago = $sentencePago->fetch(PDO::FETCH_ASSOC);

        if (!$pago) {
            return Flight::json(['error' => true, 'message' => 'Pago no encontrado'], 404);
        }

        // 2. Obtener los datos del colaborador con su rol
        $sentenceColaborador = $db->prepare("
                SELECT 
                    c.id,
                    CONCAT(p.primer_nombre, ' ', 
                        COALESCE(p.segundo_nombre, ''), ' ', 
                        p.primer_apellido, ' ', 
                        COALESCE(p.segundo_apellido, '')) AS nombre,
                    p.numero_identificacion AS documento,
                    rc.nombre AS rol,
                    c.correo_electronico
                FROM 
                    colaboradores c
                JOIN 
                    personas p ON c.id_persona = p.id
                LEFT JOIN
                    roles_colaborador rc ON c.id_rol_colaborador = rc.id
                WHERE 
                    c.id = :id_colaborador
            ");
        $sentenceColaborador->bindParam(':id_colaborador', $pago['id_colaborador']);
        $sentenceColaborador->execute();
        $colaborador = $sentenceColaborador->fetch(PDO::FETCH_ASSOC);

        // 3. Obtener las cuentas aplicadas con este pago
        $sentenceCuentas = $db->prepare("
                SELECT 
                    cp.id,
                    cp.id_cuenta_por_cobrar,
                    cp.valor_aplicado,
                    cpc.valor,
                    cpc.detalle,
                    cpc.fecha as fecha_cuenta,
                    ps.nombre AS nombre_producto_servicio,
                    (cpc.valor - COALESCE((
                        SELECT SUM(cp2.valor_aplicado) 
                        FROM cuenta_pagada cp2 
                        INNER JOIN pagos_recibidos pr2 ON cp2.id_pago_recibido = pr2.id 
                            AND (pr2.anulado = 0 OR pr2.anulado IS NULL)
                        WHERE cp2.id_cuenta_por_cobrar = cpc.id
                          AND cp2.id_pago_recibido != :id_pago_excluir
                    ), 0)) AS saldo_antes_pago,
                    (cpc.valor - COALESCE((
                        SELECT SUM(cp3.valor_aplicado) 
                        FROM cuenta_pagada cp3 
                        INNER JOIN pagos_recibidos pr3 ON cp3.id_pago_recibido = pr3.id 
                            AND (pr3.anulado = 0 OR pr3.anulado IS NULL)
                        WHERE cp3.id_cuenta_por_cobrar = cpc.id
                    ), 0)) AS saldo_actual_cuenta
                FROM 
                    cuenta_pagada cp
                JOIN 
                    cuentas_por_cobrar cpc ON cp.id_cuenta_por_cobrar = cpc.id
                LEFT JOIN
                    productos_servicios ps ON cpc.id_producto_servicio = ps.id
                WHERE 
                    cp.id_pago_recibido = :id_pago
                ORDER BY
                    cpc.fecha ASC, cp.id
            ");
        $sentenceCuentas->bindParam(':id_pago', $id_pago_recibido);
        $sentenceCuentas->bindParam(':id_pago_excluir', $id_pago_recibido);
        $sentenceCuentas->execute();
        $cuentasAplicadas = $sentenceCuentas->fetchAll(PDO::FETCH_ASSOC);

        // 4. Obtener información del tipo de pago
        $sentenceTipoPago = $db->prepare("
                SELECT 
                    id,
                    nombre
                FROM 
                    tipos_pagos
                WHERE 
                    id = :id_tipo_pago
            ");
        $sentenceTipoPago->bindParam(':id_tipo_pago', $pago['id_tipo_pago']);
        $sentenceTipoPago->execute();
        $tipoPago = $sentenceTipoPago->fetch(PDO::FETCH_ASSOC);

        // Construir la respuesta completa
        $respuesta = [
            'pago' => $pago,
            'colaborador' => $colaborador,
            'tipoPago' => $tipoPago
        ];

        // Añadir las cuentas aplicadas al objeto de pago
        $pago['cuentas_aplicadas'] = $cuentasAplicadas;
        $respuesta['pago'] = $pago;

        return Flight::json($respuesta);
    }
    public static function getPendientesContabilizar()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
            SELECT 
                pr.id, 
                pr.fecha, 
                pr.id_estudiante,
                pr.id_colaborador,
                pr.id_acudiente, 
                -- Nombre del estudiante (si es pago de estudiante)
                CONCAT(p_est.primer_nombre, ' ', COALESCE(p_est.segundo_nombre, ''), ' ', 
                       p_est.primer_apellido, ' ', COALESCE(p_est.segundo_apellido, '')) AS nombre_estudiante,
                -- Nombre del colaborador (si es pago de colaborador)
                CONCAT(p_col.primer_nombre, ' ', COALESCE(p_col.segundo_nombre, ''), ' ', 
                       p_col.primer_apellido, ' ', COALESCE(p_col.segundo_apellido, '')) AS nombre_colaborador,
                -- Tipo de persona (para identificar si es estudiante o colaborador)
                CASE 
                    WHEN pr.id_estudiante IS NOT NULL THEN 'Estudiante'
                    WHEN pr.id_colaborador IS NOT NULL THEN 'Colaborador'
                    ELSE 'Desconocido'
                END AS tipo_persona,
                CONCAT(p.primer_nombre, ' ', COALESCE(p.segundo_nombre, ''), ' ', 
                       p.primer_apellido, ' ', COALESCE(p.segundo_apellido, '')) AS nombre_acudiente,
                ta.nombre AS tipo_acudiente,
                pr.id_tipo_pago, 
                tp.nombre AS tipo_pago,
                pr.valor_recibido, 
                COALESCE(SUM(cp.valor_aplicado), 0) AS valor_aplicado_cuentas,
                (pr.valor_recibido - COALESCE(SUM(cp.valor_aplicado), 0)) AS saldo,
                pr.observaciones, 
                pr.referencia_bancaria, 
                pr.fecha_registro, 
                pr.id_usuario_registro,
                u.usuario AS nombre_usuario_registro,
                CONCAT(p_ur.primer_nombre, ' ', COALESCE(p_ur.segundo_nombre, ''), ' ', 
                       p_ur.primer_apellido, ' ', COALESCE(p_ur.segundo_apellido, '')) AS nombre_completo_usuario_registro
            FROM 
                pagos_recibidos pr
            -- Join para estudiantes
            LEFT JOIN 
                estudiantes e ON pr.id_estudiante = e.id
            LEFT JOIN 
                personas p_est ON e.id_persona = p_est.id
            -- Join para colaboradores
            LEFT JOIN 
                colaboradores col ON pr.id_colaborador = col.id
            LEFT JOIN 
                personas p_col ON col.id_persona = p_col.id
            -- Join para acudientes
            LEFT JOIN 
                acudientes a ON pr.id_acudiente = a.id
            LEFT JOIN 
                personas p ON a.id_persona = p.id
            LEFT JOIN 
                tipos_acudiente ta ON a.id_tipo_acudiente = ta.id
            -- Otros joins
            LEFT JOIN 
                tipos_pagos tp ON pr.id_tipo_pago = tp.id
            LEFT JOIN 
                usuarios u ON pr.id_usuario_registro = u.id
            LEFT JOIN 
                personas p_ur ON u.id_persona = p_ur.id
            LEFT JOIN 
                cuenta_pagada cp ON pr.id = cp.id_pago_recibido
            WHERE 
                pr.id_usuario_contable IS NULL 
                AND pr.anulado != 1
            GROUP BY 
                pr.id, pr.fecha, pr.id_estudiante, pr.id_colaborador, pr.id_acudiente,
                p_est.primer_nombre, p_est.segundo_nombre, p_est.primer_apellido, p_est.segundo_apellido,
                p_col.primer_nombre, p_col.segundo_nombre, p_col.primer_apellido, p_col.segundo_apellido,
                p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
                ta.nombre, pr.id_tipo_pago, tp.nombre, pr.valor_recibido, pr.observaciones, 
                pr.referencia_bancaria, pr.fecha_registro, pr.id_usuario_registro, u.usuario, 
                p_ur.primer_nombre, p_ur.segundo_nombre, p_ur.primer_apellido, p_ur.segundo_apellido
            ORDER BY 
                pr.fecha DESC, pr.id DESC
        ");
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getPendientesContabilizar: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener los pagos pendientes de contabilizar',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
    public static function contabilizarMultiple()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            // Obtener los datos de la solicitud
            $ids = Flight::request()->data['ids']; // Array de IDs
            $id_usuario_contable = Flight::request()->data['id_usuario_contable'];
            $fecha_contabilizacion = isset(Flight::request()->data['fecha_contabilizacion']) ?
                Flight::request()->data['fecha_contabilizacion'] :
                date('Y-m-d H:i:s');
            $observaciones_contabilizacion = isset(Flight::request()->data['observaciones_contabilizacion']) ?
                Flight::request()->data['observaciones_contabilizacion'] :
                "Contabilización masiva";

            // Validar que se recibieron IDs
            if (!is_array($ids) || count($ids) === 0) {
                Flight::json([
                    'error' => true,
                    'message' => 'No se recibieron pagos para contabilizar'
                ], 400);
                return;
            }

            // Iniciar transacción
            $db->beginTransaction();

            try {
                // Preparar la consulta para actualizar cada pago
                $sentence = $db->prepare("
                    UPDATE pagos_recibidos 
                    SET fecha_contabilizacion = :fecha_contabilizacion, 
                        id_usuario_contable = :id_usuario_contable,
                        observaciones = CONCAT(observaciones, ' | CONTABLILIZACIÓN MÚLTIPLE: ', :observaciones_contabilizacion)
                    WHERE id = :id
                    AND id_usuario_contable IS NULL
                    AND anulado != 1
                ");

                $contabilizados = 0;
                $errores = [];

                // Ejecutar la actualización para cada ID
                foreach ($ids as $id) {
                    $sentence->bindParam(':id', $id);
                    $sentence->bindParam(':fecha_contabilizacion', $fecha_contabilizacion);
                    $sentence->bindParam(':id_usuario_contable', $id_usuario_contable);
                    $sentence->bindParam(':observaciones_contabilizacion', $observaciones_contabilizacion);

                    if ($sentence->execute() && $sentence->rowCount() > 0) {
                        $contabilizados++;
                    } else {
                        $errores[] = $id;
                    }
                }

                // Confirmar la transacción
                $db->commit();

                // Preparar respuesta
                $response = [
                    'success' => true,
                    'contabilizados' => $contabilizados,
                    'total_enviados' => count($ids),
                    'message' => "Se contabilizaron {$contabilizados} de " . count($ids) . " pagos"
                ];

                if (count($errores) > 0) {
                    $response['errores'] = $errores;
                    $response['message'] .= ". Algunos pagos no pudieron ser contabilizados (pueden estar ya contabilizados o anulados).";
                }

                Flight::json($response);
            } catch (Exception $e) {
                // Revertir la transacción en caso de error
                $db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            error_log('Error en contabilizarMultiple: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al contabilizar los pagos',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene todos los datos necesarios para el registro rápido de pagos en una sola llamada.
     * GET /pagos-recibidos/datos-registro-rapido
     * 
     * Retorna:
     * - estudiantes: activos con cuentas por cobrar pendientes
     * - cuentas_por_cobrar: detalle de cada cuenta pendiente por estudiante (para distribución FIFO)
     * - acudientes: responsables de pago
     * - tipos_pagos: con campo requiere_documento
     */
    public static function getDatosRegistroRapido()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            // 1. Estudiantes activos que tienen al menos una cuenta por cobrar con saldo > 0
            $stmtEstudiantes = $db->prepare("
                SELECT DISTINCT
                    e.id AS id_estudiante,
                    e.id_persona,
                    CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                           IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_estudiante,
                    p.numero_identificacion,
                    IFNULL(g.nombre, 'Sin grupo') AS grupo_estudiante
                FROM estudiantes e
                INNER JOIN personas p ON e.id_persona = p.id
                LEFT JOIN estudiantes_x_grupos eg ON e.id = eg.id_estudiante AND eg.activo = 1
                LEFT JOIN grupos g ON eg.id_grupo = g.id
                WHERE e.activo = 1
                ORDER BY g.nombre, p.primer_nombre, p.primer_apellido
            ");
            $stmtEstudiantes->execute();
            $estudiantes = $stmtEstudiantes->fetchAll(PDO::FETCH_ASSOC);

            // Limpiar espacios múltiples en nombres
            foreach ($estudiantes as &$est) {
                $est['nombre_estudiante'] = trim(preg_replace('/\s+/', ' ', $est['nombre_estudiante']));
            }

            // 2. Cuentas por cobrar pendientes (saldo > 0) de todos los estudiantes activos
            //    El saldo se calcula como: valor - SUM(valor_aplicado en cuenta_pagada)
            $stmtCuentas = $db->prepare("
                SELECT 
                    c.id,
                    c.id_persona,
                    e.id AS id_estudiante,
                    c.fecha,
                    c.valor,
                    c.detalle,
                    ps.nombre AS nombre_producto_servicio,
                    COALESCE(SUM(cp.valor_aplicado), 0) AS total_pagado,
                    (c.valor - COALESCE(SUM(cp.valor_aplicado), 0)) AS saldo
                FROM cuentas_por_cobrar c
                INNER JOIN estudiantes e ON e.id_persona = c.id_persona AND e.activo = 1
                LEFT JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
                LEFT JOIN cuenta_pagada cp ON cp.id_cuenta_por_cobrar = c.id
                    LEFT JOIN pagos_recibidos pr_cp ON cp.id_pago_recibido = pr_cp.id 
                        AND (pr_cp.anulado = 0 OR pr_cp.anulado IS NULL)
                WHERE (c.anulado = 0 OR c.anulado IS NULL)
                GROUP BY c.id, c.id_persona, e.id, c.fecha, c.valor, c.detalle, ps.nombre
                HAVING saldo > 0
                ORDER BY e.id, c.fecha ASC
            ");
            $stmtCuentas->execute();
            $cuentas = $stmtCuentas->fetchAll(PDO::FETCH_ASSOC);

            // 3. Acudientes responsables de pago
            $stmtAcudientes = $db->prepare("
                SELECT 
                    a.id AS id_acudiente,
                    a.id_estudiante,
                    a.id_persona AS id_persona_acudiente,
                    CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                           IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_acudiente,
                    ta.nombre AS tipo_acudiente,
                    p.telefono,
                    p.correo_electronico
                FROM acudientes a
                INNER JOIN personas p ON a.id_persona = p.id
                INNER JOIN tipos_acudiente ta ON a.id_tipo_acudiente = ta.id
                WHERE a.activo = 1
                  AND a.es_responsable_pago = 1
                ORDER BY a.id_estudiante, ta.nombre
            ");
            $stmtAcudientes->execute();
            $acudientes = $stmtAcudientes->fetchAll(PDO::FETCH_ASSOC);

            foreach ($acudientes as &$acud) {
                $acud['nombre_acudiente'] = trim(preg_replace('/\s+/', ' ', $acud['nombre_acudiente']));
            }

            // 4. Tipos de pago con requiere_documento
            $stmtTiposPago = $db->prepare("SELECT id, nombre, requiere_documento FROM tipos_pagos ORDER BY id");
            $stmtTiposPago->execute();
            $tiposPago = $stmtTiposPago->fetchAll(PDO::FETCH_ASSOC);

            Flight::json(array(
                'estudiantes' => $estudiantes,
                'cuentas_por_cobrar' => $cuentas,
                'acudientes' => $acudientes,
                'tipos_pagos' => $tiposPago
            ));
        } catch (Exception $e) {
            error_log("Error en getDatosRegistroRapido: " . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener datos: ' . $e->getMessage()), 500);
        }
    }

    /**
     * Analiza un comprobante de pago usando Gemini Flash.
     * POST /pagos-recibidos/analizar-comprobante
     */
    public static function analizarComprobante()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
                Flight::json(array('error' => 'No se recibió el archivo o hubo un error al subirlo'), 400);
                return;
            }

            $archivo = $_FILES['comprobante'];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

            $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array($extension, $extensiones_permitidas)) {
                Flight::json(array('error' => 'Solo se permiten archivos PDF, JPG, JPEG o PNG'), 400);
                return;
            }

            if ($archivo['size'] > 10 * 1024 * 1024) {
                Flight::json(array('error' => 'El archivo excede el tamaño máximo de 10MB'), 400);
                return;
            }

            $db = Flight::db();
            $stmt = $db->prepare("SELECT valor FROM ia_configuracion WHERE clave = 'gemini_api_key' LIMIT 1");
            $stmt->execute();
            $config = $stmt->fetch();

            if (!$config || empty($config['valor'])) {
                Flight::json(array('error' => 'API Key de Gemini no configurada en ia_configuracion'), 500);
                return;
            }

            $apiKey = $config['valor'];

            $stmtEstado = $db->prepare("SELECT valor FROM ia_configuracion WHERE clave = 'estado_servicio' LIMIT 1");
            $stmtEstado->execute();
            $estado = $stmtEstado->fetch();

            if ($estado && $estado['valor'] !== 'activo') {
                Flight::json(array('error' => 'El servicio de IA se encuentra pausado o en mantenimiento'), 503);
                return;
            }

            $contenidoArchivo = file_get_contents($archivo['tmp_name']);
            $base64 = base64_encode($contenidoArchivo);
            $mimeType = ($extension === 'pdf') ? 'application/pdf' : 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension);

            $prompt = "Analiza este comprobante de pago bancario colombiano y extrae ÚNICAMENTE los siguientes datos en formato JSON estricto. "
                . "No incluyas explicaciones ni texto adicional, SOLO el JSON:\n\n"
                . "{\n"
                . "  \"valor\": (número entero, solo dígitos, sin puntos ni comas),\n"
                . "  \"referencia\": (string con el número de referencia, aprobación o comprobante),\n"
                . "  \"fecha\": (string en formato YYYY-MM-DD)\n"
                . "}\n\n"
                . "Si no puedes identificar algún campo, usa null para ese campo.\n"
                . "IMPORTANTE sobre el valor:\n"
                . "- Este es un comprobante colombiano donde el PUNTO es separador de MILES y la COMA es separador de decimales.\n"
                . "- Ejemplo: '$150.000,00' significa CIENTO CINCUENTA MIL pesos = 150000\n"
                . "- Ejemplo: '$1.200.000,00' significa UN MILLÓN DOSCIENTOS MIL pesos = 1200000\n"
                . "- Elimina los puntos de miles y los decimales, retorna solo el número entero.\n"
                . "- El valor debe ser un número entero sin separadores.";

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=" . $apiKey;

            $payload = array(
                'contents' => array(
                    array(
                        'parts' => array(
                            array(
                                'inlineData' => array(
                                    'mimeType' => $mimeType,
                                    'data' => $base64
                                )
                            ),
                            array(
                                'text' => $prompt
                            )
                        )
                    )
                ),
                'generationConfig' => array(
                    'temperature' => 0.1,
                    'maxOutputTokens' => 500
                )
            );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                Flight::json(array('error' => 'Error de conexión con el servicio de IA: ' . $curlError), 500);
                return;
            }

            if ($httpCode !== 200) {
                error_log("Error HTTP Gemini: " . $httpCode . " - " . $response);
                Flight::json(array('error' => 'Error en el servicio de IA (HTTP ' . $httpCode . ')'), 500);
                return;
            }

            $respuestaGemini = json_decode($response, true);

            if (!$respuestaGemini || !isset($respuestaGemini['candidates'][0]['content']['parts'][0]['text'])) {
                Flight::json(array('error' => 'No se pudo interpretar la respuesta de la IA'), 500);
                return;
            }

            $textoRespuesta = $respuestaGemini['candidates'][0]['content']['parts'][0]['text'];
            $textoRespuesta = preg_replace('/```json\s*/', '', $textoRespuesta);
            $textoRespuesta = preg_replace('/```\s*/', '', $textoRespuesta);
            $textoRespuesta = trim($textoRespuesta);

            $datosExtraidos = json_decode($textoRespuesta, true);

            if (!$datosExtraidos) {
                Flight::json(array(
                    'error' => 'No se pudieron extraer los datos del comprobante',
                    'respuesta_ia' => $textoRespuesta
                ), 422);
                return;
            }

            $stmtContador = $db->prepare("UPDATE ia_configuracion SET valor = valor + 1, fecha_actualizacion = NOW() WHERE clave = 'mensajes_generados_hoy'");
            $stmtContador->execute();

            // Leer tokens consumidos de la respuesta de Gemini
            $tokensInput = 0;
            $tokensOutput = 0;
            $tokensTotal = 0;
            if (isset($respuestaGemini['usageMetadata'])) {
                $tokensInput = isset($respuestaGemini['usageMetadata']['promptTokenCount']) ? intval($respuestaGemini['usageMetadata']['promptTokenCount']) : 0;
                $tokensOutput = isset($respuestaGemini['usageMetadata']['candidatesTokenCount']) ? intval($respuestaGemini['usageMetadata']['candidatesTokenCount']) : 0;
                $tokensTotal = $tokensInput + $tokensOutput;
            }

            // Acumular tokens consumidos
            if ($tokensTotal > 0) {
                $stmtTokens = $db->prepare("UPDATE ia_configuracion SET valor = valor + :tokens, fecha_actualizacion = NOW() WHERE clave = 'tokens_consumidos_hoy'");
                $stmtTokens->bindParam(':tokens', $tokensTotal);
                $stmtTokens->execute();
            }

            Flight::json(array(
                'success' => true,
                'datos' => array(
                    'valor' => isset($datosExtraidos['valor']) ? intval($datosExtraidos['valor']) : null,
                    'referencia' => isset($datosExtraidos['referencia']) ? trim($datosExtraidos['referencia']) : null,
                    'fecha' => isset($datosExtraidos['fecha']) ? $datosExtraidos['fecha'] : null
                ),
                'tokens' => array(
                    'input' => $tokensInput,
                    'output' => $tokensOutput,
                    'total' => $tokensTotal
                )
            ));
        } catch (Exception $e) {
            error_log("Error en analizarComprobante: " . $e->getMessage());
            Flight::json(array('error' => 'Error interno al procesar el comprobante: ' . $e->getMessage()), 500);
        }
    }

    /**
     * Registra múltiples pagos con sus cuentas aplicadas en una sola transacción.
     * POST /pagos-recibidos/registrar-masivo
     *
     * Body (JSON): {
     *   pagos: [
     *     {
     *       id_estudiante, id_acudiente, id_tipo_pago, fecha,
     *       referencia_bancaria, valor_recibido, observaciones,
     *       valor_comprobante, id_documento_persona,
     *       cuentas_aplicadas: [
     *         { id_cuenta_por_cobrar, valor_aplicado }
     *       ]
     *     }
     *   ],
     *   id_usuario_registro: number
     * }
     */
    public static function registrarMasivo()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            $pagos = Flight::request()->data['pagos'];
            $id_usuario_registro = Flight::request()->data['id_usuario_registro'];

            if (!is_array($pagos) || count($pagos) === 0) {
                Flight::json(array('error' => 'No se recibieron pagos para registrar'), 400);
                return;
            }

            if (!$id_usuario_registro) {
                Flight::json(array('error' => 'Se requiere el ID del usuario que registra'), 400);
                return;
            }

            // Validar referencias vs comprobantes
            $totalesPorReferencia = array();
            foreach ($pagos as $pago) {
                if (!empty($pago['referencia_bancaria']) && !empty($pago['valor_comprobante'])) {
                    $ref = trim($pago['referencia_bancaria']);
                    if (!isset($totalesPorReferencia[$ref])) {
                        $totalesPorReferencia[$ref] = array(
                            'valor_comprobante' => intval($pago['valor_comprobante']),
                            'total_asignado' => 0
                        );
                    }
                    $totalesPorReferencia[$ref]['total_asignado'] += intval($pago['valor_recibido']);
                }
            }

            $erroresReferencia = array();
            foreach ($totalesPorReferencia as $ref => $datos) {
                if ($datos['total_asignado'] > $datos['valor_comprobante']) {
                    $erroresReferencia[] = "Referencia '{$ref}': pagos por \${$datos['total_asignado']} exceden comprobante de \${$datos['valor_comprobante']}";
                }
            }

            if (count($erroresReferencia) > 0) {
                Flight::json(array(
                    'error' => 'Validación de comprobante fallida',
                    'detalles' => $erroresReferencia
                ), 400);
                return;
            }

            // Iniciar transacción
            $db->beginTransaction();

            try {
                $fecha_registro = date('Y-m-d H:i:s');
                $pagosRegistrados = array();
                $errores = array();

                $stmtPago = $db->prepare("
                    INSERT INTO pagos_recibidos 
                    (fecha, id_estudiante, id_colaborador, id_acudiente, id_tipo_pago, 
                     valor_recibido, observaciones, referencia_bancaria, 
                     fecha_registro, id_usuario_registro, 
                     fecha_contabilizacion, id_usuario_contable, id_documento_persona) 
                    VALUES 
                    (:fecha, :id_estudiante, NULL, :id_acudiente, :id_tipo_pago, 
                     :valor_recibido, :observaciones, :referencia_bancaria, 
                     :fecha_registro, :id_usuario_registro, 
                     NULL, NULL, :id_documento_persona)
                ");

                $stmtCuenta = $db->prepare("
                    INSERT INTO cuenta_pagada 
                    (id_cuenta_por_cobrar, id_pago_recibido, valor_aplicado, fecha) 
                    VALUES 
                    (:id_cuenta_por_cobrar, :id_pago_recibido, :valor_aplicado, :fecha)
                ");

                // Cargar reportes de pago pendientes UNA sola vez para asociar automáticamente
                $stmtReportesPendientes = $db->prepare("
                    SELECT id, id_estudiante, id_tipo_pago, valor 
                    FROM reportes_pago 
                    WHERE estado = 'pendiente' 
                    ORDER BY fecha_registro ASC
                ");
                $stmtReportesPendientes->execute();
                $reportesPendientes = $stmtReportesPendientes->fetchAll(PDO::FETCH_ASSOC);

                $stmtAsociarReporte = $db->prepare("
                    UPDATE reportes_pago 
                    SET id_pago_recibido = :id_pago_recibido, 
                        estado = 'asociado', 
                        fecha_asociacion = NOW() 
                    WHERE id = :id
                ");

                foreach ($pagos as $index => $pago) {
                    // Validar campos obligatorios
                    if (empty($pago['id_estudiante']) || empty($pago['id_tipo_pago']) ||
                        empty($pago['fecha']) || empty($pago['valor_recibido']) || $pago['valor_recibido'] <= 0) {
                        $errores[] = array(
                            'index' => $index,
                            'mensaje' => 'Faltan campos obligatorios o el valor es inválido'
                        );
                        continue;
                    }

                    $id_acudiente = !empty($pago['id_acudiente']) ? $pago['id_acudiente'] : null;
                    $observaciones = !empty($pago['observaciones']) ? $pago['observaciones'] : 'Registro rápido de pago';
                    $referencia_bancaria = !empty($pago['referencia_bancaria']) ? $pago['referencia_bancaria'] : '';
                    $id_documento_persona = !empty($pago['id_documento_persona']) ? $pago['id_documento_persona'] : null;

                    $stmtPago->bindParam(':fecha', $pago['fecha']);
                    $stmtPago->bindParam(':id_estudiante', $pago['id_estudiante']);
                    $stmtPago->bindParam(':id_acudiente', $id_acudiente);
                    $stmtPago->bindParam(':id_tipo_pago', $pago['id_tipo_pago']);
                    $stmtPago->bindParam(':valor_recibido', $pago['valor_recibido']);
                    $stmtPago->bindParam(':observaciones', $observaciones);
                    $stmtPago->bindParam(':referencia_bancaria', $referencia_bancaria);
                    $stmtPago->bindParam(':fecha_registro', $fecha_registro);
                    $stmtPago->bindParam(':id_usuario_registro', $id_usuario_registro);
                    $stmtPago->bindParam(':id_documento_persona', $id_documento_persona);

                    if ($stmtPago->execute()) {
                        $idPago = $db->lastInsertId();

                        // Insertar cuentas aplicadas para este pago
                        $cuentasAplicadas = isset($pago['cuentas_aplicadas']) ? $pago['cuentas_aplicadas'] : array();
                        $cuentasInsertadas = 0;

                        foreach ($cuentasAplicadas as $cuenta) {
                            if (empty($cuenta['id_cuenta_por_cobrar']) || empty($cuenta['valor_aplicado']) || $cuenta['valor_aplicado'] <= 0) {
                                continue;
                            }

                            $stmtCuenta->bindParam(':id_cuenta_por_cobrar', $cuenta['id_cuenta_por_cobrar']);
                            $stmtCuenta->bindParam(':id_pago_recibido', $idPago);
                            $stmtCuenta->bindParam(':valor_aplicado', $cuenta['valor_aplicado']);
                            $stmtCuenta->bindParam(':fecha', $pago['fecha']);
                            $stmtCuenta->execute();
                            $cuentasInsertadas++;
                        }

                        // Asociar reporte de pago pendiente buscando en el array cargado al inicio
                        $reporteAsociado = null;
                        try {
                            foreach ($reportesPendientes as $keyReporte => $reporte) {
                                if ($reporte['id_estudiante'] == $pago['id_estudiante'] 
                                    && $reporte['id_tipo_pago'] == $pago['id_tipo_pago'] 
                                    && intval($reporte['valor']) == intval($pago['valor_recibido'])) {
                                    
                                    $stmtAsociarReporte->bindParam(':id_pago_recibido', $idPago);
                                    $stmtAsociarReporte->bindParam(':id', $reporte['id']);
                                    $stmtAsociarReporte->execute();
                                    $reporteAsociado = $reporte['id'];
                                    // Sacarlo del array para que no se asocie a otro pago
                                    unset($reportesPendientes[$keyReporte]);
                                    break;
                                }
                            }
                        } catch (Exception $eReporte) {
                            error_log("Advertencia: No se pudo asociar reporte de pago para pago #{$idPago}: " . $eReporte->getMessage());
                        }

                        $pagosRegistrados[] = array(
                            'index' => $index,
                            'id_pago' => $idPago,
                            'id_estudiante' => $pago['id_estudiante'],
                            'valor' => $pago['valor_recibido'],
                            'cuentas_aplicadas' => $cuentasInsertadas,
                            'reporte_asociado' => $reporteAsociado
                        );
                    } else {
                        $errores[] = array(
                            'index' => $index,
                            'mensaje' => 'Error al insertar el pago'
                        );
                    }
                }

                if (count($pagosRegistrados) === 0 && count($errores) > 0) {
                    $db->rollBack();
                    Flight::json(array(
                        'success' => false,
                        'message' => 'No se pudo registrar ningún pago',
                        'errores' => $errores
                    ), 400);
                    return;
                }

                $db->commit();

                Flight::json(array(
                    'success' => true,
                    'registrados' => count($pagosRegistrados),
                    'total_enviados' => count($pagos),
                    'pagos' => $pagosRegistrados,
                    'errores' => $errores,
                    'message' => 'Se registraron ' . count($pagosRegistrados) . ' de ' . count($pagos) . ' pagos'
                ));
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            error_log("Error en registrarMasivo: " . $e->getMessage());
            Flight::json(array('error' => 'Error al registrar los pagos: ' . $e->getMessage()), 500);
        }
    }
}