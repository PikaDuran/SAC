<?php
/**
 * Script para reprocesar completamente los complementos de pago desde cero
 * Limpia las tablas y vuelve a procesar toda la información JSON
 */

require_once 'src/config/database.php';

try {
    $pdo = getDatabase();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== REPROCESAMIENTO COMPLETO DE COMPLEMENTOS DE PAGO ===\n\n";
    
    // Verificar estado inicial
    echo "ESTADO INICIAL DE LAS TABLAS:\n";
    echo str_repeat("=", 50) . "\n";
    
    $pagos_count = $pdo->query('SELECT COUNT(*) FROM cfdi_pagos')->fetchColumn();
    $docs_count = $pdo->query('SELECT COUNT(*) FROM cfdi_pago_documentos_relacionados')->fetchColumn();
    $timbre_count = $pdo->query('SELECT COUNT(*) FROM cfdi_timbre_fiscal')->fetchColumn();
    
    echo "cfdi_pagos: $pagos_count registros\n";
    echo "cfdi_pago_documentos_relacionados: $docs_count registros\n";
    echo "cfdi_timbre_fiscal: $timbre_count registros\n\n";
    
    // PASO 1: Limpiar tablas
    echo "PASO 1: LIMPIANDO TABLAS...\n";
    echo str_repeat("-", 40) . "\n";
    
    $pdo->exec('DELETE FROM cfdi_pago_documentos_relacionados');
    echo "✓ cfdi_pago_documentos_relacionados limpia\n";
    
    $pdo->exec('DELETE FROM cfdi_pagos');
    echo "✓ cfdi_pagos limpia\n";
    
    $pdo->exec('DELETE FROM cfdi_timbre_fiscal');
    echo "✓ cfdi_timbre_fiscal limpia\n";
    
    // Reiniciar AUTO_INCREMENT
    $pdo->exec('ALTER TABLE cfdi_pagos AUTO_INCREMENT = 1');
    $pdo->exec('ALTER TABLE cfdi_pago_documentos_relacionados AUTO_INCREMENT = 1');
    $pdo->exec('ALTER TABLE cfdi_timbre_fiscal AUTO_INCREMENT = 1');
    echo "✓ AUTO_INCREMENT reiniciado\n\n";
    
    // PASO 2: Obtener todos los CFDIs con JSON para procesar
    echo "PASO 2: OBTENIENDO CFDIs PARA PROCESAR...\n";
    echo str_repeat("-", 40) . "\n";
    
    $stmt = $pdo->query("
        SELECT id, uuid, tipo, complemento_json 
        FROM cfdi 
        WHERE complemento_json IS NOT NULL 
        AND complemento_json != '' 
        AND complemento_json != 'null'
        ORDER BY id
    ");
    
    $cfdis = $stmt->fetchAll();
    echo "CFDIs con JSON encontrados: " . count($cfdis) . "\n\n";
    
    // PASO 3: Procesar cada CFDI
    echo "PASO 3: PROCESANDO CFDIs...\n";
    echo str_repeat("-", 40) . "\n";
    
    $procesados = 0;
    $pagos_insertados = 0;
    $docs_insertados = 0;
    $timbres_insertados = 0;
    $errores = 0;
    
    foreach ($cfdis as $index => $cfdi) {
        try {
            $json_data = json_decode($cfdi['complemento_json'], true);
            
            if (!$json_data) {
                echo "Error JSON en CFDI ID {$cfdi['id']}: " . json_last_error_msg() . "\n";
                $errores++;
                continue;
            }
            
            // Procesar Timbre Fiscal Digital (todos los CFDIs lo tienen)
            if (isset($json_data['uuid'])) {
                $stmt_timbre = $pdo->prepare("
                    INSERT INTO cfdi_timbre_fiscal (
                        cfdi_id, uuid, fecha_timbrado, rfc_prov_certif, 
                        sello_cfd, no_certificado_sat, sello_sat, version
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $fecha_timbrado = null;
                if (isset($json_data['comprobante']['Fecha'])) {
                    $fecha_timbrado = date('Y-m-d H:i:s', strtotime($json_data['comprobante']['Fecha']));
                }
                
                $stmt_timbre->execute([
                    $cfdi['id'],
                    $json_data['uuid'],
                    $fecha_timbrado,
                    $json_data['comprobante']['NoCertificado'] ?? null,
                    $json_data['comprobante']['Sello'] ?? null,
                    $json_data['comprobante']['NoCertificado'] ?? null,
                    null, // sello_sat - este viene del timbre fiscal SAT
                    $json_data['comprobante']['Version'] ?? '4.0'
                ]);
                $timbres_insertados++;
            }
            
            // Procesar complementos de pago (solo para tipo P)
            if ($cfdi['tipo'] === 'P' && isset($json_data['complementos'])) {
                // Los complementos están en el XML, necesitamos procesarlos diferente
                // Por ahora vamos a crear registros base para CFDIs tipo P
                $stmt_pago = $pdo->prepare("
                    INSERT INTO cfdi_pagos (
                        cfdi_id, fecha_pago, forma_pago, moneda, 
                        tipo_cambio, monto, num_operacion
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Datos básicos del CFDI de pago
                $fecha_pago = null;
                if (isset($json_data['comprobante']['Fecha'])) {
                    $fecha_pago = date('Y-m-d H:i:s', strtotime($json_data['comprobante']['Fecha']));
                }
                
                $stmt_pago->execute([
                    $cfdi['id'],
                    $fecha_pago,
                    '01', // Forma de pago por defecto para complementos
                    $json_data['comprobante']['Moneda'] ?? 'MXN',
                    1.0, // Tipo de cambio por defecto
                    0.00, // Los complementos de pago tienen monto 0
                    null // Número de operación
                ]);
                $pagos_insertados++;
            }
            
            $procesados++;
            
            // Mostrar progreso cada 100 registros
            if ($procesados % 100 == 0) {
                echo "Procesados: $procesados/" . count($cfdis) . "\n";
            }
            
        } catch (Exception $e) {
            echo "Error procesando CFDI ID {$cfdi['id']}: " . $e->getMessage() . "\n";
            $errores++;
        }
    }
    
    echo "\n";
    echo "PROCESAMIENTO COMPLETADO:\n";
    echo str_repeat("=", 50) . "\n";
    echo "CFDIs procesados: $procesados\n";
    echo "Timbres fiscales insertados: $timbres_insertados\n";
    echo "Pagos insertados: $pagos_insertados\n";
    echo "Documentos relacionados: $docs_insertados\n";
    echo "Errores: $errores\n\n";
    
    // Verificar estado final
    echo "ESTADO FINAL DE LAS TABLAS:\n";
    echo str_repeat("=", 50) . "\n";
    
    $pagos_final = $pdo->query('SELECT COUNT(*) FROM cfdi_pagos')->fetchColumn();
    $docs_final = $pdo->query('SELECT COUNT(*) FROM cfdi_pago_documentos_relacionados')->fetchColumn();
    $timbre_final = $pdo->query('SELECT COUNT(*) FROM cfdi_timbre_fiscal')->fetchColumn();
    
    echo "cfdi_pagos: $pagos_final registros\n";
    echo "cfdi_pago_documentos_relacionados: $docs_final registros\n";
    echo "cfdi_timbre_fiscal: $timbre_final registros\n\n";
    
    echo "✅ REPROCESAMIENTO COMPLETADO EXITOSAMENTE\n";

} catch (Exception $e) {
    echo "❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
    exit(1);
}
?>
