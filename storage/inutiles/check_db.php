<?php
// Script temporal para verificar estructura de base de datos
require_once 'src/config/database.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "=== VERIFICACIÓN DE BASE DE DATOS SAC ===\n\n";

    // Mostrar todas las tablas
    echo "TABLAS EXISTENTES:\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "- $table\n";
    }

    echo "\n=== ESTRUCTURA DETALLADA ===\n\n";

    // Para cada tabla, mostrar su estructura
    foreach ($tables as $table) {
        echo "TABLA: $table\n";
        echo str_repeat("-", 50) . "\n";

        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll();

        foreach ($columns as $column) {
            echo sprintf(
                "%-20s %-15s %-10s %-10s %-10s %s\n",
                $column['Field'],
                $column['Type'],
                $column['Null'],
                $column['Key'],
                $column['Default'],
                $column['Extra']
            );
        }
        echo "\n";
    }

    // Verificar si existen tablas de descarga masiva
    echo "=== VERIFICACIÓN DESCARGA MASIVA ===\n";
    $descarga_tables = array_filter($tables, function ($table) {
        return strpos($table, 'descarga') !== false || strpos($table, 'sat_download') !== false;
    });

    if (empty($descarga_tables)) {
        echo "❌ NO hay tablas específicas para descarga masiva\n";
        echo "📝 Necesario crear tabla: sat_descarga_masiva\n";
    } else {
        echo "✅ Tablas de descarga masiva encontradas:\n";
        foreach ($descarga_tables as $table) {
            echo "- $table\n";
        }
    }
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}
