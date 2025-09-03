<?php
$pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');

// Verificar estructura de la tabla cfdi
echo "Estructura tabla cfdi:\n";
$result = $pdo->query('DESCRIBE cfdi');
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\nPrimeros 5 registros:\n";
$result = $pdo->query('SELECT * FROM cfdi LIMIT 5');
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    foreach ($row as $campo => $valor) {
        if ($campo === 'uuid' || $campo === 'tipo_de_comprobante' || $campo === 'archivo_xml') {
            echo "$campo: $valor | ";
        }
    }
    echo "\n";
}
