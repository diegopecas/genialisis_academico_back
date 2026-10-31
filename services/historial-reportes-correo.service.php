<?php
/**
 * Historial de envíos de los reportes por correo (tabla
 * historial_reportes_correo). Lo escribe MotorReportesCorreo en cada intento;
 * aquí solo se consulta. Sirve al reporte "Historial de Reportes por Correo"
 * y a la sección de historial de las opciones de cada colaborador.
 */
class HistorialReportesCorreo
{
    const PERMISO_REPORTE = 'reportes.historial_reportes_correo';
    const PERMISO_COLABORADOR = 'colaboradores.reportes_correo';

    /**
     * GET /historial-reportes-correo?desde=AAAA-MM-DD&hasta=AAAA-MM-DD[&id_colaborador=]
     * Envíos con fecha de envío en el rango (por defecto, el mes en curso).
     * Con id_colaborador se limita a ese colaborador.
     */
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validarAlguno($userData, array(self::PERMISO_REPORTE, self::PERMISO_COLABORADOR));
        try {
            $db = Flight::db();
            $desde = Flight::request()->query['desde'] ?? date('Y-m-01');
            $hasta = Flight::request()->query['hasta'] ?? date('Y-m-d');
            $idColaborador = Flight::request()->query['id_colaborador'] ?? null;

            if (!self::esFechaValida($desde) || !self::esFechaValida($hasta)) {
                Flight::json(array('error' => 'Las fechas deben tener el formato AAAA-MM-DD'), 400);
                return;
            }
            if ($desde > $hasta) {
                Flight::json(array('error' => 'La fecha inicial no puede ser mayor que la fecha final'), 400);
                return;
            }

            $sql = "SELECT h.id, h.fecha_reporte, h.fecha_envio, h.origen, h.correo_destino, h.exito, h.error,
                        rc.id_colaborador, rc.id_tipo_reporte_correo,
                        TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_colaborador,
                        t.nombre AS nombre_tipo
                    FROM historial_reportes_correo h
                    INNER JOIN reportes_correo_colaboradores rc ON rc.id = h.id_reporte_correo_colaborador
                    INNER JOIN colaboradores c ON c.id = rc.id_colaborador
                    INNER JOIN personas p ON p.id = c.id_persona
                    INNER JOIN tipos_reporte_correo t ON t.id = rc.id_tipo_reporte_correo
                    WHERE h.id_tenant = :id_tenant
                      AND h.fecha_envio >= :desde
                      AND h.fecha_envio < DATE_ADD(:hasta, INTERVAL 1 DAY)";
            if ($idColaborador) {
                $sql .= " AND rc.id_colaborador = :id_colaborador";
            }
            $sql .= " ORDER BY h.fecha_envio DESC";

            $sentence = $db->prepare($sql);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':desde', $desde);
            $sentence->bindValue(':hasta', $hasta);
            if ($idColaborador) {
                $sentence->bindValue(':id_colaborador', $idColaborador);
            }
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en HistorialReportesCorreo::getAll: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo consultar el historial de envíos'), 500);
        }
    }

    private static function esFechaValida($fecha)
    {
        if (!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return false;
        }
        list($anio, $mes, $dia) = array_map('intval', explode('-', $fecha));
        return checkdate($mes, $dia, $anio);
    }
}
