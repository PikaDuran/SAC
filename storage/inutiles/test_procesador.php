<?php

// Test del procesador CFDI completo
require_once 'procesador_cfdi_completo.php';

echo "=== INICIANDO PRUEBA DEL PROCESADOR CFDI COMPLETO ===\n\n";

try {
    // Obtener conexión a la base de datos
    $pdo = getDatabase();
    echo "✓ Conexión a base de datos establecida\n";

    // Crear el procesador
    $procesador = new ProcesadorCFDICompleto($pdo);
    echo "✓ Procesador inicializado correctamente\n\n";

    echo "Iniciando procesamiento de archivos XML...\n";
    echo "==========================================\n\n";

    // Usar modo incremental (no limpia tablas)
    $resultado = $procesador->procesarDirectorio('storage/sat_downloads', false);

    echo "\n=== PROCESAMIENTO COMPLETADO ===\n";
    echo "Resultado: " . ($resultado ? "ÉXITO" : "ERROR") . "\n";
} catch (Exception $e) {
    echo "❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";
