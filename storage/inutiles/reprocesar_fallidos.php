<?php
/**
 * REPROCESADOR DE ARCHIVOS FALLIDOS
 * Permite reprocesar archivos que fallaron en la importación inicial
 */

require_once __DIR__ . '/importador_completo_definitivo.php';

if ($argc < 2) {
    echo "USO:\n";
    echo "  php " . basename(__FILE__) . " <archivo_lista_fallidos>\n\n";
    echo "EJEMPLO:\n";
    echo "  php " . basename(__FILE__) . " logs/archivos_fallidos_2025-08-26_15-30-00.txt\n\n";
    exit(1);
}

$archivo_lista = $argv[1];

try {
    $importador = new ImportadorCompletoSAT();
    $importador->reprocesarFallidos($archivo_lista);
} catch (Exception $e) {
    echo "❌ Error crítico: " . $e->getMessage() . "\n";
    exit(1);
}
?>
