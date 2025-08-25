<?php
require_once 'src/config/database.php';
$pdo = getDatabase();
$result = $pdo->query('SELECT request_id, mensaje_verificacion, fecha_inicial, fecha_final, rfc_emisor FROM sat_download_history WHERE id = 7 LIMIT 1');
$row = $result->fetch();
echo 'Request ID: ' . $row['request_id'] . "\n";
echo 'RFC Emisor: ' . $row['rfc_emisor'] . "\n";
echo 'Fecha Inicial: ' . $row['fecha_inicial'] . "\n";
echo 'Fecha Final: ' . $row['fecha_final'] . "\n";
echo 'Mensaje: ' . $row['mensaje_verificacion'] . "\n";
