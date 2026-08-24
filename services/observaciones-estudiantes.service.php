<?php
class ObservacionesEstudiantes
{

    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.observaciones');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_estudiante, id_tipo_observacion_estudiante, descripcion, fecha, id_estudiante_afectado, id_sprint, para_informe, fecha_informe_padre, firma_informe_padre, fecha_informe_padre_afectado, firma_informe_padre_afectado, id_usuario, fecha_registro FROM observaciones_estudiantes WHERE id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.observaciones');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_estudiante, id_tipo_observacion_estudiante, descripcion, fecha, id_estudiante_afectado, id_sprint, para_informe, fecha_informe_padre, firma_informe_padre, fecha_informe_padre_afectado, firma_informe_padre_afectado, id_usuario, fecha_registro FROM observaciones_estudiantes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdEstudiante($id)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.observaciones');

        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT 
            oe.id, 
            oe.id_estudiante, 
            oe.id_tipo_observacion_estudiante, 
            toe.nombre AS nombre_tipo_observacion,
            toe.seccion AS seccion_observacion,
            toe.color AS color_seccion,
            toe.orden_seccion,
            toe.icono AS icono_seccion,
            toe.aplica_informe,
            oe.descripcion, 
            oe.fecha, 
            oe.id_estudiante_afectado, 
            oe.id_sprint,
            s.nombre_sprint,
            s.numero_sprint,
            s.fecha_inicial AS fecha_inicial_sprint,
            s.fecha_final AS fecha_final_sprint,
            oe.para_informe,
            oe.fecha_informe_padre, 
            oe.firma_informe_padre, 
            oe.fecha_informe_padre_afectado, 
            oe.firma_informe_padre_afectado, 
            oe.id_usuario, 
            oe.fecha_registro,
            -- CONCAT_WS y no CONCAT: en CONCAT un solo NULL vuelve NULL toda
            -- la cadena, y basta con que la persona no tenga apellido (pasa
            -- con los usuarios que son una empresa y no una persona) para que
            -- el nombre llegara vacio y el front mostrara el texto por
            -- defecto. CONCAT_WS ignora los NULL y NULLIF deja el campo nulo
            -- solo cuando de verdad no hay ningun nombre.
            NULLIF(TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)), '') AS nombre_usuario,
            -- Obtener el nombre del estudiante afectado si existe
            NULLIF(TRIM(CONCAT_WS(' ',
                pa.primer_nombre,
                pa.segundo_nombre,
                pa.primer_apellido,
                pa.segundo_apellido
            )), '') AS nombre_estudiante_afectado,
            -- Marca de cual de los dos lados es el estudiante consultado.
            -- Con 1 la observacion la protagonizo otro nino y este solo fue
            -- el afectado.
            CASE WHEN oe.id_estudiante_afectado = :id_estudiante_marca
                 THEN 1 ELSE 0 END AS es_afectado
        FROM 
            observaciones_estudiantes oe
        LEFT JOIN 
            usuarios u ON oe.id_usuario = u.id
        LEFT JOIN 
            personas p ON u.id_persona = p.id
        -- Nuevos joins para obtener la información del estudiante afectado
        LEFT JOIN 
            estudiantes ea ON oe.id_estudiante_afectado = ea.id
        LEFT JOIN 
            personas pa ON ea.id_persona = pa.id
        -- Join con sprints para mostrar el sprint asociado
        LEFT JOIN
            sprints s ON oe.id_sprint = s.id
        LEFT JOIN
            tipos_observaciones_estudiantes toe ON oe.id_tipo_observacion_estudiante = toe.id
        WHERE 
            -- Salen las dos caras: donde el estudiante es el protagonista y
            -- donde es el afectado. Al papa del afectado tambien le interesa
            -- saber que paso, aunque el hecho se haya registrado del otro lado.
            (
                oe.id_estudiante = :id_estudiante
                OR oe.id_estudiante_afectado = :id_estudiante_afectado
            )
            AND oe.id_tenant = :id_tenant
        -- Ordenar por fecha descendente (más reciente primero)
        ORDER BY 
            oe.fecha DESC, oe.fecha_registro DESC
    ");
        $sentence->bindParam(':id_estudiante', $id);
        $sentence->bindParam(':id_estudiante_afectado', $id);
        $sentence->bindParam(':id_estudiante_marca', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.observaciones.administrar');

        $db = Flight::db();
        $id_estudiante = Flight::request()->data['id_estudiante'];
        $id_tipo_observacion_estudiante = Flight::request()->data['id_tipo_observacion_estudiante'];
        $descripcion = Flight::request()->data['descripcion'];
        $fecha = Flight::request()->data['fecha'] ?? date('Y-m-d H:i:s');
        $id_estudiante_afectado = Flight::request()->data['id_estudiante_afectado'] ?? null;
        $id_sprint = Flight::request()->data['id_sprint'] ?? null;
        $para_informe = !empty(Flight::request()->data['para_informe']) ? 1 : 0;
        $fecha_informe_padre = Flight::request()->data['fecha_informe_padre'] ?? null;
        $firma_informe_padre = Flight::request()->data['firma_informe_padre'] ?? null;
        $fecha_informe_padre_afectado = Flight::request()->data['fecha_informe_padre_afectado'] ?? null;
        $firma_informe_padre_afectado = Flight::request()->data['firma_informe_padre_afectado'] ?? null;
        $id_usuario = Flight::request()->data['id_usuario'];

        // Validar que id_sprint sea obligatorio
        if (empty($id_sprint)) {
            Flight::json(array('error' => 'El sprint es obligatorio'), 400);
            return;
        }

        // La fecha de registro se manejará automáticamente con el valor default de la BD

        $idNew = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO observaciones_estudiantes(id, id_tenant, id_estudiante, id_tipo_observacion_estudiante, descripcion, fecha, id_estudiante_afectado, id_sprint, para_informe, fecha_informe_padre, firma_informe_padre, fecha_informe_padre_afectado, firma_informe_padre_afectado, id_usuario) 
                                VALUES (:id, :id_tenant, :id_estudiante, :id_tipo_observacion_estudiante, :descripcion, :fecha, :id_estudiante_afectado, :id_sprint, :para_informe, :fecha_informe_padre, :firma_informe_padre, :fecha_informe_padre_afectado, :firma_informe_padre_afectado, :id_usuario)");

        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_tipo_observacion_estudiante', $id_tipo_observacion_estudiante);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':id_estudiante_afectado', $id_estudiante_afectado);
        $sentence->bindParam(':id_sprint', $id_sprint);
        $sentence->bindParam(':para_informe', $para_informe);
        $sentence->bindParam(':fecha_informe_padre', $fecha_informe_padre);
        $sentence->bindParam(':firma_informe_padre', $firma_informe_padre);
        $sentence->bindParam(':fecha_informe_padre_afectado', $fecha_informe_padre_afectado);
        $sentence->bindParam(':firma_informe_padre_afectado', $firma_informe_padre_afectado);
        $sentence->bindParam(':id_usuario', $id_usuario);

        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.observaciones.administrar');

        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_estudiante = Flight::request()->data['id_estudiante'];
        $id_tipo_observacion_estudiante = Flight::request()->data['id_tipo_observacion_estudiante'];
        $descripcion = Flight::request()->data['descripcion'];
        $fecha = Flight::request()->data['fecha'];
        $id_estudiante_afectado = Flight::request()->data['id_estudiante_afectado'] ?? null;
        $id_sprint = Flight::request()->data['id_sprint'] ?? null;
        $para_informe = !empty(Flight::request()->data['para_informe']) ? 1 : 0;
        $fecha_informe_padre = Flight::request()->data['fecha_informe_padre'] ?? null;
        $firma_informe_padre = Flight::request()->data['firma_informe_padre'] ?? null;
        $fecha_informe_padre_afectado = Flight::request()->data['fecha_informe_padre_afectado'] ?? null;
        $firma_informe_padre_afectado = Flight::request()->data['firma_informe_padre_afectado'] ?? null;
        $id_usuario = Flight::request()->data['id_usuario'];

        // Validar que id_sprint sea obligatorio
        if (empty($id_sprint)) {
            Flight::json(array('error' => 'El sprint es obligatorio'), 400);
            return;
        }

        // No actualizamos fecha_registro para mantener el registro original

        $sentence = $db->prepare("UPDATE observaciones_estudiantes SET 
                                id_estudiante = :id_estudiante, 
                                id_tipo_observacion_estudiante = :id_tipo_observacion_estudiante,
                                descripcion = :descripcion, 
                                fecha = :fecha, 
                                id_estudiante_afectado = :id_estudiante_afectado, 
                                id_sprint = :id_sprint,
                                para_informe = :para_informe,
                                fecha_informe_padre = :fecha_informe_padre, 
                                firma_informe_padre = :firma_informe_padre, 
                                fecha_informe_padre_afectado = :fecha_informe_padre_afectado, 
                                firma_informe_padre_afectado = :firma_informe_padre_afectado, 
                                id_usuario = :id_usuario 
                                WHERE id = :id AND id_tenant = :id_tenant");

        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_tipo_observacion_estudiante', $id_tipo_observacion_estudiante);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':id_estudiante_afectado', $id_estudiante_afectado);
        $sentence->bindParam(':id_sprint', $id_sprint);
        $sentence->bindParam(':para_informe', $para_informe);
        $sentence->bindParam(':fecha_informe_padre', $fecha_informe_padre);
        $sentence->bindParam(':firma_informe_padre', $firma_informe_padre);
        $sentence->bindParam(':fecha_informe_padre_afectado', $fecha_informe_padre_afectado);
        $sentence->bindParam(':firma_informe_padre_afectado', $firma_informe_padre_afectado);
        $sentence->bindParam(':id_usuario', $id_usuario);

        $sentence->execute();
        self::getById($id);
    }

    /**
     * Crea una observación disparada por otro módulo, no por un formulario.
     * Hoy la usa el registro de asistencia para dejar la observación de
     * ingreso y de salida.
     *
     * A diferencia de new(), aquí no hay un usuario llenando campos: el tipo
     * llega como código ('ingreso', 'salida') y el sprint se resuelve por la
     * fecha, porque quien registra la asistencia no está escogiendo ninguna
     * de las dos cosas.
     *
     * NO responde por HTTP ni valida permisos: la petición que la invoca ya
     * hizo lo suyo. Nunca lanza: un problema aquí no puede tumbar el registro
     * de asistencia, que es lo importante para quien está en la puerta.
     *
     * Devuelve un arreglo con:
     *   creada => true si quedó la observación
     *   id     => id de la observación creada, o null
     *   motivo => null si no había nada que guardar (sin observación escrita),
     *             o un código para que el front le explique a la usuaria por
     *             qué no quedó: 'tipo_no_configurado', 'sin_sprint', 'error'.
     */
    public static function crearAutomatica($db, $id_estudiante, $codigo_tipo, $descripcion, $id_usuario)
    {
        try {
            // Sin texto no hay observación que guardar. No es un error: la
            // docente simplemente no escribió nada al recibir al niño.
            if (empty($descripcion) || trim($descripcion) === '') {
                return array('creada' => false, 'id' => null, 'motivo' => null);
            }

            if (empty($id_estudiante)) {
                error_log("ObservacionesEstudiantes::crearAutomatica - llamada sin id_estudiante.");
                return array('creada' => false, 'id' => null, 'motivo' => 'error');
            }

            $sentence = $db->prepare("SELECT id FROM tipos_observaciones_estudiantes
                                      WHERE codigo = :codigo
                                      AND id_tenant = :id_tenant
                                      LIMIT 1");
            $sentence->bindParam(':codigo', $codigo_tipo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $tipo = $sentence->fetch();

            if (!$tipo) {
                error_log("ObservacionesEstudiantes::crearAutomatica - el tenant " . TenantContext::id()
                    . " no tiene el tipo de observacion con codigo '$codigo_tipo'; no se crea la observacion.");
                return array('creada' => false, 'id' => null, 'motivo' => 'tipo_no_configurado');
            }

            $fecha = date('Y-m-d');
            $id_sprint = self::resolverSprintPorFecha($db, $fecha);

            if (empty($id_sprint)) {
                error_log("ObservacionesEstudiantes::crearAutomatica - el tenant " . TenantContext::id()
                    . " no tiene un sprint que cubra $fecha ni uno marcado como actual; no se crea la observacion.");
                return array('creada' => false, 'id' => null, 'motivo' => 'sin_sprint');
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO observaciones_estudiantes(id, id_tenant, id_estudiante, id_tipo_observacion_estudiante, descripcion, fecha, id_sprint, para_informe, id_usuario)
                                      VALUES (:id, :id_tenant, :id_estudiante, :id_tipo_observacion_estudiante, :descripcion, :fecha, :id_sprint, 0, :id_usuario)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindValue(':id_tipo_observacion_estudiante', $tipo['id']);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindValue(':id_sprint', $id_sprint);
            $sentence->bindParam(':id_usuario', $id_usuario);
            $sentence->execute();

            return array('creada' => true, 'id' => $idNew, 'motivo' => null);
        } catch (Exception $e) {
            error_log("Error en ObservacionesEstudiantes::crearAutomatica: " . $e->getMessage());
            return array('creada' => false, 'id' => null, 'motivo' => 'error');
        }
    }

    /**
     * Devuelve el id del sprint que corresponde a una fecha en el tenant
     * actual. Primero el sprint cuyo rango la contenga; si ninguno la
     * contiene, el marcado como actual. null si el tenant no tiene sprints.
     *
     * Es el mismo criterio de preseleccionarSprintPorFecha() en el front, en
     * crear-observaciones.component.ts, para que una observación creada por
     * asistencia caiga en el mismo sprint que si la registraran a mano.
     */
    private static function resolverSprintPorFecha($db, $fecha)
    {
        $sentence = $db->prepare("SELECT id FROM sprints
                                  WHERE :fecha BETWEEN fecha_inicial AND fecha_final
                                  AND id_tenant = :id_tenant
                                  ORDER BY numero_sprint DESC
                                  LIMIT 1");
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $sprint = $sentence->fetch();

        if ($sprint) {
            return $sprint['id'];
        }

        $sentence = $db->prepare("SELECT id FROM sprints
                                  WHERE actual = 1
                                  AND id_tenant = :id_tenant
                                  LIMIT 1");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $sprint = $sentence->fetch();

        return $sprint ? $sprint['id'] : null;
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.observaciones.administrar');

        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM observaciones_estudiantes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }

    public static function getByEstudianteAfectado($id_estudiante_afectado)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.observaciones');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_estudiante, id_tipo_observacion_estudiante, descripcion, fecha, id_estudiante_afectado, id_sprint, para_informe, fecha_informe_padre, firma_informe_padre, fecha_informe_padre_afectado, firma_informe_padre_afectado, id_usuario, fecha_registro FROM observaciones_estudiantes WHERE id_estudiante_afectado = :id_estudiante_afectado AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_estudiante_afectado', $id_estudiante_afectado);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByTipoObservacion($id_tipo_observacion_estudiante)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.observaciones');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_estudiante, id_tipo_observacion_estudiante, descripcion, fecha, id_estudiante_afectado, id_sprint, para_informe, fecha_informe_padre, firma_informe_padre, fecha_informe_padre_afectado, firma_informe_padre_afectado, id_usuario, fecha_registro FROM observaciones_estudiantes WHERE id_tipo_observacion_estudiante = :id_tipo_observacion_estudiante AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_tipo_observacion_estudiante', $id_tipo_observacion_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Devuelve en una sola llamada todos los datos necesarios para el módulo
     * de Registro de Observaciones para Informe en un sprint específico:
     * - Estudiantes activos con su grupo
     * - Tipos de observación que aplican para informe (aplica_informe = 1)
     * - Observaciones existentes del sprint con para_informe = 1 para esos estudiantes
     * - Acudientes responsables de pago (con teléfono) por estudiante
     */
    public static function getDatosRegistroInforme($id_sprint)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.observaciones');

        $db = Flight::db();

        // 1. Estudiantes activos con grupo (mismo patrón usado en estudiantes-x-grupos-activos)
        $sqlEstudiantes = "
            SELECT 
                e.id AS id_estudiante,
                e.id_persona,
                CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_estudiante,
                p.numero_identificacion,
                IFNULL(g.nombre, 'Sin grupo') AS grupo_estudiante,
                IFNULL(g.id, 0) AS id_grupo
            FROM estudiantes e
            INNER JOIN personas p ON e.id_persona = p.id
            LEFT JOIN estudiantes_x_grupos eg ON e.id = eg.id_estudiante AND eg.activo = 1
            LEFT JOIN grupos g ON eg.id_grupo = g.id
            WHERE e.activo = 1
            AND e.id_tenant = :id_tenant
            ORDER BY g.nombre, p.primer_apellido, p.primer_nombre
        ";
        $sentence = $db->prepare($sqlEstudiantes);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $estudiantes = $sentence->fetchAll();

        // 2. Tipos de observación que aplican para informe
        $sqlTipos = "
            SELECT id, nombre, icono, valida_asistencia, requiere_firma, aplica_informe
            FROM tipos_observaciones_estudiantes
            WHERE aplica_informe = 1
            AND id_tenant = :id_tenant
            ORDER BY id
        ";
        $sentence = $db->prepare($sqlTipos);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $tipos_observaciones_informe = $sentence->fetchAll();

        // 3. Observaciones existentes en el sprint, para_informe = 1, de tipos que aplican para informe
        $sqlObservaciones = "
            SELECT 
                oe.id,
                oe.id_estudiante,
                oe.id_tipo_observacion_estudiante,
                toe.nombre AS nombre_tipo_observacion,
                toe.seccion AS seccion_observacion,
                toe.color AS color_seccion,
                toe.orden_seccion,
                oe.descripcion,
                oe.fecha,
                oe.id_sprint,
                oe.para_informe,
                oe.id_usuario,
                oe.fecha_registro
            FROM observaciones_estudiantes oe
            INNER JOIN tipos_observaciones_estudiantes toe ON oe.id_tipo_observacion_estudiante = toe.id
            WHERE oe.id_sprint = :id_sprint
              AND oe.para_informe = 1
              AND toe.aplica_informe = 1
              AND oe.id_tenant = :id_tenant
            ORDER BY oe.id_estudiante, oe.id_tipo_observacion_estudiante, oe.fecha DESC
        ";
        $sentence = $db->prepare($sqlObservaciones);
        $sentence->bindParam(':id_sprint', $id_sprint);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $observaciones_existentes = $sentence->fetchAll();

        // 4. Acudientes con teléfono por estudiante (todos, para que el componente seleccione)
        $sqlAcudientes = "
            SELECT 
                a.id AS id_acudiente,
                a.id_estudiante,
                a.id_persona AS id_persona_acudiente,
                CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_acudiente,
                ta.nombre AS tipo_acudiente,
                p.telefono,
                p.correo_electronico
            FROM acudientes a
            INNER JOIN personas p ON a.id_persona = p.id
            LEFT JOIN tipos_acudiente ta ON a.id_tipo_acudiente = ta.id
            INNER JOIN estudiantes e ON a.id_estudiante = e.id AND e.activo = 1
            WHERE a.id_tenant = :id_tenant
            ORDER BY a.id_estudiante, a.id
        ";
        $sentence = $db->prepare($sqlAcudientes);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $acudientes = $sentence->fetchAll();

        // 5. Datos del sprint solicitado (para el mensaje de WA)
        $sqlSprint = "
            SELECT id, numero_sprint, nombre_sprint, fecha_inicial, fecha_final, actual
            FROM sprints
            WHERE id = :id_sprint AND id_tenant = :id_tenant
        ";
        $sentence = $db->prepare($sqlSprint);
        $sentence->bindParam(':id_sprint', $id_sprint);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $sprintRows = $sentence->fetchAll();
        $sprint = count($sprintRows) > 0 ? $sprintRows[0] : null;

        Flight::json(array(
            'estudiantes' => $estudiantes,
            'tipos_observaciones_informe' => $tipos_observaciones_informe,
            'observaciones_existentes' => $observaciones_existentes,
            'acudientes' => $acudientes,
            'sprint' => $sprint
        ));
    }
}