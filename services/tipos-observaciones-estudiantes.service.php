<?php
class TiposObservacionesEstudiantes
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, valida_asistencia, requiere_firma, aplica_informe, icono FROM tipos_observaciones_estudiantes order by nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, valida_asistencia, requiere_firma, aplica_informe, icono FROM tipos_observaciones_estudiantes WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];
        $valida_asistencia = Flight::request()->data['valida_asistencia'];
        $requiere_firma = Flight::request()->data['requiere_firma'];
        $aplica_informe = !empty(Flight::request()->data['aplica_informe']) ? 1 : 0;
        $icono = Flight::request()->data['icono'] ?? null;

        $sentence = $db->prepare("INSERT INTO tipos_observaciones_estudiantes(nombre, valida_asistencia, requiere_firma, aplica_informe, icono) 
                                VALUES (:nombre, :valida_asistencia, :requiere_firma, :aplica_informe, :icono)");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':valida_asistencia', $valida_asistencia);
        $sentence->bindParam(':requiere_firma', $requiere_firma);
        $sentence->bindParam(':aplica_informe', $aplica_informe);
        $sentence->bindParam(':icono', $icono);

        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $valida_asistencia = Flight::request()->data['valida_asistencia'];
        $requiere_firma = Flight::request()->data['requiere_firma'];
        $aplica_informe = !empty(Flight::request()->data['aplica_informe']) ? 1 : 0;
        $icono = Flight::request()->data['icono'] ?? null;

        $sentence = $db->prepare("UPDATE tipos_observaciones_estudiantes 
                                SET nombre = :nombre, 
                                    valida_asistencia = :valida_asistencia, 
                                    requiere_firma = :requiere_firma,
                                    aplica_informe = :aplica_informe,
                                    icono = :icono 
                                WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':valida_asistencia', $valida_asistencia);
        $sentence->bindParam(':requiere_firma', $requiere_firma);
        $sentence->bindParam(':aplica_informe', $aplica_informe);
        $sentence->bindParam(':icono', $icono);

        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM tipos_observaciones_estudiantes WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
}