<?php
/**
 * Script para configurar Workspace Settings de Firma.dev
 * Solo header personalizado + team_email
 * Body en NULL para mantener plantilla por defecto con boton "Sign Document"
 * 
 * Uso: php configurar-workspace-firma.php
 */

// =====================================================
// CONFIGURACION - MODIFICAR SEGUN EL WORKSPACE
// =====================================================

$workspaceId = '7cc1dc41-99cb-4e65-97f5-e41bb2cb7286';
$apiKey = 'firma_d98b3fbb5714d0495d5ae9eb55d425e5ed90f01577b96b7a';

// Personaliza estos valores:
$settings = [
    'signing_request_email_header' => 'Solicitud de Firma - Gimnasio Educativo Jean Piaget Cucaita',
    'signing_request_email_body' => null,  // NULL = usa plantilla por defecto con boton
    'team_email' => 'acinom1718@hotmail.com',
    'timezone' => 'America/Bogota'
];

// =====================================================
// NO MODIFICAR DESDE AQUI
// =====================================================

$url = "https://api.firma.dev/functions/v1/signing-request-api/workspace/{$workspaceId}/settings";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($settings));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== Configuracion Workspace Firma.dev ===\n\n";
echo "HTTP Code: {$httpCode}\n\n";

$data = json_decode($response, true);

if ($httpCode === 200) {
    echo "Workspace: " . ($data['name'] ?? 'N/A') . "\n";
    echo "Header: " . ($data['signing_request_email_header'] ?? 'NULL') . "\n";
    echo "Body: " . ($data['signing_request_email_body'] ?? 'NULL (plantilla por defecto)') . "\n";
    echo "Team Email: " . ($data['team_email'] ?? 'NULL') . "\n";
    echo "Timezone: " . ($data['timezone'] ?? 'NULL') . "\n";
    echo "\n Workspace configurado exitosamente!\n";
} else {
    echo "Error:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}