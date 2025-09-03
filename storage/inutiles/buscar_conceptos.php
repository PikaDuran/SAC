<?php

/**
 * BUSCAR XMLs CON CONCEPTOS
 */

require_once __DIR__ . '/src/config/database.php';

echo "🔍 BUSCANDO XMLs QUE TENGAN CONCEPTOS\n";
echo str_repeat("=", 50) . "\n\n";

$directorio = 'storage/sat_downloads';
$archivos_con_conceptos = [];
$archivos_sin_conceptos = [];
$archivos_procesados = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directorio)
);

foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
        $archivos_procesados++;

        if ($archivos_procesados % 100 == 0) {
            echo "📊 Procesados: $archivos_procesados archivos...\n";
        }

        $xmlContent = file_get_contents($file->getPathname());
        if ($xmlContent) {
            $xml = simplexml_load_string($xmlContent);
            if ($xml) {
                $tipo = (string)$xml['TipoDeComprobante'] ?? '';

                if (isset($xml->Conceptos->Concepto)) {
                    $archivos_con_conceptos[] = [
                        'archivo' => $file->getPathname(),
                        'tipo' => $tipo,
                        'conceptos' => count($xml->Conceptos->Concepto)
                    ];
                } else {
                    $archivos_sin_conceptos[] = [
                        'archivo' => $file->getPathname(),
                        'tipo' => $tipo
                    ];
                }
            }
        }

        // Parar después de revisar 1000 archivos para tener una muestra
        if ($archivos_procesados >= 1000) break;
    }
}

echo "\n📊 RESULTADOS DEL ANÁLISIS:\n";
echo "Total archivos procesados: $archivos_procesados\n";
echo "Archivos CON conceptos: " . count($archivos_con_conceptos) . "\n";
echo "Archivos SIN conceptos: " . count($archivos_sin_conceptos) . "\n\n";

if (!empty($archivos_con_conceptos)) {
    echo "✅ ARCHIVOS CON CONCEPTOS ENCONTRADOS:\n";
    foreach (array_slice($archivos_con_conceptos, 0, 10) as $archivo) {
        echo "  📄 " . basename($archivo['archivo']) . " (Tipo: {$archivo['tipo']}, Conceptos: {$archivo['conceptos']})\n";
    }
} else {
    echo "❌ NO SE ENCONTRARON ARCHIVOS CON CONCEPTOS\n";
}

echo "\n";

if (!empty($archivos_sin_conceptos)) {
    echo "⚠️  MUESTRA DE ARCHIVOS SIN CONCEPTOS:\n";
    $tipos_sin_conceptos = [];
    foreach ($archivos_sin_conceptos as $archivo) {
        if (!isset($tipos_sin_conceptos[$archivo['tipo']])) {
            $tipos_sin_conceptos[$archivo['tipo']] = 0;
        }
        $tipos_sin_conceptos[$archivo['tipo']]++;
    }

    foreach ($tipos_sin_conceptos as $tipo => $cantidad) {
        echo "  📋 Tipo $tipo: $cantidad archivos\n";
    }
}

// Si encontramos archivos con conceptos, probar con uno
if (!empty($archivos_con_conceptos)) {
    echo "\n🔧 PROBANDO INSERCIÓN CON UN ARCHIVO QUE SÍ TIENE CONCEPTOS...\n";

    $archivo_prueba = $archivos_con_conceptos[0];
    echo "📄 Usando: " . basename($archivo_prueba['archivo']) . "\n";

    try {
        $pdo = getDatabase();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $xmlContent = file_get_contents($archivo_prueba['archivo']);
        $xml = simplexml_load_string($xmlContent);

        $pdo->beginTransaction();

        // Insertar CFDI de prueba
        $stmt = $pdo->prepare("
            INSERT INTO cfdi (
                uuid, tipo, rfc_emisor, nombre_emisor, rfc_receptor, nombre_receptor,
                fecha, total, archivo_xml, rfc_consultado, direccion_flujo, version
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $comprobante = $xml->attributes();
        $test_uuid = 'DEBUG-CONCEPTOS-' . uniqid();

        $stmt->execute([
            $test_uuid,
            (string)$comprobante->TipoDeComprobante ?? 'I',
            (string)$xml->Emisor['Rfc'] ?? 'TEST123456789',
            (string)$xml->Emisor['Nombre'] ?? 'EMPRESA DE PRUEBA',
            (string)$xml->Receptor['Rfc'] ?? 'RECEPTOR123456',
            (string)$xml->Receptor['Nombre'] ?? 'CLIENTE DE PRUEBA',
            date('Y-m-d H:i:s'),
            (float)($comprobante->Total ?? 1000.00),
            $archivo_prueba['archivo'],
            'TEST123456789',
            'EMITIDA',
            '4.0'
        ]);

        $cfdi_id = $pdo->lastInsertId();
        echo "✅ CFDI insertado con ID: $cfdi_id\n";

        // Insertar conceptos
        $conceptos_insertados = 0;
        $impuestos_insertados = 0;

        foreach ($xml->Conceptos->Concepto as $concepto) {
            $attrs = $concepto->attributes();

            // Insertar concepto
            $stmt = $pdo->prepare("
                INSERT INTO cfdi_conceptos (
                    cfdi_id, clave_prodserv, no_identificacion, cantidad, clave_unidad,
                    unidad, descripcion, valor_unitario, importe,
                    descuento, objeto_imp, cuenta_predial
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $version = (string)($xml['Version'] ?? '3.3');
            $esCFDI40 = version_compare($version, '4.0', '>=');

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

            echo "  ✅ Concepto insertado: " . substr((string)$attrs->Descripcion, 0, 50) . "...\n";

            // Insertar impuestos del concepto
            if (isset($concepto->Impuestos)) {
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

                        $impuestos_insertados++;
                        echo "    ✅ Impuesto traslado insertado\n";
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

                        $impuestos_insertados++;
                        echo "    ✅ Impuesto retención insertado\n";
                    }
                }
            }
        }

        echo "\n📊 RESULTADO FINAL:\n";
        echo "Conceptos insertados: $conceptos_insertados\n";
        echo "Impuestos insertados: $impuestos_insertados\n";

        // Verificar en BD
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cfdi_conceptos WHERE cfdi_id = ?");
        $stmt->execute([$cfdi_id]);
        $count_conceptos = $stmt->fetch()['count'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cfdi_impuestos WHERE cfdi_id = ?");
        $stmt->execute([$cfdi_id]);
        $count_impuestos = $stmt->fetch()['count'];

        echo "Verificación BD - Conceptos: $count_conceptos\n";
        echo "Verificación BD - Impuestos: $count_impuestos\n";

        if ($count_conceptos > 0 && $count_impuestos > 0) {
            echo "\n🎉 ¡INSERCIÓN EXITOSA! El código funciona correctamente.\n";
        } else {
            echo "\n❌ Problema en la inserción\n";
        }

        // Limpiar
        $pdo->prepare("DELETE FROM cfdi_impuestos WHERE cfdi_id = ?")->execute([$cfdi_id]);
        $pdo->prepare("DELETE FROM cfdi_conceptos WHERE cfdi_id = ?")->execute([$cfdi_id]);
        $pdo->prepare("DELETE FROM cfdi WHERE id = ?")->execute([$cfdi_id]);

        $pdo->commit();
        echo "🧹 Datos de prueba limpiados\n";
    } catch (Exception $e) {
        $pdo->rollback();
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
}
