<?php
date_default_timezone_set('America/Mexico_City');
require_once 'src/config/database.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "sat_fiel_certificates:\n";
    $stmt = $pdo->query("SELECT * FROM sat_fiel_certificates");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        print_r($row);
    }
    echo "--------------------------\n";

    echo "sat_download_history:\n";
    $stmt = $pdo->query("SELECT * FROM sat_download_history");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        print_r($row);
    }
    echo "--------------------------\n";

    echo "usuarios:\n";
    $stmt = $pdo->query("SELECT * FROM usuarios");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        print_r($row);
    }
    echo "--------------------------\n";
} catch (Exception $e) {
    echo "💥 ERROR: " . $e->getMessage() . "\n";
}
