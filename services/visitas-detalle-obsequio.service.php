<?php
class VisitasDetalleObsequio
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vdo.*,
                    tid.nombre as nombre_importancia_detalle,
                    tna.nombre as nombre_nivel_agradecimiento,
                    v.fecha as fecha_visita
                FROM visitas_detalle_obsequio vdo
                LEFT JOIN tipos_importancia_detalle tid ON vdo.id_importancia_detalle = tid.id
                LEFT JOIN tipos_nivel_agradecimiento tna ON vdo.id_nivel_agradecimiento = tna.id
                INNER JOIN visitas v ON vdo.id_visita = v.id
                WHERE vdo.id_tenant = :id_tenant
                ORDER BY v.fecha DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_detalle_obsequio getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vdo.*,
                    tid.nombre as nombre_importancia_detalle,
                    tna.nombre as nombre_nivel_agradecimiento
                FROM visitas_detalle_obsequio vdo
                LEFT JOIN tipos_importancia_detalle tid ON vdo.id_importancia_detalle = tid.id
                LEFT JOIN tipos_nivel_agradecimiento tna ON vdo.id_nivel_agradecimiento = tna.id
                WHERE vdo.id = :id
                AND vdo.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_detalle_obsequio getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vdo.*,
                    tid.nombre as nombre_importancia_detalle,
                    tna.nombre as nombre_nivel_agradecimiento
                FROM visitas_detalle_obsequio vdo
                LEFT JOIN tipos_importancia_detalle tid ON vdo.id_importancia_detalle = tid.id
                LEFT JOIN tipos_nivel_agradecimiento tna ON vdo.id_nivel_agradecimiento = tna.id
                WHERE vdo.id_visita = :id_visita
                AND vdo.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_detalle_obsequio getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();
            $data = $dataParam ?? Flight::request()->data;
            $id = Uuid::generar();

            $sentence = $db->prepare("
            INSERT INTO visitas_detalle_obsequio (
                id, id_tenant, id_visita, dio_detalle, que_detalle, id_importancia_detalle,
                id_nivel_agradecimiento, comentarios_detalle
            ) VALUES (
                :id, :id_tenant, :id_visita, :dio_detalle, :que_detalle, :id_importancia_detalle,
                :id_nivel_agradecimiento, :comentarios_detalle
            )
        ");

            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':dio_detalle', $data['dio_detalle']);
            $sentence->bindParam(':que_detalle', $data['que_detalle']);
            $sentence->bindParam(':id_importancia_detalle', $data['id_importancia_detalle']);
            $sentence->bindParam(':id_nivel_agradecimiento', $data['id_nivel_agradecimiento']);
            $sentence->bindParam(':comentarios_detalle', $data['comentarios_detalle']);

            $sentence->execute();

            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_detalle_obsequio new: " . $e->getMessage());

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
            UPDATE visitas_detalle_obsequio SET
                dio_detalle = :dio_detalle,
                que_detalle = :que_detalle,
                id_importancia_detalle = :id_importancia_detalle,
                id_nivel_agradecimiento = :id_nivel_agradecimiento,
                comentarios_detalle = :comentarios_detalle
            WHERE id = :id
            AND id_tenant = :id_tenant
        ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':dio_detalle', $data['dio_detalle']);
            $sentence->bindParam(':que_detalle', $data['que_detalle']);
            $sentence->bindParam(':id_importancia_detalle', $data['id_importancia_detalle']);
            $sentence->bindParam(':id_nivel_agradecimiento', $data['id_nivel_agradecimiento']);
            $sentence->bindParam(':comentarios_detalle', $data['comentarios_detalle']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();

            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_detalle_obsequio replace: " . $e->getMessage());

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

            $sentence = $db->prepare("DELETE FROM visitas_detalle_obsequio WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_detalle_obsequio delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Guardar o actualizar (upsert)
    public static function guardar($dataParam = null)
    {
        try {
            $db = Flight::db();

            $data = $dataParam ?? Flight::request()->data;
            $id_visita = $data['id_visita'];

            // Verificar si ya existe
            $stmt = $db->prepare("SELECT id FROM visitas_detalle_obsequio WHERE id_visita = :id_visita AND id_tenant = :id_tenant");
            $stmt->execute(['id_visita' => $id_visita, 'id_tenant' => TenantContext::id()]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existe) {
                $data['id'] = $existe['id'];
                self::replace($data);
            } else {
                self::new($data);
            }

            if ($dataParam !== null) {
                return ['success' => true];
            }

            Flight::json(array('success' => true));
        } catch (Exception $e) {
            error_log("Error en visitas_detalle_obsequio guardar: " . $e->getMessage());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
