<?php
/*=============================================
SERVICIO - MOTOR DE REPORTES POR CORREO
Archivo: services/motor-reportes-correo.service.php

Arma y envía los reportes por correo que cada colaborador tiene asociados en
reportes_correo_colaboradores. Cada tipo de reporte (tipos_reporte_correo)
tiene su propio armado aquí, identificado por su código:

  RESUMEN_COORDINACION  Resumen general del jardín con tres columnas (hoy,
                        semana y mes) y lo que hay que tener presente:
                        estudiantes que llevan días sin venir y cartera
                        vencida de estudiantes.

Lo dispara el cron (cron/reportes-correo.php, un proceso por tenant) y el
botón "Enviar ahora" del listado. El cron no repite un reporte que ya salió
bien ese día para el mismo colaborador; el envío manual siempre sale.

Los umbrales del reporte salen de configuracion_global (valor_numero):
  reporte_correo_dias_ausencia  Días hábiles sin venir a partir de los cuales
                                el estudiante sale en la lista.
  reporte_correo_max_ausentes   Máximo de estudiantes que se listan.
  reporte_correo_dias_cartera   Antigüedad mínima en días de la cartera vencida.
  reporte_correo_top_cartera    Cantidad de estudiantes con mayor cartera.

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
     * $cache guarda el reporte ya armado por código de tipo, para no repetir
     * las consultas cuando varios colaboradores reciben el mismo reporte.
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
                if (!isset($cache[$codigo])) {
                    $cache[$codigo] = self::armarReporte($db, $codigo, $fecha);
                }
                $reporte = $cache[$codigo];

                if ($reporte === null) {
                    $error = 'El tipo de reporte ' . $codigo . ' no tiene armado en el sistema.';
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
     * Devuelve {asunto, html} del tipo de reporte, o null si el código no
     * tiene armado. El html lleva {{NOMBRE_COLABORADOR}} para el saludo.
     */
    private static function armarReporte(PDO $db, $codigo, $fecha)
    {
        switch ($codigo) {
            case self::CODIGO_RESUMEN_COORDINACION:
                $datos = self::datosResumenCoordinacion($db, $fecha);
                return array(
                    'asunto' => 'Resumen del día ' . self::fechaCorta($fecha) . ' - ' . $datos['institucion'],
                    'html' => self::htmlResumenCoordinacion($datos)
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

        $ausentes = self::estudiantesSinVenir($db, $fecha, $diasAusencia);
        $cartera = self::carteraVencidaEstudiantes($db, $fecha, $diasCartera);

        return array(
            'fecha' => $fecha,
            'institucion' => self::configTexto($db, 'institucion_nombre', 'Jardín'),
            'total_activos' => $totalActivos,
            'colaboradores' => $operativo['colaboradores'],
            'academico_hoy' => $academico,
            'periodos' => $periodos,
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
     */
    private static function estudiantesSinVenir(PDO $db, $fecha, $umbral)
    {
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
                    WHERE e.activo = 1 AND e.id_tenant = :id_tenant
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
            . '<div style="' . $estiloSeccion . '"><h2 style="' . $estiloTitulo . '">✨ Lo que pasó</h2>' . $tablaIndicadores . '</div>'
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
