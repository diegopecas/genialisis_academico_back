<?php
class HistorialInformesEstudiantes
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    hi.id,
                    hi.id_estudiante,
                    hi.id_sprint,
                    hi.id_persona_acudiente,
                    hi.contacto_usado,
                    hi.nombre_destinatario,
                    hi.medio_envio,
                    hi.compromiso,
                    hi.fecha_compromiso,
                    hi.id_usuario,
                    hi.fecha_envio
                FROM historial_informes_estudiantes hi
                ORDER BY hi.fecha_envio DESC
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en HistorialInformesEstudiantes::getAll: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener historial de informes'), 500);
        }
    }

    public static function getByEstudiante($id)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    hi.id,
                    hi.id_estudiante,
                    hi.id_sprint,
                    s.numero_sprint,
                    s.nombre_sprint,
                    ca.nombre AS nombre_corte_academico,
                    hi.id_persona_acudiente,
                    hi.contacto_usado,
                    hi.nombre_destinatario,
                    hi.medio_envio,
                    hi.compromiso,
                    hi.fecha_compromiso,
                    hi.id_usuario,
                    hi.fecha_envio
                FROM historial_informes_estudiantes hi
                LEFT JOIN sprints s ON hi.id_sprint = s.id
                LEFT JOIN cortes_academicos ca ON s.id_corte_academico = ca.id
                WHERE hi.id_estudiante = :id_estudiante
                ORDER BY hi.fecha_envio DESC
            ");
            $sentence->bindParam(':id_estudiante', $id, PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en HistorialInformesEstudiantes::getByEstudiante: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener historial por estudiante'), 500);
        }
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $request = Flight::request();

            $id_estudiante = $request->data['id_estudiante'];
            $id_sprint = $request->data['id_sprint'];
            $id_persona_acudiente = isset($request->data['id_persona_acudiente']) ? $request->data['id_persona_acudiente'] : null;
            $contacto_usado = isset($request->data['contacto_usado']) ? $request->data['contacto_usado'] : null;
            $nombre_destinatario = isset($request->data['nombre_destinatario']) ? $request->data['nombre_destinatario'] : null;
            $medio_envio = isset($request->data['medio_envio']) ? $request->data['medio_envio'] : 'whatsapp';
            $compromiso = isset($request->data['compromiso']) ? $request->data['compromiso'] : null;
            $fecha_compromiso = isset($request->data['fecha_compromiso']) ? $request->data['fecha_compromiso'] : null;
            $id_usuario = isset($request->data['id_usuario']) ? $request->data['id_usuario'] : null;

            $sentence = $db->prepare("
                INSERT INTO historial_informes_estudiantes (
                    id_estudiante, id_sprint, id_persona_acudiente, contacto_usado, nombre_destinatario,
                    medio_envio, compromiso, fecha_compromiso, id_usuario
                ) VALUES (
                    :id_estudiante, :id_sprint, :id_persona_acudiente, :contacto_usado, :nombre_destinatario,
                    :medio_envio, :compromiso, :fecha_compromiso, :id_usuario
                )
            ");

            $sentence->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
            $sentence->bindParam(':id_sprint', $id_sprint, PDO::PARAM_INT);
            $sentence->bindParam(':id_persona_acudiente', $id_persona_acudiente, PDO::PARAM_INT);
            $sentence->bindParam(':contacto_usado', $contacto_usado);
            $sentence->bindParam(':nombre_destinatario', $nombre_destinatario);
            $sentence->bindParam(':medio_envio', $medio_envio);
            $sentence->bindParam(':compromiso', $compromiso);
            $sentence->bindParam(':fecha_compromiso', $fecha_compromiso);
            $sentence->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);

            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en HistorialInformesEstudiantes::new: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al crear registro de historial'), 500);
        }
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $request = Flight::request();

            $id = $request->data['id'];
            $compromiso = isset($request->data['compromiso']) ? $request->data['compromiso'] : null;
            $fecha_compromiso = isset($request->data['fecha_compromiso']) ? $request->data['fecha_compromiso'] : null;

            $sentence = $db->prepare("
                UPDATE historial_informes_estudiantes SET
                    compromiso = :compromiso,
                    fecha_compromiso = :fecha_compromiso
                WHERE id = :id
            ");

            $sentence->bindParam(':id', $id, PDO::PARAM_INT);
            $sentence->bindParam(':compromiso', $compromiso);
            $sentence->bindParam(':fecha_compromiso', $fecha_compromiso);

            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en HistorialInformesEstudiantes::replace: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar registro de historial'), 500);
        }
    }
}