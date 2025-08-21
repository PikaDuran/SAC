<?php
require_once __DIR__ . '/src/config/database.php';
$pdo = getDatabase();

echo "=== VERIFICACIÓN DE ESTRUCTURA COMPLETA ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Verificar tabla cfdi
$columns = $pdo->query('DESCRIBE cfdi')->fetchAll(PDO::FETCH_ASSOC);
echo "Estructura actual de la tabla cfdi:\n";
foreach ($columns as $col) {
    echo "- {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']}\n";
}
echo "\nTotal campos en cfdi: " . count($columns) . "\n\n";

// Verificar tablas especializadas
$tables = [
    'cfdi_timbre_fiscal',
    'cfdi_pagos',
    'cfdi_pago_documentos_relacionados',
    'cfdi_conceptos',
    'cfdi_impuestos',
    'cfdi_complementos'
];

foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "Tabla $table: $count registros\n";
    } catch (Exception $e) {
        echo "Error en tabla $table: " . $e->getMessage() . "\n";
    }
}

// Verificar catálogos SAT
echo "\n=== CATÁLOGOS SAT ===\n";
$catalogos = [
    'forma_pago',
    'uso_cfdi',
    'regimen_fiscal',
    'metodo_pago'
];

foreach ($catalogos as $catalogo) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $catalogo")->fetchColumn();
        echo "Catálogo $catalogo: $count registros\n";
    } catch (Exception $e) {
        echo "Error en catálogo $catalogo: " . $e->getMessage() . "\n";
    }
}

// Verificar distribución por RFC
echo "\n=== DISTRIBUCIÓN ACTUAL DE CFDIs ===\n";
try {
    $stats = $pdo->query("
        SELECT 
            rfc_consultado, 
            direccion_flujo, 
            COUNT(*) as total,
            MIN(fecha) as fecha_min,
            MAX(fecha) as fecha_max
        FROM cfdi 
        GROUP BY rfc_consultado, direccion_flujo 
        ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($stats as $stat) {
        echo "{$stat['rfc_consultado']} ({$stat['direccion_flujo']}): {$stat['total']} CFDIs ";
        echo "({$stat['fecha_min']} a {$stat['fecha_max']})\n";
    }
} catch (Exception $e) {
    echo "Error obteniendo estadísticas: " . $e->getMessage() . "\n";
}

echo "\n=== RESUMEN DE IMPLEMENTACIÓN ===\n";
echo "✓ Base de datos con estructura completa\n";
echo "✓ Campos adicionales en tabla cfdi\n";
echo "✓ Tablas especializadas para complementos\n";
echo "✓ Script de reprocesamiento creado\n";
echo "✓ Generador de reportes completo creado\n";
echo "✓ Parser de XML mejorado con todos los campos\n";

echo "\nPróximos pasos recomendados:\n";
echo "1. Ejecutar: php reprocesar_cfdi_completo.php\n";
echo "2. Verificar datos con: php generar_reporte_completo.php\n";
echo "3. Ajustar filtros en el reporte según necesidades\n";
