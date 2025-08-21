<?php

// Conexión directa a la base de datos
$host = 'localhost';
$dbname = 'sac_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== VERIFICACIÓN DE ESTRUCTURA DE BASE DE DATOS ===\n\n";
    
    // 1. Mostrar estructura de la tabla cfdi
    $sql = "DESCRIBE cfdi";
    $stmt = $pdo->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "1. Campos existentes en tabla 'cfdi':\n";
    foreach ($columns as $column) {
        echo "   - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // 2. Verificar los campos objetivo
    echo "\n2. Verificación de campos objetivo:\n";
    $campos_objetivo = [
        'complemento_tipo', 'complemento_json', 'rfc_consultado', 
        'direccion_de_flujo', 'version', 'sello_sat', 
        'no_certificado_sat', 'rfc_prov_certif', 'estatus_sat', 'cfdi_relacionados'
    ];
    
    $campos_existentes = array_column($columns, 'Field');
    
    foreach ($campos_objetivo as $campo) {
        $existe = in_array($campo, $campos_existentes);
        $status = $existe ? '✅' : '❌';
        echo "   $status $campo\n";
    }
    
    // 3. Verificar si hay registros recientes
    $sql = "SELECT COUNT(*) FROM cfdi WHERE id > 2020";
    $stmt = $pdo->query($sql);
    $count = $stmt->fetchColumn();
    echo "\n3. Registros CFDI recientes (ID > 2020): $count\n";
    
    if ($count > 0) {
        $sql = "SELECT id, uuid, version, fecha_timbrado FROM cfdi WHERE id > 2020 ORDER BY id DESC LIMIT 3";
        $stmt = $pdo->query($sql);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n4. Últimos registros insertados:\n";
        foreach ($registros as $registro) {
            echo "   - ID: {$registro['id']}, UUID: {$registro['uuid']}, Versión: {$registro['version']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
