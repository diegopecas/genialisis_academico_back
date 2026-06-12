<?php
class DatosMedicos
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT dm.id, dm.id_tipo_dato_medico, dm.nombre, 
            dm.es_numero, dm.es_texto, dm.es_parrafo, dm.es_fecha, dm.opciones,
            dm.orden, dm.activo, tdm.nombre AS nombre_tipo, tdm.icono AS icono_tipo
            FROM datos_medicos dm
            INNER JOIN tipos_datos_medicos tdm ON dm.id_tipo_dato_medico = tdm.id
            ORDER BY tdm.orden, dm.orden, dm.nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT dm.id, dm.id_tipo_dato_medico, dm.nombre, 
            dm.es_numero, dm.es_texto, dm.es_parrafo, dm.es_fecha, dm.opciones,
            dm.orden, dm.activo, tdm.nombre AS nombre_tipo, tdm.icono AS icono_tipo
            FROM datos_medicos dm
            INNER JOIN tipos_datos_medicos tdm ON dm.id_tipo_dato_medico = tdm.id
            WHERE dm.id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByTipo($id_tipo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT dm.id, dm.id_tipo_dato_medico, dm.nombre, 
            dm.es_numero, dm.es_texto, dm.es_parrafo, dm.es_fecha, dm.opciones,
            dm.orden, dm.activo, tdm.nombre AS nombre_tipo, tdm.icono AS icono_tipo
            FROM datos_medicos dm
            INNER JOIN tipos_datos_medicos tdm ON dm.id_tipo_dato_medico = tdm.id
            WHERE dm.id_tipo_dato_medico = :id_tipo
            ORDER BY dm.orden, dm.nombre");
        $sentence->bindParam(':id_tipo', $id_tipo);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id_tipo_dato_medico = Flight::request()->data['id_tipo_dato_medico'];
        $nombre = Flight::request()->data['nombre'];
        $es_numero = isset(Flight::request()->data['es_numero']) ? Flight::request()->data['es_numero'] : 0;
        $es_texto = isset(Flight::request()->data['es_texto']) ? Flight::request()->data['es_texto'] : 0;
        $es_parrafo = isset(Flight::request()->data['es_parrafo']) ? Flight::request()->data['es_parrafo'] : 0;
        $es_fecha = isset(Flight::request()->data['es_fecha']) ? Flight::request()->data['es_fecha'] : 0;
        $opciones = isset(Flight::request()->data['opciones']) ? Flight::request()->data['opciones'] : null;
        $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
        $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

        $sentence = $db->prepare("INSERT INTO datos_medicos (id_tipo_dato_medico, nombre, es_numero, es_texto, es_parrafo, es_fecha, opciones, orden, activo) 
            VALUES (:id_tipo_dato_medico, :nombre, :es_numero, :es_texto, :es_parrafo, :es_fecha, :opciones, :orden, :activo)");
        $sentence->bindParam(':id_tipo_dato_medico', $id_tipo_dato_medico);
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
        $id_tipo_dato_medico = Flight::request()->data['id_tipo_dato_medico'];
        $nombre = Flight::request()->data['nombre'];
        $es_numero = isset(Flight::request()->data['es_numero']) ? Flight::request()->data['es_numero'] : 0;
        $es_texto = isset(Flight::request()->data['es_texto']) ? Flight::request()->data['es_texto'] : 0;
        $es_parrafo = isset(Flight::request()->data['es_parrafo']) ? Flight::request()->data['es_parrafo'] : 0;
        $es_fecha = isset(Flight::request()->data['es_fecha']) ? Flight::request()->data['es_fecha'] : 0;
        $opciones = isset(Flight::request()->data['opciones']) ? Flight::request()->data['opciones'] : null;
        $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
        $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

        $sentence = $db->prepare("UPDATE datos_medicos SET id_tipo_dato_medico = :id_tipo_dato_medico, nombre = :nombre, 
            es_numero = :es_numero, es_texto = :es_texto, es_parrafo = :es_parrafo, es_fecha = :es_fecha, 
            opciones = :opciones, orden = :orden, activo = :activo WHERE id = :id");
        $sentence->bindParam(':id_tipo_dato_medico', $id_tipo_dato_medico);
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
        $sentence = $db->prepare("DELETE FROM datos_medicos WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
}