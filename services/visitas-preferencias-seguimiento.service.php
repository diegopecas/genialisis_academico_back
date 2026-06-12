<?php
class VisitasPreferenciasSeguimiento
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vps.*,
                    tps.nombre as nombre_preferencia,
                    tps.codigo as codigo_preferencia,
                    tps.icono as icono_preferencia
                FROM visitas_preferencias_seguimiento vps
                INNER JOIN tipos_preferencias_seguimiento tps ON vps.id_preferencia_seguimiento = tps.id
                ORDER BY vps.id_visita, tps.orden
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_preferencias_seguimiento getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vps.*,
                    tps.nombre as nombre_preferencia,
                    tps.codigo as codigo_preferencia,
                    tps.icono as icono_preferencia
                FROM visitas_preferencias_seguimiento vps
                INNER JOIN tipos_preferencias_seguimiento tps ON vps.id_preferencia_seguimiento = tps.id
                WHERE vps.id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_preferencias_seguimiento getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vps.*,
                    tps.nombre as nombre_preferencia,
                    tps.codigo as codigo_preferencia,
                    tps.icono as icono_preferencia
                FROM visitas_preferencias_seguimiento vps
                INNER JOIN tipos_preferencias_seguimiento tps ON vps.id_preferencia_seguimiento = tps.id
                WHERE vps.id_visita = :id_visita
                ORDER BY tps.orden
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_preferencias_seguimiento getByVisita: " . $e->getMessage());
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
            INSERT INTO visitas_preferencias_seguimiento (id_visita, id_preferencia_seguimiento) 
            VALUES (:id_visita, :id_preferencia_seguimiento)
        ");

            $id_visita = $data['id_visita'] ?? null;
            $id_preferencia_seguimiento = $data['id_preferencia_seguimiento'] ?? null;

            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindParam(':id_preferencia_seguimiento', $id_preferencia_seguimiento);

            $sentence->execute();
            $id = $db->lastInsertId();

            // ✅ Si se llamó con parámetro, retornar el ID
            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_preferencias_seguimiento new: " . $e->getMessage());

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
            UPDATE visitas_preferencias_seguimiento SET
                id_preferencia_seguimiento = :id_preferencia_seguimiento
            WHERE id = :id
        ");

            $id = $data['id'];
            $id_preferencia_seguimiento = $data['id_preferencia_seguimiento'] ?? null;

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_preferencia_seguimiento', $id_preferencia_seguimiento);

            $sentence->execute();

            // ✅ Si se llamó con parámetro, retornar true
            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_preferencias_seguimiento replace: " . $e->getMessage());

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

            $sentence = $db->prepare("DELETE FROM visitas_preferencias_seguimiento WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_preferencias_seguimiento delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Método para guardar múltiples preferencias de una vez
    public static function guardarMultiples($dataParam = null)
    {
        try {
            $db = Flight::db();

            // ✅ Si se pasa parámetro, usarlo. Si no, tomar de Flight::request()
            if ($dataParam !== null) {
                $data = $dataParam;
            } else {
                $data = Flight::request()->data;
            }

            $id_visita = $data['id_visita'];
            $preferencias = $data['preferencias'] ?? []; // Array de IDs

            // Primero eliminar las existentes
            $stmt = $db->prepare("DELETE FROM visitas_preferencias_seguimiento WHERE id_visita = :id_visita");
            $stmt->execute(['id_visita' => $id_visita]);

            // Insertar las nuevas usando el método new()
            if (is_array($preferencias) && count($preferencias) > 0) {
                foreach ($preferencias as $id_preferencia) {
                    $prefData = [
                        'id_visita' => $id_visita,
                        'id_preferencia_seguimiento' => $id_preferencia
                    ];
                    self::new($prefData);
                }
            }

            // ✅ Si se llamó con parámetro, retornar true
            if ($dataParam !== null) {
                return true;
            }

            Flight::json(array('success' => true, 'count' => count($preferencias)));
        } catch (Exception $e) {
            error_log("Error en visitas_preferencias_seguimiento guardarMultiples: " . $e->getMessage());

            // ✅ Si se llamó con parámetro, lanzar excepción
            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
