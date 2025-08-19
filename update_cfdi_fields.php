<?php
// Script para actualizar los campos rfc_consultado y direccion_flujo 
// en los registros CFDI existentes basándose en el análisis de emisor/receptor

require_once __DIR__ . '/src/config/database.php';
$pdo = getDatabase();

echo "Actualizando campos de CFDIs existentes...\n";

// Los RFCs conocidos en el sistema (basado en la estructura de carpetas)
$rfcs_sistema = ['BFM170822P38', 'BLM1706026AA'];

foreach ($rfcs_sistema as $rfc) {
    echo "\nProcesando RFC: $rfc\n";
    
    // Actualizar CFDIs EMITIDAS (donde el RFC es emisor)
    $stmt = $pdo->prepare("UPDATE cfdi SET rfc_consultado = ?, direccion_flujo = 'EMITIDA' WHERE rfc_emisor = ? AND rfc_consultado IS NULL");
    $stmt->execute([$rfc, $rfc]);
    $emitidas = $stmt->rowCount();
    echo "  - CFDIs EMITIDAS actualizadas: $emitidas\n";
    
    // Actualizar CFDIs RECIBIDAS (donde el RFC es receptor)
    $stmt = $pdo->prepare("UPDATE cfdi SET rfc_consultado = ?, direccion_flujo = 'RECIBIDA' WHERE rfc_receptor = ? AND rfc_consultado IS NULL");
    $stmt->execute([$rfc, $rfc]);
    $recibidas = $stmt->rowCount();
    echo "  - CFDIs RECIBIDAS actualizadas: $recibidas\n";
}

// Actualizar registros donde el RFC consultado está vacío pero se puede determinar
$stmt = $pdo->prepare("UPDATE cfdi SET 
    rfc_consultado = CASE 
        WHEN rfc_emisor IN ('" . implode("','", $rfcs_sistema) . "') THEN rfc_emisor
        WHEN rfc_receptor IN ('" . implode("','", $rfcs_sistema) . "') THEN rfc_receptor
        ELSE rfc_consultado
    END,
    direccion_flujo = CASE 
        WHEN rfc_emisor IN ('" . implode("','", $rfcs_sistema) . "') THEN 'EMITIDA'
        WHEN rfc_receptor IN ('" . implode("','", $rfcs_sistema) . "') THEN 'RECIBIDA'
        ELSE direccion_flujo
    END
    WHERE (rfc_consultado = '' OR rfc_consultado IS NULL) 
    AND (rfc_emisor IN ('" . implode("','", $rfcs_sistema) . "') OR rfc_receptor IN ('" . implode("','", $rfcs_sistema) . "'))");
$stmt->execute();
$actualizados = $stmt->rowCount();
echo "\nRegistros con campos vacíos actualizados: $actualizados\n";

// Mostrar resumen final
$stmt = $pdo->query("SELECT 
    rfc_consultado,
    direccion_flujo,
    COUNT(*) as cantidad
FROM cfdi 
GROUP BY rfc_consultado, direccion_flujo 
ORDER BY rfc_consultado, direccion_flujo");

echo "\n=== RESUMEN FINAL ===\n";
echo "RFC Consultado\t\tDirección\t\tCantidad\n";
echo "------------------------------------------------\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("%-20s\t%-15s\t%d\n", $row['rfc_consultado'] ?: '(vacío)', $row['direccion_flujo'] ?: '(vacío)', $row['cantidad']);
}

echo "\nActualización completada.\n";
?>
