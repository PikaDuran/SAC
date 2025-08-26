<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $result = $pdo->query('DESCRIBE cfdi_pagos');

    echo "Estructura tabla cfdi_pagos:\n";
    echo str_repeat('-', 80) . "\n";
    printf("%-35s %-20s %-10s %s\n", 'Campo', 'Tipo', 'Null', 'Extra');
    echo str_repeat('-', 80) . "\n";

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        printf(
            "%-35s %-20s %-10s %s\n",
            $row['Field'],
            $row['Type'],
            $row['Null'],
            $row['Extra'] ?? ''
        );
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
