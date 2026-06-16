<?php

class Ead3TablasConversion
{
    public static function getByRangoArea($id_rango, $area)
    {
        $db = Flight::db();
        $area = strtoupper($area);
        $sentence = $db->prepare("
            SELECT * FROM ead3_tablas_conversion 
            WHERE id_rango_edad = :id_rango AND area = :area
            ORDER BY puntaje_directo
        ");
        $sentence->bindParam(':id_rango', $id_rango);
        $sentence->bindParam(':area', $area);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function convertir()
    {
        $db = Flight::db();

        $id_rango_edad = Flight::request()->data['id_rango_edad'];
        $area = strtoupper(Flight::request()->data['area']);
        $puntaje_directo = Flight::request()->data['puntaje_directo'];

        $sentence = $db->prepare("
            SELECT puntaje_tipico, clasificacion 
            FROM ead3_tablas_conversion 
            WHERE id_rango_edad = :id_rango_edad AND area = :area AND puntaje_directo = :puntaje_directo
        ");
        $sentence->bindParam(':id_rango_edad', $id_rango_edad);
        $sentence->bindParam(':area', $area);
        $sentence->bindParam(':puntaje_directo', $puntaje_directo);
        $sentence->execute();
        $response = $sentence->fetch();

        if (!$response) {
            Flight::json(array('error' => 'No se encontró conversión para estos parámetros'), 404);
            return;
        }

        Flight::json($response);
    }
}