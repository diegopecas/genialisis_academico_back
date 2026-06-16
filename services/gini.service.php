<?php
class Gini
{
    public static function generarSesion()
    {
        $db = Flight::db();

        $sentence = $db->prepare("SELECT valor FROM ia_configuracion WHERE clave = 'OPENAI_API_KEY' LIMIT 1");
        $sentence->execute();
        $row = $sentence->fetch();

        if (!$row || empty($row['valor'])) {
            Flight::json(['error' => true, 'message' => 'API key no configurada'], 500);
            return;
        }

        $apiKey = $row['valor'];
        $data = Flight::request()->data;
        $modo = isset($data['modo']) ? $data['modo'] : 'es-en';

        $clave = $modo === 'en-es' ? 'GINI_PROMPT_EN_ES' : 'GINI_PROMPT_ES_EN';

        $sentencePrompt = $db->prepare("SELECT valor FROM ia_configuracion WHERE clave = :clave LIMIT 1");
        $sentencePrompt->bindParam(':clave', $clave);
        $sentencePrompt->execute();
        $rowPrompt = $sentencePrompt->fetch();

        $instructions = $rowPrompt && !empty($rowPrompt['valor'])
            ? $rowPrompt['valor']
            : ($modo === 'en-es'
                ? 'Eres Gini, traductora simultánea. Escucha TODO lo que se dice en inglés y tradúcelo EXACTAMENTE al español. No filtres, no omitas, no comentas. Solo traduce directamente.'
                : 'Eres Gini, traductora simultánea. Escucha TODO lo que se dice en español y tradúcelo EXACTAMENTE al inglés. No filtres, no omitas, no comentas. Solo traduce directamente.');

        $fuente = ($rowPrompt && !empty($rowPrompt["valor"])) ? "BD" : "fallback";
        error_log("[Gini] modo={$modo} | fuente={$fuente} | prompt=" . substr($instructions, 0, 80) . "...");

        $payload = json_encode([
            "session" => [
                "type" => "realtime",
                "model" => "gpt-4o-mini-realtime-preview",
                "instructions" => $instructions,
                "audio" => [
                    "output" => ["voice" => "shimmer"],
                    "input" => [
                        "transcription" => ["model" => "whisper-1"],
                        "turn_detection" => [
                            "type" => "server_vad",
                            "threshold" => 0.5,
                            "prefix_padding_ms" => 300,
                            "silence_duration_ms" => 600
                        ]
                    ]
                ]
            ]
        ]);

        $ch = curl_init("https://api.openai.com/v1/realtime/client_secrets");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $apiKey,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Flight::json(['error' => true, 'message' => 'Error generando sesión OpenAI', 'detalle' => $response], 500);
            return;
        }

        $result = json_decode($response, true);
        Flight::json($result);
    }
}