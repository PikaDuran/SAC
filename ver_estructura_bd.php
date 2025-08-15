<?php
date_default_timezone_set('America/Mexico_City');
// Script para mostrar la estructura de todas las tablas en la base sac_db
require_once __DIR__ . '/src/config/database.php';
$pdo = getDatabase();

echo "\nEstructura de todas las tablas en sac_db:\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo "\nTabla: $table\n";
    $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "  " . $col['Field'] . "\t" . $col['Type'] . "\t" . $col['Null'] . "\t" . $col['Key'] . "\t" . $col['Default'] . "\t" . $col['Extra'] . "\n";
    }
}
