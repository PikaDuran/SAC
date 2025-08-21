<?php

/**
 * Analizar estructura XML del complemento de pagos
 */

require_once 'src/config/database.php';

try {
    $pdo = getDatabase();

    // Obtener un CFDI de pago
    $stmt = $pdo->prepare("SELECT id, uuid, archivo_xml FROM cfdi WHERE tipo = 'P' AND complemento_json IS NOT NULL LIMIT 1");
    $stmt->execute();
    $cfdi = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cfdi && file_exists($cfdi['archivo_xml'])) {
        $xmlContent = file_get_contents($cfdi['archivo_xml']);

        echo "=== ANÁLISIS DE ESTRUCTURA XML ===\n\n";
        echo "Archivo: {$cfdi['archivo_xml']}\n\n";

        // Buscar sección completa de pagos
        if (preg_match('/<cfdi:Complemento>.*?<\/cfdi:Complemento>/s', $xmlContent, $matches)) {
            $complemento = $matches[0];
            echo "COMPLEMENTO ENCONTRADO:\n";
            echo "======================\n";
            echo $complemento . "\n\n";

            // Buscar específicamente pagos
            if (preg_match('/<pago[^:]*:Pagos[^>]*>.*?<\/pago[^:]*:Pagos>/s', $complemento, $pagosMatch)) {
                echo "SECCIÓN DE PAGOS:\n";
                echo "=================\n";
                echo $pagosMatch[0] . "\n\n";

                // Analizar estructura de pago individual
                if (preg_match('/<pago[^:]*:Pago[^>]*>/', $pagosMatch[0], $pagoMatch)) {
                    echo "ETIQUETA DE PAGO:\n";
                    echo "=================\n";
                    echo $pagoMatch[0] . "\n\n";
                }

                // Analizar documentos relacionados
                if (preg_match_all('/<pago[^:]*:DoctoRelacionado[^>]*>/', $pagosMatch[0], $docMatches)) {
                    echo "DOCUMENTOS RELACIONADOS:\n";
                    echo "========================\n";
                    foreach ($docMatches[0] as $doc) {
                        echo $doc . "\n";
                    }
                }
            } else {
                echo "NO SE ENCONTRÓ SECCIÓN DE PAGOS\n";

                // Buscar con diferentes namespaces
                echo "\nBUSCANDO CON DIFERENTES NAMESPACES:\n";
                echo "===================================\n";

                $namespaces = ['pago10', 'pago', 'pago20', 'Pago'];
                foreach ($namespaces as $ns) {
                    if (preg_match("/<{$ns}:Pagos/", $complemento)) {
                        echo "✓ Encontrado namespace: $ns\n";
                    } else {
                        echo "✗ No encontrado namespace: $ns\n";
                    }
                }
            }
        } else {
            echo "NO SE ENCONTRÓ COMPLEMENTO\n";
        }

        // Verificar TipoDeComprobante
        if (preg_match('/TipoDeComprobante=["\']([^"\']*)["\']/', $xmlContent, $matches)) {
            echo "\nTIPO DE COMPROBANTE: {$matches[1]}\n";
        }
    } else {
        echo "No se encontró archivo o CFDI\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
