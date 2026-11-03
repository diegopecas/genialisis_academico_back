<?php
/*=============================================
SERVICIO - MOTOR DE REPORTES POR CORREO
Archivo: services/motor-reportes-correo.service.php

Arma y envía los reportes por correo que cada colaborador tiene asociados en
reportes_correo_colaboradores. Cada tipo de reporte (tipos_reporte_correo)
tiene su propio armado aquí, identificado por su código:

  RESUMEN_COORDINACION  Resumen general del jardín con tres columnas (hoy,
                        semana y mes), las clases ejecutadas por docente, el
                        cuadro de indicadores trabajados del corte por grupo
                        y área, y lo que hay que tener presente: estudiantes
                        que llevan días sin venir y cartera vencida de
                        estudiantes.

  RESUMEN_DOCENTE       Resumen para el colaborador que también es docente,
                        solo con lo suyo: clases ejecutadas y sin calificar
                        completas (hoy, semana y mes), franjas de hoy sin
                        ejecutar, asistencia de hoy de sus grupos,
                        estudiantes de sus grupos que llevan días sin venir
                        e indicadores del corte de sus grupos y áreas. Sus
                        grupos y áreas salen de area_academica_x_grupo (el
                        área que dicta en cada grupo) y de docentes_x_grupos
                        (los grupos donde está asignado). Si el colaborador
                        no es docente activo, el envío queda fallido en el
                        historial con ese motivo.

Lo dispara el cron (cron/reportes-correo.php, un proceso por tenant) y el
botón "Enviar ahora" del listado. El cron no repite un reporte que ya salió
bien ese día para el mismo colaborador; el envío manual siempre sale.

Los umbrales del reporte salen de configuracion_global (valor_numero):
  reporte_correo_dias_ausencia  Días hábiles sin venir a partir de los cuales
                                el estudiante sale en la lista.
  reporte_correo_max_ausentes   Máximo de estudiantes que se listan.
  reporte_correo_dias_cartera   Antigüedad mínima en días de la cartera vencida.
  reporte_correo_top_cartera    Cantidad de estudiantes con mayor cartera.
  reporte_correo_max_indicadores Indicadores sin trabajar que se listan por
                                grupo, de su área más atrasada.

Un indicador cuenta como trabajado en el corte si el grupo tuvo al menos una
clase ejecutada, en un sprint de ese corte, con una actividad asociada a ese
indicador. El corte es el vigente a la fecha del reporte. (El reporte de
cobertura curricular cuenta también las clases programadas; aquí solo las
ejecutadas.)

Los días hábiles se cuentan con el campo dia_habil del calendario global,
igual que el tab académico del dashboard: no cuentan festivos, sábados ni
domingos. (El reporte de indicadores de asistencia filtra por id_tipo_dia 1
y 3, y ahí los sábados quedan como laborales.)

Cada envío queda en historial_reportes_correo. Nada de lo que pase aquí
lanza excepción hacia afuera: los errores quedan en el historial y en el log.
=============================================*/

class MotorReportesCorreo
{
    const CODIGO_RESUMEN_COORDINACION = 'RESUMEN_COORDINACION';
    const CODIGO_RESUMEN_DOCENTE = 'RESUMEN_DOCENTE';

    const ORIGEN_CRON = 'CRON';
    const ORIGEN_MANUAL = 'MANUAL';

    // Estados de catálogos globales que usa el resumen
    const ESTADO_TAREA_EJECUTADA = 2;
    const ESTADO_SOLICITUD_PENDIENTE = 1;

    // Paleta del correo (pastel con dorados)
    const COLOR_DORADO = '#b8892f';
    const COLOR_DORADO_CLARO = '#f6ecd6';
    const COLOR_FONDO = '#fbf8f2';
    const COLOR_BORDE = '#eadfc8';
    const COLOR_TEXTO = '#3b352c';
    const COLOR_SUAVE = '#7d725f';
    const COLOR_MENTA = '#e3f1ea';
    const COLOR_ROSA = '#f8e4e1';
    const COLOR_ROSA_TEXTO = '#9a4a3f';
    const COLOR_CIELO = '#e4eef6';
    const COLOR_AMARILLO = '#fbf1d6';
    const COLOR_MENTA_TEXTO = '#2f6b4f';

    /**
     * Envía todos los reportes activos del tenant para una fecha. Lo llama el cron.
     *
     * @param PDO    $db
     * @param string $fecha  Fecha del reporte (AAAA-MM-DD)
     * @return array Conteo de lo procesado
     */
    public static function ejecutar(PDO $db, $fecha)
    {
        $resultado = array('evaluados' => 0, 'enviados' => 0, 'fallidos' => 0, 'omitidos' => 0);

        $suscripciones = self::suscripcionesActivas($db, null);
        $cache = array();

        foreach ($suscripciones as $suscripcion) {
            $resultado['evaluados']++;

            if (self::yaEnviado($db, $suscripcion['id'], $fecha)) {
                $resultado['omitidos']++;
                continue;
            }

            $envio = self::enviarSuscripcion($db, $suscripcion, $fecha, self::ORIGEN_CRON, $cache);
            if ($envio['error'] === null) {
                $resultado['enviados']++;
            } else {
                $resultado['fallidos']++;
            }
        }

        return $resultado;
    }

    /**
     * Envía ya mismo el reporte de una asociación, sin importar si ya salió hoy.
     * Lo usa el botón "Enviar ahora".
     *
     * @return array {error}: null si salió
     */
    public static function enviarAhora(PDO $db, $idSuscripcion, $fecha)
    {
        $suscripciones = self::suscripcionesActivas($db, $idSuscripcion);
        if (empty($suscripciones)) {
            return array('error' => 'El reporte no existe, está inactivo o el colaborador está inactivo.');
        }
        $cache = array();
        return self::enviarSuscripcion($db, $suscripciones[0], $fecha, self::ORIGEN_MANUAL, $cache);
    }

    // =========================================================
    // ENVÍO
    // =========================================================

    /**
     * Arma el reporte de una asociación, lo envía y deja el historial.
     * $cache guarda el reporte ya armado por código de tipo (y por
     * colaborador en los reportes personales, como el docente), para no
     * repetir las consultas cuando varios colaboradores reciben el mismo
     * reporte.
     */
    private static function enviarSuscripcion(PDO $db, array $suscripcion, $fecha, $origen, array &$cache)
    {
        $correo = trim((string) $suscripcion['correo_electronico']);
        $error = null;

        try {
            if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $error = 'El colaborador no tiene un correo válido registrado.';
            } else {
                $codigo = $suscripcion['codigo_tipo'];
                $llave = $codigo === self::CODIGO_RESUMEN_DOCENTE
                    ? $codigo . '|' . $suscripcion['id_colaborador']
                    : $codigo;
                if (!isset($cache[$llave])) {
                    $cache[$llave] = self::armarReporte($db, $suscripcion, $fecha);
                }
                $reporte = $cache[$llave];

                if ($reporte === null) {
                    $error = 'El tipo de reporte ' . $codigo . ' no tiene armado en el sistema.';
                } elseif (!empty($reporte['error'])) {
                    $error = $reporte['error'];
                } else {
                    $html = str_replace('{{NOMBRE_COLABORADOR}}', self::e($suscripcion['primer_nombre']), $reporte['html']);
                    $envio = ConfiguracionCorreo::enviar($db, array($correo), $reporte['asunto'], $html);
                    $error = $envio['error'];
                }
            }
        } catch (Exception $e) {
            error_log('[MotorReportesCorreo::enviarSuscripcion] ' . $e->getMessage());
            $error = 'Error al armar el reporte: ' . $e->getMessage();
        }

        self::registrarHistorial($db, $suscripcion['id'], $fecha, $origen, $correo, $error);

        return array('error' => $error);
    }

    /**
     * Asociaciones activas con colaborador activo y tipo activo. Si llega
     * $idSuscripcion se limita a esa.
     */
    private static function suscripcionesActivas(PDO $db, $idSuscripcion)
    {
        $sql = "SELECT rc.id, rc.id_colaborador, t.codigo AS codigo_tipo,
                       c.correo_electronico, p.primer_nombre
                FROM reportes_correo_colaboradores rc
                INNER JOIN tipos_reporte_correo t ON t.id = rc.id_tipo_reporte_correo
                INNER JOIN colaboradores c ON c.id = rc.id_colaborador
                INNER JOIN personas p ON p.id = c.id_persona
                WHERE rc.id_tenant = :id_tenant
                  AND rc.activo = 1
                  AND t.activo = 1
                  AND c.activo = 1";
        if ($idSuscripcion !== null) {
            $sql .= " AND rc.id = :id";
        }
        $sql .= " ORDER BY t.codigo, p.primer_nombre";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        if ($idSuscripcion !== null) {
            $stmt->bindValue(':id', $idSuscripcion);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** true si el reporte ya salió bien ese día para esa asociación */
    private static function yaEnviado(PDO $db, $idSuscripcion, $fecha)
    {
        $stmt = $db->prepare("SELECT 1 FROM historial_reportes_correo
                              WHERE id_reporte_correo_colaborador = :id
                                AND fecha_reporte = :fecha
                                AND exito = 1
                                AND id_tenant = :id_tenant
                              LIMIT 1");
        $stmt->bindValue(':id', $idSuscripcion);
        $stmt->bindValue(':fecha', $fecha);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    private static function registrarHistorial(PDO $db, $idSuscripcion, $fecha, $origen, $correo, $error)
    {
        try {
            $stmt = $db->prepare("INSERT INTO historial_reportes_correo
                    (id, id_tenant, id_reporte_correo_colaborador, fecha_reporte, origen, correo_destino, exito, error)
                VALUES (:id, :id_tenant, :id_suscripcion, :fecha, :origen, :correo, :exito, :error)");
            $stmt->bindValue(':id', Uuid::generar());
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindValue(':id_suscripcion', $idSuscripcion);
            $stmt->bindValue(':fecha', $fecha);
            $stmt->bindValue(':origen', $origen);
            $stmt->bindValue(':correo', $correo !== '' ? mb_substr($correo, 0, 150) : null);
            $stmt->bindValue(':exito', $error === null ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':error', $error !== null ? mb_substr($error, 0, 500) : null);
            $stmt->execute();
        } catch (Exception $e) {
            error_log('[MotorReportesCorreo::registrarHistorial] ' . $e->getMessage());
        }
    }

    // =========================================================
    // ARMADO POR TIPO
    // =========================================================

    /**
     * Devuelve {asunto, html} del tipo de reporte de la asociación, {error}
     * si no se puede armar para ese colaborador, o null si el código no
     * tiene armado. El html lleva {{NOMBRE_COLABORADOR}} para el saludo.
     */
    private static function armarReporte(PDO $db, array $suscripcion, $fecha)
    {
        switch ($suscripcion['codigo_tipo']) {
            case self::CODIGO_RESUMEN_COORDINACION:
                $datos = self::datosResumenCoordinacion($db, $fecha);
                return array(
                    'asunto' => 'Resumen del día ' . self::fechaCorta($fecha) . ' - ' . $datos['institucion'],
                    'html' => self::htmlResumenCoordinacion($datos)
                );
            case self::CODIGO_RESUMEN_DOCENTE:
                $datos = self::datosResumenDocente($db, $suscripcion['id_colaborador'], $fecha);
                if ($datos === null) {
                    return array('error' => 'El colaborador no está registrado como docente activo.');
                }
                return array(
                    'asunto' => 'Tu resumen del día ' . self::fechaCorta($fecha) . ' - ' . $datos['institucion'],
                    'html' => self::htmlResumenDocente($datos)
                );
            default:
                return null;
        }
    }

    // =========================================================
    // RESUMEN DE COORDINACIÓN: DATOS
    // =========================================================

    /**
     * Todos los datos del resumen de coordinación para una fecha.
     * Semana = desde el lunes de esa semana; mes = desde el día 1 del mes.
     */
    public static function datosResumenCoordinacion(PDO $db, $fecha)
    {
        $inicioSemana = date('Y-m-d', strtotime('monday this week', strtotime($fecha)));
        $inicioMes = date('Y-m-01', strtotime($fecha));

        $rangos = array(
            'hoy' => array($fecha, $fecha),
            'semana' => array($inicioSemana, $fecha),
            'mes' => array($inicioMes, $fecha)
        );

        // Hoy sale del dashboard gerencial, para que el correo y el tablero digan lo mismo
        $operativo = DashboardGerencial::resumenOperativoContexto($db, $fecha);
        $academico = DashboardGerencial::resumenAcademicoContexto($db, $fecha);

        $totalActivos = (int) $operativo['asistencia']['total_activos'];

        $periodos = array();
        foreach ($rangos as $clave => $rango) {
            list($desde, $hasta) = $rango;
            $diasHabiles = self::diasHabiles($db, $desde, $hasta);
            $diasAsistencia = self::diasAsistenciaEstudiantes($db, $desde, $hasta);
            $promedio = $diasHabiles > 0 ? round($diasAsistencia / $diasHabiles, 1) : 0;

            $periodos[$clave] = array(
                'desde' => $desde,
                'hasta' => $hasta,
                'recaudo' => self::recaudo($db, $desde, $hasta),
                'dias_habiles' => $diasHabiles,
                'promedio_asistencia' => $promedio,
                'porcentaje_asistencia' => $totalActivos > 0 ? round(($promedio / $totalActivos) * 100) : 0,
                'llegadas_tarde' => self::llegadasTardeColaboradores($db, $desde, $hasta),
                'clases_ejecutadas' => self::clasesEjecutadas($db, $desde, $hasta),
                'observaciones' => self::contarPorFechaRegistro($db, 'observaciones_estudiantes', $desde, $hasta),
                'solicitudes' => self::contarPorFechaRegistro($db, 'solicitudes', $desde, $hasta)
            );
        }

        // Hoy se cuenta exacto (no promedio) con los mismos números del dashboard
        $periodos['hoy']['asistieron_hoy'] = (int) $operativo['asistencia']['total_asistieron'];
        $periodos['hoy']['porcentaje_asistencia'] = round((float) $operativo['asistencia']['porcentaje']);

        $diasAusencia = self::configNumero($db, 'reporte_correo_dias_ausencia', 2);
        $maxAusentes = self::configNumero($db, 'reporte_correo_max_ausentes', 20);
        $diasCartera = self::configNumero($db, 'reporte_correo_dias_cartera', 30);
        $topCartera = self::configNumero($db, 'reporte_correo_top_cartera', 10);
        $maxIndicadores = self::configNumero($db, 'reporte_correo_max_indicadores', 3);

        $ausentes = self::estudiantesSinVenir($db, $fecha, $diasAusencia);
        $cartera = self::carteraVencidaEstudiantes($db, $fecha, $diasCartera);

        return array(
            'fecha' => $fecha,
            'institucion' => self::configTexto($db, 'institucion_nombre', 'Jardín'),
            'total_activos' => $totalActivos,
            'colaboradores' => $operativo['colaboradores'],
            'academico_hoy' => $academico,
            'periodos' => $periodos,
            'clases_docentes' => self::clasesPorDocente($db, $fecha, $inicioSemana, $inicioMes),
            'indicadores' => self::indicadoresCorte($db, $fecha, $maxIndicadores, null),
            'pendientes_aprobar' => self::solicitudesPendientes($db),
            'dias_ausencia' => $diasAusencia,
            'ausentes_total' => count($ausentes),
            'ausentes' => array_slice($ausentes, 0, max(0, $maxAusentes)),
            'dias_cartera' => $diasCartera,
            'cartera_total' => $cartera['total'],
            'cartera_estudiantes' => count($cartera['estudiantes']),
            'cartera_top' => array_slice($cartera['estudiantes'], 0, max(0, $topCartera))
        );
    }

    /** Días hábiles del calendario global (dia_habil = 1) en el rango */
    private static function diasHabiles(PDO $db, $desde, $hasta)
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM calendarios
                              WHERE fecha BETWEEN :desde AND :hasta AND dia_habil = 1");
        $stmt->bindValue(':desde', $desde);
        $stmt->bindValue(':hasta', $hasta);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Suma de días-estudiante con asistencia en días hábiles del rango, contando
     * a cada estudiante activo (en grupo activo) una vez por día.
     */
    private static function diasAsistenciaEstudiantes(PDO $db, $desde, $hasta)
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM (
                    SELECT DISTINCT ae.id_estudiante, DATE(ae.fecha_ingreso) AS dia
                    FROM asistencia_estudiantes ae
                    INNER JOIN estudiantes e ON e.id = ae.id_estudiante AND e.activo = 1
                    INNER JOIN calendarios cal ON cal.fecha = DATE(ae.fecha_ingreso) AND cal.dia_habil = 1
                    WHERE ae.id_tenant = :id_tenant
                      AND DATE(ae.fecha_ingreso) BETWEEN :desde AND :hasta
                      AND EXISTS (SELECT 1 FROM estudiantes_x_grupos exg
                                  WHERE exg.id_estudiante = e.id AND exg.activo = 1)
                ) sub");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':desde', $desde);
        $stmt->bindValue(':hasta', $hasta);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /** Pagos de ingreso no anulados con fecha en el rango (mismo filtro del dashboard) */
    private static function recaudo(PDO $db, $desde, $hasta)
    {
        $stmt = $db->prepare("SELECT COUNT(*) AS cantidad, COALESCE(SUM(pr.valor_recibido), 0) AS total
                              FROM pagos_recibidos pr
                              INNER JOIN tipos_pagos tp ON tp.id = pr.id_tipo_pago AND tp.es_ingreso = 1
                              WHERE (pr.anulado = 0 OR pr.anulado IS NULL)
                                AND DATE(pr.fecha) BETWEEN :desde AND :hasta
                                AND pr.id_tenant = :id_tenant");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':desde', $desde);
        $stmt->bindValue(':hasta', $hasta);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return array('cantidad' => (int) $fila['cantidad'], 'total' => (float) $fila['total']);
    }

    /** Entradas de jornada marcadas como tarde en el rango */
    private static function llegadasTardeColaboradores(PDO $db, $desde, $hasta)
    {
        $stmt = $db->prepare("SELECT COUNT(*)
                              FROM registros_asistencia_colaboradores ra
                              INNER JOIN tipos_registro_asistencia tra ON tra.id = ra.id_tipo_registro
                              INNER JOIN estados_registro_asistencia era ON era.id = ra.id_estado
                              INNER JOIN colaboradores c ON c.id = ra.id_colaborador AND c.activo = 1
                              WHERE tra.codigo = 'jornada_entrada'
                                AND era.codigo = 'tarde'
                                AND ra.fecha BETWEEN :desde AND :hasta
                                AND ra.id_tenant = :id_tenant");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':desde', $desde);
        $stmt->bindValue(':hasta', $hasta);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /** Clases (tareas de sprint) ejecutadas en el rango, de grupos y de cursos extra */
    private static function clasesEjecutadas(PDO $db, $desde, $hasta)
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM tareas_x_sprints
                              WHERE id_estado_tarea = :ejecutada
                                AND DATE(fecha_ejecucion) BETWEEN :desde AND :hasta
                                AND id_tenant = :id_tenant");
        $stmt->bindValue(':ejecutada', self::ESTADO_TAREA_EJECUTADA, PDO::PARAM_INT);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':desde', $desde);
        $stmt->bindValue(':hasta', $hasta);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Registros creados en el rango según su fecha_registro. La tabla viene de
     * esta lista blanca, nunca de afuera.
     */
    private static function contarPorFechaRegistro(PDO $db, $tabla, $desde, $hasta)
    {
        if (!in_array($tabla, array('observaciones_estudiantes', 'solicitudes'), true)) {
            return 0;
        }
        $stmt = $db->prepare("SELECT COUNT(*) FROM $tabla
                              WHERE DATE(fecha_registro) BETWEEN :desde AND :hasta
                                AND id_tenant = :id_tenant");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':desde', $desde);
        $stmt->bindValue(':hasta', $hasta);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /** Solicitudes que siguen pendientes por aprobar, sin importar la fecha */
    private static function solicitudesPendientes(PDO $db)
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM solicitudes
                              WHERE id_estado = :pendiente AND id_tenant = :id_tenant");
        $stmt->bindValue(':pendiente', self::ESTADO_SOLICITUD_PENDIENTE, PDO::PARAM_INT);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Estudiantes activos que llevan más de $umbral días hábiles sin venir a
     * la fecha. Misma regla del reporte de indicadores de asistencia: si vino
     * ese día son 0; si no, se cuentan los días hábiles desde el día siguiente
     * a su última asistencia (o a su fecha de ingreso si nunca ha venido)
     * hasta la fecha. Ordenados de más a menos días.
     *
     * Si llega $idsGrupos, solo los estudiantes de esos grupos (lo usa el
     * reporte docente); null es todo el jardín.
     */
    private static function estudiantesSinVenir(PDO $db, $fecha, $umbral, $idsGrupos = null)
    {
        $filtroGrupos = '';
        $paramsGrupos = array();
        if ($idsGrupos !== null) {
            if (empty($idsGrupos)) {
                return array();
            }
            list($filtroGrupos, $paramsGrupos) = self::filtroIn('exg.id_grupo', 'grp', $idsGrupos);
            $filtroGrupos = ' AND ' . $filtroGrupos;
        }

        $stmt = $db->prepare("SELECT sub.id_estudiante, sub.nombre, sub.nombre_grupo, sub.ultima_asistencia,
                    (SELECT COUNT(*) FROM calendarios cal
                     WHERE cal.dia_habil = 1
                       AND cal.fecha > COALESCE(sub.ultima_asistencia, sub.fecha_ingreso)
                       AND cal.fecha <= :fecha_conteo) AS dias_sin_venir
                FROM (
                    SELECT e.id AS id_estudiante,
                           TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS nombre,
                           MIN(g.nombre) AS nombre_grupo,
                           DATE(e.fecha_ingreso) AS fecha_ingreso,
                           (SELECT MAX(DATE(ae.fecha_ingreso)) FROM asistencia_estudiantes ae
                            WHERE ae.id_estudiante = e.id AND ae.id_tenant = :id_tenant_ae
                              AND DATE(ae.fecha_ingreso) <= :fecha_ultima) AS ultima_asistencia
                    FROM estudiantes e
                    INNER JOIN personas p ON p.id = e.id_persona
                    INNER JOIN estudiantes_x_grupos exg ON exg.id_estudiante = e.id AND exg.activo = 1
                    INNER JOIN grupos g ON g.id = exg.id_grupo
                    WHERE e.activo = 1 AND e.id_tenant = :id_tenant" . $filtroGrupos . "
                    GROUP BY e.id, p.primer_nombre, p.primer_apellido, e.fecha_ingreso
                ) sub
                WHERE COALESCE(sub.ultima_asistencia, sub.fecha_ingreso) IS NOT NULL
                  AND (sub.ultima_asistencia IS NULL OR sub.ultima_asistencia < :fecha_hoy)
                HAVING dias_sin_venir > :umbral
                ORDER BY dias_sin_venir DESC, sub.nombre");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':id_tenant_ae', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':fecha_conteo', $fecha);
        $stmt->bindValue(':fecha_ultima', $fecha);
        $stmt->bindValue(':fecha_hoy', $fecha);
        $stmt->bindValue(':umbral', (int) $umbral, PDO::PARAM_INT);
        foreach ($paramsGrupos as $llave => $valor) {
            $stmt->bindValue($llave, $valor);
        }
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) {
            $f['dias_sin_venir'] = (int) $f['dias_sin_venir'];
        }
        unset($f);
        return $filas;
    }

    /**
     * Cartera de estudiantes con más de $dias días de vencida a la fecha:
     * saldo de las cuentas (valor menos pagos no anulados) cuya fecha quedó
     * más de $dias días atrás. Devuelve el total y los estudiantes ordenados
     * de mayor a menor saldo.
     */
    private static function carteraVencidaEstudiantes(PDO $db, $fecha, $dias)
    {
        $stmt = $db->prepare("SELECT e.id AS id_estudiante,
                       TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS nombre,
                       COALESCE((SELECT MIN(g.nombre) FROM estudiantes_x_grupos exg
                                 INNER JOIN grupos g ON g.id = exg.id_grupo
                                 WHERE exg.id_estudiante = e.id AND exg.activo = 1), 'Sin grupo') AS nombre_grupo,
                       e.activo,
                       COUNT(*) AS cuentas,
                       SUM(sub.saldo) AS saldo,
                       MAX(DATEDIFF(:fecha_dias, sub.fecha)) AS dias_max_vencido
                FROM (
                    SELECT cpc.id, cpc.id_persona, cpc.fecha,
                           cpc.valor - COALESCE(SUM(CASE WHEN pr.anulado = 0 OR pr.anulado IS NULL
                                                         THEN cp.valor_aplicado ELSE 0 END), 0) AS saldo
                    FROM cuentas_por_cobrar cpc
                    LEFT JOIN cuenta_pagada cp ON cp.id_cuenta_por_cobrar = cpc.id
                    LEFT JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
                    WHERE (cpc.anulado = 0 OR cpc.anulado IS NULL)
                      AND cpc.id_tenant = :id_tenant
                      AND DATEDIFF(:fecha_corte, cpc.fecha) > :dias
                    GROUP BY cpc.id, cpc.id_persona, cpc.fecha, cpc.valor
                    HAVING saldo > 0
                ) sub
                INNER JOIN personas p ON p.id = sub.id_persona
                INNER JOIN estudiantes e ON e.id_persona = p.id
                GROUP BY e.id, p.primer_nombre, p.primer_apellido, e.activo
                ORDER BY saldo DESC");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':fecha_dias', $fecha);
        $stmt->bindValue(':fecha_corte', $fecha);
        $stmt->bindValue(':dias', (int) $dias, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = 0;
        foreach ($filas as &$f) {
            $f['saldo'] = (float) $f['saldo'];
            $f['cuentas'] = (int) $f['cuentas'];
            $f['dias_max_vencido'] = (int) $f['dias_max_vencido'];
            $f['activo'] = (int) $f['activo'];
            $total += $f['saldo'];
        }
        unset($f);

        return array('total' => $total, 'estudiantes' => $filas);
    }

    /**
     * Clases ejecutadas por cada docente activo hoy, en la semana y en el
     * mes, según el docente que quedó en la clase. Salen los docentes que
     * tienen grupo o área asignada, o alguna clase en el periodo.
     */
    private static function clasesPorDocente(PDO $db, $fecha, $inicioSemana, $inicioMes)
    {
        $desde = min($inicioSemana, $inicioMes);

        $stmt = $db->prepare("SELECT d.id,
                       TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS nombre,
                       COALESCE(SUM(DATE(t.fecha_ejecucion) = :hoy), 0) AS hoy,
                       COALESCE(SUM(DATE(t.fecha_ejecucion) >= :inicio_semana), 0) AS semana,
                       COALESCE(SUM(DATE(t.fecha_ejecucion) >= :inicio_mes), 0) AS mes
                FROM docentes d
                INNER JOIN personas p ON p.id = d.id_persona
                LEFT JOIN tareas_x_sprints t ON t.id_docente = d.id
                      AND t.id_tenant = d.id_tenant
                      AND t.id_estado_tarea = :ejecutada
                      AND DATE(t.fecha_ejecucion) BETWEEN :desde AND :hasta
                WHERE d.id_tenant = :id_tenant
                  AND d.activo = 1
                GROUP BY d.id, p.primer_nombre, p.primer_apellido
                HAVING COUNT(t.id) > 0
                    OR EXISTS (SELECT 1 FROM area_academica_x_grupo axg
                               WHERE axg.id_docente = d.id AND axg.id_tenant = :id_tenant_axg)
                    OR EXISTS (SELECT 1 FROM docentes_x_grupos dxg
                               WHERE dxg.id_docente = d.id AND dxg.activo = 1 AND dxg.id_tenant = :id_tenant_dxg)
                ORDER BY mes DESC, nombre");
        $stmt->bindValue(':hoy', $fecha);
        $stmt->bindValue(':inicio_semana', $inicioSemana);
        $stmt->bindValue(':inicio_mes', $inicioMes);
        $stmt->bindValue(':ejecutada', self::ESTADO_TAREA_EJECUTADA, PDO::PARAM_INT);
        $stmt->bindValue(':desde', $desde);
        $stmt->bindValue(':hasta', $fecha);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':id_tenant_axg', TenantContext::id(), PDO::PARAM_INT);
        $stmt->bindValue(':id_tenant_dxg', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) {
            $f['hoy'] = (int) $f['hoy'];
            $f['semana'] = (int) $f['semana'];
            $f['mes'] = (int) $f['mes'];
        }
        unset($f);
        return $filas;
    }

    /** Corte académico vigente a la fecha, o null */
    private static function corteVigente(PDO $db, $fecha)
    {
        $stmt = $db->prepare("SELECT id, nombre, fecha_inicio, fecha_fin FROM cortes_academicos
                              WHERE :fecha BETWEEN fecha_inicio AND fecha_fin
                                AND id_tenant = :id_tenant
                              ORDER BY fecha_inicio DESC
                              LIMIT 1");
        $stmt->bindValue(':fecha', $fecha);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Indicadores del corte vigente por grupo y área: cuántos tiene cada
     * grupo en cada área (según los grados del grupo) y cuántos ya se
     * trabajaron. Por grupo se toma el área más atrasada y se listan hasta
     * $maxIndicadores de sus indicadores sin trabajar.
     *
     * Solo grupos con estudiantes activos. Si llega $filtro (reporte
     * docente) se limita a sus pares grupo-área ('pares': "idGrupo|idArea")
     * y a todas las áreas de los grupos donde está asignado ('grupos').
     *
     * @return array {corte, grupos, areas, celdas[idArea][idGrupo] {total, trabajados}, atrasados}
     */
    private static function indicadoresCorte(PDO $db, $fecha, $maxIndicadores, $filtro)
    {
        $vacio = array('corte' => self::corteVigente($db, $fecha), 'grupos' => array(), 'areas' => array(), 'celdas' => array(), 'atrasados' => array());
        if ($vacio['corte'] === null) {
            return $vacio;
        }

        $stmt = $db->prepare("SELECT g.id AS id_grupo, g.nombre AS nombre_grupo, g.orden AS orden_grupo,
                       a.id AS id_area, a.nombre AS nombre_area,
                       il.id AS id_indicador, il.nombre AS nombre_indicador,
                       (SELECT COUNT(DISTINCT t.id)
                        FROM actividades_academicas_x_indicadores_logros x
                        INNER JOIN tareas_x_sprints t ON t.id_actividad_academica = x.id_actividad_academica
                              AND t.id_grupo = g.id
                              AND t.id_estado_tarea = :ejecutada
                              AND t.id_tenant = g.id_tenant
                        INNER JOIN sprints s ON s.id = t.id_sprint AND s.id_corte_academico = :id_corte_sprint
                        WHERE x.id_indicador_logro = il.id) AS veces
                FROM grupos g
                INNER JOIN grados_x_grupo gxg ON gxg.id_grupo = g.id AND gxg.id_tenant = g.id_tenant
                INNER JOIN logros l ON l.id_grado = gxg.id_grado
                      AND l.id_corte_academico = :id_corte
                      AND l.id_tenant = g.id_tenant
                INNER JOIN indicadores_logros il ON il.id_logro = l.id
                INNER JOIN areas_academicas a ON a.id = l.id_area_academica
                WHERE g.id_tenant = :id_tenant
                  AND EXISTS (SELECT 1 FROM estudiantes_x_grupos exg
                              INNER JOIN estudiantes e ON e.id = exg.id_estudiante AND e.activo = 1
                              WHERE exg.id_grupo = g.id AND exg.activo = 1)
                ORDER BY g.orden, g.nombre, a.nombre, il.nombre");
        $stmt->bindValue(':ejecutada', self::ESTADO_TAREA_EJECUTADA, PDO::PARAM_INT);
        $stmt->bindValue(':id_corte_sprint', $vacio['corte']['id']);
        $stmt->bindValue(':id_corte', $vacio['corte']['id']);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();

        $grupos = array();
        $areas = array();
        $celdas = array();
        $sinTrabajar = array();
        $vistos = array();

        while ($f = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($filtro !== null
                && !isset($filtro['pares'][$f['id_grupo'] . '|' . $f['id_area']])
                && !isset($filtro['grupos'][$f['id_grupo']])) {
                continue;
            }

            // Un indicador puede venir dos veces si el grupo tiene varios grados
            $llave = $f['id_area'] . '|' . $f['id_grupo'] . '|' . $f['id_indicador'];
            if (isset($vistos[$llave])) {
                continue;
            }
            $vistos[$llave] = true;

            $grupos[$f['id_grupo']] = $f['nombre_grupo'];
            $areas[$f['id_area']] = $f['nombre_area'];
            if (!isset($celdas[$f['id_area']][$f['id_grupo']])) {
                $celdas[$f['id_area']][$f['id_grupo']] = array('total' => 0, 'trabajados' => 0);
            }
            $celdas[$f['id_area']][$f['id_grupo']]['total']++;
            if ((int) $f['veces'] > 0) {
                $celdas[$f['id_area']][$f['id_grupo']]['trabajados']++;
            } else {
                $sinTrabajar[$f['id_grupo']][$f['id_area']][] = $f['nombre_indicador'];
            }
        }
        asort($areas);

        // Por grupo, el área con menor porcentaje (si empata, la de más indicadores)
        $atrasados = array();
        foreach ($grupos as $idGrupo => $nombreGrupo) {
            $peor = null;
            foreach ($areas as $idArea => $nombreArea) {
                if (!isset($celdas[$idArea][$idGrupo])) {
                    continue;
                }
                $c = $celdas[$idArea][$idGrupo];
                if ($c['trabajados'] >= $c['total']) {
                    continue;
                }
                $pct = $c['trabajados'] / $c['total'];
                if ($peor === null || $pct < $peor['pct'] || ($pct == $peor['pct'] && $c['total'] > $peor['total'])) {
                    $peor = array('id_area' => $idArea, 'pct' => $pct, 'total' => $c['total']);
                }
            }
            if ($peor === null) {
                continue;
            }
            $c = $celdas[$peor['id_area']][$idGrupo];
            $atrasados[] = array(
                'nombre_grupo' => $nombreGrupo,
                'nombre_area' => $areas[$peor['id_area']],
                'trabajados' => $c['trabajados'],
                'total' => $c['total'],
                'sin_trabajar' => array_slice($sinTrabajar[$idGrupo][$peor['id_area']], 0, max(0, $maxIndicadores)),
                'sin_trabajar_total' => count($sinTrabajar[$idGrupo][$peor['id_area']])
            );
        }

        return array('corte' => $vacio['corte'], 'grupos' => $grupos, 'areas' => $areas, 'celdas' => $celdas, 'atrasados' => $atrasados);
    }

    // =========================================================
    // RESUMEN DOCENTE: DATOS
    // =========================================================

    /**
     * Datos del resumen del docente que corresponde al colaborador, o null
     * si el colaborador no es docente activo.
     */
    public static function datosResumenDocente(PDO $db, $idColaborador, $fecha)
    {
        $stmt = $db->prepare("SELECT d.id, TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS nombre
                              FROM docentes d
                              INNER JOIN personas p ON p.id = d.id_persona
                              WHERE d.id_colaborador = :id_colaborador
                                AND d.activo = 1
                                AND d.id_tenant = :id_tenant
                              LIMIT 1");
        $stmt->bindValue(':id_colaborador', $idColaborador);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $docente = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$docente) {
            return null;
        }
        $idDocente = $docente['id'];

        $inicioSemana = date('Y-m-d', strtotime('monday this week', strtotime($fecha)));
        $inicioMes = date('Y-m-01', strtotime($fecha));

        // Lo que dicta: área por grupo, y grupos donde está asignado
        $stmt = $db->prepare("SELECT id_grupo, id_area_academica FROM area_academica_x_grupo
                              WHERE id_docente = :id_docente AND id_tenant = :id_tenant");
        $stmt->bindValue(':id_docente', $idDocente);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $pares = array();
        $idsGrupos = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $pares[$f['id_grupo'] . '|' . $f['id_area_academica']] = true;
            $idsGrupos[$f['id_grupo']] = true;
        }

        $stmt = $db->prepare("SELECT id_grupo FROM docentes_x_grupos
                              WHERE id_docente = :id_docente AND activo = 1 AND id_tenant = :id_tenant");
        $stmt->bindValue(':id_docente', $idDocente);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $gruposAsignados = array();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $idGrupo) {
            $gruposAsignados[$idGrupo] = true;
            $idsGrupos[$idGrupo] = true;
        }
        $idsGrupos = array_keys($idsGrupos);

        $periodos = array(
            'hoy' => self::clasesDocente($db, $idDocente, $fecha, $fecha),
            'semana' => self::clasesDocente($db, $idDocente, $inicioSemana, $fecha),
            'mes' => self::clasesDocente($db, $idDocente, $inicioMes, $fecha)
        );

        // Franjas de hoy sin ejecutar en las áreas que dicta, con la regla del dashboard
        $academico = DashboardGerencial::resumenAcademicoContexto($db, $fecha);
        $franjasSinEjecutar = array();
        $franjasHoy = 0;
        foreach (DashboardGerencial::franjasAcademicasContexto($db, $fecha) as $f) {
            if (!isset($pares[$f['id_grupo'] . '|' . $f['id_area_academica']])) {
                continue;
            }
            $franjasHoy++;
            if ($f['estado'] === 'sin_ejecutar') {
                $franjasSinEjecutar[] = $f;
            }
        }

        $diasAusencia = self::configNumero($db, 'reporte_correo_dias_ausencia', 2);
        $maxAusentes = self::configNumero($db, 'reporte_correo_max_ausentes', 20);
        $maxIndicadores = self::configNumero($db, 'reporte_correo_max_indicadores', 3);
        $ausentes = self::estudiantesSinVenir($db, $fecha, $diasAusencia, $idsGrupos);

        return array(
            'fecha' => $fecha,
            'institucion' => self::configTexto($db, 'institucion_nombre', 'Jardín'),
            'docente' => $docente['nombre'],
            'tiene_asignacion' => !empty($idsGrupos),
            'inicio_semana' => $inicioSemana,
            'inicio_mes' => $inicioMes,
            'periodos' => $periodos,
            'dia_habil' => !empty($academico['dia_habil']),
            'franjas_hoy' => $franjasHoy,
            'franjas_sin_ejecutar' => $franjasSinEjecutar,
            'asistencia_grupos' => self::asistenciaGrupos($db, $fecha, $idsGrupos),
            'dias_ausencia' => $diasAusencia,
            'ausentes_total' => count($ausentes),
            'ausentes' => array_slice($ausentes, 0, max(0, $maxAusentes)),
            'indicadores' => self::indicadoresCorte($db, $fecha, $maxIndicadores, array('pares' => $pares, 'grupos' => $gruposAsignados))
        );
    }

    /**
     * Clases que el docente ejecutó en el rango y cuántas de ellas no tienen
     * calificados a todos los estudiantes del grupo (solo grupos calificables).
     */
    private static function clasesDocente(PDO $db, $idDocente, $desde, $hasta)
    {
        $stmt = $db->prepare("SELECT COUNT(*) AS ejecutadas,
                       COALESCE(SUM(g.calificable = 1
                                    AND COALESCE(t.total_estudiantes_calificados, 0) < COALESCE(t.total_estudiantes_grupo, 0)), 0) AS sin_calificar
                FROM tareas_x_sprints t
                LEFT JOIN grupos g ON g.id = t.id_grupo
                WHERE t.id_docente = :id_docente
                  AND t.id_estado_tarea = :ejecutada
                  AND DATE(t.fecha_ejecucion) BETWEEN :desde AND :hasta
                  AND t.id_tenant = :id_tenant");
        $stmt->bindValue(':id_docente', $idDocente);
        $stmt->bindValue(':ejecutada', self::ESTADO_TAREA_EJECUTADA, PDO::PARAM_INT);
        $stmt->bindValue(':desde', $desde);
        $stmt->bindValue(':hasta', $hasta);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return array('ejecutadas' => (int) $fila['ejecutadas'], 'sin_calificar' => (int) $fila['sin_calificar']);
    }

    /** Estudiantes activos de cada grupo y cuántos asistieron en la fecha */
    private static function asistenciaGrupos(PDO $db, $fecha, array $idsGrupos)
    {
        if (empty($idsGrupos)) {
            return array();
        }
        list($filtro, $params) = self::filtroIn('g.id', 'grp', $idsGrupos);

        $stmt = $db->prepare("SELECT g.nombre,
                       COUNT(DISTINCT e.id) AS total,
                       COUNT(DISTINCT ae.id_estudiante) AS asistieron
                FROM grupos g
                INNER JOIN estudiantes_x_grupos exg ON exg.id_grupo = g.id AND exg.activo = 1
                INNER JOIN estudiantes e ON e.id = exg.id_estudiante AND e.activo = 1
                LEFT JOIN asistencia_estudiantes ae ON ae.id_estudiante = e.id
                      AND DATE(ae.fecha_ingreso) = :fecha
                      AND ae.id_tenant = g.id_tenant
                WHERE g.id_tenant = :id_tenant AND " . $filtro . "
                GROUP BY g.id, g.nombre, g.orden
                ORDER BY g.orden, g.nombre");
        $stmt->bindValue(':fecha', $fecha);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        foreach ($params as $llave => $valor) {
            $stmt->bindValue($llave, $valor);
        }
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) {
            $f['total'] = (int) $f['total'];
            $f['asistieron'] = (int) $f['asistieron'];
        }
        unset($f);
        return $filas;
    }

    // =========================================================
    // RESUMEN DE COORDINACIÓN: HTML
    // =========================================================

    /**
     * Correo del resumen. Todo con estilos en línea y tablas, porque los
     * clientes de correo no cargan hojas de estilo ni respetan flexbox.
     */
    private static function htmlResumenCoordinacion(array $d)
    {
        $p = $d['periodos'];
        $hoy = $p['hoy'];
        $col = $d['colaboradores'];
        $acad = $d['academico_hoy'];

        $filas = array();

        $filas[] = self::filaIndicador(
            '💰',
            'Recaudo',
            self::celdaValor(self::pesos($hoy['recaudo']['total']), self::plural($hoy['recaudo']['cantidad'], 'pago', 'pagos')),
            self::celdaValor(self::pesos($p['semana']['recaudo']['total']), self::plural($p['semana']['recaudo']['cantidad'], 'pago', 'pagos')),
            self::celdaValor(self::pesos($p['mes']['recaudo']['total']), self::plural($p['mes']['recaudo']['cantidad'], 'pago', 'pagos'))
        );

        $filas[] = self::filaIndicador(
            '🧒',
            'Asistencia de niños',
            self::celdaValor($hoy['asistieron_hoy'] . ' de ' . $d['total_activos'], $hoy['porcentaje_asistencia'] . '%'),
            self::celdaValor(self::decimal($p['semana']['promedio_asistencia']) . ' al día', $p['semana']['porcentaje_asistencia'] . '% prom.'),
            self::celdaValor(self::decimal($p['mes']['promedio_asistencia']) . ' al día', $p['mes']['porcentaje_asistencia'] . '% prom.')
        );

        $filas[] = self::filaIndicador(
            '👩‍🏫',
            'Llegadas tarde del equipo',
            self::celdaValor($hoy['llegadas_tarde'], 'ingresaron ' . (int) $col['ingresaron'] . ' de ' . (int) $col['total_activos']),
            self::celdaValor($p['semana']['llegadas_tarde'], ''),
            self::celdaValor($p['mes']['llegadas_tarde'], '')
        );

        $filas[] = self::filaIndicador(
            '📚',
            'Clases ejecutadas',
            self::celdaValor($hoy['clases_ejecutadas'], ''),
            self::celdaValor($p['semana']['clases_ejecutadas'], ''),
            self::celdaValor($p['mes']['clases_ejecutadas'], '')
        );

        $filas[] = self::filaIndicador(
            '📝',
            'Observaciones registradas',
            self::celdaValor($hoy['observaciones'], ''),
            self::celdaValor($p['semana']['observaciones'], ''),
            self::celdaValor($p['mes']['observaciones'], '')
        );

        $filas[] = self::filaIndicador(
            '📩',
            'Solicitudes nuevas',
            self::celdaValor($hoy['solicitudes'], ''),
            self::celdaValor($p['semana']['solicitudes'], ''),
            self::celdaValor($p['mes']['solicitudes'], '')
        );

        $tablaIndicadores = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">'
            . '<tr>'
            . '<th align="left" style="padding:10px 8px;color:' . self::COLOR_DORADO . ';font-size:12px;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid ' . self::COLOR_BORDE . ';">Indicador</th>'
            . '<th align="center" style="padding:10px 8px;color:' . self::COLOR_DORADO . ';font-size:12px;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid ' . self::COLOR_BORDE . ';">Hoy</th>'
            . '<th align="center" style="padding:10px 8px;color:' . self::COLOR_DORADO . ';font-size:12px;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid ' . self::COLOR_BORDE . ';">Semana</th>'
            . '<th align="center" style="padding:10px 8px;color:' . self::COLOR_DORADO . ';font-size:12px;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid ' . self::COLOR_BORDE . ';">Mes</th>'
            . '</tr>'
            . implode('', $filas)
            . '</table>';

        // Avisos rápidos arriba de las listas
        $avisos = '';
        if ($d['pendientes_aprobar'] > 0) {
            $avisos .= self::aviso(self::COLOR_CIELO, self::COLOR_TEXTO,
                '📌 Hay <b>' . self::plural($d['pendientes_aprobar'], 'solicitud pendiente', 'solicitudes pendientes') . '</b> por aprobar.');
        }
        if (empty($acad['dia_habil'])) {
            $avisos .= self::aviso(self::COLOR_CIELO, self::COLOR_TEXTO, '🗓️ Hoy no es día hábil en el calendario.');
        } elseif ((int) $acad['franjas_sin_ejecutar'] > 0) {
            $avisos .= self::aviso(self::COLOR_ROSA, self::COLOR_ROSA_TEXTO,
                '⏰ Quedaron <b>' . self::plural((int) $acad['franjas_sin_ejecutar'], 'franja', 'franjas') . ' del horario sin ejecutar</b> hoy.');
        }

        $seccionAusentes = self::seccionAusentes($d);
        $seccionCartera = self::seccionCartera($d);

        $estiloSeccion = 'background:#ffffff;border:1px solid ' . self::COLOR_BORDE . ';border-radius:14px;padding:16px 12px 10px;margin:0 0 16px;';
        $estiloTitulo = 'margin:0 0 12px;font-size:17px;color:' . self::COLOR_TEXTO . ';';

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:' . self::COLOR_FONDO . ';font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . self::COLOR_TEXTO . ';">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . self::COLOR_FONDO . ';"><tr><td align="center" style="padding:16px 8px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;">'
            . '<tr><td style="background-color:' . self::COLOR_DORADO_CLARO . ';background-image:linear-gradient(135deg,' . self::COLOR_DORADO_CLARO . ',' . self::COLOR_CIELO . ');border:1px solid ' . self::COLOR_BORDE . ';border-radius:16px;padding:22px 22px 18px;">'
            . '<div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:' . self::COLOR_DORADO . ';font-weight:600;">' . self::e($d['institucion']) . '</div>'
            . '<div style="font-size:24px;font-weight:700;margin:4px 0 6px;">Resumen del día</div>'
            . '<div style="font-size:14px;color:' . self::COLOR_SUAVE . ';">' . self::e(ucfirst(self::fechaLarga($d['fecha']))) . '</div>'
            . '<div style="font-size:15px;margin-top:14px;">Hola, {{NOMBRE_COLABORADOR}}. Esto fue lo que pasó hoy en el jardín, con lo acumulado de la semana y del mes.</div>'
            . '</td></tr>'
            . '<tr><td style="height:16px;"></td></tr>'
            . '<tr><td>'
            . $avisos
            . '<div style="' . $estiloSeccion . '"><h2 style="' . $estiloTitulo . '">✨ Lo que pasó</h2>' . $tablaIndicadores . self::seccionClasesDocentes($d['clases_docentes']) . '</div>'
            . '<div style="' . $estiloSeccion . '"><h2 style="' . $estiloTitulo . '">🎯 Indicadores del corte</h2>' . self::seccionIndicadores($d['indicadores']) . '</div>'
            . '<div style="' . $estiloSeccion . '"><h2 style="' . $estiloTitulo . '">👀 Para tener presente</h2>' . $seccionAusentes . $seccionCartera . '</div>'
            . '</td></tr>'
            . '<tr><td align="center" style="padding:6px 0 0;font-size:12px;color:' . self::COLOR_SUAVE . ';">'
            . 'Semana desde el lunes ' . self::e(self::fechaCorta($p['semana']['desde'])) . ' · Mes desde el ' . self::e(self::fechaCorta($p['mes']['desde'])) . '. '
            . 'Los promedios de asistencia cuentan solo días hábiles.<br>Enviado por Genialisis.'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private static function seccionAusentes(array $d)
    {
        $titulo = '<div style="font-weight:600;margin:0 0 8px;">🚸 Estudiantes con más de '
            . self::plural($d['dias_ausencia'], 'día hábil', 'días hábiles') . ' sin venir</div>';

        if ($d['ausentes_total'] === 0) {
            return $titulo . self::aviso(self::COLOR_MENTA, '#2f6b4f', 'Ningún estudiante lleva tanto tiempo sin venir. 🎉');
        }

        $filas = '';
        foreach ($d['ausentes'] as $a) {
            $ultima = $a['ultima_asistencia'] ? self::fechaCorta($a['ultima_asistencia']) : 'Nunca ha venido';
            $filas .= '<tr>'
                . self::td(self::e($a['nombre']))
                . self::td(self::e($a['nombre_grupo']))
                . self::td('<b>' . (int) $a['dias_sin_venir'] . '</b>', 'center')
                . self::td(self::e($ultima), 'center')
                . '</tr>';
        }

        $mas = '';
        if ($d['ausentes_total'] > count($d['ausentes'])) {
            $mas = '<div style="font-size:12px;color:' . self::COLOR_SUAVE . ';margin-top:6px;">Y '
                . ($d['ausentes_total'] - count($d['ausentes'])) . ' más. Ver el reporte de indicadores de asistencia.</div>';
        }

        return $titulo
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;margin-bottom:16px;">'
            . '<tr>' . self::th('Estudiante') . self::th('Grupo') . self::th('Días', 'center') . self::th('Última vez', 'center') . '</tr>'
            . $filas
            . '</table>' . $mas;
    }

    private static function seccionCartera(array $d)
    {
        $titulo = '<div style="font-weight:600;margin:12px 0 8px;">💳 Cartera de estudiantes vencida hace más de '
            . self::plural($d['dias_cartera'], 'día', 'días') . '</div>';

        if ($d['cartera_estudiantes'] === 0) {
            return $titulo . self::aviso(self::COLOR_MENTA, '#2f6b4f', 'No hay cartera de estudiantes con esa antigüedad. 🎉');
        }

        $resumen = self::aviso(self::COLOR_DORADO_CLARO, self::COLOR_TEXTO,
            'Total: <b>' . self::pesos($d['cartera_total']) . '</b> de '
            . self::plural($d['cartera_estudiantes'], 'estudiante', 'estudiantes') . '.');

        $filas = '';
        foreach ($d['cartera_top'] as $c) {
            $nombre = self::e($c['nombre']) . ($c['activo'] === 1 ? '' : ' <span style="color:' . self::COLOR_SUAVE . ';">(retirado)</span>');
            $filas .= '<tr>'
                . self::td($nombre)
                . self::td(self::e($c['nombre_grupo']))
                . self::td('<b>' . self::pesos($c['saldo']) . '</b>', 'right')
                . self::td((int) $c['dias_max_vencido'], 'center')
                . '</tr>';
        }

        return $titulo . $resumen
            . '<div style="font-size:13px;color:' . self::COLOR_SUAVE . ';margin:0 0 6px;">Los ' . count($d['cartera_top']) . ' con mayor saldo:</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">'
            . '<tr>' . self::th('Estudiante') . self::th('Grupo') . self::th('Saldo', 'right') . self::th('Días máx.', 'center') . '</tr>'
            . $filas
            . '</table>';
    }

    /** Clases ejecutadas por docente; en rosa quien no lleva clases en el mes */
    private static function seccionClasesDocentes(array $filas)
    {
        $titulo = '<div style="font-weight:600;margin:16px 0 8px;">👩‍🏫 Clases ejecutadas por docente</div>';
        if (empty($filas)) {
            return $titulo . self::aviso(self::COLOR_CIELO, self::COLOR_TEXTO, 'No hay docentes activos con grupos o áreas asignadas.');
        }

        $html = '';
        foreach ($filas as $f) {
            $estilo = $f['mes'] === 0 ? 'color:' . self::COLOR_ROSA_TEXTO . ';' : '';
            $html .= '<tr>'
                . self::td('<span style="' . $estilo . '">' . self::e($f['nombre']) . '</span>')
                . self::td('<b>' . $f['hoy'] . '</b>', 'center')
                . self::td($f['semana'], 'center')
                . self::td($f['mes'], 'center')
                . '</tr>';
        }

        return $titulo
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">'
            . '<tr>' . self::th('Docente') . self::th('Hoy', 'center') . self::th('Semana', 'center') . self::th('Mes', 'center') . '</tr>'
            . $html
            . '</table>';
    }

    /**
     * Cuadro compacto de indicadores trabajados del corte: áreas en filas y
     * grupos en columnas, con el porcentaje en color; debajo, por grupo, su
     * área más atrasada con algunos indicadores sin trabajar.
     */
    private static function seccionIndicadores(array $ind)
    {
        if ($ind['corte'] === null) {
            return self::aviso(self::COLOR_CIELO, self::COLOR_TEXTO, '🗓️ No hay un corte académico vigente para esta fecha.');
        }
        $corte = $ind['corte'];
        $subtitulo = '<div style="font-size:12px;color:' . self::COLOR_SUAVE . ';margin:0 0 8px;">'
            . self::e($corte['nombre']) . ', del ' . self::e(self::fechaCorta($corte['fecha_inicio'])) . ' al ' . self::e(self::fechaCorta($corte['fecha_fin']))
            . '. Porcentaje de indicadores trabajados en clases ejecutadas.</div>';

        if (empty($ind['grupos'])) {
            return $subtitulo . self::aviso(self::COLOR_CIELO, self::COLOR_TEXTO, 'No hay indicadores de este corte para los grupos.');
        }

        $cabecera = '<tr>' . self::th('Área');
        foreach ($ind['grupos'] as $nombreGrupo) {
            $cabecera .= self::th($nombreGrupo, 'center');
        }
        $cabecera .= '</tr>';

        $filas = '';
        foreach ($ind['areas'] as $idArea => $nombreArea) {
            $filas .= '<tr><td style="padding:5px 6px;border-bottom:1px solid ' . self::COLOR_BORDE . ';font-size:11px;">' . self::e($nombreArea) . '</td>';
            foreach ($ind['grupos'] as $idGrupo => $nombreGrupo) {
                if (!isset($ind['celdas'][$idArea][$idGrupo])) {
                    $filas .= '<td align="center" style="padding:5px 4px;border-bottom:1px solid ' . self::COLOR_BORDE . ';color:' . self::COLOR_SUAVE . ';">—</td>';
                    continue;
                }
                $c = $ind['celdas'][$idArea][$idGrupo];
                $pct = (int) round(($c['trabajados'] / $c['total']) * 100);
                $fondo = $pct < 40 ? self::COLOR_ROSA : ($pct < 70 ? self::COLOR_AMARILLO : self::COLOR_MENTA);
                $filas .= '<td align="center" style="padding:5px 4px;border-bottom:1px solid ' . self::COLOR_BORDE . ';background:' . $fondo . ';">'
                    . '<div style="font-weight:700;font-size:13px;">' . $pct . '%</div>'
                    . '<div style="font-size:10px;color:' . self::COLOR_SUAVE . ';">' . $c['trabajados'] . '/' . $c['total'] . '</div>'
                    . '</td>';
            }
            $filas .= '</tr>';
        }

        $cuadro = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:12px;margin-bottom:10px;">'
            . $cabecera . $filas . '</table>';

        $atrasados = '';
        foreach ($ind['atrasados'] as $a) {
            $nombres = array();
            foreach ($a['sin_trabajar'] as $nombre) {
                $nombres[] = self::e(self::recortar($nombre, 70));
            }
            $mas = $a['sin_trabajar_total'] > count($a['sin_trabajar'])
                ? ' <span style="color:' . self::COLOR_SUAVE . ';">(y ' . ($a['sin_trabajar_total'] - count($a['sin_trabajar'])) . ' más)</span>'
                : '';
            $atrasados .= '<div style="font-size:12px;padding:5px 0;border-bottom:1px dashed ' . self::COLOR_BORDE . ';">'
                . '<b>' . self::e($a['nombre_grupo']) . '</b> · ' . self::e($a['nombre_area'])
                . ' <span style="color:' . self::COLOR_SUAVE . ';">(' . $a['trabajados'] . ' de ' . $a['total'] . ')</span><br>'
                . '<span style="color:' . self::COLOR_SUAVE . ';">Sin trabajar:</span> ' . implode(' · ', $nombres) . $mas
                . '</div>';
        }
        if ($atrasados !== '') {
            $atrasados = '<div style="font-weight:600;font-size:13px;margin:4px 0 4px;">Lo más atrasado por grupo</div>' . $atrasados;
        } else {
            $atrasados = self::aviso(self::COLOR_MENTA, self::COLOR_MENTA_TEXTO, 'Todos los indicadores del corte ya se trabajaron. 🎉');
        }

        return $subtitulo . $cuadro . $atrasados;
    }

    // =========================================================
    // RESUMEN DOCENTE: HTML
    // =========================================================

    private static function htmlResumenDocente(array $d)
    {
        $p = $d['periodos'];

        $avisos = '';
        if (!$d['tiene_asignacion']) {
            $avisos .= self::aviso(self::COLOR_CIELO, self::COLOR_TEXTO,
                '📌 No tienes grupos ni áreas asignados en el sistema. Pídele a coordinación que te los asigne para ver aquí lo de tus grupos.');
        }
        if (!$d['dia_habil']) {
            $avisos .= self::aviso(self::COLOR_CIELO, self::COLOR_TEXTO, '🗓️ Hoy no es día hábil en el calendario.');
        } elseif (!empty($d['franjas_sin_ejecutar'])) {
            $lista = array();
            foreach ($d['franjas_sin_ejecutar'] as $f) {
                $lista[] = self::e($f['nombre_grupo']) . ' · ' . self::e($f['nombre_area']) . ' <span style="color:' . self::COLOR_SUAVE . ';">'
                    . self::e($f['hora_inicial']) . ' a ' . self::e($f['hora_final']) . '</span>';
            }
            $avisos .= self::aviso(self::COLOR_ROSA, self::COLOR_ROSA_TEXTO,
                '⏰ Hoy quedaron <b>' . self::plural(count($d['franjas_sin_ejecutar']), 'franja', 'franjas') . ' sin ejecutar</b>:<br>' . implode('<br>', $lista));
        } elseif ($d['franjas_hoy'] > 0) {
            $avisos .= self::aviso(self::COLOR_MENTA, self::COLOR_MENTA_TEXTO, '✅ Ninguna de tus franjas de hoy quedó sin ejecutar.');
        }

        $tablaClases = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">'
            . '<tr>' . self::th('Indicador') . self::th('Hoy', 'center') . self::th('Semana', 'center') . self::th('Mes', 'center') . '</tr>'
            . self::filaIndicador('📚', 'Clases ejecutadas',
                self::celdaValor($p['hoy']['ejecutadas'], ''),
                self::celdaValor($p['semana']['ejecutadas'], ''),
                self::celdaValor($p['mes']['ejecutadas'], ''))
            . self::filaIndicador('✏️', 'Sin calificar completas',
                self::celdaValor($p['hoy']['sin_calificar'], ''),
                self::celdaValor($p['semana']['sin_calificar'], ''),
                self::celdaValor($p['mes']['sin_calificar'], ''))
            . '</table>';

        $asistencia = '';
        if (empty($d['asistencia_grupos'])) {
            $asistencia = self::aviso(self::COLOR_CIELO, self::COLOR_TEXTO, 'Tus grupos no tienen estudiantes activos.');
        } else {
            $filas = '';
            foreach ($d['asistencia_grupos'] as $g) {
                $pct = $g['total'] > 0 ? round(($g['asistieron'] / $g['total']) * 100) : 0;
                $filas .= '<tr>'
                    . self::td(self::e($g['nombre']))
                    . self::td('<b>' . $g['asistieron'] . '</b> de ' . $g['total'], 'center')
                    . self::td($pct . '%', 'center')
                    . '</tr>';
            }
            $asistencia = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">'
                . '<tr>' . self::th('Grupo') . self::th('Asistieron', 'center') . self::th('%', 'center') . '</tr>'
                . $filas . '</table>';
        }

        $cuerpo = $avisos
            . self::seccion('✨ Tus clases', $tablaClases
                . '<div style="font-size:12px;color:' . self::COLOR_SUAVE . ';margin-top:8px;">Sin calificar completas: clases ejecutadas en grupos calificables donde faltan estudiantes por calificar.</div>')
            . self::seccion('🧒 Asistencia de hoy en tus grupos', $asistencia)
            . self::seccion('👀 Para tener presente', self::seccionAusentes($d)
                . '<div style="font-weight:600;margin:12px 0 4px;">🎯 Indicadores del corte en tus grupos y áreas</div>'
                . self::seccionIndicadores($d['indicadores']));

        $pie = 'Semana desde el lunes ' . self::e(self::fechaCorta($d['inicio_semana'])) . ' · Mes desde el ' . self::e(self::fechaCorta($d['inicio_mes'])) . '.<br>Enviado por Genialisis.';

        return self::plantillaCorreo($d['institucion'], 'Tu resumen del día', $d['fecha'],
            'Hola, {{NOMBRE_COLABORADOR}}. Esto fue lo que pasó hoy en tus clases y tus grupos, con lo acumulado de la semana y del mes.',
            $cuerpo, $pie);
    }

    /** Sección blanca con título, como las del resumen de coordinación */
    private static function seccion($titulo, $html)
    {
        return '<div style="background:#ffffff;border:1px solid ' . self::COLOR_BORDE . ';border-radius:14px;padding:16px 12px 10px;margin:0 0 16px;">'
            . '<h2 style="margin:0 0 12px;font-size:17px;color:' . self::COLOR_TEXTO . ';">' . $titulo . '</h2>' . $html . '</div>';
    }

    /** Encabezado glaseado, cuerpo y pie del correo, con estilos en línea */
    private static function plantillaCorreo($institucion, $titulo, $fecha, $intro, $cuerpo, $pie)
    {
        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:' . self::COLOR_FONDO . ';font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . self::COLOR_TEXTO . ';">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . self::COLOR_FONDO . ';"><tr><td align="center" style="padding:16px 8px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;">'
            . '<tr><td style="background-color:' . self::COLOR_DORADO_CLARO . ';background-image:linear-gradient(135deg,' . self::COLOR_DORADO_CLARO . ',' . self::COLOR_CIELO . ');border:1px solid ' . self::COLOR_BORDE . ';border-radius:16px;padding:22px 22px 18px;">'
            . '<div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:' . self::COLOR_DORADO . ';font-weight:600;">' . self::e($institucion) . '</div>'
            . '<div style="font-size:24px;font-weight:700;margin:4px 0 6px;">' . self::e($titulo) . '</div>'
            . '<div style="font-size:14px;color:' . self::COLOR_SUAVE . ';">' . self::e(ucfirst(self::fechaLarga($fecha))) . '</div>'
            . '<div style="font-size:15px;margin-top:14px;">' . $intro . '</div>'
            . '</td></tr>'
            . '<tr><td style="height:16px;"></td></tr>'
            . '<tr><td>' . $cuerpo . '</td></tr>'
            . '<tr><td align="center" style="padding:6px 0 0;font-size:12px;color:' . self::COLOR_SUAVE . ';">' . $pie . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private static function filaIndicador($icono, $nombre, $hoy, $semana, $mes)
    {
        $borde = 'border-bottom:1px solid ' . self::COLOR_BORDE . ';';
        return '<tr>'
            . '<td style="padding:10px 6px;' . $borde . 'font-weight:600;font-size:13px;">' . $icono . ' ' . self::e($nombre) . '</td>'
            . '<td align="center" style="padding:10px 4px;' . $borde . 'background:' . self::COLOR_DORADO_CLARO . ';">' . $hoy . '</td>'
            . '<td align="center" style="padding:10px 4px;' . $borde . '">' . $semana . '</td>'
            . '<td align="center" style="padding:10px 4px;' . $borde . '">' . $mes . '</td>'
            . '</tr>';
    }

    private static function celdaValor($valor, $detalle)
    {
        $html = '<div style="font-size:15px;font-weight:700;">' . self::e((string) $valor) . '</div>';
        if ((string) $detalle !== '') {
            $html .= '<div style="font-size:11px;color:' . self::COLOR_SUAVE . ';">' . self::e((string) $detalle) . '</div>';
        }
        return $html;
    }

    private static function aviso($fondo, $color, $html)
    {
        return '<div style="background:' . $fondo . ';color:' . $color . ';border-radius:10px;padding:10px 14px;margin:0 0 12px;font-size:14px;">' . $html . '</div>';
    }

    private static function th($texto, $alinear = 'left')
    {
        return '<th align="' . $alinear . '" style="padding:6px 6px;color:' . self::COLOR_DORADO . ';font-size:11px;text-transform:uppercase;border-bottom:1px solid ' . self::COLOR_BORDE . ';">' . self::e($texto) . '</th>';
    }

    private static function td($html, $alinear = 'left')
    {
        return '<td align="' . $alinear . '" style="padding:6px 6px;border-bottom:1px solid ' . self::COLOR_BORDE . ';">' . $html . '</td>';
    }

    // =========================================================
    // AYUDANTES
    // =========================================================

    /**
     * Arma "campo IN (:prefijo_0, :prefijo_1, ...)" con sus parámetros, para
     * listas de ids que se enlazan uno por uno.
     *
     * @return array [sql, params]
     */
    private static function filtroIn($campo, $prefijo, array $valores)
    {
        $marcas = array();
        $params = array();
        foreach (array_values($valores) as $i => $valor) {
            $marca = ':' . $prefijo . '_' . $i;
            $marcas[] = $marca;
            $params[$marca] = $valor;
        }
        return array($campo . ' IN (' . implode(', ', $marcas) . ')', $params);
    }

    private static function configNumero(PDO $db, $clave, $porDefecto)
    {
        $stmt = $db->prepare("SELECT valor_numero FROM configuracion_global
                              WHERE clave = :clave AND id_tenant = :id_tenant LIMIT 1");
        $stmt->bindValue(':clave', $clave);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $valor = $stmt->fetchColumn();
        return ($valor === false || $valor === null) ? $porDefecto : (int) $valor;
    }

    private static function configTexto(PDO $db, $clave, $porDefecto)
    {
        $stmt = $db->prepare("SELECT valor_texto FROM configuracion_global
                              WHERE clave = :clave AND id_tenant = :id_tenant LIMIT 1");
        $stmt->bindValue(':clave', $clave);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $valor = $stmt->fetchColumn();
        return ($valor === false || trim((string) $valor) === '') ? $porDefecto : trim((string) $valor);
    }

    private static function pesos($valor)
    {
        return '$' . number_format((float) $valor, 0, ',', '.');
    }

    private static function decimal($valor)
    {
        return number_format((float) $valor, 1, ',', '.');
    }

    /** Corta un texto largo con puntos suspensivos */
    private static function recortar($texto, $max)
    {
        $texto = trim((string) $texto);
        return mb_strlen($texto) > $max ? rtrim(mb_substr($texto, 0, $max - 1)) . '…' : $texto;
    }

    private static function plural($n, $singular, $plural)
    {
        return $n . ' ' . ((int) $n === 1 ? $singular : $plural);
    }

    private static function e($texto)
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }

    private static function fechaLarga($fecha)
    {
        $dias = array(1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo');
        $t = strtotime($fecha);
        return $dias[(int) date('N', $t)] . ' ' . (int) date('j', $t) . ' de ' . self::mes((int) date('n', $t)) . ' de ' . date('Y', $t);
    }

    private static function fechaCorta($fecha)
    {
        $t = strtotime($fecha);
        return (int) date('j', $t) . ' de ' . self::mes((int) date('n', $t));
    }

    private static function mes($n)
    {
        $meses = array(1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
            'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
        return $meses[$n];
    }
}
