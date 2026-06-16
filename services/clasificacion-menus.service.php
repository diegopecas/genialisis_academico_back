<?php
class ClasificacionMenus
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, nombre FROM clasificacion_menus ORDER BY id");
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en ClasificacionMenus->getAll(): ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener clasificaciones de menú',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, nombre FROM clasificacion_menus WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetch();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en ClasificacionMenus->getById(): ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener la clasificación de menú',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
}