<?php

/**
 * Analizar estructura XML real de complementos de pago
 */

require_once 'src/config/database.php';

try {
    $pdo = getDatabase();

    // Obtener un CFDI de pago
    $cfdi = $pdo->query("
        SELECT archivo_xml, complemento_json
        FROM cfdi 
        WHERE tipo = 'P' 
        AND complemento_json IS NOT NULL 
        AND complemento_json != '[]' 
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$cfdi) {
        echo "❌ No se encontró CFDI de pago\n";
        exit;
    }

    $contenidoXML = file_get_contents($cfdi['archivo_xml']);

    echo "=== ANÁLISIS ESTRUCTURA XML ===\n\n";

    // 1. Verificar tipo de comprobante
    if (preg_match('/TipoDeComprobante\s*=\s*["\']([^"\']+)["\']/', $contenidoXML, $matches)) {
        echo "✅ TipoDeComprobante: '{$matches[1]}'\n";
    }

    // 2. Buscar todas las etiquetas que contengan "Pago"
    echo "\n=== ETIQUETAS CON 'PAGO' ===\n";
    if (preg_match_all('/<[^>]*[Pp]ago[^>]*>/', $contenidoXML, $matches)) {
        foreach ($matches[0] as $i => $match) {
            echo "Etiqueta $i: $match\n";
            if ($i >= 10) break; // Limitar salida
        }
    }

    // 3. Buscar namespace completo
    echo "\n=== NAMESPACES ===\n";
    if (preg_match_all('/xmlns:([^=]+)="([^"]*pago[^"]*)"/', $contenidoXML, $matches)) {
        for ($i = 0; $i < count($matches[1]); $i++) {
            echo "Namespace: {$matches[1][$i]} = {$matches[2][$i]}\n";
        }
    }

    // 4. Extraer una porción del XML donde aparezca "Pago"
    echo "\n=== FRAGMENTO CON PAGO ===\n";
    $pos = strpos($contenidoXML, 'Pago');
    if ($pos !== false) {
        $inicio = max(0, $pos - 200);
        $fragmento = substr($contenidoXML, $inicio, 600);
        echo $fragmento . "\n";
    }

    // 5. Mostrar el JSON guardado
    echo "\n=== JSON GUARDADO ===\n";
    echo $cfdi['complemento_json'] . "\n";

    // 6. Intentar diferentes patrones de búsqueda
    echo "\n=== PRUEBAS DE PATRONES ===\n";

    $patrones = [
        '/<pago10:Pagos[^>]*>/',
        '/<[^:]*:Pagos[^>]*>/',
        '/Pagos[^>]*Version="2\.0"/',
        '/<[^>]*Pagos[^>]*>/',
        '/complemento[^>]*>.*?Pago/si'
    ];

    foreach ($patrones as $i => $patron) {
        if (preg_match($patron, $contenidoXML, $matches)) {
            echo "✅ Patrón $i funcionó: " . htmlspecialchars($matches[0]) . "\n";
        } else {
            echo "❌ Patrón $i falló\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
