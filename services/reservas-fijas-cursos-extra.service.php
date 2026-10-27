<?php
/**
 * Reservas fijas de cursos extra: "voy todos los sabados de 8 a 10".
 *
 * Una reserva fija amarra una inscripcion a un horario del curso desde una
 * fecha. Al generar las sesiones de ese horario el estudiante queda
 * reservado. Al crearla tambien se reserva en las sesiones programadas que ya
 * existan desde su fecha; al terminarla se liberan las reservas que tenga de
 * ahi en adelante y que todavia no se hayan registrado.
 *
 * En los cursos con reserva automatica no hace falta: todos los inscritos
 * quedan en todas las sesiones.
 */
class ReservasFijasCursosExtra
{
    public static function getByCurso($id_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::consultaBase() . "
            WHERE exce.id_curso_extra = :id_curso_extra AND rf.id_tenant = :id_tenant
            ORDER BY rf.activo DESC, hce.id_dia_semana, hce.hora_inicial, p.primer_nombre
        ");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::consultaBase() . "
            WHERE rf.id = :id AND rf.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Crea la reserva fija y reserva al estudiante en las sesiones
     * programadas de ese horario que ya existan desde fecha_desde. Las que
     * no tengan cupo se informan en 'sin_cupo'.
     * Body: { id_estudiante_x_curso_extra, id_horario_curso_extra, fecha_desde, fecha_hasta? }
     */
    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id_inscripcion = $data['id_estudiante_x_curso_extra'];
        $id_horario = $data['id_horario_curso_extra'];
        $fecha_desde = isset($data['fecha_desde']) ? $data['fecha_desde'] : '';
        $fecha_hasta = isset($data['fecha_hasta']) && $data['fecha_hasta'] !== '' ? $data['fecha_hasta'] : null;

        if ($fecha_desde === '') {
            Flight::json(array('error' => 'La fecha desde la que aplica la reserva fija es obligatoria.'), 400);
            return;
        }
        if ($fecha_hasta !== null && $fecha_hasta < $fecha_desde) {
            Flight::json(array('error' => 'La fecha hasta no puede ser anterior a la fecha desde.'), 400);
            return;
        }

        try {
            $db->beginTransaction();
            $resultado = self::crear($db, $id_inscripcion, $id_horario, $fecha_desde, $fecha_hasta);
            if (isset($resultado['error'])) {
                $db->rollBack();
                Flight::json(array('error' => $resultado['error']), 400);
                return;
            }
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al crear la reserva fija: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo crear la reserva fija.'), 500);
            return;
        }

        Flight::json($resultado);
    }

    /**
     * Crea la reserva fija y reserva al estudiante en las sesiones
     * programadas de ese horario que ya existan desde fecha_desde. No abre
     * transaccion: la abre quien llama (el endpoint new o la aprobacion de una
     * solicitud de inscripcion), para que todo quede completo o nada.
     *
     * @return array {id, reservas_creadas, sin_cupo} o {error} si la
     *               inscripcion y el horario no cuadran o ya existe una
     *               reserva fija vigente en ese horario.
     */
    public static function crear($db, $id_inscripcion, $id_horario, $fecha_desde, $fecha_hasta)
    {
        $verificar = $db->prepare("
            SELECT exce.id_curso_extra AS curso_inscripcion, hce.id_curso_extra AS curso_horario
            FROM estudiantes_x_cursos_extra exce, horarios_cursos_extra hce
            WHERE exce.id = :id_inscripcion AND exce.id_tenant = :id_tenant_1
              AND hce.id = :id_horario AND hce.id_tenant = :id_tenant_2
        ");
        $verificar->bindParam(':id_inscripcion', $id_inscripcion);
        $verificar->bindParam(':id_horario', $id_horario);
        $verificar->bindValue(':id_tenant_1', TenantContext::id(), PDO::PARAM_INT);
        $verificar->bindValue(':id_tenant_2', TenantContext::id(), PDO::PARAM_INT);
        $verificar->execute();
        $fila = $verificar->fetch();
        if (!$fila) {
            return array('error' => 'La inscripción o el horario no existen.');
        }
        if ($fila['curso_inscripcion'] !== $fila['curso_horario']) {
            return array('error' => 'El horario no es del curso en el que está inscrito el estudiante.');
        }

        $duplicada = $db->prepare("
            SELECT id FROM reservas_fijas_cursos_extra
            WHERE id_estudiante_x_curso_extra = :id_inscripcion AND id_horario_curso_extra = :id_horario
              AND id_tenant = :id_tenant AND activo = 1
              AND (fecha_hasta IS NULL OR fecha_hasta >= :fecha_desde)
        ");
        $duplicada->bindParam(':id_inscripcion', $id_inscripcion);
        $duplicada->bindParam(':id_horario', $id_horario);
        $duplicada->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $duplicada->bindParam(':fecha_desde', $fecha_desde);
        $duplicada->execute();
        if ($duplicada->fetch()) {
            return array('error' => 'El estudiante ya tiene una reserva fija vigente en ese horario.');
        }

        $id = Uuid::generar();
        $reservadas = 0;
        $sinCupo = array();

        $sentence = $db->prepare("
            INSERT INTO reservas_fijas_cursos_extra
                (id, id_tenant, id_estudiante_x_curso_extra, id_horario_curso_extra, fecha_desde, fecha_hasta, activo)
            VALUES
                (:id, :id_tenant, :id_inscripcion, :id_horario, :fecha_desde, :fecha_hasta, 1)
        ");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_inscripcion', $id_inscripcion);
        $sentence->bindParam(':id_horario', $id_horario);
        $sentence->bindParam(':fecha_desde', $fecha_desde);
        $sentence->bindValue(':fecha_hasta', $fecha_hasta);
        $sentence->execute();

        // Sesiones programadas de ese horario que ya existen en el rango.
        $sesiones = $db->prepare("
            SELECT s.id
            FROM sesiones_cursos_extra s
            INNER JOIN estados_sesiones_cursos_extra es ON s.id_estado_sesion_curso_extra = es.id
            WHERE s.id_horario_curso_extra = :id_horario AND s.id_tenant = :id_tenant
              AND es.codigo = 'PROGRAMADA'
              AND s.fecha >= :fecha_desde
              AND (:fecha_hasta IS NULL OR s.fecha <= :fecha_hasta_2)
            ORDER BY s.fecha
        ");
        $sesiones->bindParam(':id_horario', $id_horario);
        $sesiones->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sesiones->bindParam(':fecha_desde', $fecha_desde);
        $sesiones->bindValue(':fecha_hasta', $fecha_hasta);
        $sesiones->bindValue(':fecha_hasta_2', $fecha_hasta);
        $sesiones->execute();

        foreach ($sesiones->fetchAll() as $s) {
            $sesion = SesionesCursosExtra::obtenerSesion($db, $s['id']);
            $resultado = ReservasSesionesCursosExtra::reservar($db, $sesion, $id_inscripcion, $id, null);
            if (isset($resultado['error'])) {
                // Ya tenia puesto (no pasa nada) o no hay cupo (se informa).
                if (!ReservasSesionesCursosExtra::hayCupo($db, $sesion)) {
                    $sinCupo[] = $sesion['fecha'];
                }
            } else {
                $reservadas++;
            }
        }

        return array('id' => $id, 'reservas_creadas' => $reservadas, 'sin_cupo' => $sinCupo);
    }

    /**
     * Termina o reactiva una reserva fija. Al ponerle fecha_hasta (o al
     * inactivarla) se liberan las reservas que dejo en sesiones posteriores y
     * que siguen solo reservadas.
     * Body: { id, fecha_hasta?, activo? }
     */
    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id = $data['id'];
        $fecha_hasta = isset($data['fecha_hasta']) && $data['fecha_hasta'] !== '' ? $data['fecha_hasta'] : null;
        $activo = isset($data['activo']) ? ($data['activo'] ? 1 : 0) : 1;

        $actual = $db->prepare("SELECT fecha_desde FROM reservas_fijas_cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
        $actual->bindParam(':id', $id);
        $actual->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $actual->execute();
        $fila = $actual->fetch();
        if (!$fila) {
            Flight::json(array('error' => 'La reserva fija no existe.'), 404);
            return;
        }
        if ($fecha_hasta !== null && $fecha_hasta < $fila['fecha_desde']) {
            Flight::json(array('error' => 'La fecha hasta no puede ser anterior a la fecha desde.'), 400);
            return;
        }

        try {
            $db->beginTransaction();

            $sentence = $db->prepare("
                UPDATE reservas_fijas_cursos_extra SET fecha_hasta = :fecha_hasta, activo = :activo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindValue(':fecha_hasta', $fecha_hasta);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            // Desde cuando se liberan: el dia siguiente a fecha_hasta, o hoy si
            // se inactiva. Lo ya registrado (asistio, no asistio) no se toca.
            $liberarDesde = null;
            if (!$activo) {
                $liberarDesde = date('Y-m-d');
            } elseif ($fecha_hasta !== null) {
                $liberarDesde = date('Y-m-d', strtotime($fecha_hasta . ' +1 day'));
            }
            if ($liberarDesde !== null) {
                self::liberarReservas($db, $id, $liberarDesde);
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al actualizar la reserva fija: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo actualizar la reserva fija.'), 500);
            return;
        }

        self::getById($id);
    }

    /**
     * Borra la reserva fija. Antes libera las reservas que dejo desde hoy y
     * que siguen solo reservadas; las demas quedan como sueltas (la llave
     * pasa a nulo).
     */
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        try {
            $db->beginTransaction();

            self::liberarReservas($db, $id, date('Y-m-d'));

            $sentence = $db->prepare("DELETE FROM reservas_fijas_cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al eliminar la reserva fija: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo eliminar la reserva fija.'), 500);
            return;
        }

        Flight::json(array('id' => $id));
    }

    /**
     * Cancela las reservas de la reserva fija en sesiones desde $desde que
     * siguen en RESERVADA.
     */
    private static function liberarReservas($db, $id_reserva_fija, $desde)
    {
        $sentence = $db->prepare("
            SELECT r.id
            FROM reservas_sesiones_cursos_extra r
            INNER JOIN estados_reservas_cursos_extra er ON r.id_estado_reserva_curso_extra = er.id
            INNER JOIN sesiones_cursos_extra s ON r.id_sesion_curso_extra = s.id
            WHERE r.id_reserva_fija_curso_extra = :id_fija AND r.id_tenant = :id_tenant
              AND er.codigo = 'RESERVADA' AND s.fecha >= :desde
        ");
        $sentence->bindParam(':id_fija', $id_reserva_fija);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':desde', $desde);
        $sentence->execute();

        foreach ($sentence->fetchAll() as $r) {
            ReservasSesionesCursosExtra::cambiarEstado($db, $r['id'], 'CANCELADA', true, 'Terminó la reserva fija');
        }
    }

    private static function consultaBase()
    {
        return "
            SELECT rf.id, rf.id_estudiante_x_curso_extra, rf.id_horario_curso_extra, rf.fecha_desde, rf.fecha_hasta, rf.activo,
                   exce.id_curso_extra, exce.id_estudiante,
                   TRIM(REGEXP_REPLACE(CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                        IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')), '\\\\s+', ' ')) AS nombre_estudiante,
                   hce.id_dia_semana, ds.nombre AS nombre_dia, hce.hora_inicial, hce.hora_final
            FROM reservas_fijas_cursos_extra rf
            INNER JOIN estudiantes_x_cursos_extra exce ON rf.id_estudiante_x_curso_extra = exce.id
            INNER JOIN estudiantes e ON exce.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            INNER JOIN horarios_cursos_extra hce ON rf.id_horario_curso_extra = hce.id
            INNER JOIN dias_semana ds ON hce.id_dia_semana = ds.id
        ";
    }
}
