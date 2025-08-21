<?php

// Procesador CFDI desde cero (limpia tablas)
require_once 'procesador_cfdi_completo.php';

echo "=== PROCESAMIENTO CFDI DESDE CERO ===\n";
echo "⚠️  ADVERTENCIA: Esto borrará todos los datos existentes\n\n";

try {
    $pdo = getDatabase();
    $procesador = new ProcesadorCFDICompleto($pdo);

    echo "✓ Conexión establecida\n";

    // Verificar si hay datos existentes
    $stmt = $pdo->query("SELECT COUNT(*) FROM cfdi");
    $existentes = $stmt->fetchColumn();

    if ($existentes > 0) {
        echo "📊 Se encontraron $existentes CFDIs existentes\n";
        echo "🗑️  Estos serán eliminados y procesados nuevamente\n\n";
    } else {
        echo "📊 Base de datos vacía\n\n";
    }

    // Configurar memoria y tiempo
    ini_set('memory_limit', '2G');
    set_time_limit(0);

    echo "🚀 Iniciando procesamiento completo (con limpieza de tablas)...\n\n";

    // Procesar con limpieza de tablas
    $procesador->procesarDirectorio('storage/sat_downloads', true);

    echo "\n🎉 Procesamiento desde cero completado exitosamente\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN DEL PROCESAMIENTO ===\n";
