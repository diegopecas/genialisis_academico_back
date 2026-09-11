<?php 
class Calendarios
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, fecha, id_tipo_dia, dia, mes, anio, id_dia_semana, dia_habil from calendarios");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, fecha, id_tipo_dia, dia, mes, anio, id_dia_semana, dia_habil from calendarios where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getDiasHabiles($fecha_inicial, $fecha_final)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                c.id, c.fecha, c.id_tipo_dia, c.dia, c.mes, c.anio, c.id_dia_semana,
                td.nombre as tipo_dia_nombre,
                ds.nombre as dia_semana_nombre
            FROM calendarios c
            LEFT JOIN tipos_dias td ON c.id_tipo_dia = td.id
            LEFT JOIN dias_semana ds ON c.id_dia_semana = ds.id
            WHERE c.fecha BETWEEN :fecha_inicial AND :fecha_final
                AND c.id_tipo_dia = 1
            ORDER BY c.fecha
        ");
        $sentence->bindParam(':fecha_inicial', $fecha_inicial);
        $sentence->bindParam(':fecha_final', $fecha_final);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByRangoFechas($fecha_inicial, $fecha_final)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                c.id, c.fecha, c.id_tipo_dia, c.dia, c.mes, c.anio, c.id_dia_semana,
                td.nombre as tipo_dia_nombre,
                ds.nombre as dia_semana_nombre
            FROM calendarios c
            LEFT JOIN tipos_dias td ON c.id_tipo_dia = td.id
            LEFT JOIN dias_semana ds ON c.id_dia_semana = ds.id
            WHERE c.fecha BETWEEN :fecha_inicial AND :fecha_final
            ORDER BY c.fecha
        ");
        $sentence->bindParam(':fecha_inicial', $fecha_inicial);
        $sentence->bindParam(':fecha_final', $fecha_final);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Endpoint para el portal de padres (y el calendario institucional por mes).
     * Devuelve días del mes, eventos y cumpleaños al vuelo.
     * La estructura de la respuesta no cambia; solo se suman campos.
     */
    public static function getCalendarioMes($anio, $mes)
    {
        $db = Flight::db();
        $anio = (int) $anio;
        $mes = (int) $mes;

        // 1. Días del mes
        $stmtDias = $db->prepare("
            SELECT 
                c.id, c.fecha, c.id_tipo_dia, c.dia, c.mes, c.anio, c.id_dia_semana, c.dia_habil,
                td.nombre AS tipo_dia_nombre,
                ds.nombre AS dia_semana_nombre
            FROM calendarios c
            LEFT JOIN tipos_dias td ON c.id_tipo_dia = td.id
            LEFT JOIN dias_semana ds ON c.id_dia_semana = ds.id
            WHERE c.anio = :anio AND c.mes = :mes
            ORDER BY c.dia
        ");
        $stmtDias->bindParam(':anio', $anio, PDO::PARAM_INT);
        $stmtDias->bindParam(':mes', $mes, PDO::PARAM_INT);
        $stmtDias->execute();
        $dias = $stmtDias->fetchAll();

        // 2. Eventos del mes
        $fecha_inicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fecha_fin = date('Y-m-t', strtotime($fecha_inicio));
        $eventos = self::eventosRango($db, $fecha_inicio, $fecha_fin);

        // 3. Cumpleaños de estudiantes, colaboradores y acudientes
        $cumpleanos = self::cumpleanos($db, $anio, $mes);

        Flight::json([
            'anio' => $anio,
            'mes' => $mes,
            'dias' => $dias,
            'eventos' => $eventos,
            'cumpleanos' => $cumpleanos
        ]);
    }

    /**
     * Calendario de todo el año en una sola llamada (vistas Año y Lista del institucional).
     * Misma estructura que getCalendarioMes; cada cumpleaños trae además 'mes' y 'fecha'.
     */
    public static function getCalendarioAnio($anio)
    {
        $db = Flight::db();
        $anio = (int) $anio;

        $stmtDias = $db->prepare("
            SELECT 
                c.id, c.fecha, c.id_tipo_dia, c.dia, c.mes, c.anio, c.id_dia_semana, c.dia_habil,
                td.nombre AS tipo_dia_nombre,
                ds.nombre AS dia_semana_nombre
            FROM calendarios c
            LEFT JOIN tipos_dias td ON c.id_tipo_dia = td.id
            LEFT JOIN dias_semana ds ON c.id_dia_semana = ds.id
            WHERE c.anio = :anio
            ORDER BY c.fecha
        ");
        $stmtDias->bindParam(':anio', $anio, PDO::PARAM_INT);
        $stmtDias->execute();
        $dias = $stmtDias->fetchAll();

        $eventos = self::eventosRango($db, sprintf('%04d-01-01', $anio), sprintf('%04d-12-31', $anio));
        $cumpleanos = self::cumpleanos($db, $anio, null);

        Flight::json([
            'anio' => $anio,
            'dias' => $dias,
            'eventos' => $eventos,
            'cumpleanos' => $cumpleanos
        ]);
    }

    /**
     * Eventos del tenant entre dos fechas, con su tipo, icono y color.
     */
    private static function eventosRango($db, $fecha_inicio, $fecha_fin)
    {
        $stmtEventos = $db->prepare("
            SELECT 
                ce.id, ce.fecha, ce.hora_inicio, ce.hora_fin, ce.id_tipo_evento_calendario, ce.descripcion,
                tec.nombre AS tipo_evento_nombre,
                tec.icono AS tipo_evento_icono,
                tec.color AS tipo_evento_color
            FROM calendarios_eventos ce
            LEFT JOIN tipos_evento_calendario tec ON tec.id = ce.id_tipo_evento_calendario
            WHERE ce.fecha BETWEEN :fecha_inicio AND :fecha_fin
            AND ce.id_tenant = :id_tenant
            ORDER BY ce.fecha, ce.hora_inicio
        ");
        $stmtEventos->bindParam(':fecha_inicio', $fecha_inicio);
        $stmtEventos->bindParam(':fecha_fin', $fecha_fin);
        $stmtEventos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtEventos->execute();
        return $stmtEventos->fetchAll();
    }

    /**
     * Cumpleaños de estudiantes, colaboradores y acudientes activos.
     *
     * Privacidad: en el portal de padres cada acudiente solo recibe SU propio
     * cumpleaños (el claim 'portal' y el id_persona viajan firmados en el token);
     * en el institucional se reciben todos.
     *
     * @param int      $anio Año que se pinta (para ubicar el 29 de febrero en años no bisiestos)
     * @param int|null $mes  Mes de 1 a 12, o null para todo el año
     */
    private static function cumpleanos($db, $anio, $mes)
    {
        $userData = JWTService::requerirAutenticacion();
        $esPortalPadres = isset($userData->portal) && $userData->portal === JWTService::PORTAL_PADRES;
        $idPersonaUsuario = isset($userData->id_persona) ? $userData->id_persona : null;

        $filtroMes = $mes === null ? '' : 'AND MONTH(p.fecha_nacimiento) = :mes';

        // Estudiantes activos
        $stmtEstudiantes = $db->prepare("
            SELECT 
                p.id AS id_persona,
                p.primer_nombre,
                p.primer_apellido,
                p.fecha_nacimiento,
                p.id_genero
            FROM personas p
            INNER JOIN estudiantes e ON e.id_persona = p.id AND e.activo = 1
            WHERE p.fecha_nacimiento IS NOT NULL
            $filtroMes
            AND e.id_tenant = :id_tenant
        ");
        if ($mes !== null) {
            $stmtEstudiantes->bindValue(':mes', $mes, PDO::PARAM_INT);
        }
        $stmtEstudiantes->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtEstudiantes->execute();

        // Colaboradores activos (con sobrenombre y cargo)
        $stmtColaboradores = $db->prepare("
            SELECT 
                p.id AS id_persona,
                p.primer_nombre,
                p.primer_apellido,
                p.fecha_nacimiento,
                p.id_genero,
                col.sobrenombre,
                ca.nombre_corto AS cargo_nombre_corto
            FROM personas p
            INNER JOIN colaboradores col ON col.id_persona = p.id AND col.activo = 1
            LEFT JOIN cargos ca ON ca.id = col.id_cargo
            WHERE p.fecha_nacimiento IS NOT NULL
            $filtroMes
            AND col.id_tenant = :id_tenant
        ");
        if ($mes !== null) {
            $stmtColaboradores->bindValue(':mes', $mes, PDO::PARAM_INT);
        }
        $stmtColaboradores->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtColaboradores->execute();

        // Acudientes activos de estudiantes activos (una fila por cada niño que acompañan)
        $filtroPersona = $esPortalPadres ? 'AND p.id = :id_persona_usuario' : '';
        $stmtAcudientes = $db->prepare("
            SELECT 
                p.id AS id_persona,
                p.primer_nombre,
                p.primer_apellido,
                p.fecha_nacimiento,
                p.id_genero,
                ta.nombre_femenino,
                ta.nombre_masculino,
                pe.primer_nombre AS estudiante_nombre
            FROM acudientes a
            INNER JOIN personas p ON p.id = a.id_persona
            INNER JOIN estudiantes e ON e.id = a.id_estudiante AND e.activo = 1
            INNER JOIN personas pe ON pe.id = e.id_persona
            LEFT JOIN tipos_acudiente ta ON ta.id = a.id_tipo_acudiente
            WHERE a.activo = 1
            AND p.fecha_nacimiento IS NOT NULL
            $filtroMes
            $filtroPersona
            AND a.id_tenant = :id_tenant
        ");
        if ($mes !== null) {
            $stmtAcudientes->bindValue(':mes', $mes, PDO::PARAM_INT);
        }
        if ($esPortalPadres) {
            $stmtAcudientes->bindValue(':id_persona_usuario', $idPersonaUsuario);
        }
        $stmtAcudientes->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtAcudientes->execute();

        $cumpleanos = [];

        foreach ($stmtEstudiantes->fetchAll() as $c) {
            $cumpleanos[] = self::armarCumpleanos($c, $anio, 'estudiante', [
                'nombre' => trim($c['primer_nombre'] . ' ' . $c['primer_apellido']),
                'nombre_corto' => trim((string) $c['primer_nombre']),
                'es_usuario_actual' => $c['id_persona'] === $idPersonaUsuario
            ]);
        }

        foreach ($stmtColaboradores->fetchAll() as $c) {
            // Para colaboradores: usar sobrenombre si existe, si no primer nombre y apellido
            $cumpleanos[] = self::armarCumpleanos($c, $anio, 'colaborador', [
                'nombre' => !empty($c['sobrenombre']) ? $c['sobrenombre'] : trim($c['primer_nombre'] . ' ' . $c['primer_apellido']),
                'nombre_corto' => !empty($c['sobrenombre']) ? trim($c['sobrenombre']) : trim((string) $c['primer_nombre']),
                'cargo' => $c['cargo_nombre_corto'],
                'es_usuario_actual' => $c['id_persona'] === $idPersonaUsuario
            ]);
        }

        // Un acudiente con varios niños llega en varias filas: se agrupa por persona
        $acudientes = [];
        foreach ($stmtAcudientes->fetchAll() as $c) {
            $id = $c['id_persona'];
            if (!isset($acudientes[$id])) {
                $acudientes[$id] = ['fila' => $c, 'estudiantes' => [], 'parentescos' => []];
            }
            $nombreEstudiante = trim((string) $c['estudiante_nombre']);
            if ($nombreEstudiante !== '' && !in_array($nombreEstudiante, $acudientes[$id]['estudiantes'], true)) {
                $acudientes[$id]['estudiantes'][] = $nombreEstudiante;
            }
            $acudientes[$id]['parentescos'][] = self::parentesco($c['id_genero'], $c['nombre_femenino'], $c['nombre_masculino']);
        }

        foreach ($acudientes as $id => $a) {
            $c = $a['fila'];
            $parentescos = array_unique($a['parentescos']);
            // Si con todos sus niños tiene el mismo parentesco se usa; si no, "familiar"
            $parentesco = (count($parentescos) === 1 && reset($parentescos) !== null) ? reset($parentescos) : 'familiar';

            $cumpleanos[] = self::armarCumpleanos($c, $anio, 'acudiente', [
                'nombre' => trim($c['primer_nombre'] . ' ' . $c['primer_apellido']),
                'nombre_corto' => trim((string) $c['primer_nombre']),
                'parentesco' => $parentesco,
                'estudiantes' => self::unirNombres($a['estudiantes']),
                'es_usuario_actual' => $id === $idPersonaUsuario
            ]);
        }

        // Ordenar cumpleaños por mes y día
        usort($cumpleanos, function ($a, $b) {
            return ($a['mes'] - $b['mes']) ?: ($a['dia'] - $b['dia']);
        });

        return $cumpleanos;
    }

    /**
     * Arma el registro de cumpleaños con los campos comunes a todos los tipos.
     * El 29 de febrero se muestra el 28 en los años que no son bisiestos.
     */
    private static function armarCumpleanos($fila, $anio, $tipoPersona, $extra)
    {
        // Nombres sin espacios dobles (hay registros con espacios de más)
        foreach (['nombre', 'nombre_corto'] as $campo) {
            if (isset($extra[$campo])) {
                $extra[$campo] = trim(preg_replace('/\s+/u', ' ', (string) $extra[$campo]));
            }
        }

        $mesCumple = (int) date('n', strtotime($fila['fecha_nacimiento']));
        $diaCumple = (int) date('j', strtotime($fila['fecha_nacimiento']));
        if ($mesCumple === 2 && $diaCumple === 29 && !checkdate(2, 29, (int) $anio)) {
            $diaCumple = 28;
        }

        return array_merge([
            'id_persona' => $fila['id_persona'],
            'nombre' => '',
            'nombre_corto' => '',
            'tipo_persona' => $tipoPersona,
            'dia' => $diaCumple,
            'mes' => $mesCumple,
            'fecha' => sprintf('%04d-%02d-%02d', $anio, $mesCumple, $diaCumple),
            'fecha_nacimiento' => $fila['fecha_nacimiento'],
            'id_genero' => $fila['id_genero'] !== null ? (int) $fila['id_genero'] : null,
            'cargo' => null,
            'parentesco' => null,
            'estudiantes' => null,
            'es_usuario_actual' => false
        ], $extra);
    }

    /**
     * Parentesco según el género de la persona (1 femenino, 2 masculino).
     * Sin género solo se usa si es igual en las dos formas (ej. mamá, papá).
     * Devuelve null cuando no aplica, para que el mensaje diga "familiar".
     */
    private static function parentesco($idGenero, $nombreFemenino, $nombreMasculino)
    {
        $femenino = trim((string) $nombreFemenino);
        $masculino = trim((string) $nombreMasculino);

        if ((int) $idGenero === 1) {
            return $femenino !== '' ? $femenino : null;
        }
        if ((int) $idGenero === 2) {
            return $masculino !== '' ? $masculino : null;
        }
        return ($femenino !== '' && $femenino === $masculino) ? $femenino : null;
    }

    /** "Sofía", "Sofía y Juan", "Sofía, Juan y Ana" */
    private static function unirNombres($nombres)
    {
        if (count($nombres) <= 1) {
            return implode('', $nombres);
        }
        $ultimo = array_pop($nombres);
        return implode(', ', $nombres) . ' y ' . $ultimo;
    }

    private static function convertirDiaSemana($dia_php)
    {
        if ($dia_php == 0) {
            return 7;
        } else {
            return $dia_php;
        }
    }

    public static function new()
    {
        $db = Flight::db();
        $fecha = Flight::request()->data['fecha'];
        $id_tipo_dia = Flight::request()->data['id_tipo_dia'];
        
        $fecha_obj = new DateTime($fecha);
        $dia = (int)$fecha_obj->format('d');
        $mes = (int)$fecha_obj->format('m');
        $anio = (int)$fecha_obj->format('Y');
        $dia_semana_php = (int)$fecha_obj->format('w');
        $id_dia_semana = self::convertirDiaSemana($dia_semana_php);
        
        $sentence = $db->prepare("insert into calendarios(fecha, id_tipo_dia, dia, mes, anio, id_dia_semana) values (:fecha, :id_tipo_dia, :dia, :mes, :anio, :id_dia_semana)");
        
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':id_tipo_dia', $id_tipo_dia);
        $sentence->bindParam(':dia', $dia);
        $sentence->bindParam(':mes', $mes);
        $sentence->bindParam(':anio', $anio);
        $sentence->bindParam(':id_dia_semana', $id_dia_semana);
        
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $fecha = Flight::request()->data['fecha'];
        $id_tipo_dia = Flight::request()->data['id_tipo_dia'];
        
        $fecha_obj = new DateTime($fecha);
        $dia = (int)$fecha_obj->format('d');
        $mes = (int)$fecha_obj->format('m');
        $anio = (int)$fecha_obj->format('Y');
        $dia_semana_php = (int)$fecha_obj->format('w');
        $id_dia_semana = self::convertirDiaSemana($dia_semana_php);
        
        $sentence = $db->prepare("update calendarios set fecha = :fecha, id_tipo_dia = :id_tipo_dia, dia = :dia, mes = :mes, anio = :anio, id_dia_semana = :id_dia_semana where id = :id");
        
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':id_tipo_dia', $id_tipo_dia);
        $sentence->bindParam(':dia', $dia);
        $sentence->bindParam(':mes', $mes);
        $sentence->bindParam(':anio', $anio);
        $sentence->bindParam(':id_dia_semana', $id_dia_semana);
        
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from calendarios where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }

    // =============================================
    // MÉTODOS DE CÁLCULO DE TIEMPO HÁBIL
    // Uso interno, no son endpoints
    // =============================================

    /**
     * Carga el calendario hábil con horarios para un rango de fechas.
     * Se llama UNA vez y el resultado se pasa a los métodos de cálculo.
     * Retorna array indexado por fecha: ['2025-01-02' => ['hora_entrada' => '08:00:00', 'hora_salida' => '18:00:00'], ...]
     */
    public static function obtenerCalendarioHabil($fechaInicio, $fechaFin, $db)
    {
        $sentence = $db->prepare("
            SELECT 
                c.fecha,
                ds.hora_entrada,
                ds.hora_salida
            FROM calendarios c
            INNER JOIN dias_semana ds ON ds.id = c.id_dia_semana
            WHERE c.dia_habil = 1
              AND c.fecha BETWEEN :fecha_inicio AND :fecha_fin
            ORDER BY c.fecha
        ");
        $sentence->bindParam(':fecha_inicio', $fechaInicio);
        $sentence->bindParam(':fecha_fin', $fechaFin);
        $sentence->execute();
        $rows = $sentence->fetchAll(PDO::FETCH_ASSOC);

        $calendario = [];
        foreach ($rows as $row) {
            $calendario[$row['fecha']] = [
                'hora_entrada' => $row['hora_entrada'],
                'hora_salida' => $row['hora_salida']
            ];
        }

        return $calendario;
    }

    /**
     * Calcula días hábiles completos entre dos fechas.
     * @param string $fechaInicio formato 'Y-m-d H:i:s'
     * @param string $fechaFin formato 'Y-m-d H:i:s'
     * @param array $calendarioHabil resultado de obtenerCalendarioHabil()
     * @return int cantidad de días hábiles
     */
    public static function calcularDiasHabiles2($fechaInicio, $fechaFin, $calendarioHabil)
    {
        $diaInicio = substr($fechaInicio, 0, 10);
        $diaFin = substr($fechaFin, 0, 10);

        $dias = 0;
        foreach ($calendarioHabil as $fecha => $horario) {
            if ($fecha >= $diaInicio && $fecha <= $diaFin) {
                $dias++;
            }
        }

        return $dias;
    }

    /**
     * Calcula horas hábiles entre dos fechas/horas.
     * Considera horas parciales del primer y último día.
     * @param string $fechaInicio formato 'Y-m-d H:i:s'
     * @param string $fechaFin formato 'Y-m-d H:i:s'
     * @param array $calendarioHabil resultado de obtenerCalendarioHabil()
     * @return float horas hábiles con decimales
     */
    public static function calcularHorasHabiles($fechaInicio, $fechaFin, $calendarioHabil)
    {
        $dtInicio = new DateTime($fechaInicio);
        $dtFin = new DateTime($fechaFin);

        if ($dtFin <= $dtInicio) {
            return 0.0;
        }

        $diaInicio = $dtInicio->format('Y-m-d');
        $diaFin = $dtFin->format('Y-m-d');

        $totalHoras = 0.0;

        foreach ($calendarioHabil as $fecha => $horario) {
            if ($fecha < $diaInicio || $fecha > $diaFin) {
                continue;
            }

            $jornadaInicio = new DateTime($fecha . ' ' . $horario['hora_entrada']);
            $jornadaFin = new DateTime($fecha . ' ' . $horario['hora_salida']);

            // Determinar inicio efectivo para este día
            if ($fecha === $diaInicio) {
                // Primer día: contar desde la hora del registro o desde hora_entrada (lo que sea mayor)
                $efectivoInicio = max($dtInicio, $jornadaInicio);
            } else {
                $efectivoInicio = $jornadaInicio;
            }

            // Determinar fin efectivo para este día
            if ($fecha === $diaFin) {
                // Último día: contar hasta la hora actual o hasta hora_salida (lo que sea menor)
                $efectivoFin = min($dtFin, $jornadaFin);
            } else {
                $efectivoFin = $jornadaFin;
            }

            // Solo sumar si hay tiempo efectivo
            if ($efectivoFin > $efectivoInicio) {
                $diff = $efectivoInicio->diff($efectivoFin);
                $horas = $diff->h + ($diff->i / 60) + ($diff->s / 3600);
                // Si hay días en el diff (no debería pasar pero por seguridad)
                $horas += $diff->days * 24;
                $totalHoras += $horas;
            }
        }

        return round($totalHoras, 2);
    }

    /**
     * Calcula tiempo hábil y devuelve texto legible: "2d 5h", "0d 3h", "5d 0h"
     * @param string $fechaInicio formato 'Y-m-d H:i:s'
     * @param string $fechaFin formato 'Y-m-d H:i:s'
     * @param array $calendarioHabil resultado de obtenerCalendarioHabil()
     * @return array ['texto' => '2d 5h', 'total_horas' => 25.5, 'dias' => 2, 'horas' => 5]
     */
    public static function calcularTiempoHabil($fechaInicio, $fechaFin, $calendarioHabil)
    {
        $totalHoras = self::calcularHorasHabiles($fechaInicio, $fechaFin, $calendarioHabil);

        // Calcular horas de jornada estándar (Lunes-Viernes = 10h)
        $horasJornada = 10;
        $dias = floor($totalHoras / $horasJornada);
        $horasRestantes = floor($totalHoras - ($dias * $horasJornada));

        $texto = $dias . 'd ' . $horasRestantes . 'h';

        return [
            'texto' => $texto,
            'total_horas' => $totalHoras,
            'dias' => (int) $dias,
            'horas' => (int) $horasRestantes
        ];
    }
}