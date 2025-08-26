<?php
require_once 'vendor/autoload.php';
require_once 'src/config/database.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;

// Usar la solicitud más reciente
$requestId = 'fb1adbfb-6bf2-4ebf-8b8c-07e25c00f768';
$rfc = 'BFM170822P38';

try {
    $pdo = getDatabase();

    // Buscar certificado
    $stmt = $pdo->prepare("SELECT certificate_path, key_path, password_plain FROM sat_fiel_certificates WHERE rfc = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$rfc]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cert) {
        throw new Exception("Certificado no encontrado para RFC: $rfc");
    }

    // Construir rutas
    $certBaseDir = realpath(__DIR__ . '/storage/fiel_certificates/');
    $certFile = basename($cert['certificate_path']);
    $keyFile = basename($cert['key_path']);
    $certPath = $certBaseDir . DIRECTORY_SEPARATOR . $certFile;
    $keyPath = $certBaseDir . DIRECTORY_SEPARATOR . $keyFile;

    echo "🔐 Certificado: $certPath\n";
    echo "🗝️  Llave: $keyPath\n";
    echo "📋 Request ID: $requestId\n\n";

    // Crear objetos para verificación
    $fiel = Fiel::create(
        file_get_contents($certPath),
        file_get_contents($keyPath),
        $cert['password_plain']
    );

    echo "✅ FIEL Válido: " . ($fiel->isValid() ? 'SÍ' : 'NO') . "\n\n";

    $requestBuilder = new FielRequestBuilder($fiel);
    $webClient = new GuzzleWebClient();
    $service = new Service($requestBuilder, $webClient);

    echo "🔍 Verificando solicitud con el SAT...\n";
    $result = $service->verify($requestId);

    echo "\n📊 RESULTADO DE VERIFICACIÓN:\n";
    echo "=================================\n";

    $status = $result->getStatus();
    echo "Status: " . $status->getMessage() . "\n";

    $statusRequest = $result->getStatusRequest();
    if (method_exists($statusRequest, 'value')) {
        echo "Status Request: " . $statusRequest->value() . "\n";
    } else if (method_exists($statusRequest, '__toString')) {
        echo "Status Request: " . $statusRequest->__toString() . "\n";
    } else {
        echo "Status Request: [objeto StatusRequest]\n";
        echo "Status Request Debug: " . print_r($statusRequest, true) . "\n";
    }

    $packageIds = $result->getPackagesIds();
    echo "Paquetes: " . json_encode($packageIds) . "\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}
