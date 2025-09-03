<?php
require_once __DIR__ . '/../../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $uuid = $_GET['uuid'] ?? '';
    if (empty($uuid)) {
        die('UUID requerido');
    }

    $pdo = new PDO("mysql:host=localhost;dbname=sac_db;charset=utf8mb4", "root", "");
    $stmt = $pdo->prepare("SELECT ruta_xml FROM cfdi WHERE uuid = ?");
    $stmt->execute([$uuid]);
    $cfdi = $stmt->fetch();

    if (!$cfdi) {
        die('CFDI no encontrado');
    }

    $rutaCompleta = __DIR__ . '/../../../../' . $cfdi['ruta_xml'];
    if (!file_exists($rutaCompleta)) {
        die('Archivo XML no encontrado');
    }

    $xml = simplexml_load_file($rutaCompleta);
    if (!$xml) {
        die('Error al leer XML');
    }

    $xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
    $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

    $emisor = $xml->Emisor;
    $receptor = $xml->Receptor;
    $timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0] ?? null;
    $conceptos = $xml->Conceptos->Concepto;

    $total = floatval($xml['Total'] ?: 0);
    $subTotal = floatval($xml['SubTotal'] ?: 0);
    $iva = 0;

    $impuestos = $xml->Impuestos;
    if ($impuestos && isset($impuestos['TotalImpuestosTrasladados'])) {
        $iva = floatval($impuestos['TotalImpuestosTrasladados']);
    }

    // HTML EXACTO como tu imagen
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size: 9px; 
            margin: 20px; 
            line-height: 1.2;
        }
        .main-container {
            border: 2px solid #000;
            margin-bottom: 10px;
        }
        
        /* HEADER EXACTO */
        .header-row {
            display: table;
            width: 100%;
            border-bottom: 1px solid #000;
        }
        .header-left {
            display: table-cell;
            width: 60%;
            padding: 15px;
            text-align: center;
            border-right: 1px solid #000;
        }
        .header-right {
            display: table-cell;
            width: 40%;
            padding: 10px;
            text-align: center;
            background-color: #f5f5f5;
        }
        .factura-title {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 10px;
        }
        .folio-number {
            font-size: 18px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        /* RECEPTOR EXACTO */
        .receptor-header {
            background-color: #e8e8e8;
            padding: 5px;
            text-align: center;
            font-weight: bold;
            border-bottom: 1px solid #000;
        }
        .receptor-content {
            display: table;
            width: 100%;
        }
        .receptor-left, .receptor-right {
            display: table-cell;
            width: 50%;
            padding: 10px;
            vertical-align: top;
        }
        .receptor-right {
            border-left: 1px solid #000;
        }
        .campo {
            margin-bottom: 8px;
            font-size: 9px;
        }
        .campo-label {
            font-weight: bold;
            display: inline-block;
            width: 80px;
        }
        
        /* CONCEPTOS EXACTO */
        .conceptos-header {
            background-color: #e8e8e8;
            padding: 5px;
            text-align: center;
            font-weight: bold;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        .conceptos-table {
            width: 100%;
            border-collapse: collapse;
        }
        .conceptos-table th {
            background-color: #f0f0f0;
            padding: 8px 5px;
            border: 1px solid #000;
            font-weight: bold;
            text-align: center;
            font-size: 8px;
        }
        .conceptos-table td {
            padding: 8px 5px;
            border: 1px solid #000;
            font-size: 8px;
        }
        
        /* TOTALES EXACTO */
        .totales-section {
            text-align: right;
            padding: 15px;
            font-size: 10px;
        }
        .total-row {
            margin: 5px 0;
            font-weight: bold;
        }
        .total-final {
            font-size: 12px;
            margin-top: 10px;
        }
        
        /* SELLOS EXACTO */
        .sello-container {
            border: 2px solid #000;
            margin: 10px 0;
        }
        .sello-header {
            background-color: #e8e8e8;
            padding: 5px;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
        }
        .sello-content {
            padding: 10px;
            font-family: monospace;
            font-size: 7px;
            word-break: break-all;
            line-height: 1.3;
        }
    </style>
</head>
<body>
    <!-- CONTAINER PRINCIPAL -->
    <div class="main-container">
        <!-- HEADER -->
        <div class="header-row">
            <div class="header-left">
                <div style="font-weight: bold; margin-bottom: 5px;">RFC:</div>
                <div style="margin-bottom: 10px;">Lugar de expedición: ' . htmlspecialchars($xml['LugarExpedicion']) . '</div>
                <div>Régimen Fiscal:</div>
            </div>
            <div class="header-right">
                <div class="factura-title">FACTURA</div>
                <div class="folio-number">' . htmlspecialchars($xml['Serie'] . $xml['Folio']) . '</div>
                <div style="font-size: 8px; margin-top: 10px;">
                    <div>FECHA DE EMISIÓN</div>
                    <div>' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($xml['Fecha']))) . '</div>
                </div>
            </div>
        </div>
        
        <!-- RECEPTOR -->
        <div class="receptor-header">RECEPTOR</div>
        <div class="receptor-content">
            <div class="receptor-left">
                <div class="campo">
                    <span class="campo-label">R.F.C.:</span>
                    <span>' . htmlspecialchars($receptor['Rfc']) . '</span>
                </div>
                <div class="campo">
                    <span class="campo-label">NOMBRE:</span>
                    <span>' . htmlspecialchars($receptor['Nombre']) . '</span>
                </div>
                <div class="campo">
                    <span class="campo-label">CÓDIGO POSTAL:</span>
                    <span>' . htmlspecialchars($receptor['DomicilioFiscalReceptor']) . '</span>
                </div>
            </div>
            <div class="receptor-right">
                <div class="campo">
                    <span class="campo-label">USO CFDI:</span>
                    <span>' . htmlspecialchars($receptor['UsoCFDI']) . '</span>
                </div>
                <div class="campo">
                    <span class="campo-label">RÉGIMEN FISCAL:</span>
                    <span>' . htmlspecialchars($receptor['RegimenFiscalReceptor']) . '</span>
                </div>
            </div>
        </div>
        
        <!-- CONCEPTOS -->
        <div class="conceptos-header">CONCEPTOS</div>
        <table class="conceptos-table">
            <thead>
                <tr>
                    <th style="width: 10%;">CANTIDAD</th>
                    <th style="width: 50%;">DESCRIPCIÓN</th>
                    <th style="width: 20%;">VALOR UNITARIO</th>
                    <th style="width: 20%;">IMPORTE</th>
                </tr>
            </thead>
            <tbody>';

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

    $html .= '    </tbody>
        </table>
        
        <!-- TOTALES -->
        <div class="totales-section">
            <div class="total-row">SUBTOTAL: $' . number_format($subTotal, 2) . '</div>
            <div class="total-row">IVA: $' . number_format($iva, 2) . '</div>
            <div class="total-row total-final">TOTAL: $' . number_format($total, 2) . '</div>
        </div>
    </div>
    
    <!-- SELLO DIGITAL DEL CFDI -->
    <div class="sello-container">
        <div class="sello-header">SELLO DIGITAL DEL CFDI</div>
        <div class="sello-content">' . htmlspecialchars(chunk_split($xml['Sello'], 80, "\n")) . '</div>
    </div>
    
    <!-- SELLO DEL SAT -->
    <div class="sello-container">
        <div class="sello-header">SELLO DEL SAT</div>
        <div class="sello-content">' . htmlspecialchars(chunk_split($timbre['SelloSAT'], 80, "\n")) . '</div>
    </div>
    
</body>
</html>';

    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="CFDI_CLON_' . ($timbre ? $timbre['UUID'] : $uuid) . '.pdf"');

    echo $dompdf->output();
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
