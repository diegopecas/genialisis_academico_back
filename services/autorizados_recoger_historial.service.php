<?php
class AutorizadosRecogerHistorial
{
    /**
     * Una autorizacion de un dia solo se puede cambiar o quitar si todavia no
     * se ha usado: la fecha es de hoy en adelante y ese dia esa persona no
     * trajo ni recogio al nino. Lo de atras queda como constancia.
     *
     * Se miran las dos columnas de asistencia porque si el autorizado trajo al
     * nino la autorizacion tambien surtio efecto.
     *
     * @return array [ 'puede' => bool, 'motivo' => string|null ]
     */
    private static function validarModificacion($db, $fila)
    {
        if ($fila['fecha_autorizada'] < date('Y-m-d')) {
            return array('puede' => false, 'motivo' => 'No se puede modificar una autorización de una fecha que ya pasó');
        }

        $sentence = $db->prepare("SELECT ae.id
                                  FROM autorizados_recoger ar
                                  INNER JOIN asistencia_estudiantes ae
                                          ON ae.id_estudiante = ar.id_estudiante
                                         AND ae.id_tenant = ar.id_tenant
                                         AND (ae.id_persona_recoge = ar.id_persona OR ae.id_persona_entrega = ar.id_persona)
                                         AND (DATE(ae.fecha_salida) = :fecha_salida OR DATE(ae.fecha_ingreso) = :fecha_ingreso)
                                  WHERE ar.id = :id_autorizado
                                    AND ar.id_tenant = :id_tenant
                                  LIMIT 1");
        $sentence->bindValue(':id_autorizado', $fila['id_autorizado_recoger']);
        // Dos marcadores distintos para la misma fecha: con prepares nativos
        // (PDO::ATTR_EMULATE_PREPARES => false) un mismo nombre no se puede
        // repetir en la consulta.
        $sentence->bindValue(':fecha_salida', $fila['fecha_autorizada']);
        $sentence->bindValue(':fecha_ingreso', $fila['fecha_autorizada']);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        if ($sentence->fetch()) {
            return array('puede' => false, 'motivo' => 'Esta autorización ya se usó: esa persona entregó o recogió al estudiante ese día');
        }

        return array('puede' => true, 'motivo' => null);
    }

    /**
     * Lee una fila del historial del tenant. Null si no existe.
     */
    private static function obtenerFila($db, $id)
    {
        $sentence = $db->prepare("SELECT id, id_autorizado_recoger, fecha_autorizada
                                  FROM autorizados_recoger_historial
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila : null;
    }

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_autorizado_recoger, fecha_autorizada, id_persona_autoriza, observaciones, fecha_registro FROM autorizados_recoger_historial WHERE id_tenant = :id_tenant ORDER BY fecha_autorizada DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_autorizado_recoger, fecha_autorizada, id_persona_autoriza, observaciones, fecha_registro FROM autorizados_recoger_historial WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByAutorizado($idAutorizado)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
                                    arh.id,
                                    arh.id_autorizado_recoger,
                                    arh.fecha_autorizada,
                                    arh.id_persona_autoriza,
                                    arh.observaciones,
                                    arh.fecha_registro,
                                    TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.segundo_nombre, pa.primer_apellido, pa.segundo_apellido)) AS nombre_persona_autoriza
                                  FROM autorizados_recoger_historial arh
                                  INNER JOIN personas pa ON pa.id = arh.id_persona_autoriza
                                  WHERE arh.id_autorizado_recoger = :id_autorizado AND arh.id_tenant = :id_tenant
                                  ORDER BY arh.fecha_autorizada DESC");
        $sentence->bindParam(':id_autorizado', $idAutorizado);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        // 'editable' le dice al front si mostrar los botones de cambiar y
        // quitar la fecha. La regla igual se vuelve a validar al grabar.
        foreach ($response as &$fila) {
            $validacion = self::validarModificacion($db, $fila);
            $fila['editable'] = $validacion['puede'] ? 1 : 0;
            $fila['motivo_no_editable'] = $validacion['motivo'];
        }
        unset($fila);

        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $id_autorizado_recoger = Flight::request()->data['id_autorizado_recoger'];
            $fecha_autorizada = Flight::request()->data['fecha_autorizada'];
            $id_persona_autoriza = Flight::request()->data['id_persona_autoriza'];
            $observaciones = isset(Flight::request()->data['observaciones']) ? Flight::request()->data['observaciones'] : null;

            // Una misma fecha no se registra dos veces: antes cada guardada
            // creaba una fila nueva y el historial se llenaba de repetidos.
            $sentence = $db->prepare("SELECT id FROM autorizados_recoger_historial
                                      WHERE id_autorizado_recoger = :id_autorizado
                                        AND fecha_autorizada = :fecha
                                        AND id_tenant = :id_tenant");
            $sentence->bindValue(':id_autorizado', $id_autorizado_recoger);
            $sentence->bindValue(':fecha', $fecha_autorizada);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->fetch()) {
                Flight::json(['error' => 'Esa persona ya está autorizada para esa fecha'], 409);
                return;
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO autorizados_recoger_historial (id, id_tenant, id_autorizado_recoger, fecha_autorizada, id_persona_autoriza, observaciones) 
                                      VALUES (:id, :id_tenant, :id_autorizado_recoger, :fecha_autorizada, :id_persona_autoriza, :observaciones)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_autorizado_recoger', $id_autorizado_recoger);
            $sentence->bindParam(':fecha_autorizada', $fecha_autorizada);
            $sentence->bindParam(':id_persona_autoriza', $id_persona_autoriza);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->execute();
            $id = $idNew;

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en AutorizadosRecogerHistorial::new: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cambia la fecha de una autorizacion de un dia.
     * Espera: { id, fecha_autorizada, observaciones (opcional) }
     */
    public static function replace()
    {
        try {
            $db = Flight::db();

            $id = Flight::request()->data['id'];
            $fecha_autorizada = Flight::request()->data['fecha_autorizada'];

            $fila = self::obtenerFila($db, $id);

            if (!$fila) {
                Flight::json(['error' => 'No se encontró la autorización'], 404);
                return;
            }

            $validacion = self::validarModificacion($db, $fila);

            if (!$validacion['puede']) {
                Flight::json(['error' => $validacion['motivo']], 409);
                return;
            }

            // La fecha nueva tambien tiene que ser de hoy en adelante: mover
            // una autorizacion al pasado no tiene sentido.
            if ($fecha_autorizada < date('Y-m-d')) {
                Flight::json(['error' => 'La fecha debe ser de hoy en adelante'], 409);
                return;
            }

            $sentence = $db->prepare("SELECT id FROM autorizados_recoger_historial
                                      WHERE id_autorizado_recoger = :id_autorizado
                                        AND fecha_autorizada = :fecha
                                        AND id <> :id
                                        AND id_tenant = :id_tenant");
            $sentence->bindValue(':id_autorizado', $fila['id_autorizado_recoger']);
            $sentence->bindValue(':fecha', $fecha_autorizada);
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->fetch()) {
                Flight::json(['error' => 'Esa persona ya está autorizada para esa fecha'], 409);
                return;
            }

            $observaciones = isset(Flight::request()->data['observaciones']) ? Flight::request()->data['observaciones'] : null;

            $sentence = $db->prepare("UPDATE autorizados_recoger_historial
                                      SET fecha_autorizada = :fecha, observaciones = :observaciones
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':fecha', $fecha_autorizada);
            $sentence->bindValue(':observaciones', $observaciones);
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en AutorizadosRecogerHistorial::replace: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function delete($id)
    {
        try {
            $db = Flight::db();

            $fila = self::obtenerFila($db, $id);

            if (!$fila) {
                Flight::json(["success" => false, "message" => "No se encontró el registro"], 404);
                return;
            }

            $validacion = self::validarModificacion($db, $fila);

            if (!$validacion['puede']) {
                Flight::json(["success" => false, "error" => $validacion['motivo']], 409);
                return;
            }

            $sentence = $db->prepare("DELETE FROM autorizados_recoger_historial WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() > 0) {
                Flight::json(["success" => true, "message" => "Registro eliminado correctamente"]);
            } else {
                Flight::json(["success" => false, "message" => "No se encontró el registro"], 404);
            }
        } catch (Exception $e) {
            Flight::json(["success" => false, "error" => $e->getMessage()], 500);
        }
    }
}