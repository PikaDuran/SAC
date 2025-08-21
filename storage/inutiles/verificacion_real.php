<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== VERIFICACIÓN REAL SIN SIMULACIONES ===\n\n";

echo "1. VERIFICAR QUE ARCHIVOS EXISTEN REALMENTE EN 2025\n";
echo "====================================================\n";

$ruta_2025 = 'C:/xampp/htdocs/SAC/storage/sat_downloads/BFM170822P38/EMITIDAS/2025';

if (!is_dir($ruta_2025)) {
    echo "❌ LA CARPETA 2025 NO EXISTE: $ruta_2025\n";
    exit();
}

echo "✅ Carpeta 2025 existe: $ruta_2025\n";

// Listar contenido real
$subdirs = scandir($ruta_2025);
echo "Contenido de la carpeta 2025:\n";
foreach ($subdirs as $item) {
    if ($item != '.' && $item != '..') {
        $full_path = $ruta_2025 . '/' . $item;
        if (is_dir($full_path)) {
            echo "  📁 Carpeta: $item\n";
            $files = glob($full_path . '/*.xml');
            echo "      Archivos XML: " . count($files) . "\n";
            if (count($files) > 0) {
                // Verificar fechas reales del primer archivo
                $primer_archivo = $files[0];
                $contenido = file_get_contents($primer_archivo);
                if (preg_match('/Fecha="([^"]+)"/', $contenido, $matches)) {
                    echo "      Fecha en XML: " . $matches[1] . "\n";
                }
                if (preg_match('/Version="([^"]+)"/', $contenido, $matches)) {
                    echo "      Versión CFDI: " . $matches[1] . "\n";
                }
            }
        }
    }
}

echo "\n2. VERIFICAR BASE DE DATOS ACTUAL\n";
echo "==================================\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM cfdi");
    $total = $stmt->fetchColumn();
    echo "Total registros en BD: $total\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM cfdi WHERE YEAR(fecha) = 2025");
    $registros_2025 = $stmt->fetchColumn();
    echo "Registros de 2025: $registros_2025\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM cfdi WHERE YEAR(fecha) = 2020");
    $registros_2020 = $stmt->fetchColumn();
    echo "Registros de 2020: $registros_2020\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM cfdi WHERE version = '4.0'");
    $cfdi_40 = $stmt->fetchColumn();
    echo "CFDI 4.0 en BD: $cfdi_40\n";
    
} catch (Exception $e) {
    echo "Error BD: " . $e->getMessage() . "\n";
}

echo "\n3. CONCLUSIÓN REAL\n";
echo "==================\n";

if ($registros_2025 == 0 && $registros_2020 > 0) {
    echo "❌ CONFIRMADO: No hay datos de 2025 en la BD\n";
    echo "❌ CONFIRMADO: Solo hay datos históricos del 2020\n";
    echo "❌ CONFIRMADO: El sistema NO está procesando archivos de 2025\n";
    echo "❌ El problema está en el código, no en los datos\n";
} else {
    echo "✅ Hay datos de 2025 en la BD\n";
}

echo "\n=== FIN VERIFICACIÓN REAL ===\n";
?>
