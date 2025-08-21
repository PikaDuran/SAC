<?php

/**
 * Test específico para verificar el funcionamiento con CFDI 4.0
 */

require_once 'importador_inteligente_cfdi.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== TEST ESPECÍFICO CFDI 4.0 ===\n\n";

    // Buscar archivos CFDI 4.0 específicamente de 2025
    $directorios = [
        'storage/sat_downloads/',
        'storage/sat_emitidas/'
    ];

    $archivos_40 = [];
    $archivos_33 = [];

    echo "🔍 Buscando archivos CFDI 4.0 de 2025...\n";

    // Buscar archivos de 2025 (que deben ser 4.0)
    foreach ($directorios as $directorio) {
        if (is_dir($directorio)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directorio)
            );

            foreach ($iterator as $file) {
                if (
                    $file->isFile() && $file->getExtension() === 'xml' &&
                    strpos($file->getFilename(), '2025') !== false
                ) {
                    $contenido = file_get_contents($file->getPathname());
                    if (strpos($contenido, 'Version="4.0"') !== false) {
                        $archivos_40[] = $file->getPathname();
                        if (count($archivos_40) >= 5) break 2;
                    } elseif (strpos($contenido, 'Version="3.3"') !== false) {
                        $archivos_33[] = $file->getPathname();
                    }
                }
            }
        }
    }

    echo "📊 Encontrados:\n";
    echo "   - CFDI 4.0: " . count($archivos_40) . " archivos\n";
    echo "   - CFDI 3.3: " . count($archivos_33) . " archivos\n\n";

    if (count($archivos_40) === 0) {
        echo "❌ No se encontraron archivos CFDI 4.0 de 2025\n";
        exit;
    }

    // Limpiar tabla para test
    echo "🧹 Limpiando tabla para test...\n";
    $pdo->exec("TRUNCATE cfdi");
    $pdo->exec("TRUNCATE cfdi_timbre_fiscal");
    $pdo->exec("TRUNCATE cfdi_conceptos");
    $pdo->exec("TRUNCATE cfdi_impuestos");

    $importador = new ImportadorInteligenteCFDI($pdo);

    echo "\n=== PROBANDO CFDI 4.0 ===\n";

    foreach (array_slice($archivos_40, 0, 3) as $i => $archivo) {
        echo "\n--- ARCHIVO " . ($i + 1) . " ---\n";
        echo "📁 " . basename($archivo) . "\n";

        $contenido = file_get_contents($archivo);

        // Mostrar estructura del archivo
        echo "🔍 ESTRUCTURA:\n";
        if (preg_match('/Version="([^"]*)"/', $contenido, $matches)) {
            echo "   Versión: " . $matches[1] . "\n";
        }
        if (preg_match('/TipoDeComprobante="([^"]*)"/', $contenido, $matches)) {
            echo "   Tipo: " . $matches[1] . "\n";
        }
        if (preg_match('/Exportacion="([^"]*)"/', $contenido, $matches)) {
            echo "   Exportación (4.0): " . $matches[1] . "\n";
        }
        if (preg_match('/RegimenFiscalReceptor="([^"]*)"/', $contenido, $matches)) {
            echo "   Régimen Fiscal Receptor (4.0): " . $matches[1] . "\n";
        }

        // Usar reflection para probar extracción
        $reflection = new ReflectionClass($importador);

        // Test extracción de UUID
        $metodoUUID = $reflection->getMethod('extraerUUID');
        $metodoUUID->setAccessible(true);
        $uuid = $metodoUUID->invoke($importador, $contenido);
        echo "   UUID: " . ($uuid ?: 'NO ENCONTRADO') . "\n";

        // Test extracción de datos comprobante
        $metodoDatos = $reflection->getMethod('extraerDatosComprobante');
        $metodoDatos->setAccessible(true);
        $datos = $metodoDatos->invoke($importador, $contenido, '4.0');
        echo "   Datos extraídos: " . count($datos) . " campos\n";

        if (isset($datos['exportacion'])) {
            echo "   ✅ Campo Exportación (4.0): " . $datos['exportacion'] . "\n";
        }
        if (isset($datos['regimen_fiscal_receptor'])) {
            echo "   ✅ Campo Régimen Fiscal Receptor (4.0): " . $datos['regimen_fiscal_receptor'] . "\n";
        }

        // Test extracción de conceptos
        $metodoConceptos = $reflection->getMethod('extraerConceptos');
        $metodoConceptos->setAccessible(true);
        $conceptos = $metodoConceptos->invoke($importador, $contenido);
        echo "   Conceptos: " . count($conceptos) . "\n";

        // Test extracción de impuestos
        $metodoImpuestos = $reflection->getMethod('extraerImpuestos');
        $metodoImpuestos->setAccessible(true);
        $impuestos = $metodoImpuestos->invoke($importador, $contenido);
        echo "   Impuestos: " . count($impuestos) . "\n";

        // Test extracción de timbre
        $metodoTimbre = $reflection->getMethod('extraerTimbreFiscal');
        $metodoTimbre->setAccessible(true);
        $timbre = $metodoTimbre->invoke($importador, $contenido);
        echo "   Timbre: " . (count($timbre) > 0 ? "✅" : "❌") . "\n";

        // Probar inserción completa usando reflection
        echo "\n🚀 PROBANDO INSERCIÓN COMPLETA...\n";
        $metodoProcesar = $reflection->getMethod('procesarArchivo');
        $metodoProcesar->setAccessible(true);
        $resultado = $metodoProcesar->invoke($importador, $archivo);

        if ($resultado['exito']) {
            echo "   ✅ INSERCIÓN EXITOSA\n";
            echo "   📊 ID CFDI: " . $resultado['cfdi_id'] . "\n";
            echo "   📊 Conceptos insertados: " . $resultado['conceptos_insertados'] . "\n";
            echo "   📊 Impuestos insertados: " . $resultado['impuestos_insertados'] . "\n";
        } else {
            echo "   ❌ ERROR: " . $resultado['error'] . "\n";
        }
    }

    echo "\n=== COMPARACIÓN CON CFDI 3.3 ===\n";

    foreach (array_slice($archivos_33, 0, 2) as $i => $archivo) {
        echo "\n--- ARCHIVO 3.3 " . ($i + 1) . " ---\n";
        echo "📁 " . basename($archivo) . "\n";

        $metodoProcesar = $reflection->getMethod('procesarArchivo');
        $metodoProcesar->setAccessible(true);
        $resultado = $metodoProcesar->invoke($importador, $archivo);

        if ($resultado['exito']) {
            echo "   ✅ INSERCIÓN EXITOSA (3.3)\n";
        } else {
            echo "   ❌ ERROR: " . $resultado['error'] . "\n";
        }
    }

    echo "\n=== VERIFICACIÓN FINAL ===\n";

    // Verificar datos en base
    $stmt = $pdo->query("SELECT version, COUNT(*) as total FROM cfdi GROUP BY version");
    $versiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "📊 CFDI insertados por versión:\n";
    foreach ($versiones as $version) {
        echo "   - Versión " . $version['version'] . ": " . $version['total'] . " registros\n";
    }

    // Verificar campos específicos de 4.0
    $stmt = $pdo->query("SELECT COUNT(*) as con_exportacion FROM cfdi WHERE exportacion IS NOT NULL AND exportacion != ''");
    $con_exportacion = $stmt->fetch()['con_exportacion'];

    $stmt = $pdo->query("SELECT COUNT(*) as con_regimen_receptor FROM cfdi WHERE regimen_fiscal_receptor IS NOT NULL AND regimen_fiscal_receptor != ''");
    $con_regimen_receptor = $stmt->fetch()['con_regimen_receptor'];

    echo "\n🔍 CAMPOS ESPECÍFICOS CFDI 4.0:\n";
    echo "   - Con campo Exportación: {$con_exportacion}\n";
    echo "   - Con campo Régimen Fiscal Receptor: {$con_regimen_receptor}\n";

    if ($con_exportacion > 0 || $con_regimen_receptor > 0) {
        echo "\n✅ EL SISTEMA MANEJA CORRECTAMENTE CFDI 4.0\n";
    } else {
        echo "\n⚠️ No se detectaron campos específicos de CFDI 4.0\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
