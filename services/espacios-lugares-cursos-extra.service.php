<?php
/**
 * Espacios de un lugar de cursos extra: canchas, piscinas o salones.
 *
 * Cada espacio trae su horario fijo contratado (horarios_espacios_...), que se
 * guarda en la misma transaccion. Los espacios se escogen en los horarios de
 * los cursos y en las sesiones.
 */
class EspaciosLugaresCursosExtra
{
    /**
     * Espacios de un lugar, activos e inactivos, cada uno con su horario fijo
     * en la llave 'horarios'.
     */
    public static function getByLugar($id_lugar_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT e.id, e.id_lugar_curso_extra, e.nombre, e.descripcion, e.activo
            FROM espacios_lugares_cursos_extra e
            WHERE e.id_lugar_curso_extra = :id_lugar AND e.id_tenant = :id_tenant
            ORDER BY e.nombre
        ");
        $sentence->bindParam(':id_lugar', $id_lugar_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$espacio) {
            $espacio['horarios'] = HorariosEspaciosLugaresCursosExtra::listar($db, $espacio['id']);
        }
        unset($espacio);

        Flight::json($response);
    }

    // Solo los activos: es lo que se ofrece en los horarios y en las sesiones.
    public static function getActivosByLugar($id_lugar_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, id_lugar_curso_extra, nombre, descripcion
            FROM espacios_lugares_cursos_extra
            WHERE id_lugar_curso_extra = :id_lugar AND id_tenant = :id_tenant AND activo = 1
            ORDER BY nombre
        ");
        $sentence->bindParam(':id_lugar', $id_lugar_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Todos los espacios activos del tenant, con el nombre del lugar. Lo usa
    // la sesion suelta, que puede dictarse en cualquier espacio.
    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT e.id, e.id_lugar_curso_extra, e.nombre, e.descripcion, l.nombre AS nombre_lugar
            FROM espacios_lugares_cursos_extra e
            INNER JOIN lugares_cursos_extra l ON e.id_lugar_curso_extra = l.id
            WHERE e.id_tenant = :id_tenant AND e.activo = 1
            ORDER BY l.nombre, e.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, id_lugar_curso_extra, nombre, descripcion, activo
            FROM espacios_lugares_cursos_extra
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$espacio) {
            $espacio['horarios'] = HorariosEspaciosLugaresCursosExtra::listar($db, $espacio['id']);
        }
        unset($espacio);

        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id_lugar_curso_extra = $data['id_lugar_curso_extra'];
        $nombre = isset($data['nombre']) ? trim($data['nombre']) : '';
        $descripcion = isset($data['descripcion']) && $data['descripcion'] !== '' ? $data['descripcion'] : null;
        $activo = isset($data['activo']) ? ($data['activo'] ? 1 : 0) : 1;
        $horarios = isset($data['horarios']) ? $data['horarios'] : array();

        $error = self::validarNombre($db, $id_lugar_curso_extra, $nombre, null);
        if ($error !== null) {
            Flight::json(array('error' => $error), 400);
            return;
        }

        $id = Uuid::generar();
        try {
            $db->beginTransaction();

            $sentence = $db->prepare("
                INSERT INTO espacios_lugares_cursos_extra (id, id_tenant, id_lugar_curso_extra, nombre, descripcion, activo)
                VALUES (:id, :id_tenant, :id_lugar, :nombre, :descripcion, :activo)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_lugar', $id_lugar_curso_extra);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindParam(':activo', $activo, PDO::PARAM_INT);
            $sentence->execute();

            $error = HorariosEspaciosLugaresCursosExtra::sincronizar($db, $id, $horarios);
            if ($error !== null) {
                $db->rollBack();
                Flight::json(array('error' => $error), 400);
                return;
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al crear el espacio: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo crear el espacio.'), 500);
            return;
        }

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id = $data['id'];
        $nombre = isset($data['nombre']) ? trim($data['nombre']) : '';
        $descripcion = isset($data['descripcion']) && $data['descripcion'] !== '' ? $data['descripcion'] : null;
        $activo = isset($data['activo']) ? ($data['activo'] ? 1 : 0) : 1;
        $horarios = isset($data['horarios']) ? $data['horarios'] : array();

        $lugar = $db->prepare("SELECT id_lugar_curso_extra FROM espacios_lugares_cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
        $lugar->bindParam(':id', $id);
        $lugar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $lugar->execute();
        $fila = $lugar->fetch();
        if (!$fila) {
            Flight::json(array('error' => 'El espacio no existe.'), 404);
            return;
        }

        $error = self::validarNombre($db, $fila['id_lugar_curso_extra'], $nombre, $id);
        if ($error !== null) {
            Flight::json(array('error' => $error), 400);
            return;
        }

        try {
            $db->beginTransaction();

            $sentence = $db->prepare("
                UPDATE espacios_lugares_cursos_extra SET
                    nombre = :nombre,
                    descripcion = :descripcion,
                    activo = :activo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindParam(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $error = HorariosEspaciosLugaresCursosExtra::sincronizar($db, $id, $horarios);
            if ($error !== null) {
                $db->rollBack();
                Flight::json(array('error' => $error), 400);
                return;
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al actualizar el espacio: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo actualizar el espacio.'), 500);
            return;
        }

        self::getById($id);
    }

    /**
     * Eliminar un espacio.
     *
     * Se bloquea si algun horario de curso o alguna sesion lo usa, para que
     * el mensaje diga que hacer en lugar de dejar sesiones sin cancha. En ese
     * caso se puede inactivar.
     */
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $verificar = $db->prepare("
            SELECT
                (SELECT COUNT(*) FROM horarios_cursos_extra
                 WHERE id_espacio_lugar_curso_extra = :id_h AND id_tenant = :id_tenant_h) AS horarios,
                (SELECT COUNT(*) FROM sesiones_cursos_extra
                 WHERE id_espacio_lugar_curso_extra = :id_s AND id_tenant = :id_tenant_s) AS sesiones
        ");
        $verificar->bindParam(':id_h', $id);
        $verificar->bindParam(':id_s', $id);
        $verificar->bindValue(':id_tenant_h', TenantContext::id(), PDO::PARAM_INT);
        $verificar->bindValue(':id_tenant_s', TenantContext::id(), PDO::PARAM_INT);
        $verificar->execute();
        $uso = $verificar->fetch();

        if ($uso && ((int) $uso['horarios'] > 0 || (int) $uso['sesiones'] > 0)) {
            Flight::json(array(
                'error' => 'No se puede eliminar: el espacio está en ' . $uso['horarios'] . ' horario(s) de curso y en ' .
                           $uso['sesiones'] . ' sesión(es). Puedes inactivarlo para que no se ofrezca más.'
            ), 400);
            return;
        }

        // El horario fijo se borra solo por la llave con ON DELETE CASCADE.
        $sentence = $db->prepare("DELETE FROM espacios_lugares_cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * El nombre es obligatorio y no se puede repetir dentro del mismo lugar.
     *
     * @return string|null Mensaje de error, o null si el nombre sirve.
     */
    private static function validarNombre($db, $id_lugar_curso_extra, $nombre, $id_excluir)
    {
        if ($nombre === '') {
            return 'El nombre del espacio es obligatorio.';
        }

        $sentence = $db->prepare("
            SELECT id FROM espacios_lugares_cursos_extra
            WHERE id_lugar_curso_extra = :id_lugar AND id_tenant = :id_tenant AND nombre = :nombre
              AND (:id_excluir IS NULL OR id <> :id_excluir_2)
        ");
        $sentence->bindParam(':id_lugar', $id_lugar_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindValue(':id_excluir', $id_excluir);
        $sentence->bindValue(':id_excluir_2', $id_excluir);
        $sentence->execute();

        if ($sentence->fetch()) {
            return 'Ya existe un espacio con ese nombre en este lugar.';
        }
        return null;
    }
}
