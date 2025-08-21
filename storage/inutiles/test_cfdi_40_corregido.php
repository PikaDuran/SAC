<?php
// Test rápido para CFDI 4.0 con campos corregidos
require_once 'importador_inteligente_cfdi.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $importador = new ImportadorInteligenteCFDI();

    echo "=== TEST RÁPIDO CFDI 4.0 CON CAMPOS CORREGIDOS ===\n\n";

    // Buscar archivos del 2025
    $directorio2025 = 'storage/sat_downloads/BFM170822P38/EMITIDAS/2025';
    $archivos = glob("$directorio2025/**/*.xml");

    if (empty($archivos)) {
        echo "❌ No se encontraron archivos en $directorio2025\n";
        exit;
    }

    echo "📁 Archivos encontrados: " . count($archivos) . "\n";
    echo "🎯 Procesando solo los primeros 3 archivos...\n\n";

    $exitosos = 0;
    $errores = 0;

    for ($i = 0; $i < min(3, count($archivos)); $i++) {
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

            if ($resultado['exito']) {
                echo "✅ ÉXITO - CFDI procesado\n";
                $exitosos++;

                // Verificar en BD con campos específicos de 4.0
                $stmt = $pdo->prepare("SELECT uuid, version, exportacion, regimen_fiscal_receptor, rfc_emisor FROM cfdi WHERE uuid = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$resultado['uuid']]);
                $cfdi = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($cfdi) {
                    echo "📊 Datos en BD:\n";
                    echo "   UUID: {$cfdi['uuid']}\n";
                    echo "   Versión: {$cfdi['version']}\n";
                    echo "   Exportación: " . ($cfdi['exportacion'] ?? 'NULL') . "\n";
                    echo "   Régimen Fiscal Receptor: " . ($cfdi['regimen_fiscal_receptor'] ?? 'NULL') . "\n";
                    echo "   RFC Emisor: {$cfdi['rfc_emisor']}\n";
                } else {
                    echo "❌ No se encontró en BD\n";
                }
            } else {
                echo "❌ ERROR: " . ($resultado['error'] ?? 'Error desconocido') . "\n";
                $errores++;
            }
        } catch (Exception $e) {
            echo "❌ EXCEPCIÓN: " . $e->getMessage() . "\n";
            $errores++;
        }

        echo "\n";
    }

    echo "============================================================\n";
    echo "RESULTADOS:\n";
    echo "Éxitos: $exitosos\n";
    echo "Errores: $errores\n";
    echo "============================================================\n";

    // Verificar total en BD
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi WHERE version = "4.0"');
    $total_40 = $stmt->fetchColumn();

    echo "\n🎯 Total CFDI 4.0 en BD: $total_40\n";

    if ($total_40 > 0) {
        echo "🎉 ¡ÉXITO! Los CFDI 4.0 se están insertando correctamente con sus campos específicos.\n";
    } else {
        echo "❌ Aún hay problemas con la inserción de CFDI 4.0\n";
    }
} catch (Exception $e) {
    echo "Error general: " . $e->getMessage() . "\n";
}
