<?php
require_once __DIR__ . '/../../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function formatearSello($sello)
{
    return chunk_split($sello, 80, "\n");
}

function numeroEnLetras($numero)
{
    $numero = floatval($numero);
    $entero = intval($numero);
    $decimales = intval(($numero - $entero) * 100);

    $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
    $decenas = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    $texto = '';

    if ($entero >= 1000) {
        $miles = intval($entero / 1000);
        if ($miles == 1) {
            $texto .= "MIL ";
        } else {
            $texto .= $unidades[$miles] . " MIL ";
        }
        $entero = $entero % 1000;
    }

    if ($entero >= 100) {
        $centena = intval($entero / 100);
        if ($entero == 100) {
            $texto .= "CIEN ";
        } else {
            $texto .= $centenas[$centena] . " ";
        }
        $entero = $entero % 100;
    }

    if ($entero >= 10 && $entero <= 19) {
        $especiales = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
        $texto .= $especiales[$entero - 10] . " ";
    } else if ($entero >= 20) {
        $decena = intval($entero / 10);
        $unidad = $entero % 10;
        $texto .= $decenas[$decena];
        if ($unidad > 0) $texto .= " Y " . $unidades[$unidad];
        $texto .= " ";
    } else if ($entero > 0) {
        $texto .= $unidades[$entero] . " ";
    }

    $texto .= "PESOS " . sprintf("%02d", $decimales) . "/100 M.N.";
    return trim($texto);
}

function extraerDatosCompletos($rutaXml)
{
    // Construir ruta absoluta desde la raíz del proyecto
    $rutaCompleta = __DIR__ . '/../../../../' . $rutaXml;

    if (!file_exists($rutaCompleta)) {
        throw new Exception('Archivo XML no encontrado: ' . $rutaCompleta);
    }

    $xml = simplexml_load_file($rutaCompleta);
    if (!$xml) {
        throw new Exception('Error al leer XML');
    }

    $xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
    $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

    // Datos básicos
    $datos = [
        'version' => (string)$xml['Version'] ?: '4.0',
        'serie' => (string)$xml['Serie'] ?: '',
        'folio' => (string)$xml['Folio'] ?: '',
        'fecha' => (string)$xml['Fecha'] ?: '',
        'sello' => (string)$xml['Sello'] ?: '',
        'noCertificado' => (string)$xml['NoCertificado'] ?: '',
        'subTotal' => floatval($xml['SubTotal'] ?: 0),
        'moneda' => (string)$xml['Moneda'] ?: 'MXN',
        'tipoCambio' => floatval($xml['TipoCambio'] ?: 1),
        'total' => floatval($xml['Total'] ?: 0),
        'tipoDeComprobante' => (string)$xml['TipoDeComprobante'] ?: 'I',
        'metodoPago' => (string)$xml['MetodoPago'] ?: '',
        'lugarExpedicion' => (string)$xml['LugarExpedicion'] ?: '',
        'formaPago' => (string)$xml['FormaPago'] ?: '',
        'totalImpuestosTrasladados' => 0
    ];

    // Emisor
    $emisor = $xml->Emisor;
    $datos['emisor'] = [
        'rfc' => (string)$emisor['Rfc'] ?: '',
        'nombre' => (string)$emisor['Nombre'] ?: '',
        'regimenFiscal' => (string)$emisor['RegimenFiscal'] ?: ''
    ];

    // Receptor
    $receptor = $xml->Receptor;
    $datos['receptor'] = [
        'rfc' => (string)$receptor['Rfc'] ?: '',
        'nombre' => (string)$receptor['Nombre'] ?: '',
        'usoCFDI' => (string)$receptor['UsoCFDI'] ?: '',
        'domicilioFiscalReceptor' => (string)$receptor['DomicilioFiscalReceptor'] ?: '',
        'regimenFiscalReceptor' => (string)$receptor['RegimenFiscalReceptor'] ?: ''
    ];

    // Conceptos
    $conceptos = $xml->Conceptos->Concepto;
    $datos['conceptos'] = [];
    if ($conceptos) {
        foreach ($conceptos as $concepto) {
            $datos['conceptos'][] = [
                'cantidad' => floatval($concepto['Cantidad'] ?: 0),
                'descripcion' => (string)$concepto['Descripcion'] ?: '',
                'claveUnidad' => (string)$concepto['ClaveUnidad'] ?: '',
                'unidad' => (string)$concepto['Unidad'] ?: '',
                'valorUnitario' => floatval($concepto['ValorUnitario'] ?: 0),
                'importe' => floatval($concepto['Importe'] ?: 0)
            ];
        }
    }

    // Impuestos
    $impuestos = $xml->Impuestos;
    if ($impuestos && isset($impuestos['TotalImpuestosTrasladados'])) {
        $datos['totalImpuestosTrasladados'] = floatval($impuestos['TotalImpuestosTrasladados']);
    }

    // Timbre
    $timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0] ?? null;
    if ($timbre) {
        $datos['timbre'] = [
            'version' => (string)$timbre['Version'] ?: '',
            'uuid' => (string)$timbre['UUID'] ?: '',
            'fechaTimbrado' => (string)$timbre['FechaTimbrado'] ?: '',
            'rfcProvCertif' => (string)$timbre['RfcProvCertif'] ?: '',
            'selloSAT' => (string)$timbre['SelloSAT'] ?: '',
            'noCertificadoSAT' => (string)$timbre['NoCertificadoSAT'] ?: ''
        ];
    }

    return $datos;
}

try {
    $uuid = $_GET['uuid'] ?? '';
    if (empty($uuid)) {
        throw new Exception('UUID requerido');
    }

    $pdo = new PDO("mysql:host=localhost;dbname=sac_db;charset=utf8mb4", "root", "");
    $stmt = $pdo->prepare("SELECT ruta_xml FROM cfdi WHERE uuid = ?");
    $stmt->execute([$uuid]);
    $cfdi = $stmt->fetch();

    if (!$cfdi) {
        throw new Exception('CFDI no encontrado');
    }

    $datos = extraerDatosCompletos($cfdi['ruta_xml']);

    // QR Code
    $qrData = "https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?" .
        "&id=" . $datos['timbre']['uuid'] .
        "&re=" . $datos['emisor']['rfc'] .
        "&rr=" . $datos['receptor']['rfc'] .
        "&tt=" . sprintf("%.6f", $datos['total']) .
        "&fe=" . substr(hash('sha1', $datos['sello']), -8);

    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData);

    // HTML
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: letter; margin: 15mm; }
        body { 
            font-family: Arial, sans-serif; 
            font-size: 11px; 
            margin: 0; 
            padding: 0; 
            line-height: 1.3;
        }
        
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 8px;
            border: 2px solid #000;
            padding: 12px;
        }
        
        .empresa-info {
            display: table-cell;
            width: 65%;
            vertical-align: top;
            padding-right: 15px;
        }
        
        .empresa-info h1 {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 4px 0;
            text-align: center;
        }
        
        .empresa-info h2 {
            font-size: 12px;
            margin: 3px 0;
            text-align: center;
        }
        
        .factura-info {
            display: table-cell;
            width: 35%;
            vertical-align: top;
            border: 2px solid #000;
            padding: 8px;
            text-align: center;
        }
        
        .factura-info h3 {
            margin: 0 0 8px 0;
            font-size: 14px;
            font-weight: bold;
            background-color: #f0f0f0;
            padding: 6px;
            border: 1px solid #000;
        }
        
        .factura-info p {
            font-size: 11px;
            margin: 3px 0;
            font-weight: bold;
        }
        
        /* Títulos de sección */
        .seccion-titulo {
            background-color: #f0f0f0;
            padding: 8px;
            font-weight: bold;
            font-size: 12px;
            border: 2px solid #000;
            margin-bottom: 0;
            text-align: center;
        }
        
        /* Receptor */
        .receptor-section {
            margin-bottom: 8px;
            border: 2px solid #000;
            border-top: none;
        }
        
        .receptor-content {
            display: table;
            width: 100%;
            padding: 10px;
        }
        
        .receptor-left, .receptor-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 5px;
            font-size: 11px;
        }
        
        .receptor-right {
            border-left: 1px solid #000;
            padding-left: 12px;
        }
        
        .receptor-left p, .receptor-right p {
            margin-bottom: 4px;
            line-height: 1.4;
        }
        
        /* Conceptos */
        .conceptos-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            margin-bottom: 8px;
        }
        
        .conceptos-table th {
            background-color: #f0f0f0;
            padding: 8px 4px;
            border: 1px solid #000;
            font-weight: bold;
            text-align: center;
            font-size: 11px;
        }
        
        .conceptos-table td {
            padding: 6px 4px;
            border: 1px solid #000;
            font-size: 10px;
            vertical-align: top;
        }
        
        /* Total con letra */
        .total-letra {
            margin: 8px 0;
            font-size: 11px;
            font-weight: bold;
            border: 2px solid #000;
            padding: 12px;
        }
        
        /* Totales */
        .totales-section {
            display: table;
            width: 100%;
            margin-top: 8px;
            border: 2px solid #000;
            padding: 12px;
        }
        
        .comercial-box {
            display: table-cell;
            width: 60%;
            vertical-align: top;
            padding-right: 15px;
            font-size: 11px;
        }
        
        .totales-box {
            display: table-cell;
            width: 40%;
            vertical-align: top;
            border-left: 1px solid #000;
            padding-left: 12px;
            font-size: 11px;
        }
        
        /* Sellos */
        .sello-section {
            width: 100%;
            margin-bottom: 8px;
            border: 2px solid #000;
            padding: 10px;
        }
        
        .sello-title {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 8px;
            background-color: #f0f0f0;
            padding: 6px;
            border: 1px solid #000;
            text-align: center;
        }
        
        .sello-content {
            font-size: 8px;
            line-height: 1.4;
            word-break: break-all;
            padding: 5px;
        }
        
        /* QR */
        .qr-section {
            text-align: center;
            border: 2px solid #000;
            padding: 10px;
            margin-top: 15px;
        }
        
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="empresa-info">
                <h1>' . htmlspecialchars($datos['emisor']['nombre']) . '</h1>
                <h2><strong>RFC: ' . htmlspecialchars($datos['emisor']['rfc']) . '</strong></h2>
                <h2><strong>Lugar de expedición:</strong> ' . htmlspecialchars($datos['lugarExpedicion']) . '</h2>
                <h2><strong>Régimen Fiscal:</strong> ' . htmlspecialchars($datos['emisor']['regimenFiscal']) . '</h2>
            </div>
            <div class="factura-info">
                <h3>FACTURA</h3>
                <p>' . htmlspecialchars($datos['serie'] . $datos['folio']) . '</p>
                <p><strong>FECHA DE EMISIÓN</strong></p>
                <p>' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($datos['fecha']))) . '</p>
                <p><strong>FECHA DE CERTIFICACIÓN</strong></p>
                <p>' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($datos['timbre']['fechaTimbrado']))) . '</p>
            </div>
        </div>

        <!-- Receptor -->
        <div class="seccion-titulo">RECEPTOR</div>
        <div class="receptor-section">
            <div class="receptor-content">
                <div class="receptor-left">
                    <p><strong>R.F.C.:</strong> ' . htmlspecialchars($datos['receptor']['rfc']) . '</p>
                    <p><strong>NOMBRE / RAZÓN SOCIAL:</strong> ' . htmlspecialchars($datos['receptor']['nombre']) . '</p>
                    <p><strong>CÓDIGO POSTAL:</strong> ' . htmlspecialchars($datos['receptor']['domicilioFiscalReceptor']) . '</p>
                </div>
                <div class="receptor-right">
                    <p><strong>USO CFDI:</strong> ' . htmlspecialchars($datos['receptor']['usoCFDI']) . '</p>
                    <p><strong>RÉGIMEN FISCAL:</strong> ' . htmlspecialchars($datos['receptor']['regimenFiscalReceptor']) . '</p>
                </div>
            </div>
        </div>

        <!-- Conceptos -->
        <div class="seccion-titulo">CONCEPTOS</div>';

    if (!empty($datos['conceptos'])) {
        $html .= '<table class="conceptos-table">
            <thead>
                <tr>
                    <th>CANTIDAD</th>
                    <th>No. ID-DESCRIPCIÓN</th>
                    <th>UNIDAD</th>
                    <th>VALOR UNITARIO</th>
                    <th>IMPORTE</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($datos['conceptos'] as $concepto) {
            $html .= '<tr>
                <td style="text-align: center;">' . number_format($concepto['cantidad'], 2) . '</td>
                <td>' . htmlspecialchars($concepto['descripcion']) . '</td>
                <td>' . htmlspecialchars($concepto['claveUnidad'] . '-' . $concepto['unidad']) . '</td>
                <td class="text-right">$' . number_format($concepto['valorUnitario'], 2) . '</td>
                <td class="text-right">$' . number_format($concepto['importe'], 2) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';
    }

    $html .= '<div class="total-letra">
            <strong>TOTAL CON LETRA:</strong> ' . numeroEnLetras($datos['total']) . '
        </div>

        <!-- Totales -->
        <div class="totales-section">
            <div class="comercial-box">
                <h4><strong>TRANSFERENCIA ELECTRÓNICA DE FONDOS</strong></h4>
                <p><strong>Tipo de cambio:</strong> ' . number_format($datos['tipoCambio'], 4) . '</p>
                <p><strong>Forma de pago:</strong> ' . htmlspecialchars($datos['formaPago']) . '</p>
                <p><strong>Método de pago:</strong> ' . htmlspecialchars($datos['metodoPago']) . '</p>
            </div>
            <div class="totales-box">
                <p><strong>SUBTOTAL:</strong> $' . number_format($datos['subTotal'], 2) . '</p>
                <p><strong>IVA ' . number_format(($datos['totalImpuestosTrasladados'] / ($datos['subTotal'] ?: 1)) * 100, 2) . '%:</strong> $' . number_format($datos['totalImpuestosTrasladados'], 2) . '</p>
                <p class="font-bold" style="font-size: 14px;"><strong>TOTAL:</strong> $' . number_format($datos['total'], 2) . '</p>
            </div>
        </div>

        <!-- Sellos -->
        <div class="sello-section">
            <div class="sello-title">SELLO DIGITAL DEL CFDI</div>
            <div class="sello-content">' . nl2br(htmlspecialchars(formatearSello($datos['sello']))) . '</div>
        </div>
        
        <div class="sello-section">
            <div class="sello-title">SELLO DEL SAT</div>
            <div class="sello-content">' . nl2br(htmlspecialchars(formatearSello($datos['timbre']['selloSAT']))) . '</div>
        </div>
        
        <div class="sello-section">
            <div class="sello-title">CADENA ORIGINAL DEL COMPLEMENTO SAT</div>
            <div class="sello-content">||' .
        $datos['timbre']['version'] . '|' .
        $datos['timbre']['uuid'] . '|' .
        $datos['timbre']['fechaTimbrado'] . '|' .
        $datos['timbre']['rfcProvCertif'] . '|' .
        substr($datos['sello'], -50) . '|' .
        $datos['timbre']['noCertificadoSAT'] . '||
            </div>
        </div>

        <!-- QR -->
        <div class="qr-section">
            <img src="' . $qrUrl . '" style="width: 140px; height: 140px; border: 2px solid #000;">
        </div>
    </div>
</body>
</html>';

    // Generar PDF
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('dpi', 150);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    $filename = 'CFDI_' . $datos['timbre']['uuid'] . '.pdf';
    $dompdf->stream($filename, array("Attachment" => true));
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
