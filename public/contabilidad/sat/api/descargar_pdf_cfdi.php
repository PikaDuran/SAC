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
        'tipoCambio' => (float)$xml['TipoCambio'] ?: 1,
        'total' => (float)$xml['Total'] ?: 0,
        'tipoDeComprobante' => (string)$xml['TipoDeComprobante'],
        'exportacion' => (string)$xml['Exportacion'],
        'metodoPago' => (string)$xml['MetodoPago'],
        'lugarExpedicion' => (string)$xml['LugarExpedicion'],
        'formaPago' => (string)$xml['FormaPago']
    ];

    // Emisor completo
    $emisor = $xml->xpath('//cfdi:Emisor')[0] ?? null;
    if ($emisor) {
        $datos['emisor'] = [
            'rfc' => (string)$emisor['Rfc'],
            'nombre' => (string)$emisor['Nombre'],
            'regimenFiscal' => (string)$emisor['RegimenFiscal']
        ];
    }

    // Receptor completo
    $receptor = $xml->xpath('//cfdi:Receptor')[0] ?? null;
    if ($receptor) {
        $datos['receptor'] = [
            'rfc' => (string)$receptor['Rfc'],
            'nombre' => (string)$receptor['Nombre'],
            'usoCFDI' => (string)$receptor['UsoCFDI'],
            'domicilioFiscalReceptor' => (string)$receptor['DomicilioFiscalReceptor'],
            'regimenFiscalReceptor' => (string)$receptor['RegimenFiscalReceptor']
        ];
    }

    // Conceptos completos
    $conceptos = $xml->xpath('//cfdi:Conceptos/cfdi:Concepto');
    if ($conceptos) {
        $datos['conceptos'] = [];
        foreach ($conceptos as $concepto) {
            $datos['conceptos'][] = [
                'claveProdServ' => (string)$concepto['ClaveProdServ'] ?: '',
                'noIdentificacion' => (string)$concepto['NoIdentificacion'] ?: '',
                'cantidad' => (float)$concepto['Cantidad'] ?: 0,
                'claveUnidad' => (string)$concepto['ClaveUnidad'] ?: '',
                'unidad' => (string)$concepto['Unidad'] ?: '',
                'descripcion' => (string)$concepto['Descripcion'] ?: '',
                'valorUnitario' => (float)$concepto['ValorUnitario'] ?: 0,
                'importe' => (float)$concepto['Importe'] ?: 0,
                'descuento' => (float)$concepto['Descuento'] ?: 0,
                'objetoImp' => (string)$concepto['ObjetoImp'] ?: ''
            ];
        }
    }

    // Impuestos completos
    $impuestos = $xml->xpath('//cfdi:Impuestos')[0] ?? null;
    if ($impuestos) {
        $datos['impuestos'] = [
            'totalImpuestosRetenidos' => (float)$impuestos['TotalImpuestosRetenidos'],
            'totalImpuestosTrasladados' => (float)$impuestos['TotalImpuestosTrasladados'] ?: 0
        ];

        // Traslados
        $traslados = $xml->xpath('//cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
        if ($traslados) {
            $datos['impuestos']['traslados'] = [];
            foreach ($traslados as $traslado) {
                $datos['impuestos']['traslados'][] = [
                    'base' => (float)$traslado['Base'],
                    'impuesto' => (string)$traslado['Impuesto'],
                    'tipoFactor' => (string)$traslado['TipoFactor'],
                    'tasaOCuota' => (string)$traslado['TasaOCuota'],
                    'importe' => (float)$traslado['Importe']
                ];
            }
        }

        // Retenciones
        $retenciones = $xml->xpath('//cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion');
        if ($retenciones) {
            $datos['impuestos']['retenciones'] = [];
            foreach ($retenciones as $retencion) {
                $datos['impuestos']['retenciones'][] = [
                    'impuesto' => (string)$retencion['Impuesto'],
                    'importe' => (float)$retencion['Importe']
                ];
            }
        }
    }

    // Timbre fiscal completo
    $timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0] ?? null;
    if ($timbre) {
        $datos['timbre'] = [
            'version' => (string)$timbre['Version'],
            'uuid' => (string)$timbre['UUID'],
            'fechaTimbrado' => (string)$timbre['FechaTimbrado'],
            'rfcProvCertif' => (string)$timbre['RfcProvCertif'],
            'leyenda' => (string)$timbre['Leyenda'],
            'selloSAT' => (string)$timbre['SelloSAT'],
            'noCertificadoSAT' => (string)$timbre['NoCertificadoSAT']
        ];
    }

    return $datos;
}

// Función para formatear sellos con saltos de línea
function formatearSello($sello, $caracteresPorLinea = 80)
{
    return chunk_split(substr($sello, 0, 500), $caracteresPorLinea, "\n");
}

// Función para convertir número a letra REAL
function numeroEnLetras($numero)
{
    $entero = intval($numero);
    $decimales = intval(($numero - $entero) * 100);

    $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
    $decenas = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    if ($entero == 0) return "CERO PESOS " . sprintf("%02d", $decimales) . "/100 M.N.";

    $texto = "";

    // Miles
    if ($entero >= 1000) {
        $miles = intval($entero / 1000);
        if ($miles == 1) {
            $texto .= "MIL ";
        } else if ($miles < 10) {
            $texto .= $unidades[$miles] . " MIL ";
        } else if ($miles < 100) {
            $decena = intval($miles / 10);
            $unidad = $miles % 10;
            if ($miles >= 10 && $miles <= 19) {
                $especiales = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
                $texto .= $especiales[$miles - 10] . " MIL ";
            } else {
                $texto .= $decenas[$decena];
                if ($unidad > 0) $texto .= " Y " . $unidades[$unidad];
                $texto .= " MIL ";
            }
        }
        $entero = $entero % 1000;
    }

    // Centenas
    if ($entero >= 100) {
        $centena = intval($entero / 100);
        if ($entero == 100) {
            $texto .= "CIEN ";
        } else {
            $texto .= $centenas[$centena] . " ";
        }
        $entero = $entero % 100;
    }

    // Decenas y unidades
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

    $pdo = new PDO("mysql:host=localhost;dbname=sac_db;charset=utf8mb4", "root", "");
    $stmt = $pdo->prepare("SELECT ruta_xml FROM cfdi WHERE uuid = ?");
    $stmt->execute([$uuid]);
    $cfdi = $stmt->fetch();

    if (!$cfdi) {
        throw new Exception('CFDI no encontrado');
    }

    $datos = extraerDatosCompletos($cfdi['ruta_xml']);

    // Generar QR con datos completos del SAT
    $qrData = "https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?" .
        "&id=" . $datos['timbre']['uuid'] .
        "&re=" . $datos['emisor']['rfc'] .
        "&rr=" . $datos['receptor']['rfc'] .
        "&tt=" . sprintf("%.6f", $datos['total']) .
        "&fe=" . substr(hash('sha1', $datos['sello']), -8);

    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData);

    // HTML con formato EXACTO según las imágenes
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page { size: letter; margin: 15mm; }
            body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; }
            
            /* Header principal - FORMATO SAT */
            .empresa-header { 
                display: table; 
                width: 100%; 
                margin-bottom: 15px; 
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
            .empresa-info p { 
                margin: 1px 0; 
                font-size: 11px; 
            }
            
            .factura-box { 
                display: table-cell; 
                width: 35%; 
                vertical-align: top; 
                text-align: center;
                border: 2px solid #000;
                padding: 8px;
            }
            .factura-box h3 { 
                margin: 0 0 8px 0; 
                font-size: 14px; 
                font-weight: bold; 
                background-color: #f0f0f0;
                padding: 6px;
                border: 1px solid #000;
            }
            .factura-box p { 
                font-size: 11px; 
                margin: 3px 0; 
                font-weight: bold;
            }
            
            /* Títulos de sección con fondo gris */
            .seccion-titulo { 
                background-color: #f0f0f0;
                padding: 8px;
                font-weight: bold;
                font-size: 12px;
                border: 2px solid #000;
                margin-bottom: 0;
                text-align: center;
            }
            
            /* Información del receptor - DOS COLUMNAS */
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
            .receptor-left { 
                display: table-cell; 
                width: 50%; 
                vertical-align: top;
                padding: 5px;
                font-size: 11px;
            }
            .receptor-right { 
                display: table-cell; 
                width: 50%; 
                vertical-align: top;
                border-left: 1px solid #000;
                padding-left: 12px;
                font-size: 11px;
            }
            .receptor-left p, .receptor-right p {
                margin-bottom: 4px;
                line-height: 1.4;
            }
            
            /* Tablas */
            .info-table { 
                width: 100%; 
                border-collapse: collapse; 
                font-size: 11px; 
            }
            .info-table td { 
                padding: 4px 6px; 
                vertical-align: top; 
            }
            .info-table .label { 
                font-weight: bold; 
                width: 100px; 
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
            
            /* Información comercial y totales */
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
            
            /* Sellos digitales */
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
            
            /* Footer con QR y sellos */
            .footer-section { 
                display: table; 
                width: 100%; 
                margin-top: 15px; 
            }
            .qr-section { 
                display: table-cell; 
                width: 160px; 
                vertical-align: top; 
                text-align: center;
                border: 2px solid #000;
                padding: 10px;
                margin-top: 15px;
            }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .font-bold { font-weight: bold; }
            .mb-2 { margin-bottom: 3px; }
            .mb-3 { margin-bottom: 5px; }
        </style> 
                vertical-align: top; 
                text-align: center; 
            }
            .sellos-section { 
                display: table-cell; 
                vertical-align: top; 
                padding-left: 10px; 
            }
            
            .sello-box { 
                margin-bottom: 8px; 
                border: 1px solid #000; 
                border-radius: 8px;
            }
            .sello-header { 
                background: repeating-linear-gradient(
                    to right,
                    #e0e0e0 0px,
                    #e0e0e0 10px,
                    #f0f0f0 10px,
                    #f0f0f0 20px
                );
                padding: 2px 4px; 
                font-weight: bold; 
                font-size: 6px; 
                border-bottom: 1px solid #000; 
                border-radius: 8px 8px 0 0;
            }
            .sello-content { 
                padding: 3px; 
                font-family: "Courier New", monospace; 
                font-size: 7px; 
                word-break: break-all;
                white-space: pre-wrap;
                line-height: 1.3; 
                max-height: 60px;
                overflow: hidden;
            }
        </style>
    </head>
    <body>
        <!-- Header con información de la empresa -->
        <div class="empresa-header">
            <div class="empresa-info">
                <h1>' . htmlspecialchars($datos['emisor']['nombre']) . '</h1>
                <h2><strong>RFC: ' . htmlspecialchars($datos['emisor']['rfc']) . '</strong></h2>
                <h2><strong>Lugar de expedición:</strong> ' . htmlspecialchars($datos['lugarExpedicion']) . '</h2>
                <h2><strong>Régimen Fiscal:</strong> ' . htmlspecialchars($datos['emisor']['regimenFiscal']) . '</h2>
            </div>
            <div class="factura-box">
                <h3>FACTURA</h3>
                <p>' . htmlspecialchars($datos['serie'] . $datos['folio']) . '</p>
                <p><strong>FECHA DE EMISIÓN</strong></p>
                <p>' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($datos['fecha']))) . '</p>
                <p><strong>FECHA DE CERTIFICACIÓN</strong></p>
                <p>' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($datos['timbre']['fechaTimbrado']))) . '</p>
            </div>
        </div>

        <!-- Información del Receptor -->
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
            <div class="receptor-right">
                <div class="section-header">FOLIO FISCAL</div>
                <div class="section-content">
                    <div style="margin-bottom: 8px;">
                        <strong>' . htmlspecialchars($datos['timbre']['uuid']) . '</strong>
                    </div>
                    <div style="margin-bottom: 4px;">
                        <strong>No CSD EMISOR</strong><br>
                        ' . htmlspecialchars($datos['noCertificado']) . '
                    </div>
                    <div>
                        <strong>No CSD SAT</strong><br>
                        ' . htmlspecialchars($datos['timbre']['noCertificadoSAT']) . '
                    </div>
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
                <td style="text-align: center;">' . number_format(floatval($concepto['cantidad'] ?: 0), 2) . '</td>
                <td>' . htmlspecialchars($concepto['descripcion']) . '</td>
                <td>' . htmlspecialchars($concepto['claveUnidad'] . '-' . $concepto['unidad']) . '</td>
                <td style="text-align: right;">$' . number_format(floatval($concepto['valorUnitario'] ?: 0), 2) . '</td>
                <td style="text-align: right;">$' . number_format(floatval($concepto['importe'] ?: 0), 2) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';
    }

    $html .= '<div class="total-letra">
            <strong>TOTAL CON LETRA:</strong> ' . numeroEnLetras(floatval($datos['total'] ?: 0)) . '
        </div>

        <!-- Información Comercial y Totales -->
        <div class="totales-section">
            <div class="comercial-box">
                <h4><strong>TRANSFERENCIA ELECTRÓNICA DE FONDOS</strong></h4>
                <p><strong>Tipo de cambio:</strong> ' . number_format($datos['tipoCambio'], 4) . '</p>
                <p><strong>Forma de pago:</strong> ' . htmlspecialchars($datos['formaPago']) . '</p>
                <p><strong>Método de pago:</strong> ' . htmlspecialchars($datos['metodoPago']) . '</p>
            </div>
            <div class="totales-box">
                <p><strong>SUBTOTAL:</strong> $' . number_format(floatval($datos['subTotal'] ?: 0), 2) . '</p>
                <p><strong>IVA ' . number_format((floatval($datos['totalImpuestosTrasladados'] ?: 0) / floatval($datos['subTotal'] ?: 1)) * 100, 2) . '%:</strong> $' . number_format(floatval($datos['totalImpuestosTrasladados'] ?: 0), 2) . '</p>
                <p style="font-size: 14px; font-weight: bold;"><strong>TOTAL:</strong> $' . number_format(floatval($datos['total'] ?: 0), 2) . '</p>
            </div>
        </div>

        <!-- Sellos Digitales -->
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

        <!-- QR Code -->
        <div class="qr-section">
            <img src="' . $qrUrl . '" style="width: 140px; height: 140px; border: 2px solid #000;">
        </div>
    </body>
    </html>';    // Generar PDF con configuración optimizada
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('dpi', 150);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    $filename = 'CFDI_' . $datos['timbre']['uuid'] . '.pdf';
    $dompdf->stream($filename, array('Attachment' => true));
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
