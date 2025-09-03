<?php
/**
 * MAPEO DEFINITIVO Y PERMANENTE DE TODOS LOS CAMPOS CFDI
 * Análisis completo: CFDI 3.3/4.0, EMITIDAS/RECIBIDAS, COMPLEMENTOS
 */

echo "🔍 MAPEO DEFINITIVO DE CAMPOS CFDI - TODAS LAS VARIANTES\n";
echo str_repeat("=", 100) . "\n\n";

// Archivos a analizar
$archivos = [
    'CFDI_33_2022_EMITIDO' => 'C:\\xampp\\htdocs\\SAC\\storage\\sat_downloads\\BFM170822P38\\EMITIDAS\\2022\\1\\2022_01_05_79A26765-70A7-43EE-80E4-29902797C525.xml',
    'CFDI_33_2022_RECIBIDO' => 'C:\\xampp\\htdocs\\SAC\\storage\\sat_downloads\\BFM170822P38\\RECIBIDAS\\2022\\1\\2022_01_01_03012C1F-061C-498B-9829-0FF6FB08B8D1.xml',
    'CFDI_40_2024_EMITIDO' => 'C:\\xampp\\htdocs\\SAC\\storage\\sat_downloads\\BFM170822P38\\EMITIDAS\\2024\\1\\2024_01_03_094FA3BB-FE1B-4990-8507-04A864BD4697.xml',
    'CFDI_40_2024_RECIBIDO' => 'C:\\xampp\\htdocs\\SAC\\storage\\sat_downloads\\BFM170822P38\\RECIBIDAS\\2024\\1\\2024_01_02_13716747-1391-48B4-BC32-64FFEA0E7C25.xml'
];

// Función para extraer TODOS los campos de un XML
function extraerTodosCampos($rutaArchivo, $tipo) {
    if (!file_exists($rutaArchivo)) {
        return ['ERROR' => 'Archivo no encontrado'];
    }
    
    $contenidoXML = file_get_contents($rutaArchivo);
    if ($contenidoXML === false) {
        return ['ERROR' => 'No se pudo leer archivo'];
    }
    
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($contenidoXML);
    if ($xml === false) {
        return ['ERROR' => 'Error al parsear XML'];
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
    
    $resultado = [
        'VERSION' => (string)$xml['Version'],
        'NAMESPACES' => $namespaces,
        'CAMPOS' => []
    ];
    
    // COMPROBANTE
    $atributos_comprobante = ['Version', 'Serie', 'Folio', 'Fecha', 'Sello', 'FormaPago', 'NoCertificado', 
                             'Certificado', 'SubTotal', 'Descuento', 'Moneda', 'TipoCambio', 'Total', 
                             'TipoDeComprobante', 'Exportacion', 'MetodoPago', 'LugarExpedicion', 'Confirmacion'];
    
    foreach ($atributos_comprobante as $attr) {
        $valor = isset($xml[$attr]) ? (string)$xml[$attr] : null;
        if ($valor !== null) {
            $resultado['CAMPOS']['COMPROBANTE_' . $attr] = $valor;
        }
    }
    
    // EMISOR
    $emisor = $xml->xpath('//cfdi:Emisor')[0] ?? null;
    if ($emisor) {
        foreach ($emisor->attributes() as $attr => $valor) {
            $resultado['CAMPOS']['EMISOR_' . $attr] = (string)$valor;
        }
    }
    
    // RECEPTOR
    $receptor = $xml->xpath('//cfdi:Receptor')[0] ?? null;
    if ($receptor) {
        foreach ($receptor->attributes() as $attr => $valor) {
            $resultado['CAMPOS']['RECEPTOR_' . $attr] = (string)$valor;
        }
    }
    
    // IMPUESTOS
    $impuestos = $xml->xpath('//cfdi:Impuestos')[0] ?? null;
    if ($impuestos) {
        foreach ($impuestos->attributes() as $attr => $valor) {
            $resultado['CAMPOS']['IMPUESTOS_' . $attr] = (string)$valor;
        }
    }
    
    // TIMBRE FISCAL DIGITAL
    $timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0] ?? null;
    if ($timbre) {
        foreach ($timbre->attributes() as $attr => $valor) {
            $resultado['CAMPOS']['TIMBRE_' . $attr] = (string)$valor;
        }
    }
    
    // CONCEPTOS (primer concepto)
    $conceptos = $xml->xpath('//cfdi:Concepto');
    if (!empty($conceptos)) {
        $resultado['CAMPOS']['CONCEPTOS_TOTAL'] = count($conceptos);
        $primer_concepto = $conceptos[0];
        foreach ($primer_concepto->attributes() as $attr => $valor) {
            $resultado['CAMPOS']['CONCEPTO_' . $attr] = (string)$valor;
        }
    }
    
    // COMPLEMENTOS
    $complementos = $xml->xpath('//cfdi:Complemento/*');
    $resultado['COMPLEMENTOS'] = [];
    
    foreach ($complementos as $comp) {
        $nombre = $comp->getName();
        
        $resultado['COMPLEMENTOS'][$nombre] = [
            'NAMESPACE' => 'detected',
            'ATRIBUTOS' => []
        ];
        
        foreach ($comp->attributes() as $attr => $valor) {
            $resultado['COMPLEMENTOS'][$nombre]['ATRIBUTOS'][$attr] = (string)$valor;
            $resultado['CAMPOS']['COMPLEMENTO_' . strtoupper($nombre) . '_' . $attr] = (string)$valor;
        }
        
        // Si es complemento de Pagos, analizar estructura interna
        if ($nombre === 'Pagos') {
            // Analizar Totales
            foreach ($comp->xpath('.//pago20:Totales') as $totales) {
                foreach ($totales->attributes() as $attr => $valor) {
                    $resultado['CAMPOS']['PAGOS_TOTALES_' . $attr] = (string)$valor;
                }
            }
            
            // Analizar Pago
            foreach ($comp->xpath('.//pago20:Pago') as $pago) {
                foreach ($pago->attributes() as $attr => $valor) {
                    $resultado['CAMPOS']['PAGOS_PAGO_' . $attr] = (string)$valor;
                }
            }
            
            // Analizar DoctoRelacionado
            foreach ($comp->xpath('.//pago20:DoctoRelacionado') as $docto) {
                foreach ($docto->attributes() as $attr => $valor) {
                    $resultado['CAMPOS']['PAGOS_DOCTO_' . $attr] = (string)$valor;
                }
            }
        }
    }
    
    return $resultado;
}

// Analizar todos los archivos
$analisis_completo = [];

foreach ($archivos as $tipo => $ruta) {
    echo "📄 ANALIZANDO: $tipo\n";
    echo "   Ruta: " . basename($ruta) . "\n";
    
    $analisis_completo[$tipo] = extraerTodosCampos($ruta, $tipo);
    
    if (isset($analisis_completo[$tipo]['ERROR'])) {
        echo "   ❌ Error: " . $analisis_completo[$tipo]['ERROR'] . "\n";
    } else {
        echo "   ✅ Versión: " . $analisis_completo[$tipo]['VERSION'] . "\n";
        echo "   🔢 Campos: " . count($analisis_completo[$tipo]['CAMPOS']) . "\n";
        echo "   📦 Complementos: " . count($analisis_completo[$tipo]['COMPLEMENTOS']) . "\n";
    }
    echo "\n";
}

// CREAR TABLA COMPARATIVA COMPLETA
echo str_repeat("=", 100) . "\n";
echo "📊 TABLA COMPARATIVA COMPLETA DE CAMPOS\n";
echo str_repeat("=", 100) . "\n\n";

// Recopilar TODOS los campos únicos
$todos_campos = [];
foreach ($analisis_completo as $tipo => $data) {
    if (isset($data['CAMPOS'])) {
        foreach ($data['CAMPOS'] as $campo => $valor) {
            $todos_campos[$campo] = true;
        }
    }
}

ksort($todos_campos);

// Encabezados
echo sprintf("%-50s | %-15s | %-15s | %-15s | %-15s\n", 
    "CAMPO", 
    "3.3 EMIT", 
    "3.3 RECIB",
    "4.0 EMIT", 
    "4.0 RECIB"
);
echo str_repeat("-", 140) . "\n";

// Mostrar cada campo
foreach ($todos_campos as $campo => $dummy) {
    $cfdi33_emit = isset($analisis_completo['CFDI_33_2022_EMITIDO']['CAMPOS'][$campo]) ? '✅' : '❌';
    $cfdi33_recib = isset($analisis_completo['CFDI_33_2022_RECIBIDO']['CAMPOS'][$campo]) ? '✅' : '❌';
    $cfdi40_emit = isset($analisis_completo['CFDI_40_2024_EMITIDO']['CAMPOS'][$campo]) ? '✅' : '❌';
    $cfdi40_recib = isset($analisis_completo['CFDI_40_2024_RECIBIDO']['CAMPOS'][$campo]) ? '✅' : '❌';
    
    echo sprintf("%-50s | %-15s | %-15s | %-15s | %-15s\n", 
        substr($campo, 0, 49), 
        $cfdi33_emit, 
        $cfdi33_recib,
        $cfdi40_emit, 
        $cfdi40_recib
    );
}

// RESUMEN POR CATEGORÍAS
echo "\n" . str_repeat("=", 100) . "\n";
echo "📋 RESUMEN POR CATEGORÍAS\n";
echo str_repeat("=", 100) . "\n\n";

$categorias = [
    'COMPROBANTE' => 'Datos del comprobante principal',
    'EMISOR' => 'Datos del emisor',
    'RECEPTOR' => 'Datos del receptor',
    'IMPUESTOS' => 'Información de impuestos',
    'TIMBRE' => 'Timbre Fiscal Digital',
    'CONCEPTO' => 'Datos de conceptos',
    'COMPLEMENTO' => 'Complementos diversos',
    'PAGOS' => 'Complemento de Pagos 2.0'
];

foreach ($categorias as $prefijo => $descripcion) {
    echo "🔸 $descripcion:\n";
    
    $campos_categoria = array_filter(array_keys($todos_campos), function($campo) use ($prefijo) {
        return strpos($campo, $prefijo . '_') === 0;
    });
    
    echo "   Total campos: " . count($campos_categoria) . "\n";
    
    foreach (['CFDI_33_2022_EMITIDO', 'CFDI_33_2022_RECIBIDO', 'CFDI_40_2024_EMITIDO', 'CFDI_40_2024_RECIBIDO'] as $tipo) {
        $presentes = 0;
        foreach ($campos_categoria as $campo) {
            if (isset($analisis_completo[$tipo]['CAMPOS'][$campo])) {
                $presentes++;
            }
        }
        echo "   $tipo: $presentes/" . count($campos_categoria) . "\n";
    }
    echo "\n";
}

// COMPLEMENTOS DETECTADOS
echo str_repeat("=", 100) . "\n";
echo "📦 COMPLEMENTOS DETECTADOS POR TIPO\n";
echo str_repeat("=", 100) . "\n\n";

foreach ($analisis_completo as $tipo => $data) {
    if (isset($data['COMPLEMENTOS'])) {
        echo "🔹 $tipo:\n";
        foreach ($data['COMPLEMENTOS'] as $nombre => $info) {
            echo "   📦 $nombre (Namespace: " . basename($info['NAMESPACE']) . ")\n";
            echo "      Atributos: " . count($info['ATRIBUTOS']) . "\n";
        }
        echo "\n";
    }
}

// GUARDAR REPORTE COMPLETO
$fecha = date('Y-m-d_H-i-s');
$archivo_reporte = "MAPEO_DEFINITIVO_CFDI_CAMPOS.txt";

ob_start();
echo "MAPEO DEFINITIVO DE CAMPOS CFDI XML -> BASE DE DATOS\n";
echo "Generado: $fecha\n";
echo str_repeat("=", 80) . "\n\n";

echo "RESUMEN:\n";
echo "- CFDI 3.3 (2022 Emitido): " . count($analisis_completo['CFDI_33_2022_EMITIDO']['CAMPOS']) . " campos\n";
echo "- CFDI 3.3 (2022 Recibido): " . count($analisis_completo['CFDI_33_2022_RECIBIDO']['CAMPOS']) . " campos\n";
echo "- CFDI 4.0 (2024 Emitido): " . count($analisis_completo['CFDI_40_2024_EMITIDO']['CAMPOS']) . " campos\n";
echo "- CFDI 4.0 (2024 Recibido): " . count($analisis_completo['CFDI_40_2024_RECIBIDO']['CAMPOS']) . " campos\n";
echo "- Total campos únicos: " . count($todos_campos) . "\n\n";

echo "LISTADO COMPLETO DE CAMPOS:\n";
echo str_repeat("-", 80) . "\n";
foreach ($todos_campos as $campo => $dummy) {
    echo "$campo\n";
}

$contenido_reporte = ob_get_clean();
file_put_contents($archivo_reporte, $contenido_reporte);

echo "💾 REPORTE GUARDADO EN: $archivo_reporte\n";
echo "📊 TOTAL CAMPOS ÚNICOS IDENTIFICADOS: " . count($todos_campos) . "\n";
echo "\n✅ ANÁLISIS DEFINITIVO COMPLETADO\n";
?>
