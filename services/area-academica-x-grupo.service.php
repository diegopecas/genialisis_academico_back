<?php
class AreaAcademicaXGrupo
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT axg.id, 
                   axg.id_area_academica,
                   axg.id_grupo,
                   axg.id_docente,
                   a.nombre AS nombre_area,
                   a.icono AS icono_area,
                   g.nombre AS nombre_grupo,
                   CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) AS nombre_docente
            FROM area_academica_x_grupo axg
            INNER JOIN areas_academicas a ON axg.id_area_academica = a.id
            INNER JOIN grupos g ON axg.id_grupo = g.id
            LEFT JOIN docentes d ON axg.id_docente = d.id
            LEFT JOIN personas p ON d.id_persona = p.id
            WHERE axg.id_tenant = :id_tenant
            ORDER BY g.orden, a.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByGrupo($id_grupo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT axg.id, 
                   axg.id_area_academica,
                   axg.id_grupo,
                   axg.id_docente,
                   a.nombre AS nombre_area_academica,
                   a.icono,
                   g.nombre AS nombre_grupo,
                   CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                          IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_docente
            FROM area_academica_x_grupo axg
            INNER JOIN areas_academicas a ON axg.id_area_academica = a.id
            INNER JOIN grupos g ON axg.id_grupo = g.id
            LEFT JOIN docentes d ON axg.id_docente = d.id
            LEFT JOIN personas p ON d.id_persona = p.id
            WHERE axg.id_grupo = :id_grupo AND axg.id_tenant = :id_tenant
            ORDER BY a.nombre
        ");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        
        // Limpiar nombres de espacios extras
        foreach ($response as &$row) {
            if (isset($row['nombre_docente'])) {
                $row['nombre_docente'] = trim(preg_replace('/\s+/', ' ', $row['nombre_docente']));
                if ($row['nombre_docente'] == '') {
                    $row['nombre_docente'] = 'Sin docente asignado';
                }
            }
        }
        
        Flight::json($response);
    }

    public static function getByDocente($id_docente)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT axg.id, 
                   axg.id_area_academica,
                   axg.id_grupo,
                   axg.id_docente,
                   a.nombre AS nombre_area_academica,
                   a.icono,
                   a.color,
                   g.nombre AS nombre_grupo,
                   g.orden
            FROM area_academica_x_grupo axg
            INNER JOIN areas_academicas a ON axg.id_area_academica = a.id
            INNER JOIN grupos g ON axg.id_grupo = g.id
            WHERE axg.id_docente = :id_docente AND axg.id_tenant = :id_tenant
            ORDER BY g.orden, a.nombre
        ");
        $sentence->bindParam(':id_docente', $id_docente);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByAreaAcademica($id_area)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT axg.id, 
                   axg.id_grupo,
                   axg.id_docente,
                   g.nombre AS nombre_grupo,
                   CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) AS nombre_docente
            FROM area_academica_x_grupo axg
            INNER JOIN grupos g ON axg.id_grupo = g.id
            LEFT JOIN docentes d ON axg.id_docente = d.id
            LEFT JOIN personas p ON d.id_persona = p.id
            WHERE axg.id_area_academica = :id_area AND axg.id_tenant = :id_tenant
            ORDER BY g.orden
        ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $id_area_academica = Flight::request()->data['id_area_academica'];
            $id_grupo = Flight::request()->data['id_grupo'];
            $id_docente = isset(Flight::request()->data['id_docente']) ? 
                Flight::request()->data['id_docente'] : null;

            // Verificar si ya existe la combinación área-grupo
            $checkSentence = $db->prepare("
                SELECT id FROM area_academica_x_grupo 
                WHERE id_area_academica = :id_area_academica 
                AND id_grupo = :id_grupo
                AND id_tenant = :id_tenant
            ");
            $checkSentence->bindParam(':id_area_academica', $id_area_academica);
            $checkSentence->bindParam(':id_grupo', $id_grupo);
            $checkSentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkSentence->execute();
            
            if ($checkSentence->fetch()) {
                Flight::json(array('error' => 'Esta área académica ya está asignada a este grupo'), 400);
                return;
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO area_academica_x_grupo (id, id_tenant, id_area_academica, id_grupo, id_docente) 
                VALUES (:id, :id_tenant, :id_area_academica, :id_grupo, :id_docente)
            ");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_area_academica', $id_area_academica);
            $sentence->bindParam(':id_grupo', $id_grupo);
            $sentence->bindParam(':id_docente', $id_docente);
            $sentence->execute();

            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en AreaAcademicaXGrupo::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function updateDocente()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $id_docente = isset(Flight::request()->data['id_docente']) ? 
                Flight::request()->data['id_docente'] : null;

            $sentence = $db->prepare("
                UPDATE area_academica_x_grupo 
                SET id_docente = :id_docente 
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id_docente', $id_docente);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('success' => true, 'message' => 'Docente actualizado correctamente'));
        } catch (Exception $e) {
            error_log("Error en AreaAcademicaXGrupo::updateDocente: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function updateDocenteByAreaGrupo()
    {
        try {
            $db = Flight::db();
            $id_area_academica = Flight::request()->data['id_area_academica'];
            $id_grupo = Flight::request()->data['id_grupo'];
            $id_docente = isset(Flight::request()->data['id_docente']) ? 
                Flight::request()->data['id_docente'] : null;

            $sentence = $db->prepare("
                UPDATE area_academica_x_grupo 
                SET id_docente = :id_docente 
                WHERE id_area_academica = :id_area_academica 
                AND id_grupo = :id_grupo
                AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id_docente', $id_docente);
            $sentence->bindParam(':id_area_academica', $id_area_academica);
            $sentence->bindParam(':id_grupo', $id_grupo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró la asignación área-grupo'), 404);
                return;
            }

            Flight::json(array('success' => true, 'message' => 'Docente actualizado correctamente'));
        } catch (Exception $e) {
            error_log("Error en AreaAcademicaXGrupo::updateDocenteByAreaGrupo: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("
                DELETE FROM area_academica_x_grupo 
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el registro'), 404);
                return;
            }

            Flight::json(array('success' => true, 'message' => 'Asignación eliminada correctamente'));
        } catch (Exception $e) {
            error_log("Error en AreaAcademicaXGrupo::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function deleteByAreaGrupo()
    {
        try {
            $db = Flight::db();
            $id_area_academica = Flight::request()->data['id_area_academica'];
            $id_grupo = Flight::request()->data['id_grupo'];

            $sentence = $db->prepare("
                DELETE FROM area_academica_x_grupo 
                WHERE id_area_academica = :id_area_academica 
                AND id_grupo = :id_grupo
                AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id_area_academica', $id_area_academica);
            $sentence->bindParam(':id_grupo', $id_grupo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró la asignación área-grupo'), 404);
                return;
            }

            Flight::json(array('success' => true, 'message' => 'Asignación eliminada correctamente'));
        } catch (Exception $e) {
            error_log("Error en AreaAcademicaXGrupo::deleteByAreaGrupo: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getResumenDocente($id_docente)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                COUNT(DISTINCT axg.id_grupo) AS total_grupos,
                COUNT(DISTINCT axg.id_area_academica) AS total_areas,
                GROUP_CONCAT(DISTINCT g.nombre ORDER BY g.orden SEPARATOR ', ') AS grupos,
                GROUP_CONCAT(DISTINCT a.nombre ORDER BY a.nombre SEPARATOR ', ') AS areas
            FROM area_academica_x_grupo axg
            INNER JOIN grupos g ON axg.id_grupo = g.id
            INNER JOIN areas_academicas a ON axg.id_area_academica = a.id
            WHERE axg.id_docente = :id_docente AND axg.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_docente', $id_docente);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }
}
?>