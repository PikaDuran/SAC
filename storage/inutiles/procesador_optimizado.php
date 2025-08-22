<?php

// Procesador CFDI optimizado para grandes volúmenes
require_once 'procesador_cfdi_completo.php';

echo "=== PROCESADOR CFDI OPTIMIZADO ===\n";
echo "Procesamiento en lotes de 500 archivos\n";
echo "Reportes cada 5000 archivos\n\n";

try {
    // Configurar memoria y tiempo para procesamiento grande
    ini_set('memory_limit', '2G');
    set_time_limit(0); // Sin límite de tiempo

    $pdo = getDatabase();
    $procesador = new ProcesadorCFDICompleto($pdo);

    echo "✓ Conexión establecida\n";
    echo "✓ Memoria configurada: 2GB\n";
    echo "✓ Sin límite de tiempo\n\n";

    // Verificar si ya hay datos
    $stmt = $pdo->query("SELECT COUNT(*) FROM cfdi");
    $existentes = $stmt->fetchColumn();

    if ($existentes > 0) {
        echo "ℹ️  Base de datos contiene $existentes CFDIs existentes\n";
        echo "¿Deseas procesar incremental (solo archivos nuevos)? [s/n]: ";

        // Para automatizar, vamos a procesar incremental por defecto
        $incremental = true;
        echo "s (modo automático)\n\n";

        if ($incremental) {
            echo "🔄 Procesando en modo INCREMENTAL (no limpia tablas)\n";
            $procesador->procesarDirectorio('storage/sat_downloads', false);
        } else {
            echo "🧹 Procesando en modo COMPLETO (limpia tablas)\n";
            $procesador->procesarDirectorio('storage/sat_downloads', true);
        }
    } else {
        echo "📊 Base de datos vacía - procesamiento completo\n\n";
        $procesador->procesarDirectorio('storage/sat_downloads', true);
    }

    echo "\n🎉 Procesamiento completado exitosamente\n";
} catch (Exception $e) {
    echo "❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN DEL PROCESAMIENTO ===\n";
