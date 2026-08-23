<?php
/*=============================================
SERVICIO - MI AGENDA
Archivo: services/mi-agenda.service.php

Agregador de solo lectura: no tiene tabla propia. Arma en tiempo de
ejecucion la linea de tiempo de un estudiante en una fecha, leyendo las
tablas de cada modulo.

El catalogo FUENTES es el unico punto de registro. Para sumar una fuente
nueva basta agregar una entrada aqui y su metodo fuenteXxx(); nada mas
cambia. Cada metodo recibe la misma firma y devuelve un arreglo de
eventos ya normalizados.

Si una fuente falla, se reporta en `fuentes_con_error` y las demas siguen:
un modulo roto no puede tumbar la agenda completa.
=============================================*/

class MiAgenda
{
    /** Estado 'Ejecutada' de tareas_x_sprints. Solo esas ve el acudiente. */
    const ESTADO_TAREA_EJECUTADA = 2;

    /** Estados de solicitudes que siguen vigentes para mostrar. */
    const ESTADO_SOLICITUD_PENDIENTE  = 1;
    const ESTADO_SOLICITUD_AUTORIZADO = 2;

    /** Estado 1 de entregas_alimentacion = entregada. */
    const ESTADO_ALIMENTACION_ENTREGADA = 1;

    /**
     * Catalogo de fuentes. La clave es la que viaja al front y la que se
     * puede pedir en el parametro `fuentes` del endpoint.
     *
     * orden = posicion por defecto dentro del dia cuando el evento no tiene
     * hora propia. Los que si tienen hora se ordenan por hora.
     */
    const FUENTES = [
        'asistencia' => [
            'nombre' => 'Entradas y salidas',
            'icono'  => '🚪',
            'color'  => '#1abc9c',
            'metodo' => 'fuenteAsistencia',
            'orden'  => 1,
        ],
        'utiles' => [
            'nombre' => 'Útiles y accesorios',
            'icono'  => '🎒',
            'color'  => '#e67e22',
            'metodo' => 'fuenteUtiles',
            'orden'  => 2,
        ],
        'actividades' => [
            'nombre' => 'Actividades',
            'icono'  => '🎨',
            'color'  => '#3498db',
            'metodo' => 'fuenteActividades',
            'orden'  => 3,
        ],
        'observaciones' => [
            'nombre' => 'Observaciones',
            'icono'  => '💬',
            'color'  => '#9b59b6',
            'metodo' => 'fuenteObservaciones',
            'orden'  => 4,
        ],
        'alimentacion' => [
            'nombre' => 'Alimentación',
            'icono'  => '🍽️',
            'color'  => '#27ae60',
            'metodo' => 'fuenteAlimentacion',
            'orden'  => 5,
        ],
        'solicitudes' => [
            'nombre' => 'Solicitudes',
            'icono'  => '📝',
            'color'  => '#f39c12',
            'metodo' => 'fuenteSolicitudes',
            'orden'  => 6,
        ],
        'galerias' => [
            'nombre' => 'Fotos del día',
            'icono'  => '📷',
            'color'  => '#e84393',
            'metodo' => 'fuenteGalerias',
            'orden'  => 7,
        ],
        'notificaciones' => [
            'nombre' => 'Notificaciones',
            'icono'  => '🔔',
            'color'  => '#2980b9',
            'metodo' => 'fuenteNotificaciones',
            'orden'  => 8,
        ],
        'medidas' => [
            'nombre' => 'Medidas',
            'icono'  => '📏',
            'color'  => '#16a085',
            'metodo' => 'fuenteMedidas',
            'orden'  => 9,
        ],
        'pagos' => [
            'nombre' => 'Pagos recibidos',
            'icono'  => '💰',
            'color'  => '#2ecc71',
            'metodo' => 'fuentePagos',
            'orden'  => 10,
        ],
        'cuentas' => [
            'nombre' => 'Cuentas generadas',
            'icono'  => '🧾',
            'color'  => '#c0392b',
            'metodo' => 'fuenteCuentas',
            'orden'  => 11,
        ],
    ];

    // =====================================================================
    // ENDPOINTS
    // =====================================================================

    /**
     * Catalogo de fuentes disponibles. Lo usa el front para pintar los tabs
     * y los filtros sin tener que repetir la lista en el codigo del cliente.
     */
    public static function getFuentes()
    {
        JWTService::requerirAutenticacion();

        $salida = [];
        foreach (self::FUENTES as $clave => $config) {
            $salida[] = [
                'clave'  => $clave,
                'nombre' => $config['nombre'],
                'icono'  => $config['icono'],
                'color'  => $config['color'],
                'orden'  => $config['orden'],
            ];
        }

        Flight::json($salida);
    }

    /**
     * Linea de tiempo de un estudiante en una fecha.
     *
     * @param string $id_estudiante
     * @param string $fecha Formato Y-m-d
     */
    public static function getDia($id_estudiante, $fecha)
    {
        $userData = JWTService::requerirAutenticacion();

        if (!self::fechaValida($fecha)) {
            Flight::json(['error' => 'Formato de fecha invalido. Se espera Y-m-d'], 400);
            return;
        }

        $db = Flight::db();

        // Barrera de acceso: en el portal de padres el acudiente solo puede
        // consultar a los estudiantes que tiene asociados y habilitados.
        // El front ya lo filtra, pero eso no sirve de nada si alguien cambia
        // el uuid en la URL.
        if (!self::puedeVerEstudiante($db, $userData, $id_estudiante)) {
            Flight::json([
                'error' => 'No tienes acceso a la informacion de este estudiante',
                'code'  => 'FORBIDDEN'
            ], 403);
            return;
        }

        $estudiante = self::obtenerEstudiante($db, $id_estudiante);

        if (!$estudiante) {
            Flight::json(['error' => 'Estudiante no encontrado'], 404);
            return;
        }

        // Contexto compartido por todas las fuentes. Se calcula una sola vez
        // para que ninguna tenga que volver a resolver el grupo ni la persona.
        $contexto = [
            'id_grupo'   => $estudiante['id_grupo'],
            'id_persona' => $estudiante['id_persona'],
            'anio'       => (int) date('Y', strtotime($fecha)),
        ];

        $clavesPedidas = self::clavesPedidas();

        $eventos = [];
        $errores = [];
        $totales = [];

        foreach ($clavesPedidas as $clave) {
            $config = self::FUENTES[$clave];

            try {
                $delaFuente = call_user_func(
                    [self::class, $config['metodo']],
                    $db,
                    $id_estudiante,
                    $fecha,
                    $contexto
                );
            } catch (Exception $e) {
                // Una fuente caida no puede dejar al papa sin agenda.
                error_log("Mi Agenda - fuente '{$clave}' fallo: " . $e->getMessage());
                $errores[] = ['clave' => $clave, 'mensaje' => $e->getMessage()];
                $delaFuente = [];
            }

            $totales[$clave] = count($delaFuente);
            $eventos = array_merge($eventos, $delaFuente);
        }

        usort($eventos, [self::class, 'compararEventos']);

        Flight::json([
            'id_estudiante'    => $id_estudiante,
            'fecha'            => $fecha,
            'fecha_minima'     => $estudiante['fecha_ingreso'],
            'estudiante'       => [
                'id'              => $estudiante['id'],
                'nombre_completo' => $estudiante['nombre_completo'],
                'id_grupo'        => $estudiante['id_grupo'],
                'nombre_grupo'    => $estudiante['nombre_grupo'],
                'foto'            => $estudiante['foto'],
            ],
            'eventos'          => $eventos,
            'fuentes'          => self::resumenFuentes($clavesPedidas, $totales),
            'fuentes_con_error' => $errores,
            'total_eventos'    => count($eventos),
        ]);
    }

    // =====================================================================
    // FUENTES
    // Cada una recibe ($db, $id_estudiante, $fecha, $contexto) y devuelve
    // un arreglo de eventos normalizados por self::evento().
    // =====================================================================

    /**
     * Entradas y salidas del dia. Genera un evento por cada ingreso y otro
     * por cada salida, porque un nino puede entrar y salir varias veces.
     */
    private static function fuenteAsistencia($db, $id_estudiante, $fecha, $contexto)
    {
        $sentence = $db->prepare("
            SELECT a.id,
                   a.fecha_ingreso,
                   a.fecha_salida,
                   a.observacion_ingreso,
                   a.observacion_salida,
                   TRIM(CONCAT_WS(' ', pi.primer_nombre, pi.primer_apellido)) AS nombre_usuario_ingreso,
                   TRIM(CONCAT_WS(' ', ps.primer_nombre, ps.primer_apellido)) AS nombre_usuario_salida
            FROM asistencia_estudiantes a
            LEFT JOIN usuarios ui ON ui.id = a.id_usuario_ingreso
            LEFT JOIN personas pi ON pi.id = ui.id_persona
            LEFT JOIN usuarios us ON us.id = a.id_usuario_salida
            LEFT JOIN personas ps ON ps.id = us.id_persona
            WHERE a.id_tenant = :id_tenant
              AND a.id_estudiante = :id_estudiante
              AND (DATE(a.fecha_ingreso) = :fecha OR DATE(a.fecha_salida) = :fecha_salida)
            ORDER BY a.fecha_ingreso
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':fecha_salida', $fecha);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $eventos = [];

        foreach ($filas as $fila) {
            if (!empty($fila['fecha_ingreso']) && substr($fila['fecha_ingreso'], 0, 10) === $fecha) {
                $eventos[] = self::evento('asistencia', 'ingreso', $fila['id'] . '-in', [
                    'fecha_hora' => $fila['fecha_ingreso'],
                    'titulo'     => 'Llegó al jardín',
                    'detalle'    => $fila['observacion_ingreso'],
                    'pie'        => $fila['nombre_usuario_ingreso'] ? 'Recibido por ' . $fila['nombre_usuario_ingreso'] : null,
                    'orden'      => 10,
                    'meta'       => ['id_asistencia' => $fila['id']],
                ]);
            }

            if (!empty($fila['fecha_salida']) && substr($fila['fecha_salida'], 0, 10) === $fecha) {
                $eventos[] = self::evento('asistencia', 'salida', $fila['id'] . '-out', [
                    'fecha_hora' => $fila['fecha_salida'],
                    'titulo'     => 'Salió del jardín',
                    'detalle'    => $fila['observacion_salida'],
                    'pie'        => $fila['nombre_usuario_salida'] ? 'Entregado por ' . $fila['nombre_usuario_salida'] : null,
                    'orden'      => 900,
                    'meta'       => ['id_asistencia' => $fila['id']],
                ]);
            }
        }

        return $eventos;
    }

    /**
     * Utiles del dia. Se agrupan en dos eventos (lo que trajo y lo que
     * regreso) en lugar de uno por util: una lista de diez renglones sueltos
     * ahoga la agenda.
     */
    private static function fuenteUtiles($db, $id_estudiante, $fecha, $contexto)
    {
        $sentence = $db->prepare("
            SELECT r.id,
                   r.trajo,
                   r.regreso,
                   r.observacion,
                   COALESCE(u.nombre, r.nombre_libre) AS nombre,
                   u.icono,
                   COALESCE(u.orden, 999) AS orden
            FROM utiles_diarios_registro r
            LEFT JOIN utiles_diarios u ON u.id = r.id_util_diario
            WHERE r.id_tenant = :id_tenant
              AND r.id_estudiante = :id_estudiante
              AND r.fecha = :fecha
            ORDER BY orden, nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        if (empty($filas)) {
            return [];
        }

        $trajo      = [];
        $noRegreso  = [];
        $regreso    = [];

        foreach ($filas as $fila) {
            $item = [
                'nombre'      => $fila['nombre'],
                'icono'       => $fila['icono'],
                'observacion' => $fila['observacion'],
            ];

            if ((int) $fila['trajo'] === 1) {
                $trajo[] = $item;

                // regreso NULL significa que aun no se ha registrado la
                // salida, distinto de haberla registrado en 0 (se quedo).
                if ($fila['regreso'] !== null && (int) $fila['regreso'] === 0) {
                    $noRegreso[] = $item;
                } elseif ((int) $fila['regreso'] === 1) {
                    $regreso[] = $item;
                }
            }
        }

        $eventos = [];

        if (!empty($trajo)) {
            $eventos[] = self::evento('utiles', 'trajo', 'utiles-in-' . $fecha, [
                'fecha_hora' => null,
                'titulo'     => 'Llegó con ' . count($trajo) . ' ' . (count($trajo) === 1 ? 'cosa' : 'cosas'),
                'detalle'    => self::listaDeNombres($trajo),
                'orden'      => 20,
                'meta'       => ['items' => $trajo],
            ]);
        }

        if (!empty($regreso) || !empty($noRegreso)) {
            $titulo = !empty($noRegreso)
                ? 'Se quedaron ' . count($noRegreso) . ' ' . (count($noRegreso) === 1 ? 'cosa' : 'cosas')
                : 'Se llevó todo de vuelta';

            $eventos[] = self::evento('utiles', 'regreso', 'utiles-out-' . $fecha, [
                'fecha_hora' => null,
                'titulo'     => $titulo,
                'detalle'    => !empty($noRegreso)
                    ? 'No regresó: ' . self::listaDeNombres($noRegreso)
                    : self::listaDeNombres($regreso),
                'orden'      => 890,
                'meta'       => ['regresaron' => $regreso, 'no_regresaron' => $noRegreso],
            ]);
        }

        return $eventos;
    }

    /**
     * Actividades ejecutadas del grupo del estudiante. La actividad es
     * grupal (tareas_x_sprints) pero la observacion es individual y vive en
     * tareas_x_sprints_x_estudiante.
     */
    private static function fuenteActividades($db, $id_estudiante, $fecha, $contexto)
    {
        if (empty($contexto['id_grupo'])) {
            return [];
        }

        $sentence = $db->prepare("
            SELECT ts.id,
                   ts.fecha_ejecucion,
                   ts.orden_ejecucion,
                   ts.observaciones AS observacion_grupo,
                   aa.titulo,
                   aa.descripcion,
                   aa.minutos_duracion,
                   ta.nombre AS nombre_tipo_actividad,
                   ta.icono AS icono_tipo_actividad,
                   ar.nombre AS nombre_area_academica,
                   ar.color AS color_area_academica,
                   txe.observacion AS observacion_estudiante,
                   TRIM(CONCAT_WS(' ', pd.primer_nombre, pd.primer_apellido)) AS nombre_docente
            FROM tareas_x_sprints ts
            INNER JOIN actividades_academicas aa ON aa.id = ts.id_actividad_academica
            LEFT JOIN tipos_actividades_academicas ta ON ta.id = aa.id_tipo_actividad_academica
            LEFT JOIN areas_academicas ar ON ar.id = ts.id_area_academica
            LEFT JOIN tareas_x_sprints_x_estudiante txe
                   ON txe.id_tarea_x_sprint = ts.id
                  AND txe.id_estudiante = :id_estudiante
            LEFT JOIN docentes d ON d.id = ts.id_docente
            LEFT JOIN personas pd ON pd.id = d.id_persona
            WHERE ts.id_tenant = :id_tenant
              AND ts.id_grupo = :id_grupo
              AND DATE(ts.fecha_ejecucion) = :fecha
              AND ts.id_estado_tarea = :estado
            ORDER BY ts.fecha_ejecucion, ts.orden_ejecucion
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_grupo', $contexto['id_grupo']);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindValue(':estado', self::ESTADO_TAREA_EJECUTADA, PDO::PARAM_INT);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $eventos = [];

        foreach ($filas as $fila) {
            // La observacion individual pesa mas que la descripcion generica
            // de la actividad: es lo que el papa quiere leer de su hijo.
            $detalle = !empty($fila['observacion_estudiante'])
                ? $fila['observacion_estudiante']
                : $fila['descripcion'];

            $eventos[] = self::evento('actividades', 'actividad', $fila['id'], [
                'fecha_hora' => $fila['fecha_ejecucion'],
                'titulo'     => $fila['titulo'],
                'detalle'    => $detalle,
                'pie'        => $fila['nombre_docente'] ? 'Con ' . $fila['nombre_docente'] : null,
                'etiqueta'   => $fila['nombre_area_academica'],
                'color'      => $fila['color_area_academica'],
                'icono'      => $fila['icono_tipo_actividad'],
                'orden'      => 100 + (int) $fila['orden_ejecucion'],
                'meta'       => [
                    'tipo_actividad'         => $fila['nombre_tipo_actividad'],
                    'minutos_duracion'       => $fila['minutos_duracion'],
                    'descripcion_actividad'  => $fila['descripcion'],
                    'observacion_estudiante' => $fila['observacion_estudiante'],
                    'observacion_grupo'      => $fila['observacion_grupo'],
                ],
            ]);
        }

        return $eventos;
    }

    /**
     * Observaciones del dia. Las marcadas para informe NO salen aqui: esas
     * se entregan formalmente en el informe firmado, no en la agenda diaria.
     */
    private static function fuenteObservaciones($db, $id_estudiante, $fecha, $contexto)
    {
        $sentence = $db->prepare("
            SELECT oe.id,
                   oe.descripcion,
                   oe.fecha,
                   oe.fecha_registro,
                   toe.nombre AS nombre_tipo_observacion,
                   toe.color,
                   toe.icono,
                   TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS nombre_usuario
            FROM observaciones_estudiantes oe
            LEFT JOIN tipos_observaciones_estudiantes toe ON toe.id = oe.id_tipo_observacion_estudiante
            LEFT JOIN usuarios u ON u.id = oe.id_usuario
            LEFT JOIN personas p ON p.id = u.id_persona
            WHERE oe.id_tenant = :id_tenant
              AND oe.id_estudiante = :id_estudiante
              AND DATE(oe.fecha) = :fecha
              AND COALESCE(oe.para_informe, 0) = 0
            ORDER BY oe.fecha, oe.fecha_registro
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $eventos = [];

        foreach ($filas as $fila) {
            $eventos[] = self::evento('observaciones', 'observacion', $fila['id'], [
                // oe.fecha guarda el dia, no la hora real (llega siempre a la
                // misma hora). La hora util es la del registro, y solo si se
                // hizo ese mismo dia; si la digitaron despues, el evento va
                // sin hora y se ordena por su posicion.
                'fecha_hora' => self::horaDelDia($fila['fecha_registro'], $fecha),
                'titulo'     => $fila['nombre_tipo_observacion'] ?: 'Observación',
                'detalle'    => $fila['descripcion'],
                'pie'        => $fila['nombre_usuario'] ? 'Registrada por ' . $fila['nombre_usuario'] : null,
                'color'      => $fila['color'],
                'icono'      => $fila['icono'],
                'orden'      => 300,
                'meta'       => ['tipo' => $fila['nombre_tipo_observacion']],
            ]);
        }

        return $eventos;
    }

    /**
     * Alimentacion entregada. Se llega por la cuenta por cobrar, que es la
     * que amarra el servicio con la persona del estudiante.
     */
    private static function fuenteAlimentacion($db, $id_estudiante, $fecha, $contexto)
    {
        if (empty($contexto['id_persona'])) {
            return [];
        }

        $sentence = $db->prepare("
            SELECT ea.id,
                   ea.fecha_hora_entrega,
                   ha.nombre AS nombre_horario,
                   ha.orden AS orden_horario,
                   ps.nombre AS nombre_producto,
                   mp.nombre AS menu_programado,
                   ms.nombre AS menu_servido
            FROM entregas_alimentacion ea
            INNER JOIN cuentas_por_cobrar cpc ON cpc.id = ea.id_cuenta_por_cobrar
            INNER JOIN productos_servicios ps ON ps.id = cpc.id_producto_servicio
            LEFT JOIN horarios_alimentacion ha ON ha.id = ea.id_horario_alimentacion
            LEFT JOIN menus mp ON mp.id = ea.id_menu_programado
            LEFT JOIN menus ms ON ms.id = ea.id_menu_servido
            WHERE ea.id_tenant = :id_tenant
              AND cpc.id_persona = :id_persona
              AND ea.estado = :estado
              AND DATE(ea.fecha_hora_entrega) = :fecha
            ORDER BY ea.fecha_hora_entrega, ha.orden
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_persona', $contexto['id_persona']);
        $sentence->bindValue(':estado', self::ESTADO_ALIMENTACION_ENTREGADA, PDO::PARAM_INT);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $eventos = [];

        foreach ($filas as $fila) {
            // El menu servido manda sobre el programado: puede que ese dia
            // hayan cambiado la minuta.
            $menu = $fila['menu_servido'] ?: $fila['menu_programado'];

            $eventos[] = self::evento('alimentacion', 'entrega', $fila['id'], [
                'fecha_hora' => $fila['fecha_hora_entrega'],
                'titulo'     => $fila['nombre_horario'] ?: $fila['nombre_producto'],
                'detalle'    => $menu,
                'etiqueta'   => $fila['nombre_producto'],
                'orden'      => 200 + (int) $fila['orden_horario'],
                'meta'       => [
                    'menu_programado' => $fila['menu_programado'],
                    'menu_servido'    => $fila['menu_servido'],
                ],
            ]);
        }

        return $eventos;
    }

    /**
     * Solicitudes vigentes en la fecha. Una solicitud puede cubrir varios
     * dias; aqui interesa la ocurrencia de ESTE dia si la tiene.
     */
    private static function fuenteSolicitudes($db, $id_estudiante, $fecha, $contexto)
    {
        $sentence = $db->prepare("
            SELECT s.id,
                   s.descripcion,
                   s.fecha_inicio,
                   s.fecha_fin,
                   s.id_estado,
                   es.nombre AS nombre_estado,
                   es.color AS color_estado,
                   ts.nombre AS nombre_tipo,
                   ts.icono AS icono_tipo,
                   o.hora_programada,
                   o.hora_real,
                   o.observacion AS observacion_ocurrencia,
                   eo.nombre AS nombre_estado_ocurrencia,
                   eo.color AS color_estado_ocurrencia
            FROM solicitudes s
            INNER JOIN tipos_solicitud ts ON ts.id = s.id_tipo_solicitud
            LEFT JOIN estados_solicitud es ON es.id = s.id_estado
            LEFT JOIN solicitudes_ocurrencias o ON o.id_solicitud = s.id AND o.fecha = :fecha_ocurrencia
            LEFT JOIN estados_ocurrencia eo ON eo.id = o.id_estado
            WHERE s.id_tenant = :id_tenant
              AND s.id_estudiante = :id_estudiante
              AND :fecha BETWEEN s.fecha_inicio AND s.fecha_fin
              AND s.id_estado IN (:pendiente, :autorizado)
            ORDER BY o.hora_programada, s.fecha_registro
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':fecha_ocurrencia', $fecha);
        $sentence->bindValue(':pendiente', self::ESTADO_SOLICITUD_PENDIENTE, PDO::PARAM_INT);
        $sentence->bindValue(':autorizado', self::ESTADO_SOLICITUD_AUTORIZADO, PDO::PARAM_INT);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $eventos = [];

        foreach ($filas as $fila) {
            // La hora real (si ya se cumplio) manda sobre la programada.
            $hora = $fila['hora_real'] ?: $fila['hora_programada'];
            $fechaHora = $hora ? $fecha . ' ' . $hora : null;

            $eventos[] = self::evento('solicitudes', 'solicitud', $fila['id'], [
                'fecha_hora' => $fechaHora,
                'titulo'     => $fila['nombre_tipo'],
                'detalle'    => $fila['descripcion'],
                'pie'        => $fila['observacion_ocurrencia'],
                'etiqueta'   => $fila['nombre_estado_ocurrencia'] ?: $fila['nombre_estado'],
                'color'      => $fila['color_estado_ocurrencia'] ?: $fila['color_estado'],
                'icono'      => $fila['icono_tipo'],
                'orden'      => 400,
                'meta'       => [
                    'estado'          => $fila['nombre_estado'],
                    'hora_programada' => $fila['hora_programada'],
                    'hora_real'       => $fila['hora_real'],
                    'fecha_inicio'    => $fila['fecha_inicio'],
                    'fecha_fin'       => $fila['fecha_fin'],
                ],
            ]);
        }

        return $eventos;
    }

    /**
     * Galerias fechadas ese dia y visibles para el estudiante: publicas o
     * asignadas a su grupo. Se devuelve el conteo, no las imagenes.
     */
    private static function fuenteGalerias($db, $id_estudiante, $fecha, $contexto)
    {
        $sentence = $db->prepare("
            SELECT g.id,
                   g.nombre,
                   g.descripcion,
                   g.es_publica,
                   COUNT(gi.id) AS total_imagenes
            FROM galerias g
            INNER JOIN galeria_imagenes gi ON gi.id_galeria = g.id
            WHERE g.id_tenant = :id_tenant
              AND g.activo = 1
              AND g.fecha = :fecha
              AND (
                    g.es_publica = 1
                    OR EXISTS (
                        SELECT 1
                        FROM galerias_x_grupos gxg
                        WHERE gxg.id_galeria = g.id
                          AND gxg.id_grupo = :id_grupo
                    )
              )
            GROUP BY g.id, g.nombre, g.descripcion, g.es_publica, g.orden
            ORDER BY g.orden, g.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindValue(':id_grupo', $contexto['id_grupo']);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $eventos = [];

        foreach ($filas as $fila) {
            $total = (int) $fila['total_imagenes'];

            $eventos[] = self::evento('galerias', 'galeria', $fila['id'], [
                'fecha_hora' => null,
                'titulo'     => $fila['nombre'],
                'detalle'    => $fila['descripcion'],
                'pie'        => $total . ' ' . ($total === 1 ? 'foto' : 'fotos'),
                'orden'      => 500,
                'meta'       => [
                    'total_imagenes' => $total,
                    'es_publica'     => (int) $fila['es_publica'],
                    'ruta'           => '/galeria',
                ],
            ]);
        }

        return $eventos;
    }

    /** Notificaciones que le llegaron al estudiante ese dia. */
    private static function fuenteNotificaciones($db, $id_estudiante, $fecha, $contexto)
    {
        $sentence = $db->prepare("
            SELECT n.id,
                   n.titulo,
                   n.cuerpo,
                   n.fecha_envio,
                   nc.nombre AS nombre_categoria,
                   nc.icono,
                   nc.color,
                   nd.fecha_lectura
            FROM notificaciones_destinatarios nd
            INNER JOIN notificaciones n ON n.id = nd.id_notificacion
            LEFT JOIN notificaciones_categorias nc ON nc.id = n.id_categoria
            WHERE nd.id_tenant = :id_tenant
              AND nd.id_estudiante = :id_estudiante
              AND n.activo = 1
              AND DATE(n.fecha_envio) = :fecha
            GROUP BY n.id, n.titulo, n.cuerpo, n.fecha_envio, nc.nombre, nc.icono, nc.color, nd.fecha_lectura
            ORDER BY n.fecha_envio
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $eventos = [];

        foreach ($filas as $fila) {
            $eventos[] = self::evento('notificaciones', 'notificacion', $fila['id'], [
                'fecha_hora' => $fila['fecha_envio'],
                'titulo'     => $fila['titulo'],
                // El cuerpo puede traer HTML del editor: se limpia para la
                // tarjeta y el detalle completo se ve en Notificaciones.
                'detalle'    => self::resumirTexto($fila['cuerpo'], 220),
                'etiqueta'   => $fila['nombre_categoria'],
                'color'      => $fila['color'],
                'icono'      => $fila['icono'],
                'orden'      => 600,
                'meta'       => [
                    'leida' => !empty($fila['fecha_lectura']),
                    'ruta'  => '/notificaciones',
                ],
            ]);
        }

        return $eventos;
    }

    /** Medidas tomadas ese dia (talla, peso, lo que tenga parametrizado). */
    private static function fuenteMedidas($db, $id_estudiante, $fecha, $contexto)
    {
        $sentence = $db->prepare("
            SELECT mxe.id,
                   mxe.valor,
                   mxe.fecha_registro,
                   m.nombre AS nombre_medida,
                   m.unidad,
                   vm.etiqueta
            FROM medidas_x_estudiantes mxe
            INNER JOIN medidas m ON m.id = mxe.id_medida
            LEFT JOIN valores_medidas vm ON vm.id_medida = m.id AND vm.valor_numerico = mxe.valor
            WHERE mxe.id_tenant = :id_tenant
              AND mxe.id_estudiante = :id_estudiante
              AND mxe.fecha = :fecha
            ORDER BY m.orden, m.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $eventos = [];

        foreach ($filas as $fila) {
            // Si la medida es de lista (valores_medidas) se muestra la
            // etiqueta; si es numerica, el valor con su unidad.
            $detalle = !empty($fila['etiqueta'])
                ? $fila['etiqueta']
                : trim($fila['valor'] . ' ' . ($fila['unidad'] ?? ''));

            $eventos[] = self::evento('medidas', 'medida', $fila['id'], [
                // La medida se toma un dia y se puede digitar otro: solo se
                // muestra hora cuando ambas cosas pasaron el mismo dia.
                'fecha_hora' => self::horaDelDia($fila['fecha_registro'], $fecha),
                'titulo'     => $fila['nombre_medida'],
                'detalle'    => $detalle,
                'orden'      => 700,
                'meta'       => [
                    'valor'  => $fila['valor'],
                    'unidad' => $fila['unidad'],
                ],
            ]);
        }

        return $eventos;
    }

    /** Pagos recibidos ese dia, sin contar los anulados. */
    private static function fuentePagos($db, $id_estudiante, $fecha, $contexto)
    {
        $sentence = $db->prepare("
            SELECT pr.id,
                   pr.fecha,
                   pr.fecha_registro,
                   pr.anio,
                   pr.numero,
                   pr.valor_recibido,
                   pr.observaciones,
                   pr.referencia_bancaria,
                   tp.nombre AS nombre_tipo_pago
            FROM pagos_recibidos pr
            LEFT JOIN tipos_pagos tp ON tp.id = pr.id_tipo_pago
            WHERE pr.id_tenant = :id_tenant
              AND pr.id_estudiante = :id_estudiante
              AND COALESCE(pr.anulado, 0) = 0
              AND DATE(pr.fecha) = :fecha
            ORDER BY pr.fecha
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $eventos = [];

        foreach ($filas as $fila) {
            $eventos[] = self::evento('pagos', 'pago', $fila['id'], [
                // pr.fecha es la fecha contable del pago y no trae hora real.
                'fecha_hora' => self::horaDelDia($fila['fecha_registro'], $fecha),
                'titulo'     => 'Pago registrado',
                'detalle'    => $fila['observaciones'],
                'etiqueta'   => $fila['nombre_tipo_pago'],
                'valor'      => (float) $fila['valor_recibido'],
                'orden'      => 800,
                'meta'       => [
                    'anio'                => $fila['anio'],
                    'numero'              => $fila['numero'],
                    'referencia_bancaria' => $fila['referencia_bancaria'],
                    'ruta'                => '/mi-cuenta',
                ],
            ]);
        }

        return $eventos;
    }

    /** Cuentas por cobrar generadas ese dia. */
    private static function fuenteCuentas($db, $id_estudiante, $fecha, $contexto)
    {
        if (empty($contexto['id_persona'])) {
            return [];
        }

        $sentence = $db->prepare("
            SELECT cpc.id,
                   cpc.fecha,
                   cpc.valor,
                   cpc.detalle,
                   cpc.es_mora,
                   ps.nombre AS nombre_producto_servicio
            FROM cuentas_por_cobrar cpc
            INNER JOIN productos_servicios ps ON ps.id = cpc.id_producto_servicio
            WHERE cpc.id_tenant = :id_tenant
              AND cpc.id_persona = :id_persona
              AND COALESCE(cpc.anulado, 0) = 0
              AND cpc.fecha = :fecha
            ORDER BY ps.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_persona', $contexto['id_persona']);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $eventos = [];

        foreach ($filas as $fila) {
            $eventos[] = self::evento('cuentas', (int) $fila['es_mora'] === 1 ? 'mora' : 'cuenta', $fila['id'], [
                'fecha_hora' => null,
                'titulo'     => $fila['nombre_producto_servicio'],
                'detalle'    => $fila['detalle'],
                'etiqueta'   => (int) $fila['es_mora'] === 1 ? 'Intereses de mora' : null,
                'valor'      => (float) $fila['valor'],
                'orden'      => 810,
                'meta'       => [
                    'es_mora' => (int) $fila['es_mora'],
                    'ruta'    => '/mi-cuenta',
                ],
            ]);
        }

        return $eventos;
    }

    // =====================================================================
    // APOYO
    // =====================================================================

    /**
     * Construye un evento con la forma unica que consume el front.
     *
     * @param string $clave Clave de la fuente
     * @param string $tipo Subtipo dentro de la fuente
     * @param string $id Id del registro origen
     * @param array $datos Campos propios del evento
     * @return array
     */
    private static function evento($clave, $tipo, $id, $datos)
    {
        $config = self::FUENTES[$clave];

        return [
            'clave'         => $clave,
            'tipo'          => $tipo,
            'id'            => $id,
            'fecha_hora'    => $datos['fecha_hora'] ?? null,
            'hora'          => !empty($datos['fecha_hora']) ? substr($datos['fecha_hora'], 11, 5) : null,
            'titulo'        => $datos['titulo'] ?? '',
            'detalle'       => $datos['detalle'] ?? null,
            'pie'           => $datos['pie'] ?? null,
            'etiqueta'      => $datos['etiqueta'] ?? null,
            'valor'         => $datos['valor'] ?? null,
            // El color y el icono propios del registro mandan sobre los de la
            // fuente: asi una observacion sale con el color de su tipo.
            'color'         => !empty($datos['color']) ? $datos['color'] : $config['color'],
            'icono'         => !empty($datos['icono']) ? $datos['icono'] : $config['icono'],
            'nombre_fuente' => $config['nombre'],
            'orden'         => $datos['orden'] ?? $config['orden'] * 100,
            'meta'          => $datos['meta'] ?? new stdClass(),
        ];
    }

    /**
     * Orden de la linea de tiempo: primero por hora, y los eventos sin hora
     * caen en la posicion que les da su `orden`. Asi "llegó al jardín" queda
     * antes que "llegó con la lonchera" aunque el segundo no tenga hora.
     */
    private static function compararEventos($a, $b)
    {
        $horaA = $a['fecha_hora'] ? strtotime($a['fecha_hora']) : null;
        $horaB = $b['fecha_hora'] ? strtotime($b['fecha_hora']) : null;

        if ($horaA !== null && $horaB !== null && $horaA !== $horaB) {
            return $horaA < $horaB ? -1 : 1;
        }

        if ($a['orden'] !== $b['orden']) {
            return $a['orden'] < $b['orden'] ? -1 : 1;
        }

        return strcmp((string) $a['titulo'], (string) $b['titulo']);
    }

    /**
     * Claves de fuente pedidas en el query string. Sin el parametro se
     * devuelven todas.
     */
    private static function clavesPedidas()
    {
        $query = Flight::request()->query;
        $pedidas = isset($query['fuentes']) ? trim($query['fuentes']) : '';

        if ($pedidas === '') {
            return array_keys(self::FUENTES);
        }

        $claves = array_map('trim', explode(',', $pedidas));

        // Se ignoran las claves desconocidas en lugar de reventar: el front
        // puede quedar con una version anterior del catalogo.
        $validas = array_values(array_intersect($claves, array_keys(self::FUENTES)));

        return empty($validas) ? array_keys(self::FUENTES) : $validas;
    }

    /** Resumen por fuente para pintar los tabs con su contador. */
    private static function resumenFuentes($claves, $totales)
    {
        $salida = [];

        foreach ($claves as $clave) {
            $config = self::FUENTES[$clave];
            $salida[] = [
                'clave'  => $clave,
                'nombre' => $config['nombre'],
                'icono'  => $config['icono'],
                'color'  => $config['color'],
                'orden'  => $config['orden'],
                'total'  => isset($totales[$clave]) ? $totales[$clave] : 0,
            ];
        }

        return $salida;
    }

    /**
     * Datos basicos del estudiante mas su grupo activo del anio.
     * fecha_ingreso es el tope inferior de la navegacion por fecha.
     */
    private static function obtenerEstudiante($db, $id_estudiante)
    {
        $sentence = $db->prepare("
            SELECT e.id,
                   e.id_persona,
                   e.fecha_ingreso,
                   TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                   p.foto,
                   exg.id_grupo,
                   g.nombre AS nombre_grupo
            FROM estudiantes e
            INNER JOIN personas p ON p.id = e.id_persona
            LEFT JOIN estudiantes_x_grupos exg ON exg.id_estudiante = e.id AND exg.activo = 1
            LEFT JOIN grupos g ON g.id = exg.id_grupo
            WHERE e.id = :id_estudiante
              AND e.id_tenant = :id_tenant
            LIMIT 1
        ");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ?: null;
    }

    /**
     * Barrera de acceso al estudiante.
     *
     * En el portal de padres se exige que el usuario sea acudiente activo
     * del estudiante y con ve_en_portal_padres. En el institucional se
     * mantiene el comportamiento del resto del sistema (el control lo hace
     * el front por permisos de menu).
     */
    private static function puedeVerEstudiante($db, $userData, $id_estudiante)
    {
        $portal = isset($userData->portal)
            ? $userData->portal
            : JWTService::PORTAL_INSTITUCIONAL;

        if ($portal !== JWTService::PORTAL_PADRES) {
            return true;
        }

        if (empty($userData->id_persona)) {
            return false;
        }

        return Acudientes::esEstudianteDelAcudiente($db, $userData->id_persona, $id_estudiante);
    }

    /**
     * Devuelve el timestamp solo si cae dentro del dia consultado.
     *
     * Varias tablas guardan la fecha del hecho sin hora util y la hora real
     * en el campo de registro. Pintar esa hora sin validar el dia mete
     * eventos a horas que no corresponden, asi que cuando no coinciden el
     * evento se queda sin hora y se ordena por su posicion en el dia.
     *
     * @param string|null $fechaHora
     * @param string $fecha Dia consultado en Y-m-d
     * @return string|null
     */
    private static function horaDelDia($fechaHora, $fecha)
    {
        if (empty($fechaHora)) {
            return null;
        }

        return substr($fechaHora, 0, 10) === $fecha ? $fechaHora : null;
    }

    /** Valida que la fecha venga en Y-m-d y sea una fecha real. */
    private static function fechaValida($fecha)
    {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);

        return $d && $d->format('Y-m-d') === $fecha;
    }

    /** Une los nombres de una lista de items en un texto legible. */
    private static function listaDeNombres($items)
    {
        $nombres = array_map(function ($item) {
            return $item['nombre'];
        }, $items);

        return implode(', ', array_filter($nombres));
    }

    /**
     * Quita el HTML del editor y recorta a un largo de tarjeta.
     *
     * Se corta con preg y el modificador /u en vez de mb_substr para no
     * depender de la extension mbstring, que no esta garantizada en todos
     * los servidores. El /u respeta los caracteres multibyte, asi que no
     * parte una tilde por la mitad.
     */
    private static function resumirTexto($texto, $largo)
    {
        $plano = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $texto)));

        if ($plano === '') {
            return null;
        }

        if (!preg_match('/^.{0,' . (int) $largo . '}/us', $plano, $coincidencias)) {
            return $plano;
        }

        $recortado = $coincidencias[0];

        return $recortado === $plano ? $plano : rtrim($recortado) . '...';
    }
}
