<?php
/**
 * Importador Inteligente de Complementos de Pago CFDI
 * Sistema SAC - Manejo completo de CFDIs con complementos de pago
 * 
 * Funcionalidades:
 * - Detecta automáticamente CFDIs tipo "P" (Pago)
 * - Extrae y procesa complementos de pago
 * - Maneja documentos relacionados
 * - Calcula totales de impuestos
 * - Soporte para CFDI 3.3 y 4.0
 * 
 * @author Sistema SAC
 * @version 1.0
 * @date 2025-08-25
 */

require_once __DIR__ . '/src/config/database.php';

class ImportadorComplementosPago
{
    private $pdo;
    private $stats = [
        'procesados' => 0,
        'pagos_insertados' => 0,
        'documentos_relacionados' => 0,
        'impuestos_procesados' => 0,
        'errores' => []
    ];

    public function __construct()
    {
        $this->pdo = getDatabase();
    }

    /**
     * Procesar todos los CFDIs de tipo Pago pendientes
     */
    public function procesarCFDIsPago()
    {
        echo "🔄 INICIANDO PROCESAMIENTO DE COMPLEMENTOS DE PAGO\n\n";
        
        // Buscar CFDIs tipo "P" que no tengan complementos procesados
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.uuid, c.archivo_xml, c.rfc_emisor, c.rfc_receptor, c.fecha
            FROM cfdi c
            LEFT JOIN cfdi_pagos p ON c.id = p.cfdi_id
            WHERE c.tipo = 'P' 
            AND p.id IS NULL
            ORDER BY c.fecha DESC
        ");
        
        $stmt->execute();
        $cfdis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "📊 CFDIs de pago encontrados: " . count($cfdis) . "\n\n";
        
        foreach ($cfdis as $cfdi) {
            $this->procesarCFDIPago($cfdi);
        }
        
        $this->mostrarEstadisticas();
    }

    /**
     * Procesar un CFDI de pago específico
     */
    private function procesarCFDIPago($cfdi)
    {
        try {
            $this->stats['procesados']++;
            
            echo "🔍 Procesando CFDI: {$cfdi['uuid']}\n";
            echo "   RFC Emisor: {$cfdi['rfc_emisor']}\n";
            echo "   RFC Receptor: {$cfdi['rfc_receptor']}\n";
            echo "   Fecha: {$cfdi['fecha']}\n";
            
            // Cargar y parsear XML
            $xmlContent = $cfdi['archivo_xml'];
            if (file_exists($xmlContent)) {
                $xmlContent = file_get_contents($xmlContent);
            }
            
            $xml = simplexml_load_string($xmlContent);
            if (!$xml) {
                throw new Exception("Error al parsear XML del CFDI {$cfdi['uuid']}");
            }
            
            // Registrar namespaces
            $xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/3');
            $xml->registerXPathNamespace('cfdi40', 'http://www.sat.gob.mx/cfd/4');
            $xml->registerXPathNamespace('pago10', 'http://www.sat.gob.mx/Pagos');
            $xml->registerXPathNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');
            
            // Buscar complemento de pago
            $complementoPago = $this->extraerComplementoPago($xml);
            
            if ($complementoPago) {
                $this->procesarComplementoPago($cfdi['id'], $complementoPago);
                echo "   ✅ Complemento de pago procesado\n\n";
            } else {
                echo "   ⚠️  No se encontró complemento de pago válido\n\n";
            }
            
        } catch (Exception $e) {
            $this->stats['errores'][] = "CFDI {$cfdi['uuid']}: " . $e->getMessage();
            echo "   ❌ Error: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Extraer complemento de pago del XML
     */
    private function extraerComplementoPago($xml)
    {
        // Intentar CFDI 4.0 primero
        $pagos = $xml->xpath('//cfdi40:Complemento//pago20:Pagos');
        if (empty($pagos)) {
            // Intentar CFDI 3.3
            $pagos = $xml->xpath('//cfdi:Complemento//pago10:Pagos');
        }
        
        if (empty($pagos)) {
            return null;
        }
        
        return $pagos[0];
    }

    /**
     * Procesar complemento de pago completo
     */
    private function procesarComplementoPago($cfdi_id, $complementoPago)
    {
        // Obtener totales si existen
        $totales = $this->extraerTotales($complementoPago);
        
        // Procesar cada pago individual
        if (isset($complementoPago->Pago)) {
            foreach ($complementoPago->Pago as $pago) {
                $pago_id = $this->insertarPago($cfdi_id, $pago);
                
                if ($pago_id) {
                    $this->stats['pagos_insertados']++;
                    
                    // Procesar documentos relacionados
                    if (isset($pago->DoctoRelacionado)) {
                        foreach ($pago->DoctoRelacionado as $docRelacionado) {
                            $doc_id = $this->insertarDocumentoRelacionado($pago_id, $docRelacionado);
                            
                            if ($doc_id) {
                                $this->stats['documentos_relacionados']++;
                                
                                // Procesar impuestos del documento relacionado
                                if (isset($docRelacionado->ImpuestosDR)) {
                                    $this->procesarImpuestosDR($doc_id, $docRelacionado->ImpuestosDR);
                                }
                            }
                        }
                    }
                    
                    // Insertar totales si existen
                    if ($totales) {
                        $this->insertarTotales($pago_id, $totales);
                    }
                }
            }
        }
    }

    /**
     * Insertar un pago individual
     */
    private function insertarPago($cfdi_id, $pago)
    {
        $attrs = $pago->attributes();
        
        $stmt = $this->pdo->prepare("
            INSERT INTO cfdi_pagos (
                cfdi_id, fecha_pago, forma_pago_p, moneda_p, tipo_cambio_p, monto,
                num_operacion, rfc_emisor_cta_ord, nom_banco_ord_ext, cta_ordenante,
                rfc_emisor_cta_ben, cta_beneficiario, tipo_cad_pago, cert_pago,
                cad_pago, sello_pago
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $fecha_pago = $this->convertirFecha((string)$attrs->FechaPago);
        
        $stmt->execute([
            $cfdi_id,
            $fecha_pago,
            (string)$attrs->FormaDePagoP ?? null,
            (string)$attrs->MonedaP ?? 'MXN',
            (float)$attrs->TipoCambioP ?? 1.0,
            (float)$attrs->Monto ?? 0.0,
            (string)$attrs->NumOperacion ?? null,
            (string)$attrs->RfcEmisorCtaOrd ?? null,
            (string)$attrs->NomBancoOrdExt ?? null,
            (string)$attrs->CtaOrdenante ?? null,
            (string)$attrs->RfcEmisorCtaBen ?? null,
            (string)$attrs->CtaBeneficiario ?? null,
            (string)$attrs->TipoCadPago ?? null,
            (string)$attrs->CertPago ?? null,
            (string)$attrs->CadPago ?? null,
            (string)$attrs->SelloPago ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }

    /**
     * Insertar documento relacionado
     */
    private function insertarDocumentoRelacionado($pago_id, $docRelacionado)
    {
        $attrs = $docRelacionado->attributes();
        
        $stmt = $this->pdo->prepare("
            INSERT INTO cfdi_pago_documentos_relacionados (
                pago_id, id_documento, serie, folio, moneda_dr, equivalencia_dr,
                num_parcialidad, imp_saldo_ant, imp_pagado, imp_saldo_insoluto,
                objetivo_imp_dr
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $pago_id,
            (string)$attrs->IdDocumento,
            (string)$attrs->Serie ?? null,
            (string)$attrs->Folio ?? null,
            (string)$attrs->MonedaDR ?? 'MXN',
            (float)$attrs->EquivalenciaDR ?? 1.0,
            (int)$attrs->NumParcialidad ?? 1,
            (float)$attrs->ImpSaldoAnt ?? 0.0,
            (float)$attrs->ImpPagado ?? 0.0,
            (float)$attrs->ImpSaldoInsoluto ?? 0.0,
            (string)$attrs->ObjetoImpDR ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }

    /**
     * Procesar impuestos de documento relacionado
     */
    private function procesarImpuestosDR($doc_id, $impuestosDR)
    {
        // Procesar traslados
        if (isset($impuestosDR->TrasladosDR->TrasladoDR)) {
            foreach ($impuestosDR->TrasladosDR->TrasladoDR as $traslado) {
                $this->insertarImpuestoDR($doc_id, $traslado);
                $this->stats['impuestos_procesados']++;
            }
        }
        
        // Procesar retenciones
        if (isset($impuestosDR->RetencionesDR->RetencionDR)) {
            foreach ($impuestosDR->RetencionesDR->RetencionDR as $retencion) {
                $this->insertarImpuestoDR($doc_id, $retencion);
                $this->stats['impuestos_procesados']++;
            }
        }
    }

    /**
     * Insertar impuesto de documento relacionado
     */
    private function insertarImpuestoDR($doc_id, $impuesto)
    {
        $attrs = $impuesto->attributes();
        
        $stmt = $this->pdo->prepare("
            INSERT INTO cfdi_pago_impuestos_dr (
                documento_relacionado_id, base_dr, impuesto_dr, tipo_factor_dr,
                tasa_o_cuota_dr, importe_dr
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $doc_id,
            (float)$attrs->BaseDR ?? 0.0,
            (string)$attrs->ImpuestoDR ?? null,
            (string)$attrs->TipoFactorDR ?? null,
            (float)$attrs->TasaOCuotaDR ?? 0.0,
            (float)$attrs->ImporteDR ?? 0.0
        ]);
    }

    /**
     * Extraer totales del complemento
     */
    private function extraerTotales($complementoPago)
    {
        if (isset($complementoPago->Totales)) {
            return $complementoPago->Totales->attributes();
        }
        return null;
    }

    /**
     * Insertar totales de impuestos
     */
    private function insertarTotales($pago_id, $totales)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO cfdi_pago_totales (
                pago_id, total_retenciones_iva, total_retenciones_isr, total_retenciones_ieps,
                total_traslados_base_iva16, total_traslados_impuesto_iva16,
                total_traslados_base_iva0, total_traslados_base_iva_exento, monto_total_pagos
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $pago_id,
            (float)$totales->TotalRetencionesIVA ?? 0.0,
            (float)$totales->TotalRetencionesISR ?? 0.0,
            (float)$totales->TotalRetencionesIEPS ?? 0.0,
            (float)$totales->TotalTrasladosBaseIVA16 ?? 0.0,
            (float)$totales->TotalTrasladosImpuestoIVA16 ?? 0.0,
            (float)$totales->TotalTrasladosBaseIVA0 ?? 0.0,
            (float)$totales->TotalTrasladosBaseIVAExento ?? 0.0,
            (float)$totales->MontoTotalPagos ?? 0.0
        ]);
    }

    /**
     * Convertir fecha a formato MySQL
     */
    private function convertirFecha($fecha)
    {
        if (empty($fecha)) return null;
        
        try {
            $dt = new DateTime($fecha);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Mostrar estadísticas finales
     */
    private function mostrarEstadisticas()
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 ESTADÍSTICAS FINALES DE PROCESAMIENTO\n";
        echo str_repeat("=", 60) . "\n";
        echo "CFDIs procesados: " . $this->stats['procesados'] . "\n";
        echo "Pagos insertados: " . $this->stats['pagos_insertados'] . "\n";
        echo "Documentos relacionados: " . $this->stats['documentos_relacionados'] . "\n";
        echo "Impuestos procesados: " . $this->stats['impuestos_procesados'] . "\n";
        echo "Errores: " . count($this->stats['errores']) . "\n";
        
        if (!empty($this->stats['errores'])) {
            echo "\n❌ ERRORES ENCONTRADOS:\n";
            foreach ($this->stats['errores'] as $error) {
                echo "   - $error\n";
            }
        }
        
        echo "\n✅ PROCESAMIENTO COMPLETADO\n";
    }

    /**
     * Obtener estadísticas de complementos de pago
     */
    public function obtenerEstadisticasPagos()
    {
        $stats = [];
        
        // Total de CFDIs de pago
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P'");
        $stats['total_cfdis_pago'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // CFDIs con complementos procesados
        $stmt = $this->pdo->query("
            SELECT COUNT(DISTINCT c.id) as procesados 
            FROM cfdi c 
            INNER JOIN cfdi_pagos p ON c.id = p.cfdi_id 
            WHERE c.tipo = 'P'
        ");
        $stats['cfdis_con_complementos'] = $stmt->fetch(PDO::FETCH_ASSOC)['procesados'];
        
        // Total de pagos
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi_pagos");
        $stats['total_pagos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de documentos relacionados
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi_pago_documentos_relacionados");
        $stats['total_documentos_relacionados'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Pagos por forma de pago
        $stmt = $this->pdo->query("
            SELECT forma_pago_p, COUNT(*) as cantidad 
            FROM cfdi_pagos 
            GROUP BY forma_pago_p 
            ORDER BY cantidad DESC
        ");
        $stats['por_forma_pago'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
}

// Ejecución directa del script
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $importador = new ImportadorComplementosPago();
    $importador->procesarCFDIsPago();
}
?>
