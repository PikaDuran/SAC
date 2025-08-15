<?php
// Mostrar las últimas 20 actividades de todos los usuarios
require_once '../../src/config/database.php';

$pdo = getDatabase();
$stmt = $pdo->prepare("SELECT al.*, u.usuario as username FROM activity_logs al LEFT JOIN usuarios u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 20");
$stmt->execute();
$logs = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($logs);
