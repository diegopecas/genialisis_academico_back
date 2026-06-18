<?php
class VisitasObjeciones
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vo.*,
                    tob.nombre as nombre_objecion,
                    v.fecha as fecha_visita
                FROM visitas_objeciones vo
                INNER JOIN tipos_objeciones tob ON vo.id_tipo_objecion = tob.id
                INNER JOIN visitas v ON vo.id_visita = v.id
                WHERE vo.id_tenant = :id_tenant
                ORDER BY v.fecha DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_objeciones getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vo.*,
                    tob.nombre as nombre_objecion,
                    tob.descripcion as descripcion_objecion,
                    v.fecha as fecha_visita
                FROM visitas_objeciones vo
                INNER JOIN tipos_objeciones tob ON vo.id_tipo_objecion = tob.id
                INNER JOIN visitas v ON vo.id_visita = v.id
                WHERE vo.id = :id
                AND vo.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_objeciones getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vo.*,
                    tob.nombre as nombre_objecion,
                    tob.descripcion as descripcion_objecion
                FROM visitas_objeciones vo
                INNER JOIN tipos_objeciones tob ON vo.id_tipo_objecion = tob.id
                WHERE vo.id_visita = :id_visita
                AND vo.id_tenant = :id_tenant
                ORDER BY vo.id
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_objeciones getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // ✅ MODIFICADO: Ahora acepta parámetros
    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();
            
            // ✅ Si se pasa parámetro, usarlo. Si no, tomar de Flight::request()
            $data = $dataParam ?? Flight::request()->data;

            $sentence = $db->prepare("
                INSERT INTO visitas_objeciones (
                    id, id_tenant, id_visita, id_tipo_objecion, como_se_manejo, superada, notas_adicionales
                ) VALUES (
                    :id, :id_tenant, :id_visita, :id_tipo_objecion, :como_se_manejo, :superada, :notas_adicionales
                )
            ");

            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':id_tipo_objecion', $data['id_tipo_objecion']);
            $sentence->bindParam(':como_se_manejo', $data['como_se_manejo']);
            $sentence->bindParam(':superada', $data['superada']);
            $sentence->bindParam(':notas_adicionales', $data['notas_adicionales']);

            $sentence->execute();
            
            // ✅ Si se llamó con parámetro, retornar el ID, sino usar Flight::json
            if ($dataParam !== null) {
                return $id;
            }
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_objeciones new: " . $e->getMessage());
            
            // ✅ Si se llamó con parámetro, lanzar excepción, sino usar Flight::json
            if ($dataParam !== null) {
                throw $e;
            }
            
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                UPDATE visitas_objeciones SET
                    id_tipo_objecion = :id_tipo_objecion,
                    como_se_manejo = :como_se_manejo,
                    superada = :superada,
                    notas_adicionales = :notas_adicionales
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':id_tipo_objecion', $data['id_tipo_objecion']);
            $sentence->bindParam(':como_se_manejo', $data['como_se_manejo']);
            $sentence->bindParam(':superada', $data['superada']);
            $sentence->bindParam(':notas_adicionales', $data['notas_adicionales']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_objeciones replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM visitas_objeciones WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_objeciones delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // ✅ REESCRITO: Ahora acepta parámetros y usa self::new()
    public static function guardarMultiples($dataParam = null)
    {
        try {
            $db = Flight::db();
            
            $data = $dataParam ?? Flight::request()->data;
            $id_visita = $data['id_visita'];
            $objeciones = $data['objeciones'] ?? [];

            error_log("📋 guardarMultiples objeciones - id_visita: $id_visita");
            error_log("📋 Objeciones recibidas: " . json_encode($objeciones));

            // ✅ Eliminar las objeciones existentes
            $stmt = $db->prepare("DELETE FROM visitas_objeciones WHERE id_visita = :id_visita AND id_tenant = :id_tenant");
            $stmt->execute(['id_visita' => $id_visita, 'id_tenant' => TenantContext::id()]);

            $totalInsertadas = 0;

            // ✅ Insertar las nuevas objeciones usando self::new()
            if (is_array($objeciones) && count($objeciones) > 0) {
                foreach ($objeciones as $obj) {
                    // Preparar datos para insertar
                    $nuevaObjecion = [
                        'id_visita' => $id_visita,
                        'id_tipo_objecion' => $obj['id_tipo_objecion'] ?? null,
                        'como_se_manejo' => $obj['como_se_manejo'] ?? null,
                        'superada' => 0,
                        'notas_adicionales' => $obj['notas_adicionales'] ?? null
                    ];

                    // ✅ Convertir booleanos a 1/0
                    if (isset($obj['superada'])) {
                        $nuevaObjecion['superada'] = ($obj['superada'] === true || $obj['superada'] === 1 || $obj['superada'] === '1') ? 1 : 0;
                    }

                    // Solo insertar si tiene id_tipo_objecion válido
                    if ($nuevaObjecion['id_tipo_objecion'] !== null) {
                        error_log("📝 Insertando objeción: " . json_encode($nuevaObjecion));
                        
                        // ✅ LLAMAR A self::new() - SIN SQL DIRECTO
                        self::new($nuevaObjecion);
                        
                        $totalInsertadas++;
                        error_log("✅ Insertada objeción: " . $nuevaObjecion['id_tipo_objecion']);
                    }
                }
            }

            error_log("✅ Total objeciones insertadas: $totalInsertadas");

            if ($dataParam !== null) {
                return ['success' => true, 'count' => $totalInsertadas];
            }

            Flight::json(array('success' => true, 'count' => $totalInsertadas));
        } catch (Exception $e) {
            error_log("❌ Error en visitas_objeciones guardarMultiples: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Obtener estadísticas de objeciones más frecuentes
    public static function getEstadisticas()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    tob.nombre as objecion,
                    COUNT(*) as total_veces,
                    SUM(CASE WHEN vo.superada = 1 THEN 1 ELSE 0 END) as veces_superada,
                    ROUND((SUM(CASE WHEN vo.superada = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as porcentaje_superada
                FROM visitas_objeciones vo
                INNER JOIN tipos_objeciones tob ON vo.id_tipo_objecion = tob.id
                WHERE vo.id_tenant = :id_tenant
                GROUP BY vo.id_tipo_objecion, tob.nombre
                ORDER BY total_veces DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_objeciones getEstadisticas: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}