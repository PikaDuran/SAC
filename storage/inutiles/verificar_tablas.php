<?php

require_once 'src/config/database.php';

try {
    $pdo = getDatabase();

    echo "=== ESTRUCTURA REAL DE LAS TABLAS ===\n\n";

    $tablas = ['cfdi', 'cfdi_conceptos', 'cfdi_impuestos', 'cfdi_pagos', 'cfdi_pago_documentos_relacionados', 'cfdi_timbre_fiscal'];

    foreach ($tablas as $tabla) {
        echo "TABLA: $tabla\n";
        echo str_repeat("-", 50) . "\n";

        $result = $pdo->query("DESCRIBE $tabla");
        while ($row = $result->fetch()) {
            echo sprintf("%-25s %-15s %s\n", $row['Field'], $row['Type'], $row['Null']);
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
