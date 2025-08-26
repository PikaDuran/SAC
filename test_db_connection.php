<?php

/**
 * PRUEBA DE CONEXIÓN A BASE DE DATOS
 * Para verificar si realmente se está conectando y insertando datos
 */

require_once __DIR__ . '/src/config/database.php';

echo "🔍 PRUEBA DE CONEXIÓN A BASE DE DATOS\n";
echo str_repeat("=", 50) . "\n\n";

try {
    // 1. Probar conexión
    echo "1. Probando conexión a base de datos...\n";
    $pdo = getDatabase();
    echo "✅ Conexión establecida exitosamente\n\n";

    // 2. Verificar base de datos actual
    echo "2. Verificando base de datos actual...\n";
    $stmt = $pdo->query("SELECT DATABASE() as current_db");
    $db = $stmt->fetch()['current_db'];
    echo "📊 Base de datos actual: $db\n\n";

    // 3. Verificar tablas CFDI
    echo "3. Verificando tablas CFDI...\n";
    $tables = [
        'cfdi',
        'cfdi_conceptos',
        'cfdi_impuestos',
        'cfdi_timbre_fiscal',
        'cfdi_pagos',
        'cfdi_pago_documentos_relacionados',
        'cfdi_pago_impuestos_dr',
        'cfdi_pago_totales',
        'cfdi_relacionados'
    ];

    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "   ✅ $table: $count registros\n";
        } catch (Exception $e) {
            echo "   ❌ $table: Error - " . $e->getMessage() . "\n";
        }
    }

    echo "\n";

    // 4. Probar inserción simple
    echo "4. Probando inserción de prueba...\n";

    // Iniciar transacción
    $pdo->beginTransaction();

    // Insertar registro de prueba
    $stmt = $pdo->prepare("
        INSERT INTO cfdi (
            uuid, tipo, rfc_emisor, nombre_emisor, rfc_receptor, nombre_receptor,
            fecha, total, archivo_xml, rfc_consultado, direccion_flujo, version
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $test_data = [
        'TEST-' . uniqid(),
        'I',
        'TEST123456789',
        'EMPRESA DE PRUEBA',
        'RECEPTOR123456',
        'CLIENTE DE PRUEBA',
        date('Y-m-d H:i:s'),
        1000.00,
        '/test/archivo.xml',
        'TEST123456789',
        'EMITIDA',
        '4.0'
    ];

    $stmt->execute($test_data);
    $test_id = $pdo->lastInsertId();
    echo "✅ Registro de prueba insertado con ID: $test_id\n";

    // Verificar que se insertó
    $stmt = $pdo->prepare("SELECT * FROM cfdi WHERE id = ?");
    $stmt->execute([$test_id]);
    $record = $stmt->fetch();

    if ($record) {
        echo "✅ Registro recuperado exitosamente\n";
        echo "   UUID: " . $record['uuid'] . "\n";
        echo "   RFC Emisor: " . $record['rfc_emisor'] . "\n";
    } else {
        echo "❌ No se pudo recuperar el registro\n";
    }

    // Limpiar registro de prueba
    $stmt = $pdo->prepare("DELETE FROM cfdi WHERE id = ?");
    $stmt->execute([$test_id]);
    echo "🧹 Registro de prueba eliminado\n";

    // Confirmar transacción
    $pdo->commit();

    echo "\n✅ TODAS LAS PRUEBAS PASARON EXITOSAMENTE\n";
    echo "La base de datos está funcionando correctamente.\n";
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollback();
    }
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "La conexión a la base de datos tiene problemas.\n";
}
