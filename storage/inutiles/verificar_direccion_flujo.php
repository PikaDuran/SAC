<?php

/**
 * Verificar contenido del campo direccion_flujo
 */

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VERIFICACIÓN DIRECCION FLUJO ===\n\n";
    echo "Campo actual: direccion_flujo\n";
    echo "Campo solicitado: direccion_de_flujo\n\n";

    // Verificar valores existentes
    $stmt = $pdo->query("SELECT DISTINCT direccion_flujo FROM cfdi WHERE direccion_flujo IS NOT NULL LIMIT 10");
    $valores = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📊 Valores actuales en direccion_flujo:\n";
    if (count($valores) > 0) {
        foreach ($valores as $valor) {
            echo "   - $valor\n";
        }
    } else {
        echo "   (Sin valores)\n";
    }

    // Contar registros con y sin valor
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        COUNT(direccion_flujo) as con_valor,
        COUNT(*) - COUNT(direccion_flujo) as sin_valor
        FROM cfdi");
    $stats = $stmt->fetch();

    echo "\n📈 Estadísticas:\n";
    echo "   - Total registros: {$stats['total']}\n";
    echo "   - Con direccion_flujo: {$stats['con_valor']}\n";
    echo "   - Sin direccion_flujo: {$stats['sin_valor']}\n";

    // Verificar si necesitamos renombrar el campo
    echo "\n🤔 ANÁLISIS:\n";
    if ($stats['con_valor'] > 0) {
        echo "   ✅ El campo direccion_flujo YA EXISTE y tiene datos\n";
        echo "   💡 Posiblemente solo necesitas usar 'direccion_flujo' en lugar de 'direccion_de_flujo'\n";
    } else {
        echo "   ⚠️ El campo direccion_flujo existe pero está vacío\n";
        echo "   💡 Necesita implementar la lógica para llenarlo\n";
    }

    // Verificar estructura del campo
    $stmt = $pdo->query("SHOW COLUMNS FROM cfdi LIKE 'direccion_flujo'");
    $estructura = $stmt->fetch();
    
    if ($estructura) {
        echo "\n🔧 Estructura del campo:\n";
        echo "   - Tipo: {$estructura['Type']}\n";
        echo "   - Null: {$estructura['Null']}\n";
        echo "   - Default: {$estructura['Default']}\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
