<?php
class AutorizadosRecoger
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_estudiante, id_persona, id_tipo_autorizacion, id_persona_autoriza, observaciones, activo, fecha_registro FROM autorizados_recoger WHERE id_tenant = :id_tenant ORDER BY fecha_registro DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_estudiante, id_persona, id_tipo_autorizacion, id_persona_autoriza, observaciones, activo, fecha_registro FROM autorizados_recoger WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByEstudiante($idEstudiante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
                                    ar.id,
                                    ar.id_estudiante,
                                    ar.id_persona,
                                    ar.id_tipo_autorizacion,
                                    ar.id_persona_autoriza,
                                    ar.observaciones,
                                    ar.activo,
                                    ar.fecha_registro,
                                    tar.nombre AS nombre_tipo_autorizacion,
                                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_persona,
                                    p.numero_identificacion AS documento_persona,
                                    p.foto,
                                    TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.segundo_nombre, pa.primer_apellido, pa.segundo_apellido)) AS nombre_persona_autoriza
                                  FROM autorizados_recoger ar
                                  INNER JOIN tipos_autorizacion_recoger tar ON tar.id = ar.id_tipo_autorizacion
                                  INNER JOIN personas p ON p.id = ar.id_persona
                                  INNER JOIN personas pa ON pa.id = ar.id_persona_autoriza
                                  WHERE ar.id_estudiante = :id_estudiante AND ar.id_tenant = :id_tenant
                                  ORDER BY ar.activo DESC, p.primer_apellido ASC, p.primer_nombre ASC");
        $sentence->bindParam(':id_estudiante', $idEstudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Obtener autorizados activos para el día de hoy (permanentes + temporales con historial del día)
     * Usado desde el módulo de asistencia
     */
    public static function getActivosHoyByEstudiante($idEstudiante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
                                    ar.id,
                                    ar.id_persona,
                                    ar.id_tipo_autorizacion,
                                    tar.nombre AS nombre_tipo_autorizacion,
                                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_persona,
                                    p.numero_identificacion AS documento_persona,
                                    p.foto,
                                    TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.segundo_nombre, pa.primer_apellido, pa.segundo_apellido)) AS nombre_persona_autoriza
                                  FROM autorizados_recoger ar
                                  INNER JOIN tipos_autorizacion_recoger tar ON tar.id = ar.id_tipo_autorizacion
                                  INNER JOIN personas p ON p.id = ar.id_persona
                                  INNER JOIN personas pa ON pa.id = ar.id_persona_autoriza
                                  WHERE ar.id_estudiante = :id_estudiante
                                    AND ar.activo = 1
                                    AND ar.id_tenant = :id_tenant
                                    AND (
                                      ar.id_tipo_autorizacion = 1
                                      OR EXISTS (
                                        SELECT 1 FROM autorizados_recoger_historial arh 
                                        WHERE arh.id_autorizado_recoger = ar.id 
                                        AND arh.fecha_autorizada = CURDATE()
                                      )
                                    )
                                  ORDER BY p.primer_apellido ASC, p.primer_nombre ASC");
        $sentence->bindParam(':id_estudiante', $idEstudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $id_estudiante = Flight::request()->data['id_estudiante'];
            $id_persona = Flight::request()->data['id_persona'];
            $id_tipo_autorizacion = Flight::request()->data['id_tipo_autorizacion'];
            $id_persona_autoriza = Flight::request()->data['id_persona_autoriza'];
            $observaciones = isset(Flight::request()->data['observaciones']) ? Flight::request()->data['observaciones'] : null;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO autorizados_recoger (id, id_tenant, id_estudiante, id_persona, id_tipo_autorizacion, id_persona_autoriza, observaciones, activo) 
                                      VALUES (:id, :id_tenant, :id_estudiante, :id_persona, :id_tipo_autorizacion, :id_persona_autoriza, :observaciones, :activo)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_tipo_autorizacion', $id_tipo_autorizacion);
            $sentence->bindParam(':id_persona_autoriza', $id_persona_autoriza);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();
            $id = $idNew;

            $db->commit();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error en AutorizadosRecoger::new: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $id = Flight::request()->data['id'];
            $id_tipo_autorizacion = Flight::request()->data['id_tipo_autorizacion'];
            $observaciones = isset(Flight::request()->data['observaciones']) ? Flight::request()->data['observaciones'] : null;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $sentence = $db->prepare("UPDATE autorizados_recoger SET 
                                        id_tipo_autorizacion = :id_tipo_autorizacion,
                                        observaciones = :observaciones,
                                        activo = :activo
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_tipo_autorizacion', $id_tipo_autorizacion);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();

            $db->commit();
            self::getById($id);
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error en AutorizadosRecoger::replace: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function delete($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("DELETE FROM autorizados_recoger WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() > 0) {
                Flight::json(["success" => true, "message" => "Registro eliminado correctamente"]);
            } else {
                Flight::json(["success" => false, "message" => "No se encontró el registro"], 404);
            }
        } catch (Exception $e) {
            Flight::json(["success" => false, "error" => $e->getMessage()], 500);
        }
    }

    public static function verificarDuplicados()
    {
        $db = Flight::db();
        $id_estudiante = Flight::request()->data['id_estudiante'];
        $id_persona = Flight::request()->data['id_persona'];

        $sentence = $db->prepare("SELECT COUNT(*) as total FROM autorizados_recoger 
                                  WHERE id_estudiante = :id_estudiante 
                                  AND id_persona = :id_persona
                                  AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();

        Flight::json(array('existe' => $response['total'] > 0));
    }
}