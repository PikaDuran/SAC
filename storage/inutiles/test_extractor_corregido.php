<?php

/**
 * Extractor corregido de complementos de pago
 */

require_once 'src/config/database.php';

// Función para extraer complementos de pago CORREGIDA
function extraerComplementoPagosCorregido($contenidoXML)
{
    // Verificar si es un CFDI de pago
    if (!preg_match('/TipoDeComprobante\s*=\s*["\']P["\']/', $contenidoXML)) {
        return null;
    }

    $resultado = [];

    // Buscar complemento de pagos
    if (preg_match('/<pago10:Pagos[^>]*>.*?<\/pago10:Pagos>/s', $contenidoXML, $matches)) {
        $complemento = $matches[0];

        // Extraer pagos individuales
        if (preg_match_all('/<pago10:Pago[^>]*>.*?<\/pago10:Pago>/s', $complemento, $pagosCompletos)) {
            foreach ($pagosCompletos[0] as $pagoCompleto) {
                $pago = [];

                // Extraer atributos del pago
                if (preg_match('/<pago10:Pago\s+([^>]*)>/', $pagoCompleto, $pagoAttr)) {
                    $atributos = $pagoAttr[1];

                    $campos = [
                        'FechaPago' => 'fecha_pago',
                        'FormaDePagoP' => 'forma_pago',
                        'MonedaP' => 'moneda',
                        'TipoCambioP' => 'tipo_cambio',
                        'Monto' => 'monto',
                        'NumOperacion' => 'num_operacion'
                    ];

                    foreach ($campos as $campo => $columna) {
                        if (preg_match('/' . $campo . '\s*=\s*["\']([^"\']*)["\']/', $atributos, $matches)) {
                            $pago[$columna] = trim($matches[1]);
                        }
                    }
                }

                // Extraer documentos relacionados - PATRÓN CORREGIDO
                $documentos = [];
                if (preg_match_all('/<pago10:DoctoRelacionado[^>]*>.*?<\/pago10:DoctoRelacionado>/s', $pagoCompleto, $docsMatches)) {
                    foreach ($docsMatches[0] as $docCompleto) {
                        $documento = [];

                        $camposDoc = [
                            'IdDocumento' => 'uuid_documento',
                            'Serie' => 'serie',
                            'Folio' => 'folio',
                            'MonedaDR' => 'moneda_dr',
                            'ImpPagado' => 'imp_pagado',
                            'ImpSaldoAnt' => 'imp_saldo_ant',
                            'ImpSaldoInsoluto' => 'imp_saldo_insoluto',
                            'NumParcialidad' => 'num_parcialidad'
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
    echo "=== EXTRACTOR CORREGIDO DE COMPLEMENTOS ===\n\n";

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
    $complementos = extraerComplementoPagosCorregido($contenidoXML);

    if ($complementos) {
        echo "✅ EXTRACCIÓN EXITOSA!\n";
        echo "   Pagos encontrados: " . count($complementos) . "\n\n";

        foreach ($complementos as $i => $pago) {
            echo "PAGO $i:\n";
            foreach ($pago as $campo => $valor) {
                if ($campo !== 'documentos_relacionados') {
                    echo "  $campo: $valor\n";
                }
            }

            if (isset($pago['documentos_relacionados'])) {
                echo "  documentos_relacionados: " . count($pago['documentos_relacionados']) . "\n";
                foreach ($pago['documentos_relacionados'] as $j => $doc) {
                    echo "    Documento $j:\n";
                    foreach ($doc as $campo => $valor) {
                        echo "      $campo: $valor\n";
                    }
                }
            }
            echo "\n";
        }

        // Insertar en la base de datos
        echo "=== INSERTANDO EN BASE DE DATOS ===\n";

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
                            pago_id, uuid_documento, serie, folio, moneda_dr, 
                            imp_pagado, imp_saldo_ant, imp_saldo_insoluto, num_parcialidad
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

                        $stmt_doc = $pdo->prepare($sql_doc);
                        $result_doc = $stmt_doc->execute([
                            $pago_id,
                            $doc['uuid_documento'] ?? null,
                            $doc['serie'] ?? null,
                            $doc['folio'] ?? null,
                            $doc['moneda_dr'] ?? null,
                            $doc['imp_pagado'] ?? null,
                            $doc['imp_saldo_ant'] ?? null,
                            $doc['imp_saldo_insoluto'] ?? null,
                            $doc['num_parcialidad'] ?? null
                        ]);

                        if ($result_doc) {
                            $doc_id = $pdo->lastInsertId();
                            echo "  ✅ Documento insertado con ID: $doc_id\n";
                        } else {
                            echo "  ❌ Error insertando documento: " . implode(', ', $stmt_doc->errorInfo()) . "\n";
                        }
                    }
                }
            } else {
                echo "❌ Error insertando pago: " . implode(', ', $stmt->errorInfo()) . "\n";
            }
        }

        echo "\n=== VERIFICACIÓN FINAL ===\n";
        $pagosEnTabla = $pdo->query("SELECT COUNT(*) FROM cfdi_pagos WHERE cfdi_id = {$cfdi['id']}")->fetchColumn();
        $docsEnTabla = $pdo->query("SELECT COUNT(*) FROM cfdi_pago_documentos_relacionados WHERE pago_id IN (SELECT id FROM cfdi_pagos WHERE cfdi_id = {$cfdi['id']})")->fetchColumn();

        echo "Pagos en tabla: $pagosEnTabla\n";
        echo "Documentos en tabla: $docsEnTabla\n";
    } else {
        echo "❌ No se pudieron extraer complementos\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
