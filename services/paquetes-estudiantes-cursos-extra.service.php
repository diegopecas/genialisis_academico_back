<?php
/**
 * Paquetes de sesiones de un estudiante en un curso extra: la bolsa que
 * compro (numero de sesiones, desde cuando y hasta cuando).
 *
 * Las sesiones usadas no se guardan: son las reservas que tienen el paquete
 * asignado. Una reserva toma el paquete cuando se registra la asistencia y
 * el estado consume (ASISTIO siempre; NO_ASISTIO si el curso descuenta la
 * inasistencia). Si deja de consumir, lo suelta.
 */
class PaquetesEstudiantesCursosExtra
{
    /**
     * Paquetes de todos los inscritos de un curso, con usadas y disponibles.
     */
    public static function getByCurso($id_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::consultaBase() . "
            WHERE exce.id_curso_extra = :id_curso_extra AND pq.id_tenant = :id_tenant
            ORDER BY p.primer_nombre, p.primer_apellido, pq.fecha_inicio DESC
        ");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByInscripcion($id_estudiante_x_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::consultaBase() . "
            WHERE pq.id_estudiante_x_curso_extra = :id_inscripcion AND pq.id_tenant = :id_tenant
            ORDER BY pq.fecha_inicio DESC
        ");
        $sentence->bindParam(':id_inscripcion', $id_estudiante_x_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::consultaBase() . "
            WHERE pq.id = :id AND pq.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Crea un paquete. La fecha de vencimiento puede llegar directa o como
     * vigencia_dias desde la fecha de inicio (es lo que trae el producto en
     * la tarifa del curso). Sin ninguna de las dos, no vence.
     */
    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id_estudiante_x_curso_extra = $data['id_estudiante_x_curso_extra'];
        $numero_sesiones = isset($data['numero_sesiones']) ? (int) $data['numero_sesiones'] : 0;
        $fecha_inicio = isset($data['fecha_inicio']) ? $data['fecha_inicio'] : '';
        $fecha_vencimiento = self::calcularVencimiento($data, $fecha_inicio);
        $observaciones = isset($data['observaciones']) && $data['observaciones'] !== '' ? $data['observaciones'] : null;
        $id_cuenta_por_cobrar = isset($data['id_cuenta_por_cobrar']) && $data['id_cuenta_por_cobrar'] !== '' ? $data['id_cuenta_por_cobrar'] : null;

        $error = self::validar($numero_sesiones, $fecha_inicio, $fecha_vencimiento, 0);
        if ($error !== null) {
            Flight::json(array('error' => $error), 400);
            return;
        }

        $inscripcion = $db->prepare("SELECT id FROM estudiantes_x_cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
        $inscripcion->bindParam(':id', $id_estudiante_x_curso_extra);
        $inscripcion->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $inscripcion->execute();
        if (!$inscripcion->fetch()) {
            Flight::json(array('error' => 'La inscripción del estudiante no existe.'), 400);
            return;
        }

        $id = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO paquetes_estudiantes_cursos_extra
                (id, id_tenant, id_estudiante_x_curso_extra, id_cuenta_por_cobrar, numero_sesiones,
                 fecha_inicio, fecha_vencimiento, observaciones, activo)
            VALUES
                (:id, :id_tenant, :id_inscripcion, :id_cuenta, :numero_sesiones,
                 :fecha_inicio, :fecha_vencimiento, :observaciones, 1)
        ");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_inscripcion', $id_estudiante_x_curso_extra);
        $sentence->bindValue(':id_cuenta', $id_cuenta_por_cobrar);
        $sentence->bindValue(':numero_sesiones', $numero_sesiones, PDO::PARAM_INT);
        $sentence->bindParam(':fecha_inicio', $fecha_inicio);
        $sentence->bindValue(':fecha_vencimiento', $fecha_vencimiento);
        $sentence->bindValue(':observaciones', $observaciones);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id = $data['id'];
        $numero_sesiones = isset($data['numero_sesiones']) ? (int) $data['numero_sesiones'] : 0;
        $fecha_inicio = isset($data['fecha_inicio']) ? $data['fecha_inicio'] : '';
        $fecha_vencimiento = self::calcularVencimiento($data, $fecha_inicio);
        $observaciones = isset($data['observaciones']) && $data['observaciones'] !== '' ? $data['observaciones'] : null;
        $activo = isset($data['activo']) ? ($data['activo'] ? 1 : 0) : 1;

        $usadas = self::contarUsadas($db, $id);
        $error = self::validar($numero_sesiones, $fecha_inicio, $fecha_vencimiento, $usadas);
        if ($error !== null) {
            Flight::json(array('error' => $error), 400);
            return;
        }

        $sentence = $db->prepare("
            UPDATE paquetes_estudiantes_cursos_extra SET
                numero_sesiones = :numero_sesiones,
                fecha_inicio = :fecha_inicio,
                fecha_vencimiento = :fecha_vencimiento,
                observaciones = :observaciones,
                activo = :activo
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindValue(':numero_sesiones', $numero_sesiones, PDO::PARAM_INT);
        $sentence->bindParam(':fecha_inicio', $fecha_inicio);
        $sentence->bindValue(':fecha_vencimiento', $fecha_vencimiento);
        $sentence->bindValue(':observaciones', $observaciones);
        $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    /**
     * Solo se borra un paquete que no se ha usado. Si ya tiene sesiones
     * descontadas, se puede inactivar.
     */
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $usadas = self::contarUsadas($db, $id);
        if ($usadas > 0) {
            Flight::json(array('error' => 'No se puede eliminar: el paquete ya tiene ' . $usadas . ' sesión(es) usada(s). Puedes inactivarlo.'), 400);
            return;
        }

        $sentence = $db->prepare("DELETE FROM paquetes_estudiantes_cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Paquete del que se descuenta una sesion de esa fecha: activo, vigente
     * en la fecha y con sesiones disponibles. Si hay varios, el que vence
     * primero. Devuelve el id o null si no hay ninguno.
     *
     * $id_reserva_excluir sirve para no contar la misma reserva cuando ya
     * tenia el paquete asignado.
     */
    public static function paqueteVigente($db, $id_estudiante_x_curso_extra, $fecha, $id_reserva_excluir = null)
    {
        $sentence = $db->prepare("
            SELECT pq.id
            FROM paquetes_estudiantes_cursos_extra pq
            WHERE pq.id_estudiante_x_curso_extra = :id_inscripcion
              AND pq.id_tenant = :id_tenant
              AND pq.activo = 1
              AND pq.fecha_inicio <= :fecha_1
              AND (pq.fecha_vencimiento IS NULL OR pq.fecha_vencimiento >= :fecha_2)
              AND pq.numero_sesiones > (
                    SELECT COUNT(*) FROM reservas_sesiones_cursos_extra r
                    WHERE r.id_paquete_estudiante_curso_extra = pq.id
                      AND (:id_excluir IS NULL OR r.id <> :id_excluir_2))
            ORDER BY pq.fecha_vencimiento IS NULL, pq.fecha_vencimiento, pq.fecha_inicio
            LIMIT 1
        ");
        $sentence->bindParam(':id_inscripcion', $id_estudiante_x_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':fecha_1', $fecha);
        $sentence->bindValue(':fecha_2', $fecha);
        $sentence->bindValue(':id_excluir', $id_reserva_excluir);
        $sentence->bindValue(':id_excluir_2', $id_reserva_excluir);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? $fila['id'] : null;
    }

    /**
     * Sesiones disponibles de un estudiante en una fecha, sumando todos sus
     * paquetes vigentes. Devuelve null si no tiene ningun paquete vigente
     * (para distinguir "sin paquete" de "paquete agotado").
     */
    public static function disponibles($db, $id_estudiante_x_curso_extra, $fecha)
    {
        $sentence = $db->prepare("
            SELECT COUNT(*) AS paquetes,
                   IFNULL(SUM(pq.numero_sesiones - (
                       SELECT COUNT(*) FROM reservas_sesiones_cursos_extra r
                       WHERE r.id_paquete_estudiante_curso_extra = pq.id)), 0) AS disponibles
            FROM paquetes_estudiantes_cursos_extra pq
            WHERE pq.id_estudiante_x_curso_extra = :id_inscripcion
              AND pq.id_tenant = :id_tenant
              AND pq.activo = 1
              AND pq.fecha_inicio <= :fecha_1
              AND (pq.fecha_vencimiento IS NULL OR pq.fecha_vencimiento >= :fecha_2)
        ");
        $sentence->bindParam(':id_inscripcion', $id_estudiante_x_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':fecha_1', $fecha);
        $sentence->bindValue(':fecha_2', $fecha);
        $sentence->execute();
        $fila = $sentence->fetch();
        if (!$fila || (int) $fila['paquetes'] === 0) {
            return null;
        }
        return (int) $fila['disponibles'];
    }

    private static function contarUsadas($db, $id_paquete)
    {
        $sentence = $db->prepare("
            SELECT COUNT(*) AS usadas FROM reservas_sesiones_cursos_extra
            WHERE id_paquete_estudiante_curso_extra = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id_paquete);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? (int) $fila['usadas'] : 0;
    }

    private static function calcularVencimiento($data, $fecha_inicio)
    {
        if (isset($data['fecha_vencimiento']) && $data['fecha_vencimiento'] !== '' && $data['fecha_vencimiento'] !== null) {
            return $data['fecha_vencimiento'];
        }
        if (isset($data['vigencia_dias']) && $data['vigencia_dias'] !== '' && (int) $data['vigencia_dias'] > 0 && $fecha_inicio !== '') {
            $fecha = new DateTime($fecha_inicio);
            // La vigencia cuenta el dia de inicio: 30 dias desde el 1 vencen el 30.
            $fecha->modify('+' . ((int) $data['vigencia_dias'] - 1) . ' days');
            return $fecha->format('Y-m-d');
        }
        return null;
    }

    /**
     * @return string|null Mensaje de error, o null si el paquete sirve.
     */
    private static function validar($numero_sesiones, $fecha_inicio, $fecha_vencimiento, $usadas)
    {
        if ($numero_sesiones < 1) {
            return 'El paquete debe tener al menos una sesión.';
        }
        if ($fecha_inicio === '' || $fecha_inicio === null) {
            return 'La fecha de inicio del paquete es obligatoria.';
        }
        if ($fecha_vencimiento !== null && $fecha_vencimiento < $fecha_inicio) {
            return 'La fecha de vencimiento no puede ser anterior a la de inicio.';
        }
        if ($numero_sesiones < $usadas) {
            return 'El paquete ya tiene ' . $usadas . ' sesión(es) usada(s): no puede quedar con menos.';
        }
        return null;
    }

    private static function consultaBase()
    {
        return "
            SELECT pq.id, pq.id_estudiante_x_curso_extra, pq.id_cuenta_por_cobrar, pq.numero_sesiones,
                   pq.fecha_inicio, pq.fecha_vencimiento, pq.observaciones, pq.activo,
                   exce.id_curso_extra, exce.id_estudiante,
                   TRIM(REGEXP_REPLACE(CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                        IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')), '\\\\s+', ' ')) AS nombre_estudiante,
                   (SELECT COUNT(*) FROM reservas_sesiones_cursos_extra r
                    WHERE r.id_paquete_estudiante_curso_extra = pq.id) AS usadas,
                   pq.numero_sesiones - (SELECT COUNT(*) FROM reservas_sesiones_cursos_extra r2
                    WHERE r2.id_paquete_estudiante_curso_extra = pq.id) AS disponibles,
                   CASE
                       WHEN pq.activo = 0 THEN 'INACTIVO'
                       WHEN pq.fecha_vencimiento IS NOT NULL AND pq.fecha_vencimiento < CURDATE() THEN 'VENCIDO'
                       WHEN pq.numero_sesiones <= (SELECT COUNT(*) FROM reservas_sesiones_cursos_extra r3
                            WHERE r3.id_paquete_estudiante_curso_extra = pq.id) THEN 'AGOTADO'
                       ELSE 'VIGENTE'
                   END AS estado
            FROM paquetes_estudiantes_cursos_extra pq
            INNER JOIN estudiantes_x_cursos_extra exce ON pq.id_estudiante_x_curso_extra = exce.id
            INNER JOIN estudiantes e ON exce.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
        ";
    }
}
