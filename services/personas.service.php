<?php
class Personas
{
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
                a.id_estudiante AS id_destino,
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

    public static function replace()
    {
        try {
            $db = Flight::db();

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

            // Se excluye la propia persona: editarla sin cambiarle el
            // documento no puede chocar consigo misma.
            $existente = self::buscarPorNumero($db, $numero_identificacion, $id);

            if ($existente) {
                Flight::json(array('error' => self::mensajeDuplicado($existente)), 400);
                return;
            }

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

            error_log("ID actualizado: $id");

            // Obtener y devolver los datos actualizados
            self::getById($id);
        } catch (Exception $e) {
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