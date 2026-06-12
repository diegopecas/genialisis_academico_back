<?php

class IaChat
{
    // =====================================================
    // ENDPOINTS PRINCIPALES
    // =====================================================

    public static function enviarMensaje()
    {
        try {
            $db = Flight::db();

            $id_persona = Flight::request()->data['id_persona'];
            $mensaje = Flight::request()->data['mensaje'];
            $id_conversacion = Flight::request()->data['id_conversacion'] ?? null;

            if (!$id_persona || empty(trim($mensaje))) {
                Flight::json(["error" => "id_persona y mensaje son requeridos"], 400);
                return;
            }

            $mensaje = trim($mensaje);

            // 1. Verificar que el usuario esté activo
            $sentence = $db->prepare("SELECT activo FROM usuarios WHERE id_persona = :id_persona LIMIT 1");
            $sentence->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
            $sentence->execute();
            $usuario_row = $sentence->fetch(PDO::FETCH_ASSOC);

            if (!$usuario_row || (int)$usuario_row['activo'] !== 1) {
                // Usuario inactivo - responder con contexto vacío
                $config = self::obtenerConfiguracion($db);
                $nombre_asistente = $config['nombre_asistente'] ?? 'Lumi';
                $contexto_inactivo = "Eres {$nombre_asistente}. El usuario que te habla NO tiene acceso al sistema. ";
                $contexto_inactivo .= "Su cuenta está inactiva. Responde amablemente que no tienes autorización para proporcionarle información ";
                $contexto_inactivo .= "y sugiérele que contacte a la administración del jardín para reactivar su acceso.";

                $inicio_tiempo = microtime(true);
                $respuesta_ia = self::llamarIA($config, $contexto_inactivo, [], $mensaje);
                $tiempo_ms = round((microtime(true) - $inicio_tiempo) * 1000);

                $id_conversacion_resp = $id_conversacion ?? self::crearConversacion($db, $id_persona, 'inactivo', $mensaje);
                self::guardarMensaje($db, $id_conversacion_resp, 'user', $mensaje);
                self::guardarMensaje($db, $id_conversacion_resp, 'assistant', $respuesta_ia['respuesta'], $respuesta_ia['proveedor'], $tiempo_ms);

                Flight::json([
                    "success" => true,
                    "id_conversacion" => (int) $id_conversacion_resp,
                    "respuesta" => $respuesta_ia['respuesta'],
                    "proveedor" => $respuesta_ia['proveedor'],
                    "tiempo_ms" => $tiempo_ms
                ]);
                return;
            }

            // 2. Determinar rol del usuario
            $rol = self::determinarRol($db, $id_persona);

            // 3. Obtener nombre de la persona
            $nombre = self::obtenerNombrePersona($db, $id_persona);

            // 4. Obtener o crear conversación
            if (!$id_conversacion) {
                $id_conversacion = self::crearConversacion($db, $id_persona, $rol, $mensaje);
            }

            // 4. Guardar mensaje del usuario
            self::guardarMensaje($db, $id_conversacion, 'user', $mensaje);

            // 5. Obtener historial reciente de la conversación
            $historial = self::obtenerHistorialReciente($db, $id_conversacion, 10);

            // 6. Obtener API keys y configuración
            $config = self::obtenerConfiguracion($db);

            // 7. Armar contexto según permisos del usuario
            $stmtUsuario = $db->prepare("SELECT id, super_admin FROM usuarios WHERE id_persona = :id_persona AND activo = 1 LIMIT 1");
            $stmtUsuario->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
            $stmtUsuario->execute();
            $usuarioRow = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
            $id_usuario = $usuarioRow ? (int)$usuarioRow['id'] : null;
            $es_super_admin = $usuarioRow ? (int)($usuarioRow['super_admin'] ?? 0) === 1 : false;

            $contexto = self::armarContexto($db, $id_persona, $rol, $nombre, $config, $id_usuario, $es_super_admin);

            // 8. Llamar a la IA (Gemini primero, Groq fallback)
            $inicio_tiempo = microtime(true);
            $respuesta_ia = self::llamarIA($config, $contexto, $historial, $mensaje);
            $tiempo_ms = round((microtime(true) - $inicio_tiempo) * 1000);

            // 9. Guardar respuesta de la IA
            self::guardarMensaje($db, $id_conversacion, 'assistant', $respuesta_ia['respuesta'], $respuesta_ia['proveedor'], $tiempo_ms);

            Flight::json([
                "success" => true,
                "id_conversacion" => (int) $id_conversacion,
                "respuesta" => $respuesta_ia['respuesta'],
                "proveedor" => $respuesta_ia['proveedor'],
                "tiempo_ms" => $tiempo_ms
            ]);
        } catch (PDOException $e) {
            error_log("Error en IaChat::enviarMensaje: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    public static function listarConversaciones($id_persona)
    {
        try {
            $db = Flight::db();

            $sentence = $db->prepare("SELECT id, titulo, rol, fecha_creacion, fecha_actualizacion 
                FROM ia_chat_conversaciones 
                WHERE id_persona = :id_persona AND activo = 1 
                ORDER BY fecha_actualizacion DESC 
                LIMIT 50");
            $sentence->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);

            Flight::json(["success" => true, "conversaciones" => $response]);
        } catch (PDOException $e) {
            error_log("Error en IaChat::listarConversaciones: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    public static function obtenerConversacion($id_conversacion)
    {
        try {
            $db = Flight::db();

            $sentence = $db->prepare("SELECT id, rol_mensaje, mensaje, proveedor, fecha 
                FROM ia_chat_mensajes 
                WHERE id_conversacion = :id_conversacion 
                ORDER BY fecha ASC");
            $sentence->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);

            Flight::json(["success" => true, "mensajes" => $response]);
        } catch (PDOException $e) {
            error_log("Error en IaChat::obtenerConversacion: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    public static function eliminarConversacion($id_conversacion)
    {
        try {
            $db = Flight::db();

            $sentence = $db->prepare("UPDATE ia_chat_conversaciones SET activo = 0 WHERE id = :id");
            $sentence->bindParam(':id', $id_conversacion, PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(["success" => true, "message" => "Conversación eliminada"]);
        } catch (PDOException $e) {
            error_log("Error en IaChat::eliminarConversacion: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    public static function obtenerLog()
    {
        try {
            $db = Flight::db();

            $limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 50;
            $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

            // Log: pares pregunta/respuesta desde mensajes + conversaciones
            $sentence = $db->prepare("SELECT 
                    m.id,
                    CONCAT(p.primer_nombre, ' ', p.primer_apellido) as nombre_persona,
                    c.rol,
                    m.mensaje as respuesta,
                    m.proveedor,
                    m.tiempo_respuesta_ms,
                    m.fecha,
                    (SELECT m2.mensaje FROM ia_chat_mensajes m2 
                     WHERE m2.id_conversacion = m.id_conversacion 
                     AND m2.id < m.id AND m2.rol_mensaje = 'user' 
                     ORDER BY m2.id DESC LIMIT 1) as pregunta
                FROM ia_chat_mensajes m
                INNER JOIN ia_chat_conversaciones c ON m.id_conversacion = c.id
                INNER JOIN personas p ON c.id_persona = p.id
                WHERE m.rol_mensaje = 'assistant'
                ORDER BY m.fecha DESC
                LIMIT :limite OFFSET :offset");
            $sentence->bindParam(':limite', $limite, PDO::PARAM_INT);
            $sentence->bindParam(':offset', $offset, PDO::PARAM_INT);
            $sentence->execute();
            $log = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // Estadísticas generales
            $sentence2 = $db->prepare("SELECT 
                    COUNT(*) as total_interacciones,
                    COUNT(DISTINCT c.id_persona) as usuarios_unicos,
                    ROUND(AVG(m.tiempo_respuesta_ms)) as promedio_tiempo_ms,
                    SUM(CASE WHEN m.proveedor = 'gemini' THEN 1 ELSE 0 END) as uso_gemini,
                    SUM(CASE WHEN m.proveedor = 'groq' THEN 1 ELSE 0 END) as uso_groq,
                    SUM(CASE WHEN m.proveedor = 'fallback' THEN 1 ELSE 0 END) as uso_fallback,
                    SUM(CASE WHEN DATE(m.fecha) = CURDATE() THEN 1 ELSE 0 END) as interacciones_hoy
                FROM ia_chat_mensajes m
                INNER JOIN ia_chat_conversaciones c ON m.id_conversacion = c.id
                WHERE m.rol_mensaje = 'assistant'");
            $sentence2->execute();
            $stats = $sentence2->fetch(PDO::FETCH_ASSOC);

            // Uso por rol
            $sentence3 = $db->prepare("SELECT c.rol, COUNT(*) as total
                FROM ia_chat_mensajes m
                INNER JOIN ia_chat_conversaciones c ON m.id_conversacion = c.id
                WHERE m.rol_mensaje = 'assistant'
                GROUP BY c.rol");
            $sentence3->execute();
            $uso_por_rol = $sentence3->fetchAll(PDO::FETCH_ASSOC);

            Flight::json([
                "success" => true,
                "log" => $log,
                "stats" => $stats,
                "uso_por_rol" => $uso_por_rol
            ]);
        } catch (PDOException $e) {
            error_log("Error en IaChat::obtenerLog: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    public static function verificarAccesoInstitucional($id_persona)
    {
        self::verificarAcceso($id_persona, 'chat_habilitado_institucional');
    }

    public static function verificarAccesoPadres($id_persona)
    {
        self::verificarAcceso($id_persona, 'chat_habilitado_padres');
    }

    private static function verificarAcceso($id_persona, $clave_config)
    {
        try {
            $db = Flight::db();

            // 1. Verificar si el chat está habilitado para este portal
            $config = self::obtenerConfiguracion($db);
            $habilitado = $config[$clave_config] ?? '0';
            if ($habilitado !== '1') {
                Flight::json(["success" => true, "tiene_acceso" => false, "razon" => "chat_deshabilitado"]);
                return;
            }

            // 2. Verificar que el usuario esté activo
            $sentence = $db->prepare("SELECT activo FROM usuarios WHERE id_persona = :id_persona LIMIT 1");
            $sentence->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
            $sentence->execute();
            $usuario = $sentence->fetch(PDO::FETCH_ASSOC);

            if (!$usuario || (int) $usuario['activo'] !== 1) {
                Flight::json(["success" => true, "tiene_acceso" => false, "razon" => "usuario_inactivo"]);
                return;
            }

            // 3. Verificar que tenga al menos un permiso activo
            $sentence = $db->prepare("SELECT COUNT(*) as total FROM ia_chat_permisos_usuario WHERE id_persona = :id_persona AND activo = 1");
            $sentence->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
            $sentence->execute();
            $permisos = $sentence->fetch(PDO::FETCH_ASSOC);

            if (!$permisos || (int) $permisos['total'] === 0) {
                Flight::json(["success" => true, "tiene_acceso" => false, "razon" => "sin_permisos"]);
                return;
            }

            // Todo OK
            $nombre_asistente = $config['nombre_asistente'] ?? 'Lumi';
            Flight::json([
                "success" => true,
                "tiene_acceso" => true,
                "nombre_asistente" => $nombre_asistente
            ]);
        } catch (PDOException $e) {
            error_log("Error en IaChat::verificarAcceso: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    // =====================================================
    // MÉTODOS PRIVADOS
    // =====================================================

    private static function determinarRol($db, $id_persona)
    {
        // ¿Es docente?
        $sentence = $db->prepare("SELECT id FROM docentes WHERE id_persona = :id_persona AND activo = 1 LIMIT 1");
        $sentence->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
        $sentence->execute();
        if ($sentence->fetch()) {
            return 'docente';
        }

        // ¿Es colaborador (admin)?
        $sentence = $db->prepare("SELECT id FROM colaboradores WHERE id_persona = :id_persona AND activo = 1 LIMIT 1");
        $sentence->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
        $sentence->execute();
        if ($sentence->fetch()) {
            return 'admin';
        }

        // ¿Es acudiente?
        $sentence = $db->prepare("SELECT id FROM acudientes WHERE id_persona = :id_persona AND activo = 1 LIMIT 1");
        $sentence->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
        $sentence->execute();
        if ($sentence->fetch()) {
            return 'acudiente';
        }

        return 'general';
    }

    private static function obtenerNombrePersona($db, $id_persona)
    {
        $sentence = $db->prepare("SELECT CONCAT_WS(' ', primer_nombre, primer_apellido) as nombre FROM personas WHERE id = :id");
        $sentence->bindParam(':id', $id_persona, PDO::PARAM_INT);
        $sentence->execute();
        $row = $sentence->fetch(PDO::FETCH_ASSOC);
        return ($row && $row['nombre']) ? trim($row['nombre']) : 'Usuario';
    }

    private static function crearConversacion($db, $id_persona, $rol, $primer_mensaje)
    {
        $titulo = mb_substr($primer_mensaje, 0, 80);

        $sentence = $db->prepare("INSERT INTO ia_chat_conversaciones (id_persona, rol, titulo) VALUES (:id_persona, :rol, :titulo)");
        $sentence->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
        $sentence->bindParam(':rol', $rol);
        $sentence->bindParam(':titulo', $titulo);
        $sentence->execute();

        return $db->lastInsertId();
    }

    private static function guardarMensaje($db, $id_conversacion, $rol_mensaje, $mensaje, $proveedor = null, $tiempo_ms = null)
    {
        $sentence = $db->prepare("INSERT INTO ia_chat_mensajes (id_conversacion, rol_mensaje, mensaje, proveedor, tiempo_respuesta_ms) VALUES (:id_conversacion, :rol_mensaje, :mensaje, :proveedor, :tiempo_ms)");
        $sentence->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
        $sentence->bindParam(':rol_mensaje', $rol_mensaje);
        $sentence->bindParam(':mensaje', $mensaje);
        $sentence->bindParam(':proveedor', $proveedor);
        $sentence->bindParam(':tiempo_ms', $tiempo_ms, PDO::PARAM_INT);
        $sentence->execute();
    }

    private static function obtenerHistorialReciente($db, $id_conversacion, $limite = 10)
    {
        $sentence = $db->prepare("SELECT rol_mensaje, mensaje FROM ia_chat_mensajes WHERE id_conversacion = :id_conversacion ORDER BY fecha DESC LIMIT :limite");
        $sentence->bindParam(':id_conversacion', $id_conversacion, PDO::PARAM_INT);
        $sentence->bindParam(':limite', $limite, PDO::PARAM_INT);
        $sentence->execute();
        $mensajes = $sentence->fetchAll(PDO::FETCH_ASSOC);

        return array_reverse($mensajes);
    }

    /**
     * Arma el contexto según los permisos del usuario
     * Consulta ia_chat_permisos_usuario y por cada tipo permitido
     * llama al método correspondiente para obtener la información
     */
    private static function armarContexto($db, $id_persona, $rol, $nombre, $config = [], $id_usuario = null, $es_super_admin = false)
    {
        // Obtener nombre del asistente desde configuración
        $nombre_asistente = $config['nombre_asistente'] ?? 'Lumi';
        $descripcion_asistente = $config['descripcion_asistente'] ?? 'Asistente virtual del Jardín';

        $contexto = "Eres {$nombre_asistente}, {$descripcion_asistente}. ";
        $contexto .= "Eres amable, profesional y hablas en español colombiano. ";
        $contexto .= "Respondes de forma clara y concisa. ";
        $contexto .= "Si no tienes información suficiente para responder, dilo honestamente y sugiere contactar a la administración del jardín. ";
        $contexto .= "Hoy es " . date('l j \d\e F \d\e Y') . ". ";
        $contexto .= "El usuario se llama {$nombre} y su rol es: {$rol}.\n\n";

        // Obtener permisos del usuario
        $permisos = self::obtenerPermisosUsuario($db, $id_persona);

        if (empty($permisos)) {
            $contexto .= "IMPORTANTE: Este usuario no tiene permisos de información configurados. ";
            $contexto .= "Solo puedes responder preguntas generales sobre el jardín (horarios, dirección, contacto). ";
            $contexto .= "Para cualquier otra consulta, sugiere contactar a la administración.\n";
            return $contexto;
        }

        // Obtener IDs de estudiantes y grupos a los que tiene acceso
        $ids_estudiantes = self::obtenerIdsEstudiantesAcceso($db, $id_persona, $rol);
        $ids_grupos = self::obtenerIdsGruposAcceso($db, $id_persona);

        $csv_estudiantes = !empty($ids_estudiantes) ? implode(',', $ids_estudiantes) : '';
        $csv_grupos = !empty($ids_grupos) ? implode(',', $ids_grupos) : '';

        // Por cada permiso, armar el bloque de contexto correspondiente
        foreach ($permisos as $permiso) {
            $codigo = $permiso['codigo'];
            $requiere_ids = (int) $permiso['requiere_ids_estudiantes'];

            // Si requiere IDs y no tiene ni estudiantes ni grupos, saltar
            if ($requiere_ids && empty($ids_estudiantes) && empty($ids_grupos)) {
                continue;
            }

            $contexto .= self::obtenerContextoPorTipo($db, $codigo, $csv_estudiantes, $csv_grupos);
        }

        // Agregar documentación de módulos accesibles desde db_master
        if ($id_usuario) {
            $contexto .= self::obtenerContextoAyudaModulos($db, $id_usuario, $es_super_admin);
        }

        // Reglas de seguridad según permisos
        $contexto .= "\nREGLAS DE SEGURIDAD:\n";
        $contexto .= "- Solo puedes responder con la información proporcionada arriba.\n";
        $contexto .= "- No inventes datos que no estén en el contexto.\n";
        $contexto .= "- Si te preguntan algo fuera de tu información disponible, indícalo amablemente.\n";
        $contexto .= "- Si te preguntan cómo usar un módulo del sistema, responde basándote en la documentación de módulos.\n";
        $contexto .= "- Si te piden un link o cómo acceder a un módulo, proporciona la ruta interna que aparece en la documentación.\n";

        return $contexto;
    }

    /**
     * Obtiene la documentación de módulos accesibles para el contexto de ayuda
     * Lee opciones_sistema de db_master, cruza permisos con db tenant
     */
    private static function obtenerContextoAyudaModulos($db, $id_usuario, $es_super_admin = false)
    {
        try {
            $dbMaster = Flight::db_master();

            if ($es_super_admin) {
                $stmt = $dbMaster->prepare("
                    SELECT nombre, descripcion_texto, ruta, ruta_principal
                    FROM opciones_sistema
                    WHERE activo = 1 AND descripcion_texto IS NOT NULL AND descripcion_texto != ''
                    ORDER BY orden, nombre
                ");
                $stmt->execute();
            } else {
                $stmtPermisos = $db->prepare("
                    SELECT DISTINCT pxr.codigo_permiso
                    FROM roles_x_usuario ru
                    INNER JOIN permisos_x_rol pxr ON ru.id_rol = pxr.id_rol
                    WHERE ru.id_usuario = :id_usuario
                ");
                $stmtPermisos->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
                $stmtPermisos->execute();
                $codigos = $stmtPermisos->fetchAll(PDO::FETCH_COLUMN);

                if (empty($codigos)) {
                    return "";
                }

                $placeholders = implode(',', array_fill(0, count($codigos), '?'));
                $stmt = $dbMaster->prepare("
                    SELECT DISTINCT os.nombre, os.descripcion_texto, os.ruta, os.ruta_principal
                    FROM opciones_sistema os
                    INNER JOIN permisos p ON p.id_modulo = os.id
                    WHERE p.codigo IN ({$placeholders}) 
                    AND p.activo = 1 
                    AND os.activo = 1 
                    AND os.descripcion_texto IS NOT NULL 
                    AND os.descripcion_texto != ''
                    ORDER BY os.orden, os.nombre
                ");
                $stmt->execute($codigos);
            }

            $modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($modulos)) {
                return "";
            }

            $contexto = "\nDOCUMENTACIÓN DE MÓDULOS DEL SISTEMA:\n";
            $contexto .= "(Cada módulo incluye su ruta interna. Cuando el usuario pregunte cómo acceder, proporciona la ruta.)\n\n";
            foreach ($modulos as $m) {
                $ruta = $m['ruta_principal'] ?: ($m['ruta'] ?: 'sin ruta');
                $contexto .= "--- {$m['nombre']} (ruta: {$ruta}) ---\n{$m['descripcion_texto']}\n\n";
            }

            return $contexto;
        } catch (Exception $e) {
            error_log("Error obteniendo contexto de ayuda: " . $e->getMessage());
            return "";
        }
    }

    /**
     * Obtiene los permisos activos del usuario
     */
    private static function obtenerPermisosUsuario($db, $id_persona)
    {
        $sentence = $db->prepare("
            SELECT t.codigo, t.nombre, t.requiere_ids_estudiantes
            FROM ia_chat_permisos_usuario p
            INNER JOIN ia_chat_tipos_informacion t ON p.id_tipo_informacion = t.id
            WHERE p.id_persona = :id_persona 
            AND p.activo = 1 
            AND t.activo = 1
        ");
        $sentence->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los IDs de estudiantes a los que la persona tiene acceso
     * Se resuelve en tiempo real según las relaciones en la BD
     */
    private static function obtenerIdsEstudiantesAcceso($db, $id_persona, $rol)
    {
        $ids = [];

        // Como acudiente: sus hijos
        $sentence = $db->prepare("
            SELECT DISTINCT a.id_estudiante 
            FROM acudientes a 
            WHERE a.id_persona = :id_persona AND a.activo = 1
        ");
        $sentence->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
        $sentence->execute();
        $hijos = $sentence->fetchAll(PDO::FETCH_COLUMN);
        $ids = array_merge($ids, $hijos);

        // Como docente: estudiantes de sus grupos
        $sentence = $db->prepare("
            SELECT DISTINCT eg.id_estudiante
            FROM docentes d
            INNER JOIN docentes_x_grupos dg ON d.id = dg.id_docente
            INNER JOIN estudiantes_x_grupos eg ON dg.id_grupo = eg.id_grupo AND eg.activo = 1
            WHERE d.id_persona = :id_persona AND d.activo = 1
        ");
        $sentence->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
        $sentence->execute();
        $grupo = $sentence->fetchAll(PDO::FETCH_COLUMN);
        $ids = array_merge($ids, $grupo);

        // Como admin/colaborador: todos los estudiantes activos
        $sentence = $db->prepare("SELECT id FROM colaboradores WHERE id_persona = :id_persona AND activo = 1 LIMIT 1");
        $sentence->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
        $sentence->execute();
        if ($sentence->fetch()) {
            $sentence2 = $db->prepare("
                SELECT DISTINCT eg.id_estudiante 
                FROM estudiantes_x_grupos eg 
                WHERE eg.activo = 1
            ");
            $sentence2->execute();
            $todos = $sentence2->fetchAll(PDO::FETCH_COLUMN);
            $ids = array_merge($ids, $todos);
        }

        return array_unique($ids);
    }

    /**
     * Obtiene los IDs de grupos a los que la persona tiene acceso como docente
     */
    private static function obtenerIdsGruposAcceso($db, $id_persona)
    {
        $sentence = $db->prepare("
            SELECT DISTINCT dg.id_grupo
            FROM docentes d
            INNER JOIN docentes_x_grupos dg ON d.id = dg.id_docente AND dg.activo = 1
            WHERE d.id_persona = :id_persona AND d.activo = 1
        ");
        $sentence->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Retorna el bloque de contexto para un tipo de información
     * Llama a stored procedures para datos reales, dummy para los pendientes
     */
    private static function obtenerContextoPorTipo($db, $codigo, $csv_estudiantes, $csv_grupos)
    {
        switch ($codigo) {
            case 'est_personal':
                return self::llamarSP($db, 'sp_ia_contexto_personal', $csv_estudiantes, null);
            case 'grupo_personal':
                return self::llamarSP($db, 'sp_ia_contexto_personal', null, $csv_grupos);
            case 'est_academico':
                return self::contextoDummyEstAcademico([]);
            case 'est_financiero':
                return self::contextoDummyEstFinanciero([]);
            case 'grupo_academico':
                return self::contextoDummyGrupoAcademico([]);
            case 'grupo_financiero':
                return self::contextoDummyGrupoFinanciero([]);
            case 'global_operativo':
                return self::contextoDummyGlobalOperativo();
            case 'global_academico':
                return self::contextoDummyGlobalAcademico();
            case 'global_financiero':
                return self::contextoDummyGlobalFinanciero();
            default:
                return "";
        }
    }

    /**
     * Llama a un stored procedure de contexto y retorna el texto
     */
    private static function llamarSP($db, $nombre_sp, $csv_estudiantes, $csv_grupos)
    {
        try {
            $sentence = $db->prepare("CALL {$nombre_sp}(:ids_est, :ids_gru)");
            $sentence->bindParam(':ids_est', $csv_estudiantes, PDO::PARAM_STR);
            $sentence->bindParam(':ids_gru', $csv_grupos, PDO::PARAM_STR);
            $sentence->execute();
            $resultado = $sentence->fetch(PDO::FETCH_ASSOC);
            $sentence->closeCursor();
            return $resultado['contexto'] ?? '';
        } catch (PDOException $e) {
            error_log("Error llamando SP {$nombre_sp}: " . $e->getMessage());
            return "\n[Error obteniendo datos de {$nombre_sp}]\n";
        }
    }

    // =====================================================
    // CONTEXTOS DUMMY POR TIPO - Reemplazar por queries reales
    // Cada método recibe los IDs de estudiantes cuando aplica
    // =====================================================

    private static function contextoDummyEstPersonal($ids_estudiantes)
    {
        $cantidad = count($ids_estudiantes);
        return <<<EOT

DATOS PERSONALES DE ESTUDIANTES (acceso a {$cantidad} estudiante(s)):
- Santiago Morales | 4 años | Grupo: Párvulos A | Docente: María García
  Acudiente principal: Miguel Morales (padre) | Tel: 310-XXX-XXXX
  Acudiente secundario: Laura Galindo (madre) | Tel: 311-XXX-XXXX
  Dirección: Chía, Cundinamarca
  EPS: Compensar | RH: O+

EOT;
    }

    private static function contextoDummyEstAcademico($ids_estudiantes)
    {
        $cantidad = count($ids_estudiantes);
        return <<<EOT

DATOS ACADÉMICOS DE ESTUDIANTES (acceso a {$cantidad} estudiante(s)):
- Santiago Morales:
  Sprint actual: Sprint 3 (Febrero 2026)
  Asistencia mes actual: 15/18 días (83%)
  Última observación: "Ha mejorado mucho en motricidad fina. Participa activamente."
  Calificaciones último sprint:
    Desarrollo cognitivo: Sobresaliente (4.5)
    Desarrollo social: Excelente (5.0)
    Motricidad: Sobresaliente (4.5)
    Comunicativa: Notable (4.0)
  Pendientes: Ninguno

EOT;
    }

    private static function contextoDummyEstFinanciero($ids_estudiantes)
    {
        $cantidad = count($ids_estudiantes);
        return <<<EOT

DATOS FINANCIEROS DE ESTUDIANTES (acceso a {$cantidad} estudiante(s)):
- Santiago Morales:
  Pensión mensual: $450.000
  Saldo pendiente: $450.000 (Febrero 2026, vence 15 Feb)
  Último pago: $450.000 (15 Ene 2026 - Pensión Enero)
  Estado general: Al día en pagos anteriores
  Historial de pagos (últimos 3 meses):
    Enero 2026: $450.000 ✓ pagado 15/01
    Diciembre 2025: $450.000 ✓ pagado 13/12
    Noviembre 2025: $450.000 ✓ pagado 14/11

EOT;
    }

    private static function contextoDummyGrupoPersonal($ids_estudiantes)
    {
        $cantidad = count($ids_estudiantes);
        return <<<EOT

DATOS PERSONALES DE ESTUDIANTES DEL GRUPO (acceso a {$cantidad} estudiante(s)):
- Santiago Morales | 4 años | Grupo: Párvulos A
  Acudiente: Miguel Morales (padre) | Tel: 310-XXX-XXXX
- Valentina López | 3 años | Grupo: Párvulos A
  Acudiente: Andrea López (madre) | Tel: 312-XXX-XXXX
- Mateo Rodríguez | 4 años | Grupo: Párvulos A
  Acudiente: Carlos Rodríguez (padre) | Tel: 315-XXX-XXXX
- Isabella Martínez | 3 años | Grupo: Párvulos A
  Acudiente: Paula Martínez (madre) | Tel: 318-XXX-XXXX
- Samuel García | 4 años | Grupo: Párvulos A
  Acudiente: Andrés García (padre) | Tel: 320-XXX-XXXX

EOT;
    }

    private static function contextoDummyGrupoAcademico($ids_estudiantes)
    {
        $cantidad = count($ids_estudiantes);
        return <<<EOT

DATOS ACADÉMICOS DEL GRUPO (acceso a {$cantidad} estudiante(s)):
Sprint actual: Sprint 3 (Febrero 2026)

Resumen de asistencia del grupo:
- Promedio asistencia: 87%
- Presentes hoy: 13/15

Calificaciones del grupo (promedio por área):
- Desarrollo cognitivo: 4.3
- Desarrollo social: 4.6
- Motricidad: 4.1
- Comunicativa: 3.9

Estudiantes que requieren apoyo:
- Mateo Rodríguez: Requiere apoyo en lenguaje (comunicativa: 3.2)

Pendientes docente:
- Observaciones semanales (vence Viernes)
- Calificaciones corte 1 (vence 28 Feb)

EOT;
    }

    private static function contextoDummyGrupoFinanciero($ids_estudiantes)
    {
        $cantidad = count($ids_estudiantes);
        return <<<EOT

DATOS FINANCIEROS DEL GRUPO (acceso a {$cantidad} estudiante(s)):
- Familias al día: 12 de 15 (80%)
- Familias en mora: 3
  - Familia Rodríguez: $450.000 pendiente (Febrero)
  - Familia López: $900.000 pendiente (Enero + Febrero)
  - Familia García: $450.000 pendiente (Febrero)
- Total pendiente del grupo: $1.800.000

EOT;
    }

    private static function contextoDummyGlobalOperativo()
    {
        return <<<EOT

DATOS OPERATIVOS DEL JARDÍN:
- Estudiantes activos: 85
- Docentes: 8 | Colaboradores: 12
- Ocupación: 85% (85 de 100 cupos)
- Horario: Lunes a Viernes 7:00 AM - 5:00 PM
- Dirección: Chía, Cundinamarca

Distribución por grupos:
  Sala cuna (0-1 año): 10 niños - Docente: Ana Ruiz
  Caminadores (1-2 años): 12 niños - Docente: Laura Torres
  Párvulos A (3-4 años): 15 niños - Docente: María García
  Párvulos B (3-4 años): 14 niños - Docente: Carmen Díaz
  Pre-jardín A (4-5 años): 18 niños - Docente: Patricia Gómez
  Pre-jardín B (4-5 años): 16 niños - Docente: Sofía Herrera

Asistencia hoy: 78/85 presentes (91.8%)
Inasistencias reportadas: 5 | Sin reporte: 2

EOT;
    }

    private static function contextoDummyGlobalAcademico()
    {
        return <<<EOT

DATOS ACADÉMICOS GLOBALES DEL JARDÍN:
- Sprint actual: Sprint 3 (Febrero 2026)
- Promedio general de calificaciones: 4.2 / 5.0
- Área con mejor rendimiento: Desarrollo social (4.6)
- Área con menor rendimiento: Comunicativa (3.8)
- Estudiantes con todas las áreas en Sobresaliente o superior: 23 (27%)
- Estudiantes que requieren apoyo: 8 (9.4%)
- Calificaciones pendientes por registrar: 12 tareas

EOT;
    }

    private static function contextoDummyGlobalFinanciero()
    {
        return <<<EOT

DATOS FINANCIEROS GLOBALES DEL JARDÍN (mes actual):
- Total facturado: $38.250.000
- Total recaudado: $31.500.000
- Cartera pendiente: $6.750.000
- Familias en mora: 8 de 85 (9.4%)
- Porcentaje de recaudo: 82.4%
- Familias al día: 77 de 85 (90.6%)

EOT;
    }

    // =====================================================
    // LLAMADAS A PROVEEDORES IA
    // =====================================================

    private static function llamarIA($config, $contexto, $historial, $mensaje_usuario)
    {
        // Intentar Gemini primero
        $gemini_key = $config['gemini_api_key'] ?? null;
        if ($gemini_key) {
            $resultado = self::llamarGemini($gemini_key, $contexto, $historial, $mensaje_usuario);
            if ($resultado['success']) {
                return ["respuesta" => $resultado['respuesta'], "proveedor" => "gemini"];
            }
            error_log("Gemini falló: " . ($resultado['error'] ?? 'desconocido'));
        }

        // Fallback a Groq
        $groq_key = $config['groq_api_key'] ?? null;
        if ($groq_key) {
            $resultado = self::llamarGroq($groq_key, $contexto, $historial, $mensaje_usuario);
            if ($resultado['success']) {
                return ["respuesta" => $resultado['respuesta'], "proveedor" => "groq"];
            }
            error_log("Groq falló: " . ($resultado['error'] ?? 'desconocido'));
        }

        return [
            "respuesta" => "Lo siento, en este momento no puedo procesar tu consulta. Por favor intenta de nuevo en unos minutos o contacta a la administración del jardín.",
            "proveedor" => "fallback"
        ];
    }

    private static function llamarGemini($api_key, $contexto, $historial, $mensaje_usuario)
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . $api_key;

            $contents = [];

            // Contexto como primer intercambio
            $contents[] = ["role" => "user", "parts" => [["text" => $contexto . "\n\nResponde siempre en español."]]];
            $contents[] = ["role" => "model", "parts" => [["text" => "Entendido. Estoy listo para ayudarte. ¿En qué puedo asistirte?"]]];

            // Historial
            foreach ($historial as $msg) {
                $role = $msg['rol_mensaje'] === 'user' ? 'user' : 'model';
                $contents[] = ["role" => $role, "parts" => [["text" => $msg['mensaje']]]];
            }

            // Mensaje actual (si no está ya en historial)
            if (empty($historial) || end($historial)['mensaje'] !== $mensaje_usuario) {
                $contents[] = ["role" => "user", "parts" => [["text" => $mensaje_usuario]]];
            }

            $body = json_encode([
                "contents" => $contents,
                "generationConfig" => ["temperature" => 0.7, "maxOutputTokens" => 1024]
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                return ["success" => false, "error" => "HTTP " . $http_code];
            }

            $data = json_decode($response, true);

            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return ["success" => true, "respuesta" => trim($data['candidates'][0]['content']['parts'][0]['text'])];
            }

            return ["success" => false, "error" => "Formato inesperado"];
        } catch (Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    private static function llamarGroq($api_key, $contexto, $historial, $mensaje_usuario)
    {
        try {
            $url = "https://api.groq.com/openai/v1/chat/completions";

            $messages = [];
            $messages[] = ["role" => "system", "content" => $contexto . "\n\nResponde siempre en español."];

            foreach ($historial as $msg) {
                $messages[] = [
                    "role" => $msg['rol_mensaje'] === 'user' ? 'user' : 'assistant',
                    "content" => $msg['mensaje']
                ];
            }

            if (empty($historial) || end($historial)['mensaje'] !== $mensaje_usuario) {
                $messages[] = ["role" => "user", "content" => $mensaje_usuario];
            }

            $body = json_encode([
                "model" => "llama-3.3-70b-versatile",
                "messages" => $messages,
                "temperature" => 0.7,
                "max_tokens" => 1024
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                return ["success" => false, "error" => "HTTP " . $http_code];
            }

            $data = json_decode($response, true);

            if (isset($data['choices'][0]['message']['content'])) {
                return ["success" => true, "respuesta" => trim($data['choices'][0]['message']['content'])];
            }

            return ["success" => false, "error" => "Formato inesperado"];
        } catch (Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    private static function obtenerConfiguracion($db)
    {
        $sentence = $db->prepare("SELECT clave, valor FROM ia_configuracion");
        $sentence->execute();
        $rows = $sentence->fetchAll(PDO::FETCH_ASSOC);

        $config = [];
        foreach ($rows as $row) {
            $config[$row['clave']] = $row['valor'];
        }

        return $config;
    }
}