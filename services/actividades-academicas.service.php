<?php
class ActividadesAcademicas
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
                SELECT 
                    aa.id,
                    aa.titulo,
                    aa.descripcion,
                    aa.nivel_uno,
                    aa.nivel_dos,
                    aa.minutos_duracion,
                    aa.materiales,
                    aa.id_tipo_actividad_academica,
                    aa.id_ambiente,
                    ta.nombre as nombre_tipo_actividad,
                    amb.nombre as nombre_ambiente,
                    amb.icono as icono_ambiente
                FROM actividades_academicas aa
                LEFT JOIN tipos_actividades_academicas ta ON aa.id_tipo_actividad_academica = ta.id
                LEFT JOIN ambientes amb ON aa.id_ambiente = amb.id
                WHERE aa.id_tenant = :id_tenant
                ORDER BY aa.id DESC
            ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT aa.*, amb.nombre as nombre_ambiente, amb.icono as icono_ambiente
        FROM actividades_academicas aa 
        LEFT JOIN ambientes amb ON aa.id_ambiente = amb.id
        WHERE aa.id = :id AND aa.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdGrupo($id_grupo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT s.numero_sprint, g.id id_grupo, g.nombre nombre_grupo, area.id id_area_academica, area.nombre nombre_area, 
                                            aa.titulo, aa.descripcion, aa.materiales, aa.nivel_uno, aa.nivel_dos,
                                            txs.id id_tarea_x_sprint, txs.id_estado_tarea, et.nombre, txs.id_docente, txs.fecha_ejecucion,
                                            s.id id_sprint, il.nombre as indicador_logro_nombre
                                    FROM grupos g
                                    INNER JOIN grados_x_grupo gxg ON g.id = gxg.id_grupo
                                    INNER JOIN grados gr ON gxg.id_grado = gr.id
                                    INNER JOIN logros l ON gr.id = l.id_grado
                                    INNER JOIN areas_academicas area ON area.id = l.id_area_academica
                                    INNER JOIN indicadores_logros il ON l.id = il.id_logro 
                                    INNER JOIN actividades_academicas_x_indicadores_logros aaxil ON il.id = aaxil.id_indicador_logro 
                                    INNER JOIN actividades_academicas aa ON aa.id = aaxil.id_actividad_academica 
                                    INNER JOIN tareas_x_sprints txs ON aa.id = txs.id_actividad_academica 
                                    INNER JOIN sprints s ON s.id = txs.id_sprint 
                                    INNER JOIN estados_tareas et ON et.id = txs.id_estado_tarea
                                    WHERE g.id = :id_grupo AND g.id_tenant = :id_tenant");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdGrupoArea($id_grupo, $id_area_academica)
    {
        $db = Flight::db();

        $sprintQuery = $db->prepare("SELECT id FROM sprints WHERE actual = 1 AND id_tenant = :id_tenant LIMIT 1");
        $sprintQuery->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sprintQuery->execute();
        $sprintActual = $sprintQuery->fetch();

        if (!$sprintActual) {
            Flight::json(array('error' => 'No hay sprint actual configurado'), 404);
            return;
        }

        $id_sprint_actual = $sprintActual['id'];

        // Ahora usa txs.id_grupo y txs.id_area_academica directos
        $sentence = $db->prepare("SELECT 
                                s.numero_sprint, 
                                g.id id_grupo, 
                                g.nombre nombre_grupo, 
                                ar.id id_area_academica, 
                                ar.nombre nombre_area, 
                                aa.id id_actividad_academica,
                                aa.titulo, 
                                aa.descripcion, 
                                aa.materiales, 
                                aa.nivel_uno, 
                                aa.nivel_dos,
                                aa.minutos_duracion,
                                txs.id id_tarea_x_sprint, 
                                txs.id_estado_tarea, 
                                et.nombre nombre_estado, 
                                txs.id_docente, 
                                txs.fecha_ejecucion,
                                s.id id_sprint, 
                                s.es_evaluacion,
                                txs.id_docente_inicia, 
                                txs.fecha_ejecucion_inicia,
                                txs.orden_ejecucion,
                                GROUP_CONCAT(DISTINCT il.nombre ORDER BY il.nombre SEPARATOR ', ') as indicador_logro_nombre,
                                COUNT(DISTINCT il.id) as cantidad_indicadores
                        FROM tareas_x_sprints txs 
                        INNER JOIN actividades_academicas aa ON aa.id = txs.id_actividad_academica
                        INNER JOIN grupos g ON g.id = txs.id_grupo
                        INNER JOIN areas_academicas ar ON ar.id = txs.id_area_academica
                        INNER JOIN sprints s ON txs.id_sprint = s.id 
                        INNER JOIN estados_tareas et ON et.id = txs.id_estado_tarea
                        LEFT JOIN actividades_academicas_x_indicadores_logros aaxil ON aa.id = aaxil.id_actividad_academica 
                        LEFT JOIN indicadores_logros il ON il.id = aaxil.id_indicador_logro 
                        WHERE txs.id_grupo = :id_grupo
                        AND txs.id_tenant = :id_tenant
                        AND txs.id_area_academica = :id_area_academica
                        AND s.id = :id_sprint_actual
                        GROUP BY txs.id
                        ORDER BY txs.orden_ejecucion ASC, txs.id ASC");

        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->bindParam(':id_sprint_actual', $id_sprint_actual);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        error_log("Actividades encontradas para grupo $id_grupo, área $id_area_academica, sprint $id_sprint_actual: " . count($response));

        Flight::json($response);
    }

    public static function getByIdCategoriaActividad($id_categoria_actividad)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select aa.id, aa.titulo, aa.descripcion, 
        aa.id_tipo_actividad_academica, ta.nombre nombre_tipo_actividad,
        aa.minutos_duracion,
        aa.materiales 
        from actividades_academicas aa 
        left outer join tipos_actividades_academicas ta on aa.id_tipo_actividad_academica = ta.id 
        where aa.id_tipo_actividad_academica = :id_categoria_actividad AND aa.id_tenant = :id_tenant");
        $sentence->bindParam(':id_categoria_actividad', $id_categoria_actividad);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdObjetivoAcademico($id_objetivo_academico)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select aa.id, aa.titulo, aa.descripcion, 
        aa.id_tipo_actividad_academica, ta.nombre nombre_tipo_actividad,
        aa.minutos_duracion,
        aa.materiales 
        from actividades_academicas aa 
        left outer join tipos_actividades_academicas ta on aa.id_tipo_actividad_academica = ta.id 
        where aa.id_tipo_actividad_academica = :id_objetivo_academico AND aa.id_tenant = :id_tenant");
        $sentence->bindParam(':id_objetivo_academico', $id_objetivo_academico);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdAreaAcademica($id_area_academica)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT DISTINCT
            aa.id, aa.titulo, aa.descripcion, 
            aa.minutos_duracion, aa.materiales,
            aa.id_tipo_actividad_academica,
            ta.nombre as nombre_tipo_actividad
        FROM actividades_academicas aa
        LEFT JOIN tipos_actividades_academicas ta ON aa.id_tipo_actividad_academica = ta.id
        INNER JOIN actividades_academicas_x_indicadores_logros aaxil ON aa.id = aaxil.id_actividad_academica
        INNER JOIN indicadores_logros il ON aaxil.id_indicador_logro = il.id
        INNER JOIN logros l ON il.id_logro = l.id
        WHERE l.id_area_academica = :id_area_academica AND aa.id_tenant = :id_tenant");
        $sentence->bindParam(':id_area_academica', $id_area_academica);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdCorteAcademico($id_corte_academico)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT DISTINCT
            aa.id, aa.titulo, aa.descripcion, 
            aa.minutos_duracion, aa.materiales,
            aa.id_tipo_actividad_academica,
            ta.nombre as nombre_tipo_actividad
        FROM actividades_academicas aa
        LEFT JOIN tipos_actividades_academicas ta ON aa.id_tipo_actividad_academica = ta.id
        INNER JOIN actividades_academicas_x_indicadores_logros aaxil ON aa.id = aaxil.id_actividad_academica
        INNER JOIN indicadores_logros il ON aaxil.id_indicador_logro = il.id
        INNER JOIN logros l ON il.id_logro = l.id
        WHERE l.id_corte_academico = :id_corte_academico AND aa.id_tenant = :id_tenant");
        $sentence->bindParam(':id_corte_academico', $id_corte_academico);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }


    public static function new()
    {
        try {
            $db = Flight::db();
            $id_tipo_actividad_academica = Flight::request()->data['id_tipo_actividad_academica'];
            $titulo = Flight::request()->data['titulo'];
            $descripcion = Flight::request()->data['descripcion'];
            $nivel_uno = Flight::request()->data['nivel_uno'];
            $nivel_dos = Flight::request()->data['nivel_dos'];
            $minutos_duracion = Flight::request()->data['minutos_duracion'];
            $materiales = Flight::request()->data['materiales'];
            $id_ambiente = Flight::request()->data['id_ambiente'] ?? null;

            error_log("Datos recibidos: id_tipo_actividad_academica=$id_tipo_actividad_academica, titulo=$titulo");

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO actividades_academicas(
            id, id_tenant, id_tipo_actividad_academica, titulo, descripcion, 
            nivel_uno, nivel_dos, minutos_duracion, materiales, id_ambiente
            ) VALUES (
                :id, :id_tenant, :id_tipo_actividad_academica, :titulo, :descripcion, 
                :nivel_uno, :nivel_dos, :minutos_duracion, :materiales, :id_ambiente
            )");

            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_tipo_actividad_academica', $id_tipo_actividad_academica);
            $sentence->bindParam(':titulo', $titulo);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindParam(':nivel_uno', $nivel_uno);
            $sentence->bindParam(':nivel_dos', $nivel_dos);
            $sentence->bindParam(':minutos_duracion', $minutos_duracion);
            $sentence->bindParam(':materiales', $materiales);
            $sentence->bindParam(':id_ambiente', $id_ambiente);

            $sentence->execute();

            $id = $idNew;

            if ($id == 0) {
                error_log("Error: El ID insertado es 0.");
            }

            error_log("ID insertado: $id");

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método new: " . $e->getMessage());
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();

            $id = Flight::request()->data['id'];
            $id_tipo_actividad_academica = Flight::request()->data['id_tipo_actividad_academica'];
            $titulo = Flight::request()->data['titulo'];
            $descripcion = Flight::request()->data['descripcion'];
            $nivel_uno = Flight::request()->data['nivel_uno'] ?? '';
            $nivel_dos = Flight::request()->data['nivel_dos'] ?? '';
            $minutos_duracion = Flight::request()->data['minutos_duracion'];
            $materiales = Flight::request()->data['materiales'];
            $id_ambiente = Flight::request()->data['id_ambiente'] ?? null;

            error_log("Datos recibidos para actualización: id=$id, titulo=$titulo");

            // Obligatorios: los que la tabla exige como NOT NULL.
            // nivel_uno y nivel_dos quedan por fuera: estan ocultos en el
            // formulario y llegan vacios, asi que exigirlos impedia editar
            // cualquier actividad.
            // Se comparan contra '' y no con ! para no rechazar un 0 valido.
            $faltantes = ($id === null || $id === '')
                || ($id_tipo_actividad_academica === null || $id_tipo_actividad_academica === '')
                || ($titulo === null || $titulo === '')
                || ($descripcion === null || $descripcion === '')
                || ($minutos_duracion === null || $minutos_duracion === '')
                || ($materiales === null || $materiales === '');

            if ($faltantes) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            $sentence = $db->prepare("UPDATE actividades_academicas SET 
            id_tipo_actividad_academica = :id_tipo_actividad_academica, 
            titulo = :titulo, 
            descripcion = :descripcion, 
            nivel_uno = :nivel_uno, 
            nivel_dos = :nivel_dos, 
            minutos_duracion = :minutos_duracion, 
            materiales = :materiales,
            id_ambiente = :id_ambiente
            WHERE id = :id AND id_tenant = :id_tenant");

            $sentence->bindParam(':id_tipo_actividad_academica', $id_tipo_actividad_academica);
            $sentence->bindParam(':titulo', $titulo);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindParam(':nivel_uno', $nivel_uno);
            $sentence->bindParam(':nivel_dos', $nivel_dos);
            $sentence->bindParam(':minutos_duracion', $minutos_duracion);
            $sentence->bindParam(':materiales', $materiales);
            $sentence->bindParam(':id_ambiente', $id_ambiente);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el registro con el ID especificado para actualizar'), 404);
                return;
            }

            error_log("ID actualizado: " . $id);

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método update: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al actualizar la actividad. Inténtalo más tarde.'), 500);
        }
    }


    /**
     * Duplica una actividad completa: la fila principal, sus materiales y sus
     * indicadores de logro asociados. Los sprints NO se copian, porque un
     * sprint es un periodo concreto y la copia nace como plantilla suelta.
     *
     * Todo va en una transaccion: o queda la copia completa o no queda nada.
     * Devuelve el id de la actividad nueva.
     */
    public static function duplicar()
    {
        $db = Flight::db();

        try {
            $id = Flight::request()->data['id'] ?? null;

            if ($id === null || $id === '') {
                Flight::json(array('error' => 'Falta el id de la actividad a duplicar'), 400);
                return;
            }

            $idTenant = TenantContext::id();

            // 1. Traer la actividad original, siempre dentro del tenant actual
            $sentence = $db->prepare("SELECT titulo, descripcion, nivel_uno, nivel_dos,
                minutos_duracion, materiales, id_tipo_actividad_academica, id_ambiente
                FROM actividades_academicas
                WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $sentence->execute();
            $original = $sentence->fetch();

            if (!$original) {
                Flight::json(array('error' => 'No se encontró la actividad a duplicar'), 404);
                return;
            }

            $db->beginTransaction();

            // 2. Crear la copia. El titulo lleva sufijo para distinguirla en el listado
            $idNuevo = Uuid::generar();
            $tituloCopia = $original['titulo'] . ' (copia)';

            $insert = $db->prepare("INSERT INTO actividades_academicas(
                id, id_tenant, id_tipo_actividad_academica, titulo, descripcion,
                nivel_uno, nivel_dos, minutos_duracion, materiales, id_ambiente
            ) VALUES (
                :id, :id_tenant, :id_tipo_actividad_academica, :titulo, :descripcion,
                :nivel_uno, :nivel_dos, :minutos_duracion, :materiales, :id_ambiente
            )");

            $insert->bindValue(':id', $idNuevo);
            $insert->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $insert->bindValue(':id_tipo_actividad_academica', $original['id_tipo_actividad_academica']);
            $insert->bindValue(':titulo', $tituloCopia);
            $insert->bindValue(':descripcion', $original['descripcion']);
            $insert->bindValue(':nivel_uno', $original['nivel_uno']);
            $insert->bindValue(':nivel_dos', $original['nivel_dos']);
            $insert->bindValue(':minutos_duracion', $original['minutos_duracion']);
            $insert->bindValue(':materiales', $original['materiales']);
            $insert->bindValue(':id_ambiente', $original['id_ambiente']);
            $insert->execute();

            // 3. Copiar los materiales
            $materiales = $db->prepare("SELECT nombre_material, cantidad, id_producto
                FROM materiales_x_actividad
                WHERE id_actividad_academica = :id AND id_tenant = :id_tenant");
            $materiales->bindParam(':id', $id);
            $materiales->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $materiales->execute();
            $listaMateriales = $materiales->fetchAll();

            $insertMaterial = $db->prepare("INSERT INTO materiales_x_actividad(
                id, id_tenant, nombre_material, cantidad, id_actividad_academica, id_producto
            ) VALUES (
                :id, :id_tenant, :nombre_material, :cantidad, :id_actividad_academica, :id_producto
            )");

            foreach ($listaMateriales as $material) {
                $insertMaterial->bindValue(':id', Uuid::generar());
                $insertMaterial->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $insertMaterial->bindValue(':nombre_material', $material['nombre_material']);
                $insertMaterial->bindValue(':cantidad', $material['cantidad']);
                $insertMaterial->bindValue(':id_actividad_academica', $idNuevo);
                $insertMaterial->bindValue(':id_producto', $material['id_producto']);
                $insertMaterial->execute();
            }

            // 4. Copiar los indicadores de logro asociados
            $indicadores = $db->prepare("SELECT id_indicador_logro
                FROM actividades_academicas_x_indicadores_logros
                WHERE id_actividad_academica = :id AND id_tenant = :id_tenant");
            $indicadores->bindParam(':id', $id);
            $indicadores->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $indicadores->execute();
            $listaIndicadores = $indicadores->fetchAll();

            $insertIndicador = $db->prepare("INSERT INTO actividades_academicas_x_indicadores_logros(
                id, id_tenant, id_actividad_academica, id_indicador_logro
            ) VALUES (
                :id, :id_tenant, :id_actividad_academica, :id_indicador_logro
            )");

            foreach ($listaIndicadores as $indicador) {
                $insertIndicador->bindValue(':id', Uuid::generar());
                $insertIndicador->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $insertIndicador->bindValue(':id_actividad_academica', $idNuevo);
                $insertIndicador->bindValue(':id_indicador_logro', $indicador['id_indicador_logro']);
                $insertIndicador->execute();
            }

            $db->commit();

            error_log("Actividad duplicada: original=$id, copia=$idNuevo, materiales=" . count($listaMateriales) . ", indicadores=" . count($listaIndicadores));

            Flight::json(array(
                'id' => $idNuevo,
                'titulo' => $tituloCopia,
                'materiales_copiados' => count($listaMateriales),
                'indicadores_copiados' => count($listaIndicadores)
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en la ejecución del método duplicar: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al duplicar la actividad. Inténtalo más tarde.'), 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        error_log("Datos recibidos para eliminar: id=$id");
        $sentence = $db->prepare("delete from actividades_academicas where id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }

    public static function getIndicadoresLogrosByActividad($id_actividad_academica)
    {
        error_log("Datos recibidos getIndicadoresLogrosByActividad: id_actividad_academica=$id_actividad_academica");

        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
                gr.nombre nombre_grado, 
                il.nombre indicador_logro_nombre, 
                aa.nombre area_academica_nombre,
                ed.nombre esfera_desarrollo_nombre, 
                ca.nombre corte_academico_nombre, 
                aa.id,
                (SELECT JSON_ARRAYAGG(JSON_OBJECT('id', g.id, 'nombre', g.nombre))
                 FROM grados_x_grupo gxg
                 INNER JOIN grupos g ON gxg.id_grupo = g.id
                 WHERE gxg.id_grado = l.id_grado) AS grupos_json
                FROM logros l 
                INNER JOIN indicadores_logros il ON l.id = il.id_logro
                INNER JOIN grados gr ON l.id_grado = gr.id
                INNER JOIN areas_academicas aa ON l.id_area_academica = aa.id
                INNER JOIN esferas_desarrollo ed ON l.id_esfera_desarrollo = ed.id
                INNER JOIN cortes_academicos ca ON l.id_corte_academico = ca.id
                INNER JOIN actividades_academicas_x_indicadores_logros aaxil ON aaxil.id_indicador_logro = il.id
                INNER JOIN actividades_academicas aa2 ON aaxil.id_actividad_academica = aa2.id
                WHERE aa2.id = :id_actividad_academica AND l.id_tenant = :id_tenant");

        $sentence->bindParam(':id_actividad_academica', $id_actividad_academica);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $response = $sentence->fetchAll();

        Flight::json($response);
    }

    public static function getByCorte($id_corte)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT DISTINCT
            aa.id,
            aa.titulo,
            aa.descripcion,
            aa.nivel_uno,
            aa.nivel_dos,
            aa.minutos_duracion,
            aa.materiales,
            aa.id_tipo_actividad_academica,
            ta.nombre as nombre_tipo_actividad,
            GROUP_CONCAT(DISTINCT gr.nombre ORDER BY gr.nombre) as grados,
            GROUP_CONCAT(DISTINCT ar.nombre ORDER BY ar.nombre) as areas,
            GROUP_CONCAT(DISTINCT ed.nombre ORDER BY ed.nombre) as esferas
        FROM actividades_academicas aa
        LEFT JOIN tipos_actividades_academicas ta ON aa.id_tipo_actividad_academica = ta.id
        INNER JOIN actividades_academicas_x_indicadores_logros aaxil ON aa.id = aaxil.id_actividad_academica
        INNER JOIN indicadores_logros il ON aaxil.id_indicador_logro = il.id
        INNER JOIN logros l ON il.id_logro = l.id
        LEFT JOIN grados gr ON l.id_grado = gr.id
        LEFT JOIN areas_academicas ar ON l.id_area_academica = ar.id
        LEFT JOIN esferas_desarrollo ed ON l.id_esfera_desarrollo = ed.id
        WHERE l.id_corte_academico = :id_corte
        AND aa.id_tenant = :id_tenant
        GROUP BY aa.id
        ORDER BY aa.titulo
    ");
        $sentence->bindParam(':id_corte', $id_corte);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Filtrar actividades por grupo (via grados_x_grupo), área, esfera y texto.
     * Ahora la relación grupo->logro pasa por grados_x_grupo.
     */
    public static function getByFiltros()
    {
        $db = Flight::db();

        $id_corte = $_GET['id_corte'] ?? null;
        $id_grupo = $_GET['id_grupo'] ?? null;
        $id_area = $_GET['id_area'] ?? null;
        $id_esfera = $_GET['id_esfera'] ?? null;
        $busqueda = $_GET['busqueda'] ?? null;

        $sql = "
        SELECT DISTINCT
            aa.id,
            aa.titulo,
            aa.descripcion,
            aa.nivel_uno,
            aa.nivel_dos,
            aa.minutos_duracion,
            aa.materiales,
            aa.id_tipo_actividad_academica,
            ta.nombre as nombre_tipo_actividad,
            GROUP_CONCAT(DISTINCT gr.nombre ORDER BY gr.nombre) as grados,
            GROUP_CONCAT(DISTINCT ar.nombre ORDER BY ar.nombre) as areas,
            GROUP_CONCAT(DISTINCT ed.nombre ORDER BY ed.nombre) as esferas
        FROM actividades_academicas aa
        LEFT JOIN tipos_actividades_academicas ta ON aa.id_tipo_actividad_academica = ta.id
        INNER JOIN actividades_academicas_x_indicadores_logros aaxil ON aa.id = aaxil.id_actividad_academica
        INNER JOIN indicadores_logros il ON aaxil.id_indicador_logro = il.id
        INNER JOIN logros l ON il.id_logro = l.id
        LEFT JOIN grados gr ON l.id_grado = gr.id
        LEFT JOIN areas_academicas ar ON l.id_area_academica = ar.id
        LEFT JOIN esferas_desarrollo ed ON l.id_esfera_desarrollo = ed.id
        WHERE 1=1
          AND aa.id_tenant = :id_tenant
    ";

        $params = [];

        if ($id_corte) {
            $sql .= " AND l.id_corte_academico = :id_corte";
            $params[':id_corte'] = $id_corte;
        }

        // Filtro por grupo: pasa por grados_x_grupo
        if ($id_grupo) {
            $sql .= " AND l.id_grado IN (SELECT gxg.id_grado FROM grados_x_grupo gxg WHERE gxg.id_grupo = :id_grupo)";
            $params[':id_grupo'] = $id_grupo;
        }

        if ($id_area) {
            $sql .= " AND l.id_area_academica = :id_area";
            $params[':id_area'] = $id_area;
        }

        if ($id_esfera) {
            $sql .= " AND l.id_esfera_desarrollo = :id_esfera";
            $params[':id_esfera'] = $id_esfera;
        }

        if ($busqueda) {
            $sql .= " AND (aa.titulo LIKE :busqueda OR aa.descripcion LIKE :busqueda2)";
            $params[':busqueda'] = "%$busqueda%";
            $params[':busqueda2'] = "%$busqueda%";
        }

        $sql .= " GROUP BY aa.id ORDER BY aa.titulo";

        $sentence = $db->prepare($sql);

        foreach ($params as $key => $value) {
            $sentence->bindValue($key, $value);
        }
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}