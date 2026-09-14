<?php
/*=============================================
SERVICIO - NOTIFICACION DE ASISTENCIA
Archivo: services/notificaciones-asistencia.service.php

Arma y envia la notificacion al portal de padres cuando se
registra el ingreso o la salida de un estudiante.

No expone rutas: lo invocan asistencia-estudiantes.service.php
y motor-cobros-automaticos.service.php.

Nada de lo que pasa aqui puede tumbar el registro de
asistencia: todos los errores se atrapan y se dejan en el log.
=============================================*/

class NotificacionesAsistencia
{
    const TIPO_INGRESO = 'ingreso';
    const TIPO_SALIDA  = 'salida';

    /**
     * Los usa el modulo de edicion de asistencia. La correccion se manda
     * despues de guardar; la eliminacion, antes de borrar la fila, porque el
     * mensaje se arma leyendola.
     */
    const TIPO_CORRECCION  = 'correccion';
    const TIPO_ELIMINACION = 'eliminacion';

    /**
     * Lo manda la grilla de utiles y accesorios diarios cuando la docente
     * graba, para contarle al acudiente que trajo y que no trajo el nino.
     * Ese dato no alcanza a ir en la notificacion de ingreso, porque al
     * registrar la asistencia casi siempre queda en sin verificar.
     */
    const TIPO_UTILES = 'utiles';

    /**
     * Categorias propias de asistencia. Van separadas por evento para que el
     * jardin pueda filtrar y reportar las llegadas aparte de las salidas.
     *
     * Si alguna no existe en el tenant se cae a GENERAL, para que la
     * notificacion salga igual aunque el catalogo no se haya sembrado.
     */
    const CODIGO_CATEGORIA_INGRESO = 'ASISTENCIA_INGRESO';
    const CODIGO_CATEGORIA_SALIDA  = 'ASISTENCIA_SALIDA';
    const CODIGO_CATEGORIA_RESPALDO = 'GENERAL';

    /**
     * Envia la notificacion de los utiles de un estudiante en una fecha.
     *
     * La invoca la grilla de utiles y accesorios diarios, y solo cuando la
     * docente graba a peticion: en el autoguardado no, porque le llegarian
     * avisos al acudiente cada vez que toca una celda.
     *
     * Igual que enviar(), nada de lo que pase aqui puede tumbar el guardado.
     *
     * @param  PDO    $db
     * @param  string $idEstudiante
     * @param  string $fecha        Y-m-d
     * @param  string $modo         'entrada' o 'salida'
     * @param  string $idUsuario    Usuario que grabo
     * @return array  Resultado informativo; nunca lanza excepcion
     */
    public static function enviarUtiles(PDO $db, $idEstudiante, $fecha, $modo, $idUsuario)
    {
        $resultado = array('enviada' => false, 'motivo' => null);

        try {
            if (empty($idEstudiante) || empty($fecha)) {
                $resultado['motivo'] = 'sin estudiante o sin fecha';
                return $resultado;
            }

            $cuerpo = self::armarCuerpoUtiles($db, $idEstudiante, $fecha, $modo);

            // Si quedo todo en sin verificar no hay nada que contar.
            if ($cuerpo === '') {
                $resultado['motivo'] = 'no hay útiles marcados';
                return $resultado;
            }

            $categoria = self::obtenerCategoria($db, $modo === 'salida' ? self::TIPO_SALIDA : self::TIPO_INGRESO);

            if (!$categoria) {
                $resultado['motivo'] = 'no hay categoria de notificaciones configurada';
                return $resultado;
            }

            $destinatarios = self::obtenerAcudientes($db, $idEstudiante);

            if (count($destinatarios) === 0) {
                $resultado['motivo'] = 'el estudiante no tiene acudientes habilitados en el portal';
                return $resultado;
            }

            $nombre = self::obtenerNombreEstudiante($db, $idEstudiante);
            $titulo = $modo === 'salida'
                ? 'Lo que ' . $nombre . ' se llevó a casa'
                : 'Lo que ' . $nombre . ' trajo al jardín';

            // guardar() solo usa id y id_estudiante del registro de asistencia,
            // asi que se le pasa lo minimo: aqui no hay movimiento asociado.
            $idNotificacion = self::guardar(
                $db,
                array('id' => null, 'id_estudiante' => $idEstudiante),
                $categoria,
                $titulo,
                $cuerpo,
                $destinatarios,
                $idUsuario
            );

            $idsUsuarios = array();
            foreach ($destinatarios as $destinatario) {
                if (!empty($destinatario['id_usuario'])) {
                    $idsUsuarios[$destinatario['id_usuario']] = true;
                }
            }

            $push = array('enviadas' => 0);

            if (count($idsUsuarios) > 0) {
                $pushService = new PushNotificationService($db);
                $push = $pushService->notificarAUsuarios(
                    array_keys($idsUsuarios),
                    $titulo,
                    self::generarPreview($cuerpo),
                    array(
                        'id_notificacion' => $idNotificacion,
                        'tipo'            => 'notificacion',
                    ),
                    JWTService::PORTAL_PADRES
                );
            }

            $resultado['enviada'] = true;
            $resultado['id_notificacion'] = $idNotificacion;
            $resultado['destinatarios'] = count($destinatarios);
            $resultado['push'] = $push;

            return $resultado;
        } catch (Exception $e) {
            error_log('[NotificacionesAsistencia] Error enviando útiles: ' . $e->getMessage());
            $resultado['motivo'] = $e->getMessage();
            return $resultado;
        }
    }

    /**
     * Envia la notificacion de asistencia.
     *
     * Se llama desde dos lados: desde el registro de asistencia cuando no hay
     * cobros que ejecutar, y desde el motor de cobros cuando si los hay. De
     * ese modo el mensaje siempre alcanza a incluir los cobros generados, sin
     * depender de una tercera peticion que el navegador podria cancelar.
     *
     * @param  PDO    $db
     * @param  string $idAsistencia Registro de asistencia_estudiantes
     * @param  string $tipo         self::TIPO_INGRESO o self::TIPO_SALIDA
     * @param  string $idUsuario    Usuario que registro el movimiento
     * @return array  Resultado informativo; nunca lanza excepcion
     */
    public static function enviar(PDO $db, $idAsistencia, $tipo, $idUsuario)
    {
        $resultado = array('enviada' => false, 'motivo' => null);

        try {
            if (empty($idAsistencia)) {
                $resultado['motivo'] = 'sin id de asistencia';
                return $resultado;
            }

            $asistencia = self::obtenerAsistencia($db, $idAsistencia);

            if (!$asistencia) {
                $resultado['motivo'] = 'registro de asistencia no encontrado';
                return $resultado;
            }

            $categoria = self::obtenerCategoria($db, $tipo);

            if (!$categoria) {
                $resultado['motivo'] = 'no hay categoria de notificaciones configurada';
                error_log('[NotificacionesAsistencia] Sin categoria de asistencia ni GENERAL en el tenant ' . TenantContext::id());
                return $resultado;
            }

            $destinatarios = self::obtenerAcudientes($db, $asistencia['id_estudiante']);

            if (count($destinatarios) === 0) {
                $resultado['motivo'] = 'el estudiante no tiene acudientes habilitados en el portal';
                return $resultado;
            }

            $titulo = self::armarTitulo($asistencia, $tipo);
            $cuerpo = self::armarCuerpo($db, $asistencia, $tipo);

            $idNotificacion = self::guardar($db, $asistencia, $categoria, $titulo, $cuerpo, $destinatarios, $idUsuario);

            $idsUsuarios = array();
            foreach ($destinatarios as $destinatario) {
                if (!empty($destinatario['id_usuario'])) {
                    $idsUsuarios[$destinatario['id_usuario']] = true;
                }
            }

            $push = array('enviadas' => 0);

            if (count($idsUsuarios) > 0) {
                $pushService = new PushNotificationService($db);
                $push = $pushService->notificarAUsuarios(
                    array_keys($idsUsuarios),
                    $titulo,
                    self::generarPreview($cuerpo),
                    array(
                        'id_notificacion' => $idNotificacion,
                        'tipo'            => 'notificacion',
                    ),
                    JWTService::PORTAL_PADRES
                );
            }

            $resultado['enviada'] = true;
            $resultado['id_notificacion'] = $idNotificacion;
            $resultado['destinatarios'] = count($destinatarios);
            $resultado['push'] = $push;

            return $resultado;
        } catch (Exception $e) {
            // El registro de asistencia ya quedo guardado: aqui solo se avisa.
            error_log('[NotificacionesAsistencia] ' . $e->getMessage());
            $resultado['motivo'] = 'error al enviar';
            return $resultado;
        }
    }

    /**
     * Datos del registro de asistencia con el nombre del estudiante.
     */
    private static function obtenerAsistencia(PDO $db, $idAsistencia)
    {
        $sentence = $db->prepare("
            SELECT
                a.id,
                a.id_estudiante,
                a.fecha_ingreso,
                a.fecha_salida,
                a.observacion_ingreso,
                a.observacion_salida,
                p.primer_nombre    AS estudiante_primer_nombre,
                p.primer_apellido  AS estudiante_primer_apellido,
                NULLIF(TRIM(CONCAT_WS(' ', ppe.primer_nombre, ppe.primer_apellido)), '') AS persona_entrega,
                NULLIF(TRIM(CONCAT_WS(' ', ppr.primer_nombre, ppr.primer_apellido)), '') AS persona_recoge,
                NULLIF(TRIM(CONCAT_WS(' ', pcr.primer_nombre, pcr.primer_apellido)), '') AS colaborador_recibe,
                NULLIF(TRIM(CONCAT_WS(' ', pce.primer_nombre, pce.primer_apellido)), '') AS colaborador_entrega
            FROM asistencia_estudiantes a
            INNER JOIN estudiantes e ON e.id = a.id_estudiante
            INNER JOIN personas p    ON p.id = e.id_persona
            LEFT JOIN personas ppe ON ppe.id = a.id_persona_entrega
            LEFT JOIN personas ppr ON ppr.id = a.id_persona_recoge
            LEFT JOIN colaboradores ccr ON ccr.id = a.id_colaborador_recibe
            LEFT JOIN personas pcr ON pcr.id = ccr.id_persona
            LEFT JOIN colaboradores cce ON cce.id = a.id_colaborador_entrega
            LEFT JOIN personas pce ON pce.id = cce.id_persona
            WHERE a.id = :id AND a.id_tenant = :id_tenant
        ");
        $sentence->bindValue(':id', $idAsistencia);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        return $sentence->fetch();
    }

    /**
     * Categoria segun el evento, con GENERAL como respaldo.
     */
    private static function obtenerCategoria(PDO $db, $tipo)
    {
        // Correccion y eliminacion no tienen categoria propia: se mandan por
        // la de salida y, si el tenant no la tiene, cae a GENERAL como todas.
        $codigo = ($tipo === self::TIPO_SALIDA
                || $tipo === self::TIPO_CORRECCION
                || $tipo === self::TIPO_ELIMINACION)
            ? self::CODIGO_CATEGORIA_SALIDA
            : self::CODIGO_CATEGORIA_INGRESO;

        $sentence = $db->prepare("SELECT id FROM notificaciones_categorias WHERE codigo = :codigo AND id_tenant = :id_tenant AND activo = 1 LIMIT 1");

        foreach (array($codigo, self::CODIGO_CATEGORIA_RESPALDO) as $buscado) {
            $sentence->bindValue(':codigo', $buscado);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $fila = $sentence->fetch();

            if ($fila) {
                return $fila['id'];
            }
        }

        return null;
    }

    /**
     * Acudientes habilitados del estudiante. Mismo criterio que usa el envio
     * manual: vinculo activo y ve_en_portal_padres = 1.
     */
    private static function obtenerAcudientes(PDO $db, $idEstudiante)
    {
        $sentence = $db->prepare("
            SELECT DISTINCT
                a.id_estudiante,
                a.id_persona,
                u.id AS id_usuario
            FROM acudientes a
            LEFT JOIN usuarios u
                   ON u.id_persona = a.id_persona
                  AND u.id_tenant = a.id_tenant
                  AND u.activo = 1
                  AND u.acceso_portal_padres = 1
            WHERE a.id_tenant = :id_tenant
              AND a.activo = 1
              AND a.ve_en_portal_padres = 1
              AND a.id_estudiante = :id_estudiante
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_estudiante', $idEstudiante);
        $sentence->execute();

        return $sentence->fetchAll();
    }

    private static function armarTitulo($asistencia, $tipo)
    {
        $nombre = trim(($asistencia['estudiante_primer_nombre'] ?? '') . ' ' . ($asistencia['estudiante_primer_apellido'] ?? ''));

        if ($tipo === self::TIPO_CORRECCION) {
            return 'Corregimos el registro de ' . $nombre;
        }

        if ($tipo === self::TIPO_ELIMINACION) {
            return 'Eliminamos un registro de ' . $nombre;
        }

        return $tipo === self::TIPO_SALIDA
            ? $nombre . ' salió del jardín'
            : $nombre . ' llegó al jardín';
    }

    /**
     * Arma el cuerpo con lo que quedo registrado en el movimiento: hora,
     * observacion de la docente, utiles del dia y cobros generados.
     */
    /**
     * Quien entrego o recogio al nino y que persona del jardin lo recibio o
     * lo entrego. Los dos datos son opcionales: se arma solo con lo que haya
     * y devuelve cadena vacia si no hay ninguno.
     *
     * @param string $tipo self::TIPO_INGRESO o self::TIPO_SALIDA
     * @return string
     */
    private static function armarLineaEntrega($asistencia, $tipo)
    {
        if ($tipo === self::TIPO_SALIDA) {
            $persona = isset($asistencia['persona_recoge']) ? $asistencia['persona_recoge'] : null;
            $colaborador = isset($asistencia['colaborador_entrega']) ? $asistencia['colaborador_entrega'] : null;
            $textoPersona = 'Lo recogió ';
            $textoColaborador = 'Lo entregó ';
        } else {
            $persona = isset($asistencia['persona_entrega']) ? $asistencia['persona_entrega'] : null;
            $colaborador = isset($asistencia['colaborador_recibe']) ? $asistencia['colaborador_recibe'] : null;
            $textoPersona = 'Lo trajo ';
            $textoColaborador = 'Lo recibió ';
        }

        $partes = array();

        if (!empty($persona)) {
            $partes[] = $textoPersona . $persona;
        }

        if (!empty($colaborador)) {
            $partes[] = $textoColaborador . $colaborador;
        }

        return count($partes) > 0 ? implode('. ', $partes) . '.' : '';
    }

    private static function armarCuerpo(PDO $db, $asistencia, $tipo)
    {
        $nombre = trim($asistencia['estudiante_primer_nombre'] ?? '');
        $lineas = array();

        // Correccion y eliminacion cuentan el movimiento completo, porque lo
        // que le interesa al acudiente es como quedo (o que ya no esta), no
        // solo la hora que se toco.
        if ($tipo === self::TIPO_CORRECCION || $tipo === self::TIPO_ELIMINACION) {
            $fecha = !empty($asistencia['fecha_ingreso'])
                ? date('d/m/Y', strtotime($asistencia['fecha_ingreso']))
                : '';
            $horaIngreso = !empty($asistencia['fecha_ingreso']) ? date('h:i a', strtotime($asistencia['fecha_ingreso'])) : null;
            $horaSalida  = !empty($asistencia['fecha_salida']) ? date('h:i a', strtotime($asistencia['fecha_salida'])) : null;

            if ($tipo === self::TIPO_ELIMINACION) {
                $lineas[] = 'Eliminamos el registro de asistencia de ' . $nombre
                    . ($fecha !== '' ? ' del ' . $fecha : '') . '.';
                $lineas[] = 'Si tienes alguna duda, escríbenos.';
                return implode("\n", $lineas);
            }

            $lineas[] = 'Corregimos el registro de asistencia de ' . $nombre
                . ($fecha !== '' ? ' del ' . $fecha : '') . '. Así quedó:';
            $lineas[] = '';
            $lineas[] = 'Ingreso: ' . ($horaIngreso ? $horaIngreso : 'sin registrar');

            $entrega = self::armarLineaEntrega($asistencia, self::TIPO_INGRESO);
            if ($entrega !== '') {
                $lineas[] = $entrega;
            }

            $lineas[] = 'Salida: ' . ($horaSalida ? $horaSalida : 'sin registrar');

            if (!empty($asistencia['fecha_salida'])) {
                $recogida = self::armarLineaEntrega($asistencia, self::TIPO_SALIDA);
                if ($recogida !== '') {
                    $lineas[] = $recogida;
                }
            }

            if (!empty($asistencia['observacion_ingreso'])) {
                $lineas[] = 'Observación de ingreso: ' . $asistencia['observacion_ingreso'];
            }

            if (!empty($asistencia['observacion_salida'])) {
                $lineas[] = 'Observación de salida: ' . $asistencia['observacion_salida'];
            }

            $cobrosCorreccion = self::armarBloqueCobros($db, $asistencia['id']);
            if ($cobrosCorreccion !== '') {
                $lineas[] = '';
                $lineas[] = $cobrosCorreccion;
            }

            return implode("\n", $lineas);
        }

        if ($tipo === self::TIPO_SALIDA) {
            $hora = !empty($asistencia['fecha_salida']) ? date('h:i a', strtotime($asistencia['fecha_salida'])) : null;
            $lineas[] = $hora
                ? $nombre . ' salió del jardín a las ' . $hora . '.'
                : $nombre . ' salió del jardín.';

            $entrega = self::armarLineaEntrega($asistencia, self::TIPO_SALIDA);
            if ($entrega !== '') {
                $lineas[] = '';
                $lineas[] = $entrega;
            }

            if (!empty($asistencia['observacion_salida'])) {
                $lineas[] = '';
                $lineas[] = 'Observación: ' . $asistencia['observacion_salida'];
            }
        } else {
            $hora = !empty($asistencia['fecha_ingreso']) ? date('h:i a', strtotime($asistencia['fecha_ingreso'])) : null;
            $lineas[] = $hora
                ? $nombre . ' llegó al jardín a las ' . $hora . '.'
                : $nombre . ' llegó al jardín.';

            $entrega = self::armarLineaEntrega($asistencia, self::TIPO_INGRESO);
            if ($entrega !== '') {
                $lineas[] = '';
                $lineas[] = $entrega;
            }

            if (!empty($asistencia['observacion_ingreso'])) {
                $lineas[] = '';
                $lineas[] = 'Observación: ' . $asistencia['observacion_ingreso'];
            }
        }

        $utiles = self::armarBloqueUtiles($db, $asistencia, $tipo);
        if ($utiles !== '') {
            $lineas[] = '';
            $lineas[] = $utiles;
        }

        $cobros = self::armarBloqueCobros($db, $asistencia['id']);
        if ($cobros !== '') {
            $lineas[] = '';
            $lineas[] = $cobros;
        }

        return implode("\n", $lineas);
    }

    /**
     * En el ingreso se listan los utiles que el niño trajo. En la salida se
     * listan los que no regresaron, que es lo que al acudiente le interesa
     * saber esa tarde.
     */
    /**
     * Cuerpo del mensaje de utiles: lo que trajo y lo que no trajo en modo
     * entrada, y lo que regreso y lo que no en modo salida. Lo que quedo sin
     * verificar no se nombra.
     *
     * @return string Cadena vacia si no hay nada marcado.
     */
    private static function armarCuerpoUtiles(PDO $db, $idEstudiante, $fecha, $modo)
    {
        $columna = $modo === 'salida' ? 'r.regreso' : 'r.trajo';

        $sentence = $db->prepare("
            SELECT COALESCE(ud.nombre, r.nombre_libre) AS nombre,
                   $columna AS estado,
                   r.observacion
            FROM utiles_diarios_registro r
            LEFT JOIN utiles_diarios ud ON ud.id = r.id_util_diario
            WHERE r.id_tenant = :id_tenant
              AND r.id_estudiante = :id_estudiante
              AND r.fecha = :fecha
              AND $columna IS NOT NULL
            ORDER BY nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_estudiante', $idEstudiante);
        $sentence->bindValue(':fecha', $fecha);
        $sentence->execute();

        $si = array();
        $no = array();
        $notas = array();

        foreach ($sentence->fetchAll() as $fila) {
            if (empty($fila['nombre'])) {
                continue;
            }

            if (intval($fila['estado']) === 1) {
                $si[] = $fila['nombre'];
            } else {
                $no[] = $fila['nombre'];
            }

            if (!empty($fila['observacion'])) {
                $notas[] = $fila['nombre'] . ': ' . $fila['observacion'];
            }
        }

        if (count($si) === 0 && count($no) === 0) {
            return '';
        }

        $lineas = array();

        if ($modo === 'salida') {
            if (count($si) > 0) {
                $lineas[] = 'Regresó a casa con: ' . implode(', ', $si) . '.';
            }
            if (count($no) > 0) {
                $lineas[] = 'No regresaron a casa: ' . implode(', ', $no) . '.';
            }
        } else {
            if (count($si) > 0) {
                $lineas[] = 'Útiles que trajo: ' . implode(', ', $si) . '.';
            }
            if (count($no) > 0) {
                $lineas[] = 'No trajo: ' . implode(', ', $no) . '.';
            }
        }

        if (count($notas) > 0) {
            $lineas[] = '';
            $lineas[] = 'Notas: ' . implode(' · ', $notas);
        }

        return implode("\n", $lineas);
    }

    /**
     * Primer nombre del estudiante, para el titulo del mensaje.
     */
    private static function obtenerNombreEstudiante(PDO $db, $idEstudiante)
    {
        $sentence = $db->prepare("
            SELECT p.primer_nombre
            FROM estudiantes e
            INNER JOIN personas p ON p.id = e.id_persona
            WHERE e.id = :id AND e.id_tenant = :id_tenant
        ");
        $sentence->bindValue(':id', $idEstudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? trim($fila['primer_nombre']) : 'tu hijo';
    }

    private static function armarBloqueUtiles(PDO $db, $asistencia, $tipo)
    {
        try {
            $condicion = $tipo === self::TIPO_SALIDA
                ? 'r.trajo = 1 AND (r.regreso IS NULL OR r.regreso = 0)'
                : 'r.trajo = 1';

            $sentence = $db->prepare("
                SELECT COALESCE(ud.nombre, r.nombre_libre) AS nombre
                FROM utiles_diarios_registro r
                LEFT JOIN utiles_diarios ud ON ud.id = r.id_util_diario
                WHERE r.id_tenant = :id_tenant
                  AND r.id_estudiante = :id_estudiante
                  AND r.fecha = :fecha
                  AND $condicion
                ORDER BY nombre
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_estudiante', $asistencia['id_estudiante']);
            $sentence->bindValue(':fecha', date('Y-m-d', strtotime($asistencia['fecha_ingreso'])));
            $sentence->execute();

            $filas = $sentence->fetchAll();

            if (count($filas) === 0) {
                return '';
            }

            $nombres = array();
            foreach ($filas as $fila) {
                if (!empty($fila['nombre'])) {
                    $nombres[] = $fila['nombre'];
                }
            }

            if (count($nombres) === 0) {
                return '';
            }

            // En el ingreso se dice "registrados" y no "que trajo" porque en ese
            // momento la docente casi nunca ha revisado toda la maleta: lo que
            // va en la lista es lo que alcanzo a marcar.
            $encabezado = $tipo === self::TIPO_SALIDA
                ? 'No regresaron a casa: '
                : 'Útiles registrados en el ingreso: ';

            return $encabezado . implode(', ', $nombres) . '.';
        } catch (Exception $e) {
            error_log('[NotificacionesAsistencia] Error armando útiles: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Cobros generados en este movimiento. Se leen del historial, que es lo
     * que enlaza la cuenta por cobrar con el registro de asistencia.
     */
    private static function armarBloqueCobros(PDO $db, $idAsistencia)
    {
        try {
            $sentence = $db->prepare("
                SELECT c.valor, c.detalle
                FROM cobros_automaticos_historial h
                INNER JOIN cuentas_por_cobrar c ON c.id = h.id_cuenta_por_cobrar
                WHERE h.id_tenant = :id_tenant
                  AND h.id_asistencia_estudiante = :id_asistencia
                  AND c.anulado = 0
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_asistencia', $idAsistencia);
            $sentence->execute();

            $filas = $sentence->fetchAll();

            if (count($filas) === 0) {
                return '';
            }

            $lineas = array('Se registraron estos cobros:');
            $total = 0;

            foreach ($filas as $fila) {
                $valor = (float)$fila['valor'];
                $total += $valor;
                $lineas[] = '- ' . $fila['detalle'] . ': $' . number_format($valor, 0, ',', '.');
            }

            if (count($filas) > 1) {
                $lineas[] = 'Total: $' . number_format($total, 0, ',', '.');
            }

            return implode("\n", $lineas);
        } catch (Exception $e) {
            error_log('[NotificacionesAsistencia] Error armando cobros: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Crea la notificacion y sus destinatarios.
     *
     * incluir_whatsapp queda en 0: estas notificaciones son automaticas y de
     * alto volumen, invitar a responder por WhatsApp en cada entrada y salida
     * le caeria encima al jardin.
     */
    private static function guardar(PDO $db, $asistencia, $idCategoria, $titulo, $cuerpo, array $destinatarios, $idUsuario)
    {
        $idNotificacion = Uuid::generar();

        $insertar = $db->prepare("
            INSERT INTO notificaciones
                (id, id_tenant, titulo, cuerpo, id_categoria, id_respuesta_tipo, id_plantilla, criterio_texto,
                 incluir_whatsapp, whatsapp_numero, enviar_correo, id_usuario_envio)
            VALUES
                (:id, :id_tenant, :titulo, :cuerpo, :id_categoria, NULL, NULL, :criterio_texto,
                 0, NULL, 0, :id_usuario_envio)
        ");
        $insertar->bindValue(':id', $idNotificacion);
        $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $insertar->bindValue(':titulo', $titulo);
        $insertar->bindValue(':cuerpo', $cuerpo);
        $insertar->bindValue(':id_categoria', $idCategoria);
        $insertar->bindValue(':criterio_texto', 'Acudientes del estudiante');
        $insertar->bindValue(':id_usuario_envio', $idUsuario);
        $insertar->execute();

        $insertarDestinatario = $db->prepare("
            INSERT INTO notificaciones_destinatarios
                (id, id_tenant, id_notificacion, id_estudiante, id_persona, id_usuario)
            VALUES
                (:id, :id_tenant, :id_notificacion, :id_estudiante, :id_persona, :id_usuario)
        ");

        foreach ($destinatarios as $destinatario) {
            $insertarDestinatario->bindValue(':id', Uuid::generar());
            $insertarDestinatario->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $insertarDestinatario->bindValue(':id_notificacion', $idNotificacion);
            $insertarDestinatario->bindValue(':id_estudiante', $destinatario['id_estudiante']);
            $insertarDestinatario->bindValue(':id_persona', $destinatario['id_persona']);
            $insertarDestinatario->bindValue(':id_usuario', $destinatario['id_usuario']);
            $insertarDestinatario->execute();
        }

        return $idNotificacion;
    }

    private static function generarPreview($cuerpo)
    {
        $texto = trim(preg_replace('/\s+/u', ' ', strip_tags($cuerpo)));

        if (mb_strlen($texto, 'UTF-8') > 120) {
            $texto = mb_substr($texto, 0, 117, 'UTF-8') . '...';
        }

        return $texto;
    }
}