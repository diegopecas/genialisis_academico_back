<?php
class RegistrosLimpieza
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                rl.*,
                af.nombre as nombre_area,
                tpl.nombre as nombre_proceso,
                erl.nombre as nombre_estado,
                erl.color as color_estado,
                CONCAT(ue.primer_nombre, ' ', ue.primer_apellido) as nombre_ejecutor,
                CONCAT(us.primer_nombre, ' ', us.primer_apellido) as nombre_supervisor,
                (SELECT COUNT(*) FROM registros_limpieza_detalle WHERE id_registro_limpieza = rl.id) as total_elementos,
                (SELECT SUM(cantidad_consumida) FROM registros_limpieza_consumos WHERE id_registro_limpieza = rl.id) as total_productos_consumidos,
                DATE_FORMAT(rl.fecha_programada, '%d/%m/%Y') as fecha_programada_formateada
            FROM registros_limpieza rl
            INNER JOIN areas_fisicas af ON rl.id_area_fisica = af.id
            INNER JOIN tipos_proceso_limpieza tpl ON rl.id_tipo_proceso_limpieza = tpl.id
            INNER JOIN estados_registro_limpieza erl ON rl.id_estado = erl.id
            LEFT JOIN usuarios ue_user ON rl.id_usuario_ejecutor = ue_user.id
            LEFT JOIN personas ue ON ue_user.id_persona = ue.id
            LEFT JOIN usuarios us_user ON rl.id_usuario_supervisor = us_user.id
            LEFT JOIN personas us ON us_user.id_persona = us.id
            WHERE rl.id_tenant = :id_tenant
            ORDER BY rl.fecha_programada DESC, rl.id DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();

        // Obtener registro principal
        $sentence = $db->prepare("
            SELECT 
                rl.*,
                af.nombre as nombre_area,
                tpl.nombre as nombre_proceso,
                erl.nombre as nombre_estado,
                erl.color as color_estado,
                CONCAT(ue.primer_nombre, ' ', ue.primer_apellido) as nombre_ejecutor,
                CONCAT(us.primer_nombre, ' ', us.primer_apellido) as nombre_supervisor
            FROM registros_limpieza rl
            INNER JOIN areas_fisicas af ON rl.id_area_fisica = af.id
            INNER JOIN tipos_proceso_limpieza tpl ON rl.id_tipo_proceso_limpieza = tpl.id
            INNER JOIN estados_registro_limpieza erl ON rl.id_estado = erl.id
            LEFT JOIN usuarios ue_user ON rl.id_usuario_ejecutor = ue_user.id
            LEFT JOIN personas ue ON ue_user.id_persona = ue.id
            LEFT JOIN usuarios us_user ON rl.id_usuario_supervisor = us_user.id
            LEFT JOIN personas us ON us_user.id_persona = us.id
            WHERE rl.id = :id AND rl.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $registro = $sentence->fetch();

        if (!$registro) {
            Flight::json(array('error' => 'Registro no encontrado'), 404);
            return;
        }

        // Obtener detalles de elementos
        $sentence = $db->prepare("
            SELECT 
                rld.*,
                ef.nombre as nombre_elemento,
                p.nombre as nombre_mobiliario
            FROM registros_limpieza_detalle rld
            LEFT JOIN elementos_fisicos ef ON rld.id_elemento_fisico = ef.id
            LEFT JOIN productos_mobiliario pm ON rld.id_producto_mobiliario = pm.id
            LEFT JOIN productos p ON pm.id_producto = p.id
            WHERE rld.id_registro_limpieza = :id AND rld.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $registro['detalles'] = $sentence->fetchAll();

        // Obtener consumos
        $sentence = $db->prepare("
            SELECT 
                rlc.*,
                p.nombre as nombre_producto,
                um.abreviatura as unidad
            FROM registros_limpieza_consumos rlc
            INNER JOIN productos_limpieza pl ON rlc.id_producto_limpieza = pl.id
            INNER JOIN productos p ON pl.id_producto = p.id
            INNER JOIN unidades_medida um ON rlc.id_unidad_medida = um.id
            WHERE rlc.id_registro_limpieza = :id AND rlc.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $registro['consumos'] = $sentence->fetchAll();

        Flight::json($registro);
    }

    public static function new()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $id_area_fisica = Flight::request()->data['id_area_fisica'];
            $id_tipo_proceso_limpieza = Flight::request()->data['id_tipo_proceso_limpieza'];
            // CAMBIO: usar fecha_programada
            $fecha_programada = Flight::request()->data['fecha_programada'] ?? date('Y-m-d');
            $id_usuario_ejecutor = Flight::request()->data['id_usuario_ejecutor'] ?? null;
            $observaciones = Flight::request()->data['observaciones'] ?? null;

            // Crear registro principal - CAMBIO: NO registrar fecha, solo fecha_programada
            $sentence = $db->prepare("
                INSERT INTO registros_limpieza (
                    id,
                    id_tenant,
                    id_area_fisica,
                    id_tipo_proceso_limpieza,
                    fecha_programada,
                    id_estado,
                    id_usuario_ejecutor,
                    observaciones
                ) VALUES (
                    :id,
                    :id_tenant,
                    :id_area_fisica,
                    :id_tipo_proceso_limpieza,
                    :fecha_programada,
                    1, -- Estado: Programado
                    :id_usuario_ejecutor,
                    :observaciones
                )
            ");

            $idRL = Uuid::generar();
            $sentence->bindValue(':id', $idRL);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_area_fisica', $id_area_fisica);
            $sentence->bindParam(':id_tipo_proceso_limpieza', $id_tipo_proceso_limpieza);
            $sentence->bindParam(':fecha_programada', $fecha_programada);
            $sentence->bindParam(':id_usuario_ejecutor', $id_usuario_ejecutor);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->execute();

            $id_registro = $idRL;

            // Insertar detalles si vienen
            if (isset(Flight::request()->data['detalles'])) {
                foreach (Flight::request()->data['detalles'] as $detalle) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_detalle (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_elemento_fisico,
                            id_producto_mobiliario
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            :id_elemento_fisico,
                            :id_producto_mobiliario
                        )
                    ");

                    $id_elemento = $detalle['id_elemento_fisico'] ?? null;
                    $id_mobiliario = $detalle['id_producto_mobiliario'] ?? null;

                    $idRLD = Uuid::generar();
                    $sentence->bindValue(':id', $idRLD);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id_registro);
                    $sentence->bindParam(':id_elemento_fisico', $id_elemento);
                    $sentence->bindParam(':id_producto_mobiliario', $id_mobiliario);
                    $sentence->execute();
                }
            }

            // Insertar consumos programados si vienen
            if (isset(Flight::request()->data['consumos'])) {
                foreach (Flight::request()->data['consumos'] as $consumo) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_consumos (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_producto_limpieza,
                            cantidad_consumida,
                            id_unidad_medida
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            :id_producto_limpieza,
                            :cantidad_consumida,
                            :id_unidad_medida
                        )
                    ");

                    $idRLC = Uuid::generar();
                    $sentence->bindValue(':id', $idRLC);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id_registro);
                    $sentence->bindParam(':id_producto_limpieza', $consumo['id_producto_limpieza']);
                    $sentence->bindParam(':cantidad_consumida', $consumo['cantidad_consumida']);
                    $sentence->bindParam(':id_unidad_medida', $consumo['id_unidad_medida']);
                    $sentence->execute();
                }
            }

            $db->commit();
            Flight::json(array('id' => $id_registro));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en RegistrosLimpieza::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function iniciar()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $hora_inicio = date('H:i:s');
        $fecha_actual = date('Y-m-d'); // CAMBIO: registrar fecha real de inicio

        $sentence = $db->prepare("
            UPDATE registros_limpieza 
            SET hora_inicio = :hora_inicio,
                fecha = :fecha,  -- CAMBIO: registrar fecha real
                id_estado = 2 -- En Proceso
            WHERE id = :id AND id_estado = 1 AND id_tenant = :id_tenant
        ");

        $sentence->bindParam(':hora_inicio', $hora_inicio);
        $sentence->bindParam(':fecha', $fecha_actual);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        if ($sentence->rowCount() == 0) {
            Flight::json(array('error' => 'No se pudo iniciar el registro'), 400);
            return;
        }

        Flight::json(array(
            'success' => true,
            'hora_inicio' => $hora_inicio,
            'fecha' => $fecha_actual
        ));
    }

    public static function finalizar()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $id = Flight::request()->data['id'];
            $hora_fin = date('H:i:s');
            $id_usuario = Flight::request()->data['id_usuario'];

            // Obtener los consumos programados
            $sentence = $db->prepare("
                SELECT 
                    rlc.*,
                    pl.id_producto,
                    p.stock_actual,
                    p.nombre as nombre_producto
                FROM registros_limpieza_consumos rlc
                INNER JOIN productos_limpieza pl ON rlc.id_producto_limpieza = pl.id
                INNER JOIN productos p ON pl.id_producto = p.id
                WHERE rlc.id_registro_limpieza = :id AND rlc.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $consumos = $sentence->fetchAll();

            if (count($consumos) == 0) {
                throw new Exception('No hay productos para consumir');
            }

            // Crear movimiento de salida
            $sentence = $db->prepare("
                INSERT INTO movimientos_productos (
                    id,
                    id_tenant,
                    fecha_movimiento,
                    id_concepto_movimiento,
                    id_estado,
                    observaciones,
                    id_usuario_registro
                ) VALUES (
                    :id,
                    :id_tenant,
                    NOW(),
                    (SELECT id FROM conceptos_movimiento WHERE nombre = 'Salida por Limpieza' AND id_tenant = " . TenantContext::id() . " LIMIT 1),
                    3, -- Aprobado
                    CONCAT('Registro de limpieza #', :id),
                    :id_usuario
                )
            ");
            $idMov = Uuid::generar();
            $sentence->bindValue(':id', $idMov);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_usuario', $id_usuario);
            $sentence->execute();

            $id_movimiento = $idMov;

            // Variables para tracking
            $productos_ajustados = [];

            // Crear detalles del movimiento y actualizar stock
            foreach ($consumos as $consumo) {
                // CAMBIO: Validar stock y ajustar cantidad si es necesario
                $cantidad_a_descontar = $consumo['cantidad_consumida'];
                $stock_disponible = $consumo['stock_actual'];

                // Si no hay suficiente stock, usar solo lo disponible
                if ($cantidad_a_descontar > $stock_disponible) {
                    $cantidad_a_descontar = $stock_disponible;
                    $productos_ajustados[] = array(
                        'producto' => $consumo['nombre_producto'],
                        'solicitado' => $consumo['cantidad_consumida'],
                        'disponible' => $stock_disponible,
                        'descontado' => $cantidad_a_descontar
                    );
                }

                // Si no hay stock, continuar con el siguiente producto
                if ($cantidad_a_descontar <= 0) {
                    error_log("Producto sin stock: " . $consumo['nombre_producto']);
                    continue;
                }

                // Insertar detalle del movimiento con la cantidad ajustada
                $sentence = $db->prepare("
                    INSERT INTO movimientos_productos_detalle (
                        id,
                        id_tenant,
                        id_movimiento,
                        id_producto,
                        cantidad,
                        stock_anterior,
                        precio_unitario
                    ) VALUES (
                        :id,
                        :id_tenant,
                        :id_movimiento,
                        :id_producto,
                        :cantidad,
                        :stock_anterior,
                        (SELECT precio_unitario FROM productos WHERE id = :id_producto2)
                    )
                ");

                $idMovDet = Uuid::generar();
                $sentence->bindValue(':id', $idMovDet);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindParam(':id_movimiento', $id_movimiento);
                $sentence->bindParam(':id_producto', $consumo['id_producto']);
                $sentence->bindParam(':cantidad', $cantidad_a_descontar);
                $sentence->bindParam(':stock_anterior', $consumo['stock_actual']);
                $sentence->bindParam(':id_producto2', $consumo['id_producto']);
                $sentence->execute();

                $id_movimiento_detalle = $idMovDet;

                // Actualizar stock del producto - NUNCA permitir negativos
                $sentence = $db->prepare("
                    UPDATE productos 
                    SET stock_anterior = stock_actual,
                        stock_actual = GREATEST(0, stock_actual - :cantidad), -- CAMBIO: usar GREATEST para evitar negativos
                        id_ultimo_movimiento = :id_movimiento,
                        fecha_ultimo_movimiento = NOW()
                    WHERE id = :id_producto AND id_tenant = :id_tenant
                ");

                $sentence->bindParam(':cantidad', $cantidad_a_descontar);
                $sentence->bindParam(':id_movimiento', $id_movimiento);
                $sentence->bindParam(':id_producto', $consumo['id_producto']);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();

                // Actualizar referencia en consumos
                $sentence = $db->prepare("
                    UPDATE registros_limpieza_consumos 
                    SET id_movimiento_inventario = :id_movimiento_detalle
                    WHERE id = :id_consumo AND id_tenant = :id_tenant
                ");

                $sentence->bindParam(':id_movimiento_detalle', $id_movimiento_detalle);
                $sentence->bindParam(':id_consumo', $consumo['id']);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();
            }

            // Actualizar registro de limpieza
            $sentence = $db->prepare("
                UPDATE registros_limpieza 
                SET hora_fin = :hora_fin,
                    id_estado = 3 -- Realizado
                WHERE id = :id AND id_estado = 2 AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':hora_fin', $hora_fin);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                throw new Exception('El registro debe estar en proceso para finalizar');
            }

            $db->commit();

            // Preparar respuesta
            $response = array(
                'success' => true,
                'hora_fin' => $hora_fin,
                'id_movimiento' => $id_movimiento
            );

            // Agregar información de ajustes si hubo
            if (count($productos_ajustados) > 0) {
                $response['productos_ajustados'] = $productos_ajustados;
            }

            Flight::json($response);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en RegistrosLimpieza::finalizar: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function supervisar()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_usuario_supervisor = Flight::request()->data['id_usuario_supervisor'];
        $observaciones = Flight::request()->data['observaciones'] ?? null;

        $sentence = $db->prepare("
            UPDATE registros_limpieza 
            SET id_estado = 4, -- Supervisado
                id_usuario_supervisor = :id_usuario_supervisor,
                observaciones = CONCAT(IFNULL(observaciones, ''), ' | Supervisión: ', :observaciones)
            WHERE id = :id AND id_estado = 3 AND id_tenant = :id_tenant
        ");

        $sentence->bindParam(':id_usuario_supervisor', $id_usuario_supervisor);
        $sentence->bindParam(':observaciones', $observaciones);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        if ($sentence->rowCount() == 0) {
            Flight::json(array('error' => 'El registro debe estar realizado para supervisar'), 400);
            return;
        }

        Flight::json(array('success' => true));
    }

    public static function cancelar()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $motivo = Flight::request()->data['motivo'] ?? 'Sin motivo especificado';

        $sentence = $db->prepare("
            UPDATE registros_limpieza 
            SET id_estado = 5, -- Cancelado
                observaciones = CONCAT(IFNULL(observaciones, ''), ' | Cancelado: ', :motivo)
            WHERE id = :id AND id_estado IN (1, 2) AND id_tenant = :id_tenant
        ");

        $sentence->bindParam(':motivo', $motivo);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        if ($sentence->rowCount() == 0) {
            Flight::json(array('error' => 'Solo se pueden cancelar registros programados o en proceso'), 400);
            return;
        }

        Flight::json(array('success' => true));
    }


    public static function getElementosParaProceso()
    {
        $db = Flight::db();

        $id_area = Flight::request()->query['id_area'] ??
            Flight::request()->query->id_area ??
            $_GET['id_area'] ??
            null;

        $id_proceso = Flight::request()->query['id_proceso'] ??
            Flight::request()->query->id_proceso ??
            $_GET['id_proceso'] ??
            null;

        if (!$id_area || !$id_proceso) {
            Flight::json(array('error' => 'Faltan parámetros requeridos (id_area, id_proceso)'), 400);
            return;
        }

        $response = array(
            'elementos_fisicos' => [],
            'productos_mobiliario' => [],
            'productos_limpieza' => []
        );

        // Obtener elementos físicos que aplican al proceso con sus cantidades en el área
        $sentence = $db->prepare("
                        SELECT DISTINCT
                            ef.id,
                            ef.nombre,
                            ef.descripcion,
                            IFNULL(efaf.cantidad, 0) as cantidad_en_area
                        FROM elementos_fisicos ef
                        INNER JOIN elementos_fisicos_x_procesos_limpieza efpl ON ef.id = efpl.id_elemento_fisico
                        LEFT JOIN elementos_fisicos_x_areas_fisicas efaf 
                            ON ef.id = efaf.id_elemento_fisico 
                            AND efaf.id_area_fisica = :id_area
                        WHERE efpl.id_tipo_proceso_limpieza = :id_proceso AND ef.id_tenant = :id_tenant
                        ORDER BY ef.nombre
                    ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response['elementos_fisicos'] = $sentence->fetchAll();

        // Obtener mobiliario del área que aplica al proceso
        $sentence = $db->prepare("
                                SELECT DISTINCT
                                    pm.id,
                                    p.nombre,
                                    pmxa.cantidad,
                                    p.id as id_producto
                                FROM productos_mobiliario pm
                                INNER JOIN productos p ON pm.id_producto = p.id
                                INNER JOIN productos_mobiliario_x_areas_fisicas pmxa ON pm.id = pmxa.id_producto_mobiliario
                                INNER JOIN productos_mobiliario_x_procesos_limpieza pmpl ON pm.id = pmpl.id_producto_mobiliario
                                WHERE pmxa.id_area = :id_area 
                                AND pmpl.id_tipo_proceso_limpieza = :id_proceso
                                AND p.activo = 1
                                AND pm.id_tenant = :id_tenant
                                ORDER BY p.nombre
                            ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response['productos_mobiliario'] = $sentence->fetchAll();

        // Calcular productos de limpieza necesarios
        $productos_temp = array();

        // PARTE 1: Productos para elementos físicos
        // cantidad_total = cantidad_sugerida * cantidad_del_elemento_en_el_area
        $sentence = $db->prepare("
                                SELECT 
                                    pl.id as id_producto_limpieza,
                                    p.id as id_producto,
                                    p.nombre,
                                    p.stock_actual,
                                    um.id as id_unidad_medida,
                                    um.abreviatura,
                                    efplp.cantidad_sugerida,
                                    IFNULL(efaf.cantidad, 0) as cantidad_elemento_area,
                                    ef.nombre as nombre_elemento,
                                    ef.id as id_elemento
                                FROM elementos_fisicos_x_procesos_limpieza efpl
                                INNER JOIN elementos_fisicos ef ON efpl.id_elemento_fisico = ef.id
                                INNER JOIN elementos_fisicos_x_procesos_limpieza_productos efplp 
                                    ON efpl.id = efplp.id_elementos_fisicos_x_procesos_limpieza
                                INNER JOIN productos_limpieza pl ON efplp.id_producto_limpieza = pl.id
                                INNER JOIN productos p ON pl.id_producto = p.id
                                INNER JOIN unidades_medida um ON p.id_unidad_medida = um.id
                                LEFT JOIN elementos_fisicos_x_areas_fisicas efaf 
                                    ON efpl.id_elemento_fisico = efaf.id_elemento_fisico 
                                    AND efaf.id_area_fisica = :id_area
                                WHERE efpl.id_tipo_proceso_limpieza = :id_proceso
                                AND p.activo = 1
                                AND efpl.id_tenant = :id_tenant
                            ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $elementosProductos = $sentence->fetchAll();

        // Procesar productos de elementos físicos
        foreach ($elementosProductos as $prod) {
            $key = $prod['id_producto_limpieza'];

            // Calcular cantidad: cantidad_sugerida * cantidad_del_elemento_en_area
            $cantidad_calculada = $prod['cantidad_sugerida'] * $prod['cantidad_elemento_area'];

            if (!isset($productos_temp[$key])) {
                $productos_temp[$key] = array(
                    'id_producto_limpieza' => $prod['id_producto_limpieza'],
                    'id_producto' => $prod['id_producto'],
                    'nombre' => $prod['nombre'],
                    'stock_actual' => $prod['stock_actual'],
                    'id_unidad_medida' => $prod['id_unidad_medida'],
                    'abreviatura' => $prod['abreviatura'],
                    'cantidad_total' => $cantidad_calculada,
                    'detalle_elementos' => array(),
                    'detalle_mobiliario' => array()
                );
            } else {
                $productos_temp[$key]['cantidad_total'] += $cantidad_calculada;
            }

            // Agregar detalle para debugging
            if ($cantidad_calculada > 0) {
                $productos_temp[$key]['detalle_elementos'][] = array(
                    'elemento' => $prod['nombre_elemento'],
                    'id_elemento' => $prod['id_elemento'],
                    'cantidad_sugerida' => $prod['cantidad_sugerida'],
                    'cantidad_area' => $prod['cantidad_elemento_area'],
                    'subtotal' => $cantidad_calculada
                );
            }
        }

        // PARTE 2: Productos para mobiliario del área
        if (count($response['productos_mobiliario']) > 0) {
            $ids_mobiliario = array_column($response['productos_mobiliario'], 'id');
            $placeholders = implode(',', array_fill(0, count($ids_mobiliario), '?'));

            $sql = "
                    SELECT 
                        pl.id as id_producto_limpieza,
                        p.id as id_producto,
                        p.nombre,
                        p.stock_actual,
                        um.id as id_unidad_medida,
                        um.abreviatura,
                        pmplp.cantidad_sugerida,
                        pm.id as id_mobiliario,
                        prod_mob.nombre as nombre_mobiliario
                    FROM productos_mobiliario_x_procesos_limpieza pmpl
                    INNER JOIN productos_mobiliario pm ON pmpl.id_producto_mobiliario = pm.id
                    INNER JOIN productos prod_mob ON pm.id_producto = prod_mob.id
                    INNER JOIN productos_mobiliario_x_procesos_limpieza_productos pmplp 
                        ON pmpl.id = pmplp.id_proceso_limpieza
                    INNER JOIN productos_mobiliario_x_productos_limpieza pmxpl 
                        ON pmplp.id_productos_mobiliario_x_productos_limpieza = pmxpl.id
                    INNER JOIN productos_limpieza pl ON pmxpl.id_producto_limpieza = pl.id
                    INNER JOIN productos p ON pl.id_producto = p.id
                    INNER JOIN unidades_medida um ON p.id_unidad_medida = um.id
                    WHERE pmpl.id_producto_mobiliario IN ($placeholders)
                    AND pmpl.id_tipo_proceso_limpieza = ?
                    AND p.activo = 1
                    AND pmpl.id_tenant = ?
                ";

            $sentence = $db->prepare($sql);
            $params = array_merge($ids_mobiliario, [$id_proceso, TenantContext::id()]);
            $sentence->execute($params);
            $productosMobiliario = $sentence->fetchAll();

            // Crear lookup de cantidades de mobiliario
            $cantidadesMobiliario = array();
            foreach ($response['productos_mobiliario'] as $mob) {
                $cantidadesMobiliario[$mob['id']] = array(
                    'cantidad' => $mob['cantidad'],
                    'nombre' => $mob['nombre']
                );
            }

            // Procesar productos del mobiliario
            foreach ($productosMobiliario as $prod) {
                $key = $prod['id_producto_limpieza'];
                $cantidad_mobiliario = $cantidadesMobiliario[$prod['id_mobiliario']]['cantidad'] ?? 0;

                // Calcular cantidad: cantidad_sugerida * cantidad_de_mobiliario
                $cantidad_calculada = $prod['cantidad_sugerida'] * $cantidad_mobiliario;

                if (!isset($productos_temp[$key])) {
                    $productos_temp[$key] = array(
                        'id_producto_limpieza' => $prod['id_producto_limpieza'],
                        'id_producto' => $prod['id_producto'],
                        'nombre' => $prod['nombre'],
                        'stock_actual' => $prod['stock_actual'],
                        'id_unidad_medida' => $prod['id_unidad_medida'],
                        'abreviatura' => $prod['abreviatura'],
                        'cantidad_total' => $cantidad_calculada,
                        'detalle_elementos' => array(),
                        'detalle_mobiliario' => array()
                    );
                } else {
                    $productos_temp[$key]['cantidad_total'] += $cantidad_calculada;
                }

                // Agregar detalle para debugging
                if ($cantidad_calculada > 0) {
                    $productos_temp[$key]['detalle_mobiliario'][] = array(
                        'mobiliario' => $prod['nombre_mobiliario'],
                        'id_mobiliario' => $prod['id_mobiliario'],
                        'cantidad_sugerida' => $prod['cantidad_sugerida'],
                        'cantidad_mobiliario' => $cantidad_mobiliario,
                        'subtotal' => $cantidad_calculada
                    );
                }
            }
        }

        // Redondear cantidades totales a 1 decimal y limpiar estructura para respuesta
        foreach ($productos_temp as &$producto) {
            $producto['cantidad_total'] = round($producto['cantidad_total'], 1);

            // Opcional: Incluir detalles del cálculo para debugging
            // Si no quieres enviar los detalles al frontend, comenta estas líneas
            $producto['calculo_detallado'] = array(
                'elementos' => $producto['detalle_elementos'],
                'mobiliario' => $producto['detalle_mobiliario']
            );

            // Remover arrays temporales si no se necesitan
            unset($producto['detalle_elementos']);
            unset($producto['detalle_mobiliario']);
        }

        $response['productos_limpieza'] = array_values($productos_temp);

        Flight::json($response);
    }

    public static function update()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $id = Flight::request()->data['id'];
            $fecha_programada = Flight::request()->data['fecha_programada'] ?? null;
            $observaciones = Flight::request()->data['observaciones'] ?? null;

            // Verificar que esté en estado programado
            $sentence = $db->prepare("SELECT id_estado FROM registros_limpieza WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $registro = $sentence->fetch();

            if (!$registro || $registro['id_estado'] != 1) {
                throw new Exception('Solo se pueden editar registros en estado Programado');
            }

            // Actualizar fecha_programada y observaciones
            $sentence = $db->prepare("
                UPDATE registros_limpieza 
                SET fecha_programada = :fecha_programada,
                    observaciones = :observaciones
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':fecha_programada', $fecha_programada);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            // Limpiar detalles anteriores
            $sentence = $db->prepare("DELETE FROM registros_limpieza_detalle WHERE id_registro_limpieza = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            // Limpiar consumos anteriores
            $sentence = $db->prepare("DELETE FROM registros_limpieza_consumos WHERE id_registro_limpieza = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            // Insertar nuevos detalles
            if (isset(Flight::request()->data['detalles'])) {
                foreach (Flight::request()->data['detalles'] as $detalle) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_detalle (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_elemento_fisico,
                            id_producto_mobiliario
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            :id_elemento_fisico,
                            :id_producto_mobiliario
                        )
                    ");

                    $id_elemento = $detalle['id_elemento_fisico'] ?? null;
                    $id_mobiliario = $detalle['id_producto_mobiliario'] ?? null;

                    $idRLD2 = Uuid::generar();
                    $sentence->bindValue(':id', $idRLD2);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id);
                    $sentence->bindParam(':id_elemento_fisico', $id_elemento);
                    $sentence->bindParam(':id_producto_mobiliario', $id_mobiliario);
                    $sentence->execute();
                }
            }

            // Insertar nuevos consumos
            if (isset(Flight::request()->data['consumos'])) {
                foreach (Flight::request()->data['consumos'] as $consumo) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_consumos (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_producto_limpieza,
                            cantidad_consumida,
                            id_unidad_medida
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            :id_producto_limpieza,
                            :cantidad_consumida,
                            :id_unidad_medida
                        )
                    ");

                    $idRLC2 = Uuid::generar();
                    $sentence->bindValue(':id', $idRLC2);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id);
                    $sentence->bindParam(':id_producto_limpieza', $consumo['id_producto_limpieza']);
                    $sentence->bindParam(':cantidad_consumida', $consumo['cantidad_consumida']);
                    $sentence->bindParam(':id_unidad_medida', $consumo['id_unidad_medida']);
                    $sentence->execute();
                }
            }

            $db->commit();
            self::getById($id);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en RegistrosLimpieza::update: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Devuelve las áreas configuradas para un proceso de limpieza, con el consumo
     * calculado de cada una, cuáles aplican hoy y cuáles venían marcadas la última
     * vez que se registró ese proceso.
     * Usado por la pantalla de Registro Rápido de Aseo.
     */
    public static function getRapidoPreview()
    {
        $db = Flight::db();

        $id_proceso = Flight::request()->query['id_proceso'] ??
            Flight::request()->query->id_proceso ??
            $_GET['id_proceso'] ??
            null;

        if (!$id_proceso) {
            Flight::json(array('error' => 'Falta el parámetro requerido (id_proceso)'), 400);
            return;
        }

        $response = array(
            'areas' => [],
            'ultima_fecha' => null
        );

        // La columna del día de hoy dentro de la configuración área/proceso
        $columnasDia = array(
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            7 => 'domingo'
        );
        $columnaHoy = $columnasDia[(int) date('N')];

        // Áreas configuradas y activas para el proceso, en orden de prioridad
        $sentence = $db->prepare("
            SELECT
                af.id,
                af.nombre,
                af.descripcion,
                af.ubicacion,
                axp.prioridad,
                axp.tiempo_estimado_minutos,
                axp.hora_sugerida,
                axp.$columnaHoy as aplica_hoy
            FROM areas_fisicas_x_procesos_limpieza axp
            INNER JOIN areas_fisicas af ON axp.id_area_fisica = af.id
            WHERE axp.id_tipo_proceso_limpieza = :id_proceso
                AND axp.activo = 1
                AND af.activo = 1
                AND axp.id_tenant = :id_tenant
            ORDER BY axp.prioridad ASC, af.nombre ASC
        ");
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $areas = $sentence->fetchAll();

        if (count($areas) == 0) {
            Flight::json($response);
            return;
        }

        $ids_areas = array_column($areas, 'id');

        // Última fecha en la que se registró este proceso (realizado o supervisado)
        $sentence = $db->prepare("
            SELECT MAX(fecha) as ultima_fecha
            FROM registros_limpieza
            WHERE id_tipo_proceso_limpieza = :id_proceso
                AND id_estado IN (3, 4)
                AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        $ultima_fecha = $fila['ultima_fecha'] ?? null;

        // Áreas que se limpiaron esa última fecha
        $areas_ultima = array();
        if ($ultima_fecha) {
            $sentence = $db->prepare("
                SELECT DISTINCT id_area_fisica
                FROM registros_limpieza
                WHERE id_tipo_proceso_limpieza = :id_proceso
                    AND fecha = :fecha
                    AND id_estado IN (3, 4)
                    AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id_proceso', $id_proceso);
            $sentence->bindParam(':fecha', $ultima_fecha);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $areas_ultima = array_flip(array_column($sentence->fetchAll(), 'id_area_fisica'));
        }

        $calculo = self::calcularAreasProceso($db, $id_proceso, $ids_areas);

        foreach ($areas as &$area) {
            $datos = $calculo[$area['id']] ?? array('elementos' => [], 'mobiliario' => [], 'productos' => []);

            $area['aplica_hoy'] = ((int) $area['aplica_hoy']) === 1;
            $area['prioridad'] = (int) $area['prioridad'];
            $area['tiempo_estimado_minutos'] = (int) ($area['tiempo_estimado_minutos'] ?? 0);
            $area['total_elementos'] = count($datos['elementos']);
            $area['total_mobiliario'] = count($datos['mobiliario']);
            $area['productos'] = $datos['productos'];

            // Si el proceso nunca se ha registrado, se premarcan las áreas que aplican hoy
            $area['preseleccionada'] = $ultima_fecha
                ? isset($areas_ultima[$area['id']])
                : $area['aplica_hoy'];
        }
        unset($area);

        $response['areas'] = $areas;
        $response['ultima_fecha'] = $ultima_fecha;

        Flight::json($response);
    }

    /**
     * Crea en una sola transacción los registros de limpieza de todas las áreas
     * seleccionadas, ya en estado Realizado, y descuenta el inventario con un
     * único movimiento consolidado.
     * Usado por la pantalla de Registro Rápido de Aseo.
     */
    public static function crearRapido()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $id_proceso = Flight::request()->data['id_tipo_proceso_limpieza'] ?? null;
            $fecha = Flight::request()->data['fecha'] ?? date('Y-m-d');
            $hora_inicio = Flight::request()->data['hora_inicio'] ?? null;
            $hora_fin = Flight::request()->data['hora_fin'] ?? null;
            $observaciones = Flight::request()->data['observaciones'] ?? null;
            $id_usuario_ejecutor = Flight::request()->data['id_usuario_ejecutor'] ?? null;
            $areas = Flight::request()->data['areas'] ?? array();

            if (!$id_proceso) {
                throw new Exception('Debe indicar el tipo de proceso de limpieza');
            }
            if (!is_array($areas) || count($areas) == 0) {
                throw new Exception('Debe seleccionar al menos un área');
            }

            // Solo se aceptan áreas configuradas y activas para el proceso
            $placeholders = implode(',', array_fill(0, count($areas), '?'));
            $sentence = $db->prepare("
                SELECT axp.id_area_fisica
                FROM areas_fisicas_x_procesos_limpieza axp
                INNER JOIN areas_fisicas af ON axp.id_area_fisica = af.id
                WHERE axp.id_tipo_proceso_limpieza = ?
                    AND axp.activo = 1
                    AND af.activo = 1
                    AND axp.id_tenant = ?
                    AND axp.id_area_fisica IN ($placeholders)
            ");
            $sentence->execute(array_merge([$id_proceso, TenantContext::id()], array_values($areas)));
            $areas_validas = array_column($sentence->fetchAll(), 'id_area_fisica');

            if (count($areas_validas) == 0) {
                throw new Exception('Ninguna de las áreas seleccionadas está configurada para este proceso');
            }

            // El consumo se recalcula en el servidor, no se toma del cliente
            $calculo = self::calcularAreasProceso($db, $id_proceso, $areas_validas);

            $ids_registros = array();
            $consumos_por_producto = array();
            $consumo_consolidado = array();

            foreach ($areas_validas as $id_area) {
                $datos = $calculo[$id_area] ?? array('elementos' => [], 'mobiliario' => [], 'productos' => []);

                // Registro principal, directo en estado Realizado
                $sentence = $db->prepare("
                    INSERT INTO registros_limpieza (
                        id,
                        id_tenant,
                        fecha,
                        fecha_programada,
                        hora_inicio,
                        hora_fin,
                        id_estado,
                        observaciones,
                        id_area_fisica,
                        id_tipo_proceso_limpieza,
                        id_usuario_ejecutor
                    ) VALUES (
                        :id,
                        :id_tenant,
                        :fecha,
                        :fecha_programada,
                        :hora_inicio,
                        :hora_fin,
                        3, -- Realizado
                        :observaciones,
                        :id_area_fisica,
                        :id_tipo_proceso_limpieza,
                        :id_usuario_ejecutor
                    )
                ");

                $id_registro = Uuid::generar();
                $sentence->bindValue(':id', $id_registro);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindParam(':fecha', $fecha);
                $sentence->bindParam(':fecha_programada', $fecha);
                $sentence->bindParam(':hora_inicio', $hora_inicio);
                $sentence->bindParam(':hora_fin', $hora_fin);
                $sentence->bindParam(':observaciones', $observaciones);
                $sentence->bindParam(':id_area_fisica', $id_area);
                $sentence->bindParam(':id_tipo_proceso_limpieza', $id_proceso);
                $sentence->bindParam(':id_usuario_ejecutor', $id_usuario_ejecutor);
                $sentence->execute();

                $ids_registros[] = $id_registro;

                // Detalle: todos los elementos y mobiliario del área para ese proceso
                foreach ($datos['elementos'] as $elemento) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_detalle (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_elemento_fisico,
                            id_producto_mobiliario
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            :id_elemento_fisico,
                            NULL
                        )
                    ");
                    $sentence->bindValue(':id', Uuid::generar());
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id_registro);
                    $sentence->bindParam(':id_elemento_fisico', $elemento['id']);
                    $sentence->execute();
                }

                foreach ($datos['mobiliario'] as $mobiliario) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_detalle (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_elemento_fisico,
                            id_producto_mobiliario
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            NULL,
                            :id_producto_mobiliario
                        )
                    ");
                    $sentence->bindValue(':id', Uuid::generar());
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id_registro);
                    $sentence->bindParam(':id_producto_mobiliario', $mobiliario['id']);
                    $sentence->execute();
                }

                // Consumos del área. Un área sin productos configurados queda sin consumo.
                foreach ($datos['productos'] as $producto) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_consumos (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_producto_limpieza,
                            cantidad_consumida,
                            id_unidad_medida
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            :id_producto_limpieza,
                            :cantidad_consumida,
                            :id_unidad_medida
                        )
                    ");

                    $id_consumo = Uuid::generar();
                    $sentence->bindValue(':id', $id_consumo);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id_registro);
                    $sentence->bindParam(':id_producto_limpieza', $producto['id_producto_limpieza']);
                    $sentence->bindParam(':cantidad_consumida', $producto['cantidad']);
                    $sentence->bindParam(':id_unidad_medida', $producto['id_unidad_medida']);
                    $sentence->execute();

                    $id_producto = $producto['id_producto'];

                    // Se acumula por producto para descontar una sola vez
                    if (!isset($consumo_consolidado[$id_producto])) {
                        $consumo_consolidado[$id_producto] = array(
                            'id_producto' => $id_producto,
                            'nombre' => $producto['nombre'],
                            'abreviatura' => $producto['abreviatura'],
                            'stock_actual' => $producto['stock_actual'],
                            'cantidad' => 0
                        );
                    }
                    $consumo_consolidado[$id_producto]['cantidad'] += $producto['cantidad'];
                    $consumos_por_producto[$id_producto][] = $id_consumo;
                }
            }

            $id_movimiento = null;
            $productos_ajustados = array();

            if (count($consumo_consolidado) > 0) {
                // Concepto de movimiento usado también por RegistrosLimpieza::finalizar
                $sentence = $db->prepare("
                    SELECT id
                    FROM conceptos_movimiento
                    WHERE nombre = 'Salida por Limpieza' AND id_tenant = :id_tenant
                    LIMIT 1
                ");
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();
                $concepto = $sentence->fetch();

                if (!$concepto) {
                    throw new Exception("No existe el concepto de movimiento 'Salida por Limpieza'");
                }

                $observaciones_movimiento = 'Registro rápido de aseo - ' . count($ids_registros) . ' área(s)';

                $sentence = $db->prepare("
                    INSERT INTO movimientos_productos (
                        id,
                        id_tenant,
                        fecha_movimiento,
                        id_concepto_movimiento,
                        id_estado,
                        observaciones,
                        id_usuario_registro
                    ) VALUES (
                        :id,
                        :id_tenant,
                        NOW(),
                        :id_concepto_movimiento,
                        3, -- Aprobado
                        :observaciones,
                        :id_usuario
                    )
                ");

                $id_movimiento = Uuid::generar();
                $sentence->bindValue(':id', $id_movimiento);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindParam(':id_concepto_movimiento', $concepto['id']);
                $sentence->bindParam(':observaciones', $observaciones_movimiento);
                $sentence->bindParam(':id_usuario', $id_usuario_ejecutor);
                $sentence->execute();

                foreach ($consumo_consolidado as $id_producto => $item) {
                    $cantidad_a_descontar = round($item['cantidad'], 2);
                    $stock_disponible = $item['stock_actual'];

                    // Si no alcanza el stock se descuenta solo lo disponible y se avisa
                    if ($cantidad_a_descontar > $stock_disponible) {
                        $productos_ajustados[] = array(
                            'producto' => $item['nombre'],
                            'abreviatura' => $item['abreviatura'],
                            'solicitado' => $cantidad_a_descontar,
                            'disponible' => $stock_disponible,
                            'descontado' => max(0, $stock_disponible)
                        );
                        $cantidad_a_descontar = max(0, $stock_disponible);
                    }

                    if ($cantidad_a_descontar <= 0) {
                        error_log("Producto sin stock en registro rápido: " . $item['nombre']);
                        continue;
                    }

                    $sentence = $db->prepare("
                        INSERT INTO movimientos_productos_detalle (
                            id,
                            id_tenant,
                            id_movimiento,
                            id_producto,
                            cantidad,
                            stock_anterior,
                            precio_unitario
                        ) VALUES (
                            :id,
                            :id_tenant,
                            :id_movimiento,
                            :id_producto,
                            :cantidad,
                            :stock_anterior,
                            (SELECT precio_unitario FROM productos WHERE id = :id_producto2)
                        )
                    ");

                    $id_movimiento_detalle = Uuid::generar();
                    $sentence->bindValue(':id', $id_movimiento_detalle);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_movimiento', $id_movimiento);
                    $sentence->bindParam(':id_producto', $id_producto);
                    $sentence->bindParam(':cantidad', $cantidad_a_descontar);
                    $sentence->bindParam(':stock_anterior', $item['stock_actual']);
                    $sentence->bindParam(':id_producto2', $id_producto);
                    $sentence->execute();

                    // Actualizar stock del producto - NUNCA permitir negativos
                    $sentence = $db->prepare("
                        UPDATE productos
                        SET stock_anterior = stock_actual,
                            stock_actual = GREATEST(0, stock_actual - :cantidad),
                            id_ultimo_movimiento = :id_movimiento,
                            fecha_ultimo_movimiento = NOW()
                        WHERE id = :id_producto AND id_tenant = :id_tenant
                    ");
                    $sentence->bindParam(':cantidad', $cantidad_a_descontar);
                    $sentence->bindParam(':id_movimiento', $id_movimiento);
                    $sentence->bindParam(':id_producto', $id_producto);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->execute();

                    // Todos los consumos de ese producto apuntan al mismo detalle de movimiento
                    foreach ($consumos_por_producto[$id_producto] as $id_consumo) {
                        $sentence = $db->prepare("
                            UPDATE registros_limpieza_consumos
                            SET id_movimiento_inventario = :id_movimiento_detalle
                            WHERE id = :id_consumo AND id_tenant = :id_tenant
                        ");
                        $sentence->bindParam(':id_movimiento_detalle', $id_movimiento_detalle);
                        $sentence->bindParam(':id_consumo', $id_consumo);
                        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                        $sentence->execute();
                    }
                }
            }

            $db->commit();

            $response = array(
                'success' => true,
                'ids' => $ids_registros,
                'total_registros' => count($ids_registros),
                'id_movimiento' => $id_movimiento
            );

            if (count($productos_ajustados) > 0) {
                $response['productos_ajustados'] = $productos_ajustados;
            }

            Flight::json($response);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en RegistrosLimpieza::crearRapido: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Calcula, para un proceso y un conjunto de áreas, los elementos físicos y el
     * mobiliario a limpiar en cada área y los productos de limpieza que consumen.
     * Misma fórmula que getElementosParaProceso (cantidad_sugerida * cantidad en el
     * área) pero resuelta para todas las áreas en un solo juego de consultas.
     *
     * Devuelve un mapa id_area => ['elementos' => [], 'mobiliario' => [], 'productos' => []]
     */
    private static function calcularAreasProceso($db, $id_proceso, array $ids_areas)
    {
        $resultado = array();
        foreach ($ids_areas as $id_area) {
            $resultado[$id_area] = array('elementos' => [], 'mobiliario' => [], 'productos' => []);
        }

        if (count($ids_areas) == 0) {
            return $resultado;
        }

        $id_tenant = TenantContext::id();
        $placeholders = implode(',', array_fill(0, count($ids_areas), '?'));

        // Elementos físicos del proceso presentes en cada área
        $sentence = $db->prepare("
            SELECT DISTINCT
                efaf.id_area_fisica,
                ef.id,
                ef.nombre,
                efaf.cantidad
            FROM elementos_fisicos ef
            INNER JOIN elementos_fisicos_x_procesos_limpieza efpl ON ef.id = efpl.id_elemento_fisico
            INNER JOIN elementos_fisicos_x_areas_fisicas efaf ON ef.id = efaf.id_elemento_fisico
            WHERE efpl.id_tipo_proceso_limpieza = ?
                AND efpl.id_tenant = ?
                AND ef.id_tenant = ?
                AND efaf.id_area_fisica IN ($placeholders)
            ORDER BY ef.nombre
        ");
        $sentence->execute(array_merge([$id_proceso, $id_tenant, $id_tenant], $ids_areas));
        foreach ($sentence->fetchAll() as $fila) {
            $resultado[$fila['id_area_fisica']]['elementos'][] = array(
                'id' => $fila['id'],
                'nombre' => $fila['nombre'],
                'cantidad' => (float) $fila['cantidad']
            );
        }

        // Mobiliario del proceso presente en cada área
        $sentence = $db->prepare("
            SELECT DISTINCT
                pmxa.id_area,
                pm.id,
                p.nombre,
                pmxa.cantidad
            FROM productos_mobiliario pm
            INNER JOIN productos p ON pm.id_producto = p.id
            INNER JOIN productos_mobiliario_x_areas_fisicas pmxa ON pm.id = pmxa.id_producto_mobiliario
            INNER JOIN productos_mobiliario_x_procesos_limpieza pmpl ON pm.id = pmpl.id_producto_mobiliario
            WHERE pmpl.id_tipo_proceso_limpieza = ?
                AND p.activo = 1
                AND pm.id_tenant = ?
                AND pmxa.id_area IN ($placeholders)
            ORDER BY p.nombre
        ");
        $sentence->execute(array_merge([$id_proceso, $id_tenant], $ids_areas));
        foreach ($sentence->fetchAll() as $fila) {
            $resultado[$fila['id_area']]['mobiliario'][] = array(
                'id' => $fila['id'],
                'nombre' => $fila['nombre'],
                'cantidad' => (float) $fila['cantidad']
            );
        }

        $acumulado = array();

        // PARTE 1: productos que consumen los elementos físicos
        // cantidad = cantidad_sugerida * cantidad_del_elemento_en_el_area
        $sentence = $db->prepare("
            SELECT
                efaf.id_area_fisica,
                pl.id as id_producto_limpieza,
                p.id as id_producto,
                p.nombre,
                p.stock_actual,
                um.id as id_unidad_medida,
                um.abreviatura,
                efplp.cantidad_sugerida,
                efaf.cantidad as cantidad_elemento_area
            FROM elementos_fisicos_x_procesos_limpieza efpl
            INNER JOIN elementos_fisicos_x_procesos_limpieza_productos efplp
                ON efpl.id = efplp.id_elementos_fisicos_x_procesos_limpieza
            INNER JOIN productos_limpieza pl ON efplp.id_producto_limpieza = pl.id
            INNER JOIN productos p ON pl.id_producto = p.id
            INNER JOIN unidades_medida um ON p.id_unidad_medida = um.id
            INNER JOIN elementos_fisicos_x_areas_fisicas efaf
                ON efpl.id_elemento_fisico = efaf.id_elemento_fisico
            WHERE efpl.id_tipo_proceso_limpieza = ?
                AND p.activo = 1
                AND efpl.id_tenant = ?
                AND efaf.id_area_fisica IN ($placeholders)
        ");
        $sentence->execute(array_merge([$id_proceso, $id_tenant], $ids_areas));
        foreach ($sentence->fetchAll() as $fila) {
            self::acumularProducto(
                $acumulado,
                $fila['id_area_fisica'],
                $fila,
                $fila['cantidad_sugerida'] * $fila['cantidad_elemento_area']
            );
        }

        // PARTE 2: productos que consume el mobiliario
        // cantidad = cantidad_sugerida * cantidad_de_mobiliario_en_el_area
        $sentence = $db->prepare("
            SELECT
                pmxa.id_area,
                pl.id as id_producto_limpieza,
                p.id as id_producto,
                p.nombre,
                p.stock_actual,
                um.id as id_unidad_medida,
                um.abreviatura,
                pmplp.cantidad_sugerida,
                pmxa.cantidad as cantidad_mobiliario_area
            FROM productos_mobiliario_x_procesos_limpieza pmpl
            INNER JOIN productos_mobiliario pm ON pmpl.id_producto_mobiliario = pm.id
            INNER JOIN productos prod_mob ON pm.id_producto = prod_mob.id
            INNER JOIN productos_mobiliario_x_areas_fisicas pmxa ON pm.id = pmxa.id_producto_mobiliario
            INNER JOIN productos_mobiliario_x_procesos_limpieza_productos pmplp
                ON pmpl.id = pmplp.id_proceso_limpieza
            INNER JOIN productos_mobiliario_x_productos_limpieza pmxpl
                ON pmplp.id_productos_mobiliario_x_productos_limpieza = pmxpl.id
            INNER JOIN productos_limpieza pl ON pmxpl.id_producto_limpieza = pl.id
            INNER JOIN productos p ON pl.id_producto = p.id
            INNER JOIN unidades_medida um ON p.id_unidad_medida = um.id
            WHERE pmpl.id_tipo_proceso_limpieza = ?
                AND prod_mob.activo = 1
                AND p.activo = 1
                AND pmpl.id_tenant = ?
                AND pmxa.id_area IN ($placeholders)
        ");
        $sentence->execute(array_merge([$id_proceso, $id_tenant], $ids_areas));
        foreach ($sentence->fetchAll() as $fila) {
            self::acumularProducto(
                $acumulado,
                $fila['id_area'],
                $fila,
                $fila['cantidad_sugerida'] * $fila['cantidad_mobiliario_area']
            );
        }

        // Redondear y descartar los productos que no aportan consumo
        foreach ($acumulado as $id_area => $productos) {
            foreach ($productos as $producto) {
                $producto['cantidad'] = round($producto['cantidad'], 1);
                if ($producto['cantidad'] > 0) {
                    $resultado[$id_area]['productos'][] = $producto;
                }
            }
        }

        return $resultado;
    }

    /**
     * Suma la cantidad calculada de un producto de limpieza dentro del acumulado del área.
     */
    private static function acumularProducto(array &$acumulado, $id_area, array $fila, $cantidad)
    {
        $key = $fila['id_producto_limpieza'];

        if (!isset($acumulado[$id_area][$key])) {
            $acumulado[$id_area][$key] = array(
                'id_producto_limpieza' => $fila['id_producto_limpieza'],
                'id_producto' => $fila['id_producto'],
                'nombre' => $fila['nombre'],
                'stock_actual' => (float) $fila['stock_actual'],
                'id_unidad_medida' => $fila['id_unidad_medida'],
                'abreviatura' => $fila['abreviatura'],
                'cantidad' => 0
            );
        }

        $acumulado[$id_area][$key]['cantidad'] += $cantidad;
    }
}