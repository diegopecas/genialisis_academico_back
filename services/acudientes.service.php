<?php
class Acudientes
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_estudiante, id_persona, id_tipo_acudiente, empresa, cargo, telefono_oficina, es_responsable_pago, autorizado_recoger, ve_en_portal_padres, activo FROM acudientes WHERE id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_estudiante, id_persona, id_tipo_acudiente, empresa, cargo, telefono_oficina, es_responsable_pago, autorizado_recoger, ve_en_portal_padres, activo FROM acudientes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByEstudiante($idEstudiante)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.acudientes');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
                                  a.id,
                                  a.id_estudiante,
                                  a.id_persona,
                                  a.id_tipo_acudiente,
                                  a.es_responsable_pago,
                                  a.autorizado_recoger,
                                  a.ve_en_portal_padres,
                                  a.activo,
                                  ta.nombre AS nombre_tipo_acudiente,
                                  TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_persona,
                                  p.numero_identificacion AS documento_acudiente,
                                  p.correo_electronico,
                                  p.foto
                                FROM acudientes a
                                INNER JOIN tipos_acudiente ta ON ta.id = a.id_tipo_acudiente
                                INNER JOIN personas p ON p.id = a.id_persona
                                WHERE a.id_estudiante = :id_estudiante AND a.id_tenant = :id_tenant
                                ORDER BY p.primer_apellido ASC, p.primer_nombre ASC");
        $sentence->bindParam(':id_estudiante', $idEstudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Un estudiante no puede ser su propio acudiente.
     *
     * Se revisan dos cosas, porque hay dos formas de caer en el error:
     *
     * 1. Que sea literalmente la misma persona (mismo id_persona), que es lo
     *    que pasa cuando se escoge al estudiante de una lista.
     *
     * 2. Que sean dos personas distintas con el MISMO numero de documento.
     *    Es el caso del registro rapido: si se digita el documento del
     *    estudiante en el campo del acudiente y esa persona todavia no
     *    existe, se crean dos personas separadas y el id_persona nunca
     *    coincide.
     *
     * El numero se compara solo, sin el tipo de documento: el mismo numero
     * registrado una vez como NUIP y otra como Cedula sigue siendo el error
     * que se quiere evitar.
     *
     * @param  PDO    $db
     * @param  string $id_estudiante
     * @param  string $id_persona Persona que se quiere dejar como acudiente
     * @return bool   true si esa persona ES el propio estudiante
     */
    private static function esElMismoEstudiante(PDO $db, $id_estudiante, $id_persona)
    {
        if (empty($id_estudiante) || empty($id_persona)) {
            return false;
        }

        $sentence = $db->prepare("SELECT e.id_persona,
                                         p.numero_identificacion
                                  FROM estudiantes e
                                  INNER JOIN personas p ON p.id = e.id_persona
                                  WHERE e.id = :id_estudiante AND e.id_tenant = :id_tenant
                                  LIMIT 1");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $estudiante = $sentence->fetch();

        if (!$estudiante) {
            return false;
        }

        if ($estudiante['id_persona'] === $id_persona) {
            return true;
        }

        if (empty($estudiante['numero_identificacion'])) {
            return false;
        }

        $sentence = $db->prepare("SELECT numero_identificacion FROM personas
                                  WHERE id = :id_persona LIMIT 1");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->execute();
        $acudiente = $sentence->fetch();

        if (!$acudiente || empty($acudiente['numero_identificacion'])) {
            return false;
        }

        return trim((string)$acudiente['numero_identificacion']) === trim((string)$estudiante['numero_identificacion']);
    }

    /**
     * True si esa persona YA es acudiente de ese estudiante.
     *
     * No se mira el tipo de acudiente a proposito: la misma persona no puede
     * estar dos veces en el mismo estudiante, asi la primera vez se haya
     * registrado como Padre y la segunda como Otro. El endpoint
     * verificarDuplicados si incluye el tipo y se deja como esta, porque el
     * front ya lo consume asi.
     *
     * @param  PDO    $db
     * @param  string $id_estudiante
     * @param  string $id_persona
     * @param  string $id_excluir Fila que no cuenta, para el caso de editar
     * @return array|null Fila existente con el parentesco, o null
     */
    private static function acudienteYaRegistrado(PDO $db, $id_estudiante, $id_persona, $id_excluir = null)
    {
        if (empty($id_estudiante) || empty($id_persona)) {
            return null;
        }

        $sql = "SELECT a.id,
                       a.activo,
                       ta.nombre AS tipo_acudiente,
                       TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS nombre
                FROM acudientes a
                LEFT JOIN tipos_acudiente ta ON ta.id = a.id_tipo_acudiente
                LEFT JOIN personas p ON p.id = a.id_persona
                WHERE a.id_estudiante = :id_estudiante
                  AND a.id_persona = :id_persona
                  AND a.id_tenant = :id_tenant";

        if (!empty($id_excluir)) {
            $sql .= " AND a.id <> :id_excluir";
        }

        $sentence = $db->prepare($sql . " LIMIT 1");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

        if (!empty($id_excluir)) {
            $sentence->bindParam(':id_excluir', $id_excluir);
        }

        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila : null;
    }

    /**
     * Mensaje de acudiente repetido, diciendo con que parentesco quedo.
     */
    private static function mensajeAcudienteRepetido($existente)
    {
        $mensaje = !empty($existente['nombre'])
            ? $existente['nombre'] . ' ya es acudiente de este estudiante'
            : 'Esa persona ya es acudiente de este estudiante';

        if (!empty($existente['tipo_acudiente'])) {
            $mensaje .= ', registrada como ' . $existente['tipo_acudiente'];
        }

        if (isset($existente['activo']) && (int)$existente['activo'] === 0) {
            return $mensaje . ', pero esta inactiva. Actívela en lugar de crearla otra vez.';
        }

        return $mensaje . '.';
    }

    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'estudiantes.acudientes.administrar');

            $db = Flight::db();
            $db->beginTransaction();

            $id_estudiante = Flight::request()->data['id_estudiante'];
            $id_persona = Flight::request()->data['id_persona'];
            $id_tipo_acudiente = Flight::request()->data['id_tipo_acudiente'];
            $empresa = Flight::request()->data['empresa'] ?? null;
            $cargo = Flight::request()->data['cargo'] ?? null;
            $telefono_oficina = Flight::request()->data['telefono_oficina'] ?? null;
            $es_responsable_pago = Flight::request()->data['es_responsable_pago'];
            $autorizado_recoger = Flight::request()->data['autorizado_recoger'];
            $ve_en_portal_padres = Flight::request()->data['ve_en_portal_padres'];
            $activo = Flight::request()->data['activo'];

            if (self::esElMismoEstudiante($db, $id_estudiante, $id_persona)) {
                $db->rollback();
                Flight::json(['error' => 'El estudiante no puede ser su propio acudiente'], 400);
                return;
            }

            $repetido = self::acudienteYaRegistrado($db, $id_estudiante, $id_persona);

            if ($repetido) {
                $db->rollback();
                Flight::json(['error' => self::mensajeAcudienteRepetido($repetido)], 400);
                return;
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO acudientes(id, id_tenant, id_estudiante, id_persona, id_tipo_acudiente, empresa, cargo, telefono_oficina, es_responsable_pago, autorizado_recoger, ve_en_portal_padres, activo) 
                                 VALUES (:id, :id_tenant, :id_estudiante, :id_persona, :id_tipo_acudiente, :empresa, :cargo, :telefono_oficina, :es_responsable_pago, :autorizado_recoger, :ve_en_portal_padres, :activo)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_tipo_acudiente', $id_tipo_acudiente);
            $sentence->bindParam(':empresa', $empresa);
            $sentence->bindParam(':cargo', $cargo);
            $sentence->bindParam(':telefono_oficina', $telefono_oficina);
            $sentence->bindParam(':es_responsable_pago', $es_responsable_pago);
            $sentence->bindParam(':autorizado_recoger', $autorizado_recoger);
            $sentence->bindParam(':ve_en_portal_padres', $ve_en_portal_padres);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();
            $id = $idNew;

            $db->commit();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error en new acudiente: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'estudiantes.acudientes.administrar');

            $db = Flight::db();
            $db->beginTransaction();

            $id = Flight::request()->data['id'];
            $id_estudiante = Flight::request()->data['id_estudiante'];
            $id_persona = Flight::request()->data['id_persona'];
            $id_tipo_acudiente = Flight::request()->data['id_tipo_acudiente'];
            $empresa = Flight::request()->data['empresa'] ?? null;
            $cargo = Flight::request()->data['cargo'] ?? null;
            $telefono_oficina = Flight::request()->data['telefono_oficina'] ?? null;
            $es_responsable_pago = Flight::request()->data['es_responsable_pago'];
            $autorizado_recoger = Flight::request()->data['autorizado_recoger'];
            $ve_en_portal_padres = Flight::request()->data['ve_en_portal_padres'];
            $activo = Flight::request()->data['activo'];

            if (self::esElMismoEstudiante($db, $id_estudiante, $id_persona)) {
                $db->rollback();
                Flight::json(['error' => 'El estudiante no puede ser su propio acudiente'], 400);
                return;
            }

            $repetido = self::acudienteYaRegistrado($db, $id_estudiante, $id_persona, $id);

            if ($repetido) {
                $db->rollback();
                Flight::json(['error' => self::mensajeAcudienteRepetido($repetido)], 400);
                return;
            }

            $sentence = $db->prepare("UPDATE acudientes SET 
                                id_estudiante = :id_estudiante, 
                                id_persona = :id_persona, 
                                id_tipo_acudiente = :id_tipo_acudiente, 
                                empresa = :empresa,
                                cargo = :cargo,
                                telefono_oficina = :telefono_oficina,
                                es_responsable_pago = :es_responsable_pago,
                                autorizado_recoger = :autorizado_recoger,
                                ve_en_portal_padres = :ve_en_portal_padres,
                                activo = :activo 
                                WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_tipo_acudiente', $id_tipo_acudiente);
            $sentence->bindParam(':empresa', $empresa);
            $sentence->bindParam(':cargo', $cargo);
            $sentence->bindParam(':telefono_oficina', $telefono_oficina);
            $sentence->bindParam(':es_responsable_pago', $es_responsable_pago);
            $sentence->bindParam(':autorizado_recoger', $autorizado_recoger);
            $sentence->bindParam(':ve_en_portal_padres', $ve_en_portal_padres);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();

            $db->commit();
            self::getById($id);
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error en replace acudiente: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function getByIdConUsuario($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
                              a.id,
                              a.id_estudiante,
                              a.id_persona,
                              a.id_tipo_acudiente,
                              a.es_responsable_pago,
                              a.autorizado_recoger,
                              a.ve_en_portal_padres,
                              a.activo,
                              ta.nombre AS nombre_tipo_acudiente,
                              CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_persona,
                              p.numero_identificacion AS documento_acudiente,
                              u.id as id_usuario,
                              u.usuario,
                              u.activo as usuario_activo
                            FROM acudientes a
                            INNER JOIN tipos_acudiente ta ON ta.id = a.id_tipo_acudiente
                            INNER JOIN personas p ON p.id = a.id_persona
                            LEFT JOIN usuarios u ON u.id_persona = a.id_persona
                            WHERE a.id = :id AND a.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }


    public static function delete($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'estudiantes.acudientes.administrar');

            $db = Flight::db();

            $sentence = $db->prepare("DELETE FROM acudientes WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() > 0) {
                Flight::json(["success" => true, "message" => "Registro eliminado correctamente"]);
            } else {
                Flight::json(["success" => false, "message" => "No se encontró el registro para eliminar"], 404);
            }
        } catch (Exception $e) {
            Flight::json(["success" => false, "message" => "Error en la eliminación", "error" => $e->getMessage()], 500);
        }
    }

    public static function verificarDuplicados()
    {
        $db = Flight::db();
        $id_estudiante = Flight::request()->data['id_estudiante'];
        $id_persona = Flight::request()->data['id_persona'];
        $id_tipo_acudiente = Flight::request()->data['id_tipo_acudiente'];

        $sentence = $db->prepare("SELECT COUNT(*) as total FROM acudientes 
                                WHERE id_estudiante = :id_estudiante 
                                AND id_persona = :id_persona 
                                AND id_tipo_acudiente = :id_tipo_acudiente
                                AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindParam(':id_tipo_acudiente', $id_tipo_acudiente);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();

        Flight::json(array('existe' => $response['total'] > 0));
    }

    public static function getEstudiantesByAcudiente($idPersona)
    {
        error_log("=== DEBUG getEstudiantesByAcudiente ===");
        error_log("idPersona recibido: " . $idPersona);

        $db = Flight::db();

        // Debug: verificar acudientes de esta persona
        $checkAcudiente = $db->prepare("SELECT id, id_estudiante, id_persona, ve_en_portal_padres, activo FROM acudientes WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
        $checkAcudiente->bindParam(':id_persona', $idPersona);
        $checkAcudiente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $checkAcudiente->execute();
        $acudientesDebug = $checkAcudiente->fetchAll();
        error_log("Acudientes encontrados para id_persona $idPersona: " . json_encode($acudientesDebug));

        $sentence = $db->prepare("SELECT 
                            e.id as id_estudiante,
                            e.id_persona,
                            e.fecha_ingreso,
                            e.activo,
                            e.alimentacion,
                            e.telefono_emergencia,
                            e.eps,
                            e.anno,
                            p.primer_nombre,
                            p.segundo_nombre,
                            p.primer_apellido,
                            p.segundo_apellido,
                            p.id_tipo_identificacion,
                            ti.nombre AS tipo_identificacion,
                            p.numero_identificacion,
                            p.fecha_nacimiento,
                            TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                            p.id_genero,
                            g.nombre AS nombre_genero,
                            p.direccion,
                            grp.id AS id_grupo,
                            grp.nombre AS nombre_grupo,
                            CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
                            a.es_responsable_pago,
                            a.autorizado_recoger,
                            ta.nombre AS tipo_acudiente
                            FROM acudientes a
                            INNER JOIN estudiantes e ON a.id_estudiante = e.id
                            INNER JOIN personas p ON e.id_persona = p.id
                            INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
                            LEFT JOIN generos g ON p.id_genero = g.id
                            INNER JOIN tipos_acudiente ta ON a.id_tipo_acudiente = ta.id
                            LEFT JOIN estudiantes_x_grupos eg ON e.id = eg.id_estudiante AND eg.activo = 1
                            LEFT JOIN grupos grp ON eg.id_grupo = grp.id
                            WHERE a.id_persona = :id_persona 
                            AND a.activo = 1 
                            AND a.ve_en_portal_padres = 1
                            AND e.activo = 1
                            AND a.id_tenant = :id_tenant
                            ORDER BY grp.orden ASC, p.primer_apellido ASC, p.primer_nombre ASC");

        $sentence->bindParam(':id_persona', $idPersona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        Flight::json($response);
    }

    public static function getEstudiantesIdsOnly($idPersona)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT e.id as id_estudiante
                                FROM acudientes a
                                INNER JOIN estudiantes e ON a.id_estudiante = e.id
                                WHERE a.id_persona = :id_persona 
                                AND a.activo = 1 
                                AND a.ve_en_portal_padres = 1
                                AND e.activo = 1
                                AND a.id_tenant = :id_tenant");

        $sentence->bindParam(':id_persona', $idPersona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * True si la persona es acudiente activo del estudiante y con permiso de
     * ver el portal de padres.
     *
     * No responde JSON: es una consulta interna pensada para que otros
     * servicios validen el acceso de un acudiente antes de entregar datos del
     * nino. Recibe la conexion ya abierta para poder usarse dentro de una
     * transaccion en curso.
     *
     * @param PDO $db
     * @param string $idPersona Persona del usuario en sesion
     * @param string $idEstudiante
     * @return bool
     */
    public static function esEstudianteDelAcudiente(PDO $db, $idPersona, $idEstudiante)
    {
        if (empty($idPersona) || empty($idEstudiante)) {
            return false;
        }

        $sentence = $db->prepare("SELECT COUNT(*) AS total
                                FROM acudientes a
                                INNER JOIN estudiantes e ON a.id_estudiante = e.id
                                WHERE a.id_persona = :id_persona
                                AND a.id_estudiante = :id_estudiante
                                AND a.activo = 1
                                AND a.ve_en_portal_padres = 1
                                AND e.activo = 1
                                AND a.id_tenant = :id_tenant");

        $sentence->bindParam(':id_persona', $idPersona);
        $sentence->bindParam(':id_estudiante', $idEstudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();

        return !empty($response) && (int) $response['total'] > 0;
    }
}