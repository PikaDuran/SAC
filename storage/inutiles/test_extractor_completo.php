<?php

/**
 * Extractor completo de complementos de pago con TODOS los campos
 */

require_once 'src/config/database.php';

function extraerComplementoPagosCompleto($xmlContent)
{
    $result = [
        'pagos' => [],
        'documentos_relacionados' => []
    ];

    // Buscar TipoDeComprobante="P"
    if (!preg_match('/TipoDeComprobante=["\']P["\']/', $xmlContent)) {
        return $result;
    }

    // Extraer toda la sección de Pagos
    if (preg_match('/<pago10:Pagos[^>]*>.*?<\/pago10:Pagos>/s', $xmlContent, $matches)) {
        $pagosXml = $matches[0];

        // Extraer cada Pago individual
        if (preg_match_all('/<pago10:Pago([^>]*)>(.*?)<\/pago10:Pago>/s', $pagosXml, $pagoMatches, PREG_SET_ORDER)) {
            foreach ($pagoMatches as $pagoMatch) {
                $atributosPago = $pagoMatch[1];
                $contenidoPago = $pagoMatch[2];

                // Extraer TODOS los atributos del pago
                $pago = [];

                // Atributos obligatorios
                preg_match('/FechaPago=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['fecha_pago'] = $m[1];
                preg_match('/FormaDePagoP=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['forma_pago'] = $m[1];
                preg_match('/MonedaP=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['moneda'] = $m[1];
                preg_match('/Monto=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['monto'] = $m[1];

                // Atributos opcionales
                preg_match('/TipoCambioP=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['tipo_cambio'] = $m[1];
                preg_match('/NumOperacion=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['num_operacion'] = $m[1];
                preg_match('/RfcEmisorCtaOrd=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['rfc_emisor_cuenta_ordenante'] = $m[1];
                preg_match('/NomBancoOrdExt=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['nombre_banco_extranjero'] = $m[1];
                preg_match('/CtaOrdenante=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['cuenta_ordenante'] = $m[1];
                preg_match('/RfcEmisorCtaBen=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['rfc_emisor_cuenta_beneficiario'] = $m[1];
                preg_match('/CtaBeneficiario=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['cuenta_beneficiario'] = $m[1];
                preg_match('/TipoCadPago=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['tipo_cadena_pago'] = $m[1];
                preg_match('/CertPago=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['certificado_pago'] = $m[1];
                preg_match('/CadPago=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['cadena_pago'] = $m[1];
                preg_match('/SelloPago=["\']([^"\']*)["\']/', $atributosPago, $m) && $pago['sello_pago'] = $m[1];

                // Versión del complemento
                if (preg_match('/Version=["\']([^"\']*)["\']/', $pagosXml, $m)) {
                    $pago['version'] = $m[1];
                }

                $result['pagos'][] = $pago;

                // Extraer TODOS los documentos relacionados
                if (preg_match_all('/<pago10:DoctoRelacionado([^>]*)\/?>/s', $contenidoPago, $docMatches, PREG_SET_ORDER)) {
                    foreach ($docMatches as $docMatch) {
                        $atributosDoc = $docMatch[1];

                        $documento = [];

                        // Atributos obligatorios
                        preg_match('/IdDocumento=["\']([^"\']*)["\']/', $atributosDoc, $m) && $documento['uuid_documento'] = $m[1];
                        preg_match('/MonedaDR=["\']([^"\']*)["\']/', $atributosDoc, $m) && $documento['moneda_dr'] = $m[1];
                        preg_match('/NumParcialidad=["\']([^"\']*)["\']/', $atributosDoc, $m) && $documento['num_parcialidad'] = $m[1];
                        preg_match('/ImpSaldoAnt=["\']([^"\']*)["\']/', $atributosDoc, $m) && $documento['imp_saldo_ant'] = $m[1];
                        preg_match('/ImpPagado=["\']([^"\']*)["\']/', $atributosDoc, $m) && $documento['imp_pagado'] = $m[1];
                        preg_match('/ImpSaldoInsoluto=["\']([^"\']*)["\']/', $atributosDoc, $m) && $documento['imp_saldo_insoluto'] = $m[1];

                        // Atributos opcionales
                        preg_match('/Serie=["\']([^"\']*)["\']/', $atributosDoc, $m) && $documento['serie'] = $m[1];
                        preg_match('/Folio=["\']([^"\']*)["\']/', $atributosDoc, $m) && $documento['folio'] = $m[1];
                        preg_match('/EquivalenciaDR=["\']([^"\']*)["\']/', $atributosDoc, $m) && $documento['equivalencia_dr'] = $m[1];

                        // ESTE ES EL CAMPO QUE FALTABA: ObjetoImpDR
                        preg_match('/ObjetoImpDR=["\']([^"\']*)["\']/', $atributosDoc, $m) && $documento['objeto_imp_dr'] = $m[1];

                        $result['documentos_relacionados'][] = $documento;
                    }
                }
            }
        }
    }

    return $result;
}

// Probar con un CFDI de pago
try {
    $pdo = getDatabase();

    echo "=== PRUEBA DE EXTRACTOR COMPLETO ===\n\n";

    // Obtener un CFDI de pago
    $stmt = $pdo->prepare("SELECT id, uuid, archivo_xml FROM cfdi WHERE tipo = 'P' AND complemento_json IS NOT NULL LIMIT 1");
    $stmt->execute();
    $cfdi = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cfdi) {
        echo "Procesando CFDI ID: {$cfdi['id']}, UUID: {$cfdi['uuid']}\n";
        echo "Archivo: {$cfdi['archivo_xml']}\n\n";

        if (file_exists($cfdi['archivo_xml'])) {
            $xmlContent = file_get_contents($cfdi['archivo_xml']);
            $datos = extraerComplementoPagosCompleto($xmlContent);

            echo "PAGOS EXTRAÍDOS:\n";
            echo "================\n";
            foreach ($datos['pagos'] as $i => $pago) {
                echo "Pago " . ($i + 1) . ":\n";
                foreach ($pago as $campo => $valor) {
                    echo "  $campo: $valor\n";
                }
                echo "\n";
            }

            echo "DOCUMENTOS RELACIONADOS EXTRAÍDOS:\n";
            echo "==================================\n";
            foreach ($datos['documentos_relacionados'] as $i => $doc) {
                echo "Documento " . ($i + 1) . ":\n";
                foreach ($doc as $campo => $valor) {
                    echo "  $campo: $valor\n";
                }
                echo "\n";
            }

            echo "RESUMEN:\n";
            echo "========\n";
            echo "Pagos encontrados: " . count($datos['pagos']) . "\n";
            echo "Documentos relacionados: " . count($datos['documentos_relacionados']) . "\n";

            // Verificar qué campos están llenos
            if (count($datos['pagos']) > 0) {
                $camposLlenos = 0;
                $camposTotal = 0;
                foreach ($datos['pagos'][0] as $campo => $valor) {
                    $camposTotal++;
                    if (!empty($valor)) $camposLlenos++;
                }
                echo "Campos de pago llenos: $camposLlenos / $camposTotal\n";
            }

            if (count($datos['documentos_relacionados']) > 0) {
                $camposLlenos = 0;
                $camposTotal = 0;
                foreach ($datos['documentos_relacionados'][0] as $campo => $valor) {
                    $camposTotal++;
                    if (!empty($valor)) $camposLlenos++;
                }
                echo "Campos de documento llenos: $camposLlenos / $camposTotal\n";
            }
        } else {
            echo "Archivo XML no encontrado: {$cfdi['archivo_xml']}\n";
        }
    } else {
        echo "No se encontraron CFDIs de pago\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
