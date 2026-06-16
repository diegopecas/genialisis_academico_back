<?php 
class HorariosCursosExtra
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT hce.id, hce.id_curso_extra, hce.id_dia_semana, hce.hora_inicial, hce.hora_final, hce.total_minutos,
        ds.nombre AS nombre_dia,
        ce.nombre AS nombre_curso
        FROM horarios_cursos_extra hce
        INNER JOIN dias_semana ds ON hce.id_dia_semana = ds.id
        INNER JOIN cursos_extra ce ON hce.id_curso_extra = ce.id
        ORDER BY hce.id_dia_semana, hce.hora_inicial");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT hce.id, hce.id_curso_extra, hce.id_dia_semana, hce.hora_inicial, hce.hora_final, hce.total_minutos,
        ds.nombre AS nombre_dia
        FROM horarios_cursos_extra hce
        INNER JOIN dias_semana ds ON hce.id_dia_semana = ds.id
        WHERE hce.id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByCurso($id_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT hce.id, hce.id_curso_extra, hce.id_dia_semana, hce.hora_inicial, hce.hora_final, hce.total_minutos,
        ds.nombre AS nombre_dia
        FROM horarios_cursos_extra hce
        INNER JOIN dias_semana ds ON hce.id_dia_semana = ds.id
        WHERE hce.id_curso_extra = :id_curso_extra
        ORDER BY hce.id_dia_semana, hce.hora_inicial");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_curso_extra = Flight::request()->data['id_curso_extra'];
        $id_dia_semana = Flight::request()->data['id_dia_semana'];
        $hora_inicial = Flight::request()->data['hora_inicial'];
        $hora_final = Flight::request()->data['hora_final'];
        $total_minutos = Flight::request()->data['total_minutos'];

        $sentence = $db->prepare("INSERT INTO horarios_cursos_extra(id_curso_extra, id_dia_semana, hora_inicial, hora_final, total_minutos) 
        VALUES (:id_curso_extra, :id_dia_semana, :hora_inicial, :hora_final, :total_minutos)");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindParam(':id_dia_semana', $id_dia_semana);
        $sentence->bindParam(':hora_inicial', $hora_inicial);
        $sentence->bindParam(':hora_final', $hora_final);
        $sentence->bindParam(':total_minutos', $total_minutos, PDO::PARAM_INT);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_dia_semana = Flight::request()->data['id_dia_semana'];
        $hora_inicial = Flight::request()->data['hora_inicial'];
        $hora_final = Flight::request()->data['hora_final'];
        $total_minutos = Flight::request()->data['total_minutos'];

        $sentence = $db->prepare("UPDATE horarios_cursos_extra SET id_dia_semana = :id_dia_semana, hora_inicial = :hora_inicial, 
        hora_final = :hora_final, total_minutos = :total_minutos WHERE id = :id");
        $sentence->bindParam(':id_dia_semana', $id_dia_semana);
        $sentence->bindParam(':hora_inicial', $hora_inicial);
        $sentence->bindParam(':hora_final', $hora_final);
        $sentence->bindParam(':total_minutos', $total_minutos, PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM horarios_cursos_extra WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

}