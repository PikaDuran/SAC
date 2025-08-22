<?php

require_once 'procesador_cfdi_completo.php';

echo "=== DIAGNÓSTICO DE EXTRACCIÓN DE PAGOS ===\n\n";

try {
    $pdo = getDatabase();
    $procesador = new ProcesadorCFDICompleto($pdo);

    // Buscar archivos tipo P desde la base de datos
    echo "📊 Buscando CFDIs tipo P en la base de datos...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM cfdi WHERE tipo = 'P'");
    $cfdiTipoP = $stmt->fetchColumn();
    echo "   CFDIs tipo P en BD: $cfdiTipoP\n";

    // Verificar complementos de pago
    $stmt = $pdo->query("SELECT COUNT(*) FROM cfdi_pagos");
    $pagosEnBD = $stmt->fetchColumn();
    echo "   Registros en cfdi_pagos: $pagosEnBD\n\n";

    if ($cfdiTipoP > 0 && $pagosEnBD == 0) {
        echo "❌ PROBLEMA: Hay CFDIs tipo P pero no hay registros en cfdi_pagos\n\n";
    }

    // Buscar un archivo XML tipo P para analizar
    echo "🔍 Buscando archivos XML tipo P...\n";
    $archivos = glob('storage/sat_downloads/*/*/*/*/*.xml');
    $encontrado = false;

    foreach (array_slice($archivos, 0, 1000) as $archivo) {
        if ($encontrado) break;

        $contenido = file_get_contents($archivo);
        if (preg_match('/TipoDeComprobante\s*=\s*["\']P["\']/', $contenido)) {
            echo "✅ Archivo tipo P encontrado: " . basename($archivo) . "\n\n";
            $encontrado = true;

            // Usar reflection para acceder al método privado
            $reflection = new ReflectionClass($procesador);
            $method = $reflection->getMethod('extraerComplementoPagos');
            $method->setAccessible(true);

            echo "📄 Analizando complemento de pagos...\n";
            $complementoPagos = $method->invoke($procesador, $contenido);

            if ($complementoPagos) {
                echo "✅ Se extrajeron " . count($complementoPagos) . " pagos:\n";
                foreach ($complementoPagos as $i => $pago) {
                    echo "\n   🔸 PAGO #" . ($i + 1) . ":\n";
                    foreach ($pago as $campo => $valor) {
                        if ($campo !== 'documentos_relacionados') {
                            echo "     • $campo: " . ($valor ?: '❌ NULL') . "\n";
                        }
                    }
                    if (isset($pago['documentos_relacionados'])) {
                        echo "     • documentos_relacionados: " . count($pago['documentos_relacionados']) . " documentos\n";
                    }
                }
            } else {
                echo "❌ No se extrajeron datos de pagos\n";
            }

            // Mostrar fragmento del XML
            echo "\n📋 Fragmento del complemento XML:\n";
            if (preg_match('/<pago10:Pagos[^>]*>.*?<\/pago10:Pagos>/s', $contenido, $matches)) {
                $xmlCompleto = $matches[0];
                echo "   Tamaño del complemento: " . strlen($xmlCompleto) . " caracteres\n";
                echo "   XML completo:\n";
                echo "   " . htmlspecialchars($xmlCompleto) . "\n\n";

                // Buscar el primer pago
                if (preg_match('/<pago10:Pago[^>]*>/', $xmlCompleto, $pagoMatch)) {
                    echo "   ✅ Primer elemento Pago encontrado:\n";
                    echo "   " . htmlspecialchars($pagoMatch[0]) . "\n";
                } else {
                    echo "   ❌ No se encontró elemento <pago10:Pago>\n";

                    // Buscar variantes
                    if (preg_match('/<[^:]+:Pago[^>]*>/', $xmlCompleto, $pagoMatch2)) {
                        echo "   🔍 Encontrado elemento Pago con namespace diferente:\n";
                        echo "   " . htmlspecialchars($pagoMatch2[0]) . "\n";
                    }
                }
            } else {
                echo "   ❌ No se encontró complemento <pago10:Pagos>\n";

                // Buscar otros namespaces
                if (preg_match('/<[^:]*:Pagos[^>]*>.*?<\/[^:]*:Pagos>/s', $contenido, $matches2)) {
                    echo "   🔍 Encontrado complemento con namespace diferente:\n";
                    echo "   " . htmlspecialchars(substr($matches2[0], 0, 200)) . "...\n";
                }
            }
        }
    }

    if (!$encontrado) {
        echo "❌ No se encontraron archivos CFDI tipo P en la muestra\n";
    }

    // Verificar un registro existente en cfdi_pagos si existe
    echo "\n📊 Verificando registros existentes en cfdi_pagos...\n";
    $stmt = $pdo->query("SELECT * FROM cfdi_pagos LIMIT 1");
    $pago = $stmt->fetch();

    if ($pago) {
        echo "✅ Registro encontrado en cfdi_pagos:\n";
        foreach ($pago as $campo => $valor) {
            echo "   • $campo: " . ($valor ?: '❌ NULL') . "\n";
        }
    } else {
        echo "❌ No hay registros en cfdi_pagos\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "   Línea: " . $e->getLine() . "\n";
    echo "   Archivo: " . $e->getFile() . "\n";
}
