<?php

/**
 * Script CORREGIDO para reprocesar CFDIs de pago usando el importador real
 */

require_once 'src/config/database.php';
require_once 'importador_inteligente_cfdi.php';

class ReprocesadorComplementosPagoCorregido
{
    private $pdo;
    private $importador;

    public function __construct()
    {
        $this->pdo = getDatabase();
        $this->importador = new ImportadorInteligenteCFDI();
    }

    /**
     * Usar reflexión para acceder al método privado del importador real
     */
    private function extraerComplementoPagos($contenidoXML)
    {
        $reflection = new ReflectionClass($this->importador);
        $metodo = $reflection->getMethod('extraerComplementoPagos');
        $metodo->setAccessible(true);
        return $metodo->invoke($this->importador, $contenidoXML);
    }

    /**
     * Insertar pago en tabla estructurada
     */
    private function insertarPago($cfdi_id, $pago)
    {
        try {
            // Insertar en cfdi_pagos
            $sqlPago = "INSERT INTO cfdi_pagos (
                cfdi_id, version, fecha_pago, forma_pago, moneda, tipo_cambio, monto,
                num_operacion, rfc_emisor_cuenta_ordenante, nombre_banco_extranjero,
                cuenta_ordenante, rfc_emisor_cuenta_beneficiario, cuenta_beneficiario,
                tipo_cadena_pago, certificado_pago, cadena_pago, sello_pago
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmtPago = $this->pdo->prepare($sqlPago);
            $resultPago = $stmtPago->execute([
                $cfdi_id,
                '1.0', // version por defecto
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

            if (!$resultPago) {
                throw new Exception("Error al insertar pago: " . implode(", ", $stmtPago->errorInfo()));
            }

            $pago_id = $this->pdo->lastInsertId();

            // Insertar documentos relacionados
            if (isset($pago['documentos_relacionados']) && is_array($pago['documentos_relacionados'])) {
                foreach ($pago['documentos_relacionados'] as $documento) {
                    $sqlDoc = "INSERT INTO cfdi_pago_documentos_relacionados (
                        pago_id, uuid_documento, serie, folio, moneda_dr, equivalencia_dr,
                        num_parcialidad, imp_saldo_ant, imp_pagado, imp_saldo_insoluto, objeto_imp_dr
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                    $stmtDoc = $this->pdo->prepare($sqlDoc);
                    $resultDoc = $stmtDoc->execute([
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

                    if (!$resultDoc) {
                        throw new Exception("Error al insertar documento relacionado: " . implode(", ", $stmtDoc->errorInfo()));
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            echo "Error al insertar pago: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Reprocesar todos los CFDIs de pago
     */
    public function reprocesar()
    {
        try {
            echo "=== REPROCESAMIENTO CORREGIDO DE COMPLEMENTOS DE PAGO ===\n\n";

            // Obtener CFDIs de tipo P
            $sql = "SELECT id, uuid, archivo_xml FROM cfdi WHERE tipo = 'P' ORDER BY id LIMIT 10";
            $cfdisPago = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            echo "CFDIs de pago encontrados: " . count($cfdisPago) . "\n\n";

            $procesados = 0;
            $errores = 0;
            $conDatos = 0;

            foreach ($cfdisPago as $cfdi) {
                echo "Procesando CFDI ID: {$cfdi['id']}, UUID: {$cfdi['uuid']}\n";

                // Verificar archivo
                if (!file_exists($cfdi['archivo_xml'])) {
                    echo "  ❌ Archivo no existe: {$cfdi['archivo_xml']}\n";
                    $errores++;
                    continue;
                }

                // Leer XML completo
                $contenidoXML = file_get_contents($cfdi['archivo_xml']);
                if (!$contenidoXML) {
                    echo "  ❌ No se pudo leer el archivo\n";
                    $errores++;
                    continue;
                }

                // DEBUG: Verificar patrones
                $tieneP = preg_match('/TipoDeComprobante\s*=\s*["\']P["\']/', $contenidoXML);
                $tienePagos = preg_match('/<pago10:Pagos/', $contenidoXML);
                echo "  Debug: TipoP=$tieneP, Pagos=$tienePagos\n";

                // Extraer complementos usando el importador real
                $complementos = $this->extraerComplementoPagos($contenidoXML);

                if ($complementos) {
                    echo "  ✓ Encontrados " . count($complementos) . " pagos\n";
                    $conDatos++;

                    // Limpiar datos existentes
                    $this->pdo->exec("DELETE FROM cfdi_pago_documentos_relacionados WHERE pago_id IN (SELECT id FROM cfdi_pagos WHERE cfdi_id = {$cfdi['id']})");
                    $this->pdo->exec("DELETE FROM cfdi_pagos WHERE cfdi_id = {$cfdi['id']}");

                    // Insertar nuevos datos
                    foreach ($complementos as $pago) {
                        if (!$this->insertarPago($cfdi['id'], $pago)) {
                            $errores++;
                            break;
                        }
                    }

                    echo "  ✓ Datos insertados en tablas estructuradas\n";
                } else {
                    echo "  ❌ No se encontraron complementos de pago\n";
                }

                $procesados++;
                echo "\n";
            }

            // Resumen final
            echo "=== RESUMEN FINAL ===\n";
            echo "CFDIs procesados: $procesados\n";
            echo "CFDIs con datos: $conDatos\n";
            echo "Errores: $errores\n";

            // Verificar resultados
            $totalEnTablas = $this->pdo->query("SELECT COUNT(*) FROM cfdi_pagos")->fetchColumn();
            $totalDocs = $this->pdo->query("SELECT COUNT(*) FROM cfdi_pago_documentos_relacionados")->fetchColumn();

            echo "\nDatos en tablas estructuradas:\n";
            echo "cfdi_pagos: $totalEnTablas registros\n";
            echo "cfdi_pago_documentos_relacionados: $totalDocs registros\n";
        } catch (Exception $e) {
            echo "Error en reprocesamiento: " . $e->getMessage() . "\n";
        }
    }
}

// Ejecutar reprocesamiento
$reprocesador = new ReprocesadorComplementosPagoCorregido();
$reprocesador->reprocesar();
