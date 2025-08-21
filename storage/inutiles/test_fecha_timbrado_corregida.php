<?php
require_once 'importador_inteligente_cfdi.php';

echo "=== TEST DE FECHA DE TIMBRADO CORREGIDA ===\n\n";

// Limpiar registros de prueba anteriores
$pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');

echo "🧹 Limpiando registros de prueba anteriores...\n";
$stmt = $pdo->prepare("DELETE FROM cfdi_impuestos WHERE cfdi_id IN (SELECT id FROM cfdi WHERE version = '4.0')");
$stmt->execute();
$stmt = $pdo->prepare("DELETE FROM cfdi_conceptos WHERE cfdi_id IN (SELECT id FROM cfdi WHERE version = '4.0')");
$stmt->execute();
$stmt = $pdo->prepare("DELETE FROM cfdi_timbre_fiscal WHERE cfdi_id IN (SELECT id FROM cfdi WHERE version = '4.0')");
$stmt->execute();
$stmt = $pdo->prepare("DELETE FROM cfdi WHERE version = '4.0'");
$result = $stmt->execute();
echo "✅ Registros limpiados\n\n";

// Procesar solo 3 archivos CFDI 4.0 para verificar la corrección
$importador = new ImportadorInteligenteCFDI();

$dir = "storage/sat_downloads/BFM170822P38/EMITIDAS/2025/1/";
$files = array_slice(glob($dir . "*.xml"), 0, 3);

echo "📁 Procesando " . count($files) . " archivos CFDI 4.0...\n\n";

// Obtener acceso al PDO del importador para hacer transacciones directas
$reflection = new ReflectionClass($importador);
$pdoProperty = $reflection->getProperty('pdo');
$pdoProperty->setAccessible(true);
$importadorPdo = $pdoProperty->getValue($importador);

echo "✅ Acceso a PDO del importador obtenido\n";
echo "🔄 Transacción iniciada en PDO del importador\n\n";

$importadorPdo->beginTransaction();

try {
    foreach ($files as $i => $file) {
        echo "--- ARCHIVO " . ($i + 1) . " ---\n";
        echo "📁 " . basename($file) . "\n";

        // Usar reflection para acceder al método privado
        $method = $reflection->getMethod('procesarArchivo');
        $method->setAccessible(true);
        $method->invoke($importador, $file);

        echo "✅ Procesado\n\n";
    }

    echo "🔄 Haciendo commit en PDO del importador...\n";
    $importadorPdo->commit();
    echo "✅ Commit realizado en PDO del importador\n\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    $importadorPdo->rollback();
    exit(1);
}

echo "=== VERIFICACIÓN DE FECHA DE TIMBRADO ===\n\n";

$stmt = $pdo->query('
    SELECT 
        uuid, 
        fecha as fecha_cfdi, 
        fecha_timbrado,
        CASE 
            WHEN fecha_timbrado IS NOT NULL THEN "✅ CORRECTO" 
            ELSE "❌ NULL" 
        END as estado_timbrado
    FROM cfdi 
    WHERE version = "4.0" 
    ORDER BY id DESC 
    LIMIT 5
');

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "UUID: {$row['uuid']}\n";
    echo "Fecha CFDI: {$row['fecha_cfdi']}\n";
    echo "Fecha Timbrado: " . ($row['fecha_timbrado'] ?: 'NULL') . " " . $row['estado_timbrado'] . "\n";
    echo "---\n";
}

echo "\n🎯 Verificación cruzada con tabla timbre:\n\n";

$stmt = $pdo->query('
    SELECT 
        c.uuid,
        c.fecha_timbrado as fecha_principal,
        t.fecha_timbrado as fecha_timbre,
        CASE 
            WHEN c.fecha_timbrado = t.fecha_timbrado THEN "✅ COINCIDEN"
            ELSE "❌ NO COINCIDEN"
        END as estado
    FROM cfdi c
    JOIN cfdi_timbre_fiscal t ON c.id = t.cfdi_id
    WHERE c.version = "4.0"
    ORDER BY c.id DESC
    LIMIT 5
');

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "UUID: {$row['uuid']}\n";
    echo "Fecha Principal: " . ($row['fecha_principal'] ?: 'NULL') . "\n";
    echo "Fecha Timbre: " . ($row['fecha_timbre'] ?: 'NULL') . "\n";
    echo "Estado: {$row['estado']}\n";
    echo "---\n";
}

echo "\n🎉 ¡Test completado!\n";
