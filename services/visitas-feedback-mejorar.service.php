<?php
class VisitasFeedbackMejorar
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vfm.*,
                    am.nombre as nombre_aspecto,
                    tvf.nombre as nombre_validez,
                    v.fecha as fecha_visita
                FROM visitas_feedback_mejorar vfm
                INNER JOIN aspectos_mejorar am ON vfm.id_aspecto_mejorar = am.id
                LEFT JOIN tipos_validez_feedback tvf ON vfm.id_validez_feedback = tvf.id
                INNER JOIN visitas v ON vfm.id_visita = v.id
                WHERE vfm.id_tenant = :id_tenant
                ORDER BY v.fecha DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_feedback_mejorar getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vfm.*,
                    am.nombre as nombre_aspecto,
                    tvf.nombre as nombre_validez
                FROM visitas_feedback_mejorar vfm
                INNER JOIN aspectos_mejorar am ON vfm.id_aspecto_mejorar = am.id
                LEFT JOIN tipos_validez_feedback tvf ON vfm.id_validez_feedback = tvf.id
                WHERE vfm.id = :id
                AND vfm.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_feedback_mejorar getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vfm.*,
                    am.nombre as nombre_aspecto,
                    tvf.nombre as nombre_validez
                FROM visitas_feedback_mejorar vfm
                INNER JOIN aspectos_mejorar am ON vfm.id_aspecto_mejorar = am.id
                LEFT JOIN tipos_validez_feedback tvf ON vfm.id_validez_feedback = tvf.id
                WHERE vfm.id_visita = :id_visita
                AND vfm.id_tenant = :id_tenant
                ORDER BY vfm.id
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_feedback_mejorar getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();

            $data = $dataParam ?? Flight::request()->data;

            $sentence = $db->prepare("
            INSERT INTO visitas_feedback_mejorar (
                id, id_tenant, id_visita, id_aspecto_mejorar, comentarios_mejorar, id_validez_feedback
            ) VALUES (
                :id, :id_tenant, :id_visita, :id_aspecto_mejorar, :comentarios_mejorar, :id_validez_feedback
            )
        ");

            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':id_aspecto_mejorar', $data['id_aspecto_mejorar']);
            $sentence->bindParam(':comentarios_mejorar', $data['comentarios_mejorar']);
            $sentence->bindParam(':id_validez_feedback', $data['id_validez_feedback']);

            $sentence->execute();

            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_feedback_mejorar new: " . $e->getMessage());

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
                UPDATE visitas_feedback_mejorar SET
                    id_aspecto_mejorar = :id_aspecto_mejorar,
                    comentarios_mejorar = :comentarios_mejorar,
                    id_validez_feedback = :id_validez_feedback
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':id_aspecto_mejorar', $data['id_aspecto_mejorar']);
            $sentence->bindParam(':comentarios_mejorar', $data['comentarios_mejorar']);
            $sentence->bindParam(':id_validez_feedback', $data['id_validez_feedback']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_feedback_mejorar replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM visitas_feedback_mejorar WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_feedback_mejorar delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Guardar múltiples feedbacks
    public static function guardarMultiples($dataParam = null)
    {
        try {
            $db = Flight::db();

            $data = $dataParam ?? Flight::request()->data;
            $id_visita = $data['id_visita'];
            $feedbacks = $data['feedbacks'] ?? [];

            error_log("📋 guardarMultiples feedbacks - id_visita: $id_visita");
            error_log("📋 Feedbacks recibidos: " . json_encode($feedbacks));

            // ✅ Eliminar feedbacks existentes
            $stmt = $db->prepare("DELETE FROM visitas_feedback_mejorar WHERE id_visita = :id_visita AND id_tenant = :id_tenant");
            $stmt->execute(['id_visita' => $id_visita, 'id_tenant' => TenantContext::id()]);

            $totalInsertados = 0;

            // Insertar los nuevos
            if (is_array($feedbacks) && count($feedbacks) > 0) {
                foreach ($feedbacks as $feedback) {
                    // ✅ Manejar si viene como objeto completo o estructura simple
                    $nuevoFeedback = [
                        'id_visita' => $id_visita,
                        'id_aspecto_mejorar' => null,
                        'comentarios_mejorar' => null,
                        'id_validez_feedback' => null
                    ];

                    // ✅ Extraer datos según la estructura
                    if (is_array($feedback)) {
                        // ✅ CRÍTICO: Manejar cuando id_aspecto_mejorar es un objeto anidado
                        $id_aspecto = $feedback['id_aspecto_mejorar'] ?? null;
                        
                        if (is_array($id_aspecto)) {
                            // Si id_aspecto_mejorar es un objeto, extraer el ID
                            $nuevoFeedback['id_aspecto_mejorar'] = $id_aspecto['id_aspecto_mejorar'] ?? $id_aspecto['id'] ?? null;
                        } else {
                            // Si ya es un número, usarlo directamente
                            $nuevoFeedback['id_aspecto_mejorar'] = $id_aspecto;
                        }
                        
                        $nuevoFeedback['comentarios_mejorar'] = $feedback['comentarios_mejorar'] ?? null;
                        $nuevoFeedback['id_validez_feedback'] = $feedback['id_validez_feedback'] ?? null;
                    } else {
                        // Si viene solo el ID del aspecto
                        $nuevoFeedback['id_aspecto_mejorar'] = $feedback;
                    }

                    // Solo insertar si tiene un aspecto válido
                    if ($nuevoFeedback['id_aspecto_mejorar'] !== null) {
                        error_log("📝 Insertando feedback: " . json_encode($nuevoFeedback));
                        self::new($nuevoFeedback);
                        $totalInsertados++;
                        error_log("✅ Insertado feedback aspecto: " . $nuevoFeedback['id_aspecto_mejorar']);
                    }
                }
            }

            error_log("✅ Total feedbacks insertados: $totalInsertados");

            if ($dataParam !== null) {
                return ['success' => true, 'count' => $totalInsertados];
            }

            Flight::json(array('success' => true, 'count' => $totalInsertados));
        } catch (Exception $e) {
            error_log("❌ Error en visitas_feedback_mejorar guardarMultiples: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Obtener aspectos más mencionados para mejorar
    public static function getAspectosMasMencionados()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    am.nombre as aspecto,
                    COUNT(*) as veces_mencionado,
                    SUM(CASE WHEN tvf.codigo = 'muy_valido' THEN 1 ELSE 0 END) as feedback_muy_valido,
                    SUM(CASE WHEN tvf.codigo = 'valido' THEN 1 ELSE 0 END) as feedback_valido
                FROM visitas_feedback_mejorar vfm
                INNER JOIN aspectos_mejorar am ON vfm.id_aspecto_mejorar = am.id
                LEFT JOIN tipos_validez_feedback tvf ON vfm.id_validez_feedback = tvf.id
                WHERE vfm.id_tenant = :id_tenant
                GROUP BY vfm.id_aspecto_mejorar, am.nombre
                ORDER BY veces_mencionado DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_feedback_mejorar getAspectosMasMencionados: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}