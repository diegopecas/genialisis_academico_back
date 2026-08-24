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
            'color'  => '#00B894',
            'metodo' => 'fuenteAsistencia',
            'orden'  => 1,
        ],
        'utiles' => [
            'nombre' => 'Útiles y accesorios',
            'icono'  => '🎒',
            'color'  => '#0984E3',
            'metodo' => 'fuenteUtiles',
            'orden'  => 2,
        ],
        'actividades' => [
            'nombre' => 'Actividades',
            'icono'  => '🎨',
            'color'  => '#E17055',
            'metodo' => 'fuenteActividades',
            'orden'  => 3,
        ],
        'observaciones' => [
            'nombre' => 'Observaciones',
            'icono'  => '💬',
            'color'  => '#6C5CE7',
            'metodo' => 'fuenteObservaciones',
            'orden'  => 4,
        ],
        'alimentacion' => [
            'nombre' => 'Alimentación',
            'icono'  => '🍽️',
            'color'  => '#00CEC9',
            'metodo' => 'fuenteAlimentacion',
            'orden'  => 5,
        ],
        'solicitudes' => [
            'nombre' => 'Solicitudes',
            'icono'  => '📝',
            'color'  => '#F39C12',
            'metodo' => 'fuenteSolicitudes',
            'orden'  => 6,
        ],
        'galerias' => [
            'nombre' => 'Fotos del día',
            'icono'  => '📷',
            'color'  => '#E84393',
            'metodo' => 'fuenteGalerias',
            'orden'  => 7,
        ],
        'notificaciones' => [
            'nombre' => 'Notificaciones',
            'icono'  => '🔔',
            'color'  => '#D63031',
            'metodo' => 'fuenteNotificaciones',
            'orden'  => 8,
        ],
        'medidas' => [
            'nombre' => 'Medidas',
            'icono'  => '📏',
            'color'  => '#16A085',
            'metodo' => 'fuenteMedidas',
            'orden'  => 9,
        ],
        'pagos' => [
            'nombre' => 'Pagos recibidos',
            'icono'  => '💰',
            'color'  => '#27AE60',
            'metodo' => 'fuentePagos',
            'orden'  => 10,
        ],
        'cuentas' => [
            'nombre' => 'Cuentas generadas',
            'icono'  => '🧾',
            'color'  => '#8E44AD',
            'metodo' => 'fuenteCuentas',
            'orden'  => 11,
        ],
    ];

    // =====================================================================
    // ENDPOINTS
    // =====================================================================

    /**
     * Ruta a una pestana de la ficha del estudiante en el portal de padres.
     *
     * La ficha abre siempre en "Datos" salvo que se le diga a cual pestana
     * ir, asi que la ruta viaja con el query param y con los permisos que
     * hacen falta: el de entrar a la ficha y el de esa pestana en concreto.
     * El front solo ofrece el "Ver mas" cuando el usuario tiene los dos.
     *
     * @param string $id_estudiante
     * @param string $tab      id de la pestana en vista-estudiante
     * @param string $permiso  permiso de esa pestana
     */
    private static function rutaFichaEstudiante($id_estudiante, $tab, $permiso)
    {
        return [
            'ruta'          => '/estudiantes-vista/' . $id_estudiante,
            'ruta_query'    => ['tab' => $tab],
            'ruta_permisos' => ['padres.estudiantes.ver', $permiso],
        ];
    }

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

        $eventos = self::ordenarPorHora($eventos);

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
                    'meta'       => array_merge(
                        ['id_asistencia' => $fila['id']],
                        self::rutaFichaEstudiante($id_estudiante, 'asistencia', 'padres.estudiante.asistencia')
                    ),
                ]);
            }

            if (!empty($fila['fecha_salida']) && substr($fila['fecha_salida'], 0, 10) === $fecha) {
                $eventos[] = self::evento('asistencia', 'salida', $fila['id'] . '-out', [
                    'fecha_hora' => $fila['fecha_salida'],
                    'titulo'     => 'Salió del jardín',
                    'detalle'    => $fila['observacion_salida'],
                    'pie'        => $fila['nombre_usuario_salida'] ? 'Entregado por ' . $fila['nombre_usuario_salida'] : null,
                    'orden'      => 900,
                    'meta'       => array_merge(
                        ['id_asistencia' => $fila['id']],
                        self::rutaFichaEstudiante($id_estudiante, 'asistencia', 'padres.estudiante.asistencia')
                    ),
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
                'orden'      => 910,
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

        // Igual que en galerias: sin esto GROUP_CONCAT corta en 1024 bytes y
        // una actividad con muchos parametros perderia los ultimos.
        $db->exec('SET SESSION group_concat_max_len = 100000');

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
                   TRIM(CONCAT_WS(' ', pd.primer_nombre, pd.primer_apellido)) AS nombre_docente,
                   -- Calificaciones del estudiante en esa actividad. Van en
                   -- subconsulta y no en JOIN para no multiplicar la fila de
                   -- la actividad por cada parametro calificado.
                   (SELECT GROUP_CONCAT(
                               CONCAT_WS('|@|', pc.nombre, vpc.valor_cualitativo,
                                         vpc.valor_cuantitativo, COALESCE(vpc.icono, ''))
                               ORDER BY pc.nombre
                               SEPARATOR '|#|')
                    FROM calificaciones c
                    INNER JOIN parametros_calificaciones pc ON pc.id = c.id_parametro_calificacion
                    INNER JOIN valores_parametros_calificaciones vpc ON vpc.id = c.id_valor_parametro_calificacion
                    WHERE c.id_tarea_x_sprint = ts.id
                      AND c.id_estudiante = :id_estudiante_calif
                   ) AS calificaciones_crudas
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
        $sentence->bindParam(':id_estudiante_calif', $id_estudiante);
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
                    'calificaciones'         => self::desarmarCalificaciones($fila['calificaciones_crudas']),
                ] + self::rutaFichaEstudiante($id_estudiante, 'evaluaciones', 'padres.estudiante.evaluaciones'),
            ]);
        }

        return $eventos;
    }

    /**
     * Observaciones del dia. Las marcadas para informe NO salen aqui: esas
     * se entregan formalmente en el informe firmado, no en la agenda diaria.
     *
     * Salen las dos caras de la observacion: aquellas donde el estudiante es
     * el protagonista (id_estudiante) y aquellas donde es el afectado
     * (id_estudiante_afectado). Al papa del afectado tambien le interesa
     * saber que paso, aunque el hecho lo haya registrado el otro lado.
     *
     * De la observacion donde es afectado NO se manda el nombre del otro
     * estudiante: es un menor ajeno y su nombre no tiene por que viajar al
     * portal de otra familia. Solo va la marca de que hubo alguien mas.
     */
    private static function fuenteObservaciones($db, $id_estudiante, $fecha, $contexto)
    {
        $sentence = $db->prepare("
            SELECT oe.id,
                   oe.descripcion,
                   oe.fecha,
                   oe.fecha_registro,
                   -- Marca de cual de los dos lados es el estudiante que se
                   -- esta consultando. Se resuelve en SQL para no traer los
                   -- ids de los estudiantes hasta el front.
                   CASE WHEN oe.id_estudiante_afectado = :id_estudiante_marca
                        THEN 1 ELSE 0 END AS es_afectado,
                   toe.nombre AS nombre_tipo_observacion,
                   toe.color,
                   toe.icono,
                   TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido)) AS nombre_usuario
            FROM observaciones_estudiantes oe
            LEFT JOIN tipos_observaciones_estudiantes toe ON toe.id = oe.id_tipo_observacion_estudiante
            LEFT JOIN usuarios u ON u.id = oe.id_usuario
            LEFT JOIN personas p ON p.id = u.id_persona
            WHERE oe.id_tenant = :id_tenant
              AND (
                    oe.id_estudiante = :id_estudiante
                    OR oe.id_estudiante_afectado = :id_estudiante_afectado
              )
              AND DATE(oe.fecha) = :fecha
              AND COALESCE(oe.para_informe, 0) = 0
            ORDER BY oe.fecha, oe.fecha_registro
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_estudiante_afectado', $id_estudiante);
        $sentence->bindParam(':id_estudiante_marca', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $eventos = [];

        foreach ($filas as $fila) {
            $esAfectado = (int) $fila['es_afectado'] === 1;

            $eventos[] = self::evento('observaciones', 'observacion', $fila['id'], [
                // oe.fecha guarda el dia, no la hora real (llega siempre a la
                // misma hora). La hora util es la del registro, y solo si se
                // hizo ese mismo dia; si la digitaron despues, el evento va
                // sin hora y se ordena por su posicion.
                'fecha_hora' => self::horaDelDia($fila['fecha_registro'], $fecha),
                'titulo'     => $fila['nombre_tipo_observacion'] ?: 'Observación',
                'detalle'    => $fila['descripcion'],
                'pie'        => $fila['nombre_usuario'] ? 'Registrada por ' . $fila['nombre_usuario'] : null,
                'etiqueta'   => $esAfectado ? 'Con otro estudiante' : null,
                'color'      => $fila['color'],
                'icono'      => $fila['icono'],
                'orden'      => 300,
                'meta'       => [
                    'tipo'        => $fila['nombre_tipo_observacion'],
                    'es_afectado' => $esAfectado,
                ] + self::rutaFichaEstudiante($id_estudiante, 'observaciones', 'padres.estudiante.observaciones'),
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
                    'ruta'            => '/solicitudes',
                    'ruta_permisos'   => ['padres.solicitudes.ver'],
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
        // Por defecto GROUP_CONCAT corta en 1024 bytes y una galeria con
        // muchas fotos perderia las ultimas. Solo afecta a esta sesion.
        $db->exec('SET SESSION group_concat_max_len = 100000');

        $sentence = $db->prepare("
            SELECT g.id,
                   g.nombre,
                   g.descripcion,
                   g.es_publica,
                   COUNT(gi.id) AS total_imagenes,
                   -- Las imagenes viajan en el mismo query para no disparar
                   -- una consulta por galeria. Se manda el guid y no la url:
                   -- gi.url es relativa y las imagenes se sirven con un token
                   -- efimero, asi que el front arma la url con el guid.
                   -- El separador raro evita chocar con el texto del alt.
                   GROUP_CONCAT(
                       CONCAT_WS('|@|', gi.guid, COALESCE(gi.alt, ''), gi.tipo_media)
                       ORDER BY gi.orden, gi.created_at
                       SEPARATOR '|#|'
                   ) AS imagenes_crudas
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
                    'imagenes'       => self::desarmarImagenes($fila['imagenes_crudas']),
                    // Las fotos de este dia ya se ven en la tarjeta; el
                    // enlace lleva a la galeria completa del jardin, que es
                    // lo que si agrega algo.
                    'ruta'           => '/galeria',
                    'ruta_permisos'  => ['padres.galeria.ver'],
                ],
            ]);
        }

        return $eventos;
    }

    /**
     * Notificaciones que le llegaron al estudiante ese dia.
     *
     * Se dejan por fuera las de llegada y salida del jardin: el sistema las
     * dispara solo al registrar la asistencia, asi que en la agenda salian
     * duplicando exactamente lo que ya cuenta la fuente de asistencia, con
     * la misma hora y el mismo texto. Se filtran por el codigo de la
     * categoria y no por el titulo, que cada jardin redacta a su manera.
     */
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
              AND COALESCE(nc.codigo, '') NOT IN ('ASISTENCIA_INGRESO', 'ASISTENCIA_SALIDA')
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
                    'ruta_permisos' => ['padres.notificaciones.ver'],
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

        if (empty($filas)) {
            return [];
        }

        // Las medidas se toman todas de una sentada (talla, peso, perimetro)
        // y antes salian como una tarjeta por cada una, llenando el dia de
        // fichas casi vacias. Van juntas en una sola, ubicada a la hora de
        // la primera que se registro.
        $items = [];
        $resumen = [];
        $primeraHora = null;

        foreach ($filas as $fila) {
            // Si la medida es de lista (valores_medidas) se muestra la
            // etiqueta; si es numerica, el valor con su unidad.
            $valorTexto = !empty($fila['etiqueta'])
                ? $fila['etiqueta']
                : trim($fila['valor'] . ' ' . ($fila['unidad'] ?? ''));

            $items[] = [
                'nombre'      => $fila['nombre_medida'],
                'observacion' => $valorTexto,
                'valor'       => $fila['valor'],
                'unidad'      => $fila['unidad'],
            ];

            $resumen[] = $fila['nombre_medida'] . ': ' . $valorTexto;

            // La medida se toma un dia y se puede digitar otro: solo cuenta
            // como hora la del registro hecho el mismo dia.
            $hora = self::horaDelDia($fila['fecha_registro'], $fecha);
            if ($hora !== null && ($primeraHora === null || $hora < $primeraHora)) {
                $primeraHora = $hora;
            }
        }

        $total = count($items);

        // El id del evento es el de la primera medida: la agenda necesita un
        // id estable por tarjeta y no existe una fila que represente al grupo.
        return [
            self::evento('medidas', 'medidas_dia', $filas[0]['id'], [
                'fecha_hora' => $primeraHora,
                'titulo'     => $total === 1 ? 'Se tomó una medida' : 'Se tomaron ' . $total . ' medidas',
                'detalle'    => implode(' · ', $resumen),
                'orden'      => 700,
                'meta'       => [
                    'items' => $items,
                    'ruta'  => '/estudiantes-vista/' . $id_estudiante,
                    'ruta_query'    => ['tab' => 'medidas'],
                    'ruta_permisos' => ['padres.estudiantes.ver', 'padres.estudiante.medidas'],
                ],
            ]),
        ];
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
                   -- Soporte que subio el acudiente al registrar el pago.
                   -- El front solo ofrece el enlace de descarga cuando viene
                   -- lleno; si el pago se registro sin adjunto, no hay nada
                   -- que bajar.
                   pr.id_documento_persona,
                   tp.nombre AS nombre_tipo_pago
            FROM pagos_recibidos pr
            LEFT JOIN tipos_pagos tp ON tp.id = pr.id_tipo_pago
            WHERE pr.id_tenant = :id_tenant
              AND pr.id_estudiante = :id_estudiante
              AND COALESCE(pr.anulado, 0) = 0
              AND DATE(pr.fecha_registro) = :fecha
            ORDER BY pr.fecha_registro
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $eventos = [];

        foreach ($filas as $fila) {
            // La agenda cuenta lo que paso HOY, y lo que paso hoy es que se
            // registro el pago. pr.fecha es la fecha contable (puede ser de
            // otro dia) y va en el texto, no en la linea de tiempo.
            $partes = ['Pago del ' . self::fechaEnTexto($fila['fecha'])];

            if (!empty($fila['nombre_tipo_pago'])) {
                $partes[] = $fila['nombre_tipo_pago'];
            }

            if (!empty($fila['referencia_bancaria'])) {
                $partes[] = 'Ref. ' . $fila['referencia_bancaria'];
            }

            if (!empty($fila['observaciones'])) {
                $partes[] = $fila['observaciones'];
            }

            $eventos[] = self::evento('pagos', 'pago', $fila['id'], [
                'fecha_hora' => $fila['fecha_registro'],
                'titulo'     => 'Se registró un pago',
                'detalle'    => implode(' · ', $partes),
                'etiqueta'   => $fila['nombre_tipo_pago'],
                'valor'      => (float) $fila['valor_recibido'],
                'orden'      => 800,
                'meta'       => [
                    'anio'                => $fila['anio'],
                    'numero'              => $fila['numero'],
                    'referencia_bancaria' => $fila['referencia_bancaria'],
                    'fecha_pago'          => $fila['fecha'],
                    'observaciones'       => $fila['observaciones'],
                    'id_documento'        => $fila['id_documento_persona'],
                    'ruta'                => '/mi-cuenta',
                    'ruta_permisos'       => ['padres.mi_cuenta.ver'],
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
                   cpc.fecha_generado,
                   cpc.valor,
                   cpc.detalle,
                   cpc.es_mora,
                   cpc.valor_recargo_mora,
                   ps.nombre AS nombre_producto_servicio
            FROM cuentas_por_cobrar cpc
            INNER JOIN productos_servicios ps ON ps.id = cpc.id_producto_servicio
            WHERE cpc.id_tenant = :id_tenant
              AND cpc.id_persona = :id_persona
              AND COALESCE(cpc.anulado, 0) = 0
              AND DATE(cpc.fecha_generado) = :fecha
            ORDER BY cpc.fecha_generado
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_persona', $contexto['id_persona']);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        $eventos = [];

        foreach ($filas as $fila) {
            $esMora = (int) $fila['es_mora'] === 1;

            // Igual que en pagos: la linea de tiempo va por cuando se genero
            // la cuenta, y la fecha de vencimiento se cuenta en el texto.
            $partes = ['Vence el ' . self::fechaEnTexto($fila['fecha'])];

            if (!empty($fila['detalle'])) {
                $partes[] = $fila['detalle'];
            }

            $eventos[] = self::evento('cuentas', $esMora ? 'mora' : 'cuenta', $fila['id'], [
                'fecha_hora' => $fila['fecha_generado'],
                'titulo'     => 'Se generó ' . ($esMora ? 'un recargo por mora' : 'una cuenta') . ': ' . $fila['nombre_producto_servicio'],
                'detalle'    => implode(' · ', $partes),
                'etiqueta'   => $esMora ? 'Intereses de mora' : null,
                'valor'      => (float) $fila['valor'],
                'orden'      => 810,
                'meta'       => [
                    'es_mora'            => (int) $fila['es_mora'],
                    'producto_servicio'  => $fila['nombre_producto_servicio'],
                    'fecha_vencimiento'  => $fila['fecha'],
                    'detalle_cuenta'     => $fila['detalle'],
                    'ruta'               => '/mi-cuenta',
                    'ruta_permisos'      => ['padres.mi_cuenta.ver'],
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
            // El color y el icono salen SIEMPRE de la fuente, nunca del
            // registro: asi las tres vistas pintan lo mismo para el mismo
            // tipo de evento. Antes el tipo de observacion podia traer su
            // propio color o una clase de Font Awesome, y cada vista lo
            // resolvia distinto. El color del registro sigue disponible en
            // meta por si alguna vista lo necesita.
            'color'         => $config['color'],
            'icono'         => $config['icono'],
            'nombre_fuente' => $config['nombre'],
            'orden'         => $datos['orden'] ?? $config['orden'] * 100,
            'meta'          => $datos['meta'] ?? new stdClass(),
        ];
    }

    /**
     * Ordena todos los eventos del dia en una sola linea de tiempo.
     *
     * El problema: no todos los eventos tienen hora confiable (los utiles y
     * las galerias no la tienen). Comparar "por hora si ambos la tienen, si
     * no por orden" da un comparador no transitivo, y usort con eso devuelve
     * un orden impredecible: por eso los eventos salian revueltos.
     *
     * La solucion es que TODOS tengan un peso comparable antes de ordenar.
     * Al evento sin hora se le presta la del evento con hora que le queda
     * mas cerca por debajo segun su `orden`, que es justo donde deberia caer
     * en el dia: los utiles que trajo se anclan a la entrada (orden 10), los
     * que devolvio a la salida (orden 900). Si no hay ninguno por debajo, se
     * ancla al primero del dia. Y si el dia entero viene sin horas, se cae a
     * `orden`, que ya trae el orden logico de la jornada.
     *
     * @param array $eventos
     * @return array
     */
    private static function ordenarPorHora($eventos)
    {
        if (empty($eventos)) {
            return [];
        }

        // Eventos con hora, ordenados por su `orden`, para poder buscar el
        // ancla de cada evento sin hora.
        $conHora = [];

        foreach ($eventos as $evento) {
            if (!empty($evento['fecha_hora'])) {
                $conHora[] = [
                    'orden' => $evento['orden'],
                    'peso'  => strtotime($evento['fecha_hora']),
                ];
            }
        }

        usort($conHora, function ($a, $b) {
            return $a['orden'] <=> $b['orden'];
        });

        $pesoMinimo = null;
        foreach ($conHora as $referencia) {
            if ($pesoMinimo === null || $referencia['peso'] < $pesoMinimo) {
                $pesoMinimo = $referencia['peso'];
            }
        }

        foreach ($eventos as $i => $evento) {
            if (!empty($evento['fecha_hora'])) {
                $eventos[$i]['peso_orden'] = strtotime($evento['fecha_hora']);
                continue;
            }

            $eventos[$i]['peso_orden'] = self::anclarSinHora(
                $evento['orden'],
                $conHora,
                $pesoMinimo
            );
        }

        usort($eventos, function ($a, $b) {
            if ($a['peso_orden'] !== $b['peso_orden']) {
                return $a['peso_orden'] <=> $b['peso_orden'];
            }

            if ($a['orden'] !== $b['orden']) {
                return $a['orden'] <=> $b['orden'];
            }

            return strcmp((string) $a['titulo'], (string) $b['titulo']);
        });

        // peso_orden es de uso interno: no tiene por que viajar al front.
        foreach ($eventos as $i => $evento) {
            unset($eventos[$i]['peso_orden']);
        }

        return array_values($eventos);
    }

    /**
     * Peso de un evento sin hora: el del ultimo evento con hora que quede
     * por debajo suyo en la jornada.
     *
     * @param int $orden Posicion logica del evento en el dia
     * @param array $conHora Eventos con hora, ya ordenados por `orden`
     * @param int|null $pesoMinimo Hora mas temprana del dia
     * @return int
     */
    private static function anclarSinHora($orden, $conHora, $pesoMinimo)
    {
        if (empty($conHora)) {
            return $orden;
        }

        $ancla = null;

        foreach ($conHora as $referencia) {
            if ($referencia['orden'] <= $orden) {
                $ancla = $referencia['peso'];
            } else {
                break;
            }
        }

        // Nada por debajo: va antes que todo lo del dia.
        return $ancla !== null ? $ancla : $pesoMinimo - 1;
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
            // Las fuentes sin eventos no se devuelven: si ese dia no hubo
            // nada de esa categoria, no tiene por que aparecer en ningun
            // lado de la pantalla.
            if (empty($totales[$clave])) {
                continue;
            }

            $config = self::FUENTES[$clave];
            $salida[] = [
                'clave'  => $clave,
                'nombre' => $config['nombre'],
                'icono'  => $config['icono'],
                'color'  => $config['color'],
                'orden'  => $config['orden'],
                'total'  => $totales[$clave],
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
     * Convierte el GROUP_CONCAT de calificaciones en una lista utilizable.
     *
     * @param string|null $crudas
     * @return array
     */
    private static function desarmarCalificaciones($crudas)
    {
        if (empty($crudas)) {
            return [];
        }

        $salida = [];

        foreach (explode('|#|', $crudas) as $pedazo) {
            $partes = explode('|@|', $pedazo);

            if (empty($partes[0])) {
                continue;
            }

            $salida[] = [
                'parametro'   => $partes[0],
                'cualitativo' => isset($partes[1]) ? $partes[1] : '',
                'cuantitativo' => isset($partes[2]) ? (int) $partes[2] : null,
                'icono'       => isset($partes[3]) ? $partes[3] : '',
            ];
        }

        return $salida;
    }

    /**
     * Convierte el GROUP_CONCAT de imagenes en una lista utilizable.
     *
     * El limite por defecto de group_concat_max_len (1024) corta la cadena
     * en galerias grandes, por eso se sube para esta consulta y aqui se
     * descartan los pedazos que no traigan al menos guid.
     *
     * @param string|null $crudas
     * @return array
     */
    private static function desarmarImagenes($crudas)
    {
        if (empty($crudas)) {
            return [];
        }

        $salida = [];

        foreach (explode('|#|', $crudas) as $pedazo) {
            $partes = explode('|@|', $pedazo);

            if (empty($partes[0])) {
                continue;
            }

            $salida[] = [
                'guid'       => $partes[0],
                'alt'        => isset($partes[1]) ? $partes[1] : '',
                'tipo_media' => isset($partes[2]) ? $partes[2] : 'imagen',
            ];
        }

        return $salida;
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

    /**
     * Fecha en texto corto para meter dentro de una frase: "5 de noviembre".
     *
     * Los meses van en un arreglo propio y no con strftime ni IntlDateFormatter
     * porque dependen del locale instalado en el servidor, que no siempre trae
     * espanol y devolveria los meses en ingles.
     */
    private static function fechaEnTexto($fecha)
    {
        if (empty($fecha)) {
            return '';
        }

        $meses = [
            1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
        ];

        $tiempo = strtotime($fecha);

        if ($tiempo === false) {
            return (string) $fecha;
        }

        $dia = (int) date('j', $tiempo);
        $mes = (int) date('n', $tiempo);

        // El anio solo se escribe cuando no es el actual: en la agenda del
        // dia repetirlo en cada renglon es ruido.
        $anio = date('Y', $tiempo) !== date('Y') ? ' de ' . date('Y', $tiempo) : '';

        return $dia . ' de ' . $meses[$mes] . $anio;
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
