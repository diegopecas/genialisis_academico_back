<?php
/**
 * IaClaude — llamadas a la API de Claude (Anthropic) para texto y visión.
 *
 * La usan los servicios de IA cuando en su cadena aparece el proveedor "claude".
 * El paso de la cadena puede traer el esfuerzo como tercer segmento:
 *
 *   claude|claude-sonnet-5-5          -> esfuerzo por defecto del modelo
 *   claude|claude-sonnet-5-5|low      -> esfuerzo low (más rápido y barato)
 *
 * Como los parsers de las cadenas cortan en el primer "|", al servicio le llega
 * "claude-sonnet-5-5|low" como modelo; aquí se separa con separarModelo().
 *
 * Particularidades de los modelos actuales de Claude que se manejan aquí:
 *   - No aceptan temperature/top_p/top_k (devuelven 400): no se envían.
 *   - Piensan por defecto (pensamiento adaptativo) y ese pensamiento cuenta
 *     dentro de max_tokens: por eso max_tokens tiene un piso (MIN_MAX_TOKENS).
 *     Solo se cobran los tokens que realmente se generan, no el máximo.
 *   - La respuesta puede traer bloques de pensamiento antes del texto: se toma
 *     solo el texto de los bloques tipo "text".
 *   - La conversación debe terminar en un mensaje del usuario (no hay prefill).
 *
 * Key: claude_api_key en ia_configuracion (la entrega quien llama).
 *
 * Devuelve siempre el mismo formato que los demás proveedores de los servicios:
 *   ['success' => bool, 'http' => int|null, 'respuesta' => string|null,
 *    'tokens' => ['entrada' => int, 'salida' => int, 'total' => int]|null,
 *    'error' => string|null]
 */
class IaClaude
{
    const URL = 'https://api.anthropic.com/v1/messages';
    const VERSION_API = '2026-10-21';

    // Piso de max_tokens para dejar espacio al pensamiento adaptativo.
    const MIN_MAX_TOKENS = 4096;

    // Niveles de esfuerzo que acepta la API (output_config.effort).
    const ESFUERZOS = ['low', 'medium', 'high', 'xhigh', 'max'];

    /**
     * Separa "modelo|esfuerzo" en sus partes. Si el esfuerzo no viene o no es
     * un nivel válido, queda null y se usa el valor por defecto del modelo.
     *
     * @param string $modelo Modelo tal como viene de la cadena.
     * @return array ['modelo' => string, 'esfuerzo' => string|null]
     */
    public static function separarModelo($modelo)
    {
        $partes = explode('|', (string) $modelo, 2);
        $nombre = trim($partes[0]);
        $esfuerzo = isset($partes[1]) ? strtolower(trim($partes[1])) : null;

        if ($esfuerzo !== null && !in_array($esfuerzo, self::ESFUERZOS, true)) {
            error_log("IaClaude - esfuerzo '{$esfuerzo}' no válido; se usa el del modelo");
            $esfuerzo = null;
        }

        return ['modelo' => $nombre, 'esfuerzo' => $esfuerzo];
    }

    /**
     * Llamada de texto (chat o prompt único).
     *
     * @param string $apiKey    claude_api_key del tenant.
     * @param string $modelo    Modelo de la cadena ("modelo" o "modelo|esfuerzo").
     * @param string $sistema   Instrucción de sistema (contexto). Puede ir vacía.
     * @param array  $mensajes  Turnos [['role' => 'user'|'assistant', 'content' => string]].
     * @param int    $maxTokens Límite de salida pedido por el servicio.
     * @param int    $timeout   Timeout de la llamada en segundos.
     * @return array
     */
    public static function llamarTexto($apiKey, $modelo, $sistema, array $mensajes, $maxTokens, $timeout)
    {
        $limpios = self::normalizarMensajes($mensajes);
        if (empty($limpios)) {
            return ['success' => false, 'http' => null, 'respuesta' => null, 'tokens' => null, 'error' => 'No hay mensaje del usuario para enviar'];
        }

        $payload = self::armarPayload($modelo, $maxTokens, $limpios);
        if (trim((string) $sistema) !== '') {
            $payload['system'] = $sistema;
        }

        return self::enviar($apiKey, $payload, $timeout);
    }

    /**
     * Llamada con un archivo (imagen o PDF) más un prompt.
     *
     * @param string $apiKey    claude_api_key del tenant.
     * @param string $modelo    Modelo de la cadena ("modelo" o "modelo|esfuerzo").
     * @param string $base64    Contenido del archivo en base64.
     * @param string $mimeType  image/jpeg, image/png, image/webp, image/gif o application/pdf.
     * @param string $prompt    Instrucción para el modelo.
     * @param int    $maxTokens Límite de salida pedido por el servicio.
     * @param int    $timeout   Timeout de la llamada en segundos.
     * @return array
     */
    public static function llamarVision($apiKey, $modelo, $base64, $mimeType, $prompt, $maxTokens, $timeout)
    {
        $fuente = ['type' => 'base64', 'media_type' => $mimeType, 'data' => $base64];

        // El PDF va como bloque "document"; las imágenes como bloque "image".
        // La documentación recomienda poner el archivo antes del texto.
        $bloqueArchivo = ($mimeType === 'application/pdf')
            ? ['type' => 'document', 'source' => $fuente]
            : ['type' => 'image', 'source' => $fuente];

        $mensajes = [[
            'role' => 'user',
            'content' => [
                $bloqueArchivo,
                ['type' => 'text', 'text' => $prompt]
            ]
        ]];

        $payload = self::armarPayload($modelo, $maxTokens, $mensajes);

        return self::enviar($apiKey, $payload, $timeout);
    }

    /**
     * Arma el cuerpo común de la petición (modelo, max_tokens, esfuerzo, mensajes).
     */
    private static function armarPayload($modelo, $maxTokens, array $mensajes)
    {
        $m = self::separarModelo($modelo);

        $payload = [
            'model' => $m['modelo'],
            'max_tokens' => max((int) $maxTokens, self::MIN_MAX_TOKENS),
            'messages' => $mensajes
        ];

        if ($m['esfuerzo'] !== null) {
            $payload['output_config'] = ['effort' => $m['esfuerzo']];
        }

        return $payload;
    }

    /**
     * Deja los turnos en el formato que exige la API: roles user/assistant,
     * sin mensajes vacíos, empezando y terminando en user.
     */
    private static function normalizarMensajes(array $mensajes)
    {
        $limpios = [];
        foreach ($mensajes as $msg) {
            $rol = (isset($msg['role']) && $msg['role'] === 'assistant') ? 'assistant' : 'user';
            $contenido = isset($msg['content']) ? trim((string) $msg['content']) : '';
            if ($contenido === '') {
                continue;
            }
            $limpios[] = ['role' => $rol, 'content' => $contenido];
        }

        // Si el historial recortado arranca con una respuesta del asistente, se descarta.
        while (!empty($limpios) && $limpios[0]['role'] !== 'user') {
            array_shift($limpios);
        }
        // No hay prefill: la conversación debe terminar en el usuario.
        while (!empty($limpios) && end($limpios)['role'] !== 'user') {
            array_pop($limpios);
        }

        return $limpios;
    }

    /**
     * Envía la petición y traduce la respuesta al formato común.
     */
    private static function enviar($apiKey, array $payload, $timeout)
    {
        try {
            $ch = curl_init(self::URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: ' . self::VERSION_API
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeout);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return ['success' => false, 'http' => null, 'respuesta' => null, 'tokens' => null, 'error' => 'conexión: ' . $curlError];
            }
            if ($httpCode !== 200) {
                return ['success' => false, 'http' => $httpCode, 'respuesta' => null, 'tokens' => null, 'error' => 'HTTP ' . $httpCode . ' - ' . substr((string) $response, 0, 300)];
            }

            $data = json_decode($response, true);
            $tokens = self::tokens($data);

            // Se toma solo el texto: los bloques de pensamiento se ignoran.
            $texto = '';
            foreach (($data['content'] ?? []) as $bloque) {
                if (($bloque['type'] ?? '') === 'text') {
                    $texto .= $bloque['text'] ?? '';
                }
            }

            $stopReason = $data['stop_reason'] ?? '';
            if ($stopReason === 'refusal') {
                return ['success' => false, 'http' => $httpCode, 'respuesta' => null, 'tokens' => $tokens, 'error' => 'El modelo rechazó la solicitud (refusal)'];
            }
            if (trim($texto) === '') {
                return ['success' => false, 'http' => $httpCode, 'respuesta' => null, 'tokens' => $tokens, 'error' => 'Respuesta sin texto (stop_reason: ' . ($stopReason ?: 'desconocido') . ')'];
            }

            return ['success' => true, 'http' => $httpCode, 'respuesta' => trim($texto), 'tokens' => $tokens, 'error' => null];
        } catch (Exception $e) {
            return ['success' => false, 'http' => null, 'respuesta' => null, 'tokens' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Tokens de la respuesta (usage). El pensamiento ya viene sumado en output_tokens.
     * null si no vienen.
     */
    private static function tokens($data)
    {
        if (!is_array($data) || !isset($data['usage'])) {
            return null;
        }
        $uso = $data['usage'];
        $entrada = (int) ($uso['input_tokens'] ?? 0)
            + (int) ($uso['cache_creation_input_tokens'] ?? 0)
            + (int) ($uso['cache_read_input_tokens'] ?? 0);
        $salida = (int) ($uso['output_tokens'] ?? 0);

        return ['entrada' => $entrada, 'salida' => $salida, 'total' => $entrada + $salida];
    }
}
