<?php
require 'src/config/database.php';

try {
    $pdo = getDatabase();
    
    echo "=== VERIFICACIÓN DE TODAS LAS TABLAS CFDI ===\n\n";
    
    // Buscar todas las tablas que contengan 'cfdi' en su nombre
    $stmt = $pdo->prepare("SHOW TABLES LIKE '%cfdi%'");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tablas encontradas con 'cfdi':\n";
    foreach ($tables as $table) {
        echo "- {$table}\n";
    }
    
    echo "\n=== VERIFICACIÓN DE REGISTROS EN CADA TABLA ===\n";
    
    $tablas_con_datos = [];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM `{$table}`");
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($count > 0) {
                $tablas_con_datos[] = $table;
                echo "📊 {$table}: {$count} registros\n";
            } else {
                echo "🈳 {$table}: 0 registros (VACÍA)\n";
            }
        } catch (Exception $e) {
            echo "❌ {$table}: Error al consultar - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== ANÁLISIS DE ESTRUCTURAS ===\n";
    
    // Verificar estructura de las tablas con datos
    foreach ($tablas_con_datos as $table) {
        echo "\n--- ESTRUCTURA DE {$table} ---\n";
        try {
            $stmt = $pdo->prepare("DESCRIBE `{$table}`");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($columns as $column) {
                echo "  {$column['Field']} ({$column['Type']}) {$column['Null']} {$column['Key']}\n";
            }
            
            // Mostrar algunos registros de ejemplo
            echo "\n  Ejemplo de datos:\n";
            $stmt = $pdo->prepare("SELECT * FROM `{$table}` LIMIT 2");
            $stmt->execute();
            $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($examples as $i => $row) {
                echo "    Registro " . ($i + 1) . ":\n";
                foreach ($row as $key => $value) {
                    $display_value = is_string($value) ? substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '') : $value;
                    echo "      {$key}: {$display_value}\n";
                }
                echo "\n";
            }
            
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== RECOMENDACIÓN ===\n";
    if (!empty($tablas_con_datos)) {
        echo "Las siguientes tablas tienen datos y deben ser evaluadas:\n";
        foreach ($tablas_con_datos as $table) {
            echo "- {$table}\n";
        }
        echo "\nDecide si estas tablas son necesarias o si deben ser vaciadas también.\n";
    } else {
        echo "✅ Todas las tablas CFDI están vacías.\n";
    }
    
} catch(PDOException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>
