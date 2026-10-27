<?php
/**
 * Sesiones de cursos extra: la clase de un curso en un dia y hora, con su
 * espacio, sus profesores y su cupo.
 *
 * Es el centro de la asistencia de los extracurriculares. De una sesion
 * dictada salen a la vez la asistencia de los estudiantes (sus reservas), las
 * horas del profesor (docentes_x_sesiones) y el consumo del paquete de
 * sesiones (la reserva toma el paquete al registrar la asistencia).
 *
 * Las sesiones de un horario no se crean por adelantado: getAll las calcula
 * desde los horarios y la fila nace la primera vez que alguien abre la sesion
 * (materializar). Una sesion suelta, que no sale de ningun horario, se crea
 * con new().
 *
 * Las reglas de reserva de cada curso estan en
 * ConfiguracionReservasCursosExtra; un curso sin configurar funciona como
 * fijo.
 */
class SesionesCursosExtra
{
    // Rango maximo que se consulta de una vez en la agenda.
    const MAX_DIAS_AGENDA = 62;

    /**
     * Sesiones de un rango de fechas: las guardadas y las de los horarios
     * que todavia no tienen fila (llave 'virtual' = 1, id nulo).
     *
     * Una sesion de horario no se crea por adelantado: la agenda la calcula
     * desde el horario y solo se guarda cuando se abre (materializar). Para
     * las virtuales el cupo ocupado se calcula igual que se pondrian las
     * reservas: en un curso con reserva automatica, los inscritos vigentes en
     * la fecha; si no, las reservas fijas vigentes de ese horario.
     *
     * Query: fecha_desde, fecha_hasta (por defecto hoy y seis dias despues),
     *        id_curso_extra, id_docente (opcionales).
     */
    public static function getAll()
    {
        $db = Flight::db();
        $query = Flight::request()->query;

        $fecha_desde = !empty($query['fecha_desde']) ? $query['fecha_desde'] : date('Y-m-d');
        $fecha_hasta = !empty($query['fecha_hasta']) ? $query['fecha_hasta'] : date('Y-m-d', strtotime($fecha_desde . ' +6 days'));
        $id_curso_extra = !empty($query['id_curso_extra']) ? $query['id_curso_extra'] : null;
        $id_docente = !empty($query['id_docente']) ? $query['id_docente'] : null;

        if ($fecha_hasta < $fecha_desde) {
            Flight::json(array('error' => 'La fecha hasta no puede ser anterior a la fecha desde.'), 400);
            return;
        }
        $dias = (new DateTime($fecha_desde))->diff(new DateTime($fecha_hasta))->days + 1;
        if ($dias > self::MAX_DIAS_AGENDA) {
            Flight::json(array('error' => 'Se pueden consultar máximo ' . self::MAX_DIAS_AGENDA . ' días de una vez.'), 400);
            return;
        }

        $sentence = $db->prepare(self::consultaBase() . "
            WHERE s.id_tenant = :id_tenant
              AND s.fecha BETWEEN :fecha_desde AND :fecha_hasta
              AND (:id_curso IS NULL OR s.id_curso_extra = :id_curso_2)
              AND (:id_docente IS NULL OR EXISTS (
                    SELECT 1 FROM docentes_x_sesiones_cursos_extra dxs
                    WHERE dxs.id_sesion_curso_extra = s.id AND dxs.id_docente = :id_docente_2))
            ORDER BY s.fecha, s.hora_inicial, ce.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':fecha_desde', $fecha_desde);
        $sentence->bindValue(':fecha_hasta', $fecha_hasta);
        $sentence->bindValue(':id_curso', $id_curso_extra);
        $sentence->bindValue(':id_curso_2', $id_curso_extra);
        $sentence->bindValue(':id_docente', $id_docente);
        $sentence->bindValue(':id_docente_2', $id_docente);
        $sentence->execute();
        $guardadas = $sentence->fetchAll();

        // Las de horario que ya tienen fila no se calculan otra vez, aunque se
        // hayan movido de fecha o no sean del profesor del filtro.
        $existentes = self::horariosConSesion($db, $fecha_desde, $fecha_hasta);
        foreach ($guardadas as &$s) {
            $s['virtual'] = 0;
        }
        unset($s);

        $virtuales = self::sesionesVirtuales($db, $fecha_desde, $fecha_hasta, $id_curso_extra, $id_docente, $existentes);
        $response = array_merge($guardadas, $virtuales);
        usort($response, function ($a, $b) {
            return strcmp($a['fecha'] . $a['hora_inicial'] . $a['nombre_curso'], $b['fecha'] . $b['hora_inicial'] . $b['nombre_curso']);
        });

        Flight::json($response);
    }

    /**
     * Una sesion con sus profesores (llave 'docentes'), sus reservas (llave
     * 'reservas') y las reglas de reserva del curso (llave 'reglas').
     */
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::consultaBase() . "
            WHERE s.id = :id AND s.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$sesion) {
            $sesion['docentes'] = DocentesXSesionesCursosExtra::listar($db, $sesion['id']);
            $sesion['reservas'] = ReservasSesionesCursosExtra::listarPorSesion($db, $sesion['id']);
            $sesion['reglas'] = ConfiguracionReservasCursosExtra::obtener($db, $sesion['id_curso_extra']);
        }
        unset($sesion);

        Flight::json($response);
    }

    /**
     * Docente del usuario que esta en sesion, para el filtro "Solo mis
     * clases". Devuelve { id_docente } con null si el usuario no es docente.
     */
    public static function getMiDocente()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $sentence = $db->prepare("
            SELECT id FROM docentes
            WHERE id_persona = :id_persona AND id_tenant = :id_tenant AND activo = 1
            LIMIT 1
        ");
        $sentence->bindValue(':id_persona', $userData->id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        Flight::json(array('id_docente' => $fila ? $fila['id'] : null));
    }

    /**
     * Devuelve el id de la sesion de un horario en una fecha y, si todavia no
     * tiene fila, la crea. Es el momento en que una sesion de horario empieza
     * a existir: cuando alguien la abre para reservar, tomar asistencia,
     * cancelarla o editarla.
     *
     * Al crearla le pone el espacio, el cupo y el profesor del horario (o el
     * titular del curso) y las reservas que tocan:
     *   - Curso con reserva automatica: todos los inscritos vigentes.
     *   - Curso sin reserva automatica: los que tienen reserva fija vigente
     *     en ese horario, mientras haya cupo.
     * Si ya existe y sigue programada, le completa las reservas que le falten
     * (por ejemplo de un inscrito nuevo). Las canceladas no se reviven.
     * Body: { id_horario_curso_extra, fecha }
     */
    public static function materializar()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id_horario = isset($data['id_horario_curso_extra']) ? $data['id_horario_curso_extra'] : null;
        $fecha = isset($data['fecha']) ? $data['fecha'] : '';

        $horario = $db->prepare("
            SELECT hce.id, hce.id_curso_extra, hce.id_dia_semana, hce.hora_inicial, hce.hora_final, hce.total_minutos,
                   hce.id_espacio_lugar_curso_extra, hce.id_docente, hce.cupo,
                   ce.cupo_maximo, ce.fecha_inicio, ce.fecha_fin, ce.activo
            FROM horarios_cursos_extra hce
            INNER JOIN cursos_extra ce ON hce.id_curso_extra = ce.id
            WHERE hce.id = :id AND hce.id_tenant = :id_tenant
        ");
        $horario->bindValue(':id', $id_horario);
        $horario->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $horario->execute();
        $h = $horario->fetch();

        if (!$h) {
            Flight::json(array('error' => 'El horario no existe.'), 404);
            return;
        }
        if ($fecha === '' || (int) date('N', strtotime($fecha)) !== (int) $h['id_dia_semana']) {
            Flight::json(array('error' => 'La fecha no corresponde al día del horario.'), 400);
            return;
        }

        $idSesion = self::idSesionDeHorario($db, $h['id'], $fecha);
        // Un curso sin fechas es permanente: solo se valida la fecha que tenga.
        $fueraDeFechas = ($h['fecha_inicio'] !== null && $fecha < $h['fecha_inicio'])
            || ($h['fecha_fin'] !== null && $fecha > $h['fecha_fin']);
        if ($idSesion === null && (!$h['activo'] || $fueraDeFechas)) {
            Flight::json(array('error' => 'El curso no está activo en esa fecha.'), 400);
            return;
        }

        try {
            $db->beginTransaction();

            if ($idSesion === null) {
                $idSesion = Uuid::generar();
                $cupo = $h['cupo'] !== null ? $h['cupo'] : $h['cupo_maximo'];
                $insertar = $db->prepare("
                    INSERT INTO sesiones_cursos_extra
                        (id, id_tenant, id_curso_extra, id_horario_curso_extra, fecha, hora_inicial, hora_final,
                         id_espacio_lugar_curso_extra, cupo, id_estado_sesion_curso_extra)
                    VALUES
                        (:id, :id_tenant, :id_curso, :id_horario, :fecha, :hora_inicial, :hora_final,
                         :id_espacio, :cupo, :id_estado)
                ");
                $insertar->bindValue(':id', $idSesion);
                $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $insertar->bindValue(':id_curso', $h['id_curso_extra']);
                $insertar->bindValue(':id_horario', $h['id']);
                $insertar->bindValue(':fecha', $fecha);
                $insertar->bindValue(':hora_inicial', $h['hora_inicial']);
                $insertar->bindValue(':hora_final', $h['hora_final']);
                $insertar->bindValue(':id_espacio', $h['id_espacio_lugar_curso_extra']);
                $insertar->bindValue(':cupo', $cupo);
                $insertar->bindValue(':id_estado', EstadosSesionesCursosExtra::idPorCodigo($db, 'PROGRAMADA'));
                $insertar->execute();

                // Profesor: el habitual del horario o, si no tiene, el titular
                // del curso.
                $idDocente = $h['id_docente'] ? $h['id_docente'] : self::idTitularCurso($db, $h['id_curso_extra']);
                if ($idDocente) {
                    DocentesXSesionesCursosExtra::sincronizar($db, $idSesion, array(
                        array('id_docente' => $idDocente, 'es_titular' => 1, 'minutos' => $h['total_minutos'])
                    ));
                }
            }

            $sesion = self::obtenerSesion($db, $idSesion);
            if ($sesion['codigo_estado'] === 'PROGRAMADA') {
                self::completarReservas($db, $sesion, ConfiguracionReservasCursosExtra::obtener($db, $sesion['id_curso_extra']));
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // Si otra persona la creo al mismo tiempo, la llave unica de
            // horario + fecha lo frena: se devuelve la que quedo.
            $idExistente = self::idSesionDeHorario($db, $id_horario, $fecha);
            if ($idExistente !== null) {
                Flight::json(array('id' => $idExistente));
                return;
            }
            error_log("Error al crear la sesión del horario: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo abrir la sesión.'), 500);
            return;
        }

        Flight::json(array('id' => $idSesion));
    }

    /**
     * Crea una sesion suelta (que no sale de ningun horario), por ejemplo una
     * clase individual. Puede llegar con los estudiantes que la toman.
     * Body: { id_curso_extra, fecha, hora_inicial, hora_final, id_espacio_lugar_curso_extra?,
     *         cupo?, observaciones?, docentes: [{id_docente, minutos, es_titular}],
     *         estudiantes: [id_estudiante_x_curso_extra] }
     */
    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id_curso_extra = isset($data['id_curso_extra']) ? $data['id_curso_extra'] : null;
        $fecha = isset($data['fecha']) ? $data['fecha'] : '';
        $hora_inicial = isset($data['hora_inicial']) ? $data['hora_inicial'] : '';
        $hora_final = isset($data['hora_final']) ? $data['hora_final'] : '';
        $id_espacio = !empty($data['id_espacio_lugar_curso_extra']) ? $data['id_espacio_lugar_curso_extra'] : null;
        $observaciones = isset($data['observaciones']) && $data['observaciones'] !== '' ? $data['observaciones'] : null;
        $docentes = isset($data['docentes']) ? $data['docentes'] : array();
        $estudiantes = isset($data['estudiantes']) ? $data['estudiantes'] : array();

        $error = self::validarDatos($id_curso_extra, $fecha, $hora_inicial, $hora_final);
        if ($error !== null) {
            Flight::json(array('error' => $error), 400);
            return;
        }

        $curso = $db->prepare("SELECT cupo_maximo FROM cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
        $curso->bindParam(':id', $id_curso_extra);
        $curso->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $curso->execute();
        $filaCurso = $curso->fetch();
        if (!$filaCurso) {
            Flight::json(array('error' => 'El curso no existe.'), 400);
            return;
        }
        $cupo = isset($data['cupo']) && $data['cupo'] !== '' && $data['cupo'] !== null ? (int) $data['cupo'] : $filaCurso['cupo_maximo'];

        $id = Uuid::generar();
        try {
            $db->beginTransaction();

            $sentence = $db->prepare("
                INSERT INTO sesiones_cursos_extra
                    (id, id_tenant, id_curso_extra, id_horario_curso_extra, fecha, hora_inicial, hora_final,
                     id_espacio_lugar_curso_extra, cupo, id_estado_sesion_curso_extra, observaciones)
                VALUES
                    (:id, :id_tenant, :id_curso, NULL, :fecha, :hora_inicial, :hora_final,
                     :id_espacio, :cupo, :id_estado, :observaciones)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_curso', $id_curso_extra);
            $sentence->bindValue(':fecha', $fecha);
            $sentence->bindValue(':hora_inicial', $hora_inicial);
            $sentence->bindValue(':hora_final', $hora_final);
            $sentence->bindValue(':id_espacio', $id_espacio);
            $sentence->bindValue(':cupo', $cupo);
            $sentence->bindValue(':id_estado', EstadosSesionesCursosExtra::idPorCodigo($db, 'PROGRAMADA'));
            $sentence->bindValue(':observaciones', $observaciones);
            $sentence->execute();

            $error = DocentesXSesionesCursosExtra::sincronizar($db, $id, $docentes);
            if ($error !== null) {
                $db->rollBack();
                Flight::json(array('error' => $error), 400);
                return;
            }

            $sesion = self::obtenerSesion($db, $id);
            foreach ($estudiantes as $id_inscripcion) {
                $error = ReservasSesionesCursosExtra::validarInscripcion($db, $id_inscripcion, $sesion);
                if ($error === null) {
                    $resultado = ReservasSesionesCursosExtra::reservar($db, $sesion, $id_inscripcion, null, null);
                    $error = isset($resultado['error']) ? $resultado['error'] : null;
                }
                if ($error !== null) {
                    $db->rollBack();
                    Flight::json(array('error' => $error), 400);
                    return;
                }
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al crear la sesión: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo crear la sesión.'), 500);
            return;
        }

        Flight::json(array('id' => $id));
    }

    /**
     * Edita la fecha, la hora, el espacio, el cupo, las observaciones y, si
     * llegan, los profesores. El cupo no puede quedar por debajo de los
     * puestos ocupados.
     */
    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id = $data['id'];
        $sesion = self::obtenerSesion($db, $id);
        if (!$sesion) {
            Flight::json(array('error' => 'La sesión no existe.'), 404);
            return;
        }

        $fecha = isset($data['fecha']) ? $data['fecha'] : $sesion['fecha'];
        $hora_inicial = isset($data['hora_inicial']) ? $data['hora_inicial'] : $sesion['hora_inicial'];
        $hora_final = isset($data['hora_final']) ? $data['hora_final'] : $sesion['hora_final'];
        $id_espacio = array_key_exists('id_espacio_lugar_curso_extra', $data->getData())
            ? ($data['id_espacio_lugar_curso_extra'] ? $data['id_espacio_lugar_curso_extra'] : null)
            : $sesion['id_espacio_lugar_curso_extra'];
        $cupo = array_key_exists('cupo', $data->getData())
            ? ($data['cupo'] !== '' && $data['cupo'] !== null ? (int) $data['cupo'] : null)
            : $sesion['cupo'];
        $observaciones = array_key_exists('observaciones', $data->getData())
            ? ($data['observaciones'] !== '' ? $data['observaciones'] : null)
            : $sesion['observaciones'];

        $error = self::validarDatos($sesion['id_curso_extra'], $fecha, $hora_inicial, $hora_final);
        if ($error !== null) {
            Flight::json(array('error' => $error), 400);
            return;
        }
        $ocupados = self::contarOcupados($db, $id);
        if ($cupo !== null && $cupo < $ocupados && empty($sesion['permite_sobrecupo'])) {
            Flight::json(array('error' => 'El cupo no puede quedar por debajo de los ' . $ocupados . ' puestos ocupados.'), 400);
            return;
        }
        if ($fecha !== $sesion['fecha'] && $sesion['id_horario_curso_extra']) {
            // La sesion de un horario es unica por fecha: si se mueve a una fecha
            // donde ese horario ya tiene sesion, chocan.
            $otra = self::idSesionDeHorario($db, $sesion['id_horario_curso_extra'], $fecha);
            if ($otra !== null && $otra !== $id) {
                Flight::json(array('error' => 'Ese horario ya tiene una sesión en la fecha ' . $fecha . '.'), 400);
                return;
            }
        }

        try {
            $db->beginTransaction();

            $sentence = $db->prepare("
                UPDATE sesiones_cursos_extra SET
                    fecha = :fecha,
                    hora_inicial = :hora_inicial,
                    hora_final = :hora_final,
                    id_espacio_lugar_curso_extra = :id_espacio,
                    cupo = :cupo,
                    observaciones = :observaciones
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindValue(':fecha', $fecha);
            $sentence->bindValue(':hora_inicial', $hora_inicial);
            $sentence->bindValue(':hora_final', $hora_final);
            $sentence->bindValue(':id_espacio', $id_espacio);
            $sentence->bindValue(':cupo', $cupo);
            $sentence->bindValue(':observaciones', $observaciones);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if (isset($data['docentes'])) {
                $error = DocentesXSesionesCursosExtra::sincronizar($db, $id, $data['docentes']);
                if ($error !== null) {
                    $db->rollBack();
                    Flight::json(array('error' => $error), 400);
                    return;
                }
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al actualizar la sesión: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo actualizar la sesión.'), 500);
            return;
        }

        self::getById($id);
    }

    /**
     * Cancela una sesion (por ejemplo por lluvia). Las reservas que seguian
     * reservadas quedan canceladas con la observacion "Sesión cancelada", para
     * que no descuenten y se puedan devolver si la sesion se reabre.
     * Body: { id, motivo_cancelacion }
     */
    public static function cancelar()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        $id = $data['id'];
        $motivo = isset($data['motivo_cancelacion']) ? trim($data['motivo_cancelacion']) : '';

        $sesion = self::obtenerSesion($db, $id);
        if (!$sesion) {
            Flight::json(array('error' => 'La sesión no existe.'), 404);
            return;
        }
        if ($sesion['codigo_estado'] === 'DICTADA') {
            Flight::json(array('error' => 'La sesión ya se dictó: no se puede cancelar.'), 400);
            return;
        }
        if ($motivo === '') {
            Flight::json(array('error' => 'El motivo de la cancelación es obligatorio.'), 400);
            return;
        }

        try {
            $db->beginTransaction();

            $sentence = $db->prepare("
                UPDATE sesiones_cursos_extra SET id_estado_sesion_curso_extra = :id_estado, motivo_cancelacion = :motivo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindValue(':id_estado', EstadosSesionesCursosExtra::idPorCodigo($db, 'CANCELADA'));
            $sentence->bindValue(':motivo', $motivo);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            foreach (self::reservasEnEstado($db, $id, 'RESERVADA') as $r) {
                ReservasSesionesCursosExtra::cambiarEstado($db, $r['id'], 'CANCELADA', true, 'Sesión cancelada');
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al cancelar la sesión: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo cancelar la sesión.'), 500);
            return;
        }

        self::getById($id);
    }

    /**
     * Devuelve una sesion cancelada a programada. Las reservas que se
     * cancelaron con la sesion vuelven a quedar reservadas.
     * Body: { id }
     */
    public static function reabrir()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sesion = self::obtenerSesion($db, $id);
        if (!$sesion) {
            Flight::json(array('error' => 'La sesión no existe.'), 404);
            return;
        }
        if ($sesion['codigo_estado'] !== 'CANCELADA') {
            Flight::json(array('error' => 'Solo se puede reabrir una sesión cancelada.'), 400);
            return;
        }

        try {
            $db->beginTransaction();

            $sentence = $db->prepare("
                UPDATE sesiones_cursos_extra SET id_estado_sesion_curso_extra = :id_estado, motivo_cancelacion = NULL
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindValue(':id_estado', EstadosSesionesCursosExtra::idPorCodigo($db, 'PROGRAMADA'));
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $devolver = $db->prepare("
                UPDATE reservas_sesiones_cursos_extra SET
                    id_estado_reserva_curso_extra = :id_reservada,
                    observacion = NULL,
                    fecha_cancelacion = NULL
                WHERE id_sesion_curso_extra = :id_sesion AND id_tenant = :id_tenant
                  AND id_estado_reserva_curso_extra = :id_cancelada
                  AND observacion = 'Sesión cancelada'
            ");
            $devolver->bindValue(':id_reservada', EstadosReservasCursosExtra::idPorCodigo($db, 'RESERVADA'));
            $devolver->bindValue(':id_cancelada', EstadosReservasCursosExtra::idPorCodigo($db, 'CANCELADA'));
            $devolver->bindParam(':id_sesion', $id);
            $devolver->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $devolver->execute();

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al reabrir la sesión: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo reabrir la sesión.'), 500);
            return;
        }

        self::getById($id);
    }

    /**
     * Registra la asistencia y da la sesion por dictada. Se puede volver a
     * llamar para corregir.
     *   - Cada reserva que llega queda en ASISTIO o NO_ASISTIO. Las que
     *     seguian reservadas y no llegan quedan en NO_ASISTIO.
     *   - Si el estado consume (ASISTIO siempre; NO_ASISTIO si el curso
     *     descuenta la inasistencia) la reserva toma un paquete vigente con
     *     sesiones disponibles; si no consume, lo suelta.
     *   - Si llegan profesores, quedan esos con sus minutos.
     * Body: { id, reservas: [{id, codigo_estado}], docentes?: [...], observaciones? }
     * Devuelve la sesion y 'sin_paquete': cuantas reservas consumieron sin
     * tener un paquete del que descontar.
     */
    public static function registrarAsistencia()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id = $data['id'];
        $marcas = isset($data['reservas']) ? $data['reservas'] : array();

        $sesion = self::obtenerSesion($db, $id);
        if (!$sesion) {
            Flight::json(array('error' => 'La sesión no existe.'), 404);
            return;
        }
        if ($sesion['codigo_estado'] === 'CANCELADA') {
            Flight::json(array('error' => 'La sesión está cancelada: reábrela antes de registrar la asistencia.'), 400);
            return;
        }

        $marcaPorReserva = array();
        foreach ($marcas as $m) {
            if (empty($m['id']) || !isset($m['codigo_estado']) || !in_array($m['codigo_estado'], array('ASISTIO', 'NO_ASISTIO'))) {
                Flight::json(array('error' => 'Cada estudiante debe quedar como asistió o no asistió.'), 400);
                return;
            }
            $marcaPorReserva[$m['id']] = $m['codigo_estado'];
        }

        $reglas = ConfiguracionReservasCursosExtra::obtener($db, $sesion['id_curso_extra']);
        $sinPaquete = 0;

        try {
            $db->beginTransaction();

            $idAsistio = EstadosReservasCursosExtra::idPorCodigo($db, 'ASISTIO');
            $idNoAsistio = EstadosReservasCursosExtra::idPorCodigo($db, 'NO_ASISTIO');
            $actualizar = $db->prepare("
                UPDATE reservas_sesiones_cursos_extra SET
                    id_estado_reserva_curso_extra = :id_estado,
                    id_paquete_estudiante_curso_extra = :id_paquete
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            // Solo las reservas que ocupan puesto: las canceladas y reagendadas
            // no se tocan aunque lleguen.
            $reservas = $db->prepare("
                SELECT r.id, r.id_estudiante_x_curso_extra, r.id_paquete_estudiante_curso_extra, er.codigo
                FROM reservas_sesiones_cursos_extra r
                INNER JOIN estados_reservas_cursos_extra er ON r.id_estado_reserva_curso_extra = er.id
                WHERE r.id_sesion_curso_extra = :id_sesion AND r.id_tenant = :id_tenant
                  AND er.codigo IN ('RESERVADA', 'ASISTIO', 'NO_ASISTIO')
            ");
            $reservas->bindParam(':id_sesion', $id);
            $reservas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $reservas->execute();

            foreach ($reservas->fetchAll() as $r) {
                if (isset($marcaPorReserva[$r['id']])) {
                    $codigo = $marcaPorReserva[$r['id']];
                } elseif ($r['codigo'] === 'RESERVADA') {
                    $codigo = 'NO_ASISTIO';
                } else {
                    $codigo = $r['codigo'];
                }

                $consume = $codigo === 'ASISTIO' || ($codigo === 'NO_ASISTIO' && $reglas['descuenta_inasistencia']);
                $idPaquete = null;
                if ($consume) {
                    // Si ya tenia paquete (se esta corrigiendo) lo conserva.
                    $idPaquete = $r['id_paquete_estudiante_curso_extra']
                        ? $r['id_paquete_estudiante_curso_extra']
                        : PaquetesEstudiantesCursosExtra::paqueteVigente($db, $r['id_estudiante_x_curso_extra'], $sesion['fecha'], $r['id']);
                    if ($idPaquete === null) {
                        $sinPaquete++;
                    }
                }

                $actualizar->bindValue(':id_estado', $codigo === 'ASISTIO' ? $idAsistio : $idNoAsistio);
                $actualizar->bindValue(':id_paquete', $idPaquete);
                $actualizar->bindValue(':id', $r['id']);
                $actualizar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $actualizar->execute();
            }

            if (isset($data['docentes'])) {
                $error = DocentesXSesionesCursosExtra::sincronizar($db, $id, $data['docentes']);
                if ($error !== null) {
                    $db->rollBack();
                    Flight::json(array('error' => $error), 400);
                    return;
                }
            }

            $sentence = $db->prepare("
                UPDATE sesiones_cursos_extra SET
                    id_estado_sesion_curso_extra = :id_estado,
                    observaciones = :observaciones
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindValue(':id_estado', EstadosSesionesCursosExtra::idPorCodigo($db, 'DICTADA'));
            $sentence->bindValue(':observaciones', array_key_exists('observaciones', $data->getData())
                ? ($data['observaciones'] !== '' ? $data['observaciones'] : null)
                : $sesion['observaciones']);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al registrar la asistencia de la sesión: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo registrar la asistencia.'), 500);
            return;
        }

        Flight::json(array('id' => $id, 'sin_paquete' => $sinPaquete));
    }

    /**
     * Borra una sesion que no se ha dictado ni tiene asistencia registrada.
     * Sus reservas y profesores se van con ella. Una sesion de horario que se
     * borra se vuelve a crear si se generan otra vez esas fechas; para que no
     * se dicte, mejor cancelarla.
     */
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sesion = self::obtenerSesion($db, $id);
        if (!$sesion) {
            Flight::json(array('error' => 'La sesión no existe.'), 404);
            return;
        }
        $registradas = count(self::reservasEnEstado($db, $id, 'ASISTIO')) + count(self::reservasEnEstado($db, $id, 'NO_ASISTIO'));
        if ($sesion['codigo_estado'] === 'DICTADA' || $registradas > 0) {
            Flight::json(array('error' => 'No se puede eliminar: la sesión ya tiene asistencia registrada.'), 400);
            return;
        }

        $sentence = $db->prepare("DELETE FROM sesiones_cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    // ==================== USO DESDE OTROS SERVICIOS ====================

    /**
     * Datos de una sesion para validar: curso, fecha, horas, cupo, estado y
     * si el curso permite sobrecupo. Devuelve null si no existe.
     */
    public static function obtenerSesion($db, $id)
    {
        $sentence = $db->prepare("
            SELECT s.id, s.id_curso_extra, s.id_horario_curso_extra, s.fecha, s.hora_inicial, s.hora_final,
                   s.id_espacio_lugar_curso_extra, s.cupo, s.observaciones, es.codigo AS codigo_estado,
                   ce.permite_sobrecupo
            FROM sesiones_cursos_extra s
            INNER JOIN estados_sesiones_cursos_extra es ON s.id_estado_sesion_curso_extra = es.id
            INNER JOIN cursos_extra ce ON s.id_curso_extra = ce.id
            WHERE s.id = :id AND s.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? $fila : null;
    }

    /**
     * Puestos ocupados: reservas en RESERVADA, ASISTIO o NO_ASISTIO.
     */
    public static function contarOcupados($db, $id_sesion)
    {
        $sentence = $db->prepare("
            SELECT COUNT(*) AS ocupados
            FROM reservas_sesiones_cursos_extra r
            INNER JOIN estados_reservas_cursos_extra er ON r.id_estado_reserva_curso_extra = er.id
            WHERE r.id_sesion_curso_extra = :id_sesion AND r.id_tenant = :id_tenant
              AND er.codigo IN ('RESERVADA', 'ASISTIO', 'NO_ASISTIO')
        ");
        $sentence->bindParam(':id_sesion', $id_sesion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? (int) $fila['ocupados'] : 0;
    }

    // ==================== PRIVADOS ====================

    /**
     * Llaves "id_horario|fecha" de las sesiones de horario que ya tienen fila
     * en el rango.
     */
    private static function horariosConSesion($db, $fecha_desde, $fecha_hasta)
    {
        $sentence = $db->prepare("
            SELECT id_horario_curso_extra, fecha FROM sesiones_cursos_extra
            WHERE id_tenant = :id_tenant AND id_horario_curso_extra IS NOT NULL
              AND fecha BETWEEN :fecha_desde AND :fecha_hasta
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':fecha_desde', $fecha_desde);
        $sentence->bindValue(':fecha_hasta', $fecha_hasta);
        $sentence->execute();
        $llaves = array();
        foreach ($sentence->fetchAll() as $f) {
            $llaves[$f['id_horario_curso_extra'] . '|' . $f['fecha']] = true;
        }
        return $llaves;
    }

    /**
     * Sesiones calculadas desde los horarios para las fechas que no tienen
     * fila. Todo sale de cuatro consultas (horarios, reglas, inscritos y
     * reservas fijas); el cruce por fecha se hace aqui.
     */
    private static function sesionesVirtuales($db, $fecha_desde, $fecha_hasta, $id_curso_extra, $id_docente, $existentes)
    {
        $horarios = $db->prepare("
            SELECT hce.id, hce.id_curso_extra, hce.id_dia_semana, hce.hora_inicial, hce.hora_final,
                   hce.id_espacio_lugar_curso_extra, hce.id_docente, hce.cupo,
                   ce.nombre AS nombre_curso, ce.color AS color_curso, ce.permite_sobrecupo, ce.id_lugar_curso_extra,
                   ce.cupo_maximo, ce.fecha_inicio, ce.fecha_fin,
                   mce.codigo AS codigo_modalidad,
                   esp.nombre AS nombre_espacio,
                   TRIM(CONCAT(IFNULL(ph.primer_nombre, ''), ' ', IFNULL(ph.primer_apellido, ''))) AS nombre_docente_horario,
                   (SELECT dxc.id_docente FROM docentes_x_cursos_extra dxc
                    WHERE dxc.id_curso_extra = ce.id AND dxc.activo = 1
                    ORDER BY dxc.es_titular DESC, dxc.fecha_asignacion LIMIT 1) AS id_titular,
                   (SELECT TRIM(CONCAT(IFNULL(pt.primer_nombre, ''), ' ', IFNULL(pt.primer_apellido, '')))
                    FROM docentes_x_cursos_extra dxc2
                    INNER JOIN docentes dt ON dxc2.id_docente = dt.id
                    INNER JOIN personas pt ON dt.id_persona = pt.id
                    WHERE dxc2.id_curso_extra = ce.id AND dxc2.activo = 1
                    ORDER BY dxc2.es_titular DESC, dxc2.fecha_asignacion LIMIT 1) AS nombre_titular
            FROM horarios_cursos_extra hce
            INNER JOIN cursos_extra ce ON hce.id_curso_extra = ce.id
            LEFT JOIN modalidades_cursos_extra mce ON ce.id_modalidad_curso_extra = mce.id
            LEFT JOIN espacios_lugares_cursos_extra esp ON hce.id_espacio_lugar_curso_extra = esp.id
            LEFT JOIN docentes dh ON hce.id_docente = dh.id
            LEFT JOIN personas ph ON dh.id_persona = ph.id
            WHERE hce.id_tenant = :id_tenant AND ce.activo = 1
              AND (ce.fecha_inicio IS NULL OR ce.fecha_inicio <= :fecha_hasta)
              AND (ce.fecha_fin IS NULL OR ce.fecha_fin >= :fecha_desde)
              AND (:id_curso IS NULL OR hce.id_curso_extra = :id_curso_2)
        ");
        $horarios->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $horarios->bindValue(':fecha_desde', $fecha_desde);
        $horarios->bindValue(':fecha_hasta', $fecha_hasta);
        $horarios->bindValue(':id_curso', $id_curso_extra);
        $horarios->bindValue(':id_curso_2', $id_curso_extra);
        $horarios->execute();
        $listaHorarios = $horarios->fetchAll();
        if (empty($listaHorarios)) {
            return array();
        }

        // Reglas: un curso sin fila tiene reserva automatica.
        $reglas = $db->prepare("SELECT id_curso_extra, reserva_automatica FROM configuracion_reservas_cursos_extra WHERE id_tenant = :id_tenant");
        $reglas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $reglas->execute();
        $automatica = array();
        foreach ($reglas->fetchAll() as $r) {
            $automatica[$r['id_curso_extra']] = (int) $r['reserva_automatica'];
        }

        $inscritos = $db->prepare("
            SELECT id_curso_extra, fecha_inscripcion, fecha_retiro FROM estudiantes_x_cursos_extra
            WHERE id_tenant = :id_tenant AND activo = 1
        ");
        $inscritos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $inscritos->execute();
        $inscritosPorCurso = array();
        foreach ($inscritos->fetchAll() as $i) {
            $inscritosPorCurso[$i['id_curso_extra']][] = $i;
        }

        $fijas = $db->prepare("
            SELECT rf.id_horario_curso_extra, rf.fecha_desde, rf.fecha_hasta, exce.fecha_retiro
            FROM reservas_fijas_cursos_extra rf
            INNER JOIN estudiantes_x_cursos_extra exce ON rf.id_estudiante_x_curso_extra = exce.id
            WHERE rf.id_tenant = :id_tenant AND rf.activo = 1 AND exce.activo = 1
        ");
        $fijas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $fijas->execute();
        $fijasPorHorario = array();
        foreach ($fijas->fetchAll() as $f) {
            $fijasPorHorario[$f['id_horario_curso_extra']][] = $f;
        }

        $virtuales = array();
        foreach ($listaHorarios as $h) {
            $idDocente = $h['id_docente'] ? $h['id_docente'] : $h['id_titular'];
            if ($id_docente !== null && $idDocente !== $id_docente) {
                continue;
            }
            $nombreDocente = $h['id_docente'] ? $h['nombre_docente_horario'] : $h['nombre_titular'];
            $esAutomatica = isset($automatica[$h['id_curso_extra']]) ? $automatica[$h['id_curso_extra']] : 1;
            $cupo = $h['cupo'] !== null ? $h['cupo'] : $h['cupo_maximo'];

            // Sin fecha de inicio o de fin el curso es permanente: se toma el rango pedido.
            $fecha = new DateTime($h['fecha_inicio'] !== null ? max($fecha_desde, $h['fecha_inicio']) : $fecha_desde);
            $fin = new DateTime($h['fecha_fin'] !== null ? min($fecha_hasta, $h['fecha_fin']) : $fecha_hasta);
            while ($fecha <= $fin) {
                $dia = $fecha->format('Y-m-d');
                $fecha->modify('+1 day');
                if ((int) date('N', strtotime($dia)) !== (int) $h['id_dia_semana'] || isset($existentes[$h['id'] . '|' . $dia])) {
                    continue;
                }

                // Mismo criterio con el que se pondrian las reservas al abrirla.
                $ocupados = 0;
                if ($esAutomatica) {
                    foreach (isset($inscritosPorCurso[$h['id_curso_extra']]) ? $inscritosPorCurso[$h['id_curso_extra']] : array() as $i) {
                        if ($i['fecha_inscripcion'] <= $dia && ($i['fecha_retiro'] === null || $i['fecha_retiro'] > $dia)) {
                            $ocupados++;
                        }
                    }
                } else {
                    foreach (isset($fijasPorHorario[$h['id']]) ? $fijasPorHorario[$h['id']] : array() as $f) {
                        if ($f['fecha_desde'] <= $dia && ($f['fecha_hasta'] === null || $f['fecha_hasta'] >= $dia)
                            && ($f['fecha_retiro'] === null || $f['fecha_retiro'] > $dia)) {
                            $ocupados++;
                        }
                    }
                    if ($cupo !== null && $ocupados > (int) $cupo && empty($h['permite_sobrecupo'])) {
                        $ocupados = (int) $cupo;
                    }
                }

                $virtuales[] = array(
                    'id' => null,
                    'virtual' => 1,
                    'id_curso_extra' => $h['id_curso_extra'],
                    'id_horario_curso_extra' => $h['id'],
                    'fecha' => $dia,
                    'hora_inicial' => $h['hora_inicial'],
                    'hora_final' => $h['hora_final'],
                    'id_espacio_lugar_curso_extra' => $h['id_espacio_lugar_curso_extra'],
                    'cupo' => $cupo,
                    'id_estado_sesion_curso_extra' => null,
                    'motivo_cancelacion' => null,
                    'observaciones' => null,
                    'fecha_registro' => null,
                    'nombre_curso' => $h['nombre_curso'],
                    'color_curso' => $h['color_curso'],
                    'permite_sobrecupo' => $h['permite_sobrecupo'],
                    'id_lugar_curso_extra' => $h['id_lugar_curso_extra'],
                    'codigo_modalidad' => $h['codigo_modalidad'],
                    'nombre_espacio' => $h['nombre_espacio'],
                    'codigo_estado' => 'PROGRAMADA',
                    'nombre_estado' => 'Programada',
                    'ocupados' => $ocupados,
                    'asistieron' => 0,
                    'nombres_docentes' => $idDocente ? $nombreDocente : null
                );
            }
        }

        return $virtuales;
    }

    /**
     * Pone en una sesion programada las reservas que le tocan segun las
     * reglas del curso y que todavia no tiene. Nunca revive una reserva que
     * alguien cancelo o reagendo.
     */
    private static function completarReservas($db, $sesion, $reglas)
    {
        $creadas = 0;
        $sinCupo = 0;
        $idReservada = EstadosReservasCursosExtra::idPorCodigo($db, 'RESERVADA');

        if ($reglas['reserva_automatica']) {
            $candidatos = $db->prepare("
                SELECT exce.id AS id_inscripcion, NULL AS id_reserva_fija
                FROM estudiantes_x_cursos_extra exce
                WHERE exce.id_curso_extra = :id_curso AND exce.id_tenant = :id_tenant
                  AND exce.activo = 1
                  AND exce.fecha_inscripcion <= :fecha_1
                  AND (exce.fecha_retiro IS NULL OR exce.fecha_retiro > :fecha_2)
                  AND NOT EXISTS (SELECT 1 FROM reservas_sesiones_cursos_extra r
                                  WHERE r.id_sesion_curso_extra = :id_sesion AND r.id_estudiante_x_curso_extra = exce.id)
            ");
            $candidatos->bindValue(':id_curso', $sesion['id_curso_extra']);
        } else {
            $candidatos = $db->prepare("
                SELECT rf.id_estudiante_x_curso_extra AS id_inscripcion, rf.id AS id_reserva_fija
                FROM reservas_fijas_cursos_extra rf
                INNER JOIN estudiantes_x_cursos_extra exce ON rf.id_estudiante_x_curso_extra = exce.id
                WHERE rf.id_horario_curso_extra = :id_horario AND rf.id_tenant = :id_tenant
                  AND rf.activo = 1 AND exce.activo = 1
                  AND rf.fecha_desde <= :fecha_1
                  AND (rf.fecha_hasta IS NULL OR rf.fecha_hasta >= :fecha_2)
                  AND (exce.fecha_retiro IS NULL OR exce.fecha_retiro > :fecha_3)
                  AND NOT EXISTS (SELECT 1 FROM reservas_sesiones_cursos_extra r
                                  WHERE r.id_sesion_curso_extra = :id_sesion AND r.id_estudiante_x_curso_extra = exce.id)
                ORDER BY rf.fecha_registro
            ");
            $candidatos->bindValue(':id_horario', $sesion['id_horario_curso_extra']);
            $candidatos->bindValue(':fecha_3', $sesion['fecha']);
        }
        $candidatos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $candidatos->bindValue(':fecha_1', $sesion['fecha']);
        $candidatos->bindValue(':fecha_2', $sesion['fecha']);
        $candidatos->bindValue(':id_sesion', $sesion['id']);
        $candidatos->execute();

        foreach ($candidatos->fetchAll() as $c) {
            // En la reserva automatica el cupo es el del curso, que ya se valido
            // al inscribir: no se deja a nadie por fuera.
            if (!$reglas['reserva_automatica'] && !ReservasSesionesCursosExtra::hayCupo($db, $sesion)) {
                $sinCupo++;
                continue;
            }
            $idReserva = Uuid::generar();
            $insertar = $db->prepare("
                INSERT INTO reservas_sesiones_cursos_extra
                    (id, id_tenant, id_sesion_curso_extra, id_estudiante_x_curso_extra, id_estado_reserva_curso_extra,
                     id_reserva_fija_curso_extra)
                VALUES
                    (:id, :id_tenant, :id_sesion, :id_inscripcion, :id_estado, :id_fija)
            ");
            $insertar->bindValue(':id', $idReserva);
            $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $insertar->bindValue(':id_sesion', $sesion['id']);
            $insertar->bindValue(':id_inscripcion', $c['id_inscripcion']);
            $insertar->bindValue(':id_estado', $idReservada);
            $insertar->bindValue(':id_fija', $c['id_reserva_fija']);
            $insertar->execute();
            $creadas++;
        }

        return array('creadas' => $creadas, 'sin_cupo' => $sinCupo);
    }

    private static function idSesionDeHorario($db, $id_horario, $fecha)
    {
        $sentence = $db->prepare("
            SELECT id FROM sesiones_cursos_extra
            WHERE id_horario_curso_extra = :id_horario AND fecha = :fecha AND id_tenant = :id_tenant
        ");
        $sentence->bindValue(':id_horario', $id_horario);
        $sentence->bindValue(':fecha', $fecha);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? $fila['id'] : null;
    }

    private static function idTitularCurso($db, $id_curso_extra)
    {
        $sentence = $db->prepare("
            SELECT id_docente FROM docentes_x_cursos_extra
            WHERE id_curso_extra = :id_curso AND id_tenant = :id_tenant AND activo = 1
            ORDER BY es_titular DESC, fecha_asignacion
            LIMIT 1
        ");
        $sentence->bindValue(':id_curso', $id_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? $fila['id_docente'] : null;
    }

    private static function reservasEnEstado($db, $id_sesion, $codigo)
    {
        $sentence = $db->prepare("
            SELECT r.id
            FROM reservas_sesiones_cursos_extra r
            INNER JOIN estados_reservas_cursos_extra er ON r.id_estado_reserva_curso_extra = er.id
            WHERE r.id_sesion_curso_extra = :id_sesion AND r.id_tenant = :id_tenant AND er.codigo = :codigo
        ");
        $sentence->bindParam(':id_sesion', $id_sesion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':codigo', $codigo);
        $sentence->execute();
        return $sentence->fetchAll();
    }

    /**
     * @return string|null Mensaje de error, o null si los datos sirven.
     */
    private static function validarDatos($id_curso_extra, $fecha, $hora_inicial, $hora_final)
    {
        if (empty($id_curso_extra)) {
            return 'El curso de la sesión es obligatorio.';
        }
        if ($fecha === '' || $fecha === null) {
            return 'La fecha de la sesión es obligatoria.';
        }
        if ($hora_inicial === '' || $hora_final === '' || $hora_inicial === null || $hora_final === null) {
            return 'La hora de inicio y la de fin son obligatorias.';
        }
        // Se comparan en HH:mm para que '08:00' y '08:00:00' den lo mismo.
        if (substr($hora_inicial, 0, 5) >= substr($hora_final, 0, 5)) {
            return 'La hora de inicio debe ser menor que la de fin.';
        }
        return null;
    }

    private static function consultaBase()
    {
        return "
            SELECT s.id, s.id_curso_extra, s.id_horario_curso_extra, s.fecha, s.hora_inicial, s.hora_final,
                   s.id_espacio_lugar_curso_extra, s.cupo, s.id_estado_sesion_curso_extra, s.motivo_cancelacion,
                   s.observaciones, s.fecha_registro,
                   ce.nombre AS nombre_curso, ce.color AS color_curso, ce.permite_sobrecupo, ce.id_lugar_curso_extra,
                   mce.codigo AS codigo_modalidad,
                   esp.nombre AS nombre_espacio,
                   es.codigo AS codigo_estado, es.nombre AS nombre_estado,
                   (SELECT COUNT(*) FROM reservas_sesiones_cursos_extra r
                    INNER JOIN estados_reservas_cursos_extra er ON r.id_estado_reserva_curso_extra = er.id
                    WHERE r.id_sesion_curso_extra = s.id
                      AND er.codigo IN ('RESERVADA', 'ASISTIO', 'NO_ASISTIO')) AS ocupados,
                   (SELECT COUNT(*) FROM reservas_sesiones_cursos_extra r2
                    INNER JOIN estados_reservas_cursos_extra er2 ON r2.id_estado_reserva_curso_extra = er2.id
                    WHERE r2.id_sesion_curso_extra = s.id AND er2.codigo = 'ASISTIO') AS asistieron,
                   (SELECT GROUP_CONCAT(TRIM(CONCAT(IFNULL(pd.primer_nombre, ''), ' ', IFNULL(pd.primer_apellido, '')))
                           ORDER BY dxs.es_titular DESC SEPARATOR ', ')
                    FROM docentes_x_sesiones_cursos_extra dxs
                    INNER JOIN docentes d ON dxs.id_docente = d.id
                    INNER JOIN personas pd ON d.id_persona = pd.id
                    WHERE dxs.id_sesion_curso_extra = s.id) AS nombres_docentes
            FROM sesiones_cursos_extra s
            INNER JOIN cursos_extra ce ON s.id_curso_extra = ce.id
            INNER JOIN estados_sesiones_cursos_extra es ON s.id_estado_sesion_curso_extra = es.id
            LEFT JOIN modalidades_cursos_extra mce ON ce.id_modalidad_curso_extra = mce.id
            LEFT JOIN espacios_lugares_cursos_extra esp ON s.id_espacio_lugar_curso_extra = esp.id
        ";
    }
}
