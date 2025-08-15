<?php
// TEST: Verificación real de solicitud SAT para el id 1 (no simulación)
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/SatDescargaMasivaService.php';

$id = isset($argv[1]) ? (int)$argv[1] : 1;

try {
    $pdo = getDatabase();
    $stmt = $pdo->prepare("SELECT * FROM sat_download_history WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('No se encontró el registro para el id 1');
    }
    $certificateId = $row['certificate_id'];
    $requestId = $row['request_id'];

    $stmt = $pdo->prepare("SELECT rfc, certificate_path, key_path, password_plain FROM sat_fiel_certificates WHERE id = ? LIMIT 1");
    $stmt->execute([$certificateId]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cert) {
        throw new Exception('No se encontró el certificado FIEL');
    }

    $certBaseDir = realpath(__DIR__ . '/storage/fiel_certificates/');
    $certFile = basename($cert['certificate_path']);
    $keyFile = basename($cert['key_path']);
    $certPath = $certBaseDir . DIRECTORY_SEPARATOR . $certFile;
    $keyPath = $certBaseDir . DIRECTORY_SEPARATOR . $keyFile;

    $fiel = \PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel::create(
        file_get_contents($certPath),
        file_get_contents($keyPath),
        $cert['password_plain']
    );
    if (!$fiel->isValid()) {
        throw new Exception('El certificado FIEL no es válido o está vencido');
    }
    $requestBuilder = new \PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder($fiel);
    $webClient = new \PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient();
    $service = new \PhpCfdi\SatWsDescargaMasiva\Service($requestBuilder, $webClient);
    $result = $service->verify($requestId);

    // Mostrar respuesta cruda y desglosada
    echo "Respuesta cruda de verify():\n";
    var_dump($result);
    echo "\n\n";
    echo "Status: ", var_export($result->getStatus(), true), "\n";
    echo "StatusRequest: ", var_export($result->getStatusRequest(), true), "\n";
    echo "PackagesIds: ", var_export($result->getPackagesIds(), true), "\n";
    echo "\n";
    echo "\nTodos los métodos disponibles:\n";
    foreach (get_class_methods($result) as $method) {
        echo "- $method\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
