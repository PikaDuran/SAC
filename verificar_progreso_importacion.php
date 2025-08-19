<?php
require 'src/config/database.php';

echo "=== VERIFICACIÓN DE PROGRESO DE IMPORTACIÓN ===\n\n";

try {
    $pdo = getDatabase();
    
    // Verificar CFDIs totales
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM cfdi');
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📋 CFDIs importados hasta ahora: " . $total['total'] . "\n";
    
    // Verificar complementos de pago
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM cfdi WHERE tipo = "P"');
    $stmt->execute();
    $pagos = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "💰 Complementos de pago (tipo P): " . $pagos['total'] . "\n";
    
    // Verificar registros de auditoría
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM cfdi_auditoria');
    $stmt->execute();
    $auditoria = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📊 Registros de auditoría: " . $auditoria['total'] . "\n";
    
    // Verificar timbre fiscal
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM cfdi_timbre_fiscal');
    $stmt->execute();
    $timbres = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "🏷️  Timbres fiscales: " . $timbres['total'] . "\n";
    
    // Verificar complementos
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM cfdi_complementos');
    $stmt->execute();
    $complementos = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📎 Complementos: " . $complementos['total'] . "\n";
    
    // Últimos registros insertados
    $stmt = $pdo->prepare('SELECT uuid, fecha, rfc_emisor, tipo FROM cfdi ORDER BY id DESC LIMIT 5');
    $stmt->execute();
    $ultimos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n--- ÚLTIMOS 5 CFDIs IMPORTADOS ---\n";
    foreach ($ultimos as $cfdi) {
        echo "UUID: " . substr($cfdi['uuid'], 0, 20) . "... | ";
        echo "Fecha: " . $cfdi['fecha'] . " | ";
        echo "Emisor: " . $cfdi['rfc_emisor'] . " | ";
        echo "Tipo: " . $cfdi['tipo'] . "\n";
    }
    
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>
