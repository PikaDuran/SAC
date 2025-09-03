<?php
// Script para leer toda la estructura de la base de datos sac_db y exportar los campos de todas las tablas
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'sac_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "\n==== Tabla: $table ====";
        $stmt = $pdo->query("SHOW FULL COLUMNS FROM `$table`");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "\n{$col['Field']} | {$col['Type']} | {$col['Collation']} | {$col['Null']} | {$col['Key']} | {$col['Default']} | {$col['Extra']} | {$col['Comment']}";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
