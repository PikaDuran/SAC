<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=sac_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi");
    $total = $stmt->fetch()['total'];
    echo "📊 CFDIs en base actual: $total\n";

    if ($total > 0) {
        $stmt = $pdo->query("SELECT Version, COUNT(*) as cantidad FROM cfdi GROUP BY Version");
        echo "\n📋 Por versión:\n";
        while ($row = $stmt->fetch()) {
            echo "   {$row['Version']}: {$row['cantidad']}\n";
        }

        $stmt = $pdo->query("SELECT DATE(created_at) as fecha, COUNT(*) as cantidad FROM cfdi GROUP BY DATE(created_at) ORDER BY fecha DESC LIMIT 5");
        echo "\n📅 Últimos días de inserción:\n";
        while ($row = $stmt->fetch()) {
            echo "   {$row['fecha']}: {$row['cantidad']}\n";
        }
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
