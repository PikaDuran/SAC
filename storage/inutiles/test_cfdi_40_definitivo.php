<?php
// Test definitivo para CFDI 4.0 con commit manual
require_once 'importador_inteligente_cfdi.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Iniciar transacción manual
    $pdo->beginTransaction();

    $importador = new ImportadorInteligenteCFDI();

    echo "=== TEST DEFINITIVO CFDI 4.0 CON COMMIT MANUAL ===\n\n";

    // Buscar archivos del 2025
    $directorio2025 = 'storage/sat_downloads/BFM170822P38/EMITIDAS/2025';
    $archivos = glob("$directorio2025/**/*.xml");

    if (empty($archivos)) {
        echo "❌ No se encontraron archivos en $directorio2025\n";
        exit;
    }

    echo "📁 Archivos encontrados: " . count($archivos) . "\n";
    echo "🎯 Procesando los primeros 5 archivos con commit manual...\n\n";

    $exitosos = 0;
    $errores = 0;

    for ($i = 0; $i < min(5, count($archivos)); $i++) {
        $archivo = $archivos[$i];
        $nombreArchivo = basename($archivo);

        echo "--- ARCHIVO " . ($i + 1) . " ---\n";
        echo "📁 $nombreArchivo\n";

        try {
            // Leer y verificar versión
            $contenido = file_get_contents($archivo);
            if (preg_match('/Version="([^"]+)"/', $contenido, $matches)) {
                $version = $matches[1];
                echo "📋 Versión: $version\n";

                if ($version != '4.0') {
                    echo "⚠️ No es CFDI 4.0, saltando...\n\n";
                    continue;
                }
            }

            // Procesar archivo usando reflexión
            $reflection = new ReflectionClass($importador);
            $metodo = $reflection->getMethod('procesarArchivo');
            $metodo->setAccessible(true);

            $resultado = $metodo->invoke($importador, $archivo);

            echo "✅ Archivo procesado\n";
            $exitosos++;
        } catch (Exception $e) {
            echo "❌ EXCEPCIÓN: " . $e->getMessage() . "\n";
            $errores++;
        }

        echo "\n";
    }

    // HACER COMMIT MANUAL - ESTO ES CLAVE
    echo "🔄 Haciendo commit manual...\n";
    $pdo->commit();
    echo "✅ Commit realizado\n\n";

    echo "============================================================\n";
    echo "RESULTADOS:\n";
    echo "Archivos procesados: " . ($exitosos + $errores) . "\n";
    echo "Éxitos: $exitosos\n";
    echo "Errores: $errores\n";
    echo "============================================================\n";

    // Verificar total en BD después del commit
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi WHERE version = "4.0"');
    $total_40 = $stmt->fetchColumn();

    echo "\n🎯 Total CFDI 4.0 en BD después del commit: $total_40\n";

    if ($total_40 > 0) {
        echo "🎉 ¡ÉXITO! Los CFDI 4.0 se insertaron correctamente.\n\n";

        // Mostrar ejemplos
        echo "📋 EJEMPLOS DE CFDI 4.0 INSERTADOS:\n";
        $stmt = $pdo->query('SELECT uuid, version, exportacion, regimen_fiscal_receptor, fecha FROM cfdi WHERE version = "4.0" LIMIT 5');
        $ejemplos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ejemplos as $i => $e) {
            echo "\n--- CFDI 4.0 #" . ($i + 1) . " ---\n";
            echo "UUID: {$e['uuid']}\n";
            echo "Versión: {$e['version']}\n";
            echo "Fecha: {$e['fecha']}\n";
            echo "Exportación: " . ($e['exportacion'] ?? 'NULL') . "\n";
            echo "Régimen Fiscal Receptor: " . ($e['regimen_fiscal_receptor'] ?? 'NULL') . "\n";
        }
    } else {
        echo "❌ Aún hay problemas con la inserción de CFDI 4.0\n";
    }
} catch (Exception $e) {
    echo "Error general: " . $e->getMessage() . "\n";
    if (isset($pdo)) {
        $pdo->rollback();
    }
}
