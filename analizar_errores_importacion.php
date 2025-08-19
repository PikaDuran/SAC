<?php
require 'src/config/database.php';

try {
    $pdo = getDatabase();
    echo "=== ANÁLISIS DE ERRORES EN AUDITORÍA ===\n\n";
    
    $stmt = $pdo->prepare('SELECT estado, COUNT(*) as cantidad FROM cfdi_auditoria GROUP BY estado');
    $stmt->execute();
    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Estados en auditoría:\n";
    foreach ($estados as $estado) {
        echo "- " . $estado['estado'] . ": " . $estado['cantidad'] . " registros\n";
    }
    
    echo "\nPrimeros 10 errores específicos:\n";
    $stmt = $pdo->prepare('SELECT archivo, mensaje FROM cfdi_auditoria WHERE estado = "ERROR" LIMIT 10');
    $stmt->execute();
    $errores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($errores as $i => $error) {
        $archivo_corto = basename($error['archivo']);
        echo "\n" . ($i + 1) . ". Archivo: " . $archivo_corto . "\n";
        echo "   Error: " . $error['mensaje'] . "\n";
    }
    
    echo "\n=== VERIFICACIÓN DE DATOS INSERTADOS ===\n";
    
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM cfdi');
    $stmt->execute();
    $total_cfdi = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "CFDIs insertados: " . $total_cfdi['total'] . "\n";
    
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM cfdi_conceptos');
    $stmt->execute();
    $total_conceptos = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Conceptos insertados: " . $total_conceptos['total'] . "\n";
    
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM cfdi_complementos');
    $stmt->execute();
    $total_complementos = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Complementos insertados: " . $total_complementos['total'] . "\n";
    
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>
