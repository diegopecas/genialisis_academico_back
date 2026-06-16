<?php
class ActividadesAcademicasXIndicadoresLogros
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
                                    aaxil.id,
                                    aaxil.id_actividad_academica,
                                    aaxil.id_indicador_logro,
                                    aa.titulo as titulo_actividad,
                                    il.nombre as nombre_indicador,
                                    l.nombre as nombre_logro,
                                    gr.nombre as nombre_grado,
                                    ar.nombre as nombre_area,
                                    (SELECT JSON_ARRAYAGG(JSON_OBJECT('id', g.id, 'nombre', g.nombre))
                                     FROM grados_x_grupo gxg
                                     INNER JOIN grupos g ON gxg.id_grupo = g.id
                                     WHERE gxg.id_grado = l.id_grado) AS grupos_json
                                  FROM actividades_academicas_x_indicadores_logros aaxil
                                  INNER JOIN actividades_academicas aa ON aaxil.id_actividad_academica = aa.id
                                  INNER JOIN indicadores_logros il ON aaxil.id_indicador_logro = il.id
                                  LEFT JOIN logros l ON il.id_logro = l.id
                                  LEFT JOIN grados gr ON l.id_grado = gr.id
                                  LEFT JOIN areas_academicas ar ON l.id_area_academica = ar.id
                                  ORDER BY aaxil.id DESC");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM actividades_academicas_x_indicadores_logros WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByActividad($id_actividad)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
                                    aaxil.id,
                                    aaxil.id_actividad_academica,
                                    aaxil.id_indicador_logro,
                                    il.nombre as nombre_indicador,
                                    l.nombre as nombre_logro,
                                    gr.nombre as nombre_grado,
                                    ar.nombre as nombre_area,
                                    ca.nombre as nombre_corte,
                                    (SELECT JSON_ARRAYAGG(JSON_OBJECT('id', g.id, 'nombre', g.nombre))
                                     FROM grados_x_grupo gxg
                                     INNER JOIN grupos g ON gxg.id_grupo = g.id
                                     WHERE gxg.id_grado = l.id_grado) AS grupos_json
                                  FROM actividades_academicas_x_indicadores_logros aaxil
                                  INNER JOIN indicadores_logros il ON aaxil.id_indicador_logro = il.id
                                  LEFT JOIN logros l ON il.id_logro = l.id
                                  LEFT JOIN grados gr ON l.id_grado = gr.id
                                  LEFT JOIN areas_academicas ar ON l.id_area_academica = ar.id
                                  LEFT JOIN cortes_academicos ca ON l.id_corte_academico = ca.id
                                  WHERE aaxil.id_actividad_academica = :id_actividad
                                  ORDER BY il.nombre");
        $sentence->bindParam(':id_actividad', $id_actividad);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIndicador($id_indicador)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
                                    aaxil.id,
                                    aaxil.id_actividad_academica,
                                    aaxil.id_indicador_logro,
                                    aa.titulo as titulo_actividad,
                                    aa.descripcion as descripcion_actividad
                                  FROM actividades_academicas_x_indicadores_logros aaxil
                                  INNER JOIN actividades_academicas aa ON aaxil.id_actividad_academica = aa.id
                                  WHERE aaxil.id_indicador_logro = :id_indicador
                                  ORDER BY aa.titulo");
        $sentence->bindParam(':id_indicador', $id_indicador);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActividadesByLogro($id_logro)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT DISTINCT
        aa.id AS actividad_id,
        aa.titulo AS actividad_titulo,
        aa.descripcion AS actividad_descripcion,
        aa.nivel_uno AS actividad_nivel_uno,
        aa.nivel_dos AS actividad_nivel_dos,
        aa.minutos_duracion AS actividad_minutos_duracion,
        aa.materiales AS actividad_materiales,
        aa.id_tipo_actividad_academica AS actividad_id_tipo,
        taa.nombre AS actividad_tipo_nombre,
        il.id AS indicador_id,
        il.nombre AS indicador_nombre,
        GROUP_CONCAT(DISTINCT il.nombre SEPARATOR ', ') AS indicadores_nombres
        FROM indicadores_logros il
        INNER JOIN logros l
            ON il.id_logro = l.id
        INNER JOIN actividades_academicas_x_indicadores_logros aaxil
            ON aaxil.id_indicador_logro = il.id
        INNER JOIN actividades_academicas aa
            ON aa.id = aaxil.id_actividad_academica
        LEFT JOIN tipos_actividades_academicas taa
            ON aa.id_tipo_actividad_academica = taa.id
        WHERE il.id_logro = :id_logro
        GROUP BY aa.id, aa.titulo, aa.descripcion, aa.nivel_uno, aa.nivel_dos, 
                aa.minutos_duracion, aa.materiales, aa.id_tipo_actividad_academica, 
                taa.nombre
        ORDER BY aa.titulo
    ");
        $sentence->bindParam(':id_logro', $id_logro);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $id_actividad_academica = Flight::request()->data['id_actividad_academica'];
            $id_indicador_logro = Flight::request()->data['id_indicador_logro'];

            error_log("Creando asociación: actividad=$id_actividad_academica, indicador=$id_indicador_logro");

            // Validar datos requeridos
            if (!$id_actividad_academica || !$id_indicador_logro) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            // Verificar si ya existe la asociación
            $checkSentence = $db->prepare("SELECT COUNT(*) as count 
                                          FROM actividades_academicas_x_indicadores_logros 
                                          WHERE id_actividad_academica = :id_actividad 
                                          AND id_indicador_logro = :id_indicador");
            $checkSentence->bindParam(':id_actividad', $id_actividad_academica);
            $checkSentence->bindParam(':id_indicador', $id_indicador_logro);
            $checkSentence->execute();
            $result = $checkSentence->fetch();

            if ($result['count'] > 0) {
                Flight::json(array('error' => 'Esta asociación ya existe'), 409);
                return;
            }

            // Crear la asociación
            $sentence = $db->prepare("INSERT INTO actividades_academicas_x_indicadores_logros 
                                     (id_actividad_academica, id_indicador_logro) 
                                     VALUES (:id_actividad, :id_indicador)");
            $sentence->bindParam(':id_actividad', $id_actividad_academica);
            $sentence->bindParam(':id_indicador', $id_indicador_logro);
            $sentence->execute();

            $id = $db->lastInsertId();
            error_log("Asociación creada con ID: $id");

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al crear asociación: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear la asociación: ' . $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            if ($id) {
                // Eliminar por ID
                error_log("Eliminando asociación por ID: $id");
                $sentence = $db->prepare("DELETE FROM actividades_academicas_x_indicadores_logros WHERE id = :id");
                $sentence->bindParam(':id', $id);
            } else {
                // Eliminar por actividad e indicador
                $id_actividad_academica = Flight::request()->data['id_actividad_academica'];
                $id_indicador_logro = Flight::request()->data['id_indicador_logro'];

                error_log("Eliminando asociación: actividad=$id_actividad_academica, indicador=$id_indicador_logro");

                if (!$id_actividad_academica || !$id_indicador_logro) {
                    Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                    return;
                }

                $sentence = $db->prepare("DELETE FROM actividades_academicas_x_indicadores_logros 
                                         WHERE id_actividad_academica = :id_actividad 
                                         AND id_indicador_logro = :id_indicador");
                $sentence->bindParam(':id_actividad', $id_actividad_academica);
                $sentence->bindParam(':id_indicador', $id_indicador_logro);
            }

            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró la asociación especificada'), 404);
                return;
            }

            error_log("Asociación eliminada correctamente");
            Flight::json(array('message' => 'Asociación eliminada correctamente'));
        } catch (Exception $e) {
            error_log("Error al eliminar asociación: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar la asociación: ' . $e->getMessage()), 500);
        }
    }
}