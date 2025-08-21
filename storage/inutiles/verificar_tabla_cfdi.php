<?php
require_once 'src/config/database.php';

try {
    $pdo = getDatabase();

    echo "=== ESTRUCTURA DE LA TABLA CFDI ===\n\n";

    $stmt = $pdo->query('DESCRIBE cfdi');
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columnas as $columna) {
        echo sprintf("%-30s %s\n", $columna['Field'], $columna['Type']);
    }

    echo "\n=== VERIFICANDO COLUMNAS DE COMPLEMENTOS ===\n";

    $tiene_complemento_tipo = false;
    $tiene_complemento_json = false;

    foreach ($columnas as $columna) {
        if ($columna['Field'] === 'complemento_tipo') {
            $tiene_complemento_tipo = true;
            echo "✅ complemento_tipo: " . $columna['Type'] . "\n";
        }
        if ($columna['Field'] === 'complemento_json') {
            $tiene_complemento_json = true;
            echo "✅ complemento_json: " . $columna['Type'] . "\n";
        }
    }

    if (!$tiene_complemento_tipo) {
        echo "❌ NO EXISTE: complemento_tipo\n";
    }

    if (!$tiene_complemento_json) {
        echo "❌ NO EXISTE: complemento_json\n";
    }

    echo "\n=== VERIFICANDO CFDIs CON TIPO P ===\n";

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P'");
    $result = $stmt->fetch();
    echo "CFDIs tipo P (complementos de pago): " . $result['total'] . "\n";

    if ($result['total'] > 0) {
        echo "\n=== EJEMPLO DE CFDI TIPO P ===\n";
        $stmt = $pdo->query("SELECT uuid, rfc_emisor, fecha, " .
            ($tiene_complemento_tipo ? "complemento_tipo, " : "") .
            ($tiene_complemento_json ? "complemento_json " : "") .
            "archivo_xml FROM cfdi WHERE tipo = 'P' LIMIT 1");
        $ejemplo = $stmt->fetch(PDO::FETCH_ASSOC);

        foreach ($ejemplo as $campo => $valor) {
            if ($campo === 'complemento_json' && $valor) {
                echo "$campo: " . (strlen($valor) > 100 ? substr($valor, 0, 100) . "..." : $valor) . "\n";
            } else {
                echo "$campo: $valor\n";
            }
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
