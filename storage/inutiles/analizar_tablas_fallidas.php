<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');

    // Tablas que están vacías según verificación anterior
    $tablasVacias = [
        'cfdi_conceptos',
        'cfdi_impuestos',
        'cfdi_pago_documentos_relacionados',
        'cfdi_pago_impuestos_dr',
        'cfdi_pago_totales',
        'cfdi_complementos'
    ];

    echo "ANÁLISIS ESTRUCTURAL DE TABLAS VACÍAS\n";
    echo str_repeat("=", 70) . "\n\n";

    foreach ($tablasVacias as $tabla) {
        echo "TABLA: {$tabla}\n";
        echo str_repeat("-", 50) . "\n";

        $result = $pdo->query("DESCRIBE {$tabla}");
        $campos = [];
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $campos[] = $row;
            printf(
                "%-30s %-20s %-10s %s\n",
                $row['Field'],
                $row['Type'],
                $row['Null'],
                $row['Extra'] ?? ''
            );
        }

        echo "\nCAMPOS REQUERIDOS PARA INSERT:\n";
        $camposInsert = [];
        foreach ($campos as $campo) {
            if ($campo['Field'] !== 'id') { // Excluir auto_increment
                $camposInsert[] = $campo['Field'];
            }
        }
        echo "INSERT INTO {$tabla} (" . implode(', ', $camposInsert) . ") VALUES (...)\n";
        echo "\n" . str_repeat("=", 70) . "\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
