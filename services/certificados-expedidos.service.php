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
                       ce.fecha_desde, ce.fecha_hasta, ce.origen, ce.fecha_expedicion,
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

                $evaluacion = $soloAutomaticos
                    ? self::evaluarRegla($db, $clave, $idEstudiante, $configuracion)
                    : ['cumple' => true, 'mensaje' => null, 'saldo' => 0];

                $resultado[] = [
                    'clave_certificado' => $clave,
                    'nombre' => CertificadosConfiguracion::$NOMBRES[$clave],
                    'modo' => $modo,
                    'regla' => $configuracion ? $configuracion['regla'] : 'libre',
                    'cumple' => $evaluacion['cumple'] ? 1 : 0,
                    'mensaje' => $evaluacion['mensaje'],
                    'saldo_pendiente' => $evaluacion['saldo']
                ];
            }

            Flight::json($resultado);
        } catch (Exception $e) {
            error_log('Error en CertificadosExpedidos::getDisponibles: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener los certificados disponibles'], 500);
        }
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
            }

            $variables = self::armarVariables($db, $clave, $idEstudiante, $idAcudiente, $anioCertificado, $fechaDesde, $fechaHasta);
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

            $contenidoHtml = self::resolverPlantilla($plantilla, $variables);

            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO certificados_expedidos
                    (id, id_tenant, anio, numero, clave_certificado, id_estudiante, id_acudiente,
                     anio_certificado, fecha_desde, fecha_hasta, contenido_html, origen, id_usuario)
                VALUES (:id, :id_tenant, :anio, :numero, :clave, :id_estudiante, :id_acudiente,
                        :anio_certificado, :fecha_desde, :fecha_hasta, :contenido_html, :origen, :id_usuario)
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
            $sentence->bindParam(':contenido_html', $contenidoHtml);
            $sentence->bindParam(':origen', $origen);
            $sentence->bindParam(':id_usuario', $idUsuario);
            $sentence->execute();

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
              AND c.id_persona = ?
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
            SELECT id, clave_certificado, modo, regla, mensaje_no_cumple, activo
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
    private static function armarVariables($db, $clave, $idEstudiante, $idAcudiente, $anioCertificado, $fechaDesde, $fechaHasta)
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
            '{{ciudad}}' => self::valor($configuracion, 'certificado_ciudad'),
            '{{fecha_larga}}' => self::fechaLarga(date('Y-m-d')),
            '{{anio}}' => date('Y'),
            '{{estudiante_nombre}}' => $estudiante['nombre_completo'],
            '{{estudiante_tipo_documento}}' => $estudiante['tipo_identificacion'],
            '{{estudiante_documento}}' => $estudiante['numero_identificacion'],
            '{{estudiante_articulo_el}}' => $esFemenino ? 'La' : 'El',
            '{{estudiante_articulo_del}}' => $esFemenino ? 'de la' : 'del',
            '{{estudiante_identificado}}' => $esFemenino ? 'identificada' : 'identificado',
            '{{grupo_nombre}}' => $estudiante['nombre_grupo'],
            '{{grado_nombre}}' => $estudiante['nombre_grado'],
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
            '{{tabla_cuentas_pendientes}}' => ''
        ];

        if ($clave === 'pagos_acudiente') {
            $acudiente = self::datosAcudiente($db, $idAcudiente);
            if (!$acudiente) {
                return ['__error' => 'Acudiente no encontrado'];
            }

            $pagos = self::pagosDeAcudiente($db, $idAcudiente, $fechaDesde, $fechaHasta);
            $variables['{{acudiente_nombre}}'] = $acudiente['nombre_completo'];
            $variables['{{acudiente_tipo_documento}}'] = $acudiente['tipo_identificacion'];
            $variables['{{acudiente_documento}}'] = $acudiente['numero_identificacion'];
            $variables['{{estudiantes_nombres}}'] = self::nombresEstudiantes($pagos);
            $variables['{{tabla_pagos}}'] = self::tablaPagos($pagos, true);
            $total = self::totalPagos($pagos);
            $variables['{{total_pagado}}'] = self::formatearMoneda($total);
            $variables['{{total_pagado_letras}}'] = self::numeroALetras($total);
        }

        if ($clave === 'pagos_estudiante') {
            $pagos = self::pagosDeEstudiante($db, $idEstudiante, $fechaDesde, $fechaHasta);
            $variables['{{tabla_pagos}}'] = self::tablaPagos($pagos, false);
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
    private static function pagosDeAcudiente($db, $idAcudiente, $fechaDesde, $fechaHasta)
    {
        $sentence = $db->prepare("
            SELECT pr.id, pr.fecha, pr.anio, pr.numero, pr.valor_recibido,
                   pr.referencia_bancaria, pr.id_acudiente,
                   tp.nombre AS tipo_pago,
                   TRIM(CONCAT_WS(' ', pe.primer_nombre, pe.segundo_nombre,
                                  pe.primer_apellido, pe.segundo_apellido)) AS estudiante_nombre,
                   (SELECT GROUP_CONCAT(DISTINCT ps.nombre ORDER BY ps.nombre SEPARATOR ', ')
                      FROM cuenta_pagada cp
                      INNER JOIN cuentas_por_cobrar cc ON cc.id = cp.id_cuenta_por_cobrar
                      INNER JOIN productos_servicios ps ON ps.id = cc.id_producto_servicio
                     WHERE cp.id_pago_recibido = pr.id) AS conceptos
            FROM pagos_recibidos pr
            INNER JOIN acudientes ap ON ap.id = pr.id_acudiente
            LEFT JOIN tipos_pagos tp ON tp.id = pr.id_tipo_pago
            LEFT JOIN estudiantes e ON e.id = pr.id_estudiante
            LEFT JOIN personas pe ON pe.id = e.id_persona
            WHERE pr.id_tenant = :id_tenant
              AND COALESCE(pr.anulado, 0) = 0
              AND ap.id_persona = (SELECT a2.id_persona FROM acudientes a2 WHERE a2.id = :id_acudiente)
              AND DATE(pr.fecha) BETWEEN :fecha_desde AND :fecha_hasta
            ORDER BY pr.fecha, pr.numero
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_acudiente', $idAcudiente);
        $sentence->bindParam(':fecha_desde', $fechaDesde);
        $sentence->bindParam(':fecha_hasta', $fechaHasta);
        $sentence->execute();

        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function pagosDeEstudiante($db, $idEstudiante, $fechaDesde, $fechaHasta)
    {
        $sentence = $db->prepare("
            SELECT pr.id, pr.fecha, pr.anio, pr.numero, pr.valor_recibido,
                   pr.referencia_bancaria, pr.id_acudiente,
                   tp.nombre AS tipo_pago,
                   TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.segundo_nombre,
                                  pa.primer_apellido, pa.segundo_apellido)) AS acudiente_nombre,
                   (SELECT GROUP_CONCAT(DISTINCT ps.nombre ORDER BY ps.nombre SEPARATOR ', ')
                      FROM cuenta_pagada cp
                      INNER JOIN cuentas_por_cobrar cc ON cc.id = cp.id_cuenta_por_cobrar
                      INNER JOIN productos_servicios ps ON ps.id = cc.id_producto_servicio
                     WHERE cp.id_pago_recibido = pr.id) AS conceptos
            FROM pagos_recibidos pr
            LEFT JOIN tipos_pagos tp ON tp.id = pr.id_tipo_pago
            LEFT JOIN acudientes a ON a.id = pr.id_acudiente
            LEFT JOIN personas pa ON pa.id = a.id_persona
            WHERE pr.id_tenant = :id_tenant
              AND COALESCE(pr.anulado, 0) = 0
              AND pr.id_estudiante = :id_estudiante
              AND DATE(pr.fecha) BETWEEN :fecha_desde AND :fecha_hasta
            ORDER BY pr.fecha, pr.numero
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $idEstudiante);
        $sentence->bindParam(':fecha_desde', $fechaDesde);
        $sentence->bindParam(':fecha_hasta', $fechaHasta);
        $sentence->execute();

        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function totalPagos($pagos)
    {
        $total = 0;
        foreach ($pagos as $pago) {
            $total += (float) $pago['valor_recibido'];
        }
        return round($total, 2);
    }

    private static function nombresEstudiantes($pagos)
    {
        $nombres = [];
        foreach ($pagos as $pago) {
            if (!empty($pago['estudiante_nombre']) && !in_array($pago['estudiante_nombre'], $nombres, true)) {
                $nombres[] = $pago['estudiante_nombre'];
            }
        }

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
     * Tabla de pagos en HTML. Con $conEstudiante se agrega la columna del
     * estudiante, que solo tiene sentido en el certificado del acudiente.
     */
    private static function tablaPagos($pagos, $conEstudiante)
    {
        if (count($pagos) === 0) {
            return '<p><i>No se registran pagos en el periodo indicado.</i></p>';
        }

        $html = '<table><thead><tr><th>Fecha</th><th>Recibo</th>';
        if ($conEstudiante) {
            $html .= '<th>Estudiante</th>';
        }
        $html .= '<th>Concepto</th><th>Valor</th></tr></thead><tbody>';

        foreach ($pagos as $pago) {
            $html .= '<tr>';
            $html .= '<td>' . self::escapar(self::fechaCorta($pago['fecha'])) . '</td>';
            $html .= '<td>' . self::escapar(self::formatearNumero((int) $pago['anio'], (int) $pago['numero'])) . '</td>';
            if ($conEstudiante) {
                $html .= '<td>' . self::escapar($pago['estudiante_nombre']) . '</td>';
            }
            $html .= '<td>' . self::escapar($pago['conceptos'] ? $pago['conceptos'] : $pago['tipo_pago']) . '</td>';
            $html .= '<td>' . self::escapar(self::formatearMoneda((float) $pago['valor_recibido'])) . '</td>';
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
              AND c.id_persona = :id_estudiante
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
    private static function resolverPlantilla($plantilla, $variables)
    {
        $titulo = isset($plantilla['titulo']) ? $plantilla['titulo'] : '';
        $cuerpo = isset($plantilla['cuerpo']) ? $plantilla['cuerpo'] : '';

        $reemplazables = [];
        foreach ($variables as $marcador => $valor) {
            if (substr($marcador, 0, 2) === '{{') {
                $reemplazables[$marcador] = (string) $valor;
            }
        }

        $titulo = strtr($titulo, $reemplazables);
        $cuerpo = strtr($cuerpo, $reemplazables);

        // Variables que la plantilla use y el certificado no alimente.
        $cuerpo = preg_replace('/\{\{[a-z0-9_]+\}\}/i', '', $cuerpo);

        $firma = '<p style="text-align:center">Cordialmente,</p>'
            . '<p style="text-align:center">{{firma_linea}}</p>'
            . '<p style="text-align:center"><b>' . self::escapar($variables['{{firmante_nombre}}']) . '</b></p>';

        if (!empty($variables['{{firmante_cargo}}'])) {
            $firma .= '<p style="text-align:center"><b>' . self::escapar($variables['{{firmante_cargo}}']) . '</b></p>';
        }

        $firma .= '<p style="text-align:center"><b>' . self::escapar($variables['{{institucion_nombre}}']) . '</b></p>';
        $firma .= '<p style="text-align:center">NIT: ' . self::escapar($variables['{{institucion_nit}}']) . '</p>';

        $pie = '<p style="text-align:center;font-size:8">Certificado No. '
            . self::escapar($variables['{{numero_certificado}}']) . '</p>';

        return '<h1>' . self::escapar($titulo) . '</h1>' . $cuerpo . $firma . $pie;
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
        return '$ ' . number_format((float) $valor, 0, ',', '.');
    }

    private static function fechaCorta($fecha)
    {
        return date('d/m/Y', strtotime($fecha));
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
