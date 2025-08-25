<?php
session_start();
require_once '../../../src/helpers/auth.php';
checkAuth(['admin', 'contabilidad']);
require_once '../../../src/config/database.php';

// Obtener RFC disponibles de los certificados FIEL
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->query("
        SELECT id, rfc, legal_name, valid_to 
        FROM sat_fiel_certificates 
        WHERE is_active = 1 AND valid_to > NOW() 
        ORDER BY rfc
    ");
    $certificados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $certificados = [];
    $error = "Error al cargar certificados: " . $e->getMessage();
}

// Calcular fechas por defecto
$primer_dia_mes = date('Y-m-01'); // Primer día del mes actual
$dia_actual = date('Y-m-d');      // Día actual
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descarga Masiva SAT - SAC</title>
    <link rel="stylesheet" href="../../dashboard/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="e-firma.css">
    <link rel="stylesheet" href="descarga-xml.css">

    <!-- Estilos personalizados para SweetAlert2 -->
    <style>
        .swal-solicitudes-info {
            text-align: left;
            font-size: 1rem;
        }

        .swal-solicitudes-info p {
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .swal-solicitudes-info i {
            color: #007cba;
            width: 20px;
        }

        .total-solicitudes {
            background: #e3f2fd;
            border: 2px solid #007cba;
            border-radius: 8px;
            padding: 12px;
            margin-top: 15px;
            text-align: center;
            font-size: 1.1rem;
            color: #1976d2;
        }

        .swal-wide {
            width: 500px !important;
        }

        .swal-html-container {
            margin: 1rem 0 !important;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../../src/views/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../../../src/views/header.php'; ?>

            <div class="content">
                <div class="content-header">
                    <nav class="breadcrumb">
                        <a href="../../dashboard/dashboard.php">Dashboard</a> &gt;
                        <a href="../dashboard.php">Contabilidad</a> &gt;
                        <a href="dashboard.php">SAT</a> &gt;
                        Descarga Masiva
                    </nav>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Selector de RFC y Buscador -->
                <div class="form-container">
                    <div class="content-header-actions">
                        <h2>📋 Descarga Masiva de CFDIs</h2>
                        <a href="documentacion.php" class="btn btn-info" style="margin-left: auto;">
                            📚 Ver Documentación SAT v1.5
                        </a>
                    </div>
                    <p class="form-description">
                        <small><strong>✅ Sistema certificado</strong> según documentación oficial SAT v1.5</small>
                    </p>

                    <form id="descargaMasivaForm" class="efirma-form">
                        <div class="descarga-form-grid">
                            <!-- Columna izquierda: Campos principales -->
                            <div class="descarga-form-left">
                                <!-- Selector de RFC -->
                                <div class="form-group full-width">
                                    <label for="rfc_selected">RFC y Vencimiento</label>
                                    <select id="rfc_selected" name="rfc_selected" required>
                                        <option value="">Selecciona un RFC...</option>
                                        <option value="TODOS" style="font-weight: bold; background-color: #e3f2fd;">
                                            🔄 TODOS LOS RFCs - Consulta masiva
                                        </option>
                                        <?php foreach ($certificados as $cert): ?>
                                            <option value="<?php echo $cert['id']; ?>"
                                                data-rfc="<?php echo htmlspecialchars($cert['rfc']); ?>"
                                                data-vencimiento="<?php echo date('d/m/Y', strtotime($cert['valid_to'])); ?>">
                                                <?php echo htmlspecialchars($cert['rfc']); ?> -
                                                <?php echo htmlspecialchars($cert['legal_name'] ?? 'Sin nombre'); ?>
                                                (Vencimiento: <?php echo date('d/m/Y', strtotime($cert['valid_to'])); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-hint">
                                        💡 Selecciona "TODOS LOS RFCs" para crear solicitudes para ambos certificados simultáneamente
                                    </small>
                                </div>

                                <!-- Tipo de documento -->
                                <div class="form-group">
                                    <label for="tipo_documento">Tipo de documento</label>
                                    <select id="tipo_documento" name="tipo_documento" required>
                                        <option value="Emitidas">📤 Emitidas</option>
                                        <option value="Recibidas">📥 Recibidas</option>
                                        <option value="Ambos" style="font-weight: bold; background-color: #e8f5e8;">
                                            🔄 Ambos (Emitidas + Recibidas)
                                        </option>
                                    </select>
                                    <small class="form-hint">
                                        💡 "Ambos" creará 2 solicitudes separadas: una de Emitidas y otra de Recibidas
                                    </small>
                                </div>

                                <!-- Fechas -->
                                <div class="form-group">
                                    <label for="fecha_desde">Fecha desde</label>
                                    <input type="date" id="fecha_desde" name="fecha_desde"
                                        value="<?php echo $primer_dia_mes; ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="fecha_hasta">Fecha hasta</label>
                                    <input type="date" id="fecha_hasta" name="fecha_hasta"
                                        value="<?php echo $dia_actual; ?>" required>
                                </div>
                            </div>

                            <!-- Columna derecha: Botones -->
                            <div class="descarga-form-right">
                                <button type="submit" class="btn btn-primary">
                                    📥 SOLICITAR DESCARGA
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tabla de Solicitudes -->
                <div class="list-container">
                    <div class="list-header">
                        <h3>📊 Solicitudes de Descarga Masiva</h3>
                    </div>

                    <div class="efirma-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Acciones</th>
                                    <th>RFC Emisor</th>
                                    <th>Token SAT</th>
                                    <th>Estatus</th>
                                    <th>Última actualización</th>
                                    <th>Fecha inicial</th>
                                    <th>Fecha final</th>
                                    <th>Tipo</th>
                                    <th>Mensaje Verificación</th>
                                    <th>Paquetes</th>
                                    <th>Fecha solicitud</th>
                                </tr>
                            </thead>
                            <tbody id="tablaSolicitudes">
                                <tr>
                                    <td colspan="11" class="empty-state">
                                        <p>No hay solicitudes de descarga. Crea tu primera solicitud usando el formulario superior.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../../dashboard/dashboard.js"></script>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="descarga-xml.js"></script>
</body>

</html>