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
                'importe' => floatval($concepto['Importe'] ?: 0),
                'claveProdServ' => (string)$concepto['ClaveProdServ'] ?: '',
                'noIdentificacion' => (string)$concepto['NoIdentificacion'] ?: ''
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

    // QR Code para validación SAT
    $qrData = "https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?" .
        "&id=" . $datos['timbre']['uuid'] .
        "&re=" . $datos['emisor']['rfc'] .
        "&rr=" . $datos['receptor']['rfc'] .
        "&tt=" . sprintf("%.6f", $datos['total']) .
        "&fe=" . substr(hash('sha1', $datos['sello']), -8);

    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData);

    // HTML con formato EXACTO del SAT oficial
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { 
            size: letter; 
            margin: 10mm; 
        }
        
        body { 
            font-family: "Arial", sans-serif; 
            font-size: 9px; 
            margin: 0; 
            padding: 0; 
            line-height: 1.2;
            color: #000;
        }
        
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        
        /* HEADER PRINCIPAL CON EMPRESA Y FACTURA */
        .main-header {
            width: 100%;
            border: 2px solid #000;
            margin-bottom: 5px;
        }
        
        .header-row {
            display: table;
            width: 100%;
        }
        
        .empresa-section {
            display: table-cell;
            width: 65%;
            padding: 12px;
            vertical-align: top;
            border-right: 2px solid #000;
        }
        
        .empresa-nombre {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .empresa-rfc {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 6px;
        }
        
        .empresa-datos {
            font-size: 9px;
            text-align: center;
            line-height: 1.3;
        }
        
        .factura-section {
            display: table-cell;
            width: 35%;
            padding: 10px;
            text-align: center;
            vertical-align: top;
        }
        
        .factura-header {
            background-color: #000;
            color: #fff;
            padding: 8px;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .factura-datos {
            font-size: 10px;
            line-height: 1.4;
        }
        
        .factura-datos p {
            margin: 4px 0;
            font-weight: bold;
        }
        
        /* RECEPTOR */
        .receptor-header {
            background-color: #000;
            color: #fff;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
        }
        
        .receptor-content {
            border: 2px solid #000;
            border-top: none;
            padding: 10px;
            margin-bottom: 5px;
        }
        
        .receptor-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        
        .receptor-left, .receptor-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }
        
        .receptor-label {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 2px;
        }
        
        .receptor-value {
            font-size: 9px;
            margin-bottom: 6px;
        }
        
        /* CONCEPTOS */
        .conceptos-header {
            background-color: #000;
            color: #fff;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
        }
        
        .conceptos-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            border-top: none;
            margin-bottom: 5px;
            font-size: 8px;
        }
        
        .conceptos-table th {
            background-color: #f0f0f0;
            padding: 6px 3px;
            border: 1px solid #000;
            font-weight: bold;
            text-align: center;
            font-size: 8px;
        }
        
        .conceptos-table td {
            padding: 6px 3px;
            border: 1px solid #000;
            vertical-align: top;
            font-size: 8px;
        }
        
        /* TOTAL CON LETRA */
        .total-letra {
            border: 2px solid #000;
            padding: 8px;
            margin-bottom: 5px;
            font-size: 9px;
            font-weight: bold;
        }
        
        /* DATOS COMERCIALES Y TOTALES */
        .datos-comerciales {
            display: table;
            width: 100%;
            border: 2px solid #000;
            margin-bottom: 5px;
        }
        
        .comercial-left {
            display: table-cell;
            width: 60%;
            padding: 10px;
            border-right: 1px solid #000;
            vertical-align: top;
        }
        
        .comercial-right {
            display: table-cell;
            width: 40%;
            padding: 10px;
            vertical-align: top;
        }
        
        .totales-row {
            margin-bottom: 6px;
            font-size: 10px;
        }
        
        .total-final {
            font-weight: bold;
            font-size: 12px;
            border-top: 1px solid #000;
            padding-top: 4px;
            margin-top: 6px;
        }
        
        /* SELLOS DIGITALES */
        .sello-section {
            border: 2px solid #000;
            margin-bottom: 5px;
        }
        
        .sello-header {
            background-color: #000;
            color: #fff;
            padding: 6px;
            font-weight: bold;
            font-size: 10px;
            text-align: center;
        }
        
        .sello-content {
            padding: 8px;
            font-size: 7px;
            line-height: 1.3;
            word-break: break-all;
            font-family: "Courier New", monospace;
        }
        
        /* QR CODE Y VALIDACIÓN */
        .validacion-section {
            border: 2px solid #000;
            padding: 10px;
            text-align: center;
        }
        
        .qr-container {
            display: inline-block;
            vertical-align: top;
            margin-right: 15px;
        }
        
        .validacion-info {
            display: inline-block;
            vertical-align: top;
            text-align: left;
            font-size: 8px;
            line-height: 1.4;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        
        /* Ajustes específicos para conceptos */
        .concepto-cantidad { text-align: center; width: 8%; }
        .concepto-clave { text-align: center; width: 12%; }
        .concepto-descripcion { width: 45%; }
        .concepto-unidad { text-align: center; width: 8%; }
        .concepto-unitario { text-align: right; width: 13%; }
        .concepto-importe { text-align: right; width: 14%; }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- HEADER PRINCIPAL -->
        <div class="main-header">
            <div class="header-row">
                <div class="empresa-section">
                    <div class="empresa-nombre">' . htmlspecialchars($datos['emisor']['nombre']) . '</div>
                    <div class="empresa-rfc">R.F.C.: ' . htmlspecialchars($datos['emisor']['rfc']) . '</div>
                    <div class="empresa-datos">
                        <p><strong>Lugar de expedición:</strong> ' . htmlspecialchars($datos['lugarExpedicion']) . '</p>
                        <p><strong>Régimen Fiscal:</strong> ' . htmlspecialchars($datos['emisor']['regimenFiscal']) . '</p>
                    </div>
                </div>
                <div class="factura-section">
                    <div class="factura-header">FACTURA</div>
                    <div class="factura-datos">
                        <p>SERIE Y FOLIO: ' . htmlspecialchars($datos['serie'] . $datos['folio']) . '</p>
                        <p>No. CERTIFICADO: ' . htmlspecialchars($datos['noCertificado']) . '</p>
                        <p><strong>FECHA DE EMISIÓN</strong></p>
                        <p>' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($datos['fecha']))) . '</p>
                        <p><strong>FECHA DE CERTIFICACIÓN</strong></p>
                        <p>' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($datos['timbre']['fechaTimbrado']))) . '</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- RECEPTOR -->
        <div class="receptor-header">RECEPTOR</div>
        <div class="receptor-content">
            <div class="receptor-row">
                <div class="receptor-left">
                    <div class="receptor-label">R.F.C.:</div>
                    <div class="receptor-value">' . htmlspecialchars($datos['receptor']['rfc']) . '</div>
                    
                    <div class="receptor-label">NOMBRE / RAZÓN SOCIAL:</div>
                    <div class="receptor-value">' . htmlspecialchars($datos['receptor']['nombre']) . '</div>
                    
                    <div class="receptor-label">CÓDIGO POSTAL:</div>
                    <div class="receptor-value">' . htmlspecialchars($datos['receptor']['domicilioFiscalReceptor']) . '</div>
                </div>
                <div class="receptor-right">
                    <div class="receptor-label">USO CFDI:</div>
                    <div class="receptor-value">' . htmlspecialchars($datos['receptor']['usoCFDI']) . '</div>
                    
                    <div class="receptor-label">RÉGIMEN FISCAL:</div>
                    <div class="receptor-value">' . htmlspecialchars($datos['receptor']['regimenFiscalReceptor']) . '</div>
                </div>
            </div>
        </div>

        <!-- CONCEPTOS -->
        <div class="conceptos-header">CONCEPTOS</div>';

    if (!empty($datos['conceptos'])) {
        $html .= '<table class="conceptos-table">
            <thead>
                <tr>
                    <th class="concepto-cantidad">CANTIDAD</th>
                    <th class="concepto-clave">CLAVE PROD/SERV</th>
                    <th class="concepto-descripcion">DESCRIPCIÓN</th>
                    <th class="concepto-unidad">UNIDAD</th>
                    <th class="concepto-unitario">VALOR UNITARIO</th>
                    <th class="concepto-importe">IMPORTE</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($datos['conceptos'] as $concepto) {
            $html .= '<tr>
                <td class="concepto-cantidad">' . number_format($concepto['cantidad'], 2) . '</td>
                <td class="concepto-clave">' . htmlspecialchars($concepto['claveProdServ']) . '</td>
                <td class="concepto-descripcion">' . htmlspecialchars($concepto['descripcion']) . '</td>
                <td class="concepto-unidad">' . htmlspecialchars($concepto['claveUnidad']) . '</td>
                <td class="concepto-unitario">$' . number_format($concepto['valorUnitario'], 2) . '</td>
                <td class="concepto-importe">$' . number_format($concepto['importe'], 2) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';
    }

    // Total con letra
    $html .= '<div class="total-letra">
        <strong>TOTAL CON LETRA:</strong> ' . numeroEnLetras($datos['total']) . '
    </div>';

    // Datos comerciales y totales
    $html .= '<div class="datos-comerciales">
        <div class="comercial-left">
            <div style="font-weight: bold; margin-bottom: 8px;">DATOS COMERCIALES</div>
            <div>Moneda: ' . htmlspecialchars($datos['moneda']) . '</div>
            <div>Tipo de cambio: ' . number_format($datos['tipoCambio'], 6) . '</div>
            <div>Forma de pago: ' . htmlspecialchars($datos['formaPago']) . '</div>
            <div>Método de pago: ' . htmlspecialchars($datos['metodoPago']) . '</div>
            <div>Tipo de comprobante: ' . htmlspecialchars($datos['tipoDeComprobante']) . '</div>
        </div>
        <div class="comercial-right">
            <div class="totales-row">SUBTOTAL: $' . number_format($datos['subTotal'], 2) . '</div>';

    if ($datos['totalImpuestosTrasladados'] > 0) {
        $tasaIva = ($datos['totalImpuestosTrasladados'] / ($datos['subTotal'] ?: 1)) * 100;
        $html .= '<div class="totales-row">IVA ' . number_format($tasaIva, 2) . '%: $' . number_format($datos['totalImpuestosTrasladados'], 2) . '</div>';
    }

    $html .= '<div class="totales-row total-final">TOTAL: $' . number_format($datos['total'], 2) . '</div>
        </div>
    </div>';

    // Sellos digitales
    $html .= '<div class="sello-section">
        <div class="sello-header">SELLO DIGITAL DEL CFDI</div>
        <div class="sello-content">' . nl2br(htmlspecialchars(formatearSello($datos['sello']))) . '</div>
    </div>
    
    <div class="sello-section">
        <div class="sello-header">SELLO DEL SAT</div>
        <div class="sello-content">' . nl2br(htmlspecialchars(formatearSello($datos['timbre']['selloSAT']))) . '</div>
    </div>
    
    <div class="sello-section">
        <div class="sello-header">CADENA ORIGINAL DEL COMPLEMENTO DE CERTIFICACIÓN DIGITAL DEL SAT</div>
        <div class="sello-content">||' .
        $datos['timbre']['version'] . '|' .
        $datos['timbre']['uuid'] . '|' .
        $datos['timbre']['fechaTimbrado'] . '|' .
        $datos['timbre']['rfcProvCertif'] . '|' .
        substr($datos['sello'], -50) . '|' .
        $datos['timbre']['noCertificadoSAT'] . '||
        </div>
    </div>';

    // QR y validación
    $html .= '<div class="validacion-section">
        <div class="qr-container">
            <img src="' . $qrUrl . '" style="width: 120px; height: 120px; border: 1px solid #000;">
        </div>
        <div class="validacion-info">
            <div><strong>FOLIO FISCAL (UUID):</strong></div>
            <div>' . htmlspecialchars($datos['timbre']['uuid']) . '</div>
            <br>
            <div><strong>CERTIFICADO SAT:</strong></div>
            <div>' . htmlspecialchars($datos['timbre']['noCertificadoSAT']) . '</div>
            <br>
            <div><strong>RFC PROVEEDOR CERTIFICACIÓN:</strong></div>
            <div>' . htmlspecialchars($datos['timbre']['rfcProvCertif']) . '</div>
            <br>
            <div style="font-size: 7px;">
                Este documento es una representación impresa de un CFDI<br>
                Consulte su validez en: verificacfdi.facturaelectronica.sat.gob.mx
            </div>
        </div>
    </div>

    </div>
</body>
</html>';

    // Generar PDF con configuración optimizada
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('dpi', 150);
    $options->set('debugPng', false);
    $options->set('debugKeepTemp', false);
    $options->set('debugCss', false);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    // Descargar PDF
    $filename = 'CFDI_SAT_OFICIAL_' . $datos['timbre']['uuid'] . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    echo $dompdf->output();
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
