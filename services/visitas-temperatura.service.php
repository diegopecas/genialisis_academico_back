<?php
class VisitasTemperatura
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vt.*,
                    tni.nombre as nombre_nivel_interes,
                    tni.codigo as codigo_nivel_interes,
                    tu.nombre as nombre_urgencia,
                    tu.codigo as codigo_urgencia,
                    v.fecha as fecha_visita
                FROM visitas_temperatura vt
                LEFT JOIN tipos_nivel_interes tni ON vt.id_nivel_interes = tni.id
                LEFT JOIN tipos_urgencia tu ON vt.id_urgencia = tu.id
                INNER JOIN visitas v ON vt.id_visita = v.id
                WHERE vt.id_tenant = :id_tenant
                ORDER BY v.fecha DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_temperatura getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vt.*,
                    tni.nombre as nombre_nivel_interes,
                    tni.codigo as codigo_nivel_interes,
                    tu.nombre as nombre_urgencia,
                    tu.codigo as codigo_urgencia,
                    v.fecha as fecha_visita
                FROM visitas_temperatura vt
                LEFT JOIN tipos_nivel_interes tni ON vt.id_nivel_interes = tni.id
                LEFT JOIN tipos_urgencia tu ON vt.id_urgencia = tu.id
                INNER JOIN visitas v ON vt.id_visita = v.id
                WHERE vt.id = :id
                AND vt.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_temperatura getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vt.*,
                    tni.nombre as nombre_nivel_interes,
                    tni.codigo as codigo_nivel_interes,
                    tu.nombre as nombre_urgencia,
                    tu.codigo as codigo_urgencia
                FROM visitas_temperatura vt
                LEFT JOIN tipos_nivel_interes tni ON vt.id_nivel_interes = tni.id
                LEFT JOIN tipos_urgencia tu ON vt.id_urgencia = tu.id
                WHERE vt.id_visita = :id_visita
                AND vt.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_temperatura getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

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
                INSERT INTO visitas_temperatura (
                    id, id_tenant, id_visita, id_nivel_interes, id_urgencia, 
                    pidio_descuento, visito_instalaciones_completas, pregunto_por_matricula
                ) VALUES (
                    :id, :id_tenant, :id_visita, :id_nivel_interes, :id_urgencia,
                    :pidio_descuento, :visito_instalaciones_completas, :pregunto_por_matricula
                )
            ");

            // ✅ Obtener valores con manejo de NULL
            $id_visita = $data['id_visita'] ?? null;
            $id_nivel_interes = $data['id_nivel_interes'] ?? null;
            $id_urgencia = $data['id_urgencia'] ?? null;
            
            // ✅ Convertir booleanos a TINYINT
            $pidio_descuento = isset($data['pidio_descuento']) ? ($data['pidio_descuento'] ? 1 : 0) : 0;
            $visito_instalaciones_completas = isset($data['visito_instalaciones_completas']) ? ($data['visito_instalaciones_completas'] ? 1 : 0) : 0;
            $pregunto_por_matricula = isset($data['pregunto_por_matricula']) ? ($data['pregunto_por_matricula'] ? 1 : 0) : 0;

            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindParam(':id_nivel_interes', $id_nivel_interes);
            $sentence->bindParam(':id_urgencia', $id_urgencia);
            $sentence->bindParam(':pidio_descuento', $pidio_descuento);
            $sentence->bindParam(':visito_instalaciones_completas', $visito_instalaciones_completas);
            $sentence->bindParam(':pregunto_por_matricula', $pregunto_por_matricula);

            $sentence->execute();

            // ✅ Si se llamó con parámetro, retornar el ID
            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_temperatura new: " . $e->getMessage());

            // ✅ Si se llamó con parámetro, lanzar excepción
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

            // ✅ Si se pasa parámetro, usarlo. Si no, tomar de Flight::request()
            if ($dataParam !== null) {
                $data = $dataParam;
            } else {
                $data = Flight::request()->data;
            }

            $sentence = $db->prepare("
                UPDATE visitas_temperatura SET
                    id_nivel_interes = :id_nivel_interes,
                    id_urgencia = :id_urgencia,
                    pidio_descuento = :pidio_descuento,
                    visito_instalaciones_completas = :visito_instalaciones_completas,
                    pregunto_por_matricula = :pregunto_por_matricula
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");

            $id = $data['id'];
            $id_nivel_interes = $data['id_nivel_interes'] ?? null;
            $id_urgencia = $data['id_urgencia'] ?? null;
            
            // ✅ Convertir booleanos a TINYINT
            $pidio_descuento = isset($data['pidio_descuento']) ? ($data['pidio_descuento'] ? 1 : 0) : 0;
            $visito_instalaciones_completas = isset($data['visito_instalaciones_completas']) ? ($data['visito_instalaciones_completas'] ? 1 : 0) : 0;
            $pregunto_por_matricula = isset($data['pregunto_por_matricula']) ? ($data['pregunto_por_matricula'] ? 1 : 0) : 0;

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_nivel_interes', $id_nivel_interes);
            $sentence->bindParam(':id_urgencia', $id_urgencia);
            $sentence->bindParam(':pidio_descuento', $pidio_descuento);
            $sentence->bindParam(':visito_instalaciones_completas', $visito_instalaciones_completas);
            $sentence->bindParam(':pregunto_por_matricula', $pregunto_por_matricula);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();

            // ✅ Si se llamó con parámetro, retornar true
            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_temperatura replace: " . $e->getMessage());

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

            $sentence = $db->prepare("DELETE FROM visitas_temperatura WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_temperatura delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // ✅ Método para actualizar o crear temperatura (upsert) - USA new() y replace()
    public static function guardarTemperatura($dataParam = null)
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

            // Verificar si ya existe
            $stmt = $db->prepare("SELECT id FROM visitas_temperatura WHERE id_visita = :id_visita AND id_tenant = :id_tenant");
            $stmt->execute(['id_visita' => $id_visita, 'id_tenant' => TenantContext::id()]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existe) {
                // ✅ Actualizar usando replace()
                $data['id'] = $existe['id'];
                self::replace($data);
                $id = $existe['id'];

                // ✅ Si se llamó con parámetro, retornar el ID
                if ($dataParam !== null) {
                    return $id;
                }

                Flight::json(array('success' => true, 'id' => $id, 'action' => 'updated'));
            } else {
                // ✅ Insertar usando new()
                $id = self::new($data);

                // ✅ Si se llamó con parámetro, retornar el ID
                if ($dataParam !== null) {
                    return $id;
                }

                Flight::json(array('success' => true, 'id' => $id, 'action' => 'created'));
            }
        } catch (Exception $e) {
            error_log("Error en visitas_temperatura guardarTemperatura: " . $e->getMessage());

            // ✅ Si se llamó con parámetro, lanzar excepción
            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}