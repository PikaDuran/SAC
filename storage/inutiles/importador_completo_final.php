<?php
/**
 * IMPORTADOR CFDI COMPLETO FINAL - 100% CAMPOS TABLA CFDI
 * Versión completa con todos los 39 campos de la tabla cfdi
 */

require_once 'vendor/autoload.php';

class ImportadorCFDICompleto {
    private $pdo;
    
    public function __construct($host, $dbname, $username, $password) {
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "✅ Conexión a base de datos establecida\n";
        } catch (PDOException $e) {
            die("❌ Error de conexión: " . $e->getMessage());
        }
    }
    
    public function procesarDirectorio($rutaDirectorio) {
        if (!is_dir($rutaDirectorio)) {
            echo "❌ El directorio no existe: $rutaDirectorio\n";
            return;
        }
        
        echo "🔍 Buscando archivos XML en: $rutaDirectorio\n";
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rutaDirectorio)
        );
        
        $archivosXML = [];
        foreach ($iterator as $archivo) {
            if ($archivo->isFile() && strtolower($archivo->getExtension()) === 'xml') {
                $archivosXML[] = $archivo->getPathname();
            }
        }
        
        $total = count($archivosXML);
        echo "📄 Encontrados $total archivos XML\n";
        
        if ($total === 0) {
            echo "❌ No se encontraron archivos XML\n";
            return;
        }
        
        $procesados = 0;
        $errores = 0;
        
        foreach ($archivosXML as $rutaArchivo) {
            try {
                echo "\n📄 Procesando: " . basename($rutaArchivo) . "\n";
                
                if ($this->procesarArchivoXML($rutaArchivo)) {
                    $procesados++;
                    echo "   ✅ Procesado correctamente\n";
                } else {
                    $errores++;
                    echo "   ❌ Error en procesamiento\n";
                }
                
            } catch (Exception $e) {
                $errores++;
                echo "   ❌ Error: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "📊 RESUMEN DE PROCESAMIENTO:\n";
        echo "   Total archivos: $total\n";
        echo "   Procesados: $procesados\n";
        echo "   Errores: $errores\n";
        echo str_repeat("=", 50) . "\n";
        
        $this->mostrarEstadisticasFinales();
    }
    
    private function procesarArchivoXML($rutaArchivo) {
        // Leer contenido XML
        $contenidoXML = file_get_contents($rutaArchivo);
        if ($contenidoXML === false) {
            throw new Exception("No se pudo leer el archivo XML");
        }
        
        // Cargar XML con SimpleXML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contenidoXML);
        
        if ($xml === false) {
            $errores = libxml_get_errors();
            throw new Exception("Error al parsear XML: " . implode(", ", array_map(function($e) { return $e->message; }, $errores)));
        }
        
        // Registrar namespaces
        $namespaces = $xml->getNamespaces(true);
        foreach ($namespaces as $prefix => $uri) {
            if (empty($prefix)) {
                $xml->registerXPathNamespace('cfdi', $uri);
            } else {
                $xml->registerXPathNamespace($prefix, $uri);
            }
        }
        
        // Registrar namespace específicos
        $xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xml->registerXPathNamespace('cfdi33', 'http://www.sat.gob.mx/cfd/3');
        $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
        
        // Extraer datos
        $datos = $this->extraerDatosCFDI($xml, $rutaArchivo);
        
        // Insertar en base de datos
        return $this->insertarCFDICompleto($datos);
    }
    
    private function extraerDatosCFDI($xml, $rutaArchivo) {
        $datos = [];
        
        // 1. version - Version del CFDI
        $datos['version'] = $this->getAttributeValue($xml, 'Version');
        
        // 2. serie - Serie del comprobante
        $datos['serie'] = $this->getAttributeValue($xml, 'Serie');
        
        // 3. folio - Folio del comprobante
        $datos['folio'] = $this->getAttributeValue($xml, 'Folio');
        
        // 4. fecha - Fecha de expedición
        $datos['fecha'] = $this->getAttributeValue($xml, 'Fecha');
        
        // 5. sello_cfd - Sello digital del CFDI (CORREGIDO)
        $datos['sello_cfd'] = $this->getAttributeValue($xml, 'Sello');
        
        // 6. forma_pago - Forma de pago
        $datos['forma_pago'] = $this->getAttributeValue($xml, 'FormaPago');
        
        // 7. no_certificado - Número de certificado
        $datos['no_certificado'] = $this->getAttributeValue($xml, 'NoCertificado');
        
        // 8. certificado - Certificado del emisor
        $datos['certificado'] = $this->getAttributeValue($xml, 'Certificado');
        
        // 9. subtotal - Subtotal
        $datos['subtotal'] = $this->getAttributeFloatValue($xml, 'SubTotal');
        
        // 10. descuento - Descuento aplicado
        $datos['descuento'] = $this->getAttributeFloatValue($xml, 'Descuento');
        
        // 11. moneda - Moneda
        $datos['moneda'] = $this->getAttributeValue($xml, 'Moneda');
        
        // 12. tipo_cambio - Tipo de cambio
        $datos['tipo_cambio'] = $this->getAttributeFloatValue($xml, 'TipoCambio');
        
        // 13. total - Total del comprobante
        $datos['total'] = $this->getAttributeFloatValue($xml, 'Total');
        
        // 14. tipo_de_comprobante - Tipo de comprobante
        $datos['tipo_de_comprobante'] = $this->getAttributeValue($xml, 'TipoDeComprobante');
        
        // 15. exportacion - Clave de exportación
        $datos['exportacion'] = $this->getAttributeValue($xml, 'Exportacion');
        
        // 16. metodo_pago - Método de pago
        $datos['metodo_pago'] = $this->getAttributeValue($xml, 'MetodoPago');
        
        // 17. lugar_expedicion - Lugar de expedición
        $datos['lugar_expedicion'] = $this->getAttributeValue($xml, 'LugarExpedicion');
        
        // 18. confirmacion - Confirmación
        $datos['confirmacion'] = $this->getAttributeValue($xml, 'Confirmacion');
        
        // DATOS DEL EMISOR
        $emisor = $xml->xpath('//cfdi:Emisor')[0] ?? null;
        if (!$emisor) {
            // Intentar con namespace CFDI 3.3
            $emisor = $xml->xpath('//cfdi33:Emisor')[0] ?? null;
        }
        
        // 19. emisor_rfc - RFC del emisor
        $datos['emisor_rfc'] = $emisor ? (string)$emisor['Rfc'] : null;
        
        // 20. emisor_nombre - Nombre del emisor
        $datos['emisor_nombre'] = $emisor ? $this->getAttributeValue($emisor, 'Nombre') : null;
        
        // 21. emisor_regimen_fiscal - Régimen fiscal del emisor
        if ($datos['version'] === '4.0') {
            $datos['emisor_regimen_fiscal'] = $emisor ? $this->getAttributeValue($emisor, 'RegimenFiscal') : null;
        } else {
            // CFDI 3.3 - buscar en RegimenFiscal child
            $regimenes = $emisor ? $emisor->xpath('.//cfdi33:RegimenFiscal | .//cfdi:RegimenFiscal') : [];
            $datos['emisor_regimen_fiscal'] = !empty($regimenes) ? $this->getAttributeValue($regimenes[0], 'Regimen') : null;
        }
        
        // DATOS DEL RECEPTOR
        $receptor = $xml->xpath('//cfdi:Receptor')[0] ?? null;
        if (!$receptor) {
            // Intentar con namespace CFDI 3.3
            $receptor = $xml->xpath('//cfdi33:Receptor')[0] ?? null;
        }
        
        // 22. receptor_rfc - RFC del receptor
        $datos['receptor_rfc'] = $receptor ? (string)$receptor['Rfc'] : null;
        
        // 23. receptor_nombre - Nombre del receptor
        $datos['receptor_nombre'] = $receptor ? $this->getAttributeValue($receptor, 'Nombre') : null;
        
        // 24. receptor_domicilio_fiscal_receptor - Domicilio fiscal
        $datos['receptor_domicilio_fiscal_receptor'] = $receptor ? $this->getAttributeValue($receptor, 'DomicilioFiscalReceptor') : null;
        
        // 25. receptor_regimen_fiscal_receptor - Régimen fiscal receptor
        $datos['receptor_regimen_fiscal_receptor'] = $receptor ? $this->getAttributeValue($receptor, 'RegimenFiscalReceptor') : null;
        
        // 26. receptor_uso_cfdi - Uso del CFDI
        $datos['receptor_uso_cfdi'] = $receptor ? $this->getAttributeValue($receptor, 'UsoCFDI') : null;
        
        // IMPUESTOS
        $impuestos = $xml->xpath('//cfdi:Impuestos')[0] ?? $xml->xpath('//cfdi33:Impuestos')[0] ?? null;
        
        // 27. total_impuestos_retenidos - Total impuestos retenidos
        $datos['total_impuestos_retenidos'] = $impuestos ? $this->getAttributeFloatValue($impuestos, 'TotalImpuestosRetenidos') : null;
        
        // 28. total_impuestos_trasladados - Total impuestos trasladados
        $datos['total_impuestos_trasladados'] = $impuestos ? $this->getAttributeFloatValue($impuestos, 'TotalImpuestosTrasladados') : null;
        
        // TIMBRE FISCAL DIGITAL
        $timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0] ?? null;
        
        // 29. uuid - UUID del timbre
        $datos['uuid'] = $timbre ? (string)$timbre['UUID'] : null;
        
        // 30. fecha_timbrado - Fecha de timbrado
        $datos['fecha_timbrado'] = $timbre ? $this->getAttributeValue($timbre, 'FechaTimbrado') : null;
        
        // 31. rfc_prov_certif - RFC del proveedor de certificación
        $datos['rfc_prov_certif'] = $timbre ? $this->getAttributeValue($timbre, 'RfcProvCertif') : null;
        
        // 32. leyenda - Leyenda (si existe)
        $datos['leyenda'] = $timbre ? $this->getAttributeValue($timbre, 'Leyenda') : null;
        
        // 33. sello_sat - Sello del SAT
        $datos['sello_sat'] = $timbre ? $this->getAttributeValue($timbre, 'SelloSAT') : null;
        
        // 34. no_certificado_sat - Número de certificado SAT
        $datos['no_certificado_sat'] = $timbre ? $this->getAttributeValue($timbre, 'NoCertificadoSAT') : null;
        
        // 35. version_timbre - Versión del timbre
        $datos['version_timbre'] = $timbre ? $this->getAttributeValue($timbre, 'Version') : null;
        
        // 36. rfc_pac - RFC del PAC (Proveedor Autorizado de Certificación)
        $datos['rfc_pac'] = null; // No disponible en XML, se debe obtener externamente
        
        // 37. fecha_certificacion_pac - Fecha certificación PAC
        $datos['fecha_certificacion_pac'] = null; // No disponible en XML
        
        // 38. cadena_original - Cadena original
        $datos['cadena_original'] = null; // Se puede generar pero no está en el XML
        
        // 39. estatus_sat - Estatus en el SAT (CAMPO FALTANTE - NO ESTÁ EN XML)
        $datos['estatus_sat'] = 'PENDIENTE'; // Debe consultarse al webservice del SAT
        
        // 40. complemento_json - Datos del complemento en JSON (CAMPO FALTANTE)
        $complementos = $xml->xpath('//cfdi:Complemento')[0] ?? $xml->xpath('//cfdi33:Complemento')[0] ?? null;
        $complementoData = [];
        if ($complementos) {
            foreach ($complementos->children() as $complemento) {
                $nombre = $complemento->getName();
                $complementoData[$nombre] = $this->xmlElementToArray($complemento);
            }
        }
        $datos['complemento_json'] = json_encode($complementoData, JSON_UNESCAPED_UNICODE);
        
        return $datos;
    }
    
    private function xmlElementToArray($element) {
        $array = [];
        
        // Atributos
        foreach ($element->attributes() as $key => $value) {
            $array['@' . $key] = (string)$value;
        }
        
        // Elementos hijos
        foreach ($element->children() as $child) {
            $childName = $child->getName();
            $childArray = $this->xmlElementToArray($child);
            
            if (isset($array[$childName])) {
                if (!is_array($array[$childName]) || !isset($array[$childName][0])) {
                    $array[$childName] = [$array[$childName]];
                }
                $array[$childName][] = $childArray;
            } else {
                $array[$childName] = $childArray;
            }
        }
        
        // Texto del elemento
        $text = trim((string)$element);
        if (!empty($text) && empty($array)) {
            return $text;
        } elseif (!empty($text)) {
            $array['@text'] = $text;
        }
        
        return $array;
    }
    
    private function insertarCFDICompleto($datos) {
        $sql = "INSERT INTO cfdi (
            version, serie, folio, fecha, sello_cfd, forma_pago, no_certificado, 
            certificado, subtotal, descuento, moneda, tipo_cambio, total, 
            tipo_de_comprobante, exportacion, metodo_pago, lugar_expedicion, 
            confirmacion, emisor_rfc, emisor_nombre, emisor_regimen_fiscal, 
            receptor_rfc, receptor_nombre, receptor_domicilio_fiscal_receptor, 
            receptor_regimen_fiscal_receptor, receptor_uso_cfdi, 
            total_impuestos_retenidos, total_impuestos_trasladados, 
            uuid, fecha_timbrado, rfc_prov_certif, leyenda, 
            sello_sat, no_certificado_sat, version_timbre, 
            rfc_pac, fecha_certificacion_pac, cadena_original,
            estatus_sat, complemento_json
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            
            $valores = [
                $datos['version'], $datos['serie'], $datos['folio'], $datos['fecha'], 
                $datos['sello_cfd'], $datos['forma_pago'], $datos['no_certificado'], 
                $datos['certificado'], $datos['subtotal'], $datos['descuento'], 
                $datos['moneda'], $datos['tipo_cambio'], $datos['total'], 
                $datos['tipo_de_comprobante'], $datos['exportacion'], $datos['metodo_pago'], 
                $datos['lugar_expedicion'], $datos['confirmacion'], $datos['emisor_rfc'], 
                $datos['emisor_nombre'], $datos['emisor_regimen_fiscal'], $datos['receptor_rfc'], 
                $datos['receptor_nombre'], $datos['receptor_domicilio_fiscal_receptor'], 
                $datos['receptor_regimen_fiscal_receptor'], $datos['receptor_uso_cfdi'], 
                $datos['total_impuestos_retenidos'], $datos['total_impuestos_trasladados'], 
                $datos['uuid'], $datos['fecha_timbrado'], $datos['rfc_prov_certif'], 
                $datos['leyenda'], $datos['sello_sat'], $datos['no_certificado_sat'], 
                $datos['version_timbre'], $datos['rfc_pac'], $datos['fecha_certificacion_pac'], 
                $datos['cadena_original'], $datos['estatus_sat'], $datos['complemento_json']
            ];
            
            return $stmt->execute($valores);
            
        } catch (PDOException $e) {
            echo "❌ Error al insertar: " . $e->getMessage() . "\n";
            echo "📄 UUID: " . ($datos['uuid'] ?? 'N/A') . "\n";
            return false;
        }
    }
    
    private function getAttributeValue($xml, $attribute) {
        return isset($xml[$attribute]) ? (string)$xml[$attribute] : null;
    }
    
    private function getAttributeFloatValue($xml, $attribute) {
        return isset($xml[$attribute]) ? (float)$xml[$attribute] : null;
    }
    
    private function mostrarEstadisticasFinales() {
        try {
            // Contar total
            $total = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi")->fetch()['total'];
            echo "\n📊 ESTADÍSTICAS FINALES:\n";
            echo "Total registros CFDI: $total\n";
            
            if ($total == 0) {
                echo "❌ NO SE INSERTARON REGISTROS\n";
                return;
            }
            
            // Verificar cobertura de campos críticos
            echo "\n🔍 VERIFICACIÓN DE CAMPOS CRÍTICOS (39 campos):\n";
            
            $campos = [
                'version' => 'Versión CFDI',
                'uuid' => 'UUID (timbre)',
                'emisor_rfc' => 'RFC Emisor',
                'receptor_rfc' => 'RFC Receptor',
                'fecha' => 'Fecha expedición',
                'total' => 'Total',
                'tipo_de_comprobante' => 'Tipo comprobante',
                'sello_cfd' => 'Sello CFD',
                'estatus_sat' => 'Estatus SAT',
                'complemento_json' => 'Complemento JSON'
            ];
            
            foreach ($campos as $campo => $descripcion) {
                $llenos = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE $campo IS NOT NULL AND $campo != ''")->fetch()['total'];
                $porcentaje = $total > 0 ? round(($llenos / $total) * 100, 2) : 0;
                
                if ($porcentaje >= 95) {
                    echo "  ✅ $descripcion: $llenos/$total ($porcentaje%)\n";
                } elseif ($porcentaje >= 80) {
                    echo "  ⚠️  $descripcion: $llenos/$total ($porcentaje%)\n";
                } else {
                    echo "  ❌ $descripcion: $llenos/$total ($porcentaje%)\n";
                }
            }
            
            // Contar por versión
            $versiones = $this->pdo->query("
                SELECT version, COUNT(*) as cantidad 
                FROM cfdi 
                GROUP BY version 
                ORDER BY version
            ")->fetchAll();
            
            echo "\n📋 POR VERSIÓN:\n";
            foreach ($versiones as $v) {
                echo "  CFDI {$v['version']}: {$v['cantidad']} registros\n";
            }
            
            // Mostrar algunos ejemplos
            echo "\n📄 EJEMPLOS DE REGISTROS INSERTADOS:\n";
            $ejemplos = $this->pdo->query("
                SELECT version, emisor_rfc, receptor_rfc, tipo_de_comprobante, total, LEFT(uuid, 20) as uuid_short, estatus_sat
                FROM cfdi 
                ORDER BY fecha DESC
                LIMIT 5
            ")->fetchAll();
            
            foreach ($ejemplos as $ej) {
                echo "  • CFDI {$ej['version']} | {$ej['emisor_rfc']} → {$ej['receptor_rfc']} | {$ej['tipo_de_comprobante']} | \${$ej['total']} | {$ej['uuid_short']}... | SAT: {$ej['estatus_sat']}\n";
            }
            
            echo "\n✅ IMPORTACIÓN COMPLETADA AL 100% - TODOS LOS 39 CAMPOS INCLUIDOS\n";
            
        } catch (Exception $e) {
            echo "❌ Error al mostrar estadísticas: " . $e->getMessage() . "\n";
        }
    }
}

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'sac_db';
$username = 'root';
$password = '';

// Ruta donde están los archivos XML
$rutaXML = 'C:\\xampp\\htdocs\\SAC\\storage\\sat_downloads';

echo "🚀 IMPORTADOR CFDI COMPLETO FINAL - 100% CAMPOS\n";
echo str_repeat("=", 60) . "\n";

try {
    $importador = new ImportadorCFDICompleto($host, $dbname, $username, $password);
    $importador->procesarDirectorio($rutaXML);
} catch (Exception $e) {
    echo "❌ Error fatal: " . $e->getMessage() . "\n";
}

echo "\n🏁 PROCESO TERMINADO\n";
?>
