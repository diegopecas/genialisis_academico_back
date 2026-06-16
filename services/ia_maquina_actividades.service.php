<?php
class IaMaquinaActividades
{
    /**
     * Genera actividades usando IA basándose en los parámetros de la docente.
     * Retorna las actividades sugeridas para revisión (no graba nada aún).
     */
    public static function generarActividades()
    {
        try {
            $db = Flight::db();
            $data = json_decode(Flight::request()->getBody(), true);

            $camposRequeridos = ['id_grupo', 'id_area', 'id_sprint', 'cantidad', 'descripcion_docente'];
            foreach ($camposRequeridos as $campo) {
                if (empty($data[$campo])) {
                    Flight::json(["error" => "El campo $campo es requerido"], 400);
                    return;
                }
            }

            $id_grupo = $data['id_grupo'];
            $id_area = $data['id_area'];
            $id_sprint = $data['id_sprint'];
            $cantidad = min(max((int)$data['cantidad'], 1), 10);
            $descripcion_docente = $data['descripcion_docente'];
            $ambientes = $data['ambientes'] ?? [];
            $materiales = $data['materiales'] ?? [];
            $nombre_grupo = $data['nombre_grupo'] ?? 'el grupo';
            $nombre_area = $data['nombre_area'] ?? 'el área';

            $stmtGrados = $db->prepare("
                SELECT g.id, g.nombre 
                FROM grados_x_grupo gxg 
                INNER JOIN grados g ON gxg.id_grado = g.id 
                WHERE gxg.id_grupo = :id_grupo
            ");
            $stmtGrados->bindParam(':id_grupo', $id_grupo);
            $stmtGrados->execute();
            $grados = $stmtGrados->fetchAll(PDO::FETCH_ASSOC);
            $gradosIds = array_column($grados, 'id');
            $gradosTexto = implode(', ', array_column($grados, 'nombre'));

            $stmtSprint = $db->prepare("
                SELECT s.id_corte_academico, ca.nombre AS nombre_corte 
                FROM sprints s 
                LEFT JOIN cortes_academicos ca ON s.id_corte_academico = ca.id 
                WHERE s.id = :id_sprint
            ");
            $stmtSprint->bindParam(':id_sprint', $id_sprint);
            $stmtSprint->execute();
            $sprint = $stmtSprint->fetch(PDO::FETCH_ASSOC);
            $id_corte = $sprint['id_corte_academico'] ?? null;

            $placeholders = implode(',', array_fill(0, count($gradosIds), '?'));
            $sqlLogros = "
                SELECT 
                    l.id AS logro_id,
                    l.nombre AS logro_nombre,
                    ed.nombre AS esfera_nombre,
                    il.id AS indicador_id,
                    il.nombre AS indicador_nombre
                FROM logros l
                INNER JOIN indicadores_logros il ON l.id = il.id_logro
                LEFT JOIN esferas_desarrollo ed ON l.id_esfera_desarrollo = ed.id
                WHERE l.id_grado IN ($placeholders)
                AND l.id_area_academica = ?
                " . ($id_corte ? "AND l.id_corte_academico = ?" : "") . "
                ORDER BY l.nombre, il.nombre
            ";
            $stmtLogros = $db->prepare($sqlLogros);
            $paramIndex = 1;
            foreach ($gradosIds as $gid) {
                $stmtLogros->bindValue($paramIndex++, $gid);
            }
            $stmtLogros->bindValue($paramIndex++, $id_area);
            if ($id_corte) {
                $stmtLogros->bindValue($paramIndex++, $id_corte);
            }
            $stmtLogros->execute();
            $logrosIndicadores = $stmtLogros->fetchAll(PDO::FETCH_ASSOC);

            if (empty($logrosIndicadores)) {
                Flight::json(["error" => "No se encontraron logros/indicadores para este grupo, área y corte"], 404);
                return;
            }

            $logrosAgrupados = [];
            foreach ($logrosIndicadores as $li) {
                $lid = $li['logro_id'];
                if (!isset($logrosAgrupados[$lid])) {
                    $logrosAgrupados[$lid] = [
                        'id' => $lid,
                        'nombre' => $li['logro_nombre'],
                        'esfera' => $li['esfera_nombre'],
                        'indicadores' => []
                    ];
                }
                $logrosAgrupados[$lid]['indicadores'][] = [
                    'id' => $li['indicador_id'],
                    'nombre' => $li['indicador_nombre']
                ];
            }

            $stmtTipos = $db->prepare("SELECT id, nombre FROM tipos_actividades_academicas ORDER BY nombre");
            $stmtTipos->execute();
            $tiposActividad = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

            $logrosTexto = "";
            foreach ($logrosAgrupados as $logro) {
                $logrosTexto .= "\nLOGRO ID:{$logro['id']} \"{$logro['nombre']}\" (Esfera: {$logro['esfera']})\n";
                foreach ($logro['indicadores'] as $ind) {
                    $logrosTexto .= "  INDICADOR ID:{$ind['id']} \"{$ind['nombre']}\"\n";
                }
            }

            $ambientesTexto = !empty($ambientes) ? implode(', ', array_column($ambientes, 'nombre')) : 'No especificado';
            $materialesTexto = "";
            if (!empty($materiales)) {
                $nombres = array_map(function($m) {
                    return $m['nombre'] ?? $m['nombre_material'] ?? '';
                }, $materiales);
                $materialesTexto = implode(', ', array_filter($nombres));
            }
            if (empty($materialesTexto)) $materialesTexto = 'No especificados';

            $tiposTexto = implode(', ', array_map(function($t) {
                return "ID:{$t['id']} \"{$t['nombre']}\"";
            }, $tiposActividad));

            $prompt = <<<PROMPT
Eres un experto pedagógico en educación preescolar colombiana. Genera exactamente {$cantidad} actividades académicas para el grupo "{$nombre_grupo}" (Grado: {$gradosTexto}) en el área "{$nombre_area}".

INSTRUCCIONES DE LA DOCENTE:
"{$descripcion_docente}"

AMBIENTES DISPONIBLES: {$ambientesTexto}
MATERIALES DISPONIBLES: {$materialesTexto}

TIPOS DE ACTIVIDAD DISPONIBLES: {$tiposTexto}

LOGROS E INDICADORES DISPONIBLES:
{$logrosTexto}

REGLAS:
1. Genera exactamente {$cantidad} actividades distintas, variadas y creativas.
2. Cada actividad debe ser apropiada para niños de grado {$gradosTexto}.
3. Usa los materiales proporcionados cuando sea posible.
4. Usa los ambientes proporcionados, distribuyéndolos entre las actividades.
5. Cada actividad debe cubrir al menos 1 indicador de logro de los disponibles. Usa los IDs exactos.
6. El id_tipo_actividad debe corresponder a uno de los tipos disponibles. Usa los IDs exactos.
7. nivel_uno es la actividad básica/introductoria. nivel_dos es la actividad avanzada/de profundización.
8. Las actividades deben ser divertidas, originales y estimulantes para la edad.

Responde ÚNICAMENTE con JSON válido (sin markdown, sin backticks). Estructura:

[
  {
    "titulo": "nombre creativo de la actividad",
    "descripcion": "descripción detallada en HTML con formato (máx 500 chars)",
    "nivel_uno": "actividad nivel básico en HTML (máx 300 chars)",
    "nivel_dos": "actividad nivel avanzado en HTML (máx 300 chars)",
    "minutos_duracion": 60,
    "id_tipo_actividad_academica": 1,
    "materiales_sugeridos": ["nombre material 1", "nombre material 2"],
    "id_ambiente": 1,
    "indicadores_ids": [1, 2, 3]
  }
]

Responde SOLO con el array JSON.
PROMPT;

            $config = self::obtenerConfiguracion($db);
            $inicio_tiempo = microtime(true);
            $respuesta_ia = self::llamarIA($config, $prompt);
            $tiempo_ms = round((microtime(true) - $inicio_tiempo) * 1000);

            if (!$respuesta_ia['success']) {
                Flight::json(["error" => "Error al generar actividades: " . ($respuesta_ia['error'] ?? 'desconocido')], 500);
                return;
            }

            $textoRespuesta = $respuesta_ia['respuesta'];
            $textoRespuesta = preg_replace('/^```json\s*/s', '', $textoRespuesta);
            $textoRespuesta = preg_replace('/\s*```$/s', '', $textoRespuesta);
            $textoRespuesta = trim($textoRespuesta);

            $posInicio = strpos($textoRespuesta, '[');
            $posFin = strrpos($textoRespuesta, ']');
            if ($posInicio !== false && $posFin !== false) {
                $textoRespuesta = substr($textoRespuesta, $posInicio, $posFin - $posInicio + 1);
            }

            $actividades = json_decode($textoRespuesta, true);

            if (!$actividades || json_last_error() !== JSON_ERROR_NONE) {
                error_log("IaMaquinaActividades - JSON parse error: " . json_last_error_msg());
                error_log("IaMaquinaActividades - Texto: " . substr($textoRespuesta, 0, 500));
                Flight::json([
                    "error" => "Error al parsear la respuesta de IA",
                    "respuesta_cruda" => $textoRespuesta,
                    "proveedor" => $respuesta_ia['proveedor'],
                    "tiempo_ms" => $tiempo_ms
                ], 500);
                return;
            }

            foreach ($actividades as &$act) {
                $indicadoresEnriquecidos = [];
                $idsIndicadores = $act['indicadores_ids'] ?? [];
                foreach ($idsIndicadores as $idInd) {
                    foreach ($logrosIndicadores as $li) {
                        if ((int)$li['indicador_id'] === (int)$idInd) {
                            $indicadoresEnriquecidos[] = [
                                'id' => (int)$li['indicador_id'],
                                'nombre' => $li['indicador_nombre'],
                                'logro_id' => (int)$li['logro_id'],
                                'logro_nombre' => $li['logro_nombre']
                            ];
                            break;
                        }
                    }
                }
                $act['indicadores'] = $indicadoresEnriquecidos;
            }
            unset($act);

            Flight::json([
                "success" => true,
                "actividades" => $actividades,
                "logros_disponibles" => array_values($logrosAgrupados),
                "proveedor" => $respuesta_ia['proveedor'],
                "tiempo_ms" => $tiempo_ms
            ]);

        } catch (Exception $e) {
            error_log("Error en IaMaquinaActividades::generarActividades: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Graba todas las actividades confirmadas en una sola transacción.
     */
    public static function grabarActividades()
    {
        $db = Flight::db();

        try {
            $data = json_decode(Flight::request()->getBody(), true);

            if (!$data || empty($data['actividades'])) {
                Flight::json(["error" => "Se requiere al menos una actividad"], 400);
                return;
            }

            $id_sprint = $data['id_sprint'] ?? null;
            $id_grupo = $data['id_grupo'] ?? null;
            $id_area = $data['id_area'] ?? null;
            $es_tarea_adicional = !empty($data['es_tarea_adicional']) ? 1 : 0;

            if (!$id_sprint || !$id_grupo || !$id_area) {
                Flight::json(["error" => "id_sprint, id_grupo e id_area son requeridos"], 400);
                return;
            }

            $db->beginTransaction();

            $resultados = [];

            foreach ($data['actividades'] as $act) {
                $materialesTexto = '';
                if (!empty($act['materiales'])) {
                    $nombres = array_map(function($m) {
                        return $m['nombre_material'] ?? $m['nombre'] ?? '';
                    }, $act['materiales']);
                    $materialesTexto = implode(', ', array_filter($nombres));
                }

                $idTipo = !empty($act['id_tipo_actividad_academica']) ? (int)$act['id_tipo_actividad_academica'] : 1;
                $idAmbiente = !empty($act['id_ambiente']) ? (int)$act['id_ambiente'] : null;

                $stmtActividad = $db->prepare("
                    INSERT INTO actividades_academicas 
                    (id_tipo_actividad_academica, titulo, descripcion, nivel_uno, nivel_dos, minutos_duracion, materiales, id_ambiente)
                    VALUES (:id_tipo, :titulo, :descripcion, :nivel_uno, :nivel_dos, :duracion, :materiales, :id_ambiente)
                ");
                $stmtActividad->bindValue(':id_tipo', $idTipo);
                $stmtActividad->bindValue(':titulo', $act['titulo']);
                $stmtActividad->bindValue(':descripcion', $act['descripcion'] ?? '');
                $stmtActividad->bindValue(':nivel_uno', $act['nivel_uno'] ?? '');
                $stmtActividad->bindValue(':nivel_dos', $act['nivel_dos'] ?? '');
                $stmtActividad->bindValue(':duracion', $act['minutos_duracion'] ?? 45);
                $stmtActividad->bindValue(':materiales', $materialesTexto);
                $stmtActividad->bindValue(':id_ambiente', $idAmbiente);
                $stmtActividad->execute();

                $idActividad = $db->lastInsertId();

                if (!empty($act['materiales'])) {
                    $stmtMaterial = $db->prepare("
                        INSERT INTO materiales_x_actividad (id_actividad_academica, id_producto, nombre_material, cantidad)
                        VALUES (:id_actividad, :id_producto, :nombre_material, :cantidad)
                    ");
                    foreach ($act['materiales'] as $mat) {
                        $stmtMaterial->bindValue(':id_actividad', $idActividad);
                        $stmtMaterial->bindValue(':id_producto', $mat['id_producto'] ?? null);
                        $stmtMaterial->bindValue(':nombre_material', $mat['nombre_material'] ?? $mat['nombre'] ?? '');
                        $stmtMaterial->bindValue(':cantidad', $mat['cantidad'] ?? 1);
                        $stmtMaterial->execute();
                    }
                }

                if (!empty($act['indicadores_ids'])) {
                    $stmtIndicador = $db->prepare("
                        INSERT INTO actividades_academicas_x_indicadores_logros (id_actividad_academica, id_indicador_logro)
                        VALUES (:id_actividad, :id_indicador)
                    ");
                    foreach ($act['indicadores_ids'] as $idIndicador) {
                        $stmtCheck = $db->prepare("SELECT COUNT(*) as count FROM actividades_academicas_x_indicadores_logros WHERE id_actividad_academica = :id_act AND id_indicador_logro = :id_ind");
                        $stmtCheck->bindValue(':id_act', $idActividad);
                        $stmtCheck->bindValue(':id_ind', $idIndicador);
                        $stmtCheck->execute();
                        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                        if ($existe['count'] == 0) {
                            $stmtIndicador->bindValue(':id_actividad', $idActividad);
                            $stmtIndicador->bindValue(':id_indicador', $idIndicador);
                            $stmtIndicador->execute();
                        }
                    }
                }

                $stmtTarea = $db->prepare("
                    INSERT INTO tareas_x_sprints 
                    (id_sprint, id_actividad_academica, id_grupo, id_area_academica, id_estado_tarea, es_tarea_adicional, fecha_registro)
                    VALUES (:id_sprint, :id_actividad, :id_grupo, :id_area, 1, :es_tarea_adicional, NOW())
                ");
                $stmtTarea->bindValue(':id_sprint', $id_sprint);
                $stmtTarea->bindValue(':id_actividad', $idActividad);
                $stmtTarea->bindValue(':id_grupo', $id_grupo);
                $stmtTarea->bindValue(':id_area', $id_area);
                $stmtTarea->bindValue(':es_tarea_adicional', $es_tarea_adicional);
                $stmtTarea->execute();

                $resultados[] = [
                    'id_actividad' => $idActividad,
                    'titulo' => $act['titulo'],
                    'id_tarea_sprint' => $db->lastInsertId()
                ];
            }

            $db->commit();

            Flight::json([
                "success" => true,
                "total_creadas" => count($resultados),
                "actividades" => $resultados
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en IaMaquinaActividades::grabarActividades: " . $e->getMessage());
            Flight::json(["error" => "Error al grabar actividades: " . $e->getMessage()], 500);
        }
    }

    private static function obtenerConfiguracion($db)
    {
        $sentence = $db->prepare("SELECT clave, valor FROM ia_configuracion");
        $sentence->execute();
        $rows = $sentence->fetchAll(PDO::FETCH_ASSOC);
        $config = [];
        foreach ($rows as $row) {
            $config[$row['clave']] = $row['valor'];
        }
        return $config;
    }

    private static function llamarIA($config, $prompt)
    {
        $gemini_key = $config['gemini_api_key'] ?? null;
        if ($gemini_key) {
            $resultado = self::llamarGemini($gemini_key, $prompt);
            if ($resultado['success']) {
                return ["success" => true, "respuesta" => $resultado['respuesta'], "proveedor" => "gemini"];
            }
            error_log("IaMaquinaActividades - Gemini falló: " . ($resultado['error'] ?? 'desconocido'));
        }

        $groq_key = $config['groq_api_key'] ?? null;
        if ($groq_key) {
            $resultado = self::llamarGroq($groq_key, $prompt);
            if ($resultado['success']) {
                return ["success" => true, "respuesta" => $resultado['respuesta'], "proveedor" => "groq"];
            }
            error_log("IaMaquinaActividades - Groq falló: " . ($resultado['error'] ?? 'desconocido'));
        }

        return ["success" => false, "error" => "No hay proveedores de IA disponibles"];
    }

    private static function llamarGemini($api_key, $prompt)
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . $api_key;

            $body = json_encode([
                "contents" => [["role" => "user", "parts" => [["text" => $prompt]]]],
                "generationConfig" => ["temperature" => 0.7, "maxOutputTokens" => 32768]
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                return ["success" => false, "error" => "HTTP " . $http_code . " - " . substr($response, 0, 300)];
            }

            $data = json_decode($response, true);

            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return ["success" => true, "respuesta" => trim($data['candidates'][0]['content']['parts'][0]['text'])];
            }

            return ["success" => false, "error" => "Formato inesperado de Gemini"];
        } catch (Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    private static function llamarGroq($api_key, $prompt)
    {
        try {
            $url = "https://api.groq.com/openai/v1/chat/completions";

            $body = json_encode([
                "model" => "llama-3.3-70b-versatile",
                "messages" => [
                    ["role" => "system", "content" => "Eres un experto pedagógico en educación preescolar colombiana. Responde SOLO con JSON válido, sin markdown ni texto adicional."],
                    ["role" => "user", "content" => $prompt]
                ],
                "temperature" => 0.7,
                "max_tokens" => 8192
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $api_key]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                return ["success" => false, "error" => "HTTP " . $http_code];
            }

            $data = json_decode($response, true);

            if (isset($data['choices'][0]['message']['content'])) {
                return ["success" => true, "respuesta" => trim($data['choices'][0]['message']['content'])];
            }

            return ["success" => false, "error" => "Formato inesperado de Groq"];
        } catch (Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * Sugiere campos faltantes para una actividad individual.
     */
    public static function sugerirIndividual()
    {
        try {
            $db = Flight::db();
            $data = json_decode(Flight::request()->getBody(), true);

            $titulo = $data['titulo'] ?? '';
            $descripcion = $data['descripcion'] ?? '';
            $id_grupo = $data['id_grupo'] ?? null;
            $id_area = $data['id_area'] ?? null;
            $id_sprint = $data['id_sprint'] ?? null;
            $nombre_grupo = $data['nombre_grupo'] ?? 'el grupo';
            $nombre_area = $data['nombre_area'] ?? 'el área';
            $ambientes = $data['ambientes'] ?? [];
            $materiales = $data['materiales'] ?? [];
            $id_tipo_actividad = $data['id_tipo_actividad'] ?? null;
            $nivel_uno_existente = $data['nivel_uno'] ?? '';
            $nivel_dos_existente = $data['nivel_dos'] ?? '';

            if (!$titulo || !$id_grupo || !$id_area || !$id_sprint) {
                Flight::json(["error" => "titulo, id_grupo, id_area e id_sprint son requeridos"], 400);
                return;
            }

            $stmtGrados = $db->prepare("SELECT g.id, g.nombre FROM grados_x_grupo gxg INNER JOIN grados g ON gxg.id_grado = g.id WHERE gxg.id_grupo = :id_grupo");
            $stmtGrados->bindParam(':id_grupo', $id_grupo);
            $stmtGrados->execute();
            $grados = $stmtGrados->fetchAll(PDO::FETCH_ASSOC);
            $gradosIds = array_column($grados, 'id');
            $gradosTexto = implode(', ', array_column($grados, 'nombre'));

            $stmtSprint = $db->prepare("SELECT s.id_corte_academico FROM sprints s WHERE s.id = :id_sprint");
            $stmtSprint->bindParam(':id_sprint', $id_sprint);
            $stmtSprint->execute();
            $sprint = $stmtSprint->fetch(PDO::FETCH_ASSOC);
            $id_corte = $sprint['id_corte_academico'] ?? null;

            $placeholders = implode(',', array_fill(0, count($gradosIds), '?'));
            $sqlLogros = "SELECT l.id AS logro_id, l.nombre AS logro_nombre, il.id AS indicador_id, il.nombre AS indicador_nombre
                FROM logros l INNER JOIN indicadores_logros il ON l.id = il.id_logro
                WHERE l.id_grado IN ($placeholders) AND l.id_area_academica = ?" . ($id_corte ? " AND l.id_corte_academico = ?" : "") . " ORDER BY l.nombre";
            $stmtLogros = $db->prepare($sqlLogros);
            $paramIndex = 1;
            foreach ($gradosIds as $gid) { $stmtLogros->bindValue($paramIndex++, $gid); }
            $stmtLogros->bindValue($paramIndex++, $id_area);
            if ($id_corte) { $stmtLogros->bindValue($paramIndex++, $id_corte); }
            $stmtLogros->execute();
            $logrosIndicadores = $stmtLogros->fetchAll(PDO::FETCH_ASSOC);

            $indicadoresTexto = "";
            foreach ($logrosIndicadores as $li) {
                $indicadoresTexto .= "INDICADOR ID:{$li['indicador_id']} \"{$li['indicador_nombre']}\" (Logro: {$li['logro_nombre']})\n";
            }

            $ambientesTexto = '';
            if (!empty($ambientes)) {
                $ambientesItems = array_map(function($a) { return "ID:{$a['id']} \"{$a['nombre']}\""; }, $ambientes);
                $ambientesTexto = implode(', ', $ambientesItems);
            } else {
                $ambientesTexto = 'No especificado';
            }
            $materialesTexto = !empty($materiales) ? implode(', ', array_map(function($m) { return $m['nombre'] ?? $m['nombre_material'] ?? ''; }, $materiales)) : 'No especificados';

            $nombreTipo = '';
            if ($id_tipo_actividad) {
                $stmtTipo = $db->prepare("SELECT nombre FROM tipos_actividades_academicas WHERE id = :id");
                $stmtTipo->bindParam(':id', $id_tipo_actividad);
                $stmtTipo->execute();
                $tipo = $stmtTipo->fetch(PDO::FETCH_ASSOC);
                $nombreTipo = $tipo['nombre'] ?? '';
            }

            $stmtTipos = $db->prepare("SELECT id, nombre FROM tipos_actividades_academicas ORDER BY id");
            $stmtTipos->execute();
            $tiposDisponibles = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);
            $tiposTexto = implode(', ', array_map(function($t) { return "ID:{$t['id']} \"{$t['nombre']}\""; }, $tiposDisponibles));

            $prompt = <<<PROMPT
Eres un experto pedagógico en educación preescolar colombiana. Completa los campos faltantes para esta actividad del grupo "{$nombre_grupo}" (Grado: {$gradosTexto}) en el área "{$nombre_area}".

ACTIVIDAD:
- Título: "{$titulo}"
- Descripción: "{$descripcion}"
- Nivel Básico: "{$nivel_uno_existente}"
- Nivel Avanzado: "{$nivel_dos_existente}"
- Tipo: {$nombreTipo}
- Ambientes disponibles: {$ambientesTexto}
- Materiales disponibles: {$materialesTexto}
- Tipos de actividad disponibles: {$tiposTexto}

INDICADORES DE LOGRO DISPONIBLES:
{$indicadoresTexto}

INSTRUCCIONES:
- Solo genera contenido para los campos que estén vacíos ("").
- Si un campo ya tiene contenido, NO lo modifiques, devuélvelo exactamente igual.
- Responde ÚNICAMENTE con JSON válido (sin markdown, sin backticks):

{"descripcion":"solo si estaba vacío","nivel_uno":"solo si estaba vacío (máx 300 chars)","nivel_dos":"solo si estaba vacío (máx 300 chars)","materiales_sugeridos":["materiales de la lista disponible"],"indicadores_ids":[IDs de indicadores relevantes],"id_ambiente":ID del ambiente apropiado o null,"id_tipo_actividad_academica":ID del tipo apropiado,"minutos_duracion":45}

Usa materiales disponibles cuando sea posible. Selecciona indicadores usando sus IDs exactos. Para id_ambiente e id_tipo_actividad_academica usa los IDs exactos. Sé conciso.
PROMPT;

            $config = self::obtenerConfiguracion($db);
            $inicio_tiempo = microtime(true);
            $respuesta_ia = self::llamarIA($config, $prompt);
            $tiempo_ms = round((microtime(true) - $inicio_tiempo) * 1000);

            if (!$respuesta_ia['success']) {
                Flight::json(["error" => "Error al generar sugerencia: " . ($respuesta_ia['error'] ?? 'desconocido')], 500);
                return;
            }

            $textoRespuesta = $respuesta_ia['respuesta'];
            $textoRespuesta = preg_replace('/^```json\s*/s', '', $textoRespuesta);
            $textoRespuesta = preg_replace('/\s*```$/s', '', $textoRespuesta);
            $textoRespuesta = trim($textoRespuesta);

            $posInicio = strpos($textoRespuesta, '{');
            $posFin = strrpos($textoRespuesta, '}');
            if ($posInicio !== false && $posFin !== false) {
                $textoRespuesta = substr($textoRespuesta, $posInicio, $posFin - $posInicio + 1);
            }

            $sugerencia = json_decode($textoRespuesta, true);

            if (!$sugerencia || json_last_error() !== JSON_ERROR_NONE) {
                Flight::json(["error" => "Error al parsear sugerencia", "respuesta_cruda" => $textoRespuesta], 500);
                return;
            }

            $indicadoresEnriquecidos = [];
            $idsIndicadores = $sugerencia['indicadores_ids'] ?? [];
            foreach ($idsIndicadores as $idInd) {
                foreach ($logrosIndicadores as $li) {
                    if ((int)$li['indicador_id'] === (int)$idInd) {
                        $indicadoresEnriquecidos[] = [
                            'id' => (int)$li['indicador_id'],
                            'nombre' => $li['indicador_nombre'],
                            'logro_id' => (int)$li['logro_id'],
                            'logro_nombre' => $li['logro_nombre']
                        ];
                        break;
                    }
                }
            }
            $sugerencia['indicadores'] = $indicadoresEnriquecidos;

            Flight::json([
                "success" => true,
                "sugerencia" => $sugerencia,
                "proveedor" => $respuesta_ia['proveedor'],
                "tiempo_ms" => $tiempo_ms
            ]);

        } catch (Exception $e) {
            error_log("Error en IaMaquinaActividades::sugerirIndividual: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene los logros del corte para uno o varios grupos+áreas, agrupados con sus indicadores.
     */
    public static function obtenerLogrosEvaluacion()
    {
        try {
            $db = Flight::db();
            $data = json_decode(Flight::request()->getBody(), true);

            $id_grupo = $data['id_grupo'] ?? null;
            $id_corte = $data['id_corte'] ?? null;
            $id_area = $data['id_area'] ?? null;

            if (!$id_grupo || !$id_corte) {
                Flight::json(["error" => "id_grupo e id_corte son requeridos"], 400);
                return;
            }

            $stmtGrados = $db->prepare("SELECT id_grado FROM grados_x_grupo WHERE id_grupo = :id_grupo");
            $stmtGrados->bindParam(':id_grupo', $id_grupo);
            $stmtGrados->execute();
            $gradosIds = array_column($stmtGrados->fetchAll(PDO::FETCH_ASSOC), 'id_grado');

            if (empty($gradosIds)) {
                Flight::json(["success" => true, "logros" => []]);
                return;
            }

            $placeholders = implode(',', array_fill(0, count($gradosIds), '?'));
            $sql = "
                SELECT 
                    l.id AS logro_id,
                    l.nombre AS logro_nombre,
                    l.id_area_academica,
                    aa.nombre AS area_nombre,
                    ed.nombre AS esfera_nombre,
                    il.id AS indicador_id,
                    il.nombre AS indicador_nombre
                FROM logros l
                INNER JOIN indicadores_logros il ON l.id = il.id_logro
                LEFT JOIN areas_academicas aa ON l.id_area_academica = aa.id
                LEFT JOIN esferas_desarrollo ed ON l.id_esfera_desarrollo = ed.id
                WHERE l.id_grado IN ($placeholders)
                AND l.id_corte_academico = ?
                " . ($id_area ? "AND l.id_area_academica = ?" : "") . "
                ORDER BY aa.nombre, l.nombre, il.nombre
            ";

            $stmt = $db->prepare($sql);
            $idx = 1;
            foreach ($gradosIds as $gid) { $stmt->bindValue($idx++, $gid); }
            $stmt->bindValue($idx++, $id_corte);
            if ($id_area) { $stmt->bindValue($idx++, $id_area); }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $logrosAgrupados = [];
            foreach ($rows as $r) {
                $lid = $r['logro_id'];
                if (!isset($logrosAgrupados[$lid])) {
                    $logrosAgrupados[$lid] = [
                        'id' => (int)$lid,
                        'nombre' => $r['logro_nombre'],
                        'id_area_academica' => (int)$r['id_area_academica'],
                        'area_nombre' => $r['area_nombre'],
                        'esfera' => $r['esfera_nombre'],
                        'indicadores' => []
                    ];
                }
                $logrosAgrupados[$lid]['indicadores'][] = [
                    'id' => (int)$r['indicador_id'],
                    'nombre' => $r['indicador_nombre']
                ];
            }

            Flight::json([
                "success" => true,
                "logros" => array_values($logrosAgrupados)
            ]);

        } catch (Exception $e) {
            error_log("Error en IaMaquinaActividades::obtenerLogrosEvaluacion: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene las actividades ya creadas en un sprint, agrupadas por id_logro.
     * Útil para identificar qué logros ya tienen actividades y bloquearlos en la UI.
     * Retorna un mapa { id_logro: [ {id_actividad, titulo, descripcion}, ... ] }
     */
    public static function obtenerActividadesPorLogroEnSprint($id_sprint)
    {
        try {
            $db = Flight::db();

            if (!$id_sprint) {
                Flight::json(["error" => "id_sprint es requerido"], 400);
                return;
            }

            $sql = "
                SELECT DISTINCT
                    l.id AS id_logro,
                    aa.id AS id_actividad,
                    aa.titulo,
                    aa.descripcion
                FROM tareas_x_sprints txs
                INNER JOIN actividades_academicas aa ON txs.id_actividad_academica = aa.id
                INNER JOIN actividades_academicas_x_indicadores_logros aaxil ON aa.id = aaxil.id_actividad_academica
                INNER JOIN indicadores_logros il ON aaxil.id_indicador_logro = il.id
                INNER JOIN logros l ON il.id_logro = l.id
                WHERE txs.id_sprint = :id_sprint
                ORDER BY l.id, aa.id
            ";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id_sprint', $id_sprint);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar por id_logro
            $mapa = [];
            foreach ($rows as $r) {
                $idLogro = (int)$r['id_logro'];
                if (!isset($mapa[$idLogro])) {
                    $mapa[$idLogro] = [];
                }
                // Evitar duplicados de actividad dentro del mismo logro
                $idAct = (int)$r['id_actividad'];
                $yaExiste = false;
                foreach ($mapa[$idLogro] as $a) {
                    if ($a['id_actividad'] === $idAct) { $yaExiste = true; break; }
                }
                if (!$yaExiste) {
                    $mapa[$idLogro][] = [
                        'id_actividad' => $idAct,
                        'titulo' => $r['titulo'],
                        'descripcion' => $r['descripcion']
                    ];
                }
            }

            Flight::json([
                "success" => true,
                "actividades_por_logro" => $mapa
            ]);

        } catch (Exception $e) {
            error_log("Error en IaMaquinaActividades::obtenerActividadesPorLogroEnSprint: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Genera con IA una actividad de evaluación por cada logro recibido.
     * Una sola llamada al LLM con todos los logros.
     */
    public static function generarActividadesEvaluacion()
    {
        try {
            $db = Flight::db();
            $data = json_decode(Flight::request()->getBody(), true);

            $id_grupo = $data['id_grupo'] ?? null;
            $id_corte = $data['id_corte'] ?? null;
            $logros_ids = $data['logros_ids'] ?? [];
            $ambientes = $data['ambientes'] ?? [];
            $materiales = $data['materiales'] ?? [];
            $id_tipo_actividad = $data['id_tipo_actividad'] ?? null;
            $nombre_grupo = $data['nombre_grupo'] ?? 'el grupo';

            if (!$id_grupo || !$id_corte || empty($logros_ids)) {
                Flight::json(["error" => "id_grupo, id_corte y logros_ids son requeridos"], 400);
                return;
            }

            $stmtGrados = $db->prepare("SELECT g.id, g.nombre, g.descripcion FROM grados_x_grupo gxg INNER JOIN grados g ON gxg.id_grado = g.id WHERE gxg.id_grupo = :id_grupo");
            $stmtGrados->bindParam(':id_grupo', $id_grupo);
            $stmtGrados->execute();
            $grados = $stmtGrados->fetchAll(PDO::FETCH_ASSOC);
            // Texto enriquecido con la edad: "Jardín (4 a 5 años), Transición (5 a 6 años)"
            $gradosTexto = implode(', ', array_map(function($g) {
                return $g['descripcion'] ? "{$g['nombre']} ({$g['descripcion']})" : $g['nombre'];
            }, $grados));

            $placeholders = implode(',', array_fill(0, count($logros_ids), '?'));
            $sql = "
                SELECT 
                    l.id AS logro_id,
                    l.nombre AS logro_nombre,
                    l.id_area_academica,
                    aa.nombre AS area_nombre,
                    il.id AS indicador_id,
                    il.nombre AS indicador_nombre
                FROM logros l
                INNER JOIN indicadores_logros il ON l.id = il.id_logro
                LEFT JOIN areas_academicas aa ON l.id_area_academica = aa.id
                WHERE l.id IN ($placeholders)
                ORDER BY l.nombre, il.nombre
            ";
            $stmt = $db->prepare($sql);
            $idx = 1;
            foreach ($logros_ids as $lid) { $stmt->bindValue($idx++, $lid); }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                Flight::json(["error" => "No se encontraron logros con los IDs proporcionados"], 404);
                return;
            }

            $logrosAgrupados = [];
            foreach ($rows as $r) {
                $lid = $r['logro_id'];
                if (!isset($logrosAgrupados[$lid])) {
                    $logrosAgrupados[$lid] = [
                        'id' => (int)$lid,
                        'nombre' => $r['logro_nombre'],
                        'id_area_academica' => (int)$r['id_area_academica'],
                        'area_nombre' => $r['area_nombre'],
                        'indicadores' => []
                    ];
                }
                $logrosAgrupados[$lid]['indicadores'][] = [
                    'id' => (int)$r['indicador_id'],
                    'nombre' => $r['indicador_nombre']
                ];
            }
            $logrosAgrupados = array_values($logrosAgrupados);

            $stmtTipos = $db->prepare("SELECT id, nombre FROM tipos_actividades_academicas ORDER BY nombre");
            $stmtTipos->execute();
            $tiposActividad = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

            $logrosTexto = "";
            foreach ($logrosAgrupados as $logro) {
                $logrosTexto .= "\nLOGRO ID:{$logro['id']} \"{$logro['nombre']}\" (Área: {$logro['area_nombre']})\n";
                foreach ($logro['indicadores'] as $ind) {
                    $logrosTexto .= "  INDICADOR ID:{$ind['id']} \"{$ind['nombre']}\"\n";
                }
            }

            $ambientesTexto = !empty($ambientes) ? implode(', ', array_map(function($a) { return "ID:{$a['id']} \"{$a['nombre']}\""; }, $ambientes)) : 'No especificado';
            $materialesTexto = !empty($materiales) ? implode(', ', array_map(function($m) { return $m['nombre'] ?? $m['nombre_material'] ?? ''; }, $materiales)) : 'No especificados';
            $tiposTexto = implode(', ', array_map(function($t) { return "ID:{$t['id']} \"{$t['nombre']}\""; }, $tiposActividad));

            $cantidad = count($logrosAgrupados);

            $prompt = <<<PROMPT
Eres un experto pedagógico en educación preescolar colombiana. Genera EXACTAMENTE {$cantidad} actividades de EVALUACIÓN para el grupo "{$nombre_grupo}" (Grado: {$gradosTexto}).

⚠️ EDAD DE LOS NIÑOS: el grupo es de "{$gradosTexto}". TODAS las actividades deben ser estrictamente apropiadas para esta etapa del desarrollo:
- Vocabulario, instrucciones y consignas adaptadas al nivel cognitivo de la edad.
- Duración acorde a la capacidad de atención de la edad (a menor edad, menor duración).
- Habilidades motrices, cognitivas, sociales y emocionales realistas para la etapa.
- Materiales seguros y manipulables apropiados para la edad.
- Si la edad es menor a 3 años, evita actividades con lectoescritura, conceptos abstractos o instrucciones complejas.

OBJETIVO: Cada actividad debe permitir al docente EVALUAR si el niño ha alcanzado el logro correspondiente, con evidencias observables y criterios claros.

LOGROS A EVALUAR (genera UNA actividad por cada logro, en el mismo orden):
{$logrosTexto}

CONTEXTO:
- Ambientes disponibles: {$ambientesTexto}
- Materiales disponibles: {$materialesTexto}
- Tipos de actividad disponibles: {$tiposTexto}

REGLAS:
1. Genera exactamente {$cantidad} actividades, UNA por cada logro listado, en el mismo orden.
2. Cada actividad debe estar enfocada en EVALUAR la conducta esperada del logro, no en enseñarla.
3. Asocia a cada actividad TODOS los indicadores del logro correspondiente (usa los IDs exactos).
4. id_tipo_actividad_academica: usa los IDs exactos de los tipos disponibles.
5. id_ambiente: usa el ID exacto de un ambiente disponible o null.
6. nivel_uno: criterios observables de evaluación nivel básico. nivel_dos: criterios nivel avanzado.
7. Las actividades DEBEN ser apropiadas para la edad indicada y permitir observar evidencias concretas.
8. minutos_duracion debe ser realista para la edad (Sala cuna/Caminadores: 10-20 min, Párvulos: 20-30 min, Prejardín: 25-40 min, Jardín/Transición: 30-45 min).

Responde ÚNICAMENTE con JSON válido (sin markdown, sin backticks). Estructura:

[
  {
    "id_logro": 123,
    "titulo": "Evaluación: ...",
    "descripcion": "descripción detallada de la actividad de evaluación en HTML (máx 500 chars)",
    "nivel_uno": "criterios de evaluación nivel básico (máx 300 chars)",
    "nivel_dos": "criterios de evaluación nivel avanzado (máx 300 chars)",
    "minutos_duracion": 45,
    "id_tipo_actividad_academica": 1,
    "materiales_sugeridos": ["material 1", "material 2"],
    "id_ambiente": 1,
    "indicadores_ids": [1, 2, 3]
  }
]

Responde SOLO con el array JSON.
PROMPT;

            $config = self::obtenerConfiguracion($db);
            $inicio_tiempo = microtime(true);
            $respuesta_ia = self::llamarIA($config, $prompt);
            $tiempo_ms = round((microtime(true) - $inicio_tiempo) * 1000);

            if (!$respuesta_ia['success']) {
                Flight::json(["error" => "Error al generar actividades de evaluación: " . ($respuesta_ia['error'] ?? 'desconocido')], 500);
                return;
            }

            $textoRespuesta = $respuesta_ia['respuesta'];
            $textoRespuesta = preg_replace('/^```json\s*/s', '', $textoRespuesta);
            $textoRespuesta = preg_replace('/\s*```$/s', '', $textoRespuesta);
            $textoRespuesta = trim($textoRespuesta);

            $posInicio = strpos($textoRespuesta, '[');
            $posFin = strrpos($textoRespuesta, ']');
            if ($posInicio !== false && $posFin !== false) {
                $textoRespuesta = substr($textoRespuesta, $posInicio, $posFin - $posInicio + 1);
            }

            $actividades = json_decode($textoRespuesta, true);

            if (!$actividades || json_last_error() !== JSON_ERROR_NONE) {
                error_log("IaMaquinaActividades::generarActividadesEvaluacion - JSON parse error: " . json_last_error_msg());
                Flight::json([
                    "error" => "Error al parsear la respuesta de IA",
                    "respuesta_cruda" => $textoRespuesta,
                    "proveedor" => $respuesta_ia['proveedor'],
                    "tiempo_ms" => $tiempo_ms
                ], 500);
                return;
            }

            // Enriquecer cada actividad: vincular con su logro y forzar todos los indicadores
            $actividadesEnriquecidas = [];
            foreach ($actividades as $i => $act) {
                $logro = null;
                if (!empty($act['id_logro'])) {
                    foreach ($logrosAgrupados as $l) {
                        if ((int)$l['id'] === (int)$act['id_logro']) { $logro = $l; break; }
                    }
                }
                if (!$logro && isset($logrosAgrupados[$i])) {
                    $logro = $logrosAgrupados[$i];
                }
                if (!$logro) continue;

                $idsIndicadores = array_map(function($ind) { return (int)$ind['id']; }, $logro['indicadores']);
                $indicadoresEnriquecidos = array_map(function($ind) use ($logro) {
                    return [
                        'id' => (int)$ind['id'],
                        'nombre' => $ind['nombre'],
                        'logro_id' => (int)$logro['id'],
                        'logro_nombre' => $logro['nombre']
                    ];
                }, $logro['indicadores']);

                $actividadesEnriquecidas[] = [
                    'id_logro' => (int)$logro['id'],
                    'logro_nombre' => $logro['nombre'],
                    'id_area_academica' => (int)$logro['id_area_academica'],
                    'area_nombre' => $logro['area_nombre'],
                    'titulo' => $act['titulo'] ?? "Evaluación: {$logro['nombre']}",
                    'descripcion' => $act['descripcion'] ?? '',
                    'nivel_uno' => $act['nivel_uno'] ?? '',
                    'nivel_dos' => $act['nivel_dos'] ?? '',
                    'minutos_duracion' => $act['minutos_duracion'] ?? 45,
                    'id_tipo_actividad_academica' => !empty($act['id_tipo_actividad_academica']) ? (int)$act['id_tipo_actividad_academica'] : ($id_tipo_actividad ? (int)$id_tipo_actividad : null),
                    'materiales_sugeridos' => $act['materiales_sugeridos'] ?? [],
                    'id_ambiente' => !empty($act['id_ambiente']) ? (int)$act['id_ambiente'] : null,
                    'indicadores_ids' => $idsIndicadores,
                    'indicadores' => $indicadoresEnriquecidos
                ];
            }

            Flight::json([
                "success" => true,
                "actividades" => $actividadesEnriquecidas,
                "proveedor" => $respuesta_ia['proveedor'],
                "tiempo_ms" => $tiempo_ms
            ]);

        } catch (Exception $e) {
            error_log("Error en IaMaquinaActividades::generarActividadesEvaluacion: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Graba en lote las actividades de evaluación.
     * Soporta múltiples áreas. Asigna orden_ejecucion secuencial empezando desde MAX+1 del sprint.
     */
    public static function grabarActividadesEvaluacion()
    {
        $db = Flight::db();

        try {
            $data = json_decode(Flight::request()->getBody(), true);

            if (!$data || empty($data['actividades'])) {
                Flight::json(["error" => "Se requiere al menos una actividad"], 400);
                return;
            }

            $id_sprint = $data['id_sprint'] ?? null;
            $id_grupo = $data['id_grupo'] ?? null;

            if (!$id_sprint || !$id_grupo) {
                Flight::json(["error" => "id_sprint e id_grupo son requeridos"], 400);
                return;
            }

            $db->beginTransaction();

            // Obtener el orden_ejecucion máximo actual en el sprint para continuar la numeración
            $stmtMax = $db->prepare("SELECT COALESCE(MAX(orden_ejecucion), 0) AS max_orden FROM tareas_x_sprints WHERE id_sprint = :id_sprint");
            $stmtMax->bindParam(':id_sprint', $id_sprint);
            $stmtMax->execute();
            $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
            $ordenActual = (int)($rowMax['max_orden'] ?? 0);

            $resultados = [];

            foreach ($data['actividades'] as $act) {
                $idArea = !empty($act['id_area_academica']) ? (int)$act['id_area_academica'] : null;
                if (!$idArea) {
                    throw new Exception("Toda actividad debe traer id_area_academica");
                }

                $materialesTexto = '';
                if (!empty($act['materiales'])) {
                    $nombres = array_map(function($m) {
                        return $m['nombre_material'] ?? $m['nombre'] ?? '';
                    }, $act['materiales']);
                    $materialesTexto = implode(', ', array_filter($nombres));
                }

                $idTipo = !empty($act['id_tipo_actividad_academica']) ? (int)$act['id_tipo_actividad_academica'] : 1;
                $idAmbiente = !empty($act['id_ambiente']) ? (int)$act['id_ambiente'] : null;

                // 1. Insertar actividad académica
                $stmtActividad = $db->prepare("
                    INSERT INTO actividades_academicas 
                    (id_tipo_actividad_academica, titulo, descripcion, nivel_uno, nivel_dos, minutos_duracion, materiales, id_ambiente)
                    VALUES (:id_tipo, :titulo, :descripcion, :nivel_uno, :nivel_dos, :duracion, :materiales, :id_ambiente)
                ");
                $stmtActividad->bindValue(':id_tipo', $idTipo);
                $stmtActividad->bindValue(':titulo', $act['titulo']);
                $stmtActividad->bindValue(':descripcion', $act['descripcion'] ?? '');
                $stmtActividad->bindValue(':nivel_uno', $act['nivel_uno'] ?? '');
                $stmtActividad->bindValue(':nivel_dos', $act['nivel_dos'] ?? '');
                $stmtActividad->bindValue(':duracion', $act['minutos_duracion'] ?? 45);
                $stmtActividad->bindValue(':materiales', $materialesTexto);
                $stmtActividad->bindValue(':id_ambiente', $idAmbiente);
                $stmtActividad->execute();
                $idActividad = $db->lastInsertId();

                // 2. Materiales x actividad
                if (!empty($act['materiales'])) {
                    $stmtMaterial = $db->prepare("
                        INSERT INTO materiales_x_actividad (id_actividad_academica, id_producto, nombre_material, cantidad)
                        VALUES (:id_actividad, :id_producto, :nombre_material, :cantidad)
                    ");
                    foreach ($act['materiales'] as $mat) {
                        $stmtMaterial->bindValue(':id_actividad', $idActividad);
                        $stmtMaterial->bindValue(':id_producto', $mat['id_producto'] ?? null);
                        $stmtMaterial->bindValue(':nombre_material', $mat['nombre_material'] ?? $mat['nombre'] ?? '');
                        $stmtMaterial->bindValue(':cantidad', $mat['cantidad'] ?? 1);
                        $stmtMaterial->execute();
                    }
                }

                // 3. Indicadores de logro
                if (!empty($act['indicadores_ids'])) {
                    $stmtIndicador = $db->prepare("
                        INSERT INTO actividades_academicas_x_indicadores_logros (id_actividad_academica, id_indicador_logro)
                        VALUES (:id_actividad, :id_indicador)
                    ");
                    $stmtCheck = $db->prepare("SELECT COUNT(*) as count FROM actividades_academicas_x_indicadores_logros WHERE id_actividad_academica = :id_act AND id_indicador_logro = :id_ind");
                    foreach ($act['indicadores_ids'] as $idIndicador) {
                        $stmtCheck->bindValue(':id_act', $idActividad);
                        $stmtCheck->bindValue(':id_ind', $idIndicador);
                        $stmtCheck->execute();
                        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                        if ($existe['count'] == 0) {
                            $stmtIndicador->bindValue(':id_actividad', $idActividad);
                            $stmtIndicador->bindValue(':id_indicador', $idIndicador);
                            $stmtIndicador->execute();
                        }
                    }
                }

                // 4. Tarea en el sprint con orden_ejecucion incremental
                $ordenActual++;
                $stmtTarea = $db->prepare("
                    INSERT INTO tareas_x_sprints 
                    (id_sprint, id_actividad_academica, id_grupo, id_area_academica, id_estado_tarea, es_tarea_adicional, orden_ejecucion, fecha_registro)
                    VALUES (:id_sprint, :id_actividad, :id_grupo, :id_area, 1, 0, :orden, NOW())
                ");
                $stmtTarea->bindValue(':id_sprint', $id_sprint);
                $stmtTarea->bindValue(':id_actividad', $idActividad);
                $stmtTarea->bindValue(':id_grupo', $id_grupo);
                $stmtTarea->bindValue(':id_area', $idArea);
                $stmtTarea->bindValue(':orden', $ordenActual);
                $stmtTarea->execute();

                $resultados[] = [
                    'id_actividad' => $idActividad,
                    'titulo' => $act['titulo'],
                    'id_area_academica' => $idArea,
                    'orden_ejecucion' => $ordenActual,
                    'id_tarea_sprint' => $db->lastInsertId()
                ];
            }

            $db->commit();

            Flight::json([
                "success" => true,
                "total_creadas" => count($resultados),
                "actividades" => $resultados
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en IaMaquinaActividades::grabarActividadesEvaluacion: " . $e->getMessage());
            Flight::json(["error" => "Error al grabar actividades de evaluación: " . $e->getMessage()], 500);
        }
    }
}