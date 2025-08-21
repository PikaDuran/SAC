<?php
require 'src/config/database.php';

try {
    $pdo = getDatabase();

    echo "=== VACIANDO TABLAS RELACIONADAS CON IMPORTACIÓN XML ===\n\n";

    // Deshabilitar verificaciones de claves foráneas temporalmente
    $pdo->exec("SET foreign_key_checks = 0");

    // Lista de tablas a vaciar (en orden para evitar problemas de FK)
    $tables = [
        'cfdi_pago_documentos_relacionados',
        'cfdi_pagos',
        'cfdi_timbre_fiscal',
        'cfdi'
    ];

    foreach ($tables as $table) {
        echo "Vaciando tabla: {$table}...\n";

        // Verificar cuántos registros había antes
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM {$table}");
        $stmt->execute();
        $before = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Vaciar la tabla
        $pdo->exec("TRUNCATE TABLE {$table}");

        // Verificar que se vació
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM {$table}");
        $stmt->execute();
        $after = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        echo "  - Registros eliminados: {$before}\n";
        echo "  - Registros restantes: {$after}\n\n";
    }

    // Reactivar verificaciones de claves foráneas
    $pdo->exec("SET foreign_key_checks = 1");

    echo "=== VERIFICACIÓN FINAL ===\n";

    // Verificar que todas las tablas están vacías
    $total_registros = 0;
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM {$table}");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "Tabla {$table}: {$count} registros\n";
        $total_registros += $count;
    }

    echo "\nTOTAL DE REGISTROS EN TODAS LAS TABLAS: {$total_registros}\n";

    if ($total_registros == 0) {
        echo "\n✅ TODAS LAS TABLAS HAN SIDO VACIADAS EXITOSAMENTE\n";
        echo "Listo para proceder con el paso 2: importación real de archivos XML\n";
    } else {
        echo "\n❌ ERROR: Algunas tablas no se vaciaron completamente\n";
    }
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
