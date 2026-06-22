<?php
class CocinaDisponibilidad
{
    /**
     * Obtiene productos de alimentación diaria (periodicidad_cobro = 3, clasificacion = 3, disponible = 1)
     * con su estado de disponibilidad para la fecha dada.
     * El backend devuelve todos los horarios; el filtro por horario se aplica en el frontend.
     * Los que estuvieron disponibles la última fecha registrada vienen primero.
     */
    public static function getProductosPorFecha()
    {
        try {
            $request = Flight::request();
            $data = $request->data->getData();

            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            $fecha = isset($data['fecha']) ? $data['fecha'] : date('Y-m-d');

            $db = Flight::db();

            // Última fecha con registros previos a la fecha solicitada
            $stmtUf = $db->prepare("SELECT MAX(fecha) FROM cocina_disponibilidad WHERE fecha < :fecha AND id_tenant = :id_tenant");
            $stmtUf->bindValue(':fecha', $fecha);
            $stmtUf->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtUf->execute();
            $ultimaFecha = $stmtUf->fetchColumn();

            $sql = "SELECT 
                        ps.id,
                        ps.nombre,
                        COALESCE(ps.detalles, '') AS detalles,
                        ps.id_horario_alimentacion_sugerido,
                        ha.nombre AS nombre_horario_sugerido,
                        COALESCE(cd_hoy.disponible, 0) AS disponible_hoy,
                        COALESCE(cd_ultima.disponible, 0) AS disponible_ultima_vez
                    FROM productos_servicios ps
                    LEFT JOIN horarios_alimentacion ha 
                        ON ps.id_horario_alimentacion_sugerido = ha.id
                    LEFT JOIN cocina_disponibilidad cd_hoy 
                        ON cd_hoy.id_producto_servicio = ps.id 
                        AND cd_hoy.fecha = :fecha
                    LEFT JOIN cocina_disponibilidad cd_ultima 
                        ON cd_ultima.id_producto_servicio = ps.id 
                        AND cd_ultima.fecha = :ultima_fecha
                    WHERE ps.id_clasificacion_productos_servicios IN (SELECT cps.id FROM clasificacion_productos_servicios cps WHERE cps.codigo = 'ALIMENTACION' AND cps.id_tenant = ps.id_tenant)
                      AND ps.id_periodicidad_cobro = 3
                      AND ps.disponible = 1
                      AND ps.id_tenant = :id_tenant
                    ORDER BY 
                        COALESCE(cd_ultima.disponible, 0) DESC,
                        ps.nombre ASC";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':fecha', $fecha);
            $stmt->bindValue(':ultima_fecha', $ultimaFecha ?: '1970-01-01');
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($productos as &$p) {
                $p['id'] = (string)$p['id'];
                $p['disponible_hoy'] = (int)$p['disponible_hoy'];
                $p['disponible_ultima_vez'] = (int)$p['disponible_ultima_vez'];
                $p['id_horario_alimentacion_sugerido'] = $p['id_horario_alimentacion_sugerido']
                    ? (string)$p['id_horario_alimentacion_sugerido']
                    : null;
            }

            Flight::json([
                'productos'    => $productos,
                'ultima_fecha' => $ultimaFecha ?: null
            ]);

        } catch (Exception $e) {
            error_log("Error en CocinaDisponibilidad::getProductosPorFecha: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Guarda en batch la disponibilidad de múltiples productos para una fecha.
     * Body: { fecha: string, productos: [{id_producto_servicio: string, disponible: 0|1}] }
     * 
     * NOTA: PDO no permite reutilizar el mismo named parameter más de una vez,
     * por eso cada fila tiene su propio :fecha_N en lugar de compartir :fecha.
     */
    public static function guardarBatch()
    {
        try {
            $request = Flight::request();
            $data = $request->data->getData();

            if (empty($data)) {
                $body = $request->getBody();
                $data = json_decode($body, true);
            }

            $fecha     = isset($data['fecha'])     ? $data['fecha']     : null;
            $productos = isset($data['productos']) ? $data['productos'] : [];

            if (!$fecha || empty($productos)) {
                Flight::json(['error' => 'Faltan parámetros requeridos'], 400);
                return;
            }

            $db = Flight::db();

            $valores    = [];
            $parametros = [];

            foreach ($productos as $i => $p) {
                $keyId   = ":id_{$i}";
                $keyFecha = ":fecha_{$i}";
                $keyDisp = ":disp_{$i}";
                $keyTen  = ":ten_{$i}";

                $valores[]              = "({$keyTen}, {$keyId}, {$keyFecha}, {$keyDisp})";
                $parametros[$keyTen]    = TenantContext::id();
                $parametros[$keyId]     = $p['id_producto_servicio'];
                $parametros[$keyFecha]  = $fecha;
                $parametros[$keyDisp]   = (int)$p['disponible'];
            }

            $sql = "INSERT INTO cocina_disponibilidad (id_tenant, id_producto_servicio, fecha, disponible)
                    VALUES " . implode(', ', $valores) . "
                    ON DUPLICATE KEY UPDATE disponible = VALUES(disponible)";

            $stmt = $db->prepare($sql);
            $stmt->execute($parametros);

            Flight::json(['success' => true, 'guardados' => count($productos)]);

        } catch (Exception $e) {
            error_log("Error en CocinaDisponibilidad::guardarBatch: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}