<?php

/**
 * Prueba del extractor de complementos de pago - versión simple
 */

require_once 'src/config/database.php';

// Función para extraer complementos de pago sin conflictos
function extraerComplementoPagosDirect($contenidoXML)
{
    // Verificar si es un CFDI de pago
    if (!preg_match('/TipoDeComprobante\s*=\s*["\']P["\']/', $contenidoXML)) {
        return null;
    }

    $resultado = [];

    // Buscar complemento de pagos (puede variar según versión)
    if (preg_match('/<pago10:Pagos[^>]*>.*?<\/pago10:Pagos>/s', $contenidoXML, $matches)) {
        $complemento = $matches[0];

        // Extraer pagos individuales con todo el bloque
        if (preg_match_all('/<pago10:Pago[^>]*>.*?<\/pago10:Pago>/s', $complemento, $pagosCompletos)) {
            foreach ($pagosCompletos[0] as $pagoCompleto) {
                $pago = [];

                // Extraer atributos del pago
                if (preg_match('/<pago10:Pago[^>]*([^>]*)>/', $pagoCompleto, $pagoAttr)) {
                    $campos = [
                        'FechaPago' => 'fecha_pago',
                        'FormaDePagoP' => 'forma_pago',
                        'MonedaP' => 'moneda',
                        'TipoCambioP' => 'tipo_cambio',
                        'Monto' => 'monto',
                        'NumOperacion' => 'num_operacion'
                    ];

                    foreach ($campos as $campo => $columna) {
                        if (preg_match('/' . $campo . '\s*=\s*["\']([^"\']*)["\']/', $pagoAttr[1], $matches)) {
                            $pago[$columna] = trim($matches[1]);
                        }
                    }
                }

                // Extraer documentos relacionados
                $documentos = [];
                if (preg_match_all('/<pago10:DoctoRelacionado[^>]*([^>\/]*)\/?>/s', $pagoCompleto, $docsMatches)) {
                    foreach ($docsMatches[0] as $docCompleto) {
                        $documento = [];

                        $camposDoc = [
                            'IdDocumento' => 'uuid_documento',
                            'MonedaDR' => 'moneda_dr',
                            'ImpPagado' => 'imp_pagado'
                        ];

                        foreach ($camposDoc as $campo => $columna) {
                            if (preg_match('/' . $campo . '\s*=\s*["\']([^"\']*)["\']/', $docCompleto, $matches)) {
                                $documento[$columna] = trim($matches[1]);
                            }
                        }

                        if (!empty($documento)) {
                            $documentos[] = $documento;
                        }
                    }
                }

                if (!empty($pago)) {
                    $pago['documentos_relacionados'] = $documentos;
                    $resultado[] = $pago;
                }
            }
        }
    }

    return !empty($resultado) ? $resultado : null;
}

try {
    $pdo = getDatabase();
    echo "=== PRUEBA SIMPLE EXTRACTOR COMPLEMENTOS ===\n\n";

    // Obtener un CFDI de pago
    $cfdi = $pdo->query("
        SELECT id, uuid, archivo_xml 
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

    echo "✅ CFDI: {$cfdi['uuid']}\n";

    $contenidoXML = file_get_contents($cfdi['archivo_xml']);
    $complementos = extraerComplementoPagosDirect($contenidoXML);

    if ($complementos) {
        echo "✅ EXTRACCIÓN EXITOSA!\n";
        echo "   Pagos encontrados: " . count($complementos) . "\n";

        foreach ($complementos as $i => $pago) {
            echo "\n   Pago $i:\n";
            foreach ($pago as $campo => $valor) {
                if ($campo !== 'documentos_relacionados') {
                    echo "     $campo: $valor\n";
                }
            }
            if (isset($pago['documentos_relacionados'])) {
                echo "     documentos: " . count($pago['documentos_relacionados']) . "\n";
            }
        }

        // Ahora vamos a insertar manualmente para probar
        echo "\n=== INSERTANDO EN TABLA ===\n";

        foreach ($complementos as $pago) {
            $sql = "INSERT INTO cfdi_pagos (
                cfdi_id, fecha_pago, forma_pago, moneda, monto
            ) VALUES (?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $cfdi['id'],
                $pago['fecha_pago'] ?? null,
                $pago['forma_pago'] ?? null,
                $pago['moneda'] ?? null,
                $pago['monto'] ?? null
            ]);

            if ($result) {
                $pago_id = $pdo->lastInsertId();
                echo "✅ Pago insertado con ID: $pago_id\n";

                // Insertar documentos relacionados
                if (!empty($pago['documentos_relacionados'])) {
                    foreach ($pago['documentos_relacionados'] as $doc) {
                        $sql_doc = "INSERT INTO cfdi_pago_documentos_relacionados (
                            pago_id, uuid_documento, moneda_dr, imp_pagado
                        ) VALUES (?, ?, ?, ?)";

                        $stmt_doc = $pdo->prepare($sql_doc);
                        $result_doc = $stmt_doc->execute([
                            $pago_id,
                            $doc['uuid_documento'] ?? null,
                            $doc['moneda_dr'] ?? null,
                            $doc['imp_pagado'] ?? null
                        ]);

                        if ($result_doc) {
                            echo "   ✅ Documento insertado\n";
                        }
                    }
                }
            } else {
                echo "❌ Error insertando pago\n";
                print_r($stmt->errorInfo());
            }
        }
    } else {
        echo "❌ No se pudieron extraer complementos\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
