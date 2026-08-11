<?php
/**
 * Exenciones de mora: apagan el cobro a una persona, opcionalmente acotado a
 * un producto y a un rango de fechas.
 *
 *   id_producto_servicio NULL = todos los productos
 *   fecha_hasta          NULL = vigente indefinidamente
 *
 * Como el motor recalcula la mora completa en cada corte, crear una exencion
 * hace desaparecer tambien la mora ya causada dentro del rango, sin necesidad
 * de borrar nada. Lo unico que nunca se pierde es la mora ya PAGADA: el motor
 * respeta ese piso para no dejar abonos huerfanos.
 */
class MoraExenciones
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    me.id,
                    me.id_persona,
                    me.id_producto_servicio,
                    me.fecha_desde,
                    me.fecha_hasta,
                    me.motivo,
                    me.activo,
                    me.fecha_registro,
                    me.id_usuario,
                    CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_persona,
                    ps.nombre AS nombre_producto_servicio
                FROM mora_exenciones me
                INNER JOIN personas p ON me.id_persona = p.id
                LEFT JOIN productos_servicios ps ON me.id_producto_servicio = ps.id
                WHERE me.id_tenant = :id_tenant
                ORDER BY me.fecha_registro DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en mora_exenciones getAll: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener las exenciones de mora'), 500);
        }
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    me.id,
                    me.id_persona,
                    me.id_producto_servicio,
                    me.fecha_desde,
                    me.fecha_hasta,
                    me.motivo,
                    me.activo,
                    me.fecha_registro,
                    me.id_usuario,
                    CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_persona,
                    ps.nombre AS nombre_producto_servicio
                FROM mora_exenciones me
                INNER JOIN personas p ON me.id_persona = p.id
                LEFT JOIN productos_servicios ps ON me.id_producto_servicio = ps.id
                WHERE me.id = :id AND me.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en mora_exenciones getById: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener la exencion de mora'), 500);
        }
    }

    public static function getByPersona($id_persona)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    me.id,
                    me.id_persona,
                    me.id_producto_servicio,
                    me.fecha_desde,
                    me.fecha_hasta,
                    me.motivo,
                    me.activo,
                    me.fecha_registro,
                    me.id_usuario,
                    ps.nombre AS nombre_producto_servicio
                FROM mora_exenciones me
                LEFT JOIN productos_servicios ps ON me.id_producto_servicio = ps.id
                WHERE me.id_persona = :id_persona AND me.id_tenant = :id_tenant
                ORDER BY me.fecha_desde DESC
            ");
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en mora_exenciones getByPersona: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener las exenciones de la persona'), 500);
        }
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            $error = self::validar($data);
            if ($error !== null) {
                Flight::json(array('error' => $error), 400);
                return;
            }

            $sentence = $db->prepare("
                INSERT INTO mora_exenciones
                    (id, id_tenant, id_persona, id_producto_servicio, fecha_desde, fecha_hasta,
                     motivo, activo, id_usuario)
                VALUES
                    (:id, :id_tenant, :id_persona, :id_producto_servicio, :fecha_desde, :fecha_hasta,
                     :motivo, :activo, :id_usuario)
            ");

            $id = Uuid::generar();
            $idProducto = !empty($data['id_producto_servicio']) ? $data['id_producto_servicio'] : null;
            $fechaHasta = !empty($data['fecha_hasta']) ? $data['fecha_hasta'] : null;
            $motivo = isset($data['motivo']) ? $data['motivo'] : null;
            $activo = isset($data['activo']) ? (int) $data['activo'] : 1;
            $idUsuario = isset($data['id_usuario']) ? $data['id_usuario'] : null;

            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_persona', $data['id_persona']);
            $sentence->bindParam(':id_producto_servicio', $idProducto);
            $sentence->bindParam(':fecha_desde', $data['fecha_desde']);
            $sentence->bindParam(':fecha_hasta', $fechaHasta);
            $sentence->bindParam(':motivo', $motivo);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindParam(':id_usuario', $idUsuario);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en mora_exenciones new: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al crear la exencion de mora'), 500);
        }
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            if (!isset($data['id'])) {
                Flight::json(array('error' => 'Falta el id de la exencion'), 400);
                return;
            }

            $error = self::validar($data);
            if ($error !== null) {
                Flight::json(array('error' => $error), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE mora_exenciones SET
                    id_persona = :id_persona,
                    id_producto_servicio = :id_producto_servicio,
                    fecha_desde = :fecha_desde,
                    fecha_hasta = :fecha_hasta,
                    motivo = :motivo,
                    activo = :activo,
                    id_usuario = :id_usuario
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            $idProducto = !empty($data['id_producto_servicio']) ? $data['id_producto_servicio'] : null;
            $fechaHasta = !empty($data['fecha_hasta']) ? $data['fecha_hasta'] : null;
            $motivo = isset($data['motivo']) ? $data['motivo'] : null;
            $activo = isset($data['activo']) ? (int) $data['activo'] : 1;
            $idUsuario = isset($data['id_usuario']) ? $data['id_usuario'] : null;

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':id_persona', $data['id_persona']);
            $sentence->bindParam(':id_producto_servicio', $idProducto);
            $sentence->bindParam(':fecha_desde', $data['fecha_desde']);
            $sentence->bindParam(':fecha_hasta', $fechaHasta);
            $sentence->bindParam(':motivo', $motivo);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindParam(':id_usuario', $idUsuario);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() === 0) {
                Flight::json(array('error' => 'No se encontro la exencion indicada'), 404);
                return;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log('Error en mora_exenciones replace: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar la exencion de mora'), 500);
        }
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM mora_exenciones WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en mora_exenciones delete: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al borrar la exencion de mora'), 500);
        }
    }

    /**
     * Personas con cuentas por cobrar, con sus datos de curso/grupo y si ya
     * tienen exencion vigente. Alimenta el registro rapido, donde se filtra
     * por nombre, curso y grupo.
     */
    public static function getPersonasParaExencion()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT DISTINCT
                    p.id AS id_persona,
                    CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_persona,
                    p.numero_identificacion,
                    e.id AS id_estudiante,
                    g.id AS id_grupo,
                    g.nombre AS nombre_grupo,
                    gr.id AS id_grado,
                    gr.nombre AS nombre_grado,
                    (
                        SELECT COUNT(*)
                        FROM mora_exenciones me
                        WHERE me.id_persona = p.id AND me.id_tenant = :id_tenant AND me.activo = 1
                    ) AS exenciones_activas
                FROM personas p
                INNER JOIN cuentas_por_cobrar c ON c.id_persona = p.id AND c.id_tenant = :id_tenant
                LEFT JOIN estudiantes e ON e.id_persona = p.id AND e.id_tenant = :id_tenant
                LEFT JOIN estudiantes_x_grupos eg ON eg.id_estudiante = e.id AND eg.activo = 1
                LEFT JOIN grupos g ON eg.id_grupo = g.id
                LEFT JOIN grados gr ON eg.id_grado = gr.id
                WHERE p.id_tenant = :id_tenant
                ORDER BY nombre_persona
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en mora_exenciones getPersonasParaExencion: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener las personas'), 500);
        }
    }

    /**
     * Crea o desactiva exenciones para varias personas de una sola vez.
     * Espera:
     *   personas: [id_persona, ...]
     *   accion:   'aplicar' | 'quitar'
     *   y, cuando es 'aplicar', los campos de la exencion.
     *
     * 'quitar' desactiva (activo = 0) en vez de borrar, para no perder el
     * registro de quien autorizo y por que.
     */
    public static function aplicarMasivo()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            $personas = isset($data['personas']) ? $data['personas'] : array();
            $accion = isset($data['accion']) ? $data['accion'] : 'aplicar';
            $idProducto = !empty($data['id_producto_servicio']) ? $data['id_producto_servicio'] : null;

            if (empty($personas) || !is_array($personas)) {
                Flight::json(array('error' => 'Debe indicar al menos una persona'), 400);
                return;
            }

            if ($accion === 'quitar') {
                $db->beginTransaction();
                $sentence = $db->prepare("
                    UPDATE mora_exenciones SET activo = 0
                    WHERE id_persona = :id_persona AND id_tenant = :id_tenant AND activo = 1
                ");

                $afectados = 0;
                foreach ($personas as $idPersona) {
                    $sentence->bindValue(':id_persona', $idPersona);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->execute();
                    $afectados += $sentence->rowCount();
                }
                $db->commit();

                Flight::json(array(
                    'success'   => true,
                    'accion'    => 'quitar',
                    'afectados' => $afectados,
                    'message'   => $afectados . ' exencion(es) desactivada(s)'
                ));
                return;
            }

            if (empty($data['fecha_desde'])) {
                Flight::json(array('error' => 'Debe indicar la fecha desde la cual aplica la exencion'), 400);
                return;
            }

            $fechaHasta = !empty($data['fecha_hasta']) ? $data['fecha_hasta'] : null;
            if ($fechaHasta !== null && $fechaHasta < $data['fecha_desde']) {
                Flight::json(array('error' => 'La fecha hasta no puede ser anterior a la fecha desde'), 400);
                return;
            }

            $motivo = isset($data['motivo']) ? $data['motivo'] : null;
            $idUsuario = isset($data['id_usuario']) ? $data['id_usuario'] : null;

            $db->beginTransaction();

            $sentence = $db->prepare("
                INSERT INTO mora_exenciones
                    (id, id_tenant, id_persona, id_producto_servicio, fecha_desde, fecha_hasta,
                     motivo, activo, id_usuario)
                VALUES
                    (:id, :id_tenant, :id_persona, :id_producto_servicio, :fecha_desde, :fecha_hasta,
                     :motivo, 1, :id_usuario)
            ");

            $creadas = 0;
            foreach ($personas as $idPersona) {
                $sentence->bindValue(':id', Uuid::generar());
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindValue(':id_persona', $idPersona);
                $sentence->bindValue(':id_producto_servicio', $idProducto);
                $sentence->bindValue(':fecha_desde', $data['fecha_desde']);
                $sentence->bindValue(':fecha_hasta', $fechaHasta);
                $sentence->bindValue(':motivo', $motivo);
                $sentence->bindValue(':id_usuario', $idUsuario);
                $sentence->execute();
                $creadas++;
            }

            $db->commit();

            Flight::json(array(
                'success'   => true,
                'accion'    => 'aplicar',
                'afectados' => $creadas,
                'message'   => $creadas . ' exencion(es) creada(s)'
            ));
        } catch (Exception $e) {
            if (Flight::db()->inTransaction()) {
                Flight::db()->rollBack();
            }
            error_log('Error en mora_exenciones aplicarMasivo: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al aplicar las exenciones'), 500);
        }
    }

    /**
     * Exenciones activas de una persona. Uso interno del motor: devuelve el
     * arreglo crudo, sin responder HTTP.
     *
     * @param PDO    $db
     * @param string $id_persona
     * @return array
     */
    public static function obtenerActivasPorPersona($db, $id_persona)
    {
        $sentence = $db->prepare("
            SELECT id_producto_servicio, fecha_desde, fecha_hasta
            FROM mora_exenciones
            WHERE id_persona = :id_persona
              AND id_tenant = :id_tenant
              AND activo = 1
        ");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Todas las exenciones activas del tenant, indexadas por persona. Se carga
     * una sola vez por corrida del motor para no consultar cuenta por cuenta.
     *
     * @param PDO $db
     * @return array  [id_persona => [ ['id_producto_servicio','fecha_desde','fecha_hasta'], ... ]]
     */
    public static function obtenerActivasIndexadas($db)
    {
        $sentence = $db->prepare("
            SELECT id_persona, id_producto_servicio, fecha_desde, fecha_hasta
            FROM mora_exenciones
            WHERE id_tenant = :id_tenant AND activo = 1
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $indexadas = array();
        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $indexadas[$fila['id_persona']][] = array(
                'id_producto_servicio' => $fila['id_producto_servicio'],
                'fecha_desde'          => $fila['fecha_desde'],
                'fecha_hasta'          => $fila['fecha_hasta']
            );
        }

        return $indexadas;
    }

    /**
     * @return string|null Mensaje de error, o null si esta bien
     */
    private static function validar($data)
    {
        if (empty($data['id_persona'])) {
            return 'Debe indicar la persona';
        }
        if (empty($data['fecha_desde'])) {
            return 'Debe indicar la fecha desde la cual aplica la exencion';
        }
        if (!empty($data['fecha_hasta']) && $data['fecha_hasta'] < $data['fecha_desde']) {
            return 'La fecha hasta no puede ser anterior a la fecha desde';
        }

        return null;
    }
}
