<?php
// API para descargar paquetes de CFDIs usando implementación real SAT
date_default_timezone_set('America/Mexico_City');
header('Content-Type: application/json');
session_start();

require_once '../../../../vendor/autoload.php';
require_once '../../../../src/helpers/auth.php';
require_once '../../../../src/config/database.php';
require_once '../../../../src/Services/SatDescargaMasivaService.php';

use App\Services\SatDescargaMasivaService;

// Verificar autenticación
checkAuth(['admin', 'contabilidad']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método POST requerido']);
    exit;
}

$id = $_POST['id'] ?? $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
    exit;
}

try {
    $pdo = getDatabase();

    // Obtener solicitud con paquetes disponibles
    $stmt = $pdo->prepare("
        SELECT h.*, c.rfc, c.certificate_path, c.key_path, c.password_plain
        FROM sat_download_history h
        INNER JOIN sat_fiel_certificates c ON h.certificate_id = c.id
        WHERE h.id = ? 
        AND h.status IN ('COMPLETED', 'REQUESTED')
        AND h.paquetes IS NOT NULL 
        AND h.paquetes != '[]'
        AND (h.requested_by = ? OR ? = 'admin')
    ");
    $stmt->execute([$id, $_SESSION['user_id'], $_SESSION['user_role']]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        throw new Exception('Solicitud no encontrada, no completada, o sin paquetes disponibles');
    }

    $paquetes = json_decode($solicitud['paquetes'], true);
    if (empty($paquetes)) {
        throw new Exception('No hay paquetes disponibles para descargar');
    }

    // Crear servicio SAT real
    $certBaseDir = realpath(__DIR__ . '/../../../../storage/fiel_certificates/');
    $certFile = basename($solicitud['certificate_path']);
    $keyFile = basename($solicitud['key_path']);
    $certPath = $certBaseDir . DIRECTORY_SEPARATOR . $certFile;
    $keyPath = $certBaseDir . DIRECTORY_SEPARATOR . $keyFile;

    $service = SatDescargaMasivaService::fromCertificateFiles(
        $certPath,
        $keyPath,
        $solicitud['password_plain']
    );

    // Crear directorio con estructura: RFC/EMITIDAS|RECIBIDAS/año/mes/
    $tipo_documento = $solicitud['tipo_documento'] ?? 'Emitidas';
    $year = date('Y', strtotime($solicitud['date_from']));
    $month = date('m', strtotime($solicitud['date_from']));
    $downloadPath = realpath(__DIR__ . '/../../../../storage/sat_downloads/') .
        DIRECTORY_SEPARATOR . $solicitud['rfc'] .
        DIRECTORY_SEPARATOR . strtoupper($tipo_documento) .
        DIRECTORY_SEPARATOR . $year .
        DIRECTORY_SEPARATOR . $month;

    // Descargar paquetes reales del SAT
    $resultado = $service->descargarPaquetes(
        $solicitud['request_id'],
        $paquetes,
        $downloadPath
    );

    if (!$resultado['success']) {
        throw new Exception($resultado['message']);
    }

    // Actualizar solicitud con información de descarga
    $stmt = $pdo->prepare("
        UPDATE sat_download_history 
        SET 
            status = 'COMPLETED',
            download_path = ?,
            completed_at = NOW(),
            files_count = ?,
            total_size_bytes = ?
        WHERE id = ?
    ");

    $totalSize = array_sum(array_column($resultado['files'], 'size'));
    $stmt->execute([
        $downloadPath,
        $resultado['total_files'],
        $totalSize,
        $id
    ]);

    // Log de actividad
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        'SAT_DOWNLOAD',
        "Descarga real SAT completada - ID: {$id} - RFC: {$solicitud['rfc']} - {$resultado['total_files']} archivos",
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Descarga completada del SAT',
        'data' => [
            'request_id' => $solicitud['request_id'],
            'rfc' => $solicitud['rfc'],
            'files_downloaded' => $resultado['total_files'],
            'total_size' => $totalSize,
            'download_path' => $downloadPath,
            'files' => $resultado['files']
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    error_log("Error en descargar-paquetes.php: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Error descargando del SAT: ' . $e->getMessage()
    ]);
}
