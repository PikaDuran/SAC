<?php

require_once 'src/config/database.php';

echo "=== AGREGANDO COLUMNAS FALTANTES A TABLAS CFDI ===\n\n";

try {
    $pdo = getDatabase();

    echo "✓ Conectado a la base de datos\n\n";

    // Agregar columna observaciones a tabla cfdi
    echo "🔧 Agregando columna 'observaciones' a tabla cfdi...\n";
    try {
        $pdo->exec("ALTER TABLE cfdi ADD COLUMN observaciones TEXT AFTER exportacion");
        echo "✅ Columna 'observaciones' agregada exitosamente\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️  Columna 'observaciones' ya existe\n";
        } else {
            throw $e;
        }
    }

    // Agregar columna no_identificacion a tabla cfdi_conceptos  
    echo "🔧 Agregando columna 'no_identificacion' a tabla cfdi_conceptos...\n";
    try {
        $pdo->exec("ALTER TABLE cfdi_conceptos ADD COLUMN no_identificacion VARCHAR(100) AFTER clave_prodserv");
        echo "✅ Columna 'no_identificacion' agregada exitosamente\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️  Columna 'no_identificacion' ya existe\n";
        } else {
            throw $e;
        }
    }

    echo "\n=== VERIFICANDO ESTRUCTURAS ACTUALIZADAS ===\n\n";

    // Verificar tabla cfdi
    echo "📋 Estructura tabla cfdi:\n";
    $result = $pdo->query("DESCRIBE cfdi");
    while ($row = $result->fetch()) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }

    echo "\n📋 Estructura tabla cfdi_conceptos:\n";
    $result = $pdo->query("DESCRIBE cfdi_conceptos");
    while ($row = $result->fetch()) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }

    echo "\n✅ TODAS LAS COLUMNAS AGREGADAS CORRECTAMENTE!\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
