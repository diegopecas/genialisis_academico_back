<?php
class IaCoberturaCurricular
{
    public static function analizarCobertura()
    {
        try {
            $db = Flight::db();
            $data = json_decode(Flight::request()->getBody(), true);

            if (!$data || !isset($data['resumen']) || !isset($data['logros'])) {
                Flight::json(["error" => "Datos del análisis son requeridos"], 400);
                return;
            }

            $config = self::obtenerConfiguracion($db);
            $id_grupo = $data['id_grupo'] ?? null;
            $id_corte = $data['id_corte'] ?? null;
            $id_area = $data['id_area'] ?? null;
            $nombre_grupo = $data['nombre_grupo'] ?? 'el grupo';
            $nombre_corte = $data['nombre_corte'] ?? 'el corte';

            // Si viene un área específica, analizar solo esa (comportamiento original)
            if ($id_area) {
                $resultado = self::analizarArea($config, $data, $nombre_grupo, $nombre_corte);

                // Si parseó bien y tenemos ids, guardar en BD
                if ($resultado['analisis'] && $id_grupo && $id_corte) {
                    $jsonStr = json_encode($resultado['analisis'], JSON_UNESCAPED_UNICODE);
                    self::guardarEnBD($db, $id_grupo, $id_corte, $id_area, null, $jsonStr, $resultado['proveedor'], $resultado['tiempo_ms']);
                }

                Flight::json($resultado);
                return;
            }

            // Sin área: analizar por cada área que tenga logros
            $porArea = $data['por_area'] ?? [];
            $logros = $data['logros'] ?? [];
            $materiales = $data['materiales_consolidados'] ?? [];
            $resultados = [];

            // Consultar qué áreas ya tienen análisis en BD
            $areasConAnalisis = [];
            if ($id_grupo && $id_corte) {
                $stmtExistentes = $db->prepare("SELECT id_area, analisis_json, proveedor, tiempo_ms, fecha_actualizacion FROM ia_analisis_cobertura_curricular WHERE id_grupo = :g AND id_corte = :c AND id_area IS NOT NULL");
                $stmtExistentes->bindValue(':g', $id_grupo);
                $stmtExistentes->bindValue(':c', $id_corte);
                $stmtExistentes->execute();
                $existentes = $stmtExistentes->fetchAll(PDO::FETCH_ASSOC);
                foreach ($existentes as $e) {
                    $areasConAnalisis[(int)$e['id_area']] = $e;
                }
            }

            foreach ($porArea as $area) {
                $idAreaActual = (int)$area['id_area'];
                $nombreArea = $area['nombre'];

                // Si ya tiene análisis en BD, usarlo
                if (isset($areasConAnalisis[$idAreaActual])) {
                    $guardado = $areasConAnalisis[$idAreaActual];
                    $analisis = json_decode($guardado['analisis_json'], true);
                    $resultados[] = [
                        "id_area" => $idAreaActual,
                        "nombre_area" => $nombreArea,
                        "analisis" => $analisis,
                        "proveedor" => $guardado['proveedor'],
                        "tiempo_ms" => (int)$guardado['tiempo_ms'],
                        "fecha" => $guardado['fecha_actualizacion'],
                        "desde_bd" => true
                    ];
                    continue;
                }

                // Filtrar logros de esta área
                $logrosArea = array_filter($logros, function($l) use ($idAreaActual) {
                    return (int)$l['id_area'] === $idAreaActual;
                });
                $logrosArea = array_values($logrosArea);

                if (empty($logrosArea)) continue;

                // Filtrar materiales de esta área
                $materialesArea = [];
                foreach ($logrosArea as $logro) {
                    if (!empty($logro['actividades'])) {
                        foreach ($logro['actividades'] as $act) {
                            if (!empty($act['materiales'])) {
                                $mats = preg_split('/[,;\/\-\n]+/', $act['materiales']);
                                foreach ($mats as $mat) {
                                    $mat = trim(mb_strtolower($mat));
                                    if (strlen($mat) > 1) {
                                        if (!isset($materialesArea[$mat])) $materialesArea[$mat] = 0;
                                        $materialesArea[$mat]++;
                                    }
                                }
                            }
                        }
                    }
                }
                arsort($materialesArea);
                $materialesAreaArr = [];
                foreach (array_slice($materialesArea, 0, 15, true) as $nom => $freq) {
                    $materialesAreaArr[] = ["nombre" => $nom, "frecuencia" => $freq];
                }

                // Construir resumen del área
                $totalLogrosArea = count($logrosArea);
                $cubiertosArea = count(array_filter($logrosArea, function($l) { return $l['cubierto']; }));
                $totalActArea = 0;
                foreach ($logrosArea as $l) { $totalActArea += (int)$l['cantidad_actividades']; }
                $pctArea = $totalLogrosArea > 0 ? round(($cubiertosArea / $totalLogrosArea) * 100) : 0;

                $resumenArea = [
                    'total_logros' => $totalLogrosArea,
                    'logros_cubiertos' => $cubiertosArea,
                    'logros_sin_cobertura' => $totalLogrosArea - $cubiertosArea,
                    'porcentaje_cobertura' => $pctArea,
                    'total_actividades' => $totalActArea
                ];

                $dataArea = [
                    'nombre_grupo' => $nombre_grupo,
                    'nombre_corte' => $nombre_corte,
                    'resumen' => $resumenArea,
                    'por_area' => [["id_area" => $idAreaActual, "nombre" => $nombreArea, "total_logros" => $totalLogrosArea, "cubiertos" => $cubiertosArea, "porcentaje" => $pctArea]],
                    'logros' => $logrosArea,
                    'materiales_consolidados' => $materialesAreaArr
                ];

                $resultado = self::analizarArea($config, $dataArea, $nombre_grupo, $nombre_corte . " - " . $nombreArea);

                if ($resultado['analisis'] && $id_grupo && $id_corte) {
                    $jsonStr = json_encode($resultado['analisis'], JSON_UNESCAPED_UNICODE);
                    self::guardarEnBD($db, $id_grupo, $id_corte, $idAreaActual, null, $jsonStr, $resultado['proveedor'], $resultado['tiempo_ms']);
                }

                $resultados[] = [
                    "id_area" => $idAreaActual,
                    "nombre_area" => $nombreArea,
                    "analisis" => $resultado['analisis'] ?? null,
                    "analisis_texto" => $resultado['analisis_texto'] ?? null,
                    "proveedor" => $resultado['proveedor'] ?? null,
                    "tiempo_ms" => $resultado['tiempo_ms'] ?? null,
                    "desde_bd" => false
                ];
            }

            Flight::json(["success" => true, "resultados" => $resultados]);
        } catch (Exception $e) {
            error_log("Error en IaCoberturaCurricular::analizarCobertura: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    private static function analizarArea($config, $data, $nombre_grupo, $nombre_corte)
    {
        $resumen = $data['resumen'];
        $materiales = $data['materiales_consolidados'] ?? [];
        $logros = $data['logros'] ?? [];
        $porArea = $data['por_area'] ?? [];

        // Extraer grados únicos con descripciones
        $gradosUnicos = [];
        foreach ($logros as $logro) {
            $grado = $logro['nombre_grado'] ?? '';
            if ($grado && !isset($gradosUnicos[$grado])) {
                $gradosUnicos[$grado] = $grado;
            }
        }
        $gradosTexto = implode(', ', array_values($gradosUnicos));

        $areasTexto = "";
        foreach ($porArea as $area) {
            $areasTexto .= "- {$area['nombre']}: {$area['cubiertos']}/{$area['total_logros']} ({$area['porcentaje']}%)\n";
        }

        $logrosYActividades = "";
        $actividadesUnicas = [];

        foreach ($logros as $logro) {
            $estado = $logro['cubierto'] ? "CUBIERTO" : "SIN COBERTURA";
            $grado = $logro['nombre_grado'] ?? 'N/A';
            $logrosYActividades .= "\nLOGRO: \"{$logro['nombre']}\" | Área: {$logro['nombre_area']} | Esfera: {$logro['nombre_esfera']} | Grado: {$grado} | Estado: {$estado}\n";

            if (!empty($logro['actividades'])) {
                foreach ($logro['actividades'] as $act) {
                    $desc = !empty($act['descripcion']) ? mb_substr(strip_tags($act['descripcion']), 0, 120) : '';
                    $logrosYActividades .= "  ACT: \"{$act['titulo']}\" | {$act['minutos_duracion']}min | Materiales: {$act['materiales']} | Descripción: {$desc} | Estado: {$act['estado']}\n";

                    if (!isset($actividadesUnicas[$act['id']])) {
                        $actividadesUnicas[$act['id']] = [
                            'titulo' => $act['titulo'],
                            'materiales' => $act['materiales'],
                            'minutos' => $act['minutos_duracion'],
                            'descripcion' => $desc,
                            'logros_asignados' => []
                        ];
                    }
                    $actividadesUnicas[$act['id']]['logros_asignados'][] = $logro['nombre'];
                }
            }
        }

        $actividadesParaAnalisis = "";
        foreach ($actividadesUnicas as $id => $act) {
            $logrosAsig = implode(' | ', array_unique($act['logros_asignados']));
            $actividadesParaAnalisis .= "\nID:{$id} \"{$act['titulo']}\" | {$act['minutos']}min | Materiales: {$act['materiales']} | Descripción: {$act['descripcion']}\n";
            $actividadesParaAnalisis .= "  Logros asignados: {$logrosAsig}\n";
        }

        $materialesTop = "";
        foreach (array_slice($materiales, 0, 20) as $mat) {
            $materialesTop .= "{$mat['nombre']} ({$mat['frecuencia']}), ";
        }

        $prompt = <<<PROMPT
Eres un experto pedagógico en educación preescolar colombiana. Analiza la cobertura curricular del grupo "{$nombre_grupo}" (Grado: {$gradosTexto}) en "{$nombre_corte}".

IMPORTANTE: Evalúa cada actividad considerando que son niños de grado {$gradosTexto}. Las actividades deben ser apropiadas, divertidas, originales y estimulantes para esa edad.

RESUMEN: {$resumen['logros_cubiertos']}/{$resumen['total_logros']} logros cubiertos ({$resumen['porcentaje_cobertura']}%), {$resumen['total_actividades']} actividades programadas.

COBERTURA POR ÁREA:
{$areasTexto}
LOGROS Y ACTIVIDADES:
{$logrosYActividades}
ACTIVIDADES ÚNICAS PARA ANÁLISIS INDIVIDUAL:
{$actividadesParaAnalisis}
MATERIALES MÁS USADOS: {$materialesTop}

Responde ÚNICAMENTE con JSON válido (sin markdown, sin backticks, sin texto antes o después). Estructura:

{"resumen_ejecutivo":"máximo 3 frases sobre el estado general","fortalezas":["máximo 4 items"],"brechas_criticas":["máximo 4 items, solo si hay logros sin cobertura"],"analisis_actividades":[{"titulo":"nombre actividad","entorno":"aire_libre o aula","modalidad":"grupal o individual","tipo":"sensorial/musical/motriz/cognitiva/lingüística/socioafectiva","bien_asignada":true,"observacion_asignacion":"breve","nivel_diversion":"alto/medio/bajo","nivel_originalidad":"alto/medio/bajo","nivel_estimulacion":"alto/medio/bajo","observacion_ludica":"1 frase evaluando qué tan divertida, original y estimulante es para la edad","logros_adicionales_sugeridos":["si aplica"]}],"analisis_materiales":{"total_aire_libre":0,"total_aula":0,"clasificacion":[{"categoria":"nombre","ejemplos":["max 3"]}],"observacion":"1 frase"},"coherencia_pedagogica":{"bien_asignadas":"observación general","revision_sugerida":"observación general","oportunidades":["max 3 sugerencias de logros que podrían cubrirse con actividades existentes"]},"recomendaciones":["máximo 5 recomendaciones concretas"],"puntaje_general":0}

El puntaje va de 0 a 100. En analisis_actividades incluye TODAS las actividades únicas. nivel_diversion evalúa qué tan divertida es la actividad para la edad. nivel_originalidad evalúa qué tan creativa e innovadora es. nivel_estimulacion evalúa qué tanto despierta curiosidad y participación activa. Sé conciso en cada campo.
PROMPT;

        $inicio_tiempo = microtime(true);
        $respuesta_ia = self::llamarIA($config, $prompt);
        $tiempo_ms = round((microtime(true) - $inicio_tiempo) * 1000);

        if ($respuesta_ia['success']) {
            $textoRespuesta = $respuesta_ia['respuesta'];
            $textoRespuesta = preg_replace('/^```json\s*/s', '', $textoRespuesta);
            $textoRespuesta = preg_replace('/\s*```$/s', '', $textoRespuesta);
            $textoRespuesta = trim($textoRespuesta);

            $posInicio = strpos($textoRespuesta, '{');
            $posFin = strrpos($textoRespuesta, '}');
            if ($posInicio !== false && $posFin !== false) {
                $textoRespuesta = substr($textoRespuesta, $posInicio, $posFin - $posInicio + 1);
            }

            $analisisJson = json_decode($textoRespuesta, true);

            if ($analisisJson && json_last_error() === JSON_ERROR_NONE) {
                return ["success" => true, "analisis" => $analisisJson, "proveedor" => $respuesta_ia['proveedor'], "tiempo_ms" => $tiempo_ms];
            } else {
                error_log("IaCoberturaCurricular - JSON parse error: " . json_last_error_msg());
                return ["success" => true, "analisis" => null, "analisis_texto" => $textoRespuesta, "proveedor" => $respuesta_ia['proveedor'], "tiempo_ms" => $tiempo_ms];
            }
        }

        return ["success" => false, "analisis" => null, "proveedor" => null, "tiempo_ms" => 0, "error" => $respuesta_ia['error'] ?? 'Error desconocido'];
    }

    private static function guardarEnBD($db, $id_grupo, $id_corte, $id_area, $id_esfera, $analisis_json, $proveedor, $tiempo_ms)
    {
        try {
            $sql = "SELECT id FROM ia_analisis_cobertura_curricular WHERE id_grupo = :g AND id_corte = :c";
            $p = [':g' => $id_grupo, ':c' => $id_corte];
            if ($id_area) { $sql .= " AND id_area = :a"; $p[':a'] = $id_area; } else { $sql .= " AND id_area IS NULL"; }
            if ($id_esfera) { $sql .= " AND id_esfera = :e"; $p[':e'] = $id_esfera; } else { $sql .= " AND id_esfera IS NULL"; }

            $stmt = $db->prepare($sql);
            foreach ($p as $k => $v) { $stmt->bindValue($k, $v); }
            $stmt->execute();
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existente) {
                $u = $db->prepare("UPDATE ia_analisis_cobertura_curricular SET analisis_json = :j, proveedor = :p, tiempo_ms = :t WHERE id = :id");
                $u->bindValue(':j', $analisis_json);
                $u->bindValue(':p', $proveedor);
                $u->bindValue(':t', $tiempo_ms);
                $u->bindValue(':id', $existente['id']);
                $u->execute();
            } else {
                $i = $db->prepare("INSERT INTO ia_analisis_cobertura_curricular (id_grupo, id_corte, id_area, id_esfera, analisis_json, proveedor, tiempo_ms) VALUES (:g, :c, :a, :e, :j, :p, :t)");
                $i->bindValue(':g', $id_grupo);
                $i->bindValue(':c', $id_corte);
                $i->bindValue(':a', $id_area, $id_area ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $i->bindValue(':e', $id_esfera, $id_esfera ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $i->bindValue(':j', $analisis_json);
                $i->bindValue(':p', $proveedor);
                $i->bindValue(':t', $tiempo_ms);
                $i->execute();
            }
        } catch (Exception $e) {
            error_log("Error guardando análisis IA: " . $e->getMessage());
        }
    }

    public static function obtenerAnalisisGuardado()
    {
        try {
            $db = Flight::db();
            $id_grupo = $_GET['id_grupo'] ?? null;
            $id_corte = $_GET['id_corte'] ?? null;
            $id_area = $_GET['id_area'] ?? null;

            if (!$id_grupo || !$id_corte) {
                Flight::json(["error" => "id_grupo e id_corte son requeridos"], 400);
                return;
            }

            // Si viene área, devolver solo esa
            if ($id_area) {
                $sql = "SELECT id, id_area, analisis_json, proveedor, tiempo_ms, fecha_actualizacion FROM ia_analisis_cobertura_curricular WHERE id_grupo = :g AND id_corte = :c AND id_area = :a LIMIT 1";
                $stmt = $db->prepare($sql);
                $stmt->bindValue(':g', $id_grupo);
                $stmt->bindValue(':c', $id_corte);
                $stmt->bindValue(':a', $id_area);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $analisis = json_decode($row['analisis_json'], true);
                    Flight::json(["success" => true, "existe" => true, "resultados" => [[
                        "id_area" => (int)$row['id_area'],
                        "analisis" => $analisis,
                        "proveedor" => $row['proveedor'],
                        "tiempo_ms" => (int)$row['tiempo_ms'],
                        "fecha" => $row['fecha_actualizacion'],
                        "desde_bd" => true
                    ]]]);
                } else {
                    Flight::json(["success" => true, "existe" => false, "resultados" => []]);
                }
                return;
            }

            // Sin área: devolver todos los análisis por área
            $sql = "SELECT id, id_area, analisis_json, proveedor, tiempo_ms, fecha_actualizacion FROM ia_analisis_cobertura_curricular WHERE id_grupo = :g AND id_corte = :c AND id_area IS NOT NULL ORDER BY id_area";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':g', $id_grupo);
            $stmt->bindValue(':c', $id_corte);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $resultados = [];
            foreach ($rows as $row) {
                $analisis = json_decode($row['analisis_json'], true);
                $resultados[] = [
                    "id_area" => (int)$row['id_area'],
                    "analisis" => $analisis,
                    "proveedor" => $row['proveedor'],
                    "tiempo_ms" => (int)$row['tiempo_ms'],
                    "fecha" => $row['fecha_actualizacion'],
                    "desde_bd" => true
                ];
            }

            Flight::json(["success" => true, "existe" => count($resultados) > 0, "resultados" => $resultados]);
        } catch (Exception $e) {
            error_log("Error en IaCoberturaCurricular::obtenerAnalisisGuardado: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    public static function guardarAnalisis()
    {
        try {
            $db = Flight::db();
            $data = json_decode(Flight::request()->getBody(), true);

            if (!$data || !isset($data['id_grupo']) || !isset($data['id_corte']) || !isset($data['analisis_json'])) {
                Flight::json(["error" => "id_grupo, id_corte y analisis_json son requeridos"], 400);
                return;
            }

            $analisis_json = is_string($data['analisis_json']) ? $data['analisis_json'] : json_encode($data['analisis_json'], JSON_UNESCAPED_UNICODE);
            self::guardarEnBD($db, $data['id_grupo'], $data['id_corte'], $data['id_area'] ?? null, $data['id_esfera'] ?? null, $analisis_json, $data['proveedor'] ?? null, $data['tiempo_ms'] ?? null);

            Flight::json(["success" => true, "mensaje" => "Análisis guardado"]);
        } catch (Exception $e) {
            error_log("Error en IaCoberturaCurricular::guardarAnalisis: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
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
            error_log("IaCoberturaCurricular - Gemini falló: " . ($resultado['error'] ?? 'desconocido'));
        }

        $groq_key = $config['groq_api_key'] ?? null;
        if ($groq_key) {
            $resultado = self::llamarGroq($groq_key, $prompt);
            if ($resultado['success']) {
                return ["success" => true, "respuesta" => $resultado['respuesta'], "proveedor" => "groq"];
            }
            error_log("IaCoberturaCurricular - Groq falló: " . ($resultado['error'] ?? 'desconocido'));
        }

        return ["success" => false, "error" => "No hay proveedores de IA disponibles"];
    }

    private static function llamarGemini($api_key, $prompt)
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . $api_key;

            $body = json_encode([
                "contents" => [["role" => "user", "parts" => [["text" => $prompt]]]],
                "generationConfig" => ["temperature" => 0.3, "maxOutputTokens" => 32768]
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
                "temperature" => 0.3,
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
}