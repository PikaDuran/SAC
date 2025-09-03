<?php
$pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('SELECT uuid, serie, folio FROM cfdi WHERE uuid != "702888E7-16B1-4A6E-AAB5-C3F95047C4F2" ORDER BY uuid LIMIT 10');
echo "UUIDs disponibles diferentes al que ya probamos:\n";
while ($row = $stmt->fetch()) {
    echo "UUID: {$row['uuid']} - Serie: {$row['serie']} - Folio: {$row['folio']}\n";
}
