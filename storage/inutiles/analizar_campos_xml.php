<?php
/**
 * EXTRACTOR DE CAMPOS XML CFDI - ANÁLISIS COMPLETO
 * Extrae TODOS los campos posibles de un XML CFDI
 */

$xmlFile = 'C:\\xampp\\htdocs\\SAC\\storage\\sat_downloads\\BFM170822P38\\EMITIDAS\\2022\\1\\2022_01_05_79A26765-70A7-43EE-80E4-29902797C525.xml';

echo "🔍 ANÁLISIS COMPLETO DE CAMPOS XML CFDI\n";
echo "📄 Archivo: " . basename($xmlFile) . "\n";
echo str_repeat("=", 80) . "\n";

// Leer XML
$contenidoXML = file_get_contents($xmlFile);
if ($contenidoXML === false) {
    die("❌ No se pudo leer el archivo XML\n");
}

// Cargar XML
libxml_use_internal_errors(true);
$xml = simplexml_load_string($contenidoXML);
if ($xml === false) {
    die("❌ Error al parsear XML\n");
}

// Registrar namespaces
$namespaces = $xml->getNamespaces(true);
foreach ($namespaces as $prefix => $uri) {
    if (empty($prefix)) {
        $xml->registerXPathNamespace('cfdi', $uri);
    } else {
        $xml->registerXPathNamespace($prefix, $uri);
    }
}

$campos = [];

echo "📋 LISTADO COMPLETO DE CAMPOS EXTRAÍBLES:\n\n";

// 1. CAMPOS DEL COMPROBANTE PRINCIPAL
echo "🟦 COMPROBANTE PRINCIPAL:\n";
$comprobante_campos = [
    'Version' => 'Versión del CFDI',
    'Serie' => 'Serie del comprobante', 
    'Folio' => 'Folio del comprobante',
    'Fecha' => 'Fecha de expedición',
    'Sello' => 'Sello digital del CFDI',
    'FormaPago' => 'Forma de pago',
    'NoCertificado' => 'Número de certificado',
    'Certificado' => 'Certificado del emisor',
    'SubTotal' => 'Subtotal',
    'Descuento' => 'Descuento aplicado',
    'Moneda' => 'Moneda',
    'TipoCambio' => 'Tipo de cambio',
    'Total' => 'Total del comprobante',
    'TipoDeComprobante' => 'Tipo de comprobante',
    'Exportacion' => 'Clave de exportación',
    'MetodoPago' => 'Método de pago',
    'LugarExpedicion' => 'Lugar de expedición',
    'Confirmacion' => 'Confirmación'
];

foreach ($comprobante_campos as $attr => $desc) {
    $valor = isset($xml[$attr]) ? (string)$xml[$attr] : 'NULL';
    echo sprintf("%-30s | %s\n", $attr, $desc);
    $campos[] = $attr;
}

// 2. CAMPOS DEL EMISOR
echo "\n🟨 EMISOR:\n";
$emisor = $xml->xpath('//cfdi:Emisor')[0] ?? null;
$emisor_campos = [
    'Rfc' => 'RFC del emisor',
    'Nombre' => 'Nombre del emisor',
    'RegimenFiscal' => 'Régimen fiscal del emisor'
];

foreach ($emisor_campos as $attr => $desc) {
    echo sprintf("%-30s | %s\n", "emisor_" . strtolower($attr), $desc);
    $campos[] = "emisor_" . strtolower($attr);
}

// 3. CAMPOS DEL RECEPTOR
echo "\n🟩 RECEPTOR:\n";
$receptor = $xml->xpath('//cfdi:Receptor')[0] ?? null;
$receptor_campos = [
    'Rfc' => 'RFC del receptor',
    'Nombre' => 'Nombre del receptor',
    'DomicilioFiscalReceptor' => 'Domicilio fiscal receptor',
    'RegimenFiscalReceptor' => 'Régimen fiscal receptor',
    'UsoCFDI' => 'Uso del CFDI'
];

foreach ($receptor_campos as $attr => $desc) {
    echo sprintf("%-30s | %s\n", "receptor_" . strtolower($attr), $desc);
    $campos[] = "receptor_" . strtolower($attr);
}

// 4. CAMPOS DE IMPUESTOS
echo "\n🟪 IMPUESTOS:\n";
$impuestos = $xml->xpath('//cfdi:Impuestos')[0] ?? null;
$impuestos_campos = [
    'TotalImpuestosRetenidos' => 'Total impuestos retenidos',
    'TotalImpuestosTrasladados' => 'Total impuestos trasladados'
];

foreach ($impuestos_campos as $attr => $desc) {
    echo sprintf("%-30s | %s\n", strtolower($attr), $desc);
    $campos[] = strtolower($attr);
}

// 5. CAMPOS DEL TIMBRE FISCAL DIGITAL
echo "\n🟫 TIMBRE FISCAL DIGITAL:\n";
$timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0] ?? null;
$timbre_campos = [
    'Version' => 'Versión del timbre',
    'UUID' => 'UUID del timbre',
    'FechaTimbrado' => 'Fecha de timbrado',
    'RfcProvCertif' => 'RFC proveedor certificación',
    'Leyenda' => 'Leyenda',
    'SelloCFD' => 'Sello CFD',
    'NoCertificadoSAT' => 'Número certificado SAT',
    'SelloSAT' => 'Sello del SAT'
];

foreach ($timbre_campos as $attr => $desc) {
    echo sprintf("%-30s | %s\n", "timbre_" . strtolower($attr), $desc);
    $campos[] = "timbre_" . strtolower($attr);
}

// 6. CONCEPTOS (PRIMER CONCEPTO COMO MUESTRA)
echo "\n🟧 CONCEPTOS (PRIMER CONCEPTO):\n";
$conceptos = $xml->xpath('//cfdi:Concepto')[0] ?? null;
$concepto_campos = [
    'ClaveProdServ' => 'Clave producto/servicio',
    'NoIdentificacion' => 'Número identificación',
    'Cantidad' => 'Cantidad',
    'ClaveUnidad' => 'Clave unidad',
    'Unidad' => 'Unidad',
    'Descripcion' => 'Descripción',
    'ValorUnitario' => 'Valor unitario',
    'Importe' => 'Importe',
    'Descuento' => 'Descuento concepto',
    'ObjetoImp' => 'Objeto impuesto'
];

foreach ($concepto_campos as $attr => $desc) {
    echo sprintf("%-30s | %s\n", "concepto_" . strtolower($attr), $desc);
    $campos[] = "concepto_" . strtolower($attr);
}

// 7. CAMPOS ADICIONALES DE CONTROL
echo "\n🔘 CAMPOS DE CONTROL/ADICIONALES:\n";
$adicionales = [
    'archivo_xml' => 'Ruta del archivo XML',
    'fecha_procesamiento' => 'Fecha de procesamiento',
    'hash_xml' => 'Hash MD5 del XML',
    'tamaño_archivo' => 'Tamaño del archivo',
    'estatus_sat' => 'Estatus en el SAT',
    'fecha_consulta_sat' => 'Fecha consulta SAT',
    'complemento_json' => 'Complementos en JSON',
    'rfc_pac' => 'RFC del PAC',
    'fecha_certificacion_pac' => 'Fecha certificación PAC',
    'cadena_original' => 'Cadena original',
    'numero_conceptos' => 'Número de conceptos',
    'moneda_base' => 'Moneda base calculada',
    'total_pesos' => 'Total en pesos mexicanos'
];

foreach ($adicionales as $campo => $desc) {
    echo sprintf("%-30s | %s\n", $campo, $desc);
    $campos[] = $campo;
}

// 8. COMPLEMENTOS DETECTADOS
echo "\n🔶 COMPLEMENTOS DETECTADOS:\n";
$complementos = $xml->xpath('//cfdi:Complemento/*');
if (!empty($complementos)) {
    foreach ($complementos as $comp) {
        $nombre = $comp->getName();
        $namespace = $comp->getNamespace();
        echo sprintf("%-30s | Complemento: %s\n", "complemento_" . strtolower($nombre), $nombre);
        $campos[] = "complemento_" . strtolower($nombre);
        
        // Atributos del complemento
        foreach ($comp->attributes() as $attr => $valor) {
            echo sprintf("%-30s | %s - %s\n", "comp_" . strtolower($nombre) . "_" . strtolower($attr), $nombre, $attr);
            $campos[] = "comp_" . strtolower($nombre) . "_" . strtolower($attr);
        }
    }
} else {
    echo "No se detectaron complementos adicionales (solo TimbreFiscalDigital)\n";
}

// RESUMEN FINAL
echo "\n" . str_repeat("=", 80) . "\n";
echo "📊 RESUMEN DE ANÁLISIS:\n";
echo "Total de campos identificados: " . count($campos) . "\n";
echo "Archivo analizado: CFDI 3.3 de 2022\n";
echo "Emisor: " . ($emisor ? (string)$emisor['Nombre'] : 'N/A') . "\n";
echo "UUID: " . ($timbre ? (string)$timbre['UUID'] : 'N/A') . "\n";

echo "\n🔄 COMPARACIÓN CON TABLA ACTUAL:\n";
echo "Campos en tabla CFDI actual: 39\n";
echo "Campos identificables en XML: " . count($campos) . "\n";
echo "Diferencia: +" . (count($campos) - 39) . " campos adicionales\n";

echo "\n✅ ANÁLISIS COMPLETADO\n";
?>
