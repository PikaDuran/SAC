<?php
require_once 'src/config/database.php';

$pdo = getDatabase();

echo "=== ANÁLISIS DE DATOS ACTUALES ===\n\n";

// Verificar tipos de CFDI
$stmt = $pdo->query('SELECT tipo, COUNT(*) as cantidad FROM cfdi GROUP BY tipo ORDER BY cantidad DESC');
echo "TIPOS DE CFDI EN LA BASE DE DATOS:\n";
echo str_repeat("=", 40) . "\n";
while ($row = $stmt->fetch()) {
    printf("%-10s: %d\n", $row['tipo'], $row['cantidad']);
}

echo "\n";

// Verificar si hay datos en cfdi_pagos
$stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi_pagos');
$pagos = $stmt->fetch();
echo "TOTAL DE REGISTROS EN cfdi_pagos: " . $pagos['total'] . "\n";

// Verificar si hay datos en cfdi_pago_documentos_relacionados
$stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi_pago_documentos_relacionados');
$docs = $stmt->fetch();
echo "TOTAL DE REGISTROS EN cfdi_pago_documentos_relacionados: " . $docs['total'] . "\n\n";

// Verificar estructura de complementos
$stmt = $pdo->query("SELECT complemento_tipo, COUNT(*) as cantidad FROM cfdi WHERE complemento_tipo IS NOT NULL AND complemento_tipo != '' GROUP BY complemento_tipo");
echo "TIPOS DE COMPLEMENTOS ENCONTRADOS:\n";
echo str_repeat("=", 50) . "\n";
$complementos_encontrados = false;
while ($row = $stmt->fetch()) {
    printf("%-30s: %d\n", $row['complemento_tipo'], $row['cantidad']);
    $complementos_encontrados = true;
}

if (!$complementos_encontrados) {
    echo "No se encontraron complementos registrados.\n";
}

echo "\n";

// Buscar CFDIs que podrían ser de pago
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P'");
$pagos_tipo = $stmt->fetch();
echo "CFDIs con tipo 'P' (Pago): " . $pagos_tipo['total'] . "\n";

// Mostrar algunos ejemplos de CFDIs para entender la estructura
$stmt = $pdo->query("SELECT uuid, tipo, rfc_emisor, nombre_emisor, total, fecha FROM cfdi ORDER BY fecha DESC LIMIT 5");
echo "\nEJEMPLOS DE CFDIs RECIENTES:\n";
echo str_repeat("=", 120) . "\n";
printf("%-40s %-5s %-15s %-30s %-15s %s\n", "UUID", "TIPO", "RFC", "EMISOR", "TOTAL", "FECHA");
echo str_repeat("-", 120) . "\n";
while ($row = $stmt->fetch()) {
    printf(
        "%-40s %-5s %-15s %-30s $%-13s %s\n",
        substr($row['uuid'], 0, 40),
        $row['tipo'],
        $row['rfc_emisor'],
        substr($row['nombre_emisor'], 0, 30),
        number_format($row['total'], 2),
        $row['fecha']
    );
}
