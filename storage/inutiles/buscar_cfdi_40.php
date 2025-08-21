<?php
echo "=== BUSCANDO ARCHIVOS CFDI 4.0 ===\n\n";

// Buscar en la estructura correcta: RFC/EMITIDAS|RECIBIDAS/año/mes/
$patrones = [
    'storage/sat_downloads/*/EMITIDAS/2025/*/*.xml',
    'storage/sat_downloads/*/RECIBIDAS/2025/*/*.xml',
    'storage/sat_downloads/*/EMITIDAS/2024/*/*.xml',
    'storage/sat_downloads/*/RECIBIDAS/2024/*/*.xml'
];

$archivos = [];
foreach ($patrones as $patron) {
    $encontrados = glob($patron);
    $archivos = array_merge($archivos, $encontrados);
}

echo "Total archivos XML encontrados: " . count($archivos) . "\n\n";

$cfdi40_encontrados = [];
$procesados = 0;

foreach ($archivos as $archivo) {
    if (file_exists($archivo)) {
        $contenido = file_get_contents($archivo);

        if (strpos($contenido, 'Version="4.0"') !== false) {
            echo "✅ CFDI 4.0 encontrado: " . basename($archivo) . "\n";

            // Extraer UUID
            if (preg_match('/UUID="([^"]+)"/', $contenido, $matches)) {
                $uuid = $matches[1];
                echo "   UUID: $uuid\n";

                $cfdi40_encontrados[] = [
                    'archivo' => $archivo,
                    'uuid' => $uuid
                ];
            }

            if (count($cfdi40_encontrados) >= 3) break;
        }
    }

    $procesados++;
    if ($procesados % 1000 == 0) {
        echo "Procesados: $procesados archivos...\n";
    }

    // Limitar búsqueda
    if ($procesados >= 5000) break;
}

echo "\n📊 RESULTADO:\n";
echo "Archivos CFDI 4.0 encontrados: " . count($cfdi40_encontrados) . "\n";

if (!empty($cfdi40_encontrados)) {
    echo "\n🎯 Archivos CFDI 4.0 disponibles para procesar:\n";
    foreach ($cfdi40_encontrados as $i => $cfdi) {
        echo ($i + 1) . ". " . basename($cfdi['archivo']) . " (UUID: " . $cfdi['uuid'] . ")\n";
    }
} else {
    echo "\n❌ No se encontraron archivos CFDI 4.0 en los primeros $procesados archivos\n";
}
