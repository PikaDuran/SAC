<?php
require_once '../../../../src/helpers/auth.php';
checkAuth(['admin', 'contabilidad']);

$uuid = $_GET['uuid'] ?? '';

if (empty($uuid)) {
    http_response_code(400);
    die('UUID requerido');
}

// Conectar a base de datos
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=sac_db;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die("Error de conexión: " . $e->getMessage());
}

// Obtener datos del CFDI
$sql = "SELECT c.*, e.*, r.*, tfd.*
        FROM cfdi c
        LEFT JOIN emisor e ON c.id = e.cfdi_id
        LEFT JOIN receptor r ON c.id = r.cfdi_id
        LEFT JOIN cfdi_timbre_fiscal_digital tfd ON c.id = tfd.cfdi_id
        WHERE c.uuid = :uuid";

$stmt = $pdo->prepare($sql);
$stmt->execute([':uuid' => $uuid]);
$cfdi = $stmt->fetch();

if (!$cfdi) {
    http_response_code(404);
    die('CFDI no encontrado');
}

// Obtener conceptos
$sql_conceptos = "SELECT * FROM conceptos WHERE cfdi_id = :cfdi_id";
$stmt = $pdo->prepare($sql_conceptos);
$stmt->execute([':cfdi_id' => $cfdi['id']]);
$conceptos = $stmt->fetchAll();

// Generar HTML para PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CFDI - ' . htmlspecialchars($uuid) . '</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .company-info { text-align: center; margin-bottom: 20px; }
        .company-name { font-size: 18px; font-weight: bold; color: #333; }
        .grid { display: table; width: 100%; margin-bottom: 20px; }
        .col { display: table-cell; vertical-align: top; padding: 10px; }
        .label { font-weight: bold; color: #666; }
        .value { color: #333; }
        .section { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .section-title { font-size: 14px; font-weight: bold; margin-bottom: 15px; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        .concepts-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .concepts-table th, .concepts-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .concepts-table th { background-color: #f8f9fa; font-weight: bold; }
        .amount { text-align: right; font-weight: bold; color: #28a745; }
        .totals { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; }
        .total-line { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .total-final { font-size: 16px; font-weight: bold; color: #007bff; border-top: 2px solid #007bff; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="company-info">
        <div class="company-name">Sistema de Administración Contable (SAC)</div>
        <div>Comprobante Fiscal Digital por Internet (CFDI)</div>
    </div>
    
    <div class="header">
        <div class="grid">
            <div class="col">
                <div class="label">UUID:</div>
                <div class="value">' . htmlspecialchars($uuid) . '</div>
            </div>
            <div class="col">
                <div class="label">Fecha:</div>
                <div class="value">' . date('d/m/Y H:i:s', strtotime($cfdi['fecha'])) . '</div>
            </div>
            <div class="col">
                <div class="label">Tipo:</div>
                <div class="value">';

$tipos = ['I' => 'Ingreso', 'E' => 'Egreso', 'T' => 'Traslado', 'P' => 'Pago', 'N' => 'Nómina'];
$html .= $tipos[$cfdi['tipo_comprobante']] ?? $cfdi['tipo_comprobante'];

$html .= '</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">EMISOR</div>
        <div class="grid">
            <div class="col">
                <div class="label">RFC:</div>
                <div class="value">' . htmlspecialchars($cfdi['rfc']) . '</div>
                <div class="label">Nombre:</div>
                <div class="value">' . htmlspecialchars($cfdi['nombre']) . '</div>
            </div>
            <div class="col">
                <div class="label">Régimen Fiscal:</div>
                <div class="value">' . htmlspecialchars($cfdi['regimen_fiscal'] ?: 'N/A') . '</div>
                <div class="label">Lugar de Expedición:</div>
                <div class="value">' . htmlspecialchars($cfdi['lugar_expedicion'] ?: 'N/A') . '</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">RECEPTOR</div>
        <div class="grid">
            <div class="col">
                <div class="label">RFC:</div>
                <div class="value">' . htmlspecialchars($cfdi['0']) . '</div>
                <div class="label">Nombre:</div>
                <div class="value">' . htmlspecialchars($cfdi['1']) . '</div>
            </div>
            <div class="col">
                <div class="label">Uso de CFDI:</div>
                <div class="value">' . htmlspecialchars($cfdi['uso_cfdi'] ?: 'N/A') . '</div>
                <div class="label">Domicilio Fiscal:</div>
                <div class="value">' . htmlspecialchars($cfdi['domicilio_fiscal_receptor'] ?: 'N/A') . '</div>
            </div>
        </div>
    </div>';

if (!empty($conceptos)) {
    $html .= '
    <div class="section">
        <div class="section-title">CONCEPTOS</div>
        <table class="concepts-table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Cantidad</th>
                    <th>Unidad</th>
                    <th>Valor Unitario</th>
                    <th>Importe</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($conceptos as $concepto) {
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($concepto['descripcion']) . '</td>
                    <td>' . number_format($concepto['cantidad'], 2) . '</td>
                    <td>' . htmlspecialchars($concepto['clave_unidad']) . '</td>
                    <td class="amount">$' . number_format($concepto['valor_unitario'], 2) . '</td>
                    <td class="amount">$' . number_format($concepto['importe'], 2) . '</td>
                </tr>';
    }

    $html .= '
            </tbody>
        </table>
    </div>';
}

$html .= '
    <div class="totals">
        <div class="total-line">
            <span>Subtotal:</span>
            <span class="amount">$' . number_format($cfdi['subtotal'], 2) . '</span>
        </div>';

if ($cfdi['descuento'] > 0) {
    $html .= '
        <div class="total-line">
            <span>Descuento:</span>
            <span class="amount">$' . number_format($cfdi['descuento'], 2) . '</span>
        </div>';
}

$html .= '
        <div class="total-line total-final">
            <span>TOTAL (' . htmlspecialchars($cfdi['moneda']) . '):</span>
            <span class="amount">$' . number_format($cfdi['total'], 2) . '</span>
        </div>
    </div>';

if (!empty($cfdi['2'])) {
    $html .= '
    <div class="section">
        <div class="section-title">TIMBRE FISCAL DIGITAL</div>
        <div class="grid">
            <div class="col">
                <div class="label">Fecha de Timbrado:</div>
                <div class="value">' . date('d/m/Y H:i:s', strtotime($cfdi['fecha_timbrado'])) . '</div>
                <div class="label">RFC Proveedor:</div>
                <div class="value">' . htmlspecialchars($cfdi['rfc_prov_certif']) . '</div>
            </div>
            <div class="col">
                <div class="label">No. Certificado SAT:</div>
                <div class="value">' . htmlspecialchars($cfdi['no_certificado_sat']) . '</div>
                <div class="label">Estado:</div>
                <div class="value">Timbrado Válido</div>
            </div>
        </div>
    </div>';
}

$html .= '
    <div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">
        <p>Este documento es una representación impresa de un CFDI</p>
        <p>Generado el ' . date('d/m/Y H:i:s') . ' desde el Sistema SAC</p>
    </div>
</body>
</html>';

// Configurar headers para mostrar PDF en el navegador
header('Content-Type: text/html; charset=UTF-8');
echo $html;
exit;
