<?php
/**
 * Reporte "Ejecución Actividades Del Horario": cómo cumplió cada grupo su
 * horario en un rango de fechas, día por día. Se puede pedir un grupo o
 * todos; cada día se calcula una sola vez para todos los grupos.
 *
 * Cada día sale de DashboardGerencial::ejecucionAcademicaGruposContexto, la
 * misma regla del tab Académico del dashboard: la clase queda amarrada a la
 * franja de su área que se guardó al iniciarla, y la franja queda ejecutada,
 * en curso, fuera de hora, por venir o sin ejecutar.
 *
 * Lo propio de este reporte:
 *   - Salen los días hábiles en que el grupo tenía horario (aunque no se
 *     haya dictado nada, para que se vea lo que no se dictó) y los días no
 *     hábiles en que sí hubo alguna clase ejecutada. Los días hábiles sin
 *     ninguna ejecución se cuentan aparte.
 *   - Las clases fuera de horario llevan la franja del grupo que estaba
 *     corriendo a la hora real de inicio; si es de otra área, queda en
 *     en_hora_de (por ejemplo, Matemáticas dictada en la hora de Inglés).
 */
class EjecucionHorarioGrupo
{
    const PERMISO = 'reportes.ejecucion_horario_grupo';
    const MAX_DIAS = 31;
    // Valor de id_grupo para traer todos los grupos
    const TODOS = 'todos';

    // Igual que en DashboardGerencial
    const ESTADO_TAREA_EJECUTADA = 2;

    /**
     * GET /ejecucion-horario-grupo?id_grupo=&desde=AAAA-MM-DD&hasta=AAAA-MM-DD
     * id_grupo=todos trae todos los grupos que tenían horario o clases en el
     * rango. Siempre devuelve {desde, hasta, grupos: [{grupo, resumen, dias}]}.
     */
    public static function get()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);
        try {
            $db = Flight::db();
            $idGrupo = Flight::request()->query['id_grupo'] ?? null;
            $desde = Flight::request()->query['desde'] ?? null;
            $hasta = Flight::request()->query['hasta'] ?? null;

            if (!$idGrupo) {
                Flight::json(array('error' => 'Escoge el grupo'), 400);
                return;
            }
            // El front ya valida las fechas; esto queda como seguro
            if (!self::esFechaValida($desde) || !self::esFechaValida($hasta)) {
                Flight::json(array('error' => 'Las fechas deben tener el formato AAAA-MM-DD'), 400);
                return;
            }
            if ($desde > $hasta) {
                Flight::json(array('error' => 'La fecha inicial no puede ser mayor que la fecha final'), 400);
                return;
            }
            $dias = (int) ((strtotime($hasta) - strtotime($desde)) / 86400) + 1;
            if ($dias > self::MAX_DIAS) {
                Flight::json(array('error' => 'El rango puede tener máximo ' . self::MAX_DIAS . ' días'), 400);
                return;
            }

            $grupos = self::grupos($db, $idGrupo === self::TODOS ? null : $idGrupo);
            if (empty($grupos)) {
                Flight::json(array('error' => 'El grupo no existe'), 404);
                return;
            }

            $resultado = self::armar($db, $grupos, $desde, $hasta);

            // Con todos, solo quedan los grupos que tenían horario o clases
            if ($idGrupo === self::TODOS) {
                $resultado = array_values(array_filter($resultado, function ($g) {
                    return count($g['dias']) > 0;
                }));
            }

            Flight::json(array('desde' => $desde, 'hasta' => $hasta, 'grupos' => $resultado));
        } catch (Exception $e) {
            error_log('[EjecucionHorarioGrupo::get] ' . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /** El grupo pedido, o todos los del jardín en su orden si $idGrupo es null */
    private static function grupos(PDO $db, $idGrupo)
    {
        $sql = "SELECT id, nombre, icono, color FROM grupos WHERE id_tenant = :id_tenant";
        if ($idGrupo !== null) {
            $sql .= " AND id = :id";
        }
        $sql .= " ORDER BY orden, nombre";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        if ($idGrupo !== null) {
            $stmt->bindValue(':id', $idGrupo);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recorre los días del rango una sola vez (cada día trae todos los
     * grupos) y le arma a cada grupo sus días: los hábiles con horario y los
     * que tuvieron alguna clase ejecutada.
     */
    private static function armar(PDO $db, array $grupos, $desde, $hasta)
    {
        $porGrupo = array();
        foreach ($grupos as $g) {
            $porGrupo[$g['id']] = array(
                'grupo' => $g,
                'resumen' => array(
                    'dias_con_ejecucion' => 0,
                    'dias_habiles_sin_ejecucion' => 0,
                    'fechas_sin_ejecucion' => array(),
                    'total_franjas' => 0,
                    'ejecutadas' => 0,
                    'en_curso' => 0,
                    'fuera_hora' => 0,
                    'sin_ejecutar' => 0,
                    'por_venir' => 0,
                    'clases_fuera_horario' => 0,
                    'cruces_area' => 0
                ),
                'dias' => array()
            );
        }

        for ($t = strtotime($desde); $t <= strtotime($hasta); $t = strtotime('+1 day', $t)) {
            $fecha = date('Y-m-d', $t);
            $r = DashboardGerencial::ejecucionAcademicaGruposContexto($db, $fecha);

            foreach ($porGrupo as $idGrupo => &$g) {
                $dia = self::diaDelGrupo($r, $idGrupo);
                $resumen = &$g['resumen'];

                if (!$dia['tiene_ejecucion']) {
                    // Sin clases y sin horario ese día (fin de semana, festivo): no sale
                    if (!$r['dia_habil'] || count($dia['franjas']) === 0) {
                        unset($resumen);
                        continue;
                    }
                    $resumen['dias_habiles_sin_ejecucion']++;
                    $resumen['fechas_sin_ejecucion'][] = $fecha;
                } else {
                    $resumen['dias_con_ejecucion']++;
                }

                foreach (array('total_franjas', 'ejecutadas', 'en_curso', 'fuera_hora', 'sin_ejecutar', 'por_venir') as $clave) {
                    $resumen[$clave] += $dia['resumen'][$clave];
                }
                $resumen['clases_fuera_horario'] += count($dia['fuera']);
                foreach ($dia['fuera'] as $a) {
                    if ($a['en_hora_de'] !== null) {
                        $resumen['cruces_area']++;
                    }
                }
                unset($resumen);

                unset($dia['tiene_ejecucion']);
                $g['dias'][] = $dia;
            }
            unset($g);
        }

        foreach ($porGrupo as &$g) {
            $g['resumen']['evaluables'] = $g['resumen']['total_franjas'] - $g['resumen']['por_venir'];
            $g['resumen']['porcentaje_cumplimiento'] = $g['resumen']['evaluables'] > 0
                ? round(($g['resumen']['ejecutadas'] / $g['resumen']['evaluables']) * 100, 1) : 0;
        }
        unset($g);

        return array_values($porGrupo);
    }

    /**
     * Del resultado de un día para todos los grupos, lo del grupo: sus franjas
     * con estado y actividades, sus clases fuera de horario y su resumen.
     */
    private static function diaDelGrupo(array $r, $idGrupo)
    {
        $franjas = array();
        $tieneEjecucion = false;
        $contador = array('total_franjas' => 0, 'ejecutadas' => 0, 'en_curso' => 0, 'fuera_hora' => 0, 'sin_ejecutar' => 0, 'por_venir' => 0);

        foreach ($r['franjas'] as $f) {
            if ($f['id_destino'] !== $idGrupo) {
                continue;
            }
            $franjas[] = array(
                'id_franja' => $f['id_franja'],
                'id_area_academica' => $f['id_area_academica'],
                'nombre_area' => $f['nombre_area'],
                'color_area' => $f['color_area'],
                'nombre_franja' => $f['nombre_franja'],
                'hora_inicial' => $f['hora_inicial'],
                'hora_final' => $f['hora_final'],
                'docente_asignado' => $f['docente_asignado'] ?: 'Sin docente',
                'estado' => $f['estado'],
                'actividades' => $f['actividades']
            );
            $contador['total_franjas']++;
            $contador[$f['estado'] === 'ejecutada' ? 'ejecutadas' : $f['estado']]++;
            foreach ($f['actividades'] as $a) {
                if ($a['id_estado_tarea'] === self::ESTADO_TAREA_EJECUTADA) {
                    $tieneEjecucion = true;
                }
            }
        }

        $fuera = array();
        foreach ($r['fuera_horario'] as $a) {
            if ($a['id_destino'] !== $idGrupo) {
                continue;
            }
            if ($a['id_estado_tarea'] === self::ESTADO_TAREA_EJECUTADA) {
                $tieneEjecucion = true;
            }
            $ocupada = self::franjaEnLaHora($franjas, $a['hora_inicio']);
            $a['en_hora_de'] = ($ocupada !== null && $ocupada['nombre_area'] !== $a['nombre_area'])
                ? $ocupada['nombre_area'] : null;
            $a['id_franja_ocupada'] = $ocupada !== null ? $ocupada['id_franja'] : null;
            $fuera[] = $a;
        }

        $contador['evaluables'] = $contador['total_franjas'] - $contador['por_venir'];
        $contador['porcentaje_cumplimiento'] = $contador['evaluables'] > 0
            ? round(($contador['ejecutadas'] / $contador['evaluables']) * 100, 1) : 0;

        return array(
            'fecha' => $r['fecha'],
            'nombre_dia' => $r['nombre_dia'],
            'dia_habil' => $r['dia_habil'],
            'tiene_ejecucion' => $tieneEjecucion,
            'resumen' => $contador,
            'franjas' => $franjas,
            'fuera' => $fuera
        );
    }

    /** Franja del grupo que estaba corriendo a esa hora (HH:MM), o null */
    private static function franjaEnLaHora(array $franjas, $hora)
    {
        if ($hora === null) {
            return null;
        }
        $m = self::minutos($hora);
        foreach ($franjas as $f) {
            if ($m >= self::minutos($f['hora_inicial']) && $m < self::minutos($f['hora_final'])) {
                return $f;
            }
        }
        return null;
    }

    private static function minutos($hora)
    {
        $partes = explode(':', (string) $hora);
        return ((int) $partes[0]) * 60 + (isset($partes[1]) ? (int) $partes[1] : 0);
    }

    private static function esFechaValida($fecha)
    {
        if (!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return false;
        }
        list($a, $m, $d) = explode('-', $fecha);
        return checkdate((int) $m, (int) $d, (int) $a);
    }
}