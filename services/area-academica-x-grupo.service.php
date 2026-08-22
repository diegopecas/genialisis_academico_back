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
                   CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_docente
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
                   a.color,
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
                   CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_docente
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

    /**
     * Guarda de una sola vez todas las areas del grupo con su docente.
     *
     * La pestana trabaja en memoria: se asocian areas, se quitan otras y se
     * escoge quien dicta cada una, y solo al grabar se manda todo junto. Por
     * eso este metodo recibe el estado completo y no una operacion suelta.
     *
     * Espera:
     *   id_grupo
     *   areas: [ { id_area_academica, id_docente } ]
     *
     * Va en una transaccion: un grupo con la mitad de las areas guardadas es
     * peor que uno sin guardar.
     *
     * Los metodos sueltos (new, updateDocente, delete...) siguen existiendo
     * sin cambios para quien los este usando.
     */
    public static function guardarGrupo()
    {
        $db = Flight::db();

        $id_grupo = isset(Flight::request()->data['id_grupo']) ? Flight::request()->data['id_grupo'] : null;
        $areas = isset(Flight::request()->data['areas']) ? Flight::request()->data['areas'] : array();

        if (empty($id_grupo)) {
            Flight::json(array('error' => 'Falta el grupo'), 400);
            return;
        }

        if (!is_array($areas)) {
            Flight::json(array('error' => 'La lista de areas no es valida'), 400);
            return;
        }

        try {
            $db->beginTransaction();

            $enviadas = array();
            foreach ($areas as $fila) {
                if (!empty($fila['id_area_academica'])) {
                    $enviadas[] = $fila['id_area_academica'];
                }
            }

            // 1. Las que ya no estan en la lista se desasocian.
            $actuales = $db->prepare("SELECT id, id_area_academica FROM area_academica_x_grupo
                                      WHERE id_grupo = :id_grupo AND id_tenant = :id_tenant");
            $actuales->bindParam(':id_grupo', $id_grupo);
            $actuales->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $actuales->execute();
            $filasActuales = $actuales->fetchAll();

            $borrar = $db->prepare("DELETE FROM area_academica_x_grupo
                                    WHERE id = :id AND id_tenant = :id_tenant");

            $existentes = array();
            foreach ($filasActuales as $actual) {
                if (in_array($actual['id_area_academica'], $enviadas, true)) {
                    $existentes[$actual['id_area_academica']] = $actual['id'];
                    continue;
                }

                $borrar->bindValue(':id', $actual['id']);
                $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $borrar->execute();
            }

            // 2. Las enviadas se crean o se actualizan con su docente.
            $insertar = $db->prepare("INSERT INTO area_academica_x_grupo
                                      (id, id_tenant, id_area_academica, id_docente, id_grupo)
                                      VALUES (:id, :id_tenant, :id_area_academica, :id_docente, :id_grupo)");
            $actualizar = $db->prepare("UPDATE area_academica_x_grupo SET id_docente = :id_docente
                                        WHERE id = :id AND id_tenant = :id_tenant");

            foreach ($areas as $fila) {
                if (empty($fila['id_area_academica'])) {
                    continue;
                }

                $idDocente = !empty($fila['id_docente']) ? $fila['id_docente'] : null;

                if (isset($existentes[$fila['id_area_academica']])) {
                    $actualizar->bindValue(':id_docente', $idDocente);
                    $actualizar->bindValue(':id', $existentes[$fila['id_area_academica']]);
                    $actualizar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $actualizar->execute();
                } else {
                    $insertar->bindValue(':id', Uuid::generar());
                    $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $insertar->bindValue(':id_area_academica', $fila['id_area_academica']);
                    $insertar->bindValue(':id_docente', $idDocente);
                    $insertar->bindValue(':id_grupo', $id_grupo);
                    $insertar->execute();
                }
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[AreaAcademicaXGrupo::guardarGrupo] ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudieron guardar las areas del grupo'), 500);
            return;
        }

        Flight::json(array('id_grupo' => $id_grupo, 'total' => count($areas)));
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