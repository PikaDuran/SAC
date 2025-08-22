<?php
require_once 'src/config/database.php';
require_once 'procesador_cfdi_completo.php';

echo "=== PRUEBA DE INSERCIÓN REAL DE PAGOS ===\n\n";

// Buscar un CFDI tipo P real en la base de datos
try {
    $pdo = getDatabase();

    echo "📊 Consultando CFDIs tipo P en la base de datos...\n";
    $stmt = $pdo->prepare("SELECT id, uuid, archivo_xml FROM cfdi WHERE tipo = 'P' LIMIT 1");
    $stmt->execute();
    $cfdi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cfdi) {
        echo "❌ No se encontró ningún CFDI tipo P en la base de datos\n";
        exit;
    }

    echo "✅ CFDI encontrado:\n";
    echo "   ID: " . $cfdi['id'] . "\n";
    echo "   UUID: " . $cfdi['uuid'] . "\n";
    echo "   Archivo: " . $cfdi['archivo_xml'] . "\n\n";

    // Buscar si ya tiene pagos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cfdi_pagos WHERE cfdi_id = ?");
    $stmt->execute([$cfdi['id']]);
    $countPagos = $stmt->fetchColumn();

    echo "💰 Pagos existentes en BD: $countPagos\n\n";

    // Buscar el archivo XML
    $archivos = [
        $cfdi['archivo_xml'],
        'storage/sat_downloads/' . $cfdi['archivo_xml']
    ];

    $archivoXml = null;
    foreach ($archivos as $archivo) {
        if (file_exists($archivo)) {
            $archivoXml = $archivo;
            break;
        }
    }

    if (!$archivoXml) {
        // Buscar por UUID
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('storage/sat_downloads'));
        foreach ($iterator as $archivo) {
            if ($archivo->isFile() && strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) === 'xml') {
                if (strpos(basename($archivo), str_replace('-', '', $cfdi['uuid'])) !== false) {
                    $archivoXml = $archivo->getPathname();
                    break;
                }
            }
        }
    }

    if (!$archivoXml) {
        echo "❌ No se pudo encontrar el archivo XML\n";
        exit;
    }

    echo "📄 Archivo XML encontrado: " . basename($archivoXml) . "\n\n";

    // Leer el contenido del XML
    $contenido = file_get_contents($archivoXml);

    // Crear instancia del procesador
    $procesador = new ProcesadorCFDICompleto($pdo);

    // Usar reflexión para acceder al método privado
    $reflection = new ReflectionClass($procesador);
    $metodoExtraer = $reflection->getMethod('extraerComplementoPagos');
    $metodoExtraer->setAccessible(true);

    $metodoInsertar = $reflection->getMethod('insertarPago');
    $metodoInsertar->setAccessible(true);

    echo "🔍 Extrayendo complemento de pagos...\n";
    $pagos = $metodoExtraer->invoke($procesador, $contenido);

    if (!$pagos) {
        echo "❌ No se encontraron pagos en el XML\n";
        exit;
    }

    echo "✅ Se extrajeron " . count($pagos) . " pagos\n\n";

    foreach ($pagos as $i => $pago) {
        echo "--- PAGO #" . ($i + 1) . " ---\n";
        echo "Datos extraídos:\n";
        foreach ($pago as $key => $value) {
            if ($key !== 'documentos_relacionados') {
                echo "  $key: " . var_export($value, true) . "\n";
            }
        }

        echo "\n🔄 Intentando insertar en la base de datos...\n";

        try {
            $pagoId = $metodoInsertar->invoke($procesador, $cfdi['id'], $pago);

            if ($pagoId) {
                echo "✅ PAGO INSERTADO EXITOSAMENTE con ID: $pagoId\n";

                // Verificar que se insertó correctamente
                $stmt = $pdo->prepare("SELECT * FROM cfdi_pagos WHERE id = ?");
                $stmt->execute([$pagoId]);
                $pagoInsertado = $stmt->fetch(PDO::FETCH_ASSOC);

                echo "\n📊 Datos insertados en la BD:\n";
                foreach ($pagoInsertado as $campo => $valor) {
                    echo "  $campo: " . var_export($valor, true) . "\n";
                }
            } else {
                echo "❌ ERROR: insertarPago devolvió FALSE\n";
            }
        } catch (Exception $e) {
            echo "❌ ERROR SQL: " . $e->getMessage() . "\n";
        }

        echo "\n" . str_repeat("-", 50) . "\n\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
