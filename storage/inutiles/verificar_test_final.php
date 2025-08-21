<?php

// Conexión directa a la base de datos
$host = 'localhost';
$dbname = 'sac_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== VERIFICACIÓN DEL TEST FINAL ===\n\n";
    
    // 1. Verificar el registro insertado
    $sql = "SELECT COUNT(*) FROM cfdi WHERE id >= 2024";
    $stmt = $pdo->query($sql);
    $count = $stmt->fetchColumn();
    echo "1. Registros CFDI insertados (ID >= 2024): $count\n";
    
    // 2. Obtener datos del último registro
    $sql = "SELECT id, uuid, version, fecha_timbrado, complemento_tipo, complemento_json, 
                   rfc_consultado, direccion_flujo, sello_sat, no_certificado_sat, 
                   rfc_prov_certif, estatus_sat, cfdi_relacionados 
            FROM cfdi WHERE id >= 2024 ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->query($sql);
    $cfdi = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cfdi) {
        echo "\n2. Datos del CFDI insertado:\n";
        echo "   - ID: " . $cfdi['id'] . "\n";
        echo "   - UUID: " . $cfdi['uuid'] . "\n";
        echo "   - Versión: " . $cfdi['version'] . "\n";
        echo "   - Fecha timbrado: " . $cfdi['fecha_timbrado'] . "\n";
        echo "   - Complemento tipo: " . ($cfdi['complemento_tipo'] ?? 'NULL') . "\n";
        echo "   - Complemento JSON: " . (strlen($cfdi['complemento_json'] ?? '') > 0 ? 'Datos presentes' : 'NULL') . "\n";
        echo "   - RFC consultado: " . ($cfdi['rfc_consultado'] ?? 'NULL') . "\n";
        echo "   - Dirección flujo: " . ($cfdi['direccion_flujo'] ?? 'NULL') . "\n";
        echo "   - Sello SAT: " . (strlen($cfdi['sello_sat'] ?? '') > 0 ? 'Presente' : 'NULL') . "\n";
        echo "   - No. Cert. SAT: " . ($cfdi['no_certificado_sat'] ?? 'NULL') . "\n";
        echo "   - RFC Prov Certif: " . ($cfdi['rfc_prov_certif'] ?? 'NULL') . "\n";
        echo "   - Estatus SAT: " . ($cfdi['estatus_sat'] ?? 'NULL') . "\n";
        echo "   - CFDI relacionados: " . ($cfdi['cfdi_relacionados'] ?? 'NULL') . "\n";
    }
    
    // 3. Verificar conceptos
    $sql = "SELECT COUNT(*) FROM cfdi_conceptos WHERE cfdi_id >= 2024";
    $stmt = $pdo->query($sql);
    $conceptos = $stmt->fetchColumn();
    echo "\n3. Conceptos insertados: $conceptos\n";
    
    // 4. Verificar impuestos
    $sql = "SELECT COUNT(*) FROM cfdi_impuestos WHERE cfdi_id >= 2024";
    $stmt = $pdo->query($sql);
    $impuestos = $stmt->fetchColumn();
    echo "4. Impuestos insertados: $impuestos\n";
    
    // 5. Mostrar los 10 campos implementados
    echo "\n=== VERIFICACIÓN DE LOS 10 CAMPOS IMPLEMENTADOS ===\n";
    $campos_objetivo = [
        'complemento_tipo', 'complemento_json', 'rfc_consultado', 
        'direccion_flujo', 'version', 'sello_sat', 
        'no_certificado_sat', 'rfc_prov_certif', 'estatus_sat', 'cfdi_relacionados'
    ];
    
    $implementados = 0;
    foreach ($campos_objetivo as $campo) {
        $valor = $cfdi[$campo] ?? null;
        $status = $valor !== null ? '✅' : '❌';
        if ($valor !== null) $implementados++;
        echo "   $status $campo: " . ($valor ? 'IMPLEMENTADO' : 'NULL') . "\n";
    }
    
    echo "\n=== RESUMEN FINAL ===\n";
    echo "Campos implementados: $implementados/10\n";
    echo "CFDI insertado exitosamente: " . ($count > 0 ? 'SÍ' : 'NO') . "\n";
    echo "Sistema funcionando correctamente: " . ($implementados >= 8 && $count > 0 ? 'SÍ' : 'NO') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
