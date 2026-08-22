<?php
/*=============================================
SERVICIO - SOLICITUDES DE ACUDIENTES
Archivo: services/solicitudes.service.php

Tabla principal del modulo. Una fila por lo que pidio el acudiente, sin
importar cuantos dias ni cuantas horas cubra. Del lado del jardin esta
misma fila es "el compromiso": no hay dos tablas.

Aqui vive la logica especifica: armar la lista de personas, generar las
ocurrencias del rango y mover los estados. Las tablas satelite
(horarios, personas, ocurrencias) tienen su propio servicio de CRUD.
=============================================*/

class Solicitudes
{
    const ESTADO_PENDIENTE  = 1;
    const ESTADO_AUTORIZADO = 2;
    const ESTADO_RECHAZADO  = 3;
    const ESTADO_ANULADO    = 4;

    const ORIGEN_ACUDIENTE = 1;
    const ORIGEN_JARDIN    = 2;

    /** Permiso que deja aprobar y crear solicitudes ya autorizadas. */
    const PERMISO_APROBAR = 'operaciones.solicitudes_aprobar';

    /**
     * Listado general del jardin, con filtros opcionales de estado y rango.
     * Es la pantalla de seguimiento, no la agenda del dia.
     */
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $sentence = $db->prepare(self::sqlDetalle() . "
                                  WHERE s.id_tenant = :id_tenant
                                  ORDER BY s.fecha_inicio DESC, s.fecha_registro DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::sqlDetalle() . "
                                  WHERE s.id = :id AND s.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Solicitudes vigentes de un estudiante en una fecha. Es lo que se pinta
     * dentro del panel de asistencia cuando la docente recibe al nino.
     */
    public static function getPorEstudiante($id_estudiante, $fecha)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $sentence = $db->prepare(self::sqlDetalle() . "
                                  WHERE s.id_tenant = :id_tenant
                                    AND s.id_estudiante = :id_estudiante
                                    AND :fecha BETWEEN s.fecha_inicio AND s.fecha_fin
                                    AND s.id_estado IN (:pendiente, :autorizado)
                                  ORDER BY s.fecha_registro");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindValue(':pendiente', self::ESTADO_PENDIENTE, PDO::PARAM_INT);
        $sentence->bindValue(':autorizado', self::ESTADO_AUTORIZADO, PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Solicitudes pendientes de aprobacion que le corresponden al usuario que
     * consulta, por estar en la lista de aprobadores de cada una.
     *
     * Mientras esta pendiente la solicitud NO le sale a los responsables: no
     * hay nada que hacer hasta que alguien la apruebe.
     */
    public static function getPorAprobar()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $idColaborador = self::colaboradorDelUsuario($db, isset($userData->id) ? $userData->id : null);

        if (!$idColaborador) {
            Flight::json(array());
            return;
        }

        $sentence = $db->prepare(self::sqlDetalle() . "
                                  WHERE s.id_tenant = :id_tenant
                                    AND s.id_estado = :pendiente
                                    AND EXISTS (
                                        SELECT 1 FROM solicitudes_personas sp
                                        WHERE sp.id_solicitud = s.id
                                          AND sp.id_colaborador = :id_colaborador
                                          AND sp.id_rol = :rol_aprobador
                                    )
                                  ORDER BY s.fecha_registro");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':pendiente', self::ESTADO_PENDIENTE, PDO::PARAM_INT);
        $sentence->bindParam(':id_colaborador', $idColaborador);
        $sentence->bindValue(':rol_aprobador', SolicitudesPersonas::ROL_APROBADOR, PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Bandeja del acudiente: las solicitudes de sus hijos, con el estado en
     * que van. Solo entran los vinculos con ve_en_portal_padres = 1, mismo
     * criterio que usa la central de notificaciones.
     */
    public static function getMisSolicitudes()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $idUsuario = isset($userData->id) ? $userData->id : null;

        if (!$idUsuario) {
            Flight::json(array('error' => 'No se pudo identificar el usuario'), 401);
            return;
        }

        $sentence = $db->prepare(self::sqlDetalle() . "
                                  INNER JOIN usuarios u ON u.id_persona = s.id_persona_solicita
                                                       AND u.id_tenant = s.id_tenant
                                  WHERE s.id_tenant = :id_tenant
                                    AND u.id = :id_usuario
                                    AND EXISTS (
                                        SELECT 1 FROM acudientes a
                                        WHERE a.id_estudiante = s.id_estudiante
                                          AND a.id_persona = s.id_persona_solicita
                                          AND a.id_tenant = s.id_tenant
                                          AND a.activo = 1
                                          AND a.ve_en_portal_padres = 1
                                    )
                                  ORDER BY s.fecha_inicio DESC, s.fecha_registro DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_usuario', $idUsuario);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Crea la solicitud completa: cabecera, horas, lista de personas y, si
     * ya nace autorizada, las ocurrencias del rango.
     *
     * Todo va en una transaccion porque una solicitud sin sus horas o sin sus
     * responsables no sirve para nada y seria peor dejarla a medias.
     *
     * El estado inicial depende de dos cosas: si el tipo exige aprobacion, y
     * si quien la crea tiene el permiso de aprobar. Quien puede aprobar la
     * crea ya autorizada: iba a aprobarla de todos modos.
     */
    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $data = Flight::request()->data;

        $idEstudiante = isset($data['id_estudiante']) ? $data['id_estudiante'] : null;
        $idTipo       = isset($data['id_tipo_solicitud']) ? $data['id_tipo_solicitud'] : null;
        $descripcion  = isset($data['descripcion']) ? trim($data['descripcion']) : '';
        $fechaInicio  = isset($data['fecha_inicio']) ? $data['fecha_inicio'] : null;
        $fechaFin     = isset($data['fecha_fin']) ? $data['fecha_fin'] : null;
        $idPersona    = isset($data['id_persona_solicita']) ? $data['id_persona_solicita'] : null;
        $idDocumento  = isset($data['id_documento']) && $data['id_documento'] !== '' ? $data['id_documento'] : null;
        $idOrigen     = isset($data['id_origen']) ? (int)$data['id_origen'] : self::ORIGEN_JARDIN;
        $horas        = isset($data['horas']) && is_array($data['horas']) ? $data['horas'] : array();
        $responsables = isset($data['responsables']) && is_array($data['responsables']) ? $data['responsables'] : array();

        if (empty($idEstudiante) || empty($idTipo) || $descripcion === '' || empty($fechaInicio)) {
            Flight::json(array('error' => 'El estudiante, el tipo, la descripcion y la fecha de inicio son obligatorios'), 400);
            return;
        }

        if (empty($fechaFin)) {
            $fechaFin = $fechaInicio;
        }

        if ($fechaFin < $fechaInicio) {
            Flight::json(array('error' => 'La fecha final no puede ser anterior a la inicial'), 400);
            return;
        }

        $tipo = TiposSolicitud::obtener($db, $idTipo);

        if (!$tipo) {
            Flight::json(array('error' => 'El tipo de solicitud no existe'), 400);
            return;
        }

        if ((int)$tipo['activo'] !== 1) {
            Flight::json(array('error' => 'El tipo de solicitud esta inactivo y no se puede usar'), 400);
            return;
        }

        $error = self::validarContraTipo($tipo, $horas, $idDocumento);

        if (!$error) {
            $error = self::validarJornada($db, $horas, $fechaInicio, $fechaFin);
        }

        if ($error) {
            Flight::json(array('error' => $error), 400);
            return;
        }

        if (empty($idPersona)) {
            Flight::json(array('error' => 'Falta el acudiente de parte de quien va la solicitud'), 400);
            return;
        }

        $minutos = isset($data['minutos_anticipacion']) && $data['minutos_anticipacion'] !== '' && $data['minutos_anticipacion'] !== null
                 ? (int)$data['minutos_anticipacion']
                 : ($tipo['minutos_anticipacion'] !== null ? (int)$tipo['minutos_anticipacion'] : null);

        if ((int)$tipo['manejo_horas'] === TiposSolicitud::HORAS_NINGUNA) {
            $minutos = null;
        }

        $puedeAprobar = self::tienePermiso($userData, self::PERMISO_APROBAR);
        $idEstado     = ((int)$tipo['requiere_aprobacion'] === 1 && !$puedeAprobar)
                      ? self::ESTADO_PENDIENTE
                      : self::ESTADO_AUTORIZADO;

        $idUsuario = isset($userData->id) ? $userData->id : null;
        $id = Uuid::generar();

        try {
            $db->beginTransaction();

            $sentence = $db->prepare("INSERT INTO solicitudes
                (id, id_tenant, id_estudiante, id_tipo_solicitud, descripcion,
                 fecha_inicio, fecha_fin, minutos_anticipacion, id_origen,
                 id_persona_solicita, id_usuario_registra, id_documento, id_estado,
                 id_usuario_decide, fecha_decision)
                VALUES
                (:id, :id_tenant, :id_estudiante, :id_tipo_solicitud, :descripcion,
                 :fecha_inicio, :fecha_fin, :minutos_anticipacion, :id_origen,
                 :id_persona_solicita, :id_usuario_registra, :id_documento, :id_estado,
                 :id_usuario_decide, :fecha_decision)");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_estudiante', $idEstudiante);
            $sentence->bindValue(':id_tipo_solicitud', $idTipo);
            $sentence->bindValue(':descripcion', $descripcion);
            $sentence->bindValue(':fecha_inicio', $fechaInicio);
            $sentence->bindValue(':fecha_fin', $fechaFin);
            $sentence->bindValue(':minutos_anticipacion', $minutos, $minutos === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $sentence->bindValue(':id_origen', $idOrigen, PDO::PARAM_INT);
            $sentence->bindValue(':id_persona_solicita', $idPersona);
            $sentence->bindValue(':id_usuario_registra', $idUsuario);
            $sentence->bindValue(':id_documento', $idDocumento, $idDocumento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $sentence->bindValue(':id_estado', $idEstado, PDO::PARAM_INT);
            $sentence->bindValue(':id_usuario_decide', $idEstado === self::ESTADO_AUTORIZADO ? $idUsuario : null);
            $sentence->bindValue(':fecha_decision', $idEstado === self::ESTADO_AUTORIZADO ? date('Y-m-d H:i:s') : null);
            $sentence->execute();

            $orden = 0;
            foreach ($horas as $hora) {
                SolicitudesHorarios::insertar($db, $id, $hora, $orden);
                $orden++;
            }

            $totalResponsables = self::armarPersonas($db, $id, $idTipo, $idEstudiante, $tipo, $responsables);

            if ((int)$tipo['exige_responsable'] === 1 && $totalResponsables === 0) {
                $db->rollBack();
                Flight::json(array('error' => 'Este tipo exige al menos un responsable y no se pudo asignar ninguno. Revise el titular del grupo o los cargos configurados en el tipo.'), 400);
                return;
            }

            if ($idEstado === self::ESTADO_AUTORIZADO) {
                SolicitudesOcurrencias::generar($db, $id);
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Solicitudes::new] ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo crear la solicitud'), 500);
            return;
        }

        // Los avisos van fuera de la transaccion: si el push falla, la
        // solicitud ya quedo guardada y no tiene por que perderse.
        if ($idEstado === self::ESTADO_PENDIENTE) {
            MotorSolicitudesAvisos::avisarPorAprobar($db, $id);
        }

        Flight::json(array('id' => $id, 'id_estado' => $idEstado));
    }

    /**
     * Edicion de la solicitud. El jardin NO edita lo que el papa escribio: lo
     * unico que se toca aqui es lo operativo (minutos de anticipacion) y, en
     * las que aun estan pendientes, el contenido por parte de quien la creo.
     *
     * Cuando cambia el rango o las horas se regeneran las ocurrencias
     * futuras; las ya marcadas se respetan.
     */
    public static function replace()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $data = Flight::request()->data;
        $id   = isset($data['id']) ? $data['id'] : null;

        if (empty($id)) {
            Flight::json(array('error' => 'Falta el id de la solicitud'), 400);
            return;
        }

        $solicitud = self::obtener($db, $id);

        if (!$solicitud) {
            Flight::json(array('error' => 'La solicitud no existe'), 404);
            return;
        }

        if ((int)$solicitud['id_estado'] === self::ESTADO_ANULADO || (int)$solicitud['id_estado'] === self::ESTADO_RECHAZADO) {
            Flight::json(array('error' => 'Una solicitud anulada o rechazada no se puede editar'), 400);
            return;
        }

        $tipo = TiposSolicitud::obtener($db, $solicitud['id_tipo_solicitud']);

        if (!$tipo) {
            Flight::json(array('error' => 'El tipo de solicitud no existe'), 400);
            return;
        }

        $descripcion = isset($data['descripcion']) ? trim($data['descripcion']) : $solicitud['descripcion'];
        $fechaInicio = isset($data['fecha_inicio']) ? $data['fecha_inicio'] : $solicitud['fecha_inicio'];
        $fechaFin    = isset($data['fecha_fin']) && $data['fecha_fin'] !== '' ? $data['fecha_fin'] : $fechaInicio;
        $idDocumento = isset($data['id_documento']) && $data['id_documento'] !== '' ? $data['id_documento'] : $solicitud['id_documento'];
        $horas       = isset($data['horas']) && is_array($data['horas']) ? $data['horas'] : null;

        if ($descripcion === '') {
            Flight::json(array('error' => 'La descripcion es obligatoria'), 400);
            return;
        }

        if ($fechaFin < $fechaInicio) {
            Flight::json(array('error' => 'La fecha final no puede ser anterior a la inicial'), 400);
            return;
        }

        if ($horas !== null) {
            $error = self::validarContraTipo($tipo, $horas, $idDocumento);

            if (!$error) {
                $error = self::validarJornada($db, $horas, $fechaInicio, $fechaFin);
            }

            if ($error) {
                Flight::json(array('error' => $error), 400);
                return;
            }
        }

        $minutos = isset($data['minutos_anticipacion']) && $data['minutos_anticipacion'] !== '' && $data['minutos_anticipacion'] !== null
                 ? (int)$data['minutos_anticipacion']
                 : ($solicitud['minutos_anticipacion'] !== null ? (int)$solicitud['minutos_anticipacion'] : null);

        if ((int)$tipo['manejo_horas'] === TiposSolicitud::HORAS_NINGUNA) {
            $minutos = null;
        }

        try {
            $db->beginTransaction();

            $sentence = $db->prepare("UPDATE solicitudes SET
                    descripcion = :descripcion,
                    fecha_inicio = :fecha_inicio,
                    fecha_fin = :fecha_fin,
                    minutos_anticipacion = :minutos_anticipacion,
                    id_documento = :id_documento
                WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':descripcion', $descripcion);
            $sentence->bindValue(':fecha_inicio', $fechaInicio);
            $sentence->bindValue(':fecha_fin', $fechaFin);
            $sentence->bindValue(':minutos_anticipacion', $minutos, $minutos === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $sentence->bindValue(':id_documento', $idDocumento, $idDocumento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($horas !== null) {
                SolicitudesHorarios::borrarPorSolicitud($db, $id);
                $orden = 0;
                foreach ($horas as $hora) {
                    SolicitudesHorarios::insertar($db, $id, $hora, $orden);
                    $orden++;
                }
            }

            if ((int)$solicitud['id_estado'] === self::ESTADO_AUTORIZADO) {
                SolicitudesOcurrencias::generar($db, $id);
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Solicitudes::replace] ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo actualizar la solicitud'), 500);
            return;
        }

        self::getById($id);
    }

    /**
     * Aprueba una solicitud pendiente y genera sus ocurrencias. Cualquiera de
     * la lista de aprobadores puede hacerlo.
     */
    public static function aprobar()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id = isset(Flight::request()->data['id']) ? Flight::request()->data['id'] : null;
        $solicitud = self::obtener($db, $id);

        if (!$solicitud) {
            Flight::json(array('error' => 'La solicitud no existe'), 404);
            return;
        }

        if ((int)$solicitud['id_estado'] !== self::ESTADO_PENDIENTE) {
            Flight::json(array('error' => 'Solo se puede aprobar una solicitud pendiente'), 400);
            return;
        }

        try {
            $db->beginTransaction();
            self::moverEstado($db, $id, self::ESTADO_AUTORIZADO, null, isset($userData->id) ? $userData->id : null);
            SolicitudesOcurrencias::generar($db, $id);
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Solicitudes::aprobar] ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo aprobar la solicitud'), 500);
            return;
        }

        Flight::json(array('id' => $id, 'id_estado' => self::ESTADO_AUTORIZADO));
    }

    /**
     * Rechaza con motivo obligatorio y avisa al acudiente. El rechazo es
     * terminal: para corregir, el acudiente crea una solicitud nueva.
     */
    public static function rechazar()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id     = isset(Flight::request()->data['id']) ? Flight::request()->data['id'] : null;
        $motivo = isset(Flight::request()->data['motivo_rechazo']) ? trim(Flight::request()->data['motivo_rechazo']) : '';

        if ($motivo === '') {
            Flight::json(array('error' => 'El motivo del rechazo es obligatorio'), 400);
            return;
        }

        $solicitud = self::obtener($db, $id);

        if (!$solicitud) {
            Flight::json(array('error' => 'La solicitud no existe'), 404);
            return;
        }

        if ((int)$solicitud['id_estado'] !== self::ESTADO_PENDIENTE) {
            Flight::json(array('error' => 'Solo se puede rechazar una solicitud pendiente'), 400);
            return;
        }

        self::moverEstado($db, $id, self::ESTADO_RECHAZADO, $motivo, isset($userData->id) ? $userData->id : null);

        MotorSolicitudesAvisos::avisarRechazo($db, $id, $motivo, isset($userData->id) ? $userData->id : null);

        Flight::json(array('id' => $id, 'id_estado' => self::ESTADO_RECHAZADO));
    }

    /**
     * Anula la solicitud. Las ocurrencias ya marcadas se quedan como
     * constancia; solo se caen las que seguian pendientes.
     */
    public static function anular()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id = isset(Flight::request()->data['id']) ? Flight::request()->data['id'] : null;
        $solicitud = self::obtener($db, $id);

        if (!$solicitud) {
            Flight::json(array('error' => 'La solicitud no existe'), 404);
            return;
        }

        if ((int)$solicitud['id_estado'] === self::ESTADO_ANULADO) {
            Flight::json(array('error' => 'La solicitud ya esta anulada'), 400);
            return;
        }

        try {
            $db->beginTransaction();
            self::moverEstado($db, $id, self::ESTADO_ANULADO, null, isset($userData->id) ? $userData->id : null);
            SolicitudesOcurrencias::borrarPendientes($db, $id);
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Solicitudes::anular] ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo anular la solicitud'), 500);
            return;
        }

        Flight::json(array('id' => $id, 'id_estado' => self::ESTADO_ANULADO));
    }

    /**
     * Borrado fisico. Solo se permite si la solicitud nunca genero una
     * ocurrencia marcada: si el nino ya se tomo una dosis, eso es historia y
     * la via correcta es anular.
     */
    public static function delete()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("SELECT COUNT(*) AS marcadas
                                  FROM solicitudes_ocurrencias
                                  WHERE id_solicitud = :id
                                    AND id_tenant = :id_tenant
                                    AND id_estado <> :pendiente");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':pendiente', SolicitudesOcurrencias::ESTADO_PENDIENTE, PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        if ($fila && $fila['marcadas'] > 0) {
            Flight::json(array('error' => 'La solicitud ya tiene registros marcados y no se puede eliminar. Anulela en su lugar.'), 400);
            return;
        }

        // Horarios, personas y ocurrencias caen por la FK en cascada.
        $sentence = $db->prepare("DELETE FROM solicitudes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Lectura interna de una solicitud, sin responder al cliente.
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

        $sentence = $db->prepare("SELECT * FROM solicitudes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila : null;
    }

    /**
     * Colaborador asociado al usuario que esta consultando. Es lo que permite
     * saber si esta o no en la lista de responsables de una solicitud.
     *
     * @param  PDO    $db
     * @param  string $idUsuario
     * @return string|null id_colaborador o null si el usuario no es colaborador
     */
    public static function colaboradorDelUsuario(PDO $db, $idUsuario)
    {
        if (empty($idUsuario)) {
            return null;
        }

        $sentence = $db->prepare("SELECT col.id
                                  FROM usuarios u
                                  INNER JOIN colaboradores col ON col.id_persona = u.id_persona
                                                              AND col.id_tenant = u.id_tenant
                                                              AND col.activo = 1
                                  WHERE u.id = :id_usuario AND u.id_tenant = :id_tenant
                                  LIMIT 1");
        $sentence->bindParam(':id_usuario', $idUsuario);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila['id'] : null;
    }

    /**
     * Cambia el estado dejando la traza de quien decidio y cuando.
     */
    private static function moverEstado(PDO $db, $id, $idEstado, $motivo, $idUsuario)
    {
        $sentence = $db->prepare("UPDATE solicitudes SET
                id_estado = :id_estado,
                motivo_rechazo = :motivo_rechazo,
                id_usuario_decide = :id_usuario_decide,
                fecha_decision = :fecha_decision
            WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_estado', $idEstado, PDO::PARAM_INT);
        $sentence->bindValue(':motivo_rechazo', $motivo);
        $sentence->bindValue(':id_usuario_decide', $idUsuario);
        $sentence->bindValue(':fecha_decision', date('Y-m-d H:i:s'));
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    /**
     * Arma y congela la lista de personas de la solicitud: el titular del
     * grupo si el tipo lo pide, los colaboradores de los cargos configurados,
     * y los responsables que hayan escogido a mano en la pantalla.
     *
     * No se recalcula nunca mas. Es lo que evita que alguien que entre el
     * proximo mes vea los tratamientos de hace medio ano.
     *
     * @return int Cantidad de responsables que quedaron en la lista
     */
    private static function armarPersonas(PDO $db, $idSolicitud, $idTipo, $idEstudiante, $tipo, array $responsablesManuales)
    {
        $titular = SolicitudesPersonas::titularDelEstudiante($db, $idEstudiante);

        $responsables = array();
        $aprobadores  = array();

        if ((int)$tipo['titular_es_responsable'] === 1 && $titular) {
            $responsables[$titular] = true;
        }

        if ((int)$tipo['titular_es_aprobador'] === 1 && $titular) {
            $aprobadores[$titular] = true;
        }

        foreach (TiposSolicitudCargos::colaboradoresPorTipoRol($db, $idTipo, SolicitudesPersonas::ROL_RESPONSABLE) as $idColaborador) {
            $responsables[$idColaborador] = true;
        }

        foreach (TiposSolicitudCargos::colaboradoresPorTipoRol($db, $idTipo, SolicitudesPersonas::ROL_APROBADOR) as $idColaborador) {
            $aprobadores[$idColaborador] = true;
        }

        foreach ($responsablesManuales as $idColaborador) {
            if (!empty($idColaborador)) {
                $responsables[$idColaborador] = true;
            }
        }

        foreach (array_keys($responsables) as $idColaborador) {
            SolicitudesPersonas::insertar($db, $idSolicitud, $idColaborador, SolicitudesPersonas::ROL_RESPONSABLE);
        }

        foreach (array_keys($aprobadores) as $idColaborador) {
            SolicitudesPersonas::insertar($db, $idSolicitud, $idColaborador, SolicitudesPersonas::ROL_APROBADOR);
        }

        return count($responsables);
    }

    /**
     * Valida el cuerpo de la solicitud contra las banderas del tipo.
     *
     * @return string|null Mensaje de error, o null si todo esta bien
     */
    private static function validarContraTipo($tipo, array $horas, $idDocumento)
    {
        $manejoHoras = (int)$tipo['manejo_horas'];
        $totalHoras  = count($horas);

        if ($manejoHoras === TiposSolicitud::HORAS_NINGUNA && $totalHoras > 0) {
            return 'Este tipo de solicitud no maneja horas';
        }

        if ($manejoHoras === TiposSolicitud::HORAS_UNA && $totalHoras !== 1) {
            return 'Este tipo de solicitud necesita exactamente una hora';
        }

        if ($manejoHoras === TiposSolicitud::HORAS_VARIAS && $totalHoras === 0) {
            return 'Debe indicar al menos una hora';
        }

        if ((int)$tipo['documento'] === TiposSolicitud::DOC_OBLIGATORIO && empty($idDocumento)) {
            return 'Este tipo de solicitud exige adjuntar el soporte';
        }

        if ((int)$tipo['documento'] === TiposSolicitud::DOC_NO && !empty($idDocumento)) {
            return 'Este tipo de solicitud no admite soporte adjunto';
        }

        return null;
    }

    /**
     * Valida que las horas pedidas caigan dentro de la jornada del jardin.
     *
     * La jornada vive en jornada_laboral, por tenant y por dia, asi que un
     * sabado puede cerrar antes que un miercoles y cada jardin tiene la suya.
     * Se revisa contra cada dia del rango: si una hora no cabe en alguno, se
     * rechaza.
     *
     * La validacion tambien esta en la pantalla, pero se repite aqui porque
     * el front no es el unico que puede llamar el servicio.
     *
     * @param  PDO    $db
     * @param  array  $horas Horas en formato HH:MM o HH:MM:SS
     * @param  string $fechaInicio
     * @param  string $fechaFin
     * @return string|null Mensaje de error, o null si todo cabe
     */
    private static function validarJornada(PDO $db, array $horas, $fechaInicio, $fechaFin)
    {
        // No se aceptan solicitudes para una fecha que ya paso: no hay forma
        // de cumplirlas.
        if ($fechaInicio < date('Y-m-d')) {
            return 'No se pueden registrar solicitudes para una fecha pasada';
        }

        if (count($horas) === 0) {
            return null;
        }

        // La jornada sale de jornada_laboral, que es por tenant, y cae a
        // dias_semana cuando el jardin aun no la ha configurado.
        $jornadas = JornadaLaboral::obtenerPorDia($db);

        if (count($jornadas) === 0) {
            // Sin jornada configurada no se bloquea: seria peor impedir que
            // registren el compromiso.
            return null;
        }

        $actual = strtotime($fechaInicio);
        $limite = strtotime($fechaFin);

        while ($actual <= $limite) {
            // date('N') devuelve 1 para lunes y 7 para domingo, igual que los
            // ids de dias_semana.
            $idDia = (int)date('N', $actual);

            if (isset($jornadas[$idDia])) {
                $jornada = $jornadas[$idDia];

                if ((int)$jornada['atiende'] !== 1) {
                    return 'El jardin no atiende los ' . $jornada['nombre'] . ', no se pueden pedir horas ese dia';
                }

                $entrada = substr($jornada['hora_entrada'], 0, 5);
                $salida  = substr($jornada['hora_salida'], 0, 5);

                $fechaDia = date('Y-m-d', $actual);

                foreach ($horas as $hora) {
                    $corta = substr(trim((string)$hora), 0, 5);

                    if ($fechaDia === date('Y-m-d') && $corta <= date('H:i')) {
                        return 'La hora ' . $corta . ' ya paso, escoja una hora posterior';
                    }

                    if ($corta < $entrada || $corta > $salida) {
                        return 'La hora ' . $corta . ' esta fuera de la jornada del jardin para el '
                             . $jornada['nombre'] . ' (' . $entrada . ' a ' . $salida . ')';
                    }
                }
            }

            $actual = strtotime('+1 day', $actual);
        }

        return null;
    }

    /**
     * Revisa un permiso dentro del token. super_admin lo tiene todo.
     */
    private static function tienePermiso($userData, $codigo)
    {
        if (isset($userData->super_admin) && (int)$userData->super_admin === 1) {
            return true;
        }

        if (!isset($userData->permisos) || !is_array($userData->permisos)) {
            return false;
        }

        return in_array($codigo, $userData->permisos, true);
    }

    /**
     * SELECT comun del detalle. Se comparte entre los listados para que todos
     * devuelvan las mismas columnas y el front tenga un solo modelo.
     */
    private static function sqlDetalle()
    {
        return "SELECT s.id, s.id_estudiante, s.id_tipo_solicitud, s.descripcion,
                       s.fecha_inicio, s.fecha_fin, s.minutos_anticipacion,
                       s.id_origen, s.id_persona_solicita, s.id_usuario_registra,
                       s.id_documento, s.id_estado, s.motivo_rechazo,
                       s.id_usuario_decide, s.fecha_decision, s.fecha_registro,
                       t.nombre  AS tipo_nombre,
                       t.icono   AS tipo_icono,
                       t.manejo_horas,
                       t.documento,
                       t.requiere_confirmacion,
                       t.requiere_aprobacion,
                       e.nombre  AS estado_nombre,
                       e.color   AS estado_color,
                       o.nombre  AS origen_nombre,
                       TRIM(CONCAT(COALESCE(pes.primer_nombre, ''), ' ', COALESCE(pes.primer_apellido, ''))) AS estudiante_nombre,
                       TRIM(CONCAT(COALESCE(pac.primer_nombre, ''), ' ', COALESCE(pac.primer_apellido, ''))) AS acudiente_nombre,
                       (SELECT g.nombre
                          FROM estudiantes_x_grupos exg
                          INNER JOIN grupos g ON g.id = exg.id_grupo
                         WHERE exg.id_estudiante = s.id_estudiante
                           AND exg.activo = 1
                           AND exg.id_tenant = s.id_tenant
                         ORDER BY exg.anio DESC
                         LIMIT 1) AS grupo_nombre
                FROM solicitudes s
                INNER JOIN tipos_solicitud t     ON t.id = s.id_tipo_solicitud
                INNER JOIN estados_solicitud e   ON e.id = s.id_estado
                INNER JOIN origenes_solicitud o  ON o.id = s.id_origen
                INNER JOIN estudiantes est       ON est.id = s.id_estudiante
                INNER JOIN personas pes          ON pes.id = est.id_persona
                INNER JOIN personas pac          ON pac.id = s.id_persona_solicita";
    }
}
