<?php
try {
    require_once 'src/config/database.php';
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    echo "=== Verificando tabla sat_download_history ===\n";

    // Verificar si la tabla existe
    $result = $pdo->query("SHOW TABLES LIKE 'sat_download_history'");
    if ($result->rowCount() == 0) {
        echo "❌ La tabla sat_download_history NO existe\n";
        exit(1);
    }

    echo "✅ La tabla sat_download_history existe\n";

    // Mostrar columnas
    $result = $pdo->query('DESCRIBE sat_download_history');
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "\n=== Columnas de la tabla ===\n";
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . "\n";
    }

    // Contar registros
    $result = $pdo->query('SELECT COUNT(*) as total FROM sat_download_history');
    $count = $result->fetch(PDO::FETCH_ASSOC);
    echo "\n=== Total de registros ===\n";
    echo "Registros: " . $count['total'] . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
