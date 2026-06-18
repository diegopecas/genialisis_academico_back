<?php
class VisitasRazonesBusqueda
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vrb.*,
                    trb.nombre as nombre_razon,
                    v.fecha as fecha_visita
                FROM visitas_razones_busqueda vrb
                INNER JOIN tipos_razones_busqueda trb ON vrb.id_razon = trb.id
                INNER JOIN visitas v ON vrb.id_visita = v.id
                WHERE vrb.id_tenant = :id_tenant
                ORDER BY v.fecha DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_razones_busqueda getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vrb.*,
                    trb.nombre as nombre_razon,
                    v.fecha as fecha_visita
                FROM visitas_razones_busqueda vrb
                INNER JOIN tipos_razones_busqueda trb ON vrb.id_razon = trb.id
                INNER JOIN visitas v ON vrb.id_visita = v.id
                WHERE vrb.id = :id
                AND vrb.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_razones_busqueda getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vrb.*,
                    trb.nombre as nombre_razon
                FROM visitas_razones_busqueda vrb
                INNER JOIN tipos_razones_busqueda trb ON vrb.id_razon = trb.id
                WHERE vrb.id_visita = :id_visita
                AND vrb.id_tenant = :id_tenant
                ORDER BY vrb.id
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_razones_busqueda getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // ✅ AGREGAR EL PARÁMETRO $dataParam
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
                INSERT INTO visitas_razones_busqueda (id, id_tenant, id_visita, id_razon)
                VALUES (:id, :id_tenant, :id_visita, :id_razon)
            ");

            $id_visita = $data['id_visita'] ?? null;
            $id_razon = $data['id_razon'] ?? null;
            $id = Uuid::generar();

            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindParam(':id_razon', $id_razon);

            $sentence->execute();
            
            // ✅ Si se llamó con parámetro, retornar el ID
            if ($dataParam !== null) {
                return $id;
            }
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_razones_busqueda new: " . $e->getMessage());
            
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

            $sentence = $db->prepare("DELETE FROM visitas_razones_busqueda WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_razones_busqueda delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // ✅ SIMPLIFICAR guardarMultiples - NO MANIPULAR Flight::request()->data
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
            $razones = $data['razones'] ?? []; // Array de IDs

            // Primero eliminar las existentes
            $stmt = $db->prepare("DELETE FROM visitas_razones_busqueda WHERE id_visita = :id_visita AND id_tenant = :id_tenant");
            $stmt->execute(['id_visita' => $id_visita, 'id_tenant' => TenantContext::id()]);

            // Insertar las nuevas usando el método new()
            if (is_array($razones) && count($razones) > 0) {
                foreach ($razones as $id_razon) {
                    $razonData = [
                        'id_visita' => $id_visita,
                        'id_razon' => $id_razon
                    ];

                    // ✅ PASAR DIRECTAMENTE EL ARRAY - SIN MANIPULAR Flight::request()
                    self::new($razonData);
                }
            }

            // ✅ Si se llamó con parámetro, retornar true
            if ($dataParam !== null) {
                return true;
            }

            Flight::json(array('success' => true, 'count' => count($razones)));
        } catch (Exception $e) {
            error_log("Error en visitas_razones_busqueda guardarMultiples: " . $e->getMessage());

            // ✅ Si se llamó con parámetro, lanzar excepción
            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}