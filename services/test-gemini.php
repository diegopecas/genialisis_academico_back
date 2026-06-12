<?php
require_once 'env.php';

// Obtener API key de la base de datos
$db = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD);
$stmt = $db->prepare("SELECT valor FROM ia_configuracion WHERE clave = 'gemini_api_key'");
$stmt->execute();
$apiKey = $stmt->fetchColumn();

echo "API Key: " . substr($apiKey, 0, 10) . "...\n\n";

// Listar modelos disponibles
$url = "https://generativelanguage.googleapis.com/v1/models?key=" . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    echo "=== MODELOS DISPONIBLES ===\n\n";
    
    if (isset($data['models'])) {
        foreach ($data['models'] as $model) {
            echo "Nombre: " . $model['name'] . "\n";
            echo "Display Name: " . ($model['displayName'] ?? 'N/A') . "\n";
            
            if (isset($model['supportedGenerationMethods'])) {
                echo "Métodos soportados: " . implode(', ', $model['supportedGenerationMethods']) . "\n";
            }
            
            echo "---\n";
        }
    } else {
        echo "No se encontraron modelos.\n";
    }
} else {
    echo "ERROR al obtener modelos:\n";
    echo $response . "\n";
}
?>