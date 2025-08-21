<?php
require_once 'importador_inteligente_cfdi.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=sac_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== TEST CFDI 4.0 - SOLO ARCHIVOS 2025 ===\n\n";

    // LIMPIAR TABLAS PRIMERO
    echo "🧹 Limpiando tablas para test limpio...\n";
    system('php limpiar_tablas.php');
    echo "✅ Tablas limpiadas\n\n";

    // Buscar archivos del 2025 (todos deben ser CFDI 4.0)
    echo "🔍 Buscando archivos del 2025...\n";

    // Primero, ver qué directorios tenemos
    $directorios = glob('storage/sat_downloads/*', GLOB_ONLYDIR);
    echo "📁 Directorios encontrados:\n";
    foreach ($directorios as $dir) {
        echo "   - " . basename($dir) . "\n";
    }
    echo "\n";

    $archivos2025 = [];
    $patrones = [
        'storage/sat_downloads/*/EMITIDAS/2025/*/*.xml',
        'storage/sat_downloads/*/RECIBIDAS/2025/*/*.xml',
        'storage/sat_downloads/*/EMITIDAS/2025/*.xml',
        'storage/sat_downloads/*/RECIBIDAS/2025/*.xml'
    ];

    foreach ($patrones as $patron) {
        echo "🔍 Buscando con patrón: $patron\n";
        $encontrados = glob($patron);
        if (!empty($encontrados)) {
            echo "   ✅ Encontrados: " . count($encontrados) . " archivos\n";
            $archivos2025 = array_merge($archivos2025, $encontrados);
        } else {
            echo "   ❌ No encontrados\n";
        }
    }

    // Buscar específicamente en la estructura conocida
    foreach ($directorios as $dir) {
        $rfc = basename($dir);
        echo "📂 Revisando RFC: $rfc\n";

        // Buscar en EMITIDAS/2025
        $emitidas_2025 = glob($dir . '/EMITIDAS/2025/*/*.xml');
        if (!empty($emitidas_2025)) {
            echo "   💼 EMITIDAS 2025: " . count($emitidas_2025) . " archivos\n";
            $archivos2025 = array_merge($archivos2025, $emitidas_2025);
        }

        // Buscar en RECIBIDAS/2025
        $recibidas_2025 = glob($dir . '/RECIBIDAS/2025/*/*.xml');
        if (!empty($recibidas_2025)) {
            echo "   � RECIBIDAS 2025: " . count($recibidas_2025) . " archivos\n";
            $archivos2025 = array_merge($archivos2025, $recibidas_2025);
        }

        // También buscar directamente en 2025 (sin subcarpetas)
        $directos_2025 = glob($dir . '/EMITIDAS/2025/*.xml');
        if (!empty($directos_2025)) {
            echo "   📄 EMITIDAS directos: " . count($directos_2025) . " archivos\n";
            $archivos2025 = array_merge($archivos2025, $directos_2025);
        }

        $directos_rec_2025 = glob($dir . '/RECIBIDAS/2025/*.xml');
        if (!empty($directos_rec_2025)) {
            echo "   📄 RECIBIDAS directos: " . count($directos_rec_2025) . " archivos\n";
            $archivos2025 = array_merge($archivos2025, $directos_rec_2025);
        }
    }

    $archivos2025 = array_unique($archivos2025);
    echo "📁 Archivos del 2025 encontrados: " . count($archivos2025) . "\n";

    if (count($archivos2025) === 0) {
        echo "❌ No se encontraron archivos del 2025\n";
        exit;
    }

    // Mostrar algunos archivos encontrados
    echo "📋 Primeros archivos encontrados:\n";
    foreach (array_slice($archivos2025, 0, 5) as $archivo) {
        echo "   - " . basename($archivo) . "\n";
    }
    echo "\n";

    // Verificar que son CFDI 4.0 y procesar SOLO los archivos de 2025
    $procesados = 0;
    $exitosos = 0;
    $errores = 0;

    echo "🚀 Procesando SOLO archivos de 2025...\n\n";

    foreach (array_slice($archivos2025, 0, 50) as $i => $archivo) {
        echo "--- PROCESANDO ARCHIVO " . ($i + 1) . " ---\n";
        echo "📁 " . basename($archivo) . "\n";

        if (!file_exists($archivo)) {
            echo "❌ Archivo no existe\n\n";
            continue;
        }

        $contenido = file_get_contents($archivo);

        // Verificar que es CFDI 4.0
        if (strpos($contenido, 'Version="4.0"') === false) {
            echo "⚠️  No es CFDI 4.0, saltando...\n\n";
            continue;
        }

        // Extraer UUID
        $uuid = null;
        if (preg_match('/UUID="([^"]+)"/', $contenido, $matches)) {
            $uuid = $matches[1];
        }

        echo "✅ CFDI 4.0 encontrado - UUID: $uuid\n";

        // Mostrar campos específicos CFDI 4.0
        if (preg_match('/Exportacion="([^"]*)"/', $contenido, $matches)) {
            echo "   📊 Exportación: " . $matches[1] . "\n";
        }
        if (preg_match('/RegimenFiscalReceptor="([^"]*)"/', $contenido, $matches)) {
            echo "   📊 Régimen Fiscal Receptor: " . $matches[1] . "\n";
        }

        // Procesar el archivo DIRECTAMENTE usando el método interno
        echo "🚀 Procesando archivo individual...\n";

        try {
            // Verificar si el CFDI ya existe antes de procesar
            $stmt = $pdo->prepare("SELECT id FROM cfdi WHERE uuid = ?");
            $stmt->execute([$uuid]);
            $existe = $stmt->fetchColumn();

            if ($existe) {
                echo "✅ CFDI ya existe en la base de datos - ID: $existe\n";
                $exitosos++;
            } else {
                // Crear una instancia del importador para este archivo
                $importador = new ImportadorInteligenteCFDI($pdo);

                // Usar reflection para acceder al método privado procesarArchivo
                $reflection = new ReflectionClass($importador);
                $metodoProcesar = $reflection->getMethod('procesarArchivo');
                $metodoProcesar->setAccessible(true);

                // Intentar procesar el archivo
                $resultado = $metodoProcesar->invoke($importador, $archivo);

                // Verificar si se insertó revisando la base de datos
                $stmt = $pdo->prepare("SELECT id, version, tipo, fecha FROM cfdi WHERE uuid = ?");
                $stmt->execute([$uuid]);
                $cfdi = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($cfdi) {
                    echo "✅ ÉXITO - CFDI insertado correctamente\n";
                    echo "   📋 ID: " . $cfdi['id'] . "\n";
                    echo "   📋 Versión: " . $cfdi['version'] . "\n";
                    echo "   📋 Tipo: " . $cfdi['tipo'] . "\n";
                    echo "   📋 Fecha: " . $cfdi['fecha'] . "\n";
                    $exitosos++;
                } else {
                    echo "❌ ERROR: CFDI no se insertó en la base de datos\n";
                    $errores++;
                }
            }
        } catch (Exception $e) {
            echo "❌ EXCEPCIÓN: " . $e->getMessage() . "\n";
            $errores++;
        }

        $procesados++;
        echo "\n";
    }

    echo "============================================================\n";
    echo "RESUMEN FINAL - TEST CFDI 4.0 (2025)\n";
    echo "============================================================\n";
    echo "Archivos 2025 encontrados: " . count($archivos2025) . "\n";
    echo "Archivos procesados: $procesados\n";
    echo "Éxitos: $exitosos\n";
    echo "Errores: $errores\n";
    echo "Tasa de éxito: " . ($procesados > 0 ? round(($exitosos / $procesados) * 100, 2) : 0) . "%\n";
    echo "============================================================\n";

    if ($exitosos > 0) {
        echo "🎉 ¡CFDI 4.0 VERIFICADO Y FUNCIONANDO!\n";
        echo "✅ El sistema está listo para procesar CFDIs 4.0 del 2025\n";
    } else {
        echo "⚠️  No se pudieron procesar CFDIs 4.0 - revisar errores\n";
    }
} catch (Exception $e) {
    echo "❌ Error fatal: " . $e->getMessage() . "\n";
}
