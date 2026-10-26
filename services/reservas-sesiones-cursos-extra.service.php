<?php
/**
 * Reservas de sesiones de cursos extra: el puesto de un estudiante en una
 * sesion.
 *
 * Que se puede hacer depende de las reglas del curso
 * (configuracion_reservas_cursos_extra):
 *   - Reservar una sesion suelta: permite_reserva_suelta, o curso con reserva
 *     automatica (el inscrito ya deberia estar y solo se completa). Desde la
 *     clase del dia (en_clase) se puede agregar a un inscrito que llego sin
 *     reserva, siempre que haya cupo.
 *   - Cancelar: permite_cancelar.
 *   - Reagendar a otra sesion del mismo curso: permite_reagendar.
 * La asistencia (ASISTIO / NO_ASISTIO) se registra con la sesion, en
 * SesionesCursosExtra::registrarAsistencia.
 */
class ReservasSesionesCursosExtra
{
    public static function getBySesion($id_sesion_curso_extra)
    {
        $db = Flight::db();
        $response = self::listarPorSesion($db, $id_sesion_curso_extra);
        Flight::json($response);
    }

    /**
     * Historial de reservas de una inscripcion, la mas reciente primero.
     */
    public static function getByInscripcion($id_estudiante_x_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT r.id, r.id_sesion_curso_extra, r.id_estado_reserva_curso_extra, er.codigo AS codigo_estado,
                   er.nombre AS nombre_estado, r.id_reserva_fija_curso_extra, r.id_reserva_origen,
                   r.id_paquete_estudiante_curso_extra, r.observacion, r.fecha_cancelacion,
                   s.fecha, s.hora_inicial, s.hora_final, es.codigo AS codigo_estado_sesion
            FROM reservas_sesiones_cursos_extra r
            INNER JOIN estados_reservas_cursos_extra er ON r.id_estado_reserva_curso_extra = er.id
            INNER JOIN sesiones_cursos_extra s ON r.id_sesion_curso_extra = s.id
            INNER JOIN estados_sesiones_cursos_extra es ON s.id_estado_sesion_curso_extra = es.id
            WHERE r.id_estudiante_x_curso_extra = :id_inscripcion AND r.id_tenant = :id_tenant
            ORDER BY s.fecha DESC, s.hora_inicial DESC
        ");
        $sentence->bindParam(':id_inscripcion', $id_estudiante_x_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Reservar un puesto en una sesion.
     * Body: { id_sesion_curso_extra, id_estudiante_x_curso_extra, en_clase? }
     */
    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id_sesion = $data['id_sesion_curso_extra'];
        $id_inscripcion = $data['id_estudiante_x_curso_extra'];
        $en_clase = !empty($data['en_clase']);

        $sesion = SesionesCursosExtra::obtenerSesion($db, $id_sesion);
        if (!$sesion) {
            Flight::json(array('error' => 'La sesión no existe.'), 404);
            return;
        }
        if ($sesion['codigo_estado'] === 'CANCELADA') {
            Flight::json(array('error' => 'La sesión está cancelada.'), 400);
            return;
        }
        if ($sesion['codigo_estado'] === 'DICTADA' && !$en_clase) {
            Flight::json(array('error' => 'La sesión ya se dictó: el estudiante se agrega desde Clases del Día.'), 400);
            return;
        }

        $reglas = ConfiguracionReservasCursosExtra::obtener($db, $sesion['id_curso_extra']);
        if (!$en_clase && !$reglas['permite_reserva_suelta'] && !$reglas['reserva_automatica']) {
            Flight::json(array('error' => 'Este curso no permite reservar sesiones sueltas. Usa una reserva fija.'), 400);
            return;
        }

        $error = self::validarInscripcion($db, $id_inscripcion, $sesion);
        if ($error !== null) {
            Flight::json(array('error' => $error), 400);
            return;
        }

        $resultado = self::reservar($db, $sesion, $id_inscripcion, null, null);
        if (isset($resultado['error'])) {
            Flight::json(array('error' => $resultado['error']), 400);
            return;
        }

        Flight::json(array('id' => $resultado['id']));
    }

    /**
     * Cancelar una reserva. El puesto queda libre.
     * Body: { id }
     */
    public static function cancelar()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $reserva = self::obtenerReserva($db, $id);
        if (!$reserva) {
            Flight::json(array('error' => 'La reserva no existe.'), 404);
            return;
        }

        $reglas = ConfiguracionReservasCursosExtra::obtener($db, $reserva['id_curso_extra']);
        if (!$reglas['permite_cancelar']) {
            Flight::json(array('error' => 'Este curso no permite cancelar reservas.'), 400);
            return;
        }
        if ($reserva['codigo_estado'] !== 'RESERVADA') {
            Flight::json(array('error' => 'Solo se puede cancelar una reserva que esté reservada.'), 400);
            return;
        }

        self::cambiarEstado($db, $id, 'CANCELADA', true);
        Flight::json(array('id' => $id));
    }

    /**
     * Mover una reserva a otra sesion del mismo curso.
     * Body: { id, id_sesion_destino }
     */
    public static function reagendar()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        $id = $data['id'];
        $id_sesion_destino = $data['id_sesion_destino'];

        $reserva = self::obtenerReserva($db, $id);
        if (!$reserva) {
            Flight::json(array('error' => 'La reserva no existe.'), 404);
            return;
        }

        $reglas = ConfiguracionReservasCursosExtra::obtener($db, $reserva['id_curso_extra']);
        if (!$reglas['permite_reagendar']) {
            Flight::json(array('error' => 'Este curso no permite reagendar reservas.'), 400);
            return;
        }
        if ($reserva['codigo_estado'] !== 'RESERVADA') {
            Flight::json(array('error' => 'Solo se puede reagendar una reserva que esté reservada.'), 400);
            return;
        }

        $destino = SesionesCursosExtra::obtenerSesion($db, $id_sesion_destino);
        if (!$destino) {
            Flight::json(array('error' => 'La sesión de destino no existe.'), 404);
            return;
        }
        if ($destino['id'] === $reserva['id_sesion_curso_extra']) {
            Flight::json(array('error' => 'La sesión de destino es la misma de la reserva.'), 400);
            return;
        }
        if ($destino['id_curso_extra'] !== $reserva['id_curso_extra']) {
            Flight::json(array('error' => 'Solo se puede reagendar a otra sesión del mismo curso.'), 400);
            return;
        }
        if ($destino['codigo_estado'] !== 'PROGRAMADA') {
            Flight::json(array('error' => 'La sesión de destino no está programada.'), 400);
            return;
        }

        $error = self::validarInscripcion($db, $reserva['id_estudiante_x_curso_extra'], $destino);
        if ($error !== null) {
            Flight::json(array('error' => $error), 400);
            return;
        }

        try {
            $db->beginTransaction();

            $resultado = self::reservar($db, $destino, $reserva['id_estudiante_x_curso_extra'], null, $id);
            if (isset($resultado['error'])) {
                $db->rollBack();
                Flight::json(array('error' => $resultado['error']), 400);
                return;
            }
            self::cambiarEstado($db, $id, 'REAGENDADA', true);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al reagendar la reserva: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo reagendar la reserva.'), 500);
            return;
        }

        Flight::json(array('id' => $resultado['id']));
    }

    // ==================== USO DESDE OTROS SERVICIOS ====================

    /**
     * Reservas de una sesion con el nombre del estudiante y sus sesiones
     * disponibles en la fecha de la sesion.
     */
    public static function listarPorSesion($db, $id_sesion_curso_extra)
    {
        $sentence = $db->prepare("
            SELECT r.id, r.id_sesion_curso_extra, r.id_estudiante_x_curso_extra, r.id_estado_reserva_curso_extra,
                   er.codigo AS codigo_estado, er.nombre AS nombre_estado,
                   r.id_reserva_fija_curso_extra, r.id_reserva_origen, r.id_paquete_estudiante_curso_extra,
                   r.observacion, r.fecha_registro, r.fecha_cancelacion,
                   exce.id_estudiante,
                   TRIM(REGEXP_REPLACE(CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                        IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')), '\\\\s+', ' ')) AS nombre_estudiante,
                   s.fecha
            FROM reservas_sesiones_cursos_extra r
            INNER JOIN estados_reservas_cursos_extra er ON r.id_estado_reserva_curso_extra = er.id
            INNER JOIN sesiones_cursos_extra s ON r.id_sesion_curso_extra = s.id
            INNER JOIN estudiantes_x_cursos_extra exce ON r.id_estudiante_x_curso_extra = exce.id
            INNER JOIN estudiantes e ON exce.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            WHERE r.id_sesion_curso_extra = :id_sesion AND r.id_tenant = :id_tenant
            ORDER BY er.orden, p.primer_nombre, p.primer_apellido
        ");
        $sentence->bindParam(':id_sesion', $id_sesion_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        foreach ($filas as &$fila) {
            $fila['sesiones_disponibles'] = PaquetesEstudiantesCursosExtra::disponibles($db, $fila['id_estudiante_x_curso_extra'], $fila['fecha']);
        }
        unset($fila);

        return $filas;
    }

    /**
     * Pone a un estudiante en una sesion. Si ya tenia una fila cancelada o
     * reagendada en esa sesion, la reactiva (la llave unica es sesion +
     * inscripcion). Valida el cupo salvo que el curso permita sobrecupo.
     *
     * @return array ['id' => ...] o ['error' => ...]
     */
    public static function reservar($db, $sesion, $id_inscripcion, $id_reserva_fija, $id_reserva_origen)
    {
        $existente = $db->prepare("
            SELECT r.id, er.codigo
            FROM reservas_sesiones_cursos_extra r
            INNER JOIN estados_reservas_cursos_extra er ON r.id_estado_reserva_curso_extra = er.id
            WHERE r.id_sesion_curso_extra = :id_sesion AND r.id_estudiante_x_curso_extra = :id_inscripcion
              AND r.id_tenant = :id_tenant
        ");
        $existente->bindValue(':id_sesion', $sesion['id']);
        $existente->bindValue(':id_inscripcion', $id_inscripcion);
        $existente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $existente->execute();
        $fila = $existente->fetch();

        if ($fila && in_array($fila['codigo'], EstadosReservasCursosExtra::OCUPAN_CUPO)) {
            return array('error' => 'El estudiante ya tiene un puesto en esa sesión.');
        }

        if (!self::hayCupo($db, $sesion)) {
            return array('error' => 'La sesión no tiene cupo disponible.');
        }

        $idReservada = EstadosReservasCursosExtra::idPorCodigo($db, 'RESERVADA');

        if ($fila) {
            $sentence = $db->prepare("
                UPDATE reservas_sesiones_cursos_extra SET
                    id_estado_reserva_curso_extra = :id_estado,
                    id_reserva_fija_curso_extra = :id_fija,
                    id_reserva_origen = :id_origen,
                    id_paquete_estudiante_curso_extra = NULL,
                    observacion = NULL,
                    fecha_cancelacion = NULL
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindValue(':id', $fila['id']);
            $id = $fila['id'];
        } else {
            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO reservas_sesiones_cursos_extra
                    (id, id_tenant, id_sesion_curso_extra, id_estudiante_x_curso_extra, id_estado_reserva_curso_extra,
                     id_reserva_fija_curso_extra, id_reserva_origen)
                VALUES
                    (:id, :id_tenant, :id_sesion, :id_inscripcion, :id_estado, :id_fija, :id_origen)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_sesion', $sesion['id']);
            $sentence->bindValue(':id_inscripcion', $id_inscripcion);
        }
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_estado', $idReservada);
        $sentence->bindValue(':id_fija', $id_reserva_fija);
        $sentence->bindValue(':id_origen', $id_reserva_origen);
        $sentence->execute();

        return array('id' => $id);
    }

    /**
     * Cambia el estado de una reserva. Los estados que no consumen sueltan el
     * paquete; con $marcarCancelacion queda la fecha en que se libero.
     */
    public static function cambiarEstado($db, $id, $codigo, $marcarCancelacion = false, $observacion = null)
    {
        $idEstado = EstadosReservasCursosExtra::idPorCodigo($db, $codigo);
        $sentence = $db->prepare("
            UPDATE reservas_sesiones_cursos_extra SET
                id_estado_reserva_curso_extra = :id_estado,
                id_paquete_estudiante_curso_extra = NULL,
                fecha_cancelacion = " . ($marcarCancelacion ? "NOW()" : "fecha_cancelacion") . ",
                observacion = IFNULL(:observacion, observacion)
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindValue(':id_estado', $idEstado);
        $sentence->bindValue(':observacion', $observacion);
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    /**
     * Hay cupo si la sesion no tiene limite, si el curso permite sobrecupo o
     * si los puestos ocupados (reservada, asistio, no asistio) no llegan al
     * cupo.
     */
    public static function hayCupo($db, $sesion)
    {
        if ($sesion['cupo'] === null || $sesion['cupo'] === '' || !empty($sesion['permite_sobrecupo'])) {
            return true;
        }
        return SesionesCursosExtra::contarOcupados($db, $sesion['id']) < (int) $sesion['cupo'];
    }

    /**
     * La inscripcion debe ser del mismo curso y estar vigente en la fecha de
     * la sesion.
     *
     * @return string|null Mensaje de error, o null si sirve.
     */
    public static function validarInscripcion($db, $id_inscripcion, $sesion)
    {
        $sentence = $db->prepare("
            SELECT id_curso_extra, activo, fecha_inscripcion, fecha_retiro
            FROM estudiantes_x_cursos_extra
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id_inscripcion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $inscripcion = $sentence->fetch();

        if (!$inscripcion) {
            return 'La inscripción del estudiante no existe.';
        }
        if ($inscripcion['id_curso_extra'] !== $sesion['id_curso_extra']) {
            return 'El estudiante no está inscrito en el curso de la sesión.';
        }
        if (!$inscripcion['activo']) {
            return 'La inscripción del estudiante no está activa.';
        }
        if ($inscripcion['fecha_retiro'] && $inscripcion['fecha_retiro'] <= $sesion['fecha']) {
            return 'El estudiante se retiró del curso antes de la fecha de la sesión.';
        }
        return null;
    }

    private static function obtenerReserva($db, $id)
    {
        $sentence = $db->prepare("
            SELECT r.id, r.id_sesion_curso_extra, r.id_estudiante_x_curso_extra, er.codigo AS codigo_estado,
                   s.id_curso_extra, s.fecha
            FROM reservas_sesiones_cursos_extra r
            INNER JOIN estados_reservas_cursos_extra er ON r.id_estado_reserva_curso_extra = er.id
            INNER JOIN sesiones_cursos_extra s ON r.id_sesion_curso_extra = s.id
            WHERE r.id = :id AND r.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? $fila : null;
    }
}
