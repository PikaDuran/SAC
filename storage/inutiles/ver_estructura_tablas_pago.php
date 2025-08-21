<?php

/**
 * Ver estructura completa de tabla cfdi_pagos
 */

require_once 'src/config/database.php';

try {
    $pdo = getDatabase();
    echo "=== ESTRUCTURA COMPLETA DE TABLA cfdi_pagos ===\n\n";

    $campos = $pdo->query("DESCRIBE cfdi_pagos")->fetchAll(PDO::FETCH_ASSOC);

    echo "CAMPOS DE LA TABLA cfdi_pagos:\n";
    echo "═══════════════════════════════════════\n";

    $i = 1;
    foreach ($campos as $campo) {
        echo sprintf(
            "%2d. %-30s %s\n",
            $i,
            $campo['Field'],
            $campo['Type']
        );
        $i++;
    }

    echo "\nTotal de campos: " . count($campos) . "\n";

    // Ver estructura de cfdi_pago_documentos_relacionados también
    echo "\n\n=== ESTRUCTURA DE cfdi_pago_documentos_relacionados ===\n\n";

    $camposDoc = $pdo->query("DESCRIBE cfdi_pago_documentos_relacionados")->fetchAll(PDO::FETCH_ASSOC);

    echo "CAMPOS DE LA TABLA cfdi_pago_documentos_relacionados:\n";
    echo "═══════════════════════════════════════════════════\n";

    $j = 1;
    foreach ($camposDoc as $campo) {
        echo sprintf(
            "%2d. %-30s %s\n",
            $j,
            $campo['Field'],
            $campo['Type']
        );
        $j++;
    }

    echo "\nTotal de campos: " . count($camposDoc) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
