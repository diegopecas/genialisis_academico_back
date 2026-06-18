<?php
class EstudiantesXGrupos
{

    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.listado');

        $db = Flight::db();
        $sentence = $db->prepare("select exg.id, exg.anio, exg.id_estudiante, 
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        exg.id_grupo, g.nombre nombre_grupo, exg.id_grado, gr.nombre nombre_grado,
        e.activo, e.alimentacion, e.permanente, e.anno
        from estudiantes_x_grupos exg
        inner join estudiantes e on exg.id_estudiante = e.id 
        inner join personas p on e.id_persona = p.id 
        inner join grupos g on exg.id_grupo = g.id 
        left join grados gr on exg.id_grado = gr.id
        where exg.activo = 1
        and exg.id_tenant = :id_tenant
        order by g.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select exg.id, exg.anio, exg.id_estudiante, 
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        exg.id_grupo, g.nombre nombre_grupo, exg.id_grado, gr.nombre nombre_grado,
        e.activo, e.alimentacion, e.permanente, e.anno
        from estudiantes_x_grupos exg
        inner join estudiantes e on exg.id_estudiante = e.id 
        inner join personas p on e.id_persona = p.id 
        inner join grupos g on exg.id_grupo = g.id 
        left join grados gr on exg.id_grado = gr.id
        where e.activo = 1 and exg.activo = 1
        and exg.id_tenant = :id_tenant
        order by g.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByGrupo($idGrupo)
    {
        if ($idGrupo == 0) {
            self::getAll();
        } else {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'estudiantes.listado');

            $db = Flight::db();
            $sentence = $db->prepare("select exg.id, exg.anio, exg.id_estudiante, 
            p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
            exg.id_grupo, g.nombre nombre_grupo, exg.id_grado, gr.nombre nombre_grado,
            exg.activo, e.alimentacion, e.permanente, e.anno
            from estudiantes_x_grupos exg
            inner join estudiantes e on exg.id_estudiante = e.id 
            inner join personas p on e.id_persona = p.id 
            inner join grupos g on exg.id_grupo = g.id 
            left join grados gr on exg.id_grado = gr.id
            where exg.activo = 1
            and g.id = :id_grupo
            and exg.id_tenant = :id_tenant");
            $sentence->bindParam(':id_grupo', $idGrupo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        }
    }

    public static function getByEstudiante($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exg.id, exg.anio, exg.id_estudiante, 
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        exg.id_grupo, g.nombre nombre_grupo, exg.id_grado, gr.nombre nombre_grado,
        e.activo, e.alimentacion, e.permanente, e.anno,
        e.telefono_emergencia, e.eps, e.fecha_ingreso
        FROM estudiantes_x_grupos exg
        INNER JOIN estudiantes e ON exg.id_estudiante = e.id 
        INNER JOIN personas p ON e.id_persona = p.id 
        INNER JOIN grupos g ON exg.id_grupo = g.id 
        LEFT JOIN grados gr ON exg.id_grado = gr.id
        WHERE exg.id_estudiante = :id AND exg.activo = 1 AND exg.id_tenant = :id_tenant
        ORDER BY g.orden");

        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select exg.id, exg.anio, exg.id_estudiante, 
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        exg.id_grupo, g.nombre nombre_grupo, exg.id_grado, gr.nombre nombre_grado,
        exg.activo, exg.id id_estudiante_grupo, e.alimentacion, e.permanente, e.anno
        from estudiantes_x_grupos exg
        inner join estudiantes e on exg.id_estudiante = e.id 
        inner join personas p on e.id_persona = p.id 
        inner join grupos g on exg.id_grupo = g.id 
        left join grados gr on exg.id_grado = gr.id
        where exg.activo = 1
        and exg.id = :id
        and exg.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validarAlguno($userData, ['estudiantes.cambio_grupo', 'estudiantes.administrar']);

        $db = Flight::db();
        $anio = Flight::request()->data['anio'];
        $id_estudiante = Flight::request()->data['id_estudiante'];
        $id_grupo = Flight::request()->data['id_grupo'];
        // Acepta ausencia de la key, null explícito o string vacío como "sin grado" para no violar la FK
        $id_grado = (isset(Flight::request()->data['id_grado']) && Flight::request()->data['id_grado'] !== '' && Flight::request()->data['id_grado'] !== null)
            ? Flight::request()->data['id_grado']
            : null;
        $idNew = Uuid::generar();
        $sentence = $db->prepare("insert into estudiantes_x_grupos(id, id_tenant, anio, id_estudiante, id_grupo, id_grado, activo) values (:id, :id_tenant, :anio, :id_estudiante, :id_grupo, :id_grado, 1)");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':anio', $anio);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindParam(':id_grado', $id_grado);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validarAlguno($userData, ['estudiantes.cambio_grupo', 'estudiantes.administrar']);

            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $sentence = $db->prepare("update estudiantes_x_grupos set activo = 0 where id = :id and id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            self::getById($id);
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()));
        }
    }

    public static function cambioGrupoMasivo()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $estudiantes = Flight::request()->data['estudiantes'];
            $id_grupo_nuevo = Flight::request()->data['id_grupo'];
            $id_grado_nuevo = isset(Flight::request()->data['id_grado']) ? Flight::request()->data['id_grado'] : null;

            if (empty($estudiantes) || empty($id_grupo_nuevo)) {
                Flight::json(array('success' => false, 'message' => 'Datos incompletos'), 400);
                return;
            }

            $actualizados = 0;

            foreach ($estudiantes as $est) {
                $id_estudiante_grupo = $est['id_estudiante_grupo'];
                $id_estudiante = $est['id_estudiante'];
                $anno = $est['anno'];
                // Permite override por estudiante, si no usa el global
                $id_grado_est = isset($est['id_grado']) ? $est['id_grado'] : $id_grado_nuevo;

                // Inactivar registro actual
                $sentenceInactivar = $db->prepare("UPDATE estudiantes_x_grupos SET activo = 0 WHERE id = :id AND id_tenant = :id_tenant");
                $sentenceInactivar->bindParam(':id', $id_estudiante_grupo);
                $sentenceInactivar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceInactivar->execute();

                // Crear nuevo registro con el año del estudiante
                $sentenceNuevo = $db->prepare("INSERT INTO estudiantes_x_grupos (id_tenant, anio, id_estudiante, id_grupo, id_grado, activo) VALUES (:id_tenant, :anio, :id_estudiante, :id_grupo, :id_grado, 1)");
                $sentenceNuevo->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceNuevo->bindParam(':anio', $anno);
                $sentenceNuevo->bindParam(':id_estudiante', $id_estudiante);
                $sentenceNuevo->bindParam(':id_grupo', $id_grupo_nuevo);
                $sentenceNuevo->bindParam(':id_grado', $id_grado_est);
                $sentenceNuevo->execute();

                $actualizados++;
            }

            $db->commit();

            Flight::json(array(
                'success' => true,
                'actualizados' => $actualizados,
                'message' => 'Cambio de grupo completado'
            ));

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en cambioGrupoMasivo: " . $e->getMessage());
            Flight::json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }
}