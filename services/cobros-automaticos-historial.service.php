<?php
class CobrosAutomaticosHistorial
{
    public static function getByEstudiante($id_estudiante)
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT cah.*, 
                       rca.nombre AS nombre_regla,
                       tec.nombre AS nombre_tipo_evento,
                       ps.nombre AS nombre_producto_servicio,
                       cpc.valor, cpc.fecha AS fecha_cuenta,
                       CONCAT(pu.primer_nombre, ' ', COALESCE(pu.primer_apellido, '')) AS nombre_usuario
                FROM cobros_automaticos_historial cah
                INNER JOIN reglas_cobro_automatico rca ON rca.id = cah.id_regla_cobro
                INNER JOIN tipos_evento_cobro tec ON tec.id = rca.id_tipo_evento
                INNER JOIN productos_servicios ps ON ps.id = rca.id_producto_servicio
                INNER JOIN cuentas_por_cobrar cpc ON cpc.id = cah.id_cuenta_por_cobrar
                INNER JOIN usuarios u ON u.id = cah.id_usuario
                INNER JOIN personas pu ON pu.id = u.id_persona
                INNER JOIN asistencia_estudiantes ae ON ae.id = cah.id_asistencia_estudiante
                WHERE ae.id_estudiante = :id_estudiante
                  AND cah.id_tenant = :id_tenant
                ORDER BY cah.fecha_generacion DESC
            ");
            $stmt->bindParam(':id_estudiante', $id_estudiante);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en CobrosAutomaticosHistorial::getByEstudiante: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener historial de cobros automáticos'], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $idNew = Uuid::generar();
            $stmt = $db->prepare("
                INSERT INTO cobros_automaticos_historial 
                (id, id_tenant, id_regla_cobro, id_asistencia_estudiante, id_cuenta_por_cobrar, id_usuario, detalle)
                VALUES (:id, :id_tenant, :id_regla_cobro, :id_asistencia_estudiante, :id_cuenta_por_cobrar, :id_usuario, :detalle)
            ");
            $stmt->bindValue(':id', $idNew);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_regla_cobro', $data['id_regla_cobro']);
            $stmt->bindParam(':id_asistencia_estudiante', $data['id_asistencia_estudiante']);
            $stmt->bindParam(':id_cuenta_por_cobrar', $data['id_cuenta_por_cobrar']);
            $stmt->bindParam(':id_usuario', $data['id_usuario']);
            $stmt->bindParam(':detalle', $data['detalle']);
            $stmt->execute();

            $id = $idNew;
            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en CobrosAutomaticosHistorial::new: ' . $e->getMessage());
            Flight::json(['error' => 'Error al registrar cobro automático'], 500);
        }
    }
}