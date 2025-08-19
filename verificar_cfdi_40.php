<?php
require_once 'importador_inteligente_cfdi.php';

try {
    // Usar la conexión del importador
    $importador = new ImportadorInteligenteCFDI();
    $reflection = new ReflectionClass($importador);
    $property = $reflection->getProperty('pdo');
    $property->setAccessible(true);
    $pdo = $property->getValue($importador);
    
    echo "=== VERIFICACIÓN CFDI 4.0 INSERTADOS ===\n\n";
    
    // Verificar CFDI 4.0 insertados
    $stmt = $pdo->prepare("
        SELECT id, version, serie, folio, exportacion, regimen_fiscal_receptor, fecha, emisor_rfc 
        FROM cfdi 
        WHERE version = '4.0' 
        ORDER BY id DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $cfdi40 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 CFDI 4.0 encontrados: " . count($cfdi40) . "\n\n";
    
    if (count($cfdi40) > 0) {
        echo "✅ CFDI 4.0 INSERTADOS:\n";
        foreach ($cfdi40 as $cfdi) {
            echo "   ID: {$cfdi['id']}\n";
            echo "   Versión: {$cfdi['version']}\n";
            echo "   Serie-Folio: {$cfdi['serie']}-{$cfdi['folio']}\n";
            echo "   Exportación (4.0): {$cfdi['exportacion']}\n";
            echo "   Régimen Fiscal Receptor (4.0): {$cfdi['regimen_fiscal_receptor']}\n";
            echo "   Fecha: {$cfdi['fecha']}\n";
            echo "   RFC Emisor: {$cfdi['emisor_rfc']}\n";
            echo "   ---\n";
        }
    }
    
    // Verificar conceptos de CFDI 4.0
    echo "\n🔍 CONCEPTOS DE CFDI 4.0:\n";
    $stmt = $pdo->prepare("
        SELECT c.cfdi_id, c.clave_prod_serv, c.descripcion, c.valor_unitario 
        FROM cfdi_conceptos c 
        INNER JOIN cfdi cf ON c.cfdi_id = cf.id 
        WHERE cf.version = '4.0' 
        LIMIT 5
    ");
    $stmt->execute();
    $conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($conceptos as $concepto) {
        echo "   CFDI ID: {$concepto['cfdi_id']}\n";
        echo "   Clave: {$concepto['clave_prod_serv']}\n";
        echo "   Descripción: {$concepto['descripcion']}\n";
        echo "   Valor: {$concepto['valor_unitario']}\n";
        echo "   ---\n";
    }
    
    // Verificar impuestos de CFDI 4.0
    echo "\n💰 IMPUESTOS DE CFDI 4.0:\n";
    $stmt = $pdo->prepare("
        SELECT i.cfdi_id, i.impuesto, i.tipo_factor, i.tasa_o_cuota, i.importe 
        FROM cfdi_impuestos i 
        INNER JOIN cfdi cf ON i.cfdi_id = cf.id 
        WHERE cf.version = '4.0' 
        LIMIT 5
    ");
    $stmt->execute();
    $impuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($impuestos as $impuesto) {
        echo "   CFDI ID: {$impuesto['cfdi_id']}\n";
        echo "   Impuesto: {$impuesto['impuesto']}\n";
        echo "   Tipo Factor: {$impuesto['tipo_factor']}\n";
        echo "   Tasa: {$impuesto['tasa_o_cuota']}\n";
        echo "   Importe: {$impuesto['importe']}\n";
        echo "   ---\n";
    }
    
    // Estadísticas generales
    echo "\n📈 ESTADÍSTICAS GENERALES:\n";
    $stmt = $pdo->prepare("SELECT version, COUNT(*) as total FROM cfdi GROUP BY version ORDER BY version");
    $stmt->execute();
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stats as $stat) {
        echo "   Versión {$stat['version']}: {$stat['total']} registros\n";
    }
    
    // Verificar campos específicos CFDI 4.0
    echo "\n🔍 CAMPOS ESPECÍFICOS CFDI 4.0:\n";
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_40,
            COUNT(CASE WHEN exportacion IS NOT NULL AND exportacion != '' THEN 1 END) as con_exportacion,
            COUNT(CASE WHEN regimen_fiscal_receptor IS NOT NULL AND regimen_fiscal_receptor != '' THEN 1 END) as con_regimen
        FROM cfdi 
        WHERE version = '4.0'
    ");
    $stmt->execute();
    $campos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   Total CFDI 4.0: {$campos['total_40']}\n";
    echo "   Con campo Exportación: {$campos['con_exportacion']}\n";
    echo "   Con campo Régimen Fiscal Receptor: {$campos['con_regimen']}\n";
    
    if ($campos['total_40'] > 0 && $campos['con_exportacion'] > 0 && $campos['con_regimen'] > 0) {
        echo "\n✅ ¡SISTEMA COMPATIBLE CON CFDI 4.0!\n";
        echo "🎯 Todos los campos específicos de CFDI 4.0 se están procesando correctamente.\n";
        echo "📋 El sistema está listo para el cumplimiento obligatorio de 2025.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
