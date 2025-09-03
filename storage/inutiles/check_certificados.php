<?php
require_once 'src/config/database.php';

echo "=== CERTIFICADOS REGISTRADOS ===\n\n";

try {
    $pdo = getDatabase();
    $stmt = $pdo->query("SELECT id, rfc, legal_name, is_active, valid_to FROM sat_fiel_certificates ORDER BY rfc");
    $certificados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Total certificados: " . count($certificados) . "\n\n";

    foreach ($certificados as $cert) {
        $estado = $cert['is_active'] ? 'ACTIVO' : 'INACTIVO';
        $vencimiento = date('d/m/Y', strtotime($cert['valid_to']));

        echo "ID: {$cert['id']}\n";
        echo "RFC: {$cert['rfc']}\n";
        echo "Razón Social: " . ($cert['legal_name'] ?: 'Sin nombre') . "\n";
        echo "Estado: $estado\n";
        echo "Vencimiento: $vencimiento\n";
        echo str_repeat('-', 40) . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
