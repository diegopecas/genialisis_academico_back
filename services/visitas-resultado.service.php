<?php
class VisitasResultado
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vr.*,
                    trv.nombre as nombre_resultado,
                    trv.codigo as codigo_resultado,
                    trv.es_exitoso,
                    v.fecha as fecha_visita
                FROM visitas_resultado vr
                INNER JOIN tipos_resultado_visita trv ON vr.id_tipo_resultado = trv.id
                INNER JOIN visitas v ON vr.id_visita = v.id
                ORDER BY v.fecha DESC
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_resultado getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vr.*,
                    trv.nombre as nombre_resultado,
                    trv.codigo as codigo_resultado,
                    trv.es_exitoso
                FROM visitas_resultado vr
                INNER JOIN tipos_resultado_visita trv ON vr.id_tipo_resultado = trv.id
                WHERE vr.id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_resultado getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vr.*,
                    trv.nombre as nombre_resultado,
                    trv.codigo as codigo_resultado,
                    trv.es_exitoso
                FROM visitas_resultado vr
                INNER JOIN tipos_resultado_visita trv ON vr.id_tipo_resultado = trv.id
                WHERE vr.id_visita = :id_visita
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_resultado getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();
            $data = $dataParam ?? Flight::request()->data;

            $sentence = $db->prepare("
            INSERT INTO visitas_resultado (
                id_visita, id_tipo_resultado, notas_resultado
            ) VALUES (
                :id_visita, :id_tipo_resultado, :notas_resultado
            )
        ");

            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':id_tipo_resultado', $data['id_tipo_resultado']);
            $sentence->bindParam(':notas_resultado', $data['notas_resultado']);

            $sentence->execute();
            $id = $db->lastInsertId();

            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_resultado new: " . $e->getMessage());

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
            $data = $dataParam ?? Flight::request()->data;

            $sentence = $db->prepare("
            UPDATE visitas_resultado SET
                id_tipo_resultado = :id_tipo_resultado,
                notas_resultado = :notas_resultado
            WHERE id = :id
        ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':id_tipo_resultado', $data['id_tipo_resultado']);
            $sentence->bindParam(':notas_resultado', $data['notas_resultado']);

            $sentence->execute();

            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_resultado replace: " . $e->getMessage());

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

            $sentence = $db->prepare("DELETE FROM visitas_resultado WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_resultado delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function guardarResultado($dataParam = null)
    {
        try {
            $db = Flight::db();

            $data = $dataParam ?? Flight::request()->data;
            $id_visita = $data['id_visita'];

            // Verificar si ya existe
            $stmt = $db->prepare("SELECT id FROM visitas_resultado WHERE id_visita = :id_visita");
            $stmt->execute(['id_visita' => $id_visita]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existe) {
                // Ya existe, usar replace
                $data['id'] = $existe['id'];
                self::replace($data);
            } else {
                // No existe, usar new
                self::new($data);
            }

            if ($dataParam !== null) {
                return ['success' => true];
            }

            Flight::json(array('success' => true));
        } catch (Exception $e) {
            error_log("Error en visitas_resultado guardarResultado: " . $e->getMessage());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Obtener estadísticas de resultados (conversión)
    public static function getEstadisticas()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    trv.nombre as resultado,
                    trv.es_exitoso,
                    COUNT(*) as total,
                    ROUND((COUNT(*) / (SELECT COUNT(*) FROM visitas_resultado)) * 100, 2) as porcentaje
                FROM visitas_resultado vr
                INNER JOIN tipos_resultado_visita trv ON vr.id_tipo_resultado = trv.id
                GROUP BY vr.id_tipo_resultado, trv.nombre, trv.es_exitoso
                ORDER BY total DESC
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_resultado getEstadisticas: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
