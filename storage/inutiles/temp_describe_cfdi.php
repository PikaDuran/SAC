<?php
require_once 'src/config/database.php';

$pdo = getDatabase();
$stmt = $pdo->query('DESCRIBE cfdi');
echo "COLUMNAS DE LA TABLA CFDI:\n";
echo str_repeat("=", 50) . "\n";
while ($row = $stmt->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
