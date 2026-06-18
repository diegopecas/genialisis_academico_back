<?php
class VisitasAspectosPositivos
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vap.*,
                    v.fecha as fecha_visita
                FROM visitas_aspectos_positivos vap
                INNER JOIN visitas v ON vap.id_visita = v.id
                WHERE vap.id_tenant = :id_tenant
                ORDER BY v.fecha DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_aspectos_positivos getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT vap.*, v.fecha as fecha_visita
                FROM visitas_aspectos_positivos vap
                INNER JOIN visitas v ON vap.id_visita = v.id
                WHERE vap.id = :id
                AND vap.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_aspectos_positivos getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT * FROM visitas_aspectos_positivos 
                WHERE id_visita = :id_visita
                AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_aspectos_positivos getByVisita: " . $e->getMessage());
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
            INSERT INTO visitas_aspectos_positivos (
                id, id_tenant, id_visita, otros_aspectos, factor_decisivo
            ) VALUES (
                :id, :id_tenant, :id_visita, :otros_aspectos, :factor_decisivo
            )
        ");

            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':otros_aspectos', $data['otros_aspectos']);
            $sentence->bindParam(':factor_decisivo', $data['factor_decisivo']);

            $sentence->execute();

            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_aspectos_positivos new: " . $e->getMessage());

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
            UPDATE visitas_aspectos_positivos SET
                otros_aspectos = :otros_aspectos,
                factor_decisivo = :factor_decisivo
            WHERE id = :id
            AND id_tenant = :id_tenant
        ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':otros_aspectos', $data['otros_aspectos']);
            $sentence->bindParam(':factor_decisivo', $data['factor_decisivo']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();

            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_aspectos_positivos replace: " . $e->getMessage());

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

            $sentence = $db->prepare("DELETE FROM visitas_aspectos_positivos WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_aspectos_positivos delete: " . $e->getMessage());
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
            $stmt = $db->prepare("SELECT id FROM visitas_aspectos_positivos WHERE id_visita = :id_visita AND id_tenant = :id_tenant");
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
            error_log("Error en visitas_aspectos_positivos guardar: " . $e->getMessage());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
