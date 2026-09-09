<?php
/*=============================================
SERVICIO - REGISTRO MASIVO DE ASISTENCIA
Archivo: services/asistencia-masiva.service.php

Arma la grilla de candidatos y procesa el lote de ingresos o de
salidas de una fecha.

Son dos procesos distintos y excluyentes:
  - ingreso: los estudiantes activos que NO tienen un movimiento
    abierto en esa fecha.
  - salida:  los que si lo tienen.
Un estudiante nunca aparece en los dos al tiempo.

A diferencia de la pantalla de asistencia, aqui NO se notifica al
portal de padres: es una carga administrativa, normalmente de dias
anteriores, y no tiene sentido avisarle al acudiente que su hijo
llego hace tres dias.

Los cobros automaticos NO se generan aqui: el front los evalua con
/motor-cobros/evaluar y los ejecuta con /motor-cobros/ejecutar, que
es el mismo camino que usa la pantalla de asistencia. Asi el calculo
es exactamente el mismo en las dos pantallas.
=============================================*/

class AsistenciaMasiva
{
    const TIPO_INGRESO = 'ingreso';
    const TIPO_SALIDA  = 'salida';

    private static function setTimeZone()
    {
        date_default_timezone_set('America/Bogota');
        Flight::db()->exec("SET time_zone = '-05:00'");
    }

    /**
     * Valida que la fecha venga en YYYY-MM-DD. Si no, se usa hoy.
     */
    private static function normalizarFecha($fecha)
    {
        if ($fecha === null || trim($fecha) === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($fecha))) {
            return date('Y-m-d');
        }
        return trim($fecha);
    }

    /**
     * Une fecha y hora en un datetime. Si la hora no sirve, se usa la hora
     * actual, que es el mismo criterio de la pantalla de asistencia.
     */
    private static function construirFechaMovimiento($fecha, $hora)
    {
        $fecha = self::normalizarFecha($fecha);

        if ($hora === null || trim($hora) === '' || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', trim($hora))) {
            return $fecha . ' ' . date('H:i:s');
        }

        $hora = trim($hora);
        if (strlen($hora) === 5) {
            $hora .= ':00';
        }

        return $fecha . ' ' . $hora;
    }

    /**
     * Candidatos del lote.
     * POST /asistencia-masiva/candidatos
     *   { fecha, id_grupo (opcional), tipo: 'ingreso' | 'salida' }
     */
    public static function getCandidatos()
    {
        JWTService::requerirAutenticacion();
        self::setTimeZone();
        $db = Flight::db();

        $data     = Flight::request()->data;
        $fecha    = self::normalizarFecha(isset($data['fecha']) ? $data['fecha'] : null);
        $id_grupo = isset($data['id_grupo']) && $data['id_grupo'] !== '' ? $data['id_grupo'] : null;
        $tipo     = isset($data['tipo']) ? $data['tipo'] : self::TIPO_INGRESO;

        $diaSemana = date('N', strtotime($fecha));

        $filas = $tipo === self::TIPO_SALIDA
            ? self::candidatosSalida($db, $fecha, $id_grupo)
            : self::candidatosIngreso($db, $fecha, $id_grupo);

        // Hora sugerida y utiles, de a un estudiante. Son consultas cortas y
        // la grilla siempre trabaja sobre un grupo, no sobre el jardin entero.
        foreach ($filas as &$fila) {
            $horario = self::horarioDelDia($db, $fila['id_estudiante'], $diaSemana);
            $fila['hora_entrada_programada'] = $horario ? $horario['hora_entrada'] : null;
            $fila['hora_salida_programada']  = $horario ? $horario['hora_salida'] : null;

            $fila['utiles'] = $tipo === self::TIPO_SALIDA
                ? self::utilesQueTrajo($db, $fila['id_estudiante'], $fecha)
                : self::propuestaUtiles($db, $fila['id_estudiante'], $fecha);
        }
        unset($fila);

        Flight::json(array(
            'fecha'        => $fecha,
            'tipo'         => $tipo,
            'candidatos'   => $filas
        ));
    }

    /**
     * Estudiantes activos sin movimiento abierto en la fecha.
     *
     * El que ya entro y salio ese dia vuelve a aparecer: el jardin permite un
     * segundo ingreso el mismo dia (el nino que salio a mediodia y regreso).
     */
    private static function candidatosIngreso($db, $fecha, $id_grupo)
    {
        $sql = "SELECT e.id AS id_estudiante, e.id_persona,
                       p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                       g.id AS id_grupo, g.nombre AS nombre_grupo, g.icono, g.color,
                       (SELECT COUNT(*) FROM asistencia_estudiantes ae
                         WHERE ae.id_estudiante = e.id
                           AND ae.id_tenant = :id_tenant_sub
                           AND DATE(ae.fecha_ingreso) = :fecha_sub) AS movimientos_del_dia
                FROM estudiantes e
                INNER JOIN personas p ON p.id = e.id_persona
                INNER JOIN estudiantes_x_grupos exg ON exg.id_estudiante = e.id AND exg.activo = 1
                INNER JOIN grupos g ON g.id = exg.id_grupo
                WHERE e.id_tenant = :id_tenant
                  AND e.activo = 1
                  AND NOT EXISTS (
                        SELECT 1 FROM asistencia_estudiantes a
                         WHERE a.id_estudiante = e.id
                           AND a.id_tenant = :id_tenant_abierto
                           AND DATE(a.fecha_ingreso) = :fecha_abierto
                           AND a.fecha_salida IS NULL
                  )";

        if ($id_grupo !== null) {
            $sql .= " AND g.id = :id_grupo";
        }

        $sql .= " ORDER BY g.nombre, p.primer_nombre, p.primer_apellido";

        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant_sub', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant_abierto', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':fecha_sub', $fecha);
        $sentence->bindValue(':fecha_abierto', $fecha);
        if ($id_grupo !== null) {
            $sentence->bindValue(':id_grupo', $id_grupo);
        }
        $sentence->execute();

        return $sentence->fetchAll();
    }

    /**
     * Movimientos abiertos en la fecha: son los que se pueden sacar.
     */
    private static function candidatosSalida($db, $fecha, $id_grupo)
    {
        $sql = "SELECT ae.id AS id_asistencia, ae.fecha_ingreso, ae.observacion_ingreso,
                       e.id AS id_estudiante, e.id_persona,
                       p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                       g.id AS id_grupo, g.nombre AS nombre_grupo, g.icono, g.color
                FROM asistencia_estudiantes ae
                INNER JOIN estudiantes e ON e.id = ae.id_estudiante
                INNER JOIN personas p ON p.id = e.id_persona
                INNER JOIN estudiantes_x_grupos exg ON exg.id_estudiante = e.id AND exg.activo = 1
                INNER JOIN grupos g ON g.id = exg.id_grupo
                WHERE ae.id_tenant = :id_tenant
                  AND DATE(ae.fecha_ingreso) = :fecha
                  AND ae.fecha_salida IS NULL";

        if ($id_grupo !== null) {
            $sql .= " AND g.id = :id_grupo";
        }

        $sql .= " ORDER BY g.nombre, p.primer_nombre, p.primer_apellido";

        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':fecha', $fecha);
        if ($id_grupo !== null) {
            $sentence->bindValue(':id_grupo', $id_grupo);
        }
        $sentence->execute();

        return $sentence->fetchAll();
    }

    /**
     * Horario del estudiante para ese dia. Si no tiene el suyo, la jornada
     * del jardin. Es el mismo criterio del motor de cobros, para que la hora
     * que se propone en la grilla sea la que no genera cobro.
     */
    private static function horarioDelDia($db, $id_estudiante, $diaSemana)
    {
        $sentence = $db->prepare("SELECT hora_entrada, hora_salida
                                  FROM horarios_estudiante
                                  WHERE id_estudiante = :id_estudiante
                                    AND id_dia_semana = :dia_semana
                                    AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':dia_semana', $diaSemana);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $horario = $sentence->fetch();

        if ($horario) {
            return $horario;
        }

        $sentence = $db->prepare("SELECT COALESCE(jl.hora_entrada, ds.hora_entrada) AS hora_entrada,
                                         COALESCE(jl.hora_salida, ds.hora_salida)   AS hora_salida
                                  FROM dias_semana ds
                                  LEFT JOIN jornada_laboral jl
                                         ON jl.id_dia_semana = ds.id
                                        AND jl.id_tenant = :id_tenant
                                  WHERE ds.id = :dia_semana");
        $sentence->bindValue(':dia_semana', $diaSemana);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        return $sentence->fetch();
    }

    /**
     * Utiles que se le proponen al estudiante para esa fecha.
     *
     * Si el dia ya esta armado, eso manda. Si no, el catalogo que aplica a su
     * grupo. Es la misma idea de RegistroUtilesDiarios::getPropuesta, pero sin
     * pasar por Flight::json, porque aqui se necesita el arreglo para armar la
     * grilla de varios estudiantes.
     */
    private static function propuestaUtiles($db, $id_estudiante, $fecha)
    {
        $sentence = $db->prepare("SELECT i.id, i.id_util_diario, i.nombre_libre, i.trajo, i.regreso,
                                         COALESCE(u.nombre, i.nombre_libre) AS nombre, u.icono,
                                         COALESCE(u.orden, 999) AS orden, 1 AS existe
                                  FROM utiles_diarios_registro i
                                  LEFT JOIN utiles_diarios u ON u.id = i.id_util_diario
                                  WHERE i.id_tenant = :id_tenant
                                    AND i.id_estudiante = :id_estudiante
                                    AND i.fecha = :fecha
                                  ORDER BY orden, nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':fecha', $fecha);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        if (!empty($filas)) {
            return $filas;
        }

        $sentence = $db->prepare("SELECT NULL AS id, u.id AS id_util_diario, NULL AS nombre_libre,
                                         1 AS trajo, NULL AS regreso,
                                         u.nombre, u.icono, u.orden, 0 AS existe
                                  FROM utiles_diarios u
                                  INNER JOIN estudiantes_x_grupos exg
                                          ON exg.id_estudiante = :id_estudiante AND exg.activo = 1
                                  WHERE u.id_tenant = :id_tenant
                                    AND u.activo = 1
                                    AND (
                                        NOT EXISTS (SELECT 1 FROM utiles_diarios_grupos g WHERE g.id_util_diario = u.id)
                                        OR EXISTS (SELECT 1 FROM utiles_diarios_grupos g WHERE g.id_util_diario = u.id AND g.id_grupo = exg.id_grupo)
                                    )
                                  GROUP BY u.id
                                  ORDER BY u.orden, u.nombre");
        $sentence->bindValue(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        return $sentence->fetchAll();
    }

    /**
     * Lo que el estudiante trajo ese dia. En la salida la grilla marca cuales
     * NO se lleva; el resto se da por devuelto.
     */
    private static function utilesQueTrajo($db, $id_estudiante, $fecha)
    {
        $sentence = $db->prepare("SELECT i.id, i.id_util_diario, i.nombre_libre, i.trajo, i.regreso,
                                         COALESCE(u.nombre, i.nombre_libre) AS nombre, u.icono,
                                         COALESCE(u.orden, 999) AS orden, 1 AS existe
                                  FROM utiles_diarios_registro i
                                  LEFT JOIN utiles_diarios u ON u.id = i.id_util_diario
                                  WHERE i.id_tenant = :id_tenant
                                    AND i.id_estudiante = :id_estudiante
                                    AND i.fecha = :fecha
                                    AND i.trajo = 1
                                  ORDER BY orden, nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':fecha', $fecha);
        $sentence->execute();

        return $sentence->fetchAll();
    }

    /**
     * Procesa el lote.
     * POST /asistencia-masiva/procesar
     *   {
     *     fecha, tipo, id_usuario,
     *     observacion_general (opcional),
     *     filas: [
     *       { id_estudiante, id_asistencia (solo salida), hora,
     *         observacion (opcional),
     *         utiles: [ { id, id_util_diario, nombre_libre, trajo, regreso } ] }
     *     ]
     *   }
     *
     * Devuelve, por fila, el id del movimiento para que el front pueda
     * ejecutar los cobros que la usuaria haya dejado marcados.
     *
     * Cada fila se procesa aparte: si una falla, las demas siguen. Un lote a
     * medias es mejor que perder el trabajo de la usuaria completo.
     */
    public static function procesar()
    {
        JWTService::requerirAutenticacion();
        self::setTimeZone();
        $db = Flight::db();

        $data = Flight::request()->data;

        $fecha       = self::normalizarFecha(isset($data['fecha']) ? $data['fecha'] : null);
        $tipo        = isset($data['tipo']) ? $data['tipo'] : self::TIPO_INGRESO;
        $id_usuario  = isset($data['id_usuario']) ? $data['id_usuario'] : null;
        $observacionGeneral = isset($data['observacion_general']) ? trim($data['observacion_general']) : '';
        $filas       = isset($data['filas']) ? $data['filas'] : array();

        if (!is_array($filas) || count($filas) === 0) {
            Flight::json(array('error' => 'No se recibió ningún estudiante para procesar'), 400);
            return;
        }

        $resultados = array();

        foreach ($filas as $fila) {
            try {
                $observacion = isset($fila['observacion']) && trim($fila['observacion']) !== ''
                    ? trim($fila['observacion'])
                    : $observacionGeneral;

                $resultados[] = $tipo === self::TIPO_SALIDA
                    ? self::procesarSalida($db, $fila, $fecha, $observacion, $id_usuario)
                    : self::procesarIngreso($db, $fila, $fecha, $observacion, $id_usuario);
            } catch (Exception $e) {
                error_log('[AsistenciaMasiva] ' . $e->getMessage());
                $resultados[] = array(
                    'id_estudiante' => isset($fila['id_estudiante']) ? $fila['id_estudiante'] : null,
                    'procesado'     => false,
                    'motivo'        => 'No se pudo procesar'
                );
            }
        }

        $procesados = 0;
        foreach ($resultados as $resultado) {
            if ($resultado['procesado']) {
                $procesados++;
            }
        }

        Flight::json(array(
            'procesados'  => $procesados,
            'total'       => count($filas),
            'resultados'  => $resultados
        ));
    }

    /**
     * Crea el movimiento de ingreso con su observacion y sus utiles.
     * No notifica: es una carga administrativa.
     */
    private static function procesarIngreso($db, $fila, $fecha, $observacion, $id_usuario)
    {
        $id_estudiante = $fila['id_estudiante'];
        $fechaIngreso  = self::construirFechaMovimiento($fecha, isset($fila['hora']) ? $fila['hora'] : null);

        // Se revalida contra la base: entre que se cargo la grilla y se dio
        // procesar, alguien pudo registrarle el ingreso desde la pantalla de
        // asistencia.
        $sentence = $db->prepare("SELECT id FROM asistencia_estudiantes
                                  WHERE id_tenant = :id_tenant
                                    AND id_estudiante = :id_estudiante
                                    AND DATE(fecha_ingreso) = :fecha
                                    AND fecha_salida IS NULL
                                  LIMIT 1");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':fecha', $fecha);
        $sentence->execute();

        if ($sentence->fetch()) {
            return array(
                'id_estudiante' => $id_estudiante,
                'procesado'     => false,
                'motivo'        => 'Ya tiene un ingreso abierto en esa fecha'
            );
        }

        $idNew = Uuid::generar();

        $sentence = $db->prepare("INSERT INTO asistencia_estudiantes
                                  (id, id_tenant, id_estudiante, fecha_ingreso, observacion_ingreso, id_usuario_ingreso)
                                  VALUES (:id, :id_tenant, :id_estudiante, :fecha_ingreso, :observacion, :id_usuario)");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':fecha_ingreso', $fechaIngreso);
        $sentence->bindValue(':observacion', $observacion !== '' ? $observacion : null);
        $sentence->bindValue(':id_usuario', $id_usuario);
        $sentence->execute();

        // La observacion tambien queda en el observador del estudiante, con la
        // fecha del movimiento y no la de hoy: si no, un registro retroactivo
        // caeria en el sprint equivocado.
        $observacionEstudiante = ObservacionesEstudiantes::crearAutomatica(
            $db,
            $id_estudiante,
            'ingreso',
            $observacion !== '' ? 'Observacion de ingreso: ' . $observacion : null,
            $id_usuario,
            $fecha
        );

        $utiles = isset($fila['utiles']) ? $fila['utiles'] : array();
        $utilesCreados = RegistroUtilesDiarios::guardarDesdeAsistencia(
            $db,
            $id_estudiante,
            $fecha,
            $idNew,
            $utiles,
            $id_usuario
        );

        return array(
            'id_estudiante'         => $id_estudiante,
            'id_asistencia'         => $idNew,
            'procesado'             => true,
            'fecha_movimiento'      => $fechaIngreso,
            'utiles_creados'        => $utilesCreados,
            'observacion_estudiante'=> $observacionEstudiante
        );
    }

    /**
     * Cierra el movimiento con la hora de salida y marca el regreso de los
     * utiles. Tampoco notifica.
     */
    private static function procesarSalida($db, $fila, $fecha, $observacion, $id_usuario)
    {
        $id_asistencia = isset($fila['id_asistencia']) ? $fila['id_asistencia'] : null;
        $id_estudiante = $fila['id_estudiante'];

        if (empty($id_asistencia)) {
            return array(
                'id_estudiante' => $id_estudiante,
                'procesado'     => false,
                'motivo'        => 'La fila no trae el movimiento a cerrar'
            );
        }

        $fechaSalida = self::construirFechaMovimiento($fecha, isset($fila['hora']) ? $fila['hora'] : null);

        // La salida no puede quedar antes del ingreso.
        $sentence = $db->prepare("SELECT fecha_ingreso, fecha_salida
                                  FROM asistencia_estudiantes
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id', $id_asistencia);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $movimiento = $sentence->fetch();

        if (!$movimiento) {
            return array(
                'id_estudiante' => $id_estudiante,
                'procesado'     => false,
                'motivo'        => 'El movimiento ya no existe'
            );
        }

        if (!empty($movimiento['fecha_salida'])) {
            return array(
                'id_estudiante' => $id_estudiante,
                'procesado'     => false,
                'motivo'        => 'La salida ya estaba registrada'
            );
        }

        if (strtotime($fechaSalida) < strtotime($movimiento['fecha_ingreso'])) {
            return array(
                'id_estudiante' => $id_estudiante,
                'procesado'     => false,
                'motivo'        => 'La hora de salida es anterior a la de ingreso'
            );
        }

        $sentence = $db->prepare("UPDATE asistencia_estudiantes
                                  SET fecha_salida = :fecha_salida,
                                      observacion_salida = :observacion,
                                      id_usuario_salida = :id_usuario
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':fecha_salida', $fechaSalida);
        $sentence->bindValue(':observacion', $observacion !== '' ? $observacion : null);
        $sentence->bindValue(':id_usuario', $id_usuario);
        $sentence->bindValue(':id', $id_asistencia);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $observacionEstudiante = ObservacionesEstudiantes::crearAutomatica(
            $db,
            $id_estudiante,
            'salida',
            $observacion !== '' ? 'Observacion de salida: ' . $observacion : null,
            $id_usuario,
            $fecha
        );

        // Los ids que la usuaria marco como "no se lo lleva".
        $noRegresaron = array();
        if (isset($fila['utiles']) && is_array($fila['utiles'])) {
            foreach ($fila['utiles'] as $util) {
                $regreso = isset($util['regreso']) ? $util['regreso'] : 1;
                if (!empty($util['id']) && ($regreso === 0 || $regreso === '0' || $regreso === false)) {
                    $noRegresaron[] = $util['id'];
                }
            }
        }

        $utilesMarcados = RegistroUtilesDiarios::registrarSalidaEstudiante(
            $db,
            $id_estudiante,
            $fecha,
            $noRegresaron,
            $id_usuario
        );

        return array(
            'id_estudiante'          => $id_estudiante,
            'id_asistencia'          => $id_asistencia,
            'procesado'              => true,
            'fecha_movimiento'       => $fechaSalida,
            'utiles_marcados'        => $utilesMarcados,
            'observacion_estudiante' => $observacionEstudiante
        );
    }
}
