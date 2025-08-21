<?php

/**
 * Script para limpiar TODAS las tablas antes de las pruebas masivas
 */

require_once 'src/config/database.php';

try {
    $pdo = getDatabase();
    echo "=== LIMPIEZA COMPLETA DE TABLAS ===\n\n";

    // Deshabilitar verificación de claves foráneas temporalmente
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

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
        $count = $pdo->query("SELECT COUNT(*) FROM $tabla")->fetchColumn();
        echo "Limpiando $tabla ($count registros)... ";

        $pdo->exec("TRUNCATE TABLE $tabla");
        echo "✓ LIMPIA\n";
    }

    // Reactivar verificación de claves foráneas
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "\n=== VERIFICACIÓN POST-LIMPIEZA ===\n";
    foreach ($tablas as $tabla) {
        $count = $pdo->query("SELECT COUNT(*) FROM $tabla")->fetchColumn();
        echo "$tabla: $count registros\n";
    }

    echo "\n✅ TODAS LAS TABLAS LIMPIADAS - LISTO PARA PRUEBAS\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
