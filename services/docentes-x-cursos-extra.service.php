<?php 
class DocentesXCursosExtra
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT dxce.id, dxce.id_docente, dxce.id_curso_extra, dxce.es_titular, dxce.activo, dxce.fecha_asignacion,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        ce.nombre AS nombre_curso
        FROM docentes_x_cursos_extra dxce
        INNER JOIN docentes d ON dxce.id_docente = d.id
        INNER JOIN personas p ON d.id_persona = p.id
        INNER JOIN cursos_extra ce ON dxce.id_curso_extra = ce.id
        ORDER BY p.primer_apellido, p.primer_nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT dxce.id, dxce.id_docente, dxce.id_curso_extra, dxce.es_titular, dxce.activo, dxce.fecha_asignacion,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo
        FROM docentes_x_cursos_extra dxce
        INNER JOIN docentes d ON dxce.id_docente = d.id
        INNER JOIN personas p ON d.id_persona = p.id
        WHERE dxce.id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByCurso($id_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT dxce.id, dxce.id_docente, dxce.id_curso_extra, dxce.es_titular, dxce.activo, dxce.fecha_asignacion,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo
        FROM docentes_x_cursos_extra dxce
        INNER JOIN docentes d ON dxce.id_docente = d.id
        INNER JOIN personas p ON d.id_persona = p.id
        WHERE dxce.id_curso_extra = :id_curso_extra
        ORDER BY dxce.es_titular DESC, p.primer_apellido, p.primer_nombre");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_docente = Flight::request()->data['id_docente'];
        $id_curso_extra = Flight::request()->data['id_curso_extra'];
        $es_titular = Flight::request()->data['es_titular'] ?? 0;

        $sentence = $db->prepare("INSERT INTO docentes_x_cursos_extra(id_docente, id_curso_extra, es_titular, activo, fecha_asignacion) 
        VALUES (:id_docente, :id_curso_extra, :es_titular, 1, NOW())");
        $sentence->bindParam(':id_docente', $id_docente);
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindParam(':es_titular', $es_titular);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $es_titular = Flight::request()->data['es_titular'];
        $activo = Flight::request()->data['activo'];

        $sentence = $db->prepare("UPDATE docentes_x_cursos_extra SET es_titular = :es_titular, activo = :activo WHERE id = :id");
        $sentence->bindParam(':es_titular', $es_titular);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM docentes_x_cursos_extra WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

}