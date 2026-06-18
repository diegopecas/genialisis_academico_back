<?php
class VisitasPerfilCalculado
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vpc.*,
                    v.nombre_completo as nombre_visitante,
                    vis.fecha as fecha_visita
                FROM visitas_perfil_calculado vpc
                INNER JOIN visitantes v ON vpc.id_visitante = v.id
                INNER JOIN visitas vis ON v.id_visita = vis.id
                WHERE vpc.id_tenant = :id_tenant
                ORDER BY vpc.fecha_calculo DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_perfil_calculado getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vpc.*,
                    v.nombre_completo as nombre_visitante,
                    vis.fecha as fecha_visita
                FROM visitas_perfil_calculado vpc
                INNER JOIN visitantes v ON vpc.id_visitante = v.id
                INNER JOIN visitas vis ON v.id_visita = vis.id
                WHERE vpc.id = :id
                AND vpc.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_perfil_calculado getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisitante($id_visitante)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT * FROM visitas_perfil_calculado 
                WHERE id_visitante = :id_visitante
                AND id_tenant = :id_tenant
                ORDER BY fecha_calculo DESC
                LIMIT 1
            ");
            $sentence->bindParam(':id_visitante', $id_visitante);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_perfil_calculado getByVisitante: " . $e->getMessage());
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
                INSERT INTO visitas_perfil_calculado (
                    id, id_tenant, id_visitante, perfil_sugerido, puntaje_D, puntaje_I, puntaje_S, puntaje_C
                ) VALUES (
                    :id, :id_tenant, :id_visitante, :perfil_sugerido, :puntaje_D, :puntaje_I, :puntaje_S, :puntaje_C
                )
            ");

            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visitante', $data['id_visitante']);
            $sentence->bindParam(':perfil_sugerido', $data['perfil_sugerido']);
            $sentence->bindParam(':puntaje_D', $data['puntaje_D']);
            $sentence->bindParam(':puntaje_I', $data['puntaje_I']);
            $sentence->bindParam(':puntaje_S', $data['puntaje_S']);
            $sentence->bindParam(':puntaje_C', $data['puntaje_C']);

            $sentence->execute();

            // ✅ Si se llamó desde otro método, retornar el ID
            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_perfil_calculado new: " . $e->getMessage());

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
                UPDATE visitas_perfil_calculado SET
                    perfil_sugerido = :perfil_sugerido,
                    puntaje_D = :puntaje_D,
                    puntaje_I = :puntaje_I,
                    puntaje_S = :puntaje_S,
                    puntaje_C = :puntaje_C
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':perfil_sugerido', $data['perfil_sugerido']);
            $sentence->bindParam(':puntaje_D', $data['puntaje_D']);
            $sentence->bindParam(':puntaje_I', $data['puntaje_I']);
            $sentence->bindParam(':puntaje_S', $data['puntaje_S']);
            $sentence->bindParam(':puntaje_C', $data['puntaje_C']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();

            // ✅ Si se llamó desde otro método, retornar true
            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_perfil_calculado replace: " . $e->getMessage());

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

            $sentence = $db->prepare("DELETE FROM visitas_perfil_calculado WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_perfil_calculado delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // =====================================================
    // MÉTODO UNIFICADO PARA CALCULAR PERFIL
    // =====================================================
public static function calcularPerfil($dataParam = null)
{
    try {
        $db = Flight::db();
        
        // ✅ Permitir llamada desde endpoint o interna
        if ($dataParam !== null) {
            // Llamada interna: recibe directamente id_visitante o array
            $id_visitante = is_array($dataParam) ? $dataParam['id_visitante'] : $dataParam;
        } else {
            // Llamada desde endpoint: lee de request
            $data = Flight::request()->data;
            $id_visitante = $data['id_visitante'];
        }
        
        error_log("🎯 Calculando perfil para visitante: $id_visitante");
        
        // Validar que id_visitante no sea null
        if (empty($id_visitante)) {
            throw new Exception("id_visitante no puede estar vacío");
        }

        // Obtener todas las observaciones marcadas del visitante
        $stmt = $db->prepare("
            SELECT pd.perfil_asociado, pd.peso
            FROM visitas_observaciones_disc vod
            INNER JOIN parametros_disc pd ON vod.id_parametro_disc = pd.id
            WHERE vod.id_visitante = :id_visitante AND vod.marcado = 1
            AND vod.id_tenant = :id_tenant
        ");
        $stmt->execute(['id_visitante' => $id_visitante, 'id_tenant' => TenantContext::id()]);
        $observaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("📋 Observaciones encontradas: " . count($observaciones));

        // ✅ SI NO HAY OBSERVACIONES: Limpiar el perfil
        if (empty($observaciones)) {
            error_log("🧹 No hay observaciones, limpiando perfil...");
            
            // Eliminar registro de perfil calculado
            $stmt = $db->prepare("DELETE FROM visitas_perfil_calculado WHERE id_visitante = :id_visitante AND id_tenant = :id_tenant");
            $stmt->execute(['id_visitante' => $id_visitante, 'id_tenant' => TenantContext::id()]);
            
            // Actualizar visitante para limpiar perfil_disc_calculado
            $stmt = $db->prepare("UPDATE visitantes SET perfil_disc_calculado = NULL WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->execute(['id' => $id_visitante, 'id_tenant' => TenantContext::id()]);
            
            error_log("✅ Perfil DISC limpiado para visitante $id_visitante");
            
            $resultado = [
                'success' => true,
                'perfil_sugerido' => null,
                'puntajes' => ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0]
            ];
            
            // ✅ Si se llamó internamente, retornar array
            if ($dataParam !== null) {
                return $resultado;
            }
            
            // Si se llamó desde endpoint, usar Flight::json
            Flight::json($resultado);
            return;
        }

        // ✅ SI HAY OBSERVACIONES: Calcular el perfil
        
        // Calcular puntajes
        $puntajes = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];
        foreach ($observaciones as $obs) {
            $perfil = $obs['perfil_asociado'];
            $peso = $obs['peso'];
            $puntajes[$perfil] += $peso;
        }
        
        error_log("📊 Puntajes calculados: " . json_encode($puntajes));

        // Determinar el perfil dominante
        $perfil_sugerido = array_keys($puntajes, max($puntajes))[0];
        
        error_log("🎯 Perfil sugerido: $perfil_sugerido");

        // Verificar si ya existe un perfil calculado
        $stmt = $db->prepare("SELECT id FROM visitas_perfil_calculado WHERE id_visitante = :id_visitante AND id_tenant = :id_tenant");
        $stmt->execute(['id_visitante' => $id_visitante, 'id_tenant' => TenantContext::id()]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);

        $dataPerfil = [
            'id_visitante' => $id_visitante,
            'perfil_sugerido' => $perfil_sugerido,
            'puntaje_D' => $puntajes['D'],
            'puntaje_I' => $puntajes['I'],
            'puntaje_S' => $puntajes['S'],
            'puntaje_C' => $puntajes['C']
        ];

        if ($existe) {
            // ✅ Usar el método replace() existente
            $dataPerfil['id'] = $existe['id'];
            self::replace($dataPerfil);
            $id = $existe['id'];
            error_log("✅ Perfil actualizado ID: $id");
        } else {
            // ✅ Usar el método new() existente
            $id = self::new($dataPerfil);
            error_log("✅ Perfil creado ID: $id");
        }

        // Actualizar el perfil en la tabla visitantes
        $stmt = $db->prepare("UPDATE visitantes SET perfil_disc_calculado = :perfil WHERE id = :id AND id_tenant = :id_tenant");
        $stmt->execute(['perfil' => $perfil_sugerido, 'id' => $id_visitante, 'id_tenant' => TenantContext::id()]);

        $resultado = [
            'success' => true,
            'id' => $id,
            'perfil_sugerido' => $perfil_sugerido,
            'puntajes' => $puntajes
        ];
        
        // ✅ Si se llamó internamente, retornar array
        if ($dataParam !== null) {
            return $resultado;
        }
        
        // Si se llamó desde endpoint, usar Flight::json
        Flight::json($resultado);
        
    } catch (Exception $e) {
        error_log("❌ Error en calcularPerfil: " . $e->getMessage());
        
        if ($dataParam !== null) {
            throw $e;
        }
        
        Flight::json(array('error' => $e->getMessage()), 500);
    }
}
}
