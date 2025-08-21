<?php

/**
 * Verificar estructura de tablas para complementos de pago
 */

require_once 'src/config/database.php';

try {
    $pdo = getDatabase();
    echo "=== VERIFICACIÓN DE TABLAS PARA COMPLEMENTOS DE PAGO ===\n\n";

    // Buscar todas las tablas relacionadas con pagos
    echo "1. TABLAS RELACIONADAS CON PAGOS:\n";
    echo "═══════════════════════════════════════\n";

    $tablasPago = $pdo->query("SHOW TABLES LIKE '%pago%'")->fetchAll(PDO::FETCH_COLUMN);
    $tablasComplemento = $pdo->query("SHOW TABLES LIKE '%complemento%'")->fetchAll(PDO::FETCH_COLUMN);
    $tablasCfdi = $pdo->query("SHOW TABLES LIKE '%cfdi%'")->fetchAll(PDO::FETCH_COLUMN);

    echo "Tablas con 'pago':\n";
    foreach ($tablasPago as $tabla) {
        echo "  - $tabla\n";
    }

    echo "\nTablas con 'complemento':\n";
    foreach ($tablasComplemento as $tabla) {
        echo "  - $tabla\n";
    }

    echo "\nTablas con 'cfdi':\n";
    foreach ($tablasCfdi as $tabla) {
        echo "  - $tabla\n";
    }

    // Verificar estructura de tabla cfdi
    echo "\n\n2. ESTRUCTURA DE TABLA 'cfdi':\n";
    echo "═══════════════════════════════════════\n";

    $campos = $pdo->query("DESCRIBE cfdi")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($campos as $campo) {
        echo sprintf(
            "%-25s %-20s %s\n",
            $campo['Field'],
            $campo['Type'],
            ($campo['Null'] == 'NO' ? 'NOT NULL' : 'NULL')
        );
    }

    // Verificar si hay datos estructurados de pagos en otra tabla
    echo "\n\n3. BÚSQUEDA DE DATOS ESTRUCTURADOS:\n";
    echo "═══════════════════════════════════════\n";

    // Verificar si hay alguna tabla que contenga datos de pagos estructurados
    $todasTablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($todasTablas as $tabla) {
        $columnas = $pdo->query("DESCRIBE $tabla")->fetchAll(PDO::FETCH_COLUMN);
        $columnasRelacionadas = array_filter($columnas, function ($col) {
            return stripos($col, 'pago') !== false ||
                stripos($col, 'complemento') !== false ||
                stripos($col, 'uuid') !== false ||
                stripos($col, 'cfdi') !== false;
        });

        if (!empty($columnasRelacionadas)) {
            echo "\nTabla '$tabla' tiene columnas relacionadas:\n";
            foreach ($columnasRelacionadas as $col) {
                echo "  - $col\n";
            }
        }
    }

    // Verificar el problema real: datos JSON sin estructura relacional
    echo "\n\n4. PROBLEMA IDENTIFICADO:\n";
    echo "═══════════════════════════════════════\n";

    $countPagos = $pdo->query("SELECT COUNT(*) FROM cfdi WHERE tipo_comprobante = 'P'")->fetchColumn();
    $countPagosConJson = $pdo->query("SELECT COUNT(*) FROM cfdi WHERE tipo_comprobante = 'P' AND complemento_json IS NOT NULL AND complemento_json != '[]'")->fetchColumn();

    echo "CFDIs de tipo Pago (P): $countPagos\n";
    echo "CFDIs con complemento_json: $countPagosConJson\n";
    echo "Datos almacenados solo en JSON, no en tablas relacionales\n";

    echo "\n\n5. SOLUCIÓN NECESARIA:\n";
    echo "═══════════════════════════════════════\n";
    echo "✗ Los datos están en JSON pero NO son consultables\n";
    echo "✗ No hay tablas para: pagos, documentos_relacionados, retenciones, etc.\n";
    echo "✓ NECESARIO: Crear estructura relacional para complementos de pago\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
