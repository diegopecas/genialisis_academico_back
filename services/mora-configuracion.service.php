<?php
/**
 * Configuracion de mora por producto/servicio. Un producto tiene a lo sumo
 * una configuracion (unique por tenant + producto) y los dos tipos son
 * excluyentes: o recargo fijo o porcentaje.
 *
 * Estos parametros se copian a la cuenta por cobrar en el momento de crearla,
 * de modo que un cambio de tarifa nunca altera cuentas ya emitidas.
 */
class MoraConfiguracion
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    mc.id,
                    mc.id_producto_servicio,
                    mc.id_tipo_mora,
                    mc.valor_recargo,
                    mc.recargo_acumulable,
                    mc.porcentaje_mensual,
                    mc.activo,
                    mc.fecha_registro,
                    mc.id_usuario,
                    tm.codigo AS codigo_tipo_mora,
                    tm.nombre AS nombre_tipo_mora,
                    ps.nombre AS nombre_producto_servicio
                FROM mora_configuracion mc
                INNER JOIN tipos_mora tm ON mc.id_tipo_mora = tm.id
                INNER JOIN productos_servicios ps ON mc.id_producto_servicio = ps.id
                WHERE mc.id_tenant = :id_tenant
                ORDER BY ps.nombre
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en mora_configuracion getAll: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener la configuracion de mora'), 500);
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
                    mc.id_producto_servicio,
                    mc.id_tipo_mora,
                    mc.valor_recargo,
                    mc.recargo_acumulable,
                    mc.porcentaje_mensual,
                    mc.activo,
                    mc.fecha_registro,
                    mc.id_usuario,
                    tm.codigo AS codigo_tipo_mora,
                    tm.nombre AS nombre_tipo_mora,
                    ps.nombre AS nombre_producto_servicio
                FROM mora_configuracion mc
                INNER JOIN tipos_mora tm ON mc.id_tipo_mora = tm.id
                INNER JOIN productos_servicios ps ON mc.id_producto_servicio = ps.id
                WHERE mc.id = :id AND mc.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en mora_configuracion getById: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener la configuracion de mora'), 500);
        }
    }

    /**
     * Configuracion vigente de un producto. Devuelve arreglo vacio si el
     * producto no cobra mora.
     */
    public static function getByProducto($id_producto_servicio)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $config = self::obtenerVigente($db, $id_producto_servicio);
            Flight::json($config ? array($config) : array());
        } catch (Exception $e) {
            error_log('Error en mora_configuracion getByProducto: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener la configuracion de mora del producto'), 500);
        }
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            $error = self::validar($db, $data);
            if ($error !== null) {
                Flight::json(array('error' => $error), 400);
                return;
            }

            $valores = self::normalizarValores($db, $data);

            $sentence = $db->prepare("
                INSERT INTO mora_configuracion
                    (id, id_tenant, id_producto_servicio, id_tipo_mora, valor_recargo,
                     recargo_acumulable, porcentaje_mensual, activo, id_usuario)
                VALUES
                    (:id, :id_tenant, :id_producto_servicio, :id_tipo_mora, :valor_recargo,
                     :recargo_acumulable, :porcentaje_mensual, :activo, :id_usuario)
            ");

            $id = Uuid::generar();
            $activo = isset($data['activo']) ? (int) $data['activo'] : 1;
            $idUsuario = isset($data['id_usuario']) ? $data['id_usuario'] : null;

            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_producto_servicio', $data['id_producto_servicio']);
            $sentence->bindValue(':id_tipo_mora', $valores['id_tipo_mora'], PDO::PARAM_INT);
            $sentence->bindValue(':valor_recargo', $valores['valor_recargo']);
            $sentence->bindValue(':recargo_acumulable', $valores['recargo_acumulable'], PDO::PARAM_INT);
            $sentence->bindValue(':porcentaje_mensual', $valores['porcentaje_mensual']);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindParam(':id_usuario', $idUsuario);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en mora_configuracion new: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al crear la configuracion de mora'), 500);
        }
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            if (!isset($data['id'])) {
                Flight::json(array('error' => 'Falta el id de la configuracion'), 400);
                return;
            }

            $error = self::validar($db, $data);
            if ($error !== null) {
                Flight::json(array('error' => $error), 400);
                return;
            }

            $valores = self::normalizarValores($db, $data);

            $sentence = $db->prepare("
                UPDATE mora_configuracion SET
                    id_producto_servicio = :id_producto_servicio,
                    id_tipo_mora = :id_tipo_mora,
                    valor_recargo = :valor_recargo,
                    recargo_acumulable = :recargo_acumulable,
                    porcentaje_mensual = :porcentaje_mensual,
                    activo = :activo,
                    id_usuario = :id_usuario
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            $activo = isset($data['activo']) ? (int) $data['activo'] : 1;
            $idUsuario = isset($data['id_usuario']) ? $data['id_usuario'] : null;

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':id_producto_servicio', $data['id_producto_servicio']);
            $sentence->bindValue(':id_tipo_mora', $valores['id_tipo_mora'], PDO::PARAM_INT);
            $sentence->bindValue(':valor_recargo', $valores['valor_recargo']);
            $sentence->bindValue(':recargo_acumulable', $valores['recargo_acumulable'], PDO::PARAM_INT);
            $sentence->bindValue(':porcentaje_mensual', $valores['porcentaje_mensual']);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindParam(':id_usuario', $idUsuario);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() === 0) {
                Flight::json(array('error' => 'No se encontro la configuracion de mora indicada'), 404);
                return;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log('Error en mora_configuracion replace: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar la configuracion de mora'), 500);
        }
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM mora_configuracion WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en mora_configuracion delete: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al borrar la configuracion de mora'), 500);
        }
    }

    /**
     * Productos/servicios con su configuracion de mora (si la tienen), para la
     * pantalla de registro rapido. Devuelve TODOS los productos disponibles,
     * tengan o no mora, con los mismos campos de filtro que el listado de
     * productos: clasificacion, categoria, periodicidad y estado.
     */
    public static function getProductosConMora()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    ps.id AS id_producto_servicio,
                    ps.nombre,
                    ps.disponible,
                    ps.valor_sugerido,
                    ps.id_clasificacion_productos_servicios,
                    ps.id_categoria_productos_servicios,
                    ps.id_periodicidad_cobro,
                    cps.nombre AS nombre_clasificacion,
                    cat.nombre AS nombre_categoria,
                    pc.nombre AS nombre_periodicidad,
                    mc.id AS id_mora_configuracion,
                    mc.id_tipo_mora,
                    mc.valor_recargo,
                    mc.recargo_acumulable,
                    mc.porcentaje_mensual,
                    mc.activo AS mora_activa,
                    tm.codigo AS codigo_tipo_mora,
                    tm.nombre AS nombre_tipo_mora
                FROM productos_servicios ps
                LEFT JOIN mora_configuracion mc
                       ON mc.id_producto_servicio = ps.id AND mc.id_tenant = ps.id_tenant
                LEFT JOIN tipos_mora tm ON mc.id_tipo_mora = tm.id
                LEFT JOIN clasificacion_productos_servicios cps
                       ON ps.id_clasificacion_productos_servicios = cps.id
                LEFT JOIN categoria_productos_servicios cat
                       ON ps.id_categoria_productos_servicios = cat.id
                LEFT JOIN periodicidad_cobro pc ON ps.id_periodicidad_cobro = pc.id
                WHERE ps.id_tenant = :id_tenant
                ORDER BY ps.nombre
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en mora_configuracion getProductosConMora: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener los productos con su configuracion de mora'), 500);
        }
    }

    /**
     * Aplica o quita la configuracion de mora a varios productos de una sola
     * vez (registro rapido). Espera:
     *   productos: [id, id, ...]
     *   accion:    'aplicar' | 'quitar'
     *   y, cuando es 'aplicar', los mismos campos que new()/replace().
     *
     * Si el producto ya tenia configuracion se reemplaza; si no, se crea.
     * Recordar que esto solo afecta las cuentas por cobrar que se creen de
     * aqui en adelante: las ya emitidas conservan sus parametros.
     */
    public static function aplicarMasivo()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            $productos = isset($data['productos']) ? $data['productos'] : array();
            $accion = isset($data['accion']) ? $data['accion'] : 'aplicar';

            if (empty($productos) || !is_array($productos)) {
                Flight::json(array('error' => 'Debe indicar al menos un producto'), 400);
                return;
            }

            if ($accion === 'quitar') {
                $db->beginTransaction();
                $sentence = $db->prepare("
                    DELETE FROM mora_configuracion
                    WHERE id_producto_servicio = :id_producto_servicio AND id_tenant = :id_tenant
                ");

                $eliminados = 0;
                foreach ($productos as $idProducto) {
                    $sentence->bindValue(':id_producto_servicio', $idProducto);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->execute();
                    $eliminados += $sentence->rowCount();
                }
                $db->commit();

                Flight::json(array(
                    'success'   => true,
                    'accion'    => 'quitar',
                    'afectados' => $eliminados,
                    'message'   => $eliminados . ' producto(s) quedaron sin cobro de mora'
                ));
                return;
            }

            $error = self::validar($db, $data);
            if ($error !== null) {
                Flight::json(array('error' => $error), 400);
                return;
            }

            $valores = self::normalizarValores($db, $data);
            $idUsuario = isset($data['id_usuario']) ? $data['id_usuario'] : null;
            $activo = isset($data['activo']) ? (int) $data['activo'] : 1;

            $db->beginTransaction();

            $existe = $db->prepare("
                SELECT id FROM mora_configuracion
                WHERE id_producto_servicio = :id_producto_servicio AND id_tenant = :id_tenant
                LIMIT 1
            ");
            $actualizar = $db->prepare("
                UPDATE mora_configuracion SET
                    id_tipo_mora = :id_tipo_mora,
                    valor_recargo = :valor_recargo,
                    recargo_acumulable = :recargo_acumulable,
                    porcentaje_mensual = :porcentaje_mensual,
                    activo = :activo,
                    id_usuario = :id_usuario
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $insertar = $db->prepare("
                INSERT INTO mora_configuracion
                    (id, id_tenant, id_producto_servicio, id_tipo_mora, valor_recargo,
                     recargo_acumulable, porcentaje_mensual, activo, id_usuario)
                VALUES
                    (:id, :id_tenant, :id_producto_servicio, :id_tipo_mora, :valor_recargo,
                     :recargo_acumulable, :porcentaje_mensual, :activo, :id_usuario)
            ");

            $creados = 0;
            $actualizados = 0;

            foreach ($productos as $idProducto) {
                $existe->bindValue(':id_producto_servicio', $idProducto);
                $existe->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $existe->execute();
                $fila = $existe->fetch(PDO::FETCH_ASSOC);

                if ($fila) {
                    $actualizar->bindValue(':id_tipo_mora', $valores['id_tipo_mora'], PDO::PARAM_INT);
                    $actualizar->bindValue(':valor_recargo', $valores['valor_recargo']);
                    $actualizar->bindValue(':recargo_acumulable', $valores['recargo_acumulable'], PDO::PARAM_INT);
                    $actualizar->bindValue(':porcentaje_mensual', $valores['porcentaje_mensual']);
                    $actualizar->bindValue(':activo', $activo, PDO::PARAM_INT);
                    $actualizar->bindValue(':id_usuario', $idUsuario);
                    $actualizar->bindValue(':id', $fila['id']);
                    $actualizar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $actualizar->execute();
                    $actualizados++;
                } else {
                    $insertar->bindValue(':id', Uuid::generar());
                    $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $insertar->bindValue(':id_producto_servicio', $idProducto);
                    $insertar->bindValue(':id_tipo_mora', $valores['id_tipo_mora'], PDO::PARAM_INT);
                    $insertar->bindValue(':valor_recargo', $valores['valor_recargo']);
                    $insertar->bindValue(':recargo_acumulable', $valores['recargo_acumulable'], PDO::PARAM_INT);
                    $insertar->bindValue(':porcentaje_mensual', $valores['porcentaje_mensual']);
                    $insertar->bindValue(':activo', $activo, PDO::PARAM_INT);
                    $insertar->bindValue(':id_usuario', $idUsuario);
                    $insertar->execute();
                    $creados++;
                }
            }

            $db->commit();

            Flight::json(array(
                'success'      => true,
                'accion'       => 'aplicar',
                'creados'      => $creados,
                'actualizados' => $actualizados,
                'afectados'    => $creados + $actualizados,
                'message'      => ($creados + $actualizados) . ' producto(s) con mora configurada'
            ));
        } catch (Exception $e) {
            if (Flight::db()->inTransaction()) {
                Flight::db()->rollBack();
            }
            error_log('Error en mora_configuracion aplicarMasivo: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al aplicar la configuracion de mora'), 500);
        }
    }

    /**
     * Configuracion activa de un producto, o null. Uso interno (motor y
     * creacion de cuentas por cobrar), no responde HTTP.
     *
     * @param PDO    $db
     * @param string $id_producto_servicio
     * @return array|null
     */
    public static function obtenerVigente($db, $id_producto_servicio)
    {
        if (empty($id_producto_servicio)) {
            return null;
        }

        $sentence = $db->prepare("
            SELECT
                mc.id,
                mc.id_producto_servicio,
                mc.id_tipo_mora,
                mc.valor_recargo,
                mc.recargo_acumulable,
                mc.porcentaje_mensual,
                mc.activo,
                tm.codigo AS codigo_tipo_mora,
                tm.nombre AS nombre_tipo_mora
            FROM mora_configuracion mc
            INNER JOIN tipos_mora tm ON mc.id_tipo_mora = tm.id
            WHERE mc.id_producto_servicio = :id_producto_servicio
              AND mc.id_tenant = :id_tenant
              AND mc.activo = 1
              AND tm.activo = 1
            LIMIT 1
        ");
        $sentence->bindParam(':id_producto_servicio', $id_producto_servicio);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);

        return $fila ? $fila : null;
    }

    /**
     * Valida coherencia entre el tipo de mora y los valores enviados.
     *
     * @return string|null Mensaje de error, o null si esta bien
     */
    private static function validar($db, $data)
    {
        if (empty($data['id_producto_servicio'])) {
            return 'Debe indicar el producto o servicio';
        }
        if (empty($data['id_tipo_mora'])) {
            return 'Debe indicar el tipo de mora';
        }

        $codigo = TiposMora::codigoPorId($db, $data['id_tipo_mora']);
        if ($codigo === null) {
            return 'El tipo de mora indicado no existe';
        }

        if ($codigo === 'RECARGO_FIJO') {
            if (!isset($data['valor_recargo']) || (float) $data['valor_recargo'] <= 0) {
                return 'El recargo fijo debe ser mayor que cero';
            }
        }

        if ($codigo === 'PORCENTAJE') {
            if (!isset($data['porcentaje_mensual']) || (float) $data['porcentaje_mensual'] <= 0) {
                return 'El porcentaje mensual debe ser mayor que cero';
            }
        }

        return null;
    }

    /**
     * Deja en NULL los campos que no corresponden al tipo elegido, para que
     * no queden datos que confundan al motor.
     */
    private static function normalizarValores($db, $data)
    {
        $codigo = TiposMora::codigoPorId($db, $data['id_tipo_mora']);

        $valores = array(
            'id_tipo_mora'       => (int) $data['id_tipo_mora'],
            'valor_recargo'      => null,
            'recargo_acumulable' => 0,
            'porcentaje_mensual' => null
        );

        if ($codigo === 'RECARGO_FIJO') {
            $valores['valor_recargo'] = (float) $data['valor_recargo'];
            $valores['recargo_acumulable'] = isset($data['recargo_acumulable']) ? (int) $data['recargo_acumulable'] : 0;
        }

        if ($codigo === 'PORCENTAJE') {
            $valores['porcentaje_mensual'] = (float) $data['porcentaje_mensual'];
        }

        return $valores;
    }
}
