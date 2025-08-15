<?php
// Script para leer y mostrar todos los registros de las tablas CFDI
require_once __DIR__ . '/src/config/database.php';
$pdo = getDatabase();

function mostrarTabla($pdo, $tabla)
{
    echo "\n--- $tabla ---\n";
    $stmt = $pdo->query("SELECT * FROM $tabla");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        print_r($row);
        echo str_repeat('-', 60) . "\n";
    }
    echo "Total registros: " . count($rows) . "\n";
}

mostrarTabla($pdo, 'cfdi_comprobantes');
mostrarTabla($pdo, 'cfdi_conceptos');
mostrarTabla($pdo, 'cfdi_complementos');
