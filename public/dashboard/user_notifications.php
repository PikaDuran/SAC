<?php
// Endpoint para notificaciones del usuario logueado
require_once '../../src/config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$pdo = getDatabase();
$userId = $_SESSION['user_id'];

// Obtener las últimas 3 actividades del usuario
$stmt = $pdo->prepare("SELECT id, action, description, created_at, is_read FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$userId]);
$logs = $stmt->fetchAll();

// Contar las no leídas
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND is_read = 0");
$stmt2->execute([$userId]);
$newCount = (int)$stmt2->fetchColumn();

header('Content-Type: application/json');
echo json_encode([
    'notifications' => $logs,
    'newCount' => $newCount
]);
