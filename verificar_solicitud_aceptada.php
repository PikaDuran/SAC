<?php
// Verificación manual de la solicitud aceptada ID 8
require_once 'vendor/autoload.php';
require_once 'src/config/database.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;

// Request ID de la solicitud aceptada (ID 8)
$requestId = 'fb1adbfb-6bf2-4ebf-8b8c-07e25c00f768';
$rfc = 'BFM170822P38';

echo "=== VERIFICACIÓN SOLICITUD ACEPTADA ===\n";
echo "Request ID: $requestId\n";
echo "RFC: $rfc\n\n";

try {
    $pdo = getDatabase();

    // Cargar certificado
    $stmt = $pdo->prepare("SELECT certificate_path, key_path, password_plain FROM sat_fiel_certificates WHERE rfc = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$rfc]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);

    $certPath = __DIR__ . '/storage/fiel_certificates/' . basename($cert['certificate_path']);
    $keyPath = __DIR__ . '/storage/fiel_certificates/' . basename($cert['key_path']);

    $fiel = Fiel::create(
        file_get_contents($certPath),
        file_get_contents($keyPath),
        $cert['password_plain']
    );

    echo "✅ FIEL válida\n\n";

    // Crear servicio y verificar
    $requestBuilder = new FielRequestBuilder($fiel);
    $webClient = new GuzzleWebClient();
    $service = new Service($requestBuilder, $webClient);

    $result = $service->verify($requestId);

    echo "📊 RESULTADO:\n";
    echo "Status: " . $result->getStatus()->getMessage() . "\n";

    // Obtener estado usando var_dump para debugear
    $statusRequest = $result->getStatusRequest();
    echo "Status Request Object: ";
    var_dump($statusRequest);

    echo "\nPaquetes: " . count($result->getPackagesIds()) . "\n";
    if ($result->getPackagesIds()) {
        echo "🎉 ¡PAQUETES DISPONIBLES!\n";
        foreach ($result->getPackagesIds() as $package) {
            echo "  📦 " . $package . "\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
