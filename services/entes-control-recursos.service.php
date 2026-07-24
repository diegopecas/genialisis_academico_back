<?php
class EntesControlRecursos
{
    // Mapa tipo de persona (codigo) -> tabla de rol.
    // Es una lista blanca: solo estos valores pueden llegar al SQL.
    private static $tablasRol = array(
        'estudiante'   => 'estudiantes',
        'acudiente'    => 'acudientes',
        'colaborador'  => 'colaboradores',
        'autorizado'   => 'autorizados_recoger',
        'ente_control' => 'entes_control',
        'institucion'  => 'instituciones'
    );

    // ------------------------------------------------------------------
    // CONFIGURACIÓN: qué recursos tiene asignados un ente
    // ------------------------------------------------------------------
    public static function getByEnte($idEnteControl)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT ecr.id,
                       ecr.id_ente_control,
                       ecr.tipo_recurso,
                       ecr.id_tipo_persona,
                       ecr.id_tipo_documento,
                       ecr.id_reporte,
                       ecr.activo,
                       tp.codigo  AS codigo_tipo_persona,
                       tp.nombre  AS nombre_tipo_persona,
                       td.codigo  AS codigo_tipo_documento,
                       td.nombre  AS nombre_tipo_documento,
                       cr.nombre  AS nombre_reporte,
                       cr.ruta    AS ruta_reporte
                FROM entes_control_recursos ecr
                LEFT JOIN tipos_personas    tp ON tp.id = ecr.id_tipo_persona
                LEFT JOIN tipos_documentos  td ON td.id = ecr.id_tipo_documento
                LEFT JOIN catalogo_reportes cr ON cr.id = ecr.id_reporte
                WHERE ecr.id_ente_control = :id_ente_control
                  AND ecr.id_tenant = :id_tenant
                  AND ecr.activo = 1
                ORDER BY ecr.tipo_recurso ASC, tp.nombre ASC, td.nombre ASC, cr.nombre ASC
            ");
            $sentence->bindParam(':id_ente_control', $idEnteControl);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll());
        } catch (Exception $e) {
            error_log("Error en EntesControlRecursos::getByEnte: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener los recursos del ente'), 500);
        }
    }

    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'administracion.datos_maestros');

            $db = Flight::db();

            $id_ente_control   = Flight::request()->data['id_ente_control'] ?? null;
            $tipo_recurso      = Flight::request()->data['tipo_recurso'] ?? null;
            $id_tipo_persona   = Flight::request()->data['id_tipo_persona'] ?? null;
            $id_tipo_documento = Flight::request()->data['id_tipo_documento'] ?? null;
            $id_reporte        = Flight::request()->data['id_reporte'] ?? null;

            if (!$id_ente_control || !$tipo_recurso) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            // Coherencia según el tipo de recurso.
            if ($tipo_recurso === 'documento') {
                if (!$id_tipo_persona || !$id_tipo_documento) {
                    Flight::json(array('error' => 'Debe indicar tipo de persona y tipo de documento'), 400);
                    return;
                }
                $id_reporte = null;
            } else if ($tipo_recurso === 'reporte') {
                if (!$id_reporte) {
                    Flight::json(array('error' => 'Debe indicar el reporte'), 400);
                    return;
                }
                $id_tipo_persona = null;
                $id_tipo_documento = null;
            } else {
                Flight::json(array('error' => 'Tipo de recurso no válido'), 400);
                return;
            }

            // Validación de duplicados en backend: el UNIQUE no sirve porque
            // las columnas opcionales van NULL y en MariaDB NULL != NULL.
            $verif = $db->prepare("
                SELECT id FROM entes_control_recursos
                WHERE id_ente_control = :id_ente_control
                  AND id_tenant = :id_tenant
                  AND tipo_recurso = :tipo_recurso
                  AND (id_tipo_persona   <=> :id_tipo_persona)
                  AND (id_tipo_documento <=> :id_tipo_documento)
                  AND (id_reporte        <=> :id_reporte)
                LIMIT 1
            ");
            $verif->bindParam(':id_ente_control', $id_ente_control);
            $verif->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verif->bindParam(':tipo_recurso', $tipo_recurso);
            $verif->bindParam(':id_tipo_persona', $id_tipo_persona);
            $verif->bindParam(':id_tipo_documento', $id_tipo_documento);
            $verif->bindParam(':id_reporte', $id_reporte);
            $verif->execute();

            if ($verif->fetch()) {
                Flight::json(array('error' => 'Este recurso ya está asignado al ente de control'), 409);
                return;
            }

            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO entes_control_recursos
                    (id, id_tenant, id_ente_control, tipo_recurso,
                     id_tipo_persona, id_tipo_documento, id_reporte, activo)
                VALUES
                    (:id, :id_tenant, :id_ente_control, :tipo_recurso,
                     :id_tipo_persona, :id_tipo_documento, :id_reporte, 1)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_ente_control', $id_ente_control);
            $sentence->bindParam(':tipo_recurso', $tipo_recurso);
            $sentence->bindParam(':id_tipo_persona', $id_tipo_persona);
            $sentence->bindParam(':id_tipo_documento', $id_tipo_documento);
            $sentence->bindParam(':id_reporte', $id_reporte);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en EntesControlRecursos::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'administracion.datos_maestros');

            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(array('error' => 'Falta el id del recurso'), 400);
                return;
            }

            $sentence = $db->prepare("
                DELETE FROM entes_control_recursos
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el recurso'), 404);
                return;
            }

            Flight::json(array('id' => $id, 'mensaje' => 'Recurso eliminado'));
        } catch (Exception $e) {
            error_log("Error en EntesControlRecursos::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // ------------------------------------------------------------------
    // RESOLVER: convierte la configuración en el listado real que se le
    // muestra al funcionario. Devuelve documentos y reportes agrupados.
    // ------------------------------------------------------------------
    public static function resolver($idEnteControl)
    {
        try {
            $db = Flight::db();
            $idTenant = TenantContext::id();

            // 1) Recursos configurados para el ente
            $sentence = $db->prepare("
                SELECT ecr.tipo_recurso, ecr.id_tipo_documento, ecr.id_reporte,
                       tp.codigo AS codigo_tipo_persona,
                       tp.nombre AS nombre_tipo_persona,
                       td.nombre AS nombre_tipo_documento
                FROM entes_control_recursos ecr
                LEFT JOIN tipos_personas   tp ON tp.id = ecr.id_tipo_persona
                LEFT JOIN tipos_documentos td ON td.id = ecr.id_tipo_documento
                WHERE ecr.id_ente_control = :id_ente_control
                  AND ecr.id_tenant = :id_tenant
                  AND ecr.activo = 1
            ");
            $sentence->bindParam(':id_ente_control', $idEnteControl);
            $sentence->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $sentence->execute();
            $recursos = $sentence->fetchAll();

            $grupos = array();
            $reportes = array();

            foreach ($recursos as $recurso) {

                // ---- Recurso de tipo reporte ----
                if ($recurso['tipo_recurso'] === 'reporte') {
                    $rep = $db->prepare("
                        SELECT cr.id, cr.nombre, cr.ruta,
                               tr.codigo AS codigo_tipo_reporte,
                               tr.nombre AS nombre_tipo_reporte
                        FROM catalogo_reportes cr
                        LEFT JOIN tipos_reportes tr ON tr.id = cr.id_tipo_reporte
                        WHERE cr.id = :id_reporte
                          AND cr.id_tenant = :id_tenant
                          AND cr.activo = 1
                    ");
                    $rep->bindValue(':id_reporte', $recurso['id_reporte']);
                    $rep->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $rep->execute();
                    $fila = $rep->fetch();
                    if ($fila) {
                        $reportes[] = $fila;
                    }
                    continue;
                }

                // ---- Recurso de tipo documento ----
                $codigoTipoPersona = $recurso['codigo_tipo_persona'];

                // Lista blanca: si el tipo de persona no tiene tabla de rol
                // conocida, se omite en lugar de armar SQL con datos externos.
                if (!isset(self::$tablasRol[$codigoTipoPersona])) {
                    continue;
                }
                $tablaRol = self::$tablasRol[$codigoTipoPersona];

                $docs = $db->prepare("
                    SELECT dp.id,
                           dp.nombre_archivo,
                           dp.ruta_archivo,
                           dp.fecha_subida,
                           dp.fecha_vencimiento,
                           dp.observaciones,
                           dp.id_persona,
                           COALESCE(
                               NULLIF(TRIM(p.razon_social), ''),
                               TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido))
                           ) AS nombre_persona,
                           p.numero_identificacion
                    FROM documentos_personas dp
                    INNER JOIN personas p ON p.id = dp.id_persona
                    INNER JOIN {$tablaRol} rol ON rol.id_persona = p.id
                    WHERE dp.id_tipo_documento = :id_tipo_documento
                      AND dp.id_tenant = :id_tenant
                      AND dp.activo = 1
                      AND rol.id_tenant = :id_tenant_rol
                    ORDER BY nombre_persona ASC, dp.fecha_subida DESC
                ");
                $docs->bindValue(':id_tipo_documento', $recurso['id_tipo_documento']);
                $docs->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $docs->bindValue(':id_tenant_rol', $idTenant, PDO::PARAM_INT);
                $docs->execute();

                $grupos[] = array(
                    'tipo_persona'   => $recurso['nombre_tipo_persona'],
                    'codigo_persona' => $codigoTipoPersona,
                    'tipo_documento' => $recurso['nombre_tipo_documento'],
                    'documentos'     => $docs->fetchAll()
                );
            }

            Flight::json(array(
                'documentos' => $grupos,
                'reportes'   => $reportes
            ));
        } catch (Exception $e) {
            error_log("Error en EntesControlRecursos::resolver: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al resolver los recursos del ente'), 500);
        }
    }

    // ------------------------------------------------------------------
    // DISPONIBLES: todo lo que se le PUEDE asignar a un ente, en una sola
    // llamada. Documentos (desde tipos_personas_documentos) + reportes
    // marcados. Cada item trae 'asignado' según lo que ya tenga el ente.
    // ------------------------------------------------------------------
    public static function getDisponibles($idEnteControl)
    {
        try {
            $db = Flight::db();
            $idTenant = TenantContext::id();

            // Documentos posibles: pares tipo_persona / tipo_documento
            $docs = $db->prepare("
                SELECT tp.id     AS id_tipo_persona,
                       tp.codigo AS codigo_tipo_persona,
                       tp.nombre AS nombre_tipo_persona,
                       td.id     AS id_tipo_documento,
                       td.nombre AS nombre_tipo_documento,
                       tpd.orden
                FROM tipos_personas_documentos tpd
                INNER JOIN tipos_personas   tp ON tp.id = tpd.id_tipo_persona
                INNER JOIN tipos_documentos td ON td.id = tpd.id_tipo_documento
                WHERE tpd.id_tenant = :id_tenant
                ORDER BY tp.nombre ASC, tpd.orden ASC, td.nombre ASC
            ");
            $docs->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $docs->execute();
            $documentos = $docs->fetchAll();

            // Reportes marcados como expuestos a entes de control
            $reps = $db->prepare("
                SELECT cr.id     AS id_reporte,
                       cr.nombre AS nombre_reporte,
                       cr.ruta   AS ruta_reporte,
                       tr.nombre AS nombre_tipo_reporte
                FROM catalogo_reportes cr
                LEFT JOIN tipos_reportes tr ON tr.id = cr.id_tipo_reporte
                WHERE cr.id_tenant = :id_tenant
                  AND cr.activo = 1
                  AND cr.reporte_ente_control = 1
                ORDER BY cr.orden ASC, cr.nombre ASC
            ");
            $reps->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $reps->execute();
            $reportes = $reps->fetchAll();

            // Lo que el ente ya tiene asignado, para marcar
            $asig = $db->prepare("
                SELECT tipo_recurso, id_tipo_persona, id_tipo_documento, id_reporte
                FROM entes_control_recursos
                WHERE id_ente_control = :id_ente_control
                  AND id_tenant = :id_tenant
                  AND activo = 1
            ");
            $asig->bindParam(':id_ente_control', $idEnteControl);
            $asig->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $asig->execute();

            $setDoc = array();
            $setRep = array();
            foreach ($asig->fetchAll() as $a) {
                if ($a['tipo_recurso'] === 'documento') {
                    $setDoc[$a['id_tipo_persona'] . '|' . $a['id_tipo_documento']] = true;
                } else if ($a['tipo_recurso'] === 'reporte') {
                    $setRep[$a['id_reporte']] = true;
                }
            }

            foreach ($documentos as &$d) {
                $d['asignado'] = isset($setDoc[$d['id_tipo_persona'] . '|' . $d['id_tipo_documento']]);
            }
            unset($d);
            foreach ($reportes as &$r) {
                $r['asignado'] = isset($setRep[$r['id_reporte']]);
            }
            unset($r);

            Flight::json(array(
                'documentos' => $documentos,
                'reportes'   => $reportes
            ));
        } catch (Exception $e) {
            error_log("Error en EntesControlRecursos::getDisponibles: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener los recursos disponibles'), 500);
        }
    }

    // ------------------------------------------------------------------
    // SINCRONIZAR: recibe el arreglo completo de la selección y deja la
    // configuración del ente EXACTAMENTE igual al arreglo. Una sola llamada,
    // en transacción: borra lo que sobra, inserta lo que falta.
    // ------------------------------------------------------------------
    public static function sincronizar()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'administracion.datos_maestros');

            $db = Flight::db();
            $idTenant = TenantContext::id();

            $idEnteControl = Flight::request()->data['id_ente_control'] ?? null;
            $recursos = Flight::request()->data['recursos'] ?? array();

            if (!$idEnteControl) {
                Flight::json(array('error' => 'Falta id_ente_control'), 400);
                return;
            }
            if (!is_array($recursos)) {
                Flight::json(array('error' => 'recursos debe ser un arreglo'), 400);
                return;
            }

            $db->beginTransaction();

            // 1) Borrar la configuración actual del ente
            $del = $db->prepare("
                DELETE FROM entes_control_recursos
                WHERE id_ente_control = :id_ente_control AND id_tenant = :id_tenant
            ");
            $del->bindParam(':id_ente_control', $idEnteControl);
            $del->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $del->execute();

            // 2) Insertar la selección recibida (deduplicada)
            $ins = $db->prepare("
                INSERT INTO entes_control_recursos
                    (id, id_tenant, id_ente_control, tipo_recurso,
                     id_tipo_persona, id_tipo_documento, id_reporte, activo)
                VALUES
                    (:id, :id_tenant, :id_ente_control, :tipo_recurso,
                     :id_tipo_persona, :id_tipo_documento, :id_reporte, 1)
            ");

            $vistos = array();
            $insertados = 0;

            foreach ($recursos as $r) {
                $tipo = $r['tipo_recurso'] ?? null;

                if ($tipo === 'documento') {
                    $idTP = $r['id_tipo_persona'] ?? null;
                    $idTD = $r['id_tipo_documento'] ?? null;
                    if (!$idTP || !$idTD) { continue; }
                    $clave = 'd|' . $idTP . '|' . $idTD;
                    if (isset($vistos[$clave])) { continue; }
                    $vistos[$clave] = true;

                    $ins->execute(array(
                        ':id'                => Uuid::generar(),
                        ':id_tenant'         => $idTenant,
                        ':id_ente_control'   => $idEnteControl,
                        ':tipo_recurso'      => 'documento',
                        ':id_tipo_persona'   => $idTP,
                        ':id_tipo_documento' => $idTD,
                        ':id_reporte'        => null
                    ));
                    $insertados++;

                } else if ($tipo === 'reporte') {
                    $idRep = $r['id_reporte'] ?? null;
                    if (!$idRep) { continue; }
                    $clave = 'r|' . $idRep;
                    if (isset($vistos[$clave])) { continue; }
                    $vistos[$clave] = true;

                    $ins->execute(array(
                        ':id'                => Uuid::generar(),
                        ':id_tenant'         => $idTenant,
                        ':id_ente_control'   => $idEnteControl,
                        ':tipo_recurso'      => 'reporte',
                        ':id_tipo_persona'   => null,
                        ':id_tipo_documento' => null,
                        ':id_reporte'        => $idRep
                    ));
                    $insertados++;
                }
            }

            $db->commit();
            Flight::json(array('mensaje' => 'Recursos actualizados', 'total' => $insertados));
        } catch (Exception $e) {
            if (Flight::db()->inTransaction()) { Flight::db()->rollBack(); }
            error_log("Error en EntesControlRecursos::sincronizar: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}