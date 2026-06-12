<?php
class DatosAdicionales
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT da.id, da.id_tipo_dato_adicional, da.nombre, 
            da.es_numero, da.es_texto, da.es_parrafo, da.es_fecha, da.opciones,
            da.orden, da.activo, tda.nombre AS nombre_tipo, tda.icono AS icono_tipo
            FROM datos_adicionales da
            INNER JOIN tipos_datos_adicionales tda ON da.id_tipo_dato_adicional = tda.id
            ORDER BY tda.orden, da.orden, da.nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT da.id, da.id_tipo_dato_adicional, da.nombre, 
            da.es_numero, da.es_texto, da.es_parrafo, da.es_fecha, da.opciones,
            da.orden, da.activo, tda.nombre AS nombre_tipo, tda.icono AS icono_tipo
            FROM datos_adicionales da
            INNER JOIN tipos_datos_adicionales tda ON da.id_tipo_dato_adicional = tda.id
            WHERE da.id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByTipo($id_tipo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT da.id, da.id_tipo_dato_adicional, da.nombre, 
            da.es_numero, da.es_texto, da.es_parrafo, da.es_fecha, da.opciones,
            da.orden, da.activo, tda.nombre AS nombre_tipo, tda.icono AS icono_tipo
            FROM datos_adicionales da
            INNER JOIN tipos_datos_adicionales tda ON da.id_tipo_dato_adicional = tda.id
            WHERE da.id_tipo_dato_adicional = :id_tipo
            ORDER BY da.orden, da.nombre");
        $sentence->bindParam(':id_tipo', $id_tipo);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id_tipo_dato_adicional = Flight::request()->data['id_tipo_dato_adicional'];
        $nombre = Flight::request()->data['nombre'];
        $es_numero = isset(Flight::request()->data['es_numero']) ? Flight::request()->data['es_numero'] : 0;
        $es_texto = isset(Flight::request()->data['es_texto']) ? Flight::request()->data['es_texto'] : 0;
        $es_parrafo = isset(Flight::request()->data['es_parrafo']) ? Flight::request()->data['es_parrafo'] : 0;
        $es_fecha = isset(Flight::request()->data['es_fecha']) ? Flight::request()->data['es_fecha'] : 0;
        $opciones = isset(Flight::request()->data['opciones']) ? Flight::request()->data['opciones'] : null;
        $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
        $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

        $sentence = $db->prepare("INSERT INTO datos_adicionales (id_tipo_dato_adicional, nombre, es_numero, es_texto, es_parrafo, es_fecha, opciones, orden, activo) 
            VALUES (:id_tipo_dato_adicional, :nombre, :es_numero, :es_texto, :es_parrafo, :es_fecha, :opciones, :orden, :activo)");
        $sentence->bindParam(':id_tipo_dato_adicional', $id_tipo_dato_adicional);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':es_numero', $es_numero);
        $sentence->bindParam(':es_texto', $es_texto);
        $sentence->bindParam(':es_parrafo', $es_parrafo);
        $sentence->bindParam(':es_fecha', $es_fecha);
        $sentence->bindParam(':opciones', $opciones);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->execute();

        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $id_tipo_dato_adicional = Flight::request()->data['id_tipo_dato_adicional'];
        $nombre = Flight::request()->data['nombre'];
        $es_numero = isset(Flight::request()->data['es_numero']) ? Flight::request()->data['es_numero'] : 0;
        $es_texto = isset(Flight::request()->data['es_texto']) ? Flight::request()->data['es_texto'] : 0;
        $es_parrafo = isset(Flight::request()->data['es_parrafo']) ? Flight::request()->data['es_parrafo'] : 0;
        $es_fecha = isset(Flight::request()->data['es_fecha']) ? Flight::request()->data['es_fecha'] : 0;
        $opciones = isset(Flight::request()->data['opciones']) ? Flight::request()->data['opciones'] : null;
        $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
        $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

        $sentence = $db->prepare("UPDATE datos_adicionales SET id_tipo_dato_adicional = :id_tipo_dato_adicional, nombre = :nombre, 
            es_numero = :es_numero, es_texto = :es_texto, es_parrafo = :es_parrafo, es_fecha = :es_fecha, 
            opciones = :opciones, orden = :orden, activo = :activo WHERE id = :id");
        $sentence->bindParam(':id_tipo_dato_adicional', $id_tipo_dato_adicional);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':es_numero', $es_numero);
        $sentence->bindParam(':es_texto', $es_texto);
        $sentence->bindParam(':es_parrafo', $es_parrafo);
        $sentence->bindParam(':es_fecha', $es_fecha);
        $sentence->bindParam(':opciones', $opciones);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM datos_adicionales WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
}