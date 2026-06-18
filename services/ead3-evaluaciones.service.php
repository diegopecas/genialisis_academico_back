<?php

class Ead3Evaluaciones
{
    // =============================================
    // FUNCIÓN HELPER: Obtener conversión PD → PT + Clasificación
    // =============================================
    private static function obtenerConversion($db, $id_rango_edad, $area, $puntaje_directo)
    {
        // Buscar conversión exacta
        $sentence = $db->prepare("
            SELECT puntaje_tipico, clasificacion
            FROM ead3_tablas_conversion
            WHERE id_rango_edad = :id_rango
              AND area = :area
              AND puntaje_directo = :pd
            LIMIT 1
        ");
        $sentence->bindParam(':id_rango', $id_rango_edad);
        $sentence->bindParam(':area', $area);
        $sentence->bindParam(':pd', $puntaje_directo);
        $sentence->execute();
        $resultado = $sentence->fetch();

        if ($resultado) {
            return [
                'puntaje_tipico' => (int)$resultado['puntaje_tipico'],
                'clasificacion' => $resultado['clasificacion']
            ];
        }

        // Si no hay coincidencia exacta, buscar el PD más cercano (por debajo)
        $sentence2 = $db->prepare("
            SELECT puntaje_tipico, clasificacion, puntaje_directo
            FROM ead3_tablas_conversion
            WHERE id_rango_edad = :id_rango
              AND area = :area
              AND puntaje_directo <= :pd
            ORDER BY puntaje_directo DESC
            LIMIT 1
        ");
        $sentence2->bindParam(':id_rango', $id_rango_edad);
        $sentence2->bindParam(':area', $area);
        $sentence2->bindParam(':pd', $puntaje_directo);
        $sentence2->execute();
        $resultado2 = $sentence2->fetch();

        if ($resultado2) {
            return [
                'puntaje_tipico' => (int)$resultado2['puntaje_tipico'],
                'clasificacion' => $resultado2['clasificacion']
            ];
        }

        // Si el PD es menor al mínimo de la tabla, buscar el mínimo
        $sentence3 = $db->prepare("
            SELECT puntaje_tipico, clasificacion
            FROM ead3_tablas_conversion
            WHERE id_rango_edad = :id_rango
              AND area = :area
            ORDER BY puntaje_directo ASC
            LIMIT 1
        ");
        $sentence3->bindParam(':id_rango', $id_rango_edad);
        $sentence3->bindParam(':area', $area);
        $sentence3->execute();
        $resultado3 = $sentence3->fetch();

        if ($resultado3) {
            return [
                'puntaje_tipico' => (int)$resultado3['puntaje_tipico'],
                'clasificacion' => 'rojo'
            ];
        }

        // Fallback
        return [
            'puntaje_tipico' => 0,
            'clasificacion' => 'rojo'
        ];
    }

    // =============================================
    // FUNCIÓN HELPER: Recalcular resultado global
    // =============================================
    private static function recalcularResultadoGlobal($db, $id_evaluacion)
    {
        $sentGlobal = $db->prepare("
            SELECT resultado_mg, resultado_mf, resultado_al, resultado_ps 
            FROM ead3_evaluaciones 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentGlobal->bindParam(':id', $id_evaluacion);
        $sentGlobal->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentGlobal->execute();
        $eval = $sentGlobal->fetch();

        $resultados = array_filter([
            $eval['resultado_mg'], 
            $eval['resultado_mf'], 
            $eval['resultado_al'], 
            $eval['resultado_ps']
        ]);

        if (count($resultados) === 0) {
            return null;
        }

        if (in_array('rojo', $resultados)) {
            $global = 'rojo';
        } elseif (in_array('amarillo', $resultados)) {
            $global = 'amarillo';
        } else {
            $global = 'verde';
        }

        $sentUpdGlobal = $db->prepare("
            UPDATE ead3_evaluaciones 
            SET resultado_global = :global 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentUpdGlobal->bindParam(':global', $global);
        $sentUpdGlobal->bindParam(':id', $id_evaluacion);
        $sentUpdGlobal->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentUpdGlobal->execute();

        return $global;
    }

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT e.*, 
                   CONCAT(p.primer_nombre, ' ', COALESCE(p.segundo_nombre,''), ' ', p.primer_apellido, ' ', COALESCE(p.segundo_apellido,'')) AS nombre_estudiante,
                   g.nombre AS nombre_grupo,
                   r.nombre AS nombre_rango
            FROM ead3_evaluaciones e
            INNER JOIN estudiantes est ON e.id_estudiante = est.id
            INNER JOIN personas p ON est.id_persona = p.id
            LEFT JOIN estudiantes_x_grupos exg ON exg.id_estudiante = est.id AND exg.activo = 1
            LEFT JOIN grupos g ON exg.id_grupo = g.id
            INNER JOIN ead3_rangos_edad r ON e.id_rango_edad = r.id
            WHERE e.activo = 1 AND e.id_tenant = :id_tenant
            ORDER BY e.fecha_evaluacion DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT e.*, 
                   CONCAT(p.primer_nombre, ' ', COALESCE(p.segundo_nombre,''), ' ', p.primer_apellido, ' ', COALESCE(p.segundo_apellido,'')) AS nombre_estudiante,
                   p.fecha_nacimiento,
                   r.nombre AS nombre_rango
            FROM ead3_evaluaciones e
            INNER JOIN estudiantes est ON e.id_estudiante = est.id
            INNER JOIN personas p ON est.id_persona = p.id
            INNER JOIN ead3_rangos_edad r ON e.id_rango_edad = r.id
            WHERE e.id = :id AND e.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByEstudiante($id_estudiante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT e.*, 
                   r.nombre AS nombre_rango,
                   CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_estudiante,
                   CONCAT(IFNULL(pu.primer_nombre, ''), ' ', IFNULL(pu.segundo_nombre, ''), ' ', IFNULL(pu.primer_apellido, ''), ' ', IFNULL(pu.segundo_apellido, '')) AS nombre_evaluador,
                   CONCAT(IFNULL(pa.primer_nombre, ''), ' ', IFNULL(pa.segundo_nombre, ''), ' ', IFNULL(pa.primer_apellido, ''), ' ', IFNULL(pa.segundo_apellido, '')) AS nombre_analista
            FROM ead3_evaluaciones e
            INNER JOIN ead3_rangos_edad r ON e.id_rango_edad = r.id
            INNER JOIN estudiantes est ON e.id_estudiante = est.id
            INNER JOIN personas p ON est.id_persona = p.id
            LEFT JOIN usuarios u ON e.id_usuario = u.id
            LEFT JOIN personas pu ON u.id_persona = pu.id
            LEFT JOIN usuarios ua ON e.id_usuario_analisis = ua.id
            LEFT JOIN personas pa ON ua.id_persona = pa.id
            WHERE e.id_estudiante = :id_estudiante AND e.activo = 1 AND e.id_tenant = :id_tenant
            ORDER BY e.fecha_evaluacion DESC
        ");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function calcularEdad($id_estudiante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT p.fecha_nacimiento,
                   TIMESTAMPDIFF(MONTH, p.fecha_nacimiento, CURDATE()) AS edad_meses,
                   DATEDIFF(CURDATE(), p.fecha_nacimiento) AS edad_dias,
                   CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_estudiante
            FROM estudiantes est
            INNER JOIN personas p ON est.id_persona = p.id
            WHERE est.id = :id_estudiante AND est.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $data = $sentence->fetch();

        if (!$data) {
            Flight::json(array('error' => 'Estudiante no encontrado'), 404);
            return;
        }

        $edad_meses = $data['edad_meses'];

        $sentenceRango = $db->prepare("
            SELECT id, nombre, edad_meses_inicio, edad_meses_fin 
            FROM ead3_rangos_edad 
            WHERE :edad_meses >= edad_meses_inicio AND :edad_meses2 < edad_meses_fin
        ");
        $sentenceRango->bindParam(':edad_meses', $edad_meses);
        $sentenceRango->bindParam(':edad_meses2', $edad_meses);
        $sentenceRango->execute();
        $rango = $sentenceRango->fetch();

        if (!$rango) {
            Flight::json(array('error' => 'Edad fuera de rango EAD-3 (0-84 meses)'), 400);
            return;
        }

        Flight::json(array(
            'fecha_nacimiento' => $data['fecha_nacimiento'],
            'edad_meses' => $edad_meses,
            'edad_dias' => $data['edad_dias'],
            'nombre_estudiante' => trim(preg_replace('/\s+/', ' ', $data['nombre_estudiante'])),
            'rango' => $rango
        ));
    }

    public static function new()
    {
        $db = Flight::db();

        $id_estudiante = Flight::request()->data['id_estudiante'];
        $fecha_evaluacion = Flight::request()->data['fecha_evaluacion'];
        $edad_meses = Flight::request()->data['edad_meses'];
        $edad_dias = Flight::request()->data['edad_dias'];
        $id_rango_edad = Flight::request()->data['id_rango_edad'];
        $puntaje_directo_mg = Flight::request()->data['puntaje_directo_mg'];
        $puntaje_directo_mf = Flight::request()->data['puntaje_directo_mf'];
        $puntaje_directo_al = Flight::request()->data['puntaje_directo_al'];
        $puntaje_directo_ps = Flight::request()->data['puntaje_directo_ps'];
        $puntaje_tipico_mg = Flight::request()->data['puntaje_tipico_mg'] ?? null;
        $puntaje_tipico_mf = Flight::request()->data['puntaje_tipico_mf'] ?? null;
        $puntaje_tipico_al = Flight::request()->data['puntaje_tipico_al'] ?? null;
        $puntaje_tipico_ps = Flight::request()->data['puntaje_tipico_ps'] ?? null;
        $resultado_mg = Flight::request()->data['resultado_mg'];
        $resultado_mf = Flight::request()->data['resultado_mf'];
        $resultado_al = Flight::request()->data['resultado_al'];
        $resultado_ps = Flight::request()->data['resultado_ps'];
        $resultado_global = Flight::request()->data['resultado_global'];
        $observaciones = Flight::request()->data['observaciones'] ?? null;
        $id_usuario = Flight::request()->data['id_usuario'];
        $items = Flight::request()->data['items'] ?? [];

        $idNew = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO ead3_evaluaciones (
                id, id_tenant,
                id_estudiante, fecha_evaluacion, edad_meses, edad_dias, id_rango_edad,
                puntaje_directo_mg, puntaje_directo_mf, puntaje_directo_al, puntaje_directo_ps,
                puntaje_tipico_mg, puntaje_tipico_mf, puntaje_tipico_al, puntaje_tipico_ps,
                resultado_mg, resultado_mf, resultado_al, resultado_ps, resultado_global,
                observaciones, id_usuario, estado
            ) VALUES (
                :id, :id_tenant,
                :id_estudiante, :fecha_evaluacion, :edad_meses, :edad_dias, :id_rango_edad,
                :puntaje_directo_mg, :puntaje_directo_mf, :puntaje_directo_al, :puntaje_directo_ps,
                :puntaje_tipico_mg, :puntaje_tipico_mf, :puntaje_tipico_al, :puntaje_tipico_ps,
                :resultado_mg, :resultado_mf, :resultado_al, :resultado_ps, :resultado_global,
                :observaciones, :id_usuario, 'finalizado'
            )
        ");

        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha_evaluacion', $fecha_evaluacion);
        $sentence->bindParam(':edad_meses', $edad_meses);
        $sentence->bindParam(':edad_dias', $edad_dias);
        $sentence->bindParam(':id_rango_edad', $id_rango_edad);
        $sentence->bindParam(':puntaje_directo_mg', $puntaje_directo_mg);
        $sentence->bindParam(':puntaje_directo_mf', $puntaje_directo_mf);
        $sentence->bindParam(':puntaje_directo_al', $puntaje_directo_al);
        $sentence->bindParam(':puntaje_directo_ps', $puntaje_directo_ps);
        $sentence->bindParam(':puntaje_tipico_mg', $puntaje_tipico_mg);
        $sentence->bindParam(':puntaje_tipico_mf', $puntaje_tipico_mf);
        $sentence->bindParam(':puntaje_tipico_al', $puntaje_tipico_al);
        $sentence->bindParam(':puntaje_tipico_ps', $puntaje_tipico_ps);
        $sentence->bindParam(':resultado_mg', $resultado_mg);
        $sentence->bindParam(':resultado_mf', $resultado_mf);
        $sentence->bindParam(':resultado_al', $resultado_al);
        $sentence->bindParam(':resultado_ps', $resultado_ps);
        $sentence->bindParam(':resultado_global', $resultado_global);
        $sentence->bindParam(':observaciones', $observaciones);
        $sentence->bindParam(':id_usuario', $id_usuario);
        $sentence->execute();

        $id_evaluacion = $idNew;

        if (is_array($items) && count($items) > 0) {
            $sentenceDetalle = $db->prepare("
                INSERT INTO ead3_evaluaciones_detalle (
                    id_tenant, id_evaluacion, id_item, cumple, es_punto_inicio, es_punto_cierre
                ) VALUES (
                    :id_tenant, :id_evaluacion, :id_item, :cumple, :es_punto_inicio, :es_punto_cierre
                )
            ");
            $sentenceDetalle->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            foreach ($items as $item) {
                $id_item = $item['id_item'];
                $cumple = $item['cumple'];
                $es_punto_inicio = $item['es_punto_inicio'] ?? 0;
                $es_punto_cierre = $item['es_punto_cierre'] ?? 0;

                $sentenceDetalle->bindParam(':id_evaluacion', $id_evaluacion);
                $sentenceDetalle->bindParam(':id_item', $id_item);
                $sentenceDetalle->bindParam(':cumple', $cumple);
                $sentenceDetalle->bindParam(':es_punto_inicio', $es_punto_inicio);
                $sentenceDetalle->bindParam(':es_punto_cierre', $es_punto_cierre);
                $sentenceDetalle->execute();
            }
        }

        Flight::json(array('id' => $id_evaluacion));
    }

    // Iniciar evaluación parcial (Paso 0 → crea registro con estado 'iniciado')
    public static function iniciar()
    {
        $db = Flight::db();

        $id_estudiante = Flight::request()->data['id_estudiante'];
        $fecha_evaluacion = Flight::request()->data['fecha_evaluacion'];
        $edad_meses = Flight::request()->data['edad_meses'];
        $edad_dias = Flight::request()->data['edad_dias'];
        $id_rango_edad = Flight::request()->data['id_rango_edad'];
        $id_usuario = Flight::request()->data['id_usuario'];

        $idNew = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO ead3_evaluaciones (
                id, id_tenant,
                id_estudiante, fecha_evaluacion, edad_meses, edad_dias, id_rango_edad,
                id_usuario, estado
            ) VALUES (
                :id, :id_tenant,
                :id_estudiante, :fecha_evaluacion, :edad_meses, :edad_dias, :id_rango_edad,
                :id_usuario, 'iniciado'
            )
        ");

        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha_evaluacion', $fecha_evaluacion);
        $sentence->bindParam(':edad_meses', $edad_meses);
        $sentence->bindParam(':edad_dias', $edad_dias);
        $sentence->bindParam(':id_rango_edad', $id_rango_edad);
        $sentence->bindParam(':id_usuario', $id_usuario);
        $sentence->execute();

        $id_evaluacion = $idNew;
        Flight::json(array('id' => $id_evaluacion));
    }

    // =============================================
    // GUARDAR ÁREA - USA TABLA DE CONVERSIÓN
    // =============================================
    public static function guardarArea()
    {
        $db = Flight::db();

        $id_evaluacion = Flight::request()->data['id_evaluacion'];
        $area = strtoupper(Flight::request()->data['area']);
        $items = Flight::request()->data['items'] ?? [];

        // 1. Obtener el id_rango_edad de la evaluación
        $sentRango = $db->prepare("SELECT id_rango_edad FROM ead3_evaluaciones WHERE id = :id AND id_tenant = :id_tenant");
        $sentRango->bindParam(':id', $id_evaluacion);
        $sentRango->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentRango->execute();
        $evalData = $sentRango->fetch();
        
        if (!$evalData) {
            Flight::json(array('error' => 'Evaluación no encontrada'), 404);
            return;
        }
        
        $id_rango_edad = (int)$evalData['id_rango_edad'];

        // 2. Eliminar ítems previos de esta área
        $sentDel = $db->prepare("
            DELETE d FROM ead3_evaluaciones_detalle d
            INNER JOIN ead3_items i ON d.id_item = i.id
            WHERE d.id_evaluacion = :id_eval AND i.area = :area AND d.id_tenant = :id_tenant
        ");
        $sentDel->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentDel->bindParam(':id_eval', $id_evaluacion);
        $sentDel->bindParam(':area', $area);
        $sentDel->execute();

        // 3. Insertar ítems nuevos
        if (is_array($items) && count($items) > 0) {
            $sentIns = $db->prepare("
                INSERT INTO ead3_evaluaciones_detalle (id_tenant, id_evaluacion, id_item, cumple, es_punto_inicio, es_punto_cierre)
                VALUES (:id_tenant, :id_eval, :id_item, :cumple, 0, 0)
            ");
            $sentIns->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            foreach ($items as $item) {
                $id_item = $item['id_item'];
                $cumple = $item['cumple'];
                $sentIns->bindParam(':id_eval', $id_evaluacion);
                $sentIns->bindParam(':id_item', $id_item);
                $sentIns->bindParam(':cumple', $cumple);
                $sentIns->execute();
            }
        }

        // 4. Calcular puntaje directo
        $sentPD = $db->prepare("
            SELECT 
                COUNT(CASE WHEN d.cumple = 1 THEN 1 END) AS puntaje_directo,
                COUNT(*) AS total_items
            FROM ead3_evaluaciones_detalle d
            INNER JOIN ead3_items i ON d.id_item = i.id
            WHERE d.id_evaluacion = :id_eval AND i.area = :area AND d.id_tenant = :id_tenant
        ");
        $sentPD->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentPD->bindParam(':id_eval', $id_evaluacion);
        $sentPD->bindParam(':area', $area);
        $sentPD->execute();
        $dataPD = $sentPD->fetch();

        $pd = (int)$dataPD['puntaje_directo'];
        $total = (int)$dataPD['total_items'];

        // 5. OBTENER PT Y CLASIFICACIÓN DE LA TABLA DE CONVERSIÓN
        $conversion = self::obtenerConversion($db, $id_rango_edad, $area, $pd);
        $pt = $conversion['puntaje_tipico'];
        $clasificacion = $conversion['clasificacion'];

        // 6. Actualizar la evaluación
        $areaKey = strtolower($area);
        $sentUpd = $db->prepare("
            UPDATE ead3_evaluaciones 
            SET puntaje_directo_{$areaKey} = :pd, 
                puntaje_tipico_{$areaKey} = :pt,
                resultado_{$areaKey} = :resultado, 
                estado = 'en_proceso' 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentUpd->bindParam(':pd', $pd);
        $sentUpd->bindParam(':pt', $pt);
        $sentUpd->bindParam(':resultado', $clasificacion);
        $sentUpd->bindParam(':id', $id_evaluacion);
        $sentUpd->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentUpd->execute();

        // 7. Recalcular resultado global
        $resultadoGlobal = self::recalcularResultadoGlobal($db, $id_evaluacion);

        Flight::json(array(
            'id_evaluacion' => $id_evaluacion,
            'area' => $area,
            'puntaje_directo' => $pd,
            'puntaje_tipico' => $pt,
            'resultado' => $clasificacion,
            'resultado_global' => $resultadoGlobal,
            'total_items' => $total,
            'message' => "Área $area guardada correctamente"
        ));
    }

    // Finalizar evaluación
    public static function finalizar()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $observaciones = Flight::request()->data['observaciones'] ?? null;
        $analisis = Flight::request()->data['analisis'] ?? null;
        $recomendaciones = Flight::request()->data['recomendaciones'] ?? null;
        $id_usuario_analisis = Flight::request()->data['id_usuario_analisis'] ?? null;

        $sentence = $db->prepare("
            UPDATE ead3_evaluaciones 
            SET estado = 'finalizado',
                observaciones = :observaciones,
                analisis = :analisis,
                recomendaciones = :recomendaciones,
                id_usuario_analisis = :id_usuario_analisis,
                fecha_analisis = CASE WHEN :has_analisis IS NOT NULL THEN NOW() ELSE fecha_analisis END
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':observaciones', $observaciones);
        $sentence->bindParam(':analisis', $analisis);
        $sentence->bindParam(':recomendaciones', $recomendaciones);
        $sentence->bindParam(':id_usuario_analisis', $id_usuario_analisis);
        $sentence->bindParam(':has_analisis', $analisis);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id, 'message' => 'Evaluación finalizada'));
    }

    public static function getDetalleEvaluacion($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT d.*, i.area, i.area_nombre, i.numero_item, i.descripcion AS descripcion_item, 
                   i.id_rango_edad, r.nombre AS nombre_rango
            FROM ead3_evaluaciones_detalle d
            INNER JOIN ead3_items i ON d.id_item = i.id
            INNER JOIN ead3_rangos_edad r ON i.id_rango_edad = r.id
            WHERE d.id_evaluacion = :id AND d.id_tenant = :id_tenant
            ORDER BY i.area, i.orden
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Obtener evaluación completa para retomar
    public static function getEvaluacionParaRetomar($id)
    {
        $db = Flight::db();

        $sentEval = $db->prepare("
            SELECT e.*, 
                   r.nombre AS nombre_rango,
                   r.edad_meses_inicio, r.edad_meses_fin,
                   CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_estudiante,
                   p.fecha_nacimiento
            FROM ead3_evaluaciones e
            INNER JOIN ead3_rangos_edad r ON e.id_rango_edad = r.id
            INNER JOIN estudiantes est ON e.id_estudiante = est.id
            INNER JOIN personas p ON est.id_persona = p.id
            WHERE e.id = :id AND e.activo = 1 AND e.id_tenant = :id_tenant
        ");
        $sentEval->bindParam(':id', $id);
        $sentEval->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentEval->execute();
        $evaluacion = $sentEval->fetch();

        if (!$evaluacion) {
            Flight::json(array('error' => 'Evaluación no encontrada'), 404);
            return;
        }

        $sentItems = $db->prepare("
            SELECT d.id AS id_detalle, d.id_item, d.cumple,
                   i.area, i.numero_item, i.descripcion, i.instrucciones,
                   i.id_rango_edad, i.orden,
                   r.nombre AS nombre_rango
            FROM ead3_evaluaciones_detalle d
            INNER JOIN ead3_items i ON d.id_item = i.id
            INNER JOIN ead3_rangos_edad r ON i.id_rango_edad = r.id
            WHERE d.id_evaluacion = :id AND d.id_tenant = :id_tenant
            ORDER BY i.area, i.orden
        ");
        $sentItems->bindParam(':id', $id);
        $sentItems->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentItems->execute();
        $items_guardados = $sentItems->fetchAll();

        $areas_con_datos = [];
        foreach ($items_guardados as $item) {
            $area = $item['area'];
            if (!isset($areas_con_datos[$area])) {
                $areas_con_datos[$area] = 0;
            }
            $areas_con_datos[$area]++;
        }

        $evaluacion['nombre_estudiante'] = trim(preg_replace('/\s+/', ' ', $evaluacion['nombre_estudiante']));

        Flight::json(array(
            'evaluacion' => $evaluacion,
            'items_guardados' => $items_guardados,
            'areas_con_datos' => $areas_con_datos,
            'rango' => array(
                'id' => $evaluacion['id_rango_edad'],
                'nombre' => $evaluacion['nombre_rango'],
                'edad_meses_inicio' => $evaluacion['edad_meses_inicio'],
                'edad_meses_fin' => $evaluacion['edad_meses_fin']
            )
        ));
    }

    public static function anular()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("UPDATE ead3_evaluaciones SET activo = 0 WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function getListadoEstudiantes()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                est.id,
                est.activo,
                CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
                IFNULL(grp.nombre, 'Sin grupo') AS nombre_grupo,
                p.fecha_nacimiento,
                TIMESTAMPDIFF(MONTH, p.fecha_nacimiento, CURDATE()) AS edad_meses,
                TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad_anios,
                CASE WHEN est.activo = 1 THEN 'Activo' ELSE 'Inactivo' END AS estado_estudiante,
                IFNULL((SELECT 
                    CASE ev.resultado_global
                        WHEN 'verde' THEN 'Desarrollo esperado'
                        WHEN 'amarillo' THEN 'Riesgo de problemas'
                        WHEN 'rojo' THEN 'Sospecha de problemas'
                        ELSE 'Sin clasificar'
                    END
                    FROM ead3_evaluaciones ev 
                    WHERE ev.id_estudiante = est.id AND ev.activo = 1
                    ORDER BY ev.fecha_evaluacion DESC LIMIT 1), '') AS ead3_clasificacion,
                IFNULL((SELECT IFNULL(ev2.resultado_global, '') 
                    FROM ead3_evaluaciones ev2 
                    WHERE ev2.id_estudiante = est.id AND ev2.activo = 1
                    ORDER BY ev2.fecha_evaluacion DESC LIMIT 1), '') AS ead3_color,
                (SELECT ev3.id 
                    FROM ead3_evaluaciones ev3 
                    WHERE ev3.id_estudiante = est.id AND ev3.activo = 1
                    ORDER BY ev3.fecha_evaluacion DESC LIMIT 1) AS ead3_ultima_id,
                IFNULL((SELECT DATE_FORMAT(ev2.fecha_evaluacion, '%Y-%m-%d') 
                    FROM ead3_evaluaciones ev2 
                    WHERE ev2.id_estudiante = est.id AND ev2.activo = 1
                    ORDER BY ev2.fecha_evaluacion DESC LIMIT 1), '') AS ead3_fecha,
                IFNULL((SELECT ev5.estado 
                    FROM ead3_evaluaciones ev5 
                    WHERE ev5.id_estudiante = est.id AND ev5.activo = 1
                    ORDER BY ev5.fecha_evaluacion DESC LIMIT 1), '') AS ead3_estado
            FROM estudiantes est
            INNER JOIN personas p ON est.id_persona = p.id
            LEFT JOIN estudiantes_x_grupos eg ON est.id = eg.id_estudiante AND eg.activo = 1
            LEFT JOIN grupos grp ON eg.id_grupo = grp.id
            WHERE est.id_tenant = :id_tenant
            ORDER BY grp.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        if (is_array($response)) {
            foreach ($response as &$row) {
                $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
            }
        }

        Flight::json($response);
    }

    // Actualizar observaciones
    public static function actualizarObservaciones()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $observaciones = Flight::request()->data['observaciones'];

        $sentence = $db->prepare("UPDATE ead3_evaluaciones SET observaciones = :observaciones WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':observaciones', $observaciones);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id, 'message' => 'Observaciones actualizadas'));
    }

    // Actualizar análisis profesional
    public static function actualizarAnalisis()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $analisis = Flight::request()->data['analisis'] ?? null;
        $recomendaciones = Flight::request()->data['recomendaciones'] ?? null;
        $id_usuario_analisis = Flight::request()->data['id_usuario_analisis'];

        $sentence = $db->prepare("
            UPDATE ead3_evaluaciones 
            SET analisis = :analisis, 
                recomendaciones = :recomendaciones,
                id_usuario_analisis = :id_usuario_analisis,
                fecha_analisis = NOW()
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':analisis', $analisis);
        $sentence->bindParam(':recomendaciones', $recomendaciones);
        $sentence->bindParam(':id_usuario_analisis', $id_usuario_analisis);
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        Flight::json(array('id' => $id, 'message' => 'Análisis actualizado'));
    }

    // =============================================
    // ACTUALIZAR ÍTEM - USA TABLA DE CONVERSIÓN
    // =============================================
    public static function actualizarItem()
    {
        $db = Flight::db();
        
        $id_evaluacion = Flight::request()->data['id_evaluacion'];
        $id_detalle = Flight::request()->data['id_detalle'];
        $cumple = Flight::request()->data['cumple'];

        // 1. Actualizar el ítem
        $sentence = $db->prepare("UPDATE ead3_evaluaciones_detalle SET cumple = :cumple WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':cumple', $cumple);
        $sentence->bindParam(':id', $id_detalle);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        // 2. Obtener el id_rango_edad
        $sentRango = $db->prepare("SELECT id_rango_edad FROM ead3_evaluaciones WHERE id = :id AND id_tenant = :id_tenant");
        $sentRango->bindParam(':id', $id_evaluacion);
        $sentRango->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentRango->execute();
        $evalData = $sentRango->fetch();
        $id_rango_edad = (int)$evalData['id_rango_edad'];

        // 3. Recalcular puntajes para TODAS las áreas
        $areas = ['MG' => 'mg', 'MF' => 'mf', 'AL' => 'al', 'PS' => 'ps'];
        $puntajes = [];

        foreach ($areas as $areaCode => $areaKey) {
            $sentPD = $db->prepare("
                SELECT 
                    COUNT(CASE WHEN d.cumple = 1 THEN 1 END) AS puntaje_directo,
                    COUNT(*) AS total_items
                FROM ead3_evaluaciones_detalle d
                INNER JOIN ead3_items i ON d.id_item = i.id
                WHERE d.id_evaluacion = :id_eval AND i.area = :area AND d.id_tenant = :id_tenant
            ");
            $sentPD->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentPD->bindParam(':id_eval', $id_evaluacion);
            $sentPD->bindParam(':area', $areaCode);
            $sentPD->execute();
            $dataPD = $sentPD->fetch();

            $pd = (int)$dataPD['puntaje_directo'];
            $total = (int)$dataPD['total_items'];

            if ($total > 0) {
                // OBTENER PT Y CLASIFICACIÓN DE LA TABLA
                $conversion = self::obtenerConversion($db, $id_rango_edad, $areaCode, $pd);
                $pt = $conversion['puntaje_tipico'];
                $clasificacion = $conversion['clasificacion'];

                $puntajes[$areaKey] = [
                    'pd' => $pd,
                    'pt' => $pt,
                    'clasificacion' => $clasificacion
                ];

                $sentUpdate = $db->prepare("
                    UPDATE ead3_evaluaciones 
                    SET puntaje_directo_{$areaKey} = :pd, 
                        puntaje_tipico_{$areaKey} = :pt,
                        resultado_{$areaKey} = :resultado 
                    WHERE id = :id AND id_tenant = :id_tenant
                ");
                $sentUpdate->bindParam(':pd', $pd);
                $sentUpdate->bindParam(':pt', $pt);
                $sentUpdate->bindParam(':resultado', $clasificacion);
                $sentUpdate->bindParam(':id', $id_evaluacion);
                $sentUpdate->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentUpdate->execute();
            }
        }

        // 4. Recalcular resultado global
        $resultadoGlobal = self::recalcularResultadoGlobal($db, $id_evaluacion);

        Flight::json(array(
            'id_evaluacion' => $id_evaluacion,
            'puntajes' => $puntajes,
            'resultado_global' => $resultadoGlobal,
            'message' => 'Ítem actualizado y puntajes recalculados'
        ));
    }

    // =============================================
    // NUEVA: Obtener tabla de conversión
    // =============================================
    public static function getTablaConversion($id_rango_edad)
    {
        $db = Flight::db();
        
        $sentence = $db->prepare("
            SELECT area, puntaje_directo, puntaje_tipico, clasificacion
            FROM ead3_tablas_conversion
            WHERE id_rango_edad = :id_rango
            ORDER BY area, puntaje_directo
        ");
        $sentence->bindParam(':id_rango', $id_rango_edad);
        $sentence->execute();
        $response = $sentence->fetchAll();
        
        Flight::json($response);
    }
}