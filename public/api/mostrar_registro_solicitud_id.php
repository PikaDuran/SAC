<?php
// Endpoint simple para mostrar todos los datos de un registro de sat_download_history por id (autoincremental)
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../src/config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Falta el parámetro id']);
    exit;
}

try {
    $pdo = getDatabase();
    $stmt = $pdo->prepare("SELECT * FROM sat_download_history WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el registro']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
