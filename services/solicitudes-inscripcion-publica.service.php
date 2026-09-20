<?php
/**
 * Solicitudes que llegan del portal publico de inscripcion.
 *
 * Los metodos que terminan en "Publico" se exponen sin token (ver la
 * excepcion de /inscripcion-publica/ en index.php) y por eso no
 * devuelven nada interno: ni id de persona, ni cartera, ni listados
 * completos. El resto son de la pantalla de administracion.
 *
 * Una solicitud no crea nada en el sistema. Al aprobarla se crean la
 * persona, el estudiante, el acudiente y la inscripcion, y de ahi sale
 * el cobro por el flujo normal.
 */
class SolicitudesInscripcionPublica
{
    /**
     * El portal solo funciona si la configuracion existe y esta encendida.
     * Se valida en cada endpoint publico, no solo al pintar la pagina.
     */
    private static function portalActivo($db)
    {
        $stmt = $db->prepare("SELECT activo FROM configuracion_portal_publico WHERE id_tenant = :id_tenant LIMIT 1");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila && (int) $fila['activo'] === 1;
    }

    // ==================== ENDPOINTS PUBLICOS ====================

    /**
     * Instituciones cliente que tienen al menos un curso con convenio
     * vigente. No se listan todas: solo las que de verdad ofrecen algo,
     * para no exponer la cartera completa de clientes del jardin.
     */
    public static function institucionesPublico()
    {
        $db = Flight::db();

        if (!self::portalActivo($db)) {
            Flight::json(array('error' => 'El portal de inscripciones no está disponible.'), 403);
            return;
        }

        $sentence = $db->prepare("SELECT DISTINCT ic.id,
        p.foto AS logo,
        CASE
            WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
            ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.primer_apellido, ''))
        END AS nombre_institucion
        FROM instituciones_cliente ic
        INNER JOIN personas p ON ic.id_persona = p.id AND p.id_tenant = ic.id_tenant
        INNER JOIN cursos_extra_x_instituciones_cliente cxic
                ON cxic.id_institucion_cliente = ic.id
               AND cxic.id_tenant = ic.id_tenant
               AND cxic.activo = 1
        INNER JOIN cursos_extra ce
                ON ce.id = cxic.id_curso_extra
               AND ce.id_tenant = ic.id_tenant
               AND ce.activo = 1
        WHERE ic.activo = 1 AND ic.id_tenant = :id_tenant
        ORDER BY nombre_institucion");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Cursos que puede tomar quien viene de esa institucion, con el cupo
     * disponible y el valor que le corresponde. El cupo ya descuenta las
     * solicitudes pendientes, no solo los inscritos.
     *
     * Sin institucion (particular) se devuelven los cursos que NO tienen
     * ningun convenio, con la tarifa interna. Un curso con convenio no se
     * ofrece al publico general: es del colegio.
     */
    public static function cursosPublico()
    {
        $db = Flight::db();

        if (!self::portalActivo($db)) {
            Flight::json(array('error' => 'El portal de inscripciones no está disponible.'), 403);
            return;
        }

        $data = Flight::request()->data;
        $id_institucion_cliente = isset($data['id_institucion_cliente']) ? $data['id_institucion_cliente'] : null;
        if (empty($id_institucion_cliente)) {
            $id_institucion_cliente = null;
        }

        if ($id_institucion_cliente !== null) {
            $sql = "SELECT ce.id, ce.nombre, ce.descripcion, ce.icono, ce.color,
                    ce.fecha_inicio, ce.fecha_fin, ce.fecha_limite_inscripcion,
                    ce.edad_minima_meses, ce.edad_maxima_meses, ce.anio,
                    ce.cupo_maximo, ce.permite_sobrecupo,
                    lce.nombre AS nombre_lugar,
                    (SELECT COUNT(*) FROM estudiantes_x_cursos_extra x
                      WHERE x.id_curso_extra = ce.id AND x.activo = 1 AND x.id_tenant = ce.id_tenant) AS inscritos,
                    (SELECT COUNT(*) FROM solicitudes_inscripcion_publica s
                      WHERE s.id_curso_extra = ce.id AND s.estado = 'pendiente' AND s.id_tenant = ce.id_tenant) AS pendientes
                    FROM cursos_extra ce
                    INNER JOIN cursos_extra_x_instituciones_cliente cxic
                            ON cxic.id_curso_extra = ce.id
                           AND cxic.id_tenant = ce.id_tenant
                           AND cxic.activo = 1
                           AND cxic.id_institucion_cliente = :id_institucion_cliente
                    LEFT JOIN lugares_cursos_extra lce ON ce.id_lugar_curso_extra = lce.id
                    WHERE ce.activo = 1 AND ce.id_tenant = :id_tenant
                    ORDER BY ce.nombre";
        } else {
            $sql = "SELECT ce.id, ce.nombre, ce.descripcion, ce.icono, ce.color,
                    ce.fecha_inicio, ce.fecha_fin, ce.fecha_limite_inscripcion,
                    ce.edad_minima_meses, ce.edad_maxima_meses, ce.anio,
                    ce.cupo_maximo, ce.permite_sobrecupo,
                    lce.nombre AS nombre_lugar,
                    (SELECT COUNT(*) FROM estudiantes_x_cursos_extra x
                      WHERE x.id_curso_extra = ce.id AND x.activo = 1 AND x.id_tenant = ce.id_tenant) AS inscritos,
                    (SELECT COUNT(*) FROM solicitudes_inscripcion_publica s
                      WHERE s.id_curso_extra = ce.id AND s.estado = 'pendiente' AND s.id_tenant = ce.id_tenant) AS pendientes
                    FROM cursos_extra ce
                    LEFT JOIN lugares_cursos_extra lce ON ce.id_lugar_curso_extra = lce.id
                    WHERE ce.activo = 1 AND ce.id_tenant = :id_tenant
                      AND NOT EXISTS (
                          SELECT 1 FROM cursos_extra_x_instituciones_cliente cxic
                          WHERE cxic.id_curso_extra = ce.id AND cxic.id_tenant = ce.id_tenant
                      )
                    ORDER BY ce.nombre";
        }

        $sentence = $db->prepare($sql);
        if ($id_institucion_cliente !== null) {
            $sentence->bindParam(':id_institucion_cliente', $id_institucion_cliente);
        }
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $cursos = $sentence->fetchAll(PDO::FETCH_ASSOC);

        $hoy = date('Y-m-d');
        $response = [];

        foreach ($cursos as $curso) {
            // La tarifa se resuelve con el mismo criterio del cobro real: la
            // del convenio si existe, y si no la interna.
            $tarifa = TarifasCursosExtra::resolverTarifa($db, $curso['id'], $curso['anio'], $id_institucion_cliente);

            // El cupo descuenta los ya inscritos y las solicitudes pendientes:
            // una solicitud sin resolver esta ocupando un lugar aunque todavia
            // no exista la inscripcion.
            $cupoMaximo = $curso['cupo_maximo'] !== null ? (int) $curso['cupo_maximo'] : null;
            $ocupados = (int) $curso['inscritos'] + (int) $curso['pendientes'];
            $disponibles = $cupoMaximo !== null ? max(0, $cupoMaximo - $ocupados) : null;

            $cerrado = false;
            $motivoCierre = null;

            if (!empty($curso['fecha_limite_inscripcion']) && $hoy > $curso['fecha_limite_inscripcion']) {
                $cerrado = true;
                $motivoCierre = 'Las inscripciones cerraron el ' . $curso['fecha_limite_inscripcion'] . '.';
            } elseif ($cupoMaximo !== null && $disponibles === 0 && empty($curso['permite_sobrecupo'])) {
                $cerrado = true;
                $motivoCierre = 'No quedan cupos disponibles.';
            }

            $response[] = array(
                'id' => $curso['id'],
                'nombre' => $curso['nombre'],
                'descripcion' => $curso['descripcion'],
                'icono' => $curso['icono'],
                'color' => $curso['color'],
                'fecha_inicio' => $curso['fecha_inicio'],
                'fecha_fin' => $curso['fecha_fin'],
                'fecha_limite_inscripcion' => $curso['fecha_limite_inscripcion'],
                'edad_minima_meses' => $curso['edad_minima_meses'],
                'edad_maxima_meses' => $curso['edad_maxima_meses'],
                'anio' => $curso['anio'],
                'nombre_lugar' => $curso['nombre_lugar'],
                'cupo_maximo' => $cupoMaximo,
                'cupos_disponibles' => $disponibles,
                'inscritos' => (int) $curso['inscritos'],
                'pendientes' => (int) $curso['pendientes'],
                'permite_sobrecupo' => (int) $curso['permite_sobrecupo'],
                'cerrado' => $cerrado,
                'motivo_cierre' => $motivoCierre,
                'valor_matricula' => $tarifa ? (float) $tarifa['valor_matricula'] : 0,
                'cuotas_matricula' => $tarifa ? (int) $tarifa['cuotas_matricula'] : 1,
                'valor_pension' => $tarifa ? (float) $tarifa['valor_pension'] : 0,
                'valor_unico' => $tarifa ? (float) $tarifa['valor_unico'] : 0,
                'cuotas_unico' => $tarifa ? (int) $tarifa['cuotas_unico'] : 1,
                'tiene_tarifa' => $tarifa ? true : false
            );
        }

        Flight::json($response);
    }

    /**
     * Horarios del curso, para mostrarlos en la tarjeta.
     */
    public static function horariosPublico($id_curso_extra)
    {
        $db = Flight::db();

        if (!self::portalActivo($db)) {
            Flight::json(array('error' => 'El portal de inscripciones no está disponible.'), 403);
            return;
        }

        $sentence = $db->prepare("SELECT h.id_dia_semana, h.hora_inicial, h.hora_final, d.nombre AS nombre_dia
        FROM horarios_cursos_extra h
        LEFT JOIN dias_semana d ON h.id_dia_semana = d.id
        WHERE h.id_curso_extra = :id_curso_extra AND h.id_tenant = :id_tenant
        ORDER BY h.id_dia_semana, h.hora_inicial");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Catalogos que necesita el formulario publico: tipos de identificacion,
     * generos y tipos de acudiente. Son tablas globales, sin id_tenant, y no
     * exponen nada del jardin.
     */
    public static function catalogosPublico()
    {
        $db = Flight::db();

        if (!self::portalActivo($db)) {
            Flight::json(array('error' => 'El portal de inscripciones no está disponible.'), 403);
            return;
        }

        $tipos = $db->query("SELECT id, nombre FROM tipos_identificacion ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
        $generos = $db->query("SELECT id, nombre FROM generos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
        $tiposAcudiente = $db->query("SELECT id, nombre FROM tipos_acudiente ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

        Flight::json(array(
            'tipos_identificacion' => $tipos,
            'generos' => $generos,
            'tipos_acudiente' => $tiposAcudiente
        ));
    }

    /**
     * Recibe la solicitud. No crea estudiante ni inscripcion: solo guarda
     * lo que llego, en estado pendiente.
     *
     * No se reserva cupo: si entran dos y queda uno, el choque aparece al
     * aprobar la segunda.
     */
    public static function registrarPublico()
    {
        try {
            $db = Flight::db();

            if (!self::portalActivo($db)) {
                Flight::json(array('error' => 'El portal de inscripciones no está disponible.'), 403);
                return;
            }

            $data = Flight::request()->data;

            $id_curso_extra = isset($data['id_curso_extra']) ? $data['id_curso_extra'] : null;
            $id_institucion_cliente = isset($data['id_institucion_cliente']) ? $data['id_institucion_cliente'] : null;
            if (empty($id_institucion_cliente)) {
                $id_institucion_cliente = null;
            }

            // La aceptacion de terminos y de la politica de datos es obligatoria:
            // sin ella no hay autorizacion para tratar los datos del menor.
            $acepto = isset($data['acepto_terminos']) ? (int) $data['acepto_terminos'] : 0;
            if ($acepto !== 1) {
                Flight::json(array('error' => 'Debe aceptar los términos y la política de tratamiento de datos.'), 400);
                return;
            }

            $est_numero = isset($data['est_numero_identificacion']) ? trim($data['est_numero_identificacion']) : '';
            $est_nombre = isset($data['est_primer_nombre']) ? trim($data['est_primer_nombre']) : '';
            $est_apellido = isset($data['est_primer_apellido']) ? trim($data['est_primer_apellido']) : '';

            if (!$id_curso_extra || $est_numero === '' || $est_nombre === '' || $est_apellido === '') {
                Flight::json(array('error' => 'Faltan datos obligatorios.'), 400);
                return;
            }

            // El curso tiene que existir, estar activo y corresponder a lo que
            // el portal ofrece para esa institucion. Se revalida aqui porque el
            // formulario es publico y podrian mandar cualquier id.
            $stmtCurso = $db->prepare("SELECT ce.id, ce.nombre, ce.fecha_limite_inscripcion,
                       ce.cupo_maximo, ce.permite_sobrecupo
                FROM cursos_extra ce
                WHERE ce.id = :id_curso_extra AND ce.activo = 1 AND ce.id_tenant = :id_tenant");
            $stmtCurso->bindParam(':id_curso_extra', $id_curso_extra);
            $stmtCurso->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtCurso->execute();
            $curso = $stmtCurso->fetch(PDO::FETCH_ASSOC);

            if (!$curso) {
                Flight::json(array('error' => 'El curso seleccionado no está disponible.'), 400);
                return;
            }

            if (!empty($curso['fecha_limite_inscripcion']) && date('Y-m-d') > $curso['fecha_limite_inscripcion']) {
                Flight::json(array('error' => 'Las inscripciones a este curso ya cerraron.'), 400);
                return;
            }

            // El cupo cuenta inscritos mas solicitudes pendientes. Se revalida
            // aqui y no solo al pintar la pagina, porque entre que la persona
            // vio los cupos y envio el formulario pudo llenarse.
            if (!empty($curso['cupo_maximo']) && empty($curso['permite_sobrecupo'])) {
                $stmtCupo = $db->prepare("
                    SELECT
                        (SELECT COUNT(*) FROM estudiantes_x_cursos_extra x
                          WHERE x.id_curso_extra = :id_curso AND x.activo = 1 AND x.id_tenant = :id_tenant) +
                        (SELECT COUNT(*) FROM solicitudes_inscripcion_publica s
                          WHERE s.id_curso_extra = :id_curso2 AND s.estado = 'pendiente' AND s.id_tenant = :id_tenant2)
                        AS ocupados
                ");
                $stmtCupo->bindParam(':id_curso', $id_curso_extra);
                $stmtCupo->bindParam(':id_curso2', $id_curso_extra);
                $stmtCupo->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtCupo->bindValue(':id_tenant2', TenantContext::id(), PDO::PARAM_INT);
                $stmtCupo->execute();
                $fila = $stmtCupo->fetch(PDO::FETCH_ASSOC);

                if ($fila && (int) $fila['ocupados'] >= (int) $curso['cupo_maximo']) {
                    Flight::json(array('error' => 'Ya no quedan cupos disponibles en este curso.'), 400);
                    return;
                }
            }

            if ($id_institucion_cliente !== null) {
                $stmtConvenio = $db->prepare("SELECT id FROM cursos_extra_x_instituciones_cliente
                    WHERE id_curso_extra = :id_curso_extra
                      AND id_institucion_cliente = :id_institucion_cliente
                      AND activo = 1 AND id_tenant = :id_tenant");
                $stmtConvenio->bindParam(':id_curso_extra', $id_curso_extra);
                $stmtConvenio->bindParam(':id_institucion_cliente', $id_institucion_cliente);
                $stmtConvenio->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtConvenio->execute();

                if (!$stmtConvenio->fetch()) {
                    Flight::json(array('error' => 'Ese curso no está disponible para la institución seleccionada.'), 400);
                    return;
                }
            }

            // Una solicitud pendiente por documento y curso: evita que el mismo
            // formulario enviado dos veces genere dos solicitudes.
            $stmtDup = $db->prepare("SELECT id FROM solicitudes_inscripcion_publica
                WHERE est_numero_identificacion = :est_numero
                  AND id_curso_extra = :id_curso_extra
                  AND estado = 'pendiente'
                  AND id_tenant = :id_tenant LIMIT 1");
            $stmtDup->bindParam(':est_numero', $est_numero);
            $stmtDup->bindParam(':id_curso_extra', $id_curso_extra);
            $stmtDup->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtDup->execute();

            if ($stmtDup->fetch()) {
                Flight::json(array('error' => 'Ya hay una solicitud pendiente para este documento en ese curso.'), 400);
                return;
            }

            $id = Uuid::generar();
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

            $sentence = $db->prepare("INSERT INTO solicitudes_inscripcion_publica (
                id, id_tenant, id_curso_extra, id_institucion_cliente,
                est_tipo_identificacion, est_numero_identificacion,
                est_primer_nombre, est_segundo_nombre, est_primer_apellido, est_segundo_apellido,
                est_fecha_nacimiento, est_id_genero,
                acu_tipo_identificacion, acu_numero_identificacion,
                acu_primer_nombre, acu_primer_apellido, acu_telefono, acu_correo, acu_id_tipo_acudiente,
                observaciones, estado, ip_origen, fecha_registro,
                acepto_terminos, fecha_aceptacion
            ) VALUES (
                :id, :id_tenant, :id_curso_extra, :id_institucion_cliente,
                :est_tipo, :est_numero,
                :est_primer_nombre, :est_segundo_nombre, :est_primer_apellido, :est_segundo_apellido,
                :est_fecha_nacimiento, :est_genero,
                :acu_tipo, :acu_numero,
                :acu_primer_nombre, :acu_primer_apellido, :acu_telefono, :acu_correo, :acu_tipo_acudiente,
                :observaciones, 'pendiente', :ip, NOW(),
                1, NOW()
            )");

            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_curso_extra', $id_curso_extra);
            $sentence->bindValue(':id_institucion_cliente', $id_institucion_cliente);
            $sentence->bindValue(':est_tipo', isset($data['est_tipo_identificacion']) && $data['est_tipo_identificacion'] !== '' ? (int) $data['est_tipo_identificacion'] : null);
            $sentence->bindParam(':est_numero', $est_numero);
            $sentence->bindParam(':est_primer_nombre', $est_nombre);
            $sentence->bindValue(':est_segundo_nombre', isset($data['est_segundo_nombre']) && $data['est_segundo_nombre'] !== '' ? trim($data['est_segundo_nombre']) : null);
            $sentence->bindParam(':est_primer_apellido', $est_apellido);
            $sentence->bindValue(':est_segundo_apellido', isset($data['est_segundo_apellido']) && $data['est_segundo_apellido'] !== '' ? trim($data['est_segundo_apellido']) : null);
            $sentence->bindValue(':est_fecha_nacimiento', !empty($data['est_fecha_nacimiento']) ? $data['est_fecha_nacimiento'] : null);
            $sentence->bindValue(':est_genero', isset($data['est_id_genero']) && $data['est_id_genero'] !== '' ? (int) $data['est_id_genero'] : null);
            $sentence->bindValue(':acu_tipo', isset($data['acu_tipo_identificacion']) && $data['acu_tipo_identificacion'] !== '' ? (int) $data['acu_tipo_identificacion'] : null);
            $sentence->bindValue(':acu_numero', isset($data['acu_numero_identificacion']) && $data['acu_numero_identificacion'] !== '' ? trim($data['acu_numero_identificacion']) : null);
            $sentence->bindValue(':acu_primer_nombre', isset($data['acu_primer_nombre']) && $data['acu_primer_nombre'] !== '' ? trim($data['acu_primer_nombre']) : null);
            $sentence->bindValue(':acu_primer_apellido', isset($data['acu_primer_apellido']) && $data['acu_primer_apellido'] !== '' ? trim($data['acu_primer_apellido']) : null);
            $sentence->bindValue(':acu_telefono', isset($data['acu_telefono']) && $data['acu_telefono'] !== '' ? trim($data['acu_telefono']) : null);
            $sentence->bindValue(':acu_correo', isset($data['acu_correo']) && $data['acu_correo'] !== '' ? trim($data['acu_correo']) : null);
            $sentence->bindValue(':acu_tipo_acudiente', isset($data['acu_id_tipo_acudiente']) && $data['acu_id_tipo_acudiente'] !== '' ? (int) $data['acu_id_tipo_acudiente'] : null);
            $sentence->bindValue(':observaciones', isset($data['observaciones']) && $data['observaciones'] !== '' ? trim($data['observaciones']) : null);
            $sentence->bindValue(':ip', $ip);
            $sentence->execute();

            Flight::json(array(
                'id' => $id,
                'mensaje' => 'Tu solicitud quedó registrada. El jardín la revisará y te contactará.'
            ));
        } catch (Exception $e) {
            error_log("Error en registrarPublico de solicitudes de inscripcion: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo registrar la solicitud.'), 500);
        }
    }

    // ==================== ADMINISTRACION ====================

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT s.*,
        ce.nombre AS nombre_curso,
        CASE
            WHEN pi.razon_social IS NOT NULL AND pi.razon_social != '' THEN pi.razon_social
            ELSE CONCAT(IFNULL(pi.primer_nombre, ''), ' ', IFNULL(pi.primer_apellido, ''))
        END AS nombre_institucion,
        CONCAT(IFNULL(s.est_primer_nombre, ''), ' ', IFNULL(s.est_segundo_nombre, ''), ' ',
               IFNULL(s.est_primer_apellido, ''), ' ', IFNULL(s.est_segundo_apellido, '')) AS nombre_estudiante,
        CONCAT(IFNULL(s.acu_primer_nombre, ''), ' ', IFNULL(s.acu_primer_apellido, '')) AS nombre_acudiente
        FROM solicitudes_inscripcion_publica s
        INNER JOIN cursos_extra ce ON s.id_curso_extra = ce.id
        LEFT JOIN instituciones_cliente ic ON s.id_institucion_cliente = ic.id
        LEFT JOIN personas pi ON ic.id_persona = pi.id AND pi.id_tenant = ic.id_tenant
        WHERE s.id_tenant = :id_tenant
        ORDER BY s.fecha_registro DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Solicitudes que llegaron por una institucion cliente. Alimenta el tab
     * de Solicitudes del formulario de institucion.
     *
     * Trae el cupo del curso ya calculado (inscritos mas pendientes) para
     * que la pantalla pueda avisar antes de intentar aprobar.
     */
    public static function getByInstitucion($id_institucion_cliente)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT s.*,
        ce.nombre AS nombre_curso, ce.anio, ce.cupo_maximo, ce.permite_sobrecupo,
        CONCAT(IFNULL(s.est_primer_nombre, ''), ' ', IFNULL(s.est_segundo_nombre, ''), ' ',
               IFNULL(s.est_primer_apellido, ''), ' ', IFNULL(s.est_segundo_apellido, '')) AS nombre_estudiante,
        CONCAT(IFNULL(s.acu_primer_nombre, ''), ' ', IFNULL(s.acu_primer_apellido, '')) AS nombre_acudiente,
        ta.nombre AS parentesco,
        (SELECT COUNT(*) FROM estudiantes_x_cursos_extra x
          WHERE x.id_curso_extra = ce.id AND x.activo = 1 AND x.id_tenant = ce.id_tenant) AS inscritos,
        (SELECT COUNT(*) FROM solicitudes_inscripcion_publica p
          WHERE p.id_curso_extra = ce.id AND p.estado = 'pendiente' AND p.id_tenant = ce.id_tenant) AS pendientes
        FROM solicitudes_inscripcion_publica s
        INNER JOIN cursos_extra ce ON s.id_curso_extra = ce.id
        LEFT JOIN tipos_acudiente ta ON s.acu_id_tipo_acudiente = ta.id
        WHERE s.id_institucion_cliente = :id_institucion_cliente AND s.id_tenant = :id_tenant
        ORDER BY s.estado = 'pendiente' DESC, s.fecha_registro DESC");
        $sentence->bindParam(':id_institucion_cliente', $id_institucion_cliente);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT s.*,
        ce.nombre AS nombre_curso, ce.anio, ce.fecha_inicio, ce.fecha_fin,
        CASE
            WHEN pi.razon_social IS NOT NULL AND pi.razon_social != '' THEN pi.razon_social
            ELSE CONCAT(IFNULL(pi.primer_nombre, ''), ' ', IFNULL(pi.primer_apellido, ''))
        END AS nombre_institucion,
        tie.nombre AS tipo_identificacion_estudiante,
        tia.nombre AS tipo_identificacion_acudiente,
        g.nombre AS nombre_genero
        FROM solicitudes_inscripcion_publica s
        INNER JOIN cursos_extra ce ON s.id_curso_extra = ce.id
        LEFT JOIN instituciones_cliente ic ON s.id_institucion_cliente = ic.id
        LEFT JOIN personas pi ON ic.id_persona = pi.id AND pi.id_tenant = ic.id_tenant
        LEFT JOIN tipos_identificacion tie ON s.est_tipo_identificacion = tie.id
        LEFT JOIN tipos_identificacion tia ON s.acu_tipo_identificacion = tia.id
        LEFT JOIN generos g ON s.est_id_genero = g.id
        WHERE s.id = :id AND s.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Aprueba la solicitud: crea o reusa la persona del estudiante, crea el
     * estudiante si no existe, crea o reusa la persona del acudiente y lo
     * vincula, y finalmente inscribe al curso con el convenio declarado.
     *
     * Todo en una transaccion: o queda completo o no queda nada.
     *
     * NO genera las cuentas por cobrar. Eso se hace despues desde la
     * pantalla de inscripcion, que es donde se revisan y ajustan los
     * valores antes de emitir.
     */
    public static function aprobar()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        try {
            $id = Flight::request()->data['id'];

            if (!$id) {
                Flight::json(array('error' => 'Falta el ID de la solicitud'), 400);
                return;
            }

            $stmt = $db->prepare("SELECT * FROM solicitudes_inscripcion_publica
                                  WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $sol = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sol) {
                Flight::json(array('error' => 'No se encontró la solicitud'), 404);
                return;
            }
            if ($sol['estado'] !== 'pendiente') {
                Flight::json(array('error' => 'Esta solicitud ya fue ' . $sol['estado'] . '.'), 400);
                return;
            }

            $db->beginTransaction();

            // --- Persona del estudiante: se reusa si ya existe en el tenant ---
            $idPersonaEst = self::buscarOCrearPersona(
                $db,
                $sol['est_tipo_identificacion'],
                $sol['est_numero_identificacion'],
                $sol['est_primer_nombre'],
                $sol['est_segundo_nombre'],
                $sol['est_primer_apellido'],
                $sol['est_segundo_apellido'],
                $sol['est_fecha_nacimiento'],
                $sol['est_id_genero'],
                null,
                null
            );

            // --- Estudiante ---
            $stmtEst = $db->prepare("SELECT id FROM estudiantes
                                     WHERE id_persona = :id_persona AND id_tenant = :id_tenant LIMIT 1");
            $stmtEst->bindParam(':id_persona', $idPersonaEst);
            $stmtEst->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtEst->execute();
            $filaEst = $stmtEst->fetch(PDO::FETCH_ASSOC);

            if ($filaEst) {
                $idEstudiante = $filaEst['id'];
            } else {
                $idEstudiante = Uuid::generar();
                $stmtNuevoEst = $db->prepare("INSERT INTO estudiantes
                    (id, id_tenant, fecha_ingreso, alimentacion, permanente, telefono_emergencia, activo, anno, id_persona)
                    VALUES (:id, :id_tenant, CURDATE(), 0, 0, :telefono, 1, :anno, :id_persona)");
                $stmtNuevoEst->bindValue(':id', $idEstudiante);
                $stmtNuevoEst->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtNuevoEst->bindValue(':telefono', $sol['acu_telefono'] !== null ? $sol['acu_telefono'] : '');
                $stmtNuevoEst->bindValue(':anno', (int) date('Y'), PDO::PARAM_INT);
                $stmtNuevoEst->bindValue(':id_persona', $idPersonaEst);
                $stmtNuevoEst->execute();
            }

            // --- Acudiente: solo si vino con documento ---
            if (!empty($sol['acu_numero_identificacion'])) {
                $idPersonaAcu = self::buscarOCrearPersona(
                    $db,
                    $sol['acu_tipo_identificacion'],
                    $sol['acu_numero_identificacion'],
                    $sol['acu_primer_nombre'],
                    null,
                    $sol['acu_primer_apellido'],
                    null,
                    null,
                    null,
                    $sol['acu_telefono'],
                    $sol['acu_correo']
                );

                $stmtAcu = $db->prepare("SELECT id FROM acudientes
                    WHERE id_persona = :id_persona AND id_estudiante = :id_estudiante AND id_tenant = :id_tenant LIMIT 1");
                $stmtAcu->bindParam(':id_persona', $idPersonaAcu);
                $stmtAcu->bindParam(':id_estudiante', $idEstudiante);
                $stmtAcu->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtAcu->execute();

                if (!$stmtAcu->fetch()) {
                    // id_tipo_acudiente es NOT NULL. Si el solicitante no lo
                    // escogio se toma el primer tipo del catalogo, y el jardin
                    // lo corrige despues en la ficha del estudiante.
                    $idTipoAcudiente = $sol['acu_id_tipo_acudiente'];
                    if (empty($idTipoAcudiente)) {
                        $stmtTipo = $db->prepare("SELECT id FROM tipos_acudiente ORDER BY id LIMIT 1");
                        $stmtTipo->execute();
                        $filaTipo = $stmtTipo->fetch(PDO::FETCH_ASSOC);
                        $idTipoAcudiente = $filaTipo ? $filaTipo['id'] : null;
                    }

                    if ($idTipoAcudiente !== null) {
                        $stmtNuevoAcu = $db->prepare("INSERT INTO acudientes
                            (id, id_tenant, id_tipo_acudiente, id_persona, id_estudiante,
                             es_responsable_pago, autorizado_recoger, ve_en_portal_padres, activo)
                            VALUES (:id, :id_tenant, :id_tipo_acudiente, :id_persona, :id_estudiante,
                             1, 1, 1, 1)");
                        $stmtNuevoAcu->bindValue(':id', Uuid::generar());
                        $stmtNuevoAcu->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                        $stmtNuevoAcu->bindValue(':id_tipo_acudiente', (int) $idTipoAcudiente, PDO::PARAM_INT);
                        $stmtNuevoAcu->bindValue(':id_persona', $idPersonaAcu);
                        $stmtNuevoAcu->bindValue(':id_estudiante', $idEstudiante);
                        $stmtNuevoAcu->execute();
                    }
                }
            }

            // --- Pertenencia a la institucion cliente ---
            if (!empty($sol['id_institucion_cliente'])) {
                $anio = (int) date('Y');
                $stmtPert = $db->prepare("INSERT INTO estudiantes_x_instituciones_cliente
                    (id, id_tenant, id_estudiante, id_institucion_cliente, anio, activo, fecha_inicio)
                    SELECT :id, :id_tenant, :id_estudiante, :id_institucion, :anio, 1, CURDATE()
                    FROM DUAL WHERE NOT EXISTS (
                        SELECT 1 FROM estudiantes_x_instituciones_cliente e
                        WHERE e.id_estudiante = :id_estudiante2
                          AND e.id_institucion_cliente = :id_institucion2
                          AND e.anio = :anio2 AND e.id_tenant = :id_tenant2
                    )");
                $stmtPert->bindValue(':id', Uuid::generar());
                $stmtPert->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtPert->bindValue(':id_tenant2', TenantContext::id(), PDO::PARAM_INT);
                $stmtPert->bindParam(':id_estudiante', $idEstudiante);
                $stmtPert->bindParam(':id_estudiante2', $idEstudiante);
                $stmtPert->bindValue(':id_institucion', $sol['id_institucion_cliente']);
                $stmtPert->bindValue(':id_institucion2', $sol['id_institucion_cliente']);
                $stmtPert->bindValue(':anio', $anio, PDO::PARAM_INT);
                $stmtPert->bindValue(':anio2', $anio, PDO::PARAM_INT);
                $stmtPert->execute();
            }

            // --- Inscripcion al curso ---
            $stmtCursoAnio = $db->prepare("SELECT anio, cupo_maximo, permite_sobrecupo, nombre
                                           FROM cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
            $stmtCursoAnio->bindValue(':id', $sol['id_curso_extra']);
            $stmtCursoAnio->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtCursoAnio->execute();
            $curso = $stmtCursoAnio->fetch(PDO::FETCH_ASSOC);

            // El cupo no se reserva al solicitar, asi que se valida aqui: si
            // entraron dos solicitudes y solo queda uno, la segunda falla.
            if (!empty($curso['cupo_maximo']) && empty($curso['permite_sobrecupo'])) {
                // Cuenta inscritos mas las otras solicitudes pendientes, excluyendo
                // esta: si no se excluyera, se estaria contando a si misma.
                $stmtCupo = $db->prepare("
                    SELECT
                        (SELECT COUNT(*) FROM estudiantes_x_cursos_extra x
                          WHERE x.id_curso_extra = :id_curso AND x.activo = 1 AND x.id_tenant = :id_tenant) +
                        (SELECT COUNT(*) FROM solicitudes_inscripcion_publica s
                          WHERE s.id_curso_extra = :id_curso2 AND s.estado = 'pendiente'
                            AND s.id <> :id_solicitud AND s.id_tenant = :id_tenant2)
                        AS inscritos
                ");
                $stmtCupo->bindValue(':id_curso', $sol['id_curso_extra']);
                $stmtCupo->bindValue(':id_curso2', $sol['id_curso_extra']);
                $stmtCupo->bindParam(':id_solicitud', $id);
                $stmtCupo->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtCupo->bindValue(':id_tenant2', TenantContext::id(), PDO::PARAM_INT);
                $stmtCupo->execute();
                $fila = $stmtCupo->fetch(PDO::FETCH_ASSOC);

                if ($fila && (int) $fila['inscritos'] >= (int) $curso['cupo_maximo']) {
                    $db->rollBack();
                    Flight::json(array('error' => 'El curso ' . $curso['nombre'] . ' ya alcanzó su cupo máximo de ' .
                        $curso['cupo_maximo'] . ' y no permite sobrecupo.'), 400);
                    return;
                }
            }

            $stmtYaInscrito = $db->prepare("SELECT id FROM estudiantes_x_cursos_extra
                WHERE id_estudiante = :id_estudiante AND id_curso_extra = :id_curso
                  AND activo = 1 AND id_tenant = :id_tenant LIMIT 1");
            $stmtYaInscrito->bindParam(':id_estudiante', $idEstudiante);
            $stmtYaInscrito->bindValue(':id_curso', $sol['id_curso_extra']);
            $stmtYaInscrito->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtYaInscrito->execute();
            $filaInscrito = $stmtYaInscrito->fetch(PDO::FETCH_ASSOC);

            if ($filaInscrito) {
                $idInscripcion = $filaInscrito['id'];
            } else {
                $idInscripcion = Uuid::generar();
                $stmtInscribir = $db->prepare("INSERT INTO estudiantes_x_cursos_extra
                    (id, id_tenant, id_estudiante, id_curso_extra, fecha_inscripcion, anio, activo, id_institucion_cliente)
                    VALUES (:id, :id_tenant, :id_estudiante, :id_curso, CURDATE(), :anio, 1, :id_institucion)");
                $stmtInscribir->bindValue(':id', $idInscripcion);
                $stmtInscribir->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtInscribir->bindParam(':id_estudiante', $idEstudiante);
                $stmtInscribir->bindValue(':id_curso', $sol['id_curso_extra']);
                $stmtInscribir->bindValue(':anio', (int) $curso['anio'], PDO::PARAM_INT);
                $stmtInscribir->bindValue(':id_institucion', $sol['id_institucion_cliente']);
                $stmtInscribir->execute();
            }

            $stmtCerrar = $db->prepare("UPDATE solicitudes_inscripcion_publica SET
                estado = 'aprobada',
                id_estudiante_creado = :id_estudiante,
                id_inscripcion_creada = :id_inscripcion,
                id_usuario_resolvio = :id_usuario,
                fecha_resolucion = NOW()
                WHERE id = :id AND id_tenant = :id_tenant");
            $stmtCerrar->bindParam(':id_estudiante', $idEstudiante);
            $stmtCerrar->bindParam(':id_inscripcion', $idInscripcion);
            $stmtCerrar->bindValue(':id_usuario', $userData->id ?? null);
            $stmtCerrar->bindParam(':id', $id);
            $stmtCerrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtCerrar->execute();

            $db->commit();

            Flight::json(array(
                'id' => $id,
                'id_estudiante' => $idEstudiante,
                'id_inscripcion' => $idInscripcion,
                'mensaje' => 'Solicitud aprobada. El estudiante quedó inscrito; las cuentas por cobrar se generan desde la inscripción.'
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al aprobar solicitud de inscripcion: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function rechazar()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $motivo = isset(Flight::request()->data['motivo_rechazo']) ? Flight::request()->data['motivo_rechazo'] : null;

            if (!$id) {
                Flight::json(array('error' => 'Falta el ID de la solicitud'), 400);
                return;
            }

            $sentence = $db->prepare("UPDATE solicitudes_inscripcion_publica SET
                estado = 'rechazada',
                motivo_rechazo = :motivo,
                id_usuario_resolvio = :id_usuario,
                fecha_resolucion = NOW()
                WHERE id = :id AND estado = 'pendiente' AND id_tenant = :id_tenant");
            $sentence->bindValue(':motivo', $motivo);
            $sentence->bindValue(':id_usuario', $userData->id ?? null);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() === 0) {
                Flight::json(array('error' => 'La solicitud no existe o ya fue resuelta.'), 400);
                return;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al rechazar solicitud de inscripcion: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo rechazar la solicitud.'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            if (!$id) {
                Flight::json(array('error' => 'Falta el ID de la solicitud a eliminar'), 400);
                return;
            }

            // Una solicitud aprobada no se borra: es la trazabilidad de como
            // entro ese estudiante.
            $sentence = $db->prepare("DELETE FROM solicitudes_inscripcion_publica
                                      WHERE id = :id AND estado <> 'aprobada' AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() === 0) {
                Flight::json(array('error' => 'No se puede eliminar: la solicitud no existe o ya fue aprobada.'), 400);
                return;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al eliminar solicitud de inscripcion: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo eliminar la solicitud.'), 500);
        }
    }

    /**
     * Devuelve el id de la persona con ese documento en el tenant, y la crea
     * si no existe. No pisa los datos de una persona que ya estaba: lo que
     * llega del portal es declarado por un tercero y no se puede dar por
     * bueno frente a lo que ya tiene el jardin.
     */
    private static function buscarOCrearPersona($db, $idTipo, $numero, $primerNombre, $segundoNombre,
                                                $primerApellido, $segundoApellido, $fechaNacimiento,
                                                $idGenero, $telefono, $correo)
    {
        $stmt = $db->prepare("SELECT id FROM personas
                              WHERE numero_identificacion = :numero AND id_tenant = :id_tenant LIMIT 1");
        $stmt->bindParam(':numero', $numero);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fila) {
            return $fila['id'];
        }

        $idPersona = Uuid::generar();
        $stmtNueva = $db->prepare("INSERT INTO personas (
            id, id_tenant, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido,
            id_tipo_identificacion, numero_identificacion, fecha_nacimiento, id_genero,
            telefono, correo_electronico, nacionalidad
        ) VALUES (
            :id, :id_tenant, :primer_nombre, :segundo_nombre, :primer_apellido, :segundo_apellido,
            :id_tipo, :numero, :fecha_nacimiento, :id_genero,
            :telefono, :correo, 'Colombiana'
        )");
        $stmtNueva->bindValue(':id', $idPersona);
        $stmtNueva->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtNueva->bindValue(':primer_nombre', $primerNombre);
        $stmtNueva->bindValue(':segundo_nombre', $segundoNombre);
        $stmtNueva->bindValue(':primer_apellido', $primerApellido);
        $stmtNueva->bindValue(':segundo_apellido', $segundoApellido);
        $stmtNueva->bindValue(':id_tipo', $idTipo !== null ? (int) $idTipo : null, $idTipo !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmtNueva->bindValue(':numero', $numero);
        $stmtNueva->bindValue(':fecha_nacimiento', $fechaNacimiento);
        $stmtNueva->bindValue(':id_genero', $idGenero !== null ? (int) $idGenero : null, $idGenero !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmtNueva->bindValue(':telefono', $telefono);
        $stmtNueva->bindValue(':correo', $correo);
        $stmtNueva->execute();

        return $idPersona;
    }
}
