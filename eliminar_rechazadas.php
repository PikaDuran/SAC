<?php
require_once 'src/config/database.php';

try {
    $pdo = getDatabase();

    echo "=== ELIMINANDO SOLICITUDES RECHAZADAS ===\n";

    // Eliminar registros rechazados
    $stmt = $pdo->prepare("
        DELETE FROM sat_download_history 
        WHERE status IN ('REJECTED', 'CANCELLED') 
           OR estatus_solicitud = '5'
    ");

    $stmt->execute();
    $eliminados = $stmt->rowCount();

    echo "Registros eliminados: $eliminados\n\n";

    // Verificar estado actual de la tabla
    echo "=== ESTADO ACTUAL DE LA TABLA ===\n";
    $stmt = $pdo->query("
        SELECT 
            id, 
            LEFT(request_id,12) as req_id, 
            rfc_emisor, 
            status, 
            estatus_solicitud,
            tipo_documento,
            DATE(date_from) as desde,
            DATE(date_to) as hasta
        FROM sat_download_history 
        ORDER BY id
    ");

    $restantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($restantes) > 0) {
        echo "Registros restantes:\n";
        foreach ($restantes as $row) {
            echo "ID: {$row['id']} | RFC: {$row['rfc_emisor']} | Status: {$row['status']} | Estatus: {$row['estatus_solicitud']} | Tipo: {$row['tipo_documento']} | Período: {$row['desde']} a {$row['hasta']}\n";
        }
    } else {
        echo "No quedan registros en la tabla.\n";
    }

    echo "\nTotal registros restantes: " . count($restantes) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
