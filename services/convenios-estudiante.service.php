<?php
class ConveniosEstudiante
{
    public static function getByEstudiante($id_estudiante)
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT ce.*, c.nombre AS nombre_convenio, c.descripcion AS descripcion_convenio,
                       ps.nombre AS nombre_producto_servicio, ps.valor_sugerido,
                       CONCAT(pu.primer_nombre, ' ', COALESCE(pu.primer_apellido, '')) AS nombre_usuario
                FROM convenios_estudiante ce
                INNER JOIN convenios c ON c.id = ce.id_convenio
                INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
                INNER JOIN usuarios u ON u.id = ce.id_usuario
                INNER JOIN personas pu ON pu.id = u.id_persona
                WHERE ce.id_estudiante = :id_estudiante AND ce.id_tenant = :id_tenant
                ORDER BY ce.fecha_inicio DESC
            ");
            $stmt->bindParam(':id_estudiante', $id_estudiante);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en ConveniosEstudiante::getByEstudiante: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener convenios del estudiante'], 500);
        }
    }

    public static function getActivosByEstudiante($id_estudiante)
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT ce.id, ce.id_convenio, c.nombre AS nombre_convenio, 
                       c.id_producto_servicio, ps.nombre AS nombre_producto_servicio
                FROM convenios_estudiante ce
                INNER JOIN convenios c ON c.id = ce.id_convenio AND c.activo = 1
                INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
                WHERE ce.id_estudiante = :id_estudiante AND ce.id_tenant = :id_tenant
                  AND ce.fecha_inicio <= CURDATE()
                  AND (ce.fecha_fin IS NULL OR ce.fecha_fin >= CURDATE())
            ");
            $stmt->bindParam(':id_estudiante', $id_estudiante);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en ConveniosEstudiante::getActivosByEstudiante: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener convenios activos'], 500);
        }
    }

    /**
     * Crea un convenio para un estudiante y, si el flag crear_cobros_automaticos viene en true,
     * genera las cuentas por cobrar mensuales automáticamente entre fecha_inicio y fecha_fin.
     */
    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $id_estudiante = $data['id_estudiante'];
            $id_convenio = $data['id_convenio'];
            $fecha_inicio = $data['fecha_inicio'];
            $fecha_fin = $data['fecha_fin'];
            $id_usuario = $data['id_usuario'];

            // Flag opcional: si no viene, por defecto NO se crean cobros automáticos.
            $crear_cobros_automaticos = isset($data['crear_cobros_automaticos'])
                ? filter_var($data['crear_cobros_automaticos'], FILTER_VALIDATE_BOOLEAN)
                : false;

            if (!$fecha_fin) {
                Flight::json(['error' => 'La fecha fin es obligatoria'], 400);
                return;
            }

            // Obtener id_persona del estudiante
            $stmtPersona = $db->prepare("SELECT id_persona FROM estudiantes WHERE id = :id_estudiante AND id_tenant = :id_tenant");
            $stmtPersona->bindParam(':id_estudiante', $id_estudiante);
            $stmtPersona->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtPersona->execute();
            $personaRow = $stmtPersona->fetch(PDO::FETCH_ASSOC);
            if (!$personaRow) {
                Flight::json(['error' => 'Estudiante no encontrado'], 404);
                return;
            }
            $id_persona = $personaRow['id_persona'];

            // Obtener datos del convenio (producto asociado y valor)
            $stmtConvenio = $db->prepare("
                SELECT c.id_producto_servicio, c.nombre, ps.valor_sugerido, ps.nombre AS nombre_producto
                FROM convenios c
                INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
                WHERE c.id = :id_convenio AND c.id_tenant = :id_tenant
            ");
            $stmtConvenio->bindParam(':id_convenio', $id_convenio);
            $stmtConvenio->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtConvenio->execute();
            $convenio = $stmtConvenio->fetch(PDO::FETCH_ASSOC);
            if (!$convenio) {
                Flight::json(['error' => 'Convenio no encontrado'], 404);
                return;
            }

            $db->beginTransaction();

            // 1. Insertar el convenio del estudiante
            $idNew = Uuid::generar();
            $stmt = $db->prepare("
                INSERT INTO convenios_estudiante (id, id_tenant, id_estudiante, id_convenio, fecha_inicio, fecha_fin, id_usuario)
                VALUES (:id, :id_tenant, :id_estudiante, :id_convenio, :fecha_inicio, :fecha_fin, :id_usuario)
            ");
            $stmt->bindValue(':id', $idNew);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_estudiante', $id_estudiante);
            $stmt->bindParam(':id_convenio', $id_convenio);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();
            $id = $idNew;

            $cuentasCreadas = 0;
            $duplicados = [];
            $totalGenerado = 0;

            // 2. Generar cuentas por cobrar mensuales solo si el usuario lo solicitó
            if ($crear_cobros_automaticos) {
                $stmtCuenta = $db->prepare("
                    INSERT INTO cuentas_por_cobrar 
                    (id_tenant, id_producto_servicio, id_persona, fecha, valor, detalle, id_usuario, anulado, fecha_anulacion, id_usuario_anulacion, id_horario_alimentacion)
                    VALUES (:id_tenant, :id_producto_servicio, :id_persona, :fecha, :valor, :detalle, :id_usuario, 0, NULL, NULL, NULL)
                ");
                $stmtCuenta->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

                // Verificar duplicados
                $stmtVerificar = $db->prepare("
                    SELECT COUNT(*) AS cantidad
                    FROM cuentas_por_cobrar
                    WHERE id_persona = :id_persona
                      AND id_producto_servicio = :id_producto_servicio
                      AND fecha = :fecha
                      AND (anulado = 0 OR anulado IS NULL)
                      AND id_tenant = :id_tenant
                ");
                $stmtVerificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

                $nombresMeses = [
                    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                ];

                $fechaActual = new DateTime($fecha_inicio);
                $fechaLimite = new DateTime($fecha_fin);

                while ($fechaActual <= $fechaLimite) {
                    $fechaPrimeroDeMes = $fechaActual->format('Y-m-01');
                    $mesNum = (int) $fechaActual->format('n');
                    $anioFecha = $fechaActual->format('Y');
                    $nombreMes = $nombresMeses[$mesNum];

                    // Verificar si ya existe una cuenta para este mes
                    $stmtVerificar->bindParam(':id_persona', $id_persona);
                    $stmtVerificar->bindParam(':id_producto_servicio', $convenio['id_producto_servicio']);
                    $stmtVerificar->bindParam(':fecha', $fechaPrimeroDeMes);
                    $stmtVerificar->execute();
                    $resultado = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

                    if ($resultado['cantidad'] > 0) {
                        $duplicados[] = [
                            'nombre_producto' => $convenio['nombre_producto'],
                            'fecha' => $fechaPrimeroDeMes,
                            'mes' => "$nombreMes $anioFecha"
                        ];
                        $fechaActual->modify('+1 month');
                        continue;
                    }

                    $detalle = "Generado automáticamente - Convenio {$convenio['nombre']} - {$nombreMes} {$anioFecha}";

                    $stmtCuenta->bindParam(':id_producto_servicio', $convenio['id_producto_servicio']);
                    $stmtCuenta->bindParam(':id_persona', $id_persona);
                    $stmtCuenta->bindParam(':fecha', $fechaPrimeroDeMes);
                    $stmtCuenta->bindParam(':valor', $convenio['valor_sugerido']);
                    $stmtCuenta->bindParam(':detalle', $detalle);
                    $stmtCuenta->bindParam(':id_usuario', $id_usuario);
                    $stmtCuenta->execute();

                    $cuentasCreadas++;
                    $totalGenerado += floatval($convenio['valor_sugerido']);
                    $fechaActual->modify('+1 month');
                }
            }

            $db->commit();

            Flight::json([
                'id' => $id,
                'cuentas_creadas' => $cuentasCreadas,
                'total_generado' => $totalGenerado,
                'valor_mensual' => $convenio['valor_sugerido'],
                'nombre_convenio' => $convenio['nombre'],
                'duplicados' => $duplicados,
                'cobros_automaticos_solicitados' => $crear_cobros_automaticos
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en ConveniosEstudiante::new: ' . $e->getMessage());
            Flight::json(['error' => 'Error al crear convenio de estudiante'], 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $stmt = $db->prepare("
                UPDATE convenios_estudiante SET
                    id_convenio = :id_convenio,
                    fecha_inicio = :fecha_inicio,
                    fecha_fin = :fecha_fin
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_convenio', $data['id_convenio']);
            $stmt->bindParam(':fecha_inicio', $data['fecha_inicio']);
            $stmt->bindParam(':fecha_fin', $data['fecha_fin']);
            $stmt->execute();

            Flight::json(['id' => $data['id']]);
        } catch (Exception $e) {
            error_log('Error en ConveniosEstudiante::replace: ' . $e->getMessage());
            Flight::json(['error' => 'Error al actualizar convenio de estudiante'], 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data['id'];

            // Obtener info del convenio antes de eliminar para el mensaje
            $stmtInfo = $db->prepare("
                SELECT ce.id_estudiante, c.nombre AS nombre_convenio, c.id_producto_servicio,
                       ps.nombre AS nombre_producto
                FROM convenios_estudiante ce
                INNER JOIN convenios c ON c.id = ce.id_convenio
                INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
                WHERE ce.id = :id AND ce.id_tenant = :id_tenant
            ");
            $stmtInfo->bindParam(':id', $id);
            $stmtInfo->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtInfo->execute();
            $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

            // Contar cuentas por cobrar asociadas al producto del convenio
            $cuentasPendientes = 0;
            if ($info) {
                $stmtPersona = $db->prepare("SELECT id_persona FROM estudiantes WHERE id = :id_estudiante AND id_tenant = :id_tenant");
                $stmtPersona->bindParam(':id_estudiante', $info['id_estudiante']);
                $stmtPersona->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtPersona->execute();
                $persona = $stmtPersona->fetch(PDO::FETCH_ASSOC);

                if ($persona) {
                    $stmtCuentas = $db->prepare("
                        SELECT COUNT(*) AS cantidad
                        FROM cuentas_por_cobrar
                        WHERE id_persona = :id_persona
                          AND id_producto_servicio = :id_producto_servicio
                          AND (anulado = 0 OR anulado IS NULL)
                          AND fecha >= CURDATE()
                          AND id_tenant = :id_tenant
                    ");
                    $stmtCuentas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtCuentas->bindParam(':id_persona', $persona['id_persona']);
                    $stmtCuentas->bindParam(':id_producto_servicio', $info['id_producto_servicio']);
                    $stmtCuentas->execute();
                    $resultado = $stmtCuentas->fetch(PDO::FETCH_ASSOC);
                    $cuentasPendientes = $resultado['cantidad'];
                }
            }

            $stmt = $db->prepare("DELETE FROM convenios_estudiante WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            Flight::json([
                'id' => $id,
                'cuentas_pendientes' => $cuentasPendientes,
                'nombre_convenio' => $info ? $info['nombre_convenio'] : '',
                'nombre_producto' => $info ? $info['nombre_producto'] : ''
            ]);
        } catch (Exception $e) {
            error_log('Error en ConveniosEstudiante::delete: ' . $e->getMessage());
            Flight::json(['error' => 'Error al eliminar convenio de estudiante'], 500);
        }
    }
}