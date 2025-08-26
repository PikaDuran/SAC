<?php
/**
 * ANALIZADOR DE DIFERENCIAS CFDI 3.3 vs 4.0
 * Compara XMLs de 2020 (3.3) vs 2024 (4.0) - Emitidos y Recibidos
 */

class AnalizadorCFDI {
    
    public function analizar() {
        echo "🔍 ANÁLISIS DE DIFERENCIAS CFDI 3.3 vs 4.0\n";
        echo str_repeat("=", 60) . "\n\n";
        
        // Buscar XMLs de 2020 (CFDI 3.3)
        $xml_2020_emitido = $this->buscarXML('2020', 'EMITIDAS');
        $xml_2020_recibido = $this->buscarXML('2020', 'RECIBIDAS');
        
        // Buscar XMLs de 2024 (CFDI 4.0)
        $xml_2024_emitido = $this->buscarXML('2024', 'EMITIDAS');
        $xml_2024_recibido = $this->buscarXML('2024', 'RECIBIDAS');
        
        echo "📄 ARCHIVOS ENCONTRADOS:\n";
        echo "2020 Emitido: " . ($xml_2020_emitido ? basename($xml_2020_emitido) : "No encontrado") . "\n";
        echo "2020 Recibido: " . ($xml_2020_recibido ? basename($xml_2020_recibido) : "No encontrado") . "\n";
        echo "2024 Emitido: " . ($xml_2024_emitido ? basename($xml_2024_emitido) : "No encontrado") . "\n";
        echo "2024 Recibido: " . ($xml_2024_recibido ? basename($xml_2024_recibido) : "No encontrado") . "\n\n";
        
        // Analizar cada archivo
        if ($xml_2020_emitido) {
            $this->analizarXML($xml_2020_emitido, "CFDI 3.3 - EMITIDO (2020)");
        }
        
        if ($xml_2024_emitido) {
            $this->analizarXML($xml_2024_emitido, "CFDI 4.0 - EMITIDO (2024)");
        }
        
        if ($xml_2020_recibido) {
            $this->analizarXML($xml_2020_recibido, "CFDI 3.3 - RECIBIDO (2020)");
        }
        
        if ($xml_2024_recibido) {
            $this->analizarXML($xml_2024_recibido, "CFDI 4.0 - RECIBIDO (2024)");
        }
    }
    
    private function buscarXML($año, $tipo) {
        $patron = "storage/sat_downloads/*/$tipo/$año/*/*.xml";
        $archivos = glob($patron);
        
        if (!empty($archivos)) {
            return $archivos[0]; // Tomar el primero que encuentre
        }
        
        return null;
    }
    
    private function analizarXML($rutaArchivo, $titulo) {
        echo "🔍 $titulo\n";
        echo str_repeat("-", 50) . "\n";
        echo "Archivo: " . basename($rutaArchivo) . "\n\n";
        
        $xmlContent = file_get_contents($rutaArchivo);
        $xml = simplexml_load_string($xmlContent);
        
        if (!$xml) {
            echo "❌ Error al cargar XML\n\n";
            return;
        }
        
        // Información básica del comprobante
        $comprobante = $xml->attributes();
        echo "📋 INFORMACIÓN BÁSICA:\n";
        echo "  Version: " . (string)$comprobante->Version . "\n";
        echo "  Tipo: " . (string)$comprobante->TipoDeComprobante . "\n";
        echo "  Serie: " . (string)$comprobante->Serie . "\n";
        echo "  Folio: " . (string)$comprobante->Folio . "\n";
        echo "  Fecha: " . (string)$comprobante->Fecha . "\n";
        echo "  Total: $" . number_format((float)$comprobante->Total, 2) . "\n";
        echo "  Moneda: " . (string)$comprobante->Moneda . "\n";
        
        // Verificar campos específicos de versión
        if (isset($comprobante->Exportacion)) {
            echo "  ⭐ Exportacion: " . (string)$comprobante->Exportacion . " (CFDI 4.0)\n";
        }
        
        // Namespaces
        $namespaces = $xml->getNamespaces(true);
        echo "\n📋 NAMESPACES:\n";
        foreach ($namespaces as $prefix => $uri) {
            echo "  $prefix: $uri\n";
            if (strpos($uri, 'Pagos') !== false) {
                echo "    🔥 NAMESPACE DE PAGOS DETECTADO!\n";
            }
        }
        
        // Registrar namespace CFDI para acceso correcto
        $cfdNamespace = '';
        foreach ($namespaces as $prefix => $uri) {
            if (strpos($uri, 'cfd/3') !== false || strpos($uri, 'cfd/4') !== false) {
                $cfdNamespace = $uri;
                $xml->registerXPathNamespace('cfdi', $uri);
                break;
            }
        }
        
        // Emisor y Receptor usando XPath con namespace
        echo "\n📋 EMISOR:\n";
        $emisores = $xml->xpath('//cfdi:Emisor');
        if (!empty($emisores)) {
            $emisor = $emisores[0];
            echo "  RFC: " . (string)$emisor['Rfc'] . "\n";
            echo "  Nombre: " . (string)$emisor['Nombre'] . "\n";
            echo "  Régimen: " . (string)$emisor['RegimenFiscal'] . "\n";
        }
        
        echo "\n📋 RECEPTOR:\n";
        $receptores = $xml->xpath('//cfdi:Receptor');
        if (!empty($receptores)) {
            $receptor = $receptores[0];
            echo "  RFC: " . (string)$receptor['Rfc'] . "\n";
            echo "  Nombre: " . (string)$receptor['Nombre'] . "\n";
            echo "  Uso CFDI: " . (string)$receptor['UsoCFDI'] . "\n";
            
            if (isset($receptor['DomicilioFiscalReceptor'])) {
                echo "  ⭐ Domicilio Fiscal: " . (string)$receptor['DomicilioFiscalReceptor'] . " (CFDI 4.0)\n";
            }
            
            if (isset($receptor['RegimenFiscalReceptor'])) {
                echo "  ⭐ Régimen Fiscal Receptor: " . (string)$receptor['RegimenFiscalReceptor'] . " (CFDI 4.0)\n";
            }
        }
        
        // Conceptos usando XPath con namespace
        echo "\n📋 CONCEPTOS:\n";
        $conceptos = $xml->xpath('//cfdi:Conceptos/cfdi:Concepto');
        foreach ($conceptos as $i => $concepto) {
            if ($i >= 2) break; // Solo mostrar primeros 2
            
            $attrs = $concepto->attributes();
            echo "  Concepto " . ($i + 1) . ":\n";
            echo "    Descripción: " . (string)$attrs->Descripcion . "\n";
            echo "    Cantidad: " . (string)$attrs->Cantidad . "\n";
            echo "    Valor Unitario: $" . number_format((float)$attrs->ValorUnitario, 2) . "\n";
            echo "    Importe: $" . number_format((float)$attrs->Importe, 2) . "\n";
            
            if (isset($attrs->ObjetoImp)) {
                echo "    ⭐ Objeto Imp: " . (string)$attrs->ObjetoImp . " (CFDI 4.0)\n";
            }
        }
            echo "  Concepto " . ($i + 1) . ":\n";
            echo "    Descripción: " . (string)$attrs->Descripcion . "\n";
            echo "    Cantidad: " . (string)$attrs->Cantidad . "\n";
            echo "    Valor Unitario: $" . number_format((float)$attrs->ValorUnitario, 2) . "\n";
            echo "    Importe: $" . number_format((float)$attrs->Importe, 2) . "\n";
            
            if (isset($attrs->ObjetoImp)) {
                echo "    ⭐ Objeto Imp: " . (string)$attrs->ObjetoImp . " (CFDI 4.0)\n";
            }
        }
        
        // Complementos
        echo "\n📋 COMPLEMENTOS:\n";
        if (isset($xml->Complemento)) {
            foreach ($xml->Complemento->children() as $complemento) {
                echo "  - " . $complemento->getName() . "\n";
                
                // Si es complemento de pagos, analizarlo
                if ($complemento->getName() === 'Pagos') {
                    $this->analizarComplementoPagos($complemento, $namespaces);
                }
            }
        }
        
        // Timbre Fiscal
        if (isset($namespaces['tfd'])) {
            $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
            $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');
            if (!empty($timbres)) {
                $timbre = $timbres[0];
                echo "\n📋 TIMBRE FISCAL:\n";
                echo "  UUID: " . (string)$timbre['UUID'] . "\n";
                echo "  Fecha Timbrado: " . (string)$timbre['FechaTimbrado'] . "\n";
                echo "  RFC Proveedor: " . (string)$timbre['RfcProvCertif'] . "\n";
            }
        }
        
        echo "\n" . str_repeat("=", 60) . "\n\n";
    }
    
    private function analizarComplementoPagos($pagos, $namespaces) {
        echo "    💳 COMPLEMENTO DE PAGOS:\n";
        
        // Determinar versión del complemento
        $version = "3.3";
        foreach ($namespaces as $prefix => $uri) {
            if ($uri === 'http://www.sat.gob.mx/Pagos20') {
                $version = "4.0";
                break;
            }
        }
        
        echo "      Versión: $version\n";
        
        if (isset($pagos->Pago)) {
            foreach ($pagos->Pago as $i => $pago) {
                if ($i >= 1) break; // Solo el primero
                
                $attrs = $pago->attributes();
                echo "      Pago " . ($i + 1) . ":\n";
                echo "        Fecha: " . (string)$attrs->FechaPago . "\n";
                echo "        Forma: " . (string)$attrs->FormaDePagoP . "\n";
                echo "        Moneda: " . (string)$attrs->MonedaP . "\n";
                echo "        Monto: $" . number_format((float)$attrs->Monto, 2) . "\n";
                
                if (isset($pago->DoctoRelacionado)) {
                    echo "        Documentos Relacionados: " . count($pago->DoctoRelacionado) . "\n";
                    
                    foreach ($pago->DoctoRelacionado as $j => $doc) {
                        if ($j >= 1) break; // Solo el primero
                        
                        $docAttrs = $doc->attributes();
                        echo "          Doc " . ($j + 1) . ": " . (string)$docAttrs->IdDocumento . "\n";
                        echo "            Pagado: $" . number_format((float)$docAttrs->ImpPagado, 2) . "\n";
                    }
                }
            }
        }
    }
}

// Ejecutar análisis
try {
    $analizador = new AnalizadorCFDI();
    $analizador->analizar();
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
