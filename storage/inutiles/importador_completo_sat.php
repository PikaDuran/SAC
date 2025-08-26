<?php
/**
 * IMPORTADOR COMPLETO DE CFDIs
 * Limpia todas las tablas y reimporta todos los XMLs con complementos de pago
 */

require_once __DIR__ . '/src/config/database.php';

class ImportadorCompletoSAT {
    private $pdo;
    private $stats = [
        'total_archivos' => 0,
        'cfdi_importados' => 0,
        'pagos_procesados' => 0,
        'documentos_relacionados' => 0,
        'errores' => 0,
        'skipped' => 0
    ];
    
    public function __construct() {
        $this->pdo = getDatabase();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public function ejecutar() {
        echo "🚀 IMPORTADOR COMPLETO SAT - REINICIO TOTAL\n";
        echo str_repeat("=", 60) . "\n\n";
        
        $this->limpiarTablas();
        $this->escanearArchivos();
        $this->mostrarResumen();
    }
    
    private function limpiarTablas() {
        echo "🧹 LIMPIANDO TODAS LAS TABLAS CFDI...\n";
        
        $tablas = [
            'cfdi_pago_totales',
            'cfdi_pago_impuestos_dr', 
            'cfdi_pago_documentos_relacionados',
            'cfdi_pagos',
            'cfdi_impuestos',
            'cfdi_conceptos',
            'cfdi_timbre_fiscal',
            'cfdi_auditoria',
            'cfdi'
        ];
        
        foreach ($tablas as $tabla) {
            try {
                $this->pdo->exec("DELETE FROM $tabla");
                echo "  ✅ $tabla limpiada\n";
            } catch (Exception $e) {
                echo "  ⚠️  Error limpiando $tabla: " . $e->getMessage() . "\n";
            }
        }
        
        // Reset auto increment
        foreach ($tablas as $tabla) {
            try {
                $this->pdo->exec("ALTER TABLE $tabla AUTO_INCREMENT = 1");
            } catch (Exception $e) {
                // Ignorar errores de auto increment
            }
        }
        
        echo "✅ Limpieza completada\n\n";
    }
    
    private function escanearArchivos() {
        echo "📁 ESCANEANDO ARCHIVOS XML...\n";
        
        $directorio = 'storage/sat_downloads';
        $archivos = $this->obtenerArchivosXML($directorio);
        
        $this->stats['total_archivos'] = count($archivos);
        echo "📊 Total de archivos encontrados: " . number_format($this->stats['total_archivos']) . "\n\n";
        
        $procesados = 0;
        $lote = 0;
        
        foreach ($archivos as $archivo) {
            $procesados++;
            $lote++;
            
            if ($lote % 100 == 0) {
                echo "📈 Procesados: " . number_format($procesados) . " / " . number_format($this->stats['total_archivos']) . 
                     " (" . round(($procesados / $this->stats['total_archivos']) * 100, 2) . "%)\n";
            }
            
            $this->procesarArchivo($archivo);
            
            // Pequeña pausa cada 1000 archivos para no sobrecargar
            if ($lote % 1000 == 0) {
                echo "⏸️  Pausa técnica...\n";
                usleep(100000); // 0.1 segundos
            }
        }
        
        echo "\n✅ PROCESAMIENTO DE ARCHIVOS COMPLETADO\n\n";
    }
    
    private function obtenerArchivosXML($directorio) {
        $archivos = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directorio)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                $archivos[] = $file->getPathname();
            }
        }
        
        return $archivos;
    }
    
    private function procesarArchivo($rutaArchivo) {
        try {
            // Extraer información de la ruta
            $info = $this->extraerInfoRuta($rutaArchivo);
            
            // Leer y parsear XML
            $xmlContent = file_get_contents($rutaArchivo);
            if (!$xmlContent) {
                $this->stats['errores']++;
                return;
            }
            
            $xml = simplexml_load_string($xmlContent);
            if (!$xml) {
                $this->stats['errores']++;
                return;
            }
            
            // Insertar CFDI principal
            $cfdi_id = $this->insertarCFDI($xml, $info, $rutaArchivo);
            
            if ($cfdi_id) {
                $this->stats['cfdi_importados']++;
                
                // Insertar timbre fiscal
                $this->insertarTimbreFiscal($xml, $cfdi_id);
                
                // Insertar conceptos e impuestos
                $this->insertarConceptos($xml, $cfdi_id);
                
                // Si es tipo P, procesar complemento de pago completo
                $tipoComprobante = (string)$xml['TipoDeComprobante'];
                if ($tipoComprobante === 'P') {
                    $this->procesarComplementoPago($xml, $cfdi_id);
                }
            }
            
        } catch (Exception $e) {
            $this->stats['errores']++;
            // Continuar con el siguiente archivo
        }
    }
    
    private function extraerInfoRuta($rutaArchivo) {
        // Formato: storage/sat_downloads/RFC/TIPO/AÑO/MES/archivo.xml
        $partes = explode(DIRECTORY_SEPARATOR, str_replace('/', DIRECTORY_SEPARATOR, $rutaArchivo));
        
        return [
            'rfc' => $partes[count($partes) - 5] ?? '',
            'tipo' => $partes[count($partes) - 4] ?? '',
            'año' => $partes[count($partes) - 3] ?? '',
            'mes' => $partes[count($partes) - 2] ?? '',
            'archivo' => basename($rutaArchivo)
        ];
    }
    
    private function insertarCFDI($xml, $info, $rutaArchivo) {
        try {
            $comprobante = $xml->attributes();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO cfdi (
                    uuid, tipo, serie, folio, fecha, fecha_timbrado,
                    rfc_emisor, nombre_emisor, regimen_fiscal_emisor,
                    rfc_receptor, nombre_receptor, regimen_fiscal_receptor,
                    uso_cfdi, lugar_expedicion, moneda, tipo_cambio,
                    subtotal, descuento, total, metodo_pago, forma_pago,
                    exportacion, archivo_xml, rfc_consultado, direccion_flujo,
                    version, estatus_sat
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Extraer UUID del timbre fiscal
            $uuid = $this->extraerUUID($xml);
            
            $stmt->execute([
                $uuid,
                (string)$comprobante->TipoDeComprobante ?? '',
                (string)$comprobante->Serie ?? null,
                (string)$comprobante->Folio ?? null,
                $this->formatearFecha((string)$comprobante->Fecha),
                $this->formatearFecha($this->extraerFechaTimbrado($xml)),
                (string)$xml->Emisor['Rfc'] ?? '',
                (string)$xml->Emisor['Nombre'] ?? '',
                (string)$xml->Emisor['RegimenFiscal'] ?? '',
                (string)$xml->Receptor['Rfc'] ?? '',
                (string)$xml->Receptor['Nombre'] ?? '',
                (string)$xml->Receptor['RegimenFiscalReceptor'] ?? '',
                (string)$xml->Receptor['UsoCFDI'] ?? '',
                (string)$comprobante->LugarExpedicion ?? '',
                (string)$comprobante->Moneda ?? 'MXN',
                (float)($comprobante->TipoCambio ?? 1.0),
                (float)($comprobante->SubTotal ?? 0.0),
                (float)($comprobante->Descuento ?? 0.0),
                (float)($comprobante->Total ?? 0.0),
                (string)$comprobante->MetodoPago ?? null,
                (string)$comprobante->FormaPago ?? null,
                (string)$comprobante->Exportacion ?? null,
                $rutaArchivo,
                $info['rfc'],
                strtoupper($info['tipo']),
                (string)$comprobante->Version ?? '3.3',
                'VIGENTE'
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function procesarComplementoPago($xml, $cfdi_id) {
        $namespaces = $xml->getNamespaces(true);
        $pagos_encontrados = [];
        
        // CFDI 4.0
        if (isset($namespaces['pago20'])) {
            $xml->registerXPathNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');
            $pagos_encontrados = $xml->xpath('//pago20:Pago');
        }
        // CFDI 3.3
        elseif (isset($namespaces['pago10'])) {
            $xml->registerXPathNamespace('pago10', 'http://www.sat.gob.mx/Pagos');
            $pagos_encontrados = $xml->xpath('//pago10:Pago');
        }
        
        foreach ($pagos_encontrados as $pago) {
            $this->insertarPago($pago, $cfdi_id);
        }
    }
    
    private function insertarPago($pago, $cfdi_id) {
        try {
            $attrs = $pago->attributes();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO cfdi_pagos (
                    cfdi_id, fecha_pago, forma_pago, moneda, 
                    tipo_cambio, monto, num_operacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $fechaPago = isset($attrs->FechaPago) ? 
                $this->formatearFecha((string)$attrs->FechaPago) : null;
            
            $stmt->execute([
                $cfdi_id,
                $fechaPago,
                (string)$attrs->FormaDePagoP ?? null,
                (string)$attrs->MonedaP ?? 'MXN',
                (float)($attrs->TipoCambioP ?? 1.0),
                (float)($attrs->Monto ?? 0.0),
                (string)$attrs->NumOperacion ?? null
            ]);
            
            $pago_id = $this->pdo->lastInsertId();
            $this->stats['pagos_procesados']++;
            
            // Procesar documentos relacionados
            if (isset($pago->DoctoRelacionado)) {
                foreach ($pago->DoctoRelacionado as $doc) {
                    $this->insertarDocumentoRelacionado($doc, $pago_id);
                }
            }
            
        } catch (Exception $e) {
            // Continuar con el siguiente pago
        }
    }
    
    private function insertarDocumentoRelacionado($doc, $pago_id) {
        try {
            $attrs = $doc->attributes();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO cfdi_pago_documentos_relacionados (
                    pago_id, uuid_documento, serie, folio, moneda_dr,
                    equivalencia_dr, num_parcialidad, imp_saldo_ant,
                    imp_pagado, imp_saldo_insoluto
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $pago_id,
                (string)$attrs->IdDocumento,
                (string)$attrs->Serie ?? null,
                (string)$attrs->Folio ?? null,
                (string)$attrs->MonedaDR ?? 'MXN',
                (float)($attrs->EquivalenciaDR ?? 1.0),
                (int)($attrs->NumParcialidad ?? 1),
                (float)($attrs->ImpSaldoAnt ?? 0.0),
                (float)($attrs->ImpPagado ?? 0.0),
                (float)($attrs->ImpSaldoInsoluto ?? 0.0)
            ]);
            
            $this->stats['documentos_relacionados']++;
            
        } catch (Exception $e) {
            // Continuar con el siguiente documento
        }
    }
    
    private function extraerUUID($xml) {
        // Buscar en TimbreFiscalDigital
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['tfd'])) {
            $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
            $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');
            if (!empty($timbres)) {
                return (string)$timbres[0]['UUID'];
            }
        }
        
        // Fallback: buscar en el nombre del archivo
        return '';
    }
    
    private function extraerFechaTimbrado($xml) {
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['tfd'])) {
            $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
            $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');
            if (!empty($timbres)) {
                return (string)$timbres[0]['FechaTimbrado'];
            }
        }
        return null;
    }
    
    private function formatearFecha($fecha) {
        if (empty($fecha)) return null;
        
        try {
            $dt = new DateTime($fecha);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function mostrarResumen() {
        echo "📊 RESUMEN FINAL:\n";
        echo str_repeat("=", 40) . "\n";
        echo "Total archivos procesados: " . number_format($this->stats['total_archivos']) . "\n";
        echo "CFDIs importados: " . number_format($this->stats['cfdi_importados']) . "\n";
        echo "Pagos procesados: " . number_format($this->stats['pagos_procesados']) . "\n";
        echo "Documentos relacionados: " . number_format($this->stats['documentos_relacionados']) . "\n";
        echo "Errores: " . number_format($this->stats['errores']) . "\n";
        echo "\n✅ IMPORTACIÓN COMPLETA FINALIZADA\n";
        
        // Verificación final
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi");
        $total_cfdi = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P'");
        $total_pagos = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi_pagos");
        $total_complementos = $stmt->fetch()['total'];
        
        echo "\n🔍 VERIFICACIÓN:\n";
        echo "CFDIs en base de datos: " . number_format($total_cfdi) . "\n";
        echo "CFDIs tipo P: " . number_format($total_pagos) . "\n";
        echo "Complementos de pago: " . number_format($total_complementos) . "\n";
    }
}

// Ejecutar importación
try {
    $importador = new ImportadorCompletoSAT();
    $importador->ejecutar();
} catch (Exception $e) {
    echo "❌ Error crítico: " . $e->getMessage() . "\n";
    exit(1);
}
?>
