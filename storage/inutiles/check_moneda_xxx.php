<?php
require_once 'src/config/database.php';
try {
    $pdo = getDatabase();
    echo "📊 CATÁLOGO DE MONEDAS:\n";
    echo str_repeat('=', 40) . "\n";
    $stmt = $pdo->query('SELECT clave, descripcion FROM catalogo_sat_moneda ORDER BY clave');
    while ($row = $stmt->fetch()) {
        echo sprintf('%-5s: %s' . "\n", $row['clave'], $row['descripcion']);
    }
    echo "\n🔍 BUSCANDO XXX:\n";
    $stmt = $pdo->prepare('SELECT * FROM catalogo_sat_moneda WHERE clave = ?');
    $stmt->execute(['XXX']);
    $result = $stmt->fetch();
    if ($result) {
        echo "✅ XXX encontrada: " . $result['descripcion'] . "\n";
    } else {
        echo "❌ XXX NO ENCONTRADA en el catálogo\n";
        echo "\n💡 INSERTANDO XXX:\n";
        $stmt = $pdo->prepare('INSERT INTO catalogo_sat_moneda (clave, descripcion) VALUES (?, ?)');
        $stmt->execute(['XXX', 'Moneda de prueba - Sin especificar']);
        echo "✅ XXX insertada correctamente\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
