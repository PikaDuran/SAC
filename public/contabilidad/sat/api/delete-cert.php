<?php
// API para eliminar certificado e.Firma
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
session_start();

require_once '../../../../src/helpers/auth.php';
require_once '../../../../src/config/database.php';

// Verificar autenticación
checkAuth(['admin', 'contabilidad']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Obtener datos JSON
    $input = json_decode(file_get_contents('php://input'), true);

    // Log para debugging
    error_log("Delete cert input: " . print_r($input, true));

    if (!isset($input['id']) || !is_numeric($input['id'])) {
        throw new Exception('ID de certificado inválido');
    }

    $cert_id = (int)$input['id'];

    // Log para debugging
    error_log("Attempting to delete cert ID: " . $cert_id);

    // Verificar que la sesión tenga user_id
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }

    // Log para debugging
    error_log("User ID from session: " . $_SESSION['user_id']);

    // Conectar a base de datos
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verificar que el certificado existe y pertenece al usuario
    $stmt = $pdo->prepare("
        SELECT id, rfc, certificate_path, key_path 
        FROM sat_fiel_certificates 
        WHERE id = ? AND created_by = ?
    ");
    $stmt->execute([$cert_id, $_SESSION['user_id']]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$certificate) {
        throw new Exception('Certificado no encontrado');
    }

    // Eliminar archivos físicos si existen
    if (!empty($certificate['certificate_path']) && file_exists($certificate['certificate_path'])) {
        unlink($certificate['certificate_path']);
    }

    if (!empty($certificate['key_path']) && file_exists($certificate['key_path'])) {
        unlink($certificate['key_path']);
    }

    // Eliminar registro de la base de datos
    $stmt = $pdo->prepare("DELETE FROM sat_fiel_certificates WHERE id = ?");
    $result = $stmt->execute([$cert_id]);

    if (!$result) {
        throw new Exception('Error al eliminar el certificado de la base de datos');
    }

    // Forzar log de actividad, y si falla mostrar error visible
    require_once '../../../../src/config/database.php';
    $logOk = logUserActivity(
        'DELETE_CERTIFICATE',
        "Eliminó certificado e.Firma RFC: {$certificate['rfc']}",
        MODULE_EFIRMA,
        $cert_id
    );
    if (!$logOk) {
        echo json_encode([
            'success' => false,
            'message' => 'Certificado eliminado, pero NO se pudo registrar la actividad para la campana.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Certificado eliminado correctamente'
    ]);
} catch (Exception $e) {
    error_log("Error en delete-cert.php: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
