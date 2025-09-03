<?php
/**
 * ANÁLISIS XML RECIBIDO 2022 - CAMPOS COMPLETOS
 */

$xmlFile = 'C:\\xampp\\htdocs\\SAC\\storage\\sat_downloads\\BFM170822P38\\RECIBIDAS\\2022\\1\\2022_01_01_03012C1F-061C-498B-9829-0FF6FB08B8D1.xml';

echo "🔍 ANÁLISIS CFDI RECIBIDO 2022\n";
echo "📄 Archivo: " . basename($xmlFile) . "\n";
echo str_repeat("=", 50) . "\n";

$contenidoXML = file_get_contents($xmlFile);
$xml = simplexml_load_string($contenidoXML);

// Registrar namespaces
$xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/3');
$xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

echo "📊 INFORMACIÓN BÁSICA:\n";
echo "• Versión: " . (string)$xml['Version'] . "\n";
echo "• Emisor: " . (string)$xml->xpath('//cfdi:Emisor')[0]['Rfc'] . "\n";
echo "• Receptor: " . (string)$xml->xpath('//cfdi:Receptor')[0]['Rfc'] . "\n";
echo "• UUID: " . (string)$xml->xpath('//tfd:TimbreFiscalDigital')[0]['UUID'] . "\n";
echo "• Total: $" . (string)$xml['Total'] . "\n\n";

echo "CAMPO\n";
echo str_repeat("-", 50) . "\n";

// Campos del comprobante
$campos = [];
foreach ($xml->attributes() as $attr => $valor) {
    if ((string)$valor !== '') {
        $campos[] = strtolower($attr);
        echo strtolower($attr) . "\n";
    }
}

// Emisor
$emisor = $xml->xpath('//cfdi:Emisor')[0];
foreach ($emisor->attributes() as $attr => $valor) {
    $campos[] = 'emisor_' . strtolower($attr);
    echo 'emisor_' . strtolower($attr) . "\n";
}

// Receptor
$receptor = $xml->xpath('//cfdi:Receptor')[0];
foreach ($receptor->attributes() as $attr => $valor) {
    $campos[] = 'receptor_' . strtolower($attr);
    echo 'receptor_' . strtolower($attr) . "\n";
}

// Impuestos
$impuestos = $xml->xpath('//cfdi:Impuestos')[0];
if ($impuestos) {
    foreach ($impuestos->attributes() as $attr => $valor) {
        $campos[] = strtolower($attr);
        echo strtolower($attr) . "\n";
    }
}

// Timbre
$timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0];
foreach ($timbre->attributes() as $attr => $valor) {
    $campos[] = 'timbre_' . strtolower($attr);
    echo 'timbre_' . strtolower($attr) . "\n";
}

// Conceptos
$concepto = $xml->xpath('//cfdi:Concepto')[0];
foreach ($concepto->attributes() as $attr => $valor) {
    $campos[] = 'concepto_' . strtolower($attr);
    echo 'concepto_' . strtolower($attr) . "\n";
}

// Campos adicionales
$adicionales = [
    'archivo_xml', 'fecha_procesamiento', 'hash_xml', 'tamano_archivo',
    'direccion_flujo', 'estatus_sat', 'complemento_json', 'numero_conceptos'
];

foreach ($adicionales as $campo) {
    $campos[] = $campo;
    echo $campo . "\n";
}

echo str_repeat("-", 50) . "\n";
echo "TOTAL: " . count($campos) . " campos\n";

echo "\n🔄 DIFERENCIAS CON EMITIDO:\n";
echo "• RECIBIDO: " . count($campos) . " campos\n";
echo "• EMITIDO: ~37 campos básicos\n";
echo "• Estructura similar pero emisor/receptor invertidos\n";

echo "\n✅ ANÁLISIS COMPLETADO\n";
?>
