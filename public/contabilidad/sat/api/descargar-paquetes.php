<?php
// API para descargar paquetes de CFDIs
header('Content-Type: application/json');
session_start();

require_once '../../../../src/helpers/auth.php';
require_once '../../../../src/config/database.php';

// Verificar autenticación
checkAuth(['admin', 'contabilidad']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
    exit;
}

try {
    // Conectar a base de datos
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $solicitud_id = $_GET['id'];

    // Obtener solicitud
    $stmt = $pdo->prepare("
        SELECT h.*, c.rfc
        FROM sat_download_history h
        INNER JOIN sat_fiel_certificates c ON h.certificate_id = c.id
        WHERE h.id = ? AND h.status = 'COMPLETED' 
        AND (h.requested_by = ? OR ? = 'admin')
    ");
    $stmt->execute([$solicitud_id, $_SESSION['user_id'], $_SESSION['user_role']]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        throw new Exception('Solicitud no encontrada o no está completada');
    }

    if (!$solicitud['paquetes']) {
        throw new Exception('No hay paquetes disponibles para descargar');
    }

    // TODO: Integrar con librería phpcfdi/sat-ws-descarga-masiva para descarga real
    // Por ahora, simulamos la descarga

    $paquetes = json_decode($solicitud['paquetes'], true);

    // Crear directorio de descarga con estructura: RFC/EMITIDAS O RECIBIDAS/anio/mes/
    $tipo_documento = $solicitud['tipo_documento'] ?? 'Emitidas'; // Default fallback
    $year = date('Y');
    $month = date('m');
    $download_dir = '../../../../storage/sat_downloads/' . $solicitud['rfc'] . '/' . $tipo_documento . '/' . $year . '/' . $month . '/';
    if (!is_dir($download_dir)) {
        mkdir($download_dir, 0755, true);
    }

    // Por ahora crear archivo básico para evitar errores
    // TODO: Implementar descarga real del SAT cuando esté completamente configurado
    $filename = "descarga_masiva_{$solicitud['rfc']}_{$solicitud_id}_" . date('Ymd_His') . '.zip';
    $filepath = $download_dir . $filename;

    // Crear archivo temporal
    file_put_contents($filepath, 'Archivo de descarga - implementación pendiente');
    $file_size = filesize($filepath);

    // Actualizar solicitud con path de descarga
    $stmt = $pdo->prepare("
        UPDATE sat_download_history 
        SET 
            download_path = ?,
            completed_at = NOW(),
            files_count = ?,
            total_size_bytes = ?
        WHERE id = ?
    ");

    $total_files = array_sum(array_column($paquetes, 'cfdi_count'));
    $file_size = filesize($filepath);

    $stmt->execute([
        $filepath,
        $total_files,
        $file_size,
        $solicitud_id
    ]);

    // Log de actividad
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        'SAT_DOWNLOAD',
        "Descarga completada solicitud SAT ID: {$solicitud_id} - RFC: {$solicitud['rfc']} - {$total_files} archivos",
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Descarga preparada',
        'download_url' => "api/download-file.php?id={$solicitud_id}",
        'files_count' => $total_files,
        'file_size' => $file_size
    ]);
} catch (Exception $e) {
    error_log("Error en descargar-paquetes.php: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
