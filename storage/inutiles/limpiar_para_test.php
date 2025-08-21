<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== LIMPIEZA DE TABLAS PARA TEST ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. Verificando estado actual de las tablas...\n";
    
    // Lista de todas las tablas CFDI
    $tablasCfdi = [
        'cfdi',
        'cfdi_auditoria', 
        'cfdi_complementos',
        'cfdi_conceptos',
        'cfdi_impuestos',
        'cfdi_pagos',
        'cfdi_pago_documentos_relacionados',
        'cfdi_timbre_fiscal'
    ];
    
    $totalesPorTabla = [];
    
    foreach ($tablasCfdi as $tabla) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $tabla");
            $total = $stmt->fetchColumn();
            $totalesPorTabla[$tabla] = $total;
            echo "   $tabla: $total registros\n";
        } catch (Exception $e) {
            echo "   $tabla: No existe o error\n";
            $totalesPorTabla[$tabla] = 0;
        }
    }
    
    echo "\n2. Vaciando tablas en orden correcto...\n";
    
    // Deshabilitar verificación de claves foráneas temporalmente
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Vaciar tablas en orden correcto (dependencias primero)
    $ordenLimpieza = [
        'cfdi_auditoria',
        'cfdi_timbre_fiscal',
        'cfdi_pago_documentos_relacionados',
        'cfdi_pagos',
        'cfdi_impuestos',
        'cfdi_conceptos',
        'cfdi_complementos',
        'cfdi'
    ];
    
    foreach ($ordenLimpieza as $tabla) {
        if (isset($totalesPorTabla[$tabla]) && $totalesPorTabla[$tabla] > 0) {
            echo "   🗑️ Vaciando tabla $tabla...\n";
            try {
                $pdo->exec("TRUNCATE TABLE $tabla");
                echo "      ✅ $tabla limpiada\n";
            } catch (Exception $e) {
                echo "      ❌ Error en $tabla: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ⏭️ Saltando $tabla (vacía o no existe)\n";
        }
    }
    
    // Rehabilitar verificación de claves foráneas
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "\n3. Verificando limpieza...\n";
    
    $todoLimpio = true;
    $totalGeneral = 0;
    
    foreach ($tablasCfdi as $tabla) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $tabla");
            $nuevo_total = $stmt->fetchColumn();
            echo "   $tabla después de limpieza: $nuevo_total\n";
            $totalGeneral += $nuevo_total;
            if ($nuevo_total > 0) {
                $todoLimpio = false;
            }
        } catch (Exception $e) {
            echo "   $tabla: No accesible\n";
        }
    }
    
    if ($todoLimpio && $totalGeneral == 0) {
        echo "\n✅ TODAS LAS TABLAS CFDI LIMPIADAS EXITOSAMENTE\n";
        echo "✅ Total de registros eliminados: " . array_sum($totalesPorTabla) . "\n";
        echo "✅ Listo para test de inserción CFDI 3.3\n";
        
        // Resetear auto_increment para empezar desde 1
        foreach ($tablasCfdi as $tabla) {
            try {
                $pdo->exec("ALTER TABLE $tabla AUTO_INCREMENT = 1");
            } catch (Exception $e) {
                // Ignorar errores de tablas que no existen
            }
        }
        
        echo "✅ Auto-increment reseteado para todas las tablas\n";
        
    } else {
        echo "\n❌ Error en la limpieza\n";
        echo "   Total de registros restantes: $totalGeneral\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== LIMPIEZA COMPLETADA ===\n";
?>
