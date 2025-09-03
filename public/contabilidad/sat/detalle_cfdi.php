<?php
session_start();
require_once '../../../src/helpers/auth.php';
checkAuth(['admin', 'contabilidad']);

$uuid = $_GET['uuid'] ?? '';

if (empty($uuid)) {
    header('Location: reportes_especiales.php');
    exit;
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
    die("Error de conexión: " . $e->getMessage());
}

// Obtener datos del CFDI
$sql = "SELECT c.*, 
               e.rfc as rfc_emisor, e.nombre as nombre_emisor, e.regimen_fiscal,
               r.rfc as rfc_receptor, r.nombre as nombre_receptor, r.uso_cfdi, r.domicilio_fiscal_receptor,
               tfd.fecha_timbrado, tfd.rfc_prov_certif, tfd.no_certificado_sat, tfd.sello_sat
        FROM cfdi c
        LEFT JOIN emisor e ON c.id = e.cfdi_id
        LEFT JOIN receptor r ON c.id = r.cfdi_id
        LEFT JOIN cfdi_timbre_fiscal_digital tfd ON c.id = tfd.cfdi_id
        WHERE c.uuid = :uuid";

$stmt = $pdo->prepare($sql);
$stmt->execute([':uuid' => $uuid]);
$cfdi = $stmt->fetch();

if (!$cfdi) {
    header('Location: reportes_especiales.php');
    exit;
}

// Obtener conceptos
$sql_conceptos = "SELECT * FROM conceptos WHERE cfdi_id = :cfdi_id";
$stmt = $pdo->prepare($sql_conceptos);
$stmt->execute([':cfdi_id' => $cfdi['id']]);
$conceptos = $stmt->fetchAll();

// Obtener impuestos trasladados
$sql_impuestos = "SELECT it.*, c.descripcion as concepto_desc
                  FROM impuestos_trasladados it
                  JOIN conceptos c ON it.concepto_id = c.id
                  WHERE c.cfdi_id = :cfdi_id";
$stmt = $pdo->prepare($sql_impuestos);
$stmt->execute([':cfdi_id' => $cfdi['id']]);
$impuestos_trasladados = $stmt->fetchAll();

// Obtener complementos
$sql_complementos = "SELECT 'Pagos v1.0' as tipo, fecha_pago as fecha, monto as valor, 'Complemento de Pagos' as descripcion
                     FROM cfdi_complemento_pagos_v10 WHERE cfdi_id = :cfdi_id
                     UNION ALL
                     SELECT 'Pagos v2.0' as tipo, fecha_pago as fecha, monto as valor, 'Complemento de Pagos v2.0' as descripcion
                     FROM cfdi_complemento_pagos_v20 WHERE cfdi_id = :cfdi_id
                     UNION ALL
                     SELECT 'Nómina' as tipo, fecha_pago as fecha, total_percepciones as valor, 'Complemento de Nómina' as descripcion
                     FROM cfdi_complemento_nomina WHERE cfdi_id = :cfdi_id";
$stmt = $pdo->prepare($sql_complementos);
$stmt->execute([':cfdi_id' => $cfdi['id']]);
$complementos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle CFDI - <?= htmlspecialchars($uuid) ?></title>
    <link rel="stylesheet" href="../../dashboard/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .detail-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .detail-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .detail-header h1 {
            margin: 0;
            font-size: 1.5rem;
        }

        .detail-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .detail-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .detail-card h3 {
            margin: 0 0 1rem 0;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 0.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
        }

        .detail-value {
            color: #333;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 1rem;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 8px;
            overflow: hidden;
        }

        .detail-table th {
            background: #f8f9fa;
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            color: #333;
        }

        .detail-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .amount {
            font-weight: 600;
            color: #28a745;
        }

        .back-button {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .back-button:hover {
            background: #5a6268;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }
    </style>
</head>

<body>
    <div class="detail-container">
        <a href="reportes_especiales.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Volver a Reportes
        </a>

        <div class="detail-header">
            <h1><i class="fas fa-file-invoice"></i> Detalle del CFDI</h1>
            <p>UUID: <?= htmlspecialchars($uuid) ?></p>
            <p>Fecha: <?= date('d/m/Y H:i:s', strtotime($cfdi['fecha'])) ?></p>
        </div>

        <div class="detail-grid">
            <!-- Información General -->
            <div class="detail-card">
                <h3><i class="fas fa-info-circle"></i> Información General</h3>
                <div class="detail-row">
                    <span class="detail-label">Versión:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['version']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Serie:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['serie'] ?: 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Folio:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['folio'] ?: 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tipo de Comprobante:</span>
                    <span class="detail-value">
                        <?php
                        $tipos = ['I' => 'Ingreso', 'E' => 'Egreso', 'T' => 'Traslado', 'P' => 'Pago', 'N' => 'Nómina'];
                        echo $tipos[$cfdi['tipo_comprobante']] ?? $cfdi['tipo_comprobante'];
                        ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Forma de Pago:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['forma_pago'] ?: 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Método de Pago:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['metodo_pago'] ?: 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Lugar de Expedición:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['lugar_expedicion'] ?: 'N/A') ?></span>
                </div>
            </div>

            <!-- Montos -->
            <div class="detail-card">
                <h3><i class="fas fa-dollar-sign"></i> Montos</h3>
                <div class="detail-row">
                    <span class="detail-label">Subtotal:</span>
                    <span class="detail-value amount">$<?= number_format($cfdi['subtotal'], 2) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Descuento:</span>
                    <span class="detail-value">$<?= number_format($cfdi['descuento'] ?: 0, 2) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total:</span>
                    <span class="detail-value amount">$<?= number_format($cfdi['total'], 2) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Moneda:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['moneda']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tipo de Cambio:</span>
                    <span class="detail-value"><?= number_format($cfdi['tipo_cambio'] ?: 1, 4) ?></span>
                </div>
            </div>

            <!-- Emisor -->
            <div class="detail-card">
                <h3><i class="fas fa-building"></i> Emisor</h3>
                <div class="detail-row">
                    <span class="detail-label">RFC:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['rfc_emisor']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Nombre:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['nombre_emisor']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Régimen Fiscal:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['regimen_fiscal'] ?: 'N/A') ?></span>
                </div>
            </div>

            <!-- Receptor -->
            <div class="detail-card">
                <h3><i class="fas fa-user"></i> Receptor</h3>
                <div class="detail-row">
                    <span class="detail-label">RFC:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['rfc_receptor'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Nombre:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['nombre_receptor'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Uso de CFDI:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['uso_cfdi'] ?: 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Domicilio Fiscal:</span>
                    <span class="detail-value"><?= htmlspecialchars($cfdi['domicilio_fiscal_receptor'] ?: 'N/A') ?></span>
                </div>
            </div>

            <!-- Timbre Fiscal -->
            <?php if (!empty($cfdi['fecha_timbrado'])): ?>
                <div class="detail-card">
                    <h3><i class="fas fa-stamp"></i> Timbre Fiscal Digital</h3>
                    <div class="detail-row">
                        <span class="detail-label">Fecha Timbrado:</span>
                        <span class="detail-value"><?= date('d/m/Y H:i:s', strtotime($cfdi['fecha_timbrado'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">RFC Proveedor:</span>
                        <span class="detail-value"><?= htmlspecialchars($cfdi['rfc_prov_certif']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">No. Certificado SAT:</span>
                        <span class="detail-value"><?= htmlspecialchars($cfdi['no_certificado_sat']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Estado:</span>
                        <span class="detail-value">
                            <span class="status-badge status-active">Timbrado Válido</span>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Conceptos -->
        <?php if (!empty($conceptos)): ?>
            <div class="detail-card full-width">
                <h3><i class="fas fa-list"></i> Conceptos</h3>
                <div class="table-container">
                    <table class="detail-table">
                        <thead>
                            <tr>
                                <th>Clave Prod/Serv</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Unidad</th>
                                <th>Valor Unitario</th>
                                <th>Importe</th>
                                <th>Descuento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conceptos as $concepto): ?>
                                <tr>
                                    <td><?= htmlspecialchars($concepto['clave_prod_serv']) ?></td>
                                    <td><?= htmlspecialchars($concepto['descripcion']) ?></td>
                                    <td><?= number_format($concepto['cantidad'], 2) ?></td>
                                    <td><?= htmlspecialchars($concepto['clave_unidad']) ?></td>
                                    <td>$<?= number_format($concepto['valor_unitario'], 2) ?></td>
                                    <td class="amount">$<?= number_format($concepto['importe'], 2) ?></td>
                                    <td>$<?= number_format($concepto['descuento'] ?: 0, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Impuestos -->
        <?php if (!empty($impuestos_trasladados)): ?>
            <div class="detail-card full-width">
                <h3><i class="fas fa-percentage"></i> Impuestos Trasladados</h3>
                <div class="table-container">
                    <table class="detail-table">
                        <thead>
                            <tr>
                                <th>Concepto</th>
                                <th>Impuesto</th>
                                <th>Tipo Factor</th>
                                <th>Tasa/Cuota</th>
                                <th>Base</th>
                                <th>Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($impuestos_trasladados as $impuesto): ?>
                                <tr>
                                    <td><?= htmlspecialchars($impuesto['concepto_desc']) ?></td>
                                    <td><?= htmlspecialchars($impuesto['impuesto']) ?></td>
                                    <td><?= htmlspecialchars($impuesto['tipo_factor']) ?></td>
                                    <td><?= number_format($impuesto['tasa_cuota'] * 100, 2) ?>%</td>
                                    <td>$<?= number_format($impuesto['base'], 2) ?></td>
                                    <td class="amount">$<?= number_format($impuesto['importe'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Complementos -->
        <?php if (!empty($complementos)): ?>
            <div class="detail-card full-width">
                <h3><i class="fas fa-puzzle-piece"></i> Complementos</h3>
                <div class="table-container">
                    <table class="detail-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th>Fecha</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complementos as $complemento): ?>
                                <tr>
                                    <td><?= htmlspecialchars($complemento['tipo']) ?></td>
                                    <td><?= htmlspecialchars($complemento['descripcion']) ?></td>
                                    <td><?= $complemento['fecha'] ? date('d/m/Y', strtotime($complemento['fecha'])) : 'N/A' ?></td>
                                    <td class="amount">$<?= number_format($complemento['valor'] ?: 0, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Acciones -->
        <div class="detail-card full-width">
            <h3><i class="fas fa-tools"></i> Acciones</h3>
            <div class="action-buttons">
                <a href="api/descargar_xml.php?uuid=<?= urlencode($uuid) ?>" class="btn btn-success">
                    <i class="fas fa-download"></i> Descargar XML Original
                </a>
                <a href="api/generar_pdf.php?uuid=<?= urlencode($uuid) ?>" class="btn btn-info" target="_blank">
                    <i class="fas fa-file-pdf"></i> Generar PDF
                </a>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
        </div>
    </div>

    <script>
        // Mejorar la impresión
        window.addEventListener('beforeprint', function() {
            document.querySelector('.back-button').style.display = 'none';
            document.querySelector('.action-buttons').style.display = 'none';
        });

        window.addEventListener('afterprint', function() {
            document.querySelector('.back-button').style.display = 'inline-flex';
            document.querySelector('.action-buttons').style.display = 'flex';
        });
    </script>
</body>

</html>