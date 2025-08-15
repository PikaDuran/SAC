<?php
require_once __DIR__ . '/src/config/database.php';
header('Content-Type: application/json');

$requestId = $_POST['request_id'] ?? $_GET['request_id'] ?? null;
if (!$requestId) {
    echo json_encode(['success' => false, 'message' => 'Falta el parámetro request_id']);
    exit;
}

try {
    $pdo = getDatabase();
    // Buscar el certificado asociado al request_id
    $stmt = $pdo->prepare("SELECT certificate_id FROM sat_download_history WHERE request_id = ? LIMIT 1");
    $stmt->execute([$requestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el certificado para el request_id']);
        exit;
    }
    $certificateId = $row['certificate_id'];

    // Buscar los datos del certificado
    $stmt = $pdo->prepare("SELECT rfc, certificate_path, key_path, password_plain FROM sat_fiel_certificates WHERE id = ? LIMIT 1");
    $stmt->execute([$certificateId]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cert) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el certificado FIEL']);
        exit;
    }

    // Construir rutas absolutas
    $certBaseDir = realpath(__DIR__ . '/storage/fiel_certificates/');
    $certFile = basename($cert['certificate_path']);
    $keyFile = basename($cert['key_path']);
    $certPath = $certBaseDir . DIRECTORY_SEPARATOR . $certFile;
    $keyPath = $certBaseDir . DIRECTORY_SEPARATOR . $keyFile;

    $result = [
        'request_id' => $requestId,
        'certificate_id' => $certificateId,
        'certificate_path_db' => $cert['certificate_path'],
        'key_path_db' => $cert['key_path'],
        'cert_path_real' => $certPath,
        'key_path_real' => $keyPath,
        'cert_exists' => file_exists($certPath),
        'key_exists' => file_exists($keyPath),
    ];
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
