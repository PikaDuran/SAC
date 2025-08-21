<?php
// Verificar nuevos campos directamente desde base de datos
require_once 'importador_inteligente_cfdi.php';

try {
    $importador = new ImportadorInteligenteCFDI();
    
    // Obtener un registro existente de la base de datos
    $sql = "SELECT uuid, archivo_path FROM cfdi WHERE archivo_path IS NOT NULL LIMIT 1";
    $stmt = $importador->pdo->prepare($sql);
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
                
                // Usar reflexión para acceder a métodos privados
                $reflection = new ReflectionClass($importador);
                
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
                $stmt = $importador->pdo->prepare($sqlCheck);
                $stmt->execute([$uuid]);
                
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    foreach ($row as $campo => $valor) {
                        $estado = is_null($valor) ? "❌ NULL" : "✅ " . substr($valor, 0, 50) . (strlen($valor) > 50 ? "..." : "");
                        echo "{$campo}: {$estado}\n";
                    }
                } else {
                    echo "❌ No se encontró el registro en la BD\n";
                }
                
                // Test inserción real
                echo "\n=== TEST DE INSERCIÓN ===\n";
                
                // Crear datos ficticios para test
                $datosTest = [
                    'uuid' => 'TEST-' . date('YmdHis'),
                    'emisor_rfc' => 'TEST123456789',
                    'receptor_rfc' => 'RECEP123456789',
                    'version' => '3.3',
                    'fecha' => date('Y-m-d H:i:s'),
                    'folio' => '123',
                    'serie' => 'A',
                    'forma_pago' => '01',
                    'metodo_pago' => 'PUE',
                    'tipo_comprobante' => 'I',
                    'moneda' => 'MXN',
                    'tipo_cambio' => '1.0',
                    'lugar_expedicion' => '06470',
                    'subtotal' => '100.00',
                    'descuento' => '0.00',
                    'total' => '116.00',
                    'certificado' => 'TEST_CERT',
                    'sello' => 'TEST_SELLO',
                    'no_certificado' => 'TEST_NO_CERT',
                    'fecha_timbrado' => date('Y-m-d H:i:s'),
                    'uuid_timbre' => 'TEST-TIMBRE-UUID',
                    'sello_cfd' => 'TEST_SELLO_CFD',
                    'no_certificado_sat' => 'TEST_SAT_CERT',
                    'sello_sat' => 'TEST_SAT_SELLO',
                    'rfc_prov_certif' => 'SAT970701NN3',
                    'archivo_path' => $archivoPath,
                    'estatus_sat' => 'Vigente',
                    'rfc_consultado' => 'BLM170602AA6',
                    'direccion_flujo' => $direccion['tipo'],
                    'complemento_tipo' => $complemento['tipo'],
                    'complemento_json' => json_encode($complemento['datos']),
                    'cfdi_relacionados' => $relacionados
                ];
                
                // Usar reflexión para acceder al método insertarCFDI
                $methodInsert = $reflection->getMethod('insertarCFDI');
                $methodInsert->setAccessible(true);
                
                echo "Intentando inserción de prueba...\n";
                $resultado = $methodInsert->invoke($importador, $datosTest);
                
                if ($resultado) {
                    echo "✅ Inserción exitosa\n";
                    
                    // Verificar que se insertó con los nuevos campos
                    $sqlVerify = "SELECT complemento_tipo, complemento_json, direccion_flujo 
                                 FROM cfdi WHERE uuid = ?";
                    $stmtVerify = $importador->pdo->prepare($sqlVerify);
                    $stmtVerify->execute([$datosTest['uuid']]);
                    
                    if ($rowVerify = $stmtVerify->fetch(PDO::FETCH_ASSOC)) {
                        echo "Campos verificados en BD:\n";
                        foreach ($rowVerify as $campo => $valor) {
                            echo "  {$campo}: " . ($valor ? "✅ " . $valor : "❌ NULL") . "\n";
                        }
                        
                        // Limpiar registro de prueba
                        $sqlClean = "DELETE FROM cfdi WHERE uuid = ?";
                        $stmtClean = $importador->pdo->prepare($sqlClean);
                        $stmtClean->execute([$datosTest['uuid']]);
                        echo "\n🧹 Registro de prueba eliminado\n";
                    }
                } else {
                    echo "❌ Error en inserción\n";
                    $errorInfo = $importador->pdo->errorInfo();
                    if ($errorInfo[2]) {
                        echo "Error SQL: " . $errorInfo[2] . "\n";
                    }
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
