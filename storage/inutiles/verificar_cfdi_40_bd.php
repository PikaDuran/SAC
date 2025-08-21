<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VERIFICAR CFDI 4.0 EN BD ===\n";

    // Contar total por versión
    $stmt = $pdo->query('SELECT version, COUNT(*) as total FROM cfdi GROUP BY version');
    $versiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\n📊 RESUMEN POR VERSIONES:\n";
    foreach ($versiones as $v) {
        echo "Versión {$v['version']}: {$v['total']} registros\n";
    }

    // Ver últimos CFDI 4.0 insertados
    echo "\n🔍 ÚLTIMOS CFDI 4.0 INSERTADOS:\n";
    $stmt = $pdo->query('SELECT uuid, version, exportacion, regimen_fiscal_receptor, fecha FROM cfdi WHERE version = "4.0" ORDER BY id DESC LIMIT 5');
    $cfdi_40 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cfdi_40 as $i => $c) {
        echo "\n--- CFDI " . ($i + 1) . " ---\n";
        echo "UUID: {$c['uuid']}\n";
        echo "Versión: {$c['version']}\n";
        echo "Fecha: {$c['fecha']}\n";
        echo "Exportación: " . ($c['exportacion'] ?? 'NULL') . "\n";
        echo "Régimen Fiscal Receptor: " . ($c['regimen_fiscal_receptor'] ?? 'NULL') . "\n";
    }

    // Verificar campos específicos de 4.0
    echo "\n🎯 VERIFICANDO CAMPOS ESPECÍFICOS DE CFDI 4.0:\n";
    $stmt = $pdo->query('SELECT 
        COUNT(*) as total,
        COUNT(exportacion) as con_exportacion,
        COUNT(regimen_fiscal_receptor) as con_regimen
        FROM cfdi WHERE version = "4.0"');
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Total CFDI 4.0: {$stats['total']}\n";
    echo "Con campo Exportación: {$stats['con_exportacion']}\n";
    echo "Con campo Régimen Fiscal Receptor: {$stats['con_regimen']}\n";

    if ($stats['total'] > 0) {
        echo "\n✅ ¡CFDI 4.0 SE ESTÁN INSERTANDO CORRECTAMENTE!\n";
    } else {
        echo "\n❌ No se encontraron CFDI 4.0 en la base de datos\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
