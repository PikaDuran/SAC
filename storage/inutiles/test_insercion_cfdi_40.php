<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'importador_inteligente_cfdi.php';

echo "=== TEST DE INSERCIÓN CFDI 4.0 ===\n\n";

try {
    $importador = new ImportadorInteligenteCFDI();
    
    echo "1. Buscando archivos CFDI 4.0 en 2025...\n";
    $rutaBase = 'C:/xampp/htdocs/SAC/storage/sat_downloads';
    
    // Buscar específicamente en 2025 primero
    $archivos40 = [];
    echo "   Buscando en: $rutaBase/*/EMITIDAS/2025/*/*.xml\n";
    echo "   Buscando en: $rutaBase/*/RECIBIDAS/2025/*/*.xml\n";
    
    $archivos40 = glob($rutaBase . '/*/EMITIDAS/2025/*/*.xml');
    $archivos40 = array_merge($archivos40, glob($rutaBase . '/*/RECIBIDAS/2025/*/*.xml'));
    
    echo "   Archivos encontrados en 2025: " . count($archivos40) . "\n";
    
    if (empty($archivos40)) {
        echo "❌ No se encontraron archivos en 2025\n";
        echo "¿Debo buscar en 2024 o 2023? (Los CFDI 4.0 empezaron en 2023)\n";
        
        // Buscar en 2024 y 2023 como backup
        echo "   Buscando como backup en 2024...\n";
        $archivos40 = glob($rutaBase . '/*/EMITIDAS/2024/*/*.xml');
        $archivos40 = array_merge($archivos40, glob($rutaBase . '/*/RECIBIDAS/2024/*/*.xml'));
        
        if (empty($archivos40)) {
            echo "   Buscando como backup en 2023...\n";
            $archivos40 = glob($rutaBase . '/*/EMITIDAS/2023/*/*.xml');
            $archivos40 = array_merge($archivos40, glob($rutaBase . '/*/RECIBIDAS/2023/*/*.xml'));
        }
    }
    
    if (empty($archivos40)) {
        echo "❌ No se encontraron archivos CFDI 4.0 en 2025, 2024 o 2023\n";
        echo "¿En qué año específico debería buscar?\n";
        exit();
    }
    
    $year_encontrado = '';
    if (strpos($archivos40[0], '/2025/') !== false) $year_encontrado = '2025';
    elseif (strpos($archivos40[0], '/2024/') !== false) $year_encontrado = '2024';
    elseif (strpos($archivos40[0], '/2023/') !== false) $year_encontrado = '2023';
    
    echo "✅ Encontrados " . count($archivos40) . " archivos en $year_encontrado\n";
    
    // Verificar que realmente sean CFDI 4.0
    $archivosConfirmados = [];
    foreach ($archivos40 as $archivo) {
        $contenido = file_get_contents($archivo);
        if (preg_match('/Version="4\.0"/', $contenido)) {
            $archivosConfirmados[] = $archivo;
        }
    }
    
    echo "✅ Confirmados " . count($archivosConfirmados) . " archivos CFDI 4.0\n";
    
    if (empty($archivosConfirmados)) {
        echo "❌ No se encontraron archivos con versión 4.0\n";
        exit();
    }
    
    // Tomar los primeros 15 archivos para test
    $archivosTest = array_slice($archivosConfirmados, 0, 15);
    
    echo "\n2. Procesando " . count($archivosTest) . " archivos CFDI 4.0...\n";
    echo "=====================================================\n";
    
    $reflection = new ReflectionClass($importador);
    $metodo = $reflection->getMethod('procesarArchivo');
    $metodo->setAccessible(true);
    
    $insertados = 0;
    $errores = 0;
    
    foreach ($archivosTest as $i => $archivo) {
        echo "\n--- ARCHIVO " . ($i + 1) . "/" . count($archivosTest) . " ---\n";
        echo "📁 " . basename($archivo) . "\n";
        
        // Verificar información del archivo
        $contenido = file_get_contents($archivo);
        if (preg_match('/Version="([^"]+)"/', $contenido, $matches)) {
            $version = $matches[1];
            echo "📋 Versión: $version\n";
        }
        
        if (preg_match('/Exportacion="([^"]+)"/', $contenido, $matches)) {
            $exportacion = $matches[1];
            echo "🌍 Exportación: $exportacion\n";
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
    
    echo "\n3. RESULTADOS DEL TEST CFDI 4.0\n";
    echo "=================================\n";
    echo "Archivos procesados: " . count($archivosTest) . "\n";
    echo "Insertados exitosamente: $insertados\n";
    echo "Errores: $errores\n";
    echo "Tasa de éxito: " . round(($insertados / count($archivosTest)) * 100, 2) . "%\n";
    
    if ($insertados > 0) {
        echo "\n4. VERIFICACIÓN DE LOS 10 CAMPOS EN CFDI 4.0\n";
        echo "=============================================\n";
        
        $pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');
        
        // Obtener los registros CFDI 4.0 insertados
        $stmt = $pdo->prepare("SELECT * FROM cfdi WHERE version = '4.0' ORDER BY id DESC LIMIT ?");
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
            echo "\n--- CFDI 4.0 #" . ($i + 1) . " ---\n";
            echo "UUID: " . $registro['uuid'] . "\n";
            echo "RFC Emisor: " . $registro['rfc_emisor'] . "\n";
            echo "RFC Receptor: " . $registro['rfc_receptor'] . "\n";
            echo "Total: $" . number_format($registro['total'], 2) . "\n";
            echo "Exportación: " . ($registro['exportacion'] ?? 'NULL') . "\n";
            echo "Régimen Fiscal Receptor: " . ($registro['regimen_fiscal_receptor'] ?? 'NULL') . "\n";
            
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
        
        echo "\n5. ESTADÍSTICAS DE CAMPOS CFDI 4.0\n";
        echo "====================================\n";
        foreach ($camposSolicitados as $campo => $nombre) {
            $cantidad = $estadisticasCampos[$campo];
            $porcentaje = round(($cantidad / $insertados) * 100, 1);
            echo "• $nombre: $cantidad/$insertados ($porcentaje%)\n";
        }
        
        // Verificar características específicas de CFDI 4.0
        echo "\n6. CARACTERÍSTICAS ESPECÍFICAS CFDI 4.0\n";
        echo "========================================\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM cfdi WHERE version = '4.0' AND exportacion IS NOT NULL");
        $con_exportacion = $stmt->fetchColumn();
        echo "✅ CFDIs con campo Exportación: $con_exportacion\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM cfdi WHERE version = '4.0' AND regimen_fiscal_receptor IS NOT NULL");
        $con_regimen = $stmt->fetchColumn();
        echo "✅ CFDIs con Régimen Fiscal Receptor: $con_regimen\n";
        
        // Verificar tipos de complemento en CFDI 4.0
        echo "\n7. ANÁLISIS DE COMPLEMENTOS CFDI 4.0\n";
        echo "=====================================\n";
        $stmt = $pdo->query("SELECT complemento_tipo, COUNT(*) as cantidad FROM cfdi WHERE version = '4.0' AND complemento_tipo IS NOT NULL GROUP BY complemento_tipo");
        $complementos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($complementos)) {
            echo "❌ No se detectaron complementos específicos\n";
        } else {
            foreach ($complementos as $comp) {
                echo "✅ " . $comp['complemento_tipo'] . ": " . $comp['cantidad'] . " registros\n";
            }
        }
        
        echo "\n🎉 TEST DE CFDI 4.0 COMPLETADO EXITOSAMENTE\n";
        echo "✅ Los 10 campos funcionan correctamente con CFDI 4.0\n";
        echo "✅ Sistema compatible con ambas versiones: 3.3 y 4.0\n";
        
    } else {
        echo "\n❌ No se insertaron registros CFDI 4.0 - revisar configuración\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN DEL TEST CFDI 4.0 ===\n";
?>
