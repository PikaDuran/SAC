<?php
require_once 'src/config/database.php';

try {
    $pdo = getDatabase();

    echo "=== ESTRUCTURA DE LA TABLA ===\n";
    $stmt = $pdo->query("DESCRIBE sat_download_history");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        echo "{$col['Field']} | {$col['Type']} | {$col['Null']} | {$col['Key']}\n";
    }

    echo "\n=== SOLICITUDES RECHAZADAS/CANCELADAS ===\n";

    $stmt = $pdo->query("
        SELECT 
            id, 
            LEFT(request_id,12) as req_id, 
            rfc_emisor, 
            status, 
            estatus_solicitud, 
            mensaje_verificacion
        FROM sat_download_history 
        WHERE status IN ('REJECTED', 'CANCELLED') 
           OR estatus_solicitud = '5' 
        ORDER BY id
    ");

    $rechazadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rechazadas as $row) {
        echo "ID: {$row['id']} | ";
        echo "RFC: {$row['rfc_emisor']} | ";
        echo "Status: {$row['status']} | ";
        echo "Estatus: {$row['estatus_solicitud']} | ";
        echo "Mensaje: {$row['mensaje_verificacion']}\n";
    }

    echo "\nTotal rechazadas: " . count($rechazadas) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
