<?php

class PdeAplicaciones
{
    // =============================================
    // HELPER: semaforo de un bloque segun el porcentaje logrado
    // =============================================
    private static function clasificar($porcentaje, $config)
    {
        if ($porcentaje >= $config['umbral_verde']) {
            return 'verde';
        }
        if ($porcentaje >= $config['umbral_amarillo']) {
            return 'amarillo';
        }
        return 'rojo';
    }

    // =============================================
    // HELPER: recalcula una esfera completa con credito continuo.
    // Cada punto obtenido aporta meses en proporcion a la amplitud de su rango.
    // Los rangos por debajo del rango de inicio se asumen logrados y entran como meses_base.
    // =============================================
    private static function recalcularEsfera($db, $id_aplicacion, $id_esfera, $config)
    {
        $sentCab = $db->prepare("
            SELECT a.edad_meses, r.edad_meses_inicio AS meses_base, r.orden AS orden_inicio
            FROM pde_aplicaciones a
            INNER JOIN pde_rangos_edad r ON a.id_rango_inicio = r.id
            WHERE a.id = :id AND a.id_tenant = :id_tenant
        ");
        $sentCab->bindParam(':id', $id_aplicacion);
        $sentCab->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentCab->execute();
        $cab = $sentCab->fetch();

        if (!$cab) {
            throw new Exception('Aplicacion no encontrada');
        }

        $edad_meses = (int)$cab['edad_meses'];
        $meses_base = (float)$cab['meses_base'];

        // Consolidado por rango de los items realmente aplicados en esta esfera.
        $sentRangos = $db->prepare("
            SELECT r.id AS id_rango, r.orden, r.edad_meses_inicio, r.edad_meses_fin,
                   SUM(d.puntaje) AS puntos,
                   SUM(i.puntaje_maximo) AS posibles
            FROM pde_aplicaciones_detalle d
            INNER JOIN pde_items i ON d.id_item = i.id
            INNER JOIN pde_rangos_edad r ON i.id_rango_edad = r.id
            WHERE d.id_aplicacion = :id_aplicacion
              AND i.id_esfera = :id_esfera
              AND d.asumido = 0
              AND d.id_tenant = :id_tenant
            GROUP BY r.id, r.orden, r.edad_meses_inicio, r.edad_meses_fin
            ORDER BY r.orden
        ");
        $sentRangos->bindParam(':id_aplicacion', $id_aplicacion);
        $sentRangos->bindParam(':id_esfera', $id_esfera);
        $sentRangos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentRangos->execute();
        $rangos = $sentRangos->fetchAll();

        $edad_desarrollo = $meses_base;
        $total_puntos = 0;
        $total_posibles = 0;
        $id_rango_techo = null;
        $semaforo = null;

        foreach ($rangos as $rango) {
            $puntos = (int)$rango['puntos'];
            $posibles = (int)$rango['posibles'];

            if ($posibles <= 0) {
                continue;
            }

            $amplitud = (float)$rango['edad_meses_fin'] - (float)$rango['edad_meses_inicio'];
            $proporcion = $puntos / $posibles;

            $edad_desarrollo += $proporcion * $amplitud;
            $total_puntos += $puntos;
            $total_posibles += $posibles;

            $id_rango_techo = $rango['id_rango'];
            $semaforo = self::clasificar($proporcion * 100, $config);
        }

        $indice = $edad_meses > 0 ? ($edad_desarrollo / $edad_meses) * 100 : 0;

        $sentExiste = $db->prepare("
            SELECT id FROM pde_aplicaciones_esferas
            WHERE id_aplicacion = :id_aplicacion AND id_esfera = :id_esfera AND id_tenant = :id_tenant
        ");
        $sentExiste->bindParam(':id_aplicacion', $id_aplicacion);
        $sentExiste->bindParam(':id_esfera', $id_esfera);
        $sentExiste->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentExiste->execute();
        $existe = $sentExiste->fetch();

        if ($existe) {
            $sentUpd = $db->prepare("
                UPDATE pde_aplicaciones_esferas
                SET id_rango_techo = :id_rango_techo,
                    meses_base = :meses_base,
                    puntos_obtenidos = :puntos_obtenidos,
                    puntos_posibles = :puntos_posibles,
                    edad_desarrollo_meses = :edad_desarrollo,
                    indice = :indice,
                    semaforo = :semaforo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentUpd->bindParam(':id_rango_techo', $id_rango_techo);
            $sentUpd->bindValue(':meses_base', $meses_base);
            $sentUpd->bindValue(':puntos_obtenidos', $total_puntos, PDO::PARAM_INT);
            $sentUpd->bindValue(':puntos_posibles', $total_posibles, PDO::PARAM_INT);
            $sentUpd->bindValue(':edad_desarrollo', round($edad_desarrollo, 2));
            $sentUpd->bindValue(':indice', round($indice, 2));
            $sentUpd->bindParam(':semaforo', $semaforo);
            $sentUpd->bindValue(':id', $existe['id']);
            $sentUpd->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentUpd->execute();
        } else {
            $sentIns = $db->prepare("
                INSERT INTO pde_aplicaciones_esferas (
                    id, id_tenant, id_aplicacion, id_esfera, id_rango_techo, meses_base,
                    puntos_obtenidos, puntos_posibles, edad_desarrollo_meses, indice, semaforo
                ) VALUES (
                    :id, :id_tenant, :id_aplicacion, :id_esfera, :id_rango_techo, :meses_base,
                    :puntos_obtenidos, :puntos_posibles, :edad_desarrollo, :indice, :semaforo
                )
            ");
            $sentIns->bindValue(':id', Uuid::generar());
            $sentIns->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentIns->bindParam(':id_aplicacion', $id_aplicacion);
            $sentIns->bindParam(':id_esfera', $id_esfera);
            $sentIns->bindParam(':id_rango_techo', $id_rango_techo);
            $sentIns->bindValue(':meses_base', $meses_base);
            $sentIns->bindValue(':puntos_obtenidos', $total_puntos, PDO::PARAM_INT);
            $sentIns->bindValue(':puntos_posibles', $total_posibles, PDO::PARAM_INT);
            $sentIns->bindValue(':edad_desarrollo', round($edad_desarrollo, 2));
            $sentIns->bindValue(':indice', round($indice, 2));
            $sentIns->bindParam(':semaforo', $semaforo);
            $sentIns->execute();
        }

        return array(
            'id_esfera' => $id_esfera,
            'edad_desarrollo_meses' => round($edad_desarrollo, 2),
            'indice' => round($indice, 2),
            'semaforo' => $semaforo,
            'puntos_obtenidos' => $total_puntos,
            'puntos_posibles' => $total_posibles
        );
    }

    // =============================================
    // HELPER: consolida el promedio de esferas y marca la esfera mas rezagada
    // =============================================
    private static function recalcularGlobal($db, $id_aplicacion, $config)
    {
        $sentCab = $db->prepare("
            SELECT edad_meses FROM pde_aplicaciones
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentCab->bindParam(':id', $id_aplicacion);
        $sentCab->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentCab->execute();
        $cab = $sentCab->fetch();
        $edad_meses = (int)$cab['edad_meses'];

        $sentEsferas = $db->prepare("
            SELECT id_esfera, edad_desarrollo_meses, indice
            FROM pde_aplicaciones_esferas
            WHERE id_aplicacion = :id_aplicacion AND id_tenant = :id_tenant AND puntos_posibles > 0
        ");
        $sentEsferas->bindParam(':id_aplicacion', $id_aplicacion);
        $sentEsferas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentEsferas->execute();
        $esferas = $sentEsferas->fetchAll();

        if (count($esferas) === 0) {
            $sentLimpiar = $db->prepare("
                UPDATE pde_aplicaciones
                SET edad_desarrollo_promedio = NULL, indice_global = NULL, id_esfera_mas_baja = NULL
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentLimpiar->bindParam(':id', $id_aplicacion);
            $sentLimpiar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentLimpiar->execute();

            return array('edad_desarrollo_promedio' => null, 'indice_global' => null, 'id_esfera_mas_baja' => null);
        }

        $suma = 0;
        $id_esfera_mas_baja = null;
        $indice_mas_bajo = null;

        foreach ($esferas as $esfera) {
            $suma += (float)$esfera['edad_desarrollo_meses'];
            if ($indice_mas_bajo === null || (float)$esfera['indice'] < $indice_mas_bajo) {
                $indice_mas_bajo = (float)$esfera['indice'];
                $id_esfera_mas_baja = $esfera['id_esfera'];
            }
        }

        $promedio = $suma / count($esferas);
        $indice_global = $edad_meses > 0 ? ($promedio / $edad_meses) * 100 : 0;

        // Solo se resalta si la esfera se separa del promedio mas que el margen configurado.
        if ($indice_mas_bajo === null || $indice_mas_bajo >= ($indice_global - $config['margen_esfera_baja'])) {
            $id_esfera_mas_baja = null;
        }

        $sentUpd = $db->prepare("
            UPDATE pde_aplicaciones
            SET edad_desarrollo_promedio = :promedio,
                indice_global = :indice_global,
                id_esfera_mas_baja = :id_esfera_mas_baja
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentUpd->bindValue(':promedio', round($promedio, 2));
        $sentUpd->bindValue(':indice_global', round($indice_global, 2));
        $sentUpd->bindParam(':id_esfera_mas_baja', $id_esfera_mas_baja);
        $sentUpd->bindParam(':id', $id_aplicacion);
        $sentUpd->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentUpd->execute();

        return array(
            'edad_desarrollo_promedio' => round($promedio, 2),
            'indice_global' => round($indice_global, 2),
            'id_esfera_mas_baja' => $id_esfera_mas_baja
        );
    }

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT a.*,
                   CONCAT(IFNULL(p.primer_nombre,''),' ',IFNULL(p.segundo_nombre,''),' ',IFNULL(p.primer_apellido,''),' ',IFNULL(p.segundo_apellido,'')) AS nombre_estudiante,
                   g.nombre AS nombre_grupo,
                   r.nombre AS nombre_rango_inicio
            FROM pde_aplicaciones a
            INNER JOIN estudiantes est ON a.id_estudiante = est.id
            INNER JOIN personas p ON est.id_persona = p.id
            INNER JOIN pde_rangos_edad r ON a.id_rango_inicio = r.id
            LEFT JOIN estudiantes_x_grupos exg ON exg.id_estudiante = est.id AND exg.activo = 1
            LEFT JOIN grupos g ON exg.id_grupo = g.id
            WHERE a.activo = 1 AND a.id_tenant = :id_tenant
            ORDER BY a.fecha_aplicacion DESC
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
            SELECT a.*,
                   CONCAT(IFNULL(p.primer_nombre,''),' ',IFNULL(p.segundo_nombre,''),' ',IFNULL(p.primer_apellido,''),' ',IFNULL(p.segundo_apellido,'')) AS nombre_estudiante,
                   p.fecha_nacimiento,
                   r.nombre AS nombre_rango_inicio
            FROM pde_aplicaciones a
            INNER JOIN estudiantes est ON a.id_estudiante = est.id
            INNER JOIN personas p ON est.id_persona = p.id
            INNER JOIN pde_rangos_edad r ON a.id_rango_inicio = r.id
            WHERE a.id = :id AND a.id_tenant = :id_tenant
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
            SELECT a.*,
                   r.nombre AS nombre_rango_inicio,
                   CONCAT(IFNULL(p.primer_nombre,''),' ',IFNULL(p.segundo_nombre,''),' ',IFNULL(p.primer_apellido,''),' ',IFNULL(p.segundo_apellido,'')) AS nombre_estudiante,
                   CONCAT(IFNULL(pu.primer_nombre,''),' ',IFNULL(pu.segundo_nombre,''),' ',IFNULL(pu.primer_apellido,''),' ',IFNULL(pu.segundo_apellido,'')) AS nombre_evaluador,
                   CONCAT(IFNULL(pa.primer_nombre,''),' ',IFNULL(pa.segundo_nombre,''),' ',IFNULL(pa.primer_apellido,''),' ',IFNULL(pa.segundo_apellido,'')) AS nombre_analista,
                   eb.nombre AS nombre_esfera_mas_baja
            FROM pde_aplicaciones a
            INNER JOIN pde_rangos_edad r ON a.id_rango_inicio = r.id
            INNER JOIN estudiantes est ON a.id_estudiante = est.id
            INNER JOIN personas p ON est.id_persona = p.id
            LEFT JOIN usuarios u ON a.id_usuario = u.id
            LEFT JOIN personas pu ON u.id_persona = pu.id
            LEFT JOIN usuarios ua ON a.id_usuario_analisis = ua.id
            LEFT JOIN personas pa ON ua.id_persona = pa.id
            LEFT JOIN esferas_desarrollo eb ON a.id_esfera_mas_baja = eb.id
            WHERE a.id_estudiante = :id_estudiante AND a.activo = 1 AND a.id_tenant = :id_tenant
            ORDER BY a.fecha_aplicacion DESC
        ");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Edad actual del estudiante y rango sugerido de arranque (el que corresponde a su edad).
    public static function calcularEdad($id_estudiante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT p.fecha_nacimiento,
                   TIMESTAMPDIFF(MONTH, p.fecha_nacimiento, CURDATE()) AS edad_meses,
                   DATEDIFF(CURDATE(), p.fecha_nacimiento) AS edad_dias,
                   CONCAT(IFNULL(p.primer_nombre,''),' ',IFNULL(p.segundo_nombre,''),' ',IFNULL(p.primer_apellido,''),' ',IFNULL(p.segundo_apellido,'')) AS nombre_estudiante
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

        $edad_meses = (int)$data['edad_meses'];

        $sentRango = $db->prepare("
            SELECT id, nombre, edad_meses_inicio, edad_meses_fin, orden
            FROM pde_rangos_edad
            WHERE id_tenant = :id_tenant
              AND activo = 1
              AND :edad_meses >= edad_meses_inicio
              AND :edad_meses_fin < edad_meses_fin
            ORDER BY orden
            LIMIT 1
        ");
        $sentRango->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentRango->bindValue(':edad_meses', $edad_meses, PDO::PARAM_INT);
        $sentRango->bindValue(':edad_meses_fin', $edad_meses, PDO::PARAM_INT);
        $sentRango->execute();
        $rango = $sentRango->fetch();

        Flight::json(array(
            'fecha_nacimiento' => $data['fecha_nacimiento'],
            'edad_meses' => $edad_meses,
            'edad_dias' => (int)$data['edad_dias'],
            'nombre_estudiante' => trim(preg_replace('/\s+/', ' ', $data['nombre_estudiante'])),
            'rango_sugerido' => $rango ? $rango : null
        ));
    }

    // Conteo de lo que se daria por logrado si se arranca en un rango dado, para mostrarlo antes de confirmar.
    public static function getResumenAsumidos($id_rango_inicio)
    {
        $db = Flight::db();

        $sentOrden = $db->prepare("
            SELECT orden, nombre, edad_meses_inicio
            FROM pde_rangos_edad
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentOrden->bindParam(':id', $id_rango_inicio);
        $sentOrden->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentOrden->execute();
        $rangoInicio = $sentOrden->fetch();

        if (!$rangoInicio) {
            Flight::json(array('error' => 'Rango de inicio no encontrado'), 404);
            return;
        }

        $sentence = $db->prepare("
            SELECT r.id AS id_rango, r.nombre AS nombre_rango, r.orden,
                   e.nombre AS nombre_esfera,
                   COUNT(i.id) AS total_items
            FROM pde_items i
            INNER JOIN pde_rangos_edad r ON i.id_rango_edad = r.id
            INNER JOIN esferas_desarrollo e ON i.id_esfera = e.id
            WHERE r.orden < :orden
              AND i.activo = 1
              AND r.activo = 1
              AND i.id_tenant = :id_tenant
            GROUP BY r.id, r.nombre, r.orden, e.nombre
            ORDER BY r.orden, e.nombre
        ");
        $sentence->bindValue(':orden', (int)$rangoInicio['orden'], PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $detalle = $sentence->fetchAll();

        $total = 0;
        foreach ($detalle as $fila) {
            $total += (int)$fila['total_items'];
        }

        Flight::json(array(
            'rango_inicio' => $rangoInicio['nombre'],
            'meses_base' => (int)$rangoInicio['edad_meses_inicio'],
            'total_items_asumidos' => $total,
            'detalle' => $detalle
        ));
    }

    // Crea la aplicacion y siembra como logrados todos los items por debajo del rango de inicio.
    public static function iniciar()
    {
        $db = Flight::db();

        $id_estudiante = Flight::request()->data['id_estudiante'];
        $fecha_aplicacion = Flight::request()->data['fecha_aplicacion'];
        $edad_meses = Flight::request()->data['edad_meses'];
        $edad_dias = Flight::request()->data['edad_dias'];
        $id_rango_inicio = Flight::request()->data['id_rango_inicio'];
        $id_usuario = Flight::request()->data['id_usuario'];

        $sentOrden = $db->prepare("
            SELECT orden FROM pde_rangos_edad
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentOrden->bindParam(':id', $id_rango_inicio);
        $sentOrden->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentOrden->execute();
        $rangoInicio = $sentOrden->fetch();

        if (!$rangoInicio) {
            Flight::json(array('error' => 'Rango de inicio no encontrado'), 404);
            return;
        }

        $idNew = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO pde_aplicaciones (
                id, id_tenant, id_estudiante, fecha_aplicacion, edad_meses, edad_dias,
                id_rango_inicio, estado, id_usuario
            ) VALUES (
                :id, :id_tenant, :id_estudiante, :fecha_aplicacion, :edad_meses, :edad_dias,
                :id_rango_inicio, 'iniciada', :id_usuario
            )
        ");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha_aplicacion', $fecha_aplicacion);
        $sentence->bindParam(':edad_meses', $edad_meses);
        $sentence->bindParam(':edad_dias', $edad_dias);
        $sentence->bindParam(':id_rango_inicio', $id_rango_inicio);
        $sentence->bindParam(':id_usuario', $id_usuario);
        $sentence->execute();

        $sentAsumidos = $db->prepare("
            INSERT INTO pde_aplicaciones_detalle (id, id_tenant, id_aplicacion, id_item, puntaje, asumido)
            SELECT uuid(), :id_tenant, :id_aplicacion, i.id, i.puntaje_maximo, 1
            FROM pde_items i
            INNER JOIN pde_rangos_edad r ON i.id_rango_edad = r.id
            WHERE r.orden < :orden
              AND i.activo = 1
              AND r.activo = 1
              AND i.id_tenant = :id_tenant_items
        ");
        $sentAsumidos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentAsumidos->bindValue(':id_tenant_items', TenantContext::id(), PDO::PARAM_INT);
        $sentAsumidos->bindValue(':id_aplicacion', $idNew);
        $sentAsumidos->bindValue(':orden', (int)$rangoInicio['orden'], PDO::PARAM_INT);
        $sentAsumidos->execute();

        Flight::json(array('id' => $idNew, 'items_asumidos' => $sentAsumidos->rowCount()));
    }

    // Guarda los puntajes de un rango dentro de una esfera y devuelve el semaforo y la sugerencia de parar.
    public static function guardarRango()
    {
        $db = Flight::db();

        $id_aplicacion = Flight::request()->data['id_aplicacion'];
        $id_esfera = Flight::request()->data['id_esfera'];
        $id_rango_edad = Flight::request()->data['id_rango_edad'];
        $items = Flight::request()->data['items'] ?? [];

        try {
            $config = PdeConfiguracion::obtenerVigente($db);
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()), 409);
            return;
        }

        $sentExiste = $db->prepare("
            SELECT id FROM pde_aplicaciones
            WHERE id = :id AND activo = 1 AND id_tenant = :id_tenant
        ");
        $sentExiste->bindParam(':id', $id_aplicacion);
        $sentExiste->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentExiste->execute();

        if (!$sentExiste->fetch()) {
            Flight::json(array('error' => 'Aplicacion no encontrada'), 404);
            return;
        }

        $sentDel = $db->prepare("
            DELETE d FROM pde_aplicaciones_detalle d
            INNER JOIN pde_items i ON d.id_item = i.id
            WHERE d.id_aplicacion = :id_aplicacion
              AND i.id_esfera = :id_esfera
              AND i.id_rango_edad = :id_rango_edad
              AND d.id_tenant = :id_tenant
        ");
        $sentDel->bindParam(':id_aplicacion', $id_aplicacion);
        $sentDel->bindParam(':id_esfera', $id_esfera);
        $sentDel->bindParam(':id_rango_edad', $id_rango_edad);
        $sentDel->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentDel->execute();

        if (is_array($items) && count($items) > 0) {
            $sentIns = $db->prepare("
                INSERT INTO pde_aplicaciones_detalle (id, id_tenant, id_aplicacion, id_item, puntaje, asumido)
                VALUES (:id, :id_tenant, :id_aplicacion, :id_item, :puntaje, 0)
            ");
            foreach ($items as $item) {
                $id_item = $item['id_item'];
                $puntaje = $item['puntaje'];

                $sentIns->bindValue(':id', Uuid::generar());
                $sentIns->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentIns->bindParam(':id_aplicacion', $id_aplicacion);
                $sentIns->bindParam(':id_item', $id_item);
                $sentIns->bindParam(':puntaje', $puntaje);
                $sentIns->execute();
            }
        }

        $sentBloque = $db->prepare("
            SELECT SUM(d.puntaje) AS puntos, SUM(i.puntaje_maximo) AS posibles
            FROM pde_aplicaciones_detalle d
            INNER JOIN pde_items i ON d.id_item = i.id
            WHERE d.id_aplicacion = :id_aplicacion
              AND i.id_esfera = :id_esfera
              AND i.id_rango_edad = :id_rango_edad
              AND d.asumido = 0
              AND d.id_tenant = :id_tenant
        ");
        $sentBloque->bindParam(':id_aplicacion', $id_aplicacion);
        $sentBloque->bindParam(':id_esfera', $id_esfera);
        $sentBloque->bindParam(':id_rango_edad', $id_rango_edad);
        $sentBloque->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentBloque->execute();
        $bloque = $sentBloque->fetch();

        $puntos = (int)$bloque['puntos'];
        $posibles = (int)$bloque['posibles'];
        $porcentaje = $posibles > 0 ? ($puntos / $posibles) * 100 : 0;
        $semaforo_bloque = self::clasificar($porcentaje, $config);

        try {
            $resumen_esfera = self::recalcularEsfera($db, $id_aplicacion, $id_esfera, $config);
            $global = self::recalcularGlobal($db, $id_aplicacion, $config);
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()), 409);
            return;
        }

        $sentEstado = $db->prepare("
            UPDATE pde_aplicaciones SET estado = 'en_proceso'
            WHERE id = :id AND estado = 'iniciada' AND id_tenant = :id_tenant
        ");
        $sentEstado->bindParam(':id', $id_aplicacion);
        $sentEstado->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentEstado->execute();

        $sugerir_parar = ($config['avisar_al_rojo'] === 1 && $semaforo_bloque === 'rojo');

        Flight::json(array(
            'id_aplicacion' => $id_aplicacion,
            'id_esfera' => $id_esfera,
            'id_rango_edad' => $id_rango_edad,
            'puntos' => $puntos,
            'posibles' => $posibles,
            'porcentaje' => round($porcentaje, 2),
            'semaforo' => $semaforo_bloque,
            'sugerir_parar' => $sugerir_parar,
            'esfera' => $resumen_esfera,
            'global' => $global
        ));
    }

    public static function finalizar()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $observaciones = Flight::request()->data['observaciones'] ?? null;
        $analisis = Flight::request()->data['analisis'] ?? null;
        $recomendaciones = Flight::request()->data['recomendaciones'] ?? null;
        $id_usuario_analisis = Flight::request()->data['id_usuario_analisis'] ?? null;

        try {
            $config = PdeConfiguracion::obtenerVigente($db);
            self::recalcularGlobal($db, $id, $config);
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()), 409);
            return;
        }

        $sentence = $db->prepare("
            UPDATE pde_aplicaciones
            SET estado = 'finalizada',
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

        Flight::json(array('id' => $id, 'message' => 'Aplicacion finalizada'));
    }

    public static function anular()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("UPDATE pde_aplicaciones SET activo = 0 WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function actualizarObservaciones()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $observaciones = Flight::request()->data['observaciones'];

        $sentence = $db->prepare("
            UPDATE pde_aplicaciones SET observaciones = :observaciones
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':observaciones', $observaciones);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id, 'message' => 'Observaciones actualizadas'));
    }

    public static function actualizarAnalisis()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $analisis = Flight::request()->data['analisis'] ?? null;
        $recomendaciones = Flight::request()->data['recomendaciones'] ?? null;
        $id_usuario_analisis = Flight::request()->data['id_usuario_analisis'];

        $sentence = $db->prepare("
            UPDATE pde_aplicaciones
            SET analisis = :analisis,
                recomendaciones = :recomendaciones,
                id_usuario_analisis = :id_usuario_analisis,
                fecha_analisis = NOW()
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':analisis', $analisis);
        $sentence->bindParam(':recomendaciones', $recomendaciones);
        $sentence->bindParam(':id_usuario_analisis', $id_usuario_analisis);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id, 'message' => 'Analisis actualizado'));
    }

    // Cabecera, esferas y puntajes ya cargados, para retomar una aplicacion a medias.
    public static function getParaRetomar($id)
    {
        $db = Flight::db();

        $sentCab = $db->prepare("
            SELECT a.*,
                   r.nombre AS nombre_rango_inicio, r.orden AS orden_rango_inicio,
                   r.edad_meses_inicio AS meses_base,
                   CONCAT(IFNULL(p.primer_nombre,''),' ',IFNULL(p.segundo_nombre,''),' ',IFNULL(p.primer_apellido,''),' ',IFNULL(p.segundo_apellido,'')) AS nombre_estudiante,
                   p.fecha_nacimiento
            FROM pde_aplicaciones a
            INNER JOIN pde_rangos_edad r ON a.id_rango_inicio = r.id
            INNER JOIN estudiantes est ON a.id_estudiante = est.id
            INNER JOIN personas p ON est.id_persona = p.id
            WHERE a.id = :id AND a.activo = 1 AND a.id_tenant = :id_tenant
        ");
        $sentCab->bindParam(':id', $id);
        $sentCab->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentCab->execute();
        $aplicacion = $sentCab->fetch();

        if (!$aplicacion) {
            Flight::json(array('error' => 'Aplicacion no encontrada'), 404);
            return;
        }

        $aplicacion['nombre_estudiante'] = trim(preg_replace('/\s+/', ' ', $aplicacion['nombre_estudiante']));

        $sentPuntajes = $db->prepare("
            SELECT d.id_item, d.puntaje, d.asumido,
                   i.id_esfera, i.id_rango_edad
            FROM pde_aplicaciones_detalle d
            INNER JOIN pde_items i ON d.id_item = i.id
            WHERE d.id_aplicacion = :id AND d.id_tenant = :id_tenant
        ");
        $sentPuntajes->bindParam(':id', $id);
        $sentPuntajes->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentPuntajes->execute();
        $puntajes = $sentPuntajes->fetchAll();

        $sentEsferas = $db->prepare("
            SELECT ae.id_esfera, ae.id_rango_techo, ae.edad_desarrollo_meses, ae.indice, ae.semaforo,
                   e.nombre AS nombre_esfera
            FROM pde_aplicaciones_esferas ae
            INNER JOIN esferas_desarrollo e ON ae.id_esfera = e.id
            WHERE ae.id_aplicacion = :id AND ae.id_tenant = :id_tenant
            ORDER BY e.nombre
        ");
        $sentEsferas->bindParam(':id', $id);
        $sentEsferas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentEsferas->execute();
        $esferas = $sentEsferas->fetchAll();

        Flight::json(array(
            'aplicacion' => $aplicacion,
            'puntajes' => $puntajes,
            'esferas' => $esferas
        ));
    }

    // Listado de estudiantes con el estado de su ultima aplicacion.
    public static function getListadoEstudiantes()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT
                est.id,
                est.activo,
                CONCAT(IFNULL(p.primer_nombre,''),' ',IFNULL(p.segundo_nombre,''),' ',IFNULL(p.primer_apellido,''),' ',IFNULL(p.segundo_apellido,'')) AS nombre_completo,
                IFNULL(grp.nombre, 'Sin grupo') AS nombre_grupo,
                p.fecha_nacimiento,
                TIMESTAMPDIFF(MONTH, p.fecha_nacimiento, CURDATE()) AS edad_meses,
                TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad_anios,
                CASE WHEN est.activo = 1 THEN 'Activo' ELSE 'Inactivo' END AS estado_estudiante,
                (SELECT a1.id FROM pde_aplicaciones a1
                   WHERE a1.id_estudiante = est.id AND a1.activo = 1
                   ORDER BY a1.fecha_aplicacion DESC LIMIT 1) AS pde_ultima_id,
                IFNULL((SELECT a2.estado FROM pde_aplicaciones a2
                   WHERE a2.id_estudiante = est.id AND a2.activo = 1
                   ORDER BY a2.fecha_aplicacion DESC LIMIT 1), '') AS pde_estado,
                IFNULL((SELECT DATE_FORMAT(a3.fecha_aplicacion, '%Y-%m-%d') FROM pde_aplicaciones a3
                   WHERE a3.id_estudiante = est.id AND a3.activo = 1
                   ORDER BY a3.fecha_aplicacion DESC LIMIT 1), '') AS pde_fecha,
                (SELECT a4.indice_global FROM pde_aplicaciones a4
                   WHERE a4.id_estudiante = est.id AND a4.activo = 1
                   ORDER BY a4.fecha_aplicacion DESC LIMIT 1) AS pde_indice
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
}
