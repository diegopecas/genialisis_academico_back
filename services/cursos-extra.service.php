<?php 
class CursosExtra
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, descripcion, icono, color, cupo_maximo, 
        fecha_inicio, fecha_fin, anio, activo, fecha_registro
        FROM cursos_extra
        WHERE id_tenant = :id_tenant
        ORDER BY nombre ASC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, descripcion, icono, color, cupo_maximo, 
        fecha_inicio, fecha_fin, anio, activo, fecha_registro
        FROM cursos_extra
        WHERE activo = 1
        AND id_tenant = :id_tenant
        ORDER BY nombre ASC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, descripcion, icono, color, cupo_maximo, 
        fecha_inicio, fecha_fin, anio, activo, fecha_registro
        FROM cursos_extra
        WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByAnio($anio)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, descripcion, icono, color, cupo_maximo, 
        fecha_inicio, fecha_fin, anio, activo, fecha_registro
        FROM cursos_extra
        WHERE anio = :anio AND id_tenant = :id_tenant
        ORDER BY nombre ASC");
        $sentence->bindParam(':anio', $anio);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];
        $descripcion = Flight::request()->data['descripcion'];
        $icono = Flight::request()->data['icono'];
        $color = Flight::request()->data['color'];
        $cupo_maximo = Flight::request()->data['cupo_maximo'];
        $fecha_inicio = Flight::request()->data['fecha_inicio'];
        $fecha_fin = Flight::request()->data['fecha_fin'];
        $anio = Flight::request()->data['anio'];

        $idNew = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO cursos_extra(id, id_tenant, nombre, descripcion, icono, color, cupo_maximo, fecha_inicio, fecha_fin, anio, activo, fecha_registro) 
        VALUES (:id, :id_tenant, :nombre, :descripcion, :icono, :color, :cupo_maximo, :fecha_inicio, :fecha_fin, :anio, 1, NOW())");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':color', $color);
        $sentence->bindParam(':cupo_maximo', $cupo_maximo);
        $sentence->bindParam(':fecha_inicio', $fecha_inicio);
        $sentence->bindParam(':fecha_fin', $fecha_fin);
        $sentence->bindParam(':anio', $anio, PDO::PARAM_INT);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $descripcion = Flight::request()->data['descripcion'];
        $icono = Flight::request()->data['icono'];
        $color = Flight::request()->data['color'];
        $cupo_maximo = Flight::request()->data['cupo_maximo'];
        $fecha_inicio = Flight::request()->data['fecha_inicio'];
        $fecha_fin = Flight::request()->data['fecha_fin'];
        $anio = Flight::request()->data['anio'];
        $activo = Flight::request()->data['activo'];

        $sentence = $db->prepare("UPDATE cursos_extra SET nombre = :nombre, descripcion = :descripcion, icono = :icono, color = :color, 
        cupo_maximo = :cupo_maximo, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin, 
        anio = :anio, activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':color', $color);
        $sentence->bindParam(':cupo_maximo', $cupo_maximo);
        $sentence->bindParam(':fecha_inicio', $fecha_inicio);
        $sentence->bindParam(':fecha_fin', $fecha_fin);
        $sentence->bindParam(':anio', $anio, PDO::PARAM_INT);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    public static function getInscritos($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exce.id, exce.id_estudiante, exce.id_curso_extra, exce.fecha_inscripcion, exce.anio, exce.activo,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo
        FROM estudiantes_x_cursos_extra exce
        INNER JOIN estudiantes e ON exce.id_estudiante = e.id
        INNER JOIN personas p ON e.id_persona = p.id
        WHERE exce.id_curso_extra = :id AND exce.id_tenant = :id_tenant
        ORDER BY p.primer_apellido, p.primer_nombre");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getEstudiantesDisponibles($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT e.id, e.id_persona,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo
        FROM estudiantes e
        INNER JOIN personas p ON e.id_persona = p.id
        WHERE e.activo = 1
        AND e.id NOT IN (SELECT id_estudiante FROM estudiantes_x_cursos_extra WHERE id_curso_extra = :id AND activo = 1)
        AND e.id_tenant = :id_tenant
        ORDER BY p.primer_apellido, p.primer_nombre");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

}