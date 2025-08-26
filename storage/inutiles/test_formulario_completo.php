<?php
// Test del formulario completo - simular envío
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';

// Simular datos del formulario
$_POST = [
    'rfc_selected' => '3', // ID del certificado BFM170822P38
    'tipo_documento' => 'Emitidas',
    'fecha_desde' => '2025-08-25',
    'fecha_hasta' => '2025-08-25'
];

echo "=== TEST FORMULARIO DESCARGA-XML ===\n";
echo "Datos simulados:\n";
echo "RFC Selected: " . $_POST['rfc_selected'] . "\n";
echo "Tipo: " . $_POST['tipo_documento'] . "\n";
echo "Desde: " . $_POST['fecha_desde'] . "\n";
echo "Hasta: " . $_POST['fecha_hasta'] . "\n\n";

echo "Ejecutando API...\n";
ob_start();
include 'public/contabilidad/sat/api/solicitar-descarga.php';
$output = ob_get_clean();

echo "RESULTADO:\n";
echo $output;
