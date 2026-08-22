<?php
class DocentesXGrupos
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT dxg.*, 
                   d.id_persona,
                   CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_docente,
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
                   CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_docente,
                   p.numero_identificacion,
                   col.id_nivel_escolaridad,
                   ne.nombre AS nivel_escolaridad,
                   car.nombre AS cargo
            FROM docentes_x_grupos dxg
            INNER JOIN docentes d ON dxg.id_docente = d.id
            INNER JOIN personas p ON d.id_persona = p.id
            LEFT JOIN colaboradores col ON col.id = d.id_colaborador
            LEFT JOIN niveles_escolaridad ne ON ne.id = col.id_nivel_escolaridad
            LEFT JOIN cargos car ON car.id = col.id_cargo
            WHERE dxg.id_grupo = :id_grupo
            AND dxg.activo = 1
            AND dxg.id_tenant = :id_tenant
            ORDER BY dxg.es_titular DESC, p.primer_nombre, p.primer_apellido
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
                   CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_docente,
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

    /**
     * Guarda de una sola vez toda la asignacion de docentes de un grupo.
     *
     * La pantalla trabaja en memoria: se agregan docentes, se quita alguno,
     * se cambia el titular y se asigna el area, y solo al grabar se manda
     * todo junto. Por eso este metodo recibe el estado completo y no una
     * operacion suelta.
     *
     * Espera:
     *   id_grupo
     *   docentes: [ { id_docente, es_titular, id_area_x_grupo } ]
     *
     * id_area_x_grupo es la fila de area_academica_x_grupo que dicta ese
     * docente, o null si no dicta ninguna.
     *
     * Va todo en una transaccion: un grupo a medio asignar es peor que uno
     * sin asignar.
     *
     * Los metodos sueltos (new, updateTitular, desactivar, activar) siguen
     * existiendo sin cambios para quien los este usando.
     */
    public static function guardarGrupo()
    {
        $db = Flight::db();

        $id_grupo = isset(Flight::request()->data['id_grupo']) ? Flight::request()->data['id_grupo'] : null;
        $docentes = isset(Flight::request()->data['docentes']) ? Flight::request()->data['docentes'] : array();

        if (empty($id_grupo)) {
            Flight::json(array('error' => 'Falta el grupo'), 400);
            return;
        }

        if (!is_array($docentes)) {
            Flight::json(array('error' => 'La lista de docentes no es valida'), 400);
            return;
        }

        // Solo puede haber un titular por grupo.
        $titulares = 0;
        foreach ($docentes as $fila) {
            if (isset($fila['es_titular']) && (int)$fila['es_titular'] === 1) {
                $titulares++;
            }
        }

        if ($titulares > 1) {
            Flight::json(array('error' => 'Solo puede haber un titular por grupo'), 400);
            return;
        }

        try {
            $db->beginTransaction();

            // 1. Los que ya no estan en la lista se desactivan. No se borran,
            //    para no perder el historico de quien estuvo a cargo.
            $enviados = array();
            foreach ($docentes as $fila) {
                if (!empty($fila['id_docente'])) {
                    $enviados[] = $fila['id_docente'];
                }
            }

            $actuales = $db->prepare("SELECT id, id_docente FROM docentes_x_grupos
                                      WHERE id_grupo = :id_grupo AND activo = 1 AND id_tenant = :id_tenant");
            $actuales->bindParam(':id_grupo', $id_grupo);
            $actuales->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $actuales->execute();

            $desactivar = $db->prepare("UPDATE docentes_x_grupos SET activo = 0, es_titular = 0
                                        WHERE id = :id AND id_tenant = :id_tenant");
            $liberarArea = $db->prepare("UPDATE area_academica_x_grupo SET id_docente = NULL
                                         WHERE id_grupo = :id_grupo AND id_docente = :id_docente AND id_tenant = :id_tenant");

            foreach ($actuales->fetchAll() as $actual) {
                if (in_array($actual['id_docente'], $enviados, true)) {
                    continue;
                }

                $desactivar->bindValue(':id', $actual['id']);
                $desactivar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $desactivar->execute();

                // Si dictaba un area del grupo, esa area queda sin docente.
                $liberarArea->bindValue(':id_grupo', $id_grupo);
                $liberarArea->bindValue(':id_docente', $actual['id_docente']);
                $liberarArea->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $liberarArea->execute();
            }

            // 2. Los enviados se crean o se actualizan. Si el docente estuvo
            //    antes en el grupo y se quito, se reactiva su misma fila.
            $buscar = $db->prepare("SELECT id FROM docentes_x_grupos
                                    WHERE id_grupo = :id_grupo AND id_docente = :id_docente AND id_tenant = :id_tenant
                                    LIMIT 1");
            $actualizar = $db->prepare("UPDATE docentes_x_grupos SET activo = 1, es_titular = :es_titular
                                        WHERE id = :id AND id_tenant = :id_tenant");
            $insertar = $db->prepare("INSERT INTO docentes_x_grupos
                                      (id, id_tenant, es_titular, activo, fecha_asignacion, id_docente, id_grupo)
                                      VALUES (:id, :id_tenant, :es_titular, 1, :fecha, :id_docente, :id_grupo)");

            foreach ($docentes as $fila) {
                if (empty($fila['id_docente'])) {
                    continue;
                }

                $esTitular = (isset($fila['es_titular']) && (int)$fila['es_titular'] === 1) ? 1 : 0;

                $buscar->bindValue(':id_grupo', $id_grupo);
                $buscar->bindValue(':id_docente', $fila['id_docente']);
                $buscar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $buscar->execute();
                $existente = $buscar->fetch();

                if ($existente) {
                    $actualizar->bindValue(':es_titular', $esTitular, PDO::PARAM_INT);
                    $actualizar->bindValue(':id', $existente['id']);
                    $actualizar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $actualizar->execute();
                } else {
                    $insertar->bindValue(':id', Uuid::generar());
                    $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $insertar->bindValue(':es_titular', $esTitular, PDO::PARAM_INT);
                    $insertar->bindValue(':fecha', date('Y-m-d'));
                    $insertar->bindValue(':id_docente', $fila['id_docente']);
                    $insertar->bindValue(':id_grupo', $id_grupo);
                    $insertar->execute();
                }
            }

            // 3. Areas del grupo. Se limpian todas y se vuelven a asignar con
            //    lo que trae la lista: asi una area que quedo sin docente en
            //    la pantalla tambien queda sin docente en la base.
            $limpiarAreas = $db->prepare("UPDATE area_academica_x_grupo SET id_docente = NULL
                                          WHERE id_grupo = :id_grupo AND id_tenant = :id_tenant");
            $limpiarAreas->bindValue(':id_grupo', $id_grupo);
            $limpiarAreas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $limpiarAreas->execute();

            $asignarArea = $db->prepare("UPDATE area_academica_x_grupo SET id_docente = :id_docente
                                         WHERE id = :id AND id_grupo = :id_grupo AND id_tenant = :id_tenant");

            foreach ($docentes as $fila) {
                if (empty($fila['id_docente']) || empty($fila['id_area_x_grupo'])) {
                    continue;
                }

                $asignarArea->bindValue(':id_docente', $fila['id_docente']);
                $asignarArea->bindValue(':id', $fila['id_area_x_grupo']);
                $asignarArea->bindValue(':id_grupo', $id_grupo);
                $asignarArea->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $asignarArea->execute();
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[DocentesXGrupos::guardarGrupo] ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo guardar la asignacion de docentes'), 500);
            return;
        }

        Flight::json(array('id_grupo' => $id_grupo, 'total' => count($docentes)));
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
