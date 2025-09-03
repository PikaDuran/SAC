<?php

/**
 * Procesador CORREGIDO de complementos de pago
 * Procesa CFDIs de tipo P para extraer información de pagos
 */

require_once __DIR__ . '/src/config/database.php';

echo "🔄 PROCESANDO COMPLEMENTOS DE PAGO (VERSIÓN CORREGIDA)\n\n";

try {
    $pdo = getDatabase();

    // Buscar CFDIs de pago sin procesar
    $stmt = $pdo->query("
        SELECT c.id, c.uuid, c.archivo_xml, c.rfc_emisor, c.rfc_receptor, c.fecha
        FROM cfdi c
        LEFT JOIN cfdi_pagos p ON c.id = p.cfdi_id
        WHERE c.tipo = 'P' 
        AND p.id IS NULL
        ORDER BY c.fecha DESC
        LIMIT 100
    ");

    $cfdis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $procesados = 0;
    $pagos_insertados = 0;
    $documentos_insertados = 0;
    $errores = 0;

    echo "📊 CFDIs encontrados para procesar: " . count($cfdis) . "\n\n";

    foreach ($cfdis as $cfdi) {
        try {
            echo "🔍 Procesando: {$cfdi['uuid']}\n";

            // Leer archivo XML
            $xmlPath = $cfdi['archivo_xml'];
            if (!file_exists($xmlPath)) {
                echo "   ⚠️  Archivo XML no encontrado: $xmlPath\n";
                continue;
            }

            $xmlContent = file_get_contents($xmlPath);
            $xml = simplexml_load_string($xmlContent);

            if (!$xml) {
                echo "   ❌ Error al parsear XML\n";
                $errores++;
                continue;
            }

            // Buscar complemento de pagos
            $namespaces = $xml->getNamespaces(true);
            $pagos_encontrados = [];

            // CFDI 4.0
            if (isset($namespaces['pago20'])) {
                $xml->registerXPathNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');
                $pagos_encontrados = $xml->xpath('//pago20:Pago');
                echo "   🔧 Usando namespace CFDI 4.0\n";
            }
            // CFDI 3.3
            elseif (isset($namespaces['pago10'])) {
                $xml->registerXPathNamespace('pago10', 'http://www.sat.gob.mx/Pagos');
                $pagos_encontrados = $xml->xpath('//pago10:Pago');
                echo "   🔧 Usando namespace CFDI 3.3\n";
            }

            if (empty($pagos_encontrados)) {
                echo "   ⚠️  No se encontraron pagos en el complemento\n";
                continue;
            }

            echo "   💰 Pagos encontrados: " . count($pagos_encontrados) . "\n";

            // Procesar cada pago
            foreach ($pagos_encontrados as $pago) {
                $attrs = $pago->attributes();

                // Insertar pago
                $stmt_pago = $pdo->prepare("
                    INSERT INTO cfdi_pagos (
                        cfdi_id, fecha_pago, forma_pago, moneda, 
                        tipo_cambio, monto, num_operacion
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $fechaPago = isset($attrs->FechaPago) ? date('Y-m-d H:i:s', strtotime((string)$attrs->FechaPago)) : null;

                $stmt_pago->execute([
                    $cfdi['id'],
                    $fechaPago,
                    (string)$attrs->FormaDePagoP ?? null,
                    (string)$attrs->MonedaP ?? 'MXN',
                    (float)($attrs->TipoCambioP ?? 1.0),
                    (float)($attrs->Monto ?? 0.0),
                    (string)$attrs->NumOperacion ?? null
                ]);

                $pago_id = $pdo->lastInsertId();
                $pagos_insertados++;

                echo "     ✅ Pago insertado (ID: $pago_id)\n";
                echo "       - Fecha: " . (string)$attrs->FechaPago . "\n";
                echo "       - Forma de Pago: " . (string)$attrs->FormaDePagoP . "\n";
                echo "       - Moneda: " . (string)$attrs->MonedaP . "\n";
                echo "       - Monto: " . (string)$attrs->Monto . "\n";

                // Procesar documentos relacionados
                if (isset($pago->DoctoRelacionado)) {
                    echo "       📄 Documentos relacionados: " . count($pago->DoctoRelacionado) . "\n";
                    foreach ($pago->DoctoRelacionado as $doc) {
                        $doc_attrs = $doc->attributes();

                        $stmt_doc = $pdo->prepare("
                            INSERT INTO cfdi_pago_documentos_relacionados (
                                pago_id, uuid_documento, serie, folio, moneda_dr,
                                equivalencia_dr, num_parcialidad, imp_saldo_ant,
                                imp_pagado, imp_saldo_insoluto
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        $stmt_doc->execute([
                            $pago_id,
                            (string)$doc_attrs->IdDocumento,
                            (string)$doc_attrs->Serie ?? null,
                            (string)$doc_attrs->Folio ?? null,
                            (string)$doc_attrs->MonedaDR ?? 'MXN',
                            (float)($doc_attrs->EquivalenciaDR ?? 1.0),
                            (int)($doc_attrs->NumParcialidad ?? 1),
                            (float)($doc_attrs->ImpSaldoAnt ?? 0.0),
                            (float)($doc_attrs->ImpPagado ?? 0.0),
                            (float)($doc_attrs->ImpSaldoInsoluto ?? 0.0)
                        ]);

                        $documentos_insertados++;
                        echo "         • UUID: " . (string)$doc_attrs->IdDocumento . "\n";
                        echo "         • Importe Pagado: $" . number_format((float)$doc_attrs->ImpPagado, 2) . "\n";
                    }
                }
            }

            echo "   ✅ Procesado exitosamente\n\n";
            $procesados++;
        } catch (Exception $e) {
            echo "   ❌ Error: " . $e->getMessage() . "\n\n";
            $errores++;
        }
    }

    echo str_repeat("=", 50) . "\n";
    echo "📊 RESULTADOS:\n";
    echo "CFDIs procesados: $procesados\n";
    echo "Pagos insertados: $pagos_insertados\n";
    echo "Documentos relacionados: $documentos_insertados\n";
    echo "Errores: $errores\n";
    echo "\n✅ PROCESAMIENTO COMPLETADO\n\n";

    // Mostrar estadísticas generales
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P'");
    $total_cfdis_pago = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as procesados FROM cfdi_pagos");
    $total_procesados = $stmt->fetch(PDO::FETCH_ASSOC)['procesados'];

    echo "📈 ESTADO GENERAL:\n";
    echo "Total CFDIs de Pago: $total_cfdis_pago\n";
    echo "Complementos procesados: $total_procesados\n";
    echo "Pendientes: " . ($total_cfdis_pago - $total_procesados) . "\n\n";

    if (($total_cfdis_pago - $total_procesados) > 0) {
        echo "🔄 Para procesar más CFDIs, ejecuta nuevamente este script\n";
    }
} catch (Exception $e) {
    echo "❌ Error crítico: " . $e->getMessage() . "\n";
    exit(1);
}
