<?php

/**
 * DEBUG DE INSERCIÓN - ENCONTRAR EL PROBLEMA REAL
 * Este script ejecuta las mismas funciones pero muestra TODOS los errores
 */

require_once __DIR__ . '/src/config/database.php';

echo "🔍 DEBUG DE INSERCIÓN - BÚSQUEDA DEL PROBLEMA REAL\n";
echo str_repeat("=", 60) . "\n\n";

try {
    $pdo = getDatabase();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Buscar un archivo XML real para probar
    echo "📁 Buscando archivos XML reales...\n";

    $directorio = 'storage/sat_downloads';
    if (!is_dir($directorio)) {
        echo "❌ Error: No existe el directorio $directorio\n";
        exit(1);
    }

    $archivos = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directorio)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
            $archivos[] = $file->getPathname();
            if (count($archivos) >= 3) break; // Solo necesitamos 3 para probar
        }
    }

    if (empty($archivos)) {
        echo "❌ Error: No se encontraron archivos XML\n";
        exit(1);
    }

    echo "✅ Encontrados " . count($archivos) . " archivos XML para prueba\n\n";

    // Probar con cada archivo
    foreach ($archivos as $i => $archivo) {
        echo "📄 PROBANDO ARCHIVO " . ($i + 1) . ": " . basename($archivo) . "\n";
        echo str_repeat("-", 50) . "\n";

        try {
            // Leer XML
            $xmlContent = file_get_contents($archivo);
            if (!$xmlContent) {
                echo "❌ No se pudo leer el archivo\n\n";
                continue;
            }

            $xml = simplexml_load_string($xmlContent);
            if (!$xml) {
                echo "❌ Error al parsear XML\n\n";
                continue;
            }

            echo "✅ XML parseado correctamente\n";

            // Verificar conceptos
            if (!isset($xml->Conceptos->Concepto)) {
                echo "⚠️  Este XML no tiene conceptos\n\n";
                continue;
            }

            $numConceptos = count($xml->Conceptos->Concepto);
            echo "📊 Conceptos encontrados: $numConceptos\n";

            // Intentar insertar un CFDI de prueba
            $pdo->beginTransaction();

            echo "🔧 Insertando CFDI de prueba...\n";
            $stmt = $pdo->prepare("
                INSERT INTO cfdi (
                    uuid, tipo, rfc_emisor, nombre_emisor, rfc_receptor, nombre_receptor,
                    fecha, total, archivo_xml, rfc_consultado, direccion_flujo, version
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $comprobante = $xml->attributes();
            $test_uuid = 'DEBUG-' . uniqid();

            $stmt->execute([
                $test_uuid,
                (string)$comprobante->TipoDeComprobante ?? 'I',
                (string)$xml->Emisor['Rfc'] ?? 'TEST123456789',
                (string)$xml->Emisor['Nombre'] ?? 'EMPRESA DE PRUEBA',
                (string)$xml->Receptor['Rfc'] ?? 'RECEPTOR123456',
                (string)$xml->Receptor['Nombre'] ?? 'CLIENTE DE PRUEBA',
                date('Y-m-d H:i:s'),
                (float)($comprobante->Total ?? 1000.00),
                $archivo,
                'TEST123456789',
                'EMITIDA',
                '4.0'
            ]);

            $cfdi_id = $pdo->lastInsertId();
            echo "✅ CFDI insertado con ID: $cfdi_id\n";

            // Ahora probar insertar conceptos
            echo "🔧 Insertando conceptos...\n";

            $conceptos_insertados = 0;
            $errores_conceptos = [];

            foreach ($xml->Conceptos->Concepto as $j => $concepto) {
                try {
                    $attrs = $concepto->attributes();

                    echo "  📋 Concepto " . ($j + 1) . ": " . substr((string)$attrs->Descripcion, 0, 50) . "...\n";

                    // Detectar versión
                    $version = (string)($xml['Version'] ?? '3.3');
                    $esCFDI40 = version_compare($version, '4.0', '>=');

                    $stmt = $pdo->prepare("
                        INSERT INTO cfdi_conceptos (
                            cfdi_id, clave_prodserv, no_identificacion, cantidad, clave_unidad,
                            unidad, descripcion, valor_unitario, importe,
                            descuento, objeto_imp, cuenta_predial
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $cfdi_id,
                        (string)($attrs->ClaveProdServ ?? ''),
                        !empty($attrs->NoIdentificacion) ? (string)$attrs->NoIdentificacion : null,
                        (float)($attrs->Cantidad ?? 0.0),
                        (string)($attrs->ClaveUnidad ?? ''),
                        !empty($attrs->Unidad) ? (string)$attrs->Unidad : null,
                        (string)($attrs->Descripcion ?? ''),
                        (float)($attrs->ValorUnitario ?? 0.0),
                        (float)($attrs->Importe ?? 0.0),
                        (float)($attrs->Descuento ?? 0.0),
                        $esCFDI40 && !empty($attrs->ObjetoImp) ? (string)$attrs->ObjetoImp : null,
                        !empty($attrs->CuentaPredial) ? (string)$attrs->CuentaPredial : null
                    ]);

                    $concepto_id = $pdo->lastInsertId();
                    $conceptos_insertados++;
                    echo "    ✅ Concepto insertado con ID: $concepto_id\n";

                    // Probar impuestos del concepto
                    if (isset($concepto->Impuestos)) {
                        echo "    🔧 Insertando impuestos del concepto...\n";

                        // Traslados
                        if (isset($concepto->Impuestos->Traslados->Traslado)) {
                            foreach ($concepto->Impuestos->Traslados->Traslado as $traslado) {
                                $attrs_imp = $traslado->attributes();

                                $stmt = $pdo->prepare("
                                    INSERT INTO cfdi_impuestos (
                                        cfdi_id, concepto_id, tipo, impuesto, tipo_factor,
                                        tasa_cuota, base, importe
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ");

                                $stmt->execute([
                                    $cfdi_id,
                                    $concepto_id,
                                    'traslado',
                                    (string)($attrs_imp->Impuesto ?? ''),
                                    !empty($attrs_imp->TipoFactor) ? (string)$attrs_imp->TipoFactor : null,
                                    !empty($attrs_imp->TasaOCuota) ? (float)$attrs_imp->TasaOCuota : null,
                                    !empty($attrs_imp->Base) ? (float)$attrs_imp->Base : null,
                                    !empty($attrs_imp->Importe) ? (float)$attrs_imp->Importe : null
                                ]);

                                echo "      ✅ Impuesto traslado insertado\n";
                            }
                        }

                        // Retenciones
                        if (isset($concepto->Impuestos->Retenciones->Retencion)) {
                            foreach ($concepto->Impuestos->Retenciones->Retencion as $retencion) {
                                $attrs_imp = $retencion->attributes();

                                $stmt = $pdo->prepare("
                                    INSERT INTO cfdi_impuestos (
                                        cfdi_id, concepto_id, tipo, impuesto, tipo_factor,
                                        tasa_cuota, base, importe
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ");

                                $stmt->execute([
                                    $cfdi_id,
                                    $concepto_id,
                                    'retencion',
                                    (string)($attrs_imp->Impuesto ?? ''),
                                    !empty($attrs_imp->TipoFactor) ? (string)$attrs_imp->TipoFactor : null,
                                    !empty($attrs_imp->TasaOCuota) ? (float)$attrs_imp->TasaOCuota : null,
                                    !empty($attrs_imp->Base) ? (float)$attrs_imp->Base : null,
                                    !empty($attrs_imp->Importe) ? (float)$attrs_imp->Importe : null
                                ]);

                                echo "      ✅ Impuesto retención insertado\n";
                            }
                        }
                    } else {
                        echo "    ⚠️  Este concepto no tiene impuestos\n";
                    }
                } catch (PDOException $e) {
                    echo "    ❌ ERROR SQL en concepto: " . $e->getMessage() . "\n";
                    $errores_conceptos[] = $e->getMessage();
                } catch (Exception $e) {
                    echo "    ❌ ERROR general en concepto: " . $e->getMessage() . "\n";
                    $errores_conceptos[] = $e->getMessage();
                }

                if ($j >= 2) break; // Solo probar 3 conceptos por archivo
            }

            echo "📊 RESULTADO DEL ARCHIVO:\n";
            echo "  Conceptos procesados: " . ($j + 1) . "\n";
            echo "  Conceptos insertados: $conceptos_insertados\n";
            echo "  Errores: " . count($errores_conceptos) . "\n";

            if (!empty($errores_conceptos)) {
                echo "  🔴 ERRORES ENCONTRADOS:\n";
                foreach ($errores_conceptos as $error) {
                    echo "    • $error\n";
                }
            }

            // Verificar en base de datos
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cfdi_conceptos WHERE cfdi_id = ?");
            $stmt->execute([$cfdi_id]);
            $count_conceptos = $stmt->fetch()['count'];

            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cfdi_impuestos WHERE cfdi_id = ?");
            $stmt->execute([$cfdi_id]);
            $count_impuestos = $stmt->fetch()['count'];

            echo "🔍 VERIFICACIÓN EN BD:\n";
            echo "  Conceptos en BD: $count_conceptos\n";
            echo "  Impuestos en BD: $count_impuestos\n";

            // Limpiar datos de prueba
            $pdo->prepare("DELETE FROM cfdi_impuestos WHERE cfdi_id = ?")->execute([$cfdi_id]);
            $pdo->prepare("DELETE FROM cfdi_conceptos WHERE cfdi_id = ?")->execute([$cfdi_id]);
            $pdo->prepare("DELETE FROM cfdi WHERE id = ?")->execute([$cfdi_id]);

            $pdo->commit();
            echo "🧹 Datos de prueba limpiados\n\n";
        } catch (Exception $e) {
            $pdo->rollback();
            echo "❌ ERROR CRÍTICO: " . $e->getMessage() . "\n\n";
        }
    }

    echo "✅ DEBUG COMPLETADO\n";
} catch (Exception $e) {
    echo "❌ ERROR FATAL: " . $e->getMessage() . "\n";
}
