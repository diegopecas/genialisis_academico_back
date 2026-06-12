<?php
/**
 * Script para OBTENER la configuracion actual del Workspace de Firma.dev
 * Esto nos mostrara el HTML por defecto del email
 */

// Configuracion
$workspaceId = '7cc1dc41-99cb-4e65-97f5-e41bb2cb7286';
$apiKey = 'firma_d98b3fbb5714d0495d5ae9eb55d425e5ed90f01577b96b7a';

$url = "https://api.firma.dev/functions/v1/signing-request-api/workspace/{$workspaceId}/settings";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: ' . $apiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== GET Workspace Settings Firma.dev ===\n\n";
echo "HTTP Code: {$httpCode}\n\n";

$data = json_decode($response, true);

echo "=== CONFIGURACION ACTUAL ===\n";
echo "Workspace ID: " . ($data['workspace_id'] ?? 'N/A') . "\n";
echo "Name: " . ($data['name'] ?? 'N/A') . "\n";
echo "Team Email: " . ($data['team_email'] ?? 'N/A') . "\n";
echo "Timezone: " . ($data['timezone'] ?? 'N/A') . "\n";
echo "\n";

echo "=== EMAIL HEADER ===\n";
echo ($data['signing_request_email_header'] ?? '(NULL - usando default)') . "\n";
echo "\n";

echo "=== EMAIL BODY (HTML) ===\n";
$body = $data['signing_request_email_body'] ?? null;
if ($body) {
    echo $body . "\n";
    
    // Guardar en archivo para ver mejor
    file_put_contents('email_template_actual.html', $body);
    echo "\n(HTML guardado en email_template_actual.html)\n";
} else {
    echo "(NULL - usando plantilla por defecto de Firma.dev)\n";
}

echo "\n=== RESPONSE COMPLETA (JSON) ===\n";
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";