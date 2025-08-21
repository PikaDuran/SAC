<?php

/**
 * Debug específico para verificar extracción de timbre fiscal
 */

// Tomar el primer archivo XML de la muestra (en subdirectorios)
$directorio = "storage/sat_downloads";
$archivos = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio));

foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
        $archivos[] = $file->getPathname();
        if (count($archivos) >= 1) break; // Solo tomar 1 archivo
    }
}

if (empty($archivos)) {
    echo "No se encontraron archivos XML\n";
    exit;
}

// Tomar el primer archivo
$archivo = $archivos[0];
echo "Analizando archivo: " . basename($archivo) . "\n\n";

$contenido = file_get_contents($archivo);
if (!$contenido) {
    echo "Error al leer el archivo\n";
    exit;
}

echo "Tamaño del archivo: " . strlen($contenido) . " bytes\n\n";

// Buscar TimbreFiscalDigital
echo "=== BÚSQUEDA DE TIMBRE FISCAL ===\n";

// Buscar todas las coincidencias de TimbreFiscalDigital
if (preg_match_all('/<tfd:TimbreFiscalDigital[^>]*>/i', $contenido, $matches)) {
    echo "✅ Encontrados " . count($matches[0]) . " timbres fiscales:\n";
    foreach ($matches[0] as $i => $match) {
        echo "Timbre " . ($i + 1) . ": " . htmlspecialchars($match) . "\n\n";
    }
} else {
    echo "❌ No se encontró TimbreFiscalDigital con el patrón básico\n";
}

// Intentar búsqueda más flexible
if (preg_match_all('/TimbreFiscalDigital/i', $contenido, $matches)) {
    echo "✅ Encontradas " . count($matches[0]) . " menciones de 'TimbreFiscalDigital'\n";
} else {
    echo "❌ No se encontró ninguna mención de 'TimbreFiscalDigital'\n";
}

// Buscar otros patrones comunes del namespace
if (preg_match_all('/<[^:]*:TimbreFiscalDigital[^>]*>/i', $contenido, $matches)) {
    echo "✅ Encontrados con namespace flexible: " . count($matches[0]) . "\n";
    foreach ($matches[0] as $i => $match) {
        echo "Match " . ($i + 1) . ": " . htmlspecialchars($match) . "\n";
    }
}

// Mostrar una porción del XML para verificar estructura
echo "\n=== ESTRUCTURA XML (primeros 2000 caracteres) ===\n";
echo htmlspecialchars(substr($contenido, 0, 2000)) . "\n";

echo "\n=== ESTRUCTURA XML (últimos 1000 caracteres) ===\n";
echo htmlspecialchars(substr($contenido, -1000)) . "\n";
