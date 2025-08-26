<?php
/**
 * ANÁLISIS COMPARATIVO XML CFDI 2024 - EMITIDOS vs RECIBIDOS
 * Extrae y compara campos de XMLs de 2024
 */

$xmlRecibido = 'C:\\xampp\\htdocs\\SAC\\storage\\sat_downloads\\BFM170822P38\\RECIBIDAS\\2024\\1\\2024_01_02_13716747-1391-48B4-BC32-64FFEA0E7C25.xml';
$xmlEmitido = 'C:\\xampp\\htdocs\\SAC\\storage\\sat_downloads\\BFM170822P38\\EMITIDAS\\2024\\1\\2024_01_03_094FA3BB-FE1B-4990-8507-04A864BD4697.xml';

echo "🔍 ANÁLISIS COMPARATIVO XML CFDI 2024\n";
echo str_repeat("=", 80) . "\n\n";

function analizarXML($rutaArchivo, $tipo) {
    echo "📄 ANALIZANDO $tipo: " . basename($rutaArchivo) . "\n";
    echo str_repeat("-", 60) . "\n";
    
    // Leer XML
    $contenidoXML = file_get_contents($rutaArchivo);
    if ($contenidoXML === false) {
        echo "❌ No se pudo leer el archivo XML\n";
        return [];
    }
    
    // Mostrar primeras líneas para ver estructura
    echo "🔍 PRIMERAS LÍNEAS DEL XML:\n";
    $lineas = explode("\n", $contenidoXML);
    for ($i = 0; $i < min(3, count($lineas)); $i++) {
        echo "  " . trim($lineas[$i]) . "\n";
    }
    echo "\n";
    
    // Cargar XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($contenidoXML);
    if ($xml === false) {
        $errores = libxml_get_errors();
        echo "❌ Error al parsear XML: " . implode(", ", array_map(function($e) { return $e->message; }, $errores)) . "\n";
        return [];
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
    
    // Detectar versión
    $version = (string)$xml['Version'];
    echo "📋 VERSIÓN CFDI: $version\n";
    
    // Namespaces detectados
    echo "📋 NAMESPACES DETECTADOS:\n";
    foreach ($namespaces as $prefix => $uri) {
        $prefixName = empty($prefix) ? '(default)' : $prefix;
        echo "  $prefixName: $uri\n";
    }
    echo "\n";
    
    $campos = [];
    
    // CAMPOS DEL COMPROBANTE
    echo "🟦 CAMPOS DEL COMPROBANTE:\n";
    $atributos = ['Version', 'Serie', 'Folio', 'Fecha', 'Sello', 'FormaPago', 'NoCertificado', 
                 'Certificado', 'SubTotal', 'Descuento', 'Moneda', 'TipoCambio', 'Total', 
                 'TipoDeComprobante', 'Exportacion', 'MetodoPago', 'LugarExpedicion', 'Confirmacion'];
    
    foreach ($atributos as $attr) {
        $valor = isset($xml[$attr]) ? (string)$xml[$attr] : 'NULL';
        $presente = $valor !== 'NULL' ? '✅' : '❌';
        echo sprintf("  %-25s | %s | %s\n", $attr, $presente, substr($valor, 0, 30) . (strlen($valor) > 30 ? '...' : ''));
        if ($valor !== 'NULL') $campos[] = strtolower($attr);
    }
    
    // EMISOR
    echo "\n🟨 CAMPOS DEL EMISOR:\n";
    $emisor = $xml->xpath('//cfdi:Emisor')[0] ?? null;
    if ($emisor) {
        $emisor_attrs = ['Rfc', 'Nombre', 'RegimenFiscal'];
        foreach ($emisor_attrs as $attr) {
            $valor = isset($emisor[$attr]) ? (string)$emisor[$attr] : 'NULL';
            $presente = $valor !== 'NULL' ? '✅' : '❌';
            echo sprintf("  %-25s | %s | %s\n", "Emisor_$attr", $presente, substr($valor, 0, 30) . (strlen($valor) > 30 ? '...' : ''));
            if ($valor !== 'NULL') $campos[] = "emisor_" . strtolower($attr);
        }
    }
    
    // RECEPTOR
    echo "\n🟩 CAMPOS DEL RECEPTOR:\n";
    $receptor = $xml->xpath('//cfdi:Receptor')[0] ?? null;
    if ($receptor) {
        $receptor_attrs = ['Rfc', 'Nombre', 'DomicilioFiscalReceptor', 'RegimenFiscalReceptor', 'UsoCFDI'];
        foreach ($receptor_attrs as $attr) {
            $valor = isset($receptor[$attr]) ? (string)$receptor[$attr] : 'NULL';
            $presente = $valor !== 'NULL' ? '✅' : '❌';
            echo sprintf("  %-25s | %s | %s\n", "Receptor_$attr", $presente, substr($valor, 0, 30) . (strlen($valor) > 30 ? '...' : ''));
            if ($valor !== 'NULL') $campos[] = "receptor_" . strtolower($attr);
        }
    }
    
    // IMPUESTOS
    echo "\n🟪 CAMPOS DE IMPUESTOS:\n";
    $impuestos = $xml->xpath('//cfdi:Impuestos')[0] ?? null;
    if ($impuestos) {
        $imp_attrs = ['TotalImpuestosRetenidos', 'TotalImpuestosTrasladados'];
        foreach ($imp_attrs as $attr) {
            $valor = isset($impuestos[$attr]) ? (string)$impuestos[$attr] : 'NULL';
            $presente = $valor !== 'NULL' ? '✅' : '❌';
            echo sprintf("  %-25s | %s | %s\n", $attr, $presente, $valor);
            if ($valor !== 'NULL') $campos[] = strtolower($attr);
        }
    }
    
    // TIMBRE FISCAL DIGITAL
    echo "\n🟫 TIMBRE FISCAL DIGITAL:\n";
    $timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0] ?? null;
    if ($timbre) {
        $timbre_attrs = ['Version', 'UUID', 'FechaTimbrado', 'RfcProvCertif', 'SelloCFD', 'NoCertificadoSAT', 'SelloSAT'];
        foreach ($timbre_attrs as $attr) {
            $valor = isset($timbre[$attr]) ? (string)$timbre[$attr] : 'NULL';
            $presente = $valor !== 'NULL' ? '✅' : '❌';
            echo sprintf("  %-25s | %s | %s\n", "Timbre_$attr", $presente, substr($valor, 0, 30) . (strlen($valor) > 30 ? '...' : ''));
            if ($valor !== 'NULL') $campos[] = "timbre_" . strtolower($attr);
        }
    }
    
    // COMPLEMENTOS
    echo "\n🔶 COMPLEMENTOS DETECTADOS:\n";
    $complementos = $xml->xpath('//cfdi:Complemento/*');
    if (!empty($complementos)) {
        foreach ($complementos as $comp) {
            $nombre = $comp->getName();
            echo "  📦 $nombre\n";
            $campos[] = "complemento_" . strtolower($nombre);
            
            // Mostrar algunos atributos del complemento
            $count = 0;
            foreach ($comp->attributes() as $attr => $valor) {
                if ($count < 3) { // Solo mostrar primeros 3 atributos
                    echo sprintf("    %-20s | %s\n", $attr, substr((string)$valor, 0, 40));
                    $campos[] = "comp_" . strtolower($nombre) . "_" . strtolower($attr);
                }
                $count++;
            }
            if ($count > 3) {
                echo "    ... y " . ($count - 3) . " atributos más\n";
            }
        }
    } else {
        echo "  ℹ️  Solo TimbreFiscalDigital encontrado\n";
    }
    
    // CONCEPTOS
    echo "\n🟧 CONCEPTOS:\n";
    $conceptos = $xml->xpath('//cfdi:Concepto');
    echo "  📊 Total conceptos: " . count($conceptos) . "\n";
    if (!empty($conceptos)) {
        $primer_concepto = $conceptos[0];
        echo "  🔍 Primer concepto:\n";
        $concepto_attrs = ['ClaveProdServ', 'Cantidad', 'ClaveUnidad', 'Descripcion', 'ValorUnitario', 'Importe', 'ObjetoImp'];
        foreach ($concepto_attrs as $attr) {
            $valor = isset($primer_concepto[$attr]) ? (string)$primer_concepto[$attr] : 'NULL';
            $presente = $valor !== 'NULL' ? '✅' : '❌';
            echo sprintf("    %-20s | %s | %s\n", $attr, $presente, substr($valor, 0, 30) . (strlen($valor) > 30 ? '...' : ''));
            if ($valor !== 'NULL') $campos[] = "concepto_" . strtolower($attr);
        }
    }
    
    echo "\n📊 RESUMEN:\n";
    echo "  Total campos detectados: " . count($campos) . "\n";
    echo "  Versión CFDI: $version\n";
    echo "  Tipo: $tipo\n\n";
    
    return $campos;
}

// Analizar ambos archivos
echo "🔄 INICIANDO ANÁLISIS COMPARATIVO...\n\n";

$camposRecibido = analizarXML($xmlRecibido, "RECIBIDO");
echo str_repeat("=", 80) . "\n\n";
$camposEmitido = analizarXML($xmlEmitido, "EMITIDO");

// COMPARACIÓN FINAL
echo str_repeat("=", 80) . "\n";
echo "📊 COMPARACIÓN FINAL 2024:\n";
echo str_repeat("=", 80) . "\n\n";

echo "📈 ESTADÍSTICAS:\n";
echo "  Campos en RECIBIDO: " . count($camposRecibido) . "\n";
echo "  Campos en EMITIDO:  " . count($camposEmitido) . "\n";

// Campos únicos en cada uno
$soloRecibido = array_diff($camposRecibido, $camposEmitido);
$soloEmitido = array_diff($camposEmitido, $camposRecibido);
$comunes = array_intersect($camposRecibido, $camposEmitido);

echo "  Campos comunes:     " . count($comunes) . "\n";
echo "  Solo en RECIBIDO:   " . count($soloRecibido) . "\n";
echo "  Solo en EMITIDO:    " . count($soloEmitido) . "\n\n";

if (!empty($soloRecibido)) {
    echo "🟥 CAMPOS ÚNICOS EN RECIBIDO:\n";
    foreach ($soloRecibido as $campo) {
        echo "  • $campo\n";
    }
    echo "\n";
}

if (!empty($soloEmitido)) {
    echo "🟦 CAMPOS ÚNICOS EN EMITIDO:\n";
    foreach ($soloEmitido as $campo) {
        echo "  • $campo\n";
    }
    echo "\n";
}

echo "🎯 RECOMENDACIONES:\n";
echo "  • Usar estructura común para ambos tipos\n";
echo "  • Considerar campos específicos por tipo\n";
echo "  • Verificar complementos específicos de 2024\n";

echo "\n✅ ANÁLISIS COMPLETADO\n";
?>
