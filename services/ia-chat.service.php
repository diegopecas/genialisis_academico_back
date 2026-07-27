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
            $sentence = $db->prepare("SELECT activo FROM usuarios WHERE id_persona = :id_persona AND id_tenant = :id_tenant LIMIT 1");
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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

                // Reusar la conversación entrante solo si existe y es de esta persona/tenant; si no, crear nueva
                $id_conversacion_resp = self::conversacionValida($db, $id_conversacion, $id_persona)
                    ? $id_conversacion
                    : self::crearConversacion($db, $id_persona, 'inactivo', $mensaje);
                self::guardarMensaje($db, $id_conversacion_resp, 'user', $mensaje);
                self::guardarMensaje($db, $id_conversacion_resp, 'assistant', $respuesta_ia['respuesta'], $respuesta_ia['proveedor'], $tiempo_ms);

                Flight::json([
                    "success" => true,
                    "id_conversacion" => $id_conversacion_resp,
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
            // Si el id entrante no existe o no pertenece a esta persona/tenant, se crea una nueva
            if (!self::conversacionValida($db, $id_conversacion, $id_persona)) {
                $id_conversacion = self::crearConversacion($db, $id_persona, $rol, $mensaje);
            }

            // 4. Guardar mensaje del usuario
            self::guardarMensaje($db, $id_conversacion, 'user', $mensaje);

            // 5. Obtener historial reciente de la conversación
            $historial = self::obtenerHistorialReciente($db, $id_conversacion, 10);

            // 6. Obtener API keys y configuración
            $config = self::obtenerConfiguracion($db);

            // 7. Armar contexto según permisos del usuario
            $stmtUsuario = $db->prepare("SELECT id, super_admin FROM usuarios WHERE id_persona = :id_persona AND activo = 1 AND id_tenant = :id_tenant LIMIT 1");
            $stmtUsuario->bindParam(':id_persona', $id_persona);
            $stmtUsuario->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
                "id_conversacion" => $id_conversacion,
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
                WHERE id_persona = :id_persona AND activo = 1 AND id_tenant = :id_tenant 
                ORDER BY fecha_actualizacion DESC 
                LIMIT 50");
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
                WHERE id_conversacion = :id_conversacion AND id_tenant = :id_tenant 
                ORDER BY fecha ASC");
            $sentence->bindParam(':id_conversacion', $id_conversacion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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

            $sentence = $db->prepare("UPDATE ia_chat_conversaciones SET activo = 0 WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id_conversacion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
                AND m.id_tenant = :id_tenant
                ORDER BY m.fecha DESC
                LIMIT :limite OFFSET :offset");
            $sentence->bindParam(':limite', $limite, PDO::PARAM_INT);
            $sentence->bindParam(':offset', $offset, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
                WHERE m.rol_mensaje = 'assistant'
                AND m.id_tenant = :id_tenant");
            $sentence2->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence2->execute();
            $stats = $sentence2->fetch(PDO::FETCH_ASSOC);

            // Uso por rol
            $sentence3 = $db->prepare("SELECT c.rol, COUNT(*) as total
                FROM ia_chat_mensajes m
                INNER JOIN ia_chat_conversaciones c ON m.id_conversacion = c.id
                WHERE m.rol_mensaje = 'assistant'
                AND m.id_tenant = :id_tenant
                GROUP BY c.rol");
            $sentence3->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
            $sentence = $db->prepare("SELECT activo FROM usuarios WHERE id_persona = :id_persona AND id_tenant = :id_tenant LIMIT 1");
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $usuario = $sentence->fetch(PDO::FETCH_ASSOC);

            if (!$usuario || (int) $usuario['activo'] !== 1) {
                Flight::json(["success" => true, "tiene_acceso" => false, "razon" => "usuario_inactivo"]);
                return;
            }

            // 3. Verificar que tenga al menos un permiso activo
            $sentence = $db->prepare("SELECT COUNT(*) as total FROM ia_chat_permisos_usuario WHERE id_persona = :id_persona AND activo = 1 AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
        $sentence = $db->prepare("SELECT id FROM docentes WHERE id_persona = :id_persona AND activo = 1 AND id_tenant = :id_tenant LIMIT 1");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        if ($sentence->fetch()) {
            return 'docente';
        }

        // ¿Es colaborador (admin)?
        $sentence = $db->prepare("SELECT id FROM colaboradores WHERE id_persona = :id_persona AND activo = 1 AND id_tenant = :id_tenant LIMIT 1");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        if ($sentence->fetch()) {
            return 'admin';
        }

        // ¿Es acudiente?
        $sentence = $db->prepare("SELECT id FROM acudientes WHERE id_persona = :id_persona AND activo = 1 AND id_tenant = :id_tenant LIMIT 1");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        if ($sentence->fetch()) {
            return 'acudiente';
        }

        return 'general';
    }

    private static function obtenerNombrePersona($db, $id_persona)
    {
        $sentence = $db->prepare("SELECT CONCAT_WS(' ', primer_nombre, primer_apellido) as nombre FROM personas WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $row = $sentence->fetch(PDO::FETCH_ASSOC);
        return ($row && $row['nombre']) ? trim($row['nombre']) : 'Usuario';
    }

    /**
     * Verifica que la conversación exista, esté activa y pertenezca a la persona/tenant.
     * Evita que un id_conversacion inválido (p. ej. estado viejo del cliente) rompa el INSERT
     * de mensajes por la FK; en ese caso el flujo crea una conversación nueva.
     */
    private static function conversacionValida($db, $id_conversacion, $id_persona)
    {
        if (!$id_conversacion) {
            return false;
        }

        $sentence = $db->prepare("SELECT 1 FROM ia_chat_conversaciones 
            WHERE id = :id AND id_persona = :id_persona AND activo = 1 AND id_tenant = :id_tenant 
            LIMIT 1");
        $sentence->bindParam(':id', $id_conversacion);
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        return (bool) $sentence->fetch();
    }

    private static function crearConversacion($db, $id_persona, $rol, $primer_mensaje)
    {
        $titulo = mb_substr($primer_mensaje, 0, 80);

        $sentence = $db->prepare("INSERT INTO ia_chat_conversaciones (id, id_tenant, id_persona, rol, titulo) VALUES (:id, :id_tenant, :id_persona, :rol, :titulo)");
        $id = Uuid::generar();
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindParam(':rol', $rol);
        $sentence->bindParam(':titulo', $titulo);
        $sentence->execute();

        return $id;
    }

    private static function guardarMensaje($db, $id_conversacion, $rol_mensaje, $mensaje, $proveedor = null, $tiempo_ms = null)
    {
        $sentence = $db->prepare("INSERT INTO ia_chat_mensajes (id, id_tenant, id_conversacion, rol_mensaje, mensaje, proveedor, tiempo_respuesta_ms) VALUES (:id, :id_tenant, :id_conversacion, :rol_mensaje, :mensaje, :proveedor, :tiempo_ms)");
        $idMsg = Uuid::generar();
        $sentence->bindValue(':id', $idMsg);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_conversacion', $id_conversacion);
        $sentence->bindParam(':rol_mensaje', $rol_mensaje);
        $sentence->bindParam(':mensaje', $mensaje);
        $sentence->bindParam(':proveedor', $proveedor);
        $sentence->bindParam(':tiempo_ms', $tiempo_ms, PDO::PARAM_INT);
        $sentence->execute();
    }

    private static function obtenerHistorialReciente($db, $id_conversacion, $limite = 10)
    {
        $sentence = $db->prepare("SELECT rol_mensaje, mensaje FROM ia_chat_mensajes WHERE id_conversacion = :id_conversacion AND id_tenant = :id_tenant ORDER BY fecha DESC LIMIT :limite");
        $sentence->bindParam(':id_conversacion', $id_conversacion);
        $sentence->bindParam(':limite', $limite, PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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

        // Foto de contexto global (operativo/financiero) reusando el dashboard.
        // Solo se calcula si el usuario tiene algún permiso global que la necesite.
        $codigos = array_column($permisos, 'codigo');
        $necesitaFoto = in_array('global_operativo', $codigos, true) || in_array('global_financiero', $codigos, true);
        $foto = $necesitaFoto ? self::obtenerFotoContexto($db, $config) : null;

        // Por cada permiso, armar el bloque de contexto correspondiente
        foreach ($permisos as $permiso) {
            $codigo = $permiso['codigo'];
            $requiere_ids = (int) $permiso['requiere_ids_estudiantes'];

            // Si requiere IDs y no tiene ni estudiantes ni grupos, saltar
            if ($requiere_ids && empty($ids_estudiantes) && empty($ids_grupos)) {
                continue;
            }

            $contexto .= self::obtenerContextoPorTipo($db, $codigo, $csv_estudiantes, $csv_grupos, $foto);
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
                    AND ru.id_tenant = :id_tenant
                ");
                $stmtPermisos->bindParam(':id_usuario', $id_usuario);
                $stmtPermisos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
            AND p.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
            WHERE a.id_persona = :id_persona AND a.activo = 1 AND a.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $hijos = $sentence->fetchAll(PDO::FETCH_COLUMN);
        $ids = array_merge($ids, $hijos);

        // Como docente: estudiantes de sus grupos
        $sentence = $db->prepare("
            SELECT DISTINCT eg.id_estudiante
            FROM docentes d
            INNER JOIN docentes_x_grupos dg ON d.id = dg.id_docente
            INNER JOIN estudiantes_x_grupos eg ON dg.id_grupo = eg.id_grupo AND eg.activo = 1
            WHERE d.id_persona = :id_persona AND d.activo = 1 AND d.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $grupo = $sentence->fetchAll(PDO::FETCH_COLUMN);
        $ids = array_merge($ids, $grupo);

        // Como admin/colaborador: todos los estudiantes activos
        $sentence = $db->prepare("SELECT id FROM colaboradores WHERE id_persona = :id_persona AND activo = 1 AND id_tenant = :id_tenant LIMIT 1");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        if ($sentence->fetch()) {
            $sentence2 = $db->prepare("
                SELECT DISTINCT eg.id_estudiante 
                FROM estudiantes_x_grupos eg 
                WHERE eg.activo = 1 AND eg.id_tenant = :id_tenant
            ");
            $sentence2->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
            WHERE d.id_persona = :id_persona AND d.activo = 1 AND d.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Retorna el bloque de contexto para un tipo de información.
     * - Personal (est/grupo): datos reales vía stored procedure.
     * - Global operativo/financiero: datos reales del dashboard (foto en caché).
     * - Resto: aún sin fuente de datos, devuelve texto genérico (no inventa).
     */
    private static function obtenerContextoPorTipo($db, $codigo, $csv_estudiantes, $csv_grupos, $foto = null)
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
                // Datos reales del dashboard; si la foto no está disponible, cae a genérico
                return (is_array($foto) && isset($foto['operativo']))
                    ? self::textoContextoOperativo($foto['operativo'])
                    : self::contextoDummyGlobalOperativo();
            case 'global_academico':
                return self::contextoDummyGlobalAcademico();
            case 'global_financiero':
                // Datos reales del dashboard; si la foto no está disponible, cae a genérico
                return (is_array($foto) && isset($foto['financiero']))
                    ? self::textoContextoFinanciero($foto['financiero'])
                    : self::contextoDummyGlobalFinanciero();
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
            $sentence = $db->prepare("CALL {$nombre_sp}(:ids_est, :ids_gru, :id_tenant)");
            $sentence->bindParam(':ids_est', $csv_estudiantes, PDO::PARAM_STR);
            $sentence->bindParam(':ids_gru', $csv_grupos, PDO::PARAM_STR);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resultado = $sentence->fetch(PDO::FETCH_ASSOC);
            $sentence->closeCursor();
            return $resultado['contexto'] ?? '';
        } catch (PDOException $e) {
            error_log("Error llamando SP {$nombre_sp}: " . $e->getMessage());
            return "\n[Error obteniendo datos de {$nombre_sp}]\n";
        }
    }

    /**
     * Obtiene la foto del contexto global reusando el dashboard.
     * Lee la caché (ia_chat_contexto_cache); si está vencida o no existe, recalcula
     * con DashboardGerencial y la guarda. El TTL sale de ia_configuracion
     * (contexto_cache_ttl_min); si la clave no existe o es <= 0, no se cachea.
     */
    private static function obtenerFotoContexto($db, $config)
    {
        $fecha = date('Y-m-d');
        $ttlMin = (isset($config['contexto_cache_ttl_min']) && (int) $config['contexto_cache_ttl_min'] > 0)
            ? (int) $config['contexto_cache_ttl_min']
            : 0;

        // 1. Foto vigente en caché (misma fecha)
        $cacheJson = IaChatContextoCache::obtenerVigente($db, $ttlMin);
        if ($cacheJson) {
            $decoded = json_decode($cacheJson, true);
            if (is_array($decoded) && isset($decoded['fecha']) && $decoded['fecha'] === $fecha) {
                return $decoded;
            }
        }

        // 2. Recalcular desde el dashboard (fuente única). Si algo falla,
        //    se devuelve la foto sin bloques para que caiga a texto genérico.
        try {
            $foto = [
                'fecha' => $fecha,
                'operativo' => DashboardGerencial::resumenOperativoContexto($db, $fecha),
                'financiero' => DashboardGerencial::resumenFinancieroContexto($db, $fecha)
            ];
        } catch (Exception $e) {
            error_log("Error calculando foto de contexto IA: " . $e->getMessage());
            return ['fecha' => $fecha];
        }

        if ($ttlMin > 0) {
            IaChatContextoCache::guardar($db, json_encode($foto));
        }

        return $foto;
    }

    /**
     * Formatea un valor numérico como pesos colombianos para el contexto.
     */
    private static function pesos($valor)
    {
        return '$' . number_format((float) $valor, 0, ',', '.');
    }

    /**
     * Construye el bloque de texto operativo a partir del resumen del dashboard.
     */
    private static function textoContextoOperativo($op)
    {
        $a = isset($op['asistencia']) ? $op['asistencia'] : [];
        $c = isset($op['colaboradores']) ? $op['colaboradores'] : [];
        $al = isset($op['alimentacion']) ? $op['alimentacion'] : [];
        $fecha = isset($op['fecha']) ? $op['fecha'] : date('Y-m-d');

        $texto = "\nDATOS OPERATIVOS DEL JARDÍN (fecha {$fecha}):\n";

        $texto .= "Asistencia de estudiantes:\n";
        $texto .= "- Estudiantes activos: " . (int) ($a['total_activos'] ?? 0) . "\n";
        $texto .= "- Asistieron hoy: " . (int) ($a['total_asistieron'] ?? 0) . " (" . ($a['porcentaje'] ?? 0) . "%)\n";
        if (!empty($a['es_hoy'])) {
            $texto .= "- Presentes en este momento: " . (int) ($a['total_presentes_ahora'] ?? 0) . "\n";
            $texto .= "- Ya salieron: " . (int) ($a['total_salieron'] ?? 0) . "\n";
        }
        if (!empty($a['por_grupo'])) {
            $texto .= "Asistencia por grupo:\n";
            foreach ($a['por_grupo'] as $g) {
                $texto .= "  - " . $g['nombre_grupo'] . ": " . (int) $g['asistieron'] . "/" . (int) $g['total'] . " (" . $g['porcentaje'] . "%)\n";
            }
        }

        $texto .= "Colaboradores:\n";
        $texto .= "- Activos (validan jornada): " . (int) ($c['total_activos'] ?? 0) . "\n";
        if (!empty($c['es_hoy'])) {
            $texto .= "- Presentes en este momento: " . (int) ($c['presentes'] ?? 0) . "\n";
        }
        $texto .= "- Ingresaron: " . (int) ($c['ingresaron'] ?? 0) . " | Salieron: " . (int) ($c['salieron'] ?? 0) . " | En descanso: " . (int) ($c['en_descanso'] ?? 0) . " | Entradas tarde: " . (int) ($c['tarde'] ?? 0) . "\n";

        $texto .= "Alimentación:\n";
        $texto .= "- Servicios mensuales servidos: " . (int) ($al['mensuales_servidos'] ?? 0) . "/" . (int) ($al['mensuales_contratados'] ?? 0) . " (" . ($al['mensuales_porcentaje'] ?? 0) . "%)\n";
        $texto .= "- Servicios diarios servidos: " . (int) ($al['diarios_servidos'] ?? 0) . "\n";

        return $texto;
    }

    /**
     * Construye el bloque de texto financiero a partir del resumen del dashboard.
     */
    private static function textoContextoFinanciero($fin)
    {
        $car = isset($fin['cartera']) ? $fin['cartera'] : [];
        $rec = isset($fin['recaudo']) ? $fin['recaudo'] : [];
        $fecha = isset($fin['fecha']) ? $fin['fecha'] : date('Y-m-d');

        $texto = "\nDATOS FINANCIEROS DEL JARDÍN (fecha {$fecha}):\n";

        $texto .= "Cartera:\n";
        $texto .= "- Total facturado: " . self::pesos($car['total_facturado'] ?? 0) . "\n";
        $texto .= "- Total recaudado: " . self::pesos($car['total_recaudado'] ?? 0) . "\n";
        $texto .= "- Saldo pendiente: " . self::pesos($car['saldo_pendiente'] ?? 0) . "\n";
        $texto .= "- Saldo vencido: " . self::pesos($car['saldo_vencido'] ?? 0) . " (" . ($car['porcentaje_vencido'] ?? 0) . "% del pendiente)\n";

        $recHoy = isset($rec['recaudado_hoy']) ? $rec['recaudado_hoy'] : [];
        $recMes = isset($rec['recaudado_mes']) ? $rec['recaudado_mes'] : [];
        $recAnio = isset($rec['recaudado_anio']) ? $rec['recaudado_anio'] : [];
        $texto .= "Recaudo:\n";
        $texto .= "- Recaudado hoy: " . self::pesos($recHoy['total'] ?? 0) . " (" . (int) ($recHoy['cantidad'] ?? 0) . " pagos)\n";
        $texto .= "- Recaudado en el mes: " . self::pesos($recMes['total'] ?? 0) . " (" . (int) ($recMes['cantidad'] ?? 0) . " pagos)\n";
        $texto .= "- Recaudado en el año: " . self::pesos($recAnio['total'] ?? 0) . "\n";

        return $texto;
    }

    // =====================================================
    // CONTEXTOS SIN FUENTE DE DATOS AÚN
    // Devuelven texto genérico para no inventar información.
    // (Se conservan los métodos; reemplazar por datos reales cuando exista fuente.)
    // =====================================================

    private static function contextoDummyEstPersonal($ids_estudiantes)
    {
        return "\nDATOS PERSONALES DE ESTUDIANTES: sección sin datos disponibles por el momento.\n";
    }

    private static function contextoDummyEstAcademico($ids_estudiantes)
    {
        return "\nDATOS ACADÉMICOS DE ESTUDIANTES: sección sin datos disponibles por el momento.\n";
    }

    private static function contextoDummyEstFinanciero($ids_estudiantes)
    {
        return "\nDATOS FINANCIEROS DE ESTUDIANTES: sección sin datos disponibles por el momento.\n";
    }

    private static function contextoDummyGrupoPersonal($ids_estudiantes)
    {
        return "\nDATOS PERSONALES DEL GRUPO: sección sin datos disponibles por el momento.\n";
    }

    private static function contextoDummyGrupoAcademico($ids_estudiantes)
    {
        return "\nDATOS ACADÉMICOS DEL GRUPO: sección sin datos disponibles por el momento.\n";
    }

    private static function contextoDummyGrupoFinanciero($ids_estudiantes)
    {
        return "\nDATOS FINANCIEROS DEL GRUPO: sección sin datos disponibles por el momento.\n";
    }

    private static function contextoDummyGlobalOperativo()
    {
        return "\nDATOS OPERATIVOS DEL JARDÍN: sección sin datos disponibles por el momento.\n";
    }

    private static function contextoDummyGlobalAcademico()
    {
        return "\nDATOS ACADÉMICOS GLOBALES DEL JARDÍN: sección sin datos disponibles por el momento.\n";
    }

    private static function contextoDummyGlobalFinanciero()
    {
        return "\nDATOS FINANCIEROS GLOBALES DEL JARDÍN: sección sin datos disponibles por el momento.\n";
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
        $sentence = $db->prepare("SELECT clave, valor FROM ia_configuracion WHERE id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $rows = $sentence->fetchAll(PDO::FETCH_ASSOC);

        $config = [];
        foreach ($rows as $row) {
            $config[$row['clave']] = $row['valor'];
        }

        return $config;
    }
}