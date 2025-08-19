<?php
require 'src/config/database.php';

try {
    $pdo = getDatabase();
    
    echo "=== VACIANDO TABLAS CFDI ADICIONALES ===\n\n";
    
    // Deshabilitar verificaciones de claves foráneas temporalmente
    $pdo->exec("SET foreign_key_checks = 0");
    
    // Tablas adicionales a vaciar (datos de importaciones anteriores)
    $additional_tables = [
        'cfdi_conceptos',     // Conceptos de CFDIs importados
        'cfdi_complementos',  // Complementos de CFDIs importados  
        'cfdi_auditoria'      // Log de auditoría de importaciones
    ];
    
    // Tabla que NO se debe vaciar (catálogo del SAT)
    $preserve_tables = [
        'catalogo_sat_uso_cfdi'  // Catálogo oficial del SAT - NO VACIAR
    ];
    
    echo "TABLAS A VACIAR:\n";
    foreach ($additional_tables as $table) {
        echo "- {$table}\n";
    }
    
    echo "\nTABLAS QUE SE PRESERVAN (catálogos):\n";
    foreach ($preserve_tables as $table) {
        echo "- {$table}\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n\n";
    
    foreach ($additional_tables as $table) {
        echo "Vaciando tabla: {$table}...\n";
        
        // Verificar cuántos registros había antes
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM `{$table}`");
        $stmt->execute();
        $before = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Vaciar la tabla
        $pdo->exec("TRUNCATE TABLE `{$table}`");
        
        // Verificar que se vació
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM `{$table}`");
        $stmt->execute();
        $after = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo "  - Registros eliminados: {$before}\n";
        echo "  - Registros restantes: {$after}\n\n";
    }
    
    // Reactivar verificaciones de claves foráneas
    $pdo->exec("SET foreign_key_checks = 1");
    
    echo "=== VERIFICACIÓN FINAL COMPLETA ===\n\n";
    
    // Verificar TODAS las tablas CFDI
    $all_cfdi_tables = [
        'cfdi',
        'cfdi_auditoria', 
        'cfdi_complementos',
        'cfdi_conceptos',
        'cfdi_impuestos',
        'cfdi_pago_documentos_relacionados',
        'cfdi_pagos',
        'cfdi_timbre_fiscal'
    ];
    
    $total_registros = 0;
    foreach ($all_cfdi_tables as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM `{$table}`");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "📊 {$table}: {$count} registros\n";
        $total_registros += $count;
    }
    
    // Verificar tabla preservada
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM catalogo_sat_uso_cfdi");
    $stmt->execute();
    $catalogo_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "🔒 catalogo_sat_uso_cfdi: {$catalogo_count} registros (PRESERVADA)\n";
    
    echo "\nTOTAL DE REGISTROS EN TABLAS DE DATOS: {$total_registros}\n";
    
    if ($total_registros == 0) {
        echo "\n✅ TODAS LAS TABLAS DE DATOS CFDI HAN SIDO VACIADAS\n";
        echo "✅ Catálogos del SAT preservados: {$catalogo_count} registros\n";
        echo "🚀 Base de datos 100% limpia y lista para importación REAL\n";
    } else {
        echo "\n❌ ERROR: Algunas tablas no se vaciaron completamente\n";
    }
    
} catch(PDOException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>
