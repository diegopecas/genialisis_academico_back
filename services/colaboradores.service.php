<?php
class Colaboradores
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT c.id, c.id_persona, c.id_rol_colaborador, rc.nombre nombre_rol, rc.codigo rol_codigo, rc.descripcion descripcion_rol,
        c.id_nivel_escolaridad, ne.nombre nivel_escolaridad, c.id_casa_colaborador, cc.nombre nombre_casa_colaborador,
        c.correo_electronico, c.sobrenombre, c.fecha_ingreso, c.fecha_retiro, c.id_motivo_retiro, mr.nombre nombre_motivo_retiro,
        c.id_cargo, car.nombre nombre_cargo, c.salario_mensual, c.id_tipo_contrato, tc.nombre nombre_tipo_contrato, tc.aplica_nomina,
        c.id_jefe_directo, c.activo, c.valida_ingreso_jornada, c.valida_ingreso_descanso,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, p.foto,
        p.id_tipo_identificacion, ti.nombre tipo_identificacion, p.numero_identificacion, 
        p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion, p.telefono, p.id_ciudad,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        CONCAT(IFNULL(pj.primer_nombre, ''), ' ', IFNULL(pj.segundo_nombre, ''), ' ', IFNULL(pj.primer_apellido, ''), ' ', IFNULL(pj.segundo_apellido, '')) AS nombre_jefe_directo
        FROM colaboradores c 
        INNER JOIN roles_colaborador rc ON c.id_rol_colaborador = rc.id
        INNER JOIN niveles_escolaridad ne ON c.id_nivel_escolaridad = ne.id 
        INNER JOIN personas p ON c.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        INNER JOIN generos g ON p.id_genero = g.id
        LEFT OUTER JOIN casas_colaboradores cc ON c.id_casa_colaborador = cc.id
        LEFT OUTER JOIN motivos_retiro mr ON c.id_motivo_retiro = mr.id
        LEFT OUTER JOIN cargos car ON c.id_cargo = car.id
        LEFT OUTER JOIN tipos_contrato tc ON c.id_tipo_contrato = tc.id
        LEFT OUTER JOIN colaboradores cj ON c.id_jefe_directo = cj.id
        LEFT OUTER JOIN personas pj ON cj.id_persona = pj.id
        WHERE c.id_tenant = :id_tenant
        ORDER BY p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        foreach ($response as &$row) {
            if (isset($row['nombre_completo'])) $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
            if (isset($row['nombre_jefe_directo'])) $row['nombre_jefe_directo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_jefe_directo']));
        }
        Flight::json($response);
    }

    /**
     * Listado filtrado de colaboradores. Filtros opcionales por query string;
     * los vacíos no se aplican (sin filtros => trae todos los del tenant).
     *   id_rol  => c.id_rol_colaborador
     *   id_casa => c.id_casa_colaborador
     *   estado  => 'activo' | 'inactivo' (sobre c.activo)
     *   nombre  => coincidencia parcial sobre el nombre completo
     */
    public static function getPorFiltros()
    {
        $db = Flight::db();

        $idRol  = isset(Flight::request()->query['id_rol']) ? trim(Flight::request()->query['id_rol']) : '';
        $idCasa = isset(Flight::request()->query['id_casa']) ? trim(Flight::request()->query['id_casa']) : '';
        $estado = isset(Flight::request()->query['estado']) ? trim(Flight::request()->query['estado']) : '';
        $nombre = isset(Flight::request()->query['nombre']) ? trim(Flight::request()->query['nombre']) : '';

        $sql = "SELECT c.id, c.id_persona, c.id_rol_colaborador, rc.nombre nombre_rol, rc.codigo rol_codigo, rc.descripcion descripcion_rol,
        c.id_nivel_escolaridad, ne.nombre nivel_escolaridad, c.id_casa_colaborador, cc.nombre nombre_casa_colaborador,
        c.correo_electronico, c.sobrenombre, c.fecha_ingreso, c.fecha_retiro, c.id_motivo_retiro, mr.nombre nombre_motivo_retiro,
        c.id_cargo, car.nombre nombre_cargo, c.salario_mensual, c.id_tipo_contrato, tc.nombre nombre_tipo_contrato, tc.aplica_nomina,
        c.id_jefe_directo, c.activo, c.valida_ingreso_jornada, c.valida_ingreso_descanso,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, p.foto,
        p.id_tipo_identificacion, ti.nombre tipo_identificacion, p.numero_identificacion, 
        p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion, p.telefono, p.id_ciudad,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        CONCAT(IFNULL(pj.primer_nombre, ''), ' ', IFNULL(pj.segundo_nombre, ''), ' ', IFNULL(pj.primer_apellido, ''), ' ', IFNULL(pj.segundo_apellido, '')) AS nombre_jefe_directo
        FROM colaboradores c 
        INNER JOIN roles_colaborador rc ON c.id_rol_colaborador = rc.id
        INNER JOIN niveles_escolaridad ne ON c.id_nivel_escolaridad = ne.id 
        INNER JOIN personas p ON c.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        INNER JOIN generos g ON p.id_genero = g.id
        LEFT OUTER JOIN casas_colaboradores cc ON c.id_casa_colaborador = cc.id
        LEFT OUTER JOIN motivos_retiro mr ON c.id_motivo_retiro = mr.id
        LEFT OUTER JOIN cargos car ON c.id_cargo = car.id
        LEFT OUTER JOIN tipos_contrato tc ON c.id_tipo_contrato = tc.id
        LEFT OUTER JOIN colaboradores cj ON c.id_jefe_directo = cj.id
        LEFT OUTER JOIN personas pj ON cj.id_persona = pj.id
        WHERE c.id_tenant = :id_tenant";

        $params = [];

        if ($idRol !== '') {
            $sql .= " AND c.id_rol_colaborador = :id_rol";
            $params[':id_rol'] = $idRol;
        }
        if ($idCasa !== '') {
            $sql .= " AND c.id_casa_colaborador = :id_casa";
            $params[':id_casa'] = $idCasa;
        }
        if ($estado === 'activo') {
            $sql .= " AND c.activo = 1";
        } elseif ($estado === 'inactivo') {
            $sql .= " AND c.activo = 0";
        }
        if ($nombre !== '') {
            $sql .= " AND CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) LIKE :nombre";
            $params[':nombre'] = '%' . $nombre . '%';
        }

        $sql .= " ORDER BY p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido";

        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        foreach ($params as $clave => $valor) {
            $sentence->bindValue($clave, $valor);
        }
        $sentence->execute();
        $response = $sentence->fetchAll();
        foreach ($response as &$row) {
            if (isset($row['nombre_completo'])) $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
            if (isset($row['nombre_jefe_directo'])) $row['nombre_jefe_directo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_jefe_directo']));
        }
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT c.id, c.id_persona, c.id_rol_colaborador, rc.nombre nombre_rol, rc.codigo rol_codigo, rc.descripcion descripcion_rol,
        c.id_nivel_escolaridad, ne.nombre nivel_escolaridad, c.id_casa_colaborador, cc.nombre nombre_casa_colaborador,
        c.correo_electronico, c.sobrenombre, c.fecha_ingreso, c.fecha_retiro, c.id_motivo_retiro, mr.nombre nombre_motivo_retiro,
        c.id_cargo, car.nombre nombre_cargo, c.salario_mensual, c.id_tipo_contrato, tc.nombre nombre_tipo_contrato, tc.aplica_nomina,
        c.id_jefe_directo, c.activo, c.valida_ingreso_jornada, c.valida_ingreso_descanso,
        d.id id_docente,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        p.id_tipo_identificacion, ti.nombre tipo_identificacion, p.numero_identificacion, 
        p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion, p.telefono, p.id_ciudad,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
        TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
        CONCAT(IFNULL(pj.primer_nombre, ''), ' ', IFNULL(pj.segundo_nombre, ''), ' ', IFNULL(pj.primer_apellido, ''), ' ', IFNULL(pj.segundo_apellido, '')) AS nombre_jefe_directo
        FROM colaboradores c 
        INNER JOIN roles_colaborador rc ON c.id_rol_colaborador = rc.id
        INNER JOIN niveles_escolaridad ne ON c.id_nivel_escolaridad = ne.id 
        INNER JOIN personas p ON c.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        INNER JOIN generos g ON p.id_genero = g.id
        LEFT OUTER JOIN casas_colaboradores cc ON c.id_casa_colaborador = cc.id
        LEFT OUTER JOIN motivos_retiro mr ON c.id_motivo_retiro = mr.id
        LEFT OUTER JOIN cargos car ON c.id_cargo = car.id
        LEFT OUTER JOIN tipos_contrato tc ON c.id_tipo_contrato = tc.id
        LEFT OUTER JOIN colaboradores cj ON c.id_jefe_directo = cj.id
        LEFT OUTER JOIN personas pj ON cj.id_persona = pj.id
        LEFT OUTER JOIN docentes d ON c.id = d.id_colaborador
        WHERE c.id = :id AND c.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        if (!empty($response)) {
            foreach ($response as &$row) {
                if (isset($row['nombre_completo'])) $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
                if (isset($row['nombre_jefe_directo'])) $row['nombre_jefe_directo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_jefe_directo']));
            }
        }
        Flight::json($response);
    }

    public static function getByIdPersona($id_persona)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT c.id, c.id_persona, c.id_rol_colaborador, rc.nombre nombre_rol, rc.codigo rol_codigo, rc.descripcion descripcion_rol,
        c.id_nivel_escolaridad, ne.nombre nivel_escolaridad, c.id_casa_colaborador, cc.nombre nombre_casa_colaborador,
        c.correo_electronico, c.sobrenombre, c.fecha_ingreso, c.fecha_retiro, c.id_motivo_retiro, mr.nombre nombre_motivo_retiro,
        c.id_cargo, car.nombre nombre_cargo, c.salario_mensual, c.id_tipo_contrato, tc.nombre nombre_tipo_contrato, tc.aplica_nomina,
        c.id_jefe_directo, c.activo, c.valida_ingreso_jornada, c.valida_ingreso_descanso,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        p.id_tipo_identificacion, ti.nombre tipo_identificacion, p.numero_identificacion, 
        p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion, p.telefono, p.id_ciudad
        FROM colaboradores c 
        INNER JOIN roles_colaborador rc ON c.id_rol_colaborador = rc.id
        INNER JOIN niveles_escolaridad ne ON c.id_nivel_escolaridad = ne.id 
        INNER JOIN personas p ON c.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        INNER JOIN generos g ON p.id_genero = g.id
        LEFT OUTER JOIN casas_colaboradores cc ON c.id_casa_colaborador = cc.id
        LEFT OUTER JOIN motivos_retiro mr ON c.id_motivo_retiro = mr.id
        LEFT OUTER JOIN cargos car ON c.id_cargo = car.id
        LEFT OUTER JOIN tipos_contrato tc ON c.id_tipo_contrato = tc.id
        WHERE c.id_persona = :id_persona AND c.id_tenant = :id_tenant");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        try {
            $db->beginTransaction();
            $id_persona = Flight::request()->data['id_persona'];
            $id_rol_colaborador = Flight::request()->data['id_rol_colaborador'];
            $id_nivel_escolaridad = Flight::request()->data['id_nivel_escolaridad'];
            $id_casa_colaborador = isset(Flight::request()->data['id_casa_colaborador']) ? Flight::request()->data['id_casa_colaborador'] : null;
            $correo_electronico = isset(Flight::request()->data['correo_electronico']) ? Flight::request()->data['correo_electronico'] : null;
            $sobrenombre = isset(Flight::request()->data['sobrenombre']) ? Flight::request()->data['sobrenombre'] : null;
            $fecha_ingreso = isset(Flight::request()->data['fecha_ingreso']) ? Flight::request()->data['fecha_ingreso'] : null;
            $fecha_retiro = isset(Flight::request()->data['fecha_retiro']) ? Flight::request()->data['fecha_retiro'] : null;
            $id_motivo_retiro = isset(Flight::request()->data['id_motivo_retiro']) ? Flight::request()->data['id_motivo_retiro'] : null;
            $id_cargo = isset(Flight::request()->data['id_cargo']) ? Flight::request()->data['id_cargo'] : null;
            $salario_mensual = isset(Flight::request()->data['salario_mensual']) ? Flight::request()->data['salario_mensual'] : null;
            $id_tipo_contrato = isset(Flight::request()->data['id_tipo_contrato']) ? Flight::request()->data['id_tipo_contrato'] : null;
            $id_jefe_directo = isset(Flight::request()->data['id_jefe_directo']) ? Flight::request()->data['id_jefe_directo'] : null;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;
            $valida_ingreso_jornada = isset(Flight::request()->data['valida_ingreso_jornada']) ? Flight::request()->data['valida_ingreso_jornada'] : 1;
            $valida_ingreso_descanso = isset(Flight::request()->data['valida_ingreso_descanso']) ? Flight::request()->data['valida_ingreso_descanso'] : 0;

            error_log("Datos recibidos para crear colaborador: id_persona=$id_persona, id_rol_colaborador=$id_rol_colaborador");

            $checkSentence = $db->prepare("SELECT id FROM colaboradores WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
            $checkSentence->bindParam(':id_persona', $id_persona);
            $checkSentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkSentence->execute();
            if ($checkSentence->fetch()) {
                $db->rollBack();
                Flight::json(array('error' => 'Ya existe un colaborador registrado con esta persona'), 400);
                return;
            }

            $sentence = $db->prepare("INSERT INTO colaboradores(id, id_tenant, id_persona, id_rol_colaborador, id_nivel_escolaridad, id_casa_colaborador, correo_electronico, sobrenombre, fecha_ingreso, fecha_retiro, id_motivo_retiro, id_cargo, salario_mensual, id_tipo_contrato, id_jefe_directo, activo, valida_ingreso_jornada, valida_ingreso_descanso) VALUES (:id, :id_tenant, :id_persona, :id_rol_colaborador, :id_nivel_escolaridad, :id_casa_colaborador, :correo_electronico, :sobrenombre, :fecha_ingreso, :fecha_retiro, :id_motivo_retiro, :id_cargo, :salario_mensual, :id_tipo_contrato, :id_jefe_directo, :activo, :valida_ingreso_jornada, :valida_ingreso_descanso)");
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_rol_colaborador', $id_rol_colaborador);
            $sentence->bindParam(':id_nivel_escolaridad', $id_nivel_escolaridad);
            $sentence->bindParam(':id_casa_colaborador', $id_casa_colaborador);
            $sentence->bindParam(':correo_electronico', $correo_electronico);
            $sentence->bindParam(':sobrenombre', $sobrenombre);
            $sentence->bindParam(':fecha_ingreso', $fecha_ingreso);
            $sentence->bindParam(':fecha_retiro', $fecha_retiro);
            $sentence->bindParam(':id_motivo_retiro', $id_motivo_retiro);
            $sentence->bindParam(':id_cargo', $id_cargo);
            $sentence->bindParam(':salario_mensual', $salario_mensual);
            $sentence->bindParam(':id_tipo_contrato', $id_tipo_contrato);
            $sentence->bindParam(':id_jefe_directo', $id_jefe_directo);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':valida_ingreso_jornada', $valida_ingreso_jornada);
            $sentence->bindParam(':valida_ingreso_descanso', $valida_ingreso_descanso);
            $idColab = Uuid::generar();
            $sentence->bindValue(':id', $idColab);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $id_colaborador = $idColab;
            if ($id_colaborador == 0) {
                $db->rollBack();
                error_log("Error: El ID del colaborador insertado es 0.");
                Flight::json(array('error' => 'No se pudo crear el colaborador. Intente de nuevo.'), 500);
                return;
            }
            if ($id_jefe_directo !== null && $id_jefe_directo == $id_colaborador) {
                $db->rollBack();
                Flight::json(array('error' => 'El colaborador no puede ser jefe de sí mismo'), 400);
                return;
            }

            error_log("ID colaborador insertado: $id_colaborador");

            $stmtRolCod = $db->prepare("SELECT codigo FROM roles_colaborador WHERE id = :id_rol AND id_tenant = :id_tenant");
            $stmtRolCod->bindParam(':id_rol', $id_rol_colaborador);
            $stmtRolCod->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtRolCod->execute();
            $rolCodRow = $stmtRolCod->fetch();
            if ($rolCodRow && $rolCodRow['codigo'] === 'DOCENTE') {
                error_log("Creando registro de docente para colaborador $id_colaborador");

                $insertDocente = $db->prepare("INSERT INTO docentes(id, id_tenant, id_persona, id_colaborador, activo, id_casa_docente) VALUES (:id, :id_tenant, :id_persona, :id_colaborador, :activo, :id_casa_docente)");
                $idDocente = Uuid::generar();
                $insertDocente->bindValue(':id', $idDocente);
                $insertDocente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $insertDocente->bindParam(':id_persona', $id_persona);
                $insertDocente->bindParam(':id_colaborador', $id_colaborador);
                $insertDocente->bindParam(':id_casa_docente', $id_casa_colaborador);
                $insertDocente->bindParam(':activo', $activo);
                $insertDocente->execute();

                $id_docente = $idDocente;
                error_log("Docente creado con ID: $id_docente");
            }

            $db->commit();
            Flight::json(array('id' => $id_colaborador));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en new de colaboradores: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $id = Flight::request()->data['id'];
            $id_persona = Flight::request()->data['id_persona'];
            $id_rol_colaborador = Flight::request()->data['id_rol_colaborador'];
            $id_nivel_escolaridad = Flight::request()->data['id_nivel_escolaridad'];
            $id_casa_colaborador = isset(Flight::request()->data['id_casa_colaborador']) ? Flight::request()->data['id_casa_colaborador'] : null;
            $correo_electronico = isset(Flight::request()->data['correo_electronico']) ? Flight::request()->data['correo_electronico'] : null;
            $sobrenombre = isset(Flight::request()->data['sobrenombre']) ? Flight::request()->data['sobrenombre'] : null;
            $fecha_ingreso = isset(Flight::request()->data['fecha_ingreso']) ? Flight::request()->data['fecha_ingreso'] : null;
            $fecha_retiro = isset(Flight::request()->data['fecha_retiro']) ? Flight::request()->data['fecha_retiro'] : null;
            $id_motivo_retiro = isset(Flight::request()->data['id_motivo_retiro']) ? Flight::request()->data['id_motivo_retiro'] : null;
            $id_cargo = isset(Flight::request()->data['id_cargo']) ? Flight::request()->data['id_cargo'] : null;
            $salario_mensual = isset(Flight::request()->data['salario_mensual']) ? Flight::request()->data['salario_mensual'] : null;
            $id_tipo_contrato = isset(Flight::request()->data['id_tipo_contrato']) ? Flight::request()->data['id_tipo_contrato'] : null;
            $id_jefe_directo = isset(Flight::request()->data['id_jefe_directo']) ? Flight::request()->data['id_jefe_directo'] : null;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;
            $valida_ingreso_jornada = isset(Flight::request()->data['valida_ingreso_jornada']) ? Flight::request()->data['valida_ingreso_jornada'] : 1;
            $valida_ingreso_descanso = isset(Flight::request()->data['valida_ingreso_descanso']) ? Flight::request()->data['valida_ingreso_descanso'] : 0;

            error_log("Actualizando colaborador id=$id, rol=$id_rol_colaborador");

            if ($id_jefe_directo !== null && $id_jefe_directo == $id) {
                $db->rollBack();
                Flight::json(array('error' => 'El colaborador no puede ser jefe de sí mismo'), 400);
                return;
            }

            $checkSentence = $db->prepare("SELECT id FROM colaboradores WHERE id_persona = :id_persona AND id != :id AND id_tenant = :id_tenant");
            $checkSentence->bindParam(':id_persona', $id_persona);
            $checkSentence->bindParam(':id', $id);
            $checkSentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkSentence->execute();
            if ($checkSentence->fetch()) {
                $db->rollBack();
                Flight::json(array('error' => 'Ya existe otro colaborador registrado con esta persona'), 400);
                return;
            }

            $getRolActual = $db->prepare("SELECT id_rol_colaborador FROM colaboradores WHERE id = :id AND id_tenant = :id_tenant");
            $getRolActual->bindParam(':id', $id);
            $getRolActual->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $getRolActual->execute();
            $colaboradorActual = $getRolActual->fetch();
            if (!$colaboradorActual) {
                $db->rollBack();
                Flight::json(array('error' => 'No se encontró el colaborador'), 404);
                return;
            }

            $rol_anterior = $colaboradorActual['id_rol_colaborador'];
            $rol_nuevo = $id_rol_colaborador;
            error_log("Cambio de rol: anterior=$rol_anterior, nuevo=$rol_nuevo");

            if ($rol_anterior == 1 && $rol_nuevo != 1) {
                error_log("Validando si puede cambiar de rol Docente");
                $getDocente = $db->prepare("SELECT id FROM docentes WHERE id_colaborador = :id_colaborador AND id_tenant = :id_tenant");
                $getDocente->bindParam(':id_colaborador', $id);
                $getDocente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $getDocente->execute();
                $docente = $getDocente->fetch();

                if ($docente) {
                    $checkGrupos = $db->prepare("SELECT COUNT(*) as total FROM docentes_x_grupos WHERE id_docente = :id_docente AND activo = 1 AND id_tenant = :id_tenant");
                    $checkGrupos->bindParam(':id_docente', $docente['id']);
                    $checkGrupos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $checkGrupos->execute();
                    $grupos = $checkGrupos->fetch();
                    if ($grupos['total'] > 0) {
                        $db->rollBack();
                        Flight::json(array('error' => 'No se puede cambiar el rol porque tiene ' . $grupos['total'] . ' grupo(s) asignado(s). Elimine primero las asignaciones en la pestaña "Grupos Asignados".'), 400);
                        return;
                    }

                    $checkAreas = $db->prepare("SELECT COUNT(*) as total FROM area_academica_x_grupo WHERE id_docente = :id_docente AND id_tenant = :id_tenant");
                    $checkAreas->bindParam(':id_docente', $docente['id']);
                    $checkAreas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $checkAreas->execute();
                    $areas = $checkAreas->fetch();
                    if ($areas['total'] > 0) {
                        $db->rollBack();
                        Flight::json(array('error' => 'No se puede cambiar el rol porque tiene ' . $areas['total'] . ' área(s) académica(s) asignada(s). Elimine primero las asignaciones en la pestaña "Áreas Académicas".'), 400);
                        return;
                    }

                    $checkTareas = $db->prepare("SELECT COUNT(*) as total FROM tareas_x_sprints WHERE (id_docente = :id_docente OR id_docente_inicia = :id_docente_inicia) AND id_tenant = :id_tenant");
                    $checkTareas->bindParam(':id_docente', $docente['id']);
                    $checkTareas->bindParam(':id_docente_inicia', $docente['id']);
                    $checkTareas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $checkTareas->execute();
                    $tareas = $checkTareas->fetch();
                    if ($tareas['total'] > 0) {
                        $db->rollBack();
                        Flight::json(array('error' => 'No se puede cambiar el rol porque tiene ' . $tareas['total'] . ' tarea(s) asignada(s) en sprints. Primero reasigne las tareas a otro docente.'), 400);
                        return;
                    }
                }
            }

            $sentence = $db->prepare("UPDATE colaboradores SET id_persona = :id_persona, id_rol_colaborador = :id_rol_colaborador, id_nivel_escolaridad = :id_nivel_escolaridad, id_casa_colaborador = :id_casa_colaborador, correo_electronico = :correo_electronico, sobrenombre = :sobrenombre, fecha_ingreso = :fecha_ingreso, fecha_retiro = :fecha_retiro, id_motivo_retiro = :id_motivo_retiro, id_cargo = :id_cargo, salario_mensual = :salario_mensual, id_tipo_contrato = :id_tipo_contrato, id_jefe_directo = :id_jefe_directo, activo = :activo, valida_ingreso_jornada = :valida_ingreso_jornada, valida_ingreso_descanso = :valida_ingreso_descanso WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_rol_colaborador', $id_rol_colaborador);
            $sentence->bindParam(':id_nivel_escolaridad', $id_nivel_escolaridad);
            $sentence->bindParam(':id_casa_colaborador', $id_casa_colaborador);
            $sentence->bindParam(':correo_electronico', $correo_electronico);
            $sentence->bindParam(':sobrenombre', $sobrenombre);
            $sentence->bindParam(':fecha_ingreso', $fecha_ingreso);
            $sentence->bindParam(':fecha_retiro', $fecha_retiro);
            $sentence->bindParam(':id_motivo_retiro', $id_motivo_retiro);
            $sentence->bindParam(':id_cargo', $id_cargo);
            $sentence->bindParam(':salario_mensual', $salario_mensual);
            $sentence->bindParam(':id_tipo_contrato', $id_tipo_contrato);
            $sentence->bindParam(':id_jefe_directo', $id_jefe_directo);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':valida_ingreso_jornada', $valida_ingreso_jornada);
            $sentence->bindParam(':valida_ingreso_descanso', $valida_ingreso_descanso);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            // CASO 1: Era docente y ahora NO lo es
            if ($rol_anterior == 1 && $rol_nuevo != 1) {
                error_log("Eliminando docente - pasó todas las validaciones");
                $getDocente = $db->prepare("SELECT id FROM docentes WHERE id_colaborador = :id_colaborador AND id_tenant = :id_tenant");
                $getDocente->bindParam(':id_colaborador', $id);
                $getDocente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $getDocente->execute();
                $docente = $getDocente->fetch();
                if ($docente) {
                    $eliminarDocente = $db->prepare("DELETE FROM docentes WHERE id = :id AND id_tenant = :id_tenant");
                    $eliminarDocente->bindParam(':id', $docente['id']);
                    $eliminarDocente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $eliminarDocente->execute();
                    error_log("Docente eliminado: ID " . $docente['id']);
                }
            }

            // CASO 2: NO era docente y ahora SÍ lo es
            if ($rol_anterior != 1 && $rol_nuevo == 1) {
                error_log("Verificando si ya existe docente");
                $checkDocente = $db->prepare("SELECT id FROM docentes WHERE (id_colaborador = :id_colaborador OR id_persona = :id_persona) AND id_tenant = :id_tenant");
                $checkDocente->bindParam(':id_colaborador', $id);
                $checkDocente->bindParam(':id_persona', $id_persona);
                $checkDocente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $checkDocente->execute();
                $docenteExistente = $checkDocente->fetch();

                if ($docenteExistente) {
                    error_log("Docente ya existe con ID: " . $docenteExistente['id']);
                } else {
                    error_log("Creando nuevo docente directamente");

                    $insertDocente = $db->prepare("INSERT INTO docentes(id, id_tenant, id_persona, id_colaborador, activo, id_casa_docente) VALUES (:id, :id_tenant, :id_persona, :id_colaborador, :activo, :id_casa_docente)");
                    $idDocente2 = Uuid::generar();
                    $insertDocente->bindValue(':id', $idDocente2);
                    $insertDocente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $insertDocente->bindParam(':id_persona', $id_persona);
                    $insertDocente->bindParam(':id_colaborador', $id);
                    $insertDocente->bindParam(':id_casa_docente', $id_casa_colaborador);
                    $insertDocente->bindParam(':activo', $activo);
                    $insertDocente->execute();

                    $id_docente_creado = $idDocente2;
                    error_log("Docente creado directamente con ID: $id_docente_creado");
                }
            }

            // CASO 3: Era y sigue siendo docente
            if ($rol_anterior == 1 && $rol_nuevo == 1) {
                $getDocente = $db->prepare("SELECT id FROM docentes WHERE id_colaborador = :id_colaborador AND id_tenant = :id_tenant");
                $getDocente->bindParam(':id_colaborador', $id);
                $getDocente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $getDocente->execute();
                $docente = $getDocente->fetch();
                if ($docente) {
                    $actualizarDocente = $db->prepare("UPDATE docentes SET id_casa_docente = :id_casa_docente, activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
                    $actualizarDocente->bindParam(':id_casa_docente', $id_casa_colaborador);
                    $actualizarDocente->bindParam(':activo', $activo);
                    $actualizarDocente->bindParam(':id', $docente['id']);
                    $actualizarDocente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $actualizarDocente->execute();
                    error_log("Docente actualizado");
                }
            }

            $db->commit();
            Flight::json(array('id' => $id, 'message' => 'Colaborador actualizado correctamente'));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en replace: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al actualizar el colaborador'), 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();
        try {
            $db->beginTransaction();
            $id = Flight::request()->data['id'];
            error_log("Eliminando colaborador id: $id");

            $checkPuntos = $db->prepare("SELECT COUNT(*) as total FROM puntos_casas_colaboradores WHERE (id_colaborador_entrega = :id OR id_colaborador_recibe = :id) AND id_tenant = :id_tenant");
            $checkPuntos->bindParam(':id', $id);
            $checkPuntos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkPuntos->execute();
            $puntos = $checkPuntos->fetch();
            if ($puntos['total'] > 0) {
                $db->rollBack();
                Flight::json(array('error' => 'No se puede eliminar porque tiene puntos registrados'), 400);
                return;
            }

            $getRol = $db->prepare("SELECT rc.codigo AS codigo_rol FROM colaboradores c LEFT JOIN roles_colaborador rc ON rc.id = c.id_rol_colaborador WHERE c.id = :id AND c.id_tenant = :id_tenant");
            $getRol->bindParam(':id', $id);
            $getRol->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $getRol->execute();
            $colaborador = $getRol->fetch();
            if (!$colaborador) {
                $db->rollBack();
                Flight::json(array('error' => 'No se encontró el colaborador'), 404);
                return;
            }

            if ($colaborador['codigo_rol'] === 'DOCENTE') {
                error_log("Eliminando docente asociado");
                $getDocente = $db->prepare("SELECT id FROM docentes WHERE id_colaborador = :id_colaborador AND id_tenant = :id_tenant");
                $getDocente->bindParam(':id_colaborador', $id);
                $getDocente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $getDocente->execute();
                $docente = $getDocente->fetch();
                if ($docente) {
                    Flight::request()->data->setData(array('id' => $docente['id']));
                    ob_start();
                    Docentes::delete();
                    $docenteResponse = ob_get_clean();
                    $docenteData = json_decode($docenteResponse, true);
                    if (isset($docenteData['error'])) {
                        $db->rollBack();
                        error_log("Error al eliminar docente: " . $docenteData['error']);
                        Flight::json(array('error' => 'Error al eliminar docente: ' . $docenteData['error']), 400);
                        return;
                    }
                    error_log("Docente eliminado exitosamente");
                }
            }

            $sentence = $db->prepare("DELETE FROM colaboradores WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            if ($sentence->rowCount() == 0) {
                $db->rollBack();
                Flight::json(array('error' => 'No se encontró el colaborador'), 404);
                return;
            }

            $db->commit();
            Flight::json(array('id' => $id, 'message' => 'Colaborador eliminado correctamente'));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en delete: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al eliminar el colaborador'), 500);
        }
    }

    public static function verificarDuplicados()
    {
        $db = Flight::db();
        $id_persona = Flight::request()->data['id_persona'];
        error_log("Verificando duplicados para id_persona: $id_persona");
        $sentence = $db->prepare("SELECT COUNT(*) as total FROM colaboradores WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json(array('existe' => $response['total'] > 0));
    }
}