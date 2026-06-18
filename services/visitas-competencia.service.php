<?php
class VisitasCompetencia
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vc.*,
                    tid.nombre as nombre_inclinacion,
                    v.fecha as fecha_visita
                FROM visitas_competencia vc
                LEFT JOIN tipos_inclinacion_decision tid ON vc.id_hacia_donde_se_inclinan = tid.id
                INNER JOIN visitas v ON vc.id_visita = v.id
                WHERE vc.id_tenant = :id_tenant
                ORDER BY v.fecha DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_competencia getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vc.*,
                    tid.nombre as nombre_inclinacion
                FROM visitas_competencia vc
                LEFT JOIN tipos_inclinacion_decision tid ON vc.id_hacia_donde_se_inclinan = tid.id
                WHERE vc.id = :id
                AND vc.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_competencia getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vc.*,
                    tid.nombre as nombre_inclinacion
                FROM visitas_competencia vc
                LEFT JOIN tipos_inclinacion_decision tid ON vc.id_hacia_donde_se_inclinan = tid.id
                WHERE vc.id_visita = :id_visita
                AND vc.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_competencia getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();
            $data = $dataParam ?? Flight::request()->data;

            $sentence = $db->prepare("
            INSERT INTO visitas_competencia (
                id, id_tenant, id_visita, menciono_competencia, jardines_mencionados, que_les_gusto_competencia,
                por_que_nos_consideran, principal_competidor, id_hacia_donde_se_inclinan
            ) VALUES (
                :id, :id_tenant, :id_visita, :menciono_competencia, :jardines_mencionados, :que_les_gusto_competencia,
                :por_que_nos_consideran, :principal_competidor, :id_hacia_donde_se_inclinan
            )
        ");

            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':menciono_competencia', $data['menciono_competencia']);
            $sentence->bindParam(':jardines_mencionados', $data['jardines_mencionados']);
            $sentence->bindParam(':que_les_gusto_competencia', $data['que_les_gusto_competencia']);
            $sentence->bindParam(':por_que_nos_consideran', $data['por_que_nos_consideran']);
            $sentence->bindParam(':principal_competidor', $data['principal_competidor']);
            $sentence->bindParam(':id_hacia_donde_se_inclinan', $data['id_hacia_donde_se_inclinan']);

            $sentence->execute();

            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_competencia new: " . $e->getMessage());

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
            UPDATE visitas_competencia SET
                menciono_competencia = :menciono_competencia,
                jardines_mencionados = :jardines_mencionados,
                que_les_gusto_competencia = :que_les_gusto_competencia,
                por_que_nos_consideran = :por_que_nos_consideran,
                principal_competidor = :principal_competidor,
                id_hacia_donde_se_inclinan = :id_hacia_donde_se_inclinan
            WHERE id = :id
            AND id_tenant = :id_tenant
        ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':menciono_competencia', $data['menciono_competencia']);
            $sentence->bindParam(':jardines_mencionados', $data['jardines_mencionados']);
            $sentence->bindParam(':que_les_gusto_competencia', $data['que_les_gusto_competencia']);
            $sentence->bindParam(':por_que_nos_consideran', $data['por_que_nos_consideran']);
            $sentence->bindParam(':principal_competidor', $data['principal_competidor']);
            $sentence->bindParam(':id_hacia_donde_se_inclinan', $data['id_hacia_donde_se_inclinan']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();

            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_competencia replace: " . $e->getMessage());

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

            $sentence = $db->prepare("DELETE FROM visitas_competencia WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_competencia delete: " . $e->getMessage());
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
            $stmt = $db->prepare("SELECT id FROM visitas_competencia WHERE id_visita = :id_visita AND id_tenant = :id_tenant");
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
            error_log("Error en visitas_competencia guardar: " . $e->getMessage());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Obtener competidores más mencionados
    public static function getCompetidoresMasMencionados()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    principal_competidor as competidor,
                    COUNT(*) as veces_mencionado
                FROM visitas_competencia
                WHERE principal_competidor IS NOT NULL AND principal_competidor != ''
                AND id_tenant = :id_tenant
                GROUP BY principal_competidor
                ORDER BY veces_mencionado DESC
                LIMIT 10
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_competencia getCompetidoresMasMencionados: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
