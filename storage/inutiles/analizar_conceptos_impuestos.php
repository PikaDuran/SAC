<?php
echo "=== ANÁLISIS DE CONCEPTOS E IMPUESTOS EN XML ===\n";

// Analizar el primer XML para ver la estructura real
$archivoXML = 'C:\xampp\htdocs\SAC/storage/sat_downloads\BFM170822P38\EMITIDAS\2020\1\2020_01_10_702888E7-16B1-4A6E-AAB5-C3F95047C4F2.xml';

if (file_exists($archivoXML)) {
    $contenido = file_get_contents($archivoXML);

    echo "Archivo: " . basename($archivoXML) . "\n\n";

    // Buscar sección de conceptos
    echo "=== BUSCANDO CONCEPTOS ===\n";
    if (preg_match('/<cfdi:Conceptos[^>]*>.*?<\/cfdi:Conceptos>/s', $contenido, $matches)) {
        echo "✅ Sección Conceptos encontrada:\n";
        echo substr($matches[0], 0, 500) . "...\n\n";
    } else {
        echo "❌ No se encontró sección Conceptos\n\n";
    }

    // Buscar conceptos individuales
    echo "=== BUSCANDO CONCEPTO INDIVIDUAL ===\n";
    if (preg_match('/<cfdi:Concepto[^>]*([^>]*?)/', $contenido, $matches)) {
        echo "✅ Concepto encontrado:\n";
        echo $matches[0] . "\n\n";
    } else {
        echo "❌ No se encontró Concepto individual\n\n";
    }

    // Buscar impuestos
    echo "=== BUSCANDO IMPUESTOS ===\n";
    if (preg_match('/<cfdi:Impuestos[^>]*>.*?<\/cfdi:Impuestos>/s', $contenido, $matches)) {
        echo "✅ Sección Impuestos encontrada:\n";
        echo substr($matches[0], 0, 500) . "...\n\n";
    } else {
        echo "❌ No se encontró sección Impuestos\n\n";
    }

    // Buscar traslados
    echo "=== BUSCANDO TRASLADOS ===\n";
    if (preg_match('/<cfdi:Traslado[^>]*([^>]*?)/', $contenido, $matches)) {
        echo "✅ Traslado encontrado:\n";
        echo $matches[0] . "\n\n";
    } else {
        echo "❌ No se encontró Traslado\n\n";
    }
} else {
    echo "❌ Archivo XML no encontrado: $archivoXML\n";
}
