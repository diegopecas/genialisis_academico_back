<?php
/**
 * Enlaces de autoregistro de acudientes.
 *
 * El jardin crea un enlace temporal con los estudiantes que quiere mostrar
 * (normalmente en una reunion de padres). El acudiente abre el enlace en el
 * portal de padres sin iniciar sesion, escoge a sus hijos, valida su
 * documento (a mano o con foto leida por IA), llena sus datos y queda creado
 * como persona, acudiente y usuario del portal.
 *
 * Aqui vive el CRUD del enlace y toda la logica de la pagina publica. Las
 * rutas publicas empiezan por /autoregistro-publico/ y no exigen token: se
 * autentican con el id del enlace (UUID, activo y sin vencer) y el id del
 * intento que crea la misma pagina.
 */
class EnlacesAutoregistroAcudientes
{
    const PERMISO = 'estudiantes.enlaces_autoregistro';

    // Resultados de cada estudiante al terminar el registro
    const VINCULO_CREADO = 'creado';
    const VINCULO_YA_EXISTIA = 'ya_existia';
    const VINCULO_OMITIDO = 'omitido';
    const VINCULO_PARENTESCO_OCUPADO = 'parentesco_ocupado';

    // Limites del archivo que llega de la pagina publica
    const MAX_BYTES_DOCUMENTO = 10485760; // 10 MB
    const MIN_LONGITUD_CLAVE = 6;

    // =================================================================
    // CRUD (portal institucional, con token)
    // =================================================================

    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);

        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT ea.id, ea.nombre, ea.fecha_vencimiento, ea.activo, ea.fecha_creacion,
                                             (ea.fecha_vencimiento < NOW()) AS vencido,
                                             (SELECT COUNT(*) FROM enlaces_autoregistro_estudiantes eae
                                              WHERE eae.id_enlace = ea.id AND eae.id_tenant = ea.id_tenant) AS total_estudiantes,
                                             (SELECT GROUP_CONCAT(DISTINCT g.nombre ORDER BY g.nombre SEPARATOR ', ')
                                              FROM enlaces_autoregistro_estudiantes eae
                                              INNER JOIN estudiantes_x_grupos exg ON exg.id_estudiante = eae.id_estudiante AND exg.activo = 1 AND exg.id_tenant = eae.id_tenant
                                              INNER JOIN grupos g ON g.id = exg.id_grupo
                                              WHERE eae.id_enlace = ea.id AND eae.id_tenant = ea.id_tenant) AS grupos,
                                             (SELECT COUNT(*) FROM enlaces_autoregistro_intentos i
                                              WHERE i.id_enlace = ea.id AND i.id_tenant = ea.id_tenant) AS total_intentos,
                                             (SELECT COUNT(*) FROM enlaces_autoregistro_intentos i
                                              WHERE i.id_enlace = ea.id AND i.id_tenant = ea.id_tenant
                                              AND i.paso = " . EnlacesAutoregistroIntentos::PASO_COMPLETADO . ") AS total_completados
                                      FROM enlaces_autoregistro_acudientes ea
                                      WHERE ea.id_tenant = :id_tenant
                                      ORDER BY ea.fecha_creacion DESC");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $filas = $sentence->fetchAll();

            $urlBase = self::urlBasePortal($db);
            foreach ($filas as $indice => $fila) {
                $filas[$indice]['estado'] = self::textoEstado($fila);
                $filas[$indice]['url_enlace'] = self::armarUrl($urlBase, $fila['id']);
            }

            Flight::json($filas);
        } catch (Exception $e) {
            error_log("Error en EnlacesAutoregistroAcudientes::getAll: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudieron consultar los enlaces'), 500);
        }
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);

        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, fecha_vencimiento, activo, fecha_creacion,
                                         (fecha_vencimiento < NOW()) AS vencido
                                  FROM enlaces_autoregistro_acudientes
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $urlBase = self::urlBasePortal($db);
        foreach ($filas as $indice => $fila) {
            $filas[$indice]['estado'] = self::textoEstado($fila);
            $filas[$indice]['url_enlace'] = self::armarUrl($urlBase, $fila['id']);
        }

        Flight::json($filas);
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);

        try {
            $db = Flight::db();
            $nombre = trim((string) (Flight::request()->data['nombre'] ?? ''));
            $fecha_vencimiento = self::normalizarFechaHora(Flight::request()->data['fecha_vencimiento'] ?? null);
            $activo = isset(Flight::request()->data['activo']) ? (int) Flight::request()->data['activo'] : 1;

            if ($nombre === '') {
                Flight::json(array('error' => 'El nombre del enlace es obligatorio'), 400);
                return;
            }
            if (!$fecha_vencimiento) {
                Flight::json(array('error' => 'La fecha y hora de vencimiento no es válida'), 400);
                return;
            }
            if (strtotime($fecha_vencimiento) <= time()) {
                Flight::json(array('error' => 'La fecha de vencimiento debe ser posterior a este momento'), 400);
                return;
            }

            $id = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO enlaces_autoregistro_acudientes (id, id_tenant, nombre, fecha_vencimiento, activo, id_usuario_creo)
                                      VALUES (:id, :id_tenant, :nombre, :fecha_vencimiento, :activo, :id_usuario_creo)");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':nombre', $nombre);
            $sentence->bindValue(':fecha_vencimiento', $fecha_vencimiento);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindValue(':id_usuario_creo', $userData->id ?? null);
            $sentence->execute();

            Flight::json(array('id' => $id, 'url_enlace' => self::armarUrl(self::urlBasePortal($db), $id)));
        } catch (Exception $e) {
            error_log("Error en EnlacesAutoregistroAcudientes::new: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo crear el enlace'), 500);
        }
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);

        $db = Flight::db();
        $id = Flight::request()->data['id'] ?? null;
        $nombre = trim((string) (Flight::request()->data['nombre'] ?? ''));
        $fecha_vencimiento = self::normalizarFechaHora(Flight::request()->data['fecha_vencimiento'] ?? null);
        $activo = isset(Flight::request()->data['activo']) ? (int) Flight::request()->data['activo'] : 1;

        if (!$id) {
            Flight::json(array('error' => 'Falta el id del enlace'), 400);
            return;
        }
        if ($nombre === '') {
            Flight::json(array('error' => 'El nombre del enlace es obligatorio'), 400);
            return;
        }
        if (!$fecha_vencimiento) {
            Flight::json(array('error' => 'La fecha y hora de vencimiento no es válida'), 400);
            return;
        }

        $sentence = $db->prepare("UPDATE enlaces_autoregistro_acudientes
                                  SET nombre = :nombre, fecha_vencimiento = :fecha_vencimiento, activo = :activo
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':nombre', $nombre);
        $sentence->bindValue(':fecha_vencimiento', $fecha_vencimiento);
        $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    /**
     * Solo se borra un enlace que nadie ha abierto. Si ya tiene intentos se
     * desactiva, para no perder el seguimiento.
     */
    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);

        $db = Flight::db();
        $id = Flight::request()->data['id'] ?? null;

        $sentence = $db->prepare("SELECT COUNT(*) AS total FROM enlaces_autoregistro_intentos WHERE id_enlace = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        if ($fila && (int) $fila['total'] > 0) {
            Flight::json(array('error' => 'Este enlace ya tiene registros de acudientes y no se puede eliminar. Desactívalo para que deje de funcionar.'), 400);
            return;
        }

        $sentence = $db->prepare("DELETE FROM enlaces_autoregistro_acudientes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Estudiantes activos de los grupos indicados, para marcar en la pantalla
     * de creacion. Body: { grupos: [id_grupo, ...] }
     */
    public static function getEstudiantesPorGrupos()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, self::PERMISO);

        $grupos = Flight::request()->data['grupos'] ?? array();
        if (!is_array($grupos) || count($grupos) === 0) {
            Flight::json(array());
            return;
        }

        $grupos = array_values(array_unique(array_filter($grupos)));
        $marcas = implode(',', array_fill(0, count($grupos), '?'));

        $db = Flight::db();
        $sentence = $db->prepare("SELECT e.id AS id_estudiante, exg.id_grupo, g.nombre AS nombre_grupo, g.orden,
                                         TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_estudiante
                                  FROM estudiantes_x_grupos exg
                                  INNER JOIN estudiantes e ON e.id = exg.id_estudiante AND e.id_tenant = exg.id_tenant
                                  INNER JOIN personas p ON p.id = e.id_persona
                                  INNER JOIN grupos g ON g.id = exg.id_grupo
                                  WHERE exg.activo = 1 AND e.activo = 1
                                  AND exg.id_grupo IN ($marcas)
                                  AND exg.id_tenant = ?
                                  ORDER BY g.orden, p.primer_nombre, p.primer_apellido");
        $sentence->execute(array_merge($grupos, array(TenantContext::id())));
        Flight::json($sentence->fetchAll());
    }

    // =================================================================
    // PAGINA PUBLICA (portal de padres, sin token)
    // =================================================================

    /**
     * Datos para pintar la pagina publica: jardin, estudiantes del enlace y
     * catalogos del formulario.
     *
     * GET /autoregistro-publico/@idEnlace/contexto
     */
    public static function contextoPublico($idEnlace)
    {
        try {
            $db = Flight::db();
            $enlace = self::validarEnlacePublico($db, $idEnlace);
            if (!$enlace) {
                return;
            }

            // Solo nombre y primer apellido: la pagina es publica.
            $sentence = $db->prepare("SELECT e.id AS id_estudiante,
                                             TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS nombre,
                                             exg.id_grupo, g.nombre AS nombre_grupo
                                      FROM enlaces_autoregistro_estudiantes eae
                                      INNER JOIN estudiantes e ON e.id = eae.id_estudiante AND e.id_tenant = eae.id_tenant AND e.activo = 1
                                      INNER JOIN personas p ON p.id = e.id_persona
                                      LEFT JOIN estudiantes_x_grupos exg ON exg.id_estudiante = e.id AND exg.activo = 1 AND exg.id_tenant = eae.id_tenant
                                      LEFT JOIN grupos g ON g.id = exg.id_grupo
                                      WHERE eae.id_enlace = :id_enlace AND eae.id_tenant = :id_tenant
                                      ORDER BY g.orden, p.primer_nombre, p.primer_apellido");
            $sentence->bindValue(':id_enlace', $enlace['id']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $estudiantes = $sentence->fetchAll();

            // Parentescos que el estudiante ya tiene ocupados (solo los que
            // el jardin marco como unicos, ej. Padre y Madre).
            $ocupados = self::parentescosOcupados($db, array_column($estudiantes, 'id_estudiante'));
            foreach ($estudiantes as $indice => $est) {
                $estudiantes[$indice]['parentescos_ocupados'] = $ocupados[$est['id_estudiante']] ?? array();
            }

            $tiposAcudiente = $db->query("SELECT id, nombre, icono FROM tipos_acudiente ORDER BY nombre")->fetchAll();
            $tiposIdentificacion = $db->query("SELECT id, nombre, sigla FROM tipos_identificacion ORDER BY id")->fetchAll();
            $generos = $db->query("SELECT id, nombre FROM generos ORDER BY id")->fetchAll();

            $iaConfig = self::cargarConfigIa($db);
            $lecturaIaDisponible = !empty($iaConfig['ia_vision_cadena'])
                && (!isset($iaConfig['estado_servicio']) || $iaConfig['estado_servicio'] === 'activo');

            Flight::json(array(
                'enlace' => array(
                    'id' => $enlace['id'],
                    'nombre' => $enlace['nombre'],
                    'fecha_vencimiento' => $enlace['fecha_vencimiento'],
                ),
                'institucion_nombre' => self::configTexto($db, 'institucion_nombre'),
                'estudiantes' => $estudiantes,
                'tipos_acudiente' => $tiposAcudiente,
                'tipos_identificacion' => $tiposIdentificacion,
                'generos' => $generos,
                'id_tipo_identificacion_ia' => self::idTipoIdentificacionIa($db),
                'lectura_ia_disponible' => $lecturaIaDisponible,
                'min_longitud_clave' => self::MIN_LONGITUD_CLAVE,
            ));
        } catch (Exception $e) {
            error_log("Error en EnlacesAutoregistroAcudientes::contextoPublico: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo cargar la información del enlace'), 500);
        }
    }

    /**
     * Crea el intento, o retoma el que el navegador ya tenia si no ha
     * terminado. Body: { id_intento? }
     *
     * POST /autoregistro-publico/@idEnlace/iniciar
     */
    public static function iniciarPublico($idEnlace)
    {
        try {
            $db = Flight::db();
            $enlace = self::validarEnlacePublico($db, $idEnlace);
            if (!$enlace) {
                return;
            }

            $idIntentoPrevio = Flight::request()->data['id_intento'] ?? null;
            $intento = self::esUuid($idIntentoPrevio)
                ? EnlacesAutoregistroIntentos::obtener($db, $idIntentoPrevio, $enlace['id'])
                : null;

            if ($intento && (int) $intento['paso'] < EnlacesAutoregistroIntentos::PASO_COMPLETADO) {
                $sentence = $db->prepare("SELECT id_estudiante, id_tipo_acudiente FROM enlaces_autoregistro_vinculos
                                          WHERE id_intento = :id_intento AND id_tenant = :id_tenant");
                $sentence->bindValue(':id_intento', $intento['id']);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();

                Flight::json(array(
                    'id_intento' => $intento['id'],
                    'paso' => (int) $intento['paso'],
                    'modo' => $intento['modo'],
                    'seleccion' => $sentence->fetchAll(),
                    'retomado' => true,
                ));
                return;
            }

            $idIntento = EnlacesAutoregistroIntentos::crear($db, $enlace['id']);

            Flight::json(array(
                'id_intento' => $idIntento,
                'paso' => EnlacesAutoregistroIntentos::PASO_ABRIO,
                'modo' => null,
                'seleccion' => array(),
                'retomado' => false,
            ));
        } catch (Exception $e) {
            error_log("Error en EnlacesAutoregistroAcudientes::iniciarPublico: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo iniciar el registro'), 500);
        }
    }

    /**
     * Guarda el avance del asistente.
     * Body: { id_intento, paso, modo?, estudiantes?: [{ id_estudiante, id_tipo_acudiente }] }
     *
     * POST /autoregistro-publico/@idEnlace/paso
     */
    public static function pasoPublico($idEnlace)
    {
        try {
            $db = Flight::db();
            $enlace = self::validarEnlacePublico($db, $idEnlace);
            if (!$enlace) {
                return;
            }

            $intento = self::validarIntentoPublico($db, $enlace['id'], Flight::request()->data['id_intento'] ?? null);
            if (!$intento) {
                return;
            }

            $paso = (int) (Flight::request()->data['paso'] ?? 0);
            // El paso 6 solo lo pone el registro.
            if ($paso < EnlacesAutoregistroIntentos::PASO_SELECCIONO || $paso > EnlacesAutoregistroIntentos::PASO_DATOS) {
                Flight::json(array('error' => 'Paso no válido'), 400);
                return;
            }

            $campos = array();
            $modo = Flight::request()->data['modo'] ?? null;
            if (in_array($modo, array('manual', 'automatico'), true)) {
                $campos['modo'] = $modo;
            }

            $estudiantes = Flight::request()->data['estudiantes'] ?? null;
            if ($paso === EnlacesAutoregistroIntentos::PASO_SELECCIONO) {
                $seleccion = self::validarSeleccion($db, $enlace['id'], $estudiantes);
                if ($seleccion === null) {
                    return;
                }

                $db->beginTransaction();
                EnlacesAutoregistroVinculos::reemplazarPorIntento($db, $intento['id'], $seleccion);
                EnlacesAutoregistroIntentos::actualizar($db, $intento['id'], $paso, $campos);
                $db->commit();
            } else {
                EnlacesAutoregistroIntentos::actualizar($db, $intento['id'], $paso, $campos);
            }

            Flight::json(array('id_intento' => $intento['id'], 'paso' => max($paso, (int) $intento['paso'])));
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en EnlacesAutoregistroAcudientes::pasoPublico: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo guardar el avance'), 500);
        }
    }

    /**
     * Verifica si el documento ya existe en el jardin. No devuelve datos
     * personales: la pagina es publica.
     * Body: { id_intento, numero_identificacion }
     *
     * POST /autoregistro-publico/@idEnlace/validar-documento
     */
    public static function validarDocumentoPublico($idEnlace)
    {
        try {
            $db = Flight::db();
            $enlace = self::validarEnlacePublico($db, $idEnlace);
            if (!$enlace) {
                return;
            }

            $intento = self::validarIntentoPublico($db, $enlace['id'], Flight::request()->data['id_intento'] ?? null);
            if (!$intento) {
                return;
            }

            $numero = self::normalizarNumero(Flight::request()->data['numero_identificacion'] ?? '');
            if ($numero === '') {
                Flight::json(array('error' => 'Escribe un número de documento válido, sin puntos ni espacios'), 400);
                return;
            }

            EnlacesAutoregistroIntentos::actualizar($db, $intento['id'], EnlacesAutoregistroIntentos::PASO_DOCUMENTO, array(
                'numero_identificacion' => $numero,
            ));

            Flight::json(array_merge(array('numero_identificacion' => $numero), self::estadoDocumento($db, $numero)));
        } catch (Exception $e) {
            error_log("Error en EnlacesAutoregistroAcudientes::validarDocumentoPublico: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo validar el documento'), 500);
        }
    }

    /**
     * Lee con IA la foto del documento (las dos caras unidas en una sola
     * imagen por el front). La imagen queda guardada en temporal y se pasa a
     * documentos de la persona cuando termina el registro, se haya leido o no.
     *
     * Si la lectura falla responde 200 con success = false, para que la
     * pagina pase a modo manual sin mostrar un error tecnico.
     *
     * Si el celular ya leyo el codigo de barras de la cedula, llega en
     * datos_codigo (JSON) y no se usa la IA.
     *
     * POST /autoregistro-publico/@idEnlace/analizar-documento
     *      (multipart: id_intento, documento, datos_codigo?)
     */
    public static function analizarDocumentoPublico($idEnlace)
    {
        try {
            $db = Flight::db();
            $enlace = self::validarEnlacePublico($db, $idEnlace);
            if (!$enlace) {
                return;
            }

            $intento = self::validarIntentoPublico($db, $enlace['id'], Flight::request()->data['id_intento'] ?? null);
            if (!$intento) {
                return;
            }

            $datosCodigo = self::leerDatosCodigo($db, Flight::request()->data['datos_codigo'] ?? null);

            $maxLecturas = (int) self::configNumero($db, 'autoregistro_max_lecturas_ia', 0);
            if (!$datosCodigo && $maxLecturas > 0 && (int) $intento['lecturas_ia'] >= $maxLecturas) {
                Flight::json(array(
                    'success' => false,
                    'mensaje' => 'Ya usaste todos los intentos de lectura automática. Escribe tu documento a mano.',
                    'documento_guardado' => self::existeTemporal($intento['id']),
                ));
                return;
            }

            if (!isset($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
                Flight::json(array('error' => 'No se recibió la foto del documento'), 400);
                return;
            }

            $archivo = $_FILES['documento'];
            if ($archivo['size'] > self::MAX_BYTES_DOCUMENTO) {
                Flight::json(array('error' => 'La foto supera el tamaño máximo de 10 MB'), 400);
                return;
            }

            // Se revisa el contenido real, no la extension que manda el navegador.
            $infoImagen = @getimagesize($archivo['tmp_name']);
            $mimeType = $infoImagen ? $infoImagen['mime'] : null;
            if (!in_array($mimeType, array('image/jpeg', 'image/png'), true)) {
                Flight::json(array('error' => 'La foto debe ser JPG o PNG'), 400);
                return;
            }

            $rutaTemporal = self::rutaTemporal($intento['id'], true);
            if (!move_uploaded_file($archivo['tmp_name'], $rutaTemporal)) {
                Flight::json(array('error' => 'No se pudo guardar la foto del documento'), 500);
                return;
            }

            // Codigo de barras leido en el celular: no se usa la IA.
            if ($datosCodigo) {
                EnlacesAutoregistroIntentos::actualizar($db, $intento['id'], EnlacesAutoregistroIntentos::PASO_DOCUMENTO, array(
                    'modo' => 'automatico',
                    'resultado_ia' => 'codigo_barras',
                    'error_ia' => null,
                    'numero_identificacion' => $datosCodigo['numero_identificacion'],
                    'nombre_completo' => self::nombreCompleto($datosCodigo),
                ));

                Flight::json(array_merge(array(
                    'success' => true,
                    'datos' => $datosCodigo,
                    'documento_guardado' => true,
                ), self::estadoDocumento($db, $datosCodigo['numero_identificacion'])));
                return;
            }

            EnlacesAutoregistroIntentos::sumarLecturaIa($db, $intento['id']);

            $config = self::cargarConfigIa($db);
            if (isset($config['estado_servicio']) && $config['estado_servicio'] !== 'activo') {
                self::registrarFalloIa($db, $intento['id'], 'Servicio de IA pausado');
                Flight::json(array(
                    'success' => false,
                    'mensaje' => 'La lectura automática no está disponible en este momento. Escribe tu documento a mano; la foto quedó guardada.',
                    'documento_guardado' => true,
                ));
                return;
            }

            $base64 = base64_encode(file_get_contents($rutaTemporal));

            $prompt = "La imagen contiene las dos caras de un documento de identidad colombiano (cédula de ciudadanía, "
                . "amarilla con hologramas o digital), una debajo de la otra. Extrae ÚNICAMENTE los siguientes datos "
                . "en formato JSON estricto, sin explicaciones ni texto adicional:\n\n"
                . "{\n"
                . "  \"es_documento_identidad\": (true si la imagen es una cédula colombiana legible, false si no),\n"
                . "  \"numero_identificacion\": (string solo con dígitos, o null),\n"
                . "  \"primer_nombre\": (string o null),\n"
                . "  \"segundo_nombre\": (string o null),\n"
                . "  \"primer_apellido\": (string o null),\n"
                . "  \"segundo_apellido\": (string o null),\n"
                . "  \"fecha_nacimiento\": (string en formato YYYY-MM-DD o null),\n"
                . "  \"sexo\": (\"Masculino\", \"Femenino\" o null)\n"
                . "}\n\n"
                . "Reglas:\n"
                . "- El número es el que aparece rotulado como 'NÚMERO' o 'NUIP' en la cara frontal. No uses códigos de barras ni seriales.\n"
                . "- En la cédula amarilla los APELLIDOS van arriba y los NOMBRES abajo; sepáralos en primero y segundo.\n"
                . "- La fecha de nacimiento y el sexo (M o F) suelen estar en la cara posterior de la cédula amarilla.\n"
                . "- Si un campo no aparece o no es legible, usa null. No inventes datos.";

            $resultado = IaVision::extraerDeImagen($config, $base64, $mimeType, $prompt, false, 600);
            IaVision::registrarUso($db, TenantContext::id(), $resultado, 'autoregistro', 'leer_documento');

            if (!$resultado['success']) {
                self::registrarFalloIa($db, $intento['id'], $resultado['error']);
                Flight::json(array(
                    'success' => false,
                    'mensaje' => 'No pudimos leer tu documento. Escríbelo a mano; la foto quedó guardada.',
                    'documento_guardado' => true,
                ));
                return;
            }

            self::sumarContadoresIa($db, $resultado['tokens']['total']);

            $texto = trim(preg_replace('/```(json)?\s*/', '', $resultado['texto']));
            $datos = json_decode($texto, true);
            $numero = is_array($datos) ? self::normalizarNumero($datos['numero_identificacion'] ?? '') : '';

            if (!is_array($datos) || empty($datos['es_documento_identidad']) || $numero === '') {
                self::registrarFalloIa($db, $intento['id'], 'La IA no encontró un documento legible');
                Flight::json(array(
                    'success' => false,
                    'mensaje' => 'No pudimos leer tu documento. Escríbelo a mano; la foto quedó guardada.',
                    'documento_guardado' => true,
                ));
                return;
            }

            $leidos = array(
                'id_tipo_identificacion' => self::idTipoIdentificacionIa($db),
                'numero_identificacion' => $numero,
                'primer_nombre' => self::normalizarTexto($datos['primer_nombre'] ?? null),
                'segundo_nombre' => self::normalizarTexto($datos['segundo_nombre'] ?? null),
                'primer_apellido' => self::normalizarTexto($datos['primer_apellido'] ?? null),
                'segundo_apellido' => self::normalizarTexto($datos['segundo_apellido'] ?? null),
                'fecha_nacimiento' => self::normalizarFecha($datos['fecha_nacimiento'] ?? null),
                'id_genero' => self::idGeneroPorNombre($db, $datos['sexo'] ?? null),
            );

            EnlacesAutoregistroIntentos::actualizar($db, $intento['id'], EnlacesAutoregistroIntentos::PASO_DOCUMENTO, array(
                'modo' => 'automatico',
                'resultado_ia' => 'exitoso',
                'error_ia' => null,
                'numero_identificacion' => $numero,
                'nombre_completo' => self::nombreCompleto($leidos),
            ));

            Flight::json(array_merge(array(
                'success' => true,
                'datos' => $leidos,
                'documento_guardado' => true,
            ), self::estadoDocumento($db, $numero)));
        } catch (Exception $e) {
            error_log("Error en EnlacesAutoregistroAcudientes::analizarDocumentoPublico: " . $e->getMessage());
            Flight::json(array(
                'success' => false,
                'mensaje' => 'No pudimos leer tu documento. Escríbelo a mano.',
                'documento_guardado' => isset($intento['id']) ? self::existeTemporal($intento['id']) : false,
            ));
        }
    }

    /**
     * Termina el registro: persona, usuario del portal, acudiente por cada
     * estudiante escogido y documento. Todo en una transaccion.
     *
     * Los estudiantes NO llegan en el body: se toman de lo que el intento
     * guardo en el paso de seleccion.
     *
     * Body: { id_intento, id_tipo_identificacion, numero_identificacion,
     *         primer_nombre, segundo_nombre, primer_apellido, segundo_apellido,
     *         correo_electronico, telefono, fecha_nacimiento, id_genero, clave }
     *
     * POST /autoregistro-publico/@idEnlace/registrar
     */
    public static function registrarPublico($idEnlace)
    {
        $db = Flight::db();
        $intento = null;
        $copiaDocumento = null;

        try {
            $enlace = self::validarEnlacePublico($db, $idEnlace);
            if (!$enlace) {
                return;
            }

            $intento = self::validarIntentoPublico($db, $enlace['id'], Flight::request()->data['id_intento'] ?? null);
            if (!$intento) {
                return;
            }

            $data = Flight::request()->data;
            $idTenant = TenantContext::id();

            $numero = self::normalizarNumero($data['numero_identificacion'] ?? '');
            if ($numero === '') {
                Flight::json(array('error' => 'Falta el número de documento'), 400);
                return;
            }

            // Estudiantes escogidos en el paso de seleccion
            $sentence = $db->prepare("SELECT v.id_estudiante, v.id_tipo_acudiente,
                                             TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS nombre_estudiante,
                                             e.id_persona AS id_persona_estudiante,
                                             p.numero_identificacion AS documento_estudiante
                                      FROM enlaces_autoregistro_vinculos v
                                      INNER JOIN estudiantes e ON e.id = v.id_estudiante AND e.id_tenant = v.id_tenant
                                      INNER JOIN personas p ON p.id = e.id_persona
                                      WHERE v.id_intento = :id_intento AND v.id_tenant = :id_tenant");
            $sentence->bindValue(':id_intento', $intento['id']);
            $sentence->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $sentence->execute();
            $vinculos = $sentence->fetchAll();

            if (count($vinculos) === 0) {
                Flight::json(array('error' => 'Primero escoge a tus hijos'), 400);
                return;
            }

            $persona = self::buscarPersonaPorNumero($db, $numero);
            $usuario = $persona ? self::buscarUsuarioPorPersona($db, $persona['id']) : null;

            $clave = (string) ($data['clave'] ?? '');
            $correo = trim((string) ($data['correo_electronico'] ?? ''));
            $datosPersona = array(
                'id_tipo_identificacion' => $data['id_tipo_identificacion'] ?? null,
                'primer_nombre' => self::normalizarTexto($data['primer_nombre'] ?? null),
                'segundo_nombre' => self::normalizarTexto($data['segundo_nombre'] ?? null),
                'primer_apellido' => self::normalizarTexto($data['primer_apellido'] ?? null),
                'segundo_apellido' => self::normalizarTexto($data['segundo_apellido'] ?? null),
                'correo_electronico' => $correo !== '' ? $correo : null,
                'telefono' => self::normalizarTexto($data['telefono'] ?? null),
                'fecha_nacimiento' => self::normalizarFecha($data['fecha_nacimiento'] ?? null),
                'id_genero' => !empty($data['id_genero']) ? (int) $data['id_genero'] : null,
            );

            // Si ya tiene usuario no se piden datos ni clave: solo se vincula.
            if (!$usuario) {
                $errorValidacion = self::validarDatosRegistro($db, $datosPersona, $clave, $persona !== null);
                if ($errorValidacion) {
                    Flight::json(array('error' => $errorValidacion), 400);
                    return;
                }
            }

            EnlacesAutoregistroIntentos::actualizar($db, $intento['id'], EnlacesAutoregistroIntentos::PASO_DATOS, array(
                'numero_identificacion' => $numero,
            ));

            $db->beginTransaction();

            // ---------------------------------------------------------
            // 1. Persona
            // ---------------------------------------------------------
            $personaNueva = false;
            if ($persona) {
                $idPersona = $persona['id'];
                if (!$usuario) {
                    self::completarPersona($db, $idPersona, $datosPersona);
                }
            } else {
                $idPersona = self::crearPersona($db, $numero, $datosPersona);
                $personaNueva = true;
            }

            // ---------------------------------------------------------
            // 2. Usuario del portal de padres
            // ---------------------------------------------------------
            $usuarioNuevo = false;
            if ($usuario) {
                $idUsuario = $usuario['id'];
                $nombreUsuario = $usuario['usuario'];
                $usuarioActivo = (int) $usuario['activo'] === 1;

                // Quien se registra como acudiente necesita entrar al portal.
                if ((int) $usuario['acceso_portal_padres'] !== 1) {
                    $up = $db->prepare("UPDATE usuarios SET acceso_portal_padres = 1 WHERE id = :id AND id_tenant = :id_tenant");
                    $up->bindValue(':id', $idUsuario);
                    $up->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $up->execute();
                }
            } else {
                $nombreUsuario = $numero;

                $check = $db->prepare("SELECT id FROM usuarios WHERE usuario = :usuario AND id_tenant = :id_tenant LIMIT 1");
                $check->bindValue(':usuario', $nombreUsuario);
                $check->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $check->execute();
                if ($check->fetch()) {
                    $db->rollBack();
                    Flight::json(array('error' => 'Ya existe un usuario con ese número de documento. Comunícate con el jardín.'), 400);
                    return;
                }

                $idUsuario = Uuid::generar();
                $ins = $db->prepare("INSERT INTO usuarios (id, id_tenant, id_persona, usuario, clave, correo_electronico, activo,
                                                           acceso_institucional, acceso_chat_wa, acceso_portal_padres, super_admin)
                                     VALUES (:id, :id_tenant, :id_persona, :usuario, :clave, :correo, 1, 0, 1, 1, 0)");
                $ins->bindValue(':id', $idUsuario);
                $ins->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $ins->bindValue(':id_persona', $idPersona);
                $ins->bindValue(':usuario', $nombreUsuario);
                $ins->bindValue(':clave', $clave);
                $ins->bindValue(':correo', $datosPersona['correo_electronico']);
                $ins->execute();

                $usuarioNuevo = true;
                $usuarioActivo = true;
            }

            // Rol parametrizado de acudiente (no hace nada si ya lo tiene)
            Usuarios::asignarRolDefaultAcudiente($db, $idUsuario);

            // ---------------------------------------------------------
            // 3. Acudiente por cada estudiante escogido
            // ---------------------------------------------------------
            $idsEnlace = EnlacesAutoregistroEstudiantes::idsPorEnlace($db, $enlace['id']);
            $autorizadoRecoger = (int) self::configNumero($db, 'autoregistro_autorizado_recoger', 0);
            $responsablePago = (int) self::configNumero($db, 'autoregistro_responsable_pago', 0);

            // Se vuelve a revisar aqui por si otra persona tomo el parentesco
            // mientras este acudiente llenaba sus datos.
            $ocupados = self::parentescosOcupados($db, array_column($vinculos, 'id_estudiante'), $idPersona);

            $resumen = array();
            foreach ($vinculos as $vinculo) {
                $resultadoVinculo = self::VINCULO_CREADO;
                $idAcudiente = null;

                $esElMismo = $vinculo['id_persona_estudiante'] === $idPersona
                    || trim((string) $vinculo['documento_estudiante']) === $numero;

                if (!in_array($vinculo['id_estudiante'], $idsEnlace, true) || $esElMismo) {
                    $resultadoVinculo = self::VINCULO_OMITIDO;
                } else {
                    $existe = $db->prepare("SELECT id FROM acudientes
                                            WHERE id_estudiante = :id_estudiante AND id_persona = :id_persona AND id_tenant = :id_tenant
                                            LIMIT 1");
                    $existe->bindValue(':id_estudiante', $vinculo['id_estudiante']);
                    $existe->bindValue(':id_persona', $idPersona);
                    $existe->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $existe->execute();
                    $filaExistente = $existe->fetch();

                    if ($filaExistente) {
                        $idAcudiente = $filaExistente['id'];
                        $resultadoVinculo = self::VINCULO_YA_EXISTIA;
                    } elseif (in_array((int) $vinculo['id_tipo_acudiente'], $ocupados[$vinculo['id_estudiante']] ?? array(), true)) {
                        $resultadoVinculo = self::VINCULO_PARENTESCO_OCUPADO;
                    } else {
                        $idAcudiente = Uuid::generar();
                        $insAc = $db->prepare("INSERT INTO acudientes (id, id_tenant, id_estudiante, id_persona, id_tipo_acudiente,
                                                                       es_responsable_pago, autorizado_recoger, ve_en_portal_padres, activo)
                                               VALUES (:id, :id_tenant, :id_estudiante, :id_persona, :id_tipo_acudiente,
                                                       :es_responsable_pago, :autorizado_recoger, 1, 1)");
                        $insAc->bindValue(':id', $idAcudiente);
                        $insAc->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                        $insAc->bindValue(':id_estudiante', $vinculo['id_estudiante']);
                        $insAc->bindValue(':id_persona', $idPersona);
                        $insAc->bindValue(':id_tipo_acudiente', (int) $vinculo['id_tipo_acudiente'], PDO::PARAM_INT);
                        $insAc->bindValue(':es_responsable_pago', $responsablePago, PDO::PARAM_INT);
                        $insAc->bindValue(':autorizado_recoger', $autorizadoRecoger, PDO::PARAM_INT);
                        $insAc->execute();
                    }
                }

                EnlacesAutoregistroVinculos::registrarResultado($db, $intento['id'], $vinculo['id_estudiante'], $idAcudiente, $resultadoVinculo);
                $resumen[] = array(
                    'nombre_estudiante' => $vinculo['nombre_estudiante'],
                    'resultado' => $resultadoVinculo,
                );
            }

            // ---------------------------------------------------------
            // 4. Documento de identidad (si subio foto)
            // ---------------------------------------------------------
            $observaciones = array();
            $idDocumento = null;
            if (self::existeTemporal($intento['id'])) {
                $guardado = self::guardarDocumento($db, $idPersona, $idUsuario, $intento['id']);
                $idDocumento = $guardado['id_documento'];
                $copiaDocumento = $guardado['ruta_copia'];
                if ($guardado['observacion']) {
                    $observaciones[] = $guardado['observacion'];
                }
            }

            // ---------------------------------------------------------
            // 5. Cierre del intento
            // ---------------------------------------------------------
            $nombrePersona = self::nombrePersonaPorId($db, $idPersona);
            EnlacesAutoregistroIntentos::actualizar($db, $intento['id'], EnlacesAutoregistroIntentos::PASO_COMPLETADO, array(
                'numero_identificacion' => $numero,
                'nombre_completo' => $nombrePersona,
                'id_persona' => $idPersona,
                'persona_nueva' => $personaNueva ? 1 : 0,
                'id_usuario' => $idUsuario,
                'usuario_nuevo' => $usuarioNuevo ? 1 : 0,
                'id_documento' => $idDocumento,
                'observaciones' => count($observaciones) > 0 ? mb_substr(implode(' | ', $observaciones), 0, 500) : null,
                'fecha_completado' => date('Y-m-d H:i:s'),
            ));

            $db->commit();

            // Ya confirmado: se borra el temporal y se registra el usuario en master.
            self::borrarTemporal($intento['id']);
            if ($usuarioNuevo) {
                Usuarios::insertarEnMaster($nombreUsuario);
            }

            Flight::json(array(
                'usuario' => $nombreUsuario,
                'usuario_nuevo' => $usuarioNuevo,
                'usuario_activo' => $usuarioActivo,
                'persona_nueva' => $personaNueva,
                'nombre' => $nombrePersona,
                'vinculos' => $resumen,
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($copiaDocumento && file_exists($copiaDocumento)) {
                @unlink($copiaDocumento);
            }
            error_log("Error en EnlacesAutoregistroAcudientes::registrarPublico: " . $e->getMessage());

            if ($intento) {
                try {
                    EnlacesAutoregistroIntentos::actualizar($db, $intento['id'], null, array(
                        'observaciones' => mb_substr('Error al registrar: ' . $e->getMessage(), 0, 500),
                    ));
                } catch (Exception $ignorada) {
                    error_log("No se pudo guardar el error en el intento: " . $ignorada->getMessage());
                }
            }

            Flight::json(array('error' => 'No se pudo completar el registro. Intenta de nuevo o comunícate con el jardín.'), 500);
        }
    }

    // =================================================================
    // Validaciones de la pagina publica
    // =================================================================

    private static function esUuid($valor)
    {
        return is_string($valor)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $valor) === 1;
    }

    /**
     * Enlace activo y vigente, o null (y ya respondio el error).
     */
    private static function validarEnlacePublico(PDO $db, $idEnlace)
    {
        if (!self::esUuid($idEnlace)) {
            Flight::json(array('error' => 'El enlace no es válido', 'code' => 'ENLACE_NO_EXISTE'), 404);
            return null;
        }

        $sentence = $db->prepare("SELECT id, nombre, fecha_vencimiento, activo, (fecha_vencimiento < NOW()) AS vencido
                                  FROM enlaces_autoregistro_acudientes
                                  WHERE id = :id AND id_tenant = :id_tenant
                                  LIMIT 1");
        $sentence->bindValue(':id', $idEnlace);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $enlace = $sentence->fetch();

        if (!$enlace) {
            Flight::json(array('error' => 'El enlace no es válido', 'code' => 'ENLACE_NO_EXISTE'), 404);
            return null;
        }
        if ((int) $enlace['activo'] !== 1) {
            Flight::json(array('error' => 'Este enlace ya no está disponible. Pide uno nuevo al jardín.', 'code' => 'ENLACE_INACTIVO'), 410);
            return null;
        }
        if ((int) $enlace['vencido'] === 1) {
            Flight::json(array('error' => 'Este enlace ya venció. Pide uno nuevo al jardín.', 'code' => 'ENLACE_VENCIDO'), 410);
            return null;
        }

        return $enlace;
    }

    /**
     * Intento del enlace que todavia no termina, o null (y ya respondio).
     */
    private static function validarIntentoPublico(PDO $db, $idEnlace, $idIntento)
    {
        $intento = self::esUuid($idIntento) ? EnlacesAutoregistroIntentos::obtener($db, $idIntento, $idEnlace) : null;

        if (!$intento) {
            Flight::json(array('error' => 'El registro no es válido. Vuelve a abrir el enlace.', 'code' => 'INTENTO_NO_EXISTE'), 404);
            return null;
        }
        if ((int) $intento['paso'] >= EnlacesAutoregistroIntentos::PASO_COMPLETADO) {
            Flight::json(array('error' => 'Este registro ya se completó.', 'code' => 'INTENTO_COMPLETADO'), 409);
            return null;
        }

        return $intento;
    }

    /**
     * Revisa que los estudiantes esten en el enlace y que el parentesco exista.
     * Devuelve la seleccion limpia o null (y ya respondio el error).
     */
    private static function validarSeleccion(PDO $db, $idEnlace, $estudiantes)
    {
        if (!is_array($estudiantes) || count($estudiantes) === 0) {
            Flight::json(array('error' => 'Escoge al menos un estudiante'), 400);
            return null;
        }

        $idsEnlace = EnlacesAutoregistroEstudiantes::idsPorEnlace($db, $idEnlace);
        $tipos = $db->query("SELECT id FROM tipos_acudiente")->fetchAll(PDO::FETCH_COLUMN);
        $tipos = array_map('intval', $tipos);
        $ocupados = self::parentescosOcupados($db, array_column($estudiantes, 'id_estudiante'));

        $limpia = array();
        $conflictos = array();
        foreach ($estudiantes as $item) {
            $idEstudiante = $item['id_estudiante'] ?? null;
            $idTipo = isset($item['id_tipo_acudiente']) ? (int) $item['id_tipo_acudiente'] : 0;

            if (!in_array($idEstudiante, $idsEnlace, true)) {
                Flight::json(array('error' => 'Uno de los estudiantes escogidos no está en este enlace'), 400);
                return null;
            }
            if (!in_array($idTipo, $tipos, true)) {
                Flight::json(array('error' => 'Indica qué eres de cada estudiante escogido'), 400);
                return null;
            }
            if (in_array($idTipo, $ocupados[$idEstudiante] ?? array(), true)) {
                $conflictos[] = array('id_estudiante' => $idEstudiante, 'id_tipo_acudiente' => $idTipo);
            }

            $limpia[$idEstudiante] = array('id_estudiante' => $idEstudiante, 'id_tipo_acudiente' => $idTipo);
        }

        if (count($conflictos) > 0) {
            self::responderParentescoOcupado($db, $conflictos);
            return null;
        }

        return array_values($limpia);
    }

    /**
     * El parentesco escogido ya lo tiene otra persona. Lo mas comun es que
     * sea el mismo papa o mama que ya tiene usuario, por eso el mensaje le
     * recuerda la URL del portal y que su usuario es su numero de cedula.
     *
     * Ademas del texto, se envian url_portal y detalle para que el front
     * muestre el enlace.
     */
    private static function responderParentescoOcupado(PDO $db, $conflictos)
    {
        $detalle = array();
        foreach ($conflictos as $conflicto) {
            $sentence = $db->prepare("SELECT TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS estudiante,
                                             (SELECT nombre FROM tipos_acudiente WHERE id = :id_tipo) AS parentesco
                                      FROM estudiantes e
                                      INNER JOIN personas p ON p.id = e.id_persona
                                      WHERE e.id = :id_estudiante AND e.id_tenant = :id_tenant");
            $sentence->bindValue(':id_tipo', $conflicto['id_tipo_acudiente'], PDO::PARAM_INT);
            $sentence->bindValue(':id_estudiante', $conflicto['id_estudiante']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $fila = $sentence->fetch();
            if ($fila) {
                $detalle[] = $fila['estudiante'] . ' ya tiene registrado el parentesco ' . $fila['parentesco'];
            }
        }

        $urlBase = self::urlBasePortal($db);
        $urlLogin = $urlBase ? $urlBase . '/#/login' : null;

        $mensaje = (count($detalle) > 0 ? implode('. ', $detalle) . '. ' : 'Uno de los estudiantes ya tiene registrado ese parentesco. ')
            . 'Si eres tú, ingresa al Portal de Padres'
            . ($urlLogin ? ' (' . $urlLogin . ')' : '')
            . ' con tu número de cédula como usuario. Si no eres tú, escoge otro parentesco o comunícate con el jardín.';

        Flight::json(array(
            'error' => $mensaje,
            'code' => 'PARENTESCO_OCUPADO',
            'url_portal' => $urlLogin,
            'detalle' => $detalle,
        ), 400);
    }

    /**
     * Mensaje de error, o null si los datos sirven.
     * Para una persona que ya existe no se exigen nombres (no se le muestran
     * sus datos guardados), pero si el correo y la clave del usuario.
     */
    private static function validarDatosRegistro(PDO $db, $datos, $clave, $personaExiste)
    {
        if (mb_strlen($clave) < self::MIN_LONGITUD_CLAVE) {
            return 'La contraseña debe tener al menos ' . self::MIN_LONGITUD_CLAVE . ' caracteres';
        }
        if (empty($datos['correo_electronico']) || !filter_var($datos['correo_electronico'], FILTER_VALIDATE_EMAIL)) {
            return 'Escribe un correo electrónico válido';
        }
        if ($personaExiste) {
            return null;
        }

        if (empty($datos['id_tipo_identificacion'])) {
            return 'Escoge el tipo de documento';
        }
        $tipo = $db->prepare("SELECT id FROM tipos_identificacion WHERE id = :id");
        $tipo->bindValue(':id', (int) $datos['id_tipo_identificacion'], PDO::PARAM_INT);
        $tipo->execute();
        if (!$tipo->fetch()) {
            return 'El tipo de documento no es válido';
        }

        if (empty($datos['primer_nombre']) || empty($datos['primer_apellido'])) {
            return 'Escribe tu primer nombre y tu primer apellido';
        }
        if (empty($datos['telefono'])) {
            return 'Escribe tu teléfono';
        }
        if (empty($datos['fecha_nacimiento']) || strtotime($datos['fecha_nacimiento']) >= strtotime(date('Y-m-d'))) {
            return 'Escribe una fecha de nacimiento válida';
        }
        if (empty($datos['id_genero'])) {
            return 'Escoge tu género';
        }
        $genero = $db->prepare("SELECT id FROM generos WHERE id = :id");
        $genero->bindValue(':id', (int) $datos['id_genero'], PDO::PARAM_INT);
        $genero->execute();
        if (!$genero->fetch()) {
            return 'El género no es válido';
        }

        return null;
    }

    /**
     * Parentescos unicos (parametro autoregistro_parentescos_unicos, ids
     * separados por coma) que cada estudiante ya tiene con un acudiente activo.
     *
     * @param PDO         $db
     * @param array       $idsEstudiantes
     * @param string|null $idPersonaExcluir no cuenta los de esta persona
     * @return array [id_estudiante => [id_tipo_acudiente, ...]]
     */
    private static function parentescosOcupados(PDO $db, $idsEstudiantes, $idPersonaExcluir = null)
    {
        $unicos = self::parentescosUnicos($db);
        $idsEstudiantes = array_values(array_unique(array_filter($idsEstudiantes)));
        if (count($unicos) === 0 || count($idsEstudiantes) === 0) {
            return array();
        }

        $marcasEst = implode(',', array_fill(0, count($idsEstudiantes), '?'));
        $marcasTipo = implode(',', array_fill(0, count($unicos), '?'));
        $sql = "SELECT DISTINCT id_estudiante, id_tipo_acudiente
                FROM acudientes
                WHERE activo = 1
                AND id_estudiante IN ($marcasEst)
                AND id_tipo_acudiente IN ($marcasTipo)
                AND id_tenant = ?";
        $valores = array_merge($idsEstudiantes, $unicos, array(TenantContext::id()));
        if ($idPersonaExcluir) {
            $sql .= " AND id_persona <> ?";
            $valores[] = $idPersonaExcluir;
        }

        $sentence = $db->prepare($sql);
        $sentence->execute($valores);

        $resultado = array();
        foreach ($sentence->fetchAll() as $fila) {
            $resultado[$fila['id_estudiante']][] = (int) $fila['id_tipo_acudiente'];
        }
        return $resultado;
    }

    private static function parentescosUnicos(PDO $db)
    {
        $valor = self::configTexto($db, 'autoregistro_parentescos_unicos');
        if (!$valor) {
            return array();
        }
        $ids = array_map('intval', array_filter(array_map('trim', explode(',', $valor)), 'is_numeric'));
        return array_values(array_unique(array_filter($ids)));
    }

    // =================================================================
    // Personas, usuarios y documento
    // =================================================================

    /**
     * Estado del documento en el jardin, sin datos personales.
     */
    private static function estadoDocumento(PDO $db, $numero)
    {
        $persona = self::buscarPersonaPorNumero($db, $numero);
        $usuario = $persona ? self::buscarUsuarioPorPersona($db, $persona['id']) : null;

        return array(
            'existe' => $persona !== null,
            'tiene_usuario' => $usuario !== null,
            'usuario' => $usuario ? $usuario['usuario'] : null,
            'usuario_activo' => $usuario ? ((int) $usuario['activo'] === 1) : null,
        );
    }

    /**
     * Busqueda solo por numero, igual que Personas: el indice unico es
     * (id_tenant, numero_identificacion).
     */
    private static function buscarPersonaPorNumero(PDO $db, $numero)
    {
        $sentence = $db->prepare("SELECT id FROM personas WHERE numero_identificacion = :numero AND id_tenant = :id_tenant LIMIT 1");
        $sentence->bindValue(':numero', $numero);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? $fila : null;
    }

    private static function buscarUsuarioPorPersona(PDO $db, $idPersona)
    {
        $sentence = $db->prepare("SELECT id, usuario, activo, acceso_portal_padres FROM usuarios
                                  WHERE id_persona = :id_persona AND id_tenant = :id_tenant LIMIT 1");
        $sentence->bindValue(':id_persona', $idPersona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? $fila : null;
    }

    private static function crearPersona(PDO $db, $numero, $datos)
    {
        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO personas (id, id_tenant, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido,
                                                        id_tipo_identificacion, numero_identificacion, fecha_nacimiento, id_genero,
                                                        correo_electronico, telefono)
                                  VALUES (:id, :id_tenant, :primer_nombre, :segundo_nombre, :primer_apellido, :segundo_apellido,
                                          :id_tipo_identificacion, :numero_identificacion, :fecha_nacimiento, :id_genero,
                                          :correo_electronico, :telefono)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':primer_nombre', $datos['primer_nombre']);
        $sentence->bindValue(':segundo_nombre', $datos['segundo_nombre']);
        $sentence->bindValue(':primer_apellido', $datos['primer_apellido']);
        $sentence->bindValue(':segundo_apellido', $datos['segundo_apellido']);
        $sentence->bindValue(':id_tipo_identificacion', (int) $datos['id_tipo_identificacion'], PDO::PARAM_INT);
        $sentence->bindValue(':numero_identificacion', $numero);
        $sentence->bindValue(':fecha_nacimiento', $datos['fecha_nacimiento']);
        $sentence->bindValue(':id_genero', $datos['id_genero']);
        $sentence->bindValue(':correo_electronico', $datos['correo_electronico']);
        $sentence->bindValue(':telefono', $datos['telefono']);
        $sentence->execute();

        return $id;
    }

    /**
     * A una persona existente solo se le llenan los campos vacios, para no
     * pisar lo que el jardin ya tenia registrado.
     */
    private static function completarPersona(PDO $db, $idPersona, $datos)
    {
        $campos = array('primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido',
                        'fecha_nacimiento', 'id_genero', 'correo_electronico', 'telefono');

        foreach ($campos as $campo) {
            if ($datos[$campo] === null || $datos[$campo] === '') {
                continue;
            }
            $sentence = $db->prepare("UPDATE personas SET $campo = :valor
                                      WHERE id = :id AND id_tenant = :id_tenant AND ($campo IS NULL OR $campo = '')");
            $sentence->bindValue(':valor', $datos[$campo]);
            $sentence->bindValue(':id', $idPersona);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
        }
    }

    private static function nombrePersonaPorId(PDO $db, $idPersona)
    {
        $sentence = $db->prepare("SELECT TRIM(CONCAT_WS(' ', primer_nombre, segundo_nombre, primer_apellido, segundo_apellido)) AS nombre
                                  FROM personas WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id', $idPersona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? $fila['nombre'] : null;
    }

    /**
     * Copia la foto temporal a los documentos de la persona con el tipo
     * parametrizado en autoregistro_codigo_tipo_documento. Si el tipo no
     * existe, o no permite varios y la persona ya tiene uno, no se guarda y
     * se devuelve la observacion.
     *
     * @return array [id_documento, ruta_copia, observacion]
     */
    private static function guardarDocumento(PDO $db, $idPersona, $idUsuario, $idIntento)
    {
        $respuesta = array('id_documento' => null, 'ruta_copia' => null, 'observacion' => null);

        $codigo = self::configTexto($db, 'autoregistro_codigo_tipo_documento');
        $tipo = null;
        if ($codigo) {
            $sentence = $db->prepare("SELECT id, permite_multiples FROM tipos_documentos
                                      WHERE codigo = :codigo AND id_tenant = :id_tenant AND activo = 1 LIMIT 1");
            $sentence->bindValue(':codigo', $codigo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $tipo = $sentence->fetch();
        }

        if (!$tipo) {
            $respuesta['observacion'] = 'No se guardó la foto del documento: no existe el tipo documental configurado';
            return $respuesta;
        }

        if ((int) $tipo['permite_multiples'] === 0) {
            $sentence = $db->prepare("SELECT id FROM documentos_personas
                                      WHERE id_persona = :id_persona AND id_tipo_documento = :id_tipo AND id_tenant = :id_tenant AND activo = 1
                                      LIMIT 1");
            $sentence->bindValue(':id_persona', $idPersona);
            $sentence->bindValue(':id_tipo', $tipo['id']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            if ($sentence->fetch()) {
                $respuesta['observacion'] = 'No se guardó la foto del documento: la persona ya tenía uno registrado';
                return $respuesta;
            }
        }

        $directorio = UploadHelper::getUploadPath('documentos_personas') . $idPersona . '/';
        UploadHelper::ensureDirectoryExists($directorio);

        // La extension sale del contenido real de la foto temporal.
        $rutaTemporal = self::rutaTemporal($idIntento);
        $infoImagen = @getimagesize($rutaTemporal);
        $extension = ($infoImagen && $infoImagen['mime'] === 'image/png') ? 'png' : 'jpg';

        $nombreArchivo = time() . '_' . uniqid() . '.' . $extension;
        $rutaCompleta = $directorio . $nombreArchivo;
        if (!copy($rutaTemporal, $rutaCompleta)) {
            $respuesta['observacion'] = 'No se pudo copiar la foto del documento';
            return $respuesta;
        }

        $idDocumento = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO documentos_personas (id, id_tenant, id_persona, id_tipo_documento, nombre_archivo,
                                                                   ruta_archivo, tamanio_bytes, observaciones, id_usuario_subio)
                                  VALUES (:id, :id_tenant, :id_persona, :id_tipo_documento, :nombre_archivo,
                                          :ruta_archivo, :tamanio_bytes, :observaciones, :id_usuario_subio)");
        $sentence->bindValue(':id', $idDocumento);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_persona', $idPersona);
        $sentence->bindValue(':id_tipo_documento', $tipo['id']);
        $sentence->bindValue(':nombre_archivo', 'documento-identidad.' . $extension);
        $sentence->bindValue(':ruta_archivo', UploadHelper::getRelativePath('documentos_personas', $idPersona . '/' . $nombreArchivo));
        $sentence->bindValue(':tamanio_bytes', filesize($rutaCompleta), PDO::PARAM_INT);
        $sentence->bindValue(':observaciones', 'Cargado por el acudiente en el autoregistro');
        $sentence->bindValue(':id_usuario_subio', $idUsuario);
        $sentence->execute();

        $respuesta['id_documento'] = $idDocumento;
        $respuesta['ruta_copia'] = $rutaCompleta;
        return $respuesta;
    }

    private static function rutaTemporal($idIntento, $crearCarpeta = false)
    {
        $directorio = UploadHelper::getUploadPath('autoregistro_temp');
        if ($crearCarpeta) {
            UploadHelper::ensureDirectoryExists($directorio);
        }
        return $directorio . $idIntento . '.img';
    }

    private static function existeTemporal($idIntento)
    {
        return file_exists(self::rutaTemporal($idIntento));
    }

    private static function borrarTemporal($idIntento)
    {
        $ruta = self::rutaTemporal($idIntento);
        if (file_exists($ruta)) {
            @unlink($ruta);
        }
    }

    // =================================================================
    // IA
    // =================================================================

    private static function cargarConfigIa(PDO $db)
    {
        $sentence = $db->prepare("SELECT clave, valor FROM ia_configuracion WHERE id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $config = array();
        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $config[$fila['clave']] = $fila['valor'];
        }
        return $config;
    }

    private static function registrarFalloIa(PDO $db, $idIntento, $error)
    {
        EnlacesAutoregistroIntentos::actualizar($db, $idIntento, EnlacesAutoregistroIntentos::PASO_MODO, array(
            'modo' => 'automatico',
            'resultado_ia' => 'fallido',
            'error_ia' => mb_substr((string) $error, 0, 300),
        ));
    }

    /**
     * Mismo contador de mensajes que usan las demas lecturas con IA (controla el
     * limite diario). Los tokens ya no se acumulan en ia_configuracion: el
     * consumo queda en ia_consumos, que registra IaVision::registrarUso.
     * $tokens se conserva en la firma para no cambiar a quien la llama.
     */
    private static function sumarContadoresIa(PDO $db, $tokens)
    {
        $sentence = $db->prepare("UPDATE ia_configuracion SET valor = valor + 1, fecha_actualizacion = NOW()
                                  WHERE clave = 'mensajes_generados_hoy' AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    /**
     * Tipo de identificacion con el que se prellena lo leido por IA. Sale de
     * la sigla parametrizada en autoregistro_sigla_identificacion_ia.
     */
    private static function idTipoIdentificacionIa(PDO $db)
    {
        $sigla = self::configTexto($db, 'autoregistro_sigla_identificacion_ia');
        if (!$sigla) {
            return null;
        }
        $sentence = $db->prepare("SELECT id FROM tipos_identificacion WHERE sigla = :sigla LIMIT 1");
        $sentence->bindValue(':sigla', $sigla);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? (int) $fila['id'] : null;
    }

    /**
     * Datos del codigo de barras leidos en el celular. Se validan igual que
     * lo que escribe el papa a mano. Devuelve null si no sirven.
     */
    private static function leerDatosCodigo(PDO $db, $json)
    {
        if (empty($json)) {
            return null;
        }
        $datos = is_array($json) ? $json : json_decode((string) $json, true);
        if (!is_array($datos)) {
            return null;
        }

        $numero = self::normalizarNumero($datos['numero_identificacion'] ?? '');
        $primerNombre = self::normalizarTexto($datos['primer_nombre'] ?? null);
        $primerApellido = self::normalizarTexto($datos['primer_apellido'] ?? null);
        if ($numero === '' || !$primerNombre || !$primerApellido) {
            return null;
        }

        return array(
            'id_tipo_identificacion' => self::idTipoIdentificacionIa($db),
            'numero_identificacion' => $numero,
            'primer_nombre' => mb_substr($primerNombre, 0, 100),
            'segundo_nombre' => mb_substr((string) self::normalizarTexto($datos['segundo_nombre'] ?? null), 0, 100) ?: null,
            'primer_apellido' => mb_substr($primerApellido, 0, 100),
            'segundo_apellido' => mb_substr((string) self::normalizarTexto($datos['segundo_apellido'] ?? null), 0, 100) ?: null,
            'fecha_nacimiento' => self::normalizarFecha($datos['fecha_nacimiento'] ?? null),
            'id_genero' => self::idGeneroPorInicial($db, $datos['sexo'] ?? null),
        );
    }

    /**
     * El codigo de barras trae el sexo como M o F; se busca el genero cuyo
     * nombre empieza por esa letra.
     */
    private static function idGeneroPorInicial(PDO $db, $letra)
    {
        $letra = strtoupper(trim((string) $letra));
        if (!in_array($letra, array('M', 'F'), true)) {
            return null;
        }
        $sentence = $db->prepare("SELECT id FROM generos WHERE UPPER(LEFT(nombre, 1)) = :letra ORDER BY id LIMIT 1");
        $sentence->bindValue(':letra', $letra);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? (int) $fila['id'] : null;
    }

    private static function idGeneroPorNombre(PDO $db, $nombre)
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '') {
            return null;
        }
        $sentence = $db->prepare("SELECT id FROM generos WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1");
        $sentence->bindValue(':nombre', $nombre);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? (int) $fila['id'] : null;
    }

    // =================================================================
    // Configuracion y utilidades
    // =================================================================

    private static function configTexto(PDO $db, $clave)
    {
        $sentence = $db->prepare("SELECT valor_texto FROM configuracion_global WHERE clave = :clave AND id_tenant = :id_tenant LIMIT 1");
        $sentence->bindValue(':clave', $clave);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $valor = $sentence->fetchColumn();
        return ($valor === false || $valor === null) ? null : trim($valor);
    }

    private static function configNumero(PDO $db, $clave, $defecto)
    {
        $sentence = $db->prepare("SELECT valor_numero FROM configuracion_global WHERE clave = :clave AND id_tenant = :id_tenant LIMIT 1");
        $sentence->bindValue(':clave', $clave);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $valor = $sentence->fetchColumn();
        return ($valor === false || $valor === null) ? $defecto : (float) $valor;
    }

    /**
     * URL base del portal de padres (configuracion_global.url_portal_padres).
     * Se descarta lo que venga desde el '#', por si pegaron la URL del login.
     */
    private static function urlBasePortal(PDO $db)
    {
        $url = self::configTexto($db, 'url_portal_padres');
        if (!$url) {
            return null;
        }
        $posicion = strpos($url, '#');
        if ($posicion !== false) {
            $url = substr($url, 0, $posicion);
        }
        return rtrim($url, '/');
    }

    private static function armarUrl($urlBase, $idEnlace)
    {
        if (!$urlBase) {
            return null;
        }
        return $urlBase . '/#/autoregistro?inst=' . rawurlencode(TenantContext::codigo()) . '&e=' . rawurlencode($idEnlace);
    }

    private static function textoEstado($fila)
    {
        if ((int) $fila['activo'] !== 1) {
            return 'Inactivo';
        }
        return (int) $fila['vencido'] === 1 ? 'Vencido' : 'Vigente';
    }

    /**
     * Acepta 'Y-m-d H:i', 'Y-m-d\TH:i' (datetime-local) o con segundos.
     */
    private static function normalizarFechaHora($valor)
    {
        $valor = trim(str_replace('T', ' ', (string) $valor));
        foreach (array('Y-m-d H:i:s', 'Y-m-d H:i') as $formato) {
            $fecha = DateTime::createFromFormat($formato, $valor);
            if ($fecha && $fecha->format($formato) === $valor) {
                return $fecha->format('Y-m-d H:i:s');
            }
        }
        return null;
    }

    private static function normalizarFecha($valor)
    {
        $valor = trim((string) $valor);
        $fecha = DateTime::createFromFormat('Y-m-d', $valor);
        return ($fecha && $fecha->format('Y-m-d') === $valor) ? $valor : null;
    }

    /**
     * Solo letras, numeros y guion; sin puntos ni espacios. Maximo 20.
     */
    private static function normalizarNumero($valor)
    {
        $valor = preg_replace('/[\s\.,]/', '', (string) $valor);
        if ($valor === '' || strlen($valor) > 20 || !preg_match('/^[A-Za-z0-9\-]+$/', $valor)) {
            return '';
        }
        return strtoupper($valor);
    }

    /**
     * Quita espacios sobrantes y convierte la cadena vacia en NULL, igual que
     * Personas, para que el nombre completo no quede con espacios dobles.
     */
    private static function normalizarTexto($valor)
    {
        if ($valor === null) {
            return null;
        }
        $valor = trim(preg_replace('/\s+/', ' ', (string) $valor));
        return $valor === '' ? null : $valor;
    }

    private static function nombreCompleto($datos)
    {
        $partes = array_filter(array(
            $datos['primer_nombre'] ?? null,
            $datos['segundo_nombre'] ?? null,
            $datos['primer_apellido'] ?? null,
            $datos['segundo_apellido'] ?? null,
        ));
        return count($partes) > 0 ? implode(' ', $partes) : null;
    }
}
