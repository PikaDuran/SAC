<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'importador_inteligente_cfdi.php';

echo "=== TEST INDIVIDUAL CFDI ===\n\n";

try {
    $importador = new ImportadorInteligenteCFDI();
    
    // Buscar un archivo para test
    $rutaBase = 'C:/xampp/htdocs/SAC/storage/sat_downloads';
    $archivos = glob($rutaBase . '/*/RECIBIDAS/*/*.xml');
    
    if (empty($archivos)) {
        $archivos = glob($rutaBase . '/*/EMITIDAS/*/*.xml');
    }
    
    if (empty($archivos)) {
        echo "❌ No se encontraron archivos XML\n";
        exit();
    }
    
    $archivo = $archivos[0];
    echo "📁 Procesando: " . basename($archivo) . "\n";
    echo "📂 Ruta: $archivo\n\n";
    
    // Usar reflection para acceder al método privado
    $reflection = new ReflectionClass($importador);
    $metodo = $reflection->getMethod('procesarArchivo');
    $metodo->setAccessible(true);
    
    echo "Ejecutando procesarArchivo...\n";
    $resultado = $metodo->invoke($importador, $archivo);
    
    if ($resultado) {
        echo "✅ Archivo procesado exitosamente\n";
    } else {
        echo "❌ Error al procesar archivo\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN TEST INDIVIDUAL ===\n";
?>
