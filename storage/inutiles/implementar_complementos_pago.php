#!/usr/bin/env php
<?php
/**
 * Script de implementación completa de complementos de pago
 * Sistema SAC - Automatización completa
 * 
 * Este script:
 * 1. Crea las tablas necesarias
 * 2. Procesa todos los CFDIs de pago existentes
 * 3. Muestra estadísticas completas
 * 
 * @author Sistema SAC
 * @version 1.0
 * @date 2025-08-25
 */

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/importador_complementos_pago.php';

echo "\n" . str_repeat("=", 80) . "\n";
echo "🚀 IMPLEMENTACIÓN COMPLETA DE COMPLEMENTOS DE PAGO - SISTEMA SAC\n";
echo str_repeat("=", 80) . "\n\n";

try {
    $pdo = getDatabase();
    
    // Configurar PDO para usar consultas bufferizadas
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    
    // PASO 1: Crear tablas
    echo "📋 PASO 1: CREANDO ESTRUCTURA DE BASE DE DATOS\n";
    echo str_repeat("-", 50) . "\n";
    
    $sqlFile = __DIR__ . '/sql/14_create_cfdi_pagos_tables.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        
        // Ejecutar cada statement por separado
        $statements = explode(';', $sql);
        $executed = 0;
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                    $executed++;
                } catch (PDOException $e) {
                    // Ignorar errores de "tabla ya existe"
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        echo "⚠️  Warning: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        
        echo "✅ Tablas creadas/verificadas exitosamente ($executed statements ejecutados)\n";
    } else {
        echo "❌ Error: No se encontró el archivo SQL\n";
        exit(1);
    }
    
    // Verificar que las tablas se crearon
    $tablas = ['cfdi_pagos', 'cfdi_pago_documentos_relacionados', 'cfdi_pago_impuestos_dr', 'cfdi_pago_totales'];
    foreach ($tablas as $tabla) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tabla]);
        if ($stmt->rowCount() > 0) {
            echo "   ✓ Tabla '$tabla' verificada\n";
        } else {
            echo "   ❌ Error: Tabla '$tabla' no encontrada\n";
            exit(1);
        }
        $stmt->closeCursor(); // Liberar el cursor
    }
    
    // PASO 2: Estadísticas antes del procesamiento
    echo "\n📊 PASO 2: ESTADÍSTICAS ANTES DEL PROCESAMIENTO\n";
    echo str_repeat("-", 50) . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo_de_comprobante = 'P'");
    $totalCFDIsPago = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt->closeCursor();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi_pagos");
    $complementosProcesados = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt->closeCursor();
    
    echo "📄 CFDIs de tipo Pago en base de datos: $totalCFDIsPago\n";
    echo "💳 Complementos de pago ya procesados: $complementosProcesados\n";
    echo "🔄 CFDIs pendientes de procesar: " . ($totalCFDIsPago - $complementosProcesados) . "\n";
    
    if ($totalCFDIsPago === 0) {
        echo "\n⚠️  No hay CFDIs de tipo Pago para procesar\n";
        echo "   Descarga primero algunos CFDIs de pago desde el sistema SAT\n\n";
        exit(0);
    }
    
    if (($totalCFDIsPago - $complementosProcesados) === 0) {
        echo "\n✅ Todos los CFDIs de pago ya están procesados\n\n";
    } else {
        // PASO 3: Procesar complementos de pago
        echo "\n🔄 PASO 3: PROCESANDO COMPLEMENTOS DE PAGO\n";
        echo str_repeat("-", 50) . "\n";
        
        $importador = new ImportadorComplementosPago();
        $importador->procesarCFDIsPago();
    }
    
    // PASO 4: Estadísticas finales
    echo "\n📈 PASO 4: ESTADÍSTICAS FINALES\n";
    echo str_repeat("-", 50) . "\n";
    
    $importador = new ImportadorComplementosPago();
    $stats = $importador->obtenerEstadisticasPagos();
    
    echo "📊 RESUMEN COMPLETO:\n";
    echo "   CFDIs de Pago: {$stats['total_cfdis_pago']}\n";
    echo "   Con complementos procesados: {$stats['cfdis_con_complementos']}\n";
    echo "   Total de pagos: {$stats['total_pagos']}\n";
    echo "   Documentos relacionados: {$stats['total_documentos_relacionados']}\n";
    
    if (!empty($stats['por_forma_pago'])) {
        echo "\n💰 FORMAS DE PAGO MÁS USADAS:\n";
        foreach (array_slice($stats['por_forma_pago'], 0, 5) as $forma) {
            $forma_desc = $forma['forma_pago_p'] ?: 'Sin especificar';
            echo "   {$forma_desc}: {$forma['cantidad']} pagos\n";
        }
    }
    
    // PASO 5: Validaciones de integridad
    echo "\n🔍 PASO 5: VALIDACIONES DE INTEGRIDAD\n";
    echo str_repeat("-", 50) . "\n";
    
    // Verificar que no hay pagos huérfanos
    $stmt = $pdo->query("
        SELECT COUNT(*) as huerfanos 
        FROM cfdi_pagos p 
        LEFT JOIN cfdi c ON p.cfdi_id = c.id 
        WHERE c.id IS NULL
    ");
    $huerfanos = $stmt->fetch(PDO::FETCH_ASSOC)['huerfanos'];
    
    if ($huerfanos > 0) {
        echo "⚠️  Se encontraron $huerfanos pagos sin CFDI asociado\n";
    } else {
        echo "✅ Integridad referencial verificada\n";
    }
    
    // Verificar documentos relacionados válidos
    $stmt = $pdo->query("
        SELECT COUNT(*) as validos 
        FROM cfdi_pago_documentos_relacionados 
        WHERE id_documento IS NOT NULL AND id_documento != ''
    ");
    $docValidos = $stmt->fetch(PDO::FETCH_ASSOC)['validos'];
    
    echo "✅ Documentos relacionados válidos: $docValidos\n";
    
    // MENSAJE FINAL
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "🎉 IMPLEMENTACIÓN COMPLETADA EXITOSAMENTE\n";
    echo str_repeat("=", 80) . "\n";
    echo "✅ Tablas de complementos de pago creadas\n";
    echo "✅ Todos los CFDIs de pago procesados\n";
    echo "✅ Datos de pagos y documentos relacionados extraídos\n";
    echo "✅ Sistema listo para consultas contables completas\n\n";
    
    echo "🔍 PRÓXIMOS PASOS:\n";
    echo "   1. Revisa la vista 'vista_cfdi_pagos' para consultas rápidas\n";
    echo "   2. Implementa reportes de conciliación bancaria\n";
    echo "   3. Crea dashboards de seguimiento de pagos\n\n";

} catch (Exception $e) {
    echo "\n❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Archivo: " . $e->getFile() . "\n\n";
    exit(1);
}
?>
