<?php 
class CursosExtra
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ce.id, ce.nombre, ce.descripcion, ce.icono, ce.color, ce.cupo_maximo, 
        ce.permite_sobrecupo, ce.cupo_minimo, ce.fecha_inicio, ce.fecha_fin, ce.fecha_limite_inscripcion,
        ce.edad_minima_meses, ce.edad_maxima_meses, ce.anio, ce.activo, ce.fecha_registro,
        ce.id_tipo_curso_extracurricular, ce.id_lugar_curso_extra,
        tce.nombre AS nombre_tipo_curso, lce.nombre AS nombre_lugar
        FROM cursos_extra ce
        LEFT JOIN tipos_cursos_extracurriculares tce ON ce.id_tipo_curso_extracurricular = tce.id
        LEFT JOIN lugares_cursos_extra lce ON ce.id_lugar_curso_extra = lce.id
        WHERE ce.id_tenant = :id_tenant
        ORDER BY ce.nombre ASC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ce.id, ce.nombre, ce.descripcion, ce.icono, ce.color, ce.cupo_maximo, 
        ce.permite_sobrecupo, ce.cupo_minimo, ce.fecha_inicio, ce.fecha_fin, ce.fecha_limite_inscripcion,
        ce.edad_minima_meses, ce.edad_maxima_meses, ce.anio, ce.activo, ce.fecha_registro,
        ce.id_tipo_curso_extracurricular, ce.id_lugar_curso_extra,
        tce.nombre AS nombre_tipo_curso, lce.nombre AS nombre_lugar
        FROM cursos_extra ce
        LEFT JOIN tipos_cursos_extracurriculares tce ON ce.id_tipo_curso_extracurricular = tce.id
        LEFT JOIN lugares_cursos_extra lce ON ce.id_lugar_curso_extra = lce.id
        WHERE ce.activo = 1
        AND ce.id_tenant = :id_tenant
        ORDER BY ce.nombre ASC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ce.id, ce.nombre, ce.descripcion, ce.icono, ce.color, ce.cupo_maximo, 
        ce.permite_sobrecupo, ce.cupo_minimo, ce.fecha_inicio, ce.fecha_fin, ce.fecha_limite_inscripcion,
        ce.edad_minima_meses, ce.edad_maxima_meses, ce.anio, ce.activo, ce.fecha_registro,
        ce.id_tipo_curso_extracurricular, ce.id_lugar_curso_extra,
        tce.nombre AS nombre_tipo_curso, lce.nombre AS nombre_lugar
        FROM cursos_extra ce
        LEFT JOIN tipos_cursos_extracurriculares tce ON ce.id_tipo_curso_extracurricular = tce.id
        LEFT JOIN lugares_cursos_extra lce ON ce.id_lugar_curso_extra = lce.id
        WHERE ce.id = :id AND ce.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByAnio($anio)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ce.id, ce.nombre, ce.descripcion, ce.icono, ce.color, ce.cupo_maximo, 
        ce.permite_sobrecupo, ce.cupo_minimo, ce.fecha_inicio, ce.fecha_fin, ce.fecha_limite_inscripcion,
        ce.edad_minima_meses, ce.edad_maxima_meses, ce.anio, ce.activo, ce.fecha_registro,
        ce.id_tipo_curso_extracurricular, ce.id_lugar_curso_extra,
        tce.nombre AS nombre_tipo_curso, lce.nombre AS nombre_lugar
        FROM cursos_extra ce
        LEFT JOIN tipos_cursos_extracurriculares tce ON ce.id_tipo_curso_extracurricular = tce.id
        LEFT JOIN lugares_cursos_extra lce ON ce.id_lugar_curso_extra = lce.id
        WHERE ce.anio = :anio AND ce.id_tenant = :id_tenant
        ORDER BY ce.nombre ASC");
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
        // Campos opcionales: se leen con isset porque los formularios viejos no los envian.
        $id_tipo = isset(Flight::request()->data['id_tipo_curso_extracurricular']) ? Flight::request()->data['id_tipo_curso_extracurricular'] : null;
        $id_lugar = isset(Flight::request()->data['id_lugar_curso_extra']) ? Flight::request()->data['id_lugar_curso_extra'] : null;
        $permite_sobrecupo = isset(Flight::request()->data['permite_sobrecupo']) ? Flight::request()->data['permite_sobrecupo'] : 0;
        $cupo_minimo = isset(Flight::request()->data['cupo_minimo']) ? Flight::request()->data['cupo_minimo'] : null;
        $fecha_limite = isset(Flight::request()->data['fecha_limite_inscripcion']) ? Flight::request()->data['fecha_limite_inscripcion'] : null;
        $edad_minima = isset(Flight::request()->data['edad_minima_meses']) ? Flight::request()->data['edad_minima_meses'] : null;
        $edad_maxima = isset(Flight::request()->data['edad_maxima_meses']) ? Flight::request()->data['edad_maxima_meses'] : null;

        $idNew = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO cursos_extra(id, id_tenant, nombre, descripcion, icono, color, cupo_maximo, fecha_inicio, fecha_fin, anio, activo, fecha_registro,
        id_tipo_curso_extracurricular, id_lugar_curso_extra, permite_sobrecupo, cupo_minimo, fecha_limite_inscripcion, edad_minima_meses, edad_maxima_meses) 
        VALUES (:id, :id_tenant, :nombre, :descripcion, :icono, :color, :cupo_maximo, :fecha_inicio, :fecha_fin, :anio, 1, NOW(),
        :id_tipo, :id_lugar, :permite_sobrecupo, :cupo_minimo, :fecha_limite, :edad_minima, :edad_maxima)");
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
        $sentence->bindValue(':id_tipo', $id_tipo);
        $sentence->bindValue(':id_lugar', $id_lugar);
        $sentence->bindValue(':permite_sobrecupo', $permite_sobrecupo, PDO::PARAM_INT);
        $sentence->bindValue(':cupo_minimo', $cupo_minimo);
        $sentence->bindValue(':fecha_limite', $fecha_limite);
        $sentence->bindValue(':edad_minima', $edad_minima);
        $sentence->bindValue(':edad_maxima', $edad_maxima);
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
        // Campos opcionales: se leen con isset porque los formularios viejos no los envian.
        $id_tipo = isset(Flight::request()->data['id_tipo_curso_extracurricular']) ? Flight::request()->data['id_tipo_curso_extracurricular'] : null;
        $id_lugar = isset(Flight::request()->data['id_lugar_curso_extra']) ? Flight::request()->data['id_lugar_curso_extra'] : null;
        $permite_sobrecupo = isset(Flight::request()->data['permite_sobrecupo']) ? Flight::request()->data['permite_sobrecupo'] : 0;
        $cupo_minimo = isset(Flight::request()->data['cupo_minimo']) ? Flight::request()->data['cupo_minimo'] : null;
        $fecha_limite = isset(Flight::request()->data['fecha_limite_inscripcion']) ? Flight::request()->data['fecha_limite_inscripcion'] : null;
        $edad_minima = isset(Flight::request()->data['edad_minima_meses']) ? Flight::request()->data['edad_minima_meses'] : null;
        $edad_maxima = isset(Flight::request()->data['edad_maxima_meses']) ? Flight::request()->data['edad_maxima_meses'] : null;

        $sentence = $db->prepare("UPDATE cursos_extra SET nombre = :nombre, descripcion = :descripcion, icono = :icono, color = :color, 
        cupo_maximo = :cupo_maximo, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin, 
        anio = :anio, activo = :activo,
        id_tipo_curso_extracurricular = :id_tipo, id_lugar_curso_extra = :id_lugar,
        permite_sobrecupo = :permite_sobrecupo, cupo_minimo = :cupo_minimo,
        fecha_limite_inscripcion = :fecha_limite, edad_minima_meses = :edad_minima, edad_maxima_meses = :edad_maxima
        WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tipo', $id_tipo);
        $sentence->bindValue(':id_lugar', $id_lugar);
        $sentence->bindValue(':permite_sobrecupo', $permite_sobrecupo, PDO::PARAM_INT);
        $sentence->bindValue(':cupo_minimo', $cupo_minimo);
        $sentence->bindValue(':fecha_limite', $fecha_limite);
        $sentence->bindValue(':edad_minima', $edad_minima);
        $sentence->bindValue(':edad_maxima', $edad_maxima);
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

    /**
     * Inscritos al curso (activos e inactivos).
     *
     * Devuelve id_persona porque la generacion de cuentas por cobrar trabaja
     * sobre la persona, y el grupo para el filtro de la pantalla.
     */
    public static function getInscritos($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exce.id, exce.id_estudiante, exce.id_curso_extra, exce.fecha_inscripcion, exce.anio, exce.activo,
        e.id_persona,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        g.id AS id_grupo, g.nombre AS nombre_grupo,
        (SELECT COUNT(*) FROM cuentas_cobrar_x_curso_extra ccxce
         WHERE ccxce.id_estudiante_x_curso_extra = exce.id AND ccxce.id_tenant = exce.id_tenant) AS total_cuentas
        FROM estudiantes_x_cursos_extra exce
        INNER JOIN estudiantes e ON exce.id_estudiante = e.id
        INNER JOIN personas p ON e.id_persona = p.id
        LEFT JOIN estudiantes_x_grupos eg ON eg.id_estudiante = e.id AND eg.activo = 1
        LEFT JOIN grupos g ON g.id = eg.id_grupo
        WHERE exce.id_curso_extra = :id AND exce.id_tenant = :id_tenant
        ORDER BY p.primer_apellido, p.primer_nombre");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Estudiantes que todavia no estan inscritos al curso.
     *
     * Devuelve tambien el grupo (para el filtro de la pantalla de inscripcion)
     * y la edad en meses a hoy, que es contra la que se valida el rango de edad
     * del curso antes de inscribir.
     */
    public static function getEstudiantesDisponibles($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT e.id, e.id_persona,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        p.fecha_nacimiento,
        TIMESTAMPDIFF(MONTH, p.fecha_nacimiento, CURDATE()) AS edad_meses,
        g.id AS id_grupo, g.nombre AS nombre_grupo
        FROM estudiantes e
        INNER JOIN personas p ON e.id_persona = p.id
        LEFT JOIN estudiantes_x_grupos eg ON eg.id_estudiante = e.id AND eg.activo = 1
        LEFT JOIN grupos g ON g.id = eg.id_grupo
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