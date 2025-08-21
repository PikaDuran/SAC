<?php
require 'src/config/database.php';

try {
    $pdo = getDatabase();

    echo "=== VERIFICACIÓN DE DATOS REALES ===\n\n";

    // Verificar cuántos registros hay realmente en cfdi
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM cfdi');
    $stmt->execute();
    $total_cfdi = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total CFDIs en BD: " . $total_cfdi['total'] . "\n";

    // Verificar cuántos son tipo P
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM cfdi WHERE tipo = ?');
    $stmt->execute(['P']);
    $total_p = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "CFDIs tipo P: " . $total_p['total'] . "\n";

    // Verificar timbre_fiscal
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM cfdi_timbre_fiscal');
    $stmt->execute();
    $total_timbre = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Timbres fiscales: " . $total_timbre['total'] . "\n";

    // Verificar cfdi_pagos
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM cfdi_pagos');
    $stmt->execute();
    $total_pagos = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Pagos: " . $total_pagos['total'] . "\n\n";

    // Verificar archivos XML especificados vs NULL
    $stmt = $pdo->prepare('SELECT 
        COUNT(CASE WHEN archivo_xml IS NOT NULL AND archivo_xml != "" THEN 1 END) as con_archivo,
        COUNT(CASE WHEN archivo_xml IS NULL OR archivo_xml = "" THEN 1 END) as sin_archivo
        FROM cfdi');
    $stmt->execute();
    $archivos = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "CFDIs CON archivo XML especificado: " . $archivos['con_archivo'] . "\n";
    echo "CFDIs SIN archivo XML especificado: " . $archivos['sin_archivo'] . "\n\n";

    // Verificar algunos registros con archivo_xml
    $stmt = $pdo->prepare('SELECT archivo_xml, COUNT(*) as total 
                          FROM cfdi 
                          WHERE archivo_xml IS NOT NULL AND archivo_xml != "" 
                          GROUP BY archivo_xml LIMIT 10');
    $stmt->execute();
    echo "Algunos archivos XML especificados:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['archivo_xml'] . " (" . $row['total'] . " registros)\n";
    }

    echo "\n=== VERIFICACIÓN DE ESTRUCTURA COMPLEMENTO_JSON ===\n";
    // Verificar si hay datos en complemento_json
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM cfdi WHERE complemento_json IS NOT NULL AND complemento_json != ""');
    $stmt->execute();
    $con_json = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "CFDIs con complemento_json: " . $con_json['total'] . "\n";

    // Verificar ejemplo de complemento_json
    $stmt = $pdo->prepare('SELECT uuid, complemento_json FROM cfdi WHERE complemento_json IS NOT NULL AND complemento_json != "" LIMIT 1');
    $stmt->execute();
    $ejemplo = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ejemplo) {
        echo "\nEjemplo de complemento_json (UUID: " . $ejemplo['uuid'] . "):\n";
        echo substr($ejemplo['complemento_json'], 0, 500) . "...\n";
    }
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
