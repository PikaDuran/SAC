<?php
require_once 'vendor/autoload.php';

// Configuración de base de datos
$host = 'localhost';
$dbname = 'sac_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "===========================================\n";
    echo "  LIMPIEZA Y REIMPORTACIÓN COMPLETA\n";
    echo "===========================================\n\n";

    // Verificar que se quiere hacer limpieza
    echo "⚠️  ADVERTENCIA: Esta operación eliminará TODOS los datos existentes.\n";
    echo "¿Deseas continuar? (escribe 'SI' para confirmar): ";
    $confirmacion = trim(fgets(STDIN));

    if ($confirmacion !== 'SI') {
        echo "❌ Operación cancelada.\n";
        exit(0);
    }

    echo "\n🧹 INICIANDO LIMPIEZA COMPLETA...\n";
    echo "=====================================\n";

    // 1. LIMPIEZA DE TABLAS (en orden correcto para evitar errores de FK)
    $tablas_limpiar = [
        'cfdi_complemento_pagos_v20',
        'cfdi_complemento_pagos_v10',
        'cfdi_otros_complementos',
        'cfdi_complemento_nomina',
        'cfdi_complemento_carta_porte',
        'cfdi_complemento_comercio_exterior',
        'cfdi_complemento_impuestos_locales',
        'impuestos_trasladados',
        'impuestos_retenidos',
        'conceptos',
        'cfdi_relacionados',
        'cfdi_timbre_fiscal_digital',
        'receptor',
        'emisor',
        'addenda',
        'cfdi'
    ];

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($tablas_limpiar as $tabla) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM $tabla");
            $count = $stmt->fetch()['total'];

            if ($count > 0) {
                $pdo->exec("TRUNCATE TABLE $tabla");
                echo "🗑️  $tabla: $count registros eliminados\n";
            } else {
                echo "⚪ $tabla: ya estaba vacía\n";
            }
        } catch (Exception $e) {
            echo "❌ Error al limpiar $tabla: " . $e->getMessage() . "\n";
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "\n✅ LIMPIEZA COMPLETADA\n\n";

    echo "🔄 REINICIANDO IMPORTACIÓN CORREGIDA...\n";
    echo "========================================\n";

    // 2. EJECUTAR EL IMPORTADOR CORREGIDO
    echo "📁 Ejecutando importador corregido...\n";

    // Aquí podríamos llamar al importador corregido
    // Por ahora, mostremos las estadísticas finales

    foreach ($tablas_limpiar as $tabla) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM $tabla");
        $count = $stmt->fetch()['total'];
        echo "📊 $tabla: $count registros\n";
    }

    echo "\n🎉 LIMPIEZA COMPLETADA. Listo para reimportación.\n";
    echo "\n💡 PRÓXIMO PASO: Ejecutar importador corregido\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
