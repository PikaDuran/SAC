<?php

/**
 * Verificar datos en tablas estructuradas de pagos
 */

require_once 'src/config/database.php';

try {
    $pdo = getDatabase();
    echo "=== VERIFICACIÓN DE DATOS EN TABLAS DE PAGOS ===\n\n";

    // Verificar registros en cada tabla
    echo "1. CONTEO DE REGISTROS:\n";
    echo "═══════════════════════════════════\n";

    $tablas = [
        'cfdi_pagos',
        'cfdi_pago_documentos_relacionados',
        'cfdi_complementos',
        'cfdi'
    ];

    foreach ($tablas as $tabla) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM $tabla")->fetchColumn();
            echo sprintf("%-35s: %s registros\n", $tabla, number_format($count));
        } catch (Exception $e) {
            echo sprintf("%-35s: ERROR - %s\n", $tabla, $e->getMessage());
        }
    }

    // Verificar CFDIs de tipo P
    echo "\n2. CFDIs DE TIPO PAGO:\n";
    echo "═══════════════════════════════════\n";

    // Primero verificar qué campo se usa para tipo
    $campos = $pdo->query("DESCRIBE cfdi")->fetchAll(PDO::FETCH_COLUMN);

    $campoTipo = null;
    if (in_array('tipo_comprobante', $campos)) {
        $campoTipo = 'tipo_comprobante';
    } elseif (in_array('tipo', $campos)) {
        $campoTipo = 'tipo';
    }

    if ($campoTipo) {
        $totalCfdi = $pdo->query("SELECT COUNT(*) FROM cfdi")->fetchColumn();
        $cfdisP = $pdo->query("SELECT COUNT(*) FROM cfdi WHERE $campoTipo = 'P'")->fetchColumn();
        $cfdisConJson = $pdo->query("SELECT COUNT(*) FROM cfdi WHERE $campoTipo = 'P' AND complemento_json IS NOT NULL AND complemento_json != '[]' AND complemento_json != ''")->fetchColumn();

        echo "Total CFDIs: " . number_format($totalCfdi) . "\n";
        echo "CFDIs tipo P (Pago): " . number_format($cfdisP) . "\n";
        echo "CFDIs P con JSON: " . number_format($cfdisConJson) . "\n";
    } else {
        echo "No se encontró campo de tipo de comprobante\n";
    }

    // Verificar estructura de cfdi_pagos
    echo "\n3. ESTRUCTURA DE TABLA cfdi_pagos:\n";
    echo "═══════════════════════════════════\n";

    $estructuraPagos = $pdo->query("DESCRIBE cfdi_pagos")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($estructuraPagos as $campo) {
        echo sprintf("%-25s %-20s\n", $campo['Field'], $campo['Type']);
    }

    // Verificar estructura de cfdi_pago_documentos_relacionados
    echo "\n4. ESTRUCTURA DE TABLA cfdi_pago_documentos_relacionados:\n";
    echo "═══════════════════════════════════════════════════════\n";

    $estructuraDocRel = $pdo->query("DESCRIBE cfdi_pago_documentos_relacionados")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($estructuraDocRel as $campo) {
        echo sprintf("%-25s %-20s\n", $campo['Field'], $campo['Type']);
    }

    // El problema real
    echo "\n5. DIAGNÓSTICO DEL PROBLEMA:\n";
    echo "═══════════════════════════════════\n";

    $pagosEnTabla = $pdo->query("SELECT COUNT(*) FROM cfdi_pagos")->fetchColumn();
    $docsEnTabla = $pdo->query("SELECT COUNT(*) FROM cfdi_pago_documentos_relacionados")->fetchColumn();

    if ($pagosEnTabla == 0 && $docsEnTabla == 0) {
        echo "❌ PROBLEMA CONFIRMADO:\n";
        echo "   - Las tablas estructuradas están VACÍAS\n";
        echo "   - Los datos solo están en complemento_json\n";
        echo "   - El importador NO está insertando en tablas relacionales\n";
        echo "\n✅ SOLUCIÓN NECESARIA:\n";
        echo "   - Modificar importador para insertar datos estructurados\n";
        echo "   - Reprocesar CFDIs existentes para llenar tablas\n";
    } else {
        echo "✅ Hay datos en tablas estructuradas\n";
        echo "   - cfdi_pagos: $pagosEnTabla registros\n";
        echo "   - cfdi_pago_documentos_relacionados: $docsEnTabla registros\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
