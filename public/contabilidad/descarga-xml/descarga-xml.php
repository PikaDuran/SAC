<?php
// Render dinámico de la tabla de solicitudes desde la base de datos
require_once __DIR__ . '/../../../src/config/database.php';
header('Content-Type: text/html; charset=utf-8');

$pdo = getDatabase();
$stmt = $pdo->query("SELECT id, rfc, status, estatus_solicitud, mensaje_verificacion FROM sat_download_history ORDER BY id DESC");
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Descarga XML SAT</title>
    <link rel="stylesheet" href="descarga-xml.css">
    <script defer src="descarga-xml.js"></script>
    <style>
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
        }

        th {
            background: #f0f0f0;
        }
    </style>
</head>

<body>
    <h1>Solicitudes de Descarga SAT</h1>
    <table id="tabla-solicitudes">
        <thead>
            <tr>
                <th>ID</th>
                <th>RFC</th>
                <th>Status</th>
                <th>Estatus Solicitud</th>
                <th>Mensaje Verificación</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($solicitudes as $sol): ?>
                <tr id="solicitud-row-<?= htmlspecialchars($sol['id']) ?>">
                    <td><?= htmlspecialchars($sol['id']) ?></td>
                    <td><?= htmlspecialchars($sol['rfc']) ?></td>
                    <td class="celda-status"><?= htmlspecialchars($sol['status'] ?: '-') ?></td>
                    <td class="celda-estatus"><?= htmlspecialchars($sol['estatus_solicitud'] ?: '-') ?></td>
                    <td class="celda-mensaje"><?= htmlspecialchars($sol['mensaje_verificacion'] ?: '-') ?></td>
                    <td><button class="btn-verificar-sat" data-id="<?= htmlspecialchars($sol['id']) ?>">Verificar SAT</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>