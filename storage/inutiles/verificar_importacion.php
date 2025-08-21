<?php

/**
 * Script para verificar la correcta importación de CFDIs
 */

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VERIFICACIÓN DE DATOS IMPORTADOS ===\n\n";

    // Verificar CFDIs
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi');
    $cfdi_count = $stmt->fetch()['total'];
    echo "📊 CFDIs importados: {$cfdi_count}\n";

    // Verificar algunos CFDIs de muestra
    $stmt = $pdo->query('SELECT uuid, version, rfc_emisor, rfc_receptor, total, fecha FROM cfdi LIMIT 5');
    echo "\n🔍 MUESTRA DE CFDIs:\n";
    while ($row = $stmt->fetch()) {
        echo "- UUID: {$row['uuid']}\n";
        echo "  Versión: {$row['version']} | Emisor: {$row['rfc_emisor']} | Receptor: {$row['rfc_receptor']}\n";
        echo "  Total: {$row['total']} | Fecha: {$row['fecha']}\n\n";
    }

    // Verificar timbres fiscales
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi_timbre_fiscal');
    $timbre_count = $stmt->fetch()['total'];
    echo "🏷️ Timbres fiscales: {$timbre_count}\n";

    // Verificar complementos de pago
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi_pagos');
    $pagos_count = $stmt->fetch()['total'];
    echo "💰 Complementos de pago: {$pagos_count}\n";

    // Verificar auditoría
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi_auditoria');
    $auditoria_count = $stmt->fetch()['total'];
    echo "📋 Registros de auditoría: {$auditoria_count}\n";

    // Verificar distribución por versión
    $stmt = $pdo->query('SELECT version, COUNT(*) as cantidad FROM cfdi GROUP BY version');
    echo "\n📈 DISTRIBUCIÓN POR VERSIÓN:\n";
    while ($row = $stmt->fetch()) {
        echo "- CFDI {$row['version']}: {$row['cantidad']} registros\n";
    }

    // Verificar campos importantes no nulos
    $stmt = $pdo->query('SELECT 
        COUNT(*) as total,
        COUNT(uuid) as con_uuid,
        COUNT(rfc_emisor) as con_emisor,
        COUNT(rfc_receptor) as con_receptor,
        COUNT(total) as con_total,
        COUNT(sello_cfd) as con_sello
        FROM cfdi');
    $row = $stmt->fetch();
    echo "\n🔍 VERIFICACIÓN DE CAMPOS CLAVE:\n";
    echo "- Total registros: {$row['total']}\n";
    echo "- Con UUID: {$row['con_uuid']}\n";
    echo "- Con RFC Emisor: {$row['con_emisor']}\n";
    echo "- Con RFC Receptor: {$row['con_receptor']}\n";
    echo "- Con Total: {$row['con_total']}\n";
    echo "- Con Sello CFD: {$row['con_sello']}\n";

    // Verificar si hay UUIDs duplicados
    $stmt = $pdo->query('SELECT uuid, COUNT(*) as cantidad FROM cfdi GROUP BY uuid HAVING cantidad > 1 LIMIT 5');
    $duplicados = $stmt->fetchAll();
    if ($duplicados) {
        echo "\n⚠️ UUIDs DUPLICADOS ENCONTRADOS:\n";
        foreach ($duplicados as $dup) {
            echo "- UUID: {$dup['uuid']} aparece {$dup['cantidad']} veces\n";
        }
    } else {
        echo "\n✅ No hay UUIDs duplicados\n";
    }

    // Verificar muestra de timbres fiscales
    if ($timbre_count > 0) {
        $stmt = $pdo->query('SELECT version, uuid, fecha_timbrado, rfc_prov_certif FROM cfdi_timbre_fiscal LIMIT 3');
        echo "\n🏷️ MUESTRA DE TIMBRES FISCALES:\n";
        while ($row = $stmt->fetch()) {
            echo "- Versión: {$row['version']} | UUID: {$row['uuid']}\n";
            echo "  Fecha: {$row['fecha_timbrado']} | Proveedor: {$row['rfc_prov_certif']}\n\n";
        }
    }

    echo "\n============================================================\n";
    echo "RESUMEN DE VERIFICACIÓN:\n";
    echo "============================================================\n";
    echo "CFDIs: {$cfdi_count}\n";
    echo "Timbres: {$timbre_count}\n";
    echo "Pagos: {$pagos_count}\n";
    echo "Auditoría: {$auditoria_count}\n";

    if ($cfdi_count == $timbre_count) {
        echo "✅ Relación CFDI-Timbre: CORRECTA (1:1)\n";
    } else {
        echo "⚠️ Relación CFDI-Timbre: INCONSISTENTE\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
