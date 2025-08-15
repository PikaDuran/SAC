<?php
// Nuevo endpoint limpio para verificar estado de solicitud SAT
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;

header('Content-Type: application/json');

// Parámetros de entrada
$requestId = $_POST['request_id'] ?? $_GET['request_id'] ?? null;
if (!$requestId) {
    echo json_encode(['success' => false, 'message' => 'Falta el parámetro request_id']);
    exit;
}

require_once __DIR__ . '/../../src/config/database.php';

try {
    $pdo = getDatabase();
    // Buscar el certificado asociado al request_id
    $stmt = $pdo->prepare("SELECT certificate_id FROM sat_download_history WHERE request_id = ? LIMIT 1");
    $stmt->execute([$requestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el certificado para el request_id']);
        exit;
    }
    $certificateId = $row['certificate_id'];

    // Buscar los datos del certificado
    $stmt = $pdo->prepare("SELECT rfc, certificate_path, key_path, password_plain FROM sat_fiel_certificates WHERE id = ? LIMIT 1");
    $stmt->execute([$certificateId]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cert) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el certificado FIEL']);
        exit;
    }

    // Construir rutas absolutas
    $certBaseDir = realpath(__DIR__ . '/../../storage/fiel_certificates/');
    $certFile = basename($cert['certificate_path']);
    $keyFile = basename($cert['key_path']);
    $cer = $certBaseDir . DIRECTORY_SEPARATOR . $certFile;
    $key = $certBaseDir . DIRECTORY_SEPARATOR . $keyFile;
    $password = $cert['password_plain'];

    $fiel = Fiel::create(
        file_get_contents($cer),
        file_get_contents($key),
        $password
    );
    if (!$fiel->isValid()) {
        throw new Exception('El certificado FIEL no es válido o está vencido');
    }
    $requestBuilder = new FielRequestBuilder($fiel);
    $webClient = new GuzzleWebClient();
    $service = new Service($requestBuilder, $webClient);
    $result = $service->verify($requestId);

    // Helper para extraer valor real de objetos tipo ValueObject
    function extractValue($obj)
    {
        if (is_object($obj)) {
            if (method_exists($obj, 'value')) {
                return $obj->value();
            } elseif (method_exists($obj, '__toString')) {
                return (string)$obj;
            } elseif (method_exists($obj, 'getValue')) {
                return $obj->getValue();
            } else {
                return json_encode($obj); // fallback
            }
        }
        return $obj;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Estado verificado con el SAT',
        'data' => [
            'status' => extractValue($result->getStatus()),
            'status_request' => extractValue($result->getStatusRequest()),
            'code_request' => extractValue($result->getCodeRequest()),
            'number_cfdis' => $result->getNumberCfdis(),
            'packages_ids' => $result->getPackagesIds(),
            'rfc' => $fiel->getRfc(),
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'ERROR: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}
