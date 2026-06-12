<?php
class TiposMediosPagoFinancieros {
    
    public static function getAll() {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT * FROM tipos_medios_pago_financieros ORDER BY id");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en tipos_medios_pago_financieros getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id) {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT * FROM tipos_medios_pago_financieros WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en tipos_medios_pago_financieros getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new() {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                INSERT INTO tipos_medios_pago_financieros (nombre, descripcion) 
                VALUES (:nombre, :descripcion)
            ");

            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':descripcion', $data['descripcion']);

            $sentence->execute();
            $id = $db->lastInsertId();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_medios_pago_financieros new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace() {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                UPDATE tipos_medios_pago_financieros 
                SET nombre = :nombre, descripcion = :descripcion
                WHERE id = :id
            ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':descripcion', $data['descripcion']);

            $sentence->execute();
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en tipos_medios_pago_financieros replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete() {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM tipos_medios_pago_financieros WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_medios_pago_financieros delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}