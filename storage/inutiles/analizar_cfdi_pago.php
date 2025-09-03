<?php

/**
 * Analizador de CFDIs de pago
 * Verifica qué está pasando con los complementos
 */

require_once __DIR__ . '/src/config/database.php';

echo "🔍 ANALIZANDO CFDIs DE PAGO\n\n";

try {
    $pdo = getDatabase();

    // Obtener un CFDI de pago para analizar
    $stmt = $pdo->query("
        SELECT c.id, c.uuid, c.archivo_xml, c.complemento_tipo, c.complemento_json
        FROM cfdi c
        LEFT JOIN cfdi_pagos p ON c.id = p.cfdi_id
        WHERE c.tipo = 'P' 
        AND p.id IS NULL
        LIMIT 1
    ");

    $cfdi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cfdi) {
        echo "❌ No se encontraron CFDIs de pago pendientes\n";
        exit(0);
    }

    echo "📋 ANALIZANDO CFDI: {$cfdi['uuid']}\n";
    echo "Archivo XML: {$cfdi['archivo_xml']}\n";
    echo "Complemento tipo: " . ($cfdi['complemento_tipo'] ?: 'NULL') . "\n";
    echo "Complemento JSON length: " . strlen($cfdi['complemento_json'] ?: '') . "\n\n";

    // Verificar si el archivo XML existe
    if (!file_exists($cfdi['archivo_xml'])) {
        echo "❌ Archivo XML no encontrado\n";
        exit(1);
    }

    $xmlContent = file_get_contents($cfdi['archivo_xml']);
    $xml = simplexml_load_string($xmlContent);

    if (!$xml) {
        echo "❌ Error al parsear XML\n";
        exit(1);
    }

    echo "🔍 ANALIZANDO ESTRUCTURA XML:\n";
    echo str_repeat("-", 40) . "\n";

    // Mostrar namespaces
    $namespaces = $xml->getNamespaces(true);
    echo "Namespaces encontrados:\n";
    foreach ($namespaces as $prefix => $uri) {
        echo "  $prefix -> $uri\n";
    }
    echo "\n";

    // Registrar namespaces conocidos
    $xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/3');
    $xml->registerXPathNamespace('cfdi40', 'http://www.sat.gob.mx/cfd/4');
    $xml->registerXPathNamespace('pago10', 'http://www.sat.gob.mx/Pagos');
    $xml->registerXPathNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');
    $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

    // Buscar complementos
    $complementos = $xml->xpath('//cfdi:Complemento | //cfdi40:Complemento');
    if ($complementos === false) {
        $complementos = [];
    }
    echo "Complementos encontrados: " . count($complementos) . "\n";

    if (!empty($complementos)) {
        foreach ($complementos as $i => $complemento) {
            echo "\nComplemento $i:\n";
            foreach ($complemento->children() as $child) {
                $name = $child->getName();
                $childNamespaces = $child->getNamespaces(true);
                $namespace = !empty($childNamespaces) ? implode(', ', array_keys($childNamespaces)) : 'default';
                echo "  - $name (namespaces: $namespace)\n";

                // Si es un complemento de pagos, mostrar detalles
                if (strpos($name, 'Pagos') !== false || strpos($namespace, 'Pagos') !== false) {
                    echo "    🎯 COMPLEMENTO DE PAGOS ENCONTRADO!\n";

                    // Mostrar atributos del complemento
                    $attrs = $child->attributes();
                    if (count($attrs) > 0) {
                        echo "    Atributos del complemento:\n";
                        foreach ($attrs as $attr => $value) {
                            echo "      $attr = $value\n";
                        }
                    }

                    // Mostrar pagos individuales
                    if (isset($child->Pago)) {
                        echo "    Pagos encontrados: " . count($child->Pago) . "\n";
                        foreach ($child->Pago as $j => $pago) {
                            echo "      Pago $j:\n";
                            $pagoAttrs = $pago->attributes();
                            foreach ($pagoAttrs as $attr => $value) {
                                echo "        $attr = $value\n";
                            }

                            // Documentos relacionados
                            if (isset($pago->DoctoRelacionado)) {
                                echo "        Documentos relacionados: " . count($pago->DoctoRelacionado) . "\n";
                                foreach ($pago->DoctoRelacionado as $k => $doc) {
                                    echo "          Doc $k:\n";
                                    $docAttrs = $doc->attributes();
                                    foreach ($docAttrs as $attr => $value) {
                                        echo "            $attr = $value\n";
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Verificar si ya está en complemento_json
    if (!empty($cfdi['complemento_json'])) {
        echo "\n🔍 ANALIZANDO COMPLEMENTO_JSON:\n";
        echo str_repeat("-", 40) . "\n";

        $complementoData = json_decode($cfdi['complemento_json'], true);
        if ($complementoData) {
            echo "JSON válido encontrado\n";
            if (isset($complementoData['Pagos'])) {
                echo "Sección 'Pagos' encontrada en JSON\n";
                echo "Estructura: " . print_r(array_keys($complementoData['Pagos']), true);
            } else {
                echo "No se encontró sección 'Pagos' en JSON\n";
                echo "Claves disponibles: " . implode(', ', array_keys($complementoData)) . "\n";
            }
        } else {
            echo "JSON inválido o vacío\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
