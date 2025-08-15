<?php
$pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');

// Primero ver qué columnas tiene la tabla
echo "=== ESTRUCTURA DE LA TABLA ===\n";
$stmt = $pdo->query('DESCRIBE sat_download_history');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "{$col['Field']} - {$col['Type']}\n";
}

echo "\n=== SOLICITUDES DE DESCARGA MASIVA SAT ===\n";
$stmt = $pdo->query('SELECT * FROM sat_download_history ORDER BY created_at DESC LIMIT 10');
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($solicitudes as $sol) {
    echo "ID: {$sol['id']}\n";
    echo "REQUEST_ID del SAT: " . ($sol['request_id'] ?? 'NULL') . "\n";
    echo "Certificate ID: " . ($sol['certificate_id'] ?? 'NULL') . "\n";
    echo "Periodo: " . ($sol['fecha_inicial'] ?? 'NULL') . " a " . ($sol['fecha_final'] ?? 'NULL') . "\n";
    echo "Tipo: " . ($sol['tipo_documento'] ?? 'NULL') . "\n";
    echo "Status: " . ($sol['status_code'] ?? 'NULL') . " - " . ($sol['status_message'] ?? 'NULL') . "\n";
    echo "Fecha: " . ($sol['created_at'] ?? 'NULL') . "\n";
    echo "----------------------------------------\n";
}
