<?php
require_once __DIR__ . '/../../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function numeroEnLetras($numero) {
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
        } elseif ($miles <= 9) {
            $texto .= $unidades[$miles] . " MIL ";
        }
        $entero = $entero % 1000;
    }
    
    if ($entero >= 100) {
        $centena = intval($entero / 100);
        if ($entero == 100) {
            $texto .= "CIEN ";
        } elseif ($centena <= 9) {
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

try {
    $uuid = $_GET['uuid'] ?? '';
    if (empty($uuid)) {
        throw new Exception('UUID requerido');
    }
    
    // Conexión a la base de datos
    $pdo = new PDO("mysql:host=localhost;dbname=sac_db;charset=utf8mb4", "root", "");
    $stmt = $pdo->prepare("SELECT ruta_xml FROM cfdi WHERE uuid = ?");
    $stmt->execute([$uuid]);
    $cfdi = $stmt->fetch();
    
    if (!$cfdi) {
        throw new Exception('CFDI no encontrado');
    }
    
    // Cargar XML desde la ruta almacenada
    $rutaCompleta = __DIR__ . '/../../../../' . $cfdi['ruta_xml'];
    if (!file_exists($rutaCompleta)) {
        throw new Exception('Archivo XML no encontrado');
    }
    
    $xml = simplexml_load_file($rutaCompleta);
    if (!$xml) {
        throw new Exception('Error al leer XML');
    }
    
    // DEBUG: Agregar información de depuración
    $debug = $_GET['debug'] ?? false;
    if ($debug) {
        echo "<h3>DEBUG - Estructura del XML:</h3>";
        echo "<pre>Root: " . $xml->getName() . "</pre>";
        echo "<pre>Namespaces: " . print_r($xml->getNamespaces(true), true) . "</pre>";
        echo "<pre>Emisor existe: " . (isset($xml->Emisor) ? 'SI' : 'NO') . "</pre>";
        echo "<pre>Receptor existe: " . (isset($xml->Receptor) ? 'SI' : 'NO') . "</pre>";
        echo "<pre>Conceptos existe: " . (isset($xml->Conceptos) ? 'SI' : 'NO') . "</pre>";
        if (isset($xml->Conceptos) && isset($xml->Conceptos->Concepto)) {
            echo "<pre>Cantidad conceptos: " . count($xml->Conceptos->Concepto) . "</pre>";
        }
        echo "<hr>";
    }
    
    // Registrar namespaces según la versión
    if ($xml->getName() == 'Comprobante') {
        $xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
    } else {
        $xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/3');
    }
    $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
    
    // Extraer datos del XML con verificación
    $emisor = $xml->xpath('//cfdi:Emisor')[0] ?? $xml->Emisor;
    $receptor = $xml->xpath('//cfdi:Receptor')[0] ?? $xml->Receptor;
    $conceptos = $xml->xpath('//cfdi:Concepto') ?? $xml->Conceptos->Concepto ?? [];
    $timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0];
    
    // Variables básicas con verificación
    $serie = (string)($xml['Serie'] ?? '');
    $folio = (string)($xml['Folio'] ?? '');
    $fecha = (string)($xml['Fecha'] ?? '');
    $sello = (string)($xml['Sello'] ?? '');
    $noCertificado = (string)($xml['NoCertificado'] ?? '');
    $subTotal = floatval($xml['SubTotal'] ?? 0);
    $total = floatval($xml['Total'] ?? 0);
    $moneda = (string)($xml['Moneda'] ?? 'MXN');
    $tipoCambio = floatval($xml['TipoCambio'] ?? 1);
    $lugarExpedicion = (string)($xml['LugarExpedicion'] ?? '');
    
    // Emisor con verificación
    $emisorRfc = (string)($emisor['Rfc'] ?? '');
    $emisorNombre = (string)($emisor['Nombre'] ?? '');
    $emisorRegimen = (string)($emisor['RegimenFiscal'] ?? '');
    
    // Receptor con verificación
    $receptorRfc = (string)($receptor['Rfc'] ?? '');
    $receptorNombre = (string)($receptor['Nombre'] ?? '');
    $receptorUsoCfdi = (string)($receptor['UsoCFDI'] ?? '');
    $receptorDomicilio = (string)($receptor['DomicilioFiscalReceptor'] ?? '');
    $receptorRegimen = (string)($receptor['RegimenFiscalReceptor'] ?? '');
    
    // Timbre con verificación
    $timbreUuid = (string)($timbre['UUID'] ?? '');
    $timbreFecha = (string)($timbre['FechaTimbrado'] ?? '');
    $timbreRfcProv = (string)($timbre['RfcProvCertif'] ?? '');
    $timbreSelloSat = (string)($timbre['SelloSAT'] ?? '');
    $timbreNoCertSat = (string)($timbre['NoCertificadoSAT'] ?? '');
    $timbreVersion = (string)($timbre['Version'] ?? '1.1');
    
    // Impuestos con verificación
    $impuestos = $xml->xpath('//cfdi:Impuestos')[0] ?? $xml->Impuestos;
    $totalImpuestos = 0;
    if ($impuestos) {
        $totalImpuestos = floatval($impuestos['TotalImpuestosTrasladados'] ?? 0);
        
        // Si no hay en el atributo, buscar en los traslados
        if ($totalImpuestos == 0) {
            $traslados = $xml->xpath('//cfdi:Traslado') ?? [];
            foreach ($traslados as $traslado) {
                $totalImpuestos += floatval($traslado['Importe'] ?? 0);
            }
        }
    }
    
    // QR
    $qrData = "https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?id=" . $timbreUuid . "&re=" . $emisorRfc . "&rr=" . $receptorRfc . "&tt=" . sprintf("%.6f", $total) . "&fe=" . substr(hash('sha1', $sello), -8);
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData);

    // HTML con formato EXACTO de la imagen derecha
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
            font-family: Arial, sans-serif; 
            font-size: 10px; 
            margin: 0; 
            padding: 0;
            color: #000;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }
        
        .header-table {
            border: 2px solid #000;
        }
        
        .header-left {
            width: 65%;
            padding: 15px;
            border-right: 2px solid #000;
            text-align: center;
            vertical-align: middle;
        }
        
        .header-right {
            width: 35%;
            padding: 10px;
            vertical-align: top;
        }
        
        .empresa-nombre {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .empresa-rfc {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .factura-box {
            border: 2px solid #000;
            text-align: center;
            padding: 8px;
            margin-bottom: 8px;
        }
        
        .factura-title {
            background-color: #000;
            color: #fff;
            font-weight: bold;
            font-size: 14px;
            padding: 6px;
            margin: -8px -8px 8px -8px;
        }
        
        .fecha-box {
            border: 1px solid #000;
            padding: 6px;
            margin-bottom: 5px;
            text-align: center;
        }
        
        .fecha-titulo {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 3px;
        }
        
        .folio-box {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
        
        .receptor-section {
            border: 2px solid #000;
            background-color: #e6e6e6;
        }
        
        .receptor-title {
            background-color: #000;
            color: #fff;
            padding: 8px;
            font-weight: bold;
            text-align: center;
            font-size: 12px;
        }
        
        .receptor-content {
            padding: 10px;
        }
        
        .receptor-box {
            border: 1px solid #000;
            padding: 5px;
            margin: 2px;
            background-color: #fff;
        }
        
        .receptor-label {
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .conceptos-table {
            border: 2px solid #000;
        }
        
        .conceptos-table th {
            background-color: #4a4a4a;
            color: #fff;
            padding: 6px 4px;
            font-weight: bold;
            text-align: center;
            border: 1px solid #000;
            font-size: 9px;
        }
        
        .conceptos-table td {
            padding: 4px;
            border: 1px solid #000;
            vertical-align: top;
            font-size: 9px;
        }
        
        .total-letra {
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            margin: 10px 0;
        }
        
        .comercial-table {
            border: 2px solid #000;
        }
        
        .comercial-left {
            width: 60%;
            padding: 10px;
            background-color: #e6e6e6;
            border-right: 1px solid #000;
            vertical-align: top;
        }
        
        .comercial-right {
            width: 40%;
            padding: 10px;
            vertical-align: top;
        }
        
        .comercial-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 8px;
        }
        
        .sello-section {
            border: 2px solid #000;
            margin-bottom: 10px;
        }
        
        .sello-title {
            background-color: #000;
            color: #fff;
            padding: 6px;
            font-weight: bold;
            text-align: center;
            font-size: 10px;
        }
        
        .sello-content {
            padding: 8px;
            font-size: 7px;
            word-break: break-all;
            font-family: monospace;
            line-height: 1.2;
            overflow-wrap: break-word;
        }
        
        .sellos-qr-container {
            border: 2px solid #000;
            margin-bottom: 10px;
        }
        
        .sellos-left {
            width: 75%;
            padding: 0;
            vertical-align: top;
            border-right: 1px solid #000;
        }
        
        .qr-table {
            border: 2px solid #000;
        }
        
        .qr-left {
            width: 200px;
            padding: 10px;
            text-align: center;
            vertical-align: top;
            border-right: 1px solid #000;
        }
        
        .qr-right {
            padding: 10px;
            font-size: 9px;
            vertical-align: top;
        }
        
        .qr-info-right {
            width: 25%;
            padding: 10px;
            font-size: 8px;
            vertical-align: top;
            text-align: center;
        }
        
        .validacion-label {
            font-weight: bold;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>

    <!-- HEADER EXACTO COMO LA IMAGEN -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <div class="empresa-nombre">' . htmlspecialchars($emisorNombre) . '</div>
                <div class="empresa-rfc">RFC: ' . htmlspecialchars($emisorRfc) . '</div>
                <div><strong>Lugar de expedición:</strong> ' . htmlspecialchars($lugarExpedicion) . '</div>
                <div><strong>Régimen Fiscal:</strong> ' . htmlspecialchars($emisorRegimen) . '</div>
            </td>
            <td class="header-right">
                <div class="factura-box">
                    <div class="factura-title">FACTURA</div>
                    <div style="font-size: 12px; font-weight: bold; color: #ff0000;">' . 
                    htmlspecialchars(
                        (!empty($serie) || !empty($folio)) ? 
                        $serie . $folio : 
                        'SIN FOLIO'
                    ) . '</div>
                </div>
                
                <div class="folio-box">
                    <div style="font-size: 9px;"><strong>FOLIO FISCAL</strong></div>
                    <div style="font-size: 8px;">' . htmlspecialchars($timbreUuid) . '</div>
                    <div style="font-size: 9px; margin-top: 5px;"><strong>No. CERTIFICADO SAT</strong></div>
                    <div style="font-size: 8px;">' . htmlspecialchars($timbreNoCertSat) . '</div>
                </div>
                
                <div class="fecha-box">
                    <div class="fecha-titulo">FECHA DE EMISIÓN</div>
                    <div>' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($fecha))) . '</div>
                </div>
                
                <div class="fecha-box">
                    <div class="fecha-titulo">FECHA DE CERTIFICACIÓN</div>
                    <div>' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($timbreFecha))) . '</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- RECEPTOR CON CAJAS SEPARADAS -->
    <table class="receptor-section">
        <tr>
            <td colspan="2" class="receptor-title">INFORMACIÓN DEL RECEPTOR</td>
        </tr>
        <tr>
            <td class="receptor-content">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 50%; padding-right: 10px;">
                            <div class="receptor-box">
                                <div class="receptor-label">R.F.C.:</div>
                                <div>' . htmlspecialchars($receptorRfc) . '</div>
                            </div>
                        </td>
                        <td style="width: 50%;">
                            <div class="receptor-box">
                                <div class="receptor-label">FOLIO FISCAL:</div>
                                <div>' . htmlspecialchars($timbreUuid) . '</div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-right: 10px;">
                            <div class="receptor-box">
                                <div class="receptor-label">Razón Social:</div>
                                <div>' . htmlspecialchars($receptorNombre) . '</div>
                            </div>
                        </td>
                        <td>
                            <div class="receptor-box">
                                <div class="receptor-label">No. CSD EMISOR:</div>
                                <div>' . htmlspecialchars($noCertificado) . '</div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-right: 10px;">
                            <div class="receptor-box">
                                <div class="receptor-label">Uso CFDI:</div>
                                <div>' . htmlspecialchars($receptorUsoCfdi) . '</div>
                            </div>
                        </td>
                        <td>
                            <div class="receptor-box">
                                <div class="receptor-label">No. CSD SAT:</div>
                                <div>' . htmlspecialchars($timbreNoCertSat) . '</div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-right: 10px;">
                            <div class="receptor-box">
                                <div class="receptor-label">Código Postal:</div>
                                <div>' . htmlspecialchars($receptorDomicilio) . '</div>
                            </div>
                        </td>
                        <td>
                            <div class="receptor-box">
                                <div class="receptor-label">Régimen Fiscal:</div>
                                <div>' . htmlspecialchars($receptorRegimen) . '</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- CONCEPTOS -->
    <table class="conceptos-table">
        <thead>
            <tr>
                <th style="width: 10%;">CLAVE SAT</th>
                <th style="width: 8%;">CANTIDAD</th>
                <th style="width: 42%;">No. ID-DESCRIPCIÓN</th>
                <th style="width: 15%;">UNIDAD</th>
                <th style="width: 12%;">VALOR UNITARIO</th>
                <th style="width: 13%;">IMPORTE</th>
            </tr>
        </thead>
        <tbody>';

    if ($conceptos && count($conceptos) > 0) {
        foreach ($conceptos as $concepto) {
            $html .= '<tr>
                <td style="text-align: center;">' . htmlspecialchars((string)($concepto['ClaveProdServ'] ?? '')) . '</td>
                <td style="text-align: center;">' . number_format(floatval($concepto['Cantidad'] ?? 0), 2) . '</td>
                <td>' . htmlspecialchars((string)($concepto['Descripcion'] ?? '')) . '</td>
                <td style="text-align: center;">' . htmlspecialchars((string)($concepto['ClaveUnidad'] ?? 'E48')) . '</td>
                <td style="text-align: right;">$' . number_format(floatval($concepto['ValorUnitario'] ?? 0), 2) . '</td>
                <td style="text-align: right;">$' . number_format(floatval($concepto['Importe'] ?? 0), 2) . '</td>
            </tr>';
        }
    } else {
        $html .= '<tr>
            <td colspan="6" style="text-align: center; padding: 20px;">No se encontraron conceptos</td>
        </tr>';
    }

    $html .= '</tbody>
    </table>

    <!-- TOTAL CON LETRA -->
    <div class="total-letra">
        <strong>TOTAL CON LETRA:</strong> ' . numeroEnLetras($total) . '
    </div>

    <!-- INFORMACIÓN COMERCIAL Y TOTALES -->
    <table class="comercial-table">
        <tr>
            <td class="comercial-left">
                <div class="comercial-title">INFORMACIÓN COMERCIAL</div>
                <div><strong>Forma de pago:</strong> TRANSFERENCIA ELECTRÓNICA DE FONDOS</div>
                <div><strong>Moneda:</strong> ' . htmlspecialchars($moneda) . '</div>
                <div><strong>Método de pago:</strong> PAGO EN UNA SOLA EXHIBICIÓN</div>
                <div><strong>Tipo de cambio:</strong> ' . number_format($tipoCambio, 4) . '</div>
                <div><strong>Tipo de Comprobante:</strong> I</div>
            </td>
            <td class="comercial-right">
                <table style="width: 100%;">
                    <tr>
                        <td style="text-align: right; font-weight: bold;">SUBTOTAL</td>
                        <td style="text-align: right; font-weight: bold;">$' . number_format($subTotal, 2) . '</td>
                    </tr>';
    
    if ($totalImpuestos > 0) {
        $tasaIva = ($totalImpuestos / ($subTotal ?: 1)) * 100;
        $html .= '<tr>
            <td style="text-align: right; font-weight: bold;">IVA ' . number_format($tasaIva, 1) . '%</td>
            <td style="text-align: right; font-weight: bold;">$' . number_format($totalImpuestos, 2) . '</td>
        </tr>';
    } else {
        $html .= '<tr>
            <td style="text-align: right; font-weight: bold;">IVA 0.0%</td>
            <td style="text-align: right; font-weight: bold;">$0.00</td>
        </tr>';
    }
    
    $html .= '<tr style="border-top: 1px solid #000;">
            <td style="text-align: right; font-weight: bold; font-size: 12px; padding-top: 5px;">TOTAL</td>
            <td style="text-align: right; font-weight: bold; font-size: 12px; padding-top: 5px;">$' . number_format($total, 2) . '</td>
        </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- SELLOS SEPARADOS CON QR A LA DERECHA EN UNA SOLA FILA -->
    <div class="sello-section">
        <div class="sello-title">SELLO DIGITAL DEL CFDI</div>
        <div class="sello-content">' . htmlspecialchars($sello) . '</div>
    </div>
    
    <div class="sello-section">
        <div class="sello-title">SELLO DEL SAT</div>
        <div class="sello-content">' . htmlspecialchars($timbreSelloSat) . '</div>
    </div>
    
    <table style="border: 2px solid #000; margin-bottom: 10px;">
        <tr>
            <td style="width: 75%; padding: 0; vertical-align: top; border-right: 1px solid #000;">
                <div class="sello-section" style="border: none; margin-bottom: 0;">
                    <div class="sello-title">CADENA ORIGINAL DEL COMPLEMENTO DE CERTIFICACIÓN DEL SAT</div>
                    <div class="sello-content">||' . $timbreVersion . '|' . $timbreUuid . '|' . $timbreFecha . '|' . $timbreRfcProv . '|' . substr($sello, -50) . '|' . $timbreNoCertSat . '||</div>
                </div>
            </td>
            <td style="width: 25%; padding: 10px; vertical-align: middle; text-align: center;">
                <div style="margin-bottom: 10px;">
                    <img src="' . $qrUrl . '" style="width: 100px; height: 100px;" alt="QR Code">
                </div>
                
                <div style="font-size: 6px; text-align: left;">
                    <div style="font-weight: bold; margin-bottom: 2px;">FOLIO FISCAL (UUID):</div>
                    <div style="margin-bottom: 4px; word-break: break-all;">' . htmlspecialchars($timbreUuid) . '</div>
                    
                    <div style="font-weight: bold; margin-bottom: 2px;">CERTIFICADO SAT:</div>
                    <div style="margin-bottom: 4px;">' . htmlspecialchars($timbreNoCertSat) . '</div>
                    
                    <div style="font-weight: bold; margin-bottom: 2px;">RFC PROVEEDOR CERTIFICACIÓN:</div>
                    <div style="margin-bottom: 6px;">' . htmlspecialchars($timbreRfcProv) . '</div>
                    
                    <div style="font-size: 5px; text-align: center;">
                        Este documento es una representación impresa de un CFDI<br>
                        Consulte su validez en: verificacfdi.facturaelectronica.sat.gob.mx
                    </div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>';

    // Generar PDF con Dompdf
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('dpi', 150);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    // Descargar PDF
    $filename = 'CFDI_' . $timbreUuid . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    echo $dompdf->output();

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
