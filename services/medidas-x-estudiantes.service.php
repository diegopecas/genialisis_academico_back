<?php
class MedidasXEstudiantes
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT * FROM medidas_x_estudiantes WHERE id_tenant = :id_tenant");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAll medidas_x_estudiantes: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener las medidas de estudiantes'], 500);
        }
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM medidas_x_estudiantes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByEstudiante($id_estudiante)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.medidas');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                mxe.id, mxe.fecha, mxe.valor, mxe.id_estudiante, mxe.id_medida, mxe.id_documento_persona,
                m.nombre AS nombre_medida,
                umc.abreviatura AS unidad_abreviatura,
                tvm.nombre AS tipo_valor,
                CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) AS nombre_usuario,
                mxe.fecha_registro
            FROM medidas_x_estudiantes mxe
            INNER JOIN medidas m ON m.id = mxe.id_medida
            LEFT JOIN unidades_medidas_corporales umc ON umc.id = m.id_unidad
            LEFT JOIN tipos_valor_medida tvm ON tvm.id = m.id_tipo_valor
            INNER JOIN usuarios u ON u.id = mxe.id_usuario
            INNER JOIN personas p ON p.id = u.id_persona
            WHERE mxe.id_estudiante = :id_estudiante AND mxe.id_tenant = :id_tenant
            ORDER BY mxe.fecha DESC, m.id_categoria, m.orden
        ");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.medidas.administrar');

        $db = Flight::db();
        $id_estudiante = Flight::request()->data['id_estudiante'];
        $id_medida = Flight::request()->data['id_medida'];
        $fecha = Flight::request()->data['fecha'];
        $valor = Flight::request()->data['valor'];
        $id_usuario = Flight::request()->data['id_usuario'];
        $id_documento_persona = isset(Flight::request()->data['id_documento_persona']) ? Flight::request()->data['id_documento_persona'] : null;

        $idNew = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO medidas_x_estudiantes(id, id_tenant, id_estudiante, id_medida, fecha, valor, id_usuario, id_documento_persona) 
            VALUES (:id, :id_tenant, :id_estudiante, :id_medida, :fecha, :valor, :id_usuario, :id_documento_persona)
        ");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_medida', $id_medida);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':valor', $valor);
        $sentence->bindParam(':id_usuario', $id_usuario);
        $sentence->bindParam(':id_documento_persona', $id_documento_persona);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'estudiantes.medidas.administrar');

            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $id_estudiante = Flight::request()->data['id_estudiante'];
            $id_medida = Flight::request()->data['id_medida'];
            $fecha = Flight::request()->data['fecha'];
            $valor = Flight::request()->data['valor'];
            $id_usuario = Flight::request()->data['id_usuario'];
            $id_documento_persona = isset(Flight::request()->data['id_documento_persona']) ? Flight::request()->data['id_documento_persona'] : null;

            $sentence = $db->prepare("
                UPDATE medidas_x_estudiantes 
                SET id_estudiante = :id_estudiante, id_medida = :id_medida, fecha = :fecha, 
                    valor = :valor, id_usuario = :id_usuario, id_documento_persona = :id_documento_persona
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindParam(':id_medida', $id_medida);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindParam(':valor', $valor);
            $sentence->bindParam(':id_usuario', $id_usuario);
            $sentence->bindParam(':id_documento_persona', $id_documento_persona);
            $sentence->execute();
            self::getById($id);
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()));
        }
    }

    public static function delete($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'estudiantes.medidas.administrar');

            $db = Flight::db();
            $sentence = $db->prepare("DELETE FROM medidas_x_estudiantes WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            if ($sentence->rowCount() > 0) {
                Flight::json(["success" => true, "message" => "Registro eliminado correctamente"]);
            } else {
                Flight::json(["success" => false, "message" => "No se encontró el registro para eliminar"], 404);
            }
        } catch (Exception $e) {
            Flight::json(["success" => false, "message" => "Error en la eliminación", "error" => $e->getMessage()], 500);
        }
    }

    public static function verificarDuplicados()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $sql = "SELECT mxe.id, mxe.fecha, mxe.valor, m.nombre AS nombre_medida
                    FROM medidas_x_estudiantes mxe
                    INNER JOIN medidas m ON m.id = mxe.id_medida
                    WHERE mxe.id_medida = :id_medida 
                    AND mxe.id_estudiante = :id_estudiante 
                    AND mxe.fecha = :fecha
                    AND mxe.id_tenant = :id_tenant";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_medida', $data['id_medida']);
            $stmt->bindParam(':id_estudiante', $data['id_estudiante']);
            $stmt->bindParam(':fecha', $data['fecha']);
            $stmt->execute();
            $registrosDuplicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json([
                'duplicados' => $registrosDuplicados,
                'cantidad' => count($registrosDuplicados)
            ]);
        } catch (Exception $e) {
            error_log('Error en verificarDuplicados: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al verificar duplicados'), 500);
        }
    }

    public static function getResumenMedidasPorGrupo($id_grupo)
    {
        try {
            $db = Flight::db();
            $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
            $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');

            $sql = "SELECT 
                g.id AS id_grupo, g.nombre AS nombre_grupo,
                COUNT(DISTINCT eg.id_estudiante) as total_estudiantes,
                COUNT(DISTINCT CASE WHEN mxe.id IS NOT NULL THEN eg.id_estudiante END) as estudiantes_con_medidas,
                COUNT(DISTINCT CASE 
                    WHEN mxe.fecha BETWEEN :fecha_inicio1 AND :fecha_fin1 AND mxe.id_medida IN (SELECT m.id FROM medidas m WHERE m.codigo = 'PESO' AND m.id_tenant = mxe.id_tenant)
                    THEN CONCAT(eg.id_estudiante, '-', WEEK(mxe.fecha))
                END) as registros_peso_semanales,
                COUNT(DISTINCT CASE 
                    WHEN mxe.fecha BETWEEN :fecha_inicio2 AND :fecha_fin2 AND mxe.id_medida IN (SELECT m.id FROM medidas m WHERE m.codigo = 'TALLA' AND m.id_tenant = mxe.id_tenant)
                    THEN CONCAT(eg.id_estudiante, '-', WEEK(mxe.fecha))
                END) as registros_talla_semanales,
                CEIL(DATEDIFF(:fecha_fin3, :fecha_inicio3) / 7) as total_semanas
            FROM grupos g
            INNER JOIN estudiantes_x_grupos eg ON g.id = eg.id_grupo AND eg.activo = 1
            INNER JOIN estudiantes e ON eg.id_estudiante = e.id AND e.activo = 1
            LEFT JOIN medidas_x_estudiantes mxe ON eg.id_estudiante = mxe.id_estudiante
            WHERE g.id = :id_grupo
            AND g.id_tenant = :id_tenant
            GROUP BY g.id, g.nombre";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_grupo', $id_grupo);
            $stmt->bindParam(':fecha_inicio1', $fecha_inicio);
            $stmt->bindParam(':fecha_fin1', $fecha_fin);
            $stmt->bindParam(':fecha_inicio2', $fecha_inicio);
            $stmt->bindParam(':fecha_fin2', $fecha_fin);
            $stmt->bindParam(':fecha_inicio3', $fecha_inicio);
            $stmt->bindParam(':fecha_fin3', $fecha_fin);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                $total_registros_esperados = $resultado['total_estudiantes'] * $resultado['total_semanas'];
                $resultado['porcentaje_cumplimiento_peso'] = $total_registros_esperados > 0
                    ? round(($resultado['registros_peso_semanales'] / $total_registros_esperados) * 100, 2) : 0;
                $resultado['porcentaje_cumplimiento_talla'] = $total_registros_esperados > 0
                    ? round(($resultado['registros_talla_semanales'] / $total_registros_esperados) * 100, 2) : 0;
                $resultado['porcentaje_estudiantes_con_medidas'] = $resultado['total_estudiantes'] > 0
                    ? round(($resultado['estudiantes_con_medidas'] / $resultado['total_estudiantes']) * 100, 2) : 0;
            }

            Flight::json($resultado);
        } catch (Exception $e) {
            error_log("Error en getResumenMedidasPorGrupo: " . $e->getMessage());
            Flight::json(['error' => 'Error al obtener resumen de medidas'], 500);
        }
    }

    /**
     * Obtiene medidas de múltiples estudiantes - versión dinámica
     */
    public static function getMedidasMultiplesEstudiantes()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            if (empty($data) || !isset($data['estudiantes_ids']) || !isset($data['fecha'])) {
                Flight::json(['error' => 'Faltan parámetros: estudiantes_ids y fecha son requeridos'], 400);
                return;
            }

            $estudiantesIds = $data['estudiantes_ids'];
            $fecha = $data['fecha'];
            $medidasIds = isset($data['medidas_ids']) ? $data['medidas_ids'] : null;

            if (empty($estudiantesIds) || !is_array($estudiantesIds)) {
                Flight::json(['error' => 'estudiantes_ids debe ser un array con IDs válidos'], 400);
                return;
            }

            $placeholdersEst = str_repeat('?,', count($estudiantesIds) - 1) . '?';

            $filtroMedidas = '';
            $paramsMedidas = [];
            if ($medidasIds && is_array($medidasIds) && count($medidasIds) > 0) {
                $placeholdersMed = str_repeat('?,', count($medidasIds) - 1) . '?';
                $filtroMedidas = "AND mxe.id_medida IN ($placeholdersMed)";
                $paramsMedidas = $medidasIds;
            }

            $sql = "
                SELECT 
                    mxe.id, mxe.id_estudiante, mxe.id_medida, mxe.fecha, mxe.valor, mxe.id_documento_persona,
                    dp.ruta_archivo,
                    CASE 
                        WHEN mxe.fecha = ? THEN 'actual'
                        WHEN mxe.fecha < ? THEN 'anterior'
                    END AS tipo_registro,
                    ROW_NUMBER() OVER (
                        PARTITION BY mxe.id_estudiante, mxe.id_medida, 
                        CASE WHEN mxe.fecha < ? THEN 'anterior' ELSE 'actual' END
                        ORDER BY mxe.fecha DESC
                    ) as ranking
                FROM medidas_x_estudiantes mxe
                LEFT JOIN documentos_personas dp ON dp.id = mxe.id_documento_persona
                WHERE mxe.id_estudiante IN ($placeholdersEst)
                AND (mxe.fecha = ? OR mxe.fecha < ?)
                $filtroMedidas
                AND mxe.id_tenant = ?
                ORDER BY mxe.id_estudiante, mxe.id_medida, mxe.fecha DESC
            ";

            $params = [$fecha, $fecha, $fecha];
            $params = array_merge($params, $estudiantesIds);
            $params[] = $fecha;
            $params[] = $fecha;
            $params = array_merge($params, $paramsMedidas);
            $params[] = TenantContext::id();

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $todasLasMedidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $resultado = [];
            foreach ($estudiantesIds as $estudianteId) {
                $resultado[$estudianteId] = [
                    'id_estudiante' => $estudianteId,
                    'id_documento_persona' => null,
                    'ruta_imagen' => null,
                    'medidas' => []
                ];
            }

            foreach ($todasLasMedidas as $medida) {
                $estId = $medida['id_estudiante'];
                $medId = $medida['id_medida'];

                if (!isset($resultado[$estId]['medidas'][$medId])) {
                    $resultado[$estId]['medidas'][$medId] = [
                        'id_medida' => $medId,
                        'valor_actual' => null,
                        'id_registro_actual' => null,
                        'valor_anterior' => null,
                        'fecha_anterior' => null
                    ];
                }

                $ref = &$resultado[$estId]['medidas'][$medId];

                if ($medida['tipo_registro'] === 'actual') {
                    $ref['valor_actual'] = (float) $medida['valor'];
                    $ref['id_registro_actual'] = $medida['id'];
                    // Guardar ruta_imagen a nivel estudiante (solo la primera vez)
                    if ($medida['id_documento_persona'] && !$resultado[$estId]['ruta_imagen']) {
                        $resultado[$estId]['id_documento_persona'] = $medida['id_documento_persona'];
                        $resultado[$estId]['ruta_imagen'] = $medida['ruta_archivo'];
                    }
                } elseif ($medida['tipo_registro'] === 'anterior' && $medida['ranking'] == 1) {
                    $ref['valor_anterior'] = (float) $medida['valor'];
                    $ref['fecha_anterior'] = $medida['fecha'];
                }
            }

            $respuestaFinal = [];
            foreach ($resultado as $estId => $data) {
                $respuestaFinal[] = [
                    'id_estudiante' => $data['id_estudiante'],
                    'id_documento_persona' => $data['id_documento_persona'],
                    'ruta_imagen' => $data['ruta_imagen'],
                    'medidas' => array_values($data['medidas'])
                ];
            }

            Flight::json([
                'fecha' => $fecha,
                'total_estudiantes' => count($estudiantesIds),
                'estudiantes' => $respuestaFinal
            ]);

        } catch (Exception $e) {
            error_log('Error en getMedidasMultiplesEstudiantes: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener medidas de estudiantes'], 500);
        }
    }

    /**
     * Analiza imagen de reporte de báscula inteligente con Gemini
     */
    public static function analizarReporteMedidas()
    {
        try {
            if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                Flight::json(['error' => 'No se recibió la imagen o hubo un error al subirla'], 400);
                return;
            }

            $archivo = $_FILES['imagen'];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, ['jpg', 'jpeg', 'png'])) {
                Flight::json(['error' => 'Solo se permiten archivos JPG, JPEG o PNG'], 400);
                return;
            }

            if ($archivo['size'] > 10 * 1024 * 1024) {
                Flight::json(['error' => 'El archivo excede el tamaño máximo de 10MB'], 400);
                return;
            }

            $medidasJson = isset($_POST['medidas']) ? $_POST['medidas'] : null;
            if (!$medidasJson) {
                Flight::json(['error' => 'Se requiere el parámetro medidas'], 400);
                return;
            }

            $medidasSeleccionadas = json_decode($medidasJson, true);
            if (!$medidasSeleccionadas || !is_array($medidasSeleccionadas)) {
                Flight::json(['error' => 'El parámetro medidas debe ser un JSON válido'], 400);
                return;
            }

            $db = Flight::db();
            $stmt = $db->prepare("SELECT valor FROM ia_configuracion WHERE clave = 'gemini_api_key' AND id_tenant = :id_tenant LIMIT 1");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $config = $stmt->fetch();

            if (!$config || empty($config['valor'])) {
                Flight::json(['error' => 'API Key de Gemini no configurada'], 500);
                return;
            }

            $apiKey = $config['valor'];

            $stmtEstado = $db->prepare("SELECT valor FROM ia_configuracion WHERE clave = 'estado_servicio' AND id_tenant = :id_tenant LIMIT 1");
            $stmtEstado->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtEstado->execute();
            $estado = $stmtEstado->fetch();

            if ($estado && $estado['valor'] !== 'activo') {
                Flight::json(['error' => 'El servicio de IA se encuentra pausado o en mantenimiento'], 503);
                return;
            }

            $contenidoArchivo = file_get_contents($archivo['tmp_name']);
            $base64 = base64_encode($contenidoArchivo);
            $mimeType = 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension);

            $listadoMedidas = "";
            foreach ($medidasSeleccionadas as $medida) {
                $linea = "  - id_medida: " . $medida['id'] . ", nombre: \"" . $medida['nombre'] . "\"";
                if (isset($medida['opciones']) && is_array($medida['opciones']) && count($medida['opciones']) > 0) {
                    $opcionesTexto = array_map(function($o) {
                        return $o['etiqueta'] . "=" . $o['valor_numerico'];
                    }, $medida['opciones']);
                    $linea .= ", tipo: select, opciones: [" . implode(", ", $opcionesTexto) . "]";
                }
                $listadoMedidas .= $linea . "\n";
            }

            $prompt = "Analiza esta imagen de un reporte de báscula inteligente de composición corporal "
                . "(puede ser Cubitt Health, Xiaomi, Huawei, Renpho, u otra marca) y extrae los valores de las siguientes medidas.\n\n"
                . "MEDIDAS A BUSCAR:\n" . $listadoMedidas . "\n"
                . "Responde ÚNICAMENTE con un JSON estricto, sin explicaciones ni texto adicional:\n\n"
                . "{\n"
                . "  \"fecha_imagen\": \"YYYY-MM-DD o null si no se encuentra\",\n"
                . "  \"medidas\": [\n"
                . "    {\"id_medida\": (int), \"valor\": (número decimal o entero)}\n"
                . "  ]\n"
                . "}\n\n"
                . "REGLAS:\n"
                . "- Solo incluye en el array las medidas que encuentres en la imagen.\n"
                . "- Si una medida no aparece en la imagen, NO la incluyas.\n"
                . "- Los valores deben ser numéricos. Si es porcentaje, solo el número (ej: 7.6 no '7.6%').\n"
                . "- Para medidas tipo 'select' (como Tipo de cuerpo), busca el texto en la imagen y devuelve el valor_numerico correspondiente de las opciones. "
                . "Por ejemplo si la imagen dice 'Delgado con músculos' y las opciones incluyen 'Delgado con músculos=2', devuelve valor: 2.\n"
                . "- La fecha conviértela a YYYY-MM-DD.\n"
                . "- Usa el campo 'nombre' para buscar equivalentes (ej: 'IMC' puede ser 'BMI').";

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=" . $apiKey;

            $payload = [
                'contents' => [[
                    'parts' => [
                        ['inlineData' => ['mimeType' => $mimeType, 'data' => $base64]],
                        ['text' => $prompt]
                    ]
                ]],
                'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 1000]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                Flight::json(['error' => 'Error de conexión con IA: ' . $curlError], 500);
                return;
            }

            if ($httpCode !== 200) {
                error_log("Error HTTP Gemini medidas: " . $httpCode . " - " . $response);
                Flight::json(['error' => 'Error en el servicio de IA (HTTP ' . $httpCode . ')'], 500);
                return;
            }

            $respuestaGemini = json_decode($response, true);

            if (!$respuestaGemini || !isset($respuestaGemini['candidates'][0]['content']['parts'][0]['text'])) {
                Flight::json(['error' => 'No se pudo interpretar la respuesta de la IA'], 500);
                return;
            }

            $textoRespuesta = $respuestaGemini['candidates'][0]['content']['parts'][0]['text'];
            $textoRespuesta = preg_replace('/```json\s*/', '', $textoRespuesta);
            $textoRespuesta = preg_replace('/```\s*/', '', $textoRespuesta);
            $textoRespuesta = trim($textoRespuesta);

            $datosExtraidos = json_decode($textoRespuesta, true);

            if (!$datosExtraidos) {
                Flight::json(['error' => 'No se pudieron extraer los datos del reporte', 'respuesta_ia' => $textoRespuesta], 422);
                return;
            }

            $stmtContador = $db->prepare("UPDATE ia_configuracion SET valor = valor + 1, fecha_actualizacion = NOW() WHERE clave = 'mensajes_generados_hoy' AND id_tenant = :id_tenant");
            $stmtContador->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtContador->execute();

            $tokensInput = 0;
            $tokensOutput = 0;
            $tokensTotal = 0;
            if (isset($respuestaGemini['usageMetadata'])) {
                $tokensInput = isset($respuestaGemini['usageMetadata']['promptTokenCount']) ? intval($respuestaGemini['usageMetadata']['promptTokenCount']) : 0;
                $tokensOutput = isset($respuestaGemini['usageMetadata']['candidatesTokenCount']) ? intval($respuestaGemini['usageMetadata']['candidatesTokenCount']) : 0;
                $tokensTotal = $tokensInput + $tokensOutput;
            }

            if ($tokensTotal > 0) {
                $stmtTokens = $db->prepare("UPDATE ia_configuracion SET valor = valor + :tokens, fecha_actualizacion = NOW() WHERE clave = 'tokens_consumidos_hoy' AND id_tenant = :id_tenant");
                $stmtTokens->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtTokens->bindParam(':tokens', $tokensTotal);
                $stmtTokens->execute();
            }

            Flight::json([
                'success' => true,
                'datos' => [
                    'fecha_imagen' => isset($datosExtraidos['fecha_imagen']) ? $datosExtraidos['fecha_imagen'] : null,
                    'medidas' => isset($datosExtraidos['medidas']) ? $datosExtraidos['medidas'] : []
                ],
                'tokens' => ['input' => $tokensInput, 'output' => $tokensOutput, 'total' => $tokensTotal]
            ]);
        } catch (Exception $e) {
            error_log("Error en analizarReporteMedidas: " . $e->getMessage());
            Flight::json(['error' => 'Error interno al procesar el reporte: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Registra múltiples medidas de múltiples estudiantes en una transacción
     */
    public static function registrarMasivoMedidas()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $fecha = isset($data['fecha']) ? $data['fecha'] : null;
            $id_usuario = isset($data['id_usuario']) ? $data['id_usuario'] : null;
            $registros = isset($data['registros']) ? $data['registros'] : [];

            if (!$fecha || !$id_usuario || empty($registros)) {
                Flight::json(['error' => 'Faltan parámetros: fecha, id_usuario y registros son requeridos'], 400);
                return;
            }

            $db->beginTransaction();

            try {
                $stmtInsert = $db->prepare("
                    INSERT INTO medidas_x_estudiantes (id_tenant, id_estudiante, id_medida, fecha, valor, id_usuario, id_documento_persona)
                    VALUES (:id_tenant, :id_estudiante, :id_medida, :fecha, :valor, :id_usuario, :id_documento_persona)
                ");
                $stmtInsert->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

                $stmtUpdate = $db->prepare("
                    UPDATE medidas_x_estudiantes 
                    SET valor = :valor, id_usuario = :id_usuario, id_documento_persona = :id_documento_persona
                    WHERE id = :id AND id_tenant = :id_tenant
                ");
                $stmtUpdate->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

                $totalInsertados = 0;
                $totalActualizados = 0;
                $errores = [];

                foreach ($registros as $registro) {
                    $id_estudiante = $registro['id_estudiante'];
                    $id_documento_persona = isset($registro['id_documento_persona']) ? $registro['id_documento_persona'] : null;
                    $medidas = isset($registro['medidas']) ? $registro['medidas'] : [];

                    foreach ($medidas as $medida) {
                        $id_medida = $medida['id_medida'];
                        $valor = $medida['valor'];
                        $id_registro = isset($medida['id_registro']) ? $medida['id_registro'] : null;

                        if ($valor === null || $valor === '') {
                            continue;
                        }

                        try {
                            if ($id_registro) {
                                $stmtUpdate->bindParam(':valor', $valor);
                                $stmtUpdate->bindParam(':id_usuario', $id_usuario);
                                $stmtUpdate->bindParam(':id_documento_persona', $id_documento_persona);
                                $stmtUpdate->bindParam(':id', $id_registro);
                                $stmtUpdate->execute();
                                $totalActualizados++;
                            } else {
                                $stmtInsert->bindParam(':id_estudiante', $id_estudiante);
                                $stmtInsert->bindParam(':id_medida', $id_medida);
                                $stmtInsert->bindParam(':fecha', $fecha);
                                $stmtInsert->bindParam(':valor', $valor);
                                $stmtInsert->bindParam(':id_usuario', $id_usuario);
                                $stmtInsert->bindParam(':id_documento_persona', $id_documento_persona);
                                $stmtInsert->execute();
                                $totalInsertados++;
                            }
                        } catch (Exception $e) {
                            $errores[] = ['id_estudiante' => $id_estudiante, 'id_medida' => $id_medida, 'error' => $e->getMessage()];
                        }
                    }
                }

                if ($totalInsertados === 0 && $totalActualizados === 0 && count($errores) > 0) {
                    $db->rollBack();
                    Flight::json(['success' => false, 'message' => 'No se pudo registrar ninguna medida', 'errores' => $errores], 400);
                    return;
                }

                $db->commit();

                Flight::json([
                    'success' => true,
                    'insertados' => $totalInsertados,
                    'actualizados' => $totalActualizados,
                    'errores' => $errores,
                    'message' => "Se procesaron " . ($totalInsertados + $totalActualizados) . " medidas"
                ]);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            error_log("Error en registrarMasivoMedidas: " . $e->getMessage());
            Flight::json(['error' => 'Error al registrar medidas: ' . $e->getMessage()], 500);
        }
    }
}