<?php
// API para verificar estado de solicitud en el SAT - Implementación real según documentación SAT v1.5
header('Content-Type: application/json');

session_start();

// Manejo global de errores y excepciones para siempre devolver JSON
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "ERROR PHP: $errstr en $errfile:$errline"
    ]);
    exit;
});
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'ERROR: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
});

require_once '../../../../src/helpers/auth.php';
require_once '../../../../src/config/database.php';
require_once '../../../../src/Services/SatDescargaMasivaService.php';

use App\Services\SatDescargaMasivaService;

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

    // Obtener solicitud con datos del certificado
    $stmt = $pdo->prepare("
        SELECT h.*, c.rfc, c.certificate_path, c.key_path, c.password_hash, c.password_plain
        FROM sat_download_history h
        INNER JOIN sat_fiel_certificates c ON h.certificate_id = c.id
        WHERE h.id = ? AND (h.requested_by = ? OR ? = 'admin')
    ");
    $stmt->execute([$solicitud_id, $_SESSION['user_id'], $_SESSION['rol']]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        throw new Exception('Solicitud no encontrada');
    }

    // Verificar que tenemos el request_id del SAT
    if (empty($solicitud['request_id'])) {
        throw new Exception('Request ID no encontrado para esta solicitud');
    }

    // VERIFICACIÓN REAL CON EL SAT - Requiere contraseña del certificado
    $password = $solicitud['password_plain'];
    if (empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró contraseña para el certificado',
            'needs_password' => true,
            'current_status' => $solicitud['status'],
            'last_check' => $solicitud['ultima_actualizacion']
        ]);
        exit;
    }

    try {
        // Inicializar servicio SAT con certificado
        $satService = new SatDescargaMasivaService(
            $solicitud['certificate_path'],
            $solicitud['key_path'],
            $password
        );

        // Verificar estado real en el SAT
        $verificacion = $satService->verificarEstadoSolicitud($solicitud['request_id']);

        if (!$verificacion['success']) {
            throw new Exception('Error verificando con el SAT: ' . $verificacion['message']);
        }

        $datos = $verificacion['data'];

        // Mapear estados del SAT a estados internos
        $estado_sat = $datos['status_code'];
        $mensaje_sat = $datos['status_message'];

        // Determinar estado interno según códigos SAT
        switch ($estado_sat) {
            case '5000': // Solicitud Aceptada
                $nuevo_status = 'PROCESSING';
                $nuevo_estatus = 'EN PROCESO';
                break;
            case '5002': // Terminada - Se han agotado las solicitudes de por vida
                $nuevo_status = 'COMPLETED';
                $nuevo_estatus = 'COMPLETADA';
                break;
            case '5003': // Máximo solicitudes
                $nuevo_status = 'ERROR';
                $nuevo_estatus = 'ERROR - LÍMITE EXCEDIDO';
                break;
            case '5004': // Sin información
                $nuevo_status = 'NO_DATA';
                $nuevo_estatus = 'SIN INFORMACIÓN';
                break;
            case '5005': // Ya existe una solicitud
                $nuevo_status = 'DUPLICATE';
                $nuevo_estatus = 'DUPLICADA';
                break;
            default:
                $nuevo_status = 'UNKNOWN';
                $nuevo_estatus = 'ESTADO DESCONOCIDO';
        }

        // Procesar paquetes si están disponibles
        $paquetes_json = null;
        if (!empty($datos['packages'])) {
            $paquetes_json = json_encode($datos['packages']);
            if ($nuevo_status === 'PROCESSING') {
                $nuevo_status = 'COMPLETED';
                $nuevo_estatus = 'COMPLETADA - LISTA PARA DESCARGA';
            }
        }
    } catch (Exception $e) {
        // Error de autenticación o comunicación con SAT
        if (
            strpos($e->getMessage(), 'bad decrypt') !== false ||
            strpos($e->getMessage(), 'wrong password') !== false
        ) {
            echo json_encode([
                'success' => false,
                'message' => 'Contraseña incorrecta del certificado',
                'needs_password' => true
            ]);
            exit;
        }

        // Otros errores - mantener estado actual pero registrar error
        $nuevo_status = $solicitud['status'];
        $nuevo_estatus = $solicitud['estatus_solicitud'];
        $mensaje_sat = 'Error verificando: ' . $e->getMessage();
        $estado_sat = '404'; // Error no controlado
        $paquetes_json = $solicitud['paquetes'];

        error_log("Error verificando solicitud SAT {$solicitud['request_id']}: " . $e->getMessage());
    }

    // Actualizar solicitud en base de datos con datos reales del SAT
    $stmt = $pdo->prepare("
        UPDATE sat_download_history 
        SET 
            status = ?,
            estatus_solicitud = ?,
            mensaje_verificacion = ?,
            codigo_estado_verificacion = ?,
            codigo_estado_solicitud = ?,
            paquetes = ?,
            ultima_actualizacion = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $nuevo_status,
        $nuevo_estatus,
        $mensaje_sat,
        $estado_sat,
        $estado_sat,
        $paquetes_json,
        $solicitud_id
    ]);

    // Log de actividad
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        'SAT_VERIFY_REAL',
        "Verificación REAL estado solicitud SAT {$solicitud['request_id']} - Estado: {$nuevo_estatus}",
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Estado verificado con el SAT',
        'data' => [
            'status' => $nuevo_status,
            'estatus_solicitud' => $nuevo_estatus,
            'mensaje_verificacion' => $mensaje_sat,
            'codigo_estado' => $estado_sat,
            'request_id' => $solicitud['request_id'],
            'rfc' => $solicitud['rfc'],
            'paquetes' => $paquetes_json ? json_decode($paquetes_json, true) : null,
            'ultima_actualizacion' => date('Y-m-d H:i:s'),
            'puede_descargar' => ($nuevo_status === 'COMPLETED' && !empty($paquetes_json))
        ]
    ]);
} catch (Exception $e) {
    error_log("Error en verificar-solicitud.php: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'needs_password' => strpos($e->getMessage(), 'contraseña') !== false
    ]);
}
