<?php
class HistorialRecordatoriosAsistencia
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    id, 
                    id_estudiante, 
                    id_persona_acudiente, 
                    telefono_usado, 
                    nombre_destinatario, 
                    tipo_recordatorio, 
                    dias_ausencia,
                    porcentaje_asistencia_mes,
                    clasificacion_riesgo,
                    id_usuario, 
                    fecha_envio 
                FROM historial_recordatorios_asistencia 
                ORDER BY fecha_envio DESC
            ");
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAll historial_recordatorios_asistencia: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener historial', 'detalles' => $e->getMessage()], 500);
        }
    }

    public static function getByEstudiante($idEstudiante)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    hra.id, 
                    hra.id_estudiante, 
                    hra.id_persona_acudiente, 
                    hra.telefono_usado, 
                    hra.nombre_destinatario, 
                    hra.tipo_recordatorio, 
                    hra.dias_ausencia,
                    hra.porcentaje_asistencia_mes,
                    hra.clasificacion_riesgo,
                    hra.id_usuario, 
                    hra.fecha_envio,
                    TRIM(CONCAT_WS(' ', pu.primer_nombre, pu.segundo_nombre, pu.primer_apellido, pu.segundo_apellido)) AS nombre_usuario
                FROM historial_recordatorios_asistencia hra
                LEFT JOIN usuarios u ON u.id = hra.id_usuario
                LEFT JOIN personas pu ON pu.id = u.id_persona
                WHERE hra.id_estudiante = :id_estudiante
                ORDER BY hra.fecha_envio DESC
            ");
            $sentence->bindParam(':id_estudiante', $idEstudiante);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getByEstudiante historial_recordatorios_asistencia: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener historial del estudiante', 'detalles' => $e->getMessage()], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            $sentence = $db->prepare("
                INSERT INTO historial_recordatorios_asistencia (
                    id_estudiante, id_persona_acudiente, telefono_usado, nombre_destinatario, 
                    tipo_recordatorio, dias_ausencia, porcentaje_asistencia_mes, clasificacion_riesgo,
                    id_usuario, fecha_envio
                ) VALUES (
                    :id_estudiante, :id_persona_acudiente, :telefono_usado, :nombre_destinatario, 
                    :tipo_recordatorio, :dias_ausencia, :porcentaje_asistencia_mes, :clasificacion_riesgo,
                    :id_usuario, NOW()
                )
            ");
            $sentence->bindParam(':id_estudiante', $data['id_estudiante']);
            $sentence->bindParam(':id_persona_acudiente', $data['id_persona_acudiente']);
            $sentence->bindParam(':telefono_usado', $data['telefono_usado']);
            $sentence->bindParam(':nombre_destinatario', $data['nombre_destinatario']);
            $sentence->bindParam(':tipo_recordatorio', $data['tipo_recordatorio']);
            $sentence->bindParam(':dias_ausencia', $data['dias_ausencia']);
            $sentence->bindParam(':porcentaje_asistencia_mes', $data['porcentaje_asistencia_mes']);
            $sentence->bindParam(':clasificacion_riesgo', $data['clasificacion_riesgo']);
            $sentence->bindParam(':id_usuario', $data['id_usuario']);
            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en new historial_recordatorios_asistencia: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al registrar recordatorio', 'detalles' => $e->getMessage()], 500);
        }
    }
}