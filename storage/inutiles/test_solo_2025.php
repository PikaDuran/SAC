<?php
require_once 'importador_inteligente_cfdi.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=sac_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== PROCESANDO SOLO ARCHIVOS 2025 (CFDI 4.0) ===\n\n";

    // Buscar SOLO en la carpeta 2025
    $ruta_2025 = 'storage/sat_downloads/BFM170822P38/EMITIDAS/2025';

    if (!is_dir($ruta_2025)) {
        echo "❌ La carpeta 2025 no existe: $ruta_2025\n";
        exit;
    }

    // Buscar archivos XML SOLO en 2025
    $archivos_2025 = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta_2025));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'xml') {
            $archivos_2025[] = $file->getPathname();
        }
    }

    echo "📁 Archivos XML encontrados en 2025: " . count($archivos_2025) . "\n\n";

    if (empty($archivos_2025)) {
        echo "❌ No se encontraron archivos XML en 2025\n";
        exit;
    }

    $importador = new ImportadorInteligenteCFDI($pdo);
    $exitosos = 0;
    $errores = 0;
    $cfdi_40 = 0;
    $cfdi_33 = 0;

    // Procesar solo los primeros 5 archivos para test
    foreach (array_slice($archivos_2025, 0, 5) as $i => $archivo) {
        echo "--- ARCHIVO " . ($i + 1) . " ---\n";
        echo "📁 " . basename($archivo) . "\n";

        $contenido = file_get_contents($archivo);

        // Verificar versión
        if (preg_match('/Version="([^"]+)"/', $contenido, $matches)) {
            $version = $matches[1];
            echo "📋 Versión: $version\n";

            if ($version === '4.0') {
                $cfdi_40++;
                echo "✅ CFDI 4.0 confirmado\n";
            } else {
                $cfdi_33++;
                echo "⚠️  CFDI $version (no es 4.0)\n";
            }
        }

        // Extraer UUID
        $uuid = null;
        if (preg_match('/UUID="([^"]+)"/', $contenido, $matches)) {
            $uuid = $matches[1];
            echo "🔑 UUID: $uuid\n";
        }

        // Procesar el archivo
        echo "🚀 Procesando...\n";

        try {
            $reflection = new ReflectionClass($importador);
            $metodoProcesar = $reflection->getMethod('procesarArchivo');
            $metodoProcesar->setAccessible(true);

            $resultado = $metodoProcesar->invoke($importador, $archivo);

            if ($resultado['exito']) {
                echo "✅ ÉXITO - Archivo procesado\n";
                $exitosos++;

                // Verificar en BD
                if ($uuid) {
                    $stmt = $pdo->prepare("SELECT version FROM cfdi WHERE uuid = ?");
                    $stmt->execute([$uuid]);
                    $version_bd = $stmt->fetchColumn();
                    echo "📊 Versión en BD: $version_bd\n";
                }
            } else {
                echo "❌ ERROR: " . $resultado['error'] . "\n";
                $errores++;
            }
        } catch (Exception $e) {
            echo "❌ EXCEPCIÓN: " . $e->getMessage() . "\n";
            $errores++;
        }

        echo "\n";
    }

    echo "============================================================\n";
    echo "RESUMEN - SOLO ARCHIVOS 2025\n";
    echo "============================================================\n";
    echo "Total archivos 2025: " . count($archivos_2025) . "\n";
    echo "Archivos procesados: " . ($exitosos + $errores) . "\n";
    echo "Éxitos: $exitosos\n";
    echo "Errores: $errores\n";
    echo "CFDI 4.0 encontrados: $cfdi_40\n";
    echo "CFDI 3.3 encontrados: $cfdi_33\n";
    echo "============================================================\n";

    if ($cfdi_40 > 0) {
        echo "🎉 ¡SE CONFIRMÓ! HAY CFDI 4.0 EN 2025\n";
    } else {
        echo "⚠️  SORPRESA: Todos los archivos de 2025 son CFDI 3.3\n";
    }
} catch (Exception $e) {
    echo "❌ Error fatal: " . $e->getMessage() . "\n";
}
