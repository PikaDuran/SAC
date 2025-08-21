<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'importador_inteligente_cfdi.php';

echo "=== TEST ESPECÍFICO CFDI 4.0 - SOLO 2025 ===\n\n";

try {
    echo "1. Verificando que las tablas están vacías...\n";
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM cfdi");
    $total = $stmt->fetchColumn();
    echo "   Registros en tabla cfdi: $total\n";
    
    if ($total > 0) {
        echo "❌ Las tablas no están vacías. Ejecutar limpiar_para_test.php primero\n";
        exit();
    }
    
    $importador = new ImportadorInteligenteCFDI();
    
    echo "\n2. Buscando ÚNICAMENTE archivos de 2025...\n";
    $rutaBase = 'C:/xampp/htdocs/SAC/storage/sat_downloads';
    
    // Buscar SOLO en 2025
    $archivos2025 = [];
    $patrones2025 = [
        $rutaBase . '/*/EMITIDAS/2025/*/*.xml',
        $rutaBase . '/*/RECIBIDAS/2025/*/*.xml'
    ];
    
    foreach ($patrones2025 as $patron) {
        echo "   Buscando: $patron\n";
        $encontrados = glob($patron);
        echo "   Encontrados: " . count($encontrados) . " archivos\n";
        $archivos2025 = array_merge($archivos2025, $encontrados);
    }
    
    if (empty($archivos2025)) {
        echo "❌ No se encontraron archivos de 2025\n";
        
        // Mostrar qué estructura existe
        echo "\nVerificando estructura existente:\n";
        $rfcs = glob($rutaBase . '/*', GLOB_ONLYDIR);
        foreach ($rfcs as $rfc) {
            $rfc_name = basename($rfc);
            echo "RFC: $rfc_name\n";
            
            $años = glob($rfc . '/EMITIDAS/*', GLOB_ONLYDIR);
            foreach ($años as $año) {
                $año_name = basename($año);
                echo "  - Año: $año_name\n";
            }
        }
        exit();
    }
    
    echo "✅ Total archivos de 2025: " . count($archivos2025) . "\n";
    
    echo "\n3. Verificando que son CFDI 4.0...\n";
    $archivosCfdi40 = [];
    $archivosCfdi33 = [];
    
    foreach ($archivos2025 as $archivo) {
        $contenido = file_get_contents($archivo);
        if (preg_match('/Version="([^"]+)"/', $contenido, $matches)) {
            $version = $matches[1];
            if ($version === '4.0') {
                $archivosCfdi40[] = $archivo;
            } else {
                $archivosCfdi33[] = $archivo;
                echo "⚠️ Archivo de 2025 con versión $version: " . basename($archivo) . "\n";
            }
        }
    }
    
    echo "   CFDI 4.0 en 2025: " . count($archivosCfdi40) . "\n";
    echo "   CFDI 3.3 en 2025: " . count($archivosCfdi33) . " (esto está MAL)\n";
    
    if (empty($archivosCfdi40)) {
        echo "❌ No se encontraron CFDI 4.0 en 2025\n";
        exit();
    }
    
    // Tomar solo 10 archivos CFDI 4.0 para test
    $archivosTest = array_slice($archivosCfdi40, 0, 10);
    
    echo "\n4. Procesando " . count($archivosTest) . " archivos CFDI 4.0 de 2025...\n";
    echo "================================================================\n";
    
    $reflection = new ReflectionClass($importador);
    $metodo = $reflection->getMethod('procesarArchivo');
    $metodo->setAccessible(true);
    
    $insertados = 0;
    $errores = 0;
    
    foreach ($archivosTest as $i => $archivo) {
        echo "\n--- ARCHIVO " . ($i + 1) . "/" . count($archivosTest) . " ---\n";
        echo "📁 " . basename($archivo) . "\n";
        echo "📍 Ruta: " . dirname($archivo) . "\n";
        
        // Mostrar info del archivo
        $contenido = file_get_contents($archivo);
        if (preg_match('/Version="([^"]+)"/', $contenido, $matches)) {
            echo "📋 Versión: " . $matches[1] . "\n";
        }
        if (preg_match('/Fecha="([^"]+)"/', $contenido, $matches)) {
            echo "📅 Fecha: " . $matches[1] . "\n";
        }
        
        try {
            $resultado = $metodo->invoke($importador, $archivo);
            if ($resultado) {
                $insertados++;
                echo "✅ INSERTADO EXITOSAMENTE\n";
            } else {
                $errores++;
                echo "❌ Error en inserción\n";
            }
        } catch (Exception $e) {
            $errores++;
            echo "❌ Excepción: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n5. RESULTADOS FINALES\n";
    echo "=====================\n";
    echo "Archivos CFDI 4.0 procesados: " . count($archivosTest) . "\n";
    echo "Insertados: $insertados\n";
    echo "Errores: $errores\n";
    echo "Tasa de éxito: " . round(($insertados / count($archivosTest)) * 100, 2) . "%\n";
    
    // Verificar que solo tenemos CFDI 4.0 en la BD
    $stmt = $pdo->query("SELECT version, COUNT(*) as cantidad FROM cfdi GROUP BY version");
    $versiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n6. VERIFICACIÓN DE VERSIONES EN BD\n";
    echo "===================================\n";
    foreach ($versiones as $v) {
        echo "Versión " . $v['version'] . ": " . $v['cantidad'] . " registros\n";
        if ($v['version'] !== '4.0') {
            echo "❌ ERROR: Se insertó versión " . $v['version'] . " cuando solo deberían ser 4.0\n";
        }
    }
    
    if ($insertados > 0) {
        echo "\n7. VALIDACIÓN DE LOS 10 CAMPOS EN CFDI 4.0\n";
        echo "===========================================\n";
        
        $stmt = $pdo->query("SELECT * FROM cfdi WHERE version = '4.0' LIMIT 3");
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $camposSolicitados = [
            'complemento_tipo', 'complemento_json', 'rfc_consultado', 'direccion_flujo', 'version',
            'sello_sat', 'no_certificado_sat', 'rfc_prov_certif', 'estatus_sat', 'cfdi_relacionados'
        ];
        
        foreach ($registros as $i => $reg) {
            echo "\n--- CFDI 4.0 #" . ($i + 1) . " ---\n";
            echo "UUID: " . $reg['uuid'] . "\n";
            echo "Fecha: " . $reg['fecha'] . "\n";
            echo "Versión: " . $reg['version'] . "\n";
            
            foreach ($camposSolicitados as $campo) {
                $valor = $reg[$campo] ?? 'NULL';
                $status = ($valor !== 'NULL' && $valor !== '') ? '✅' : '❌';
                echo "$status $campo: " . (strlen($valor) > 30 ? substr($valor, 0, 30) . '...' : $valor) . "\n";
            }
        }
        
        echo "\n🎉 TEST CFDI 4.0 DE 2025 COMPLETADO\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL TEST ===\n";
?>
