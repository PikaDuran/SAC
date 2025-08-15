<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
// Prueba directa de solicitud SAT tipo Recibidas
// Ejecutar este archivo desde navegador o CLI para ver resultado real

header('Content-Type: text/plain; charset=utf-8');

// Configuración directa
$certificate_id = null;
$rfc = 'BFM170822P38';
$tipo_documento = 'Recibidas';
$fecha_desde = '2025-01-01';
$fecha_hasta = '2025-01-31';

// Cargar config y helpers
require_once __DIR__ . '/../../../src/config/database.php';
require_once __DIR__ . '/../../../src/Services/SatDescargaMasivaService.php';

use App\Services\SatDescargaMasivaService;

try {
    // Conectar a base de datos
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Buscar el certificado activo para el RFC
    $stmt = $pdo->prepare("SELECT * FROM sat_fiel_certificates WHERE rfc = ? AND is_active = 1 AND valid_to > NOW() LIMIT 1");
    $stmt->execute([$rfc]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cert) {
        throw new Exception('Certificado no encontrado o inactivo para RFC ' . $rfc);
    }
    $certificate_id = $cert['id'];

    // Construir rutas absolutas
    $basePath = realpath(__DIR__ . '/../../..');
    $rutaCer = $basePath . '/' . ltrim(str_replace(['\\'], ['/'], $cert['certificate_path']), '/');
    $rutaKey = $basePath . '/' . ltrim(str_replace(['\\'], ['/'], $cert['key_path']), '/');
    if (!file_exists($rutaCer)) throw new Exception('No existe CER: ' . $rutaCer);
    if (!file_exists($rutaKey)) throw new Exception('No existe KEY: ' . $rutaKey);

    // Instanciar servicio SAT
    $satService = new SatDescargaMasivaService(
        $rutaCer,
        $rutaKey,
        $cert['password_plain']
    );

    // Parámetros para Recibidas
    $parametros = [
        'fecha_inicial' => $fecha_desde,
        'fecha_final' => $fecha_hasta,
        'tipo_solicitud' => 'CFDI',
        'estado_comprobante' => 'Vigente',
        'rfc_receptor' => $cert['rfc'],
        'rfc_solicitante' => $cert['rfc']
    ];

    $resultado = $satService->solicitarDescargaRecibidos($parametros);
    print_r($resultado);
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    if (method_exists($e, 'getTraceAsString')) {
        echo $e->getTraceAsString();
    }
}
