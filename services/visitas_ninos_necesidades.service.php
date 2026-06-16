<?php 
class VisitasNinosNecesidades
{
    /**
     * Obtener todas las necesidades de un niño específico
     */
    public static function getByNino($id_visita_nino)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vnn.*,
                    tne.nombre as nombre_necesidad,
                    tne.icono as icono_necesidad
                FROM visitas_ninos_necesidades vnn
                INNER JOIN tipos_necesidades_especiales tne ON vnn.id_tipo_necesidad = tne.id
                WHERE vnn.id_visita_nino = :id_visita_nino
                ORDER BY tne.orden, tne.nombre
            ");
            $sentence->bindParam(':id_visita_nino', $id_visita_nino);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_ninos_necesidades getByNino: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Agregar una necesidad a un niño
     */
    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();

            if ($dataParam !== null) {
                $data = $dataParam;
            } else {
                $requestBody = Flight::request()->getBody();
                $data = json_decode($requestBody, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
                }
            }

            $sentence = $db->prepare("
                INSERT INTO visitas_ninos_necesidades(id_visita_nino, id_tipo_necesidad, detalle) 
                VALUES (:id_visita_nino, :id_tipo_necesidad, :detalle)
            ");
            
            $detalle = isset($data['detalle']) ? $data['detalle'] : null;
            
            $sentence->bindParam(':id_visita_nino', $data['id_visita_nino']);
            $sentence->bindParam(':id_tipo_necesidad', $data['id_tipo_necesidad']);
            $sentence->bindParam(':detalle', $detalle);
            $sentence->execute();
            
            $id = $db->lastInsertId();

            if ($dataParam !== null) {
                return $id;
            }
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_ninos_necesidades new: " . $e->getMessage());
            
            if ($dataParam !== null) {
                throw $e;
            }
            
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Actualizar el detalle de una necesidad
     */
    public static function replace($dataParam = null)
    {
        try {
            $db = Flight::db();

            if ($dataParam !== null) {
                $data = $dataParam;
            } else {
                $data = Flight::request()->data;
            }

            if (!isset($data['id']) || empty($data['id'])) {
                throw new Exception('ID de necesidad requerido para actualizar');
            }

            $sentence = $db->prepare("
                UPDATE visitas_ninos_necesidades 
                SET detalle = :detalle 
                WHERE id = :id
            ");
            
            $detalle = isset($data['detalle']) ? $data['detalle'] : null;
            
            $sentence->bindParam(':detalle', $detalle);
            $sentence->bindParam(':id', $data['id']);
            $sentence->execute();

            if ($dataParam !== null) {
                return true;
            }
            
            Flight::json(array('success' => true));
        } catch (Exception $e) {
            error_log("Error en visitas_ninos_necesidades replace: " . $e->getMessage());
            
            if ($dataParam !== null) {
                throw $e;
            }
            
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar una necesidad
     */
    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            
            $sentence = $db->prepare("DELETE FROM visitas_ninos_necesidades WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_ninos_necesidades delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar todas las necesidades de un niño (útil al guardar masivamente)
     */
    public static function deleteByNino($id_visita_nino)
    {
        try {
            $db = Flight::db();
            
            $sentence = $db->prepare("DELETE FROM visitas_ninos_necesidades WHERE id_visita_nino = :id_visita_nino");
            $sentence->bindParam(':id_visita_nino', $id_visita_nino);
            $sentence->execute();
            
            return true;
        } catch (Exception $e) {
            error_log("Error en visitas_ninos_necesidades deleteByNino: " . $e->getMessage());
            throw $e;
        }
    }
}