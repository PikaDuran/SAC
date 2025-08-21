<?php

require_once 'procesador_cfdi_completo.php';

echo "=== DIAGNÓSTICO DE ARCHIVOS NO PROCESADOS ===\n\n";

try {
    $pdo = getDatabase();
    $procesador = new ProcesadorCFDICompleto($pdo);

    echo "✓ Conexión establecida\n";

    // Obtener lista de archivos procesados exitosamente
    $stmt = $pdo->query("SELECT uuid FROM cfdi");
    $procesados = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "📊 CFDIs en base de datos: " . count($procesados) . "\n\n";

    // Obtener todos los archivos XML
    $directorio = 'storage/sat_downloads';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio));
    $archivos = [];

    foreach ($iterator as $archivo) {
        if ($archivo->isFile() && $archivo->getExtension() === 'xml') {
            $archivos[] = $archivo->getPathname();
        }
    }

    echo "📁 Total archivos XML encontrados: " . count($archivos) . "\n";

    $errores = 0;
    $sinUUID = 0;
    $archivosDanados = 0;
    $ejemplosErrores = [];

    // Revisar una muestra de archivos no procesados
    $contador = 0;
    foreach ($archivos as $archivo) {
        $contador++;

        // Solo revisar los primeros 1000 para no saturar
        if ($contador > 1000) break;

        try {
            $contenido = file_get_contents($archivo);

            if (!$contenido || strlen($contenido) < 100) {
                $archivosDanados++;
                $ejemplosErrores[] = "Archivo vacío/dañado: " . basename($archivo);
                continue;
            }

            // Extraer UUID
            if (preg_match('/UUID="([^"]+)"/', $contenido, $matches)) {
                $uuid = $matches[1];

                // Verificar si ya está procesado
                if (!in_array($uuid, $procesados)) {
                    $errores++;
                    if (count($ejemplosErrores) < 5) {
                        $ejemplosErrores[] = "UUID no procesado: $uuid - " . basename($archivo);
                    }
                }
            } else {
                $sinUUID++;
                if (count($ejemplosErrores) < 5) {
                    $ejemplosErrores[] = "Sin UUID: " . basename($archivo);
                }
            }
        } catch (Exception $e) {
            $archivosDanados++;
            if (count($ejemplosErrores) < 5) {
                $ejemplosErrores[] = "Error al leer: " . basename($archivo) . " - " . $e->getMessage();
            }
        }
    }

    echo "\n=== DIAGNÓSTICO (muestra de 1000 archivos) ===\n";
    echo "❌ Archivos sin UUID: $sinUUID\n";
    echo "❌ Archivos dañados/vacíos: $archivosDanados\n";
    echo "❌ UUIDs no procesados: $errores\n\n";

    echo "=== EJEMPLOS DE ERRORES ===\n";
    foreach ($ejemplosErrores as $ejemplo) {
        echo "• $ejemplo\n";
    }

    // Verificar si hay duplicados en la base de datos
    echo "\n=== VERIFICANDO DUPLICADOS ===\n";
    $stmt = $pdo->query("SELECT uuid, COUNT(*) as cantidad FROM cfdi GROUP BY uuid HAVING cantidad > 1 LIMIT 10");
    $duplicados = $stmt->fetchAll();

    if (count($duplicados) > 0) {
        echo "⚠️  UUIDs duplicados encontrados:\n";
        foreach ($duplicados as $dup) {
            echo "• UUID: {$dup['uuid']} - Cantidad: {$dup['cantidad']}\n";
        }
    } else {
        echo "✅ No hay UUIDs duplicados\n";
    }

    // Verificar archivos muy grandes o muy pequeños
    echo "\n=== VERIFICANDO TAMAÑOS DE ARCHIVO ===\n";
    $archivosChicos = 0;
    $archivosGrandes = 0;

    foreach (array_slice($archivos, 0, 1000) as $archivo) {
        $tamaño = filesize($archivo);
        if ($tamaño < 1000) {
            $archivosChicos++;
        } elseif ($tamaño > 1000000) { // > 1MB
            $archivosGrandes++;
        }
    }

    echo "📏 Archivos muy pequeños (<1KB): $archivosChicos\n";
    echo "📏 Archivos muy grandes (>1MB): $archivosGrandes\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
