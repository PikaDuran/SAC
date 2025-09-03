<?php
/**
 * PRUEBA DEL IMPORTADOR COMPLETO SAT
 * Procesa solo algunos archivos para verificar que todo funcione correctamente
 */

require_once __DIR__ . '/src/config/database.php';

class PruebaImportador {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDatabase();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public function ejecutarPrueba() {
        echo "🧪 PRUEBA DEL IMPORTADOR COMPLETO SAT\n";
        echo str_repeat("=", 50) . "\n\n";
        
        // Obtener algunos archivos de prueba
        $archivos = $this->obtenerArchivosPrueba(10); // Solo 10 archivos
        
        echo "📁 Archivos de prueba encontrados: " . count($archivos) . "\n\n";
        
        foreach ($archivos as $i => $archivo) {
            echo "📄 Procesando archivo " . ($i + 1) . ": " . basename($archivo) . "\n";
            $this->analizarArchivoDetallado($archivo);
            echo str_repeat("-", 40) . "\n";
        }
        
        echo "\n✅ PRUEBA COMPLETADA\n";
    }
    
    private function obtenerArchivosPrueba($limite = 10) {
        $archivos = [];
        $directorios = [
            'storage/sat_downloads/BLM1706026AA/EMITIDAS',
            'storage/sat_downloads/BLM1706026AA/RECIBIDAS', 
            'storage/sat_downloads/BFM170822P38/EMITIDAS',
            'storage/sat_downloads/BFM170822P38/RECIBIDAS'
        ];
        
        foreach ($directorios as $dir) {
            if (is_dir($dir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                        $archivos[] = $file->getPathname();
                        if (count($archivos) >= $limite) {
                            break 2;
                        }
                    }
                }
            }
        }
        
        return $archivos;
    }
    
    private function analizarArchivoDetallado($rutaArchivo) {
        try {
            // Extraer información de la ruta
            $info = $this->extraerInfoRuta($rutaArchivo);
            echo "  RFC: {$info['rfc']} | TIPO: {$info['tipo']} | AÑO: {$info['año']} | MES: {$info['mes']}\n";
            
            // Leer XML
            $xmlContent = file_get_contents($rutaArchivo);
            if (!$xmlContent) {
                echo "  ❌ ERROR: No se pudo leer el archivo\n";
                return;
            }
            
            $xml = simplexml_load_string($xmlContent);
            if (!$xml) {
                echo "  ❌ ERROR: XML inválido\n";
                return;
            }
            
            // Analizar estructura
            $comprobante = $xml->attributes();
            $version = (string)($comprobante->Version ?? '3.3');
            $tipo = (string)($comprobante->TipoDeComprobante ?? '');
            $uuid = $this->extraerUUID($xml);
            
            echo "  📋 Versión CFDI: $version | Tipo: $tipo | UUID: " . substr($uuid, 0, 8) . "...\n";
            
            // Verificar emisor/receptor
            $rfcEmisor = (string)($xml->Emisor['Rfc'] ?? '');
            $rfcReceptor = (string)($xml->Receptor['Rfc'] ?? '');
            echo "  👤 Emisor: $rfcEmisor | Receptor: $rfcReceptor\n";
            
            // Contar conceptos
            $numConceptos = isset($xml->Conceptos->Concepto) ? count($xml->Conceptos->Concepto) : 0;
            echo "  📦 Conceptos: $numConceptos\n";
            
            // Verificar complementos
            $complementos = $this->analizarComplementos($xml);
            if (!empty($complementos)) {
                echo "  🔧 Complementos: " . implode(', ', $complementos) . "\n";
            }
            
            // Verificar timbre fiscal
            $tieneTimbre = $this->tieneTimbreFiscal($xml);
            echo "  🎫 Timbre fiscal: " . ($tieneTimbre ? "✅ SÍ" : "❌ NO") . "\n";
            
            echo "  ✅ Análisis completado\n";
            
        } catch (Exception $e) {
            echo "  ❌ ERROR: " . $e->getMessage() . "\n";
        }
    }
    
    private function extraerInfoRuta($rutaArchivo) {
        $partes = explode(DIRECTORY_SEPARATOR, str_replace('/', DIRECTORY_SEPARATOR, $rutaArchivo));
        
        return [
            'rfc' => $partes[count($partes) - 5] ?? '',
            'tipo' => $partes[count($partes) - 4] ?? '',
            'año' => $partes[count($partes) - 3] ?? '',
            'mes' => $partes[count($partes) - 2] ?? '',
            'archivo' => basename($rutaArchivo)
        ];
    }
    
    private function extraerUUID($xml) {
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['tfd'])) {
            $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
            $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');
            if (!empty($timbres)) {
                return (string)$timbres[0]['UUID'];
            }
        }
        return 'SIN-UUID';
    }
    
    private function analizarComplementos($xml) {
        $complementos = [];
        
        if (isset($xml->Complemento)) {
            $namespaces = $xml->getNamespaces(true);
            
            // Verificar complemento de pago
            if (isset($namespaces['pago20'])) {
                $complementos[] = 'Pago 2.0';
            } elseif (isset($namespaces['pago10'])) {
                $complementos[] = 'Pago 1.0';
            }
            
            // Verificar otros complementos comunes
            if (isset($namespaces['nomina12']) || isset($namespaces['nomina'])) {
                $complementos[] = 'Nómina';
            }
            
            if (isset($namespaces['cartaporte20']) || isset($namespaces['cartaporte'])) {
                $complementos[] = 'Carta Porte';
            }
            
            if (isset($namespaces['cce11']) || isset($namespaces['cce'])) {
                $complementos[] = 'Comercio Exterior';
            }
        }
        
        return $complementos;
    }
    
    private function tieneTimbreFiscal($xml) {
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['tfd'])) {
            $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
            $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');
            return !empty($timbres);
        }
        return false;
    }
}

// Ejecutar prueba
try {
    $prueba = new PruebaImportador();
    $prueba->ejecutarPrueba();
} catch (Exception $e) {
    echo "❌ Error crítico en la prueba: " . $e->getMessage() . "\n";
}
?>
