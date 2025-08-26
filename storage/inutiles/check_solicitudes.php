<?php
require_once 'src/config/database.php';
$pdo = getDatabase();
$result = $pdo->query('SELECT id, request_id, rfc_emisor, status, estatus_solicitud, fecha_solicitud, tipo_documento FROM sat_download_history ORDER BY id DESC LIMIT 10');
echo "ID | Request ID | RFC Emisor | Status | Estatus Solicitud | Fecha | Tipo\n";
echo "===================================================================\n";
while ($row = $result->fetch()) {
    echo $row['id'] . ' | ' . substr($row['request_id'], 0, 8) . '... | ' . $row['rfc_emisor'] . ' | ' . $row['status'] . ' | ' . $row['estatus_solicitud'] . ' | ' . $row['fecha_solicitud'] . ' | ' . $row['tipo_documento'] . "\n";
}
