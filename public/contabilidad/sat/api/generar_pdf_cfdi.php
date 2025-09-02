<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Función para extraer información del XML
function extraerDatosXML($rutaXML) {
    if (!file_exists($rutaXML)) {
        throw new Exception('Archivo XML no encontrado en: ' . $rutaXML);
    }
    
    // Cargar XML
    $xmlContent = file_get_contents($rutaXML);
    if (!$xmlContent) {
        throw new Exception('No se pudo leer el archivo XML');
    }
    
    // Parsear XML
    $xml = simplexml_load_string($xmlContent);
    if (!$xml) {
        throw new Exception('Error al parsear el XML');
    }
    
    // Registrar namespaces
    $namespaces = $xml->getNamespaces(true);
    
    // Extraer datos principales del comprobante
    $datos = [
        'version' => (string)$xml['Version'],
        'serie' => (string)$xml['Serie'],
        'folio' => (string)$xml['Folio'],
        'fecha' => (string)$xml['Fecha'],
        'sello' => (string)$xml['Sello'],
        'noCertificado' => (string)$xml['NoCertificado'],
        'certificado' => (string)$xml['Certificado'],
        'subTotal' => (float)$xml['SubTotal'],
        'descuento' => (float)$xml['Descuento'],
        'moneda' => (string)$xml['Moneda'],
        'tipoCambio' => (string)$xml['TipoCambio'],
        'total' => (float)$xml['Total'],
        'tipoDeComprobante' => (string)$xml['TipoDeComprobante'],
        'metodoPago' => (string)$xml['MetodoPago'],
        'lugarExpedicion' => (string)$xml['LugarExpedicion'],
        'formaPago' => (string)$xml['FormaPago'],
        'confirmacion' => (string)$xml['Confirmacion']
    ];
    
    // Extraer datos del emisor
    $emisor = $xml->{'cfdi:Emisor'} ?? $xml->Emisor;
    if ($emisor) {
        $datos['emisor'] = [
            'rfc' => (string)$emisor['Rfc'],
            'nombre' => (string)$emisor['Nombre'],
            'regimenFiscal' => (string)$emisor['RegimenFiscal']
        ];
        
        // Domicilio fiscal (si existe)
        $domicilioFiscal = $emisor->{'cfdi:DomicilioFiscal'} ?? $emisor->DomicilioFiscal;
        if ($domicilioFiscal) {
            $datos['emisor']['domicilio'] = [
                'calle' => (string)$domicilioFiscal['calle'],
                'noExterior' => (string)$domicilioFiscal['noExterior'],
                'noInterior' => (string)$domicilioFiscal['noInterior'],
                'colonia' => (string)$domicilioFiscal['colonia'],
                'localidad' => (string)$domicilioFiscal['localidad'],
                'municipio' => (string)$domicilioFiscal['municipio'],
                'estado' => (string)$domicilioFiscal['estado'],
                'pais' => (string)$domicilioFiscal['pais'],
                'codigoPostal' => (string)$domicilioFiscal['codigoPostal']
            ];
        }
    }
    
    // Extraer datos del receptor
    $receptor = $xml->{'cfdi:Receptor'} ?? $xml->Receptor;
    if ($receptor) {
        $datos['receptor'] = [
            'rfc' => (string)$receptor['Rfc'],
            'nombre' => (string)$receptor['Nombre'],
            'usoCFDI' => (string)$receptor['UsoCFDI']
        ];
        
        // Domicilio (si existe)
        $domicilio = $receptor->{'cfdi:Domicilio'} ?? $receptor->Domicilio;
        if ($domicilio) {
            $datos['receptor']['domicilio'] = [
                'calle' => (string)$domicilio['calle'],
                'noExterior' => (string)$domicilio['noExterior'],
                'noInterior' => (string)$domicilio['noInterior'],
                'colonia' => (string)$domicilio['colonia'],
                'localidad' => (string)$domicilio['localidad'],
                'municipio' => (string)$domicilio['municipio'],
                'estado' => (string)$domicilio['estado'],
                'pais' => (string)$domicilio['pais'],
                'codigoPostal' => (string)$domicilio['codigoPostal']
            ];
        }
    }
    
    // Extraer conceptos
    $conceptos = $xml->{'cfdi:Conceptos'} ?? $xml->Conceptos;
    if ($conceptos) {
        $datos['conceptos'] = [];
        foreach ($conceptos->{'cfdi:Concepto'} ?? $conceptos->Concepto ?? [] as $concepto) {
            $datos['conceptos'][] = [
                'claveProdServ' => (string)$concepto['ClaveProdServ'],
                'noIdentificacion' => (string)$concepto['NoIdentificacion'],
                'cantidad' => (float)$concepto['Cantidad'],
                'claveUnidad' => (string)$concepto['ClaveUnidad'],
                'unidad' => (string)$concepto['Unidad'],
                'descripcion' => (string)$concepto['Descripcion'],
                'valorUnitario' => (float)$concepto['ValorUnitario'],
                'importe' => (float)$concepto['Importe'],
                'descuento' => (float)$concepto['Descuento']
            ];
        }
    }
    
    // Extraer impuestos
    $impuestos = $xml->{'cfdi:Impuestos'} ?? $xml->Impuestos;
    if ($impuestos) {
        $datos['impuestos'] = [
            'totalImpuestosRetenidos' => (float)$impuestos['TotalImpuestosRetenidos'],
            'totalImpuestosTrasladados' => (float)$impuestos['TotalImpuestosTrasladados']
        ];
        
        // Retenciones
        $retenciones = $impuestos->{'cfdi:Retenciones'} ?? $impuestos->Retenciones;
        if ($retenciones) {
            $datos['impuestos']['retenciones'] = [];
            foreach ($retenciones->{'cfdi:Retencion'} ?? $retenciones->Retencion ?? [] as $retencion) {
                $datos['impuestos']['retenciones'][] = [
                    'impuesto' => (string)$retencion['Impuesto'],
                    'importe' => (float)$retencion['Importe']
                ];
            }
        }
        
        // Traslados
        $traslados = $impuestos->{'cfdi:Traslados'} ?? $impuestos->Traslados;
        if ($traslados) {
            $datos['impuestos']['traslados'] = [];
            foreach ($traslados->{'cfdi:Traslado'} ?? $traslados->Traslado ?? [] as $traslado) {
                $datos['impuestos']['traslados'][] = [
                    'impuesto' => (string)$traslado['Impuesto'],
                    'tasa' => (string)$traslado['TasaOCuota'],
                    'importe' => (float)$traslado['Importe']
                ];
            }
        }
    }
    
    // Extraer timbre fiscal (UUID)
    foreach ($namespaces as $prefix => $namespace) {
        if (strpos($namespace, 'TimbreFiscalDigital') !== false) {
            $tfd = $xml->children($namespace);
            if ($tfd && $tfd->TimbreFiscalDigital) {
                $datos['timbreFiscal'] = [
                    'uuid' => (string)$tfd->TimbreFiscalDigital['UUID'],
                    'fechaTimbrado' => (string)$tfd->TimbreFiscalDigital['FechaTimbrado'],
                    'rfcProvCertif' => (string)$tfd->TimbreFiscalDigital['RfcProvCertif'],
                    'selloSAT' => (string)$tfd->TimbreFiscalDigital['SelloSAT'],
                    'noCertificadoSAT' => (string)$tfd->TimbreFiscalDigital['NoCertificadoSAT']
                ];
                break;
            }
        }
    }
    
    return $datos;
}

// Verificar si existe TCPDF, si no, usar una implementación simple
if (!class_exists('TCPDF')) {
    // Implementación simple sin TCPDF
    try {
        // Conectar a base de datos
        $pdo = new PDO(
            "mysql:host=localhost;dbname=sac_db;charset=utf8mb4",
            "root",
            "",
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]
        );

        // Obtener UUID del parámetro
        $uuid = $_GET['uuid'] ?? '';
        if (empty($uuid)) {
            throw new Exception('UUID requerido');
        }

        // Consultar información del CFDI para obtener la ruta del XML
        $sql = "SELECT ruta_xml FROM cfdi WHERE uuid = :uuid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uuid' => $uuid]);
        $cfdi = $stmt->fetch();

        if (!$cfdi || !$cfdi['ruta_xml']) {
            throw new Exception('CFDI no encontrado o sin ruta XML');
        }
        
        // Extraer datos del XML
        $datosXML = extraerDatosXML($cfdi['ruta_xml']);

        // Generar HTML para convertir a PDF o mostrar
        header('Content-Type: text/html; charset=utf-8');
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>CFDI - ' . htmlspecialchars($uuid) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; font-size: 10px; }
                .header { text-align: center; font-weight: bold; font-size: 16px; margin-bottom: 20px; }
                .section { margin-bottom: 15px; page-break-inside: avoid; }
                .section-title { font-weight: bold; font-size: 12px; color: #333; margin-bottom: 8px; background-color: #f0f0f0; padding: 3px; }
                .info-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                .info-table td { padding: 3px; border: 1px solid #ddd; font-size: 9px; }
                .label { font-weight: bold; background-color: #f5f5f5; width: 120px; }
                .qr-section { text-align: center; margin: 20px 0; }
                .sello { font-size: 7px; word-wrap: break-word; max-width: 100%; font-family: monospace; }
                .conceptos-table { width: 100%; border-collapse: collapse; }
                .conceptos-table th { background-color: #e0e0e0; padding: 4px; border: 1px solid #ddd; font-size: 8px; }
                .conceptos-table td { padding: 3px; border: 1px solid #ddd; font-size: 8px; }
                .totales { text-align: right; margin-top: 10px; }
                .totales table { margin-left: auto; }
            </style>
        </head>
        <body>
            <div class="header">COMPROBANTE FISCAL DIGITAL POR INTERNET</div>
            
            <div class="section">
                <div class="section-title">INFORMACIÓN DEL COMPROBANTE</div>
                <table class="info-table">
                    <tr>
                        <td class="label">Versión CFDI:</td>
                        <td>' . htmlspecialchars($datosXML['version'] ?? 'N/A') . '</td>
                        <td class="label">UUID:</td>
                        <td>' . htmlspecialchars($datosXML['timbreFiscal']['uuid'] ?? $uuid) . '</td>
                    </tr>
                    <tr>
                        <td class="label">Serie:</td>
                        <td>' . htmlspecialchars($datosXML['serie'] ?? 'N/A') . '</td>
                        <td class="label">Folio:</td>
                        <td>' . htmlspecialchars($datosXML['folio'] ?? 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label">Fecha:</td>
                        <td>' . htmlspecialchars($datosXML['fecha'] ?? 'N/A') . '</td>
                        <td class="label">Lugar Expedición:</td>
                        <td>' . htmlspecialchars($datosXML['lugarExpedicion'] ?? 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label">Tipo Comprobante:</td>
                        <td>' . htmlspecialchars($datosXML['tipoDeComprobante'] ?? 'N/A') . '</td>
                        <td class="label">Método de Pago:</td>
                        <td>' . htmlspecialchars($datosXML['metodoPago'] ?? 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label">Forma de Pago:</td>
                        <td>' . htmlspecialchars($datosXML['formaPago'] ?? 'N/A') . '</td>
                        <td class="label">Moneda:</td>
                        <td>' . htmlspecialchars($datosXML['moneda'] ?? 'MXN') . '</td>
                    </tr>
                </table>
            </div>
            
            <div class="section">
                <div class="section-title">INFORMACIÓN DEL EMISOR</div>
                <table class="info-table">
                    <tr>
                        <td class="label">RFC:</td>
                        <td>' . htmlspecialchars($datosXML['emisor']['rfc'] ?? 'N/A') . '</td>
                        <td class="label">Régimen Fiscal:</td>
                        <td>' . htmlspecialchars($datosXML['emisor']['regimenFiscal'] ?? 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label">Nombre/Razón Social:</td>
                        <td colspan="3">' . htmlspecialchars($datosXML['emisor']['nombre'] ?? 'N/A') . '</td>
                    </tr>
                </table>
            </div>
            
            <div class="section">
                <div class="section-title">INFORMACIÓN DEL RECEPTOR</div>
                <table class="info-table">
                    <tr>
                        <td class="label">RFC:</td>
                        <td>' . htmlspecialchars($datosXML['receptor']['rfc'] ?? 'N/A') . '</td>
                        <td class="label">Uso CFDI:</td>
                        <td>' . htmlspecialchars($datosXML['receptor']['usoCFDI'] ?? 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label">Nombre/Razón Social:</td>
                        <td colspan="3">' . htmlspecialchars($datosXML['receptor']['nombre'] ?? 'N/A') . '</td>
                    </tr>
                </table>
            </div>';

        // Mostrar conceptos si existen
        if (!empty($datosXML['conceptos'])) {
            echo '<div class="section">
                    <div class="section-title">CONCEPTOS</div>
                    <table class="conceptos-table">
                        <thead>
                            <tr>
                                <th>Cantidad</th>
                                <th>Unidad</th>
                                <th>Descripción</th>
                                <th>Valor Unitario</th>
                                <th>Importe</th>
                            </tr>
                        </thead>
                        <tbody>';
            
            foreach ($datosXML['conceptos'] as $concepto) {
                echo '<tr>
                        <td>' . number_format($concepto['cantidad'], 2) . '</td>
                        <td>' . htmlspecialchars($concepto['unidad'] ?? $concepto['claveUnidad']) . '</td>
                        <td>' . htmlspecialchars($concepto['descripcion']) . '</td>
                        <td>$' . number_format($concepto['valorUnitario'], 2) . '</td>
                        <td>$' . number_format($concepto['importe'], 2) . '</td>
                      </tr>';
            }
            
            echo '</tbody></table></div>';
        }

        // Mostrar totales
        echo '<div class="totales">
                <table class="info-table" style="width: 300px;">
                    <tr>
                        <td class="label">SubTotal:</td>
                        <td>$' . number_format($datosXML['subTotal'] ?? 0, 2) . '</td>
                    </tr>';
        
        if (($datosXML['descuento'] ?? 0) > 0) {
            echo '<tr>
                    <td class="label">Descuento:</td>
                    <td>$' . number_format($datosXML['descuento'], 2) . '</td>
                  </tr>';
        }
        
        // Mostrar impuestos si existen
        if (!empty($datosXML['impuestos']['traslados'])) {
            foreach ($datosXML['impuestos']['traslados'] as $traslado) {
                $nombreImpuesto = $traslado['impuesto'] == '002' ? 'IVA' : 'Impuesto ' . $traslado['impuesto'];
                echo '<tr>
                        <td class="label">' . $nombreImpuesto . ' (' . ($traslado['tasa'] * 100) . '%):</td>
                        <td>$' . number_format($traslado['importe'], 2) . '</td>
                      </tr>';
            }
        }
        
        if (!empty($datosXML['impuestos']['retenciones'])) {
            foreach ($datosXML['impuestos']['retenciones'] as $retencion) {
                $nombreImpuesto = $retencion['impuesto'] == '002' ? 'IVA Ret.' : 'Ret. ' . $retencion['impuesto'];
                echo '<tr>
                        <td class="label">' . $nombreImpuesto . ':</td>
                        <td>-$' . number_format($retencion['importe'], 2) . '</td>
                      </tr>';
            }
        }
        
        echo '<tr style="font-weight: bold; background-color: #f0f0f0;">
                <td class="label">TOTAL:</td>
                <td>$' . number_format($datosXML['total'] ?? 0, 2) . '</td>
              </tr>
              </table>
              </div>';

        // Generar QR Code usando servicio externo
        $qrData = urlencode("https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?&id=" . $uuid . 
                  "&re=" . ($datosXML['emisor']['rfc'] ?? '') . 
                  "&rr=" . ($datosXML['receptor']['rfc'] ?? '') . 
                  "&tt=" . number_format($datosXML['total'] ?? 0, 6) . 
                  "&fe=" . substr(hash('sha1', $datosXML['sello'] ?? ''), -8));
        
        echo '<div class="qr-section">
                <div class="section-title">CÓDIGO QR DE VERIFICACIÓN SAT</div>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . $qrData . '" alt="Código QR">
              </div>';
        
        echo '<div class="section">
                <div class="section-title">INFORMACIÓN DEL TIMBRE FISCAL DIGITAL</div>
                <table class="info-table">
                    <tr>
                        <td class="label">Fecha Timbrado:</td>
                        <td>' . htmlspecialchars($datosXML['timbreFiscal']['fechaTimbrado'] ?? 'N/A') . '</td>
                        <td class="label">RFC Proveedor:</td>
                        <td>' . htmlspecialchars($datosXML['timbreFiscal']['rfcProvCertif'] ?? 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="label">No. Certificado SAT:</td>
                        <td colspan="3">' . htmlspecialchars($datosXML['timbreFiscal']['noCertificadoSAT'] ?? 'N/A') . '</td>
                    </tr>
                </table>
              </div>';
        
        echo '<div class="section">
                <div class="section-title">SELLO DIGITAL DEL CFDI</div>
                <div class="sello">' . htmlspecialchars($datosXML['sello'] ?? 'N/A') . '</div>
              </div>';
              
        echo '<div class="section">
                <div class="section-title">SELLO DIGITAL DEL SAT</div>
                <div class="sello">' . htmlspecialchars($datosXML['timbreFiscal']['selloSAT'] ?? 'N/A') . '</div>
              </div>';
        
        echo '</body></html>';
        
    } catch (Exception $e) {
        echo "Error al generar PDF: " . $e->getMessage();
    }
    exit;
}

try {
    // Conectar a base de datos
    $pdo = new PDO(
        "mysql:host=localhost;dbname=sac_db;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );

    // Obtener UUID del parámetro
    $uuid = $_GET['uuid'] ?? '';
    if (empty($uuid)) {
        throw new Exception('UUID requerido');
    }

    // Consultar información del CFDI para obtener la ruta del XML
    $sql = "SELECT ruta_xml FROM cfdi WHERE uuid = :uuid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uuid' => $uuid]);
    $cfdi = $stmt->fetch();

    if (!$cfdi || !$cfdi['ruta_xml']) {
        throw new Exception('CFDI no encontrado o sin ruta XML');
    }
    
    // Extraer datos del XML
    $datosXML = extraerDatosXML($cfdi['ruta_xml']);

    // Crear PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configurar PDF
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('SAC - Sistema de Administración Contable');
    $pdf->SetTitle('CFDI - ' . $uuid);
    $pdf->SetSubject('Comprobante Fiscal Digital');
    
    // Configurar márgenes
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Agregar página
    $pdf->AddPage();
    
    // Configurar fuente
    $pdf->SetFont('helvetica', '', 10);
    
    // Título principal
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'COMPROBANTE FISCAL DIGITAL POR INTERNET', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Información del comprobante
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'INFORMACIÓN DEL COMPROBANTE', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(30, 6, 'Versión:', 0, 0, 'L');
    $pdf->Cell(40, 6, $datosXML['version'] ?? 'N/A', 0, 0, 'L');
    $pdf->Cell(30, 6, 'UUID:', 0, 0, 'L');
    $pdf->Cell(70, 6, $datosXML['timbreFiscal']['uuid'] ?? $uuid, 0, 1, 'L');
    
    $pdf->Cell(30, 6, 'Serie:', 0, 0, 'L');
    $pdf->Cell(40, 6, $datosXML['serie'] ?? 'N/A', 0, 0, 'L');
    $pdf->Cell(30, 6, 'Folio:', 0, 0, 'L');
    $pdf->Cell(70, 6, $datosXML['folio'] ?? 'N/A', 0, 1, 'L');
    
    $pdf->Cell(30, 6, 'Fecha:', 0, 0, 'L');
    $pdf->Cell(40, 6, $datosXML['fecha'] ?? 'N/A', 0, 0, 'L');
    $pdf->Cell(30, 6, 'Tipo:', 0, 0, 'L');
    $pdf->Cell(70, 6, $datosXML['tipoDeComprobante'] ?? 'N/A', 0, 1, 'L');
    $pdf->Ln(5);
    
    // Información del emisor
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'INFORMACIÓN DEL EMISOR', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(30, 6, 'RFC:', 0, 0, 'L');
    $pdf->Cell(60, 6, $datosXML['emisor']['rfc'] ?? 'N/A', 0, 0, 'L');
    $pdf->Cell(30, 6, 'Régimen:', 0, 0, 'L');
    $pdf->Cell(60, 6, $datosXML['emisor']['regimenFiscal'] ?? 'N/A', 0, 1, 'L');
    
    $pdf->Cell(30, 6, 'Nombre:', 0, 0, 'L');
    $pdf->MultiCell(150, 6, $datosXML['emisor']['nombre'] ?? 'N/A', 0, 'L');
    $pdf->Ln(3);
    
    // Información del receptor
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'INFORMACIÓN DEL RECEPTOR', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(30, 6, 'RFC:', 0, 0, 'L');
    $pdf->Cell(60, 6, $datosXML['receptor']['rfc'] ?? 'N/A', 0, 0, 'L');
    $pdf->Cell(30, 6, 'Uso CFDI:', 0, 0, 'L');
    $pdf->Cell(60, 6, $datosXML['receptor']['usoCFDI'] ?? 'N/A', 0, 1, 'L');
    
    $pdf->Cell(30, 6, 'Nombre:', 0, 0, 'L');
    $pdf->MultiCell(150, 6, $datosXML['receptor']['nombre'] ?? 'N/A', 0, 'L');
    $pdf->Ln(5);
    
    // Conceptos
    if (!empty($datosXML['conceptos'])) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 8, 'CONCEPTOS', 0, 1, 'L');
        
        // Encabezados de tabla
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(20, 6, 'Cantidad', 1, 0, 'C');
        $pdf->Cell(20, 6, 'Unidad', 1, 0, 'C');
        $pdf->Cell(80, 6, 'Descripción', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Valor Unit.', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Importe', 1, 1, 'C');
        
        // Conceptos
        $pdf->SetFont('helvetica', '', 7);
        foreach ($datosXML['conceptos'] as $concepto) {
            $pdf->Cell(20, 5, number_format($concepto['cantidad'], 2), 1, 0, 'C');
            $pdf->Cell(20, 5, $concepto['claveUnidad'] ?? '', 1, 0, 'C');
            $pdf->Cell(80, 5, substr($concepto['descripcion'] ?? '', 0, 50), 1, 0, 'L');
            $pdf->Cell(25, 5, '$' . number_format($concepto['valorUnitario'] ?? 0, 2), 1, 0, 'R');
            $pdf->Cell(25, 5, '$' . number_format($concepto['importe'] ?? 0, 2), 1, 1, 'R');
        }
        $pdf->Ln(5);
    }
    
    // Totales
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(120, 6, '', 0, 0, 'L'); // Espaciado
    $pdf->Cell(30, 6, 'SubTotal:', 0, 0, 'L');
    $pdf->Cell(30, 6, '$' . number_format($datosXML['subTotal'] ?? 0, 2), 0, 1, 'R');
    
    if (($datosXML['descuento'] ?? 0) > 0) {
        $pdf->Cell(120, 6, '', 0, 0, 'L');
        $pdf->Cell(30, 6, 'Descuento:', 0, 0, 'L');
        $pdf->Cell(30, 6, '$' . number_format($datosXML['descuento'], 2), 0, 1, 'R');
    }
    
    // Impuestos
    if (!empty($datosXML['impuestos']['traslados'])) {
        foreach ($datosXML['impuestos']['traslados'] as $traslado) {
            $nombreImpuesto = $traslado['impuesto'] == '002' ? 'IVA' : 'Imp. ' . $traslado['impuesto'];
            $pdf->Cell(120, 6, '', 0, 0, 'L');
            $pdf->Cell(30, 6, $nombreImpuesto . ' (' . ($traslado['tasa'] * 100) . '%):', 0, 0, 'L');
            $pdf->Cell(30, 6, '$' . number_format($traslado['importe'], 2), 0, 1, 'R');
        }
    }
    
    if (!empty($datosXML['impuestos']['retenciones'])) {
        foreach ($datosXML['impuestos']['retenciones'] as $retencion) {
            $nombreImpuesto = $retencion['impuesto'] == '002' ? 'IVA Ret.' : 'Ret. ' . $retencion['impuesto'];
            $pdf->Cell(120, 6, '', 0, 0, 'L');
            $pdf->Cell(30, 6, $nombreImpuesto . ':', 0, 0, 'L');
            $pdf->Cell(30, 6, '-$' . number_format($retencion['importe'], 2), 0, 1, 'R');
        }
    }
    
    // Total
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(120, 8, '', 0, 0, 'L');
    $pdf->Cell(30, 8, 'TOTAL:', 1, 0, 'L');
    $pdf->Cell(30, 8, '$' . number_format($datosXML['total'] ?? 0, 2), 1, 1, 'R');
    $pdf->Ln(10);
    
    // Generar código QR (si tienes librería de QR)
    if (class_exists('QRcode')) {
        // Datos para el QR según SAT
        $qrData = "https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?&id=" . $uuid . 
                  "&re=" . ($datosXML['emisor']['rfc'] ?? '') . 
                  "&rr=" . ($datosXML['receptor']['rfc'] ?? '') . 
                  "&tt=" . number_format($datosXML['total'] ?? 0, 6) . 
                  "&fe=" . substr(hash('sha1', $datosXML['sello'] ?? ''), -8);
        
        // Generar QR temporal
        $qrFile = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
        QRcode::png($qrData, $qrFile, QR_ECLEVEL_M, 4);
        
        // Agregar QR al PDF
        $pdf->Image($qrFile, 150, $pdf->GetY(), 40, 40);
        
        // Limpiar archivo temporal
        unlink($qrFile);
    }
    
    // Información del timbre
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'TIMBRE FISCAL DIGITAL', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(30, 5, 'Fecha Timbrado:', 0, 0, 'L');
    $pdf->Cell(60, 5, $datosXML['timbreFiscal']['fechaTimbrado'] ?? 'N/A', 0, 0, 'L');
    $pdf->Cell(30, 5, 'RFC Proveedor:', 0, 0, 'L');
    $pdf->Cell(60, 5, $datosXML['timbreFiscal']['rfcProvCertif'] ?? 'N/A', 0, 1, 'L');
    $pdf->Ln(5);
    
    // Sello digital
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, 'SELLO DIGITAL DEL CFDI', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 6);
    $sello = $datosXML['sello'] ?? 'N/A';
    $pdf->MultiCell(0, 3, wordwrap($sello, 140, "\n", true), 0, 'L');
    $pdf->Ln(3);
    
    // Sello del SAT
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, 'SELLO DIGITAL DEL SAT', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 6);
    $selloSat = $datosXML['timbreFiscal']['selloSAT'] ?? 'N/A';
    $pdf->MultiCell(0, 3, wordwrap($selloSat, 140, "\n", true), 0, 'L');
    
    // Generar PDF
    $nombreArchivo = 'CFDI_' . $uuid . '.pdf';
    $pdf->Output($nombreArchivo, 'D'); // 'D' para descargar
    
} catch (Exception $e) {
    // En caso de error, mostrar mensaje
    echo "Error al generar PDF: " . $e->getMessage();
}
?>
