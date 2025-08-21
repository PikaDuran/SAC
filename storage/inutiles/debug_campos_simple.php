<?php
// Verificar nuevos campos directamente
require_once 'importador_inteligente_cfdi.php';

try {
    $importador = new ImportadorInteligenteCFDI();
    
    // Usar reflexión para acceder a PDO privado
    $reflection = new ReflectionClass($importador);
    $pdoProperty = $reflection->getProperty('pdo');
    $pdoProperty->setAccessible(true);
    $pdo = $pdoProperty->getValue($importador);
    
    // Obtener un registro existente de la base de datos
    $sql = "SELECT uuid, archivo_path FROM cfdi WHERE archivo_path IS NOT NULL LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $uuid = $row['uuid'];
        $archivoPath = $row['archivo_path'];
        
        echo "=== DEBUG CAMPOS ESPECÍFICO ===\n";
        echo "UUID seleccionado: {$uuid}\n";
        echo "Archivo: {$archivoPath}\n\n";
        
        // Verificar si el archivo existe
        if (file_exists($archivoPath)) {
            echo "✅ Archivo encontrado\n";
            
            // Cargar el XML
            $xmlContent = file_get_contents($archivoPath);
            $xml = simplexml_load_string($xmlContent);
            
            if ($xml !== false) {
                echo "✅ XML cargado correctamente\n\n";
                
                // Test método extraerComplemento
                echo "=== TESTING extraerComplemento ===\n";
                $method = $reflection->getMethod('extraerComplemento');
                $method->setAccessible(true);
                $complemento = $method->invoke($importador, $xml);
                echo "Resultado: " . var_export($complemento, true) . "\n\n";
                
                // Test método detectarDireccionFlujo  
                echo "=== TESTING detectarDireccionFlujo ===\n";
                $method2 = $reflection->getMethod('detectarDireccionFlujo');
                $method2->setAccessible(true);
                $direccion = $method2->invoke($importador, $archivoPath);
                echo "Resultado: " . var_export($direccion, true) . "\n\n";
                
                // Test método extraerCfdiRelacionados
                echo "=== TESTING extraerCfdiRelacionados ===\n";
                $method3 = $reflection->getMethod('extraerCfdiRelacionados');
                $method3->setAccessible(true);
                $relacionados = $method3->invoke($importador, $xml);
                echo "Resultado: " . var_export($relacionados, true) . "\n\n";
                
                // Verificar estado actual en BD
                echo "=== ESTADO ACTUAL EN BASE DE DATOS ===\n";
                $sqlCheck = "SELECT complemento_tipo, complemento_json, direccion_flujo, cfdi_relacionados, 
                           version, sello_sat, no_certificado_sat, rfc_prov_certif, estatus_sat, rfc_consultado 
                           FROM cfdi WHERE uuid = ?";
                $stmt = $pdo->prepare($sqlCheck);
                $stmt->execute([$uuid]);
                
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    foreach ($row as $campo => $valor) {
                        $estado = is_null($valor) ? "❌ NULL" : "✅ " . substr($valor, 0, 50) . (strlen($valor) > 50 ? "..." : "");
                        echo "{$campo}: {$estado}\n";
                    }
                } else {
                    echo "❌ No se encontró el registro en la BD\n";
                }
                
            } else {
                echo "❌ Error cargando XML\n";
            }
        } else {
            echo "❌ Archivo no encontrado: {$archivoPath}\n";
        }
    } else {
        echo "❌ No se encontraron registros en la base de datos\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
