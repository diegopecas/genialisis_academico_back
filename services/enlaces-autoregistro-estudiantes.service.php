<?php
/**
 * Estudiantes incluidos en cada enlace de autoregistro de acudientes.
 *
 * El enlace solo muestra en la pagina publica los estudiantes que esten en
 * esta tabla. Los grupos se usan en la pantalla de creacion solo para filtrar
 * la lista: lo que se guarda es cada estudiante marcado.
 */
class EnlacesAutoregistroEstudiantes
{
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_enlace, id_estudiante, fecha_registro
                                  FROM enlaces_autoregistro_estudiantes
                                  WHERE id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_enlace, id_estudiante, fecha_registro
                                  FROM enlaces_autoregistro_estudiantes
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Estudiantes de un enlace con su grupo actual. El front usa el id_grupo
     * para volver a marcar los grupos al editar.
     */
    public static function getByEnlace($idEnlace)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.enlaces_autoregistro');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT eae.id, eae.id_enlace, eae.id_estudiante,
                                         exg.id_grupo, g.nombre AS nombre_grupo,
                                         TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_estudiante
                                  FROM enlaces_autoregistro_estudiantes eae
                                  INNER JOIN estudiantes e ON e.id = eae.id_estudiante AND e.id_tenant = eae.id_tenant
                                  INNER JOIN personas p ON p.id = e.id_persona
                                  LEFT JOIN estudiantes_x_grupos exg ON exg.id_estudiante = e.id AND exg.activo = 1 AND exg.id_tenant = eae.id_tenant
                                  LEFT JOIN grupos g ON g.id = exg.id_grupo
                                  WHERE eae.id_enlace = :id_enlace AND eae.id_tenant = :id_tenant
                                  ORDER BY g.orden, p.primer_nombre, p.primer_apellido");
        $sentence->bindParam(':id_enlace', $idEnlace);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.enlaces_autoregistro');

        $db = Flight::db();
        $id_enlace = Flight::request()->data['id_enlace'] ?? null;
        $id_estudiante = Flight::request()->data['id_estudiante'] ?? null;

        if (!$id_enlace || !$id_estudiante) {
            Flight::json(array('error' => 'Faltan el enlace o el estudiante'), 400);
            return;
        }

        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT IGNORE INTO enlaces_autoregistro_estudiantes (id, id_tenant, id_enlace, id_estudiante)
                                  VALUES (:id, :id_tenant, :id_enlace, :id_estudiante)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_enlace', $id_enlace);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Reemplaza la lista completa de estudiantes del enlace.
     * Body: { id_enlace, estudiantes: [id_estudiante, ...] }
     */
    public static function replaceEstudiantesEnlace()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.enlaces_autoregistro');

        $db = Flight::db();
        $id_enlace = Flight::request()->data['id_enlace'] ?? null;
        $estudiantes = Flight::request()->data['estudiantes'] ?? array();

        if (!$id_enlace) {
            Flight::json(array('error' => 'Falta el enlace'), 400);
            return;
        }
        if (!is_array($estudiantes) || count($estudiantes) === 0) {
            Flight::json(array('error' => 'Debe seleccionar al menos un estudiante'), 400);
            return;
        }

        try {
            $db->beginTransaction();

            $verificar = $db->prepare("SELECT id FROM enlaces_autoregistro_acudientes WHERE id = :id AND id_tenant = :id_tenant");
            $verificar->bindParam(':id', $id_enlace);
            $verificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verificar->execute();
            if (!$verificar->fetch()) {
                $db->rollBack();
                Flight::json(array('error' => 'No se encontró el enlace'), 404);
                return;
            }

            $borrar = $db->prepare("DELETE FROM enlaces_autoregistro_estudiantes WHERE id_enlace = :id_enlace AND id_tenant = :id_tenant");
            $borrar->bindParam(':id_enlace', $id_enlace);
            $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrar->execute();

            // Solo se aceptan estudiantes del tenant.
            $insertar = $db->prepare("INSERT IGNORE INTO enlaces_autoregistro_estudiantes (id, id_tenant, id_enlace, id_estudiante)
                                      SELECT :id, e.id_tenant, :id_enlace, e.id
                                      FROM estudiantes e
                                      WHERE e.id = :id_estudiante AND e.id_tenant = :id_tenant");

            foreach (array_unique($estudiantes) as $idEstudiante) {
                if (empty($idEstudiante)) {
                    continue;
                }
                $insertar->bindValue(':id', Uuid::generar());
                $insertar->bindValue(':id_enlace', $id_enlace);
                $insertar->bindValue(':id_estudiante', $idEstudiante);
                $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $insertar->execute();
            }

            $db->commit();
            Flight::json(array('id_enlace' => $id_enlace, 'total' => count(array_unique($estudiantes))));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en EnlacesAutoregistroEstudiantes::replaceEstudiantesEnlace: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudieron guardar los estudiantes del enlace'), 500);
        }
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.enlaces_autoregistro');

        $db = Flight::db();
        $id = Flight::request()->data['id'] ?? null;

        $sentence = $db->prepare("DELETE FROM enlaces_autoregistro_estudiantes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Ids de los estudiantes incluidos en un enlace. Uso interno (pagina
     * publica y registro), no responde JSON.
     *
     * @param PDO    $db
     * @param string $idEnlace
     * @return array Lista de id_estudiante
     */
    public static function idsPorEnlace(PDO $db, $idEnlace)
    {
        $sentence = $db->prepare("SELECT eae.id_estudiante
                                  FROM enlaces_autoregistro_estudiantes eae
                                  INNER JOIN estudiantes e ON e.id = eae.id_estudiante AND e.id_tenant = eae.id_tenant
                                  WHERE eae.id_enlace = :id_enlace AND eae.id_tenant = :id_tenant AND e.activo = 1");
        $sentence->bindParam(':id_enlace', $idEnlace);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_COLUMN);
    }
}
