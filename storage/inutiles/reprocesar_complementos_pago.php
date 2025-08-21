<?php

/**
 * REPROCESAR COMPLEMENTOS DE PAGO EXISTENTES
 * Procesa los CFDIs de pago que ya están en la BD pero sin datos estructurados
 */

require_once 'src/config/database.php';

class ReprocesadorComplementosPago
{

    private $pdo;
    private $stats = [
        'procesados' => 0,
        'pagos_insertados' => 0,
        'documentos_insertados' => 0,
        'errores' => 0,
        'sin_archivo' => 0
    ];

    public function __construct()
    {
        $this->pdo = getDatabase();
    }

    /**
     * Extrae complemento de pagos completo del XML
     */
    private function extraerComplementoPagos($contenidoXML)
    {
        // Verificar si es un CFDI de pago
        if (!preg_match('/TipoDeComprobante\s*=\s*["\']P["\']/', $contenidoXML)) {
            return null;
        }

        $resultado = [];

        // Buscar complemento de pagos (puede variar según versión)
        if (preg_match('/<pago10:Pagos[^>]*>.*?<\/pago10:Pagos>/s', $contenidoXML, $matches)) {
            $complemento = $matches[0];

            // Extraer pagos individuales con todo el bloque
            if (preg_match_all('/<pago10:Pago[^>]*>.*?<\/pago10:Pago>/s', $complemento, $pagosCompletos)) {
                foreach ($pagosCompletos[0] as $pagoCompleto) {
                    $pago = [];

                    // Extraer atributos del pago
                    if (preg_match('/<pago10:Pago[^>]*([^>]*)>/', $pagoCompleto, $pagoAttr)) {
                        $campos = [
                            'FechaPago' => 'fecha_pago',
                            'FormaDePagoP' => 'forma_pago',
                            'MonedaP' => 'moneda',
                            'TipoCambioP' => 'tipo_cambio',
                            'Monto' => 'monto',
                            'NumOperacion' => 'num_operacion',
                            'RfcEmisorCtaOrd' => 'rfc_emisor_cuenta_ordenante',
                            'NomBancoOrdExt' => 'nombre_banco_extranjero',
                            'CtaOrdenante' => 'cuenta_ordenante',
                            'RfcEmisorCtaBen' => 'rfc_emisor_cuenta_beneficiario',
                            'CtaBeneficiario' => 'cuenta_beneficiario',
                            'TipoCadPago' => 'tipo_cadena_pago',
                            'CertPago' => 'certificado_pago',
                            'CadPago' => 'cadena_pago',
                            'SelloPago' => 'sello_pago'
                        ];

                        foreach ($campos as $campo => $columna) {
                            if (preg_match('/' . $campo . '\s*=\s*["\']([^"\']*)["\']/', $pagoAttr[1], $matches)) {
                                $pago[$columna] = trim($matches[1]);
                            }
                        }
                    }

                    // Extraer documentos relacionados
                    $documentos = [];
                    if (preg_match_all('/<pago10:DoctoRelacionado[^>]*([^>\/]*)\/?>/s', $pagoCompleto, $docsMatches)) {
                        foreach ($docsMatches[0] as $docCompleto) {
                            $documento = [];

                            $camposDoc = [
                                'IdDocumento' => 'uuid_documento',
                                'Serie' => 'serie',
                                'Folio' => 'folio',
                                'MonedaDR' => 'moneda_dr',
                                'EquivalenciaDR' => 'equivalencia_dr',
                                'NumParcialidad' => 'num_parcialidad',
                                'ImpSaldoAnt' => 'imp_saldo_ant',
                                'ImpPagado' => 'imp_pagado',
                                'ImpSaldoInsoluto' => 'imp_saldo_insoluto',
                                'ObjetoImpDR' => 'objeto_imp_dr'
                            ];

                            foreach ($camposDoc as $campo => $columna) {
                                if (preg_match('/' . $campo . '\s*=\s*["\']([^"\']*)["\']/', $docCompleto, $matches)) {
                                    $documento[$columna] = trim($matches[1]);
                                }
                            }

                            if (!empty($documento)) {
                                $documentos[] = $documento;
                            }
                        }
                    }

                    if (!empty($pago)) {
                        $pago['documentos_relacionados'] = $documentos;
                        $resultado[] = $pago;
                    }
                }
            }
        }

        return !empty($resultado) ? $resultado : null;
    }

    /**
     * Inserta pago del complemento y documentos relacionados
     */
    private function insertarPago($cfdi_id, $pago)
    {
        // Insertar el pago principal
        $sql = "INSERT INTO cfdi_pagos (
            cfdi_id, fecha_pago, forma_pago, moneda, tipo_cambio, monto,
            num_operacion, rfc_emisor_cuenta_ordenante, nombre_banco_extranjero,
            cuenta_ordenante, rfc_emisor_cuenta_beneficiario, cuenta_beneficiario,
            tipo_cadena_pago, certificado_pago, cadena_pago, sello_pago
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            $cfdi_id,
            $pago['fecha_pago'] ?? null,
            $pago['forma_pago'] ?? null,
            $pago['moneda'] ?? null,
            $pago['tipo_cambio'] ?? null,
            $pago['monto'] ?? null,
            $pago['num_operacion'] ?? null,
            $pago['rfc_emisor_cuenta_ordenante'] ?? null,
            $pago['nombre_banco_extranjero'] ?? null,
            $pago['cuenta_ordenante'] ?? null,
            $pago['rfc_emisor_cuenta_beneficiario'] ?? null,
            $pago['cuenta_beneficiario'] ?? null,
            $pago['tipo_cadena_pago'] ?? null,
            $pago['certificado_pago'] ?? null,
            $pago['cadena_pago'] ?? null,
            $pago['sello_pago'] ?? null
        ]);

        if (!$result) {
            return false;
        }

        $pago_id = $this->pdo->lastInsertId();
        $this->stats['pagos_insertados']++;

        // Insertar documentos relacionados
        if (!empty($pago['documentos_relacionados'])) {
            foreach ($pago['documentos_relacionados'] as $documento) {
                if ($this->insertarDocumentoRelacionado($pago_id, $documento)) {
                    $this->stats['documentos_insertados']++;
                }
            }
        }

        return true;
    }

    /**
     * Inserta documento relacionado del pago
     */
    private function insertarDocumentoRelacionado($pago_id, $documento)
    {
        $sql = "INSERT INTO cfdi_pago_documentos_relacionados (
            pago_id, uuid_documento, serie, folio, moneda_dr, equivalencia_dr,
            num_parcialidad, imp_saldo_ant, imp_pagado, imp_saldo_insoluto, objeto_imp_dr
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $pago_id,
            $documento['uuid_documento'] ?? null,
            $documento['serie'] ?? null,
            $documento['folio'] ?? null,
            $documento['moneda_dr'] ?? null,
            $documento['equivalencia_dr'] ?? null,
            $documento['num_parcialidad'] ?? null,
            $documento['imp_saldo_ant'] ?? null,
            $documento['imp_pagado'] ?? null,
            $documento['imp_saldo_insoluto'] ?? null,
            $documento['objeto_imp_dr'] ?? null
        ]);
    }

    /**
     * Procesa un CFDI de pago individual
     */
    private function procesarCFDI($cfdi)
    {
        try {
            $this->stats['procesados']++;

            // Verificar si el archivo XML existe
            if (!file_exists($cfdi['archivo_xml'])) {
                $this->stats['sin_archivo']++;
                echo "Archivo no encontrado: {$cfdi['archivo_xml']}\n";
                return;
            }

            // Leer el archivo XML
            $contenido = file_get_contents($cfdi['archivo_xml']);
            if ($contenido === false) {
                throw new Exception("No se pudo leer el archivo XML");
            }

            // Limpiar contenido
            $contenido = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $contenido);

            // Extraer complementos de pago
            $complementoPagos = $this->extraerComplementoPagos($contenido);

            if (!$complementoPagos) {
                echo "No se encontraron complementos de pago en {$cfdi['uuid']}\n";
                return;
            }

            // Insertar pagos en tablas estructuradas
            foreach ($complementoPagos as $pago) {
                $this->insertarPago($cfdi['id'], $pago);
            }

            // Actualizar complemento_tipo y complemento_json en la tabla principal
            $sqlUpdate = "UPDATE cfdi SET 
                complemento_tipo = 'pago',
                complemento_json = ?
                WHERE id = ?";

            $stmtUpdate = $this->pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                json_encode($complementoPagos, JSON_UNESCAPED_UNICODE),
                $cfdi['id']
            ]);

            echo "✓ Procesado CFDI {$cfdi['uuid']} - Pagos: " . count($complementoPagos) . "\n";
        } catch (Exception $e) {
            $this->stats['errores']++;
            echo "✗ Error en CFDI {$cfdi['uuid']}: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Reprocesa todos los CFDIs de pago
     */
    public function reprocesar()
    {
        echo "=== REPROCESADOR DE COMPLEMENTOS DE PAGO ===\n\n";

        // Obtener CFDIs de tipo P que no tienen datos estructurados
        $sql = "SELECT c.id, c.uuid, c.archivo_xml 
                FROM cfdi c 
                LEFT JOIN cfdi_pagos cp ON c.id = cp.cfdi_id 
                WHERE c.tipo = 'P' AND cp.cfdi_id IS NULL
                ORDER BY c.id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $cfdis = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = count($cfdis);
        echo "CFDIs de pago a reprocesar: $total\n\n";

        if ($total == 0) {
            echo "No hay CFDIs de pago para reprocesar.\n";
            return;
        }

        foreach ($cfdis as $index => $cfdi) {
            if (($index + 1) % 10 == 0) {
                echo "Progreso: " . ($index + 1) . "/$total (" . round((($index + 1) / $total) * 100, 2) . "%)\n";
            }

            $this->procesarCFDI($cfdi);
        }

        $this->mostrarEstadisticas();
    }

    /**
     * Muestra estadísticas finales
     */
    private function mostrarEstadisticas()
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "ESTADÍSTICAS DE REPROCESAMIENTO\n";
        echo str_repeat("=", 60) . "\n";
        echo "CFDIs procesados: " . $this->stats['procesados'] . "\n";
        echo "Pagos insertados: " . $this->stats['pagos_insertados'] . "\n";
        echo "Documentos relacionados insertados: " . $this->stats['documentos_insertados'] . "\n";
        echo "Errores: " . $this->stats['errores'] . "\n";
        echo "Archivos no encontrados: " . $this->stats['sin_archivo'] . "\n";

        if ($this->stats['procesados'] > 0) {
            $exito = (($this->stats['procesados'] - $this->stats['errores']) / $this->stats['procesados']) * 100;
            echo "Tasa de éxito: " . number_format($exito, 2) . "%\n";
        }

        echo str_repeat("=", 60) . "\n";
    }
}

// Ejecutar reprocesamiento
try {
    echo "Iniciando reprocesamiento de complementos de pago...\n\n";

    $reprocesador = new ReprocesadorComplementosPago();
    $reprocesador->reprocesar();

    echo "\n✅ Reprocesamiento completado!\n";
    echo "\nAhora puedes consultar los complementos de pago usando las tablas:\n";
    echo "- cfdi_pagos\n";
    echo "- cfdi_pago_documentos_relacionados\n";
} catch (Exception $e) {
    echo "Error fatal: " . $e->getMessage() . "\n";
}
