<?php
require_once 'conexion_bd.php';

try {
    echo "===========================================\n";
    echo "  VERIFICAR ESTRUCTURA TABLA CFDI\n";
    echo "===========================================\n\n";

    // Mostrar estructura de la tabla cfdi
    $stmt = $pdo->query("DESCRIBE cfdi");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "📋 Columnas de la tabla 'cfdi':\n";
    echo str_repeat("-", 50) . "\n";

    foreach ($columns as $column) {
        echo sprintf(
            "%-25s | %-15s | %-10s\n",
            $column['Field'],
            $column['Type'],
            $column['Null']
        );
    }

    echo str_repeat("-", 50) . "\n\n";

    // Buscar columnas que contengan 'uuid'
    echo "🔍 Columnas que contienen 'uuid':\n";
    foreach ($columns as $column) {
        if (stripos($column['Field'], 'uuid') !== false) {
            echo "✅ " . $column['Field'] . "\n";
        }
    }

    echo "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
