<?php
require_once __DIR__ . '/../../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $uuid = $_GET['uuid'] ?? '';
    if (empty($uuid)) {
        die('UUID requerido');
    }

    // Conectar a BD
    $pdo = new PDO("mysql:host=localhost;dbname=sac_db;charset=utf8mb4", "root", "");
    $stmt = $pdo->prepare("SELECT ruta_xml FROM cfdi WHERE uuid = ?");
    $stmt->execute([$uuid]);
    $cfdi = $stmt->fetch();

    if (!$cfdi) {
        die('CFDI no encontrado');
    }

    // Leer XML
    $rutaCompleta = __DIR__ . '/../../../../' . $cfdi['ruta_xml'];
    if (!file_exists($rutaCompleta)) {
        die('Archivo XML no encontrado: ' . $rutaCompleta);
    }

    $xml = simplexml_load_file($rutaCompleta);
    if (!$xml) {
        die('Error al leer XML');
    }

    $xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
    $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

    // Extraer datos básicos
    $emisor = $xml->Emisor;
    $receptor = $xml->Receptor;
    $timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0] ?? null;

    $total = floatval($xml['Total'] ?: 0);
    $subTotal = floatval($xml['SubTotal'] ?: 0);
    $iva = 0;

    // Calcular IVA
    $impuestos = $xml->Impuestos;
    if ($impuestos && isset($impuestos['TotalImpuestosTrasladados'])) {
        $iva = floatval($impuestos['TotalImpuestosTrasladados']);
    }

    // HTML simple y limpio
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { border: 2px solid #000; padding: 15px; margin-bottom: 10px; }
        .empresa { font-size: 16px; font-weight: bold; text-align: center; margin-bottom: 10px; }
        .rfc { font-size: 14px; text-align: center; margin-bottom: 5px; }
        .factura-box { float: right; border: 2px solid #000; padding: 10px; width: 200px; text-align: center; }
        .factura-title { font-weight: bold; background-color: #f0f0f0; padding: 5px; margin-bottom: 10px; }
        .seccion-titulo { background-color: #f0f0f0; padding: 8px; font-weight: bold; border: 2px solid #000; margin: 10px 0 0 0; text-align: center; }
        .seccion-content { border: 2px solid #000; border-top: none; padding: 10px; }
        .receptor-info { display: table; width: 100%; }
        .receptor-left, .receptor-right { display: table-cell; width: 50%; padding: 5px; vertical-align: top; }
        .receptor-right { border-left: 1px solid #000; padding-left: 15px; }
        .conceptos { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .conceptos th { background-color: #f0f0f0; padding: 8px; border: 1px solid #000; font-weight: bold; }
        .conceptos td { padding: 8px; border: 1px solid #000; }
        .totales { text-align: right; margin: 20px 0; font-size: 14px; }
        .sello { border: 2px solid #000; padding: 10px; margin: 10px 0; }
        .sello-title { background-color: #f0f0f0; padding: 5px; font-weight: bold; text-align: center; margin-bottom: 10px; }
        .sello-content { font-size: 8px; word-break: break-all; line-height: 1.4; }
        .clear { clear: both; }
    </style>
</head>
<body>
    <div class="header">
        <div class="factura-box">
            <div class="factura-title">FACTURA</div>
            <div style="font-size: 14px; font-weight: bold; margin: 10px 0;">' . htmlspecialchars($xml['Serie'] . $xml['Folio']) . '</div>
            <div>FECHA DE EMISIÓN</div>
            <div>' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($xml['Fecha']))) . '</div>
        </div>
        
        <div class="empresa">' . htmlspecialchars($emisor['Nombre']) . '</div>
        <div class="rfc">RFC: ' . htmlspecialchars($emisor['Rfc']) . '</div>
        <div style="text-align: center;">Lugar de expedición: ' . htmlspecialchars($xml['LugarExpedicion']) . '</div>
        <div style="text-align: center;">Régimen Fiscal: ' . htmlspecialchars($emisor['RegimenFiscal']) . '</div>
        <div class="clear"></div>
    </div>
    
    <div class="seccion-titulo">RECEPTOR</div>
    <div class="seccion-content">
        <div class="receptor-info">
            <div class="receptor-left">
                <p><strong>R.F.C.:</strong> ' . htmlspecialchars($receptor['Rfc']) . '</p>
                <p><strong>NOMBRE:</strong> ' . htmlspecialchars($receptor['Nombre']) . '</p>
                <p><strong>CÓDIGO POSTAL:</strong> ' . htmlspecialchars($receptor['DomicilioFiscalReceptor']) . '</p>
            </div>
            <div class="receptor-right">
                <p><strong>USO CFDI:</strong> ' . htmlspecialchars($receptor['UsoCFDI']) . '</p>
                <p><strong>RÉGIMEN FISCAL:</strong> ' . htmlspecialchars($receptor['RegimenFiscalReceptor']) . '</p>
            </div>
        </div>
    </div>
    
    <div class="seccion-titulo">CONCEPTOS</div>
    <table class="conceptos">
        <thead>
            <tr>
                <th>CANTIDAD</th>
                <th>DESCRIPCIÓN</th>
                <th>VALOR UNITARIO</th>
                <th>IMPORTE</th>
            </tr>
        </thead>
        <tbody>';

    // Agregar conceptos
    $conceptos = $xml->Conceptos->Concepto;
    if ($conceptos) {
        foreach ($conceptos as $concepto) {
            $html .= '<tr>
                <td style="text-align: center;">' . number_format(floatval($concepto['Cantidad']), 2) . '</td>
                <td>' . htmlspecialchars($concepto['Descripcion']) . '</td>
                <td style="text-align: right;">$' . number_format(floatval($concepto['ValorUnitario']), 2) . '</td>
                <td style="text-align: right;">$' . number_format(floatval($concepto['Importe']), 2) . '</td>
            </tr>';
        }
    }

    $html .= '</tbody>
    </table>
    
    <div class="totales">
        <p><strong>SUBTOTAL: $' . number_format($subTotal, 2) . '</strong></p>
        <p><strong>IVA: $' . number_format($iva, 2) . '</strong></p>
        <p style="font-size: 16px;"><strong>TOTAL: $' . number_format($total, 2) . '</strong></p>
    </div>';

    // Agregar sellos si existen
    if ($timbre) {
        $html .= '<div class="sello">
            <div class="sello-title">SELLO DIGITAL DEL CFDI</div>
            <div class="sello-content">' . htmlspecialchars(chunk_split($xml['Sello'], 80, "\n")) . '</div>
        </div>
        
        <div class="sello">
            <div class="sello-title">SELLO DEL SAT</div>
            <div class="sello-content">' . htmlspecialchars(chunk_split($timbre['SelloSAT'], 80, "\n")) . '</div>
        </div>';
    }

    $html .= '</body></html>';

    // Configurar Dompdf con opciones básicas
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('chroot', __DIR__);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    // Forzar descarga
    $filename = 'CFDI_' . ($timbre ? $timbre['UUID'] : $uuid) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    echo $dompdf->output();
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
