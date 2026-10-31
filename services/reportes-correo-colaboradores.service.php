<?php
/**
 * Reportes por correo asociados a cada colaborador (tabla
 * reportes_correo_colaboradores). Un colaborador puede tener varios tipos de
 * reporte, pero no el mismo tipo dos veces.
 *
 * El armado y el envío los hace MotorReportesCorreo: el cron los manda cada
 * día y desde el listado se puede mandar uno ya mismo con "Enviar ahora".
 *
 * Se asignan de dos formas: a varios colaboradores a la vez desde el menú
 * Colaboradores (asignar), o uno a uno desde las opciones de cada
 * colaborador (new).
 */
class ReportesCorreoColaboradores
{
    const PERMISO = 'colaboradores.reportes_correo';

    /**
     * GET /reportes-correo-colaboradores
     * Listado con el colaborador, su correo, el tipo y el último envío.
     */
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);
        try {
            $db = Flight::db();
            $sentence = $db->prepare(self::sqlListado() . " ORDER BY p.primer_nombre, p.primer_apellido, t.nombre");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en ReportesCorreoColaboradores::getAll: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudieron consultar los reportes por correo'), 500);
        }
    }

    /**
     * GET /reportes-correo-colaboradores/colaborador/@id
     * Reportes de un colaborador, para las opciones del colaborador.
     */
    public static function getByColaborador($id)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);
        try {
            $db = Flight::db();
            $sentence = $db->prepare(self::sqlListado() . " AND rc.id_colaborador = :id_colaborador ORDER BY t.nombre");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_colaborador', $id);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en ReportesCorreoColaboradores::getByColaborador: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudieron consultar los reportes por correo del colaborador'), 500);
        }
    }

    /**
     * GET /reportes-correo-colaboradores/@id
     */
    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, id_colaborador, id_tipo_reporte_correo, activo
                                      FROM reportes_correo_colaboradores
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en ReportesCorreoColaboradores::getById: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo consultar el reporte por correo'), 500);
        }
    }

    /**
     * POST /reportes-correo-colaboradores  { id_colaborador, id_tipo_reporte_correo }
     */
    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);
        try {
            $db = Flight::db();
            $idColaborador = Flight::request()->data['id_colaborador'] ?? null;
            $idTipo = Flight::request()->data['id_tipo_reporte_correo'] ?? null;

            $error = self::validar($db, null, $idColaborador, $idTipo);
            if ($error !== null) {
                Flight::json(array('error' => $error), 400);
                return;
            }

            $id = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO reportes_correo_colaboradores
                    (id, id_tenant, id_colaborador, id_tipo_reporte_correo, activo, id_usuario_registro)
                VALUES (:id, :id_tenant, :id_colaborador, :id_tipo, 1, :id_usuario)");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_colaborador', $idColaborador);
            $sentence->bindValue(':id_tipo', $idTipo);
            $sentence->bindValue(':id_usuario', $userData->id ?? null);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en ReportesCorreoColaboradores::new: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo crear el reporte por correo'), 500);
        }
    }

    /**
     * PUT /reportes-correo-colaboradores  { id, id_colaborador, id_tipo_reporte_correo, activo }
     */
    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;
            $idColaborador = Flight::request()->data['id_colaborador'] ?? null;
            $idTipo = Flight::request()->data['id_tipo_reporte_correo'] ?? null;
            $activo = !empty(Flight::request()->data['activo']) ? 1 : 0;

            if (!$id) {
                Flight::json(array('error' => 'El id es obligatorio'), 400);
                return;
            }

            $error = self::validar($db, $id, $idColaborador, $idTipo);
            if ($error !== null) {
                Flight::json(array('error' => $error), 400);
                return;
            }

            $sentence = $db->prepare("UPDATE reportes_correo_colaboradores
                SET id_colaborador = :id_colaborador, id_tipo_reporte_correo = :id_tipo, activo = :activo
                WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id_colaborador', $idColaborador);
            $sentence->bindValue(':id_tipo', $idTipo);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en ReportesCorreoColaboradores::replace: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo actualizar el reporte por correo'), 500);
        }
    }

    /**
     * DELETE /reportes-correo-colaboradores  { id }
     * Borra la asociación y su historial de envíos (ON DELETE CASCADE).
     */
    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            $sentence = $db->prepare("DELETE FROM reportes_correo_colaboradores WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en ReportesCorreoColaboradores::delete: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo eliminar el reporte por correo'), 500);
        }
    }

    /**
     * POST /reportes-correo-colaboradores/asignar  { id_tipo_reporte_correo, ids_colaboradores: [] }
     * Asigna un tipo de reporte a varios colaboradores en una sola transacción.
     * Los que ya tienen ese tipo (activo o inactivo) no se tocan, y los que no
     * tienen un correo válido quedan por fuera.
     * Responde {creados, ya_tenian, sin_correo: [nombres]}.
     */
    public static function asignar()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);
        $db = Flight::db();
        try {
            $idTipo = Flight::request()->data['id_tipo_reporte_correo'] ?? null;
            $ids = Flight::request()->data['ids_colaboradores'] ?? array();
            $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('strval', $ids)))) : array();

            if (!$idTipo || empty($ids)) {
                Flight::json(array('error' => 'Escoge el tipo de reporte y al menos un colaborador'), 400);
                return;
            }

            $stmt = $db->prepare("SELECT 1 FROM tipos_reporte_correo WHERE id = :id AND activo = 1 AND id_tenant = :id_tenant");
            $stmt->bindValue(':id', $idTipo);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            if (!$stmt->fetchColumn()) {
                Flight::json(array('error' => 'El tipo de reporte no existe o está inactivo'), 400);
                return;
            }

            // Colaboradores del tenant que llegaron, con su correo y si ya tienen el tipo
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("SELECT c.id, c.correo_electronico,
                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS nombre,
                    EXISTS (SELECT 1 FROM reportes_correo_colaboradores rc
                            WHERE rc.id_colaborador = c.id AND rc.id_tipo_reporte_correo = ?
                              AND rc.id_tenant = c.id_tenant) AS ya_tiene
                FROM colaboradores c
                INNER JOIN personas p ON p.id = c.id_persona
                WHERE c.id IN ($placeholders) AND c.id_tenant = ?");
            $posicion = 1;
            $stmt->bindValue($posicion++, $idTipo);
            foreach ($ids as $id) {
                $stmt->bindValue($posicion++, $id);
            }
            $stmt->bindValue($posicion, TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $creados = 0;
            $yaTenian = 0;
            $sinCorreo = array();

            $db->beginTransaction();
            $insert = $db->prepare("INSERT INTO reportes_correo_colaboradores
                    (id, id_tenant, id_colaborador, id_tipo_reporte_correo, activo, id_usuario_registro)
                VALUES (:id, :id_tenant, :id_colaborador, :id_tipo, 1, :id_usuario)");

            foreach ($colaboradores as $c) {
                if ((int) $c['ya_tiene'] === 1) {
                    $yaTenian++;
                    continue;
                }
                if (!filter_var(trim((string) $c['correo_electronico']), FILTER_VALIDATE_EMAIL)) {
                    $sinCorreo[] = $c['nombre'];
                    continue;
                }
                $insert->bindValue(':id', Uuid::generar());
                $insert->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $insert->bindValue(':id_colaborador', $c['id']);
                $insert->bindValue(':id_tipo', $idTipo);
                $insert->bindValue(':id_usuario', $userData->id ?? null);
                $insert->execute();
                $creados++;
            }

            $db->commit();

            Flight::json(array('creados' => $creados, 'ya_tenian' => $yaTenian, 'sin_correo' => $sinCorreo));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en ReportesCorreoColaboradores::asignar: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudieron asignar los reportes por correo'), 500);
        }
    }

    /**
     * POST /reportes-correo-colaboradores/enviar  { id }
     * Manda ya mismo el reporte del día, aunque ya haya salido hoy.
     */
    public static function enviarAhora()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;
            if (!$id) {
                Flight::json(array('error' => 'El id es obligatorio'), 400);
                return;
            }

            $resultado = MotorReportesCorreo::enviarAhora($db, $id, date('Y-m-d'));
            if ($resultado['error'] !== null) {
                Flight::json(array('error' => $resultado['error']), 502);
                return;
            }
            Flight::json(array('ok' => true));
        } catch (Exception $e) {
            error_log('Error en ReportesCorreoColaboradores::enviarAhora: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudo enviar el reporte'), 500);
        }
    }

    /**
     * Consulta base del listado (general y por colaborador), con el último
     * envío de cada asociación. Quien la usa le agrega filtros y el ORDER BY.
     */
    private static function sqlListado()
    {
        return "SELECT rc.id, rc.id_colaborador, rc.id_tipo_reporte_correo, rc.activo,
                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_colaborador,
                    c.correo_electronico, c.activo AS colaborador_activo,
                    t.nombre AS nombre_tipo, t.alcance,
                    h.fecha_envio AS ultimo_envio, h.exito AS ultimo_exito, h.error AS ultimo_error
                FROM reportes_correo_colaboradores rc
                INNER JOIN colaboradores c ON c.id = rc.id_colaborador
                INNER JOIN personas p ON p.id = c.id_persona
                INNER JOIN tipos_reporte_correo t ON t.id = rc.id_tipo_reporte_correo
                LEFT JOIN historial_reportes_correo h ON h.id = (
                    SELECT h2.id FROM historial_reportes_correo h2
                    WHERE h2.id_reporte_correo_colaborador = rc.id AND h2.id_tenant = rc.id_tenant
                    ORDER BY h2.fecha_envio DESC LIMIT 1
                )
                WHERE rc.id_tenant = :id_tenant";
    }

    /**
     * Valida colaborador y tipo del tenant, y que el colaborador no tenga ya
     * ese tipo de reporte. Devuelve el mensaje de error o null.
     */
    private static function validar($db, $id, $idColaborador, $idTipo)
    {
        if (!$idColaborador || !$idTipo) {
            return 'El colaborador y el tipo de reporte son obligatorios';
        }

        $stmt = $db->prepare("SELECT correo_electronico FROM colaboradores WHERE id = :id AND id_tenant = :id_tenant");
        $stmt->bindValue(':id', $idColaborador);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$colaborador) {
            return 'El colaborador no existe';
        }
        if (!filter_var(trim((string) $colaborador['correo_electronico']), FILTER_VALIDATE_EMAIL)) {
            return 'El colaborador no tiene un correo válido registrado. Actualízalo en su ficha.';
        }

        $stmt = $db->prepare("SELECT 1 FROM tipos_reporte_correo WHERE id = :id AND activo = 1 AND id_tenant = :id_tenant");
        $stmt->bindValue(':id', $idTipo);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->fetchColumn()) {
            return 'El tipo de reporte no existe o está inactivo';
        }

        $sql = "SELECT 1 FROM reportes_correo_colaboradores
                WHERE id_colaborador = :id_colaborador AND id_tipo_reporte_correo = :id_tipo AND id_tenant = :id_tenant";
        if ($id !== null) {
            $sql .= " AND id <> :id";
        }
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id_colaborador', $idColaborador);
        $stmt->bindValue(':id_tipo', $idTipo);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        if ($id !== null) {
            $stmt->bindValue(':id', $id);
        }
        $stmt->execute();
        if ($stmt->fetchColumn()) {
            return 'Ese colaborador ya tiene asociado este tipo de reporte';
        }

        return null;
    }
}
