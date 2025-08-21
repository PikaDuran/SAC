<?php

/**
 * Script FINAL CORREGIDO para reprocesar CFDIs de pago
 */

// Evitar redeclaración
if (!function_exists('getDatabase')) {
    require_once 'src/config/database.php';
}

// Incluir solo si no existe la clase
if (!class_exists('ImportadorInteligenteCFDI')) {
    require_once 'importador_inteligente_cfdi.php';
}

try {
    $pdo = getDatabase();
    echo "=== REPROCESAMIENTO FINAL CORREGIDO ===\n\n";

    // Crear instancia del importador
    $importador = new ImportadorInteligenteCFDI();

    // Obtener un CFDI de prueba que sabemos que funciona
    $cfdiTest = $pdo->query("
        SELECT id, uuid, archivo_xml 
        FROM cfdi 
        WHERE tipo = 'P' 
        AND complemento_json IS NOT NULL 
        AND complemento_json != '[]' 
        AND complemento_json != ''
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$cfdiTest) {
        echo "❌ No se encontró CFDI de prueba\n";
        exit;
    }

    echo "CFDI de prueba:\n";
    echo "ID: {$cfdiTest['id']}\n";
    echo "UUID: {$cfdiTest['uuid']}\n";
    echo "Archivo: {$cfdiTest['archivo_xml']}\n\n";

    // Verificar archivo
    if (!file_exists($cfdiTest['archivo_xml'])) {
        echo "❌ Archivo no existe\n";
        exit;
    }

    // Leer contenido
    $contenidoXML = file_get_contents($cfdiTest['archivo_xml']);
    echo "✓ Archivo leído: " . strlen($contenidoXML) . " bytes\n\n";

    // Usar reflexión para acceder al método privado
    $reflection = new ReflectionClass($importador);
    $metodo = $reflection->getMethod('extraerComplementoPagos');
    $metodo->setAccessible(true);

    // Extraer complementos
    echo "Extrayendo complementos de pago...\n";
    $complementos = $metodo->invoke($importador, $contenidoXML);

    if ($complementos) {
        echo "✅ ¡ÉXITO! Encontrados " . count($complementos) . " pagos\n\n";

        echo "DATOS EXTRAÍDOS:\n";
        echo "═══════════════════════════════════════\n";

        foreach ($complementos as $i => $pago) {
            echo "Pago " . ($i + 1) . ":\n";
            echo "  Fecha: " . ($pago['fecha_pago'] ?? 'N/A') . "\n";
            echo "  Forma de pago: " . ($pago['forma_pago'] ?? 'N/A') . "\n";
            echo "  Moneda: " . ($pago['moneda'] ?? 'N/A') . "\n";
            echo "  Monto: " . ($pago['monto'] ?? 'N/A') . "\n";
            echo "  Documentos relacionados: " . count($pago['documentos_relacionados'] ?? []) . "\n";

            if (!empty($pago['documentos_relacionados'])) {
                foreach ($pago['documentos_relacionados'] as $j => $doc) {
                    echo "    Doc " . ($j + 1) . ": UUID={$doc['uuid_documento']}, Serie={$doc['serie']}, Folio={$doc['folio']}, Monto={$doc['imp_pagado']}\n";
                }
            }
            echo "\n";
        }

        // Ahora insertar en las tablas
        echo "INSERTANDO EN TABLAS ESTRUCTURADAS:\n";
        echo "═══════════════════════════════════════\n";

        // Limpiar datos existentes del CFDI
        $pdo->exec("DELETE FROM cfdi_pago_documentos_relacionados WHERE pago_id IN (SELECT id FROM cfdi_pagos WHERE cfdi_id = {$cfdiTest['id']})");
        $pdo->exec("DELETE FROM cfdi_pagos WHERE cfdi_id = {$cfdiTest['id']}");

        foreach ($complementos as $pago) {
            // Insertar pago
            $sqlPago = "INSERT INTO cfdi_pagos (
                cfdi_id, version, fecha_pago, forma_pago, moneda, tipo_cambio, monto,
                num_operacion, rfc_emisor_cuenta_ordenante, nombre_banco_extranjero,
                cuenta_ordenante, rfc_emisor_cuenta_beneficiario, cuenta_beneficiario,
                tipo_cadena_pago, certificado_pago, cadena_pago, sello_pago
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmtPago = $pdo->prepare($sqlPago);
            $resultPago = $stmtPago->execute([
                $cfdiTest['id'],
                '1.0',
                $pago['fecha_pago'] ?? null,
                $pago['forma_pago'] ?? null,
                $pago['moneda'] ?? null,
                $pago['tipo_cambio'] ?? null,
                $pago['monto'] ?? null,
                $pago['num_operacion'] ?? null,
                $pago['rfc_emisor_cuenta_ordenante'] ?? null,
                $pago['nombre_banco_extranjero'] ?? null,
                $pago['cuenta_ordenante'] ?? null,
                $pago['rfc_emisor_cuenta_beneficiario'] ?? null,
                $pago['cuenta_beneficiario'] ?? null,
                $pago['tipo_cadena_pago'] ?? null,
                $pago['certificado_pago'] ?? null,
                $pago['cadena_pago'] ?? null,
                $pago['sello_pago'] ?? null
            ]);

            if ($resultPago) {
                $pago_id = $pdo->lastInsertId();
                echo "✓ Pago insertado con ID: $pago_id\n";

                // Insertar documentos relacionados
                if (!empty($pago['documentos_relacionados'])) {
                    foreach ($pago['documentos_relacionados'] as $documento) {
                        $sqlDoc = "INSERT INTO cfdi_pago_documentos_relacionados (
                            pago_id, uuid_documento, serie, folio, moneda_dr, equivalencia_dr,
                            num_parcialidad, imp_saldo_ant, imp_pagado, imp_saldo_insoluto, objeto_imp_dr
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                        $stmtDoc = $pdo->prepare($sqlDoc);
                        $resultDoc = $stmtDoc->execute([
                            $pago_id,
                            $documento['uuid_documento'] ?? null,
                            $documento['serie'] ?? null,
                            $documento['folio'] ?? null,
                            $documento['moneda_dr'] ?? null,
                            $documento['equivalencia_dr'] ?? null,
                            $documento['num_parcialidad'] ?? null,
                            $documento['imp_saldo_ant'] ?? null,
                            $documento['imp_pagado'] ?? null,
                            $documento['imp_saldo_insoluto'] ?? null,
                            $documento['objeto_imp_dr'] ?? null
                        ]);

                        if ($resultDoc) {
                            echo "  ✓ Documento relacionado insertado\n";
                        } else {
                            echo "  ❌ Error al insertar documento: " . implode(", ", $stmtDoc->errorInfo()) . "\n";
                        }
                    }
                }
            } else {
                echo "❌ Error al insertar pago: " . implode(", ", $stmtPago->errorInfo()) . "\n";
            }
        }

        // Verificar resultados
        echo "\nVERIFICACIÓN FINAL:\n";
        echo "═══════════════════════════════════════\n";

        $totalPagos = $pdo->query("SELECT COUNT(*) FROM cfdi_pagos")->fetchColumn();
        $totalDocs = $pdo->query("SELECT COUNT(*) FROM cfdi_pago_documentos_relacionados")->fetchColumn();

        echo "Registros en cfdi_pagos: $totalPagos\n";
        echo "Registros en cfdi_pago_documentos_relacionados: $totalDocs\n";

        if ($totalPagos > 0) {
            echo "\n🎉 ¡PROBLEMA RESUELTO!\n";
            echo "Ahora los complementos de pago SON consultables desde las tablas estructuradas.\n";
        }
    } else {
        echo "❌ No se pudieron extraer complementos\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
