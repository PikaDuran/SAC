<?php
/**
 * ANÁLISIS EXHAUSTIVO DE COMPLEMENTOS CFDI
 * Busca TODOS los tipos de complementos en cada versión y tipo
 */

echo "🔍 ANÁLISIS EXHAUSTIVO DE COMPLEMENTOS CFDI\n";
echo str_repeat("=", 100) . "\n\n";

// Función para buscar todos los XMLs y analizar complementos
function buscarComplementosEnDirectorio($ruta, $tipo_carpeta, $year) {
    $pattern = "$ruta\\*\\$tipo_carpeta\\$year\\*\\*.xml";
    $archivos = glob($pattern);
    
    $complementos_encontrados = [];
    $total_archivos = 0;
    
    echo "📂 Analizando $tipo_carpeta $year:\n";
    echo "   Patrón: $pattern\n";
    echo "   Archivos encontrados: " . count($archivos) . "\n";
    
    foreach ($archivos as $archivo) {
        $total_archivos++;
        if ($total_archivos > 20) break; // Limitar para no procesar todo
        
        $contenido = file_get_contents($archivo);
        if ($contenido === false) continue;
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contenido);
        if ($xml === false) continue;
        
        // Registrar namespaces
        $namespaces = $xml->getNamespaces(true);
        foreach ($namespaces as $prefix => $uri) {
            if (empty($prefix)) {
                $xml->registerXPathNamespace('cfdi', $uri);
            } else {
                $xml->registerXPathNamespace($prefix, $uri);
            }
        }
        
        // Buscar complementos
        $complementos = $xml->xpath('//cfdi:Complemento/*');
        
        foreach ($complementos as $comp) {
            $nombre = $comp->getName();
            $version = '';
            
            // Intentar obtener versión del complemento
            if (isset($comp['Version'])) {
                $version = (string)$comp['Version'];
            }
            
            $clave = $nombre . ($version ? "_v$version" : '');
            
            if (!isset($complementos_encontrados[$clave])) {
                $complementos_encontrados[$clave] = [
                    'nombre' => $nombre,
                    'version' => $version,
                    'count' => 0,
                    'archivos' => [],
                    'namespaces' => [],
                    'atributos' => []
                ];
            }
            
            $complementos_encontrados[$clave]['count']++;
            $complementos_encontrados[$clave]['archivos'][] = basename($archivo);
            
            // Guardar namespace
            foreach ($namespaces as $prefix => $uri) {
                if (strpos($uri, $nombre) !== false || strpos(strtolower($uri), strtolower($nombre)) !== false) {
                    $complementos_encontrados[$clave]['namespaces'][$uri] = true;
                }
            }
            
            // Guardar atributos del complemento
            foreach ($comp->attributes() as $attr => $valor) {
                $complementos_encontrados[$clave]['atributos'][$attr] = (string)$valor;
            }
        }
    }
    
    echo "   📊 Archivos procesados: $total_archivos\n";
    echo "   🎯 Complementos únicos: " . count($complementos_encontrados) . "\n\n";
    
    // Mostrar complementos encontrados
    if (!empty($complementos_encontrados)) {
        foreach ($complementos_encontrados as $clave => $info) {
            echo "   📦 {$info['nombre']}";
            if ($info['version']) {
                echo " (v{$info['version']})";
            }
            echo " - Encontrado en {$info['count']} archivos\n";
            
            // Mostrar namespaces
            if (!empty($info['namespaces'])) {
                foreach ($info['namespaces'] as $ns => $dummy) {
                    echo "      🔗 Namespace: " . basename($ns) . "\n";
                }
            }
            
            // Mostrar algunos atributos
            if (!empty($info['atributos'])) {
                echo "      📋 Atributos: " . implode(', ', array_keys($info['atributos'])) . "\n";
            }
            echo "\n";
        }
    }
    
    return $complementos_encontrados;
}

// Analizar todas las combinaciones
$base_path = 'C:\\xampp\\htdocs\\SAC\\storage\\sat_downloads';

$analisis_completo = [
    'CFDI_33_EMITIDAS' => buscarComplementosEnDirectorio($base_path, 'EMITIDAS', '2022'),
    'CFDI_33_RECIBIDAS' => buscarComplementosEnDirectorio($base_path, 'RECIBIDAS', '2022'),
    'CFDI_40_EMITIDAS' => buscarComplementosEnDirectorio($base_path, 'EMITIDAS', '2024'),
    'CFDI_40_RECIBIDAS' => buscarComplementosEnDirectorio($base_path, 'RECIBIDAS', '2024')
];

// TABLA COMPARATIVA DE COMPLEMENTOS
echo str_repeat("=", 120) . "\n";
echo "📊 TABLA COMPARATIVA DE COMPLEMENTOS POR TIPO\n";
echo str_repeat("=", 120) . "\n\n";

// Recopilar todos los complementos únicos
$todos_complementos = [];
foreach ($analisis_completo as $tipo => $complementos) {
    foreach ($complementos as $clave => $info) {
        $todos_complementos[$clave] = $info['nombre'] . ($info['version'] ? " v{$info['version']}" : '');
    }
}

ksort($todos_complementos);

// Encabezados
echo sprintf("%-40s | %-15s | %-15s | %-15s | %-15s\n", 
    "COMPLEMENTO", 
    "3.3 EMIT", 
    "3.3 RECIB",
    "4.0 EMIT", 
    "4.0 RECIB"
);
echo str_repeat("-", 120) . "\n";

// Mostrar cada complemento
foreach ($todos_complementos as $clave => $descripcion) {
    $cfdi33_emit = isset($analisis_completo['CFDI_33_EMITIDAS'][$clave]) ? 
        "✅ ({$analisis_completo['CFDI_33_EMITIDAS'][$clave]['count']})" : '❌';
    $cfdi33_recib = isset($analisis_completo['CFDI_33_RECIBIDAS'][$clave]) ? 
        "✅ ({$analisis_completo['CFDI_33_RECIBIDAS'][$clave]['count']})" : '❌';
    $cfdi40_emit = isset($analisis_completo['CFDI_40_EMITIDAS'][$clave]) ? 
        "✅ ({$analisis_completo['CFDI_40_EMITIDAS'][$clave]['count']})" : '❌';
    $cfdi40_recib = isset($analisis_completo['CFDI_40_RECIBIDAS'][$clave]) ? 
        "✅ ({$analisis_completo['CFDI_40_RECIBIDAS'][$clave]['count']})" : '❌';
    
    echo sprintf("%-40s | %-15s | %-15s | %-15s | %-15s\n", 
        substr($descripcion, 0, 39), 
        $cfdi33_emit, 
        $cfdi33_recib,
        $cfdi40_emit, 
        $cfdi40_recib
    );
}

// RESUMEN DETALLADO POR TIPO
echo "\n" . str_repeat("=", 120) . "\n";
echo "📋 RESUMEN DETALLADO POR TIPO\n";
echo str_repeat("=", 120) . "\n\n";

foreach ($analisis_completo as $tipo => $complementos) {
    echo "🔸 $tipo:\n";
    echo "   Total complementos únicos: " . count($complementos) . "\n";
    
    foreach ($complementos as $clave => $info) {
        echo "   📦 {$info['nombre']}";
        if ($info['version']) {
            echo " v{$info['version']}";
        }
        echo " ({$info['count']} archivos)\n";
    }
    echo "\n";
}

// EVOLUCIÓN DE COMPLEMENTOS
echo str_repeat("=", 120) . "\n";
echo "📈 EVOLUCIÓN DE COMPLEMENTOS 3.3 → 4.0\n";
echo str_repeat("=", 120) . "\n\n";

echo "🔍 COMPLEMENTOS QUE CAMBIARON DE VERSIÓN:\n";
foreach ($todos_complementos as $clave => $descripcion) {
    $nombre_base = explode('_v', $clave)[0];
    
    // Buscar si hay diferentes versiones del mismo complemento
    $versiones_encontradas = [];
    foreach ($todos_complementos as $otra_clave => $otra_desc) {
        if (strpos($otra_clave, $nombre_base) === 0) {
            $versiones_encontradas[] = $otra_clave;
        }
    }
    
    if (count($versiones_encontradas) > 1) {
        echo "   📦 $nombre_base tiene múltiples versiones:\n";
        foreach ($versiones_encontradas as $version_clave) {
            echo "      • $version_clave\n";
        }
        echo "\n";
    }
}

// GUARDAR REPORTE
$fecha = date('Y-m-d_H-i-s');
$archivo_reporte = "ANALISIS_COMPLEMENTOS_COMPLETO_$fecha.txt";

ob_start();
echo "ANÁLISIS EXHAUSTIVO DE COMPLEMENTOS CFDI\n";
echo "Generado: $fecha\n";
echo str_repeat("=", 80) . "\n\n";

echo "RESUMEN TOTAL:\n";
foreach ($analisis_completo as $tipo => $complementos) {
    echo "- $tipo: " . count($complementos) . " tipos de complementos\n";
}

echo "\nCOMPLEMENTOS ENCONTRADOS:\n";
foreach ($todos_complementos as $clave => $descripcion) {
    echo "• $descripcion\n";
}

$contenido_reporte = ob_get_clean();
file_put_contents($archivo_reporte, $contenido_reporte);

echo "💾 REPORTE GUARDADO EN: $archivo_reporte\n";
echo "📊 TOTAL TIPOS DE COMPLEMENTOS: " . count($todos_complementos) . "\n";
echo "\n✅ ANÁLISIS EXHAUSTIVO COMPLETADO\n";
?>
