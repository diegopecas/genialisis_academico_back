<?php
class VisitasProtocoloPasosCompletados
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vppc.*,
                    pp.nombre as nombre_paso,
                    pp.numero_paso,
                    v.fecha as fecha_visita
                FROM visitas_protocolo_pasos_completados vppc
                INNER JOIN protocolo_pasos pp ON vppc.id_protocolo_paso = pp.id
                INNER JOIN visitas v ON vppc.id_visita = v.id
                WHERE vppc.id_tenant = :id_tenant
                ORDER BY v.fecha DESC, vppc.fecha_hora DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_pasos_completados getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vppc.*,
                    pp.nombre as nombre_paso,
                    pp.numero_paso,
                    pp.descripcion,
                    pp.objetivo
                FROM visitas_protocolo_pasos_completados vppc
                INNER JOIN protocolo_pasos pp ON vppc.id_protocolo_paso = pp.id
                WHERE vppc.id = :id
                AND vppc.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_pasos_completados getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vppc.*,
                    pp.nombre as nombre_paso,
                    pp.numero_paso,
                    pp.descripcion,
                    pp.objetivo
                FROM visitas_protocolo_pasos_completados vppc
                INNER JOIN protocolo_pasos pp ON vppc.id_protocolo_paso = pp.id
                WHERE vppc.id_visita = :id_visita
                AND vppc.id_tenant = :id_tenant
                ORDER BY pp.numero_paso ASC
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_pasos_completados getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();

            // ✅ Si se pasa parámetro, usarlo. Si no, tomar de Flight::request()
            $data = $dataParam ?? Flight::request()->data;

            $sentence = $db->prepare("
            INSERT INTO visitas_protocolo_pasos_completados (
                id, id_tenant, id_visita, id_protocolo_paso, completado, perfil_usado, notas
            ) VALUES (
                :id, :id_tenant, :id_visita, :id_protocolo_paso, :completado, :perfil_usado, :notas
            )
        ");

            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':id_protocolo_paso', $data['id_protocolo_paso']);
            $sentence->bindParam(':completado', $data['completado']);
            $sentence->bindParam(':perfil_usado', $data['perfil_usado']);
            $sentence->bindParam(':notas', $data['notas']);

            $sentence->execute();

            // ✅ Si se llamó con parámetro, retornar el ID, sino usar Flight::json
            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_pasos_completados new: " . $e->getMessage());

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
                UPDATE visitas_protocolo_pasos_completados SET
                    completado = :completado,
                    perfil_usado = :perfil_usado,
                    notas = :notas
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':completado', $data['completado']);
            $sentence->bindParam(':perfil_usado', $data['perfil_usado']);
            $sentence->bindParam(':notas', $data['notas']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_pasos_completados replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM visitas_protocolo_pasos_completados WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_pasos_completados delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Marcar paso como completado
    public static function marcarCompletado()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;
            $id_visita = $data['id_visita'];
            $id_protocolo_paso = $data['id_protocolo_paso'];

            // Verificar si ya existe
            $stmt = $db->prepare("
                SELECT id FROM visitas_protocolo_pasos_completados 
                WHERE id_visita = :id_visita AND id_protocolo_paso = :id_protocolo_paso
                AND id_tenant = :id_tenant
            ");
            $stmt->execute([
                'id_visita' => $id_visita,
                'id_protocolo_paso' => $id_protocolo_paso,
                'id_tenant' => TenantContext::id()
            ]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existe) {
                // Actualizar
                $stmt = $db->prepare("
                    UPDATE visitas_protocolo_pasos_completados SET
                        completado = :completado,
                        perfil_usado = :perfil_usado,
                        notas = :notas,
                        fecha_hora = CURRENT_TIMESTAMP
                    WHERE id = :id
                    AND id_tenant = :id_tenant
                ");
                $stmt->execute([
                    'completado' => $data['completado'],
                    'perfil_usado' => isset($data['perfil_usado']) ? $data['perfil_usado'] : null,
                    'notas' => isset($data['notas']) ? $data['notas'] : null,
                    'id' => $existe['id'],
                    'id_tenant' => TenantContext::id()
                ]);
                Flight::json(array('success' => true, 'id' => $existe['id'], 'action' => 'updated'));
            } else {
                // Insertar
                $stmt = $db->prepare("
                    INSERT INTO visitas_protocolo_pasos_completados 
                    (id, id_tenant, id_visita, id_protocolo_paso, completado, perfil_usado, notas)
                    VALUES (:id, :id_tenant, :id_visita, :id_protocolo_paso, :completado, :perfil_usado, :notas)
                ");
                $idPpc = Uuid::generar();
                $stmt->execute([
                    'id' => $idPpc,
                    'id_tenant' => TenantContext::id(),
                    'id_visita' => $id_visita,
                    'id_protocolo_paso' => $id_protocolo_paso,
                    'completado' => $data['completado'],
                    'perfil_usado' => isset($data['perfil_usado']) ? $data['perfil_usado'] : null,
                    'notas' => isset($data['notas']) ? $data['notas'] : null
                ]);
                $id = $idPpc;
                Flight::json(array('success' => true, 'id' => $id, 'action' => 'created'));
            }
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_pasos_completados marcarCompletado: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Obtener progreso del protocolo de una visita
    public static function getProgresoProtocolo($id_visita)
    {
        try {
            $db = Flight::db();

            // Total de pasos del protocolo
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM protocolo_pasos WHERE activo = 1 AND id_tenant = :id_tenant");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Pasos completados
            $stmt = $db->prepare("
                SELECT COUNT(*) as completados 
                FROM visitas_protocolo_pasos_completados 
                WHERE id_visita = :id_visita AND completado = 1
                AND id_tenant = :id_tenant
            ");
            $stmt->execute(['id_visita' => $id_visita, 'id_tenant' => TenantContext::id()]);
            $completados = $stmt->fetch(PDO::FETCH_ASSOC)['completados'];

            $porcentaje = $total > 0 ? round(($completados / $total) * 100, 2) : 0;

            Flight::json([
                'total_pasos' => $total,
                'pasos_completados' => $completados,
                'porcentaje_completado' => $porcentaje
            ]);
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_pasos_completados getProgresoProtocolo: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }


    public static function guardarMultiples($dataParam = null)
    {
        try {
            $db = Flight::db();

            $data = $dataParam ?? Flight::request()->data;
            $id_visita = $data['id_visita'];
            $pasos = $data['pasos'] ?? [];

            error_log("📋 guardarMultiples pasos protocolo - id_visita: $id_visita");
            error_log("📋 Pasos recibidos: " . json_encode($pasos));

            // ✅ Eliminar pasos existentes de esta visita
            $stmt = $db->prepare("DELETE FROM visitas_protocolo_pasos_completados WHERE id_visita = :id_visita AND id_tenant = :id_tenant");
            $stmt->execute(['id_visita' => $id_visita, 'id_tenant' => TenantContext::id()]);

            $totalInsertados = 0;

            // ✅ Insertar los nuevos pasos usando self::new()
            if (is_array($pasos) && count($pasos) > 0) {
                foreach ($pasos as $paso) {
                    // Preparar datos para insertar
                    $nuevoPaso = [
                        'id_visita' => $id_visita,
                        'id_protocolo_paso' => $paso['id_protocolo_paso'] ?? null,
                        'completado' => 0,
                        'perfil_usado' => $paso['perfil_usado'] ?? null,
                        'notas' => $paso['notas'] ?? null
                    ];

                    // ✅ Convertir booleanos a 1/0
                    if (isset($paso['completado'])) {
                        $nuevoPaso['completado'] = ($paso['completado'] === true || $paso['completado'] === 1 || $paso['completado'] === '1') ? 1 : 0;
                    }

                    // Solo insertar si tiene id_protocolo_paso válido
                    if ($nuevoPaso['id_protocolo_paso'] !== null) {
                        error_log("📝 Insertando paso: " . json_encode($nuevoPaso));

                        // ✅ LLAMAR A self::new() - SIN SQL DIRECTO
                        self::new($nuevoPaso);

                        $totalInsertados++;
                        error_log("✅ Insertado paso: " . $nuevoPaso['id_protocolo_paso'] . " - completado: " . $nuevoPaso['completado']);
                    }
                }
            }

            error_log("✅ Total pasos insertados: $totalInsertados");

            if ($dataParam !== null) {
                return ['success' => true, 'count' => $totalInsertados];
            }

            Flight::json(array('success' => true, 'registros' => $totalInsertados));
        } catch (Exception $e) {
            error_log("❌ Error en visitas_protocolo_pasos_completados guardarMultiples: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
