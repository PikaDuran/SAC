<?php

/**
 * Script para consultar complementos de pago específicos por RFC emisor
 * Permite filtrar por RFC del emisor y ver todos los complementos de pago asociados
 */

require_once 'src/config/database.php';

try {
    $pdo = getDatabase();

    echo "=== CONSULTA DE COMPLEMENTOS DE PAGO POR RFC EMISOR ===\n\n";

    // Obtener lista de RFCs emisores que tienen complementos de pago
    $sql_emisores = "
        SELECT DISTINCT c.rfc_emisor, c.nombre_emisor, COUNT(cp.id) as total_complementos
        FROM cfdi c
        INNER JOIN cfdi_pagos cp ON c.id = cp.cfdi_id
        WHERE c.tipo = 'P'
        GROUP BY c.rfc_emisor, c.nombre_emisor
        ORDER BY total_complementos DESC
    ";

    $stmt = $pdo->prepare($sql_emisores);
    $stmt->execute();
    $emisores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "RFCs EMISORES CON COMPLEMENTOS DE PAGO:\n";
    echo str_repeat("=", 80) . "\n";
    printf("%-15s %-40s %s\n", "RFC", "NOMBRE", "COMPLEMENTOS");
    echo str_repeat("-", 80) . "\n";

    foreach ($emisores as $emisor) {
        printf(
            "%-15s %-40s %d\n",
            $emisor['rfc_emisor'],
            substr($emisor['nombre_emisor'], 0, 40),
            $emisor['total_complementos']
        );
    }

    echo "\n" . str_repeat("=", 80) . "\n\n";

    // Función para consultar complementos de un RFC específico
    function consultarComplementosPorRFC($pdo, $rfc_emisor)
    {
        $sql = "
            SELECT 
                c.uuid,
                c.fecha,
                c.rfc_emisor,
                c.nombre_emisor,
                c.rfc_receptor,
                c.nombre_receptor,
                c.total,
                c.archivo_xml,
                cp.fecha_pago,
                cp.forma_de_pago_p,
                cp.moneda_p,
                cp.tipo_cambio_p,
                cp.monto,
                cp.num_operacion,
                cp.rfc_emisor_cta_ord,
                cp.nom_banco_ord_ext,
                cp.cta_ordenante,
                cp.rfc_emisor_cta_ben,
                cp.cta_beneficiario,
                cpr.id_documento,
                cpr.serie,
                cpr.folio,
                cpr.moneda_dr,
                cpr.equivalencia_dr,
                cpr.num_parcialidad,
                cpr.imp_saldo_ant,
                cpr.imp_pagado,
                cpr.imp_saldo_insoluto
            FROM cfdi c
            INNER JOIN cfdi_pagos cp ON c.id = cp.cfdi_id
            LEFT JOIN cfdi_pago_documentos_relacionados cpr ON cp.id = cpr.pago_id
            WHERE c.rfc_emisor = ? AND c.tipo = 'P'
            ORDER BY c.fecha DESC, cp.fecha_pago DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rfc_emisor]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ejemplo de consulta para un RFC específico (si existe)
    if (!empty($emisores)) {
        $rfc_ejemplo = $emisores[0]['rfc_emisor'];
        echo "EJEMPLO - COMPLEMENTOS DE PAGO PARA RFC: $rfc_ejemplo\n";
        echo str_repeat("=", 100) . "\n";

        $complementos = consultarComplementosPorRFC($pdo, $rfc_ejemplo);

        if (!empty($complementos)) {
            foreach ($complementos as $comp) {
                echo "UUID: " . $comp['uuid'] . "\n";
                echo "Fecha CFDI: " . $comp['fecha'] . "\n";
                echo "Emisor: " . $comp['rfc_emisor'] . " - " . $comp['nombre_emisor'] . "\n";
                echo "Receptor: " . $comp['rfc_receptor'] . " - " . $comp['nombre_receptor'] . "\n";
                echo "Total: $" . number_format($comp['total'], 2) . "\n";
                echo "Archivo XML: " . ($comp['archivo_xml'] ?: 'No disponible') . "\n";
                echo "Fecha Pago: " . $comp['fecha_pago'] . "\n";
                echo "Forma de Pago: " . $comp['forma_de_pago_p'] . "\n";
                echo "Moneda: " . $comp['moneda_p'] . "\n";
                echo "Monto: $" . number_format($comp['monto'], 2) . "\n";

                if ($comp['num_operacion']) {
                    echo "Núm. Operación: " . $comp['num_operacion'] . "\n";
                }

                if ($comp['id_documento']) {
                    echo "Documento Relacionado: " . $comp['id_documento'] . "\n";
                    echo "  Serie/Folio: " . $comp['serie'] . "/" . $comp['folio'] . "\n";
                    echo "  Parcialidad: " . $comp['num_parcialidad'] . "\n";
                    echo "  Saldo Anterior: $" . number_format($comp['imp_saldo_ant'], 2) . "\n";
                    echo "  Importe Pagado: $" . number_format($comp['imp_pagado'], 2) . "\n";
                    echo "  Saldo Insoluto: $" . number_format($comp['imp_saldo_insoluto'], 2) . "\n";
                }

                echo str_repeat("-", 100) . "\n";
            }
        } else {
            echo "No se encontraron complementos de pago para este RFC.\n";
        }
    }

    echo "\n=== CONSULTA COMPLETADA ===\n";
    echo "Para consultar un RFC específico, usa:\n";
    echo "\$complementos = consultarComplementosPorRFC(\$pdo, 'RFC_DESEADO');\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
