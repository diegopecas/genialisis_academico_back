<?php
/**
 * Líneas de producto de un contrato de matrícula.
 * Guarda lo que efectivamente se escogió en el contrato: cuál pensión según la
 * jornada y qué otros productos entraron, cada uno con su descuento y recargo
 * propios. De aquí salen los totales derivados de la cabecera
 * (valor_matricula, valor_pension, valor_otros).
 */
class ContratosMatriculaProductos
{
    /**
     * SELECT común de las líneas con los datos del producto y del tipo.
     */
    private static function selectBase()
    {
        return "
            SELECT cmp.id, cmp.id_contrato_matricula, cmp.id_producto_servicio,
                   cmp.id_tipo_cobro, cmp.valor_base, cmp.descuento, cmp.recargo,
                   cmp.valor_final, cmp.orden,
                   ps.nombre AS nombre_producto,
                   ps.id_periodicidad_cobro,
                   pc.nombre AS nombre_periodicidad,
                   COALESCE(tc.codigo, 'OTRO') AS codigo_tipo_cobro,
                   COALESCE(tc.nombre, 'Otro') AS nombre_tipo_cobro
            FROM contratos_matricula_productos cmp
            INNER JOIN productos_servicios ps ON cmp.id_producto_servicio = ps.id
            LEFT JOIN tipos_cobro_producto tc ON cmp.id_tipo_cobro = tc.id
            LEFT JOIN periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
        ";
    }

    public static function getByContrato($idContrato)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare(self::selectBase() . "
            WHERE cmp.id_contrato_matricula = :id_contrato AND cmp.id_tenant = :id_tenant
            ORDER BY cmp.orden
        ");
        $sentence->bindParam(':id_contrato', $idContrato);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare(self::selectBase() . "
            WHERE cmp.id = :id AND cmp.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }

    /**
     * Guarda de un golpe las líneas de un contrato (reemplaza las existentes)
     * y recalcula los totales derivados de la cabecera.
     * Espera: id_contrato_matricula y lineas[] con id_producto_servicio,
     * valor_base, descuento, recargo, valor_final y orden. El tipo de cobro se
     * copia del producto y queda como foto del momento de la firma.
     */
    public static function guardarLineas()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        $db = Flight::db();
        try {
            $id_contrato = Flight::request()->data['id_contrato_matricula'];
            $lineas = Flight::request()->data['lineas'];
            $lineas = is_array($lineas) ? $lineas : [];

            if (empty($id_contrato)) {
                Flight::json(array('error' => 'No se recibio el contrato'), 400);
                return;
            }

            $productosEnviados = [];
            foreach ($lineas as $linea) {
                if (empty($linea['id_producto_servicio'])) {
                    Flight::json(array('error' => 'Hay una linea sin producto'), 400);
                    return;
                }
                if (in_array($linea['id_producto_servicio'], $productosEnviados)) {
                    Flight::json(array('error' => 'El producto ' . $linea['id_producto_servicio'] . ' esta repetido en el contrato'), 400);
                    return;
                }
                $productosEnviados[] = $linea['id_producto_servicio'];
            }

            $db->beginTransaction();

            $borrar = $db->prepare("DELETE FROM contratos_matricula_productos
                                    WHERE id_contrato_matricula = :id_contrato AND id_tenant = :id_tenant");
            $borrar->bindParam(':id_contrato', $id_contrato);
            $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrar->execute();

            // El tipo se toma del producto en el momento de guardar
            $tipoDelProducto = $db->prepare("SELECT id_tipo_cobro FROM productos_servicios
                                             WHERE id = :id_producto AND id_tenant = :id_tenant");

            $insertar = $db->prepare("INSERT INTO contratos_matricula_productos
                (id, id_tenant, id_contrato_matricula, id_producto_servicio, id_tipo_cobro,
                 valor_base, descuento, recargo, valor_final, orden)
                VALUES (:id, :id_tenant, :id_contrato, :id_producto_servicio, :id_tipo_cobro,
                 :valor_base, :descuento, :recargo, :valor_final, :orden)");

            $ids = [];
            $orden = 1;
            foreach ($lineas as $linea) {
                $id_producto_servicio = $linea['id_producto_servicio'];

                $tipoDelProducto->bindParam(':id_producto', $id_producto_servicio);
                $tipoDelProducto->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $tipoDelProducto->execute();
                $filaTipo = $tipoDelProducto->fetch(PDO::FETCH_ASSOC);
                $id_tipo_cobro = $filaTipo ? $filaTipo['id_tipo_cobro'] : null;

                $valor_base = isset($linea['valor_base']) ? $linea['valor_base'] : 0;
                $descuento = isset($linea['descuento']) ? $linea['descuento'] : 0;
                $recargo = isset($linea['recargo']) ? $linea['recargo'] : 0;
                $valor_final = isset($linea['valor_final']) ? $linea['valor_final'] : 0;
                $ordenLinea = isset($linea['orden']) ? (int)$linea['orden'] : $orden;

                $idNew = Uuid::generar();
                $insertar->bindValue(':id', $idNew);
                $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $insertar->bindParam(':id_contrato', $id_contrato);
                $insertar->bindParam(':id_producto_servicio', $id_producto_servicio);
                $insertar->bindValue(':id_tipo_cobro', $id_tipo_cobro);
                $insertar->bindParam(':valor_base', $valor_base);
                $insertar->bindParam(':descuento', $descuento);
                $insertar->bindParam(':recargo', $recargo);
                $insertar->bindParam(':valor_final', $valor_final);
                $insertar->bindValue(':orden', $ordenLinea, PDO::PARAM_INT);
                $insertar->execute();

                $ids[] = $idNew;
                $orden++;
            }

            $totales = self::recalcularTotalesContrato($db, $id_contrato);

            $db->commit();

            Flight::json(array(
                'success' => true,
                'id_contrato' => $id_contrato,
                'ids' => $ids,
                'total' => count($ids),
                'totales' => $totales
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en ContratosMatriculaProductos::guardarLineas: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function eliminarByContrato($idContrato)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        try {
            $db = Flight::db();

            $sentence = $db->prepare("DELETE FROM contratos_matricula_productos
                                      WHERE id_contrato_matricula = :id_contrato AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_contrato', $idContrato);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('success' => true, 'id_contrato' => $idContrato));
        } catch (Exception $e) {
            error_log("Error en ContratosMatriculaProductos::eliminarByContrato: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Recalcula los totales derivados de la cabecera del contrato a partir del
     * calendario de contratos_matricula_valores, clasificando cada cuota por el
     * tipo de cobro de la línea a la que pertenece su producto.
     * Si la línea no tiene tipo se usa el del producto, y si el producto
     * tampoco lo tiene se cae a la periodicidad (1 Anual = matrícula,
     * 2 Mensual = pensión), que es como se hacía antes.
     * Devuelve el arreglo de totales para que quien lo llame pueda responderlos.
     */
    public static function recalcularTotalesContrato($db, $idContrato)
    {
        // Codigo del tipo de cobro de la cuota: primero la foto de la linea,
        // despues el producto, y de ultimas la periodicidad
        $codigoTipo = "COALESCE(tcl.codigo, tcp.codigo, CASE WHEN ps.id_periodicidad_cobro = 1 THEN 'MATRICULA' ELSE 'PENSION' END)";

        $sentence = $db->prepare("
            SELECT
                SUM(CASE WHEN $codigoTipo = 'MATRICULA' THEN cmv.valor ELSE 0 END) AS total_matricula,
                SUM(CASE WHEN $codigoTipo = 'PENSION' THEN cmv.valor ELSE 0 END) AS total_pension,
                SUM(CASE WHEN $codigoTipo = 'OTRO' THEN cmv.valor ELSE 0 END) AS total_otros,
                COUNT(CASE WHEN $codigoTipo = 'PENSION' THEN 1 END) AS numero_cuotas
            FROM contratos_matricula_valores cmv
            INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
            LEFT JOIN contratos_matricula_productos cmp
                   ON cmp.id_contrato_matricula = cmv.id_contrato_matricula
                  AND cmp.id_producto_servicio = cmv.id_producto_servicio
                  AND cmp.id_tenant = cmv.id_tenant
            LEFT JOIN tipos_cobro_producto tcl ON cmp.id_tipo_cobro = tcl.id
            LEFT JOIN tipos_cobro_producto tcp ON ps.id_tipo_cobro = tcp.id
            WHERE cmv.id_contrato_matricula = :id_contrato AND cmv.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_contrato', $idContrato);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);

        $totalMatricula = $fila ? (float)($fila['total_matricula'] ?? 0) : 0;
        $totalPension = $fila ? (float)($fila['total_pension'] ?? 0) : 0;
        $totalOtros = $fila ? (float)($fila['total_otros'] ?? 0) : 0;
        $numeroCuotas = $fila ? (int)($fila['numero_cuotas'] ?? 0) : 0;
        $valorTotal = $totalMatricula + $totalPension + $totalOtros;

        // Solo se tocan los totales, NO las fechas (esas las maneja el usuario)
        $sentenceUpdate = $db->prepare("
            UPDATE contratos_matricula SET
                valor_matricula = :valor_matricula,
                valor_pension = :valor_pension,
                valor_otros = :valor_otros,
                numero_cuotas = :numero_cuotas,
                valor_total = :valor_total
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentenceUpdate->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentenceUpdate->bindValue(':valor_matricula', $totalMatricula);
        $sentenceUpdate->bindValue(':valor_pension', $totalPension);
        $sentenceUpdate->bindValue(':valor_otros', $totalOtros);
        $sentenceUpdate->bindValue(':numero_cuotas', $numeroCuotas, PDO::PARAM_INT);
        $sentenceUpdate->bindValue(':valor_total', $valorTotal);
        $sentenceUpdate->bindParam(':id', $idContrato);
        $sentenceUpdate->execute();

        return array(
            'total_matricula' => $totalMatricula,
            'total_pension' => $totalPension,
            'total_otros' => $totalOtros,
            'numero_cuotas' => $numeroCuotas,
            'valor_total' => $valorTotal
        );
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("DELETE FROM contratos_matricula_productos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}
