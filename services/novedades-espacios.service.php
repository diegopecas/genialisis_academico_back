<?php
/**
 * Novedades de los espacios (canchas) de los lugares de cursos extra
 * (novedades_espacios): una cancha que no se pudo usar en una fecha y un
 * horario, por lluvia, mantenimiento, porque el club la ocupo...
 *
 * Estados (fijos, la pantalla depende de ellos):
 * - sin_reportar: todavia no se le ha avisado al club. Se puede editar y
 *   eliminar.
 * - reportada: salio en un reporte para el club (ReportesNovedadesEspacios).
 * - cruzada: el club ya la desconto o la devolvio.
 *
 * Al registrar se pueden marcar varias canchas del mismo lugar: se crea una
 * novedad por cada una.
 */
class NovedadesEspacios
{
    const ESTADOS = array('sin_reportar', 'reportada', 'cruzada');

    /**
     * GET /novedades-espacios?desde=&hasta=&estado=&id_lugar=
     * Todos los filtros son opcionales.
     */
    public static function getAll()
    {
        $db = Flight::db();
        $q = Flight::request()->query;
        $condiciones = array('n.id_tenant = :id_tenant');
        $parametros = array();
        if (!empty($q['desde']) && self::fechaValida($q['desde'])) {
            $condiciones[] = 'n.fecha >= :desde';
            $parametros[':desde'] = $q['desde'];
        }
        if (!empty($q['hasta']) && self::fechaValida($q['hasta'])) {
            $condiciones[] = 'n.fecha <= :hasta';
            $parametros[':hasta'] = $q['hasta'];
        }
        if (!empty($q['estado']) && in_array($q['estado'], self::ESTADOS, true)) {
            $condiciones[] = 'n.estado = :estado';
            $parametros[':estado'] = $q['estado'];
        }
        if (!empty($q['id_lugar'])) {
            $condiciones[] = 'e.id_lugar_curso_extra = :id_lugar';
            $parametros[':id_lugar'] = (string) $q['id_lugar'];
        }

        $sentence = $db->prepare(self::consultaBase() . " WHERE " . implode(' AND ', $condiciones) . "
            ORDER BY n.fecha DESC, l.nombre, e.nombre, n.hora_inicial");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        foreach ($parametros as $nombre => $valor) {
            $sentence->bindValue($nombre, $valor);
        }
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::consultaBase() . " WHERE n.id = :id AND n.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * POST /novedades-espacios
     * { ids_espacios: [...], id_tipo_novedad_espacio, fecha, hora_inicial,
     *   hora_final, observaciones } -> una novedad por cada cancha.
     */
    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $data = Flight::request()->data;

        $ids = isset($data['ids_espacios']) && is_array($data['ids_espacios'])
            ? array_values(array_unique(array_filter($data['ids_espacios'], 'is_string'))) : array();
        if (empty($ids)) {
            Flight::json(array('error' => 'Marca al menos una cancha.'), 400);
            return;
        }
        $datos = self::validarDatos($db, $data);
        if (isset($datos['error'])) {
            Flight::json(array('error' => $datos['error']), 400);
            return;
        }
        $lugar = self::lugarDeEspacios($db, $ids);
        if (isset($lugar['error'])) {
            Flight::json(array('error' => $lugar['error']), 400);
            return;
        }

        try {
            $db->beginTransaction();
            $sentence = $db->prepare("INSERT INTO novedades_espacios
                (id, id_tenant, id_espacio_lugar_curso_extra, id_tipo_novedad_espacio, fecha, hora_inicial, hora_final,
                 estado, observaciones, id_usuario)
                VALUES (:id, :id_tenant, :id_espacio, :id_tipo, :fecha, :hora_inicial, :hora_final,
                 'sin_reportar', :observaciones, :id_usuario)");
            $creadas = array();
            foreach ($ids as $idEspacio) {
                $id = Uuid::generar();
                $sentence->bindValue(':id', $id);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindValue(':id_espacio', $idEspacio);
                $sentence->bindValue(':id_tipo', $datos['id_tipo']);
                $sentence->bindValue(':fecha', $datos['fecha']);
                $sentence->bindValue(':hora_inicial', $datos['hora_inicial']);
                $sentence->bindValue(':hora_final', $datos['hora_final']);
                $sentence->bindValue(':observaciones', $datos['observaciones']);
                $sentence->bindValue(':id_usuario', $userData->id ?? null);
                $sentence->execute();
                $creadas[] = $id;
            }
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('NovedadesEspacios: no se pudieron registrar las novedades: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudieron registrar las novedades.'), 500);
            return;
        }
        Flight::json(array('ids' => $creadas));
    }

    /**
     * PUT /novedades-espacios
     * { id, id_espacio_lugar_curso_extra, id_tipo_novedad_espacio, fecha,
     *   hora_inicial, hora_final, observaciones }. Solo sin reportar.
     */
    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        $id = isset($data['id']) ? (string) $data['id'] : '';

        $actual = self::estadoDe($db, $id);
        if ($actual === null) {
            Flight::json(array('error' => 'La novedad no existe.'), 404);
            return;
        }
        if ($actual !== 'sin_reportar') {
            Flight::json(array('error' => 'Solo se puede editar una novedad sin reportar.'), 400);
            return;
        }
        $idEspacio = isset($data['id_espacio_lugar_curso_extra']) ? (string) $data['id_espacio_lugar_curso_extra'] : '';
        $lugar = self::lugarDeEspacios($db, array($idEspacio));
        if (isset($lugar['error'])) {
            Flight::json(array('error' => $lugar['error']), 400);
            return;
        }
        $datos = self::validarDatos($db, $data);
        if (isset($datos['error'])) {
            Flight::json(array('error' => $datos['error']), 400);
            return;
        }

        $sentence = $db->prepare("UPDATE novedades_espacios SET
                id_espacio_lugar_curso_extra = :id_espacio, id_tipo_novedad_espacio = :id_tipo, fecha = :fecha,
                hora_inicial = :hora_inicial, hora_final = :hora_final, observaciones = :observaciones
            WHERE id = :id AND id_tenant = :id_tenant AND estado = 'sin_reportar'");
        $sentence->bindValue(':id_espacio', $idEspacio);
        $sentence->bindValue(':id_tipo', $datos['id_tipo']);
        $sentence->bindValue(':fecha', $datos['fecha']);
        $sentence->bindValue(':hora_inicial', $datos['hora_inicial']);
        $sentence->bindValue(':hora_final', $datos['hora_final']);
        $sentence->bindValue(':observaciones', $datos['observaciones']);
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    /** DELETE /novedades-espacios { id }. Solo sin reportar. */
    public static function delete()
    {
        $db = Flight::db();
        $id = isset(Flight::request()->data['id']) ? (string) Flight::request()->data['id'] : '';
        $actual = self::estadoDe($db, $id);
        if ($actual === null) {
            Flight::json(array('error' => 'La novedad no existe.'), 404);
            return;
        }
        if ($actual !== 'sin_reportar') {
            Flight::json(array('error' => 'Solo se puede eliminar una novedad sin reportar: esta ya salió en un reporte.'), 400);
            return;
        }
        $sentence = $db->prepare("DELETE FROM novedades_espacios WHERE id = :id AND id_tenant = :id_tenant AND estado = 'sin_reportar'");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }

    /**
     * PUT /novedades-espacios/cruzar { ids: [...] }
     * Marca como cruzadas las reportadas (el club ya las desconto o
     * devolvio). Las que no estan reportadas no se tocan.
     */
    public static function cruzar()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $data = Flight::request()->data;
        $ids = isset($data['ids']) && is_array($data['ids']) ? array_values(array_filter($data['ids'], 'is_string')) : array();
        if (empty($ids)) {
            Flight::json(array('error' => 'Marca al menos una novedad reportada.'), 400);
            return;
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $sentence = $db->prepare("UPDATE novedades_espacios SET estado = 'cruzada', fecha_cruce = NOW()
            WHERE id_tenant = ? AND estado = 'reportada' AND id IN ($marcas)");
        $sentence->execute(array_merge(array(TenantContext::id()), $ids));
        $cruzadas = $sentence->rowCount();
        Flight::json(array('cruzadas' => $cruzadas, 'sin_cambio' => count($ids) - $cruzadas));
    }

    /**
     * Novedades con su cancha, lugar y tipo, para armar un reporte. Las
     * devuelve en orden de fecha, cancha y hora.
     *
     * @return array
     */
    public static function paraReporte($db, array $ids)
    {
        if (empty($ids)) {
            return array();
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $sentence = $db->prepare(self::consultaBase() . " WHERE n.id_tenant = ? AND n.id IN ($marcas)
            ORDER BY n.fecha, e.nombre, n.hora_inicial");
        $sentence->execute(array_merge(array(TenantContext::id()), $ids));
        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Las deja en reportada y amarradas al reporte. Corre dentro de la
     * transaccion de quien genera el reporte.
     */
    public static function marcarReportadas($db, array $ids, $idReporte)
    {
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $sentence = $db->prepare("UPDATE novedades_espacios SET estado = 'reportada', id_reporte_novedades_espacios = ?
            WHERE id_tenant = ? AND estado = 'sin_reportar' AND id IN ($marcas)");
        $sentence->execute(array_merge(array($idReporte, TenantContext::id()), $ids));
        return $sentence->rowCount();
    }

    // ==================== APOYO ====================

    private static function consultaBase()
    {
        return "SELECT n.id, n.id_espacio_lugar_curso_extra, n.id_tipo_novedad_espacio, n.fecha, n.hora_inicial, n.hora_final,
                n.estado, n.observaciones, n.id_reporte_novedades_espacios, n.fecha_cruce, n.fecha_registro,
                TIMESTAMPDIFF(MINUTE, n.hora_inicial, n.hora_final) AS minutos,
                e.nombre AS nombre_espacio, e.id_lugar_curso_extra, l.nombre AS nombre_lugar,
                t.nombre AS nombre_tipo,
                r.fecha_registro AS fecha_reporte
            FROM novedades_espacios n
            INNER JOIN espacios_lugares_cursos_extra e ON e.id = n.id_espacio_lugar_curso_extra AND e.id_tenant = n.id_tenant
            INNER JOIN lugares_cursos_extra l ON l.id = e.id_lugar_curso_extra AND l.id_tenant = n.id_tenant
            INNER JOIN tipos_novedad_espacio t ON t.id = n.id_tipo_novedad_espacio AND t.id_tenant = n.id_tenant
            LEFT JOIN reportes_novedades_espacios r ON r.id = n.id_reporte_novedades_espacios AND r.id_tenant = n.id_tenant";
    }

    /**
     * Tipo, fecha, horario y observaciones validados.
     *
     * @return array {id_tipo, fecha, hora_inicial, hora_final, observaciones} | {error}
     */
    private static function validarDatos($db, $data)
    {
        $idTipo = isset($data['id_tipo_novedad_espacio']) ? (string) $data['id_tipo_novedad_espacio'] : '';
        $stmt = $db->prepare("SELECT id FROM tipos_novedad_espacio WHERE id = :id AND id_tenant = :id_tenant");
        $stmt->bindValue(':id', $idTipo);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->fetch()) {
            return array('error' => 'Escoge el tipo de novedad.');
        }

        $fecha = isset($data['fecha']) ? substr((string) $data['fecha'], 0, 10) : '';
        if (!self::fechaValida($fecha)) {
            return array('error' => 'La fecha no es válida.');
        }

        $inicial = self::hora(isset($data['hora_inicial']) ? $data['hora_inicial'] : '');
        $final = self::hora(isset($data['hora_final']) ? $data['hora_final'] : '');
        if ($inicial === null || $final === null) {
            return array('error' => 'Escribe la hora desde y hasta.');
        }
        if ($final <= $inicial) {
            return array('error' => 'La hora hasta tiene que ser posterior a la hora desde.');
        }

        $observaciones = isset($data['observaciones']) && is_scalar($data['observaciones']) ? trim((string) $data['observaciones']) : '';
        return array(
            'id_tipo' => $idTipo,
            'fecha' => $fecha,
            'hora_inicial' => $inicial,
            'hora_final' => $final,
            'observaciones' => $observaciones !== '' ? mb_substr($observaciones, 0, 500) : null
        );
    }

    /**
     * Las canchas tienen que existir y ser de un mismo lugar.
     *
     * @return array {id_lugar} | {error}
     */
    private static function lugarDeEspacios($db, array $ids)
    {
        $ids = array_values(array_filter($ids, function ($id) { return is_string($id) && $id !== ''; }));
        if (empty($ids)) {
            return array('error' => 'Escoge la cancha.');
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $sentence = $db->prepare("SELECT id, id_lugar_curso_extra, activo FROM espacios_lugares_cursos_extra
            WHERE id_tenant = ? AND id IN ($marcas)");
        $sentence->execute(array_merge(array(TenantContext::id()), $ids));
        $filas = $sentence->fetchAll(PDO::FETCH_ASSOC);
        if (count($filas) !== count($ids)) {
            return array('error' => 'Una de las canchas no existe.');
        }
        $lugares = array_unique(array_column($filas, 'id_lugar_curso_extra'));
        if (count($lugares) !== 1) {
            return array('error' => 'Las canchas tienen que ser del mismo lugar.');
        }
        return array('id_lugar' => $lugares[0]);
    }

    private static function estadoDe($db, $id)
    {
        $sentence = $db->prepare("SELECT estado FROM novedades_espacios WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);
        return $fila ? $fila['estado'] : null;
    }

    private static function fechaValida($fecha)
    {
        $f = DateTime::createFromFormat('Y-m-d', (string) $fecha);
        return $f && $f->format('Y-m-d') === $fecha;
    }

    /** "HH:mm" o "HH:mm:ss" -> "HH:mm:00", o null si no es una hora */
    private static function hora($valor)
    {
        if (!is_scalar($valor) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', (string) $valor, $m)) {
            return null;
        }
        return $m[1] . ':' . $m[2] . ':00';
    }
}
