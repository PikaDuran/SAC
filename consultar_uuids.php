<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');
    $stmt = $pdo->query('SELECT uuid, ruta_xml FROM cfdi LIMIT 10');

    echo "UUIDs disponibles en la base de datos:\n";
    echo "=====================================\n";

    $count = 0;
    while ($row = $stmt->fetch()) {
        $count++;
        echo "$count. UUID: " . $row['uuid'] . "\n";
        echo "   Archivo: " . $row['ruta_xml'] . "\n";
        echo "   Existe archivo: " . (file_exists($row['ruta_xml']) ? 'SÍ' : 'NO') . "\n\n";
    }

    if ($count == 0) {
        echo "No hay registros en la tabla cfdi\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
