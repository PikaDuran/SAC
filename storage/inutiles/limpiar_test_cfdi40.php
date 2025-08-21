<?php
$pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');

// Primero obtener los IDs de los CFDIs a eliminar
$stmt = $pdo->prepare('SELECT id FROM cfdi WHERE uuid IN (?, ?, ?)');
$stmt->execute(['0D157DDC-7E07-4FD8-B37A-BAE0105F3F1C', '3B08E7A2-3EB7-46BD-8849-4675C03B718E', '728F325C-7365-4B96-B2A5-BAEB534A2CE0']);
$cfdiIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($cfdiIds)) {
    echo "IDs encontrados: " . implode(', ', $cfdiIds) . "\n";

    // Eliminar registros dependientes primero
    $tables = ['cfdi_impuestos', 'cfdi_conceptos', 'cfdi_timbre_fiscal'];
    foreach ($tables as $table) {
        $placeholders = str_repeat('?,', count($cfdiIds) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM $table WHERE cfdi_id IN ($placeholders)");
        $result = $stmt->execute($cfdiIds);
        echo "Registros eliminados de $table: " . $stmt->rowCount() . "\n";
    }

    // Finalmente eliminar los CFDIs principales
    $placeholders = str_repeat('?,', count($cfdiIds) - 1) . '?';
    $stmt = $pdo->prepare("DELETE FROM cfdi WHERE id IN ($placeholders)");
    $result = $stmt->execute($cfdiIds);
    echo "Registros eliminados de cfdi: " . $stmt->rowCount() . "\n";
} else {
    echo "No se encontraron registros para eliminar.\n";
}
