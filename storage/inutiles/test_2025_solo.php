<?php

/**
 * Prueba 1: Procesar solo 2025 (CFDI 4.0 puro)
 */

require_once 'importador_inteligente_cfdi.php';

echo "=== PRUEBA 1: PROCESANDO SOLO 2025 (CFDI 4.0 PURO) ===\n\n";

try {
    $importador = new ImportadorInteligenteCFDI();

    // Procesar solo 2025
    $directorio = 'storage/sat_downloads';
    $resultado = $importador->importar($directorio);

    echo "\n=== RESULTADOS FINALES ===\n";
    echo "Total procesados: " . ($resultado['procesados'] ?? 0) . "\n";
    echo "Insertados: " . ($resultado['insertados'] ?? 0) . "\n";
    echo "Errores: " . ($resultado['errores'] ?? 0) . "\n";
    echo "Complementos de pago: " . ($resultado['complementos_pago'] ?? 0) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
