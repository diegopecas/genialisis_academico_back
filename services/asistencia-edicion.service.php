<?php
/*=============================================
SERVICIO - EDICION DE REGISTROS DE ASISTENCIA
Archivo: services/asistencia-edicion.service.php

Permite corregir o eliminar un movimiento de asistencia ya
registrado, con todo lo que arrastra: utiles del dia, observaciones
automaticas del observador y cobros automaticos generados.

Reglas del modulo:
  - Si alguno de los cobros generados ya tiene un pago aplicado, el
    movimiento queda bloqueado: no se edita ni se elimina. Tocarlo
    descuadraria la cartera.
  - Al cambiar las horas se borran los cobros anteriores; el front
    vuelve a evaluarlos y a ejecutarlos con la hora nueva usando el
    mismo motor que la pantalla de asistencia.
  - La eliminacion es fisica y arrastra todo. Va con permiso aparte.
  - Al acudiente se le avisa, con un mensaje propio de correccion o
    de eliminacion (NotificacionesAsistencia).
=============================================*/

class AsistenciaEdicion
{
    private static function setTimeZone()
    {
        date_default_timezone_set('America/Bogota');
        Flight::db()->exec("SET time_zone = '-05:00'");
    }

    private static function normalizarFecha($fecha)
    {
        if ($fecha === null || trim($fecha) === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($fecha))) {
            return date('Y-m-d');
        }
        return trim($fecha);
    }

    /**
     * Une fecha y hora. Devuelve null si la hora viene vacia, que es como se
     * indica "este movimiento no tiene salida todavia".
     */
    private static function construirFechaMovimiento($fecha, $hora)
    {
        if ($hora === null || trim($hora) === '') {
            return null;
        }

        $hora = trim($hora);

        if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $hora)) {
            return null;
        }

        if (strlen($hora) === 5) {
            $hora .= ':00';
        }

        return self::normalizarFecha($fecha) . ' ' . $hora;
    }

    /**
     * Movimientos de una fecha.
     * POST /asistencia-edicion/listado  { fecha, id_grupo (opcional) }
     *
     * Se filtra por la fecha del ingreso, que es como la usuaria piensa el
     * dia: "el registro del martes".
     */
    public static function getListado()
    {
        JWTService::requerirAutenticacion();
        self::setTimeZone();
        $db = Flight::db();

        $data     = Flight::request()->data;
        $fecha    = self::normalizarFecha(isset($data['fecha']) ? $data['fecha'] : null);
        $id_grupo = isset($data['id_grupo']) && $data['id_grupo'] !== '' ? $data['id_grupo'] : null;

        $sql = "SELECT ae.id, ae.id_estudiante, ae.fecha_ingreso, ae.fecha_salida,
                       ae.observacion_ingreso, ae.observacion_salida,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS estudiante,
                       g.id AS id_grupo, g.nombre AS nombre_grupo, g.icono, g.color,
                       CASE WHEN ae.id_usuario_ingreso IS NOT NULL
                            THEN CONCAT(p_ui.primer_nombre, ' ', p_ui.primer_apellido) END AS usuario_ingreso,
                       CASE WHEN ae.id_usuario_salida IS NOT NULL
                            THEN CONCAT(p_us.primer_nombre, ' ', p_us.primer_apellido) END AS usuario_salida,
                       (SELECT COUNT(*) FROM cobros_automaticos_historial h
                         WHERE h.id_asistencia_estudiante = ae.id
                           AND h.id_tenant = ae.id_tenant) AS cobros_generados,
                       (SELECT COUNT(*) FROM utiles_diarios_registro u
                         WHERE u.id_estudiante = ae.id_estudiante
                           AND u.fecha = DATE(ae.fecha_ingreso)
                           AND u.id_tenant = ae.id_tenant) AS utiles_registrados,
                       (SELECT COUNT(*)
                          FROM cobros_automaticos_historial h2
                          INNER JOIN cuenta_pagada cp ON cp.id_cuenta_por_cobrar = h2.id_cuenta_por_cobrar
                         WHERE h2.id_asistencia_estudiante = ae.id
                           AND h2.id_tenant = ae.id_tenant) AS cobros_con_pago
                FROM asistencia_estudiantes ae
                INNER JOIN estudiantes e ON e.id = ae.id_estudiante
                INNER JOIN personas p ON p.id = e.id_persona
                INNER JOIN estudiantes_x_grupos exg ON exg.id_estudiante = e.id AND exg.activo = 1
                INNER JOIN grupos g ON g.id = exg.id_grupo
                LEFT JOIN usuarios u_ing ON u_ing.id = ae.id_usuario_ingreso
                LEFT JOIN personas p_ui ON p_ui.id = u_ing.id_persona
                LEFT JOIN usuarios u_sal ON u_sal.id = ae.id_usuario_salida
                LEFT JOIN personas p_us ON p_us.id = u_sal.id_persona
                WHERE ae.id_tenant = :id_tenant
                  AND DATE(ae.fecha_ingreso) = :fecha";

        if ($id_grupo !== null) {
            $sql .= " AND g.id = :id_grupo";
        }

        $sql .= " ORDER BY ae.fecha_ingreso, p.primer_nombre, p.primer_apellido";

        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':fecha', $fecha);
        if ($id_grupo !== null) {
            $sentence->bindValue(':id_grupo', $id_grupo);
        }
        $sentence->execute();

        $filas = $sentence->fetchAll();

        // Se arman columnas ya formateadas para que la tabla del front no
        // tenga que calcular nada.
        foreach ($filas as &$f) {
            $f['hora_ingreso'] = !empty($f['fecha_ingreso']) ? date('H:i', strtotime($f['fecha_ingreso'])) : '';
            $f['hora_salida']  = !empty($f['fecha_salida'])  ? date('H:i', strtotime($f['fecha_salida']))  : '';
            $f['bloqueado']    = intval($f['cobros_con_pago']) > 0 ? 1 : 0;
        }
        unset($f);

        Flight::json(array('fecha' => $fecha, 'movimientos' => $filas));
    }

    /**
     * Detalle de un movimiento con todo lo que arrastra.
     * GET /asistencia-edicion/@id
     */
    public static function getById($id)
    {
        JWTService::requerirAutenticacion();
        self::setTimeZone();
        $db = Flight::db();

        $movimiento = self::obtenerMovimiento($db, $id);

        if (!$movimiento) {
            Flight::json(array('error' => 'El registro de asistencia no existe'), 404);
            return;
        }

        $fecha = date('Y-m-d', strtotime($movimiento['fecha_ingreso']));

        Flight::json(array(
            'movimiento' => array(
                'id'                  => $movimiento['id'],
                'id_estudiante'       => $movimiento['id_estudiante'],
                'estudiante'          => $movimiento['estudiante'],
                'nombre_grupo'        => $movimiento['nombre_grupo'],
                'icono'               => $movimiento['icono'],
                'color'               => $movimiento['color'],
                'fecha'               => $fecha,
                'fecha_ingreso'       => $movimiento['fecha_ingreso'],
                'fecha_salida'        => $movimiento['fecha_salida'],
                'hora_ingreso'        => date('H:i', strtotime($movimiento['fecha_ingreso'])),
                'hora_salida'         => !empty($movimiento['fecha_salida']) ? date('H:i', strtotime($movimiento['fecha_salida'])) : '',
                'observacion_ingreso' => $movimiento['observacion_ingreso'],
                'observacion_salida'  => $movimiento['observacion_salida']
            ),
            'utiles'    => self::obtenerUtiles($db, $movimiento['id_estudiante'], $fecha),
            'cobros'    => self::obtenerCobros($db, $id),
            'bloqueado' => self::tienePagosAplicados($db, $id) ? 1 : 0
        ));
    }

    /**
     * Guarda la correccion.
     * PUT /asistencia-edicion
     *   { id, hora_ingreso, hora_salida, observacion_ingreso, observacion_salida,
     *     utiles: [ { id, trajo, regreso } ], id_usuario }
     *
     * Devuelve `recalcular_cobros` en true cuando las horas cambiaron y se
     * borraron los cobros anteriores: el front debe volver a evaluar y
     * ejecutar con la hora nueva antes de avisarle al acudiente.
     */
    public static function replace()
    {
        JWTService::requerirAutenticacion();
        self::setTimeZone();
        $db = Flight::db();

        $data       = Flight::request()->data;
        $id         = isset($data['id']) ? $data['id'] : null;
        $id_usuario = isset($data['id_usuario']) ? $data['id_usuario'] : null;

        if (empty($id)) {
            Flight::json(array('error' => 'Falta el registro a modificar'), 400);
            return;
        }

        $movimiento = self::obtenerMovimiento($db, $id);

        if (!$movimiento) {
            Flight::json(array('error' => 'El registro de asistencia no existe'), 404);
            return;
        }

        if (self::tienePagosAplicados($db, $id)) {
            Flight::json(array('error' => 'Este registro tiene cobros con pagos aplicados y no se puede modificar'), 409);
            return;
        }

        $fecha = date('Y-m-d', strtotime($movimiento['fecha_ingreso']));

        $fechaIngreso = self::construirFechaMovimiento($fecha, isset($data['hora_ingreso']) ? $data['hora_ingreso'] : null);
        $fechaSalida  = self::construirFechaMovimiento($fecha, isset($data['hora_salida']) ? $data['hora_salida'] : null);

        if ($fechaIngreso === null) {
            Flight::json(array('error' => 'La hora de ingreso es obligatoria y debe venir en formato HH:MM'), 400);
            return;
        }

        if ($fechaSalida !== null && strtotime($fechaSalida) < strtotime($fechaIngreso)) {
            Flight::json(array('error' => 'La hora de salida no puede ser anterior a la de ingreso'), 400);
            return;
        }

        // Si las horas no se movieron no hace falta tocar los cobros: se
        // conservan tal cual quedaron el dia del registro.
        $cambioIngreso = $fechaIngreso !== $movimiento['fecha_ingreso'];
        $cambioSalida  = $fechaSalida !== $movimiento['fecha_salida'];
        $cambiaronHoras = $cambioIngreso || $cambioSalida;

        $db->beginTransaction();

        try {
            $sentence = $db->prepare("UPDATE asistencia_estudiantes
                                      SET fecha_ingreso = :fecha_ingreso,
                                          fecha_salida = :fecha_salida,
                                          observacion_ingreso = :observacion_ingreso,
                                          observacion_salida = :observacion_salida
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':fecha_ingreso', $fechaIngreso);
            $sentence->bindValue(':fecha_salida', $fechaSalida);
            $sentence->bindValue(':observacion_ingreso', isset($data['observacion_ingreso']) && trim($data['observacion_ingreso']) !== '' ? trim($data['observacion_ingreso']) : null);
            $sentence->bindValue(':observacion_salida', isset($data['observacion_salida']) && trim($data['observacion_salida']) !== '' ? trim($data['observacion_salida']) : null);
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            // Utiles del dia. Se pasan todos por guardarDesdeAsistencia, que ya
            // resuelve crear los que no existian y actualizar los que si, con
            // la misma clave que usa la pantalla de asistencia. Los que la
            // usuaria deje sin verificar y sin nombre no se crean.
            $utilesActualizados = self::guardarUtiles(
                $db,
                $movimiento['id_estudiante'],
                $fecha,
                $id,
                isset($data['utiles']) ? $data['utiles'] : array(),
                $id_usuario
            );

            $cobrosEliminados = 0;
            if ($cambiaronHoras) {
                $cobrosEliminados = self::borrarCobros($db, $id);
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[AsistenciaEdicion] replace: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo guardar la corrección'), 500);
            return;
        }

        Flight::json(array(
            'id'                  => $id,
            'id_estudiante'       => $movimiento['id_estudiante'],
            'fecha'               => $fecha,
            'utiles_actualizados' => $utilesActualizados,
            'cobros_eliminados'   => $cobrosEliminados,
            'recalcular_cobros'   => $cambiaronHoras ? 1 : 0,
            'tiene_salida'        => $fechaSalida !== null ? 1 : 0
        ));
    }

    /**
     * Avisa al acudiente que el registro se corrigio.
     * POST /asistencia-edicion/notificar  { id, id_usuario }
     *
     * Va aparte del PUT porque cuando las horas cambian el front tiene que
     * regenerar los cobros primero: asi el mensaje sale con los cobros
     * definitivos y no con los viejos.
     */
    public static function notificarCorreccion()
    {
        JWTService::requerirAutenticacion();
        self::setTimeZone();
        $db = Flight::db();

        $data       = Flight::request()->data;
        $id         = isset($data['id']) ? $data['id'] : null;
        $id_usuario = isset($data['id_usuario']) ? $data['id_usuario'] : null;

        if (empty($id)) {
            Flight::json(array('error' => 'Falta el registro'), 400);
            return;
        }

        $notificacion = NotificacionesAsistencia::enviar(
            $db,
            $id,
            NotificacionesAsistencia::TIPO_CORRECCION,
            $id_usuario
        );

        Flight::json(array('notificacion' => $notificacion));
    }

    /**
     * Elimina el movimiento y todo lo que colgaba de el.
     * DELETE /asistencia-edicion  { id, id_usuario }
     *
     * Orden importante: primero se avisa al acudiente, porque el mensaje se
     * arma leyendo la fila que esta a punto de desaparecer.
     */
    public static function delete()
    {
        JWTService::requerirAutenticacion();
        self::setTimeZone();
        $db = Flight::db();

        $data       = Flight::request()->data;
        $id         = isset($data['id']) ? $data['id'] : null;
        $id_usuario = isset($data['id_usuario']) ? $data['id_usuario'] : null;

        if (empty($id)) {
            Flight::json(array('error' => 'Falta el registro a eliminar'), 400);
            return;
        }

        $movimiento = self::obtenerMovimiento($db, $id);

        if (!$movimiento) {
            Flight::json(array('error' => 'El registro de asistencia no existe'), 404);
            return;
        }

        if (self::tienePagosAplicados($db, $id)) {
            Flight::json(array('error' => 'Este registro tiene cobros con pagos aplicados y no se puede eliminar'), 409);
            return;
        }

        $fecha = date('Y-m-d', strtotime($movimiento['fecha_ingreso']));

        $notificacion = NotificacionesAsistencia::enviar(
            $db,
            $id,
            NotificacionesAsistencia::TIPO_ELIMINACION,
            $id_usuario
        );

        $db->beginTransaction();

        try {
            $cobrosEliminados = self::borrarCobros($db, $id);

            // Utiles del dia del estudiante.
            $sentence = $db->prepare("DELETE FROM utiles_diarios_registro
                                      WHERE id_tenant = :id_tenant
                                        AND id_estudiante = :id_estudiante
                                        AND fecha = :fecha");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_estudiante', $movimiento['id_estudiante']);
            $sentence->bindValue(':fecha', $fecha);
            $sentence->execute();
            $utilesEliminados = $sentence->rowCount();

            // Observaciones automaticas de ingreso y salida de ese dia. No hay
            // llave hacia el movimiento, asi que se identifican por estudiante,
            // fecha y tipo, que es como las crea ObservacionesEstudiantes.
            $sentence = $db->prepare("DELETE oe FROM observaciones_estudiantes oe
                                      INNER JOIN tipos_observaciones_estudiantes toe
                                              ON toe.id = oe.id_tipo_observacion_estudiante
                                      WHERE oe.id_tenant = :id_tenant
                                        AND oe.id_estudiante = :id_estudiante
                                        AND oe.fecha = :fecha
                                        AND toe.codigo IN ('ingreso', 'salida')");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_estudiante', $movimiento['id_estudiante']);
            $sentence->bindValue(':fecha', $fecha);
            $sentence->execute();
            $observacionesEliminadas = $sentence->rowCount();

            $sentence = $db->prepare("DELETE FROM asistencia_estudiantes
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[AsistenciaEdicion] delete: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo eliminar el registro'), 500);
            return;
        }

        Flight::json(array(
            'eliminado'               => true,
            'cobros_eliminados'       => $cobrosEliminados,
            'utiles_eliminados'       => $utilesEliminados,
            'observaciones_eliminadas'=> $observacionesEliminadas,
            'notificacion'            => $notificacion
        ));
    }

    /**
     * Guarda los utiles del dia: crea los que faltaban y actualiza los que ya
     * estaban.
     *
     * `trajo` y la observacion los maneja guardarDesdeAsistencia, que tiene el
     * ON DUPLICATE KEY con la clave del util. `regreso` toca aparte, porque
     * ese metodo no lo escribe: se resuelve leyendo las filas del dia despues
     * de guardar y cruzandolas por util del catalogo o por nombre libre.
     */
    private static function guardarUtiles($db, $id_estudiante, $fecha, $id_asistencia, $utiles, $id_usuario)
    {
        if (!is_array($utiles) || count($utiles) === 0) {
            return 0;
        }

        // Un util del catalogo que sigue sin verificar y nunca se guardo no se
        // crea: dejaria filas vacias por cada dia que alguien abra la pantalla.
        $aGuardar = array();
        foreach ($utiles as $util) {
            $sinRegistrar = empty($util['id']);
            $sinEstado = !isset($util['trajo']) || $util['trajo'] === null || $util['trajo'] === '';
            $sinRegreso = !isset($util['regreso']) || $util['regreso'] === null || $util['regreso'] === '';
            $sinNota = !isset($util['observacion']) || trim((string) $util['observacion']) === '';

            if ($sinRegistrar && $sinEstado && $sinRegreso && $sinNota) {
                continue;
            }

            $aGuardar[] = $util;
        }

        if (count($aGuardar) === 0) {
            return 0;
        }

        $guardados = RegistroUtilesDiarios::guardarDesdeAsistencia(
            $db,
            $id_estudiante,
            $fecha,
            $id_asistencia,
            $aGuardar,
            $id_usuario
        );

        // Ahora si el regreso, sobre las filas que quedaron en la base.
        $sentence = $db->prepare("SELECT id, id_util_diario, nombre_libre
                                  FROM utiles_diarios_registro
                                  WHERE id_tenant = :id_tenant
                                    AND id_estudiante = :id_estudiante
                                    AND fecha = :fecha");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':fecha', $fecha);
        $sentence->execute();

        $porCatalogo = array();
        $porNombre = array();
        foreach ($sentence->fetchAll() as $fila) {
            if (!empty($fila['id_util_diario'])) {
                $porCatalogo[$fila['id_util_diario']] = $fila['id'];
            } elseif (!empty($fila['nombre_libre'])) {
                $porNombre[mb_strtolower(trim($fila['nombre_libre']))] = $fila['id'];
            }
        }

        $sentenceRegreso = $db->prepare("UPDATE utiles_diarios_registro
                                         SET regreso = :regreso
                                         WHERE id = :id AND id_tenant = :id_tenant");

        foreach ($aGuardar as $util) {
            $idFila = null;

            if (!empty($util['id_util_diario']) && isset($porCatalogo[$util['id_util_diario']])) {
                $idFila = $porCatalogo[$util['id_util_diario']];
            } elseif (!empty($util['nombre_libre'])) {
                $clave = mb_strtolower(trim($util['nombre_libre']));
                $idFila = isset($porNombre[$clave]) ? $porNombre[$clave] : null;
            }

            if ($idFila === null) {
                continue;
            }

            $regreso = isset($util['regreso']) && $util['regreso'] !== null && $util['regreso'] !== ''
                ? ($util['regreso'] ? 1 : 0)
                : null;

            $sentenceRegreso->bindValue(':regreso', $regreso, $regreso === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $sentenceRegreso->bindValue(':id', $idFila);
            $sentenceRegreso->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceRegreso->execute();
        }

        return $guardados;
    }

    /**
     * Movimiento con el nombre del estudiante y su grupo.
     */
    private static function obtenerMovimiento($db, $id)
    {
        $sentence = $db->prepare("SELECT ae.id, ae.id_estudiante, ae.fecha_ingreso, ae.fecha_salida,
                                         ae.observacion_ingreso, ae.observacion_salida,
                                         CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS estudiante,
                                         g.nombre AS nombre_grupo, g.icono, g.color
                                  FROM asistencia_estudiantes ae
                                  INNER JOIN estudiantes e ON e.id = ae.id_estudiante
                                  INNER JOIN personas p ON p.id = e.id_persona
                                  LEFT JOIN estudiantes_x_grupos exg ON exg.id_estudiante = e.id AND exg.activo = 1
                                  LEFT JOIN grupos g ON g.id = exg.id_grupo
                                  WHERE ae.id = :id AND ae.id_tenant = :id_tenant");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        return $sentence->fetch();
    }

    /**
     * Utiles del dia del estudiante para editar que trajo y que se llevo.
     *
     * Devuelve los que ya estan registrados MAS los del catalogo que le
     * aplican a su grupo y todavia no tiene. Los del catalogo llegan con id
     * en null: si la usuaria los deja sin verificar no se crean, y si los
     * marca se crean al grabar. Sin esto no habria forma de completar un dia
     * en el que no se registro nada.
     */
    private static function obtenerUtiles($db, $id_estudiante, $fecha)
    {
        $sentence = $db->prepare("SELECT i.id, i.id_util_diario, i.nombre_libre, i.trajo, i.regreso, i.observacion,
                                         COALESCE(u.nombre, i.nombre_libre) AS nombre, u.icono,
                                         COALESCE(u.orden, 999) AS orden
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
        $registrados = $sentence->fetchAll();

        $yaEstan = array();
        foreach ($registrados as $registrado) {
            if (!empty($registrado['id_util_diario'])) {
                $yaEstan[$registrado['id_util_diario']] = true;
            }
        }

        $sentence = $db->prepare("SELECT u.id AS id_util_diario, u.nombre, u.icono, u.orden
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

        foreach ($sentence->fetchAll() as $delCatalogo) {
            if (isset($yaEstan[$delCatalogo['id_util_diario']])) {
                continue;
            }
            $registrados[] = array(
                'id'             => null,
                'id_util_diario' => $delCatalogo['id_util_diario'],
                'nombre_libre'   => null,
                'trajo'          => null,
                'regreso'        => null,
                'observacion'    => null,
                'nombre'         => $delCatalogo['nombre'],
                'icono'          => $delCatalogo['icono'],
                'orden'          => $delCatalogo['orden']
            );
        }

        return $registrados;
    }

    /**
     * Cobros automaticos generados por este movimiento, con lo que ya se les
     * abono. `valor_pagado` mayor que cero es lo que bloquea la pantalla.
     */
    private static function obtenerCobros($db, $id_asistencia)
    {
        $sentence = $db->prepare("SELECT h.id AS id_historial, h.id_regla_cobro, h.detalle,
                                         c.id AS id_cuenta_por_cobrar, c.fecha, c.valor, c.anulado,
                                         ps.nombre AS nombre_producto_servicio,
                                         COALESCE(SUM(cp.valor_aplicado), 0) AS valor_pagado
                                  FROM cobros_automaticos_historial h
                                  INNER JOIN cuentas_por_cobrar c ON c.id = h.id_cuenta_por_cobrar
                                  LEFT JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
                                  LEFT JOIN cuenta_pagada cp ON cp.id_cuenta_por_cobrar = c.id
                                  WHERE h.id_asistencia_estudiante = :id_asistencia
                                    AND h.id_tenant = :id_tenant
                                  GROUP BY h.id, h.id_regla_cobro, h.detalle, c.id, c.fecha, c.valor, c.anulado, ps.nombre
                                  ORDER BY c.fecha");
        $sentence->bindValue(':id_asistencia', $id_asistencia);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        return $sentence->fetchAll();
    }

    /**
     * True si alguno de los cobros generados por el movimiento ya recibio un
     * pago. Es la validacion que bloquea editar y eliminar.
     */
    private static function tienePagosAplicados($db, $id_asistencia)
    {
        $sentence = $db->prepare("SELECT COUNT(*) AS con_pago
                                  FROM cobros_automaticos_historial h
                                  INNER JOIN cuenta_pagada cp ON cp.id_cuenta_por_cobrar = h.id_cuenta_por_cobrar
                                  WHERE h.id_asistencia_estudiante = :id_asistencia
                                    AND h.id_tenant = :id_tenant");
        $sentence->bindValue(':id_asistencia', $id_asistencia);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila && intval($fila['con_pago']) > 0;
    }

    /**
     * Borra las cuentas por cobrar generadas por el movimiento y su historial.
     * Solo se llama cuando ya se verifico que ninguna tiene pagos.
     */
    private static function borrarCobros($db, $id_asistencia)
    {
        $sentence = $db->prepare("SELECT id_cuenta_por_cobrar
                                  FROM cobros_automaticos_historial
                                  WHERE id_asistencia_estudiante = :id_asistencia
                                    AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_asistencia', $id_asistencia);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $cuentas = $sentence->fetchAll();

        $sentence = $db->prepare("DELETE FROM cobros_automaticos_historial
                                  WHERE id_asistencia_estudiante = :id_asistencia
                                    AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_asistencia', $id_asistencia);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $sentenceCuenta = $db->prepare("DELETE FROM cuentas_por_cobrar
                                        WHERE id = :id AND id_tenant = :id_tenant");

        $eliminadas = 0;
        foreach ($cuentas as $cuenta) {
            if (empty($cuenta['id_cuenta_por_cobrar'])) {
                continue;
            }
            $sentenceCuenta->bindValue(':id', $cuenta['id_cuenta_por_cobrar']);
            $sentenceCuenta->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceCuenta->execute();
            $eliminadas += $sentenceCuenta->rowCount();
        }

        return $eliminadas;
    }
}
