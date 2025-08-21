<?php

/**
 * Generador de reporte completo de CFDIs con estructura SAT completa
 * Incluye todos los campos identificados en el análisis de CSV
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/config/database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$pdo = getDatabase();

echo "=== GENERANDO REPORTE COMPLETO DE CFDIS ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Parámetros del reporte (puedes modificar estos valores)
$rfc_filtro = null; // null para todos, o específico como 'BLM1706026AA'
$direccion_filtro = null; // null para todos, 'EMITIDA' o 'RECIBIDA'
$fecha_inicio = null; // '2024-01-01'
$fecha_fin = null; // '2024-12-31'

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if ($rfc_filtro) {
    $where_conditions[] = "c.rfc_consultado = ?";
    $params[] = $rfc_filtro;
}

if ($direccion_filtro) {
    $where_conditions[] = "c.direccion_flujo = ?";
    $params[] = $direccion_filtro;
}

if ($fecha_inicio) {
    $where_conditions[] = "DATE(c.fecha) >= ?";
    $params[] = $fecha_inicio;
}

if ($fecha_fin) {
    $where_conditions[] = "DATE(c.fecha) <= ?";
    $params[] = $fecha_fin;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Consulta principal con JOINs a catálogos SAT
$sql = "
SELECT 
    c.id,
    c.uuid,
    c.version,
    c.tipo,
    c.serie,
    c.folio,
    c.fecha,
    c.rfc_emisor,
    c.nombre_emisor,
    c.regimen_fiscal_emisor,
    rf_em.descripcion as regimen_fiscal_emisor_desc,
    c.rfc_receptor,
    c.nombre_receptor,
    c.regimen_fiscal_receptor,
    rf_rec.descripcion as regimen_fiscal_receptor_desc,
    c.uso_cfdi,
    uc.descripcion as uso_cfdi_desc,
    c.lugar_expedicion,
    c.moneda,
    c.tipo_cambio,
    c.subtotal,
    c.descuento,
    c.total,
    c.metodo_pago,
    mp.descripcion as metodo_pago_desc,
    c.forma_pago,
    fp.descripcion as forma_pago_desc,
    c.exportacion,
    c.sello_cfd,
    c.sello_sat,
    c.no_certificado_sat,
    c.rfc_prov_certif,
    c.estatus_sat,
    c.cfdi_relacionados,
    c.rfc_consultado,
    c.direccion_flujo,
    c.archivo_xml,
    -- Campos de TimbreFiscalDigital
    tfd.fecha_timbrado,
    tfd.version as tfd_version,
    tfd.leyenda as tfd_leyenda,
    -- Conteo de complementos relacionados
    (SELECT COUNT(*) FROM cfdi_pagos WHERE cfdi_id = c.id) as num_pagos,
    (SELECT COUNT(*) FROM cfdi_conceptos WHERE cfdi_id = c.id) as num_conceptos,
    (SELECT COUNT(*) FROM cfdi_impuestos WHERE cfdi_id = c.id) as num_impuestos
FROM cfdi c
LEFT JOIN regimen_fiscal rf_em ON c.regimen_fiscal_emisor = rf_em.clave
LEFT JOIN regimen_fiscal rf_rec ON c.regimen_fiscal_receptor = rf_rec.clave
LEFT JOIN uso_cfdi uc ON c.uso_cfdi = uc.clave
LEFT JOIN metodo_pago mp ON c.metodo_pago = mp.clave
LEFT JOIN forma_pago fp ON c.forma_pago = fp.clave
LEFT JOIN cfdi_timbre_fiscal tfd ON c.id = tfd.cfdi_id
$where_clause
ORDER BY c.fecha DESC, c.id DESC
";

echo "Ejecutando consulta principal...\n";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cfdis = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "CFDIs encontrados: " . count($cfdis) . "\n";

if (empty($cfdis)) {
    echo "No se encontraron CFDIs con los filtros especificados.\n";
    exit;
}

// Crear Excel
echo "Generando archivo Excel...\n";
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Reporte CFDIs Completo');

// Definir headers completos (31 campos principales)
$headers = [
    'A' => 'ID',
    'B' => 'UUID',
    'C' => 'Versión',
    'D' => 'Tipo Comprobante',
    'E' => 'Serie',
    'F' => 'Folio',
    'G' => 'Fecha Emisión',
    'H' => 'Fecha Timbrado',
    'I' => 'RFC Emisor',
    'J' => 'Nombre Emisor',
    'K' => 'Régimen Fiscal Emisor',
    'L' => 'Desc Régimen Emisor',
    'M' => 'RFC Receptor',
    'N' => 'Nombre Receptor',
    'O' => 'Régimen Fiscal Receptor',
    'P' => 'Desc Régimen Receptor',
    'Q' => 'Uso CFDI',
    'R' => 'Desc Uso CFDI',
    'S' => 'Lugar Expedición',
    'T' => 'Moneda',
    'U' => 'Tipo Cambio',
    'V' => 'Subtotal',
    'W' => 'Descuento',
    'X' => 'Total',
    'Y' => 'Método Pago',
    'Z' => 'Desc Método Pago',
    'AA' => 'Forma Pago',
    'AB' => 'Desc Forma Pago',
    'AC' => 'Exportación',
    'AD' => 'Estatus SAT',
    'AE' => 'RFC Consultado',
    'AF' => 'Dirección Flujo',
    'AG' => 'Num Conceptos',
    'AH' => 'Num Impuestos',
    'AI' => 'Num Pagos',
    'AJ' => 'CFDI Relacionados',
    'AK' => 'Archivo XML'
];

// Escribir headers con formato
foreach ($headers as $col => $header) {
    $sheet->setCellValue($col . '1', $header);
}

// Aplicar formato a headers (fondo verde)
$headerStyle = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '90EE90'] // Verde claro
    ],
    'font' => [
        'bold' => true,
        'color' => ['rgb' => '000000']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
        ]
    ]
];

$sheet->getStyle('A1:AK1')->applyFromArray($headerStyle);

// Escribir datos
$row = 2;
foreach ($cfdis as $cfdi) {
    $sheet->setCellValue('A' . $row, $cfdi['id']);
    $sheet->setCellValue('B' . $row, $cfdi['uuid']);
    $sheet->setCellValue('C' . $row, $cfdi['version']);
    $sheet->setCellValue('D' . $row, $cfdi['tipo']);
    $sheet->setCellValue('E' . $row, $cfdi['serie']);
    $sheet->setCellValue('F' . $row, $cfdi['folio']);
    $sheet->setCellValue('G' . $row, $cfdi['fecha']);
    $sheet->setCellValue('H' . $row, $cfdi['fecha_timbrado']);
    $sheet->setCellValue('I' . $row, $cfdi['rfc_emisor']);
    $sheet->setCellValue('J' . $row, $cfdi['nombre_emisor']);
    $sheet->setCellValue('K' . $row, $cfdi['regimen_fiscal_emisor']);
    $sheet->setCellValue('L' . $row, $cfdi['regimen_fiscal_emisor_desc']);
    $sheet->setCellValue('M' . $row, $cfdi['rfc_receptor']);
    $sheet->setCellValue('N' . $row, $cfdi['nombre_receptor']);
    $sheet->setCellValue('O' . $row, $cfdi['regimen_fiscal_receptor']);
    $sheet->setCellValue('P' . $row, $cfdi['regimen_fiscal_receptor_desc']);
    $sheet->setCellValue('Q' . $row, $cfdi['uso_cfdi']);
    $sheet->setCellValue('R' . $row, $cfdi['uso_cfdi_desc']);
    $sheet->setCellValue('S' . $row, $cfdi['lugar_expedicion']);
    $sheet->setCellValue('T' . $row, $cfdi['moneda']);
    $sheet->setCellValue('U' . $row, $cfdi['tipo_cambio']);
    $sheet->setCellValue('V' . $row, $cfdi['subtotal']);
    $sheet->setCellValue('W' . $row, $cfdi['descuento']);
    $sheet->setCellValue('X' . $row, $cfdi['total']);
    $sheet->setCellValue('Y' . $row, $cfdi['metodo_pago']);
    $sheet->setCellValue('Z' . $row, $cfdi['metodo_pago_desc']);
    $sheet->setCellValue('AA' . $row, $cfdi['forma_pago']);
    $sheet->setCellValue('AB' . $row, $cfdi['forma_pago_desc']);
    $sheet->setCellValue('AC' . $row, $cfdi['exportacion']);
    $sheet->setCellValue('AD' . $row, $cfdi['estatus_sat']);
    $sheet->setCellValue('AE' . $row, $cfdi['rfc_consultado']);
    $sheet->setCellValue('AF' . $row, $cfdi['direccion_flujo']);
    $sheet->setCellValue('AG' . $row, $cfdi['num_conceptos']);
    $sheet->setCellValue('AH' . $row, $cfdi['num_impuestos']);
    $sheet->setCellValue('AI' . $row, $cfdi['num_pagos']);
    $sheet->setCellValue('AJ' . $row, $cfdi['cfdi_relacionados']);
    $sheet->setCellValue('AK' . $row, basename($cfdi['archivo_xml']));

    $row++;
}

// Ajustar anchos de columnas
foreach (range('A', 'AK') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Crear nombre de archivo
$filtros_nombre = [];
if ($rfc_filtro) $filtros_nombre[] = $rfc_filtro;
if ($direccion_filtro) $filtros_nombre[] = $direccion_filtro;
if ($fecha_inicio) $filtros_nombre[] = "desde_" . $fecha_inicio;
if ($fecha_fin) $filtros_nombre[] = "hasta_" . $fecha_fin;

$nombre_archivo = 'reporte_cfdi_completo';
if (!empty($filtros_nombre)) {
    $nombre_archivo .= '_' . implode('_', $filtros_nombre);
}
$nombre_archivo .= '_' . date('Y-m-d_H-i-s') . '.xlsx';

// Guardar archivo
$writer = new Xlsx($spreadsheet);
$ruta_archivo = __DIR__ . '/storage/' . $nombre_archivo;
$writer->save($ruta_archivo);

echo "\n=== REPORTE GENERADO EXITOSAMENTE ===\n";
echo "Archivo: $ruta_archivo\n";
echo "Total de registros: " . (count($cfdis)) . "\n";
echo "Columnas incluidas: " . count($headers) . "\n";

// Mostrar estadísticas del reporte
echo "\n=== ESTADÍSTICAS DEL REPORTE ===\n";

if (!empty($cfdis)) {
    // Estadísticas por RFC
    $stats_rfc = [];
    $stats_direccion = [];
    $stats_tipo = [];
    $total_monto = 0;

    foreach ($cfdis as $cfdi) {
        // Por RFC
        $rfc = $cfdi['rfc_consultado'] ?? 'Sin RFC';
        $stats_rfc[$rfc] = ($stats_rfc[$rfc] ?? 0) + 1;

        // Por dirección
        $dir = $cfdi['direccion_flujo'] ?? 'Sin dirección';
        $stats_direccion[$dir] = ($stats_direccion[$dir] ?? 0) + 1;

        // Por tipo
        $tipo = $cfdi['tipo'] ?? 'Sin tipo';
        $stats_tipo[$tipo] = ($stats_tipo[$tipo] ?? 0) + 1;

        // Total monto
        $total_monto += floatval($cfdi['total'] ?? 0);
    }

    echo "Por RFC consultado:\n";
    foreach ($stats_rfc as $rfc => $count) {
        echo "  $rfc: $count CFDIs\n";
    }

    echo "\nPor dirección de flujo:\n";
    foreach ($stats_direccion as $dir => $count) {
        echo "  $dir: $count CFDIs\n";
    }

    echo "\nPor tipo de comprobante:\n";
    foreach ($stats_tipo as $tipo => $count) {
        echo "  $tipo: $count CFDIs\n";
    }

    echo "\nTotal general: $" . number_format($total_monto, 2) . "\n";
}

echo "\nFecha fin: " . date('Y-m-d H:i:s') . "\n";
echo "¡Reporte completo generado exitosamente!\n";
