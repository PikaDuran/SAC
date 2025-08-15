<?php
// Endpoint minimalista para probar carga de Fiel y lógica SAT
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;

header('Content-Type: application/json');

// Parámetros de prueba (ajusta según tu entorno real)
$cer = __DIR__ . '/../../storage/fiel_certificates/BLM1706026AA_1754605893.cer';
$key = __DIR__ . '/../../storage/fiel_certificates/BLM1706026AA_1754605893.key';
$password = 'BOTLM2025'; // Cambia por la contraseña real

try {
    $fiel = Fiel::create(
        file_get_contents($cer),
        file_get_contents($key),
        $password
    );
    $isValid = $fiel->isValid();
    echo json_encode([
        'success' => true,
        'message' => 'Fiel cargada y creada correctamente',
        'is_valid' => $isValid,
        'rfc' => $fiel->getRfc(),
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'ERROR: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}
