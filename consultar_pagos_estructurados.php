<?php
/**
 * Script para consultar complementos de pago específicos por RFC emisor
 * Utiliza las tablas estructuradas después del reprocesamiento
 */

require_once 'src/config/database.php';

function consultarComplementosPagoEstructurados($rfc_emisor = null, $limite = 50) {
    try {
        $pdo = getDatabase();
        
        // Si no se especifica RFC, mostrar lista de RFCs con complementos de pago
        if (!$rfc_emisor) {
            echo "=== RFCs CON COMPLEMENTOS DE PAGO (DATOS ESTRUCTURADOS) ===\n\n";
            
            $sql = "
                SELECT 
                    c.rfc_emisor, 
                    c.nombre_emisor, 
                    COUNT(cp.id) as total_complementos,
                    MIN(c.fecha) as fecha_desde,
                    MAX(c.fecha) as fecha_hasta,
                    COUNT(DISTINCT c.rfc_receptor) as receptores_unicos
                FROM cfdi c
                INNER JOIN cfdi_pagos cp ON c.id = cp.cfdi_id
                WHERE c.tipo = 'P' 
                GROUP BY c.rfc_emisor, c.nombre_emisor 
                ORDER BY total_complementos DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $emisores = $stmt->fetchAll();
            
            printf("%-15s %-35s %-8s %-8s %-12s %s\n", "RFC", "NOMBRE", "COMP.", "RECEP.", "DESDE", "HASTA");
            echo str_repeat("-", 100) . "\n";
            
            foreach ($emisores as $emisor) {
                printf("%-15s %-35s %-8d %-8d %-12s %s\n", 
                    $emisor['rfc_emisor'],
                    substr($emisor['nombre_emisor'], 0, 35),
                    $emisor['total_complementos'],
                    $emisor['receptores_unicos'],
                    date('Y-m-d', strtotime($emisor['fecha_desde'])),
                    date('Y-m-d', strtotime($emisor['fecha_hasta']))
                );
            }
            
            echo "\n" . str_repeat("=", 100) . "\n";
            echo "Para ver detalles de un RFC específico, ejecuta:\n";
            echo "php " . basename(__FILE__) . " RFC_EMISOR\n\n";
            
            return $emisores;
        }
        
        // Consultar complementos de pago para RFC específico con datos estructurados
        echo "=== COMPLEMENTOS DE PAGO ESTRUCTURADOS PARA RFC: $rfc_emisor ===\n\n";
        
        $sql = "
            SELECT 
                c.id,
                c.uuid,
                c.serie,
                c.folio, 
                c.fecha,
                c.rfc_emisor,
                c.nombre_emisor,
                c.rfc_receptor,
                c.nombre_receptor,
                c.total,
                c.moneda,
                c.archivo_xml,
                c.lugar_expedicion,
                c.version,
                cp.id as pago_id,
                cp.fecha_pago,
                cp.forma_pago,
                cp.moneda as pago_moneda,
                cp.tipo_cambio,
                cp.monto,
                cp.num_operacion,
                cp.rfc_emisor_cuenta_ordenante,
                cp.cuenta_ordenante,
                cp.rfc_emisor_cuenta_beneficiario,
                cp.cuenta_beneficiario,
                tf.uuid as timbre_uuid,
                tf.fecha_timbrado,
                tf.sello_cfd,
                tf.no_certificado_sat,
                tf.rfc_prov_certif,
                tf.version as timbre_version
            FROM cfdi c
            INNER JOIN cfdi_pagos cp ON c.id = cp.cfdi_id
            LEFT JOIN cfdi_timbre_fiscal tf ON c.id = tf.cfdi_id
            WHERE c.tipo = 'P' AND c.rfc_emisor = ?
            ORDER BY c.fecha DESC
            LIMIT 50
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rfc_emisor]);
        $complementos = $stmt->fetchAll();
        
        if (empty($complementos)) {
            echo "No se encontraron complementos de pago para el RFC: $rfc_emisor\n";
            return [];
        }
        
        echo "TOTAL DE COMPLEMENTOS ENCONTRADOS: " . count($complementos) . "\n";
        echo str_repeat("=", 120) . "\n\n";
        
        foreach ($complementos as $index => $comp) {
            echo "COMPLEMENTO " . ($index + 1) . ":\n";
            echo str_repeat("-", 80) . "\n";
            
            // Información básica del CFDI
            echo "INFORMACIÓN DEL CFDI:\n";
            echo "  UUID                : " . $comp['uuid'] . "\n";
            echo "  Serie/Folio         : " . $comp['serie'] . "/" . $comp['folio'] . "\n";
            echo "  Fecha               : " . $comp['fecha'] . "\n";
            echo "  Versión CFDI        : " . ($comp['version'] ?: 'N/A') . "\n";
            echo "  Lugar Expedición    : " . $comp['lugar_expedicion'] . "\n";
            echo "  Total               : $" . number_format($comp['total'], 2) . " " . $comp['moneda'] . "\n";
            
            // Información del emisor y receptor
            echo "\nPARTES:\n";
            echo "  Emisor              : " . $comp['rfc_emisor'] . " - " . $comp['nombre_emisor'] . "\n";
            echo "  Receptor            : " . $comp['rfc_receptor'] . " - " . $comp['nombre_receptor'] . "\n";
            
            // Información del timbre fiscal
            if ($comp['timbre_uuid']) {
                echo "\nTIMBRE FISCAL:\n";
                echo "  UUID Timbre         : " . $comp['timbre_uuid'] . "\n";
                echo "  Fecha Timbrado      : " . ($comp['fecha_timbrado'] ?: 'N/A') . "\n";
                echo "  Certificado SAT     : " . ($comp['no_certificado_sat'] ?: 'N/A') . "\n";
                echo "  RFC Proveedor       : " . ($comp['rfc_prov_certif'] ?: 'N/A') . "\n";
                echo "  Versión Timbre      : " . ($comp['timbre_version'] ?: 'N/A') . "\n";
            }
            
            // Información específica del pago
            echo "\nDETALLE DEL PAGO:\n";
            echo "  ID Pago             : " . $comp['pago_id'] . "\n";
            echo "  Fecha Pago          : " . ($comp['fecha_pago'] ?: 'N/A') . "\n";
            echo "  Forma de Pago       : " . ($comp['forma_pago'] ?: 'N/A') . "\n";
            echo "  Moneda              : " . ($comp['pago_moneda'] ?: 'N/A') . "\n";
            echo "  Tipo de Cambio      : " . ($comp['tipo_cambio'] ? number_format($comp['tipo_cambio'], 6) : 'N/A') . "\n";
            echo "  Monto               : $" . number_format($comp['monto'], 2) . "\n";
            
            if ($comp['num_operacion']) {
                echo "  Núm. Operación      : " . $comp['num_operacion'] . "\n";
            }
            
            if ($comp['rfc_emisor_cuenta_ordenante']) {
                echo "  RFC Cta. Ordenante  : " . $comp['rfc_emisor_cuenta_ordenante'] . "\n";
            }
            
            if ($comp['cuenta_ordenante']) {
                echo "  Cuenta Ordenante    : " . $comp['cuenta_ordenante'] . "\n";
            }
            
            if ($comp['rfc_emisor_cuenta_beneficiario']) {
                echo "  RFC Cta. Benefic.   : " . $comp['rfc_emisor_cuenta_beneficiario'] . "\n";
            }
            
            if ($comp['cuenta_beneficiario']) {
                echo "  Cuenta Beneficiario : " . $comp['cuenta_beneficiario'] . "\n";
            }
            
            // Información del archivo XML
            echo "\nARCHIVO:\n";
            if ($comp['archivo_xml']) {
                echo "  Archivo XML         : " . $comp['archivo_xml'] . "\n";
                $archivo_existe = file_exists($comp['archivo_xml']) ? "✓ Disponible" : "✗ No encontrado";
                echo "  Estado del archivo  : " . $archivo_existe . "\n";
            } else {
                echo "  Archivo XML         : No especificado\n";
            }
            
            echo "\n" . str_repeat("-", 120) . "\n\n";
        }
        
        return $complementos;
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        return [];
    }
}

// Verificar si se pasó un RFC como parámetro
$rfc_consultar = $argc > 1 ? $argv[1] : null;

// Ejecutar la consulta
$resultado = consultarComplementosPagoEstructurados($rfc_consultar);

if (!$rfc_consultar && !empty($resultado)) {
    echo "\nEJEMPLO DE USO:\n";
    echo "php " . basename(__FILE__) . " " . $resultado[0]['rfc_emisor'] . "\n";
}
?>
