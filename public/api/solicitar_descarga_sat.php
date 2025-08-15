<?php
// Endpoint real para solicitar descarga masiva SAT v1.5 (emitidas)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Services/SatDescargaMasivaService.php';

// Recibir parámetros POST
$rfc = $_POST['rfc'] ?? null;
$fecha_inicial = $_POST['fecha_inicial'] ?? null;
$fecha_final = $_POST['fecha_final'] ?? null;
$tipo_solicitud = $_POST['tipo_solicitud'] ?? 'CFDI'; // CFDI o Metadata
$tipo_documento = $_POST['tipo_documento'] ?? 'Emitidas'; // Emitidas o Recibidas

if (!$rfc || !$fecha_inicial || !$fecha_final) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros obligatorios']);
    exit;
}

try {
    $pdo = getDatabase();
    // Buscar certificado activo por RFC
    $stmt = $pdo->prepare("SELECT * FROM sat_fiel_certificates WHERE rfc = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$rfc]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cert) {
        echo json_encode(['success' => false, 'message' => 'No se encontró certificado activo para el RFC']);
        exit;
    }
    $baseDir = realpath(__DIR__ . '/../../');
    $certFolder = $baseDir . '/storage/fiel_certificates/';
    $rutaCer = $certFolder . basename($cert['certificate_path']);
    $rutaKey = $certFolder . basename($cert['key_path']);
    $password = $cert['password_plain'];

    $satService = new App\Services\SatDescargaMasivaService($rutaCer, $rutaKey, $password);

    $parametros = [
        'fecha_inicial' => $fecha_inicial,
        'fecha_final' => $fecha_final,
        'rfc_emisor' => $rfc,
        'tipo_solicitud' => $tipo_solicitud,
        // Puedes agregar más parámetros aquí si el frontend los envía
    ];

    $resultado = $satService->solicitarDescargaEmitidos($parametros);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
