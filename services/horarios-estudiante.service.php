<?php
class HorariosEstudiante
{
    public static function getByEstudiante($id_estudiante)
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT he.id, he.id_estudiante, he.id_dia_semana, he.hora_entrada, he.hora_salida,
                       ds.nombre AS nombre_dia
                FROM horarios_estudiante he
                INNER JOIN dias_semana ds ON ds.id = he.id_dia_semana
                WHERE he.id_estudiante = :id_estudiante
                ORDER BY he.id_dia_semana
            ");
            $stmt->bindParam(':id_estudiante', $id_estudiante);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en HorariosEstudiante::getByEstudiante: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener horarios del estudiante'], 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $id = $data['id'];
            $hora_entrada = $data['hora_entrada'];
            $hora_salida = $data['hora_salida'];

            $stmt = $db->prepare("
                UPDATE horarios_estudiante 
                SET hora_entrada = :hora_entrada, hora_salida = :hora_salida
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':hora_entrada', $hora_entrada);
            $stmt->bindParam(':hora_salida', $hora_salida);
            $stmt->execute();

            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en HorariosEstudiante::replace: ' . $e->getMessage());
            Flight::json(['error' => 'Error al actualizar horario'], 500);
        }
    }

    /**
     * Guarda todos los horarios de un estudiante de una sola vez (7 días).
     * Usa INSERT ... ON DUPLICATE KEY UPDATE para crear o actualizar.
     */
    public static function guardarTodos()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $id_estudiante = $data['id_estudiante'];
            $horarios = $data['horarios']; // array de {id_dia_semana, hora_entrada, hora_salida}

            if (empty($horarios) || !is_array($horarios)) {
                Flight::json(['error' => 'No se proporcionaron horarios'], 400);
                return;
            }

            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO horarios_estudiante (id_estudiante, id_dia_semana, hora_entrada, hora_salida)
                VALUES (:id_estudiante, :id_dia_semana, :hora_entrada, :hora_salida)
                ON DUPLICATE KEY UPDATE hora_entrada = :hora_entrada2, hora_salida = :hora_salida2
            ");

            foreach ($horarios as $horario) {
                $stmt->bindParam(':id_estudiante', $id_estudiante);
                $stmt->bindParam(':id_dia_semana', $horario['id_dia_semana']);
                $stmt->bindParam(':hora_entrada', $horario['hora_entrada']);
                $stmt->bindParam(':hora_salida', $horario['hora_salida']);
                $stmt->bindParam(':hora_entrada2', $horario['hora_entrada']);
                $stmt->bindParam(':hora_salida2', $horario['hora_salida']);
                $stmt->execute();
            }

            $db->commit();
            Flight::json(['success' => true, 'id_estudiante' => $id_estudiante]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en HorariosEstudiante::guardarTodos: ' . $e->getMessage());
            Flight::json(['error' => 'Error al guardar horarios'], 500);
        }
    }

    /**
     * Inicializa horarios de un estudiante con los valores de dias_semana.
     */
    public static function inicializarDesdeDefault()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();
            $id_estudiante = $data['id_estudiante'];

            $stmt = $db->prepare("
                INSERT INTO horarios_estudiante (id_estudiante, id_dia_semana, hora_entrada, hora_salida)
                SELECT :id_estudiante, ds.id, ds.hora_entrada, ds.hora_salida
                FROM dias_semana ds
                ON DUPLICATE KEY UPDATE hora_entrada = ds.hora_entrada, hora_salida = ds.hora_salida
            ");
            $stmt->bindParam(':id_estudiante', $id_estudiante);
            $stmt->execute();

            Flight::json(['success' => true, 'id_estudiante' => $id_estudiante]);
        } catch (Exception $e) {
            error_log('Error en HorariosEstudiante::inicializarDesdeDefault: ' . $e->getMessage());
            Flight::json(['error' => 'Error al inicializar horarios'], 500);
        }
    }
}