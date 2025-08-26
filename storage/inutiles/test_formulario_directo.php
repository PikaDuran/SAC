<?php
// Test directo de la API sin headers
require_once 'vendor/autoload.php';
require_once 'src/config/database.php';
require_once 'src/Services/SatDescargaMasivaService.php';

use App\Services\SatDescargaMasivaService;

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'rfc_selected' => '3',
    'tipo_documento' => 'Emitidas',
    'fecha_desde' => '2025-08-25',
    'fecha_hasta' => '2025-08-25'
];

// Simular sesión
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';

try {
    echo "=== SIMULACIÓN FORMULARIO WEB ===\n";
    echo "RFC: {$_POST['rfc_selected']}\n";
    echo "Tipo: {$_POST['tipo_documento']}\n";
    echo "Desde: {$_POST['fecha_desde']}\n";
    echo "Hasta: {$_POST['fecha_hasta']}\n\n";

    // Usar el servicio directamente como haría la API
    $satService = new SatDescargaMasivaService();

    $resultado = $satService->solicitarDescarga([
        'certificate_id' => $_POST['rfc_selected'],
        'tipo_documento' => $_POST['tipo_documento'],
        'fecha_desde' => $_POST['fecha_desde'],
        'fecha_hasta' => $_POST['fecha_hasta'],
        'rfc_emisor' => 'BFM170822P38'
    ]);

    echo "RESULTADO:\n";
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
