<?php
require_once 'src/config/database.php';

try {
    $pdo = getDatabase();

    echo "=== BACKUP DE SOLICITUDES RECHAZADAS ===\n";

    // Crear backup de las solicitudes rechazadas
    $stmt = $pdo->query("
        SELECT * FROM sat_download_history 
        WHERE status IN ('REJECTED', 'CANCELLED') 
           OR estatus_solicitud = '5' 
        ORDER BY id
    ");

    $rechazadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Guardar backup en archivo JSON
    $backupData = [
        'fecha_backup' => date('Y-m-d H:i:s'),
        'total_registros' => count($rechazadas),
        'criterio_eliminacion' => "status IN ('REJECTED', 'CANCELLED') OR estatus_solicitud = '5'",
        'registros' => $rechazadas
    ];

    $backupFile = 'storage/backup_solicitudes_rechazadas_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo "Backup guardado en: $backupFile\n";
    echo "Total registros respaldados: " . count($rechazadas) . "\n\n";

    // Mostrar resumen de lo que se va a eliminar
    echo "=== REGISTROS QUE SE ELIMINARÁN ===\n";
    foreach ($rechazadas as $row) {
        echo "ID: {$row['id']} | RFC: {$row['rfc_emisor']} | Request ID: " . substr($row['request_id'], 0, 12) . "... | Status: {$row['status']} | Estatus: {$row['estatus_solicitud']}\n";
    }

    echo "\n¿Proceder con la eliminación? (s/n): ";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
