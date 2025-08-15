<?php
// API para listar solicitudes de descarga masiva
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
session_start();

try {
    require_once '../../../../src/helpers/auth.php';
    require_once '../../../../src/config/database.php';

    // Verificar autenticación
    checkAuth(['admin', 'contabilidad']);

    // Conectar a base de datos
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Obtener solicitudes del usuario actual (o todas si es admin)
    $userRole = $_SESSION['user_role'] ?? 'user';
    $userId = $_SESSION['user_id'] ?? 0;

    if ($userRole === 'admin') {
        // Admin ve todas las solicitudes
        $stmt = $pdo->prepare("
            SELECT 
                h.*,
                c.rfc,
                c.legal_name
            FROM sat_download_history h
            LEFT JOIN sat_fiel_certificates c ON h.certificate_id = c.id
            ORDER BY h.fecha_solicitud DESC
            LIMIT 50
        ");
        $stmt->execute();
    } else {
        // Usuario normal solo ve sus solicitudes
        $stmt = $pdo->prepare("
            SELECT 
                h.*,
                c.rfc,
                c.legal_name
            FROM sat_download_history h
            LEFT JOIN sat_fiel_certificates c ON h.certificate_id = c.id
            WHERE h.requested_by = ?
            ORDER BY h.fecha_solicitud DESC
            LIMIT 50
        ");
        $stmt->execute([$userId]);
    }

    $solicitudes_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mapeo de campos para frontend (ajustar nombres y valores)
    $solicitudes = array_map(function ($row) {
        return [
            'id' => $row['id'],
            'token_sat' => $row['request_id'],
            'rfc_emisor' => $row['rfc_emisor'] ?: $row['rfc'],
            'paquetes' => $row['files_count'],
            'fecha_inicial' => $row['date_from'],
            'fecha_final' => $row['date_to'],
            'ultima_actualizacion' => $row['ultima_actualizacion'] ?? $row['updated_at'] ?? null,
            'tipo_documento' => $row['tipo_documento'] ?? $row['request_type'] ?? '-',
            'mensaje_verificacion' => isset($row['mensaje_verificacion']) && $row['mensaje_verificacion'] !== '' ? $row['mensaje_verificacion'] : '-',
            'estatus' => isset($row['status']) && $row['status'] !== '' ? $row['status'] : '-',
            'mensaje' => $row['error_message'],
            'fecha_solicitud' => $row['requested_at'],
            'rfc_certificado' => $row['rfc'],
            'razon_social' => $row['legal_name'],
        ];
    }, $solicitudes_raw);

    echo json_encode([
        'success' => true,
        'data' => $solicitudes,
        'count' => count($solicitudes)
    ]);
} catch (Exception $e) {
    error_log("Error en listar-solicitudes.php: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar solicitudes: ' . $e->getMessage(),
        'data' => []
    ]);
}
