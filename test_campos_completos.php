<?php

// Test de campos específicos del procesador
require_once 'procesador_cfdi_completo.php';

echo "=== VERIFICANDO EXTRACCIÓN DE CAMPOS COMPLETOS ===\n\n";

try {
    $pdo = getDatabase();
    echo "✓ Conexión a base de datos establecida\n";

    $procesador = new ProcesadorCFDICompleto($pdo);
    echo "✓ Procesador inicializado\n\n";

    // Limpiar solo algunos registros para la prueba
    echo "Preparando prueba (limpiando algunos registros)...\n";
    $pdo->exec("DELETE FROM cfdi_pago_documentos_relacionados WHERE pago_id IN (SELECT id FROM cfdi_pagos WHERE cfdi_id IN (SELECT id FROM cfdi LIMIT 10))");
    $pdo->exec("DELETE FROM cfdi_pagos WHERE cfdi_id IN (SELECT id FROM cfdi LIMIT 10)");
    $pdo->exec("DELETE FROM cfdi_impuestos WHERE cfdi_id IN (SELECT id FROM cfdi LIMIT 10)");
    $pdo->exec("DELETE FROM cfdi_conceptos WHERE cfdi_id IN (SELECT id FROM cfdi LIMIT 10)");
    $pdo->exec("DELETE FROM cfdi_timbre_fiscal WHERE cfdi_id IN (SELECT id FROM cfdi LIMIT 10)");
    $pdo->exec("DELETE FROM cfdi WHERE id IN (SELECT id FROM (SELECT id FROM cfdi LIMIT 10) AS temp)");
    echo "✓ Limpieza completada\n\n";

    // Crear una versión limitada del procesador para solo procesar 10 archivos
    echo "Procesando 10 archivos para verificación de campos...\n";
    echo "===================================================\n";

    $directorio = 'storage/sat_downloads';
    $archivos = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio));

    $contador = 0;
    foreach ($iterator as $archivo) {
        if ($archivo->isFile() && strtolower($archivo->getExtension()) === 'xml') {
            $ruta_completa = $archivo->getPathname();
            $contenido = file_get_contents($ruta_completa);

            if ($contenido && strlen($contenido) > 1000) {
                echo "Procesando: " . basename($ruta_completa) . "\n";

                // Llamar al método interno del procesador
                $reflection = new ReflectionClass($procesador);
                $metodo = $reflection->getMethod('procesarArchivo');
                $metodo->setAccessible(true);
                $metodo->invoke($procesador, $ruta_completa);

                $contador++;
                if ($contador >= 10) break;
            }
        }
    }

    echo "\n=== VERIFICANDO CAMPOS EN BASE DE DATOS ===\n";

    // Verificar campos insertados
    $query = "SELECT 
        uuid, tipo, version, serie, folio, fecha,
        rfc_emisor, nombre_emisor, regimen_fiscal_receptor,
        observaciones, complemento_tipo, complemento_json,
        rfc_consultado, direccion_flujo, sello_cfd,
        no_certificado_sat, rfc_prov_certif, estatus_sat,
        no_certificado, condiciones_de_pago
    FROM cfdi 
    ORDER BY id DESC 
    LIMIT 5";

    $result = $pdo->query($query);
    $registros = $result->fetchAll();

    foreach ($registros as $registro) {
        echo "\n--- CFDI: " . $registro['uuid'] . " ---\n";
        echo "Tipo: " . ($registro['tipo'] ?: 'NULL') . "\n";
        echo "Versión: " . ($registro['version'] ?: 'NULL') . "\n";
        echo "Serie: " . ($registro['serie'] ?: 'NULL') . "\n";
        echo "Folio: " . ($registro['folio'] ?: 'NULL') . "\n";
        echo "RFC Emisor: " . ($registro['rfc_emisor'] ?: 'NULL') . "\n";
        echo "Regimen Fiscal Receptor: " . ($registro['regimen_fiscal_receptor'] ?: 'NULL') . "\n";
        echo "Observaciones: " . ($registro['observaciones'] ?: 'NULL') . "\n";
        echo "Complemento Tipo: " . ($registro['complemento_tipo'] ?: 'NULL') . "\n";
        echo "RFC Consultado: " . ($registro['rfc_consultado'] ?: 'NULL') . "\n";
        echo "Dirección Flujo: " . ($registro['direccion_flujo'] ?: 'NULL') . "\n";
        echo "Sello CFD: " . (strlen($registro['sello_cfd'] ?: '') > 0 ? 'SÍ (' . strlen($registro['sello_cfd']) . ' chars)' : 'NULL') . "\n";
        echo "No Certificado SAT: " . ($registro['no_certificado_sat'] ?: 'NULL') . "\n";
        echo "RFC Prov Certif: " . ($registro['rfc_prov_certif'] ?: 'NULL') . "\n";
        echo "Estatus SAT: " . ($registro['estatus_sat'] ?: 'NULL') . "\n";
        echo "No Certificado: " . ($registro['no_certificado'] ?: 'NULL') . "\n";
        echo "Condiciones de Pago: " . ($registro['condiciones_de_pago'] ?: 'NULL') . "\n";

        if ($registro['complemento_json']) {
            echo "Complemento JSON: SÍ (" . strlen($registro['complemento_json']) . " chars)\n";
        } else {
            echo "Complemento JSON: NULL\n";
        }
    }

    // Verificar también tabla de pagos
    echo "\n=== VERIFICANDO TABLA CFDI_PAGOS ===\n";
    $queryPagos = "SELECT 
        cfdi_id, version, fecha_pago, forma_pago, moneda, 
        monto, num_operacion, rfc_emisor_cuenta_ordenante
    FROM cfdi_pagos 
    ORDER BY id DESC 
    LIMIT 3";

    $resultPagos = $pdo->query($queryPagos);
    $pagos = $resultPagos->fetchAll();

    if (count($pagos) > 0) {
        foreach ($pagos as $pago) {
            echo "\n--- PAGO para CFDI ID: " . $pago['cfdi_id'] . " ---\n";
            echo "Versión: " . ($pago['version'] ?: 'NULL') . "\n";
            echo "Fecha Pago: " . ($pago['fecha_pago'] ?: 'NULL') . "\n";
            echo "Forma Pago: " . ($pago['forma_pago'] ?: 'NULL') . "\n";
            echo "Moneda: " . ($pago['moneda'] ?: 'NULL') . "\n";
            echo "Monto: " . ($pago['monto'] ?: 'NULL') . "\n";
            echo "Num Operación: " . ($pago['num_operacion'] ?: 'NULL') . "\n";
            echo "RFC Emisor Cuenta: " . ($pago['rfc_emisor_cuenta_ordenante'] ?: 'NULL') . "\n";
        }
    } else {
        echo "No se encontraron registros de pagos en esta muestra.\n";
    }

    echo "\n✅ VERIFICACIÓN COMPLETADA\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}
