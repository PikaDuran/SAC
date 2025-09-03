<?php
require_once 'src/config/database.php';
try {
    $pdo = getDatabase();
    echo "📋 TABLAS EXISTENTES:\n";
    echo str_repeat('=', 40) . "\n";
    $stmt = $pdo->query('SHOW TABLES');
    $tables = [];
    while ($row = $stmt->fetch()) {
        $tables[] = $row[0];
    }

    echo "Todas las tablas:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }

    echo "\nTablas de catálogos:\n";
    foreach ($tables as $table) {
        if (strpos($table, 'cat_') === 0) {
            echo "  ✅ $table\n";
        }
    }

    echo "\nTablas que contienen 'moneda':\n";
    foreach ($tables as $table) {
        if (strpos($table, 'moneda') !== false) {
            echo "  💰 $table\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
