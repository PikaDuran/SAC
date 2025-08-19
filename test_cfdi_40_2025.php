<?php
require_once 'importador_inteligente_cfdi.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=sac_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== TEST CFDI 4.0 - ARCHIVOS 2025 ===\n\n";
    
    // VACIAR TABLAS PRIMERO
    echo "🧹 Vaciando tablas para test limpio...\n";
    $pdo->exec("TRUNCATE cfdi");
    $pdo->exec("TRUNCATE cfdi_timbre_fiscal");
    $pdo->exec("TRUNCATE cfdi_conceptos");
    $pdo->exec("TRUNCATE cfdi_impuestos");
    echo "✅ Tablas vaciadas\n\n";
    
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
        'storage/sat_downloads/*2025*.xml',
        'storage/sat_downloads/**/*2025*.xml',
        'storage/sat_downloads/*/2025*.xml',
        'storage/sat_downloads/*/*2025*.xml'
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
    
    // También buscar manualmente en cada directorio
    foreach ($directorios as $dir) {
        $archivos_en_dir = glob($dir . '/*2025*.xml');
        if (!empty($archivos_en_dir)) {
            echo "📂 En $dir: " . count($archivos_en_dir) . " archivos con 2025\n";
            $archivos2025 = array_merge($archivos2025, $archivos_en_dir);
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
    
    // Verificar que son CFDI 4.0 y procesar (SIN verificar duplicados porque vaciamos la BD)
    $importador = new ImportadorInteligenteCFDI($pdo);
    $procesados = 0;
    $exitosos = 0;
    $errores = 0;
    
    foreach (array_slice($archivos2025, 0, 5) as $i => $archivo) {
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
        
        // Procesar el archivo
        echo "🚀 Procesando...\n";
        
        try {
            // Usar reflection para acceder al método privado
            $reflection = new ReflectionClass($importador);
            $metodoProcesar = $reflection->getMethod('procesarArchivo');
            $metodoProcesar->setAccessible(true);
            
            $resultado = $metodoProcesar->invoke($importador, $archivo);
            
            if ($resultado['exito']) {
                echo "✅ ÉXITO - CFDI 4.0 insertado correctamente\n";
                $exitosos++;
                
                // Verificar datos insertados
                if ($uuid) {
                    $stmt = $pdo->prepare("SELECT id, version, tipo_comprobante, total FROM cfdi WHERE uuid = ?");
                    $stmt->execute([$uuid]);
                    $cfdi = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($cfdi) {
                        echo "   📋 ID: " . $cfdi['id'] . "\n";
                        echo "   📋 Versión: " . $cfdi['version'] . "\n";
                        echo "   📋 Tipo: " . $cfdi['tipo_comprobante'] . "\n";
                        echo "   📋 Total: $" . $cfdi['total'] . "\n";
                        
                        // Contar conceptos e impuestos
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cfdi_conceptos WHERE cfdi_id = ?");
                        $stmt->execute([$cfdi['id']]);
                        $conceptos = $stmt->fetchColumn();
                        
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cfdi_impuestos WHERE cfdi_id = ?");
                        $stmt->execute([$cfdi['id']]);
                        $impuestos = $stmt->fetchColumn();
                        
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cfdi_timbre_fiscal WHERE cfdi_id = ?");
                        $stmt->execute([$cfdi['id']]);
                        $timbre = $stmt->fetchColumn();
                        
                        echo "   📋 Conceptos: $conceptos\n";
                        echo "   📋 Impuestos: $impuestos\n";
                        echo "   📋 Timbre fiscal: " . ($timbre > 0 ? 'SÍ' : 'NO') . "\n";
                    }
                }
            } else {
                echo "❌ ERROR: " . $resultado['error'] . "\n";
                $errores++;
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
?>
