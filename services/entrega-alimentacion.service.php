<?php
class EntregaAlimentacion
{
    /**
     * Trae las cuentas del horario para la fecha dada:
     * - Diarias (periodicidad = 3): cpc.fecha = fecha exacta
     * - Mensuales (periodicidad = 2): mismo año/mes, solo estudiantes presentes ese día
     * Body: { fecha, id_horario }
     */
    public static function getPorFecha()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $request = Flight::request();
            $data = $request->data->getData();
            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            $fecha      = isset($data['fecha'])      ? $data['fecha']           : date('Y-m-d');
            $id_horario = isset($data['id_horario']) ? (int)$data['id_horario'] : null;

            if (!$id_horario) {
                Flight::json(['error' => 'Falta id_horario'], 400);
                return;
            }

            $fecha_obj = new DateTime($fecha);
            $anio      = $fecha_obj->format('Y');
            $mes       = $fecha_obj->format('m');

            $db = Flight::db();

            $selectBase = "SELECT
                        cpc.id AS id_cuenta,
                        cpc.id_persona,
                        TRIM(REGEXP_REPLACE(
                            CONCAT(
                                COALESCE(p.primer_nombre,''),' ',
                                COALESCE(p.segundo_nombre,''),' ',
                                COALESCE(p.primer_apellido,''),' ',
                                COALESCE(p.segundo_apellido,'')
                            ), '\\\\s+', ' '
                        )) AS nombre_estudiante,
                        g.id    AS id_grupo,
                        g.nombre AS nombre_grupo,
                        g.orden  AS orden_grupo,
                        ps.id    AS id_producto,
                        ps.nombre AS nombre_producto,
                        COALESCE(ps.detalles,'') AS detalle_producto,
                        ha.id    AS id_horario,
                        ha.nombre AS nombre_horario,
                        cpc.valor,
                        COALESCE(ea.estado, 0)          AS estado_entrega,
                        ea.id                           AS id_entrega,
                        ea.fecha_hora_entrega,
                        ea.id_usuario_entrega,
                        ea.fecha_hora_anulacion,
                        ea.id_usuario_anulacion,
                        ea.id_menu_programado,
                        ea.id_menu_servido,
                        ea.id_movimiento_productos,
                        CASE WHEN ae.id IS NOT NULL THEN 1 ELSE 0 END AS presente,
                        TIME(ae.fecha_ingreso) AS hora_ingreso
                    FROM cuentas_por_cobrar cpc
                    INNER JOIN personas p ON cpc.id_persona = p.id
                    INNER JOIN estudiantes e ON e.id_persona = p.id
                    INNER JOIN estudiantes_x_grupos exg ON exg.id_estudiante = e.id AND exg.activo = 1
                    INNER JOIN grupos g ON exg.id_grupo = g.id
                    INNER JOIN productos_servicios ps ON cpc.id_producto_servicio = ps.id
                    INNER JOIN periodicidad_cobro pc ON ps.id_periodicidad_cobro = pc.id
                    INNER JOIN horarios_alimentacion ha ON cpc.id_horario_alimentacion = ha.id
                    LEFT JOIN entregas_alimentacion ea ON ea.id_cuenta_por_cobrar = cpc.id
                    LEFT JOIN asistencia_estudiantes ae
                        ON ae.id_estudiante = e.id
                        AND DATE(ae.fecha_ingreso) = :fecha_ae";

            // Diarios: fecha exacta
            $sqlDiarios = $selectBase . "
                    WHERE cpc.fecha = :fecha_diaria
                      AND cpc.id_horario_alimentacion = :id_horario_d
                      AND cpc.anulado = 0
                      AND pc.id = 3";

            // Mensuales: mismo año/mes, solo presentes
            $sqlMensuales = $selectBase . "
                    WHERE YEAR(cpc.fecha) = :anio
                      AND MONTH(cpc.fecha) = :mes
                      AND cpc.id_horario_alimentacion = :id_horario_m
                      AND cpc.anulado = 0
                      AND pc.id = 2
                      AND ae.id IS NOT NULL";

            $stmtD = $db->prepare($sqlDiarios);
            $stmtD->bindValue(':fecha_ae',     $fecha);
            $stmtD->bindValue(':fecha_diaria', $fecha);
            $stmtD->bindValue(':id_horario_d', $id_horario);
            $stmtD->execute();
            $diarios = $stmtD->fetchAll(PDO::FETCH_ASSOC);

            $stmtM = $db->prepare($sqlMensuales);
            $stmtM->bindValue(':fecha_ae',     $fecha);
            $stmtM->bindValue(':anio',         $anio);
            $stmtM->bindValue(':mes',          $mes);
            $stmtM->bindValue(':id_horario_m', $id_horario);
            $stmtM->execute();
            $mensuales = $stmtM->fetchAll(PDO::FETCH_ASSOC);

            $rows = array_merge($diarios, $mensuales);

            usort($rows, function($a, $b) {
                if ($a['orden_grupo'] !== $b['orden_grupo']) return $a['orden_grupo'] - $b['orden_grupo'];
                if ($a['nombre_producto'] !== $b['nombre_producto']) return strcmp($a['nombre_producto'], $b['nombre_producto']);
                return strcmp($a['nombre_estudiante'], $b['nombre_estudiante']);
            });

            foreach ($rows as &$r) {
                $r['id_cuenta']        = (string)$r['id_cuenta'];
                $r['id_persona']       = (string)$r['id_persona'];
                $r['id_grupo']         = (string)$r['id_grupo'];
                $r['id_producto']      = (string)$r['id_producto'];
                $r['id_horario']       = (string)$r['id_horario'];
                $r['estado_entrega']   = (int)$r['estado_entrega'];
                $r['presente']         = (int)$r['presente'];
                $r['valor']            = (float)$r['valor'];
                $r['id_entrega']         = $r['id_entrega']         ? (string)$r['id_entrega']         : null;
                $r['id_menu_programado'] = $r['id_menu_programado'] ? (string)$r['id_menu_programado'] : null;
                $r['id_menu_servido']    = $r['id_menu_servido']    ? (string)$r['id_menu_servido']    : null;
                $r['id_movimiento_productos'] = $r['id_movimiento_productos'] ? (string)$r['id_movimiento_productos'] : null;
            }

            Flight::json($rows);

        } catch (Exception $e) {
            error_log("Error en EntregaAlimentacion::getPorFecha: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Registra entregas en batch (estado = 1).
     * Body: { ids_cuentas: string[], id_horario: int, id_usuario: int,
     *         cuentas_menus: [{ id_cuenta, id_menu_programado, id_menu_servido }] }
     */
    public static function registrarBatch()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $request = Flight::request();
            $data = $request->data->getData();
            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            $ids_cuentas  = isset($data['ids_cuentas'])  ? $data['ids_cuentas']  : [];
            $id_usuario   = isset($data['id_usuario'])   ? (int)$data['id_usuario'] : null;
            $id_horario   = isset($data['id_horario'])   ? (int)$data['id_horario'] : null;
            $cuentasMenus = isset($data['cuentas_menus']) ? $data['cuentas_menus'] : [];

            if (empty($ids_cuentas)) {
                Flight::json(['error' => 'Faltan ids_cuentas'], 400);
                return;
            }

            // Construir mapa id_cuenta → menús
            $mapaMenus = [];
            foreach ($cuentasMenus as $cm) {
                $mapaMenus[(int)$cm['id_cuenta']] = $cm;
            }

            $db  = Flight::db();
            $now = date('Y-m-d H:i:s');

            $sql = "INSERT INTO entregas_alimentacion
                        (id_cuenta_por_cobrar, id_horario_alimentacion, estado, fecha_hora_entrega,
                         id_usuario_entrega, id_menu_programado, id_menu_servido)
                    VALUES (:id_cuenta, :id_horario, 1, :now, :id_usuario, :menu_prog, :menu_serv)
                    ON DUPLICATE KEY UPDATE
                        estado = 1,
                        fecha_hora_entrega = :now2,
                        id_usuario_entrega = :id_usuario2,
                        id_menu_programado = :menu_prog2,
                        id_menu_servido = :menu_serv2,
                        id_usuario_anulacion = NULL,
                        fecha_hora_anulacion = NULL";

            $stmt = $db->prepare($sql);
            foreach ($ids_cuentas as $id_cuenta) {
                $id_cuenta_int = (int)$id_cuenta;
                $menu_prog = isset($mapaMenus[$id_cuenta_int]['id_menu_programado']) && $mapaMenus[$id_cuenta_int]['id_menu_programado']
                    ? (int)$mapaMenus[$id_cuenta_int]['id_menu_programado'] : null;
                $menu_serv = isset($mapaMenus[$id_cuenta_int]['id_menu_servido']) && $mapaMenus[$id_cuenta_int]['id_menu_servido']
                    ? (int)$mapaMenus[$id_cuenta_int]['id_menu_servido'] : null;

                $stmt->bindValue(':id_cuenta',   $id_cuenta_int);
                $stmt->bindValue(':id_horario',  $id_horario);
                $stmt->bindValue(':now',         $now);
                $stmt->bindValue(':id_usuario',  $id_usuario);
                $stmt->bindValue(':menu_prog',   $menu_prog, $menu_prog ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->bindValue(':menu_serv',   $menu_serv, $menu_serv ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->bindValue(':now2',        $now);
                $stmt->bindValue(':id_usuario2', $id_usuario);
                $stmt->bindValue(':menu_prog2',  $menu_prog, $menu_prog ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->bindValue(':menu_serv2',  $menu_serv, $menu_serv ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->execute();
            }

            Flight::json(['success' => true, 'registradas' => count($ids_cuentas)]);

        } catch (Exception $e) {
            error_log("Error en EntregaAlimentacion::registrarBatch: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Anula entregas en batch (estado = 2) y anula las cuentas_por_cobrar.
     * Body: { ids_cuentas: string[], id_horario: int, id_usuario: int }
     */
    public static function anularBatch()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $request = Flight::request();
            $data = $request->data->getData();
            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            $ids_cuentas = isset($data['ids_cuentas']) ? $data['ids_cuentas'] : [];
            $id_usuario  = isset($data['id_usuario'])  ? (int)$data['id_usuario']  : null;
            $id_horario  = isset($data['id_horario'])  ? (int)$data['id_horario']  : null;

            if (empty($ids_cuentas)) {
                Flight::json(['error' => 'Faltan ids_cuentas'], 400);
                return;
            }

            $db  = Flight::db();
            $now = date('Y-m-d H:i:s');

            $sqlEntrega = "INSERT INTO entregas_alimentacion (id_cuenta_por_cobrar, id_horario_alimentacion, estado, fecha_hora_anulacion, id_usuario_anulacion)
                           VALUES (:id_cuenta, :id_horario, 2, :now, :id_usuario)
                           ON DUPLICATE KEY UPDATE
                               estado = 2,
                               fecha_hora_anulacion = :now2,
                               id_usuario_anulacion = :id_usuario2,
                               fecha_hora_entrega = NULL,
                               id_usuario_entrega = NULL";

            $sqlCuenta = "UPDATE cuentas_por_cobrar
                          SET anulado = 1,
                              fecha_anulacion = :now,
                              id_usuario_anulacion = :id_usuario
                          WHERE id = :id_cuenta";

            $stmtEntrega = $db->prepare($sqlEntrega);
            $stmtCuenta  = $db->prepare($sqlCuenta);

            foreach ($ids_cuentas as $id_cuenta) {
                $stmtEntrega->bindValue(':id_cuenta',   (int)$id_cuenta);
                $stmtEntrega->bindValue(':id_horario',  $id_horario);
                $stmtEntrega->bindValue(':now',         $now);
                $stmtEntrega->bindValue(':id_usuario',  $id_usuario);
                $stmtEntrega->bindValue(':now2',        $now);
                $stmtEntrega->bindValue(':id_usuario2', $id_usuario);
                $stmtEntrega->execute();

                $stmtCuenta->bindValue(':now',        $now);
                $stmtCuenta->bindValue(':id_usuario', $id_usuario);
                $stmtCuenta->bindValue(':id_cuenta',  (int)$id_cuenta);
                $stmtCuenta->execute();
            }

            Flight::json(['success' => true, 'anuladas' => count($ids_cuentas)]);

        } catch (Exception $e) {
            error_log("Error en EntregaAlimentacion::anularBatch: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Calcula los ingredientes teóricos del inventario para un conjunto de cuentas entregadas.
     * Recorre: cuentas → productos_servicios → menu_x_productos_servicios → menu_x_items
     *          → items_menu_ingredientes → productos_alimentacion → productos
     * Agrupa por id_producto sumando cantidad × número de entregas del mismo menú.
     * Body: { ids_cuentas: string[] }
     */
    public static function calcularInventario()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $request = Flight::request();
            $data = $request->data->getData();
            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            $ids_cuentas = isset($data['ids_cuentas']) ? $data['ids_cuentas'] : [];

            if (empty($ids_cuentas)) {
                Flight::json(['error' => 'Faltan ids_cuentas'], 400);
                return;
            }

            $db = Flight::db();

            // Contar cuántas cuentas hay por producto_servicio (= cuántas entregas de ese menú)
            $placeholders = implode(',', array_fill(0, count($ids_cuentas), '?'));

            $sqlConteo = "SELECT cpc.id_producto_servicio, COUNT(*) AS total_entregas
                          FROM cuentas_por_cobrar cpc
                          WHERE cpc.id IN ($placeholders)
                          GROUP BY cpc.id_producto_servicio";

            $stmtConteo = $db->prepare($sqlConteo);
            $stmtConteo->execute(array_map('intval', $ids_cuentas));
            $conteos = $stmtConteo->fetchAll(PDO::FETCH_ASSOC);

            // Para cada producto_servicio, buscar ingredientes via el menú
            $ingredientesTotales = [];

            foreach ($conteos as $conteo) {
                $id_ps          = (int)$conteo['id_producto_servicio'];
                $total_entregas = (int)$conteo['total_entregas'];

                $sqlIngredientes = "SELECT
                        pa.id_producto,
                        p.nombre AS nombre_producto,
                        um.nombre AS nombre_unidad,
                        um.abreviatura AS abreviatura_unidad,
                        p.stock_actual,
                        SUM(imi.cantidad) AS cantidad_por_porcion
                    FROM menu_x_productos_servicios mxps
                    INNER JOIN menu_x_items mxi ON mxi.id_menu = mxps.id_menu
                    INNER JOIN items_menu_ingredientes imi ON imi.id_item_menu = mxi.id_item_menu
                    INNER JOIN productos_alimentacion pa ON pa.id = imi.id_producto_alimentacion
                    INNER JOIN productos p ON p.id = pa.id_producto
                    LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
                    WHERE mxps.id_producto_servicio = :id_ps
                    GROUP BY pa.id_producto, p.nombre, um.nombre, um.abreviatura, p.stock_actual";

                $stmtIng = $db->prepare($sqlIngredientes);
                $stmtIng->bindValue(':id_ps', $id_ps);
                $stmtIng->execute();
                $ingredientes = $stmtIng->fetchAll(PDO::FETCH_ASSOC);

                foreach ($ingredientes as $ing) {
                    $id_prod = (int)$ing['id_producto'];
                    $cantidad_total = floatval($ing['cantidad_por_porcion']) * $total_entregas;

                    if (isset($ingredientesTotales[$id_prod])) {
                        $ingredientesTotales[$id_prod]['cantidad_teorica'] += $cantidad_total;
                    } else {
                        $ingredientesTotales[$id_prod] = [
                            'id_producto'       => (string)$id_prod,
                            'nombre_producto'   => $ing['nombre_producto'],
                            'nombre_unidad'     => $ing['nombre_unidad'],
                            'abreviatura_unidad' => $ing['abreviatura_unidad'],
                            'stock_actual'      => floatval($ing['stock_actual']),
                            'cantidad_teorica'  => $cantidad_total,
                            'cantidad_real'     => $cantidad_total  // inicia igual, el usuario puede editar
                        ];
                    }
                }
            }

            Flight::json(array_values($ingredientesTotales));

        } catch (Exception $e) {
            error_log("Error en EntregaAlimentacion::calcularInventario: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crea el movimiento de salida de inventario registrado y lo asocia a las entregas.
     * Body: {
     *   ids_cuentas: string[],
     *   id_horario: int,
     *   id_concepto_movimiento: int,
     *   id_usuario: int,
     *   observaciones: string,
     *   detalle: [{ id_producto, cantidad_teorica, cantidad_real, precio_unitario,
     *               id_menu_programado, id_menu_servido, id_unidad_medida }]
     * }
     */
    public static function registrarConInventario()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $request = Flight::request();
            $data = $request->data->getData();
            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            $ids_cuentas            = isset($data['ids_cuentas'])            ? $data['ids_cuentas']               : [];
            $id_horario             = isset($data['id_horario'])             ? (int)$data['id_horario']           : null;
            $id_concepto_movimiento = isset($data['id_concepto_movimiento']) ? (int)$data['id_concepto_movimiento'] : null;
            $id_usuario             = isset($data['id_usuario'])             ? (int)$data['id_usuario']           : null;
            $observaciones          = isset($data['observaciones'])          ? $data['observaciones']             : '';
            $detalle                = isset($data['detalle'])                ? $data['detalle']                   : [];

            if (empty($ids_cuentas) || !$id_concepto_movimiento || empty($detalle)) {
                Flight::json(['error' => 'Faltan parámetros requeridos'], 400);
                return;
            }

            $db  = Flight::db();
            $now = date('Y-m-d H:i:s');

            $db->beginTransaction();

            // 1. Crear movimiento de salida en estado 3 (registrado)
            $stmtMov = $db->prepare("INSERT INTO movimientos_productos
                        (fecha_movimiento, id_concepto_movimiento, observaciones, id_usuario_registro, fecha_registro, id_estado)
                       VALUES (:fecha, :id_concepto, :obs, :id_usuario, :now, 3)");
            $stmtMov->bindValue(':fecha',      $now);
            $stmtMov->bindValue(':id_concepto', $id_concepto_movimiento);
            $stmtMov->bindValue(':obs',         $observaciones);
            $stmtMov->bindValue(':id_usuario',  $id_usuario);
            $stmtMov->bindValue(':now',         $now);
            $stmtMov->execute();
            $id_movimiento = $db->lastInsertId();

            // 2. Insertar detalle + actualizar stock + guardar comparativa
            $stmtDet = $db->prepare("INSERT INTO movimientos_productos_detalle
                            (id_movimiento, id_producto, cantidad, stock_anterior, precio_unitario)
                           VALUES (:id_mov, :id_prod, :cantidad, :stock_ant, :precio)");

            $stmtStock = $db->prepare("UPDATE productos
                         SET stock_actual = stock_actual - :cantidad,
                             stock_anterior = stock_actual,
                             id_ultimo_movimiento = :id_mov,
                             fecha_ultimo_movimiento = :now
                         WHERE id = :id_prod");

            $stmtComp = $db->prepare("INSERT INTO entrega_alimentacion_inventario
                            (id_movimiento_productos, id_producto, cantidad_teorica, cantidad_real, id_unidad_medida)
                        VALUES (:id_mov, :id_prod, :teorica, :real, :id_unidad)");

            foreach ($detalle as $item) {
                $id_producto      = (int)$item['id_producto'];
                $cantidad_real    = floatval($item['cantidad_real']);
                $cantidad_teorica = floatval($item['cantidad_teorica']);
                $precio_unitario  = floatval($item['precio_unitario'] ?? 0);

                // Obtener stock anterior e id_unidad_medida real del producto
                $stmtSA = $db->prepare("SELECT stock_actual, id_unidad_medida FROM productos WHERE id = :id");
                $stmtSA->bindValue(':id', $id_producto);
                $stmtSA->execute();
                $prodRow       = $stmtSA->fetch(PDO::FETCH_ASSOC);
                $stock_anterior = floatval($prodRow['stock_actual'] ?? 0);
                $id_unidad      = $prodRow['id_unidad_medida'] ? (int)$prodRow['id_unidad_medida'] : null;

                $stmtDet->bindValue(':id_mov',    $id_movimiento);
                $stmtDet->bindValue(':id_prod',   $id_producto);
                $stmtDet->bindValue(':cantidad',  $cantidad_real);
                $stmtDet->bindValue(':stock_ant', $stock_anterior);
                $stmtDet->bindValue(':precio',    $precio_unitario);
                $stmtDet->execute();

                // Solo actualizar stock si cantidad_real > 0
                if ($cantidad_real > 0) {
                    $stmtStock->bindValue(':cantidad', $cantidad_real);
                    $stmtStock->bindValue(':id_mov',   $id_movimiento);
                    $stmtStock->bindValue(':now',      $now);
                    $stmtStock->bindValue(':id_prod',  $id_producto);
                    $stmtStock->execute();
                }

                $stmtComp->bindValue(':id_mov',    $id_movimiento);
                $stmtComp->bindValue(':id_prod',   $id_producto);
                $stmtComp->bindValue(':teorica',   $cantidad_teorica);
                $stmtComp->bindValue(':real',      $cantidad_real);
                $stmtComp->bindValue(':id_unidad', $id_unidad, $id_unidad ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmtComp->execute();
            }

            // 3. Asociar el movimiento a las entregas existentes
            $stmtEnt = $db->prepare("INSERT INTO entregas_alimentacion
                            (id_cuenta_por_cobrar, id_horario_alimentacion, estado, fecha_hora_entrega,
                             id_usuario_entrega, id_movimiento_productos)
                           VALUES (:id_cuenta, :id_horario, 1, :now, :id_usuario, :id_mov)
                           ON DUPLICATE KEY UPDATE
                               id_movimiento_productos = :id_mov2");

            foreach ($ids_cuentas as $id_cuenta) {
                $stmtEnt->bindValue(':id_cuenta',  (int)$id_cuenta);
                $stmtEnt->bindValue(':id_horario', $id_horario);
                $stmtEnt->bindValue(':now',        $now);
                $stmtEnt->bindValue(':id_usuario', $id_usuario);
                $stmtEnt->bindValue(':id_mov',     $id_movimiento);
                $stmtEnt->bindValue(':id_mov2',    $id_movimiento);
                $stmtEnt->execute();
            }

            $db->commit();

            Flight::json([
                'success'          => true,
                'id_movimiento'    => $id_movimiento,
                'entregas'         => count($ids_cuentas),
                'productos_salida' => count($detalle)
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en EntregaAlimentacion::registrarConInventario: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Devuelve los menús que tocan hoy según la minuta, con sus ingredientes completos.
     * Usa la misma lógica del frontend: semana = semana del mes (1-5), día = día de la semana (1=Lun..6=Sáb).
     * Si no hay minuta para hoy, devuelve todos los menús activos.
     * Query param: fecha=YYYY-MM-DD
     * Response: { semana, dia, menus: [{ id_menu, nombre_menu, id_clasificacion_menu, nombre_clasificacion,
     *             productos_servicios: [id_producto_servicio], ingredientes: [{ id_producto, nombre_producto,
     *             nombre_unidad, abreviatura_unidad, stock_actual, cantidad_por_porcion }] }] }
     */
    public static function getMenusDelDia()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $fecha = Flight::request()->query['fecha'] ?? date('Y-m-d');

            $fechaObj    = new DateTime($fecha);
            $diaMes      = (int)$fechaObj->format('j');
            $jsDia       = (int)$fechaObj->format('N'); // 1=Lun, 7=Dom
            $diaSemana   = $jsDia <= 6 ? $jsDia : 0;   // 0 = domingo (sin minuta)

            // Calcular semana del mes (misma lógica que el frontend)
            $primerDiaMes    = new DateTime($fechaObj->format('Y-m-01'));
            $jsPrimero       = (int)$primerDiaMes->format('N'); // 1=Lun
            $offset          = $jsPrimero - 1;
            $semanaCalculada = (int)ceil(($diaMes + $offset) / 7);
            $semanaMes       = min($semanaCalculada, 5);

            $db = Flight::db();

            // IDs de menús que tocan hoy según la minuta
            $stmtMinuta = $db->prepare("
                SELECT mm.id_menu
                FROM menu_minutas mm
                INNER JOIN menus m ON m.id = mm.id_menu AND m.activo = 1
                WHERE mm.semana = :semana AND mm.dia = :dia
            ");
            $stmtMinuta->bindValue(':semana', $semanaMes);
            $stmtMinuta->bindValue(':dia',    $diaSemana);
            $stmtMinuta->execute();
            $idsMinuta = $stmtMinuta->fetchAll(PDO::FETCH_COLUMN);
            $sinMinuta = empty($idsMinuta);
            $idsMinutaSet = array_flip(array_map('strval', $idsMinuta));

            // Traer TODOS los menús activos
            $stmtAll = $db->prepare("
                SELECT m.id AS id_menu, m.nombre AS nombre_menu,
                       m.id_clasificacion_menu, cm.nombre AS nombre_clasificacion
                FROM menus m
                LEFT JOIN clasificacion_menus cm ON cm.id = m.id_clasificacion_menu
                WHERE m.activo = 1
                ORDER BY m.nombre
            ");
            $stmtAll->execute();
            $todosMenus = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

            // Para cada menú traer productos_servicios e ingredientes
            $sqlPS = "SELECT mxps.id_producto_servicio
                      FROM menu_x_productos_servicios mxps
                      WHERE mxps.id_menu = :id_menu";

            $sqlIngredientes = "SELECT
                        pa.id_producto,
                        p.nombre  AS nombre_producto,
                        um.nombre AS nombre_unidad,
                        um.abreviatura AS abreviatura_unidad,
                        p.stock_actual,
                        SUM(imi.cantidad) AS cantidad_por_porcion
                    FROM menu_x_items mxi
                    INNER JOIN items_menu_ingredientes imi ON imi.id_item_menu = mxi.id_item_menu
                    INNER JOIN productos_alimentacion pa ON pa.id = imi.id_producto_alimentacion
                    INNER JOIN productos p ON p.id = pa.id_producto
                    LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
                    WHERE mxi.id_menu = :id_menu
                    GROUP BY pa.id_producto, p.nombre, um.nombre, um.abreviatura, p.stock_actual
                    ORDER BY p.nombre";

            $stmtPS  = $db->prepare($sqlPS);
            $stmtIng = $db->prepare($sqlIngredientes);

            foreach ($todosMenus as &$menu) {
                $id_menu = (int)$menu['id_menu'];

                $stmtPS->bindValue(':id_menu', $id_menu);
                $stmtPS->execute();
                $ps = $stmtPS->fetchAll(PDO::FETCH_COLUMN);
                $menu['productos_servicios'] = array_map('strval', $ps);

                $stmtIng->bindValue(':id_menu', $id_menu);
                $stmtIng->execute();
                $ings = $stmtIng->fetchAll(PDO::FETCH_ASSOC);
                foreach ($ings as &$ing) {
                    $ing['id_producto']         = (string)$ing['id_producto'];
                    $ing['stock_actual']         = (float)$ing['stock_actual'];
                    $ing['cantidad_por_porcion'] = (float)$ing['cantidad_por_porcion'];
                }
                $menu['ingredientes']   = $ings;
                $menu['id_menu']        = (string)$id_menu;
                $menu['es_programado']  = isset($idsMinutaSet[(string)$id_menu]) ? true : false;
            }

            Flight::json([
                'semana'     => $semanaMes,
                'dia'        => $diaSemana,
                'sin_minuta' => $sinMinuta,
                'menus'      => $todosMenus
            ]);

        } catch (Exception $e) {
            error_log("Error en EntregaAlimentacion::getMenusDelDia: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Trae las entregas ya marcadas (estado=1) de una fecha/horario,
     * indicando si ya tienen movimiento de inventario registrado.
     * Body: { fecha, id_horario }
     */
    public static function getEntregadasParaInventario()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $request = Flight::request();
            $data = $request->data->getData();
            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            $fecha      = isset($data['fecha'])      ? $data['fecha']           : date('Y-m-d');
            $id_horario = isset($data['id_horario']) ? (int)$data['id_horario'] : null;

            if (!$id_horario) {
                Flight::json(['error' => 'Falta id_horario'], 400);
                return;
            }

            $fecha_obj = new DateTime($fecha);
            $anio = $fecha_obj->format('Y');
            $mes  = $fecha_obj->format('m');

            $db = Flight::db();

            $selectBase = "SELECT
                        cpc.id AS id_cuenta,
                        cpc.id_persona,
                        TRIM(REGEXP_REPLACE(
                            CONCAT(
                                COALESCE(p.primer_nombre,''),' ',
                                COALESCE(p.segundo_nombre,''),' ',
                                COALESCE(p.primer_apellido,''),' ',
                                COALESCE(p.segundo_apellido,'')
                            ), '\\\\s+', ' '
                        )) AS nombre_estudiante,
                        g.nombre AS nombre_grupo,
                        g.orden  AS orden_grupo,
                        ps.id    AS id_producto,
                        ps.nombre AS nombre_producto,
                        ha.nombre AS nombre_horario,
                        cpc.valor,
                        ea.id                      AS id_entrega,
                        ea.fecha_hora_entrega,
                        ea.id_movimiento_productos,
                        ea.id_menu_servido,
                        ea.id_menu_programado,
                        mp.fecha_movimiento         AS fecha_movimiento_inventario
                    FROM cuentas_por_cobrar cpc
                    INNER JOIN personas p ON cpc.id_persona = p.id
                    INNER JOIN estudiantes e ON e.id_persona = p.id
                    INNER JOIN estudiantes_x_grupos exg ON exg.id_estudiante = e.id AND exg.activo = 1
                    INNER JOIN grupos g ON exg.id_grupo = g.id
                    INNER JOIN productos_servicios ps ON cpc.id_producto_servicio = ps.id
                    INNER JOIN periodicidad_cobro pc ON ps.id_periodicidad_cobro = pc.id
                    INNER JOIN horarios_alimentacion ha ON cpc.id_horario_alimentacion = ha.id
                    INNER JOIN entregas_alimentacion ea ON ea.id_cuenta_por_cobrar = cpc.id AND ea.estado = 1
                    LEFT JOIN movimientos_productos mp ON mp.id = ea.id_movimiento_productos";

            // Diarios
            $sqlDiarios = $selectBase . "
                    WHERE cpc.fecha = :fecha
                      AND cpc.id_horario_alimentacion = :id_horario
                      AND cpc.anulado = 0
                      AND pc.id = 3
                    ORDER BY g.orden, ps.nombre, p.primer_nombre";

            // Mensuales presentes
            $sqlMensuales = $selectBase . "
                    LEFT JOIN asistencia_estudiantes ae
                        ON ae.id_estudiante = e.id AND DATE(ae.fecha_ingreso) = :fecha2
                    WHERE YEAR(cpc.fecha) = :anio
                      AND MONTH(cpc.fecha) = :mes
                      AND cpc.id_horario_alimentacion = :id_horario_m
                      AND cpc.anulado = 0
                      AND pc.id = 2
                    ORDER BY g.orden, ps.nombre, p.primer_nombre";

            $stmtD = $db->prepare($sqlDiarios);
            $stmtD->bindValue(':fecha',      $fecha);
            $stmtD->bindValue(':id_horario', $id_horario);
            $stmtD->execute();
            $diarios = $stmtD->fetchAll(PDO::FETCH_ASSOC);

            $stmtM = $db->prepare($sqlMensuales);
            $stmtM->bindValue(':fecha2',     $fecha);
            $stmtM->bindValue(':anio',       $anio);
            $stmtM->bindValue(':mes',        $mes);
            $stmtM->bindValue(':id_horario_m', $id_horario);
            $stmtM->execute();
            $mensuales = $stmtM->fetchAll(PDO::FETCH_ASSOC);

            $rows = array_merge($diarios, $mensuales);

            foreach ($rows as &$r) {
                $r['id_cuenta']               = (string)$r['id_cuenta'];
                $r['id_producto']             = (string)$r['id_producto'];
                $r['id_entrega']              = (string)$r['id_entrega'];
                $r['id_movimiento_productos'] = $r['id_movimiento_productos'] ? (string)$r['id_movimiento_productos'] : null;
                $r['id_menu_servido']         = $r['id_menu_servido']         ? (string)$r['id_menu_servido']         : null;
                $r['id_menu_programado']      = $r['id_menu_programado']      ? (string)$r['id_menu_programado']      : null;
                $r['tiene_inventario']        = $r['id_movimiento_productos'] !== null;
                $r['valor']                   = (float)$r['valor'];
            }

            // Agrupar por producto
            $grupos = [];
            foreach ($rows as $r) {
                $id_ps = $r['id_producto'];
                if (!isset($grupos[$id_ps])) {
                    $grupos[$id_ps] = [
                        'id_producto'    => $id_ps,
                        'nombre_producto' => $r['nombre_producto'],
                        'registros'      => []
                    ];
                }
                $grupos[$id_ps]['registros'][] = $r;
            }

            // Estadísticas por grupo
            foreach ($grupos as &$g) {
                $g['total']            = count($g['registros']);
                $g['con_inventario']   = count(array_filter($g['registros'], fn($r) => $r['tiene_inventario']));
                $g['sin_inventario']   = $g['total'] - $g['con_inventario'];
            }

            Flight::json(array_values($grupos));

        } catch (Exception $e) {
            error_log("Error en EntregaAlimentacion::getEntregadasParaInventario: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Trae los movimientos de inventario ya registrados para una fecha/horario.
     * Body: { fecha, id_horario }
     */
    public static function getMovimientosDelDia()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $request = Flight::request();
            $data = $request->data->getData();
            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            $fecha      = isset($data['fecha'])      ? $data['fecha']           : date('Y-m-d');
            $id_horario = isset($data['id_horario']) ? (int)$data['id_horario'] : null;

            $db = Flight::db();

            $sql = "SELECT DISTINCT
                        mp.id AS id_movimiento,
                        mp.fecha_movimiento,
                        cm.nombre AS nombre_concepto,
                        mp.observaciones,
                        COUNT(DISTINCT mpd.id) AS total_productos,
                        GROUP_CONCAT(DISTINCT p.nombre ORDER BY p.nombre SEPARATOR ', ') AS productos_nombres
                    FROM entregas_alimentacion ea
                    INNER JOIN movimientos_productos mp ON mp.id = ea.id_movimiento_productos
                    INNER JOIN cuentas_por_cobrar cpc ON cpc.id = ea.id_cuenta_por_cobrar
                    LEFT JOIN conceptos_movimiento cm ON cm.id = mp.id_concepto_movimiento
                    LEFT JOIN movimientos_productos_detalle mpd ON mpd.id_movimiento = mp.id
                    LEFT JOIN productos p ON p.id = mpd.id_producto
                    WHERE ea.id_horario_alimentacion = :id_horario
                      AND DATE(ea.fecha_hora_entrega) = :fecha
                    GROUP BY mp.id, mp.fecha_movimiento, cm.nombre, mp.observaciones
                    ORDER BY mp.fecha_movimiento DESC";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_horario', $id_horario);
            $stmt->bindValue(':fecha',      $fecha);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Traer detalle de cada movimiento
            $sqlDetalle = "SELECT
                        mpd.id_producto,
                        p.nombre AS nombre_producto,
                        mpd.cantidad,
                        mpd.stock_anterior,
                        um.abreviatura AS abreviatura_unidad,
                        eai.cantidad_teorica,
                        eai.cantidad_real
                    FROM movimientos_productos_detalle mpd
                    INNER JOIN productos p ON p.id = mpd.id_producto
                    LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
                    LEFT JOIN entrega_alimentacion_inventario eai
                        ON eai.id_movimiento_productos = mpd.id_movimiento
                        AND eai.id_producto = mpd.id_producto
                    WHERE mpd.id_movimiento = :id_movimiento
                    ORDER BY p.nombre";

            $stmtDet = $db->prepare($sqlDetalle);

            foreach ($rows as &$r) {
                $r['id_movimiento']   = (string)$r['id_movimiento'];
                $r['total_productos'] = (int)$r['total_productos'];

                $stmtDet->bindValue(':id_movimiento', (int)$r['id_movimiento']);
                $stmtDet->execute();
                $detalle = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
                foreach ($detalle as &$d) {
                    $d['cantidad']        = (float)$d['cantidad'];
                    $d['stock_anterior']  = (float)$d['stock_anterior'];
                    $d['cantidad_teorica'] = $d['cantidad_teorica'] !== null ? (float)$d['cantidad_teorica'] : null;
                    $d['cantidad_real']   = $d['cantidad_real'] !== null ? (float)$d['cantidad_real'] : null;
                }
                $r['detalle'] = $detalle;
            }

            Flight::json($rows);

        } catch (Exception $e) {
            error_log("Error en EntregaAlimentacion::getMovimientosDelDia: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}