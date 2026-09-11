<?php 
class TiposEventoCalendario
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select * from tipos_evento_calendario where id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT id, nombre, icono
                FROM tipos_evento_calendario
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en TiposEventoCalendario::getById: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener el tipo de evento'], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $nombre = trim((string) $request->data->nombre);
            $icono = isset($request->data->icono) && $request->data->icono !== '' ? $request->data->icono : null;

            if ($nombre === '') {
                Flight::json(['error' => 'El nombre del tipo de evento es obligatorio'], 400);
                return;
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO tipos_evento_calendario (id, id_tenant, nombre, icono)
                VALUES (:id, :id_tenant, :nombre, :icono)
            ");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':icono', $icono);
            $sentence->execute();

            Flight::json(['id' => $idNew]);
        } catch (Exception $e) {
            error_log('Error en TiposEventoCalendario::new: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data->id;
            $nombre = trim((string) $request->data->nombre);
            $icono = isset($request->data->icono) && $request->data->icono !== '' ? $request->data->icono : null;

            if ($nombre === '') {
                Flight::json(['error' => 'El nombre del tipo de evento es obligatorio'], 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE tipos_evento_calendario SET
                    nombre = :nombre,
                    icono = :icono
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log('Error en TiposEventoCalendario::replace: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data->id;

            // La llave foránea de calendarios_eventos no deja borrar un tipo en uso;
            // se valida antes para devolver un mensaje entendible en lugar del error de BD.
            $sentence = $db->prepare("
                SELECT COUNT(*) AS total
                FROM calendarios_eventos
                WHERE id_tipo_evento_calendario = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $total = (int) $sentence->fetchColumn();

            if ($total > 0) {
                Flight::json(['error' => 'Este tipo tiene ' . $total . ' evento(s) en el calendario y no se puede eliminar'], 409);
                return;
            }

            $sentence = $db->prepare("DELETE FROM tipos_evento_calendario WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en TiposEventoCalendario::delete: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

}
