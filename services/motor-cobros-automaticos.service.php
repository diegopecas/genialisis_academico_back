<?php
class MotorCobrosAutomaticos
{
    /**
     * Normaliza una hora a formato HH:MM:SS
     */
    private static function normalizarHora($hora)
    {
        if ($hora === null || $hora === '') return null;
        $hora = trim($hora);
        // Si viene HH:MM, agregar :00
        if (strlen($hora) === 5) {
            $hora .= ':00';
        }
        return $hora;
    }

    /**
     * Convierte una hora HH:MM:SS a segundos desde medianoche
     */
    private static function horaASegundos($hora)
    {
        $hora = self::normalizarHora($hora);
        if ($hora === null) return 0;
        $partes = explode(':', $hora);
        return intval($partes[0]) * 3600 + intval($partes[1]) * 60 + (isset($partes[2]) ? intval($partes[2]) : 0);
    }

    /**
     * Evalúa las reglas de cobro para un estudiante dado un evento de asistencia.
     * POST /motor-cobros/evaluar
     */
    public static function evaluar()
    {
        try {
            date_default_timezone_set('America/Bogota');
            $db = Flight::db();
            $db->exec("SET time_zone = '-05:00'");
            $request = Flight::request();
            $data = $request->data->getData();

            $id_estudiante = $data['id_estudiante'];
            $tipo_evento = $data['tipo_evento'];
            $hora = self::normalizarHora($data['hora']);
            $fecha = isset($data['fecha']) ? $data['fecha'] : date('Y-m-d');

            $dia_semana = date('N', strtotime($fecha));
            $horaSegundos = self::horaASegundos($hora);

            // Obtener horario del estudiante para este día
            $stmtHorario = $db->prepare("
                SELECT hora_entrada, hora_salida 
                FROM horarios_estudiante 
                WHERE id_estudiante = :id_estudiante AND id_dia_semana = :dia_semana
            ");
            $stmtHorario->bindParam(':id_estudiante', $id_estudiante);
            $stmtHorario->bindParam(':dia_semana', $dia_semana);
            $stmtHorario->execute();
            $horario = $stmtHorario->fetch(PDO::FETCH_ASSOC);

            if (!$horario) {
                $stmtDefault = $db->prepare("SELECT hora_entrada, hora_salida FROM dias_semana WHERE id = :dia_semana");
                $stmtDefault->bindParam(':dia_semana', $dia_semana);
                $stmtDefault->execute();
                $horario = $stmtDefault->fetch(PDO::FETCH_ASSOC);
            }

            if (!$horario) {
                Flight::json(['cobros' => [], 'mensaje' => 'No se encontró horario para este día']);
                return;
            }

            $horaSalidaProgramada = self::normalizarHora($horario['hora_salida']);
            $horaEntradaProgramada = self::normalizarHora($horario['hora_entrada']);
            $horaSalidaSegundos = self::horaASegundos($horaSalidaProgramada);
            $horaEntradaSegundos = self::horaASegundos($horaEntradaProgramada);

            // Obtener id_persona del estudiante (necesario para validar cuentas)
            $stmtPersona = $db->prepare("SELECT id_persona FROM estudiantes WHERE id = :id_estudiante");
            $stmtPersona->bindParam(':id_estudiante', $id_estudiante);
            $stmtPersona->execute();
            $personaRow = $stmtPersona->fetch(PDO::FETCH_ASSOC);
            $id_persona = $personaRow ? $personaRow['id_persona'] : null;

            // Obtener convenios activos del estudiante QUE tengan cuenta por cobrar generada para el mes
            $primerDiaMes = date('Y-m-01', strtotime($fecha));
            $ultimoDiaMes = date('Y-m-t', strtotime($fecha));

            $stmtConvenios = $db->prepare("
                SELECT DISTINCT ce.id_convenio
                FROM convenios_estudiante ce
                INNER JOIN convenios c ON c.id = ce.id_convenio AND c.activo = 1
                WHERE ce.id_estudiante = :id_estudiante
                  AND ce.fecha_inicio <= :fecha
                  AND (ce.fecha_fin IS NULL OR ce.fecha_fin >= :fecha2)
                  AND EXISTS (
                      SELECT 1 FROM cuentas_por_cobrar cpc
                      WHERE cpc.id_persona = :id_persona
                        AND cpc.id_producto_servicio = c.id_producto_servicio
                        AND cpc.fecha BETWEEN :primer_dia_mes AND :ultimo_dia_mes
                        AND (cpc.anulado = 0 OR cpc.anulado IS NULL)
                  )
            ");
            $stmtConvenios->bindParam(':id_estudiante', $id_estudiante);
            $stmtConvenios->bindParam(':fecha', $fecha);
            $stmtConvenios->bindParam(':fecha2', $fecha);
            $stmtConvenios->bindParam(':id_persona', $id_persona);
            $stmtConvenios->bindParam(':primer_dia_mes', $primerDiaMes);
            $stmtConvenios->bindParam(':ultimo_dia_mes', $ultimoDiaMes);
            $stmtConvenios->execute();
            $conveniosActivos = $stmtConvenios->fetchAll(PDO::FETCH_COLUMN);

            // Obtener grupo del estudiante
            $stmtGrupo = $db->prepare("
                SELECT id_grupo FROM estudiantes_x_grupos 
                WHERE id_estudiante = :id_estudiante AND activo = 1
            ");
            $stmtGrupo->bindParam(':id_estudiante', $id_estudiante);
            $stmtGrupo->execute();
            $grupoRow = $stmtGrupo->fetch(PDO::FETCH_ASSOC);
            $id_grupo = $grupoRow ? $grupoRow['id_grupo'] : null;

            // Verificar si tiene matrícula activa
            $anio_actual = date('Y');
            $stmtMatricula = $db->prepare("
                SELECT COUNT(*) as tiene_matricula
                FROM contratos_matricula 
                WHERE id_estudiante = :id_estudiante AND anio = :anio AND activo = 1
            ");
            $stmtMatricula->bindParam(':id_estudiante', $id_estudiante);
            $stmtMatricula->bindParam(':anio', $anio_actual);
            $stmtMatricula->execute();
            $matriculaRow = $stmtMatricula->fetch(PDO::FETCH_ASSOC);
            $tiene_matricula = $matriculaRow['tiene_matricula'] > 0;

            // Cargar ids de tipos_evento_cobro por nombre para no depender de ids fijos
            $stmtTipos = $db->prepare("SELECT id, nombre FROM tipos_evento_cobro WHERE activo = 1");
            $stmtTipos->execute();
            $tiposEventoMap = [];
            foreach ($stmtTipos->fetchAll(PDO::FETCH_ASSOC) as $te) {
                $tiposEventoMap[$te['nombre']] = intval($te['id']);
            }

            $id_ingreso_antes = isset($tiposEventoMap['ingreso_antes_hora']) ? $tiposEventoMap['ingreso_antes_hora'] : null;
            $id_salida_despues = isset($tiposEventoMap['salida_despues_hora']) ? $tiposEventoMap['salida_despues_hora'] : null;
            $id_dia_suelto = isset($tiposEventoMap['dia_suelto']) ? $tiposEventoMap['dia_suelto'] : null;
            $id_sabado_sin_conv = isset($tiposEventoMap['sabado_sin_convenio']) ? $tiposEventoMap['sabado_sin_convenio'] : null;
            $id_domingo_sin_conv = isset($tiposEventoMap['domingo_sin_convenio']) ? $tiposEventoMap['domingo_sin_convenio'] : null;

            // Determinar qué tipos de evento evaluar
            $tipos_evento = [];

            if ($tipo_evento === 'ingreso') {
                if ($id_ingreso_antes !== null && $horaSegundos < $horaEntradaSegundos) {
                    $tipos_evento[] = $id_ingreso_antes;
                }
                if ($id_dia_suelto !== null && !$tiene_matricula) {
                    $tipos_evento[] = $id_dia_suelto;
                }
                if ($id_sabado_sin_conv !== null && $dia_semana == 6) {
                    $tipos_evento[] = $id_sabado_sin_conv;
                }
                if ($id_domingo_sin_conv !== null && $dia_semana == 7) {
                    $tipos_evento[] = $id_domingo_sin_conv;
                }
            }

            if ($tipo_evento === 'salida') {
                if ($id_salida_despues !== null && $horaSegundos > $horaSalidaSegundos) {
                    $tipos_evento[] = $id_salida_despues;
                }
            }

            if (empty($tipos_evento)) {
                Flight::json([
                    'cobros' => [],
                    'horario' => $horario,
                    'tiene_matricula' => $tiene_matricula,
                    'convenios_activos' => $conveniosActivos
                ]);
                return;
            }

            // Obtener reglas activas para los tipos de evento
            $placeholders = implode(',', array_fill(0, count($tipos_evento), '?'));
            $sql = "
                SELECT r.*, 
                       tec.nombre AS nombre_tipo_evento,
                       ps.nombre AS nombre_producto_servicio,
                       ps.valor_sugerido
                FROM reglas_cobro_automatico r
                INNER JOIN tipos_evento_cobro tec ON tec.id = r.id_tipo_evento
                INNER JOIN productos_servicios ps ON ps.id = r.id_producto_servicio
                WHERE r.activo = 1
                  AND r.id_tipo_evento IN ($placeholders)
                ORDER BY r.id_tipo_evento, r.prioridad
            ";
            $stmtReglas = $db->prepare($sql);
            $stmtReglas->execute($tipos_evento);
            $reglas = $stmtReglas->fetchAll(PDO::FETCH_ASSOC);

            $cobrosAplicables = [];
            $tiposEventoYaProcesados = [];

            // Tipos que solo aplican la primera regla que coincida
            $tiposUnicaRegla = array_filter([$id_dia_suelto, $id_sabado_sin_conv, $id_domingo_sin_conv]);

            foreach ($reglas as $regla) {
                $tipo = intval($regla['id_tipo_evento']);

                // Para día suelto, sábado y domingo sin convenio, solo la primera regla que coincida
                if (in_array($tipo, $tiposUnicaRegla) && in_array($tipo, $tiposEventoYaProcesados)) {
                    continue;
                }

                // Verificar grupo (NULL = todos)
                if ($regla['id_grupo'] !== null && $regla['id_grupo'] != $id_grupo) {
                    continue;
                }

                // Verificar día de la semana (NULL = cualquier día)
                if ($regla['id_dia_semana'] !== null && intval($regla['id_dia_semana']) != intval($dia_semana)) {
                    continue;
                }

                // Verificar convenio que exime
                if ($regla['id_convenio_exime'] !== null && in_array($regla['id_convenio_exime'], $conveniosActivos)) {
                    continue;
                }

                // === SALIDA DESPUÉS DE HORA ===
                if ($tipo === $id_salida_despues) {
                    $cobro_fraccion = $regla['cobro_fraccion'];

                    // Regla de valor fijo (cobro_fraccion IS NULL)
                    if ($cobro_fraccion === null || $cobro_fraccion === '') {
                        $desdeSegs = $regla['hora_desde'] !== null && $regla['hora_desde'] !== '' 
                            ? self::horaASegundos($regla['hora_desde']) : null;
                        $hastaSegs = $regla['hora_hasta'] !== null && $regla['hora_hasta'] !== '' 
                            ? self::horaASegundos($regla['hora_hasta']) : null;

                        if ($desdeSegs !== null && $horaSegundos < $desdeSegs) continue;
                        if ($hastaSegs !== null && $horaSegundos >= $hastaSegs) continue;

                        $cobrosAplicables[] = self::buildCobro($regla, floatval($regla['valor_sugerido']), null, null);
                        continue;
                    }

                    // Regla por hora (cobro_fraccion = 0 o 1)
                    $inicioTramoHora = ($regla['hora_desde'] !== null && $regla['hora_desde'] !== '') 
                        ? $regla['hora_desde'] : $horaSalidaProgramada;
                    $finTramoHora = ($regla['hora_hasta'] !== null && $regla['hora_hasta'] !== '') 
                        ? $regla['hora_hasta'] : null;

                    $inicioTramoSegs = self::horaASegundos($inicioTramoHora);
                    $finTramoSegs = $finTramoHora !== null ? self::horaASegundos($finTramoHora) : null;

                    // La hora real debe haber SUPERADO el inicio del tramo (no igual)
                    if ($horaSegundos <= $inicioTramoSegs) {
                        continue;
                    }

                    // Calcular minutos en este tramo
                    if ($finTramoSegs !== null) {
                        if ($horaSegundos >= $finTramoSegs) {
                            // Pasó el tramo completo
                            $minutosEnTramo = ($finTramoSegs - $inicioTramoSegs) / 60;
                        } else {
                            // Está dentro del tramo
                            $minutosEnTramo = ($horaSegundos - $inicioTramoSegs) / 60;
                        }
                    } else {
                        // Tramo abierto
                        $minutosEnTramo = ($horaSegundos - $inicioTramoSegs) / 60;
                    }

                    if ($minutosEnTramo <= 0) {
                        continue;
                    }

                    $valorPorHora = floatval($regla['valor_sugerido']);
                    $horas = $minutosEnTramo / 60;
                    $horasACobrar = null;
                    $valorCalculado = 0;

                    $cobro_fraccion = intval($cobro_fraccion);

                    if ($cobro_fraccion === 0) {
                        // Redondear hacia arriba (cualquier fracción = hora completa)
                        $horasACobrar = intval(ceil($horas));
                        $valorCalculado = $horasACobrar * $valorPorHora;
                    } else {
                        // Proporcional al minuto
                        $valorPorMinuto = $valorPorHora / 60;
                        $valorCalculado = round($minutosEnTramo * $valorPorMinuto);
                        $horasACobrar = null;
                    }

                    $cobrosAplicables[] = self::buildCobro($regla, $valorCalculado, $horasACobrar, round($minutosEnTramo, 1));
                    continue;
                }

                // === SÁBADO O DOMINGO SIN CONVENIO ===
                if ($tipo === $id_sabado_sin_conv || $tipo === $id_domingo_sin_conv) {
                    $hastaSegs = ($regla['hora_hasta'] !== null && $regla['hora_hasta'] !== '') 
                        ? self::horaASegundos($regla['hora_hasta']) : null;
                    $desdeSegs = ($regla['hora_desde'] !== null && $regla['hora_desde'] !== '') 
                        ? self::horaASegundos($regla['hora_desde']) : null;

                    if ($hastaSegs !== null && $horaSegundos >= $hastaSegs) continue;
                    if ($desdeSegs !== null && $horaSegundos < $desdeSegs) continue;
                }

                // Reglas de valor fijo (día suelto, sábado, domingo, ingreso antes de hora)
                $cobrosAplicables[] = self::buildCobro($regla, floatval($regla['valor_sugerido']), null, null);

                if (in_array($tipo, $tiposUnicaRegla)) {
                    $tiposEventoYaProcesados[] = $tipo;
                }
            }

            Flight::json([
                'cobros' => $cobrosAplicables,
                'horario' => $horario,
                'tiene_matricula' => $tiene_matricula,
                'convenios_activos' => $conveniosActivos,
                'dia_semana' => $dia_semana
            ]);
        } catch (Exception $e) {
            error_log('Error en MotorCobrosAutomaticos::evaluar: ' . $e->getMessage());
            Flight::json(['error' => 'Error al evaluar reglas de cobro: ' . $e->getMessage()], 500);
        }
    }

    private static function buildCobro($regla, $valor, $horas, $minutos)
    {
        $detalle = $regla['nombre'];
        if ($horas !== null) {
            $detalle .= " ({$horas}h)";
        }

        return [
            'id_regla' => $regla['id'],
            'nombre_regla' => $regla['nombre'],
            'id_tipo_evento' => $regla['id_tipo_evento'],
            'nombre_tipo_evento' => $regla['nombre_tipo_evento'],
            'id_producto_servicio' => $regla['id_producto_servicio'],
            'nombre_producto_servicio' => $regla['nombre_producto_servicio'],
            'valor' => $valor,
            'valor_por_hora' => $regla['valor_sugerido'],
            'horas' => $horas,
            'minutos' => $minutos,
            'cobro_fraccion' => $regla['cobro_fraccion'],
            'hora_desde' => $regla['hora_desde'],
            'hora_hasta' => $regla['hora_hasta'],
            'prioridad' => $regla['prioridad'],
            'detalle' => $detalle
        ];
    }

    /**
     * Ejecuta los cobros: crea las cuentas por cobrar y registra el historial.
     * POST /motor-cobros/ejecutar
     */
    public static function ejecutar()
    {
        try {
            date_default_timezone_set('America/Bogota');
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $cobros = $data['cobros'];
            $id_estudiante = $data['id_estudiante'];
            $id_usuario = $data['id_usuario'];
            $fecha = isset($data['fecha']) ? $data['fecha'] : date('Y-m-d');

            if (empty($cobros) || !is_array($cobros)) {
                Flight::json(['error' => 'No se proporcionaron cobros a ejecutar'], 400);
                return;
            }

            $stmtPersona = $db->prepare("SELECT id_persona FROM estudiantes WHERE id = :id_estudiante");
            $stmtPersona->bindParam(':id_estudiante', $id_estudiante);
            $stmtPersona->execute();
            $personaRow = $stmtPersona->fetch(PDO::FETCH_ASSOC);
            if (!$personaRow) {
                Flight::json(['error' => 'Estudiante no encontrado'], 404);
                return;
            }
            $id_persona = $personaRow['id_persona'];

            $db->beginTransaction();

            $resultados = [];

            $stmtCuenta = $db->prepare("
                INSERT INTO cuentas_por_cobrar 
                (id_producto_servicio, id_persona, fecha, valor, detalle, id_usuario, anulado, fecha_anulacion, id_usuario_anulacion, id_horario_alimentacion)
                VALUES (:id_producto_servicio, :id_persona, :fecha, :valor, :detalle, :id_usuario, 0, NULL, NULL, NULL)
            ");

            $stmtHistorial = $db->prepare("
                INSERT INTO cobros_automaticos_historial 
                (id_regla_cobro, id_asistencia_estudiante, id_cuenta_por_cobrar, id_usuario, detalle)
                VALUES (:id_regla_cobro, :id_asistencia, :id_cuenta, :id_usuario, :detalle)
            ");

            foreach ($cobros as $cobro) {
                $stmtRegla = $db->prepare("
                    SELECT r.nombre, ps.nombre AS nombre_producto 
                    FROM reglas_cobro_automatico r
                    INNER JOIN productos_servicios ps ON ps.id = r.id_producto_servicio
                    WHERE r.id = :id_regla
                ");
                $stmtRegla->bindParam(':id_regla', $cobro['id_regla']);
                $stmtRegla->execute();
                $regla = $stmtRegla->fetch(PDO::FETCH_ASSOC);

                $detalle = "Cobro automático - " . ($regla ? $regla['nombre'] : 'Regla #' . $cobro['id_regla']);
                if (isset($cobro['detalle']) && !empty($cobro['detalle'])) {
                    $detalle = "Cobro automático - " . $cobro['detalle'];
                }

                $stmtCuenta->bindParam(':id_producto_servicio', $cobro['id_producto_servicio']);
                $stmtCuenta->bindParam(':id_persona', $id_persona);
                $stmtCuenta->bindParam(':fecha', $fecha);
                $stmtCuenta->bindParam(':valor', $cobro['valor']);
                $stmtCuenta->bindParam(':detalle', $detalle);
                $stmtCuenta->bindParam(':id_usuario', $id_usuario);
                $stmtCuenta->execute();

                $id_cuenta = $db->lastInsertId();

                $stmtHistorial->bindParam(':id_regla_cobro', $cobro['id_regla']);
                $stmtHistorial->bindParam(':id_asistencia', $cobro['id_asistencia']);
                $stmtHistorial->bindParam(':id_cuenta', $id_cuenta);
                $stmtHistorial->bindParam(':id_usuario', $id_usuario);
                $stmtHistorial->bindParam(':detalle', $detalle);
                $stmtHistorial->execute();

                $resultados[] = [
                    'id_cuenta_por_cobrar' => $id_cuenta,
                    'id_regla' => $cobro['id_regla'],
                    'valor' => $cobro['valor'],
                    'detalle' => $detalle
                ];
            }

            $db->commit();

            Flight::json([
                'success' => true,
                'cobros_generados' => count($resultados),
                'resultados' => $resultados
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en MotorCobrosAutomaticos::ejecutar: ' . $e->getMessage());
            Flight::json(['error' => 'Error al ejecutar cobros automáticos'], 500);
        }
    }
}