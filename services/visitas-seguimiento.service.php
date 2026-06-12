<?php
class VisitasSeguimiento
{


    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vs.*,
                    tcs.nombre as nombre_cuando_seguimiento,
                    tcs.codigo as codigo_cuando_seguimiento,
                    tqd.nombre as nombre_quien_decide,
                    tqd.codigo as codigo_quien_decide,
                    v.fecha as fecha_visita
                FROM visitas_seguimiento vs
                LEFT JOIN tipos_cuando_seguimiento tcs ON vs.id_cuando_seguimiento = tcs.id
                LEFT JOIN tipos_quien_decide tqd ON vs.id_quien_decide = tqd.id
                INNER JOIN visitas v ON vs.id_visita = v.id
                ORDER BY v.fecha DESC
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_seguimiento getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vs.*,
                    tcs.nombre as nombre_cuando_seguimiento,
                    tcs.codigo as codigo_cuando_seguimiento,
                    tqd.nombre as nombre_quien_decide,
                    tqd.codigo as codigo_quien_decide,
                    v.fecha as fecha_visita
                FROM visitas_seguimiento vs
                LEFT JOIN tipos_cuando_seguimiento tcs ON vs.id_cuando_seguimiento = tcs.id
                LEFT JOIN tipos_quien_decide tqd ON vs.id_quien_decide = tqd.id
                INNER JOIN visitas v ON vs.id_visita = v.id
                WHERE vs.id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_seguimiento getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vs.*,
                    tcs.nombre as nombre_cuando_seguimiento,
                    tcs.codigo as codigo_cuando_seguimiento,
                    tqd.nombre as nombre_quien_decide,
                    tqd.codigo as codigo_quien_decide
                FROM visitas_seguimiento vs
                LEFT JOIN tipos_cuando_seguimiento tcs ON vs.id_cuando_seguimiento = tcs.id
                LEFT JOIN tipos_quien_decide tqd ON vs.id_quien_decide = tqd.id
                WHERE vs.id_visita = :id_visita
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_seguimiento getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();

            // ✅ Si se pasa parámetro, usarlo. Si no, tomar de Flight::request()
            if ($dataParam !== null) {
                $data = $dataParam;
            } else {
                $data = Flight::request()->data;
            }

            $sentence = $db->prepare("
            INSERT INTO visitas_seguimiento (
                id_visita, id_cuando_seguimiento, fecha_seguimiento_calculada,
                mejor_horario, horario_manana, horario_tarde, horario_noche, horario_cualquiera,
                id_quien_decide, necesita_consultar, con_quien_consultar
            ) VALUES (
                :id_visita, :id_cuando_seguimiento, :fecha_seguimiento_calculada,
                :mejor_horario, :horario_manana, :horario_tarde, :horario_noche, :horario_cualquiera,
                :id_quien_decide, :necesita_consultar, :con_quien_consultar
            )
        ");

            $id_visita = $data['id_visita'] ?? null;
            $id_cuando_seguimiento = $data['id_cuando_seguimiento'] ?? null;
            $fecha_seguimiento_calculada = $data['fecha_seguimiento_calculada'] ?? null;
            $mejor_horario = $data['mejor_horario'] ?? null;
            $id_quien_decide = $data['id_quien_decide'] ?? null;
            $con_quien_consultar = $data['con_quien_consultar'] ?? null;

            // ✅ Convertir booleanos a TINYINT
            $necesita_consultar = isset($data['necesita_consultar']) ? ($data['necesita_consultar'] ? 1 : 0) : 0;
            $horario_manana = isset($data['horario_manana']) ? ($data['horario_manana'] ? 1 : 0) : 0;
            $horario_tarde = isset($data['horario_tarde']) ? ($data['horario_tarde'] ? 1 : 0) : 0;
            $horario_noche = isset($data['horario_noche']) ? ($data['horario_noche'] ? 1 : 0) : 0;
            $horario_cualquiera = isset($data['horario_cualquiera']) ? ($data['horario_cualquiera'] ? 1 : 0) : 0;

            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindParam(':id_cuando_seguimiento', $id_cuando_seguimiento);
            $sentence->bindParam(':fecha_seguimiento_calculada', $fecha_seguimiento_calculada);
            $sentence->bindParam(':mejor_horario', $mejor_horario);
            $sentence->bindParam(':horario_manana', $horario_manana);
            $sentence->bindParam(':horario_tarde', $horario_tarde);
            $sentence->bindParam(':horario_noche', $horario_noche);
            $sentence->bindParam(':horario_cualquiera', $horario_cualquiera);
            $sentence->bindParam(':id_quien_decide', $id_quien_decide);
            $sentence->bindParam(':necesita_consultar', $necesita_consultar);
            $sentence->bindParam(':con_quien_consultar', $con_quien_consultar);

            $sentence->execute();
            $id = $db->lastInsertId();

            // ✅ Si se llamó con parámetro, retornar el ID
            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_seguimiento new: " . $e->getMessage());

            // ✅ Si se llamó con parámetro, lanzar excepción
            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
    public static function replace($dataParam = null)
    {
        try {
            $db = Flight::db();

            // ✅ Si se pasa parámetro, usarlo. Si no, tomar de Flight::request()
            if ($dataParam !== null) {
                $data = $dataParam;
            } else {
                $data = Flight::request()->data;
            }

            $sentence = $db->prepare("
            UPDATE visitas_seguimiento SET
                id_cuando_seguimiento = :id_cuando_seguimiento,
                fecha_seguimiento_calculada = :fecha_seguimiento_calculada,
                mejor_horario = :mejor_horario,
                horario_manana = :horario_manana,
                horario_tarde = :horario_tarde,
                horario_noche = :horario_noche,
                horario_cualquiera = :horario_cualquiera,
                id_quien_decide = :id_quien_decide,
                necesita_consultar = :necesita_consultar,
                con_quien_consultar = :con_quien_consultar
            WHERE id = :id
        ");

            $id = $data['id'];
            $id_cuando_seguimiento = $data['id_cuando_seguimiento'] ?? null;
            $fecha_seguimiento_calculada = $data['fecha_seguimiento_calculada'] ?? null;
            $mejor_horario = $data['mejor_horario'] ?? null;
            $id_quien_decide = $data['id_quien_decide'] ?? null;
            $con_quien_consultar = $data['con_quien_consultar'] ?? null;

            // ✅ Convertir booleanos a TINYINT
            $necesita_consultar = isset($data['necesita_consultar']) ? ($data['necesita_consultar'] ? 1 : 0) : 0;
            $horario_manana = isset($data['horario_manana']) ? ($data['horario_manana'] ? 1 : 0) : 0;
            $horario_tarde = isset($data['horario_tarde']) ? ($data['horario_tarde'] ? 1 : 0) : 0;
            $horario_noche = isset($data['horario_noche']) ? ($data['horario_noche'] ? 1 : 0) : 0;
            $horario_cualquiera = isset($data['horario_cualquiera']) ? ($data['horario_cualquiera'] ? 1 : 0) : 0;

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_cuando_seguimiento', $id_cuando_seguimiento);
            $sentence->bindParam(':fecha_seguimiento_calculada', $fecha_seguimiento_calculada);
            $sentence->bindParam(':mejor_horario', $mejor_horario);
            $sentence->bindParam(':horario_manana', $horario_manana);
            $sentence->bindParam(':horario_tarde', $horario_tarde);
            $sentence->bindParam(':horario_noche', $horario_noche);
            $sentence->bindParam(':horario_cualquiera', $horario_cualquiera);
            $sentence->bindParam(':id_quien_decide', $id_quien_decide);
            $sentence->bindParam(':necesita_consultar', $necesita_consultar);
            $sentence->bindParam(':con_quien_consultar', $con_quien_consultar);

            $sentence->execute();

            // ✅ Si se llamó con parámetro, retornar true
            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_seguimiento replace: " . $e->getMessage());

            // ✅ Si se llamó con parámetro, lanzar excepción
            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM visitas_seguimiento WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_seguimiento delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // ✅ Método para actualizar o crear seguimiento (upsert)
    public static function guardarSeguimiento($dataParam = null)
    {
        try {
            $db = Flight::db();

            // ✅ Si se pasa parámetro, usarlo. Si no, tomar de Flight::request()
            if ($dataParam !== null) {
                $data = $dataParam;
            } else {
                $data = Flight::request()->data->getData();
            }

            $id_visita = $data['id_visita'];

            // Verificar si ya existe
            $stmt = $db->prepare("SELECT id FROM visitas_seguimiento WHERE id_visita = :id_visita");
            $stmt->execute(['id_visita' => $id_visita]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existe) {
                // ✅ Actualizar usando replace()
                $data['id'] = $existe['id'];
                self::replace($data);
                $id = $existe['id'];

                // ✅ Si se llamó con parámetro, retornar el ID
                if ($dataParam !== null) {
                    return $id;
                }

                Flight::json(array('success' => true, 'id' => $id, 'action' => 'updated'));
            } else {
                // ✅ Insertar usando new()
                $id = self::new($data);

                // ✅ Si se llamó con parámetro, retornar el ID
                if ($dataParam !== null) {
                    return $id;
                }

                Flight::json(array('success' => true, 'id' => $id, 'action' => 'created'));
            }
        } catch (Exception $e) {
            error_log("Error en visitas_seguimiento guardarSeguimiento: " . $e->getMessage());

            // ✅ Si se llamó con parámetro, lanzar excepción
            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Método para obtener visitas pendientes de seguimiento
    public static function getPendientesSeguimiento()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vs.*,
                    v.fecha as fecha_visita,
                    v.hora as hora_visita,
                    tcs.nombre as nombre_cuando_seguimiento,
                    tqd.nombre as nombre_quien_decide,
                    (SELECT CONCAT(primer_nombre, ' ', primer_apellido) FROM visitantes WHERE id_visita = v.id AND es_contacto_principal = 1 LIMIT 1) as nombre_contacto,
                    (SELECT telefono FROM visitantes WHERE id_visita = v.id AND es_contacto_principal = 1 LIMIT 1) as telefono_contacto
                FROM visitas_seguimiento vs
                INNER JOIN visitas v ON vs.id_visita = v.id
                LEFT JOIN tipos_cuando_seguimiento tcs ON vs.id_cuando_seguimiento = tcs.id
                LEFT JOIN tipos_quien_decide tqd ON vs.id_quien_decide = tqd.id
                WHERE vs.fecha_seguimiento_calculada IS NOT NULL
                ORDER BY vs.fecha_seguimiento_calculada ASC, v.fecha DESC
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_seguimiento getPendientesSeguimiento: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
