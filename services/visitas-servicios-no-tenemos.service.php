<?php
class VisitasServiciosNoTenemos
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vsnt.*,
                    sf.nombre as nombre_servicio,
                    tisf.nombre as nombre_importancia,
                    v.fecha as fecha_visita
                FROM visitas_servicios_no_tenemos vsnt
                INNER JOIN servicios_faltantes sf ON vsnt.id_servicio_faltante = sf.id
                LEFT JOIN tipos_importancia_servicio_faltante tisf ON vsnt.id_importancia = tisf.id
                INNER JOIN visitas v ON vsnt.id_visita = v.id
                WHERE vsnt.id_tenant = :id_tenant
                ORDER BY v.fecha DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_servicios_no_tenemos getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vsnt.*,
                    sf.nombre as nombre_servicio,
                    tisf.nombre as nombre_importancia
                FROM visitas_servicios_no_tenemos vsnt
                INNER JOIN servicios_faltantes sf ON vsnt.id_servicio_faltante = sf.id
                LEFT JOIN tipos_importancia_servicio_faltante tisf ON vsnt.id_importancia = tisf.id
                WHERE vsnt.id = :id AND vsnt.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_servicios_no_tenemos getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vsnt.*,
                    sf.nombre as nombre_servicio,
                    tisf.nombre as nombre_importancia
                FROM visitas_servicios_no_tenemos vsnt
                INNER JOIN servicios_faltantes sf ON vsnt.id_servicio_faltante = sf.id
                LEFT JOIN tipos_importancia_servicio_faltante tisf ON vsnt.id_importancia = tisf.id
                WHERE vsnt.id_visita = :id_visita AND vsnt.id_tenant = :id_tenant
                ORDER BY vsnt.id
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_servicios_no_tenemos getByVisita: " . $e->getMessage());
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
                INSERT INTO visitas_servicios_no_tenemos (
                    id, id_tenant, id_visita, id_servicio_faltante, detalle_especifico, id_importancia, perdimos_venta_por_esto
                ) VALUES (
                    :id, :id_tenant, :id_visita, :id_servicio_faltante, :detalle_especifico, :id_importancia, :perdimos_venta_por_esto
                )
            ");

            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':id_servicio_faltante', $data['id_servicio_faltante']);
            $sentence->bindParam(':detalle_especifico', $data['detalle_especifico']);
            $sentence->bindParam(':id_importancia', $data['id_importancia']);
            $sentence->bindParam(':perdimos_venta_por_esto', $data['perdimos_venta_por_esto']);


            $sentence->execute();


            $id = $idNew;


            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("❌ ERROR en visitas_servicios_no_tenemos new: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());

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
            UPDATE visitas_servicios_no_tenemos SET
                id_servicio_faltante = :id_servicio_faltante,
                detalle_especifico = :detalle_especifico,
                id_importancia = :id_importancia,
                perdimos_venta_por_esto = :perdimos_venta_por_esto
            WHERE id = :id AND id_tenant = :id_tenant
        ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_servicio_faltante', $data['id_servicio_faltante']);
            $sentence->bindParam(':detalle_especifico', $data['detalle_especifico']);
            $sentence->bindParam(':id_importancia', $data['id_importancia']);
            $sentence->bindParam(':perdimos_venta_por_esto', $data['perdimos_venta_por_esto']);

            $sentence->execute();

            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_servicios_no_tenemos replace: " . $e->getMessage());

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

            $sentence = $db->prepare("DELETE FROM visitas_servicios_no_tenemos WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_servicios_no_tenemos delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // ✅ Guardar múltiples servicios usando self::new()
    public static function guardarMultiples($dataParam = null)
    {
        try {
            $db = Flight::db();

            $data = $dataParam ?? Flight::request()->data;

            $id_visita = $data['id_visita'];
            $servicios = $data['servicios'] ?? [];

            // Primero eliminar los existentes
            $stmt = $db->prepare("DELETE FROM visitas_servicios_no_tenemos WHERE id_visita = :id_visita AND id_tenant = :id_tenant");
            $stmt->execute(['id_visita' => $id_visita, 'id_tenant' => TenantContext::id()]);


            // Insertar los nuevos usando self::new()
            if (is_array($servicios) && count($servicios) > 0) {


                foreach ($servicios as $servicio) {


                    $servicio['id_visita'] = $id_visita;
                    $idInsertado = self::new($servicio);


                }
            }

            if ($dataParam !== null) {
                return ['success' => true, 'count' => count($servicios)];
            }

            Flight::json(array('success' => true, 'count' => count($servicios)));
        } catch (Exception $e) {
            error_log("Error en visitas_servicios_no_tenemos guardarMultiples: " . $e->getMessage());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Obtener servicios más solicitados
    public static function getServiciosMasSolicitados()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    sf.nombre as servicio,
                    COUNT(*) as veces_solicitado,
                    SUM(CASE WHEN vsnt.perdimos_venta_por_esto = 'si' THEN 1 ELSE 0 END) as perdimos_venta
                FROM visitas_servicios_no_tenemos vsnt
                INNER JOIN servicios_faltantes sf ON vsnt.id_servicio_faltante = sf.id
                WHERE vsnt.id_tenant = :id_tenant
                GROUP BY vsnt.id_servicio_faltante, sf.nombre
                ORDER BY veces_solicitado DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_servicios_no_tenemos getServiciosMasSolicitados: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
