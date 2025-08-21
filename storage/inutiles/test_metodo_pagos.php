<?php

/**
 * Test directo del método extraerComplementoPagos
 */

require_once 'src/config/database.php';
require_once 'importador_inteligente_cfdi.php';

try {
    $pdo = getDatabase();
    echo "=== TEST DIRECTO DEL MÉTODO extraerComplementoPagos ===\n\n";

    // Obtener un CFDI de ejemplo que sabemos que tiene datos
    $cfdiEjemplo = $pdo->query("
        SELECT id, uuid, archivo_xml, complemento_json
        FROM cfdi 
        WHERE tipo = 'P' 
        AND complemento_json IS NOT NULL 
        AND complemento_json != '[]' 
        AND complemento_json != ''
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$cfdiEjemplo) {
        echo "❌ No se encontró CFDI de ejemplo\n";
        exit;
    }

    echo "CFDI de prueba:\n";
    echo "ID: {$cfdiEjemplo['id']}\n";
    echo "UUID: {$cfdiEjemplo['uuid']}\n";
    echo "Archivo: {$cfdiEjemplo['archivo_xml']}\n\n";

    // Verificar archivo
    if (!file_exists($cfdiEjemplo['archivo_xml'])) {
        echo "❌ Archivo no existe: {$cfdiEjemplo['archivo_xml']}\n";
        exit;
    }

    // Leer contenido del XML
    $contenidoXML = file_get_contents($cfdiEjemplo['archivo_xml']);
    echo "✓ Archivo leído: " . strlen($contenidoXML) . " bytes\n\n";

    // Verificar patrones manualmente
    echo "1. VERIFICACIÓN MANUAL DE PATRONES:\n";
    echo "═══════════════════════════════════════\n";

    $patronTipoP = preg_match('/TipoDeComprobante\s*=\s*["\']P["\']/', $contenidoXML);
    echo "TipoDeComprobante='P': " . ($patronTipoP ? "✓ ENCONTRADO" : "❌ NO ENCONTRADO") . "\n";

    $patronPagos = preg_match('/<pago10:Pagos[^>]*>.*?<\/pago10:Pagos>/s', $contenidoXML, $matchesPagos);
    echo "pago10:Pagos: " . ($patronPagos ? "✓ ENCONTRADO" : "❌ NO ENCONTRADO") . "\n";

    if ($patronPagos) {
        echo "Contenido pago10:Pagos (primeros 200 chars): " . substr($matchesPagos[0], 0, 200) . "...\n";
    }

    // Usar el método real del importador
    echo "\n2. USANDO EL MÉTODO REAL:\n";
    echo "═══════════════════════════════════════\n";

    $importador = new ImportadorInteligenteCFDI();

    // Usar reflexión para acceder al método privado
    $reflection = new ReflectionClass($importador);
    $metodo = $reflection->getMethod('extraerComplementoPagos');
    $metodo->setAccessible(true);

    $resultado = $metodo->invoke($importador, $contenidoXML);

    if ($resultado) {
        echo "✓ MÉTODO FUNCIONA! Extrajo " . count($resultado) . " pagos\n";
        echo "Resultado: " . json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ MÉTODO NO FUNCIONA - Resultado: " . var_export($resultado, true) . "\n";
    }

    // Comparar con lo que está en la base de datos
    echo "\n3. COMPARACIÓN CON BASE DE DATOS:\n";
    echo "═══════════════════════════════════════\n";

    $jsonDB = json_decode($cfdiEjemplo['complemento_json'], true);
    echo "JSON en DB: " . json_encode($jsonDB, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    if ($jsonDB && isset($jsonDB['xml'])) {
        echo "\n4. ANALIZAR EL XML DEL JSON:\n";
        echo "═══════════════════════════════════════\n";

        $xmlDelJson = $jsonDB['xml'];
        echo "XML guardado (primeros 300 chars): " . substr($xmlDelJson, 0, 300) . "...\n";

        // Verificar si este XML es diferente
        $testResult = $metodo->invoke($importador, "<root>" . $xmlDelJson . "</root>");
        echo "Test con XML del JSON: " . ($testResult ? "✓ FUNCIONA" : "❌ NO FUNCIONA") . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
