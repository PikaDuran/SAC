<?php

// Test específico para verificar campos faltantes
require_once 'procesador_cfdi_completo.php';

echo "=== TEST DE CAMPOS ESPECÍFICOS ===\n\n";

try {
    $pdo = getDatabase();
    echo "✓ Conexión a base de datos establecida\n";

    $procesador = new ProcesadorCFDICompleto($pdo);
    echo "✓ Procesador inicializado\n\n";

    // Limpiar una pequeña muestra para test
    echo "🧹 Limpiando tablas...\n";
    $procesador->limpiarTablas();

    // Obtener un archivo XML de muestra
    $archivos = glob('storage/sat_downloads/*/EMITIDAS/*/*/*.xml');
    if (empty($archivos)) {
        throw new Exception("No se encontraron archivos XML");
    }

    // Procesar solo los primeros 5 archivos para test
    $archivos_test = array_slice($archivos, 0, 5);

    echo "📁 Procesando " . count($archivos_test) . " archivos de prueba...\n\n";

    foreach ($archivos_test as $archivo) {
        echo "📄 Procesando: " . basename($archivo) . "\n";

        $contenido = file_get_contents($archivo);
        if ($contenido === false) continue;

        // Aquí usaríamos el método privado si fuera público
        // Por ahora, vamos a crear un mini-procesador

        // Verificar si es tipo P (pago)
        if (preg_match('/TipoDeComprobante\s*=\s*["\']P["\']/', $contenido)) {
            echo "  ➤ Es un CFDI de pagos (Tipo P)\n";
        }

        // Verificar si tiene CFDI relacionados
        if (preg_match('/<cfdi:CfdiRelacionados[^>]*>/', $contenido)) {
            echo "  ➤ Tiene CFDIs relacionados\n";
        }

        echo "\n";
    }

    // Ejecutar procesamiento completo de estos archivos
    echo "🔄 Ejecutando procesamiento completo...\n";
    $resultado = $procesador->procesarDirectorio('storage/sat_downloads');

    // Verificar en base de datos los campos
    echo "\n=== VERIFICACIÓN EN BASE DE DATOS ===\n";

    // Verificar cfdi_relacionados
    $result = $pdo->query("SELECT COUNT(*) as total, COUNT(cfdi_relacionados) as con_relacionados FROM cfdi WHERE cfdi_relacionados IS NOT NULL");
    $row = $result->fetch();
    echo "CFDIs con relacionados: {$row['con_relacionados']} de {$row['total']}\n";

    // Verificar campos de pagos
    $result = $pdo->query("SELECT COUNT(*) as total FROM cfdi_pagos WHERE fecha_pago IS NOT NULL AND forma_pago IS NOT NULL");
    $row = $result->fetch();
    echo "Pagos con datos completos: {$row['total']}\n";

    echo "\n✅ Test completado\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}
