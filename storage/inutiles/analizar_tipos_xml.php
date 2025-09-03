<?php

class AnalizadorTiposXML {
    private $pdo;
    
    public function __construct() {
        // No necesitamos conexión para este análisis
        $this->pdo = null;
    }
    
    public function analizar() {
        echo "🔍 ANALIZANDO TIPOS DE XML EN DIRECTORIOS SAT\n\n";
        
        $directorios = [
            'C:\\xampp\\htdocs\\SAC\\public\\contabilidad\\sat\\BFM170822P38\\EMITIDAS',
            'C:\\xampp\\htdocs\\SAC\\public\\contabilidad\\sat\\BFM170822P38\\RECIBIDAS',
            'C:\\xampp\\htdocs\\SAC\\public\\contabilidad\\sat\\BLM1706026AA\\EMITIDAS',
            'C:\\xampp\\htdocs\\SAC\\public\\contabilidad\\sat\\BLM1706026AA\\RECIBIDAS'
        ];
        
        $muestras_por_directorio = 3;
        $tipos_encontrados = [];
        
        foreach ($directorios as $directorio) {
            if (!is_dir($directorio)) continue;
            
            echo "📁 Analizando: " . basename(dirname($directorio)) . "/" . basename($directorio) . "\n";
            
            $archivos = $this->obtenerMuestraXML($directorio, $muestras_por_directorio);
            
            foreach ($archivos as $archivo) {
                $resultado = $this->analizarXML($archivo);
                if ($resultado) {
                    $tipos_encontrados[] = $resultado;
                    echo "   📄 " . basename($archivo) . " -> " . $resultado['tipo'] . "\n";
                    echo "      Versión: " . $resultado['version'] . "\n";
                    echo "      Namespaces: " . count($resultado['namespaces']) . "\n";
                    echo "      Emisor: " . ($resultado['emisor_encontrado'] ? 'SI' : 'NO') . "\n";
                    echo "      Receptor: " . ($resultado['receptor_encontrado'] ? 'SI' : 'NO') . "\n";
                    echo "      Timbre: " . ($resultado['timbre_encontrado'] ? 'SI' : 'NO') . "\n\n";
                }
            }
        }
        
        $this->generarResumen($tipos_encontrados);
    }
    
    private function obtenerMuestraXML($directorio, $cantidad) {
        $archivos = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directorio)
        );
        
        $count = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml' && $count < $cantidad) {
                $archivos[] = $file->getPathname();
                $count++;
            }
        }
        
        return $archivos;
    }
    
    private function analizarXML($archivo) {
        try {
            $xmlContent = file_get_contents($archivo);
            if (!$xmlContent) return null;
            
            // Intentar parsear con namespaces
            $xml = simplexml_load_string($xmlContent);
            if (!$xml) return null;
            
            $resultado = [
                'archivo' => $archivo,
                'version' => (string)$xml['Version'] ?: 'N/A',
                'tipo_comprobante' => (string)$xml['TipoDeComprobante'] ?: 'N/A',
                'namespaces' => $xml->getNamespaces(true),
                'emisor_encontrado' => false,
                'receptor_encontrado' => false,
                'timbre_encontrado' => false,
                'tipo' => 'CFDI',
                'estructura' => []
            ];
            
            // Verificar versión
            if (isset($xml['Version'])) {
                $version = (string)$xml['Version'];
                if (version_compare($version, '4.0', '>=')) {
                    $resultado['tipo'] = 'CFDI 4.0';
                } else {
                    $resultado['tipo'] = 'CFDI 3.3';
                }
            }
            
            // Verificar emisor
            if (isset($xml->Emisor)) {
                $resultado['emisor_encontrado'] = true;
                $resultado['estructura']['emisor'] = [
                    'rfc' => (string)$xml->Emisor['Rfc'] ?: null,
                    'nombre' => (string)$xml->Emisor['Nombre'] ?: null,
                    'regimen_fiscal' => (string)$xml->Emisor['RegimenFiscal'] ?: null
                ];
            }
            
            // Verificar receptor
            if (isset($xml->Receptor)) {
                $resultado['receptor_encontrado'] = true;
                $resultado['estructura']['receptor'] = [
                    'rfc' => (string)$xml->Receptor['Rfc'] ?: null,
                    'nombre' => (string)$xml->Receptor['Nombre'] ?: null,
                    'uso_cfdi' => (string)$xml->Receptor['UsoCFDI'] ?: null
                ];
            }
            
            // Verificar timbre fiscal
            if (isset($xml->Complemento)) {
                $namespaces = $xml->getNamespaces(true);
                foreach ($namespaces as $prefix => $uri) {
                    if (strpos($uri, 'TimbreFiscalDigital') !== false) {
                        $resultado['timbre_encontrado'] = true;
                        break;
                    }
                }
            }
            
            // Mostrar estructura detallada del primer archivo
            if (!isset($GLOBALS['estructura_mostrada'])) {
                $GLOBALS['estructura_mostrada'] = true;
                echo "\n🔍 ESTRUCTURA DETALLADA DEL PRIMER XML:\n";
                echo "Archivo: " . basename($archivo) . "\n";
                echo "Namespaces:\n";
                foreach ($resultado['namespaces'] as $prefix => $uri) {
                    echo "  $prefix => $uri\n";
                }
                echo "\nAtributos raíz:\n";
                foreach ($xml->attributes() as $key => $value) {
                    echo "  $key => $value\n";
                }
                echo "\nElementos hijos:\n";
                foreach ($xml->children() as $child) {
                    echo "  " . $child->getName() . "\n";
                }
                echo "\n";
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            echo "Error procesando $archivo: " . $e->getMessage() . "\n";
            return null;
        }
    }
    
    private function generarResumen($tipos) {
        echo "\n📊 RESUMEN DE ANÁLISIS:\n";
        echo "========================\n";
        
        $por_version = [];
        $por_tipo = [];
        $problemas = [];
        
        foreach ($tipos as $tipo) {
            // Contar por versión
            $version = $tipo['version'];
            if (!isset($por_version[$version])) $por_version[$version] = 0;
            $por_version[$version]++;
            
            // Contar por tipo de comprobante
            $tipo_comp = $tipo['tipo_comprobante'];
            if (!isset($por_tipo[$tipo_comp])) $por_tipo[$tipo_comp] = 0;
            $por_tipo[$tipo_comp]++;
            
            // Detectar problemas
            if (!$tipo['emisor_encontrado']) {
                $problemas[] = "Emisor no encontrado en " . basename($tipo['archivo']);
            }
            if (!$tipo['receptor_encontrado']) {
                $problemas[] = "Receptor no encontrado en " . basename($tipo['archivo']);
            }
            if (!$tipo['timbre_encontrado']) {
                $problemas[] = "Timbre no encontrado en " . basename($tipo['archivo']);
            }
        }
        
        echo "Versiones encontradas:\n";
        foreach ($por_version as $version => $count) {
            echo "  CFDI $version: $count archivos\n";
        }
        
        echo "\nTipos de comprobante:\n";
        foreach ($por_tipo as $tipo => $count) {
            echo "  Tipo $tipo: $count archivos\n";
        }
        
        if (!empty($problemas)) {
            echo "\n⚠️  PROBLEMAS DETECTADOS:\n";
            foreach (array_unique($problemas) as $problema) {
                echo "  • $problema\n";
            }
        } else {
            echo "\n✅ Todos los XMLs tienen estructura válida\n";
        }
        
        echo "\n🎯 RECOMENDACIÓN PARA IMPORTADOR:\n";
        echo "- Manejar versiones CFDI 3.3 y 4.0\n";
        echo "- Usar getNamespaces() para manejar prefijos\n";
        echo "- Verificar existencia de elementos antes de acceder\n";
        echo "- Implementar fallbacks para campos opcionales\n";
    }
}

echo "Iniciando análisis de tipos de XML...\n";
$analizador = new AnalizadorTiposXML();
$analizador->analizar();
echo "\n✅ Análisis completado.\n";
?>
