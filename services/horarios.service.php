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
                                    CONCAT_WS(' ', dp.primer_nombre, dp.primer_apellido) docente_nombre_completo
                                from horarios h inner join areas_academicas ac on ac.id = h.id_area_academica
                                inner join dias_semana ds on ds.id = h.id_dia_semana
                                inner join grupos g on h.id_grupo = g.id
                                inner join area_academica_x_grupo aaxg on aaxg.id_area_academica = ac.id and aaxg.id_grupo = g.id 
                                left join docentes d ON aaxg.id_docente = d.id
                                left join personas dp ON d.id_persona = dp.id
                                where h.id_tenant = :id_tenant
                                order by h.id_dia_semana, h.hora_inicial");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
                                    CONCAT_WS(' ', dp.primer_nombre, dp.primer_apellido) docente_nombre_completo
                                from horarios h inner join areas_academicas ac on ac.id = h.id_area_academica
                                inner join dias_semana ds on ds.id = h.id_dia_semana
                                inner join grupos g on h.id_grupo = g.id
                                inner join area_academica_x_grupo aaxg on aaxg.id_area_academica = ac.id and aaxg.id_grupo = g.id 
                                left join docentes d ON aaxg.id_docente = d.id
                                left join personas dp ON d.id_persona = dp.id
                                where h.id = :id and h.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
                                        WHEN d.id IS NOT NULL THEN CONCAT_WS(' ', dp.primer_nombre, dp.primer_apellido)
                                        ELSE NULL 
                                    END as docente_nombre_completo
                                from horarios h 
                                inner join areas_academicas ac on ac.id = h.id_area_academica
                                inner join dias_semana ds on ds.id = h.id_dia_semana
                                inner join grupos g on h.id_grupo = g.id
                                left join area_academica_x_grupo aaxg on aaxg.id_area_academica = ac.id and aaxg.id_grupo = g.id 
                                left join docentes d ON aaxg.id_docente = d.id
                                left join personas dp ON d.id_persona = dp.id
                                where h.id_grupo = :id_grupo and h.id_tenant = :id_tenant
                                order by h.id_dia_semana, h.hora_inicial");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
                                    CONCAT_WS(' ', dp.primer_nombre, dp.primer_apellido) docente_nombre_completo
                                from horarios h inner join areas_academicas ac on ac.id = h.id_area_academica
                                inner join dias_semana ds on ds.id = h.id_dia_semana
                                inner join grupos g on h.id_grupo = g.id
                                inner join area_academica_x_grupo aaxg on aaxg.id_area_academica = ac.id and aaxg.id_grupo = g.id 
                                left join docentes d ON aaxg.id_docente = d.id
                                left join personas dp ON d.id_persona = dp.id
                                where h.id_area_academica = :id_area_academica and h.id_tenant = :id_tenant
                                order by h.id_dia_semana, h.hora_inicial");
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
        
        $idNew = Uuid::generar();
        $sentence = $db->prepare("insert into horarios(id, id_tenant, id_grupo, id_area_academica, id_dia_semana, hora_inicial, hora_final, total_minutos, total_clases) 
                                  values (:id, :id_tenant, :id_grupo, :id_area_academica, :id_dia_semana, :hora_inicial, :hora_final, :total_minutos, :total_clases)");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->bindParam(':id_dia_semana', $id_dia_semana);
        $sentence->bindParam(':hora_inicial', $hora_inicial);
        $sentence->bindParam(':hora_final', $hora_final);
        $sentence->bindParam(':total_minutos', $total_minutos);
        $sentence->bindParam(':total_clases', $total_clases);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    /**
     * Guarda y elimina varias franjas del mismo grupo en una sola transaccion.
     * Espera: id_grupo, horarios[] con id_area_academica, id_dia_semana,
     * hora_inicial, hora_final, total_minutos y total_clases, y eliminar[] con
     * los ids de las franjas a borrar.
     * Valida solapamiento contra lo ya guardado (sin contar lo que se elimina)
     * y entre las franjas enviadas.
     */
    public static function guardarLote()
    {
        $db = Flight::db();
        try {
            $id_grupo = Flight::request()->data['id_grupo'];
            $horarios = Flight::request()->data['horarios'];
            $eliminar = Flight::request()->data['eliminar'];

            $horarios = is_array($horarios) ? $horarios : [];
            $eliminar = is_array($eliminar) ? $eliminar : [];

            if (empty($id_grupo)) {
                Flight::json(array('error' => 'No se recibio el grupo'), 400);
                return;
            }

            if (count($horarios) === 0 && count($eliminar) === 0) {
                Flight::json(array('error' => 'No se recibieron cambios para guardar'), 400);
                return;
            }

            // Franjas ya guardadas del grupo, para validar cruces
            $sentence = $db->prepare("select id, id_dia_semana, hora_inicial, hora_final from horarios where id_grupo = :id_grupo and id_tenant = :id_tenant");
            $sentence->bindParam(':id_grupo', $id_grupo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $existentes = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // Las que se van a eliminar dejan de ocupar espacio en la validacion
            $ocupadas = [];
            foreach ($existentes as $ex) {
                if (in_array($ex['id'], $eliminar)) {
                    continue;
                }
                $ocupadas[] = [
                    'id_dia_semana' => $ex['id_dia_semana'],
                    'inicio' => substr($ex['hora_inicial'], 0, 5),
                    'fin' => substr($ex['hora_final'], 0, 5)
                ];
            }

            foreach ($horarios as $indice => $franja) {
                $dia = $franja['id_dia_semana'];
                $inicio = substr($franja['hora_inicial'], 0, 5);
                $fin = substr($franja['hora_final'], 0, 5);

                if ($fin <= $inicio) {
                    Flight::json(array('error' => 'La hora final debe ser mayor que la hora inicial'), 400);
                    return;
                }

                foreach ($ocupadas as $ocupada) {
                    if ($ocupada['id_dia_semana'] == $dia && $inicio < $ocupada['fin'] && $ocupada['inicio'] < $fin) {
                        Flight::json(array('error' => 'La franja ' . $inicio . ' - ' . $fin . ' se cruza con otra del mismo dia'), 400);
                        return;
                    }
                }

                $ocupadas[] = ['id_dia_semana' => $dia, 'inicio' => $inicio, 'fin' => $fin];
            }

            $db->beginTransaction();

            // Primero se borra, para liberar las franjas que se reemplazan
            $eliminados = 0;
            if (count($eliminar) > 0) {
                $borrar = $db->prepare("delete from horarios where id = :id and id_grupo = :id_grupo and id_tenant = :id_tenant");
                foreach ($eliminar as $idHorario) {
                    $borrar->bindParam(':id', $idHorario);
                    $borrar->bindParam(':id_grupo', $id_grupo);
                    $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $borrar->execute();
                    $eliminados += $borrar->rowCount();
                }
            }

            $sentence = $db->prepare("insert into horarios(id, id_tenant, id_grupo, id_area_academica, id_dia_semana, hora_inicial, hora_final, total_minutos, total_clases)
                                      values (:id, :id_tenant, :id_grupo, :id_area_academica, :id_dia_semana, :hora_inicial, :hora_final, :total_minutos, :total_clases)");

            $ids = [];
            foreach ($horarios as $franja) {
                $idNew = Uuid::generar();
                $id_area_academica = $franja['id_area_academica'];
                $id_dia_semana = $franja['id_dia_semana'];
                $hora_inicial = $franja['hora_inicial'];
                $hora_final = $franja['hora_final'];
                $total_minutos = $franja['total_minutos'];
                $total_clases = isset($franja['total_clases']) ? $franja['total_clases'] : 1;

                $sentence->bindValue(':id', $idNew);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindParam(':id_grupo', $id_grupo);
                $sentence->bindParam(':id_area_academica', $id_area_academica);
                $sentence->bindParam(':id_dia_semana', $id_dia_semana);
                $sentence->bindParam(':hora_inicial', $hora_inicial);
                $sentence->bindParam(':hora_final', $hora_final);
                $sentence->bindParam(':total_minutos', $total_minutos);
                $sentence->bindParam(':total_clases', $total_clases);
                $sentence->execute();

                $ids[] = $idNew;
            }

            $db->commit();
            Flight::json(array('ids' => $ids, 'total' => count($ids), 'eliminados' => $eliminados));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en guardarLote de horarios: ' . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al guardar los cambios de horarios'), 500);
        }
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
                                  where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->bindParam(':id_dia_semana', $id_dia_semana);
        $sentence->bindParam(':hora_inicial', $hora_inicial);
        $sentence->bindParam(':hora_final', $hora_final);
        $sentence->bindParam(':total_minutos', $total_minutos);
        $sentence->bindParam(':total_clases', $total_clases);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from horarios where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id, 'deleted' => true));
    }
}