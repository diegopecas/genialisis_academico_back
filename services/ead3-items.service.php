<?php

class Ead3Items
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT i.*, r.nombre AS nombre_rango 
            FROM ead3_items i
            INNER JOIN ead3_rangos_edad r ON i.id_rango_edad = r.id
            ORDER BY i.area, i.orden
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByArea($area)
    {
        $db = Flight::db();
        $area = strtoupper($area);
        $sentence = $db->prepare("
            SELECT i.*, r.nombre AS nombre_rango 
            FROM ead3_items i
            INNER JOIN ead3_rangos_edad r ON i.id_rango_edad = r.id
            WHERE i.area = :area
            ORDER BY i.orden
        ");
        $sentence->bindParam(':area', $area);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByRango($id_rango)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT i.*, r.nombre AS nombre_rango 
            FROM ead3_items i
            INNER JOIN ead3_rangos_edad r ON i.id_rango_edad = r.id
            WHERE i.id_rango_edad = :id_rango
            ORDER BY i.area, i.orden
        ");
        $sentence->bindParam(':id_rango', $id_rango);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByAreaRango($area, $id_rango)
    {
        $db = Flight::db();
        $area = strtoupper($area);
        $sentence = $db->prepare("
            SELECT i.*, r.nombre AS nombre_rango 
            FROM ead3_items i
            INNER JOIN ead3_rangos_edad r ON i.id_rango_edad = r.id
            WHERE i.area = :area AND i.id_rango_edad = :id_rango
            ORDER BY i.orden
        ");
        $sentence->bindParam(':area', $area);
        $sentence->bindParam(':id_rango', $id_rango);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getItemsParaEvaluar($id_rango)
    {
        $db = Flight::db();
        $rango = intval($id_rango);
        $rango_anterior = max(1, $rango - 1);
        $rango_siguiente = min(12, $rango + 1);

        $sentence = $db->prepare("
            SELECT i.*, r.nombre AS nombre_rango 
            FROM ead3_items i
            INNER JOIN ead3_rangos_edad r ON i.id_rango_edad = r.id
            WHERE i.id_rango_edad BETWEEN :rango_anterior AND :rango_siguiente
            ORDER BY i.area, i.orden
        ");
        $sentence->bindParam(':rango_anterior', $rango_anterior);
        $sentence->bindParam(':rango_siguiente', $rango_siguiente);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}