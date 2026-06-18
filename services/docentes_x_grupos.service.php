<?php
class DocentesXGrupos
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT dxg.*, 
                   d.id_persona,
                   CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) AS nombre_docente,
                   g.nombre AS nombre_grupo
            FROM docentes_x_grupos dxg
            INNER JOIN docentes d ON dxg.id_docente = d.id
            INNER JOIN personas p ON d.id_persona = p.id
            INNER JOIN grupos g ON dxg.id_grupo = g.id
            WHERE dxg.id_tenant = :id_tenant
            ORDER BY g.orden, p.primer_nombre, p.primer_apellido
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByDocente($id_docente)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT dxg.*, 
                   g.nombre AS nombre_grupo,
                   g.orden,
                   g.id AS id_grupo
            FROM docentes_x_grupos dxg
            INNER JOIN grupos g ON dxg.id_grupo = g.id
            WHERE dxg.id_docente = :id_docente
            AND dxg.activo = 1
            AND dxg.id_tenant = :id_tenant
            ORDER BY g.orden
        ");
        $sentence->bindParam(':id_docente', $id_docente);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByGrupo($id_grupo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT dxg.*, 
                   d.id_persona,
                   CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) AS nombre_docente,
                   p.numero_identificacion,
                   d.id_nivel_escolaridad,
                   ne.nombre AS nivel_escolaridad
            FROM docentes_x_grupos dxg
            INNER JOIN docentes d ON dxg.id_docente = d.id
            INNER JOIN personas p ON d.id_persona = p.id
            INNER JOIN niveles_escolaridad ne ON d.id_nivel_escolaridad = ne.id
            WHERE dxg.id_grupo = :id_grupo
            AND dxg.activo = 1
            AND dxg.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getTitular($id_grupo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT dxg.*, 
                   d.id_persona,
                   CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) AS nombre_docente,
                   p.correo_electronico,
                   p.telefono
            FROM docentes_x_grupos dxg
            INNER JOIN docentes d ON dxg.id_docente = d.id
            INNER JOIN personas p ON d.id_persona = p.id
            WHERE dxg.id_grupo = :id_grupo
            AND dxg.es_titular = 1
            AND dxg.activo = 1
            AND dxg.id_tenant = :id_tenant
            LIMIT 1
        ");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response ? $response : null);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $id_docente = Flight::request()->data['id_docente'];
            $id_grupo = Flight::request()->data['id_grupo'];
            $es_titular = isset(Flight::request()->data['es_titular']) ? Flight::request()->data['es_titular'] : 0;
            $fecha_asignacion = isset(Flight::request()->data['fecha_asignacion']) ? 
                Flight::request()->data['fecha_asignacion'] : date('Y-m-d');

            // Verificar si ya existe una asignación activa
            $checkSentence = $db->prepare("
                SELECT id FROM docentes_x_grupos 
                WHERE id_docente = :id_docente 
                AND id_grupo = :id_grupo 
                AND activo = 1
                AND id_tenant = :id_tenant
            ");
            $checkSentence->bindParam(':id_docente', $id_docente);
            $checkSentence->bindParam(':id_grupo', $id_grupo);
            $checkSentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkSentence->execute();
            
            if ($checkSentence->fetch()) {
                Flight::json(array('error' => 'El docente ya está asignado a este grupo'), 400);
                return;
            }

            // Si es titular, verificar que no haya otro titular activo
            if ($es_titular) {
                $checkTitular = $db->prepare("
                    SELECT id FROM docentes_x_grupos 
                    WHERE id_grupo = :id_grupo 
                    AND es_titular = 1 
                    AND activo = 1
                    AND id_tenant = :id_tenant
                ");
                $checkTitular->bindParam(':id_grupo', $id_grupo);
                $checkTitular->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $checkTitular->execute();
                
                if ($checkTitular->fetch()) {
                    // Desactivar el titular anterior
                    $updateTitular = $db->prepare("
                        UPDATE docentes_x_grupos 
                        SET es_titular = 0 
                        WHERE id_grupo = :id_grupo 
                        AND es_titular = 1 
                        AND activo = 1
                        AND id_tenant = :id_tenant
                    ");
                    $updateTitular->bindParam(':id_grupo', $id_grupo);
                    $updateTitular->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $updateTitular->execute();
                }
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO docentes_x_grupos (id, id_tenant, id_docente, id_grupo, es_titular, activo, fecha_asignacion) 
                VALUES (:id, :id_tenant, :id_docente, :id_grupo, :es_titular, 1, :fecha_asignacion)
            ");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_docente', $id_docente);
            $sentence->bindParam(':id_grupo', $id_grupo);
            $sentence->bindParam(':es_titular', $es_titular);
            $sentence->bindParam(':fecha_asignacion', $fecha_asignacion);
            $sentence->execute();

            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en DocentesXGrupos::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function updateTitular()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $es_titular = Flight::request()->data['es_titular'];

            // Si se va a establecer como titular, quitar el titular actual
            if ($es_titular) {
                // Obtener el grupo de esta asignación
                $getGrupo = $db->prepare("SELECT id_grupo FROM docentes_x_grupos WHERE id = :id AND id_tenant = :id_tenant");
                $getGrupo->bindParam(':id', $id);
                $getGrupo->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $getGrupo->execute();
                $grupo = $getGrupo->fetch();

                if ($grupo) {
                    // Quitar titular actual
                    $updateOtros = $db->prepare("
                        UPDATE docentes_x_grupos 
                        SET es_titular = 0 
                        WHERE id_grupo = :id_grupo 
                        AND id != :id 
                        AND activo = 1
                        AND id_tenant = :id_tenant
                    ");
                    $updateOtros->bindParam(':id_grupo', $grupo['id_grupo']);
                    $updateOtros->bindParam(':id', $id);
                    $updateOtros->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $updateOtros->execute();
                }
            }

            $sentence = $db->prepare("
                UPDATE docentes_x_grupos 
                SET es_titular = :es_titular 
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':es_titular', $es_titular);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('success' => true));
        } catch (Exception $e) {
            error_log("Error en DocentesXGrupos::updateTitular: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function desactivar()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("
                UPDATE docentes_x_grupos 
                SET activo = 0 
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('success' => true));
        } catch (Exception $e) {
            error_log("Error en DocentesXGrupos::desactivar: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function activar()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            // Verificar que no haya otra asignación activa para el mismo docente-grupo
            $check = $db->prepare("
                SELECT dxg1.id 
                FROM docentes_x_grupos dxg1
                INNER JOIN docentes_x_grupos dxg2 ON dxg1.id_docente = dxg2.id_docente 
                    AND dxg1.id_grupo = dxg2.id_grupo
                WHERE dxg2.id = :id 
                AND dxg1.activo = 1
                AND dxg1.id != :id2
                AND dxg1.id_tenant = :id_tenant
            ");
            $check->bindParam(':id', $id);
            $check->bindParam(':id2', $id);
            $check->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $check->execute();

            if ($check->fetch()) {
                Flight::json(array('error' => 'Ya existe una asignación activa para este docente en este grupo'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE docentes_x_grupos 
                SET activo = 1 
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('success' => true));
        } catch (Exception $e) {
            error_log("Error en DocentesXGrupos::activar: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
