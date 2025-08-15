<?php
// Endpoint para marcar todas las notificaciones del usuario como leídas
require_once '../../src/config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$pdo = getDatabase();
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("UPDATE activity_logs SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);

header('Content-Type: application/json');
echo json_encode(['success' => true]);
