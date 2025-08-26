<?php
$pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');

echo "ANÁLISIS COMPLETO DE TODAS LAS TABLAS - sac_db\n";
echo str_repeat('=', 80) . "\n\n";

// Obtener todas las tablas
$result = $pdo->query('SHOW TABLES');
$tablas = [];
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $tablas[] = $row[0];
}

echo "TOTAL DE TABLAS ENCONTRADAS: " . count($tablas) . "\n";
echo str_repeat('-', 80) . "\n\n";

// Analizar estructura de cada tabla
foreach ($tablas as $tabla) {
    echo "TABLA: {$tabla}\n";
    echo str_repeat('-', 50) . "\n";
    
    $result = $pdo->query("DESCRIBE {$tabla}");
    $campos = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $campos[] = $row;
        printf("%-30s %-20s %-10s %-15s %s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'],
            $row['Key'],
            $row['Extra'] ?? ''
        );
    }
    
    // Contar registros
    $result = $pdo->query("SELECT COUNT(*) as total FROM {$tabla}");
    $count = $result->fetch(PDO::FETCH_ASSOC)['total'];
    echo "\nREGISTROS ACTUALES: {$count}\n";
    
    echo "\n" . str_repeat('=', 80) . "\n\n";
}
?>
