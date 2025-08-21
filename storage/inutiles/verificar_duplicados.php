<?php
echo "=== VERIFICAR DUPLICADOS ===\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');

    // Contar total CFDIs
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi');
    $total = $stmt->fetchColumn();
    echo "Total CFDIs en BD: $total\n";

    // Verificar UUID específico
    $uuid = '702888E7-16B1-4A6E-AAB5-C3F95047C4F2';
    $stmt = $pdo->prepare('SELECT id FROM cfdi WHERE uuid = ?');
    $stmt->execute([$uuid]);
    $result = $stmt->fetch();
    echo "UUID $uuid encontrado: " . ($result ? 'SÍ (ID: ' . $result['id'] . ')' : 'NO') . "\n";

    // Mostrar algunos registros si existen
    if ($total > 0) {
        echo "\nPrimeros 5 UUIDs en BD:\n";
        $stmt = $pdo->query('SELECT uuid FROM cfdi LIMIT 5');
        while ($row = $stmt->fetch()) {
            echo "- " . $row['uuid'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
