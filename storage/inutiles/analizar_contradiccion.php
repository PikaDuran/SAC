<?php

/**
 * Análisis de la contradicción: ¿Por qué antes encontré datos y ahora no?
 */

require_once 'src/config/database.php';

try {
    $pdo = getDatabase();
    echo "=== ANÁLISIS DE LA CONTRADICCIÓN ===\n\n";

    // 1. Verificar los datos que encontré antes
    echo "1. DATOS QUE ENCONTRÉ ANTES:\n";
    echo "══════════════════════════════════\n";

    $cfdisConJson = $pdo->query("
        SELECT id, uuid, complemento_json, LENGTH(complemento_json) as json_size
        FROM cfdi 
        WHERE tipo = 'P' 
        AND complemento_json IS NOT NULL 
        AND complemento_json != '[]' 
        AND complemento_json != ''
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "CFDIs con complemento_json (primeros 5):\n";
    foreach ($cfdisConJson as $cfdi) {
        echo "ID: {$cfdi['id']}, UUID: {$cfdi['uuid']}, JSON size: {$cfdi['json_size']} bytes\n";

        // Analizar el contenido del JSON
        $json = json_decode($cfdi['complemento_json'], true);
        if ($json) {
            echo "  Estructura JSON: " . implode(', ', array_keys($json)) . "\n";

            // Buscar específicamente datos de pago
            if (isset($json['Pagos'])) {
                echo "  ✓ Tiene 'Pagos'\n";
            }
            if (isset($json['pago10:Pagos'])) {
                echo "  ✓ Tiene 'pago10:Pagos'\n";
            }

            // Mostrar todo el contenido para analizar
            echo "  Contenido completo:\n";
            echo "  " . substr(json_encode($json, JSON_PRETTY_PRINT), 0, 500) . "...\n";
        } else {
            echo "  ❌ JSON inválido\n";
        }
        echo "\n";
    }

    // 2. Verificar la diferencia con el script de reprocesamiento
    echo "\n2. VERIFICACIÓN DEL MÉTODO DE EXTRACCIÓN:\n";
    echo "════════════════════════════════════════════\n";

    // Tomar un CFDI específico y analizarlo paso a paso
    $cfdiEjemplo = $pdo->query("
        SELECT id, uuid, archivo_xml, complemento_json
        FROM cfdi 
        WHERE tipo = 'P' 
        AND complemento_json IS NOT NULL 
        AND complemento_json != '[]' 
        AND complemento_json != ''
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if ($cfdiEjemplo) {
        echo "Analizando CFDI ID: {$cfdiEjemplo['id']}\n";
        echo "UUID: {$cfdiEjemplo['uuid']}\n";
        echo "Archivo XML: {$cfdiEjemplo['archivo_xml']}\n\n";

        // Verificar si el archivo existe
        if (file_exists($cfdiEjemplo['archivo_xml'])) {
            echo "✓ Archivo XML existe\n";

            // Leer el XML directamente
            $xmlContent = file_get_contents($cfdiEjemplo['archivo_xml']);

            // Buscar patrones de complemento de pago
            $patrones = [
                'pago10:Pagos',
                'pago20:Pagos',
                '<Pagos',
                'Complemento',
                'xmlns:pago'
            ];

            echo "\nBúsqueda de patrones en XML:\n";
            foreach ($patrones as $patron) {
                $count = substr_count($xmlContent, $patron);
                echo "  '$patron': $count ocurrencias\n";
            }

            // Verificar qué está en complemento_json vs lo que encuentra el método
            echo "\nComparación:\n";
            echo "complemento_json tiene: " . strlen($cfdiEjemplo['complemento_json']) . " bytes\n";

            $json = json_decode($cfdiEjemplo['complemento_json'], true);
            if ($json) {
                echo "Claves en JSON: " . implode(', ', array_keys($json)) . "\n";
            }
        } else {
            echo "❌ Archivo XML NO existe: {$cfdiEjemplo['archivo_xml']}\n";
        }
    }

    // 3. Explicar la contradicción
    echo "\n\n3. EXPLICACIÓN DE LA CONTRADICCIÓN:\n";
    echo "═══════════════════════════════════════════\n";

    echo "HIPÓTESIS 1: Los datos están en complemento_json pero con estructura diferente\n";
    echo "HIPÓTESIS 2: El método extraerComplementoPagos() busca un patrón específico que no coincide\n";
    echo "HIPÓTESIS 3: Los XMLs tienen la información pero en namespace diferente\n";
    echo "HIPÓTESIS 4: El complemento_json fue generado por otro proceso, no por extraerComplementoPagos()\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
