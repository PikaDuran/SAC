<?php

/**
 * Verificación profunda de los complementos de pago
 * Para determinar si realmente están vacíos o es un problema de visualización
 */

require_once 'src/config/database.php';

try {
    $pdo = getDatabase();

    echo "=== VERIFICACIÓN PROFUNDA DE COMPLEMENTOS DE PAGO ===\n\n";

    // 1. Estadísticas generales
    echo "=== 1. ESTADÍSTICAS GENERALES ===\n";

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P'");
    $total_p = $stmt->fetchColumn();
    echo "Total CFDIs tipo P: $total_p\n";

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P' AND complemento_json IS NOT NULL AND complemento_json != '' AND complemento_json != '[]'");
    $con_json = $stmt->fetchColumn();
    echo "CFDIs P con complemento_json válido: $con_json\n";

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P' AND complemento_tipo IS NOT NULL AND complemento_tipo != ''");
    $con_tipo = $stmt->fetchColumn();
    echo "CFDIs P con complemento_tipo válido: $con_tipo\n";

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P' AND archivo_xml IS NOT NULL AND archivo_xml != '' AND archivo_xml != '[]'");
    $con_archivo = $stmt->fetchColumn();
    echo "CFDIs P con archivo_xml válido: $con_archivo\n\n";

    // 2. Ejemplos reales con datos
    echo "=== 2. EJEMPLOS REALES CON DATOS ===\n";

    $stmt = $pdo->query("SELECT uuid, complemento_tipo, LENGTH(complemento_json) as json_length, archivo_xml FROM cfdi WHERE tipo = 'P' AND complemento_json IS NOT NULL AND complemento_json != '' AND complemento_json != '[]' LIMIT 3");
    $ejemplos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ejemplos as $i => $ejemplo) {
        echo "\n--- EJEMPLO " . ($i + 1) . " ---\n";
        echo "UUID: " . $ejemplo['uuid'] . "\n";
        echo "Complemento Tipo: " . ($ejemplo['complemento_tipo'] ?: 'VACÍO') . "\n";
        echo "JSON Length: " . $ejemplo['json_length'] . " bytes\n";
        echo "Archivo XML: " . (strlen($ejemplo['archivo_xml']) > 50 ? substr($ejemplo['archivo_xml'], 0, 50) . "..." : $ejemplo['archivo_xml']) . "\n";
    }

    // 3. Ver contenido real de un complemento_json
    echo "\n=== 3. CONTENIDO REAL DE COMPLEMENTO_JSON ===\n";

    $stmt = $pdo->query("SELECT uuid, complemento_json FROM cfdi WHERE tipo = 'P' AND complemento_json IS NOT NULL AND complemento_json != '' AND complemento_json != '[]' LIMIT 1");
    $ejemplo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ejemplo) {
        echo "UUID: " . $ejemplo['uuid'] . "\n";
        echo "Complemento JSON:\n";

        $json_data = json_decode($ejemplo['complemento_json'], true);
        if ($json_data) {
            echo "✅ JSON válido encontrado:\n";
            print_r($json_data);
        } else {
            echo "❌ JSON inválido o vacío\n";
            echo "Contenido raw: " . substr($ejemplo['complemento_json'], 0, 200) . "...\n";
        }
    } else {
        echo "❌ No se encontró ningún ejemplo con complemento_json\n";
    }

    // 4. Verificar archivos XML físicos
    echo "\n=== 4. VERIFICACIÓN DE ARCHIVOS XML FÍSICOS ===\n";

    $stmt = $pdo->query("SELECT uuid, archivo_xml FROM cfdi WHERE tipo = 'P' AND archivo_xml IS NOT NULL AND archivo_xml != '' AND archivo_xml != '[]' LIMIT 3");
    $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($archivos as $i => $archivo_info) {
        echo "\n--- ARCHIVO " . ($i + 1) . " ---\n";
        echo "UUID: " . $archivo_info['uuid'] . "\n";
        echo "Ruta: " . $archivo_info['archivo_xml'] . "\n";

        if (file_exists($archivo_info['archivo_xml'])) {
            echo "✅ Archivo existe físicamente\n";
            $size = filesize($archivo_info['archivo_xml']);
            echo "Tamaño: " . number_format($size) . " bytes\n";
        } else {
            echo "❌ Archivo NO existe físicamente\n";
        }
    }

    // 5. Buscar patrón en complemento_json para verificar estructura
    echo "\n=== 5. ANÁLISIS DE ESTRUCTURA DE DATOS ===\n";

    $stmt = $pdo->query("SELECT complemento_json FROM cfdi WHERE tipo = 'P' AND complemento_json IS NOT NULL AND complemento_json != '' AND complemento_json != '[]' LIMIT 5");
    $jsons = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $patrones_encontrados = [];
    foreach ($jsons as $json_str) {
        $data = json_decode($json_str, true);
        if ($data) {
            if (isset($data['complementos'])) {
                $patrones_encontrados['complementos'] = ($patrones_encontrados['complementos'] ?? 0) + 1;
            }
            if (isset($data['Pagos'])) {
                $patrones_encontrados['Pagos'] = ($patrones_encontrados['Pagos'] ?? 0) + 1;
            }
            if (isset($data['fecha_pago'])) {
                $patrones_encontrados['fecha_pago'] = ($patrones_encontrados['fecha_pago'] ?? 0) + 1;
            }
        }
    }

    echo "Patrones encontrados en complemento_json:\n";
    foreach ($patrones_encontrados as $patron => $count) {
        echo "- $patron: $count veces\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
