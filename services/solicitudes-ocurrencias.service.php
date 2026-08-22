<?php
/*=============================================
SERVICIO - OCURRENCIAS DE LA SOLICITUD
Archivo: services/solicitudes-ocurrencias.service.php

Una fila por dia habil y por hora. Es la unidad de la agenda del dia y lo
unico que se marca.

Una solicitud sin horas genera una ocurrencia por dia con hora_programada
en NULL: sale en la lista al principio y no notifica.
=============================================*/

class SolicitudesOcurrencias
{
    const ESTADO_PENDIENTE   = 1;
    const ESTADO_CUMPLIDA    = 2;
    const ESTADO_NO_CUMPLIDA = 3;

    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_solicitud, fecha, hora_programada, id_estado,
                                         hora_real, id_usuario_cumple, motivo_no_cumplida, observacion
                                  FROM solicitudes_ocurrencias
                                  WHERE id_tenant = :id_tenant
                                  ORDER BY fecha DESC, hora_programada");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::sqlDetalle() . "
                                  WHERE o.id = :id AND o.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getBySolicitud($id_solicitud)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare(self::sqlDetalle() . "
                                  WHERE o.id_solicitud = :id_solicitud AND o.id_tenant = :id_tenant
                                  ORDER BY o.fecha, o.hora_programada IS NULL, o.hora_programada");
        $sentence->bindParam(':id_solicitud', $id_solicitud);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Agenda del dia del usuario que consulta.
     *
     * Ve lo suyo (esta en la lista de responsables) mas lo que no tiene
     * responsable, que por definicion es de todos. No hay forma de ver lo de
     * las demas: la lista de responsables ES el control de visibilidad, y por
     * eso el tipo de medicamento exige responsable.
     *
     * Las que no tienen hora van primero: son las recomendaciones del dia,
     * que se tienen presentes toda la jornada.
     */
    public static function getAgendaDia($fecha)
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $idColaborador = Solicitudes::colaboradorDelUsuario($db, isset($userData->id) ? $userData->id : null);

        $sentence = $db->prepare(self::sqlDetalle() . "
                                  WHERE o.id_tenant = :id_tenant
                                    AND o.fecha = :fecha
                                    AND s.id_estado = :autorizado
                                    AND (
                                        NOT EXISTS (
                                            SELECT 1 FROM solicitudes_personas sp
                                            WHERE sp.id_solicitud = s.id AND sp.id_rol = :rol_responsable
                                        )
                                        OR EXISTS (
                                            SELECT 1 FROM solicitudes_personas sp
                                            WHERE sp.id_solicitud = s.id
                                              AND sp.id_rol = :rol_responsable_yo
                                              AND sp.id_colaborador = :id_colaborador
                                        )
                                    )
                                  ORDER BY o.hora_programada IS NULL DESC, o.hora_programada, estudiante_nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindValue(':autorizado', Solicitudes::ESTADO_AUTORIZADO, PDO::PARAM_INT);
        $sentence->bindValue(':rol_responsable', SolicitudesPersonas::ROL_RESPONSABLE, PDO::PARAM_INT);
        $sentence->bindValue(':rol_responsable_yo', SolicitudesPersonas::ROL_RESPONSABLE, PDO::PARAM_INT);
        $sentence->bindValue(':id_colaborador', $idColaborador);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Marca la ocurrencia como cumplida, con hora real y el usuario que la
     * marco. Si el tipo lo pide, avisa al acudiente.
     *
     * Marcar es siempre opcional: en los tipos que no exigen confirmacion la
     * ocurrencia se apaga sola al pasar la hora, y esto queda como constancia
     * para quien la quiera dejar.
     */
    public static function marcarCumplida()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id          = isset(Flight::request()->data['id']) ? Flight::request()->data['id'] : null;
        $horaReal    = isset(Flight::request()->data['hora_real']) && Flight::request()->data['hora_real'] !== ''
                     ? Flight::request()->data['hora_real']
                     : date('H:i:s');
        $observacion = isset(Flight::request()->data['observacion']) ? Flight::request()->data['observacion'] : null;

        $ocurrencia = self::obtener($db, $id);

        if (!$ocurrencia) {
            Flight::json(array('error' => 'La ocurrencia no existe'), 404);
            return;
        }

        $sentence = $db->prepare("UPDATE solicitudes_ocurrencias SET
                id_estado = :id_estado,
                hora_real = :hora_real,
                id_usuario_cumple = :id_usuario_cumple,
                motivo_no_cumplida = NULL,
                observacion = :observacion
            WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_estado', self::ESTADO_CUMPLIDA, PDO::PARAM_INT);
        $sentence->bindValue(':hora_real', $horaReal);
        $sentence->bindValue(':id_usuario_cumple', isset($userData->id) ? $userData->id : null);
        $sentence->bindValue(':observacion', $observacion);
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        // El aviso al acudiente no puede tumbar el marcado: si el push falla,
        // la ocurrencia ya quedo cumplida.
        MotorSolicitudesAvisos::avisarCumplida($db, $id, isset($userData->id) ? $userData->id : null);

        self::getById($id);
    }

    /**
     * Marca la ocurrencia como no cumplida. El motivo es obligatorio: es lo
     * que despues explica por que no se dio el remedio.
     */
    public static function marcarNoCumplida()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id     = isset(Flight::request()->data['id']) ? Flight::request()->data['id'] : null;
        $motivo = isset(Flight::request()->data['motivo_no_cumplida']) ? trim(Flight::request()->data['motivo_no_cumplida']) : '';

        if ($motivo === '') {
            Flight::json(array('error' => 'El motivo es obligatorio'), 400);
            return;
        }

        $ocurrencia = self::obtener($db, $id);

        if (!$ocurrencia) {
            Flight::json(array('error' => 'La ocurrencia no existe'), 404);
            return;
        }

        $sentence = $db->prepare("UPDATE solicitudes_ocurrencias SET
                id_estado = :id_estado,
                hora_real = NULL,
                id_usuario_cumple = :id_usuario_cumple,
                motivo_no_cumplida = :motivo_no_cumplida
            WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_estado', self::ESTADO_NO_CUMPLIDA, PDO::PARAM_INT);
        $sentence->bindValue(':id_usuario_cumple', isset($userData->id) ? $userData->id : null);
        $sentence->bindValue(':motivo_no_cumplida', $motivo);
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    /**
     * Devuelve la ocurrencia al estado pendiente, por si alguien se equivoco
     * de renglon.
     */
    public static function desmarcar()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id = isset(Flight::request()->data['id']) ? Flight::request()->data['id'] : null;

        $sentence = $db->prepare("UPDATE solicitudes_ocurrencias SET
                id_estado = :id_estado,
                hora_real = NULL,
                id_usuario_cumple = NULL,
                motivo_no_cumplida = NULL
            WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_estado', self::ESTADO_PENDIENTE, PDO::PARAM_INT);
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function replace()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id          = Flight::request()->data['id'];
        $observacion = isset(Flight::request()->data['observacion']) ? Flight::request()->data['observacion'] : null;

        $sentence = $db->prepare("UPDATE solicitudes_ocurrencias SET observacion = :observacion
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':observacion', $observacion);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("DELETE FROM solicitudes_ocurrencias
                                  WHERE id = :id AND id_tenant = :id_tenant AND id_estado = :pendiente");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':pendiente', self::ESTADO_PENDIENTE, PDO::PARAM_INT);
        $sentence->execute();

        if ($sentence->rowCount() === 0) {
            Flight::json(array('error' => 'Solo se pueden eliminar ocurrencias pendientes'), 400);
            return;
        }

        Flight::json(array('id' => $id));
    }

    /**
     * Genera las ocurrencias del rango de la solicitud.
     *
     * Recorre los dias habiles del calendario (asi el jardin que atiende los
     * sabados los genera y el que no, no) y crea una fila por cada hora de la
     * plantilla. Si la solicitud no tiene horas, crea una por dia con la hora
     * en NULL.
     *
     * Es idempotente: la unica (solicitud, fecha, hora) evita duplicados, asi
     * que se puede volver a llamar cuando cambian el rango o las horas. Antes
     * de regenerar borra las PENDIENTES que ya no aplican; las marcadas se
     * respetan siempre, porque son historia.
     *
     * @param  PDO    $db
     * @param  string $idSolicitud
     * @return int    Cantidad de ocurrencias creadas
     */
    public static function generar(PDO $db, $idSolicitud)
    {
        $solicitud = Solicitudes::obtener($db, $idSolicitud);

        if (!$solicitud) {
            return 0;
        }

        $horas = SolicitudesHorarios::listar($db, $idSolicitud);

        if (count($horas) === 0) {
            // Una sola ocurrencia por dia, sin hora.
            $horas = array(null);
        }

        $dias = self::diasHabiles($db, $solicitud['fecha_inicio'], $solicitud['fecha_fin']);

        self::limpiarPendientesFueraDePlan($db, $idSolicitud, $dias, $horas);

        $insertar = $db->prepare("INSERT IGNORE INTO solicitudes_ocurrencias
            (id, id_tenant, id_solicitud, fecha, hora_programada, id_estado)
            VALUES (:id, :id_tenant, :id_solicitud, :fecha, :hora, :id_estado)");

        $creadas = 0;

        foreach ($dias as $dia) {
            foreach ($horas as $hora) {
                $insertar->bindValue(':id', Uuid::generar());
                $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $insertar->bindValue(':id_solicitud', $idSolicitud);
                $insertar->bindValue(':fecha', $dia);
                $insertar->bindValue(':hora', $hora, $hora === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $insertar->bindValue(':id_estado', self::ESTADO_PENDIENTE, PDO::PARAM_INT);
                $insertar->execute();
                $creadas += $insertar->rowCount();
            }
        }

        return $creadas;
    }

    /**
     * Borra las ocurrencias pendientes de una solicitud. La usa la anulacion:
     * lo que ya se cumplio se queda como constancia.
     */
    public static function borrarPendientes(PDO $db, $idSolicitud)
    {
        $sentence = $db->prepare("DELETE FROM solicitudes_ocurrencias
                                  WHERE id_solicitud = :id_solicitud
                                    AND id_tenant = :id_tenant
                                    AND id_estado = :pendiente");
        $sentence->bindParam(':id_solicitud', $idSolicitud);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':pendiente', self::ESTADO_PENDIENTE, PDO::PARAM_INT);
        $sentence->execute();
    }

    /**
     * Lectura interna de una ocurrencia con los datos del tipo y la solicitud
     * que necesitan los avisos.
     *
     * @param  PDO    $db
     * @param  string $id
     * @return array|null
     */
    public static function obtener(PDO $db, $id)
    {
        if (empty($id)) {
            return null;
        }

        $sentence = $db->prepare("SELECT o.*, s.id_estudiante, s.descripcion, s.id_persona_solicita,
                                         s.minutos_anticipacion, s.id_tipo_solicitud,
                                         t.nombre AS tipo_nombre,
                                         t.requiere_confirmacion, t.notifica_acudiente_cumplido
                                  FROM solicitudes_ocurrencias o
                                  INNER JOIN solicitudes s ON s.id = o.id_solicitud
                                  INNER JOIN tipos_solicitud t ON t.id = s.id_tipo_solicitud
                                  WHERE o.id = :id AND o.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila : null;
    }

    /**
     * Dias habiles del rango segun el calendario del jardin.
     *
     * Si el calendario no tiene cargado el rango (por ejemplo, un ano que aun
     * no se ha parametrizado), se devuelven todos los dias: es preferible
     * generar de mas a que la solicitud se quede sin ocurrencias y nadie se
     * entere del remedio.
     *
     * @return array Lista de fechas AAAA-MM-DD
     */
    private static function diasHabiles(PDO $db, $fechaInicio, $fechaFin)
    {
        $sentence = $db->prepare("SELECT fecha FROM calendarios
                                  WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin
                                    AND dia_habil = 1
                                  ORDER BY fecha");
        $sentence->bindParam(':fecha_inicio', $fechaInicio);
        $sentence->bindParam(':fecha_fin', $fechaFin);
        $sentence->execute();

        $dias = array();
        foreach ($sentence->fetchAll() as $fila) {
            $dias[] = $fila['fecha'];
        }

        if (count($dias) > 0) {
            return $dias;
        }

        $sentence = $db->prepare("SELECT COUNT(*) AS cargados FROM calendarios
                                  WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin");
        $sentence->bindParam(':fecha_inicio', $fechaInicio);
        $sentence->bindParam(':fecha_fin', $fechaFin);
        $sentence->execute();
        $fila = $sentence->fetch();

        if ($fila && (int)$fila['cargados'] > 0) {
            // El calendario si tiene el rango y ningun dia es habil.
            return array();
        }

        error_log('[SolicitudesOcurrencias] Calendario sin datos entre ' . $fechaInicio . ' y ' . $fechaFin . '; se generan todos los dias.');

        $dias = array();
        $actual = strtotime($fechaInicio);
        $limite = strtotime($fechaFin);

        while ($actual <= $limite) {
            $dias[] = date('Y-m-d', $actual);
            $actual = strtotime('+1 day', $actual);
        }

        return $dias;
    }

    /**
     * Quita las ocurrencias PENDIENTES que quedaron por fuera del plan actual
     * (porque se recorto el rango o se quito una hora). Nunca toca las que ya
     * tienen marca.
     */
    private static function limpiarPendientesFueraDePlan(PDO $db, $idSolicitud, array $dias, array $horas)
    {
        $sentence = $db->prepare("SELECT id, fecha, hora_programada
                                  FROM solicitudes_ocurrencias
                                  WHERE id_solicitud = :id_solicitud
                                    AND id_tenant = :id_tenant
                                    AND id_estado = :pendiente");
        $sentence->bindParam(':id_solicitud', $idSolicitud);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':pendiente', self::ESTADO_PENDIENTE, PDO::PARAM_INT);
        $sentence->execute();

        $borrar = $db->prepare("DELETE FROM solicitudes_ocurrencias WHERE id = :id AND id_tenant = :id_tenant");

        foreach ($sentence->fetchAll() as $fila) {
            $diaVigente  = in_array($fila['fecha'], $dias, true);
            $horaVigente = in_array($fila['hora_programada'], $horas, true);

            if ($diaVigente && $horaVigente) {
                continue;
            }

            $borrar->bindValue(':id', $fila['id']);
            $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrar->execute();
        }
    }

    /**
     * SELECT comun del detalle, compartido por los listados para que el front
     * maneje un solo modelo.
     */
    private static function sqlDetalle()
    {
        return "SELECT o.id, o.id_solicitud, o.fecha, o.hora_programada, o.id_estado,
                       o.hora_real, o.id_usuario_cumple, o.motivo_no_cumplida, o.observacion,
                       o.id_notificacion_colaborador, o.id_notificacion,
                       s.id_estudiante, s.descripcion, s.id_tipo_solicitud, s.id_documento,
                       s.minutos_anticipacion,
                       t.nombre AS tipo_nombre,
                       t.icono  AS tipo_icono,
                       t.requiere_confirmacion,
                       eo.nombre AS estado_nombre,
                       eo.color  AS estado_color,
                       TRIM(CONCAT(COALESCE(pes.primer_nombre, ''), ' ', COALESCE(pes.primer_apellido, ''))) AS estudiante_nombre,
                       TRIM(CONCAT(COALESCE(pu.primer_nombre, ''), ' ', COALESCE(pu.primer_apellido, ''))) AS cumplio_nombre,
                       (SELECT g.nombre
                          FROM estudiantes_x_grupos exg
                          INNER JOIN grupos g ON g.id = exg.id_grupo
                         WHERE exg.id_estudiante = s.id_estudiante
                           AND exg.activo = 1
                           AND exg.id_tenant = s.id_tenant
                         ORDER BY exg.anio DESC
                         LIMIT 1) AS grupo_nombre
                FROM solicitudes_ocurrencias o
                INNER JOIN solicitudes s        ON s.id = o.id_solicitud
                INNER JOIN tipos_solicitud t    ON t.id = s.id_tipo_solicitud
                INNER JOIN estados_ocurrencia eo ON eo.id = o.id_estado
                INNER JOIN estudiantes est      ON est.id = s.id_estudiante
                INNER JOIN personas pes         ON pes.id = est.id_persona
                LEFT JOIN usuarios u            ON u.id = o.id_usuario_cumple
                LEFT JOIN personas pu           ON pu.id = u.id_persona";
    }
}
