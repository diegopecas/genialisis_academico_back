<?php
class IaMejorarTexto
{
    // Modelos por defecto cuando el tenant no tiene ia_mejorar_texto_cadena. Con
    // la cadena configurada, el modelo de cada paso sale de ella.
    const MODELO_GEMINI = 'gemini-2.5-flash';
    const MODELO_GROQ = 'llama-3.3-70b-versatile';

    /**
     * Mejora la redacción de un texto con IA aplicando estilo técnico docente.
     * Body: { texto: string, contexto?: string }
     * Respuesta: { success, texto_mejorado, proveedor, tiempo_ms }
     */
    public static function mejorar()
    {
        try {
            $db = Flight::db();
            $data = json_decode(Flight::request()->getBody(), true);

            $texto = trim($data['texto'] ?? '');
            $contexto = trim($data['contexto'] ?? '');

            if ($texto === '') {
                Flight::json(["error" => "El campo texto es requerido"], 400);
                return;
            }

            $bloqueContexto = $contexto !== ''
                ? "CONTEXTO ADICIONAL (úsalo solo como referencia, no lo incluyas en la respuesta):\n\"{$contexto}\"\n\n"
                : "";

            $prompt = <<<PROMPT
Eres un redactor experto en lenguaje técnico docente para instituciones educativas de preescolar y primaria en Colombia.

{$bloqueContexto}TEXTO ORIGINAL A MEJORAR:
"{$texto}"

REGLAS:
1. Mantén TODOS los hechos del texto original. No inventes información ni agregues detalles que no estén.
2. Mejora la redacción, ortografía, puntuación y claridad.
3. Usa lenguaje objetivo, profesional y técnico docente.
4. Evita juicios de valor injustificados y términos que generen incomodidad al lector (por ejemplo: "agresión", "ataque", "violento", "tonto", "malo", "pésimo", "fatal"). Reemplázalos por equivalentes profesionales como "comportamiento disruptivo", "conducta inadecuada", "que requiere apoyo", "que necesita acompañamiento".
5. Mantén aproximadamente la misma longitud del texto original.
6. NO uses formato markdown (sin asteriscos, sin negritas, sin encabezados, sin viñetas).
7. NO incluyas prefijos como "Observación:", "Texto mejorado:", ni comentarios adicionales.
8. Responde ÚNICAMENTE con el texto mejorado en texto plano.
PROMPT;

            $config = self::obtenerConfiguracion($db);
            $inicio_tiempo = microtime(true);
            $respuesta_ia = self::llamarIA($config, $prompt);
            $tiempo_ms = round((microtime(true) - $inicio_tiempo) * 1000);

            if (!$respuesta_ia['success']) {
                Flight::json([
                    "error" => "Error al mejorar el texto: " . ($respuesta_ia['error'] ?? 'desconocido')
                ], 500);
                return;
            }

            $textoMejorado = trim($respuesta_ia['respuesta']);
            $textoMejorado = preg_replace('/^```(?:\w+)?\s*/s', '', $textoMejorado);
            $textoMejorado = preg_replace('/\s*```$/s', '', $textoMejorado);
            $textoMejorado = trim($textoMejorado, " \t\n\r\0\x0B\"'");

            if ($textoMejorado === '') {
                Flight::json([
                    "error" => "La IA devolvió una respuesta vacía",
                    "proveedor" => $respuesta_ia['proveedor'],
                    "tiempo_ms" => $tiempo_ms
                ], 500);
                return;
            }

            Flight::json([
                "success" => true,
                "texto_mejorado" => $textoMejorado,
                "proveedor" => $respuesta_ia['proveedor'],
                "tiempo_ms" => $tiempo_ms
            ]);

        } catch (Exception $e) {
            error_log("Error en IaMejorarTexto::mejorar: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    private static function obtenerConfiguracion($db)
    {
        $sentence = $db->prepare("SELECT clave, valor FROM ia_configuracion WHERE id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $rows = $sentence->fetchAll(PDO::FETCH_ASSOC);
        $config = [];
        foreach ($rows as $row) {
            $config[$row['clave']] = $row['valor'];
        }
        return $config;
    }

    /**
     * Recorre la cadena de proveedores ia_mejorar_texto_cadena ("proveedor|modelo;...") hasta
     * que uno responda. Proveedores: claude, gemini y groq. Si el tenant no tiene
     * la clave, se conserva el comportamiento anterior: Gemini y luego Groq con
     * los modelos de las constantes.
     */
    private static function llamarIA($config, $prompt)
    {
        $cadenaRaw = isset($config['ia_mejorar_texto_cadena']) ? trim($config['ia_mejorar_texto_cadena']) : '';
        $pasos = self::parsearCadena($cadenaRaw);
        if (empty($pasos)) {
            $pasos = [
                ['proveedor' => 'gemini', 'modelo' => self::MODELO_GEMINI],
                ['proveedor' => 'groq', 'modelo' => self::MODELO_GROQ]
            ];
        }

        // Todos los proveedores que se prueben quedan en un solo registro de consumo
        $intentos = [];

        foreach ($pasos as $paso) {
            $proveedor = $paso['proveedor'];
            $modelo = $paso['modelo'];
            $resultado = null;
            $inicio = microtime(true);

            if ($proveedor === 'claude') {
                $key = $config['claude_api_key'] ?? null;
                if (!$key) {
                    error_log("IaMejorarTexto - se salta 'claude': falta claude_api_key");
                    continue;
                }
                $resultado = IaClaude::llamarTexto(
                    $key,
                    $modelo,
                    "Eres un redactor experto en lenguaje técnico docente. Responde SOLO con el texto mejorado en texto plano, sin markdown ni comentarios adicionales.",
                    [["role" => "user", "content" => $prompt]],
                    2048,
                    60
                );
            } elseif ($proveedor === 'gemini') {
                $key = $config['gemini_api_key'] ?? null;
                if (!$key) {
                    error_log("IaMejorarTexto - se salta 'gemini': falta gemini_api_key");
                    continue;
                }
                $resultado = self::llamarGemini($key, $modelo, $prompt);
            } elseif ($proveedor === 'groq') {
                $key = $config['groq_api_key'] ?? null;
                if (!$key) {
                    error_log("IaMejorarTexto - se salta 'groq': falta groq_api_key");
                    continue;
                }
                $resultado = self::llamarGroq($key, $modelo, $prompt);
            } else {
                error_log("IaMejorarTexto - proveedor no soportado en la cadena: {$proveedor}");
                continue;
            }

            $intentos[] = IaConsumos::intento($proveedor, $modelo, $resultado, $inicio);

            if (!empty($resultado['success'])) {
                IaConsumos::registrar('mejorar_texto', 'mejorar', $intentos);
                return ["success" => true, "respuesta" => $resultado['respuesta'], "proveedor" => $proveedor];
            }
            error_log("IaMejorarTexto - {$proveedor} ({$modelo}) falló: " . ($resultado['error'] ?? 'desconocido'));
        }

        IaConsumos::registrar('mejorar_texto', 'mejorar', $intentos);
        return ["success" => false, "error" => "No hay proveedores de IA disponibles"];
    }

    /**
     * Parsea "proveedor|modelo;proveedor|modelo" a una lista ordenada de pasos.
     * Ignora trozos vacíos o mal formados.
     */
    private static function parsearCadena($cadena)
    {
        $pasos = [];
        if (trim($cadena) === '') {
            return $pasos;
        }
        foreach (explode(';', $cadena) as $trozo) {
            $trozo = trim($trozo);
            if ($trozo === '') {
                continue;
            }
            $partes = explode('|', $trozo, 2);
            if (count($partes) !== 2) {
                continue;
            }
            $proveedor = strtolower(trim($partes[0]));
            $modelo = trim($partes[1]);
            if ($proveedor === '' || $modelo === '') {
                continue;
            }
            $pasos[] = ['proveedor' => $proveedor, 'modelo' => $modelo];
        }
        return $pasos;
    }

    private static function llamarGemini($api_key, $modelo, $prompt)
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1/models/" . $modelo . ":generateContent?key=" . $api_key;

            $body = json_encode([
                "contents" => [["role" => "user", "parts" => [["text" => $prompt]]]],
                "generationConfig" => ["temperature" => 0.3, "maxOutputTokens" => 2048]
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                return ["success" => false, "http" => $http_code, "error" => "HTTP " . $http_code . " - " . substr((string)$response, 0, 300)];
            }

            $data = json_decode($response, true);

            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return ["success" => true, "http" => $http_code, "respuesta" => trim($data['candidates'][0]['content']['parts'][0]['text']), "tokens" => IaConsumos::tokensGemini($data)];
            }

            return ["success" => false, "error" => "Formato inesperado de Gemini"];
        } catch (Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    private static function llamarGroq($api_key, $modelo, $prompt)
    {
        try {
            $url = "https://api.groq.com/openai/v1/chat/completions";

            $body = json_encode([
                "model" => $modelo,
                "messages" => [
                    ["role" => "system", "content" => "Eres un redactor experto en lenguaje técnico docente. Responde SOLO con el texto mejorado en texto plano, sin markdown ni comentarios adicionales."],
                    ["role" => "user", "content" => $prompt]
                ],
                "temperature" => 0.3,
                "max_tokens" => 2048
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $api_key]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                // Se incluye el cuerpo para saber por qué falla (modelo retirado, llave, etc.)
                return ["success" => false, "http" => $http_code, "error" => "HTTP " . $http_code . " - " . substr((string)$response, 0, 300)];
            }

            $data = json_decode($response, true);

            if (isset($data['choices'][0]['message']['content'])) {
                return ["success" => true, "http" => $http_code, "respuesta" => trim($data['choices'][0]['message']['content']), "tokens" => IaConsumos::tokensOpenAI($data)];
            }

            return ["success" => false, "error" => "Formato inesperado de Groq"];
        } catch (Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}