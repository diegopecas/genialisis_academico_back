<?php
class Personas
{
    /**
     * Permiso que habilita corregir el tipo o el numero de documento de una
     * persona ya creada. Sin el, replace() rechaza cualquier cambio de
     * documento aunque el front lo mande.
     */
    const PERMISO_EDITAR_DOCUMENTO = 'personas.editar_documento';

    /**
     * Normaliza un campo de texto antes de guardarlo:
     * quita espacios sobrantes y convierte la cadena vacia en NULL.
     * Se usa en los nombres y apellidos para que la concatenacion del nombre
     * completo no produzca espacios dobles ni valores basura.
     */
    private static function normalizarTexto($valor)
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT 
            p.*, 
            ti.nombre AS tipo_identificacion,
            g.nombre AS nombre_genero,
            c.nombre AS nombre_ciudad,
            EXISTS(SELECT 1 FROM colaboradores co WHERE co.id_persona = p.id AND co.id_tenant = p.id_tenant) AS es_colaborador,
            EXISTS(SELECT 1 FROM estudiantes es  WHERE es.id_persona = p.id AND es.id_tenant = p.id_tenant) AS es_estudiante,
            EXISTS(SELECT 1 FROM acudientes ac   WHERE ac.id_persona = p.id AND ac.id_tenant = p.id_tenant) AS es_acudiente,
            EXISTS(SELECT 1 FROM usuarios us    WHERE us.id_persona = p.id AND us.id_tenant = p.id_tenant) AS tiene_usuario
        FROM personas p
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        LEFT JOIN ciudades c ON p.id_ciudad = c.id
        WHERE p.id_tenant = :id_tenant
        ORDER BY p.id DESC");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            error_log("getAll: Se encontraron " . count($response) . " registros de personas");

            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en getAll: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener las personas'), 500);
        }
    }

    /**
     * Lista plana de personas para el buscador del menu principal.
     *
     * Devuelve una fila por DESTINO, no por persona: una misma persona puede
     * ser colaboradora y ademas acudiente de dos ninos, y en ese caso salen
     * tres filas con el mismo id_persona. El front las agrupa y le muestra la
     * lista al usuario para que escoja a donde ir.
     *
     * El `id_destino` es el id del registro al que se navega (estudiante,
     * colaborador o acudiente). El `id_secundario` solo lo usa el acudiente y
     * trae el id del estudiante, porque la pantalla de editar acudiente pide
     * los dos en la ruta.
     *
     * Solo se traen los campos que el buscador necesita (nombre, documento,
     * tipo, id del destino, estado y un detalle corto). Nada de foto,
     * direccion ni telefono, porque esta consulta se carga completa al abrir
     * la aplicacion y el peso del JSON si importa.
     *
     * Los inactivos NO se filtran: vienen con activo = 0 para que el front los
     * pinte en gris y los ordene de ultimos.
     *
     * Tampoco valida permisos. El filtrado por permiso se hace en el front,
     * igual que en el resto del sistema.
     */
    public static function getBuscador()
    {
        try {
            $db = Flight::db();

            // El id_tenant va con tres nombres distintos porque PDO sin
            // emulacion de prepares no permite repetir un parametro nombrado.
            $sentence = $db->prepare("
            SELECT
                p.id AS id_persona,
                TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                p.numero_identificacion,
                'estudiante' AS tipo,
                e.id AS id_destino,
                NULL AS id_secundario,
                e.activo AS activo,
                NULL AS detalle
            FROM estudiantes e
            INNER JOIN personas p ON p.id = e.id_persona AND p.id_tenant = e.id_tenant
            WHERE e.id_tenant = :id_tenant_est

            UNION ALL

            SELECT
                p.id AS id_persona,
                TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                p.numero_identificacion,
                'colaborador' AS tipo,
                c.id AS id_destino,
                NULL AS id_secundario,
                c.activo AS activo,
                car.nombre AS detalle
            FROM colaboradores c
            INNER JOIN personas p ON p.id = c.id_persona AND p.id_tenant = c.id_tenant
            LEFT JOIN cargos car ON car.id = c.id_cargo AND car.id_tenant = c.id_tenant
            WHERE c.id_tenant = :id_tenant_col

            UNION ALL

            SELECT
                p.id AS id_persona,
                TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                p.numero_identificacion,
                'acudiente' AS tipo,
                a.id AS id_destino,
                a.id_estudiante AS id_secundario,
                CASE WHEN a.activo = 1 AND e.activo = 1 THEN 1 ELSE 0 END AS activo,
                CONCAT(COALESCE(ta.nombre, 'Acudiente'), ' de ', TRIM(CONCAT_WS(' ', pe.primer_nombre, pe.primer_apellido))) AS detalle
            FROM acudientes a
            INNER JOIN personas p ON p.id = a.id_persona AND p.id_tenant = a.id_tenant
            INNER JOIN estudiantes e ON e.id = a.id_estudiante AND e.id_tenant = a.id_tenant
            INNER JOIN personas pe ON pe.id = e.id_persona AND pe.id_tenant = e.id_tenant
            LEFT JOIN tipos_acudiente ta ON ta.id = a.id_tipo_acudiente
            WHERE a.id_tenant = :id_tenant_acu

            ORDER BY nombre_completo, tipo");

            $idTenant = TenantContext::id();
            $sentence->bindValue(':id_tenant_est', $idTenant, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant_col', $idTenant, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant_acu', $idTenant, PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            // PDO devuelve el activo como cadena y en JavaScript la cadena "0"
            // es verdadera, asi que se entrega como entero para que el front
            // pueda evaluarlo directo.
            foreach ($response as $indice => $fila) {
                $response[$indice]['activo'] = (int) $fila['activo'];
            }

            error_log("getBuscador: Se devolvieron " . count($response) . " destinos de personas");

            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en getBuscador: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener las personas del buscador'), 500);
        }
    }

    public static function getById($id)
    {
        try {
            error_log("getById: Buscando persona con ID: $id");

            $db = Flight::db();
            $sentence = $db->prepare("SELECT 
            p.*, 
            ti.nombre AS tipo_identificacion,
            g.nombre AS nombre_genero,
            c.nombre AS nombre_ciudad
        FROM personas p
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        LEFT JOIN ciudades c ON p.id_ciudad = c.id
        WHERE p.id = :id AND p.id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            if (empty($response)) {
                error_log("getById: No se encontró persona con ID: $id");
                Flight::json(array('error' => 'No se encontró la persona con el ID especificado'), 404);
                return;
            }

            error_log("getById: Persona encontrada con ID: $id");
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en getById: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener la persona'), 500);
        }
    }

    /**
     * Persona que ya tiene ese numero de documento en el tenant, o null.
     *
     * Se compara solo el numero porque asi esta el indice unico. Devuelve la
     * fila para poder decirle al usuario con que tipo quedo registrada.
     *
     * @param  PDO    $db
     * @param  string $numero_identificacion
     * @param  string $id_excluir Id que no cuenta, para el caso de editar
     * @return array|null
     */
    private static function buscarPorNumero(PDO $db, $numero_identificacion, $id_excluir = null)
    {
        if (empty($numero_identificacion)) {
            return null;
        }

        $sql = "SELECT p.id,
                       p.numero_identificacion,
                       TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS nombre,
                       ti.nombre AS tipo_identificacion
                FROM personas p
                LEFT JOIN tipos_identificacion ti ON ti.id = p.id_tipo_identificacion
                WHERE p.numero_identificacion = :numero_identificacion
                  AND p.id_tenant = :id_tenant";

        if (!empty($id_excluir)) {
            $sql .= " AND p.id <> :id_excluir";
        }

        $sentence = $db->prepare($sql . " LIMIT 1");
        $sentence->bindParam(':numero_identificacion', $numero_identificacion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

        if (!empty($id_excluir)) {
            $sentence->bindParam(':id_excluir', $id_excluir);
        }

        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila : null;
    }

    /**
     * Mensaje de duplicado, diciendo con que tipo quedo registrada la
     * persona. Sin eso el usuario no entiende por que no lo deja guardar.
     */
    private static function mensajeDuplicado($existente)
    {
        $mensaje = 'Ya existe una persona con el documento ' . $existente['numero_identificacion'];

        if (!empty($existente['tipo_identificacion'])) {
            $mensaje .= ', registrada como ' . $existente['tipo_identificacion'];
        }

        if (!empty($existente['nombre'])) {
            $mensaje .= ' (' . $existente['nombre'] . ')';
        }

        return $mensaje . '. Busque el documento para cargar sus datos.';
    }

    /**
     * Mensaje de duplicado cuando se esta corrigiendo el documento de una
     * persona que ya existe. Aqui no aplica "busque el documento": lo que
     * pasa es que el numero nuevo ya es de otra persona.
     */
    private static function mensajeDuplicadoCorreccion($existente)
    {
        $mensaje = 'No se puede corregir el documento: el número ' . $existente['numero_identificacion']
            . ' ya pertenece a otra persona';

        if (!empty($existente['nombre'])) {
            $mensaje .= ' (' . $existente['nombre'] . ')';
        }

        if (!empty($existente['tipo_identificacion'])) {
            $mensaje .= ', registrada como ' . $existente['tipo_identificacion'];
        }

        return $mensaje . '.';
    }

    /**
     * Documento guardado hoy de la persona, con el nombre del tipo para el
     * historial. Devuelve null si la persona no existe en el tenant.
     */
    private static function obtenerDocumentoActual(PDO $db, $id)
    {
        $sentence = $db->prepare("SELECT p.id_tipo_identificacion,
                                         p.numero_identificacion,
                                         ti.nombre AS tipo_identificacion
                                  FROM personas p
                                  LEFT JOIN tipos_identificacion ti ON ti.id = p.id_tipo_identificacion
                                  WHERE p.id = :id AND p.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila : null;
    }

    /**
     * Nombre del tipo de identificacion. tipos_identificacion es global (no
     * tiene id_tenant), por eso no se filtra por tenant.
     */
    private static function nombreTipoIdentificacion(PDO $db, $id_tipo_identificacion)
    {
        $sentence = $db->prepare("SELECT nombre FROM tipos_identificacion WHERE id = :id");
        $sentence->bindParam(':id', $id_tipo_identificacion);
        $sentence->execute();
        $nombre = $sentence->fetchColumn();

        return $nombre !== false ? $nombre : (string) $id_tipo_identificacion;
    }

    /**
     * Usuarios de la persona cuyo nombre de usuario es el numero de documento
     * anterior. Solo esos se renombran al corregir el documento: si alguien
     * escogio otro nombre de usuario, se respeta.
     */
    private static function usuariosConDocumentoAnterior(PDO $db, $id_persona, $numero_anterior)
    {
        $sentence = $db->prepare("SELECT id, usuario
                                  FROM usuarios
                                  WHERE id_persona = :id_persona
                                    AND usuario = :usuario
                                    AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindParam(':usuario', $numero_anterior);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        return $sentence->fetchAll();
    }

    /**
     * Usuario de OTRA persona que ya tiene ese nombre de usuario en el tenant,
     * o null. Con el nombre de la persona para el mensaje.
     */
    private static function usuarioOcupado(PDO $db, $usuario, $id_usuario_excluir)
    {
        $sentence = $db->prepare("SELECT u.id,
                                         TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS nombre
                                  FROM usuarios u
                                  LEFT JOIN personas p ON p.id = u.id_persona AND p.id_tenant = u.id_tenant
                                  WHERE u.usuario = :usuario
                                    AND u.id <> :id_excluir
                                    AND u.id_tenant = :id_tenant
                                  LIMIT 1");
        $sentence->bindParam(':usuario', $usuario);
        $sentence->bindParam(':id_excluir', $id_usuario_excluir);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila : null;
    }

    /**
     * Deja constancia del cambio en historial_cambios_persona, la misma tabla
     * que usa el portal de padres cuando el acudiente actualiza sus datos.
     */
    private static function registrarHistorial(PDO $db, $id_persona, $id_usuario, $campo, $valor_anterior, $valor_nuevo)
    {
        $sentence = $db->prepare("INSERT INTO historial_cambios_persona
            (id, id_tenant, id_persona, id_usuario, campo_modificado, valor_anterior, valor_nuevo, ip_address)
            VALUES (:id, :id_tenant, :id_persona, :id_usuario, :campo_modificado, :valor_anterior, :valor_nuevo, :ip_address)");

        $sentence->bindValue(':id', Uuid::generar());
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_persona', $id_persona);
        $sentence->bindValue(':id_usuario', $id_usuario);
        $sentence->bindValue(':campo_modificado', $campo);
        $sentence->bindValue(':valor_anterior', $valor_anterior);
        $sentence->bindValue(':valor_nuevo', $valor_nuevo);
        $sentence->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
        $sentence->execute();
    }

    /**
     * Renombra el usuario en la BD maestra (usuarios_tenants, que es la que
     * resuelve el tenant en el pre-login, y el indice de credenciales
     * biometricas). Solo toca las filas del tenant actual: la misma persona
     * puede tener usuario en otro jardin con el documento viejo.
     *
     * Lanza excepcion si algo falla, para que replace() revierta todo.
     */
    private static function renombrarUsuarioEnMaster($usuario_anterior, $usuario_nuevo)
    {
        $dbMaster = Flight::db_master();
        $codigo = TenantContext::codigo();

        $stmtTenant = $dbMaster->prepare("SELECT id FROM tenants WHERE codigo = :codigo");
        $stmtTenant->bindParam(':codigo', $codigo);
        $stmtTenant->execute();
        $idTenantMaster = $stmtTenant->fetchColumn();

        if ($idTenantMaster === false) {
            throw new Exception("Tenant no encontrado en master: {$codigo}");
        }

        // Si la fila del usuario nuevo ya existe (quedo de antes), basta con
        // quitar la del anterior; el indice unico (usuario, id_tenant) no deja
        // renombrar encima de ella.
        $stmtExiste = $dbMaster->prepare("SELECT id FROM usuarios_tenants WHERE usuario = :usuario AND id_tenant = :id_tenant");
        $stmtExiste->bindParam(':usuario', $usuario_nuevo);
        $stmtExiste->bindParam(':id_tenant', $idTenantMaster);
        $stmtExiste->execute();

        if ($stmtExiste->fetch()) {
            $stmt = $dbMaster->prepare("DELETE FROM usuarios_tenants WHERE usuario = :anterior AND id_tenant = :id_tenant");
        } else {
            $stmt = $dbMaster->prepare("UPDATE usuarios_tenants SET usuario = :nuevo WHERE usuario = :anterior AND id_tenant = :id_tenant");
            $stmt->bindParam(':nuevo', $usuario_nuevo);
        }
        $stmt->bindParam(':anterior', $usuario_anterior);
        $stmt->bindParam(':id_tenant', $idTenantMaster);
        $stmt->execute();

        $stmtWebauthn = $dbMaster->prepare("UPDATE webauthn_credentials_master SET usuario = :nuevo WHERE usuario = :anterior AND tenant_codigo = :codigo");
        $stmtWebauthn->bindParam(':nuevo', $usuario_nuevo);
        $stmtWebauthn->bindParam(':anterior', $usuario_anterior);
        $stmtWebauthn->bindParam(':codigo', $codigo);
        $stmtWebauthn->execute();
    }

    /**
     * Busca una persona por su documento.
     *
     * La busqueda va SOLO por numero, aunque el tipo se siga recibiendo: el
     * indice unico personas_unique es (id_tenant, numero_identificacion), sin
     * el tipo, asi que en un tenant no puede haber dos personas con el mismo
     * numero.
     *
     * Antes se filtraba por tipo Y numero, y eso hacia que una persona
     * registrada como NUIP no apareciera al buscarla como Cedula: el usuario
     * la daba por nueva, llenaba los datos y el INSERT reventaba contra el
     * indice con un error de base.
     *
     * El parametro del tipo se conserva para no cambiar la firma ni la ruta,
     * que consumen ocho pantallas. Quien llama recibe la persona con su tipo
     * real y puede corregir lo que tenga en pantalla.
     */
    public static function getByIdentificacion($id_tipo_identificacion, $numero_identificacion)
    {
        try {
            error_log("getByIdentificacion: Buscando persona con tipo ID: $id_tipo_identificacion y número: $numero_identificacion");

            $db = Flight::db();
            $sentence = $db->prepare("SELECT 
            p.*, 
            ti.nombre AS tipo_identificacion,
            g.nombre AS nombre_genero,
            c.nombre AS nombre_ciudad
        FROM personas p
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        LEFT JOIN ciudades c ON p.id_ciudad = c.id
        WHERE p.numero_identificacion = :numero_identificacion
        AND p.id_tenant = :id_tenant");

            $sentence->bindParam(':numero_identificacion', $numero_identificacion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            if (empty($response)) {
                error_log("getByIdentificacion: No se encontró persona con tipo ID: $id_tipo_identificacion y número: $numero_identificacion");
                Flight::json(array());
                return;
            }

            error_log("getByIdentificacion: Persona encontrada con tipo ID: $id_tipo_identificacion y número: $numero_identificacion");
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en getByIdentificacion: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al buscar la persona por identificación'), 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            // Obtener datos de la solicitud
            $primer_nombre = self::normalizarTexto(isset(Flight::request()->data['primer_nombre']) ? Flight::request()->data['primer_nombre'] : null);
            $segundo_nombre = self::normalizarTexto(isset(Flight::request()->data['segundo_nombre']) ? Flight::request()->data['segundo_nombre'] : null);
            $primer_apellido = self::normalizarTexto(isset(Flight::request()->data['primer_apellido']) ? Flight::request()->data['primer_apellido'] : null);
            $segundo_apellido = self::normalizarTexto(isset(Flight::request()->data['segundo_apellido']) ? Flight::request()->data['segundo_apellido'] : null);
            $id_tipo_identificacion = Flight::request()->data['id_tipo_identificacion'];
            $numero_identificacion = Flight::request()->data['numero_identificacion'];
            $nacionalidad = isset(Flight::request()->data['nacionalidad']) ? Flight::request()->data['nacionalidad'] : null;
            $fecha_nacimiento = isset(Flight::request()->data['fecha_nacimiento']) ? Flight::request()->data['fecha_nacimiento'] : null;
            $id_genero = isset(Flight::request()->data['id_genero']) ? Flight::request()->data['id_genero'] : null;
            $direccion = isset(Flight::request()->data['direccion']) ? Flight::request()->data['direccion'] : null;
            $id_ciudad = isset(Flight::request()->data['id_ciudad']) ? Flight::request()->data['id_ciudad'] : null;
            $correo_electronico = isset(Flight::request()->data['correo_electronico']) ? Flight::request()->data['correo_electronico'] : null;
            $telefono = isset(Flight::request()->data['telefono']) ? Flight::request()->data['telefono'] : null;
            $ocupacion = isset(Flight::request()->data['ocupacion']) ? Flight::request()->data['ocupacion'] : null;
            $rh = isset(Flight::request()->data['rh']) ? Flight::request()->data['rh'] : null;
            $razon_social = isset(Flight::request()->data['razon_social']) ? Flight::request()->data['razon_social'] : null;

            error_log("Datos recibidos para crear: razon_social=$razon_social, primer_nombre=$primer_nombre, primer_apellido=$primer_apellido, numero_identificacion=$numero_identificacion");

            $idTenant = TenantContext::id();
            $id = Uuid::generar();

            // El indice unico personas_unique ya lo impide, pero reventaria
            // con un error de base. Aqui sale un mensaje que se entiende.
            $existente = self::buscarPorNumero($db, $numero_identificacion);

            if ($existente) {
                Flight::json(array('error' => self::mensajeDuplicado($existente)), 400);
                return;
            }

            // Preparar la sentencia SQL
            $sentence = $db->prepare("INSERT INTO personas (
                id,
                id_tenant,
                primer_nombre, 
                segundo_nombre, 
                primer_apellido, 
                segundo_apellido, 
                id_tipo_identificacion, 
                numero_identificacion,
                nacionalidad,
                fecha_nacimiento, 
                id_genero, 
                direccion,
                id_ciudad,
                correo_electronico,
                telefono,
                ocupacion,
                rh,
                razon_social
            ) VALUES (
                :id,
                :id_tenant,
                :primer_nombre, 
                :segundo_nombre, 
                :primer_apellido, 
                :segundo_apellido, 
                :id_tipo_identificacion, 
                :numero_identificacion,
                :nacionalidad,
                :fecha_nacimiento, 
                :id_genero, 
                :direccion,
                :id_ciudad,
                :correo_electronico,
                :telefono,
                :ocupacion,
                :rh,
                :razon_social
            )");

            // Vincular los parámetros
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $sentence->bindParam(':primer_nombre', $primer_nombre);
            $sentence->bindParam(':segundo_nombre', $segundo_nombre);
            $sentence->bindParam(':primer_apellido', $primer_apellido);
            $sentence->bindParam(':segundo_apellido', $segundo_apellido);
            $sentence->bindParam(':id_tipo_identificacion', $id_tipo_identificacion);
            $sentence->bindParam(':numero_identificacion', $numero_identificacion);
            $sentence->bindParam(':nacionalidad', $nacionalidad);
            $sentence->bindParam(':fecha_nacimiento', $fecha_nacimiento);
            $sentence->bindParam(':id_genero', $id_genero);
            $sentence->bindParam(':direccion', $direccion);
            $sentence->bindParam(':id_ciudad', $id_ciudad);
            $sentence->bindParam(':correo_electronico', $correo_electronico);
            $sentence->bindParam(':telefono', $telefono);
            $sentence->bindParam(':ocupacion', $ocupacion);
            $sentence->bindParam(':rh', $rh);
            $sentence->bindParam(':razon_social', $razon_social);

            // Ejecutar la sentencia
            $ok = $sentence->execute();

            if (!$ok) {
                error_log("Error: el INSERT de persona no se ejecutó correctamente.");
                Flight::json(array('error' => 'No se pudo crear la persona. Intente de nuevo.'), 500);
                return;
            }

            error_log("ID insertado: $id");

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Actualiza la persona.
     *
     * El documento (tipo y numero) solo se puede cambiar con el permiso
     * personas.editar_documento. Cuando cambia:
     * - no se deja poner un numero que ya tiene otra persona del tenant;
     * - los usuarios de la persona cuyo nombre de usuario era el numero
     *   anterior pasan al numero nuevo, en el tenant y en la BD maestra;
     * - queda el antes y el despues en historial_cambios_persona.
     * Todo va en una transaccion: si algo falla no queda nada a medias.
     */
    public static function replace()
    {
        $db = Flight::db();
        $masterRenombrado = [];

        try {
            $id = Flight::request()->data['id'];
            $primer_nombre = self::normalizarTexto(isset(Flight::request()->data['primer_nombre']) ? Flight::request()->data['primer_nombre'] : null);
            $segundo_nombre = self::normalizarTexto(isset(Flight::request()->data['segundo_nombre']) ? Flight::request()->data['segundo_nombre'] : null);
            $primer_apellido = self::normalizarTexto(isset(Flight::request()->data['primer_apellido']) ? Flight::request()->data['primer_apellido'] : null);
            $segundo_apellido = self::normalizarTexto(isset(Flight::request()->data['segundo_apellido']) ? Flight::request()->data['segundo_apellido'] : null);
            $id_tipo_identificacion = Flight::request()->data['id_tipo_identificacion'];
            $numero_identificacion = Flight::request()->data['numero_identificacion'];
            $nacionalidad = isset(Flight::request()->data['nacionalidad']) ? Flight::request()->data['nacionalidad'] : null;
            $fecha_nacimiento = isset(Flight::request()->data['fecha_nacimiento']) ? Flight::request()->data['fecha_nacimiento'] : null;
            $id_genero = isset(Flight::request()->data['id_genero']) ? Flight::request()->data['id_genero'] : null;
            $direccion = isset(Flight::request()->data['direccion']) ? Flight::request()->data['direccion'] : null;
            $id_ciudad = isset(Flight::request()->data['id_ciudad']) ? Flight::request()->data['id_ciudad'] : null;
            $correo_electronico = isset(Flight::request()->data['correo_electronico']) ? Flight::request()->data['correo_electronico'] : null;
            $telefono = isset(Flight::request()->data['telefono']) ? Flight::request()->data['telefono'] : null;
            $ocupacion = isset(Flight::request()->data['ocupacion']) ? Flight::request()->data['ocupacion'] : null;
            $rh = isset(Flight::request()->data['rh']) ? Flight::request()->data['rh'] : null;
            $razon_social = isset(Flight::request()->data['razon_social']) ? Flight::request()->data['razon_social'] : null;

            error_log("Datos recibidos para actualización: id=$id, razon_social=$razon_social, primer_nombre=$primer_nombre, numero_identificacion=$numero_identificacion");

            // Validar solo los datos mínimos necesarios
            if (!$id || !$id_tipo_identificacion || !$numero_identificacion) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            $actual = self::obtenerDocumentoActual($db, $id);

            if (!$actual) {
                Flight::json(array('error' => 'No se encontró la persona con el ID especificado'), 404);
                return;
            }

            // Se compara como texto y sin espacios: el front puede mandar el
            // tipo como numero o como cadena y eso no es un cambio.
            $numeroAnterior = trim((string) $actual['numero_identificacion']);
            $numeroNuevo = trim((string) $numero_identificacion);
            $cambiaNumero = $numeroAnterior !== $numeroNuevo;
            $cambiaTipo = (string) $actual['id_tipo_identificacion'] !== (string) $id_tipo_identificacion;
            $cambiaDocumento = $cambiaNumero || $cambiaTipo;

            $userData = null;
            if ($cambiaDocumento) {
                $userData = JWTService::requerirAutenticacion();

                // Se usa tiene() y no validar(): la validacion general de
                // permisos del back esta apagada y este control si debe cumplirse.
                if (!PermisosService::tiene($userData, self::PERMISO_EDITAR_DOCUMENTO)) {
                    Flight::json(array('error' => 'No tiene permiso para corregir el documento de identidad.'), 403);
                    return;
                }
            }

            // Se excluye la propia persona: editarla sin cambiarle el
            // documento no puede chocar consigo misma.
            $existente = self::buscarPorNumero($db, $numero_identificacion, $id);

            if ($existente) {
                $mensaje = $cambiaDocumento ? self::mensajeDuplicadoCorreccion($existente) : self::mensajeDuplicado($existente);
                Flight::json(array('error' => $mensaje), 400);
                return;
            }

            // Usuarios que pasan al numero nuevo. Se valida antes de tocar
            // nada que el numero nuevo no sea ya el usuario de otra persona.
            $usuariosRenombrar = $cambiaNumero ? self::usuariosConDocumentoAnterior($db, $id, $numeroAnterior) : [];

            foreach ($usuariosRenombrar as $usuarioRenombrar) {
                $ocupado = self::usuarioOcupado($db, $numeroNuevo, $usuarioRenombrar['id']);
                if ($ocupado) {
                    $quien = !empty($ocupado['nombre']) ? ' (' . $ocupado['nombre'] . ')' : '';
                    Flight::json(array('error' => 'No se puede corregir el documento: el número ' . $numeroNuevo
                        . ' ya es el usuario de ingreso de otra persona' . $quien . '.'), 400);
                    return;
                }
            }

            $db->beginTransaction();

            // Preparar la sentencia SQL
            $sentence = $db->prepare("UPDATE personas SET 
                primer_nombre = :primer_nombre,
                segundo_nombre = :segundo_nombre,
                primer_apellido = :primer_apellido,
                segundo_apellido = :segundo_apellido,
                id_tipo_identificacion = :id_tipo_identificacion,
                numero_identificacion = :numero_identificacion,
                nacionalidad = :nacionalidad,
                fecha_nacimiento = :fecha_nacimiento,
                id_genero = :id_genero,
                direccion = :direccion,
                id_ciudad = :id_ciudad,
                correo_electronico = :correo_electronico,
                telefono = :telefono,
                ocupacion = :ocupacion,
                rh = :rh,
                razon_social = :razon_social
            WHERE id = :id AND id_tenant = :id_tenant");

            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':primer_nombre', $primer_nombre);
            $sentence->bindParam(':segundo_nombre', $segundo_nombre);
            $sentence->bindParam(':primer_apellido', $primer_apellido);
            $sentence->bindParam(':segundo_apellido', $segundo_apellido);
            $sentence->bindParam(':id_tipo_identificacion', $id_tipo_identificacion);
            $sentence->bindParam(':numero_identificacion', $numero_identificacion);
            $sentence->bindParam(':nacionalidad', $nacionalidad);
            $sentence->bindParam(':fecha_nacimiento', $fecha_nacimiento);
            $sentence->bindParam(':id_genero', $id_genero);
            $sentence->bindParam(':direccion', $direccion);
            $sentence->bindParam(':id_ciudad', $id_ciudad);
            $sentence->bindParam(':correo_electronico', $correo_electronico);
            $sentence->bindParam(':telefono', $telefono);
            $sentence->bindParam(':ocupacion', $ocupacion);
            $sentence->bindParam(':rh', $rh);
            $sentence->bindParam(':razon_social', $razon_social);

            // Ejecutar la sentencia
            $sentence->execute();

            if ($cambiaDocumento) {
                $idUsuario = $userData->id ?? null;

                foreach ($usuariosRenombrar as $usuarioRenombrar) {
                    $stmtUsuario = $db->prepare("UPDATE usuarios SET usuario = :usuario WHERE id = :id AND id_tenant = :id_tenant");
                    $stmtUsuario->bindParam(':usuario', $numeroNuevo);
                    $stmtUsuario->bindParam(':id', $usuarioRenombrar['id']);
                    $stmtUsuario->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtUsuario->execute();
                }

                if ($idUsuario) {
                    if ($cambiaTipo) {
                        self::registrarHistorial($db, $id, $idUsuario, 'Tipo de Identificación',
                            $actual['tipo_identificacion'], self::nombreTipoIdentificacion($db, $id_tipo_identificacion));
                    }
                    if ($cambiaNumero) {
                        self::registrarHistorial($db, $id, $idUsuario, 'Número de Identificación', $numeroAnterior, $numeroNuevo);
                    }
                    foreach ($usuariosRenombrar as $usuarioRenombrar) {
                        self::registrarHistorial($db, $id, $idUsuario, 'Usuario', $usuarioRenombrar['usuario'], $numeroNuevo);
                    }
                }

                // La maestra va al final, justo antes del commit, para que un
                // error del tenant no la deje cambiada.
                if (!empty($usuariosRenombrar)) {
                    self::renombrarUsuarioEnMaster($numeroAnterior, $numeroNuevo);
                    $masterRenombrado = [$numeroAnterior, $numeroNuevo];
                }

                error_log("Documento corregido: persona $id, $numeroAnterior -> $numeroNuevo, usuarios renombrados: " . count($usuariosRenombrar));
            }

            $db->commit();

            error_log("ID actualizado: $id");

            // Obtener y devolver los datos actualizados
            self::getById($id);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            // Si la maestra alcanzo a cambiar y el tenant no, se devuelve.
            if (!empty($masterRenombrado)) {
                try {
                    self::renombrarUsuarioEnMaster($masterRenombrado[1], $masterRenombrado[0]);
                } catch (Exception $eMaster) {
                    error_log("Error revirtiendo el usuario en master: " . $eMaster->getMessage());
                }
            }

            error_log("Error en la ejecución del método replace: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al actualizar la persona. Inténtalo más tarde.'), 500);
        }
    }

    /**
     * Actualiza solo el correo electrónico de la persona.
     * Existe para que las pantallas de usuarios puedan completar el correo
     * sin tener que reenviar toda la persona con replace().
     */
    public static function updateCorreo()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $correo_electronico = isset(Flight::request()->data['correo_electronico']) ? trim(Flight::request()->data['correo_electronico']) : '';

            if (!$id) {
                Flight::json(['error' => 'Falta el identificador de la persona'], 400);
                return;
            }

            $sentence = $db->prepare("UPDATE personas SET correo_electronico = :correo_electronico WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':correo_electronico', $correo_electronico);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(['id' => $id, 'message' => 'Correo actualizado correctamente']);
        } catch (Exception $e) {
            error_log("Error en updateCorreo: " . $e->getMessage());
            Flight::json(['error' => 'Ocurrió un error al actualizar el correo'], 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            error_log("Datos recibidos para eliminar persona: id=$id");

            if (!$id) {
                Flight::json(array('error' => 'Falta el ID de la persona a eliminar'), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM personas WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró la persona con el ID especificado'), 404);
                return;
            }

            Flight::json(array('id' => $id, 'message' => 'Persona eliminada correctamente'));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método delete: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al eliminar la persona. Inténtalo más tarde.'), 500);
        }
    }


    public static function uploadFoto($id)
    {
        try {
            $db = Flight::db();

            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                Flight::json(array('error' => 'No se recibió el archivo o hubo un error'), 400);
                return;
            }

            $archivo = $_FILES['foto'];
            $tamanio_bytes = $archivo['size'];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

            $extensiones_permitidas = ['jpg', 'jpeg', 'png'];
            if (!in_array($extension, $extensiones_permitidas)) {
                Flight::json(array('error' => 'Solo se permiten archivos JPG, JPEG o PNG'), 400);
                return;
            }

            if ($tamanio_bytes > 10 * 1024 * 1024) {
                Flight::json(array('error' => 'El archivo excede el tamaño máximo de 10MB'), 400);
                return;
            }

            $sentence = $db->prepare("SELECT foto FROM personas WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $persona = $sentence->fetch();

            if (!$persona) {
                Flight::json(array('error' => 'Persona no encontrada'), 404);
                return;
            }

            // Eliminar foto anterior si existe
            if ($persona['foto']) {
                UploadHelper::deleteFile($persona['foto']);
            }

            // Obtener directorio de uploads por tenant
            $directorio_base = UploadHelper::getUploadPath('fotos');
            UploadHelper::ensureDirectoryExists($directorio_base);

            // Eliminar cualquier foto anterior con este ID (independiente de la extensión)
            $patron = $directorio_base . $id . '.*';
            $archivos_anteriores = glob($patron);
            foreach ($archivos_anteriores as $archivo_anterior) {
                if (file_exists($archivo_anterior)) {
                    unlink($archivo_anterior);
                }
            }

            $nombre_archivo = $id . '.' . $extension;
            $ruta_completa = $directorio_base . $nombre_archivo;
            $ruta_relativa = UploadHelper::getRelativePath('fotos', $nombre_archivo);

            if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
                Flight::json(array('error' => 'Error al guardar el archivo'), 500);
                return;
            }

            $sentence = $db->prepare("UPDATE personas SET foto = :foto WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':foto', $ruta_relativa);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array(
                'id' => $id,
                'mensaje' => 'Foto subida exitosamente',
                'ruta_foto' => $ruta_relativa
            ));

        } catch (Exception $e) {
            error_log("Error en Personas::uploadFoto: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function deleteFoto($id)
    {
        try {
            $db = Flight::db();

            $sentence = $db->prepare("SELECT foto FROM personas WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $persona = $sentence->fetch();

            if (!$persona) {
                Flight::json(array('error' => 'Persona no encontrada'), 404);
                return;
            }

            if ($persona['foto']) {
                UploadHelper::deleteFile($persona['foto']);
            }

            $sentence = $db->prepare("UPDATE personas SET foto = NULL WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array(
                'id' => $id,
                'mensaje' => 'Foto eliminada exitosamente'
            ));

        } catch (Exception $e) {
            error_log("Error en Personas::deleteFoto: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getFoto($id)
    {
        try {
            $db = Flight::db();
            
            $sentence = $db->prepare("SELECT foto FROM personas WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $persona = $sentence->fetch();

            if (!$persona) {
                Flight::json(array('error' => 'Persona no encontrada'), 404);
                return;
            }

            if (!$persona['foto']) {
                Flight::json(array('foto' => null));
                return;
            }

            $ruta_completa = UploadHelper::getFullPath($persona['foto']);
            
            if (!file_exists($ruta_completa)) {
                Flight::json(array('foto' => null));
                return;
            }

            Flight::json(array('foto' => $persona['foto']));

        } catch (Exception $e) {
            error_log("Error en Personas::getFoto: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Obtiene todos los cumpleañeros del día de hoy
     * Incluye: estudiantes activos y colaboradores activos
     * Para colaboradores: devuelve género, sobrenombre, si es docente y nombre_corto del cargo
     */
    public static function getCumpleanosHoy()
    {
        try {
            $db = Flight::db();

            // Colaboradores activos que cumplen años hoy
            // NÚCLEO: la consulta de estudiantes y el JOIN a docentes pertenecen al dominio educativo;
            // se conservan los campos del contrato (tipo, es_docente, cargo_corto) para no romper el front.
            $stmtColaboradores = $db->prepare("
                SELECT 
                    p.id AS id_persona,
                    p.primer_nombre,
                    p.primer_apellido,
                    p.id_genero,
                    'colaborador' AS tipo,
                    col.sobrenombre,
                    0 AS es_docente,
                    ca.nombre_corto AS cargo_corto
                FROM personas p
                INNER JOIN colaboradores col ON col.id_persona = p.id AND col.activo = 1
                LEFT JOIN cargos ca ON col.id_cargo = ca.id
                WHERE DAY(p.fecha_nacimiento) = DAY(CURDATE())
                AND MONTH(p.fecha_nacimiento) = MONTH(CURDATE())
                AND p.id_tenant = :id_tenant
                ORDER BY p.primer_nombre ASC
            ");
            $stmtColaboradores->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtColaboradores->execute();
            $cumpleaneros = $stmtColaboradores->fetchAll();

            Flight::json($cumpleaneros);
        } catch (Exception $e) {
            error_log("Error en getCumpleanosHoy: " . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener cumpleañeros del día'), 500);
        }
    }
}