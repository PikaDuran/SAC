<?php
require_once 'src/config/database.php';

$pdo = getDatabase();
echo "=== TABLAS CFDI ===\n";

$stmt = $pdo->query("SHOW TABLES LIKE '%cfdi%'");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo "Tabla: {$row[0]}\n";
    $desc = $pdo->query("DESCRIBE {$row[0]}");
    while ($col = $desc->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    echo "\n";
}
