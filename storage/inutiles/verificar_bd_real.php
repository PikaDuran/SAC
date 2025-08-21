<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== VERIFICACIÓN DIRECTA DE BASE DE DATOS ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. CONTEO TOTAL DE REGISTROS\n";
    echo "=============================\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi");
    $total = $stmt->fetchColumn();
    echo "Total registros en CFDI: $total\n\n";
    
    echo "2. REGISTROS POR AÑO (BASADO EN FECHA)\n";
    echo "======================================\n";
    $stmt = $pdo->query("
        SELECT 
            YEAR(fecha) as año,
            COUNT(*) as cantidad,
            MIN(fecha) as fecha_min,
            MAX(fecha) as fecha_max
        FROM cfdi 
        GROUP BY YEAR(fecha) 
        ORDER BY año DESC
    ");
    $por_año = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($por_año as $año) {
        echo "Año {$año['año']}: {$año['cantidad']} registros\n";
        echo "  Rango: {$año['fecha_min']} a {$año['fecha_max']}\n";
    }
    
    echo "\n3. REGISTROS POR VERSIÓN CFDI\n";
    echo "==============================\n";
    $stmt = $pdo->query("
        SELECT 
            version,
            COUNT(*) as cantidad,
            MIN(fecha) as fecha_min,
            MAX(fecha) as fecha_max
        FROM cfdi 
        GROUP BY version 
        ORDER BY version
    ");
    $por_version = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($por_version as $version) {
        echo "CFDI {$version['version']}: {$version['cantidad']} registros\n";
        echo "  Fechas: {$version['fecha_min']} a {$version['fecha_max']}\n";
    }
    
    echo "\n4. ÚLTIMOS 10 REGISTROS INSERTADOS\n";
    echo "===================================\n";
    $stmt = $pdo->query("
        SELECT 
            id,
            uuid,
            version,
            fecha,
            rfc_emisor,
            total,
            archivo_xml
        FROM cfdi 
        ORDER BY id DESC 
        LIMIT 10
    ");
    $ultimos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($ultimos as $reg) {
        echo "\n--- REGISTRO ID: {$reg['id']} ---\n";
        echo "UUID: {$reg['uuid']}\n";
        echo "Versión: {$reg['version']}\n";
        echo "Fecha: {$reg['fecha']}\n";
        echo "RFC Emisor: {$reg['rfc_emisor']}\n";
        echo "Total: {$reg['total']}\n";
        echo "Archivo: " . ($reg['archivo_xml'] ?? 'NULL') . "\n";
    }
    
    echo "\n5. BUSCAR REGISTROS DE 2025\n";
    echo "============================\n";
    $stmt = $pdo->query("
        SELECT 
            id,
            uuid,
            version,
            fecha,
            rfc_emisor
        FROM cfdi 
        WHERE YEAR(fecha) = 2025
        ORDER BY fecha DESC
        LIMIT 5
    ");
    $registros_2025 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($registros_2025)) {
        echo "❌ NO HAY REGISTROS DE 2025 EN LA BASE DE DATOS\n";
    } else {
        echo "✅ Registros de 2025 encontrados: " . count($registros_2025) . "\n";
        foreach ($registros_2025 as $reg) {
            echo "- ID {$reg['id']}: {$reg['uuid']} | Versión {$reg['version']} | {$reg['fecha']}\n";
        }
    }
    
    echo "\n6. BUSCAR REGISTROS DE 2020\n";
    echo "============================\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM cfdi 
        WHERE YEAR(fecha) = 2020
    ");
    $registros_2020 = $stmt->fetchColumn();
    echo "Registros de 2020: $registros_2020\n";
    
    echo "\n7. VERIFICAR LOS UUIDs MENCIONADOS EN EL TEST\n";
    echo "==============================================\n";
    $uuids_test = [
        '0D157DDC-7E07-4FD8-B37A-BAE0105F3F1C',
        '3B08E7A2-3EB7-46BD-8849-4675C03B718E',
        '728F325C-7365-4B96-B2A5-BAEB534A2CE0'
    ];
    
    foreach ($uuids_test as $uuid) {
        $stmt = $pdo->prepare("
            SELECT id, version, fecha, rfc_emisor, archivo_xml
            FROM cfdi 
            WHERE uuid = ?
        ");
        $stmt->execute([$uuid]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado) {
            echo "✅ UUID $uuid ENCONTRADO:\n";
            echo "   ID: {$resultado['id']}\n";
            echo "   Versión: {$resultado['version']}\n";
            echo "   Fecha: {$resultado['fecha']}\n";
            echo "   RFC: {$resultado['rfc_emisor']}\n";
            echo "   Archivo: " . ($resultado['archivo_xml'] ?? 'NULL') . "\n\n";
        } else {
            echo "❌ UUID $uuid NO ENCONTRADO\n\n";
        }
    }
    
    echo "8. RESUMEN FINAL\n";
    echo "================\n";
    echo "La verificación directa de la base de datos muestra:\n";
    
    if (empty($registros_2025)) {
        echo "❌ NO hay registros de 2025 - todos los datos son históricos\n";
        echo "❌ El test anterior mostró información incorrecta\n";
        echo "❌ Los CFDI 4.0 no se insertaron realmente\n";
    } else {
        echo "✅ SÍ hay registros de 2025\n";
        echo "✅ Los CFDI 4.0 se insertaron correctamente\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE VERIFICACIÓN DIRECTA ===\n";
?>
