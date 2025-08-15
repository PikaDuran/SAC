<?php
header('Content-Type: application/json');
session_start();

echo json_encode([
    'success' => true,
    'message' => 'API de prueba funcionando',
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? 'no_set',
    'user_role' => $_SESSION['user_role'] ?? 'no_set',
    'timestamp' => date('Y-m-d H:i:s')
]);
