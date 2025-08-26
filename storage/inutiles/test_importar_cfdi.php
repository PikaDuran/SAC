<?php
/**
 * Script de prueba para importar solo algunos XMLs de agosto 2025
 * Para verificar que todo funciona antes del procesamiento masivo
 */

// Zona horaria global CDMX
date_default_timezone_set('America/Mexico_City');

set_time_limit(0);
ini_set('memory_limit', '1024M');

// Usar la función de conexión real del sistema
require_once __DIR__ . '/../../src/config/database.php';

// Incluir las funciones del importador adaptado
include_once __DIR__ . '/importar_cfdi_adaptado_fixed.php';

echo "🧪 MODO PRUEBA - IMPORTACIÓN LIMITADA AGOSTO 2025\n";
echo "===============================================\n\n";

// Directorios específicos para agosto 2025
$baseDir = 'C:\xampp\htdocs\SAC\storage\sat_downloads';
$directoriosAgosto2025 = [
    $baseDir . '\BFM170822P38\EMITIDAS\2025\8',
    $baseDir . '\BFM170822P38\RECIBIDAS\2025\8', 
    $baseDir . '\BLM1706026AA\EMITIDAS\2025\8',
    $baseDir . '\BLM1706026AA\RECIBIDAS\2025\8'
];

// Buscar XMLs en todos los directorios de agosto 2025
$xmlPaths = [];
foreach ($directoriosAgosto2025 as $directorio) {
    echo "📁 Buscando en: $directorio\n";
    $archivosEnDirectorio = getXmlFiles($directorio);
    echo "   Encontrados: " . count($archivosEnDirectorio) . " archivos\n";
    $xmlPaths = array_merge($xmlPaths, $archivosEnDirectorio);
}

echo "\n� Total archivos XML encontrados: " . count($xmlPaths) . "\n";

if (empty($xmlPaths)) {
    echo "❌ No se encontraron archivos XML en los directorios de agosto 2025\n";
    echo "🔍 Verificar que existan archivos en:\n";
    foreach ($directoriosAgosto2025 as $dir) {
        echo "   - $dir\n";
    }
    exit(1);
}

// Tomar solo los primeros 5 para prueba
$xmlsPrueba = array_slice($xmlPaths, 0, 5);

echo "🧪 Procesando " . count($xmlsPrueba) . " archivos de prueba:\n";
foreach ($xmlsPrueba as $i => $xml) {
    echo "  " . ($i + 1) . ". " . basename($xml) . "\n";
}
echo "\n";

try {
    $pdo = getDatabase();
    echo "✅ Conexión a la base de datos establecida.\n\n";
    
    $ok = 0;
    $err = 0;
    $duplicados = 0;
    
    foreach ($xmlsPrueba as $i => $xmlFile) {
        echo "🔍 Procesando: " . basename($xmlFile) . "\n";
        
        $data = parseCfdi($xmlFile);
        
        if (isset($data['error'])) {
            $err++;
            echo "  ❌ Error: " . $data['error'] . "\n\n";
        } elseif (empty($data['uuid'])) {
            $err++;
            echo "  ❌ Error: No se encontró UUID\n\n";
        } else {
            echo "  📋 UUID: " . $data['uuid'] . "\n";
            echo "  🏢 RFC Emisor: " . ($data['emisor']['Rfc'] ?? 'N/A') . "\n";
            echo "  🏢 RFC Receptor: " . ($data['receptor']['Rfc'] ?? 'N/A') . "\n";
            echo "  📊 Tipo: " . ($data['comprobante']['TipoDeComprobante'] ?? 'N/A') . "\n";
            echo "  💰 Total: $" . ($data['comprobante']['Total'] ?? '0') . "\n";
            echo "  📅 Fecha: " . ($data['comprobante']['Fecha'] ?? 'N/A') . "\n";
            echo "  🗂️  Dirección: " . ($data['direccion_flujo'] ?? 'N/A') . "\n";
            
            // Verificar duplicado
            $stmtCheck = $pdo->prepare("SELECT id FROM cfdi WHERE uuid = ? LIMIT 1");
            $stmtCheck->execute([$data['uuid']]);
            if ($stmtCheck->rowCount() > 0) {
                $duplicados++;
                echo "  🔄 Ya existe en la base de datos\n\n";
            } else {
                try {
                    $cfdi_id = insertCfdi($pdo, $data);
                    $ok++;
                    echo "  ✅ Insertado correctamente (ID: $cfdi_id)\n";
                    
                    // Verificar complementos de pago si es tipo P
                    if (isset($data['comprobante']['TipoDeComprobante']) && $data['comprobante']['TipoDeComprobante'] === 'P') {
                        $stmtPagos = $pdo->prepare("SELECT COUNT(*) FROM cfdi_pagos WHERE cfdi_id = ?");
                        $stmtPagos->execute([$cfdi_id]);
                        $numPagos = $stmtPagos->fetchColumn();
                        echo "  💳 Complementos de pago insertados: $numPagos\n";
                    }
                    
                    // Verificar conceptos
                    $stmtConceptos = $pdo->prepare("SELECT COUNT(*) FROM cfdi_conceptos WHERE cfdi_id = ?");
                    $stmtConceptos->execute([$cfdi_id]);
                    $numConceptos = $stmtConceptos->fetchColumn();
                    echo "  📦 Conceptos insertados: $numConceptos\n";
                    
                    // Verificar impuestos
                    $stmtImpuestos = $pdo->prepare("SELECT COUNT(*) FROM cfdi_impuestos WHERE cfdi_id = ?");
                    $stmtImpuestos->execute([$cfdi_id]);
                    $numImpuestos = $stmtImpuestos->fetchColumn();
                    echo "  🧾 Impuestos insertados: $numImpuestos\n";
                    
                    echo "\n";
                } catch (Exception $e) {
                    $err++;
                    echo "  ❌ Error de inserción: " . $e->getMessage() . "\n\n";
                }
            }
        }
    }
    
    echo str_repeat("-", 50) . "\n";
    echo "🎯 RESUMEN DE PRUEBA:\n";
    echo "  📁 Archivos procesados: " . count($xmlsPrueba) . "\n";
    echo "  ✅ Insertados: $ok\n";
    echo "  🔄 Duplicados: $duplicados\n";
    echo "  ❌ Errores: $err\n";
    echo str_repeat("-", 50) . "\n";
    
    if ($ok > 0) {
        echo "\n✅ PRUEBA EXITOSA - El script está listo para procesamiento masivo\n";
        echo "💡 Para procesar todos los XMLs, ejecuta: importar_cfdi_adaptado.php\n";
    } else {
        echo "\n⚠️  REVISAR ERRORES antes del procesamiento masivo\n";
    }

} catch (Exception $e) {
    echo "❌ Error fatal: " . $e->getMessage() . "\n";
    exit(1);
}
?>
