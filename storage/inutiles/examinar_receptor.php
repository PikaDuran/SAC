<?php
$dir = 'storage/sat_downloads/BFM170822P38/EMITIDAS/2025/1';
$files = glob($dir . '/*.xml');

if (!empty($files)) {
    echo "Examinando archivo: " . basename($files[0]) . "\n";
    $content = file_get_contents($files[0]);

    // Buscar el elemento Receptor completo
    if (preg_match('/<cfdi:Receptor[^>]*>/', $content, $matches)) {
        echo "Receptor encontrado:\n";
        echo $matches[0] . "\n\n";

        // Verificar si tiene RegimenFiscalReceptor
        if (strpos($matches[0], 'RegimenFiscalReceptor') !== false) {
            echo "✅ Contiene RegimenFiscalReceptor\n";
            if (preg_match('/RegimenFiscalReceptor\s*=\s*["\']([^"\']*)["\']/', $matches[0], $regimen)) {
                echo "Valor: " . $regimen[1] . "\n";
            }
        } else {
            echo "❌ NO contiene RegimenFiscalReceptor\n";
        }
    } else {
        echo "No se encontró el elemento Receptor\n";
    }

    // También examinar una sección más amplia del XML
    echo "\n--- Sección completa del Receptor ---\n";
    if (preg_match('/<cfdi:Receptor[^>]*(?:\/>|>.*?<\/cfdi:Receptor>)/s', $content, $receptorCompleto)) {
        echo $receptorCompleto[0] . "\n";
    }
} else {
    echo "No se encontraron archivos XML en el directorio: $dir\n";
}
