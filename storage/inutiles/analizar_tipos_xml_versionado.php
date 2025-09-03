<?php
require_once 'vendor/autoload.php';

class AnalizadorXMLVersionado {
    
    public function analizarPorAno($ano) {
        echo "=== ANÁLISIS DE XMLs AÑO $ano ===\n";
        
        // Determinar versiones esperadas según el año
        if ($ano <= 2022) {
            echo "Versiones esperadas: CFDI 3.3 + Pagos 1.0\n";
            $versionCFDI = '3.3';
            $versionPagos = '1.0';
        } else {
            echo "Versiones esperadas: CFDI 4.0 + Pagos 2.0\n";
            $versionCFDI = '4.0';
            $versionPagos = '2.0';
        }
        
        // Buscar XMLs de ese año - estructura: RFC/EMITIDAS|RECIBIDAS/AÑO/MES/
        $ruta = "C:\\xampp\\htdocs\\SAC\\storage\\sat_downloads\\*\\*\\$ano\\*\\*.xml";
        $archivos = glob($ruta);
        
        if (empty($archivos)) {
            echo "No se encontraron XMLs para el año $ano\n";
            return;
        }
        
        echo "Encontrados: " . count($archivos) . " archivos\n";
        
        // Analizar primeros 5 archivos
        $muestra = array_slice($archivos, 0, 5);
        
        foreach ($muestra as $archivo) {
            echo "\n--- Analizando: " . basename($archivo) . " ---\n";
            $this->analizarArchivoEspecifico($archivo, $versionCFDI, $versionPagos);
        }
    }
    
    private function analizarArchivoEspecifico($archivo, $versionEsperadaCFDI, $versionEsperadaPagos) {
        $contenido = file_get_contents($archivo);
        
        // Cargar XML
        $xml = simplexml_load_string($contenido);
        if (!$xml) {
            echo "ERROR: No se pudo parsear el XML\n";
            return;
        }
        
        // Registrar namespaces
        $namespaces = $xml->getNamespaces(true);
        echo "Namespaces encontrados:\n";
        foreach ($namespaces as $prefix => $uri) {
            echo "  $prefix: $uri\n";
        }
        
        // Verificar versión CFDI
        $version = (string)$xml['Version'];
        echo "Versión CFDI: $version";
        if ($version === $versionEsperadaCFDI) {
            echo " ✓\n";
        } else {
            echo " ❌ (Esperada: $versionEsperadaCFDI)\n";
        }
        
        // Verificar tipo de comprobante
        $tipo = (string)$xml['TipoDeComprobante'];
        echo "Tipo: $tipo\n";
        
        // Analizar estructura específica
        if ($tipo === 'P') {
            echo "Es un CFDI de PAGO - Analizando complemento...\n";
            $this->analizarComplementoPago($xml, $versionEsperadaPagos);
        } else {
            echo "Es un CFDI regular - Analizando conceptos...\n";
            $this->analizarConceptosRegular($xml);
        }
        
        // Verificar emisor/receptor
        $this->verificarEmisorReceptor($xml, $version);
    }
    
    private function analizarComplementoPago($xml, $versionEsperadaPagos) {
        // Buscar complemento de pago
        $complemento = $xml->xpath('//cfdi:Complemento')[0] ?? null;
        
        if ($complemento) {
            // Versión 1.0 usa pago10, versión 2.0 usa pago20
            if ($versionEsperadaPagos === '1.0') {
                $pagos = $complemento->xpath('.//pago10:Pagos')[0] ?? null;
                if ($pagos) {
                    $versionPago = (string)$pagos['Version'];
                    echo "Versión Pagos encontrada: $versionPago\n";
                    
                    // Contar elementos
                    $listaPagos = $pagos->xpath('.//pago10:Pago') ?? [];
                    echo "Número de pagos: " . count($listaPagos) . "\n";
                    
                    foreach ($listaPagos as $pago) {
                        $documentos = $pago->xpath('.//pago10:DoctoRelacionado') ?? [];
                        echo "  Documentos relacionados: " . count($documentos) . "\n";
                    }
                }
            } else {
                $pagos = $complemento->xpath('.//pago20:Pagos')[0] ?? null;
                if ($pagos) {
                    $versionPago = (string)$pagos['Version'];
                    echo "Versión Pagos encontrada: $versionPago\n";
                    
                    // Contar elementos
                    $listaPagos = $pagos->xpath('.//pago20:Pago') ?? [];
                    echo "Número de pagos: " . count($listaPagos) . "\n";
                    
                    foreach ($listaPagos as $pago) {
                        $documentos = $pago->xpath('.//pago20:DoctoRelacionado') ?? [];
                        echo "  Documentos relacionados: " . count($documentos) . "\n";
                    }
                }
            }
        }
    }
    
    private function analizarConceptosRegular($xml) {
        $conceptos = $xml->xpath('//cfdi:Conceptos/cfdi:Concepto') ?? [];
        echo "Número de conceptos: " . count($conceptos) . "\n";
        
        if (count($conceptos) > 0) {
            $concepto = $conceptos[0];
            echo "Primer concepto:\n";
            echo "  Descripción: " . (string)$concepto['Descripcion'] . "\n";
            echo "  Cantidad: " . (string)$concepto['Cantidad'] . "\n";
            echo "  ValorUnitario: " . (string)$concepto['ValorUnitario'] . "\n";
            echo "  Importe: " . (string)$concepto['Importe'] . "\n";
            
            // Verificar impuestos del concepto
            $impuestos = $concepto->xpath('.//cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado') ?? [];
            echo "  Impuestos trasladados: " . count($impuestos) . "\n";
        }
    }
    
    private function verificarEmisorReceptor($xml, $version) {
        echo "\nEmisor/Receptor:\n";
        
        $emisor = $xml->xpath('//cfdi:Emisor')[0] ?? null;
        if ($emisor) {
            echo "Emisor RFC: " . (string)$emisor['Rfc'] . "\n";
            echo "Emisor Nombre: " . (string)$emisor['Nombre'] . "\n";
            
            if ($version === '4.0') {
                echo "Régimen Fiscal: " . (string)$emisor['RegimenFiscal'] . "\n";
            }
        }
        
        $receptor = $xml->xpath('//cfdi:Receptor')[0] ?? null;
        if ($receptor) {
            echo "Receptor RFC: " . (string)$receptor['Rfc'] . "\n";
            echo "Receptor Nombre: " . (string)$receptor['Nombre'] . "\n";
            echo "Uso CFDI: " . (string)$receptor['UsoCFDI'] . "\n";
            
            if ($version === '4.0') {
                echo "Régimen Fiscal Receptor: " . (string)$receptor['RegimenFiscalReceptor'] . "\n";
            }
        }
        
        // Verificar timbre fiscal
        $timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0] ?? null;
        if ($timbre) {
            echo "UUID: " . (string)$timbre['UUID'] . "\n";
            echo "Fecha Timbrado: " . (string)$timbre['FechaTimbrado'] . "\n";
            echo "RFC Proveedor Certificación: " . (string)$timbre['RfcProvCertif'] . "\n";
        }
    }
}

// Ejecutar análisis
$analizador = new AnalizadorXMLVersionado();

echo "=== ANÁLISIS POR AÑOS ===\n\n";

// Analizar 2020 (CFDI 3.3 + Pagos 1.0)
$analizador->analizarPorAno(2020);

echo "\n" . str_repeat("=", 50) . "\n\n";

// Analizar 2023 (CFDI 4.0 + Pagos 2.0)
$analizador->analizarPorAno(2023);

echo "\n" . str_repeat("=", 50) . "\n\n";

// Analizar 2024 (CFDI 4.0 + Pagos 2.0)
$analizador->analizarPorAno(2024);
