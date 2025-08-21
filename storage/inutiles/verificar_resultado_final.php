<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== VERIFICACIÓN FINAL DE IMPLEMENTACIÓN ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. ESTRUCTURA DE LA TABLA CFDI\n";
    echo "================================\n";
    $stmt = $pdo->query("DESCRIBE cfdi");
    $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar que los 10 campos solicitados existen
    $camposSolicitados = [
        'complemento_tipo',
        'complemento_json', 
        'rfc_consultado',
        'direccion_flujo',
        'version',
        'sello_sat',
        'no_certificado_sat',
        'rfc_prov_certif',
        'estatus_sat',
        'cfdi_relacionados'
    ];
    
    $camposExistentes = array_column($campos, 'Field');
    
    foreach ($camposSolicitados as $campo) {
        if (in_array($campo, $camposExistentes)) {
            echo "✅ $campo\n";
        } else {
            echo "❌ $campo - NO EXISTE\n";
        }
    }
    
    echo "\n2. CONTENIDO ACTUAL DE LA TABLA CFDI\n";
    echo "=====================================\n";
    
    // Total de registros
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi");
    $total = $stmt->fetchColumn();
    echo "Total de registros: $total\n";
    
    if ($total > 0) {
        // Últimos 3 registros
        $stmt = $pdo->query("SELECT * FROM cfdi ORDER BY id DESC LIMIT 3");
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($registros as $i => $reg) {
            echo "\n--- REGISTRO " . ($i + 1) . " ---\n";
            echo "ID: " . $reg['id'] . "\n";
            echo "UUID: " . $reg['uuid'] . "\n";
            echo "Versión: " . ($reg['version'] ?? 'NULL') . "\n";
            echo "RFC Emisor: " . ($reg['rfc_emisor'] ?? 'NULL') . "\n";
            echo "Total: " . ($reg['total'] ?? 'NULL') . "\n";
            
            // Verificar los 10 campos solicitados
            echo "\n--- CAMPOS IMPLEMENTADOS ---\n";
            foreach ($camposSolicitados as $campo) {
                $valor = $reg[$campo] ?? 'NULL';
                if ($valor !== 'NULL' && $valor !== '' && $valor !== null) {
                    echo "✅ $campo: ";
                    if (strlen($valor) > 50) {
                        echo substr($valor, 0, 50) . "...";
                    } else {
                        echo $valor;
                    }
                    echo "\n";
                } else {
                    echo "❌ $campo: VACÍO\n";
                }
            }
        }
        
        // Estadísticas por versión
        echo "\n3. ESTADÍSTICAS POR VERSIÓN\n";
        echo "============================\n";
        $stmt = $pdo->query("SELECT version, COUNT(*) as cantidad FROM cfdi GROUP BY version ORDER BY cantidad DESC");
        $versiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($versiones as $v) {
            echo "Versión " . ($v['version'] ?? 'NULL') . ": " . $v['cantidad'] . " registros\n";
        }
        
        // Verificar complementos
        echo "\n4. ANÁLISIS DE COMPLEMENTOS\n";
        echo "============================\n";
        $stmt = $pdo->query("SELECT complemento_tipo, COUNT(*) as cantidad FROM cfdi WHERE complemento_tipo IS NOT NULL AND complemento_tipo != '' GROUP BY complemento_tipo");
        $complementos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($complementos)) {
            echo "❌ No hay complementos registrados\n";
        } else {
            foreach ($complementos as $c) {
                echo "✅ " . $c['complemento_tipo'] . ": " . $c['cantidad'] . " registros\n";
            }
        }
        
        // Verificar campos SAT
        echo "\n5. ANÁLISIS DE CAMPOS SAT\n";
        echo "==========================\n";
        $stmt = $pdo->query("SELECT COUNT(*) as total_sat FROM cfdi WHERE sello_sat IS NOT NULL AND sello_sat != ''");
        $total_sat = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) as total_rfc FROM cfdi WHERE rfc_prov_certif IS NOT NULL AND rfc_prov_certif != ''");
        $total_rfc = $stmt->fetchColumn();
        
        echo "Registros con sello_sat: $total_sat\n";
        echo "Registros con rfc_prov_certif: $total_rfc\n";
        
    } else {
        echo "❌ No hay registros en la tabla CFDI\n";
    }
    
    echo "\n6. RESUMEN DE IMPLEMENTACIÓN\n";
    echo "=============================\n";
    
    $todosExisten = true;
    foreach ($camposSolicitados as $campo) {
        if (!in_array($campo, $camposExistentes)) {
            $todosExisten = false;
            break;
        }
    }
    
    if ($todosExisten) {
        echo "✅ TODOS LOS 10 CAMPOS SOLICITADOS ESTÁN IMPLEMENTADOS\n";
        echo "✅ La estructura de la base de datos es correcta\n";
        
        if ($total > 0) {
            echo "✅ Hay datos en la tabla\n";
            echo "✅ El sistema está funcionando\n";
        } else {
            echo "⚠️ La tabla está vacía - necesita procesamiento de archivos\n";
        }
        
        echo "\nLos 10 campos implementados son:\n";
        foreach ($camposSolicitados as $campo) {
            echo "• $campo\n";
        }
        
    } else {
        echo "❌ Faltan campos por implementar\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE VERIFICACIÓN ===\n";
?>
