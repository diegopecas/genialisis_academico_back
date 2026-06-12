<?php
class GoogleConfiguracion
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, clave, valor, descripcion, fecha_actualizacion
            FROM google_configuracion
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
            FROM google_configuracion
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
            UPDATE google_configuracion SET 
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

    /**
     * Obtiene el valor de una clave específica.
     * Uso interno desde otros services PHP.
     */
    public static function getValorPorClave($clave)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT valor FROM google_configuracion WHERE clave = :clave
        ");
        $sentence->bindParam(':clave', $clave);
        $sentence->execute();
        $row = $sentence->fetch();
        return $row ? $row['valor'] : null;
    }

    /**
     * Actualiza el valor de una clave específica.
     * Uso interno desde otros services PHP (ej: refrescar tokens).
     */
    public static function setValorPorClave($clave, $valor)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            UPDATE google_configuracion SET valor = :valor WHERE clave = :clave
        ");
        $sentence->bindParam(':clave', $clave);
        $sentence->bindParam(':valor', $valor);
        $sentence->execute();
    }
}