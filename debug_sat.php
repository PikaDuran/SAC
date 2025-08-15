<?php
$pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');

echo "=== DATOS DE LAS SOLICITUDES ===\n";
// Ver todas las solicitudes con request_id real del SAT
$stmt = $pdo->query('SELECT id, certificate_id, request_id, status, estatus_solicitud, rfc_emisor, fecha_inicial, fecha_final, tipo_documento, requested_at FROM sat_download_history ORDER BY id DESC');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . "\n";
    echo "Certificate ID: " . $row['certificate_id'] . "\n";
    echo "REQUEST_ID (Token SAT): " . ($row['request_id'] ?? 'NULL') . "\n";
    echo "Status: " . $row['status'] . "\n";
    echo "Estatus Solicitud: " . ($row['estatus_solicitud'] ?? 'NULL') . "\n";
    echo "RFC Emisor: " . ($row['rfc_emisor'] ?? 'NULL') . "\n";
    echo "Fechas: " . ($row['fecha_inicial'] ?? 'NULL') . " a " . ($row['fecha_final'] ?? 'NULL') . "\n";
    echo "Tipo: " . ($row['tipo_documento'] ?? 'NULL') . "\n";
    echo "Fecha Solicitud: " . ($row['requested_at'] ?? 'NULL') . "\n";
    echo "===================\n";
}
