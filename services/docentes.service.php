<?php
class Docentes
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT d.id, d.id_persona, d.id_colaborador, d.activo, p.primer_nombre, p.segundo_nombre,
        p.primer_apellido, p.segundo_apellido, p.id_tipo_identificacion, ti.nombre tipo_identificacion,
        p.numero_identificacion, p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion, 
        p.telefono, p.correo_electronico,
        cd.id id_casa_docente, cd.nombre nombre_casa_docente,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo
        FROM docentes d 
        INNER JOIN personas p ON d.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        INNER JOIN generos g ON p.id_genero = g.id
        LEFT OUTER JOIN casas_docentes cd ON d.id_casa_docente = cd.id
        ORDER BY p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido");
        $sentence->execute();
        $response = $sentence->fetchAll();
        foreach ($response as &$row) {
            if (isset($row['nombre_completo'])) {
                $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
            }
        }
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT d.id, d.id_persona, d.id_colaborador, d.activo, p.primer_nombre, p.segundo_nombre,
        p.primer_apellido, p.segundo_apellido, p.id_tipo_identificacion, ti.nombre tipo_identificacion,
        p.numero_identificacion, p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion,
        p.telefono, p.correo_electronico,
        cd.id id_casa_docente, cd.nombre nombre_casa_docente,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad
        FROM docentes d 
        INNER JOIN personas p ON d.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        INNER JOIN generos g ON p.id_genero = g.id
        LEFT OUTER JOIN casas_docentes cd ON d.id_casa_docente = cd.id
        WHERE d.id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        if (!empty($response)) {
            foreach ($response as &$row) {
                if (isset($row['nombre_completo'])) {
                    $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
                }
            }
        }
        Flight::json($response);
    }

    public static function getByIdPersona($id_persona)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT d.id, d.id_persona, d.id_colaborador, d.activo, p.primer_nombre, p.segundo_nombre,
        p.primer_apellido, p.segundo_apellido, p.id_tipo_identificacion, ti.nombre tipo_identificacion,
        p.numero_identificacion, p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion,
        p.telefono, p.correo_electronico,
        cd.id id_casa_docente, cd.nombre nombre_casa_docente
        FROM docentes d 
        INNER JOIN personas p ON d.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        INNER JOIN generos g ON p.id_genero = g.id
        LEFT OUTER JOIN casas_docentes cd ON d.id_casa_docente = cd.id
        WHERE d.id_persona = :id_persona");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdColaborador($id_colaborador)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT d.id, d.id_persona, d.id_colaborador, d.activo, 
                                    p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
                                    p.id_tipo_identificacion, ti.nombre tipo_identificacion,
                                    p.numero_identificacion, p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, 
                                    p.direccion, p.telefono, p.correo_electronico,
                                    cd.id id_casa_docente, cd.nombre nombre_casa_docente,
                                    CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                                        IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo
                                FROM docentes d 
                                INNER JOIN personas p ON d.id_persona = p.id
                                INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
                                INNER JOIN generos g ON p.id_genero = g.id
                                LEFT OUTER JOIN casas_docentes cd ON d.id_casa_docente = cd.id
                                WHERE d.id_colaborador = :id_colaborador");
        $sentence->bindParam(':id_colaborador', $id_colaborador);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $id_persona = Flight::request()->data['id_persona'];
            $id_casa_docente = isset(Flight::request()->data['id_casa_docente']) ? Flight::request()->data['id_casa_docente'] : null;
            $id_colaborador = isset(Flight::request()->data['id_colaborador']) ? Flight::request()->data['id_colaborador'] : null;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            error_log("Datos recibidos para crear docente: id_persona=$id_persona, id_casa_docente=$id_casa_docente, id_colaborador=$id_colaborador, activo=$activo");

            $checkSentence = $db->prepare("SELECT id FROM docentes WHERE id_persona = :id_persona");
            $checkSentence->bindParam(':id_persona', $id_persona);
            $checkSentence->execute();
            if ($checkSentence->fetch()) {
                Flight::json(array('error' => 'Ya existe un docente registrado con esta persona'), 400);
                return;
            }

            if ($id_colaborador !== null) {
                $checkColaborador = $db->prepare("SELECT id FROM docentes WHERE id_colaborador = :id_colaborador");
                $checkColaborador->bindParam(':id_colaborador', $id_colaborador);
                $checkColaborador->execute();
                if ($checkColaborador->fetch()) {
                    Flight::json(array('error' => 'Ya existe un docente registrado con este colaborador'), 400);
                    return;
                }
            }

            $sentence = $db->prepare("INSERT INTO docentes(id_persona, id_colaborador, activo, id_casa_docente) 
                VALUES (:id_persona, :id_colaborador, :activo, :id_casa_docente)");
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_colaborador', $id_colaborador);
            $sentence->bindParam(':id_casa_docente', $id_casa_docente);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();

            $id = $db->lastInsertId();
            if ($id == 0) {
                error_log("Error: El ID insertado es 0.");
                Flight::json(array('error' => 'No se pudo crear el docente. Intente de nuevo.'), 500);
                return;
            }

            error_log("ID docente insertado: $id");
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en new de docentes: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $id_persona = Flight::request()->data['id_persona'];
            $id_casa_docente = isset(Flight::request()->data['id_casa_docente']) ? Flight::request()->data['id_casa_docente'] : null;
            $id_colaborador = isset(Flight::request()->data['id_colaborador']) ? Flight::request()->data['id_colaborador'] : null;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            error_log("Datos recibidos para actualizar docente: id=$id, id_persona=$id_persona, id_casa_docente=$id_casa_docente, id_colaborador=$id_colaborador, activo=$activo");

            $checkSentence = $db->prepare("SELECT id FROM docentes WHERE id_persona = :id_persona AND id != :id");
            $checkSentence->bindParam(':id_persona', $id_persona);
            $checkSentence->bindParam(':id', $id);
            $checkSentence->execute();
            if ($checkSentence->fetch()) {
                Flight::json(array('error' => 'Ya existe otro docente registrado con esta persona'), 400);
                return;
            }

            if ($id_colaborador !== null) {
                $checkColaborador = $db->prepare("SELECT id FROM docentes WHERE id_colaborador = :id_colaborador AND id != :id");
                $checkColaborador->bindParam(':id_colaborador', $id_colaborador);
                $checkColaborador->bindParam(':id', $id);
                $checkColaborador->execute();
                if ($checkColaborador->fetch()) {
                    Flight::json(array('error' => 'Ya existe otro docente registrado con este colaborador'), 400);
                    return;
                }
            }

            $sentence = $db->prepare("UPDATE docentes SET 
                id_persona = :id_persona,
                id_colaborador = :id_colaborador,
                activo = :activo, 
                id_casa_docente = :id_casa_docente 
                WHERE id = :id");
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_colaborador', $id_colaborador);
            $sentence->bindParam(':id_casa_docente', $id_casa_docente);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en replace de docentes: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al actualizar el docente'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            error_log("Eliminando docente con id: $id");

            $checkGrupos = $db->prepare("SELECT COUNT(*) as total FROM docentes_x_grupos WHERE id_docente = :id AND activo = 1");
            $checkGrupos->bindParam(':id', $id);
            $checkGrupos->execute();
            $grupos = $checkGrupos->fetch();
            if ($grupos['total'] > 0) {
                Flight::json(array('error' => 'No se puede eliminar el docente porque tiene grupos asignados activos'), 400);
                return;
            }

            $checkAreas = $db->prepare("SELECT COUNT(*) as total FROM area_academica_x_grupo WHERE id_docente = :id");
            $checkAreas->bindParam(':id', $id);
            $checkAreas->execute();
            $areas = $checkAreas->fetch();
            if ($areas['total'] > 0) {
                Flight::json(array('error' => 'No se puede eliminar el docente porque tiene áreas académicas asignadas'), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM docentes WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el docente con el ID especificado'), 404);
                return;
            }

            Flight::json(array('id' => $id, 'message' => 'Docente eliminado correctamente'));
        } catch (Exception $e) {
            error_log("Error en delete de docentes: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al eliminar el docente'), 500);
        }
    }

    public static function verificarDuplicados()
    {
        $db = Flight::db();
        $id_persona = Flight::request()->data['id_persona'];
        error_log("Verificando duplicados de docente para id_persona: $id_persona");
        $sentence = $db->prepare("SELECT COUNT(*) as total FROM docentes WHERE id_persona = :id_persona");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json(array('existe' => $response['total'] > 0));
    }
}
