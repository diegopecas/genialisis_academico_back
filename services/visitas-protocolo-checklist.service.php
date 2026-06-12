<?php
class VisitasProtocoloChecklist
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vpc.*,
                    pp.nombre as nombre_paso,
                    pp.numero_paso,
                    v.fecha as fecha_visita
                FROM visitas_protocolo_checklist vpc
                INNER JOIN protocolo_pasos pp ON vpc.id_protocolo_paso = pp.id
                INNER JOIN visitas v ON vpc.id_visita = v.id
                ORDER BY v.fecha DESC, vpc.fecha_hora DESC
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_checklist getAll: " . $e->getMessage());
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
                    pp.nombre as nombre_paso,
                    pp.numero_paso
                FROM visitas_protocolo_checklist vpc
                INNER JOIN protocolo_pasos pp ON vpc.id_protocolo_paso = pp.id
                WHERE vpc.id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_checklist getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vpc.*,
                    pp.nombre as nombre_paso,
                    pp.numero_paso
                FROM visitas_protocolo_checklist vpc
                INNER JOIN protocolo_pasos pp ON vpc.id_protocolo_paso = pp.id
                WHERE vpc.id_visita = :id_visita
                ORDER BY pp.numero_paso ASC, vpc.fecha_hora ASC
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_checklist getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisitaYPaso($id_visita, $id_protocolo_paso)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vpc.*,
                    pp.nombre as nombre_paso
                FROM visitas_protocolo_checklist vpc
                INNER JOIN protocolo_pasos pp ON vpc.id_protocolo_paso = pp.id
                WHERE vpc.id_visita = :id_visita AND vpc.id_protocolo_paso = :id_protocolo_paso
                ORDER BY vpc.fecha_hora ASC
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindParam(':id_protocolo_paso', $id_protocolo_paso);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_checklist getByVisitaYPaso: " . $e->getMessage());
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
            INSERT INTO visitas_protocolo_checklist (
                id_visita, id_protocolo_paso, item_checklist, completado
            ) VALUES (
                :id_visita, :id_protocolo_paso, :item_checklist, :completado
            )
        ");

            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':id_protocolo_paso', $data['id_protocolo_paso']);
            $sentence->bindParam(':item_checklist', $data['item_checklist']);
            $sentence->bindParam(':completado', $data['completado']);

            $sentence->execute();
            $id = $db->lastInsertId();

            // ✅ Si se llamó con parámetro, retornar el ID, sino usar Flight::json
            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_checklist new: " . $e->getMessage());

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
                UPDATE visitas_protocolo_checklist SET
                    item_checklist = :item_checklist,
                    completado = :completado
                WHERE id = :id
            ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':item_checklist', $data['item_checklist']);
            $sentence->bindParam(':completado', $data['completado']);

            $sentence->execute();
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_checklist replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM visitas_protocolo_checklist WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_checklist delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Guardar múltiples items del checklist
    public static function guardarMultiples($dataParam = null)
    {
        try {
            $db = Flight::db();

            $data = $dataParam ?? Flight::request()->data;
            $id_visita = $data['id_visita'];
            $items = $data['items'] ?? [];

            error_log("📋 guardarMultiples checklist - id_visita: $id_visita");
            error_log("📋 Items recibidos: " . json_encode($items));

            // ✅ Primero eliminar todos los checklist existentes de esta visita
            $stmt = $db->prepare("DELETE FROM visitas_protocolo_checklist WHERE id_visita = :id_visita");
            $stmt->execute(['id_visita' => $id_visita]);

            $totalInsertados = 0;

            // ✅ Cargar todos los pasos del protocolo con sus checklist_items
            $stmt = $db->prepare("SELECT id, checklist_items FROM protocolo_pasos WHERE activo = 1");
            $stmt->execute();
            $pasos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Convertir a array asociativo para acceso rápido
            $checklistsPorPaso = [];
            foreach ($pasos as $paso) {
                if ($paso['checklist_items']) {
                    try {
                        $checklistsPorPaso[$paso['id']] = json_decode($paso['checklist_items'], true);
                    } catch (Exception $e) {
                        $checklistsPorPaso[$paso['id']] = [];
                    }
                }
            }

            // ✅ Insertar los nuevos items usando self::new()
            if (is_array($items) && count($items) > 0) {
                foreach ($items as $item) {
                    $id_protocolo_paso = $item['id_protocolo_paso'] ?? null;
                    $item_index = $item['item_index'] ?? null;
                    $completado = isset($item['completado']) ? ($item['completado'] ? 1 : 0) : 0;

                    // Validar que tengamos los datos necesarios
                    if ($id_protocolo_paso === null || $item_index === null) {
                        error_log("⚠️ Item sin id_protocolo_paso o item_index, saltando...");
                        continue;
                    }

                    // ✅ Obtener el texto del checklist desde el catálogo
                    if (!isset($checklistsPorPaso[$id_protocolo_paso])) {
                        error_log("⚠️ No se encontró checklist para paso ID: $id_protocolo_paso");
                        continue;
                    }

                    $checklistArray = $checklistsPorPaso[$id_protocolo_paso];
                    if (!isset($checklistArray[$item_index])) {
                        error_log("⚠️ No se encontró item en índice $item_index para paso ID: $id_protocolo_paso");
                        continue;
                    }

                    $item_texto = $checklistArray[$item_index];

                    // Preparar datos para insertar
                    $nuevoItem = [
                        'id_visita' => $id_visita,
                        'id_protocolo_paso' => $id_protocolo_paso,
                        'item_checklist' => $item_texto,
                        'completado' => $completado
                    ];

                    error_log("📝 Insertando checklist: " . json_encode($nuevoItem));

                    // ✅ LLAMAR A self::new() - SIN SQL DIRECTO
                    self::new($nuevoItem);

                    $totalInsertados++;
                    error_log("✅ Insertado checklist: paso $id_protocolo_paso, item: $item_texto");
                }
            }

            error_log("✅ Total items checklist insertados: $totalInsertados");

            if ($dataParam !== null) {
                return ['success' => true, 'count' => $totalInsertados];
            }

            Flight::json(array('success' => true, 'count' => $totalInsertados));
        } catch (Exception $e) {
            error_log("❌ Error en visitas_protocolo_checklist guardarMultiples: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Marcar/desmarcar un item específico
    public static function toggleItem()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $completado = Flight::request()->data['completado'];

            $sentence = $db->prepare("
                UPDATE visitas_protocolo_checklist SET
                    completado = :completado,
                    fecha_hora = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':completado', $completado);
            $sentence->execute();

            Flight::json(array('success' => true, 'id' => $id, 'completado' => $completado));
        } catch (Exception $e) {
            error_log("Error en visitas_protocolo_checklist toggleItem: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
