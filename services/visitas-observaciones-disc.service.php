<?php
class VisitasObservacionesDisc
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vod.*,
                    pd.categoria,
                    pd.descripcion,
                    pd.perfil_asociado,
                    v.nombre_completo as nombre_visitante
                FROM visitas_observaciones_disc vod
                INNER JOIN parametros_disc pd ON vod.id_parametro_disc = pd.id
                INNER JOIN visitantes v ON vod.id_visitante = v.id
                WHERE vod.id_tenant = :id_tenant
                ORDER BY v.id, pd.orden
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_observaciones_disc getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vod.*,
                    pd.categoria,
                    pd.descripcion,
                    pd.perfil_asociado,
                    v.nombre_completo as nombre_visitante
                FROM visitas_observaciones_disc vod
                INNER JOIN parametros_disc pd ON vod.id_parametro_disc = pd.id
                INNER JOIN visitantes v ON vod.id_visitante = v.id
                WHERE vod.id = :id
                AND vod.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_observaciones_disc getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisitante($id_visitante)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vod.*,
                    pd.categoria,
                    pd.descripcion,
                    pd.perfil_asociado,
                    pd.peso
                FROM visitas_observaciones_disc vod
                INNER JOIN parametros_disc pd ON vod.id_parametro_disc = pd.id
                WHERE vod.id_visitante = :id_visitante
                AND vod.id_tenant = :id_tenant
                ORDER BY pd.categoria, pd.orden
            ");
            $sentence->bindParam(':id_visitante', $id_visitante);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_observaciones_disc getByVisitante: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();

            // ✅ Permitir llamada desde otro método o desde endpoint
            $data = $dataParam ?? Flight::request()->data;

            $sentence = $db->prepare("
                INSERT INTO visitas_observaciones_disc (
                    id, id_tenant, id_visitante, id_parametro_disc, marcado
                ) VALUES (
                    :id, :id_tenant, :id_visitante, :id_parametro_disc, :marcado
                )
            ");

            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visitante', $data['id_visitante']);
            $sentence->bindParam(':id_parametro_disc', $data['id_parametro_disc']);
            $sentence->bindParam(':marcado', $data['marcado']);

            $sentence->execute();

            // ✅ Si se llamó desde otro método, retornar el ID
            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_observaciones_disc new: " . $e->getMessage());

            // ✅ Si se llamó desde otro método, lanzar excepción
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

            // ✅ Permitir llamada desde otro método o desde endpoint
            $data = $dataParam ?? Flight::request()->data;

            $sentence = $db->prepare("
                UPDATE visitas_observaciones_disc SET
                    marcado = :marcado
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':marcado', $data['marcado']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();

            // ✅ Si se llamó desde otro método, retornar true
            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_observaciones_disc replace: " . $e->getMessage());

            // ✅ Si se llamó desde otro método, lanzar excepción
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

            $sentence = $db->prepare("DELETE FROM visitas_observaciones_disc WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_observaciones_disc delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Método para guardar múltiples observaciones de una vez (LEGACY - mantener por compatibilidad)
    public static function guardarMultiples()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;
            $id_visitante = $data['id_visitante'];
            $observaciones = $data['observaciones']; // Array de {id_parametro_disc, marcado}

            // Primero eliminar las existentes
            $stmt = $db->prepare("DELETE FROM visitas_observaciones_disc WHERE id_visitante = :id_visitante AND id_tenant = :id_tenant");
            $stmt->execute(['id_visitante' => $id_visitante, 'id_tenant' => TenantContext::id()]);

            // Insertar las nuevas usando el método new()
            foreach ($observaciones as $obs) {
                self::new([
                    'id_visitante' => $id_visitante,
                    'id_parametro_disc' => $obs['id_parametro_disc'],
                    'marcado' => $obs['marcado']
                ]);
            }

            Flight::json(array('success' => true, 'count' => count($observaciones)));
        } catch (Exception $e) {
            error_log("Error en visitas_observaciones_disc guardarMultiples: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // =====================================================
    // MÉTODO PARA GUARDAR OBSERVACIONES POR VISITANTE
    // =====================================================
    public static function guardarObservacionesPorVisitante($dataParam = null)
    {
        try {
            $db = Flight::db();

            // ✅ Permitir llamada desde otro método o desde endpoint
            $data = $dataParam ?? Flight::request()->data;

            $id_visita = $data['id_visita'];
            $observaciones_por_visitante = $data['observaciones_por_visitante'];

            error_log("📋 guardarObservacionesPorVisitante - id_visita: $id_visita");
            error_log("📋 Observaciones recibidas: " . json_encode($observaciones_por_visitante));

            // ✅ VALIDACIÓN: Si no es un array válido, no hacer nada y retornar éxito
            if (!is_array($observaciones_por_visitante) || empty($observaciones_por_visitante)) {
                error_log("ℹ️ No hay observaciones DISC para procesar, continuando...");

                // ✅ Si se llamó desde otro método, retornar resultado
                if ($dataParam !== null) {
                    return ['success' => true, 'visitantes_procesados' => 0];
                }

                Flight::json([
                    'success' => true,
                    'visitantes_procesados' => 0
                ]);
                return;
            }

            // Obtener los visitantes de esta visita
            $stmt = $db->prepare("SELECT id FROM visitantes WHERE id_visita = :id_visita AND id_tenant = :id_tenant ORDER BY id");
            $stmt->execute(['id_visita' => $id_visita, 'id_tenant' => TenantContext::id()]);
            $visitantesIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            error_log("📋 IDs de visitantes encontrados: " . json_encode($visitantesIds));

            $totalProcesados = 0;

            foreach ($observaciones_por_visitante as $id_visitante => $observaciones) {
                error_log("📋 Procesando visitante ID: $id_visitante");
                error_log("📋 Observaciones: " . json_encode($observaciones));

                // ✅ Eliminar observaciones existentes de este visitante
                $stmt = $db->prepare("DELETE FROM visitas_observaciones_disc WHERE id_visitante = :id_visitante AND id_tenant = :id_tenant");
                $stmt->execute(['id_visitante' => $id_visitante, 'id_tenant' => TenantContext::id()]);

                $totalInsertadas = 0;

                // ✅ Insertar las nuevas observaciones usando el método new()
                if (is_array($observaciones) && !empty($observaciones)) {
                    foreach ($observaciones as $categoria => $parametros) {
                        error_log("📋 Categoría: $categoria, Parámetros: " . json_encode($parametros));

                        if (is_array($parametros)) {
                            foreach ($parametros as $id_parametro) {
                                // ✅ Usar el método new() existente
                                self::new([
                                    'id_visitante' => $id_visitante,
                                    'id_parametro_disc' => $id_parametro,
                                    'marcado' => 1
                                ]);
                                $totalInsertadas++;
                                error_log("✅ Insertado: visitante=$id_visitante, parametro=$id_parametro");
                            }
                        }
                    }
                }

                error_log("✅ Total observaciones insertadas para visitante $id_visitante: $totalInsertadas");

                // ✅ CORRECCIÓN: Calcular perfil SIEMPRE (incluso cuando no hay observaciones)
                // Si no hay observaciones, calcularPerfil debe limpiar/poner en NULL el perfil
                try {
                    VisitasPerfilCalculado::calcularPerfil($id_visitante);

                    if ($totalInsertadas > 0) {
                        error_log("✅ Perfil DISC calculado para visitante $id_visitante");
                    } else {
                        error_log("✅ Perfil DISC limpiado para visitante $id_visitante (sin observaciones)");
                    }
                } catch (Exception $e) {
                    error_log("⚠️ Error al calcular/limpiar perfil: " . $e->getMessage());
                }

                $totalProcesados++;
            }

            // ✅ Si se llamó desde otro método, retornar resultado
            if ($dataParam !== null) {
                return ['success' => true, 'visitantes_procesados' => $totalProcesados];
            }

            // Si se llamó desde endpoint, usar Flight::json
            Flight::json([
                'success' => true,
                'visitantes_procesados' => $totalProcesados
            ]);
        } catch (Exception $e) {
            error_log("❌ Error en guardarObservacionesPorVisitante: " . $e->getMessage());

            // ✅ Si se llamó desde otro método, lanzar excepción
            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
