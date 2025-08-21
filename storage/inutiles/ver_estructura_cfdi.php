<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');

    echo "=== ESTRUCTURA DE LA TABLA CFDI ===\n\n";
    $stmt = $pdo->query('DESCRIBE cfdi');
    $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($campos as $campo) {
        echo sprintf(
            "%-30s %-20s %s\n",
            $campo['Field'],
            $campo['Type'],
            $campo['Null'] == 'YES' ? 'NULL' : 'NOT NULL'
        );
    }

    // Verificar si existen los campos específicos de CFDI 4.0
    echo "\n=== VERIFICANDO CAMPOS CFDI 4.0 ===\n";
    $campos_40 = ['exportacion', 'regimen_fiscal_receptor'];

    foreach ($campos_40 as $campo_40) {
        $encontrado = false;
        foreach ($campos as $campo) {
            if ($campo['Field'] == $campo_40) {
                echo "✅ $campo_40: Existe\n";
                $encontrado = true;
                break;
            }
        }
        if (!$encontrado) {
            echo "❌ $campo_40: NO EXISTE\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
