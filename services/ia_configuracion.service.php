<?php
class IaConfiguracion
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, clave, valor, descripcion, fecha_actualizacion
            FROM ia_configuracion
            ORDER BY id
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, clave, valor, descripcion, fecha_actualizacion
            FROM ia_configuracion
            WHERE id = :id
        ");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id = $data['id'];
        $valor = isset($data['valor']) ? $data['valor'] : null;
        $descripcion = isset($data['descripcion']) ? $data['descripcion'] : null;

        $sentence = $db->prepare("
            UPDATE ia_configuracion SET 
                valor = :valor,
                descripcion = :descripcion
            WHERE id = :id
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':valor', $valor);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->execute();

        self::getById($id);
    }
}