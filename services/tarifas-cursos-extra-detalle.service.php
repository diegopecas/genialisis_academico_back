<?php
/**
 * Detalle de las tarifas de cursos extracurriculares.
 *
 * La tarifa (tarifas_cursos_extra) es la cabecera: curso, anio y convenio.
 * Cada fila de este detalle es un concepto que se cobra en esa tarifa:
 * matricula, pension, valor unico o paquete de sesiones.
 *
 * El tipo de la fila no se guarda: es el tipo de cobro de su producto
 * (MATRICULA, PENSION o PAQUETE). Cualquier otro caso, incluido el producto
 * sin tipo de cobro, se trata como valor unico. Asi el tipo nunca queda
 * contradiciendo al producto.
 *
 * numero_sesiones es un dato de la fila, no decide el tipo: en un paquete son
 * las sesiones que se compran (obligatorias); en una pension, las sesiones
 * que incluye el mes (opcionales).
 */
class TarifasCursosExtraDetalle
{
    /**
     * Select comun: la fila con el nombre del producto y el tipo ya resuelto.
     * Lo usan los endpoints y los servicios que necesitan el detalle.
     */
    private static function sqlBase()
    {
        return "SELECT d.id, d.id_tarifa_curso_extra, d.id_producto_servicio, d.valor, d.cuotas,
        d.numero_sesiones, d.vigencia_dias, d.orden, d.activo,
        ps.nombre AS nombre_producto, ps.id_periodicidad_cobro,
        tcp.codigo AS codigo_tipo_cobro,
        CASE
            WHEN tcp.codigo = 'MATRICULA' THEN 'matricula'
            WHEN tcp.codigo = 'PENSION' THEN 'pension'
            WHEN tcp.codigo = 'PAQUETE' THEN 'paquete'
            ELSE 'unico'
        END AS tipo
        FROM tarifas_cursos_extra_detalle d
        INNER JOIN productos_servicios ps ON ps.id = d.id_producto_servicio AND ps.id_tenant = d.id_tenant
        LEFT JOIN tipos_cobro_producto tcp ON tcp.id = ps.id_tipo_cobro";
    }

    // ==================== ENDPOINTS ====================

    public static function getByTarifa($id_tarifa_curso_extra)
    {
        $db = Flight::db();
        Flight::json(self::listar($db, $id_tarifa_curso_extra, false));
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::sqlBase() . " WHERE d.id = :id AND d.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        $linea = self::normalizarLinea($data);

        $error = self::validarLinea($db, $linea);
        if ($error === null) {
            $error = self::validarTarifa($db, $data['id_tarifa_curso_extra'] ?? null);
        }
        if ($error === null && self::productoRepetido($db, $data['id_tarifa_curso_extra'], $linea['id_producto_servicio'], null)) {
            $error = 'Ese producto ya está en la tarifa.';
        }
        if ($error !== null) {
            Flight::json(array('error' => $error), 400);
            return;
        }

        $idNew = Uuid::generar();
        self::insertar($db, $idNew, $data['id_tarifa_curso_extra'], $linea);
        Flight::json(array('id' => $idNew));
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        $id = $data['id'] ?? null;

        $stmt = $db->prepare("SELECT id_tarifa_curso_extra FROM tarifas_cursos_extra_detalle
                              WHERE id = :id AND id_tenant = :id_tenant");
        $stmt->bindParam(':id', $id);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $actual = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$actual) {
            Flight::json(array('error' => 'No se encontró la fila de la tarifa.'), 404);
            return;
        }

        $linea = self::normalizarLinea($data);
        $error = self::validarLinea($db, $linea);
        if ($error === null && self::productoRepetido($db, $actual['id_tarifa_curso_extra'], $linea['id_producto_servicio'], $id)) {
            $error = 'Ese producto ya está en la tarifa.';
        }
        if ($error !== null) {
            Flight::json(array('error' => $error), 400);
            return;
        }

        self::actualizar($db, $id, $linea);
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM tarifas_cursos_extra_detalle WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    // ==================== USO DESDE OTROS SERVICIOS ====================

    /**
     * Filas de una tarifa, en el orden configurado.
     *
     * @param bool $soloActivos true para lo que se ofrece o se cobra; false
     *                          para la pantalla de configuracion, que tambien
     *                          muestra las inactivas.
     * @return array
     */
    public static function listar($db, $id_tarifa_curso_extra, $soloActivos = false)
    {
        $sql = self::sqlBase() . " WHERE d.id_tarifa_curso_extra = :id_tarifa AND d.id_tenant = :id_tenant";
        if ($soloActivos) {
            $sql .= " AND d.activo = 1";
        }
        $sql .= " ORDER BY d.orden, d.numero_sesiones, ps.nombre";

        $sentence = $db->prepare($sql);
        $sentence->bindParam(':id_tarifa', $id_tarifa_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Agrega la llave 'detalle' a cada tarifa de la lista. Una sola consulta
     * para todas, no una por tarifa.
     *
     * @return array La misma lista, cada fila con su detalle.
     */
    public static function adjuntar($db, $tarifas, $soloActivos = false)
    {
        if (empty($tarifas)) {
            return $tarifas;
        }

        $ids = array_values(array_unique(array_column($tarifas, 'id')));
        $marcas = implode(',', array_fill(0, count($ids), '?'));

        $sql = self::sqlBase() . " WHERE d.id_tarifa_curso_extra IN ($marcas) AND d.id_tenant = ?";
        if ($soloActivos) {
            $sql .= " AND d.activo = 1";
        }
        $sql .= " ORDER BY d.orden, d.numero_sesiones, ps.nombre";

        $sentence = $db->prepare($sql);
        $i = 1;
        foreach ($ids as $idTarifa) {
            $sentence->bindValue($i++, $idTarifa);
        }
        $sentence->bindValue($i, TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $porTarifa = array();
        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porTarifa[$fila['id_tarifa_curso_extra']][] = $fila;
        }

        foreach ($tarifas as &$tarifa) {
            $tarifa['detalle'] = isset($porTarifa[$tarifa['id']]) ? $porTarifa[$tarifa['id']] : array();
        }
        unset($tarifa);

        return $tarifas;
    }

    /**
     * Deja el detalle de la tarifa igual a la lista que llega: actualiza las
     * filas que traen id, crea las que no, y borra las que ya no vienen.
     * No abre transaccion: la abre quien guarda la cabecera, para que la
     * tarifa y su detalle queden completos o no quede nada.
     *
     * @return string|null Mensaje de error, o null si todo quedo guardado.
     */
    public static function sincronizar($db, $id_tarifa_curso_extra, $lineas)
    {
        if (!is_array($lineas)) {
            $lineas = array();
        }

        $normalizadas = array();
        $productos = array();
        foreach ($lineas as $i => $l) {
            $linea = self::normalizarLinea($l);
            $error = self::validarLinea($db, $linea);
            if ($error !== null) {
                return 'Fila ' . ($i + 1) . ': ' . $error;
            }
            if (in_array($linea['id_producto_servicio'], $productos, true)) {
                return 'Fila ' . ($i + 1) . ': el producto está repetido en la tarifa.';
            }
            $productos[] = $linea['id_producto_servicio'];
            $linea['id'] = !empty($l['id']) ? $l['id'] : null;
            $normalizadas[] = $linea;
        }

        // Las que ya no vienen se borran. Se hace primero para que el indice
        // unico por producto no choque si una fila cambio de producto.
        $idsQueQuedan = array_values(array_filter(array_column($normalizadas, 'id')));
        $sqlBorrar = "DELETE FROM tarifas_cursos_extra_detalle
                      WHERE id_tarifa_curso_extra = ? AND id_tenant = ?";
        if (!empty($idsQueQuedan)) {
            $sqlBorrar .= " AND id NOT IN (" . implode(',', array_fill(0, count($idsQueQuedan), '?')) . ")";
        }
        $stmtBorrar = $db->prepare($sqlBorrar);
        $stmtBorrar->bindValue(1, $id_tarifa_curso_extra);
        $stmtBorrar->bindValue(2, TenantContext::id(), PDO::PARAM_INT);
        $p = 3;
        foreach ($idsQueQuedan as $idQueda) {
            $stmtBorrar->bindValue($p++, $idQueda);
        }
        $stmtBorrar->execute();

        foreach ($normalizadas as $linea) {
            if ($linea['id']) {
                self::actualizar($db, $linea['id'], $linea, $id_tarifa_curso_extra);
            } else {
                self::insertar($db, Uuid::generar(), $id_tarifa_curso_extra, $linea);
            }
        }

        return null;
    }

    /**
     * Borra todo el detalle de una tarifa. Va antes de borrar la cabecera,
     * porque la llave foranea no deja borrarla con filas colgando.
     */
    public static function borrarPorTarifa($db, $id_tarifa_curso_extra)
    {
        $sentence = $db->prepare("DELETE FROM tarifas_cursos_extra_detalle
                                  WHERE id_tarifa_curso_extra = :id_tarifa AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_tarifa', $id_tarifa_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    // ==================== APOYO ====================

    private static function normalizarLinea($l)
    {
        $vacio = function ($v) {
            return $v === null || $v === '';
        };

        return array(
            'id_producto_servicio' => !$vacio($l['id_producto_servicio'] ?? null) ? $l['id_producto_servicio'] : null,
            'valor' => !$vacio($l['valor'] ?? null) ? (float) $l['valor'] : 0,
            'cuotas' => !$vacio($l['cuotas'] ?? null) ? (int) $l['cuotas'] : 1,
            'numero_sesiones' => !$vacio($l['numero_sesiones'] ?? null) ? (int) $l['numero_sesiones'] : null,
            'vigencia_dias' => !$vacio($l['vigencia_dias'] ?? null) ? (int) $l['vigencia_dias'] : null,
            'orden' => !$vacio($l['orden'] ?? null) ? (int) $l['orden'] : 0,
            'activo' => isset($l['activo']) ? ((int) $l['activo'] === 1 ? 1 : 0) : 1
        );
    }

    /**
     * @return string|null Mensaje de error, o null si la fila esta bien.
     */
    private static function validarLinea($db, $linea)
    {
        if (empty($linea['id_producto_servicio'])) {
            return 'Falta el producto.';
        }
        if ($linea['valor'] < 0) {
            return 'El valor no puede ser negativo.';
        }
        if ($linea['cuotas'] < 1) {
            return 'Las cuotas deben ser al menos 1.';
        }
        if ($linea['numero_sesiones'] !== null && $linea['numero_sesiones'] < 1) {
            return 'El número de sesiones debe ser mayor que cero.';
        }
        if ($linea['vigencia_dias'] !== null && $linea['vigencia_dias'] < 1) {
            return 'La vigencia debe ser de al menos un día.';
        }

        // El producto tiene que ser del tenant: el formulario podria mandar
        // cualquier id.
        $stmt = $db->prepare("SELECT ps.id, tcp.codigo AS codigo_tipo_cobro
                              FROM productos_servicios ps
                              LEFT JOIN tipos_cobro_producto tcp ON tcp.id = ps.id_tipo_cobro
                              WHERE ps.id = :id AND ps.id_tenant = :id_tenant");
        $stmt->bindValue(':id', $linea['id_producto_servicio']);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $producto = $stmt->fetch();
        if (!$producto) {
            return 'El producto no existe.';
        }

        // Un paquete sin sesiones no se puede consumir.
        if ($producto['codigo_tipo_cobro'] === 'PAQUETE' && $linea['numero_sesiones'] === null) {
            return 'El paquete debe tener el número de sesiones.';
        }

        return null;
    }

    private static function validarTarifa($db, $id_tarifa_curso_extra)
    {
        if (empty($id_tarifa_curso_extra)) {
            return 'Falta la tarifa.';
        }
        $stmt = $db->prepare("SELECT id FROM tarifas_cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
        $stmt->bindParam(':id', $id_tarifa_curso_extra);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ? null : 'La tarifa no existe.';
    }

    private static function productoRepetido($db, $id_tarifa_curso_extra, $id_producto_servicio, $idExcluir)
    {
        $sql = "SELECT id FROM tarifas_cursos_extra_detalle
                WHERE id_tarifa_curso_extra = :id_tarifa AND id_producto_servicio = :id_producto
                  AND id_tenant = :id_tenant";
        if ($idExcluir) {
            $sql .= " AND id <> :id_excluir";
        }
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_tarifa', $id_tarifa_curso_extra);
        $stmt->bindParam(':id_producto', $id_producto_servicio);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        if ($idExcluir) {
            $stmt->bindParam(':id_excluir', $idExcluir);
        }
        $stmt->execute();
        return (bool) $stmt->fetch();
    }

    private static function insertar($db, $id, $id_tarifa_curso_extra, $linea)
    {
        $sentence = $db->prepare("INSERT INTO tarifas_cursos_extra_detalle
            (id, id_tenant, id_tarifa_curso_extra, id_producto_servicio, valor, cuotas,
             numero_sesiones, vigencia_dias, orden, activo)
            VALUES (:id, :id_tenant, :id_tarifa, :id_producto, :valor, :cuotas,
             :numero_sesiones, :vigencia_dias, :orden, :activo)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tarifa', $id_tarifa_curso_extra);
        self::enlazarLinea($sentence, $linea);
        $sentence->execute();
    }

    /**
     * Actualiza la fila. Con $id_tarifa_curso_extra solo toca filas de esa
     * tarifa: desde la sincronizacion no se puede mover una fila ajena.
     */
    private static function actualizar($db, $id, $linea, $id_tarifa_curso_extra = null)
    {
        $sql = "UPDATE tarifas_cursos_extra_detalle SET
            id_producto_servicio = :id_producto, valor = :valor, cuotas = :cuotas,
            numero_sesiones = :numero_sesiones, vigencia_dias = :vigencia_dias,
            orden = :orden, activo = :activo
            WHERE id = :id AND id_tenant = :id_tenant";
        if ($id_tarifa_curso_extra !== null) {
            $sql .= " AND id_tarifa_curso_extra = :id_tarifa";
        }
        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        if ($id_tarifa_curso_extra !== null) {
            $sentence->bindValue(':id_tarifa', $id_tarifa_curso_extra);
        }
        self::enlazarLinea($sentence, $linea);
        $sentence->execute();
    }

    private static function enlazarLinea($sentence, $linea)
    {
        $sentence->bindValue(':id_producto', $linea['id_producto_servicio']);
        $sentence->bindValue(':valor', $linea['valor']);
        $sentence->bindValue(':cuotas', $linea['cuotas'], PDO::PARAM_INT);
        $sentence->bindValue(':numero_sesiones', $linea['numero_sesiones'],
            $linea['numero_sesiones'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $sentence->bindValue(':vigencia_dias', $linea['vigencia_dias'],
            $linea['vigencia_dias'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $sentence->bindValue(':orden', $linea['orden'], PDO::PARAM_INT);
        $sentence->bindValue(':activo', $linea['activo'], PDO::PARAM_INT);
    }
}
