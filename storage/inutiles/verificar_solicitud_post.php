<?php
date_default_timezone_set('America/Mexico_City');
require_once __DIR__ . '/vendor/autoload.php';
// Verifica el estado real de una solicitud SAT por POST (id de la solicitud)
header('Content-Type: application/json');

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/Services/SatDescargaMasivaService.php';

use App\Services\SatDescargaMasivaService;


// Permitir ejecución por web (POST) o CLI (terminal)
if (php_sapi_name() === 'cli') {
    // Leer JSON desde stdin
    $stdin = file_get_contents('php://stdin');
    $data = json_decode($stdin, true);
    if (!isset($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Falta el parámetro id']);
        exit;
    }
    $solicitud_id = $data['id'];
} else {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido, usa POST']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Falta el parámetro id']);
        exit;
    }
    $solicitud_id = $data['id'];
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->prepare("
        SELECT h.*, c.rfc, c.certificate_path, c.key_path, c.password_hash, c.password_plain
        FROM sat_download_history h
        INNER JOIN sat_fiel_certificates c ON h.certificate_id = c.id
        WHERE h.id = ?
    ");
    $stmt->execute([$solicitud_id]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$solicitud) {
        throw new Exception('Solicitud no encontrada');
    }
    if (empty($solicitud['request_id'])) {
        throw new Exception('Request ID no encontrado para esta solicitud');
    }
    $password = $solicitud['password_plain'];
    if (empty($password)) {
        throw new Exception('No se encontró contraseña para el certificado');
    }
    $satService = new SatDescargaMasivaService(
        $solicitud['certificate_path'],
        $solicitud['key_path'],
        $password
    );
    $verificacion = $satService->verificarEstadoSolicitud($solicitud['request_id']);
    echo json_encode($verificacion);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
