<?php
// API para solicitar descarga masiva al SAT - Implementación completa según documentación SAT v1.5
// ¡NO DEJAR NINGÚN TEXTO FUERA DE PHP! Este archivo debe iniciar con <?php y no tener nada fuera de bloques PHP.
// Manejo global de errores y warnings para siempre devolver JSON
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
header('Content-Type: application/json');

// Establecer zona horaria global correcta para México
date_default_timezone_set('America/Mexico_City');
session_start();
error_log('EJECUTANDO API CORRECTA solicitar-descarga.php');
// Marca de log para depuración: confirmar que este archivo es el ejecutado
error_log('PRUEBA_CAMBIO_API_SOLICITAR_DESCARGA');

// Incluir autoload de Composer para todas las clases externas
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

    if (!$certificate_id || !$tipo_documento || !$fecha_desde || !$fecha_hasta) {
        throw new Exception('Todos los campos son requeridos');
    }

    // Validar que el certificado existe y está activo
    $stmt = $pdo->prepare("
        SELECT id, rfc, certificate_path, key_path, password_plain 
        FROM sat_fiel_certificates 
        WHERE id = ? AND is_active = 1 AND valid_to > NOW()
    ");
    $stmt->execute([$certificate_id]);
    $certificado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$certificado) {
        throw new Exception('Certificado no válido o expirado');
    }

    // Validar fechas
    if (strtotime($fecha_desde) > strtotime($fecha_hasta)) {
        throw new Exception('La fecha inicial no puede ser mayor que la fecha final');
    }

    // IMPLEMENTACIÓN REAL CON SAT - Usar servicio completo según documentación
    // Capturar cualquier warning/error PHP como excepción para evitar HTML
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        throw new Exception("$errstr en $errfile:$errline");
    });

    // Inicializar servicio SAT usando RFC (carga automáticamente desde DB)
    // Usar password_plain directamente de la base
    // Construir rutas absolutas igual que en test_sat_real.php
    // Limpiar rutas relativas y construir absolutas sin hardcodear RFC ni nombre
    // Usar ruta relativa desde la raíz del proyecto
    // Reparar: usar ruta absoluta solo si certificate_path es relativa, si ya es absoluta usarla directo
    // Limpiar solo '../' y './' del inicio, y normalizar barras, pero NO eliminar las carpetas
    $rutaCerRel = ltrim(str_replace(['\\'], ['/'], $certificado['certificate_path']), '/');
    $rutaKeyRel = ltrim(str_replace(['\\'], ['/'], $certificado['key_path']), '/');
    // Eliminar prefijos '../' y './' si existen
    $rutaCerRel = preg_replace('#^(\.\./|\./)+#', '', $rutaCerRel);
    $rutaKeyRel = preg_replace('#^(\.\./|\./)+#', '', $rutaKeyRel);
    // Construir ruta absoluta desde la raíz del proyecto
    $basePath = realpath(__DIR__ . '/../../../..');
    $rutaCer = $basePath . '/' . $rutaCerRel;
    $rutaKey = $basePath . '/' . $rutaKeyRel;
    error_log('[DEBUG] Ruta CER final: ' . $rutaCer);
    error_log('[DEBUG] Ruta KEY final: ' . $rutaKey);
    if (!$rutaCer || !file_exists($rutaCer)) {
        throw new Exception('Archivo de certificado (.cer) no encontrado: ' . $certificado['certificate_path']);
    }
    if (!$rutaKey || !file_exists($rutaKey)) {
        throw new Exception('Archivo de llave privada (.key) no encontrado: ' . $certificado['key_path']);
    }
    error_log('[DEBUG] Ruta CER final: ' . $rutaCer);
    error_log('[DEBUG] Ruta KEY final: ' . $rutaKey);
    if (!$rutaCer || !file_exists($rutaCer)) {
        throw new Exception('Archivo de certificado (.cer) no encontrado: ' . $certificado['certificate_path']);
    }
    if (!$rutaKey || !file_exists($rutaKey)) {
        throw new Exception('Archivo de llave privada (.key) no encontrado: ' . $certificado['key_path']);
    }
    $satService = new SatDescargaMasivaService(
        $rutaCer,
        $rutaKey,
        $certificado['password_plain']
    );


    // Preparar parámetros igual que en los scripts de prueba
    if ($tipo_documento === 'Emitidas') {
        $parametros = [
            'fecha_inicial' => $fecha_desde,
            'fecha_final' => $fecha_hasta,
            'rfc_emisor' => $certificado['rfc']
        ];
        $resultado = $satService->solicitarDescargaEmitidos($parametros);
    } elseif ($tipo_documento === 'Recibidas') {
        $parametros = [
            'fecha_inicial' => $fecha_desde,
            'fecha_final' => $fecha_hasta,
            'rfc_receptor' => $certificado['rfc']
        ];
        $resultado = $satService->solicitarDescargaRecibidos($parametros);
    } else {
        throw new Exception('Tipo de documento inválido');
    }

    if (!$resultado['success']) {
        throw new Exception($resultado['message']);
    }

    $request_id = $resultado['data']['request_id'];

    // Insertar solicitud en base de datos con datos reales del SAT
    $stmt = $pdo->prepare("
        INSERT INTO sat_download_history (
            certificate_id,
            request_type,
            date_from,
            date_to,
            rfc_emisor,
            request_id,
            status,
            estatus_solicitud,
            fecha_inicial,
            fecha_final,
            tipo_documento,
            fecha_solicitud,
            codigo_estado_solicitud,
            mensaje_verificacion,
            requested_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $certificate_id,
        'CFDI',
        $fecha_desde,
        $fecha_hasta,
        $certificado['rfc'],
        $request_id,
        'REQUESTED',
        $resultado['data']['mensaje'] ?? 'SOLICITADA',
        $fecha_desde,
        $fecha_hasta,
        $tipo_documento,
        $fecha_hasta, // Usar la fecha final de la solicitud SAT como fecha_solicitud
        $resultado['data']['codigo_estatus'] ?? '5000',
        $resultado['data']['mensaje'] ?? 'Solicitud enviada al SAT',
        $_SESSION['user_id']
    ]);

    // Log de actividad con información real
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        'SAT_REQUEST_REAL',
        "Solicitud REAL descarga masiva SAT para RFC {$certificado['rfc']} ({$tipo_documento}) del {$fecha_desde} al {$fecha_hasta} - ID: {$request_id}",
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Solicitud enviada exitosamente al SAT',
        'data' => [
            'request_id' => $request_id,
            'rfc' => $certificado['rfc'],
            'tipo' => $tipo_documento,
            'codigo_estatus' => $resultado['data']['codigo_estatus'],
            'mensaje_sat' => $resultado['data']['mensaje']
        ]
    ]);
} catch (Exception $e) {
    error_log("Error en solicitar-descarga.php: " . $e->getMessage());

    // Determinar si es error de autenticación para solicitar contraseña
    $needsPassword = false;
    if (
        strpos($e->getMessage(), 'Contraseña del certificado') !== false ||
        strpos($e->getMessage(), 'bad decrypt') !== false ||
        strpos($e->getMessage(), 'wrong password') !== false
    ) {
        $needsPassword = true;
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'needs_password' => $needsPassword
    ]);
}
