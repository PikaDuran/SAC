<?php

// Test rápido del procesador con estadísticas mejoradas
require_once 'procesador_cfdi_completo.php';

echo "=== TEST DE ESTADÍSTICAS MEJORADAS ===\n\n";

try {
    $pdo = getDatabase();
    $procesador = new ProcesadorCFDICompleto($pdo);

    echo "✓ Conectado a la base de datos\n";
    echo "🧹 Limpiando tablas para prueba limpia...\n";
    $procesador->limpiarTablas();

    echo "🔄 Ejecutando procesamiento con estadísticas detalladas...\n\n";
    $procesador->procesarDirectorio('storage/sat_downloads');
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL TEST ===\n";
