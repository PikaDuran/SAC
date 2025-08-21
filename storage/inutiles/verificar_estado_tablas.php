<?php
echo "=== VERIFICAR ESTADO DE TABLAS CFDI ===\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');

    $tablas = [
        'cfdi',
        'cfdi_timbre_fiscal',
        'cfdi_conceptos',
        'cfdi_impuestos',
        'cfdi_pagos',
        'cfdi_complementos',
        'cfdi_auditoria'
    ];

    foreach ($tablas as $tabla) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $tabla");
        $count = $stmt->fetchColumn();
        echo "{$tabla}: {$count} registros\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
