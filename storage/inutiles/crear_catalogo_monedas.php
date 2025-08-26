<?php
/**
 * Crear tabla de catálogo de monedas SAT y agregar moneda XXX
 */

$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'sac_db'
];

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "🔗 Conectado a la base de datos\n";

    // Crear tabla de monedas si no existe
    $sql_create = "
    CREATE TABLE IF NOT EXISTS catalogo_sat_monedas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(3) NOT NULL UNIQUE,
        descripcion VARCHAR(100) NOT NULL,
        decimales INT DEFAULT 2,
        porcentaje_variacion DECIMAL(5,2) DEFAULT 0,
        fecha_inicio DATE DEFAULT NULL,
        fecha_fin DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql_create);
    echo "✅ Tabla catalogo_sat_monedas creada/verificada\n";

    // Insertar monedas principales
    $monedas = [
        ['MXN', 'PESO MEXICANO', 2, 0],
        ['USD', 'DOLAR AMERICANO', 2, 13],
        ['EUR', 'EURO', 2, 10],
        ['XXX', 'SIN MONEDA DEFINIDA', 2, 0]
    ];

    $sql_insert = "INSERT IGNORE INTO catalogo_sat_monedas (codigo, descripcion, decimales, porcentaje_variacion) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql_insert);

    $insertadas = 0;
    foreach ($monedas as $moneda) {
        if ($stmt->execute($moneda)) {
            if ($stmt->rowCount() > 0) {
                $insertadas++;
                echo "  ✓ Moneda {$moneda[0]} - {$moneda[1]}\n";
            }
        }
    }

    echo "\n🎉 Proceso completado:\n";
    echo "   - Monedas insertadas: $insertadas\n";
    echo "   - Tabla catalogo_sat_monedas lista\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
