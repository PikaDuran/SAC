<?php

/**
 * Script para consultar complementos de pago específicos por RFC emisor
 * Utiliza los datos JSON almacenados en complemento_json hasta que se ejecute el reprocesamiento
 */

require_once 'src/config/database.php';

function consultarComplementosPagoRFC($rfc_emisor = null, $mostrar_detalle = true)
{
    try {
        $pdo = getDatabase();

        // Si no se especifica RFC, mostrar lista de RFCs con complementos de pago
        if (!$rfc_emisor) {
            echo "=== RFCs CON COMPLEMENTOS DE PAGO DISPONIBLES ===\n\n";

            $sql = "
                SELECT 
                    rfc_emisor, 
                    nombre_emisor, 
                    COUNT(*) as total_complementos,
                    SUM(total) as monto_total
                FROM cfdi 
                WHERE tipo = 'P' 
                GROUP BY rfc_emisor, nombre_emisor 
                ORDER BY total_complementos DESC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $emisores = $stmt->fetchAll();

            printf("%-15s %-40s %-12s %s\n", "RFC", "NOMBRE", "COMPLEMENT.", "MONTO TOTAL");
            echo str_repeat("-", 90) . "\n";

            foreach ($emisores as $emisor) {
                printf(
                    "%-15s %-40s %-12d $%s\n",
                    $emisor['rfc_emisor'],
                    substr($emisor['nombre_emisor'], 0, 40),
                    $emisor['total_complementos'],
                    number_format($emisor['monto_total'], 2)
                );
            }

            echo "\n" . str_repeat("=", 90) . "\n";
            echo "Para ver detalles de un RFC específico, ejecuta:\n";
            echo "php " . basename(__FILE__) . " RFC_EMISOR\n\n";

            return $emisores;
        }

        // Consultar complementos de pago para RFC específico
        echo "=== COMPLEMENTOS DE PAGO PARA RFC: $rfc_emisor ===\n\n";

        $sql = "
            SELECT 
                uuid,
                serie,
                folio, 
                fecha,
                fecha_timbrado,
                rfc_emisor,
                nombre_emisor,
                rfc_receptor,
                nombre_receptor,
                total,
                moneda,
                archivo_xml,
                complemento_json
            FROM cfdi 
            WHERE tipo = 'P' AND rfc_emisor = ?
            ORDER BY fecha DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rfc_emisor]);
        $complementos = $stmt->fetchAll();

        if (empty($complementos)) {
            echo "No se encontraron complementos de pago para el RFC: $rfc_emisor\n";
            return [];
        }

        echo "TOTAL DE COMPLEMENTOS ENCONTRADOS: " . count($complementos) . "\n";
        echo str_repeat("=", 100) . "\n\n";

        foreach ($complementos as $index => $comp) {
            echo "COMPLEMENTO " . ($index + 1) . ":\n";
            echo str_repeat("-", 50) . "\n";
            echo "UUID: " . $comp['uuid'] . "\n";
            echo "Serie/Folio: " . $comp['serie'] . "/" . $comp['folio'] . "\n";
            echo "Fecha: " . $comp['fecha'] . "\n";
            echo "Fecha Timbrado: " . $comp['fecha_timbrado'] . "\n";
            echo "Emisor: " . $comp['rfc_emisor'] . " - " . $comp['nombre_emisor'] . "\n";
            echo "Receptor: " . $comp['rfc_receptor'] . " - " . $comp['nombre_receptor'] . "\n";
            echo "Total: $" . number_format($comp['total'], 2) . " " . $comp['moneda'] . "\n";

            if ($comp['archivo_xml']) {
                echo "Archivo XML: " . $comp['archivo_xml'] . "\n";
                // Verificar si el archivo existe
                $archivo_existe = file_exists($comp['archivo_xml']) ? "✓ Disponible" : "✗ No encontrado";
                echo "Estado del archivo: " . $archivo_existe . "\n";
            }

            // Procesar JSON del complemento si está disponible y se solicita detalle
            if ($mostrar_detalle && $comp['complemento_json']) {
                echo "\nDETALLE DEL COMPLEMENTO:\n";
                $json_data = json_decode($comp['complemento_json'], true);

                if ($json_data && isset($json_data['Pagos'])) {
                    foreach ($json_data['Pagos'] as $pago_index => $pago) {
                        echo "  PAGO " . ($pago_index + 1) . ":\n";
                        echo "    Fecha: " . ($pago['FechaPago'] ?? 'N/A') . "\n";
                        echo "    Forma de Pago: " . ($pago['FormaDePagoP'] ?? 'N/A') . "\n";
                        echo "    Moneda: " . ($pago['MonedaP'] ?? 'N/A') . "\n";
                        echo "    Monto: $" . number_format($pago['Monto'] ?? 0, 2) . "\n";

                        if (isset($pago['NumOperacion'])) {
                            echo "    Núm. Operación: " . $pago['NumOperacion'] . "\n";
                        }

                        if (isset($pago['DoctoRelacionado'])) {
                            echo "    DOCUMENTOS RELACIONADOS:\n";
                            $documentos = is_array($pago['DoctoRelacionado']) ? $pago['DoctoRelacionado'] : [$pago['DoctoRelacionado']];

                            foreach ($documentos as $doc_index => $doc) {
                                echo "      Documento " . ($doc_index + 1) . ":\n";
                                echo "        UUID: " . ($doc['IdDocumento'] ?? 'N/A') . "\n";
                                echo "        Serie/Folio: " . ($doc['Serie'] ?? '') . "/" . ($doc['Folio'] ?? '') . "\n";
                                echo "        Moneda: " . ($doc['MonedaDR'] ?? 'N/A') . "\n";
                                echo "        Parcialidad: " . ($doc['NumParcialidad'] ?? 'N/A') . "\n";
                                echo "        Saldo Anterior: $" . number_format($doc['ImpSaldoAnt'] ?? 0, 2) . "\n";
                                echo "        Importe Pagado: $" . number_format($doc['ImpPagado'] ?? 0, 2) . "\n";
                                echo "        Saldo Insoluto: $" . number_format($doc['ImpSaldoInsoluto'] ?? 0, 2) . "\n";
                            }
                        }
                    }
                } else {
                    echo "  No se pudo procesar el JSON del complemento\n";
                }
            }

            echo "\n" . str_repeat("-", 100) . "\n\n";
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
$resultado = consultarComplementosPagoRFC($rfc_consultar);

if (!$rfc_consultar && !empty($resultado)) {
    echo "\nEJEMPLO DE USO:\n";
    echo "php " . basename(__FILE__) . " " . $resultado[0]['rfc_emisor'] . "\n";
}
