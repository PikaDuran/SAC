<?php
date_default_timezone_set('America/Mexico_City');
require_once __DIR__ . '/src/config/database.php';
$pdo = getDatabase();

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
    $pdo->exec("TRUNCATE TABLE cfdi_timbre_fiscal;");
    $pdo->exec("TRUNCATE TABLE cfdi_conceptos;");
    $pdo->exec("TRUNCATE TABLE cfdi_impuestos;");
    $pdo->exec("TRUNCATE TABLE cfdi_complementos;");
    $pdo->exec("TRUNCATE TABLE cfdi_pagos;");
    $pdo->exec("TRUNCATE TABLE cfdi_pago_documentos_relacionados;");
    $pdo->exec("TRUNCATE TABLE cfdi;");
    $pdo->exec("TRUNCATE TABLE cfdi_auditoria;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    echo "Tablas CFDI y auditoría vaciadas correctamente.\n";
} catch (Exception $e) {
    echo "Error al vaciar tablas: " . $e->getMessage() . "\n";
}
