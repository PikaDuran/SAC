<?php
require_once 'src/config/database.php';

$pdo = getDatabase();
$stmt = $pdo->prepare('SELECT uuid, complemento_json FROM cfdi WHERE tipo = ? AND rfc_emisor = ? LIMIT 1');
$stmt->execute(['P', 'BFM170822P38']);

echo "=== ANÁLISIS DETALLADO DEL COMPLEMENTO DE PAGO ===\n\n";

while ($row = $stmt->fetch()) {
    echo "UUID: " . $row['uuid'] . "\n";

    if ($row['complemento_json']) {
        $json_data = json_decode($row['complemento_json'], true);

        if ($json_data && isset($json_data['complementos'])) {
            echo "COMPLEMENTOS ENCONTRADOS:\n";
            print_r($json_data['complementos']);

            // Si hay complemento de pagos
            if (isset($json_data['complementos']['Pagos'])) {
                echo "\nCOMPLEMENTO DE PAGOS:\n";
                print_r($json_data['complementos']['Pagos']);
            }
        }
    }

    break; // Solo mostrar el primero
}
