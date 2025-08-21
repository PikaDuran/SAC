<?php
require_once 'importador_inteligente_cfdi.php';

$pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');

echo "=== VERIFICACIÓN DE FECHA DE TIMBRADO ===\n";

// Verificar los últimos CFDIs 4.0 insertados
$stmt = $pdo->query('SELECT uuid, fecha, fecha_timbrado FROM cfdi WHERE version = "4.0" ORDER BY id DESC LIMIT 5');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "UUID: {$row['uuid']}\n";
    echo "Fecha CFDI: {$row['fecha']}\n";
    echo "Fecha Timbrado: " . ($row['fecha_timbrado'] ?: 'NULL') . "\n";
    echo "---\n";
}

echo "\n=== VERIFICACIÓN EN TABLA CFDI_TIMBRE_FISCAL ===\n";

// Verificar los timbres fiscales
$stmt = $pdo->query('
    SELECT c.uuid, t.fecha_timbrado, t.version as timbre_version 
    FROM cfdi c 
    JOIN cfdi_timbre_fiscal t ON c.id = t.cfdi_id 
    WHERE c.version = "4.0" 
    ORDER BY c.id DESC 
    LIMIT 5
');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "UUID: {$row['uuid']}\n";
    echo "Fecha Timbrado (tabla timbre): {$row['fecha_timbrado']}\n";
    echo "Versión Timbre: {$row['timbre_version']}\n";
    echo "---\n";
}

echo "\n=== ANÁLISIS DE UN XML PARA VERIFICAR EXTRACCIÓN ===\n";

// Analizar un archivo XML específico para ver qué fecha de timbrado tiene
$dir = "storage/sat_downloads/BFM170822P38/EMITIDAS/2025/1/";
$files = glob($dir . "*.xml");
if (!empty($files)) {
    $file = $files[0];
    $content = file_get_contents($file);

    echo "Archivo: " . basename($file) . "\n";

    // Buscar fecha de timbrado en el XML
    if (preg_match('/<tfd:TimbreFiscalDigital[^>]*FechaTimbrado\s*=\s*["\']([^"\']*)["\'][^>]*>/', $content, $matches)) {
        echo "Fecha Timbrado en XML: {$matches[1]}\n";
    } else {
        echo "No se encontró fecha de timbrado en XML\n";
    }

    // Verificar el patrón usado en el importador
    $importador = new ImportadorInteligenteCFDI();
    $reflection = new ReflectionClass($importador);
    $method = $reflection->getMethod('extraerTimbreFiscal');
    $method->setAccessible(true);

    $timbre = $method->invoke($importador, $content);
    echo "Fecha extraída por importador: " . ($timbre['fecha_timbrado'] ?? 'NULL') . "\n";
}
