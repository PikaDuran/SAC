<?php
require_once 'src/config/database.php';

$pdo = getDatabase();
$stmt = $pdo->prepare('SELECT uuid, complemento_json FROM cfdi WHERE tipo = ? AND rfc_emisor = ? LIMIT 3');
$stmt->execute(['P', 'BFM170822P38']);

echo "=== ANÁLISIS DEL JSON DE COMPLEMENTOS ===\n\n";

while ($row = $stmt->fetch()) {
    echo "UUID: " . $row['uuid'] . "\n";
    echo "JSON LENGTH: " . strlen($row['complemento_json']) . "\n";
    
    if ($row['complemento_json']) {
        echo "JSON PREVIEW (primeros 500 caracteres):\n";
        echo substr($row['complemento_json'], 0, 500) . "\n";
        
        // Intentar decodificar el JSON
        $json_data = json_decode($row['complemento_json'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "JSON VÁLIDO - Claves principales:\n";
            if (is_array($json_data)) {
                foreach (array_keys($json_data) as $key) {
                    echo "  - $key\n";
                }
            }
        } else {
            echo "ERROR EN JSON: " . json_last_error_msg() . "\n";
        }
    } else {
        echo "JSON VACÍO\n";
    }
    
    echo str_repeat("-", 80) . "\n\n";
}
?>
