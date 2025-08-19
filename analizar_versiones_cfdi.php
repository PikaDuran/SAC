<?php
require 'src/config/database.php';

// Función para analizar versiones de CFDI en los archivos XML
function analizarVersionesCFDI($directorio) {
    $versiones = [];
    $totalArchivos = 0;
    $archivosConError = 0;
    $ejemplosVersion = [];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directorio),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    echo "Analizando versiones de CFDI en archivos XML...\n\n";
    
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
            $totalArchivos++;
            
            if ($totalArchivos % 1000 == 0) {
                echo "Procesados: {$totalArchivos} archivos...\n";
            }
            
            try {
                $contenido = file_get_contents($file->getPathname());
                if ($contenido === false) continue;
                
                // Limpiar el contenido XML
                $contenido = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $contenido);
                
                // Buscar la versión en diferentes formatos
                $version = null;
                
                // Buscar Version="X.X" en el comprobante
                if (preg_match('/Version\s*=\s*["\']([^"\']+)["\']/', $contenido, $matches)) {
                    $version = $matches[1];
                } 
                // Buscar version="X.X" (minúscula)
                elseif (preg_match('/version\s*=\s*["\']([^"\']+)["\']/', $contenido, $matches)) {
                    $version = $matches[1];
                }
                // Buscar en el namespace
                elseif (preg_match('/http:\/\/www\.sat\.gob\.mx\/cfd\/(\d+)/', $contenido, $matches)) {
                    $version = $matches[1] . '.0';
                }
                
                if ($version) {
                    if (!isset($versiones[$version])) {
                        $versiones[$version] = 0;
                        $ejemplosVersion[$version] = [];
                    }
                    $versiones[$version]++;
                    
                    // Guardar algunos ejemplos de cada versión
                    if (count($ejemplosVersion[$version]) < 3) {
                        $ejemplosVersion[$version][] = $file->getPathname();
                    }
                } else {
                    $archivosConError++;
                }
                
                // Limitar análisis para prueba inicial
                if ($totalArchivos >= 5000) {
                    echo "Limitando análisis a 5000 archivos para prueba...\n";
                    break;
                }
                
            } catch (Exception $e) {
                $archivosConError++;
                continue;
            }
        }
    }
    
    return [
        'versiones' => $versiones,
        'totalArchivos' => $totalArchivos,
        'archivosConError' => $archivosConError,
        'ejemplos' => $ejemplosVersion
    ];
}

// Función para analizar la estructura de un archivo específico
function analizarEstructuraXML($archivo) {
    echo "\n=== ANÁLISIS DE ESTRUCTURA: " . basename($archivo) . " ===\n";
    
    try {
        $contenido = file_get_contents($archivo);
        if ($contenido === false) {
            echo "Error: No se pudo leer el archivo\n";
            return;
        }
        
        // Mostrar primeras líneas del archivo
        $lineas = explode("\n", $contenido);
        echo "Primeras 5 líneas del archivo:\n";
        for ($i = 0; $i < min(5, count($lineas)); $i++) {
            echo ($i + 1) . ": " . trim($lineas[$i]) . "\n";
        }
        
        // Buscar información clave
        echo "\nInformación extraída:\n";
        
        // Versión
        if (preg_match('/Version\s*=\s*["\']([^"\']+)["\']/', $contenido, $matches)) {
            echo "- Versión CFDI: " . $matches[1] . "\n";
        }
        
        // Fecha
        if (preg_match('/Fecha\s*=\s*["\']([^"\']+)["\']/', $contenido, $matches)) {
            echo "- Fecha: " . $matches[1] . "\n";
        }
        
        // UUID en TimbreFiscalDigital
        if (preg_match('/UUID\s*=\s*["\']([^"\']+)["\']/', $contenido, $matches)) {
            echo "- UUID: " . $matches[1] . "\n";
        }
        
        // Emisor RFC
        if (preg_match('/Rfc\s*=\s*["\']([^"\']+)["\']/', $contenido, $matches)) {
            echo "- RFC Emisor: " . $matches[1] . "\n";
        }
        
        // Tipo de comprobante
        if (preg_match('/TipoDeComprobante\s*=\s*["\']([^"\']+)["\']/', $contenido, $matches)) {
            echo "- Tipo de Comprobante: " . $matches[1] . "\n";
        }
        
        // Buscar complementos
        if (strpos($contenido, 'TimbreFiscalDigital') !== false) {
            echo "- Contiene: TimbreFiscalDigital ✓\n";
        }
        
        if (strpos($contenido, 'Pagos') !== false) {
            echo "- Contiene: Complemento de Pagos ✓\n";
        }
        
    } catch (Exception $e) {
        echo "Error al analizar archivo: " . $e->getMessage() . "\n";
    }
}

try {
    $directorio = __DIR__ . '/storage/sat_downloads';
    
    if (!is_dir($directorio)) {
        echo "Error: El directorio {$directorio} no existe\n";
        exit(1);
    }
    
    echo "=== ANÁLISIS DE VERSIONES DE CFDI ===\n";
    echo "Directorio: {$directorio}\n\n";
    
    $resultado = analizarVersionesCFDI($directorio);
    
    echo "\n=== RESULTADOS DEL ANÁLISIS ===\n";
    echo "Total de archivos XML procesados: " . $resultado['totalArchivos'] . "\n";
    echo "Archivos con errores: " . $resultado['archivosConError'] . "\n\n";
    
    echo "DISTRIBUCIÓN POR VERSIONES:\n";
    arsort($resultado['versiones']);
    
    foreach ($resultado['versiones'] as $version => $cantidad) {
        $porcentaje = round(($cantidad / $resultado['totalArchivos']) * 100, 2);
        echo "- CFDI {$version}: {$cantidad} archivos ({$porcentaje}%)\n";
    }
    
    echo "\n=== ANÁLISIS DETALLADO DE EJEMPLOS ===\n";
    
    foreach ($resultado['ejemplos'] as $version => $archivos) {
        echo "\n--- CFDI VERSION {$version} ---\n";
        if (!empty($archivos)) {
            analizarEstructuraXML($archivos[0]);
        }
    }
    
    echo "\n=== RECOMENDACIONES ===\n";
    echo "Basándome en tu información:\n";
    echo "- CFDI 4.0 es obligatorio desde 1-abr-2023\n";
    echo "- Archivos anteriores pueden ser versión 3.3\n";
    echo "- El importador debe manejar ambas versiones\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
