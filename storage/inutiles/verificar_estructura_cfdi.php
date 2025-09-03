<?php
require_once 'src/config/database.php';

try {
    $pdo = getDatabase();

    echo "Estructura de la tabla cfdi:\n";
    echo str_repeat("-", 40) . "\n";

    $stmt = $pdo->query('DESCRIBE cfdi');
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }

    echo "\n" . str_repeat("-", 40) . "\n";
    echo "Primeros registros:\n";

    $stmt = $pdo->query('SELECT * FROM cfdi LIMIT 3');
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (empty($columns)) {
            $columns = array_keys($row);
            echo implode(" | ", $columns) . "\n";
            echo str_repeat("-", 80) . "\n";
        }

        $values = [];
        foreach ($columns as $col) {
            $val = $row[$col] ?? '';
            if (strlen($val) > 15) {
                $val = substr($val, 0, 15) . '...';
            }
            $values[] = $val;
        }
        echo implode(" | ", $values) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
