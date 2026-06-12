<?php
class TiposPreferenciasSeguimiento
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT * FROM tipos_preferencias_seguimiento 
                WHERE activo = 1 
                ORDER BY orden ASC
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en tipos_preferencias_seguimiento getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT * FROM tipos_preferencias_seguimiento 
                WHERE id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en tipos_preferencias_seguimiento getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                INSERT INTO tipos_preferencias_seguimiento (nombre, codigo, icono, orden, activo) 
                VALUES (:nombre, :codigo, :icono, :orden, :activo)
            ");

            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':codigo', $data['codigo']);
            $sentence->bindParam(':icono', $data['icono']);
            $sentence->bindParam(':orden', $data['orden']);
            $activo = isset($data['activo']) ? $data['activo'] : 1;
            $sentence->bindParam(':activo', $activo);

            $sentence->execute();
            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_preferencias_seguimiento new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                UPDATE tipos_preferencias_seguimiento SET
                    nombre = :nombre,
                    codigo = :codigo,
                    icono = :icono,
                    orden = :orden,
                    activo = :activo
                WHERE id = :id
            ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':codigo', $data['codigo']);
            $sentence->bindParam(':icono', $data['icono']);
            $sentence->bindParam(':orden', $data['orden']);
            $sentence->bindParam(':activo', $data['activo']);

            $sentence->execute();
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en tipos_preferencias_seguimiento replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM tipos_preferencias_seguimiento WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_preferencias_seguimiento delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}