<?php
// Script para verificar solo la tabla sat_download_history actualizada
require_once 'src/config/database.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "=== VERIFICACIÓN TABLA sat_download_history ACTUALIZADA ===\n\n";

    // Verificar estructura de la tabla sat_download_history
    echo "ESTRUCTURA DE sat_download_history:\n";
    echo str_repeat("-", 80) . "\n";

    $stmt = $pdo->query("DESCRIBE sat_download_history");
    $columns = $stmt->fetchAll();

    printf(
        "%-25s %-20s %-8s %-8s %-15s %s\n",
        "CAMPO",
        "TIPO",
        "NULL",
        "KEY",
        "DEFAULT",
        "EXTRA"
    );
    echo str_repeat("-", 80) . "\n";

    foreach ($columns as $column) {
        printf(
            "%-25s %-20s %-8s %-8s %-15s %s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'],
            $column['Key'],
            $column['Default'] ?? 'NULL',
            $column['Extra']
        );
    }

    echo "\n=== CAMPOS ESPECÍFICOS PARA DESCARGA MASIVA ===\n";

    $campos_nuevos = [
        'estatus_solicitud' => 'Estado de la solicitud SAT',
        'ultima_actualizacion' => 'Última vez que se verificó',
        'fecha_inicial' => 'Fecha desde del buscador',
        'fecha_final' => 'Fecha hasta del buscador',
        'tipo_documento' => 'Emitidas/Recibidas',
        'mensaje_verificacion' => 'Respuesta del SAT',
        'paquetes' => 'Paquetes del SAT (JSON)',
        'codigo_estado_verificacion' => 'Código de estado verificación',
        'codigo_estado_solicitud' => 'Código de estado solicitud',
        'fecha_solicitud' => 'Fecha de la solicitud'
    ];

    foreach ($campos_nuevos as $campo => $descripcion) {
        $encontrado = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $campo) {
                echo "✅ $campo: $descripcion\n";
                $encontrado = true;
                break;
            }
        }
        if (!$encontrado) {
            echo "❌ $campo: FALTANTE - $descripcion\n";
        }
    }

    echo "\n=== RESUMEN ===\n";
    echo "Total de campos: " . count($columns) . "\n";
    echo "La tabla está lista para Descarga Masiva SAT.\n";
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}
