<?php
class IaTranscripcionAudio
{
    // Modelo de transcripción. En constante para que el registro de consumo
    // (ia_consumos) guarde el mismo modelo que se llama.
    const MODELO_WHISPER = 'whisper-large-v3';

    /**
     * Recibe un archivo de audio y lo transcribe usando Groq Whisper Large v3.
     * Espera multipart/form-data con el campo "audio" y opcionalmente "idioma".
     * Respuesta: { success, texto, idioma_detectado, duracion, tiempo_ms }
     */
    public static function transcribir()
    {
        try {
            $db = Flight::db();

            if (empty($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
                Flight::json(["error" => "El archivo de audio es requerido"], 400);
                return;
            }

            $archivo = $_FILES['audio'];
            $idioma = $_POST['idioma'] ?? 'es';

            $tamanoMaximo = 25 * 1024 * 1024;
            if ($archivo['size'] > $tamanoMaximo) {
                Flight::json(["error" => "El archivo excede el tamaño máximo permitido (25MB)"], 400);
                return;
            }

            $config = self::obtenerConfiguracion($db);
            $groq_key = $config['groq_api_key'] ?? null;

            if (!$groq_key) {
                Flight::json(["error" => "No hay API key de Groq configurada"], 500);
                return;
            }

            $inicio_tiempo = microtime(true);
            $resultado = self::llamarGroqWhisper($groq_key, $archivo['tmp_name'], $archivo['name'], $idioma);
            $tiempo_ms = round((microtime(true) - $inicio_tiempo) * 1000);

            // Whisper no reporta tokens: el registro queda con tokens en NULL
            IaConsumos::registrar('transcripcion', 'transcribir', [
                IaConsumos::intento('groq', self::MODELO_WHISPER, $resultado, $inicio_tiempo)
            ]);

            if (!$resultado['success']) {
                Flight::json([
                    "error" => "Error al transcribir el audio: " . ($resultado['error'] ?? 'desconocido')
                ], 500);
                return;
            }

            Flight::json([
                "success" => true,
                "texto" => $resultado['texto'],
                "idioma_detectado" => $resultado['idioma_detectado'] ?? $idioma,
                "duracion" => $resultado['duracion'] ?? null,
                "tiempo_ms" => $tiempo_ms
            ]);

        } catch (Exception $e) {
            error_log("Error en IaTranscripcionAudio::transcribir: " . $e->getMessage());
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

    private static function llamarGroqWhisper($api_key, $rutaArchivo, $nombreArchivo, $idioma)
    {
        try {
            $url = "https://api.groq.com/openai/v1/audio/transcriptions";

            $cfile = new CURLFile($rutaArchivo, mime_content_type($rutaArchivo), $nombreArchivo);

            $postFields = [
                'file' => $cfile,
                'model' => self::MODELO_WHISPER,
                'language' => $idioma,
                'response_format' => 'verbose_json',
                'temperature' => 0
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $api_key
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                return ["success" => false, "http" => $http_code, "error" => "HTTP " . $http_code . " - " . substr((string)$response, 0, 300)];
            }

            $data = json_decode($response, true);

            if (isset($data['text'])) {
                return [
                    "success" => true,
                    "texto" => trim($data['text']),
                    "idioma_detectado" => $data['language'] ?? null,
                    "duracion" => $data['duration'] ?? null
                ];
            }

            return ["success" => false, "error" => "Formato inesperado de Groq Whisper"];
        } catch (Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}