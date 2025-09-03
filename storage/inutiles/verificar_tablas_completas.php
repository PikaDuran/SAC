<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');

    // Tablas reales de la base sac_db
    $tablas = [
        'cfdi' => 'Comprobantes principales',
        'cfdi_complemento_carta_porte' => 'Complemento carta porte',
        'cfdi_complemento_comercio_exterior' => 'Complemento comercio exterior',
        'cfdi_complemento_impuestos_locales' => 'Complemento impuestos locales',
        'cfdi_complemento_nomina' => 'Complemento nómina',
        'cfdi_complemento_pagos_v10' => 'Complemento pagos v10',
        'cfdi_complemento_pagos_v20' => 'Complemento pagos v20',
        'cfdi_timbre_fiscal_digital' => 'Timbre fiscal digital',
        'concepto' => 'Conceptos',
        'emisor' => 'Emisor',
        'receptor' => 'Receptor',
        'impuestos_retenidos' => 'Impuestos retenidos',
        'impuestos_trasladados' => 'Impuestos trasladados'
    ];

    echo "VERIFICACIÓN DE REGISTROS EN TODAS LAS TABLAS CFDI\n";
    echo str_repeat("=", 60) . "\n";

    foreach ($tablas as $tabla => $descripcion) {
        $result = $pdo->query("SELECT COUNT(*) FROM {$tabla}");
        $count = $result->fetchColumn();
        printf("%-35s : %d registros\n", $descripcion, $count);
    }

    echo "\nÚLTIMOS 5 REGISTROS DE CADA TABLA:\n";
    echo str_repeat("-", 60) . "\n";

    foreach ($tablas as $tabla => $descripcion) {
        echo "\n{$descripcion} ({$tabla}):\n";
        $result = $pdo->query("SELECT * FROM {$tabla} ORDER BY id DESC LIMIT 5");
        $rows = $result->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            echo "  Sin registros\n";
        } else {
            foreach ($rows as $row) {
                echo "  ID: {$row['id']} - ";
                if (isset($row['cfdi_id'])) echo "CFDI_ID: {$row['cfdi_id']} - ";
                if (isset($row['tipo'])) echo "TIPO: {$row['tipo']} - ";
                if (isset($row['descripcion'])) echo "DESC: " . substr($row['descripcion'], 0, 30) . "...";
                if (isset($row['monto'])) echo "MONTO: {$row['monto']} - ";
                if (isset($row['uuid'])) echo "UUID: " . substr($row['uuid'], 0, 8) . "...";
                echo "\n";
            }
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
