<?php
class MediosPagoFinancieros {
    
    public static function getAll() {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT m.*, t.nombre as tipo_nombre 
                FROM medios_pago_financieros m
                INNER JOIN tipos_medios_pago_financieros t ON m.id_tipo_medio_pago = t.id
                ORDER BY m.orden, m.nombre
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en medios_pago_financieros getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id) {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT m.*, t.nombre as tipo_nombre 
                FROM medios_pago_financieros m
                INNER JOIN tipos_medios_pago_financieros t ON m.id_tipo_medio_pago = t.id
                WHERE m.id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en medios_pago_financieros getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByTipo($id_tipo) {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT m.*, t.nombre as tipo_nombre 
                FROM medios_pago_financieros m
                INNER JOIN tipos_medios_pago_financieros t ON m.id_tipo_medio_pago = t.id
                WHERE m.id_tipo_medio_pago = :id_tipo
                ORDER BY m.orden, m.nombre
            ");
            $sentence->bindParam(':id_tipo', $id_tipo);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en medios_pago_financieros getByTipo: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new() {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                INSERT INTO medios_pago_financieros 
                (nombre, id_tipo_medio_pago, numero_cuenta, icono, color, descripcion, orden) 
                VALUES (:nombre, :id_tipo_medio_pago, :numero_cuenta, :icono, :color, :descripcion, :orden)
            ");

            $icono = $data['icono'] ?? '?';
            $color = $data['color'] ?? '#808080';
            $orden = $data['orden'] ?? 0;

            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':id_tipo_medio_pago', $data['id_tipo_medio_pago']);
            $sentence->bindParam(':numero_cuenta', $data['numero_cuenta']);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':color', $color);
            $sentence->bindParam(':descripcion', $data['descripcion']);
            $sentence->bindParam(':orden', $orden);

            $sentence->execute();
            $id = $db->lastInsertId();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en medios_pago_financieros new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace() {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                UPDATE medios_pago_financieros 
                SET nombre = :nombre, id_tipo_medio_pago = :id_tipo_medio_pago, numero_cuenta = :numero_cuenta, 
                    icono = :icono, color = :color, descripcion = :descripcion, orden = :orden
                WHERE id = :id
            ");

            $icono = $data['icono'] ?? '?';
            $color = $data['color'] ?? '#808080';
            $orden = $data['orden'] ?? 0;

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':id_tipo_medio_pago', $data['id_tipo_medio_pago']);
            $sentence->bindParam(':numero_cuenta', $data['numero_cuenta']);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':color', $color);
            $sentence->bindParam(':descripcion', $data['descripcion']);
            $sentence->bindParam(':orden', $orden);

            $sentence->execute();
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en medios_pago_financieros replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete() {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM medios_pago_financieros WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en medios_pago_financieros delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}