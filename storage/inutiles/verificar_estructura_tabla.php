<?php
// Verificar estructura de tabla cfdi
require_once 'importador_inteligente_cfdi.php';

try {
    $importador = new ImportadorInteligenteCFDI();
    
    // Usar reflexión para acceder a PDO privado
    $reflection = new ReflectionClass($importador);
    $pdoProperty = $reflection->getProperty('pdo');
    $pdoProperty->setAccessible(true);
    $pdo = $pdoProperty->getValue($importador);
    
    echo "=== ESTRUCTURA DE TABLA CFDI ===\n";
    $stmt = $pdo->query("DESCRIBE cfdi");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "{$column['Field']} - {$column['Type']} - {$column['Null']} - {$column['Default']}\n";
    }
    
    echo "\n=== BÚSQUEDA DE COLUMNAS CON 'PATH' ===\n";
    foreach ($columns as $column) {
        if (stripos($column['Field'], 'path') !== false || stripos($column['Field'], 'archivo') !== false || stripos($column['Field'], 'file') !== false) {
            echo "✅ Encontrado: {$column['Field']}\n";
        }
    }
    
    echo "\n=== VERIFICAR REGISTRO EXISTENTE ===\n";
    $stmt = $pdo->query("SELECT * FROM cfdi LIMIT 1");
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sample) {
        echo "Campos disponibles en tabla:\n";
        foreach (array_keys($sample) as $field) {
            echo "- {$field}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
