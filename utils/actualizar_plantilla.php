<?php
/**
 * Actualizar plantilla de contrato con sección firmas
 */

$host = '92.205.2.161';
$dbname = 'lumen_academico_prod';
$username = 'liceo_lumen_prod';
$password = 'lVuAT1xn2Q-j';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Leer JSON
    $jsonFile = __DIR__ . '/plantilla_contrato_actualizada.json';
    if (!file_exists($jsonFile)) {
        throw new Exception("No se encuentra: $jsonFile");
    }
    
    $jsonContent = file_get_contents($jsonFile);
    if (json_decode($jsonContent) === null) {
        throw new Exception("JSON inválido: " . json_last_error_msg());
    }
    
    // Obtener id_tipo_plantilla
    $stmt = $db->prepare("SELECT id FROM tipos_plantillas WHERE codigo = 'contrato_matricula'");
    $stmt->execute();
    $tipo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tipo) {
        throw new Exception("No existe tipo 'contrato_matricula'");
    }
    
    // Actualizar
    $update = $db->prepare("
        UPDATE plantillas 
        SET contenido = :contenido, fecha_actualizacion = NOW()
        WHERE id_tipo_plantilla = :id_tipo AND clave = 'contrato_completo'
    ");
    
    $update->execute([
        ':contenido' => $jsonContent,
        ':id_tipo' => $tipo['id']
    ]);
    
    echo "✓ Plantilla actualizada (" . $update->rowCount() . " registro)\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}