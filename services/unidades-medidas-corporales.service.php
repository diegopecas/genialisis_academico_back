<?php
class UnidadesMedidasCorporales
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("SELECT id, nombre, abreviatura FROM unidades_medidas_corporales ORDER BY nombre");
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en UnidadesMedidasCorporales::getAll: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener unidades'], 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("SELECT id, nombre, abreviatura FROM unidades_medidas_corporales WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en UnidadesMedidasCorporales::getById: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener unidad'], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $nombre = $request->data->nombre;
            $abreviatura = $request->data->abreviatura;

            $stmt = $db->prepare("INSERT INTO unidades_medidas_corporales (nombre, abreviatura) VALUES (:nombre, :abreviatura)");
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':abreviatura', $abreviatura);
            $stmt->execute();
            $id = $db->lastInsertId();
            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en UnidadesMedidasCorporales::new: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data->id;
            $nombre = $request->data->nombre;
            $abreviatura = $request->data->abreviatura;

            $stmt = $db->prepare("UPDATE unidades_medidas_corporales SET nombre = :nombre, abreviatura = :abreviatura WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':abreviatura', $abreviatura);
            $stmt->execute();
            self::getById($id);
        } catch (Exception $e) {
            error_log('Error en UnidadesMedidasCorporales::replace: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data->id;

            $stmt = $db->prepare("DELETE FROM unidades_medidas_corporales WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en UnidadesMedidasCorporales::delete: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}