<?php
$pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');

echo "=== ESTRUCTURA DE LA TABLA CFDI ===\n";
$stmt = $pdo->query('DESCRIBE cfdi');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-30s %-15s %s\n", $row['Field'], $row['Type'], $row['Null'] == 'NO' ? 'NOT NULL' : 'NULL');
}

echo "\n=== VERIFICANDO CAMPO fecha_timbrado ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM cfdi LIKE 'fecha_timbrado'");
$existe = $stmt->fetch();
if ($existe) {
    echo "✅ Campo fecha_timbrado EXISTE\n";
} else {
    echo "❌ Campo fecha_timbrado NO EXISTE\n";
}
