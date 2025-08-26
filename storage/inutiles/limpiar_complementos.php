<?php
// Limpiar todas las tablas de complementos de pago para empezar desde cero

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🧹 Limpiando tablas de complementos de pago..." . PHP_EOL;
    
    // Limpiar en orden correcto por foreign keys
    $pdo->exec('DELETE FROM cfdi_pago_totales');
    echo "✅ cfdi_pago_totales limpiada" . PHP_EOL;
    
    $pdo->exec('DELETE FROM cfdi_pago_impuestos_dr');  
    echo "✅ cfdi_pago_impuestos_dr limpiada" . PHP_EOL;
    
    $pdo->exec('DELETE FROM cfdi_pago_documentos_relacionados');
    echo "✅ cfdi_pago_documentos_relacionados limpiada" . PHP_EOL;
    
    $pdo->exec('DELETE FROM cfdi_pagos');
    echo "✅ cfdi_pagos limpiada" . PHP_EOL;
    
    // Verificar que estén vacías
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi_pagos');
    $total = $stmt->fetch()['total'];
    echo PHP_EOL . "🔍 Verificación: $total registros en cfdi_pagos (debe ser 0)" . PHP_EOL;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P'");
    $total_cfdi = $stmt->fetch()['total'];
    echo "📊 CFDIs tipo P disponibles para procesar: $total_cfdi" . PHP_EOL;
    
    echo PHP_EOL . "🎯 Tablas limpiadas correctamente. Listo para procesamiento completo." . PHP_EOL;
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}
?>
