<?php

/**
 * Depuración específica de complementos de pago
 * Analiza por qué el importador no está insertando datos en las tablas
 */

require_once 'src/config/database.php';

try {
    $pdo = getDatabase();
    echo "=== DEPURACIÓN COMPLEMENTOS DE PAGO ===\n\n";

    // Obtener un CFDI de pago con complemento_json
    $cfdi = $pdo->query("
        SELECT id, uuid, archivo_xml, complemento_json, tipo 
        FROM cfdi 
        WHERE tipo = 'P' 
        AND complemento_json IS NOT NULL 
        AND complemento_json != '[]' 
        AND complemento_json != ''
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$cfdi) {
        echo "❌ No se encontró ningún CFDI de pago con JSON\n";
        exit;
    }

    echo "✅ CFDI encontrado:\n";
    echo "   ID: {$cfdi['id']}\n";
    echo "   UUID: {$cfdi['uuid']}\n";
    echo "   Tipo: {$cfdi['tipo']}\n";
    echo "   JSON length: " . strlen($cfdi['complemento_json']) . " bytes\n";

    // Leer el archivo XML completo
    $rutaArchivo = $cfdi['archivo_xml'];
    if (!file_exists($rutaArchivo)) {
        echo "❌ Archivo XML no existe: $rutaArchivo\n";
        exit;
    }

    $contenidoXML = file_get_contents($rutaArchivo);
    echo "   XML length: " . strlen($contenidoXML) . " bytes\n";

    // Verificar que sea tipo P
    echo "\n1. VERIFICACIÓN TIPO COMPROBANTE:\n";
    echo "════════════════════════════════════\n";

    if (preg_match('/TipoDeComprobante\s*=\s*["\']([^"\']+)["\']/', $contenidoXML, $matches)) {
        echo "✅ TipoDeComprobante encontrado: '{$matches[1]}'\n";
    } else {
        echo "❌ TipoDeComprobante NO encontrado\n";
    }

    // Buscar complemento de pagos
    echo "\n2. BÚSQUEDA DE COMPLEMENTO PAGOS:\n";
    echo "════════════════════════════════════\n";

    $namespaces = ['pago10', 'pago20', 'pago']; // Diferentes versiones posibles
    $encontrado = false;

    foreach ($namespaces as $ns) {
        if (preg_match("/<{$ns}:Pagos[^>]*>/", $contenidoXML)) {
            echo "✅ Namespace '{$ns}:Pagos' encontrado\n";
            $encontrado = true;

            // Extraer el bloque completo
            if (preg_match("/<{$ns}:Pagos[^>]*>.*?<\/{$ns}:Pagos>/s", $contenidoXML, $matches)) {
                $bloqueCompleto = $matches[0];
                echo "   Bloque length: " . strlen($bloqueCompleto) . " bytes\n";

                // Buscar pagos individuales
                if (preg_match_all("/<{$ns}:Pago[^>]*>.*?<\/{$ns}:Pago>/s", $bloqueCompleto, $pagosMatches)) {
                    echo "   Pagos encontrados: " . count($pagosMatches[0]) . "\n";

                    // Analizar primer pago
                    if (isset($pagosMatches[0][0])) {
                        $primerPago = $pagosMatches[0][0];
                        echo "   Primer pago length: " . strlen($primerPago) . " bytes\n";

                        // Extraer algunos campos del primer pago
                        $campos = ['FechaPago', 'FormaDePagoP', 'MonedaP', 'Monto'];
                        foreach ($campos as $campo) {
                            if (preg_match("/{$campo}\\s*=\\s*[\"']([^\"']*)[\"']/", $primerPago, $campoMatch)) {
                                echo "   {$campo}: '{$campoMatch[1]}'\n";
                            }
                        }

                        // Buscar documentos relacionados
                        if (preg_match_all("/<{$ns}:DoctoRelacionado[^>]*\\/>/", $primerPago, $docsMatches)) {
                            echo "   Documentos relacionados: " . count($docsMatches[0]) . "\n";
                        }
                    }
                }
            }
            break;
        }
    }

    if (!$encontrado) {
        echo "❌ NO se encontró ningún namespace de pagos\n";
        echo "   Buscando cualquier etiqueta que contenga 'Pago':\n";
        if (preg_match_all('/<[^>]*[Pp]ago[^>]*>/', $contenidoXML, $matches)) {
            foreach (array_slice($matches[0], 0, 5) as $match) {
                echo "   - $match\n";
            }
        }
    }

    // Probar el método extraerComplementoPagos actual
    echo "\n3. PRUEBA DEL MÉTODO ACTUAL:\n";
    echo "════════════════════════════════════\n";

    // Incluir el importador y probar el método
    require_once 'importador_inteligente_cfdi.php';

    $importador = new ImportadorInteligenteCFDI();

    // Usar reflexión para acceder al método privado
    $reflexion = new ReflectionClass($importador);
    $metodo = $reflexion->getMethod('extraerComplementoPagos');
    $metodo->setAccessible(true);

    $resultado = $metodo->invoke($importador, $contenidoXML);

    if ($resultado) {
        echo "✅ Método extraerComplementoPagos FUNCIONA\n";
        echo "   Pagos extraídos: " . count($resultado) . "\n";

        foreach ($resultado as $i => $pago) {
            echo "   Pago $i:\n";
            foreach ($pago as $campo => $valor) {
                if ($campo !== 'documentos_relacionados') {
                    echo "     {$campo}: {$valor}\n";
                }
            }
            if (isset($pago['documentos_relacionados'])) {
                echo "     documentos_relacionados: " . count($pago['documentos_relacionados']) . "\n";
            }
        }
    } else {
        echo "❌ Método extraerComplementoPagos NO funciona\n";
    }

    // Verificar si ya hay registros para este CFDI en las tablas
    echo "\n4. VERIFICACIÓN EN TABLAS:\n";
    echo "════════════════════════════════════\n";

    $pagosExistentes = $pdo->query("SELECT COUNT(*) FROM cfdi_pagos WHERE cfdi_id = {$cfdi['id']}")->fetchColumn();
    $docsExistentes = $pdo->query("SELECT COUNT(*) FROM cfdi_pago_documentos_relacionados WHERE pago_id IN (SELECT id FROM cfdi_pagos WHERE cfdi_id = {$cfdi['id']})")->fetchColumn();

    echo "Pagos en tabla: $pagosExistentes\n";
    echo "Documentos en tabla: $docsExistentes\n";

    if ($pagosExistentes == 0 && $resultado) {
        echo "\n💡 PROBLEMA IDENTIFICADO:\n";
        echo "   - El método SÍ extrae los datos correctamente\n";
        echo "   - PERO no se están insertando en las tablas\n";
        echo "   - Verificar que insertarPago() se esté ejecutando\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
