<?php

require_once 'src/config/database.php';

echo "=== DIAGNÓSTICO SIMPLE DE ARCHIVOS NO PROCESADOS ===\n\n";

try {
    $pdo = getDatabase();

    echo "✓ Conexión establecida\n";

    // Obtener estadísticas de la base de datos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi");
    $totalCfdi = $stmt->fetch()['total'];

    echo "📊 CFDIs en base de datos: $totalCfdi\n";

    // Contar archivos XML
    $directorio = 'storage/sat_downloads';
    $command = 'Get-ChildItem "' . $directorio . '" -Recurse -Filter "*.xml" | Measure-Object | Select-Object -ExpandProperty Count';
    $totalArchivos = (int)shell_exec("powershell -Command \"$command\"");

    echo "📁 Total archivos XML: $totalArchivos\n";
    echo "❌ Archivos no procesados: " . ($totalArchivos - $totalCfdi) . "\n\n";

    // Verificar algunos archivos problemáticos
    echo "=== VERIFICANDO ARCHIVOS PROBLEMÁTICOS ===\n";

    // Obtener archivos XML de una carpeta específica
    $archivos = glob($directorio . '/*/EMITIDAS/2020/1/*.xml');
    $muestra = array_slice($archivos, 0, 10);

    echo "📋 Verificando " . count($muestra) . " archivos de muestra...\n\n";

    foreach ($muestra as $archivo) {
        $nombreArchivo = basename($archivo);

        // Extraer UUID del nombre del archivo
        if (preg_match('/([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})/', $nombreArchivo, $matches)) {
            $uuidArchivo = $matches[1];

            // Verificar si existe en la base de datos
            $stmt = $pdo->prepare("SELECT COUNT(*) as existe FROM cfdi WHERE uuid = ?");
            $stmt->execute([$uuidArchivo]);
            $existe = $stmt->fetch()['existe'];

            if ($existe > 0) {
                echo "✅ $nombreArchivo -> UUID: $uuidArchivo (PROCESADO)\n";
            } else {
                echo "❌ $nombreArchivo -> UUID: $uuidArchivo (NO PROCESADO)\n";

                // Verificar si el archivo es legible
                $tamaño = filesize($archivo);
                echo "   📏 Tamaño: $tamaño bytes\n";

                if ($tamaño > 0) {
                    $contenido = file_get_contents($archivo, false, null, 0, 500);
                    if (strpos($contenido, 'cfdi:Comprobante') !== false) {
                        echo "   ✅ Parece ser un CFDI válido\n";
                    } else {
                        echo "   ❌ No parece ser un CFDI válido\n";
                    }
                }
            }
        } else {
            echo "⚠️  $nombreArchivo -> Sin UUID en nombre\n";
        }
        echo "\n";
    }

    // Verificar tipos de comprobante
    echo "=== TIPOS DE COMPROBANTE EN BD ===\n";
    $stmt = $pdo->query("SELECT tipo, COUNT(*) as cantidad FROM cfdi GROUP BY tipo ORDER BY cantidad DESC");
    while ($row = $stmt->fetch()) {
        echo "📋 Tipo '{$row['tipo']}': {$row['cantidad']} registros\n";
    }

    echo "\n=== VERSIONES DE CFDI ===\n";
    $stmt = $pdo->query("SELECT version, COUNT(*) as cantidad FROM cfdi GROUP BY version ORDER BY cantidad DESC");
    while ($row = $stmt->fetch()) {
        echo "📋 Versión '{$row['version']}': {$row['cantidad']} registros\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
