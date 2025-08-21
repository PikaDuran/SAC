<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Limpiando todas las tablas...\n";

    // Deshabilitar verificación de claves foráneas
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // Limpiar en orden correcto (tablas dependientes primero)
    $tablas = [
        'cfdi_pago_documentos_relacionados',
        'cfdi_pagos',
        'cfdi_complementos',
        'cfdi_impuestos',
        'cfdi_conceptos',
        'cfdi_timbre_fiscal',
        'cfdi_auditoria',
        'cfdi'
    ];

    foreach ($tablas as $tabla) {
        echo "Limpiando $tabla... ";
        $pdo->exec("DELETE FROM $tabla");
        echo "✓\n";
    }

    // Reactivar verificación de claves foráneas
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "✅ Todas las tablas limpiadas correctamente\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
