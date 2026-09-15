<?php

/**
 * Configuracion de los certificados por tenant.
 *
 * Una fila por clave de certificado. Define si el certificado se le ofrece al
 * acudiente en el portal de padres (modo automatico) o si solo lo expide el
 * jardin (modo manual), y que regla financiera debe cumplir el acudiente para
 * poder descargarlo.
 *
 * El paz y salvo ignora el campo `regla`: su regla es fija (saldo en cero) y
 * vive en CertificadosReglas.
 */
class CertificadosConfiguracion
{
    /** Claves validas. Cualquier otra se rechaza al guardar. */
    public static $CLAVES = [
        'paz_y_salvo',
        'constancia_estudio',
        'constancia_anio_cursado',
        'pagos_estudiante',
        'pagos_acudiente'
    ];

    /** Nombres para mostrar, para no repetirlos en cada front. */
    public static $NOMBRES = [
        'paz_y_salvo' => 'Paz y Salvo',
        'constancia_estudio' => 'Constancia de Estudio',
        'constancia_anio_cursado' => 'Constancia de Año Cursado',
        'pagos_estudiante' => 'Certificado de Pagos del Estudiante',
        'pagos_acudiente' => 'Certificado de Pagos del Acudiente'
    ];

    private static $MODOS = ['automatico', 'manual'];
    private static $REGLAS = ['libre', 'al_dia', 'al_dia_productos'];

    /**
     * Listado completo. Si a un tenant le falta alguna clave (por ejemplo
     * porque se agrego un certificado nuevo despues de su instalacion), se
     * devuelve igual con los valores por defecto para que la pantalla la
     * muestre y se pueda guardar.
     *
     * GET /certificados-configuracion
     */
    public static function getAll()
    {
        JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cc.id, cc.clave_certificado, cc.modo, cc.regla,
                       cc.agrupar_por_mes, cc.mensaje_no_cumple, cc.activo,
                       (SELECT COUNT(*)
                          FROM certificados_configuracion_productos ccp
                         WHERE ccp.id_certificado_config = cc.id) AS total_productos
                FROM certificados_configuracion cc
                WHERE cc.id_tenant = :id_tenant
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $existentes = [];
            foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $existentes[$fila['clave_certificado']] = $fila;
            }

            $resultado = [];
            foreach (self::$CLAVES as $clave) {
                if (isset($existentes[$clave])) {
                    $fila = $existentes[$clave];
                    $fila['nombre'] = self::$NOMBRES[$clave];
                    $fila['regla_fija'] = ($clave === 'paz_y_salvo') ? 1 : 0;
                    $fila['es_de_pagos'] = self::esDePagos($clave) ? 1 : 0;
                    $resultado[] = $fila;
                    continue;
                }

                $resultado[] = [
                    'id' => null,
                    'clave_certificado' => $clave,
                    'nombre' => self::$NOMBRES[$clave],
                    'modo' => 'manual',
                    'regla' => 'libre',
                    'agrupar_por_mes' => 0,
                    'mensaje_no_cumple' => null,
                    'activo' => 1,
                    'total_productos' => 0,
                    'regla_fija' => ($clave === 'paz_y_salvo') ? 1 : 0,
                    'es_de_pagos' => self::esDePagos($clave) ? 1 : 0
                ];
            }

            Flight::json($resultado);
        } catch (Exception $e) {
            error_log('Error en CertificadosConfiguracion::getAll: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener la configuración de certificados'], 500);
        }
    }

    /**
     * Configuracion de una clave, con sus productos.
     * Se consulta por clave y no por id porque la fila puede no existir todavia.
     *
     * GET /certificados-configuracion/clave/:clave
     */
    public static function getByClave($clave)
    {
        JWTService::requerirAutenticacion();

        try {
            if (!in_array($clave, self::$CLAVES, true)) {
                Flight::json(['error' => true, 'message' => 'Certificado no válido'], 400);
                return;
            }

            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT id, clave_certificado, modo, regla, agrupar_por_mes,
                       mensaje_no_cumple, activo
                FROM certificados_configuracion
                WHERE id_tenant = :id_tenant AND clave_certificado = :clave
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':clave', $clave);
            $sentence->execute();

            $configuracion = $sentence->fetch(PDO::FETCH_ASSOC);

            if (!$configuracion) {
                $configuracion = [
                    'id' => null,
                    'clave_certificado' => $clave,
                    'modo' => 'manual',
                    'regla' => 'libre',
                    'agrupar_por_mes' => 0,
                    'mensaje_no_cumple' => null,
                    'activo' => 1
                ];
            }

            $configuracion['nombre'] = self::$NOMBRES[$clave];
            $configuracion['regla_fija'] = ($clave === 'paz_y_salvo') ? 1 : 0;
            $configuracion['es_de_pagos'] = self::esDePagos($clave) ? 1 : 0;
            $configuracion['productos'] = $configuracion['id']
                ? self::productosDe($db, $configuracion['id'])
                : [];

            Flight::json($configuracion);
        } catch (Exception $e) {
            error_log('Error en CertificadosConfiguracion::getByClave: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener la configuración'], 500);
        }
    }

    /**
     * Guarda la configuracion de una clave. Crea la fila si no existia.
     * Los productos llegan como arreglo de id_producto_servicio y se
     * reemplazan completos.
     *
     * PUT /certificados-configuracion
     */
    public static function replace()
    {
        JWTService::requerirAutenticacion();

        $db = Flight::db();

        try {
            $datos = Flight::request()->data;
            $clave = isset($datos['clave_certificado']) ? $datos['clave_certificado'] : null;
            $modo = isset($datos['modo']) ? $datos['modo'] : 'manual';
            $regla = isset($datos['regla']) ? $datos['regla'] : 'libre';
            $mensaje = isset($datos['mensaje_no_cumple']) ? $datos['mensaje_no_cumple'] : null;
            $activo = isset($datos['activo']) ? (int) $datos['activo'] : 1;
            $agruparPorMes = isset($datos['agrupar_por_mes']) ? (int) $datos['agrupar_por_mes'] : 0;
            $productos = isset($datos['productos']) && is_array($datos['productos']) ? $datos['productos'] : [];

            if (!in_array($clave, self::$CLAVES, true)) {
                Flight::json(['error' => true, 'message' => 'Certificado no válido'], 400);
                return;
            }
            if (!in_array($modo, self::$MODOS, true)) {
                Flight::json(['error' => true, 'message' => 'Modo no válido'], 400);
                return;
            }
            if (!in_array($regla, self::$REGLAS, true)) {
                Flight::json(['error' => true, 'message' => 'Regla no válida'], 400);
                return;
            }

            // El paz y salvo no admite regla configurable: se normaliza para que
            // la fila no quede diciendo algo que el evaluador no respeta.
            if ($clave === 'paz_y_salvo') {
                $regla = 'libre';
                $productos = [];
            }

            // La agrupacion por mes solo tiene sentido en los certificados de
            // pagos; en los demas no hay tabla que agrupar.
            if (!self::esDePagos($clave)) {
                $agruparPorMes = 0;
            }

            if ($regla === 'al_dia_productos' && count($productos) === 0) {
                Flight::json([
                    'error' => true,
                    'message' => 'Debe seleccionar al menos un producto para la regla por productos específicos'
                ], 400);
                return;
            }

            $db->beginTransaction();

            $sentence = $db->prepare("
                SELECT id FROM certificados_configuracion
                WHERE id_tenant = :id_tenant AND clave_certificado = :clave
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':clave', $clave);
            $sentence->execute();
            $idConfiguracion = $sentence->fetchColumn();

            if ($idConfiguracion) {
                $sentence = $db->prepare("
                    UPDATE certificados_configuracion
                    SET modo = :modo, regla = :regla, agrupar_por_mes = :agrupar,
                        mensaje_no_cumple = :mensaje, activo = :activo
                    WHERE id = :id AND id_tenant = :id_tenant
                ");
                $sentence->bindParam(':id', $idConfiguracion);
            } else {
                $idConfiguracion = Uuid::generar();
                $sentence = $db->prepare("
                    INSERT INTO certificados_configuracion
                        (id, id_tenant, clave_certificado, modo, regla, agrupar_por_mes,
                         mensaje_no_cumple, activo)
                    VALUES (:id, :id_tenant, :clave, :modo, :regla, :agrupar, :mensaje, :activo)
                ");
                $sentence->bindParam(':id', $idConfiguracion);
                $sentence->bindParam(':clave', $clave);
            }

            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':modo', $modo);
            $sentence->bindParam(':regla', $regla);
            $sentence->bindValue(':agrupar', $agruparPorMes, PDO::PARAM_INT);
            $sentence->bindParam(':mensaje', $mensaje);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->execute();

            // Los productos se reemplazan completos: es mas simple que
            // diferenciar altas y bajas y el volumen es minimo.
            $borrar = $db->prepare("
                DELETE FROM certificados_configuracion_productos
                WHERE id_certificado_config = :id AND id_tenant = :id_tenant
            ");
            $borrar->bindParam(':id', $idConfiguracion);
            $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrar->execute();

            if ($regla === 'al_dia_productos') {
                $insertar = $db->prepare("
                    INSERT INTO certificados_configuracion_productos
                        (id, id_tenant, id_certificado_config, id_producto_servicio)
                    VALUES (:id, :id_tenant, :id_config, :id_producto)
                ");
                foreach ($productos as $idProducto) {
                    $idFila = Uuid::generar();
                    $insertar->bindParam(':id', $idFila);
                    $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $insertar->bindParam(':id_config', $idConfiguracion);
                    $insertar->bindParam(':id_producto', $idProducto);
                    $insertar->execute();
                }
            }

            $db->commit();

            Flight::json(['id' => $idConfiguracion]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en CertificadosConfiguracion::replace: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al guardar la configuración'], 500);
        }
    }

    /** Los certificados de pagos son los unicos con tabla y rango de fechas. */
    public static function esDePagos($clave)
    {
        return $clave === 'pagos_estudiante' || $clave === 'pagos_acudiente';
    }

    /**
     * Productos exigidos por una configuracion. Uso interno y del getByClave.
     */
    private static function productosDe($db, $idConfiguracion)
    {
        $sentence = $db->prepare("
            SELECT ccp.id_producto_servicio, ps.nombre
            FROM certificados_configuracion_productos ccp
            INNER JOIN productos_servicios ps ON ps.id = ccp.id_producto_servicio
            WHERE ccp.id_certificado_config = :id AND ccp.id_tenant = :id_tenant
            ORDER BY ps.nombre
        ");
        $sentence->bindParam(':id', $idConfiguracion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }
}
