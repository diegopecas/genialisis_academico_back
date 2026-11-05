<?php
/**
 * Reportes de novedades que se le mandan al club (reportes_novedades_espacios).
 *
 * Generar un reporte toma novedades sin reportar de un mismo lugar, guarda
 * el reporte con una copia de cada novedad (ReportesNovedadesEspaciosDetalle)
 * y las deja en reportada. El PDF lo arma la pantalla con lo que devuelve
 * getById, asi que desde el historial se puede volver a generar identico.
 */
class ReportesNovedadesEspacios
{
    /** GET /reportes-novedades-espacios — el historial, el mas reciente primero */
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT r.id, r.id_lugar_curso_extra, r.nombre_lugar, r.fecha_desde, r.fecha_hasta,
                   r.cantidad, r.total_minutos, r.fecha_registro,
                   TRIM(CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.primer_apellido, ''))) AS generado_por
            FROM reportes_novedades_espacios r
            LEFT JOIN usuarios u ON u.id = r.id_usuario
            LEFT JOIN personas p ON p.id = u.id_persona
            WHERE r.id_tenant = :id_tenant
            ORDER BY r.fecha_registro DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /** GET /reportes-novedades-espacios/@id — el reporte con su detalle, para el PDF */
    public static function getById($id)
    {
        $db = Flight::db();
        $reporte = self::obtener($db, $id);
        if (!$reporte) {
            Flight::json(array('error' => 'El reporte no existe.'), 404);
            return;
        }
        Flight::json($reporte);
    }

    /**
     * POST /reportes-novedades-espacios { ids_novedades: [...] }
     * Las novedades tienen que estar sin reportar y ser de un mismo lugar.
     * Responde el reporte con su detalle.
     */
    public static function generar()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $data = Flight::request()->data;
        $ids = isset($data['ids_novedades']) && is_array($data['ids_novedades'])
            ? array_values(array_unique(array_filter($data['ids_novedades'], 'is_string'))) : array();
        if (empty($ids)) {
            Flight::json(array('error' => 'Marca las novedades que van en el reporte.'), 400);
            return;
        }

        $novedades = NovedadesEspacios::paraReporte($db, $ids);
        if (count($novedades) !== count($ids)) {
            Flight::json(array('error' => 'Una de las novedades ya no existe.'), 400);
            return;
        }
        $yaReportadas = array_filter($novedades, function ($n) { return $n['estado'] !== 'sin_reportar'; });
        if (!empty($yaReportadas)) {
            Flight::json(array('error' => 'Solo van novedades sin reportar. Las que ya salieron en un reporte se vuelven a generar desde Reportes enviados.'), 400);
            return;
        }
        $lugares = array_unique(array_column($novedades, 'id_lugar_curso_extra'));
        if (count($lugares) !== 1) {
            Flight::json(array('error' => 'El reporte es para un solo lugar: marca novedades de un mismo lugar.'), 400);
            return;
        }

        $fechas = array_column($novedades, 'fecha');
        $minutos = array_sum(array_map('intval', array_column($novedades, 'minutos')));
        $idReporte = Uuid::generar();
        try {
            $db->beginTransaction();
            $sentence = $db->prepare("INSERT INTO reportes_novedades_espacios
                (id, id_tenant, id_lugar_curso_extra, nombre_lugar, fecha_desde, fecha_hasta, cantidad, total_minutos, id_usuario)
                VALUES (:id, :id_tenant, :id_lugar, :nombre_lugar, :desde, :hasta, :cantidad, :minutos, :id_usuario)");
            $sentence->bindValue(':id', $idReporte);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_lugar', $lugares[0]);
            $sentence->bindValue(':nombre_lugar', $novedades[0]['nombre_lugar']);
            $sentence->bindValue(':desde', min($fechas));
            $sentence->bindValue(':hasta', max($fechas));
            $sentence->bindValue(':cantidad', count($novedades), PDO::PARAM_INT);
            $sentence->bindValue(':minutos', $minutos, PDO::PARAM_INT);
            $sentence->bindValue(':id_usuario', $userData->id ?? null);
            $sentence->execute();

            foreach ($novedades as $n) {
                ReportesNovedadesEspaciosDetalle::registrar($db, $idReporte, $n);
            }
            // Si otra persona alcanzo a reportar alguna entre tanto, no se mezcla
            if (NovedadesEspacios::marcarReportadas($db, $ids, $idReporte) !== count($ids)) {
                $db->rollBack();
                Flight::json(array('error' => 'Alguien más acaba de reportar alguna de estas novedades. Actualiza la lista.'), 409);
                return;
            }
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('ReportesNovedadesEspacios: no se pudo generar el reporte: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo generar el reporte.'), 500);
            return;
        }

        Flight::json(self::obtener($db, $idReporte));
    }

    /** El reporte con quien lo genero y su detalle */
    private static function obtener($db, $id)
    {
        $sentence = $db->prepare("SELECT r.id, r.id_lugar_curso_extra, r.nombre_lugar, r.fecha_desde, r.fecha_hasta,
                   r.cantidad, r.total_minutos, r.fecha_registro,
                   TRIM(CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.primer_apellido, ''))) AS generado_por
            FROM reportes_novedades_espacios r
            LEFT JOIN usuarios u ON u.id = r.id_usuario
            LEFT JOIN personas p ON p.id = u.id_persona
            WHERE r.id = :id AND r.id_tenant = :id_tenant");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $reporte = $sentence->fetch(PDO::FETCH_ASSOC);
        if (!$reporte) {
            return null;
        }
        $reporte['detalle'] = ReportesNovedadesEspaciosDetalle::listarDeReporte($db, $id);
        return $reporte;
    }
}
