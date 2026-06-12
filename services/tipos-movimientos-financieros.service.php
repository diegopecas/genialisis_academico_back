<?php
class TiposMovimientosFinancieros {
    
    public static function getAll() {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT * FROM tipos_movimientos_financieros ORDER BY id");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en tipos_movimientos_financieros getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id) {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT * FROM tipos_movimientos_financieros WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en tipos_movimientos_financieros getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new() {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                INSERT INTO tipos_movimientos_financieros (nombre, icono, descripcion) 
                VALUES (:nombre, :icono, :descripcion)
            ");

            $icono = $data['icono'] ?? '?';
            
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':descripcion', $data['descripcion']);

            $sentence->execute();
            $id = $db->lastInsertId();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_movimientos_financieros new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace() {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                UPDATE tipos_movimientos_financieros 
                SET nombre = :nombre, icono = :icono, descripcion = :descripcion
                WHERE id = :id
            ");

            $icono = $data['icono'] ?? '?';

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':descripcion', $data['descripcion']);

            $sentence->execute();
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en tipos_movimientos_financieros replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete() {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM tipos_movimientos_financieros WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en tipos_movimientos_financieros delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}