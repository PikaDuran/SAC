<?php
require_once 'src/config/database.php';

$pdo = getDatabase();
$stmt = $pdo->prepare('SELECT uuid, complemento_json FROM cfdi WHERE tipo = ? AND rfc_emisor = ? LIMIT 1');
$stmt->execute(['P', 'BFM170822P38']);

echo "=== ANÁLISIS COMPLETO DEL JSON ===\n\n";

while ($row = $stmt->fetch()) {
    echo "UUID: " . $row['uuid'] . "\n\n";

    if ($row['complemento_json']) {
        $json_data = json_decode($row['complemento_json'], true);

        if ($json_data) {
            echo "ESTRUCTURA COMPLETA DEL JSON:\n";
            echo json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            echo "Error al decodificar JSON: " . json_last_error_msg();
        }
    }

    break;
}
