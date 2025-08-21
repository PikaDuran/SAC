<?php

/**
 * Script de ejecución del procesador masivo CFDI
 * Interfaz interactiva para procesar XMLs
 */

require_once 'procesador_masivo_cfdi.php';

echo "=== PROCESADOR MASIVO CFDI ===\n";
echo "Sistema de importación CFDI 3.3 y 4.0 con correcciones de pago\n\n";

// Verificar estado actual de la base de datos
try {
    $pdo = getDatabase();

    echo "📊 ESTADO ACTUAL DE LA BASE DE DATOS:\n";
    echo str_repeat("-", 50) . "\n";

    $tablas = [
        'cfdi' => 'CFDIs principales',
        'cfdi_pagos' => 'Complementos de pago',
        'cfdi_conceptos' => 'Conceptos',
        'cfdi_impuestos' => 'Impuestos',
        'cfdi_timbre_fiscal' => 'Timbres fiscales'
    ];

    foreach ($tablas as $tabla => $descripcion) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $tabla");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        echo sprintf("%-20s: %s registros\n", $descripcion, number_format($count));
    }

    // Verificar CFDIs tipo P y sus pagos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cfdi WHERE tipo = 'P'");
    $stmt->execute();
    $cfdiP = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cfdi_pagos WHERE fecha_pago IS NOT NULL");
    $stmt->execute();
    $pagosConDatos = $stmt->fetchColumn();

    echo sprintf("%-20s: %s CFDIs tipo P\n", "Complementos Pago", number_format($cfdiP));
    echo sprintf("%-20s: %s pagos con datos\n", "Pagos completos", number_format($pagosConDatos));

    echo str_repeat("-", 50) . "\n\n";
} catch (Exception $e) {
    echo "❌ Error conectando a la base de datos: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "OPCIONES DISPONIBLES:\n\n";
echo "1. 🆕 Procesar solo archivos nuevos (recomendado)\n";
echo "2. 🔄 Reprocesar todos los archivos\n";
echo "3. 🧹 Limpiar tablas y procesar todo desde cero\n";
echo "4. 📊 Solo mostrar estadísticas y salir\n\n";

echo "Selecciona una opción (1-4): ";
$opcion = trim(fgets(STDIN));

switch ($opcion) {
    case '1':
        echo "\n🆕 PROCESANDO SOLO ARCHIVOS NUEVOS\n";
        echo "Se omitirán los XMLs que ya están en la base de datos\n\n";
        $procesador = new ProcesadorMasivoCFDI();
        $procesador->ejecutar(false, true);
        break;

    case '2':
        echo "\n🔄 REPROCESANDO TODOS LOS ARCHIVOS\n";
        echo "Se procesarán todos los XMLs, pero se omitirán duplicados\n\n";
        $procesador = new ProcesadorMasivoCFDI();
        $procesador->ejecutar(false, false);
        break;

    case '3':
        echo "\n⚠️  ADVERTENCIA: LIMPIEZA COMPLETA\n";
        echo "Esto eliminará TODOS los registros de las tablas CFDI\n";
        echo "¿Estás seguro? (escribe 'SI' para confirmar): ";
        $confirmacion = trim(fgets(STDIN));

        if ($confirmacion === 'SI') {
            echo "\n🧹 LIMPIANDO Y PROCESANDO DESDE CERO\n\n";
            $procesador = new ProcesadorMasivoCFDI();
            $procesador->ejecutar(true, false);
        } else {
            echo "❌ Operación cancelada\n";
        }
        break;

    case '4':
        echo "\n📊 ESTADÍSTICAS MOSTRADAS ARRIBA\n";
        echo "✅ Proceso terminado sin cambios\n";
        break;

    default:
        echo "❌ Opción inválida\n";
        break;
}

echo "\n🎉 Script completado\n";
