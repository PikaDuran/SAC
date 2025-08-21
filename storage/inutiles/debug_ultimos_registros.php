<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VERIFICANDO ÚLTIMOS REGISTROS INSERTADOS ===\n\n";

    // Ver últimos 10 registros por ID
    $stmt = $pdo->query('SELECT id, uuid, version, exportacion, regimen_fiscal_receptor, archivo_xml FROM cfdi ORDER BY id DESC LIMIT 10');
    $ultimos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "📋 ÚLTIMOS 10 REGISTROS POR ID:\n";
    foreach ($ultimos as $r) {
        echo "ID: {$r['id']}, UUID: {$r['uuid']}, Versión: {$r['version']}\n";
        echo "   Exportación: " . ($r['exportacion'] ?? 'NULL') . "\n";
        echo "   Régimen Receptor: " . ($r['regimen_fiscal_receptor'] ?? 'NULL') . "\n";
        echo "   Archivo: " . substr($r['archivo_xml'], -50) . "\n";
        echo "---\n";
    }

    // Contar total
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi');
    $total = $stmt->fetchColumn();
    echo "\n📊 Total registros en cfdi: $total\n";

    // Buscar específicamente los UUIDs que vimos en los logs
    $uuids = [
        '0D157DDC-7E07-4FD8-B37A-BAE0105F3F1C',
        '3B08E7A2-3EB7-46BD-8849-4675C03B718E',
        '728F325C-7365-4B96-B2A5-BAEB534A2CE0'
    ];

    echo "\n🔍 BUSCANDO UUIDs ESPECÍFICOS DEL LOG:\n";
    foreach ($uuids as $uuid) {
        $stmt = $pdo->prepare('SELECT id, version, exportacion FROM cfdi WHERE uuid = ?');
        $stmt->execute([$uuid]);
        $cfdi = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cfdi) {
            echo "✅ UUID $uuid ENCONTRADO - ID: {$cfdi['id']}, Versión: {$cfdi['version']}\n";
        } else {
            echo "❌ UUID $uuid NO ENCONTRADO\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
