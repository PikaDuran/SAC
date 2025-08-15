<?php
require_once 'src/config/database.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("TRUNCATE TABLE sat_download_history");
    echo "✅ Tabla sat_download_history limpiada correctamente.\n";
} catch (Exception $e) {
    echo "💥 ERROR: " . $e->getMessage() . "\n";
}
