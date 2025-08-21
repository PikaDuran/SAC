<?php

/**
 * Test para verificar la implementación de los nuevos campos
 */

require_once 'importador_inteligente_cfdi.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== TEST NUEVOS CAMPOS IMPORTADOR ===\n\n";

    // Limpiar registros de prueba
    echo "🧹 Limpiando registros de prueba...\n";
    $pdo->exec("DELETE FROM cfdi WHERE uuid LIKE '%-TEST-%'");
    
    // Buscar archivos CFDI 4.0 para probar
    $archivos_test = [];
    $directorios = ['storage/sat_downloads/', 'storage/sat_emitidas/'];
    
    foreach ($directorios as $directorio) {
        if (is_dir($directorio)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directorio)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'xml' && 
                    strpos($file->getFilename(), '2025') !== false) {
                    $contenido = file_get_contents($file->getPathname());
                    if (strpos($contenido, 'Version="4.0"') !== false) {
                        $archivos_test[] = $file->getPathname();
                        if (count($archivos_test) >= 2) break 2;
                    }
                }
            }
        }
    }

    if (empty($archivos_test)) {
        echo "❌ No se encontraron archivos CFDI 4.0 para probar\n";
        exit;
    }

    echo "📁 Archivos encontrados para prueba: " . count($archivos_test) . "\n\n";

    $importador = new ImportadorInteligenteCFDI();
    
    // Usar reflection para acceder a métodos privados
    $reflection = new ReflectionClass($importador);

    foreach ($archivos_test as $i => $archivo) {
        echo "--- PRUEBA " . ($i + 1) . " ---\n";
        echo "📁 " . basename($archivo) . "\n";
        
        $contenido = file_get_contents($archivo);
        
        // Test de los nuevos métodos
        echo "\n🔍 PROBANDO NUEVOS MÉTODOS:\n";
        
        // Método extraerComplemento
        $metodoComplemento = $reflection->getMethod('extraerComplemento');
        $metodoComplemento->setAccessible(true);
        $complemento = $metodoComplemento->invoke($importador, $contenido);
        
        echo "   📄 Complemento:\n";
        echo "      - Tipo: " . ($complemento['tipo'] ?? 'NULL') . "\n";
        echo "      - JSON: " . (isset($complemento['json']) ? "✅ Extraído" : "❌ No encontrado") . "\n";
        
        // Método extraerCfdiRelacionados
        $metodoRelacionados = $reflection->getMethod('extraerCfdiRelacionados');
        $metodoRelacionados->setAccessible(true);
        $relacionados = $metodoRelacionados->invoke($importador, $contenido);
        
        echo "   🔗 CFDI Relacionados: " . ($relacionados ? "✅ Encontrados" : "❌ No encontrados") . "\n";
        
        // Método detectarDireccionFlujo
        $metodoDireccion = $reflection->getMethod('detectarDireccionFlujo');
        $metodoDireccion->setAccessible(true);
        $direccion = $metodoDireccion->invoke($importador, $archivo);
        
        echo "   📍 Dirección de flujo: " . ($direccion ?? 'No detectada') . "\n";
        
        // Test procesamiento completo
        echo "\n🚀 PROCESAMIENTO COMPLETO:\n";
        
        $metodoProcesar = $reflection->getMethod('procesarArchivo');
        $metodoProcesar->setAccessible(true);
        
        try {
            $metodoProcesar->invoke($importador, $archivo);
            echo "   ✅ Procesamiento exitoso\n";
            
            // Verificar en BD
            $metodoUUID = $reflection->getMethod('extraerUUID');
            $metodoUUID->setAccessible(true);
            $uuid = $metodoUUID->invoke($importador, $contenido, '4.0');
            
            if ($uuid) {
                $stmt = $pdo->prepare("SELECT 
                    complemento_tipo, 
                    complemento_json IS NOT NULL as tiene_json,
                    direccion_flujo,
                    sello_sat IS NOT NULL as tiene_sello_sat,
                    no_certificado_sat,
                    rfc_prov_certif,
                    cfdi_relacionados IS NOT NULL as tiene_relacionados
                    FROM cfdi WHERE uuid = ?");
                $stmt->execute([$uuid]);
                $datos = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($datos) {
                    echo "\n   📊 DATOS EN BD:\n";
                    echo "      - Complemento tipo: " . ($datos['complemento_tipo'] ?? 'NULL') . "\n";
                    echo "      - Complemento JSON: " . ($datos['tiene_json'] ? '✅' : '❌') . "\n";
                    echo "      - Dirección flujo: " . ($datos['direccion_flujo'] ?? 'NULL') . "\n";
                    echo "      - Sello SAT: " . ($datos['tiene_sello_sat'] ? '✅' : '❌') . "\n";
                    echo "      - No. Cert. SAT: " . ($datos['no_certificado_sat'] ?? 'NULL') . "\n";
                    echo "      - RFC Prov. Cert.: " . ($datos['rfc_prov_certif'] ?? 'NULL') . "\n";
                    echo "      - CFDI Relacionados: " . ($datos['tiene_relacionados'] ? '✅' : '❌') . "\n";
                } else {
                    echo "   ❌ No se encontró el registro en BD\n";
                }
            }
            
        } catch (Exception $e) {
            echo "   ❌ Error en procesamiento: " . $e->getMessage() . "\n";
        }
        
        echo "\n" . str_repeat("-", 50) . "\n\n";
    }

    // Estadísticas finales
    echo "=== ESTADÍSTICAS FINALES ===\n";
    
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        COUNT(complemento_tipo) as con_complemento_tipo,
        COUNT(complemento_json) as con_complemento_json,
        COUNT(direccion_flujo) as con_direccion_flujo,
        COUNT(sello_sat) as con_sello_sat,
        COUNT(no_certificado_sat) as con_no_cert_sat,
        COUNT(rfc_prov_certif) as con_rfc_prov,
        COUNT(cfdi_relacionados) as con_relacionados
        FROM cfdi");
    
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "📊 Registros en BD:\n";
    echo "   - Total: {$stats['total']}\n";
    echo "   - Con complemento_tipo: {$stats['con_complemento_tipo']}\n";
    echo "   - Con complemento_json: {$stats['con_complemento_json']}\n";
    echo "   - Con direccion_flujo: {$stats['con_direccion_flujo']}\n";
    echo "   - Con sello_sat: {$stats['con_sello_sat']}\n";
    echo "   - Con no_certificado_sat: {$stats['con_no_cert_sat']}\n";
    echo "   - Con rfc_prov_certif: {$stats['con_rfc_prov']}\n";
    echo "   - Con cfdi_relacionados: {$stats['con_relacionados']}\n";

    if ($stats['con_sello_sat'] > 0 || $stats['con_direccion_flujo'] > 0) {
        echo "\n✅ IMPLEMENTACIÓN DE NUEVOS CAMPOS EXITOSA!\n";
    } else {
        echo "\n⚠️ Los nuevos campos no se están llenando correctamente\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Detalles: " . $e->getTraceAsString() . "\n";
}
