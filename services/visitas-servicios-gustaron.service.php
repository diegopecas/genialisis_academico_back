<?php
class VisitasServiciosGustaron
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vsg.*,
                    sj.nombre as nombre_servicio,
                    v.fecha as fecha_visita
                FROM visitas_servicios_gustaron vsg
                INNER JOIN servicios_jardin sj ON vsg.id_servicio = sj.id
                INNER JOIN visitas v ON vsg.id_visita = v.id
                WHERE vsg.id_tenant = :id_tenant
                ORDER BY v.fecha DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_servicios_gustaron getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vsg.*,
                    sj.nombre as nombre_servicio
                FROM visitas_servicios_gustaron vsg
                INNER JOIN servicios_jardin sj ON vsg.id_servicio = sj.id
                WHERE vsg.id = :id AND vsg.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_servicios_gustaron getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vsg.*,
                    sj.nombre as nombre_servicio
                FROM visitas_servicios_gustaron vsg
                INNER JOIN servicios_jardin sj ON vsg.id_servicio = sj.id
                WHERE vsg.id_visita = :id_visita AND vsg.id_tenant = :id_tenant
                ORDER BY vsg.id
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_servicios_gustaron getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();
            $data = $dataParam ?? Flight::request()->data;

            $idNew = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO visitas_servicios_gustaron (id, id_tenant, id_visita, id_servicio)
                VALUES (:id, :id_tenant, :id_visita, :id_servicio)
            ");

            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':id_servicio', $data['id_servicio']);

            $sentence->execute();
            $id = $idNew;

            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_servicios_gustaron new: " . $e->getMessage());

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

            $sentence = $db->prepare("DELETE FROM visitas_servicios_gustaron WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_servicios_gustaron delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Guardar múltiples servicios de una vez
    public static function guardarMultiples($dataParam = null)
    {
        try {
            $db = Flight::db();

            $data = $dataParam ?? Flight::request()->data;
            $id_visita = $data['id_visita'];
            $servicios = $data['servicios'] ?? []; // ✅ Default a array vacío

            error_log("📋 guardarMultiples - id_visita: $id_visita");
            error_log("📋 Servicios recibidos: " . json_encode($servicios));

            // Primero eliminar los existentes
            $stmt = $db->prepare("DELETE FROM visitas_servicios_gustaron WHERE id_visita = :id_visita AND id_tenant = :id_tenant");
            $stmt->execute(['id_visita' => $id_visita, 'id_tenant' => TenantContext::id()]);

            $totalInsertados = 0;

            // Insertar los nuevos solo si hay servicios
            if (is_array($servicios) && count($servicios) > 0) {
                foreach ($servicios as $servicio) {
                    // ✅ Manejar si viene como objeto o como ID simple
                    $id_servicio = is_array($servicio) ? ($servicio['id_servicio'] ?? $servicio['id'] ?? null) : $servicio;

                    if ($id_servicio !== null) {
                        // ✅ Usar self::new() en lugar de ejecutar directamente
                        $nuevoServicio = [
                            'id_visita' => $id_visita,
                            'id_servicio' => $id_servicio
                        ];

                        self::new($nuevoServicio);
                        $totalInsertados++;
                        error_log("✅ Insertado servicio: $id_servicio");
                    }
                }
            }

            error_log("✅ Total servicios insertados: $totalInsertados");

            if ($dataParam !== null) {
                return ['success' => true, 'count' => $totalInsertados];
            }

            Flight::json(array('success' => true, 'count' => $totalInsertados));
        } catch (Exception $e) {
            error_log("❌ Error en visitas_servicios_gustaron guardarMultiples: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Obtener servicios más valorados
    public static function getServiciosMasValorados()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    sj.nombre as servicio,
                    COUNT(*) as veces_destacado,
                    ROUND((COUNT(*) / (SELECT COUNT(DISTINCT id_visita) FROM visitas_servicios_gustaron WHERE id_tenant = :id_tenant_sub)) * 100, 2) as porcentaje
                FROM visitas_servicios_gustaron vsg
                INNER JOIN servicios_jardin sj ON vsg.id_servicio = sj.id
                WHERE vsg.id_tenant = :id_tenant
                GROUP BY vsg.id_servicio, sj.nombre
                ORDER BY veces_destacado DESC
            ");
            $sentence->bindValue(':id_tenant_sub', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_servicios_gustaron getServiciosMasValorados: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}