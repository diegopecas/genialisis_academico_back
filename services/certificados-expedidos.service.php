<?php

/**
 * Certificados expedidos.
 *
 * Tabla principal del modulo, asi que aqui vive la logica: evaluacion de las
 * reglas financieras, armado de los datos, resolucion de la plantilla y
 * consecutivo.
 *
 * El documento se arma en el backend y se guarda ya resuelto en
 * `contenido_html`. El front solo lo dibuja en PDF. Asi una reimpresion sale
 * identica aunque despues cambie la plantilla, la tarifa o el saldo, que es
 * justo lo que debe pasar con un paz y salvo.
 *
 * Reglas financieras (CertificadosConfiguracion.regla):
 *   libre            -> no exige nada
 *   al_dia           -> sin cuentas vencidas con saldo
 *   al_dia_productos -> sin cuentas vencidas con saldo de los productos configurados
 * El paz y salvo ignora eso y exige saldo total en cero, vencido o no.
 *
 * Una cuenta esta vencida cuando su fecha ya paso: la fecha de la cuenta es la
 * fecha de vencimiento del producto, sin dias de gracia.
 */
class CertificadosExpedidos
{
    /**
     * Historial de certificados de un estudiante. Incluye los del acudiente,
     * porque se piden desde la ficha del estudiante.
     *
     * GET /certificados-expedidos/estudiante/:idEstudiante
     */
    public static function getByEstudiante($idEstudiante)
    {
        JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT ce.id, ce.anio, ce.numero, ce.clave_certificado,
                       ce.id_estudiante, ce.id_acudiente, ce.anio_certificado,
                       ce.fecha_desde, ce.fecha_hasta, ce.origen, ce.compartido,
                       ce.fecha_expedicion,
                       TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.segundo_nombre,
                                      pa.primer_apellido, pa.segundo_apellido)) AS acudiente_nombre
                FROM certificados_expedidos ce
                LEFT JOIN acudientes a ON a.id = ce.id_acudiente
                LEFT JOIN personas pa ON pa.id = a.id_persona
                WHERE ce.id_tenant = :id_tenant AND ce.id_estudiante = :id_estudiante
                ORDER BY ce.fecha_expedicion DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $idEstudiante);
            $sentence->execute();

            $filas = $sentence->fetchAll(PDO::FETCH_ASSOC);
            foreach ($filas as &$fila) {
                $fila['nombre_certificado'] = isset(CertificadosConfiguracion::$NOMBRES[$fila['clave_certificado']])
                    ? CertificadosConfiguracion::$NOMBRES[$fila['clave_certificado']]
                    : $fila['clave_certificado'];
            }

            Flight::json($filas);
        } catch (Exception $e) {
            error_log('Error en CertificadosExpedidos::getByEstudiante: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener los certificados'], 500);
        }
    }

    /**
     * Un certificado ya expedido, con su HTML, para volver a descargarlo.
     *
     * GET /certificados-expedidos/:id
     */
    public static function getById($id)
    {
        JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT id, anio, numero, clave_certificado, id_estudiante, id_acudiente,
                       anio_certificado, fecha_desde, fecha_hasta, contenido_html,
                       origen, fecha_expedicion
                FROM certificados_expedidos
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $certificado = $sentence->fetch(PDO::FETCH_ASSOC);

            if (!$certificado) {
                Flight::json(['error' => true, 'message' => 'Certificado no encontrado'], 404);
                return;
            }

            Flight::json($certificado);
        } catch (Exception $e) {
            error_log('Error en CertificadosExpedidos::getById: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener el certificado'], 500);
        }
    }

    /**
     * Certificados que se le pueden ofrecer a un estudiante, con el resultado de
     * la regla ya evaluado.
     *
     * El parametro `origen` cambia que se devuelve: desde el portal
     * institucional salen todos (el jardin expide bajo su responsabilidad);
     * desde el portal de padres salen solo los que estan en modo automatico, y
     * cada uno dice si cumple o no y por que.
     *
     * GET /certificados-expedidos/disponibles/:idEstudiante/:origen
     */
    public static function getDisponibles($idEstudiante, $origen)
    {
        JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $configuraciones = self::configuraciones($db);
            $soloAutomaticos = ($origen === 'padres');

            $saldoTotal = self::saldoEstudiante($db, $idEstudiante, false, []);
            $saldoVencido = self::saldoEstudiante($db, $idEstudiante, true, []);

            // Los conceptos que el estudiante realmente tiene pagados. Van en la
            // misma respuesta para no hacer una segunda llamada, y sirven para
            // los dos certificados de pagos.
            $productos = self::productosDelEstudiante($db, $idEstudiante);

            $resultado = [];
            foreach (CertificadosConfiguracion::$CLAVES as $clave) {
                $configuracion = isset($configuraciones[$clave]) ? $configuraciones[$clave] : null;
                $modo = $configuracion ? $configuracion['modo'] : 'manual';
                $activo = $configuracion ? (int) $configuracion['activo'] : 1;

                if ($soloAutomaticos && ($modo !== 'automatico' || $activo !== 1)) {
                    continue;
                }
                if (!$soloAutomaticos && $activo !== 1) {
                    continue;
                }

                // Se evalua en los dos portales. En el institucional el
                // resultado no bloquea: solo sirve para advertirle al jardin
                // antes de expedir un paz y salvo con deuda.
                $evaluacion = self::evaluarRegla($db, $clave, $idEstudiante, $configuracion);

                $resultado[] = [
                    'clave_certificado' => $clave,
                    'nombre' => CertificadosConfiguracion::$NOMBRES[$clave],
                    'modo' => $modo,
                    'regla' => $configuracion ? $configuracion['regla'] : 'libre',
                    'formato' => isset($configuracion['formato']) ? $configuracion['formato'] : 'recibo',
                    'mostrar_conceptos' => isset($configuracion['mostrar_conceptos']) ? (int) $configuracion['mostrar_conceptos'] : 1,
                    'es_de_pagos' => CertificadosConfiguracion::esDePagos($clave) ? 1 : 0,
                    'cumple' => $evaluacion['cumple'] ? 1 : 0,
                    'mensaje' => $evaluacion['mensaje'],
                    'saldo_pendiente' => $evaluacion['saldo'],
                    'saldo_total' => $saldoTotal,
                    'saldo_vencido' => $saldoVencido
                ];
            }

            Flight::json(['certificados' => $resultado, 'productos' => $productos]);
        } catch (Exception $e) {
            error_log('Error en CertificadosExpedidos::getDisponibles: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener los certificados disponibles'], 500);
        }
    }

    /**
     * Productos que aparecen en los pagos del estudiante, con su clasificacion.
     * Se ofrecen solo estos para filtrar: mostrar el catalogo completo obliga a
     * buscar entre conceptos que ese estudiante nunca pago.
     */
    private static function productosDelEstudiante($db, $idEstudiante)
    {
        $sentence = $db->prepare("
            SELECT DISTINCT ps.id, ps.nombre, ps.id_periodicidad_cobro,
                   ps.id_clasificacion_productos_servicios,
                   cl.nombre AS nombre_clasificacion,
                   pc.nombre AS nombre_periodicidad
            FROM cuenta_pagada cp
            INNER JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
            INNER JOIN cuentas_por_cobrar cc ON cc.id = cp.id_cuenta_por_cobrar
            INNER JOIN productos_servicios ps ON ps.id = cc.id_producto_servicio
            LEFT JOIN clasificacion_productos_servicios cl
                   ON cl.id = ps.id_clasificacion_productos_servicios
            LEFT JOIN periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
            WHERE pr.id_tenant = :id_tenant
              AND COALESCE(pr.anulado, 0) = 0
              AND pr.id_estudiante = :id_estudiante
            ORDER BY cl.nombre, ps.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $idEstudiante);
        $sentence->execute();

        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Anios lectivos en los que el estudiante estuvo matriculado. Alimenta el
     * combo de la constancia de anio cursado, para no ofrecer anios en los que
     * no hay nada que certificar.
     *
     * GET /certificados-expedidos/anios/:idEstudiante
     */
    public static function getAnios($idEstudiante)
    {
        JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT DISTINCT exg.anio
                FROM estudiantes_x_grupos exg
                WHERE exg.id_tenant = :id_tenant AND exg.id_estudiante = :id_estudiante
                ORDER BY exg.anio DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $idEstudiante);
            $sentence->execute();

            Flight::json($sentence->fetchAll(PDO::FETCH_COLUMN));
        } catch (Exception $e) {
            error_log('Error en CertificadosExpedidos::getAnios: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener los años'], 500);
        }
    }

    /**
     * Expide un certificado: valida la regla cuando viene del portal de padres,
     * arma el documento, lo guarda y lo devuelve.
     *
     * POST /certificados-expedidos
     * Body: clave_certificado, id_estudiante, id_acudiente, anio_certificado,
     *       fecha_desde, fecha_hasta, origen
     */
    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db();

        try {
            $datos = Flight::request()->data;
            $clave = isset($datos['clave_certificado']) ? $datos['clave_certificado'] : null;
            $idEstudiante = isset($datos['id_estudiante']) ? $datos['id_estudiante'] : null;
            $idAcudiente = isset($datos['id_acudiente']) ? $datos['id_acudiente'] : null;
            $anioCertificado = isset($datos['anio_certificado']) && $datos['anio_certificado'] !== ''
                ? (int) $datos['anio_certificado'] : null;
            $fechaDesde = isset($datos['fecha_desde']) && $datos['fecha_desde'] !== '' ? $datos['fecha_desde'] : null;
            $fechaHasta = isset($datos['fecha_hasta']) && $datos['fecha_hasta'] !== '' ? $datos['fecha_hasta'] : null;
            $origen = isset($datos['origen']) && $datos['origen'] === 'padres' ? 'padres' : 'institucional';
            $dirigidoA = isset($datos['dirigido_a']) ? trim($datos['dirigido_a']) : '';
            // Lista de productos a certificar. Vacia = todos los conceptos.
            $productos = isset($datos['productos']) && is_array($datos['productos'])
                ? array_values(array_unique($datos['productos'])) : [];
            // Formato y detalle llegan de la pantalla; si no vienen, manda el
            // parametro del jardin.
            $formato = isset($datos['formato']) && $datos['formato'] !== '' ? $datos['formato'] : null;
            $mostrarConceptos = isset($datos['mostrar_conceptos']) ? (int) $datos['mostrar_conceptos'] : null;

            if (!in_array($clave, CertificadosConfiguracion::$CLAVES, true)) {
                Flight::json(['error' => true, 'message' => 'Certificado no válido'], 400);
                return;
            }
            if (!$idEstudiante) {
                Flight::json(['error' => true, 'message' => 'Debe indicar el estudiante'], 400);
                return;
            }
            if ($clave === 'constancia_anio_cursado' && !$anioCertificado) {
                Flight::json(['error' => true, 'message' => 'Debe indicar el año lectivo'], 400);
                return;
            }
            if (($clave === 'pagos_estudiante' || $clave === 'pagos_acudiente') && (!$fechaDesde || !$fechaHasta)) {
                Flight::json(['error' => true, 'message' => 'Debe indicar el rango de fechas'], 400);
                return;
            }
            if ($clave === 'pagos_acudiente' && !$idAcudiente) {
                Flight::json(['error' => true, 'message' => 'Debe indicar el acudiente'], 400);
                return;
            }

            $configuraciones = self::configuraciones($db);
            $configuracion = isset($configuraciones[$clave]) ? $configuraciones[$clave] : null;

            // Desde el portal de padres manda la configuracion. Desde el
            // institucional el jardin expide bajo su responsabilidad.
            if ($origen === 'padres') {
                if (!$configuracion || $configuracion['modo'] !== 'automatico' || (int) $configuracion['activo'] !== 1) {
                    Flight::json(['error' => true, 'message' => 'Este certificado no está disponible en el portal'], 403);
                    return;
                }

                // El acudiente nunca llega del cliente: se resuelve desde el
                // usuario autenticado, para que nadie pida el certificado
                // tributario de otra persona cambiando el id en la peticion.
                if ($clave === 'pagos_acudiente') {
                    $idAcudiente = self::acudienteDelUsuario($db, $userData, $idEstudiante);
                    if (!$idAcudiente) {
                        Flight::json([
                            'error' => true,
                            'message' => 'No estás registrado como acudiente de este estudiante'
                        ], 403);
                        return;
                    }
                }

                $evaluacion = self::evaluarRegla($db, $clave, $idEstudiante, $configuracion);
                if (!$evaluacion['cumple']) {
                    Flight::json(['error' => true, 'message' => $evaluacion['mensaje']], 403);
                    return;
                }

                // El acudiente no escoge conceptos ni formato: manda lo que el
                // jardin dejo configurado.
                $productos = [];
                $formato = null;
                $mostrarConceptos = null;
                $dirigidoA = '';
            }

            if ($formato === null) {
                $formato = isset($configuracion['formato']) ? $configuracion['formato'] : 'recibo';
            }
            if ($mostrarConceptos === null) {
                $mostrarConceptos = isset($configuracion['mostrar_conceptos'])
                    ? (int) $configuracion['mostrar_conceptos'] : 1;
            }
            if (!in_array($formato, CertificadosConfiguracion::$FORMATOS, true)) {
                Flight::json(['error' => true, 'message' => 'Formato no válido'], 400);
                return;
            }

            if (!CertificadosConfiguracion::esDePagos($clave)) {
                $formato = 'recibo';
                $mostrarConceptos = 1;
                $productos = [];
            }

            $variables = self::armarVariables($db, $clave, $idEstudiante, $idAcudiente, $anioCertificado,
                                              $fechaDesde, $fechaHasta, $productos, $formato,
                                              $mostrarConceptos, $dirigidoA);
            if (isset($variables['__error'])) {
                Flight::json(['error' => true, 'message' => $variables['__error']], 400);
                return;
            }

            $plantilla = self::plantillaDe($db, $clave);
            if (!$plantilla) {
                Flight::json([
                    'error' => true,
                    'message' => 'No hay plantilla configurada para este certificado'
                ], 404);
                return;
            }

            $db->beginTransaction();

            $anio = (int) date('Y');
            $numero = self::siguienteNumero($db, $anio);
            $variables['{{numero_certificado}}'] = self::formatearNumero($anio, $numero);

            $contenidoHtml = self::resolverPlantilla($plantilla, $variables, $formato);

            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO certificados_expedidos
                    (id, id_tenant, anio, numero, clave_certificado, id_estudiante, id_acudiente,
                     anio_certificado, fecha_desde, fecha_hasta, formato, mostrar_conceptos,
                     dirigido_a, contenido_html, origen, compartido, fecha_compartido, id_usuario)
                VALUES (:id, :id_tenant, :anio, :numero, :clave, :id_estudiante, :id_acudiente,
                        :anio_certificado, :fecha_desde, :fecha_hasta, :formato, :conceptos,
                        :dirigido_a, :contenido_html, :origen, :compartido, :fecha_compartido, :id_usuario)
            ");
            $idUsuario = isset($userData->id) ? $userData->id : null;
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':anio', $anio, PDO::PARAM_INT);
            $sentence->bindValue(':numero', $numero, PDO::PARAM_INT);
            $sentence->bindParam(':clave', $clave);
            $sentence->bindParam(':id_estudiante', $idEstudiante);
            $sentence->bindParam(':id_acudiente', $idAcudiente);
            $sentence->bindValue(':anio_certificado', $anioCertificado, $anioCertificado === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $sentence->bindParam(':fecha_desde', $fechaDesde);
            $sentence->bindParam(':fecha_hasta', $fechaHasta);
            $sentence->bindParam(':formato', $formato);
            $sentence->bindValue(':conceptos', $mostrarConceptos, PDO::PARAM_INT);
            $sentence->bindParam(':dirigido_a', $dirigidoA);
            $sentence->bindParam(':contenido_html', $contenidoHtml);
            $sentence->bindParam(':origen', $origen);
            // Lo que el acudiente genera ya es suyo; lo del jardin se comparte aparte.
            $compartido = ($origen === 'padres') ? 1 : 0;
            $fechaCompartido = $compartido ? date('Y-m-d H:i:s') : null;
            $sentence->bindValue(':compartido', $compartido, PDO::PARAM_INT);
            $sentence->bindParam(':fecha_compartido', $fechaCompartido);
            $sentence->bindParam(':id_usuario', $idUsuario);
            $sentence->execute();

            // Queda el detalle de que se certifico, para que el historico se
            // explique solo.
            if (count($productos) > 0) {
                $insertar = $db->prepare("
                    INSERT INTO certificados_expedidos_productos
                        (id, id_tenant, id_certificado_expedido, id_producto_servicio)
                    VALUES (:id, :id_tenant, :id_certificado, :id_producto)
                ");
                foreach ($productos as $idProducto) {
                    $idFila = Uuid::generar();
                    $insertar->bindParam(':id', $idFila);
                    $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $insertar->bindParam(':id_certificado', $id);
                    $insertar->bindParam(':id_producto', $idProducto);
                    $insertar->execute();
                }
            }

            $db->commit();

            Flight::json([
                'id' => $id,
                'anio' => $anio,
                'numero' => $numero,
                'numero_certificado' => $variables['{{numero_certificado}}'],
                'contenido_html' => $contenidoHtml,
                'advertencia' => isset($variables['__advertencia']) ? $variables['__advertencia'] : null
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en CertificadosExpedidos::new: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al expedir el certificado'], 500);
        }
    }

    /**
     * Marca un certificado ya expedido como disponible para el acudiente en el
     * portal de padres. Sirve para los certificados en modo manual: el jardin
     * igual se los va a hacer llegar, y asi tambien quedan a la mano.
     *
     * PUT /certificados-expedidos/compartir
     * Body: id, compartido (1 o 0)
     */
    public static function compartir()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $datos = Flight::request()->data;
            $id = isset($datos['id']) ? $datos['id'] : null;
            $compartido = isset($datos['compartido']) ? (int) $datos['compartido'] : 1;

            if (!$id) {
                Flight::json(['error' => true, 'message' => 'Debe indicar el certificado'], 400);
                return;
            }

            $db = Flight::db();
            $fecha = $compartido ? date('Y-m-d H:i:s') : null;

            $sentence = $db->prepare("
                UPDATE certificados_expedidos
                SET compartido = :compartido, fecha_compartido = :fecha
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindValue(':compartido', $compartido, PDO::PARAM_INT);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() === 0) {
                Flight::json(['error' => true, 'message' => 'Certificado no encontrado'], 404);
                return;
            }

            // Solo al compartir: dejar de compartir no se avisa.
            if ($compartido === 1) {
                $idUsuario = isset($userData->id) ? $userData->id : null;
                self::notificarCompartido($db, $id, $idUsuario);
            }

            Flight::json(['id' => $id, 'compartido' => $compartido]);
        } catch (Exception $e) {
            error_log('Error en CertificadosExpedidos::compartir: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al compartir el certificado'], 500);
        }
    }

    /**
     * Avisa por el portal de padres que hay un certificado nuevo disponible.
     *
     * El texto sale de la plantilla `certificado_compartido` (tipo mensaje) y,
     * si el tenant no la tiene, de un texto por defecto: el aviso no se pierde
     * por falta de parametrizacion.
     *
     * No se envia WhatsApp ni correo, igual que los avisos automaticos de
     * solicitudes: son de bajo valor para el jardin y de alto volumen.
     */
    private static function notificarCompartido($db, $idCertificado, $idUsuarioEnvio)
    {
        try {
            $sentence = $db->prepare("
                SELECT ce.numero, ce.anio, ce.clave_certificado, ce.id_estudiante,
                       TRIM(CONCAT_WS(' ', pe.primer_nombre, pe.segundo_nombre,
                                      pe.primer_apellido, pe.segundo_apellido)) AS estudiante_nombre
                FROM certificados_expedidos ce
                INNER JOIN estudiantes e ON e.id = ce.id_estudiante
                INNER JOIN personas pe ON pe.id = e.id_persona
                WHERE ce.id = :id AND ce.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $idCertificado);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $certificado = $sentence->fetch(PDO::FETCH_ASSOC);

            if (!$certificado) {
                return;
            }

            // Acudientes del estudiante que ven el portal y tienen usuario.
            $sentence = $db->prepare("
                SELECT a.id_persona, u.id AS id_usuario
                FROM acudientes a
                LEFT JOIN usuarios u ON u.id_persona = a.id_persona
                                    AND u.id_tenant = a.id_tenant
                                    AND u.acceso_portal_padres = 1
                                    AND u.activo = 1
                WHERE a.id_tenant = :id_tenant
                  AND a.id_estudiante = :id_estudiante
                  AND a.ve_en_portal_padres = 1
                  AND COALESCE(a.activo, 1) = 1
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $certificado['id_estudiante']);
            $sentence->execute();

            $destinatarios = $sentence->fetchAll(PDO::FETCH_ASSOC);

            if (count($destinatarios) === 0 || !$idUsuarioEnvio) {
                return;
            }

            $configuracion = self::configuracionGlobal($db);
            $variables = [
                '{nombre_estudiante}' => $certificado['estudiante_nombre'],
                '{nombre_certificado}' => isset(CertificadosConfiguracion::$NOMBRES[$certificado['clave_certificado']])
                    ? CertificadosConfiguracion::$NOMBRES[$certificado['clave_certificado']]
                    : 'certificado',
                '{numero_certificado}' => self::formatearNumero((int) $certificado['anio'], (int) $certificado['numero']),
                '{nombre_colegio}' => self::valor($configuracion, 'institucion_nombre'),
            ];

            $texto = self::textoNotificacion($db, $variables);

            $idNotificacion = Uuid::generar();
            $categoria = self::categoriaGeneral($db);

            $insertar = $db->prepare("
                INSERT INTO notificaciones
                    (id, id_tenant, titulo, cuerpo, id_categoria, id_respuesta_tipo, id_plantilla,
                     criterio_texto, incluir_whatsapp, whatsapp_numero, enviar_correo, id_usuario_envio)
                VALUES (:id, :id_tenant, :titulo, :cuerpo, :id_categoria, NULL, NULL,
                        :criterio_texto, 0, NULL, 0, :id_usuario_envio)
            ");
            $insertar->bindParam(':id', $idNotificacion);
            $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $insertar->bindParam(':titulo', $texto['titulo']);
            $insertar->bindParam(':cuerpo', $texto['cuerpo']);
            $insertar->bindValue(':id_categoria', $categoria);
            $insertar->bindValue(':criterio_texto', 'Certificados');
            $insertar->bindParam(':id_usuario_envio', $idUsuarioEnvio);
            $insertar->execute();

            $insertarDestinatario = $db->prepare("
                INSERT INTO notificaciones_destinatarios
                    (id, id_tenant, id_notificacion, id_estudiante, id_persona, id_usuario)
                VALUES (:id, :id_tenant, :id_notificacion, :id_estudiante, :id_persona, :id_usuario)
            ");

            $usuarios = [];

            foreach ($destinatarios as $destinatario) {
                $idFila = Uuid::generar();
                $insertarDestinatario->bindParam(':id', $idFila);
                $insertarDestinatario->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $insertarDestinatario->bindParam(':id_notificacion', $idNotificacion);
                $insertarDestinatario->bindParam(':id_estudiante', $certificado['id_estudiante']);
                $insertarDestinatario->bindValue(':id_persona', $destinatario['id_persona']);
                $insertarDestinatario->bindValue(':id_usuario', $destinatario['id_usuario']);
                $insertarDestinatario->execute();

                if (!empty($destinatario['id_usuario'])) {
                    $usuarios[] = $destinatario['id_usuario'];
                }
            }

            if (count($usuarios) > 0 && class_exists('PushNotificationService')) {
                $push = new PushNotificationService($db);
                $push->notificarAUsuarios(
                    $usuarios,
                    $texto['titulo'],
                    $texto['cuerpo'],
                    ['id_notificacion' => $idNotificacion, 'tipo' => 'notificacion'],
                    JWTService::PORTAL_PADRES
                );
            }
        } catch (Exception $e) {
            // El aviso es un extra: si falla, el certificado igual quedo compartido.
            error_log('[Certificados] No se pudo notificar el certificado ' . $idCertificado
                . ': ' . $e->getMessage());
        }
    }

    /** Texto del aviso, de la plantilla del tenant o del texto por defecto. */
    private static function textoNotificacion($db, $variables)
    {
        $titulo = 'Nuevo certificado disponible';
        $cuerpo = 'El ' . $variables['{nombre_colegio}'] . ' puso a tu disposición el '
            . $variables['{nombre_certificado}'] . ' de ' . $variables['{nombre_estudiante}']
            . ' (No. ' . $variables['{numero_certificado}'] . '). Puedes descargarlo desde la ficha '
            . 'del estudiante, en la pestaña Certificados.';

        $sentence = $db->prepare("
            SELECT p.contenido
            FROM plantillas p
            INNER JOIN tipos_plantillas tp ON tp.id = p.id_tipo_plantilla
            WHERE p.id_tenant = :id_tenant
              AND tp.codigo = 'mensaje'
              AND p.clave = 'certificado_compartido'
            LIMIT 1
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $contenido = $sentence->fetchColumn();
        $plantilla = $contenido ? json_decode($contenido, true) : null;

        if (is_array($plantilla)) {
            if (!empty($plantilla['titulo'])) {
                $titulo = $plantilla['titulo'];
            }
            if (!empty($plantilla['cuerpo'])) {
                $cuerpo = $plantilla['cuerpo'];
            }
        }

        return [
            'titulo' => strtr($titulo, $variables),
            'cuerpo' => strtr($cuerpo, $variables),
        ];
    }

    /** Categoria del aviso. Sin ella la notificacion igual se crea. */
    private static function categoriaGeneral($db)
    {
        $sentence = $db->prepare("
            SELECT id FROM notificaciones_categorias
            WHERE id_tenant = :id_tenant AND codigo = 'GENERAL' AND activo = 1
            LIMIT 1
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $id = $sentence->fetchColumn();

        return $id ? $id : null;
    }

    /**
     * Certificados que el acudiente puede ver: los que genero el mismo y los
     * que el jardin le compartio.
     *
     * GET /certificados-expedidos/compartidos/:idEstudiante
     */
    public static function getCompartidos($idEstudiante)
    {
        JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT ce.id, ce.anio, ce.numero, ce.clave_certificado,
                       ce.anio_certificado, ce.fecha_desde, ce.fecha_hasta,
                       ce.origen, ce.fecha_expedicion, ce.fecha_compartido
                FROM certificados_expedidos ce
                WHERE ce.id_tenant = :id_tenant
                  AND ce.id_estudiante = :id_estudiante
                  AND ce.compartido = 1
                ORDER BY ce.fecha_expedicion DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $idEstudiante);
            $sentence->execute();

            $filas = $sentence->fetchAll(PDO::FETCH_ASSOC);
            foreach ($filas as &$fila) {
                $fila['nombre_certificado'] = isset(CertificadosConfiguracion::$NOMBRES[$fila['clave_certificado']])
                    ? CertificadosConfiguracion::$NOMBRES[$fila['clave_certificado']]
                    : $fila['clave_certificado'];
            }

            Flight::json($filas);
        } catch (Exception $e) {
            error_log('Error en CertificadosExpedidos::getCompartidos: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener los certificados'], 500);
        }
    }

    // -----------------------------------------------------------------
    // Reglas
    // -----------------------------------------------------------------

    /**
     * Evalua si el estudiante cumple la regla de un certificado.
     * Devuelve cumple, mensaje y el saldo que lo bloquea.
     */
    private static function evaluarRegla($db, $clave, $idEstudiante, $configuracion)
    {
        // El paz y salvo no negocia: saldo total en cero, este vencido o no.
        if ($clave === 'paz_y_salvo') {
            $saldo = self::saldoEstudiante($db, $idEstudiante, false, []);
            if ($saldo > 0) {
                return [
                    'cumple' => false,
                    'mensaje' => self::mensajeDe($configuracion, 'Para descargar el paz y salvo debes estar al día con todos tus pagos.'),
                    'saldo' => $saldo
                ];
            }
            return ['cumple' => true, 'mensaje' => null, 'saldo' => 0];
        }

        $regla = $configuracion ? $configuracion['regla'] : 'libre';

        if ($regla === 'libre') {
            return ['cumple' => true, 'mensaje' => null, 'saldo' => 0];
        }

        $productos = [];
        if ($regla === 'al_dia_productos') {
            $sentence = $db->prepare("
                SELECT id_producto_servicio
                FROM certificados_configuracion_productos
                WHERE id_certificado_config = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindValue(':id', $configuracion['id']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $productos = $sentence->fetchAll(PDO::FETCH_COLUMN);

            // Sin productos configurados la regla no puede evaluarse; se deja
            // pasar en vez de bloquear por una configuracion incompleta.
            if (count($productos) === 0) {
                return ['cumple' => true, 'mensaje' => null, 'saldo' => 0];
            }
        }

        $saldo = self::saldoEstudiante($db, $idEstudiante, true, $productos);

        if ($saldo > 0) {
            return [
                'cumple' => false,
                'mensaje' => self::mensajeDe($configuracion, 'Para descargar este certificado debes estar al día con tus pagos.'),
                'saldo' => $saldo
            ];
        }

        return ['cumple' => true, 'mensaje' => null, 'saldo' => 0];
    }

    /**
     * Saldo del estudiante. Con $soloVencidas solo cuenta las cuentas cuya
     * fecha ya paso; la fecha de la cuenta es la de vencimiento del producto.
     * Con $productos filtra a esos productos.
     */
    private static function saldoEstudiante($db, $idEstudiante, $soloVencidas, $productos)
    {
        $filtroProductos = '';
        if (count($productos) > 0) {
            $marcadores = implode(',', array_fill(0, count($productos), '?'));
            $filtroProductos = " AND c.id_producto_servicio IN ($marcadores) ";
        }

        $filtroVencidas = $soloVencidas ? ' AND c.fecha < CURDATE() ' : '';

        $sql = "
            SELECT COALESCE(SUM(c.valor - COALESCE(ap.aplicado, 0)), 0) AS saldo
            FROM cuentas_por_cobrar c
            LEFT JOIN (
                SELECT cp.id_cuenta_por_cobrar, SUM(cp.valor_aplicado) AS aplicado
                FROM cuenta_pagada cp
                INNER JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
                WHERE cp.id_tenant = ? AND COALESCE(pr.anulado, 0) = 0
                GROUP BY cp.id_cuenta_por_cobrar
            ) ap ON ap.id_cuenta_por_cobrar = c.id
            WHERE c.id_tenant = ?
              AND c.id_persona = (SELECT e.id_persona FROM estudiantes e WHERE e.id = ?)
              AND COALESCE(c.anulado, 0) = 0
              AND (c.valor - COALESCE(ap.aplicado, 0)) > 0
              $filtroVencidas
              $filtroProductos
        ";

        $parametros = [TenantContext::id(), TenantContext::id(), $idEstudiante];
        foreach ($productos as $idProducto) {
            $parametros[] = $idProducto;
        }

        $sentence = $db->prepare($sql);
        $sentence->execute($parametros);

        return round((float) $sentence->fetchColumn(), 2);
    }

    private static function mensajeDe($configuracion, $porDefecto)
    {
        if ($configuracion && !empty($configuracion['mensaje_no_cumple'])) {
            return $configuracion['mensaje_no_cumple'];
        }
        return $porDefecto;
    }

    /**
     * Acudiente que corresponde al usuario autenticado del portal de padres,
     * dentro de ese estudiante. Se resuelve por la persona del usuario, no por
     * lo que mande el cliente.
     */
    private static function acudienteDelUsuario($db, $userData, $idEstudiante)
    {
        if (!isset($userData->id_persona) || !$userData->id_persona) {
            return null;
        }

        $sentence = $db->prepare("
            SELECT a.id
            FROM acudientes a
            WHERE a.id_tenant = :id_tenant
              AND a.id_persona = :id_persona
              AND a.id_estudiante = :id_estudiante
            LIMIT 1
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_persona', $userData->id_persona);
        $sentence->bindParam(':id_estudiante', $idEstudiante);
        $sentence->execute();

        $id = $sentence->fetchColumn();

        return $id ? $id : null;
    }

    private static function configuraciones($db)
    {
        $sentence = $db->prepare("
            SELECT id, clave_certificado, modo, regla, formato, mostrar_conceptos,
                   mensaje_no_cumple, activo
            FROM certificados_configuracion
            WHERE id_tenant = :id_tenant
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $configuraciones = [];
        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $configuraciones[$fila['clave_certificado']] = $fila;
        }

        return $configuraciones;
    }

    // -----------------------------------------------------------------
    // Datos del certificado
    // -----------------------------------------------------------------

    /**
     * Arma el diccionario de variables con el que se resuelve la plantilla.
     * Las claves llegan con las llaves puestas para reemplazar de una.
     */
    private static function armarVariables($db, $clave, $idEstudiante, $idAcudiente, $anioCertificado,
                                          $fechaDesde, $fechaHasta, $productos = [], $formato = 'recibo',
                                          $mostrarConceptos = 1, $dirigidoA = '')
    {
        $configuracion = self::configuracionGlobal($db);
        $estudiante = self::datosEstudiante($db, $idEstudiante, $anioCertificado);

        if (!$estudiante) {
            return ['__error' => 'Estudiante no encontrado'];
        }

        $esFemenino = (strtolower((string) $estudiante['genero']) === 'femenino');

        $variables = [
            '{{institucion_nombre}}' => self::valor($configuracion, 'institucion_nombre'),
            '{{institucion_nit}}' => self::valor($configuracion, 'institucion_nit'),
            '{{institucion_direccion}}' => self::valor($configuracion, 'institucion_direccion'),
            '{{resolucion}}' => self::valor($configuracion, 'institucion_resolucion'),
            '{{ciudad}}' => self::ciudad($configuracion),
            '{{fecha_larga}}' => self::fechaLarga(date('Y-m-d')),
            '{{ciudad_fecha}}' => trim(self::ciudad($configuracion) . ', ' . self::fechaLarga(date('Y-m-d')), ' ,'),
            '{{anio}}' => date('Y'),
            '{{estudiante_nombre}}' => $estudiante['nombre_completo'],
            '{{estudiante_tipo_documento}}' => $estudiante['tipo_identificacion'],
            '{{estudiante_documento}}' => $estudiante['numero_identificacion'],
            '{{estudiante_articulo_el}}' => $esFemenino ? 'La' : 'El',
            '{{estudiante_articulo_del}}' => $esFemenino ? 'de la' : 'del',
            '{{estudiante_identificado}}' => $esFemenino ? 'identificada' : 'identificado',
            '{{grupo_nombre}}' => $estudiante['nombre_grupo'],
            '{{grado_nombre}}' => $estudiante['nombre_grado'] ? $estudiante['nombre_grado'] : $estudiante['nombre_grupo'],
            '{{anio_certificado}}' => $anioCertificado ? (string) $anioCertificado : '',
            '{{fecha_ingreso_larga}}' => self::fechaIngresoLarga($estudiante['fecha_ingreso']),
            '{{firmante_nombre}}' => self::firmanteNombre($configuracion),
            '{{firmante_cargo}}' => self::valor($configuracion, 'certificado_firmante_cargo'),
            '{{acudiente_nombre}}' => '',
            '{{acudiente_tipo_documento}}' => '',
            '{{acudiente_documento}}' => '',
            '{{estudiantes_nombres}}' => $estudiante['nombre_completo'],
            '{{total_pagado}}' => '',
            '{{total_pagado_letras}}' => '',
            '{{fecha_desde}}' => $fechaDesde ? self::fechaLarga($fechaDesde) : '',
            '{{fecha_hasta}}' => $fechaHasta ? self::fechaLarga($fechaHasta) : '',
            '{{tabla_pagos}}' => '',
            '{{tabla_cuentas_pendientes}}' => '',
            '{{conceptos_certificados}}' => self::nombresProductos($db, $productos),
            '{{pie_contacto}}' => self::lineaContacto($configuracion),
            // Si el jardin no indica destinatario, el certificado queda abierto.
            '{{dirigido_a}}' => $dirigidoA !== '' ? 'Señores: ' . $dirigidoA : 'A QUIEN INTERESE'
        ];

        if ($clave === 'pagos_acudiente') {
            $acudiente = self::datosAcudiente($db, $idAcudiente);
            if (!$acudiente) {
                return ['__error' => 'Acudiente no encontrado'];
            }

            $pagos = self::pagosDeAcudiente($db, $idAcudiente, $fechaDesde, $fechaHasta, $productos);
            $variables['{{acudiente_nombre}}'] = $acudiente['nombre_completo'];
            $variables['{{acudiente_tipo_documento}}'] = $acudiente['tipo_identificacion'];
            $variables['{{acudiente_documento}}'] = $acudiente['numero_identificacion'];
            $variables['{{estudiantes_nombres}}'] = self::nombresEstudiantes($pagos);
            // La columna del estudiante solo aporta si el acudiente pago por mas
            // de uno; si no, repite el mismo nombre en cada fila y estrecha el
            // concepto.
            $variables['{{tabla_pagos}}'] = self::tablaPagos($pagos, count(self::estudiantesDistintos($pagos)) > 1,
                                                             $formato, $mostrarConceptos);
            $total = self::totalPagos($pagos);
            $variables['{{total_pagado}}'] = self::formatearMoneda($total);
            $variables['{{total_pagado_letras}}'] = self::numeroALetras($total);
        }

        if ($clave === 'pagos_estudiante') {
            $pagos = self::pagosDeEstudiante($db, $idEstudiante, $fechaDesde, $fechaHasta, $productos);
            $variables['{{tabla_pagos}}'] = self::tablaPagos($pagos, false, $formato, $mostrarConceptos);
            $total = self::totalPagos($pagos);
            $variables['{{total_pagado}}'] = self::formatearMoneda($total);
            $variables['{{total_pagado_letras}}'] = self::numeroALetras($total);

            // Aviso para el institucional: si hay pagos sin acudiente, el
            // certificado por acudiente de este estudiante saldra corto.
            $sinAcudiente = 0;
            foreach ($pagos as $pago) {
                if (empty($pago['id_acudiente'])) {
                    $sinAcudiente++;
                }
            }
            if ($sinAcudiente > 0) {
                $variables['__advertencia'] = $sinAcudiente . ' de los pagos del rango no tienen acudiente registrado. '
                    . 'Este certificado los incluye, pero el certificado por acudiente no.';
            }
        }

        $variables['{{tabla_cuentas_pendientes}}'] = self::tablaCuentasPendientes($db, $idEstudiante);

        return $variables;
    }

    /** Conceptos certificados, por si el jardin los quiere citar en el texto. */
    private static function nombresProductos($db, $productos)
    {
        if (count($productos) === 0) {
            return '';
        }

        $marcadores = self::marcadores($productos, 'np');

        $sentence = $db->prepare("
            SELECT GROUP_CONCAT(nombre ORDER BY nombre SEPARATOR ', ')
            FROM productos_servicios
            WHERE id_tenant = :id_tenant AND id IN ($marcadores)
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        foreach ($productos as $indice => $idProducto) {
            $sentence->bindValue(':np' . $indice, $idProducto);
        }
        $sentence->execute();

        $nombres = $sentence->fetchColumn();

        return $nombres ? $nombres : '';
    }

    private static function datosEstudiante($db, $idEstudiante, $anioCertificado)
    {
        // El grupo que se certifica depende del certificado: si se pidio un anio
        // lectivo, ese; si no, el ultimo activo.
        $sentence = $db->prepare("
            SELECT TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre,
                                  p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                   p.numero_identificacion,
                   ti.nombre AS tipo_identificacion,
                   g.nombre AS genero,
                   e.fecha_ingreso,
                   (SELECT gr.nombre
                      FROM estudiantes_x_grupos exg
                      INNER JOIN grupos gr ON gr.id = exg.id_grupo
                     WHERE exg.id_estudiante = e.id
                       AND exg.id_tenant = e.id_tenant
                       AND (:anio_grupo IS NULL OR exg.anio = :anio_grupo2)
                     ORDER BY exg.activo DESC, exg.anio DESC
                     LIMIT 1) AS nombre_grupo,
                   (SELECT gd.nombre
                      FROM estudiantes_x_grupos exg
                      INNER JOIN grados gd ON gd.id = exg.id_grado
                     WHERE exg.id_estudiante = e.id
                       AND exg.id_tenant = e.id_tenant
                       AND (:anio_grado IS NULL OR exg.anio = :anio_grado2)
                     ORDER BY exg.activo DESC, exg.anio DESC
                     LIMIT 1) AS nombre_grado
            FROM estudiantes e
            INNER JOIN personas p ON p.id = e.id_persona
            LEFT JOIN tipos_identificacion ti ON ti.id = p.id_tipo_identificacion
            LEFT JOIN generos g ON g.id = p.id_genero
            WHERE e.id = :id_estudiante AND e.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_estudiante', $idEstudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':anio_grupo', $anioCertificado, $anioCertificado === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $sentence->bindValue(':anio_grupo2', $anioCertificado, $anioCertificado === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $sentence->bindValue(':anio_grado', $anioCertificado, $anioCertificado === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $sentence->bindValue(':anio_grado2', $anioCertificado, $anioCertificado === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $sentence->execute();

        return $sentence->fetch(PDO::FETCH_ASSOC);
    }

    private static function datosAcudiente($db, $idAcudiente)
    {
        $sentence = $db->prepare("
            SELECT TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre,
                                  p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                   p.numero_identificacion,
                   ti.nombre AS tipo_identificacion
            FROM acudientes a
            INNER JOIN personas p ON p.id = a.id_persona
            LEFT JOIN tipos_identificacion ti ON ti.id = p.id_tipo_identificacion
            WHERE a.id = :id_acudiente AND a.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_acudiente', $idAcudiente);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        return $sentence->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Pagos hechos por una persona acudiente. Se resuelve por la persona y no
     * por la fila de acudiente, porque un mismo papa tiene una fila de
     * acudiente por cada hijo y el certificado debe traerlos todos.
     */
    /**
     * Pagos hechos por una persona acudiente. Se resuelve por la persona y no
     * por la fila de acudiente, porque un mismo papa tiene una fila de
     * acudiente por cada hijo y el certificado debe traerlos todos.
     *
     * Con $productos el valor deja de ser el del recibo y pasa a ser lo aplicado
     * a cuentas de esos productos: un recibo suele cubrir varios a la vez.
     */
    private static function pagosDeAcudiente($db, $idAcudiente, $fechaDesde, $fechaHasta, $productos)
    {
        $sql = "
            SELECT pr.id, pr.fecha, pr.anio, pr.numero,
                   " . self::expresionValor($productos) . " AS valor_recibido,
                   pr.referencia_bancaria, pr.id_acudiente,
                   tp.nombre AS tipo_pago,
                   TRIM(CONCAT_WS(' ', pe.primer_nombre, pe.segundo_nombre,
                                  pe.primer_apellido, pe.segundo_apellido)) AS estudiante_nombre,
                   " . self::expresionConceptos($productos) . " AS conceptos,
                   " . self::expresionDetalle($productos) . " AS detalle_conceptos
            FROM pagos_recibidos pr
            INNER JOIN acudientes ap ON ap.id = pr.id_acudiente
            LEFT JOIN tipos_pagos tp ON tp.id = pr.id_tipo_pago
            LEFT JOIN estudiantes e ON e.id = pr.id_estudiante
            LEFT JOIN personas pe ON pe.id = e.id_persona
            WHERE pr.id_tenant = :id_tenant
              AND COALESCE(pr.anulado, 0) = 0
              AND ap.id_persona = (SELECT a2.id_persona FROM acudientes a2 WHERE a2.id = :id_acudiente)
              AND DATE(pr.fecha) BETWEEN :fecha_desde AND :fecha_hasta
            " . self::filtroProductos($productos) . "
            ORDER BY pr.fecha, pr.numero
        ";

        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_acudiente', $idAcudiente);
        $sentence->bindParam(':fecha_desde', $fechaDesde);
        $sentence->bindParam(':fecha_hasta', $fechaHasta);
        self::ligarProductos($sentence, $productos);
        $sentence->execute();

        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function pagosDeEstudiante($db, $idEstudiante, $fechaDesde, $fechaHasta, $productos)
    {
        $sql = "
            SELECT pr.id, pr.fecha, pr.anio, pr.numero,
                   " . self::expresionValor($productos) . " AS valor_recibido,
                   pr.referencia_bancaria, pr.id_acudiente,
                   tp.nombre AS tipo_pago,
                   TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.segundo_nombre,
                                  pa.primer_apellido, pa.segundo_apellido)) AS acudiente_nombre,
                   " . self::expresionConceptos($productos) . " AS conceptos,
                   " . self::expresionDetalle($productos) . " AS detalle_conceptos
            FROM pagos_recibidos pr
            LEFT JOIN tipos_pagos tp ON tp.id = pr.id_tipo_pago
            LEFT JOIN acudientes a ON a.id = pr.id_acudiente
            LEFT JOIN personas pa ON pa.id = a.id_persona
            WHERE pr.id_tenant = :id_tenant
              AND COALESCE(pr.anulado, 0) = 0
              AND pr.id_estudiante = :id_estudiante
              AND DATE(pr.fecha) BETWEEN :fecha_desde AND :fecha_hasta
            " . self::filtroProductos($productos) . "
            ORDER BY pr.fecha, pr.numero
        ";

        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $idEstudiante);
        $sentence->bindParam(':fecha_desde', $fechaDesde);
        $sentence->bindParam(':fecha_hasta', $fechaHasta);
        self::ligarProductos($sentence, $productos);
        $sentence->execute();

        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sin filtro se certifica el valor del recibo. Con productos escogidos solo
     * se puede certificar lo aplicado a esos productos: un recibo suele cubrir
     * varios a la vez.
     */
    private static function expresionValor($productos)
    {
        if (count($productos) === 0) {
            return 'pr.valor_recibido';
        }

        $marcadores = self::marcadores($productos, 'pv');

        return "(SELECT COALESCE(SUM(cp.valor_aplicado), 0)
                   FROM cuenta_pagada cp
                   INNER JOIN cuentas_por_cobrar cc ON cc.id = cp.id_cuenta_por_cobrar
                  WHERE cp.id_pago_recibido = pr.id
                    AND cc.id_producto_servicio IN ($marcadores))";
    }

    private static function expresionConceptos($productos)
    {
        $filtro = '';
        if (count($productos) > 0) {
            $filtro = ' AND cc.id_producto_servicio IN (' . self::marcadores($productos, 'pc') . ') ';
        }

        return "(SELECT GROUP_CONCAT(DISTINCT ps.nombre ORDER BY ps.nombre SEPARATOR ', ')
                   FROM cuenta_pagada cp
                   INNER JOIN cuentas_por_cobrar cc ON cc.id = cp.id_cuenta_por_cobrar
                   INNER JOIN productos_servicios ps ON ps.id = cc.id_producto_servicio
                  WHERE cp.id_pago_recibido = pr.id $filtro)";
    }

    /**
     * Cuanto de cada recibo se aplico a cada producto, como "nombre~valor"
     * separados por ||. Es lo unico que permite repartir un recibo que cubre
     * varios conceptos.
     */
    private static function expresionDetalle($productos)
    {
        $filtro = '';
        if (count($productos) > 0) {
            $filtro = ' AND cc.id_producto_servicio IN (' . self::marcadores($productos, 'pd') . ') ';
        }

        // Una linea por abono, sin preagrupar: una subconsulta derivada no puede
        // referenciar pr.id de la consulta externa. Si un recibo abona dos
        // cuentas del mismo producto salen dos lineas iguales, y el PHP las suma.
        return "(SELECT GROUP_CONCAT(CONCAT(ps.nombre, '~', cp.valor_aplicado) ORDER BY ps.nombre SEPARATOR '||')
                   FROM cuenta_pagada cp
                   INNER JOIN cuentas_por_cobrar cc ON cc.id = cp.id_cuenta_por_cobrar
                   INNER JOIN productos_servicios ps ON ps.id = cc.id_producto_servicio
                  WHERE cp.id_pago_recibido = pr.id $filtro)";
    }

    /** Deja fuera los recibos que no tocaron ninguno de los productos escogidos. */
    private static function filtroProductos($productos)
    {
        if (count($productos) === 0) {
            return '';
        }

        $marcadores = self::marcadores($productos, 'pf');

        return " AND EXISTS (SELECT 1
                               FROM cuenta_pagada cp2
                               INNER JOIN cuentas_por_cobrar cc2 ON cc2.id = cp2.id_cuenta_por_cobrar
                              WHERE cp2.id_pago_recibido = pr.id
                                AND cc2.id_producto_servicio IN ($marcadores)) ";
    }

    /**
     * Marcadores con nombre para una lista. Cada subconsulta usa su propio
     * prefijo: repetir un mismo nombre solo funciona con prepares emulados.
     */
    private static function marcadores($productos, $prefijo)
    {
        $nombres = [];
        for ($i = 0; $i < count($productos); $i++) {
            $nombres[] = ':' . $prefijo . $i;
        }

        return implode(', ', $nombres);
    }

    /** Ata la lista de productos a los tres grupos de marcadores. */
    private static function ligarProductos($sentence, $productos)
    {
        foreach (['pv', 'pc', 'pf', 'pd'] as $prefijo) {
            foreach ($productos as $indice => $idProducto) {
                $sentence->bindValue(':' . $prefijo . $indice, $idProducto);
            }
        }
    }

    private static function totalPagos($pagos)
    {
        $total = 0;
        foreach ($pagos as $pago) {
            $total += (float) $pago['valor_recibido'];
        }
        return round($total, 2);
    }

    /** Nombres distintos de estudiante presentes en los pagos. */
    private static function estudiantesDistintos($pagos)
    {
        $nombres = [];
        foreach ($pagos as $pago) {
            if (!empty($pago['estudiante_nombre']) && !in_array($pago['estudiante_nombre'], $nombres, true)) {
                $nombres[] = $pago['estudiante_nombre'];
            }
        }

        return $nombres;
    }

    private static function nombresEstudiantes($pagos)
    {
        $nombres = self::estudiantesDistintos($pagos);

        if (count($nombres) === 0) {
            return '';
        }
        if (count($nombres) === 1) {
            return $nombres[0];
        }

        $ultimo = array_pop($nombres);
        return implode(', ', $nombres) . ' y ' . $ultimo;
    }

    /**
     * Tabla de pagos en HTML, segun el formato pedido:
     *   recibo   -> un renglon por recibo
     *   mes      -> una fila por mes, con el total
     *   concepto -> una fila por concepto, con lo pagado por cada uno
     *   total    -> sin tabla; el valor total ya va en el texto
     *
     * $conEstudiante agrega la columna del estudiante, que solo tiene sentido
     * cuando el acudiente pago por varios. $mostrarConceptos prende la columna
     * de conceptos, que no aplica a los formatos concepto ni total.
     */
    private static function tablaPagos($pagos, $conEstudiante, $formato = 'recibo', $mostrarConceptos = 1)
    {
        if ($formato === 'total') {
            return '';
        }

        if (count($pagos) === 0) {
            return '<p><i>No se registran pagos en el periodo indicado.</i></p>';
        }

        if ($formato === 'mes') {
            return self::tablaPagosPorMes($pagos, $conEstudiante, $mostrarConceptos);
        }

        if ($formato === 'concepto') {
            return self::tablaPagosPorConcepto($pagos, $conEstudiante);
        }

        $html = '<table><thead><tr><th>Fecha</th><th>Recibo</th>';
        if ($conEstudiante) {
            $html .= '<th>Estudiante</th>';
        }
        if ($mostrarConceptos) {
            $html .= '<th>Concepto</th>';
        }
        $html .= '<th>Valor</th></tr></thead><tbody>';

        foreach ($pagos as $pago) {
            $html .= '<tr>';
            $html .= '<td>' . self::escapar(self::fechaCorta($pago['fecha'])) . '</td>';
            $html .= '<td>' . self::escapar(self::formatearNumero((int) $pago['anio'], (int) $pago['numero'])) . '</td>';
            if ($conEstudiante) {
                $html .= '<td>' . self::escapar($pago['estudiante_nombre']) . '</td>';
            }
            if ($mostrarConceptos) {
                $html .= '<td>' . self::escapar($pago['conceptos'] ? $pago['conceptos'] : $pago['tipo_pago']) . '</td>';
            }
            $html .= '<td>' . self::escapar(self::formatearMoneda((float) $pago['valor_recibido'])) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Una fila por mes con el total y los conceptos distintos de ese mes.
     * Cuando hay varios estudiantes se agrupa por mes y estudiante, para no
     * mezclar en una misma fila lo pagado por hijos distintos.
     */
    private static function tablaPagosPorMes($pagos, $conEstudiante, $mostrarConceptos)
    {
        $meses = [];

        foreach ($pagos as $pago) {
            $periodo = date('Y-m', strtotime($pago['fecha']));
            $estudiante = $conEstudiante ? (string) $pago['estudiante_nombre'] : '';
            $llave = $periodo . '|' . $estudiante;

            if (!isset($meses[$llave])) {
                $meses[$llave] = [
                    'periodo' => $periodo,
                    'estudiante' => $estudiante,
                    'total' => 0,
                    'conceptos' => []
                ];
            }

            $meses[$llave]['total'] += (float) $pago['valor_recibido'];

            $conceptos = $pago['conceptos'] ? $pago['conceptos'] : $pago['tipo_pago'];
            foreach (explode(', ', (string) $conceptos) as $concepto) {
                $concepto = trim($concepto);
                if ($concepto !== '' && !in_array($concepto, $meses[$llave]['conceptos'], true)) {
                    $meses[$llave]['conceptos'][] = $concepto;
                }
            }
        }

        ksort($meses);

        $html = '<table><thead><tr><th>Mes</th>';
        if ($conEstudiante) {
            $html .= '<th>Estudiante</th>';
        }
        if ($mostrarConceptos) {
            $html .= '<th>Concepto</th>';
        }
        $html .= '<th>Valor</th></tr></thead><tbody>';

        foreach ($meses as $mes) {
            sort($mes['conceptos']);

            $html .= '<tr>';
            $html .= '<td>' . self::escapar(self::mesLargo($mes['periodo'])) . '</td>';
            if ($conEstudiante) {
                $html .= '<td>' . self::escapar($mes['estudiante']) . '</td>';
            }
            if ($mostrarConceptos) {
                $html .= '<td>' . self::escapar(implode(', ', $mes['conceptos'])) . '</td>';
            }
            $html .= '<td>' . self::escapar(self::formatearMoneda($mes['total'])) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Una fila por concepto con el total del periodo.
     *
     * Un recibo que cubre varios conceptos no dice cuanto fue a cada uno, asi
     * que el valor sale de lo aplicado en cuenta_pagada, que si lo sabe. Por eso
     * esta tabla no se arma con los pagos sino con las cuentas que abonaron.
     */
    private static function tablaPagosPorConcepto($pagos, $conEstudiante)
    {
        $conceptos = [];

        foreach ($pagos as $pago) {
            $detalle = isset($pago['detalle_conceptos']) ? $pago['detalle_conceptos'] : null;

            // Sin detalle aplicado, el recibo se atribuye completo a lo que
            // diga su lista de conceptos.
            if (!$detalle) {
                $nombre = $pago['conceptos'] ? $pago['conceptos'] : $pago['tipo_pago'];
                $estudiante = $conEstudiante ? (string) $pago['estudiante_nombre'] : '';
                $llave = $nombre . '|' . $estudiante;

                if (!isset($conceptos[$llave])) {
                    $conceptos[$llave] = ['nombre' => $nombre, 'estudiante' => $estudiante, 'total' => 0];
                }
                $conceptos[$llave]['total'] += (float) $pago['valor_recibido'];
                continue;
            }

            foreach (explode('||', $detalle) as $linea) {
                $partes = explode('~', $linea);
                if (count($partes) < 2) {
                    continue;
                }

                $nombre = $partes[0];
                $estudiante = $conEstudiante ? (string) $pago['estudiante_nombre'] : '';
                $llave = $nombre . '|' . $estudiante;

                if (!isset($conceptos[$llave])) {
                    $conceptos[$llave] = ['nombre' => $nombre, 'estudiante' => $estudiante, 'total' => 0];
                }
                $conceptos[$llave]['total'] += (float) $partes[1];
            }
        }

        ksort($conceptos);

        $html = '<table><thead><tr><th>Concepto</th>';
        if ($conEstudiante) {
            $html .= '<th>Estudiante</th>';
        }
        $html .= '<th>Valor</th></tr></thead><tbody>';

        foreach ($conceptos as $concepto) {
            $html .= '<tr>';
            $html .= '<td>' . self::escapar($concepto['nombre']) . '</td>';
            if ($conEstudiante) {
                $html .= '<td>' . self::escapar($concepto['estudiante']) . '</td>';
            }
            $html .= '<td>' . self::escapar(self::formatearMoneda($concepto['total'])) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private static function tablaCuentasPendientes($db, $idEstudiante)
    {
        $sentence = $db->prepare("
            SELECT c.fecha, ps.nombre AS producto,
                   ROUND(c.valor - COALESCE(ap.aplicado, 0), 2) AS saldo
            FROM cuentas_por_cobrar c
            INNER JOIN productos_servicios ps ON ps.id = c.id_producto_servicio
            LEFT JOIN (
                SELECT cp.id_cuenta_por_cobrar, SUM(cp.valor_aplicado) AS aplicado
                FROM cuenta_pagada cp
                INNER JOIN pagos_recibidos pr ON pr.id = cp.id_pago_recibido
                WHERE cp.id_tenant = :id_tenant_cp AND COALESCE(pr.anulado, 0) = 0
                GROUP BY cp.id_cuenta_por_cobrar
            ) ap ON ap.id_cuenta_por_cobrar = c.id
            WHERE c.id_tenant = :id_tenant
              AND c.id_persona = (SELECT e.id_persona FROM estudiantes e WHERE e.id = :id_estudiante)
              AND COALESCE(c.anulado, 0) = 0
              AND (c.valor - COALESCE(ap.aplicado, 0)) > 0
            ORDER BY c.fecha
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant_cp', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $idEstudiante);
        $sentence->execute();

        $filas = $sentence->fetchAll(PDO::FETCH_ASSOC);

        if (count($filas) === 0) {
            return '<p><i>No hay saldos pendientes.</i></p>';
        }

        $html = '<table><thead><tr><th>Fecha</th><th>Concepto</th><th>Saldo</th></tr></thead><tbody>';
        foreach ($filas as $fila) {
            $html .= '<tr>';
            $html .= '<td>' . self::escapar(self::fechaCorta($fila['fecha'])) . '</td>';
            $html .= '<td>' . self::escapar($fila['producto']) . '</td>';
            $html .= '<td>' . self::escapar(self::formatearMoneda((float) $fila['saldo'])) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    // -----------------------------------------------------------------
    // Plantilla
    // -----------------------------------------------------------------

    private static function plantillaDe($db, $clave)
    {
        $sentence = $db->prepare("
            SELECT p.contenido
            FROM plantillas p
            INNER JOIN tipos_plantillas tp ON tp.id = p.id_tipo_plantilla
            WHERE p.id_tenant = :id_tenant
              AND tp.codigo = 'certificado'
              AND p.clave = :clave
            LIMIT 1
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':clave', $clave);
        $sentence->execute();

        $contenido = $sentence->fetchColumn();

        return $contenido ? json_decode($contenido, true) : null;
    }

    /**
     * Reemplaza las variables en el cuerpo de la plantilla. Las que no tengan
     * valor se dejan vacias, para que el documento no salga con llaves sueltas.
     */
    /**
     * Deja el cuerpo pidiendo la ciudad y la fecha de expedicion.
     *
     * Si la plantilla ya las trae, no toca nada. Si no, las engancha al final
     * de la frase de cierre; y si esa frase tampoco esta, agrega un parrafo
     * propio, para que ningun certificado salga sin fecha.
     */
    private static function agregarFechaExpedicion($cuerpo)
    {
        if (strpos($cuerpo, '{{fecha_larga}}') !== false || strpos($cuerpo, '{{ciudad_fecha}}') !== false) {
            return $cuerpo;
        }

        $cierre = ', en {{ciudad}}, a los {{fecha_larga}}.';
        $suelto = '<p>Se expide en {{ciudad}}, a los {{fecha_larga}}.</p>';

        // Las tres redacciones sembradas terminan igual, sin importar si hablan
        // de certificado, constancia o paz y salvo.
        $frase = 'para los fines que estime convenientes';

        $posicion = strpos($cuerpo, $frase);
        if ($posicion !== false) {
            $corte = $posicion + strlen($frase);
            // Se descarta el punto que venia, porque la frase ahora sigue.
            $resto = substr($cuerpo, $corte);
            if (substr($resto, 0, 1) === '.') {
                $resto = substr($resto, 1);
            }

            return substr($cuerpo, 0, $corte) . $cierre . $resto;
        }

        return $cuerpo . $suelto;
    }

    /**
     * El NIT sale del encabezado del cuerpo y en su lugar queda la resolucion,
     * debajo del nombre de la institucion. Se hace aqui y no con un UPDATE a
     * las plantillas para que aplique aunque el jardin las haya tocado.
     */
    private static function ajustarEncabezadoInstitucion($cuerpo, $variables)
    {
        $marcador = 'NIT: {{institucion_nit}}';
        $posicion = strpos($cuerpo, $marcador);

        if ($posicion === false) {
            return $cuerpo;
        }

        $resolucion = isset($variables['{{resolucion}}']) ? $variables['{{resolucion}}'] : '';

        return substr($cuerpo, 0, $posicion)
            . ($resolucion !== '' ? '{{resolucion}}' : '')
            . substr($cuerpo, $posicion + strlen($marcador));
    }

    /**
     * Quita la frase que anuncia el detalle cuando el certificado sale sin
     * tabla, y cierra la oracion con punto.
     */
    private static function quitarAnuncioDetalle($cuerpo)
    {
        $frases = [', segun el siguiente detalle:', ', según el siguiente detalle:',
                   ' segun el siguiente detalle:', ' según el siguiente detalle:'];

        foreach ($frases as $frase) {
            if (strpos($cuerpo, $frase) !== false) {
                return str_replace($frase, '.', $cuerpo);
            }
        }

        return $cuerpo;
    }

    private static function resolverPlantilla($plantilla, $variables, $formato = 'recibo')
    {
        $titulo = isset($plantilla['titulo']) ? $plantilla['titulo'] : '';
        $cuerpo = isset($plantilla['cuerpo']) ? $plantilla['cuerpo'] : '';

        $reemplazables = [];
        foreach ($variables as $marcador => $valor) {
            if (substr($marcador, 0, 2) === '{{') {
                $reemplazables[$marcador] = (string) $valor;
            }
        }

        // Si la plantilla no pide la fecha de expedicion, se agrega sola al
        // cierre. Antes dependia de un UPDATE sobre las plantillas y bastaba
        // una diferencia de redaccion para que el certificado saliera sin ella.
        $cuerpo = self::agregarFechaExpedicion($cuerpo);
        $cuerpo = self::ajustarEncabezadoInstitucion($cuerpo, $variables);

        // Sin tabla no hay detalle que anunciar.
        if ($formato === 'total') {
            $cuerpo = self::quitarAnuncioDetalle($cuerpo);
        }

        $titulo = strtr($titulo, $reemplazables);
        $cuerpo = strtr($cuerpo, $reemplazables);

        // Variables que la plantilla use y el certificado no alimente.
        $cuerpo = preg_replace('/\{\{[a-z0-9_]+\}\}/i', '', $cuerpo);

        $firma = '<p>Cordialmente,</p>'
            . '<p style="text-align:center">{{firma_linea}}</p>'
            . '<p style="text-align:center"><b>' . self::escapar($variables['{{firmante_nombre}}']) . '</b></p>';

        if (!empty($variables['{{firmante_cargo}}'])) {
            $firma .= '<p style="text-align:center"><b>' . self::escapar($variables['{{firmante_cargo}}']) . '</b></p>';
        }

        $firma .= '<p style="text-align:center"><b>' . self::escapar($variables['{{institucion_nombre}}']) . '</b></p>';
        $firma .= '<p style="text-align:center">NIT: ' . self::escapar($variables['{{institucion_nit}}']) . '</p>';

        // El numero va en la cabecera y los datos de contacto en el pie: el
        // renderizador los saca de aqui y los dibuja aparte del cuerpo.
        $meta = '<div data-numero="' . self::escapar($variables['{{numero_certificado}}']) . '"'
            . ' data-contacto="' . self::escapar($variables['{{pie_contacto}}']) . '"></div>';

        return '<h1>' . self::escapar($titulo) . '</h1>' . $meta . $cuerpo . $firma;
    }

    // -----------------------------------------------------------------
    // Utilidades
    // -----------------------------------------------------------------

    private static function configuracionGlobal($db)
    {
        $sentence = $db->prepare("
            SELECT clave, valor_texto
            FROM configuracion_global
            WHERE id_tenant = :id_tenant
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $configuracion = [];
        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $configuracion[$fila['clave']] = $fila['valor_texto'];
        }

        return $configuracion;
    }

    private static function valor($configuracion, $clave)
    {
        return isset($configuracion[$clave]) && $configuracion[$clave] !== null ? $configuracion[$clave] : '';
    }

    /**
     * Quien firma. Si el jardin no configuro un firmante propio se usa el
     * representante legal, que es lo que hacian los contratos.
     */
    private static function firmanteNombre($configuracion)
    {
        $propio = self::valor($configuracion, 'certificado_firmante_nombre');
        return $propio !== '' ? $propio : self::valor($configuracion, 'representante_legal_nombre');
    }

    /**
     * Linea de contacto del pie: direccion, telefono, correo y web, con lo que
     * el jardin tenga configurado. El Instagram se deja por fuera a proposito:
     * el certificado va a bancos y entidades.
     */
    private static function lineaContacto($configuracion)
    {
        $partes = [];

        foreach (['institucion_direccion', 'institucion_telefono',
                  'institucion_email', 'institucion_web'] as $clave) {
            $valor = self::valor($configuracion, $clave);
            if ($valor !== '') {
                $partes[] = $valor;
            }
        }

        return implode('  ·  ', $partes);
    }

    /**
     * Ciudad de la fecha. Si el jardin no configuro una, se usa la direccion de
     * la institucion, que normalmente ya viene como "Chia, Cundinamarca".
     */
    private static function ciudad($configuracion)
    {
        $propia = self::valor($configuracion, 'certificado_ciudad');
        return $propia !== '' ? $propia : self::valor($configuracion, 'institucion_direccion');
    }

    private static function siguienteNumero($db, $anio)
    {
        $sentence = $db->prepare("
            SELECT COALESCE(MAX(numero), 0) + 1
            FROM certificados_expedidos
            WHERE id_tenant = :id_tenant AND anio = :anio
            FOR UPDATE
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':anio', $anio, PDO::PARAM_INT);
        $sentence->execute();

        return (int) $sentence->fetchColumn();
    }

    private static function formatearNumero($anio, $numero)
    {
        return $anio . '-' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }

    private static function formatearMoneda($valor)
    {
        // Sin espacio despues del signo: en la tabla del PDF "$ 900.000" se
        // partia en dos lineas porque el ancho se calcula por palabra.
        return '$' . number_format((float) $valor, 0, ',', '.');
    }

    private static function fechaCorta($fecha)
    {
        return date('d/m/Y', strtotime($fecha));
    }

    /** "Febrero de 2026" a partir de un periodo Y-m. */
    private static function mesLargo($periodo)
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $partes = explode('-', $periodo);
        $indice = (int) $partes[1] - 1;

        return ucfirst($meses[$indice]) . ' de ' . $partes[0];
    }

    private static function fechaLarga($fecha)
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $tiempo = strtotime($fecha);

        return (int) date('j', $tiempo) . ' de ' . $meses[(int) date('n', $tiempo) - 1] . ' de ' . date('Y', $tiempo);
    }

    /**
     * "el mes de febrero del año 2025". Se usa en la constancia de estudio,
     * donde el dia exacto de ingreso no aporta.
     */
    private static function fechaIngresoLarga($fecha)
    {
        if (!$fecha) {
            return '';
        }

        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $tiempo = strtotime($fecha);

        return 'el mes de ' . $meses[(int) date('n', $tiempo) - 1] . ' del año ' . date('Y', $tiempo);
    }

    private static function escapar($texto)
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Valor en letras para el certificado de pagos, que se usa ante terceros.
     * Solo pesos enteros: los centavos no se manejan en la cartera.
     */
    private static function numeroALetras($valor)
    {
        $entero = (int) round($valor);

        if ($entero === 0) {
            return 'CERO PESOS M/CTE';
        }

        return strtoupper(trim(self::convertirGrupo($entero))) . ' PESOS M/CTE';
    }

    private static function convertirGrupo($numero)
    {
        $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
                     'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE',
                     'DIECIOCHO', 'DIECINUEVE', 'VEINTE'];
        $decenas = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
                     'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        if ($numero === 0) {
            return '';
        }

        if ($numero >= 1000000) {
            $millones = intdiv($numero, 1000000);
            $resto = $numero % 1000000;
            $texto = ($millones === 1) ? 'UN MILLON' : self::convertirGrupo($millones) . ' MILLONES';
            return trim($texto . ' ' . self::convertirGrupo($resto));
        }

        if ($numero >= 1000) {
            $miles = intdiv($numero, 1000);
            $resto = $numero % 1000;
            $texto = ($miles === 1) ? 'MIL' : self::convertirGrupo($miles) . ' MIL';
            return trim($texto . ' ' . self::convertirGrupo($resto));
        }

        if ($numero === 100) {
            return 'CIEN';
        }

        if ($numero >= 100) {
            return trim($centenas[intdiv($numero, 100)] . ' ' . self::convertirGrupo($numero % 100));
        }

        if ($numero <= 20) {
            return $unidades[$numero];
        }

        if ($numero < 30) {
            return 'VEINTI' . $unidades[$numero % 10];
        }

        $decena = $decenas[intdiv($numero, 10)];
        $unidad = $numero % 10;

        return $unidad === 0 ? $decena : $decena . ' Y ' . $unidades[$unidad];
    }
}