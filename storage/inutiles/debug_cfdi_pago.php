<?php

/**
 * Analizador detallado de un CFDI de pago específico
 * Para entender exactamente qué está pasando en el procesamiento
 */

require_once __DIR__ . '/src/config/database.php';

try {
    $pdo = getDatabase();

    // Obtener un CFDI de pago específico
    $stmt = $pdo->query("
        SELECT id, uuid, archivo_xml 
        FROM cfdi 
        WHERE tipo = 'P' 
        LIMIT 1
    ");

    $cfdi = $stmt->fetch();

    if (!$cfdi) {
        echo "❌ No se encontraron CFDIs de tipo P" . PHP_EOL;
        exit;
    }

    echo "🔍 ANÁLISIS DETALLADO DE CFDI DE PAGO" . PHP_EOL;
    echo "========================================" . PHP_EOL;
    echo "UUID: {$cfdi['uuid']}" . PHP_EOL;
    echo "Archivo: {$cfdi['archivo_xml']}" . PHP_EOL;
    echo PHP_EOL;

    if (!file_exists($cfdi['archivo_xml'])) {
        echo "❌ Archivo XML no encontrado" . PHP_EOL;
        exit;
    }

    $xmlContent = file_get_contents($cfdi['archivo_xml']);
    $xml = simplexml_load_string($xmlContent);

    if (!$xml) {
        echo "❌ Error al parsear XML" . PHP_EOL;
        exit;
    }

    echo "✅ XML cargado correctamente" . PHP_EOL;

    // Analizar namespaces
    $namespaces = $xml->getNamespaces(true);
    echo PHP_EOL . "📋 NAMESPACES DETECTADOS:" . PHP_EOL;
    foreach ($namespaces as $prefix => $uri) {
        echo "  $prefix: $uri" . PHP_EOL;
        if (strpos($uri, 'Pagos') !== false) {
            echo "    ⭐ NAMESPACE DE PAGOS!" . PHP_EOL;
        }
    }

    // Intentar registrar namespaces de pago
    echo PHP_EOL . "🔧 REGISTRANDO NAMESPACES:" . PHP_EOL;

    if (isset($namespaces['pago20'])) {
        echo "  ✅ Registrando namespace CFDI 4.0 (pago20)" . PHP_EOL;
        $xml->registerXPathNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');
        $pagos = $xml->xpath('//pago20:Pagos');
    } elseif (isset($namespaces['pago10'])) {
        echo "  ✅ Registrando namespace CFDI 3.3 (pago10)" . PHP_EOL;
        $xml->registerXPathNamespace('pago10', 'http://www.sat.gob.mx/Pagos');
        $pagos = $xml->xpath('//pago10:Pagos');
    } else {
        echo "  ❌ No se encontró namespace de pagos" . PHP_EOL;
        $pagos = [];
    }

    echo "📊 Complementos de pago encontrados: " . count($pagos) . PHP_EOL;

    if (empty($pagos)) {
        echo "⚠️  No se encontraron complementos de pago con XPath" . PHP_EOL;
        echo PHP_EOL . "🔍 Intentando búsqueda manual en complementos:" . PHP_EOL;

        // Buscar complementos manualmente
        if (isset($xml->Complemento)) {
            echo "  ✅ Elemento Complemento encontrado" . PHP_EOL;
            foreach ($xml->Complemento as $comp) {
                $children = $comp->children();
                echo "    Hijos del complemento: " . count($children) . PHP_EOL;
                foreach ($children as $child) {
                    echo "      - " . $child->getName() . PHP_EOL;
                    if ($child->getName() === 'Pagos') {
                        echo "        ⭐ COMPLEMENTO DE PAGOS ENCONTRADO!" . PHP_EOL;

                        // Analizar los pagos
                        if (isset($child->Pago)) {
                            echo "        📊 Pagos dentro del complemento: " . count($child->Pago) . PHP_EOL;

                            foreach ($child->Pago as $pago) {
                                $attrs = $pago->attributes();
                                echo "        💰 Pago detectado:" . PHP_EOL;
                                echo "          - FechaPago: " . (string)$attrs->FechaPago . PHP_EOL;
                                echo "          - FormaDePagoP: " . (string)$attrs->FormaDePagoP . PHP_EOL;
                                echo "          - MonedaP: " . (string)$attrs->MonedaP . PHP_EOL;
                                echo "          - Monto: " . (string)$attrs->Monto . PHP_EOL;

                                if (isset($pago->DoctoRelacionado)) {
                                    echo "          📋 Documentos relacionados: " . count($pago->DoctoRelacionado) . PHP_EOL;
                                    foreach ($pago->DoctoRelacionado as $doc) {
                                        $doc_attrs = $doc->attributes();
                                        echo "            • UUID: " . (string)$doc_attrs->IdDocumento . PHP_EOL;
                                        echo "            • Importe Pagado: " . (string)$doc_attrs->ImpPagado . PHP_EOL;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            echo "  ❌ No se encontró elemento Complemento" . PHP_EOL;
        }
    } else {
        echo "✅ Complementos encontrados con XPath!" . PHP_EOL;
        foreach ($pagos as $complemento) {
            echo "  📋 Analizando complemento..." . PHP_EOL;
            if (isset($complemento->Pago)) {
                echo "    💰 Pagos encontrados: " . count($complemento->Pago) . PHP_EOL;
            }
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
    echo "Stack trace:" . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
}
