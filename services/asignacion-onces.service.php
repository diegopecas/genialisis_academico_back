<?php
class AsignacionOnces
{
    /**
     * Retorna todos los estudiantes activos separados en presentes y ausentes.
     * Sin filtro de producto — el frontend hace el cruce.
     * Body: { fecha }
     */
    public static function getEstudiantesDelDia()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $request = Flight::request();
            $data = $request->data->getData();

            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            $fecha = isset($data['fecha']) ? $data['fecha'] : date('Y-m-d');

            $db = Flight::db();

            $sql = "SELECT 
                        e.id AS id_estudiante,
                        p.id AS id_persona,
                        TRIM(REGEXP_REPLACE(
                            CONCAT(
                                COALESCE(p.primer_nombre, ''), ' ',
                                COALESCE(p.segundo_nombre, ''), ' ',
                                COALESCE(p.primer_apellido, ''), ' ',
                                COALESCE(p.segundo_apellido, '')
                            ), '\\\\s+', ' '
                        )) AS nombre_estudiante,
                        g.id AS id_grupo,
                        g.nombre AS nombre_grupo,
                        g.orden AS orden_grupo,
                        TIME(ae.fecha_ingreso) AS hora_ingreso,
                        CASE WHEN ae.id_estudiante IS NOT NULL THEN 1 ELSE 0 END AS presente
                    FROM estudiantes e
                    INNER JOIN personas p ON e.id_persona = p.id
                    INNER JOIN estudiantes_x_grupos exg ON e.id = exg.id_estudiante AND exg.activo = 1
                    INNER JOIN grupos g ON exg.id_grupo = g.id
                    LEFT JOIN (
                        SELECT id_estudiante, MIN(fecha_ingreso) AS fecha_ingreso
                        FROM asistencia_estudiantes
                        WHERE DATE(fecha_ingreso) = :fecha
                          AND fecha_salida IS NULL
                        GROUP BY id_estudiante
                    ) ae ON ae.id_estudiante = e.id
                    WHERE e.activo = 1
                    AND e.id_tenant = :id_tenant
                    ORDER BY g.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':fecha', $fecha);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $presentes = [];
            $ausentes  = [];

            foreach ($todos as &$est) {
                $est['id_estudiante'] = (string)$est['id_estudiante'];
                $est['id_persona']    = (string)$est['id_persona'];
                $est['id_grupo']      = (string)$est['id_grupo'];
                $est['presente']      = (int)$est['presente'];
                $est['orden_grupo']   = (int)$est['orden_grupo'];

                if ($est['presente'] === 1) {
                    $presentes[] = $est;
                } else {
                    $ausentes[] = $est;
                }
            }

            Flight::json([
                'presentes' => $presentes,
                'ausentes'  => $ausentes
            ]);

        } catch (Exception $e) {
            error_log("Error en AsignacionOnces::getEstudiantesDelDia: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Retorna las asignaciones ya hechas para una fecha y horario.
     * El frontend cruza con la lista de estudiantes para excluir los ya asignados.
     * Body: { fecha, id_horario }
     */
    public static function getAsignacionesDelDia()
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
            $id_horario = isset($data['id_horario']) ? $data['id_horario'] : null;

            if (!$id_horario) {
                Flight::json(['error' => 'Falta id_horario'], 400);
                return;
            }

            $db = Flight::db();

            $sql = "SELECT 
                        id_persona,
                        id_producto_servicio,
                        id_horario_alimentacion
                    FROM cuentas_por_cobrar
                    WHERE fecha = :fecha
                      AND id_horario_alimentacion = :id_horario
                      AND anulado = 0
                      AND id_tenant = :id_tenant";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':fecha',      $fecha);
            $stmt->bindValue(':id_horario', $id_horario);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($asignaciones as &$a) {
                $a['id_persona']           = (string)$a['id_persona'];
                $a['id_producto_servicio'] = (string)$a['id_producto_servicio'];
                $a['id_horario_alimentacion'] = (string)$a['id_horario_alimentacion'];
            }

            Flight::json($asignaciones);

        } catch (Exception $e) {
            error_log("Error en AsignacionOnces::getAsignacionesDelDia: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crea en batch registros en cuentas_por_cobrar para los estudiantes seleccionados.
     * Body: { fecha, id_horario, id_producto_servicio, valor, id_usuario, detalle, estudiantes: [id_persona] }
     */
    public static function crearBatch()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $request = Flight::request();
            $data = $request->data->getData();

            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            $fecha                = isset($data['fecha'])                ? $data['fecha']                     : null;
            $id_horario           = isset($data['id_horario'])           ? $data['id_horario']                : null;
            $id_producto_servicio = isset($data['id_producto_servicio']) ? $data['id_producto_servicio']      : null;
            $valor                = isset($data['valor'])                ? (float)$data['valor']              : 0;
            $id_usuario           = isset($data['id_usuario'])           ? $data['id_usuario']                : null;
            $detalle              = isset($data['detalle'])              ? $data['detalle']                   : '';
            $estudiantes          = isset($data['estudiantes'])          ? $data['estudiantes']               : [];

            if (!$fecha || !$id_horario || !$id_producto_servicio || empty($estudiantes)) {
                Flight::json(['error' => 'Faltan parámetros requeridos'], 400);
                return;
            }

            $db = Flight::db();

            $sql = "INSERT INTO cuentas_por_cobrar 
                        (id_tenant, id_producto_servicio, id_persona, fecha, valor, detalle, id_usuario, anulado, fecha_anulacion, id_usuario_anulacion, id_horario_alimentacion)
                    VALUES 
                        (:id_tenant, :id_producto_servicio, :id_persona, :fecha, :valor, :detalle, :id_usuario, 0, NULL, NULL, :id_horario_alimentacion)";

            $stmt    = $db->prepare($sql);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $creados = 0;

            foreach ($estudiantes as $id_persona) {
                $stmt->bindValue(':id_producto_servicio',    $id_producto_servicio);
                $stmt->bindValue(':id_persona',              $id_persona);
                $stmt->bindValue(':fecha',                   $fecha);
                $stmt->bindValue(':valor',                   $valor);
                $stmt->bindValue(':detalle',                 $detalle);
                $stmt->bindValue(':id_usuario',              $id_usuario);
                $stmt->bindValue(':id_horario_alimentacion', $id_horario);
                $stmt->execute();
                $creados++;
            }

            // Retornar las nuevas asignaciones para que el frontend actualice su caché
            $sqlNuevas = "SELECT id_persona, id_producto_servicio, id_horario_alimentacion
                          FROM cuentas_por_cobrar
                          WHERE fecha = :fecha
                            AND id_horario_alimentacion = :id_horario
                            AND anulado = 0
                            AND id_tenant = :id_tenant";
            $stmtN = $db->prepare($sqlNuevas);
            $stmtN->bindValue(':fecha',      $fecha);
            $stmtN->bindValue(':id_horario', $id_horario);
            $stmtN->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtN->execute();
            $asignaciones = $stmtN->fetchAll(PDO::FETCH_ASSOC);

            foreach ($asignaciones as &$a) {
                $a['id_persona']              = (string)$a['id_persona'];
                $a['id_producto_servicio']    = (string)$a['id_producto_servicio'];
                $a['id_horario_alimentacion'] = (string)$a['id_horario_alimentacion'];
            }

            Flight::json([
                'success'     => true,
                'creados'     => $creados,
                'asignaciones' => $asignaciones
            ]);

        } catch (Exception $e) {
            error_log("Error en AsignacionOnces::crearBatch: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}