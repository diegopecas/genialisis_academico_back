<?php
class VisitasAprendizajes
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    va.*,
                    v.fecha as fecha_visita
                FROM visitas_aprendizajes va
                INNER JOIN visitas v ON va.id_visita = v.id
                WHERE va.id_tenant = :id_tenant
                ORDER BY v.fecha DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_aprendizajes getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    va.*,
                    v.fecha as fecha_visita
                FROM visitas_aprendizajes va
                INNER JOIN visitas v ON va.id_visita = v.id
                WHERE va.id = :id
                AND va.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_aprendizajes getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT * FROM visitas_aprendizajes 
                WHERE id_visita = :id_visita
                AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_aprendizajes getByVisita: " . $e->getMessage());
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
            INSERT INTO visitas_aprendizajes (
                id, id_tenant, id_visita, que_salio_bien, que_mejorar_proximo, que_sorprendio,
                recomendaciones_equipo, resumen_ejecutivo
            ) VALUES (
                :id, :id_tenant, :id_visita, :que_salio_bien, :que_mejorar_proximo, :que_sorprendio,
                :recomendaciones_equipo, :resumen_ejecutivo
            )
        ");

            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':que_salio_bien', $data['que_salio_bien']);
            $sentence->bindParam(':que_mejorar_proximo', $data['que_mejorar_proximo']);
            $sentence->bindParam(':que_sorprendio', $data['que_sorprendio']);
            $sentence->bindParam(':recomendaciones_equipo', $data['recomendaciones_equipo']);
            $sentence->bindParam(':resumen_ejecutivo', $data['resumen_ejecutivo']);

            $sentence->execute();

            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_aprendizajes new: " . $e->getMessage());

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
            UPDATE visitas_aprendizajes SET
                que_salio_bien = :que_salio_bien,
                que_mejorar_proximo = :que_mejorar_proximo,
                que_sorprendio = :que_sorprendio,
                recomendaciones_equipo = :recomendaciones_equipo,
                resumen_ejecutivo = :resumen_ejecutivo
            WHERE id = :id
            AND id_tenant = :id_tenant
        ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':que_salio_bien', $data['que_salio_bien']);
            $sentence->bindParam(':que_mejorar_proximo', $data['que_mejorar_proximo']);
            $sentence->bindParam(':que_sorprendio', $data['que_sorprendio']);
            $sentence->bindParam(':recomendaciones_equipo', $data['recomendaciones_equipo']);
            $sentence->bindParam(':resumen_ejecutivo', $data['resumen_ejecutivo']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();

            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_aprendizajes replace: " . $e->getMessage());

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

            $sentence = $db->prepare("DELETE FROM visitas_aprendizajes WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_aprendizajes delete: " . $e->getMessage());
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
            $stmt = $db->prepare("SELECT id FROM visitas_aprendizajes WHERE id_visita = :id_visita AND id_tenant = :id_tenant");
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
            error_log("Error en visitas_aprendizajes guardar: " . $e->getMessage());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Obtener aprendizajes recientes para el equipo
    public static function getAprendizajesRecientes($limite = 10)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    va.*,
                    v.fecha as fecha_visita,
                    CONCAT(u.usuario, ' - ', p.primer_nombre, ' ', p.primer_apellido) as asesor
                FROM visitas_aprendizajes va
                INNER JOIN visitas v ON va.id_visita = v.id
                INNER JOIN usuarios u ON v.id_usuario_registro = u.id
                LEFT JOIN personas p ON u.id_persona = p.id
                WHERE va.recomendaciones_equipo IS NOT NULL AND va.recomendaciones_equipo != ''
                AND va.id_tenant = :id_tenant
                ORDER BY v.fecha DESC
                LIMIT :limite
            ");
            $sentence->bindParam(':limite', $limite, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_aprendizajes getAprendizajesRecientes: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
