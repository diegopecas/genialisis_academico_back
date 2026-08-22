<?php
/*=============================================
SERVICIO - CARGOS POR TIPO DE SOLICITUD
Archivo: services/tipos-solicitud-cargos.service.php

Cargos que se suman solos a la lista de personas cuando se crea una
solicitud de ese tipo. El rol distingue si el cargo entra como responsable
o como aprobador, por eso ambos casos viven en la misma tabla.

Ojo con el alcance: poner el cargo "Docente" agrega a TODAS las docentes.
Es a proposito, y es la parametrizacion la que decide quien termina viendo
la solicitud.
=============================================*/

class TiposSolicitudCargos
{
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT tsc.id, tsc.id_tipo_solicitud, tsc.id_cargo, tsc.id_rol,
                                         c.nombre AS cargo_nombre,
                                         r.nombre AS rol_nombre
                                  FROM tipos_solicitud_cargos tsc
                                  INNER JOIN cargos c ON c.id = tsc.id_cargo
                                  INNER JOIN roles_solicitud_persona r ON r.id = tsc.id_rol
                                  WHERE tsc.id_tenant = :id_tenant
                                  ORDER BY r.orden, c.nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_tipo_solicitud, id_cargo, id_rol
                                  FROM tipos_solicitud_cargos
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Cargos configurados para un tipo. Es lo que carga la pantalla del tipo.
     */
    public static function getByTipo($id_tipo_solicitud)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT tsc.id, tsc.id_cargo, tsc.id_rol,
                                         c.nombre AS cargo_nombre,
                                         r.nombre AS rol_nombre
                                  FROM tipos_solicitud_cargos tsc
                                  INNER JOIN cargos c ON c.id = tsc.id_cargo
                                  INNER JOIN roles_solicitud_persona r ON r.id = tsc.id_rol
                                  WHERE tsc.id_tipo_solicitud = :id_tipo_solicitud
                                    AND tsc.id_tenant = :id_tenant
                                  ORDER BY r.orden, c.nombre");
        $sentence->bindParam(':id_tipo_solicitud', $id_tipo_solicitud);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function new()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $idTipo  = isset(Flight::request()->data['id_tipo_solicitud']) ? Flight::request()->data['id_tipo_solicitud'] : null;
        $idCargo = isset(Flight::request()->data['id_cargo']) ? Flight::request()->data['id_cargo'] : null;
        $idRol   = isset(Flight::request()->data['id_rol']) ? (int)Flight::request()->data['id_rol'] : null;

        if (empty($idTipo) || empty($idCargo) || empty($idRol)) {
            Flight::json(array('error' => 'El tipo, el cargo y el rol son obligatorios'), 400);
            return;
        }

        // La unica del indice ya lo impide, pero devolver un mensaje claro es
        // mejor que dejar que reviente la restriccion.
        $sentence = $db->prepare("SELECT COUNT(*) AS repetidos
                                  FROM tipos_solicitud_cargos
                                  WHERE id_tipo_solicitud = :id_tipo_solicitud
                                    AND id_cargo = :id_cargo
                                    AND id_rol = :id_rol
                                    AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_tipo_solicitud', $idTipo);
        $sentence->bindParam(':id_cargo', $idCargo);
        $sentence->bindValue(':id_rol', $idRol, PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        if ($fila && $fila['repetidos'] > 0) {
            Flight::json(array('error' => 'Ese cargo ya esta configurado con ese rol para este tipo'), 400);
            return;
        }

        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO tipos_solicitud_cargos
            (id, id_tenant, id_tipo_solicitud, id_cargo, id_rol)
            VALUES (:id, :id_tenant, :id_tipo_solicitud, :id_cargo, :id_rol)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_tipo_solicitud', $idTipo);
        $sentence->bindParam(':id_cargo', $idCargo);
        $sentence->bindValue(':id_rol', $idRol, PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id      = Flight::request()->data['id'];
        $idCargo = isset(Flight::request()->data['id_cargo']) ? Flight::request()->data['id_cargo'] : null;
        $idRol   = isset(Flight::request()->data['id_rol']) ? (int)Flight::request()->data['id_rol'] : null;

        if (empty($idCargo) || empty($idRol)) {
            Flight::json(array('error' => 'El cargo y el rol son obligatorios'), 400);
            return;
        }

        $sentence = $db->prepare("UPDATE tipos_solicitud_cargos
                                  SET id_cargo = :id_cargo, id_rol = :id_rol
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_cargo', $idCargo);
        $sentence->bindValue(':id_rol', $idRol, PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        // Se puede borrar sin mirar el historico: las solicitudes ya creadas
        // guardan su lista de personas congelada y no dependen de esta fila.
        $sentence = $db->prepare("DELETE FROM tipos_solicitud_cargos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Colaboradores activos que hoy ocupan los cargos configurados para un
     * tipo y un rol. Lectura interna: la usa el armado de la lista de
     * personas al crear la solicitud.
     *
     * @param  PDO    $db
     * @param  string $idTipoSolicitud
     * @param  int    $idRol
     * @return array  Lista de id_colaborador
     */
    public static function colaboradoresPorTipoRol(PDO $db, $idTipoSolicitud, $idRol)
    {
        $sentence = $db->prepare("SELECT DISTINCT col.id
                                  FROM tipos_solicitud_cargos tsc
                                  INNER JOIN colaboradores col ON col.id_cargo = tsc.id_cargo
                                                              AND col.id_tenant = tsc.id_tenant
                                                              AND col.activo = 1
                                  WHERE tsc.id_tipo_solicitud = :id_tipo_solicitud
                                    AND tsc.id_rol = :id_rol
                                    AND tsc.id_tenant = :id_tenant");
        $sentence->bindParam(':id_tipo_solicitud', $idTipoSolicitud);
        $sentence->bindValue(':id_rol', $idRol, PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $ids = array();
        foreach ($sentence->fetchAll() as $fila) {
            $ids[] = $fila['id'];
        }

        return $ids;
    }
}
