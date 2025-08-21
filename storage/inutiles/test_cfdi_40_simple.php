<?php
require_once 'importador_inteligente_cfdi.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=sac", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== TEST SIMPLE CFDI 4.0 (2025) ===\n\n";

    // Buscar archivos de 2025 específicamente
    $patrones = [
        'storage/sat_downloads/**/2025*/*.xml',
        'storage/sat_downloads/**/*2025*.xml',
        'storage/sat_downloads/2025*/*.xml'
    ];

    $archivos2025 = [];
    foreach ($patrones as $patron) {
        $archivos2025 = array_merge($archivos2025, glob($patron));
        if (!empty($archivos2025)) break;
    }

    echo "📁 Archivos de 2025 encontrados: " . count($archivos2025) . "\n";

    if (empty($archivos2025)) {
        echo "❌ No se encontraron archivos de 2025\n";
        exit;
    }

    $cfdi40_nuevos = [];
    $cfdi33_en_2025 = [];

    // Verificar primeros 10 archivos
    foreach (array_slice($archivos2025, 0, 10) as $archivo) {
        if (!file_exists($archivo)) continue;

        $contenido = file_get_contents($archivo);

        // Extraer UUID
        $uuid = null;
        if (preg_match('/UUID="([^"]+)"/', $contenido, $matches)) {
            $uuid = $matches[1];
        }

        if (!$uuid) continue;

        // Verificar si ya existe en BD
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cfdi WHERE uuid = ?");
        $stmt->execute([$uuid]);
        $existe = $stmt->fetchColumn() > 0;

        if ($existe) {
            echo "⚠️  UUID ya existe: " . basename($archivo) . " ($uuid)\n";
            continue;
        }

        // Verificar versión
        if (strpos($contenido, 'Version="4.0"') !== false) {
            $cfdi40_nuevos[] = ['archivo' => $archivo, 'uuid' => $uuid];
            echo "✅ CFDI 4.0 NUEVO: " . basename($archivo) . " ($uuid)\n";
        } elseif (strpos($contenido, 'Version="3.3"') !== false) {
            $cfdi33_en_2025[] = ['archivo' => $archivo, 'uuid' => $uuid];
            echo "⚠️  CFDI 3.3 en 2025: " . basename($archivo) . " ($uuid)\n";
        }
    }

    echo "\n📊 RESULTADOS:\n";
    echo "   - CFDI 4.0 nuevos: " . count($cfdi40_nuevos) . "\n";
    echo "   - CFDI 3.3 en 2025: " . count($cfdi33_en_2025) . "\n\n";

    if (empty($cfdi40_nuevos)) {
        echo "❌ No se encontraron CFDI 4.0 nuevos para procesar\n";
        exit;
    }

    // Procesar el primer CFDI 4.0 encontrado
    $primer_cfdi40 = $cfdi40_nuevos[0];
    echo "🚀 PROCESANDO CFDI 4.0: " . basename($primer_cfdi40['archivo']) . "\n";

    $importador = new ImportadorInteligenteCFDI($pdo);

    // Usar reflection para procesar el archivo
    $reflection = new ReflectionClass($importador);
    $metodoProcesar = $reflection->getMethod('procesarArchivo');
    $metodoProcesar->setAccessible(true);

    $resultado = $metodoProcesar->invoke($importador, $primer_cfdi40['archivo']);

    if ($resultado['exito']) {
        echo "✅ CFDI 4.0 procesado exitosamente\n";

        // Verificar datos insertados
        $stmt = $pdo->prepare("SELECT * FROM cfdi WHERE uuid = ?");
        $stmt->execute([$primer_cfdi40['uuid']]);
        $cfdi_insertado = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cfdi_insertado) {
            echo "\n📋 DATOS INSERTADOS:\n";
            echo "   - UUID: " . $cfdi_insertado['uuid'] . "\n";
            echo "   - Versión: " . $cfdi_insertado['version'] . "\n";
            echo "   - Tipo: " . $cfdi_insertado['tipo_comprobante'] . "\n";
            echo "   - Total: $" . $cfdi_insertado['total'] . "\n";

            // Verificar campos específicos CFDI 4.0
            if (isset($cfdi_insertado['exportacion'])) {
                echo "   - Exportación (4.0): " . $cfdi_insertado['exportacion'] . "\n";
            }
            if (isset($cfdi_insertado['regimen_fiscal_receptor'])) {
                echo "   - Régimen Fiscal Receptor (4.0): " . $cfdi_insertado['regimen_fiscal_receptor'] . "\n";
            }

            // Verificar conceptos
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cfdi_conceptos WHERE cfdi_id = ?");
            $stmt->execute([$cfdi_insertado['id']]);
            $conceptos = $stmt->fetchColumn();
            echo "   - Conceptos: " . $conceptos . "\n";

            // Verificar impuestos
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cfdi_impuestos WHERE cfdi_id = ?");
            $stmt->execute([$cfdi_insertado['id']]);
            $impuestos = $stmt->fetchColumn();
            echo "   - Impuestos: " . $impuestos . "\n";

            // Verificar timbre
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cfdi_timbre_fiscal WHERE cfdi_id = ?");
            $stmt->execute([$cfdi_insertado['id']]);
            $timbre = $stmt->fetchColumn();
            echo "   - Timbre fiscal: " . ($timbre > 0 ? "SÍ" : "NO") . "\n";
        }
    } else {
        echo "❌ Error procesando CFDI 4.0: " . $resultado['error'] . "\n";
    }

    echo "\n🎯 TEST COMPLETADO - CFDI 4.0 VERIFICADO\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
