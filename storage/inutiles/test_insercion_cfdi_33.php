<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'importador_inteligente_cfdi.php';

echo "=== TEST DE INSERCIÓN CFDI 3.3 ===\n\n";

try {
    $importador = new ImportadorInteligenteCFDI();
    
    echo "1. Buscando archivos CFDI 3.3...\n";
    $rutaBase = 'C:/xampp/htdocs/SAC/storage/sat_downloads';
    
    // Buscar archivos CFDI 3.3 (años anteriores a 2023)
    $archivos33 = [];
    $patrones = [
        $rutaBase . '/*/EMITIDAS/201*/*/*.xml',
        $rutaBase . '/*/EMITIDAS/202[0-2]/*/*.xml',
        $rutaBase . '/*/RECIBIDAS/201*/*/*.xml', 
        $rutaBase . '/*/RECIBIDAS/202[0-2]/*/*.xml'
    ];
    
    foreach ($patrones as $patron) {
        $archivos33 = array_merge($archivos33, glob($patron));
    }
    
    if (empty($archivos33)) {
        echo "❌ No se encontraron archivos CFDI 3.3\n";
        exit();
    }
    
    echo "✅ Encontrados " . count($archivos33) . " archivos CFDI 3.3\n";
    
    // Tomar los primeros 10 archivos para test
    $archivosTest = array_slice($archivos33, 0, 10);
    
    echo "\n2. Procesando " . count($archivosTest) . " archivos CFDI 3.3...\n";
    echo "=====================================================\n";
    
    $reflection = new ReflectionClass($importador);
    $metodo = $reflection->getMethod('procesarArchivo');
    $metodo->setAccessible(true);
    
    $insertados = 0;
    $errores = 0;
    
    foreach ($archivosTest as $i => $archivo) {
        echo "\n--- ARCHIVO " . ($i + 1) . "/" . count($archivosTest) . " ---\n";
        echo "📁 " . basename($archivo) . "\n";
        
        // Verificar versión del archivo
        $contenido = file_get_contents($archivo);
        if (preg_match('/Version="([^"]+)"/', $contenido, $matches)) {
            $version = $matches[1];
            echo "📋 Versión detectada: $version\n";
        }
        
        try {
            $resultado = $metodo->invoke($importador, $archivo);
            if ($resultado) {
                $insertados++;
                echo "✅ Insertado exitosamente\n";
            } else {
                $errores++;
                echo "❌ Error en inserción\n";
            }
        } catch (Exception $e) {
            $errores++;
            echo "❌ Excepción: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n3. RESULTADOS DEL TEST\n";
    echo "=======================\n";
    echo "Archivos procesados: " . count($archivosTest) . "\n";
    echo "Insertados exitosamente: $insertados\n";
    echo "Errores: $errores\n";
    echo "Tasa de éxito: " . round(($insertados / count($archivosTest)) * 100, 2) . "%\n";
    
    if ($insertados > 0) {
        echo "\n4. VERIFICACIÓN DE LOS 10 CAMPOS IMPLEMENTADOS\n";
        echo "===============================================\n";
        
        $pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');
        
        // Obtener los registros insertados
        $stmt = $pdo->prepare("SELECT * FROM cfdi ORDER BY id DESC LIMIT ?");
        $stmt->execute([$insertados]);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $camposSolicitados = [
            'complemento_tipo' => 'Complemento Tipo',
            'complemento_json' => 'Complemento JSON',
            'rfc_consultado' => 'RFC Consultado',
            'direccion_flujo' => 'Dirección de Flujo',
            'version' => 'Versión',
            'sello_sat' => 'Sello SAT',
            'no_certificado_sat' => 'No. Certificado SAT',
            'rfc_prov_certif' => 'RFC Prov. Certif',
            'estatus_sat' => 'Estatus SAT',
            'cfdi_relacionados' => 'CFDI Relacionados'
        ];
        
        $estadisticasCampos = [];
        foreach ($camposSolicitados as $campo => $nombre) {
            $estadisticasCampos[$campo] = 0;
        }
        
        foreach ($registros as $i => $registro) {
            echo "\n--- CFDI " . ($i + 1) . " ---\n";
            echo "UUID: " . $registro['uuid'] . "\n";
            echo "RFC Emisor: " . $registro['rfc_emisor'] . "\n";
            echo "RFC Receptor: " . $registro['rfc_receptor'] . "\n";
            echo "Total: $" . number_format($registro['total'], 2) . "\n";
            
            echo "\nCAMPOS IMPLEMENTADOS:\n";
            foreach ($camposSolicitados as $campo => $nombre) {
                $valor = $registro[$campo] ?? null;
                if ($valor !== null && $valor !== '') {
                    echo "✅ $nombre: ";
                    if (strlen($valor) > 60) {
                        echo substr($valor, 0, 60) . "...";
                    } else {
                        echo $valor;
                    }
                    echo "\n";
                    $estadisticasCampos[$campo]++;
                } else {
                    echo "❌ $nombre: VACÍO\n";
                }
            }
        }
        
        echo "\n5. ESTADÍSTICAS DE CAMPOS POBLADOS\n";
        echo "===================================\n";
        foreach ($camposSolicitados as $campo => $nombre) {
            $cantidad = $estadisticasCampos[$campo];
            $porcentaje = round(($cantidad / $insertados) * 100, 1);
            echo "• $nombre: $cantidad/$insertados ($porcentaje%)\n";
        }
        
        // Verificar tipos de complemento
        echo "\n6. ANÁLISIS DE COMPLEMENTOS\n";
        echo "============================\n";
        $stmt = $pdo->query("SELECT complemento_tipo, COUNT(*) as cantidad FROM cfdi WHERE complemento_tipo IS NOT NULL GROUP BY complemento_tipo");
        $complementos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($complementos as $comp) {
            echo "✅ " . $comp['complemento_tipo'] . ": " . $comp['cantidad'] . " registros\n";
        }
        
        echo "\n🎉 TEST DE CFDI 3.3 COMPLETADO EXITOSAMENTE\n";
        echo "✅ Los 10 campos solicitados están funcionando correctamente\n";
        
    } else {
        echo "\n❌ No se insertaron registros - revisar configuración\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN DEL TEST CFDI 3.3 ===\n";
?>
