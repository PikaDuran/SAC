<?php
// Analizar catálogos SAT para entender tipos de CFDI

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');

    echo "📋 CATÁLOGO SAT - TIPOS DE COMPROBANTE:" . PHP_EOL;
    echo str_repeat('=', 50) . PHP_EOL;

    $stmt = $pdo->query('SELECT clave, descripcion FROM catalogo_sat_tipo_comprobante ORDER BY clave');
    while ($row = $stmt->fetch()) {
        echo $row['clave'] . ' - ' . $row['descripcion'] . PHP_EOL;
    }

    echo PHP_EOL . "📋 CATÁLOGO SAT - FORMAS DE PAGO:" . PHP_EOL;
    echo str_repeat('=', 50) . PHP_EOL;

    $stmt = $pdo->query('SELECT clave, descripcion FROM catalogo_sat_forma_pago ORDER BY clave LIMIT 10');
    while ($row = $stmt->fetch()) {
        echo $row['clave'] . ' - ' . $row['descripcion'] . PHP_EOL;
    }

    echo PHP_EOL . "📋 CATÁLOGO SAT - USOS CFDI:" . PHP_EOL;
    echo str_repeat('=', 50) . PHP_EOL;

    $stmt = $pdo->query('SELECT clave, descripcion FROM catalogo_sat_uso_cfdi ORDER BY clave LIMIT 10');
    while ($row = $stmt->fetch()) {
        echo $row['clave'] . ' - ' . $row['descripcion'] . PHP_EOL;
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}
