<?php
/*=============================================
SERVICIO - PERSONAS DE LA SOLICITUD
Archivo: services/solicitudes-personas.service.php

Lista de responsables y aprobadores de una solicitud. Se arma UNA VEZ, al
crear la solicitud, y desde ahi queda congelada: quien entre al jardin
manana no tiene por que ver los tratamientos de hace meses.

Lista de responsables vacia significa "la ven todos los colaboradores".
Por eso el tipo tiene la bandera exige_responsable: es lo que impide que
una solicitud delicada quede visible para todo el mundo por descuido.
=============================================*/

class SolicitudesPersonas
{
    const ROL_RESPONSABLE = 1;
    const ROL_APROBADOR   = 2;

    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_solicitud, id_colaborador, id_rol
                                  FROM solicitudes_personas
                                  WHERE id_tenant = :id_tenant
                                  ORDER BY id_solicitud, id_rol");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_solicitud, id_colaborador, id_rol
                                  FROM solicitudes_personas
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Personas de una solicitud con el nombre del colaborador, para pintarlas
     * en el detalle.
     */
    public static function getBySolicitud($id_solicitud)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT sp.id, sp.id_solicitud, sp.id_colaborador, sp.id_rol,
                                         r.nombre AS rol_nombre,
                                         TRIM(CONCAT(COALESCE(p.primer_nombre, ''), ' ', COALESCE(p.primer_apellido, ''))) AS colaborador_nombre,
                                         c.nombre AS cargo_nombre
                                  FROM solicitudes_personas sp
                                  INNER JOIN roles_solicitud_persona r ON r.id = sp.id_rol
                                  INNER JOIN colaboradores col ON col.id = sp.id_colaborador
                                  INNER JOIN personas p ON p.id = col.id_persona
                                  LEFT JOIN cargos c ON c.id = col.id_cargo
                                  WHERE sp.id_solicitud = :id_solicitud
                                    AND sp.id_tenant = :id_tenant
                                  ORDER BY r.orden, p.primer_apellido, p.primer_nombre");
        $sentence->bindParam(':id_solicitud', $id_solicitud);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function new()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $idSolicitud   = isset(Flight::request()->data['id_solicitud']) ? Flight::request()->data['id_solicitud'] : null;
        $idColaborador = isset(Flight::request()->data['id_colaborador']) ? Flight::request()->data['id_colaborador'] : null;
        $idRol         = isset(Flight::request()->data['id_rol']) ? (int)Flight::request()->data['id_rol'] : null;

        if (empty($idSolicitud) || empty($idColaborador) || empty($idRol)) {
            Flight::json(array('error' => 'La solicitud, el colaborador y el rol son obligatorios'), 400);
            return;
        }

        $id = self::insertar($db, $idSolicitud, $idColaborador, $idRol);

        if ($id === null) {
            Flight::json(array('error' => 'Ese colaborador ya esta en la solicitud con ese rol'), 400);
            return;
        }

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id            = Flight::request()->data['id'];
        $idColaborador = isset(Flight::request()->data['id_colaborador']) ? Flight::request()->data['id_colaborador'] : null;
        $idRol         = isset(Flight::request()->data['id_rol']) ? (int)Flight::request()->data['id_rol'] : null;

        if (empty($idColaborador) || empty($idRol)) {
            Flight::json(array('error' => 'El colaborador y el rol son obligatorios'), 400);
            return;
        }

        $sentence = $db->prepare("UPDATE solicitudes_personas
                                  SET id_colaborador = :id_colaborador, id_rol = :id_rol
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_colaborador', $idColaborador);
        $sentence->bindValue(':id_rol', $idRol, PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    /**
     * Quitar a alguien de la lista. Si el tipo exige responsable, no se deja
     * quitar al ultimo: la solicitud quedaria visible para todo el jardin.
     */
    public static function delete()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("SELECT sp.id_solicitud, sp.id_rol, t.exige_responsable
                                  FROM solicitudes_personas sp
                                  INNER JOIN solicitudes s ON s.id = sp.id_solicitud
                                  INNER JOIN tipos_solicitud t ON t.id = s.id_tipo_solicitud
                                  WHERE sp.id = :id AND sp.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        if (!$fila) {
            Flight::json(array('error' => 'El registro no existe'), 404);
            return;
        }

        if ((int)$fila['id_rol'] === self::ROL_RESPONSABLE && (int)$fila['exige_responsable'] === 1) {
            $sentence = $db->prepare("SELECT COUNT(*) AS total
                                      FROM solicitudes_personas
                                      WHERE id_solicitud = :id_solicitud
                                        AND id_rol = :id_rol
                                        AND id_tenant = :id_tenant");
            $sentence->bindValue(':id_solicitud', $fila['id_solicitud']);
            $sentence->bindValue(':id_rol', self::ROL_RESPONSABLE, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $conteo = $sentence->fetch();

            if ($conteo && (int)$conteo['total'] <= 1) {
                Flight::json(array('error' => 'Este tipo de solicitud exige al menos un responsable. Agregue otro antes de quitar este.'), 400);
                return;
            }
        }

        $sentence = $db->prepare("DELETE FROM solicitudes_personas WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Insercion interna, sin responder al cliente.
     *
     * @param  PDO    $db
     * @param  string $idSolicitud
     * @param  string $idColaborador
     * @param  int    $idRol
     * @return string|null Id creado, o null si ya estaba con ese rol
     */
    public static function insertar(PDO $db, $idSolicitud, $idColaborador, $idRol)
    {
        $sentence = $db->prepare("SELECT COUNT(*) AS repetidos
                                  FROM solicitudes_personas
                                  WHERE id_solicitud = :id_solicitud
                                    AND id_colaborador = :id_colaborador
                                    AND id_rol = :id_rol");
        $sentence->bindParam(':id_solicitud', $idSolicitud);
        $sentence->bindParam(':id_colaborador', $idColaborador);
        $sentence->bindValue(':id_rol', $idRol, PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        if ($fila && $fila['repetidos'] > 0) {
            return null;
        }

        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO solicitudes_personas
            (id, id_tenant, id_solicitud, id_colaborador, id_rol)
            VALUES (:id, :id_tenant, :id_solicitud, :id_colaborador, :id_rol)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_solicitud', $idSolicitud);
        $sentence->bindParam(':id_colaborador', $idColaborador);
        $sentence->bindValue(':id_rol', $idRol, PDO::PARAM_INT);
        $sentence->execute();

        return $id;
    }

    /**
     * Colaboradores de una solicitud con un rol dado, con su usuario
     * institucional cuando existe. Lectura interna para el envio de avisos.
     *
     * @param  PDO    $db
     * @param  string $idSolicitud
     * @param  int    $idRol
     * @return array  Filas con id_colaborador e id_usuario (id_usuario puede venir null)
     */
    public static function listarPorRol(PDO $db, $idSolicitud, $idRol)
    {
        $sentence = $db->prepare("SELECT sp.id_colaborador,
                                         (SELECT u.id
                                            FROM usuarios u
                                           WHERE u.id_persona = col.id_persona
                                             AND u.id_tenant = sp.id_tenant
                                             AND u.activo = 1
                                             AND u.acceso_institucional = 1
                                           LIMIT 1) AS id_usuario
                                  FROM solicitudes_personas sp
                                  INNER JOIN colaboradores col ON col.id = sp.id_colaborador
                                  WHERE sp.id_solicitud = :id_solicitud
                                    AND sp.id_rol = :id_rol
                                    AND sp.id_tenant = :id_tenant");
        $sentence->bindParam(':id_solicitud', $idSolicitud);
        $sentence->bindValue(':id_rol', $idRol, PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        return $sentence->fetchAll();
    }

    /**
     * Titular del grupo activo del estudiante. Es el responsable por defecto.
     * Se resuelve hasta colaborador porque los cargos viven alli, no en
     * docentes: asi el mismo campo aguanta a la titular, a la rectora y a la
     * enfermera.
     *
     * @param  PDO    $db
     * @param  string $idEstudiante
     * @return string|null id_colaborador del titular, o null si no tiene
     */
    public static function titularDelEstudiante(PDO $db, $idEstudiante)
    {
        $sentence = $db->prepare("SELECT d.id_colaborador
                                  FROM estudiantes_x_grupos exg
                                  INNER JOIN docentes_x_grupos dxg ON dxg.id_grupo = exg.id_grupo
                                                                  AND dxg.id_tenant = exg.id_tenant
                                                                  AND dxg.activo = 1
                                                                  AND dxg.es_titular = 1
                                  INNER JOIN docentes d ON d.id = dxg.id_docente AND d.activo = 1
                                  WHERE exg.id_estudiante = :id_estudiante
                                    AND exg.activo = 1
                                    AND exg.id_tenant = :id_tenant
                                    AND d.id_colaborador IS NOT NULL
                                  ORDER BY exg.anio DESC
                                  LIMIT 1");
        $sentence->bindParam(':id_estudiante', $idEstudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return ($fila && !empty($fila['id_colaborador'])) ? $fila['id_colaborador'] : null;
    }
}
