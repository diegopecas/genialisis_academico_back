<?php
class AutorizadosRecogerHistorial
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_autorizado_recoger, fecha_autorizada, id_persona_autoriza, observaciones, fecha_registro FROM autorizados_recoger_historial WHERE id_tenant = :id_tenant ORDER BY fecha_autorizada DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_autorizado_recoger, fecha_autorizada, id_persona_autoriza, observaciones, fecha_registro FROM autorizados_recoger_historial WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByAutorizado($idAutorizado)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
                                    arh.id,
                                    arh.id_autorizado_recoger,
                                    arh.fecha_autorizada,
                                    arh.id_persona_autoriza,
                                    arh.observaciones,
                                    arh.fecha_registro,
                                    TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.segundo_nombre, pa.primer_apellido, pa.segundo_apellido)) AS nombre_persona_autoriza
                                  FROM autorizados_recoger_historial arh
                                  INNER JOIN personas pa ON pa.id = arh.id_persona_autoriza
                                  WHERE arh.id_autorizado_recoger = :id_autorizado AND arh.id_tenant = :id_tenant
                                  ORDER BY arh.fecha_autorizada DESC");
        $sentence->bindParam(':id_autorizado', $idAutorizado);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $id_autorizado_recoger = Flight::request()->data['id_autorizado_recoger'];
            $fecha_autorizada = Flight::request()->data['fecha_autorizada'];
            $id_persona_autoriza = Flight::request()->data['id_persona_autoriza'];
            $observaciones = isset(Flight::request()->data['observaciones']) ? Flight::request()->data['observaciones'] : null;

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO autorizados_recoger_historial (id, id_tenant, id_autorizado_recoger, fecha_autorizada, id_persona_autoriza, observaciones) 
                                      VALUES (:id, :id_tenant, :id_autorizado_recoger, :fecha_autorizada, :id_persona_autoriza, :observaciones)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_autorizado_recoger', $id_autorizado_recoger);
            $sentence->bindParam(':fecha_autorizada', $fecha_autorizada);
            $sentence->bindParam(':id_persona_autoriza', $id_persona_autoriza);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->execute();
            $id = $idNew;

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en AutorizadosRecogerHistorial::new: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function delete($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("DELETE FROM autorizados_recoger_historial WHERE id = :id AND id_tenant = :id_tenant");
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
}