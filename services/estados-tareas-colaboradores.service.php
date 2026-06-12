<?php
class EstadosTareasColaboradores
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, nombre, color, activo FROM estados_tareas_colaboradores WHERE activo = 1 ORDER BY id");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en EstadosTareasColaboradores::getAll: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener estados'), 500);
        }
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, nombre, color, activo FROM estados_tareas_colaboradores WHERE id = :id");
            $sentence->bindParam(':id', $id, PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en EstadosTareasColaboradores::getById: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener estado'), 500);
        }
    }
}