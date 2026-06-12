<?php
class ConceptosMovimiento
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, tipo,
            CASE tipo 
                WHEN 'E' THEN 'Entrada'
                WHEN 'S' THEN 'Salida'
                WHEN 'I' THEN 'Inicial'
            END AS tipo_descripcion
            FROM conceptos_movimiento 
            ORDER BY tipo, nombre
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByTipo($tipo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, tipo,
            CASE tipo 
                WHEN 'E' THEN 'Entrada'
                WHEN 'S' THEN 'Salida'
                WHEN 'I' THEN 'Inicial'
            END AS tipo_descripcion
            FROM conceptos_movimiento 
            WHERE tipo = :tipo
            ORDER BY nombre
        ");
        $sentence->bindParam(':tipo', $tipo);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM conceptos_movimiento WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $nombre = Flight::request()->data['nombre'];
            $tipo = Flight::request()->data['tipo'];

            $sentence = $db->prepare("INSERT INTO conceptos_movimiento(nombre, tipo) VALUES (:nombre, :tipo)");
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':tipo', $tipo);
            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $tipo = Flight::request()->data['tipo'];

        $sentence = $db->prepare("UPDATE conceptos_movimiento SET nombre = :nombre, tipo = :tipo WHERE id = :id");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':tipo', $tipo);
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM conceptos_movimiento WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}