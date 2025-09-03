<?php

/**
 * ANALIZADOR COMPLETO DE TIPOS DE PAGOS EN XMLs CFDI
 * Examina todos los XMLs (emitidos y recibidos) para identificar:
 * - Tipos de complementos de pago
 * - Formas de pago
 * - Métodos de pago
 * - Versiones de complementos
 */

echo "🔍 ANÁLISIS EXHAUSTIVO DE TIPOS DE PAGOS EN XMLs CFDI\n";
echo "Generado: " . date('Y-m-d_H-i-s') . "\n";
echo str_repeat("=", 80) . "\n\n";

// Directorios a analizar
$directorios = [
    'storage/sat_downloads' => 'Todas las descargas SAT (EMITIDAS y RECIBIDAS)'
];

$resultados = [
    'complementos_pago' => [],
    'formas_pago' => [],
    'metodos_pago' => [],
    'versiones_complementos' => [],
    'tipos_comprobante_pago' => [],
    'monedas_pago' => [],
    'archivos_analizados' => [],
    'estadisticas' => [
        'total_xmls' => 0,
        'con_complemento_pago' => 0,
        'sin_complemento_pago' => 0,
        'cfdi_33' => 0,
        'cfdi_40' => 0,
        'emitidos' => 0,
        'recibidos' => 0
    ]
];

function buscarXMLsRecursivo($directorio, &$archivos = [])
{
    if (!is_dir($directorio)) {
        return $archivos;
    }

    $items = scandir($directorio);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;

        $ruta_completa = $directorio . '/' . $item;
        if (is_dir($ruta_completa)) {
            buscarXMLsRecursivo($ruta_completa, $archivos);
        } elseif (strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'xml') {
            $archivos[] = $ruta_completa;
        }
    }
    return $archivos;
}

function analizarXMLPagos($ruta_xml, $tipo_directorio)
{
    global $resultados;

    if (!file_exists($ruta_xml)) {
        return null;
    }

    $contenido = file_get_contents($ruta_xml);
    if (!$contenido) {
        return null;
    }

    // Crear DOMDocument
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);

    if (!$dom->loadXML($contenido)) {
        echo "⚠️  Error al cargar XML: " . basename($ruta_xml) . "\n";
        return null;
    }

    $xpath = new DOMXPath($dom);

    // Registrar namespaces comunes
    $namespaces = [
        'cfdi' => 'http://www.sat.gob.mx/cfd/3',
        'cfdi40' => 'http://www.sat.gob.mx/cfd/4',
        'pago10' => 'http://www.sat.gob.mx/Pagos',
        'pago20' => 'http://www.sat.gob.mx/Pagos20',
        'tfd' => 'http://www.sat.gob.mx/TimbreFiscalDigital'
    ];

    foreach ($namespaces as $prefix => $uri) {
        $xpath->registerNamespace($prefix, $uri);
    }

    $info = [
        'archivo' => basename($ruta_xml),
        'ruta' => $ruta_xml,
        'tipo' => $tipo_directorio,
        'version_cfdi' => null,
        'tipo_comprobante' => null,
        'forma_pago' => null,
        'metodo_pago' => null,
        'moneda' => null,
        'tiene_complemento_pago' => false,
        'complementos_pago' => [],
        'detalles_pagos' => []
    ];

    // Detectar versión CFDI
    $comprobante = $xpath->query('//cfdi:Comprobante | //cfdi40:Comprobante')->item(0);
    if ($comprobante) {
        $info['version_cfdi'] = $comprobante->getAttribute('Version');
        $info['tipo_comprobante'] = $comprobante->getAttribute('TipoDeComprobante');
        $info['forma_pago'] = $comprobante->getAttribute('FormaPago');
        $info['metodo_pago'] = $comprobante->getAttribute('MetodoPago');
        $info['moneda'] = $comprobante->getAttribute('Moneda');

        // Actualizar estadísticas
        if ($info['version_cfdi'] == '3.3') {
            $resultados['estadisticas']['cfdi_33']++;
        } elseif ($info['version_cfdi'] == '4.0') {
            $resultados['estadisticas']['cfdi_40']++;
        }

        if (strpos($tipo_directorio, 'EMITIDAS') !== false) {
            $resultados['estadisticas']['emitidos']++;
        } else {
            $resultados['estadisticas']['recibidos']++;
        }
    }

    // Buscar complementos de pago (versión 1.0)
    $pagos10 = $xpath->query('//pago10:Pagos');
    if ($pagos10->length > 0) {
        $info['tiene_complemento_pago'] = true;
        $info['complementos_pago'][] = 'Pagos v1.0';

        foreach ($pagos10 as $pago) {
            $version = $pago->getAttribute('Version');
            if ($version) {
                $resultados['versiones_complementos']['Pagos v1.0'][$version] =
                    ($resultados['versiones_complementos']['Pagos v1.0'][$version] ?? 0) + 1;
            }

            // Buscar detalles de pagos individuales
            $pagosIndividuales = $xpath->query('.//pago10:Pago', $pago);
            foreach ($pagosIndividuales as $pagoInd) {
                $detalle = [
                    'forma_pago' => $pagoInd->getAttribute('FormaDePagoP'),
                    'moneda' => $pagoInd->getAttribute('MonedaP'),
                    'monto' => $pagoInd->getAttribute('Monto'),
                    'fecha_pago' => $pagoInd->getAttribute('FechaPago')
                ];
                $info['detalles_pagos'][] = $detalle;

                if ($detalle['forma_pago']) {
                    $resultados['formas_pago'][$detalle['forma_pago']] =
                        ($resultados['formas_pago'][$detalle['forma_pago']] ?? 0) + 1;
                }
                if ($detalle['moneda']) {
                    $resultados['monedas_pago'][$detalle['moneda']] =
                        ($resultados['monedas_pago'][$detalle['moneda']] ?? 0) + 1;
                }
            }
        }
    }

    // Buscar complementos de pago (versión 2.0)
    $pagos20 = $xpath->query('//pago20:Pagos');
    if ($pagos20->length > 0) {
        $info['tiene_complemento_pago'] = true;
        $info['complementos_pago'][] = 'Pagos v2.0';

        foreach ($pagos20 as $pago) {
            $version = $pago->getAttribute('Version');
            if ($version) {
                $resultados['versiones_complementos']['Pagos v2.0'][$version] =
                    ($resultados['versiones_complementos']['Pagos v2.0'][$version] ?? 0) + 1;
            }

            // Buscar detalles de pagos individuales en v2.0
            $pagosIndividuales = $xpath->query('.//pago20:Pago', $pago);
            foreach ($pagosIndividuales as $pagoInd) {
                $detalle = [
                    'forma_pago' => $pagoInd->getAttribute('FormaDePagoP'),
                    'moneda' => $pagoInd->getAttribute('MonedaP'),
                    'monto' => $pagoInd->getAttribute('Monto'),
                    'fecha_pago' => $pagoInd->getAttribute('FechaPago'),
                    'tipo_cambio' => $pagoInd->getAttribute('TipoCambioP'),
                    'cuenta_beneficiario' => $pagoInd->getAttribute('CtaBeneficiario')
                ];
                $info['detalles_pagos'][] = $detalle;

                if ($detalle['forma_pago']) {
                    $resultados['formas_pago'][$detalle['forma_pago']] =
                        ($resultados['formas_pago'][$detalle['forma_pago']] ?? 0) + 1;
                }
                if ($detalle['moneda']) {
                    $resultados['monedas_pago'][$detalle['moneda']] =
                        ($resultados['monedas_pago'][$detalle['moneda']] ?? 0) + 1;
                }
            }
        }
    }

    // Registrar complementos encontrados
    foreach ($info['complementos_pago'] as $comp) {
        $resultados['complementos_pago'][$comp] =
            ($resultados['complementos_pago'][$comp] ?? 0) + 1;
    }

    // Registrar formas y métodos de pago del comprobante principal
    if ($info['forma_pago']) {
        $resultados['formas_pago'][$info['forma_pago']] =
            ($resultados['formas_pago'][$info['forma_pago']] ?? 0) + 1;
    }
    if ($info['metodo_pago']) {
        $resultados['metodos_pago'][$info['metodo_pago']] =
            ($resultados['metodos_pago'][$info['metodo_pago']] ?? 0) + 1;
    }
    if ($info['tipo_comprobante']) {
        $resultados['tipos_comprobante_pago'][$info['tipo_comprobante']] =
            ($resultados['tipos_comprobante_pago'][$info['tipo_comprobante']] ?? 0) + 1;
    }
    if ($info['moneda']) {
        $resultados['monedas_pago'][$info['moneda']] =
            ($resultados['monedas_pago'][$info['moneda']] ?? 0) + 1;
    }

    // Actualizar estadísticas
    if ($info['tiene_complemento_pago']) {
        $resultados['estadisticas']['con_complemento_pago']++;
    } else {
        $resultados['estadisticas']['sin_complemento_pago']++;
    }

    return $info;
}

// Procesar cada directorio
foreach ($directorios as $directorio => $descripcion) {
    echo "📁 ANALIZANDO: $descripcion ($directorio)\n";
    echo str_repeat("-", 50) . "\n";

    $archivos_xml = [];
    buscarXMLsRecursivo($directorio, $archivos_xml);

    if (empty($archivos_xml)) {
        echo "   ⚠️  No se encontraron archivos XML en este directorio\n\n";
        continue;
    }

    echo "   📄 Archivos XML encontrados: " . count($archivos_xml) . "\n";
    $resultados['estadisticas']['total_xmls'] += count($archivos_xml);

    $contador = 0;
    foreach ($archivos_xml as $archivo) {
        $contador++;
        if ($contador % 10 == 0) {
            echo "   📊 Procesados: $contador/" . count($archivos_xml) . "\n";
        }

        $info = analizarXMLPagos($archivo, $descripcion);
        if ($info) {
            $resultados['archivos_analizados'][] = $info;
        }
    }

    echo "   ✅ Completado: $contador archivos procesados\n\n";
}

// Generar reporte
echo "\n" . str_repeat("=", 80) . "\n";
echo "📊 REPORTE COMPLETO DE ANÁLISIS DE PAGOS\n";
echo str_repeat("=", 80) . "\n\n";

echo "📈 ESTADÍSTICAS GENERALES:\n";
echo "• Total XMLs analizados: " . $resultados['estadisticas']['total_xmls'] . "\n";
echo "• Con complemento de pago: " . $resultados['estadisticas']['con_complemento_pago'] . "\n";
echo "• Sin complemento de pago: " . $resultados['estadisticas']['sin_complemento_pago'] . "\n";
echo "• CFDI 3.3: " . $resultados['estadisticas']['cfdi_33'] . "\n";
echo "• CFDI 4.0: " . $resultados['estadisticas']['cfdi_40'] . "\n";
echo "• Emitidos: " . $resultados['estadisticas']['emitidos'] . "\n";
echo "• Recibidos: " . $resultados['estadisticas']['recibidos'] . "\n\n";

echo "🔧 COMPLEMENTOS DE PAGO ENCONTRADOS:\n";
if (!empty($resultados['complementos_pago'])) {
    arsort($resultados['complementos_pago']);
    foreach ($resultados['complementos_pago'] as $complemento => $cantidad) {
        echo "• $complemento: $cantidad archivos\n";
    }
} else {
    echo "• No se encontraron complementos de pago\n";
}
echo "\n";

echo "💰 FORMAS DE PAGO IDENTIFICADAS:\n";
if (!empty($resultados['formas_pago'])) {
    arsort($resultados['formas_pago']);
    foreach ($resultados['formas_pago'] as $forma => $cantidad) {
        echo "• $forma: $cantidad ocurrencias\n";
    }
} else {
    echo "• No se encontraron formas de pago\n";
}
echo "\n";

echo "💳 MÉTODOS DE PAGO IDENTIFICADOS:\n";
if (!empty($resultados['metodos_pago'])) {
    arsort($resultados['metodos_pago']);
    foreach ($resultados['metodos_pago'] as $metodo => $cantidad) {
        echo "• $metodo: $cantidad ocurrencias\n";
    }
} else {
    echo "• No se encontraron métodos de pago\n";
}
echo "\n";

echo "💵 MONEDAS UTILIZADAS:\n";
if (!empty($resultados['monedas_pago'])) {
    arsort($resultados['monedas_pago']);
    foreach ($resultados['monedas_pago'] as $moneda => $cantidad) {
        echo "• $moneda: $cantidad ocurrencias\n";
    }
} else {
    echo "• No se encontraron monedas específicas\n";
}
echo "\n";

echo "📋 TIPOS DE COMPROBANTE:\n";
if (!empty($resultados['tipos_comprobante_pago'])) {
    arsort($resultados['tipos_comprobante_pago']);
    foreach ($resultados['tipos_comprobante_pago'] as $tipo => $cantidad) {
        echo "• $tipo: $cantidad ocurrencias\n";
    }
}
echo "\n";

echo "🔢 VERSIONES DE COMPLEMENTOS:\n";
foreach ($resultados['versiones_complementos'] as $complemento => $versiones) {
    echo "• $complemento:\n";
    foreach ($versiones as $version => $cantidad) {
        echo "  - Versión $version: $cantidad archivos\n";
    }
}
echo "\n";

// Guardar reporte detallado
$reporte_detallado = "ANÁLISIS EXHAUSTIVO DE TIPOS DE PAGOS EN XMLs CFDI\n";
$reporte_detallado .= "Generado: " . date('Y-m-d_H-i-s') . "\n";
$reporte_detallado .= str_repeat("=", 80) . "\n\n";

$reporte_detallado .= "ESTADÍSTICAS GENERALES:\n";
$reporte_detallado .= "Total XMLs analizados: " . $resultados['estadisticas']['total_xmls'] . "\n";
$reporte_detallado .= "Con complemento de pago: " . $resultados['estadisticas']['con_complemento_pago'] . "\n";
$reporte_detallado .= "Sin complemento de pago: " . $resultados['estadisticas']['sin_complemento_pago'] . "\n";
$reporte_detallado .= "CFDI 3.3: " . $resultados['estadisticas']['cfdi_33'] . "\n";
$reporte_detallado .= "CFDI 4.0: " . $resultados['estadisticas']['cfdi_40'] . "\n";
$reporte_detallado .= "Emitidos: " . $resultados['estadisticas']['emitidos'] . "\n";
$reporte_detallado .= "Recibidos: " . $resultados['estadisticas']['recibidos'] . "\n\n";

$reporte_detallado .= "COMPLEMENTOS DE PAGO ENCONTRADOS:\n";
foreach ($resultados['complementos_pago'] as $complemento => $cantidad) {
    $reporte_detallado .= "$complemento: $cantidad archivos\n";
}
$reporte_detallado .= "\n";

$reporte_detallado .= "FORMAS DE PAGO IDENTIFICADAS:\n";
foreach ($resultados['formas_pago'] as $forma => $cantidad) {
    $reporte_detallado .= "$forma: $cantidad ocurrencias\n";
}
$reporte_detallado .= "\n";

$reporte_detallado .= "MÉTODOS DE PAGO IDENTIFICADOS:\n";
foreach ($resultados['metodos_pago'] as $metodo => $cantidad) {
    $reporte_detallado .= "$metodo: $cantidad ocurrencias\n";
}
$reporte_detallado .= "\n";

$reporte_detallado .= "MONEDAS UTILIZADAS:\n";
foreach ($resultados['monedas_pago'] as $moneda => $cantidad) {
    $reporte_detallado .= "$moneda: $cantidad ocurrencias\n";
}
$reporte_detallado .= "\n";

$reporte_detallado .= "TIPOS DE COMPROBANTE:\n";
foreach ($resultados['tipos_comprobante_pago'] as $tipo => $cantidad) {
    $reporte_detallado .= "$tipo: $cantidad ocurrencias\n";
}
$reporte_detallado .= "\n";

$reporte_detallado .= "VERSIONES DE COMPLEMENTOS:\n";
foreach ($resultados['versiones_complementos'] as $complemento => $versiones) {
    $reporte_detallado .= "$complemento:\n";
    foreach ($versiones as $version => $cantidad) {
        $reporte_detallado .= "  Versión $version: $cantidad archivos\n";
    }
}
$reporte_detallado .= "\n";

$reporte_detallado .= "DETALLE POR ARCHIVO:\n";
$reporte_detallado .= str_repeat("-", 80) . "\n";
foreach ($resultados['archivos_analizados'] as $archivo) {
    $reporte_detallado .= "Archivo: " . $archivo['archivo'] . "\n";
    $reporte_detallado .= "Tipo: " . $archivo['tipo'] . "\n";
    $reporte_detallado .= "Versión CFDI: " . ($archivo['version_cfdi'] ?? 'N/A') . "\n";
    $reporte_detallado .= "Tipo Comprobante: " . ($archivo['tipo_comprobante'] ?? 'N/A') . "\n";
    $reporte_detallado .= "Forma Pago: " . ($archivo['forma_pago'] ?? 'N/A') . "\n";
    $reporte_detallado .= "Método Pago: " . ($archivo['metodo_pago'] ?? 'N/A') . "\n";
    $reporte_detallado .= "Moneda: " . ($archivo['moneda'] ?? 'N/A') . "\n";
    $reporte_detallado .= "Complementos: " . implode(', ', $archivo['complementos_pago']) . "\n";
    if (!empty($archivo['detalles_pagos'])) {
        $reporte_detallado .= "Detalles de Pagos:\n";
        foreach ($archivo['detalles_pagos'] as $detalle) {
            $reporte_detallado .= "  - Forma: " . ($detalle['forma_pago'] ?? 'N/A') .
                ", Moneda: " . ($detalle['moneda'] ?? 'N/A') .
                ", Monto: " . ($detalle['monto'] ?? 'N/A') . "\n";
        }
    }
    $reporte_detallado .= str_repeat("-", 40) . "\n";
}

$nombre_archivo = "ANALISIS_TIPOS_PAGOS_COMPLETO_" . date('Y-m-d_H-i-s') . ".txt";
file_put_contents($nombre_archivo, $reporte_detallado);

echo "💾 REPORTE DETALLADO GUARDADO EN: $nombre_archivo\n";
echo "✅ ANÁLISIS COMPLETO DE TIPOS DE PAGOS FINALIZADO\n";
