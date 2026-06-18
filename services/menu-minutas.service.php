<?php
class MenuMinutas
{
    public static function getByMenu($id_menu)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT mm.*, m.nombre as nombre_menu
            FROM menu_minutas mm
            INNER JOIN menus m ON mm.id_menu = m.id
            WHERE mm.id_menu = :id_menu AND mm.id_tenant = :id_tenant
            ORDER BY mm.semana, mm.dia
        ");
        $sentence->bindParam(':id_menu', $id_menu);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT mm.*, m.nombre as nombre_menu, m.id_clasificacion_menu
            FROM menu_minutas mm
            INNER JOIN menus m ON mm.id_menu = m.id
            WHERE mm.id_tenant = :id_tenant
            ORDER BY mm.semana, mm.dia
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function asignar($id_menu)
    {
        try {
            $db = Flight::db();
            $minutas = Flight::request()->data['minutas'] ?? [];

            // Obtener la clasificación del menú actual
            $stmtMenu = $db->prepare("SELECT id_clasificacion_menu FROM menus WHERE id = :id AND id_tenant = :id_tenant");
            $stmtMenu->bindParam(':id', $id_menu);
            $stmtMenu->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtMenu->execute();
            $menuActual = $stmtMenu->fetch();

            if (!$menuActual || !$menuActual['id_clasificacion_menu']) {
                Flight::json(['error' => 'El menú debe tener una clasificación asignada para gestionar la minuta'], 400);
                return;
            }

            $id_clasificacion = $menuActual['id_clasificacion_menu'];

            $db->beginTransaction();

            // Eliminar minutas actuales de este menú
            $deleteStmt = $db->prepare("DELETE FROM menu_minutas WHERE id_menu = :id_menu AND id_tenant = :id_tenant");
            $deleteStmt->bindParam(':id_menu', $id_menu);
            $deleteStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $deleteStmt->execute();

            if (!empty($minutas)) {
                $insertStmt = $db->prepare("
                    INSERT INTO menu_minutas (id_tenant, id_menu, semana, dia)
                    VALUES (:id_tenant, :id_menu, :semana, :dia)
                ");
                $insertStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

                $conflictos = [];

                foreach ($minutas as $minuta) {
                    $semana = $minuta['semana'];
                    $dia = $minuta['dia'];

                    // Verificar si ya existe otro menú de la MISMA clasificación en esa semana/día
                    $checkStmt = $db->prepare("
                        SELECT mm.id, m.nombre as nombre_menu
                        FROM menu_minutas mm
                        INNER JOIN menus m ON mm.id_menu = m.id
                        WHERE mm.semana = :semana AND mm.dia = :dia 
                          AND mm.id_menu != :id_menu
                          AND m.id_clasificacion_menu = :id_clasificacion
                          AND mm.id_tenant = :id_tenant
                    ");
                    $checkStmt->bindParam(':semana', $semana);
                    $checkStmt->bindParam(':dia', $dia);
                    $checkStmt->bindParam(':id_menu', $id_menu);
                    $checkStmt->bindParam(':id_clasificacion', $id_clasificacion);
                    $checkStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $checkStmt->execute();
                    $existente = $checkStmt->fetch();

                    if ($existente) {
                        $conflictos[] = [
                            'semana' => $semana,
                            'dia' => $dia,
                            'menu_existente' => $existente['nombre_menu']
                        ];

                        // Eliminar solo el registro de la misma clasificación
                        $deleteExistente = $db->prepare("
                            DELETE mm FROM menu_minutas mm
                            INNER JOIN menus m ON mm.id_menu = m.id
                            WHERE mm.semana = :semana AND mm.dia = :dia
                              AND m.id_clasificacion_menu = :id_clasificacion
                              AND mm.id_menu != :id_menu
                              AND mm.id_tenant = :id_tenant
                        ");
                        $deleteExistente->bindParam(':semana', $semana);
                        $deleteExistente->bindParam(':dia', $dia);
                        $deleteExistente->bindParam(':id_clasificacion', $id_clasificacion);
                        $deleteExistente->bindParam(':id_menu', $id_menu);
                        $deleteExistente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                        $deleteExistente->execute();
                    }

                    $insertStmt->bindParam(':id_menu', $id_menu);
                    $insertStmt->bindParam(':semana', $semana);
                    $insertStmt->bindParam(':dia', $dia);
                    $insertStmt->execute();
                }
            }

            $db->commit();

            // Retornar las minutas actualizadas junto con conflictos
            $sentence = $db->prepare("
                SELECT mm.*, m.nombre as nombre_menu
                FROM menu_minutas mm
                INNER JOIN menus m ON mm.id_menu = m.id
                WHERE mm.id_menu = :id_menu AND mm.id_tenant = :id_tenant
                ORDER BY mm.semana, mm.dia
            ");
            $sentence->bindParam(':id_menu', $id_menu);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            $result = ['minutas' => $response];
            if (!empty($conflictos)) {
                $result['conflictos'] = $conflictos;
            }

            Flight::json($result);
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Error en asignar minutas: ' . $e->getMessage());
            Flight::json(['error' => 'Error al asignar minutas: ' . $e->getMessage()], 500);
        }
    }

    // Obtener minuta completa filtrada por clasificación
    public static function getMinutaCompleta()
    {
        $db = Flight::db();

        $id_clasificacion = Flight::request()->query['id_clasificacion'] ?? null;

        $sql = "
            SELECT mm.semana, mm.dia, mm.id_menu, 
                   m.nombre AS nombre_menu,
                   m.descripcion AS descripcion_menu,
                   m.id_clasificacion_menu
            FROM menu_minutas mm
            INNER JOIN menus m ON mm.id_menu = m.id
            WHERE m.activo = 1
            AND mm.id_tenant = :id_tenant
        ";

        if ($id_clasificacion) {
            $sql .= " AND m.id_clasificacion_menu = :id_clasificacion";
        }

        $sql .= " ORDER BY mm.semana, mm.dia";

        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

        if ($id_clasificacion) {
            $sentence->bindParam(':id_clasificacion', $id_clasificacion);
        }

        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Verificar conflictos antes de asignar (por clasificación)
    public static function verificarConflictos($id_menu)
    {
        $db = Flight::db();
        $minutas = Flight::request()->data['minutas'] ?? [];

        // Obtener clasificación del menú
        $stmtMenu = $db->prepare("SELECT id_clasificacion_menu FROM menus WHERE id = :id AND id_tenant = :id_tenant");
        $stmtMenu->bindParam(':id', $id_menu);
        $stmtMenu->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtMenu->execute();
        $menuActual = $stmtMenu->fetch();
        $id_clasificacion = $menuActual['id_clasificacion_menu'] ?? null;

        $conflictos = [];
        foreach ($minutas as $minuta) {
            $semana = $minuta['semana'];
            $dia = $minuta['dia'];

            $checkStmt = $db->prepare("
                SELECT mm.id, m.nombre as nombre_menu, mm.semana, mm.dia
                FROM menu_minutas mm
                INNER JOIN menus m ON mm.id_menu = m.id
                WHERE mm.semana = :semana AND mm.dia = :dia 
                  AND mm.id_menu != :id_menu
                  AND m.id_clasificacion_menu = :id_clasificacion
                  AND mm.id_tenant = :id_tenant
            ");
            $checkStmt->bindParam(':semana', $semana);
            $checkStmt->bindParam(':dia', $dia);
            $checkStmt->bindParam(':id_menu', $id_menu);
            $checkStmt->bindParam(':id_clasificacion', $id_clasificacion);
            $checkStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkStmt->execute();
            $existente = $checkStmt->fetch();

            if ($existente) {
                $conflictos[] = [
                    'semana' => $semana,
                    'dia' => $dia,
                    'menu_existente' => $existente['nombre_menu']
                ];
            }
        }

        Flight::json(['conflictos' => $conflictos]);
    }
}