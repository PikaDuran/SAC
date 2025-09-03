<?php
// API para solicitar descarga masiva al SAT - Versión mejorada con múltiples RFCs
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "ERROR PHP: $errstr en $errfile:$errline"
    ]);
    exit;
});

header('Content-Type: application/json');
date_default_timezone_set('America/Mexico_City');
session_start();

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once '../../../../src/helpers/auth.php';
require_once '../../../../src/config/database.php';
require_once '../../../../src/Services/SatDescargaMasivaService.php';

use App\Services\SatDescargaMasivaService;

// Verificar autenticación
checkAuth(['admin', 'contabilidad']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
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

    // Validar datos del formulario
    $rfc_selected = $_POST['rfc_selected'] ?? null;
    $tipo_documento = $_POST['tipo_documento'] ?? null;
    $fecha_desde = $_POST['fecha_desde'] ?? null;
    $fecha_hasta = $_POST['fecha_hasta'] ?? null;
    $password_certificado = $_POST['password_certificado'] ?? null;

    // Validaciones básicas
    if (!$rfc_selected || !$tipo_documento || !$fecha_desde || !$fecha_hasta) {
        throw new Exception('Todos los campos son requeridos');
    }

    // Validar rango de fechas (máximo 31 días)
    $fechaDesde = new DateTime($fecha_desde);
    $fechaHasta = new DateTime($fecha_hasta);
    $diff = $fechaDesde->diff($fechaHasta);

    if ($diff->days > 31) {
        throw new Exception("El rango de fechas no puede ser mayor a 31 días. Rango actual: {$diff->days} días");
    }

    // Obtener certificados a procesar
    $certificados = [];
    if ($rfc_selected === 'TODOS') {
        $stmt = $pdo->prepare("SELECT * FROM sat_fiel_certificates WHERE is_active = 1 ORDER BY rfc");
        $stmt->execute();
        $certificados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM sat_fiel_certificates WHERE id = ? AND is_active = 1");
        $stmt->execute([$rfc_selected]);
        $certificado = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($certificado) {
            $certificados[] = $certificado;
        }
    }

    if (empty($certificados)) {
        throw new Exception('No se encontraron certificados válidos');
    }

    // Determinar tipos de documento a procesar
    $tipos = [];
    if ($tipo_documento === 'Ambos') {
        $tipos = ['Emitidas', 'Recibidas'];
    } else {
        $tipos = [$tipo_documento];
    }

    $resultados = [];
    $errores = [];

    // Procesar cada combinación RFC + Tipo
    foreach ($certificados as $certificado) {
        foreach ($tipos as $tipo) {
            try {
                // Verificar archivos del certificado
                $certBaseDir = realpath(__DIR__ . '/../../../../storage/fiel_certificates/');
                $rutaCer = $certBaseDir . DIRECTORY_SEPARATOR . basename($certificado['certificate_path']);
                $rutaKey = $certBaseDir . DIRECTORY_SEPARATOR . basename($certificado['key_path']);

                if (!file_exists($rutaCer)) {
                    throw new Exception("Certificado no encontrado: {$certificado['rfc']}");
                }
                if (!file_exists($rutaKey)) {
                    throw new Exception("Llave privada no encontrada: {$certificado['rfc']}");
                }

                // Crear servicio SAT
                $satService = new SatDescargaMasivaService(
                    $rutaCer,
                    $rutaKey,
                    $password_certificado ?: $certificado['password_plain']
                );

                // Preparar parámetros
                $parametros = [
                    'fecha_inicial' => $fecha_desde,
                    'fecha_final' => $fecha_hasta,
                    'rfc_emisor' => $certificado['rfc']
                ];

                // Ejecutar solicitud según tipo
                if ($tipo === 'Emitidas') {
                    $resultado = $satService->solicitarDescargaEmitidos($parametros);
                } else {
                    $resultado = $satService->solicitarDescargaRecibidos($parametros);
                }

                if ($resultado['success']) {
                    // Guardar en base de datos
                    $stmt = $pdo->prepare("
                        INSERT INTO sat_download_history (
                            certificate_id, request_type, date_from, date_to, rfc_emisor,
                            request_id, status, tipo_documento, fecha_solicitud, requested_by
                        ) VALUES (?, 'CFDI', ?, ?, ?, ?, 'REQUESTED', ?, NOW(), ?)
                    ");
                    $stmt->execute([
                        $certificado['id'],
                        $fecha_desde,
                        $fecha_hasta,
                        $certificado['rfc'],
                        $resultado['data']['request_id'],
                        $tipo,
                        $_SESSION['user_id']
                    ]);

                    $resultados[] = [
                        'rfc' => $certificado['rfc'],
                        'tipo' => $tipo,
                        'request_id' => $resultado['data']['request_id'],
                        'mensaje_sat' => $resultado['data']['mensaje_sat'] ?? 'Solicitud creada'
                    ];
                } else {
                    $errores[] = "RFC {$certificado['rfc']} ($tipo): " . $resultado['message'];
                }
            } catch (Exception $e) {
                $errores[] = "RFC {$certificado['rfc']} ($tipo): " . $e->getMessage();
            }
        }
    }

    // Responder según resultados
    if (!empty($resultados)) {
        $response = [
            'success' => true,
            'message' => count($resultados) . ' solicitud(es) creada(s) exitosamente',
            'data' => count($resultados) === 1 ? $resultados[0] : $resultados
        ];

        if (!empty($errores)) {
            $response['warnings'] = $errores;
        }

        echo json_encode($response);
    } else {
        throw new Exception('No se pudo crear ninguna solicitud: ' . implode('; ', $errores));
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
