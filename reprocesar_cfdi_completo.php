<?php
/**
 * Script para reprocesar todos los CFDIs existentes con la nueva estructura mejorada
 * Extrae todos los campos nuevos y actualiza las tablas especializadas
 */

require_once __DIR__ . '/src/config/database.php';

$pdo = getDatabase();

echo "=== REPROCESAMIENTO DE CFDIs CON ESTRUCTURA COMPLETA ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Obtener todos los CFDIs existentes
$stmt = $pdo->query("SELECT id, archivo_xml, uuid FROM cfdi ORDER BY id");
$cfdis = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de CFDIs a reprocesar: " . count($cfdis) . "\n\n";

$procesados = 0;
$actualizados = 0;
$errores = 0;

foreach ($cfdis as $cfdi) {
    $procesados++;
    
    if ($procesados % 100 == 0) {
        echo "Procesados: $procesados/" . count($cfdis) . " (" . round($procesados/count($cfdis)*100, 1) . "%)\n";
    }
    
    try {
        $xmlFile = $cfdi['archivo_xml'];
        
        // Verificar que el archivo XML existe
        if (!file_exists($xmlFile)) {
            echo "ERROR: Archivo no encontrado: $xmlFile\n";
            $errores++;
            continue;
        }
        
        // Parsear el XML usando la función mejorada
        $data = parseCfdiCompleto($xmlFile);
        
        if (isset($data['error']) || empty($data['uuid'])) {
            echo "ERROR: No se pudo parsear $xmlFile\n";
            $errores++;
            continue;
        }
        
        // Actualizar campos nuevos en tabla cfdi
        $tfdData = extractTimbreFiscalDigital($data);
        
        $sqlUpdate = "UPDATE cfdi SET 
            version = ?, 
            sello_cfd = ?, 
            sello_sat = ?, 
            no_certificado_sat = ?, 
            rfc_prov_certif = ?, 
            estatus_sat = ?, 
            cfdi_relacionados = ?
            WHERE id = ?";
        
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([
            $data['version'] ?? null,
            $data['comprobante']['Sello'] ?? null,
            $tfdData['SelloSAT'] ?? null,
            $tfdData['NoCertificadoSAT'] ?? null,
            $tfdData['RfcProvCertif'] ?? null,
            'Vigente',
            extractCfdiRelacionados($data),
            $cfdi['id']
        ]);
        
        // Limpiar tablas especializadas existentes para este CFDI
        $pdo->prepare("DELETE FROM cfdi_timbre_fiscal WHERE cfdi_id = ?")->execute([$cfdi['id']]);
        $stmtPagos = $pdo->prepare("SELECT id FROM cfdi_pagos WHERE cfdi_id = ?");
        $stmtPagos->execute([$cfdi['id']]);
        $pagosIds = $stmtPagos->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($pagosIds)) {
            $pdo->prepare("DELETE FROM cfdi_pago_documentos_relacionados WHERE pago_id IN (" . implode(',', array_fill(0, count($pagosIds), '?')) . ")")->execute($pagosIds);
        }
        $pdo->prepare("DELETE FROM cfdi_pagos WHERE cfdi_id = ?")->execute([$cfdi['id']]);
        
        // Insertar en tablas especializadas
        insertSpecializedComplements($pdo, $cfdi['id'], $data);
        
        $actualizados++;
        
    } catch (Exception $e) {
        echo "ERROR procesando CFDI ID {$cfdi['id']}: " . $e->getMessage() . "\n";
        $errores++;
    }
}

echo "\n=== RESUMEN FINAL ===\n";
echo "Total procesados: $procesados\n";
echo "Actualizados exitosamente: $actualizados\n";
echo "Errores: $errores\n";
echo "Fecha fin: " . date('Y-m-d H:i:s') . "\n";

// Verificar estadísticas finales
echo "\n=== ESTADÍSTICAS FINALES ===\n";

$stats = [
    'cfdi' => $pdo->query("SELECT COUNT(*) FROM cfdi")->fetchColumn(),
    'cfdi_timbre_fiscal' => $pdo->query("SELECT COUNT(*) FROM cfdi_timbre_fiscal")->fetchColumn(),
    'cfdi_pagos' => $pdo->query("SELECT COUNT(*) FROM cfdi_pagos")->fetchColumn(),
    'cfdi_pago_documentos_relacionados' => $pdo->query("SELECT COUNT(*) FROM cfdi_pago_documentos_relacionados")->fetchColumn(),
    'cfdi_conceptos' => $pdo->query("SELECT COUNT(*) FROM cfdi_conceptos")->fetchColumn(),
    'cfdi_impuestos' => $pdo->query("SELECT COUNT(*) FROM cfdi_impuestos")->fetchColumn(),
    'cfdi_complementos' => $pdo->query("SELECT COUNT(*) FROM cfdi_complementos")->fetchColumn()
];

foreach ($stats as $tabla => $count) {
    echo "$tabla: $count registros\n";
}

// Mostrar distribución por RFC
echo "\n=== DISTRIBUCIÓN POR RFC ===\n";
$rfcStats = $pdo->query("SELECT rfc_consultado, direccion_flujo, COUNT(*) as total FROM cfdi GROUP BY rfc_consultado, direccion_flujo ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rfcStats as $stat) {
    echo "{$stat['rfc_consultado']} ({$stat['direccion_flujo']}): {$stat['total']} CFDIs\n";
}

/**
 * Función mejorada de parseo de CFDI con todos los campos
 */
function parseCfdiCompleto($xmlFile) {
    // Incluir las funciones del script principal
    include_once __DIR__ . '/importar_cfdi.php';
    
    // Usar la función parseCfdi ya existente que tiene toda la lógica
    return parseCfdi($xmlFile);
}

// Las funciones auxiliares ya están definidas en importar_cfdi.php
function extractTimbreFiscalDigital($data) {
    $tfdData = [
        'FechaTimbrado' => null,
        'SelloSAT' => null,
        'NoCertificadoSAT' => null,
        'RfcProvCertif' => null
    ];
    
    if (isset($data['complementos']['TimbreFiscalDigital'])) {
        $tfd = $data['complementos']['TimbreFiscalDigital'][0] ?? [];
        $tfdData['FechaTimbrado'] = $tfd['FechaTimbrado'] ?? null;
        $tfdData['SelloSAT'] = $tfd['SelloSAT'] ?? null;
        $tfdData['NoCertificadoSAT'] = $tfd['NoCertificadoSAT'] ?? null;
        $tfdData['RfcProvCertif'] = $tfd['RfcProvCertif'] ?? null;
    }
    
    return $tfdData;
}

function extractCfdiRelacionados($data) {
    $relacionados = [];
    
    if (isset($data['comprobante']['CfdiRelacionados'])) {
        if (is_array($data['comprobante']['CfdiRelacionados'])) {
            foreach ($data['comprobante']['CfdiRelacionados'] as $rel) {
                if (isset($rel['UUID'])) {
                    $relacionados[] = $rel['UUID'];
                }
            }
        }
    }
    
    return !empty($relacionados) ? json_encode($relacionados) : null;
}

function insertSpecializedComplements($pdo, $cfdi_id, $data) {
    // Insertar TimbreFiscalDigital
    if (isset($data['complementos']['TimbreFiscalDigital'])) {
        $tfd = $data['complementos']['TimbreFiscalDigital'][0] ?? [];
        $sql = "INSERT INTO cfdi_timbre_fiscal (cfdi_id, version, uuid, fecha_timbrado, rfc_prov_certif, leyenda, sello_cfd, no_certificado_sat, sello_sat) VALUES (?,?,?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $cfdi_id,
            $tfd['Version'] ?? null,
            $tfd['UUID'] ?? null,
            $tfd['FechaTimbrado'] ?? null,
            $tfd['RfcProvCertif'] ?? null,
            $tfd['Leyenda'] ?? null,
            $tfd['SelloCFD'] ?? null,
            $tfd['NoCertificadoSAT'] ?? null,
            $tfd['SelloSAT'] ?? null
        ]);
    }
    
    // Insertar Pagos
    if (isset($data['complementos']['Pagos'])) {
        $pagos = $data['complementos']['Pagos'][0] ?? [];
        if (isset($pagos['Pago'])) {
            foreach ($pagos['Pago'] as $pago) {
                $sql = "INSERT INTO cfdi_pagos (cfdi_id, fecha_pago, forma_pago_p, moneda_p, tipo_cambio_p, monto, num_operacion, rfc_emisor_cta_ord, nom_banco_ord_ext, cta_ordenante, rfc_emisor_cta_ben, cta_beneficiario) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $cfdi_id,
                    $pago['FechaPago'] ?? null,
                    $pago['FormaDePagoP'] ?? null,
                    $pago['MonedaP'] ?? null,
                    $pago['TipoCambioP'] ?? null,
                    $pago['Monto'] ?? null,
                    $pago['NumOperacion'] ?? null,
                    $pago['RfcEmisorCtaOrd'] ?? null,
                    $pago['NomBancoOrdExt'] ?? null,
                    $pago['CtaOrdenante'] ?? null,
                    $pago['RfcEmisorCtaBen'] ?? null,
                    $pago['CtaBeneficiario'] ?? null
                ]);
                $pago_id = $pdo->lastInsertId();
                
                // Insertar DocumentoRelacionado
                if (isset($pago['DoctoRelacionado'])) {
                    foreach ($pago['DoctoRelacionado'] as $doc) {
                        $sql = "INSERT INTO cfdi_pago_documentos_relacionados (pago_id, id_documento, serie, folio, moneda_dr, equivalencia_dr, num_parcialidad, imp_saldo_ant, imp_pagado, imp_saldo_insoluto, objetivo_imp_dr) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            $pago_id,
                            $doc['IdDocumento'] ?? null,
                            $doc['Serie'] ?? null,
                            $doc['Folio'] ?? null,
                            $doc['MonedaDR'] ?? null,
                            $doc['EquivalenciaDR'] ?? null,
                            $doc['NumParcialidad'] ?? null,
                            $doc['ImpSaldoAnt'] ?? null,
                            $doc['ImpPagado'] ?? null,
                            $doc['ImpSaldoInsoluto'] ?? null,
                            $doc['ObjetoImpDR'] ?? null
                        ]);
                    }
                }
            }
        }
    }
}
