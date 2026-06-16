<?php
class Horarios
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select h.id,
                                    h.id_grupo,
                                    h.id_area_academica,
                                    h.id_dia_semana,
                                    aaxg.id_docente,
                                    h.hora_inicial,
                                    h.hora_final,
                                    h.total_minutos,
                                    h.total_clases,
                                    ac.nombre area_academica_nombre,
                                    ac.color area_academica_color,
                                    ds.nombre dia_semana_nombre,
                                    g.nombre grupo_nombre,
                                    CONCAT(dp.primer_nombre, ' ', dp.primer_apellido) docente_nombre_completo
                                from horarios h inner join areas_academicas ac on ac.id = h.id_area_academica
                                inner join dias_semana ds on ds.id = h.id_dia_semana
                                inner join grupos g on h.id_grupo = g.id
                                inner join area_academica_x_grupo aaxg on aaxg.id_area_academica = ac.id and aaxg.id_grupo = g.id 
                                left join docentes d ON aaxg.id_docente = d.id
                                left join personas dp ON d.id_persona = dp.id
                                order by h.id_dia_semana, h.hora_inicial");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select h.id,
                                    h.id_grupo,
                                    h.id_area_academica,
                                    h.id_dia_semana,
                                    aaxg.id_docente,
                                    h.hora_inicial,
                                    h.hora_final,
                                    h.total_minutos,
                                    h.total_clases,
                                    ac.nombre area_academica_nombre,
                                    ac.color area_academica_color,
                                    ds.nombre dia_semana_nombre,
                                    g.nombre grupo_nombre,
                                    CONCAT(dp.primer_nombre, ' ', dp.primer_apellido) docente_nombre_completo
                                from horarios h inner join areas_academicas ac on ac.id = h.id_area_academica
                                inner join dias_semana ds on ds.id = h.id_dia_semana
                                inner join grupos g on h.id_grupo = g.id
                                inner join area_academica_x_grupo aaxg on aaxg.id_area_academica = ac.id and aaxg.id_grupo = g.id 
                                left join docentes d ON aaxg.id_docente = d.id
                                left join personas dp ON d.id_persona = dp.id
                                where h.id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByGrupo($id_grupo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select h.id,
                                    h.id_grupo,
                                    h.id_area_academica,
                                    h.id_dia_semana,
                                    aaxg.id_docente,
                                    h.hora_inicial,
                                    h.hora_final,
                                    h.total_minutos,
                                    h.total_clases,
                                    ac.nombre area_academica_nombre,
                                    ac.color area_academica_color,
                                    ds.nombre dia_semana_nombre,
                                    g.nombre grupo_nombre,
                                    CASE 
                                        WHEN d.id IS NOT NULL THEN CONCAT(dp.primer_nombre, ' ', dp.primer_apellido)
                                        ELSE NULL 
                                    END as docente_nombre_completo
                                from horarios h 
                                inner join areas_academicas ac on ac.id = h.id_area_academica
                                inner join dias_semana ds on ds.id = h.id_dia_semana
                                inner join grupos g on h.id_grupo = g.id
                                left join area_academica_x_grupo aaxg on aaxg.id_area_academica = ac.id and aaxg.id_grupo = g.id 
                                left join docentes d ON aaxg.id_docente = d.id
                                left join personas dp ON d.id_persona = dp.id
                                where h.id_grupo = :id_grupo
                                order by h.id_dia_semana, h.hora_inicial");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByArea($id_area_academica)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select h.id,
                                    h.id_grupo,
                                    h.id_area_academica,
                                    h.id_dia_semana,
                                    aaxg.id_docente,
                                    h.hora_inicial,
                                    h.hora_final,
                                    h.total_minutos,
                                    h.total_clases,
                                    ac.nombre area_academica_nombre,
                                    ac.color area_academica_color,
                                    ds.nombre dia_semana_nombre,
                                    g.nombre grupo_nombre,
                                    CONCAT(dp.primer_nombre, ' ', dp.primer_apellido) docente_nombre_completo
                                from horarios h inner join areas_academicas ac on ac.id = h.id_area_academica
                                inner join dias_semana ds on ds.id = h.id_dia_semana
                                inner join grupos g on h.id_grupo = g.id
                                inner join area_academica_x_grupo aaxg on aaxg.id_area_academica = ac.id and aaxg.id_grupo = g.id 
                                left join docentes d ON aaxg.id_docente = d.id
                                left join personas dp ON d.id_persona = dp.id
                                where h.id_area_academica = :id_area_academica
                                order by h.id_dia_semana, h.hora_inicial");
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_grupo = Flight::request()->data['id_grupo'];
        $id_area_academica = Flight::request()->data['id_area_academica'];
        $id_dia_semana = Flight::request()->data['id_dia_semana'];
        $hora_inicial = Flight::request()->data['hora_inicial'];
        $hora_final = Flight::request()->data['hora_final'];
        $total_minutos = Flight::request()->data['total_minutos'];
        $total_clases = Flight::request()->data['total_clases'] ?? 1;
        
        $sentence = $db->prepare("insert into horarios(id_grupo, id_area_academica, id_dia_semana, hora_inicial, hora_final, total_minutos, total_clases) 
                                  values (:id_grupo, :id_area_academica, :id_dia_semana, :hora_inicial, :hora_final, :total_minutos, :total_clases)");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->bindParam(':id_dia_semana', $id_dia_semana);
        $sentence->bindParam(':hora_inicial', $hora_inicial);
        $sentence->bindParam(':hora_final', $hora_final);
        $sentence->bindParam(':total_minutos', $total_minutos);
        $sentence->bindParam(':total_clases', $total_clases);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_grupo = Flight::request()->data['id_grupo'];
        $id_area_academica = Flight::request()->data['id_area_academica'];
        $id_dia_semana = Flight::request()->data['id_dia_semana'];
        $hora_inicial = Flight::request()->data['hora_inicial'];
        $hora_final = Flight::request()->data['hora_final'];
        $total_minutos = Flight::request()->data['total_minutos'];
        $total_clases = Flight::request()->data['total_clases'] ?? 1;
        
        $sentence = $db->prepare("update horarios set 
                                  id_grupo = :id_grupo, 
                                  id_area_academica = :id_area_academica, 
                                  id_dia_semana = :id_dia_semana, 
                                  hora_inicial = :hora_inicial, 
                                  hora_final = :hora_final, 
                                  total_minutos = :total_minutos, 
                                  total_clases = :total_clases 
                                  where id = :id");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->bindParam(':id_dia_semana', $id_dia_semana);
        $sentence->bindParam(':hora_inicial', $hora_inicial);
        $sentence->bindParam(':hora_final', $hora_final);
        $sentence->bindParam(':total_minutos', $total_minutos);
        $sentence->bindParam(':total_clases', $total_clases);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from horarios where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        Flight::json(array('id' => $id, 'deleted' => true));
    }
}