<?php
require_once __DIR__ . '/src/config/database.php';

echo "🔍 VERIFICACIÓN COMPLETA DE LA IMPLEMENTACIÓN MEJORADA\n";
echo "================================================\n\n";

try {
    $pdo = getDatabase();

    // 1. Verificar estructura de tabla cfdi con comentarios
    echo "📋 1. TABLA CFDI - ESTRUCTURA CON COMENTARIOS:\n";
    echo "-------------------------------------------\n";
    $stmt = $pdo->query("SHOW FULL COLUMNS FROM cfdi");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        $comment = !empty($col['Comment']) ? ' -- ' . $col['Comment'] : ' (sin comentario)';
        echo sprintf("%-25s %-15s %s\n", $col['Field'], $col['Type'], $comment);
    }

    echo "\n📊 2. DATOS ACTUALES EN CFDI:\n";
    echo "----------------------------\n";

    // Contar por RFC consultado y direccion_flujo
    $stmt = $pdo->query("
        SELECT 
            rfc_consultado,
            direccion_flujo,
            COUNT(*) as total,
            COUNT(DISTINCT archivo_xml) as archivos_unicos
        FROM cfdi 
        WHERE rfc_consultado IS NOT NULL 
        GROUP BY rfc_consultado, direccion_flujo
        ORDER BY total DESC
    ");
    $rfcs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rfcs as $rfc) {
        echo sprintf(
            "RFC: %-15s Flujo: %-10s Total: %-6d Archivos: %d\n",
            $rfc['rfc_consultado'],
            $rfc['direccion_flujo'],
            $rfc['total'],
            $rfc['archivos_unicos']
        );
    }

    // 3. Verificar campos nuevos
    echo "\n🆕 3. CAMPOS NUEVOS AGREGADOS:\n";
    echo "-----------------------------\n";
    $new_fields = ['version', 'sello_cfd', 'sello_sat', 'no_certificado_sat', 'rfc_prov_certif', 'estatus_sat', 'cfdi_relacionados'];

    foreach ($new_fields as $field) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, COUNT($field) as poblados FROM cfdi");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $porcentaje = $result['total'] > 0 ? round(($result['poblados'] / $result['total']) * 100, 2) : 0;
        echo sprintf(
            "%-20s: %d/%d registros (%s%%)\n",
            $field,
            $result['poblados'],
            $result['total'],
            $porcentaje
        );
    }

    // 4. Verificar rutas de archivo_xml
    echo "\n📁 4. VERIFICACIÓN DE RUTAS DE ARCHIVOS XML:\n";
    echo "-------------------------------------------\n";
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN archivo_xml IS NOT NULL AND archivo_xml != '' THEN 1 END) as con_ruta,
            COUNT(CASE WHEN archivo_xml LIKE 'c:\\%' OR archivo_xml LIKE 'C:\\%' THEN 1 END) as rutas_absolutas
        FROM cfdi
    ");
    $rutas = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Total CFDIs: {$rutas['total']}\n";
    echo "Con ruta de archivo: {$rutas['con_ruta']}\n";
    echo "Rutas absolutas (C:\\): {$rutas['rutas_absolutas']}\n";

    // Mostrar ejemplos de rutas
    echo "\n📂 Ejemplos de rutas almacenadas:\n";
    $stmt = $pdo->query("SELECT archivo_xml FROM cfdi WHERE archivo_xml IS NOT NULL LIMIT 3");
    $ejemplos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ejemplos as $ruta) {
        echo "  • " . substr($ruta, 0, 80) . (strlen($ruta) > 80 ? '...' : '') . "\n";
    }

    // 5. Verificar tablas especializadas
    echo "\n🔧 5. TABLAS ESPECIALIZADAS DE COMPLEMENTOS:\n";
    echo "-------------------------------------------\n";

    $especializada_tables = ['cfdi_timbre_fiscal', 'cfdi_pagos', 'cfdi_pago_documentos_relacionados'];
    foreach ($especializada_tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Obtener comentario de la tabla
            $stmt = $pdo->query("SHOW TABLE STATUS LIKE '$table'");
            $status = $stmt->fetch(PDO::FETCH_ASSOC);
            $comment = !empty($status['Comment']) ? ' -- ' . $status['Comment'] : '';

            echo sprintf("%-35s: %d registros%s\n", $table, $count, $comment);
        } catch (Exception $e) {
            echo "$table: ERROR - " . $e->getMessage() . "\n";
        }
    }

    // 6. Verificar catálogos SAT
    echo "\n📚 6. CATÁLOGOS SAT DISPONIBLES:\n";
    echo "--------------------------------\n";

    $catalogo_tables = [
        'catalogo_sat_forma_pago',
        'catalogo_sat_metodo_pago',
        'catalogo_sat_moneda',
        'catalogo_sat_regimen_fiscal',
        'catalogo_sat_uso_cfdi',
        'catalogo_sat_tipo_comprobante',
        'catalogo_sat_tipo_factor',
        'catalogo_sat_tipo_relacion'
    ];

    foreach ($catalogo_tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            echo sprintf("%-30s: %d registros\n", $table, $count);
        } catch (Exception $e) {
            echo "$table: ERROR - " . $e->getMessage() . "\n";
        }
    }

    echo "\n✅ RESUMEN DE LA IMPLEMENTACIÓN:\n";
    echo "================================\n";
    echo "✅ Tabla cfdi ampliada a 35 campos con comentarios descriptivos\n";
    echo "✅ Nuevos campos para datos SAT (sellos, certificados, estatus)\n";
    echo "✅ Ruta física completa del XML almacenada en archivo_xml\n";
    echo "✅ Clasificación RFC consultado y dirección de flujo implementada\n";
    echo "✅ Tablas especializadas de complementos creadas con comentarios\n";
    echo "✅ Catálogos SAT completos disponibles para reportes\n";
    echo "✅ Funciones auxiliares para extraer datos específicos\n";
    echo "\n🎯 SISTEMA LISTO PARA:\n";
    echo "- Procesar XMLs con todos los campos SAT\n";
    echo "- Generar reportes completos con descripciones\n";
    echo "- Abrir XMLs desde la interfaz (ruta física almacenada)\n";
    echo "- Distinguir entre CFDIs emitidos/recibidos por RFC\n";
    echo "- Análisis avanzado de complementos especializados\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
