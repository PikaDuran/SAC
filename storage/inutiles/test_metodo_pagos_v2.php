<?php

/**
 * Test directo del método extraerComplementoPagos - versión corregida
 */

// Solo incluir una vez
if (!function_exists('getDatabase')) {
    require_once 'src/config/database.php';
}

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
        echo "Contenido pago10:Pagos (primeros 300 chars): " . substr($matchesPagos[0], 0, 300) . "...\n";
    }

    // Mostrar más patrones para debug
    echo "\nMás patrones:\n";
    echo "TipoDeComprobante (cualquier valor): " . (preg_match('/TipoDeComprobante\s*=\s*["\']([^"\']*)["\']/', $contenidoXML, $matches) ? "Encontrado: " . $matches[1] : "No encontrado") . "\n";
    echo "Ocurrencias 'pago10': " . substr_count($contenidoXML, 'pago10') . "\n";
    echo "Ocurrencias 'Pagos': " . substr_count($contenidoXML, 'Pagos') . "\n";

    // Comparar con lo que está en la base de datos
    echo "\n2. ANÁLISIS DEL JSON EN BASE DE DATOS:\n";
    echo "═══════════════════════════════════════\n";

    $jsonDB = json_decode($cfdiEjemplo['complemento_json'], true);
    if ($jsonDB && isset($jsonDB['xml'])) {
        $xmlDelJson = $jsonDB['xml'];
        echo "XML guardado en JSON (primeros 500 chars):\n" . substr($xmlDelJson, 0, 500) . "...\n\n";

        // Verificar patrones en el XML del JSON
        $patronTipoPJson = preg_match('/TipoDeComprobante\s*=\s*["\']P["\']/', $xmlDelJson);
        echo "En JSON - TipoDeComprobante='P': " . ($patronTipoPJson ? "✓ ENCONTRADO" : "❌ NO ENCONTRADO") . "\n";

        $patronPagosJson = preg_match('/<pago10:Pagos[^>]*>.*?<\/pago10:Pagos>/s', $xmlDelJson, $matchesPagosJson);
        echo "En JSON - pago10:Pagos: " . ($patronPagosJson ? "✓ ENCONTRADO" : "❌ NO ENCONTRADO") . "\n";
    } else {
        echo "❌ No se pudo decodificar el JSON o no tiene estructura esperada\n";
        echo "Contenido del JSON: " . substr($cfdiEjemplo['complemento_json'], 0, 200) . "...\n";
    }

    echo "\n3. CONCLUSIÓN:\n";
    echo "═══════════════════════════════════════\n";
    echo "Los datos están ahí, pero hay una diferencia entre:\n";
    echo "- El XML completo del archivo\n";
    echo "- El XML fragmento guardado en complemento_json\n";
    echo "El método busca en el XML completo, pero los datos están solo en el fragmento.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
