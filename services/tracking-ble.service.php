<?php
class TrackingBle
{
    // ===================================================================
    // CRUD
    // ===================================================================

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                t.*,
                tet.nombre as tipo_evento,
                af.nombre as nombre_area,
                d.nombre as nombre_dispositivo,
                b.nombre as nombre_beacon,
                b.mac_address
            FROM tracking_ble t
            INNER JOIN tipos_eventos_tracking tet ON t.id_tipo_evento = tet.id
            INNER JOIN areas_fisicas af ON t.id_area_fisica = af.id
            INNER JOIN dispositivos_ble d ON t.id_dispositivo = d.id
            INNER JOIN beacons_ble b ON t.id_beacon = b.id
            WHERE t.id_tenant = :id_tenant
            ORDER BY t.fecha_evento DESC
            LIMIT 200
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                t.*,
                tet.nombre as tipo_evento,
                af.nombre as nombre_area,
                d.nombre as nombre_dispositivo,
                b.nombre as nombre_beacon,
                b.mac_address
            FROM tracking_ble t
            INNER JOIN tipos_eventos_tracking tet ON t.id_tipo_evento = tet.id
            INNER JOIN areas_fisicas af ON t.id_area_fisica = af.id
            INNER JOIN dispositivos_ble d ON t.id_dispositivo = d.id
            INNER JOIN beacons_ble b ON t.id_beacon = b.id
            WHERE t.id = :id
            AND t.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // ===================================================================
    // REPORTE DESDE ESP32 (endpoint IoT)
    // ===================================================================

    public static function recibirReporte()
    {
        $db = Flight::db();

        $api_key = Flight::request()->data['api_key'] ?? null;
        $beacons = Flight::request()->data['beacons'] ?? [];

        if (empty($api_key)) {
            Flight::json(array('error' => 'api_key es requerido'), 400);
            return;
        }

        // Validar dispositivo por API Key
        $stmt = $db->prepare("
            SELECT d.id, d.id_tenant, d.id_area_fisica, d.nombre, af.nombre as nombre_area
            FROM dispositivos_ble d
            INNER JOIN areas_fisicas af ON d.id_area_fisica = af.id
            WHERE d.api_key = :api_key AND d.activo = 1
        ");
        $stmt->bindParam(':api_key', $api_key);
        $stmt->execute();
        $dispositivo = $stmt->fetch();

        if (!$dispositivo) {
            Flight::json(array('error' => 'Dispositivo no autorizado'), 401);
            return;
        }

        $id_dispositivo = $dispositivo['id'];
        $id_area = $dispositivo['id_area_fisica'];
        $id_tenant = $dispositivo['id_tenant'];

        // Obtener IDs de tipos de evento
        $tiposStmt = $db->prepare("SELECT id, nombre FROM tipos_eventos_tracking WHERE activo = 1");
        $tiposStmt->execute();
        $tiposRows = $tiposStmt->fetchAll();
        $tiposEvento = [];
        foreach ($tiposRows as $tipo) {
            $tiposEvento[$tipo['nombre']] = $tipo['id'];
        }

        $resultados = [];

        foreach ($beacons as $beaconData) {
            $mac = strtolower($beaconData['mac'] ?? '');
            $rssi = $beaconData['rssi'] ?? null;
            $evento = strtoupper($beaconData['evento'] ?? 'PING');

            if (empty($mac)) continue;

            if (!isset($tiposEvento[$evento])) continue;

            $id_tipo_evento = $tiposEvento[$evento];

            // Buscar beacon registrado
            $beaconStmt = $db->prepare("SELECT id FROM beacons_ble WHERE mac_address = :mac AND activo = 1 AND id_tenant = :id_tenant");
            $beaconStmt->bindParam(':mac', $mac);
            $beaconStmt->bindValue(':id_tenant', $id_tenant, PDO::PARAM_INT);
            $beaconStmt->execute();
            $beacon = $beaconStmt->fetch();

            if (!$beacon) {
                $resultados[] = array('mac' => $mac, 'status' => 'no_registrado');
                continue;
            }

            $id_beacon = $beacon['id'];

            // Registrar evento
            $idTrk = Uuid::generar();
            $logStmt = $db->prepare("
                INSERT INTO tracking_ble(id, id_tenant, id_beacon, id_dispositivo, id_area_fisica, id_tipo_evento, rssi, fecha_evento)
                VALUES (:id, :id_tenant, :id_beacon, :id_dispositivo, :id_area, :id_tipo_evento, :rssi, NOW())
            ");
            $logStmt->bindValue(':id', $idTrk);
            $logStmt->bindValue(':id_tenant', $id_tenant, PDO::PARAM_INT);
            $logStmt->bindParam(':id_beacon', $id_beacon);
            $logStmt->bindParam(':id_dispositivo', $id_dispositivo);
            $logStmt->bindParam(':id_area', $id_area);
            $logStmt->bindParam(':id_tipo_evento', $id_tipo_evento);
            $logStmt->bindParam(':rssi', $rssi);
            $logStmt->execute();

            $resultados[] = array('mac' => $mac, 'status' => 'ok', 'evento' => $evento);
        }

        Flight::json(array(
            'dispositivo' => $dispositivo['nombre'],
            'zona' => $dispositivo['nombre_area'],
            'procesados' => count($resultados),
            'resultados' => $resultados
        ));
    }

    // ===================================================================
    // CONSULTAS DE UBICACIÓN (desde Angular)
    // ===================================================================

    public static function getUbicacionActual()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                b.id,
                b.mac_address,
                b.nombre as nombre_beacon,
                CONCAT(p.primer_nombre, ' ', p.primer_apellido) as nombre_estudiante,
                e.id as id_estudiante,
                ultimo.id_area_fisica,
                af.nombre as nombre_area,
                ultimo.rssi as ultimo_rssi,
                ultimo.fecha_evento as ultima_deteccion,
                tet.nombre as ultimo_evento,
                TIMESTAMPDIFF(SECOND, ultimo.fecha_evento, NOW()) as segundos_sin_deteccion
            FROM beacons_ble b
            LEFT JOIN estudiantes e ON b.id_estudiante = e.id
            LEFT JOIN personas p ON e.id_persona = p.id
            LEFT JOIN (
                SELECT t1.id_beacon, t1.id_area_fisica, t1.rssi, t1.fecha_evento, t1.id_tipo_evento
                FROM tracking_ble t1
                /* MAX(id) devolvia el UUID mayor alfabeticamente, no el evento mas reciente.
                   Se ancla por MAX(fecha_evento) y se desempata por el mayor id de esa fecha. */
                INNER JOIN (
                    SELECT id_beacon, MAX(fecha_evento) AS max_fecha
                    FROM tracking_ble
                    GROUP BY id_beacon
                ) t2 ON t1.id_beacon = t2.id_beacon
                    AND t1.fecha_evento = t2.max_fecha
                INNER JOIN (
                    SELECT id_beacon, fecha_evento, MAX(id) AS max_id
                    FROM tracking_ble
                    GROUP BY id_beacon, fecha_evento
                ) t3 ON t1.id_beacon = t3.id_beacon
                    AND t1.fecha_evento = t3.fecha_evento
                    AND t1.id = t3.max_id
            ) ultimo ON b.id = ultimo.id_beacon
            LEFT JOIN areas_fisicas af ON ultimo.id_area_fisica = af.id
            LEFT JOIN tipos_eventos_tracking tet ON ultimo.id_tipo_evento = tet.id
            WHERE b.activo = 1
            AND b.id_tenant = :id_tenant
            ORDER BY af.nombre ASC, p.primer_nombre ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getUbicacionPorArea($id_area)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                b.id,
                b.mac_address,
                b.nombre as nombre_beacon,
                CONCAT(p.primer_nombre, ' ', p.primer_apellido) as nombre_estudiante,
                e.id as id_estudiante,
                ultimo.rssi as ultimo_rssi,
                ultimo.fecha_evento as ultima_deteccion,
                tet.nombre as ultimo_evento,
                TIMESTAMPDIFF(SECOND, ultimo.fecha_evento, NOW()) as segundos_sin_deteccion
            FROM beacons_ble b
            LEFT JOIN estudiantes e ON b.id_estudiante = e.id
            LEFT JOIN personas p ON e.id_persona = p.id
            INNER JOIN (
                SELECT t1.id_beacon, t1.id_area_fisica, t1.rssi, t1.fecha_evento, t1.id_tipo_evento
                FROM tracking_ble t1
                /* MAX(id) devolvia el UUID mayor alfabeticamente, no el evento mas reciente.
                   Se ancla por MAX(fecha_evento) y se desempata por el mayor id de esa fecha. */
                INNER JOIN (
                    SELECT id_beacon, MAX(fecha_evento) AS max_fecha
                    FROM tracking_ble
                    GROUP BY id_beacon
                ) t2 ON t1.id_beacon = t2.id_beacon
                    AND t1.fecha_evento = t2.max_fecha
                INNER JOIN (
                    SELECT id_beacon, fecha_evento, MAX(id) AS max_id
                    FROM tracking_ble
                    GROUP BY id_beacon, fecha_evento
                ) t3 ON t1.id_beacon = t3.id_beacon
                    AND t1.fecha_evento = t3.fecha_evento
                    AND t1.id = t3.max_id
            ) ultimo ON b.id = ultimo.id_beacon
            LEFT JOIN tipos_eventos_tracking tet ON ultimo.id_tipo_evento = tet.id
            WHERE ultimo.id_area_fisica = :id_area 
            AND b.activo = 1
            AND b.id_tenant = :id_tenant
            AND tet.nombre != 'SALIDA'
            ORDER BY p.primer_nombre ASC
        ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getHistorialBeacon($id_beacon)
    {
        $db = Flight::db();

        $fecha_inicio = Flight::request()->query['fecha_inicio'] ?? date('Y-m-d');
        $fecha_fin = Flight::request()->query['fecha_fin'] ?? date('Y-m-d');

        $sentence = $db->prepare("
            SELECT 
                t.id,
                t.rssi,
                t.fecha_evento,
                tet.nombre as tipo_evento,
                af.nombre as nombre_area,
                d.nombre as nombre_dispositivo
            FROM tracking_ble t
            INNER JOIN tipos_eventos_tracking tet ON t.id_tipo_evento = tet.id
            INNER JOIN areas_fisicas af ON t.id_area_fisica = af.id
            INNER JOIN dispositivos_ble d ON t.id_dispositivo = d.id
            WHERE t.id_beacon = :id_beacon
            AND t.id_tenant = :id_tenant
            AND DATE(t.fecha_evento) BETWEEN :fecha_inicio AND :fecha_fin
            ORDER BY t.fecha_evento DESC
            LIMIT 500
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_beacon', $id_beacon);
        $sentence->bindParam(':fecha_inicio', $fecha_inicio);
        $sentence->bindParam(':fecha_fin', $fecha_fin);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getResumenPorZona()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                af.id,
                af.nombre as nombre_area,
                af.capacidad,
                COUNT(DISTINCT presentes.id_beacon) as total_presentes
            FROM areas_fisicas af
            LEFT JOIN (
                SELECT t1.id_beacon, t1.id_area_fisica, tet.nombre as evento
                FROM tracking_ble t1
                /* MAX(id) devolvia el UUID mayor alfabeticamente, no el evento mas reciente.
                   Se ancla por MAX(fecha_evento) y se desempata por el mayor id de esa fecha. */
                INNER JOIN (
                    SELECT id_beacon, MAX(fecha_evento) AS max_fecha
                    FROM tracking_ble
                    GROUP BY id_beacon
                ) t2 ON t1.id_beacon = t2.id_beacon
                    AND t1.fecha_evento = t2.max_fecha
                INNER JOIN (
                    SELECT id_beacon, fecha_evento, MAX(id) AS max_id
                    FROM tracking_ble
                    GROUP BY id_beacon, fecha_evento
                ) t3 ON t1.id_beacon = t3.id_beacon
                    AND t1.fecha_evento = t3.fecha_evento
                    AND t1.id = t3.max_id
                INNER JOIN tipos_eventos_tracking tet ON t1.id_tipo_evento = tet.id
                INNER JOIN beacons_ble b ON t1.id_beacon = b.id AND b.activo = 1
                WHERE tet.nombre != 'SALIDA'
            ) presentes ON af.id = presentes.id_area_fisica
            WHERE af.activo = 1
            AND af.id_tenant = :id_tenant
            GROUP BY af.id, af.nombre, af.capacidad
            ORDER BY af.nombre ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}