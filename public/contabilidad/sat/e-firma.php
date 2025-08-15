<?php
session_start();

// Verificar si hay sesión activa
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/login.html');
    exit;
}

// Verificar timeout de sesión
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1500) {
    session_destroy();
    header('Location: ../../login/login.html');
    exit;
}

$_SESSION['last_activity'] = time();

require_once '../../../vendor/autoload.php';
require_once '../../../src/config/database.php';
require_once '../../../src/Services/SatAuthenticationService.php';

use App\Services\SatAuthenticationService;


$message = '';
$messageType = '';

// Procesar formulario
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'validate_fiel') {
    $rfc = trim($_POST['rfc'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validar archivos subidos
    if (!isset($_FILES['cert_file']) || !isset($_FILES['key_file'])) {
        $message = 'Debe seleccionar ambos archivos (.cer y .key)';
        $messageType = 'error';
    } elseif (empty($rfc) || empty($password)) {
        $message = 'Todos los campos son obligatorios';
        $messageType = 'error';
    } else {
        // Crear directorio temporal para archivos
        $tempDir = '../../../temp/';
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Procesar archivos subidos
        $certFile = $_FILES['cert_file'];
        $keyFile = $_FILES['key_file'];

        // Validar extensiones
        $certExt = strtolower(pathinfo($certFile['name'], PATHINFO_EXTENSION));
        $keyExt = strtolower(pathinfo($keyFile['name'], PATHINFO_EXTENSION));

        if ($certExt !== 'cer' || $keyExt !== 'key') {
            $message = 'Tipos de archivo incorrectos. Debe subir un archivo .cer y un archivo .key';
            $messageType = 'error';
        } else {
            // Mover archivos a directorio temporal
            $certTempPath = $tempDir . uniqid() . '.cer';
            $keyTempPath = $tempDir . uniqid() . '.key';

            if (
                move_uploaded_file($certFile['tmp_name'], $certTempPath) &&
                move_uploaded_file($keyFile['tmp_name'], $keyTempPath)
            ) {

                // Validar con el SAT
                $satService = new SatAuthenticationService();
                $validationResult = $satService->validateFiel($rfc, $certTempPath, $keyTempPath, $password);

                if ($validationResult['success']) {
                    // Validación exitosa - guardar en base de datos
                    $pdo = getDatabase();
                    $permanentDir = '../../../storage/fiel_certificates/';
                    if (!file_exists($permanentDir)) {
                        mkdir($permanentDir, 0755, true);
                    }
                    $certPermanentName = $rfc . '_' . time() . '.cer';
                    $keyPermanentName = $rfc . '_' . time() . '.key';
                    $certPermanentPath = $permanentDir . $certPermanentName;
                    $keyPermanentPath = $permanentDir . $keyPermanentName;
                    copy($certTempPath, $certPermanentPath);
                    copy($keyTempPath, $keyPermanentPath);
                    // Guardar solo la ruta relativa limpia
                    $certDbPath = 'storage/fiel_certificates/' . $certPermanentName;
                    $keyDbPath = 'storage/fiel_certificates/' . $keyPermanentName;
                    require_once dirname(__DIR__, 3) . '/src/Services/CertificatePasswordService.php';
                    $passwordService = new App\Services\CertificatePasswordService();
                    $encryptedPassword = $passwordService->encrypt($password);
                    $stmt = $pdo->prepare("
                        INSERT INTO sat_fiel_certificates 
                        (rfc, legal_name, certificate_serial, certificate_path, key_path, 
                         password_hash, password_plain, valid_from, valid_to, created_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $validationResult['data']['rfc'],
                        $validationResult['data']['legal_name'] ?? '',
                        $validationResult['data']['certificate_serial'],
                        $certDbPath,
                        $keyDbPath,
                        $encryptedPassword,
                        $password,
                        $validationResult['data']['certificate_valid_from'],
                        $validationResult['data']['certificate_valid_to'],
                        $_SESSION['user_id']
                    ]);
                    // Forzar log de actividad, y si falla mostrar error visible
                    $logOk = logUserActivity(
                        LOG_CREATE,
                        "Registró e.Firma para RFC: " . $validationResult['data']['rfc'],
                        MODULE_EFIRMA,
                        $pdo->lastInsertId()
                    );
                    if (!$logOk) {
                        $message = 'e.Firma registrada, pero NO se pudo registrar la actividad para la campana.';
                        $messageType = 'error';
                    } else {
                        $message = 'e.Firma registrada exitosamente. RFC: ' . $validationResult['data']['rfc'];
                        $messageType = 'success';
                    }
                } else {
                    $message = $validationResult['message'];
                    $messageType = 'error';
                }

                // Limpiar archivos temporales
                if (file_exists($certTempPath)) unlink($certTempPath);
                if (file_exists($keyTempPath)) unlink($keyTempPath);
            } else {
                $message = 'Error al subir los archivos';
                $messageType = 'error';
            }
        }
    }
}

// Obtener lista de e.Firmas registradas
try {
    $pdo = getDatabase();

    $stmt = $pdo->query("
        SELECT sf.*, u.usuario as created_by_name 
        FROM sat_fiel_certificates sf 
        LEFT JOIN usuarios u ON sf.created_by = u.id 
        ORDER BY sf.created_at DESC
    ");
    $fielCertificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $fielCertificates = [];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de e.Firma - SAC</title>
    <link rel="stylesheet" href="../../dashboard/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="e-firma.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../../src/views/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../../../src/views/header.php'; ?>
            <div class="content">
                <div class="toolbar">
                    <h1>Gestión de e.Firma</h1>
                    <div class="toolbar-actions">
                        <button class="btn-secondary" onclick="showHelp()">❓ Ayuda</button>
                        <!-- Modal de Ayuda -->
                        <div id="helpModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:9999; align-items:center; justify-content:center;">
                            <div style="background:#fff; border-radius:8px; max-width:420px; margin:auto; padding:32px 24px; box-shadow:0 4px 32px rgba(0,0,0,0.18); position:relative;">
                                <h2 style="margin-top:0;">Ayuda - Gestión de e.Firma</h2>
                                <ul style="padding-left:18px;">
                                    <li>Ingrese el <b>RFC</b> del contribuyente.</li>
                                    <li>Ingrese la <b>contraseña</b> de la llave privada (.key).</li>
                                    <li>Seleccione los archivos <b>.CER</b> y <b>.KEY</b> correspondientes.</li>
                                    <li>Presione <b>Validar y Agregar</b> para verificar la e.Firma con el SAT y guardarla.</li>
                                    <li>En la tabla inferior puede ver, editar o eliminar e.Firmas registradas.</li>
                                </ul>
                                <button onclick="closeHelp()" style="margin-top:18px; background:#0969da; color:#fff; border:none; border-radius:6px; padding:8px 18px; font-size:15px; cursor:pointer;">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <h2>Agregar nueva e.Firma</h2>
                    <p class="form-description">
                        Registre una nueva e.Firma (FIEL) validándola directamente con el SAT antes de guardar.
                    </p>

                    <form method="POST" enctype="multipart/form-data" class="efirma-form">
                        <input type="hidden" name="action" value="validate_fiel">

                        <div class="form-group">
                            <label for="rfc">RFC</label>
                            <input type="text" id="rfc" name="rfc" required
                                pattern="[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}"
                                placeholder="Ej: ABC123456ABC"
                                title="Formato válido de RFC">
                        </div>

                        <div class="form-group">
                            <label for="password">Contraseña FIEL</label>
                            <input type="password" id="password" name="password" required
                                placeholder="Contraseña de la llave privada">
                        </div>

                        <div class="form-group">
                            <label>Archivos FIEL</label>
                            <div class="file-upload-container">
                                <div class="file-upload">
                                    <label for="cert_file" class="file-upload-label">
                                        <span class="file-icon">📄</span>
                                        <span class="file-text">Seleccionar archivo .CER</span>
                                    </label>
                                    <input type="file" id="cert_file" name="cert_file" accept=".cer" required>
                                </div>

                                <div class="file-upload">
                                    <label for="key_file" class="file-upload-label">
                                        <span class="file-icon">🔑</span>
                                        <span class="file-text">Seleccionar archivo .KEY</span>
                                    </label>
                                    <input type="file" id="key_file" name="key_file" accept=".key" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Validar y Agregar</button>
                        </div>
                    </form>
                </div>

                <!-- Lista de e.Firmas registradas -->
                <div class="list-container">
                    <div class="list-header">
                        <h3>e.Firmas Registradas</h3>
                    </div>

                    <?php if (!empty($fielCertificates)): ?>
                        <div class="efirma-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>RFC</th>
                                        <th>Fecha de Creación</th>
                                        <th>Válido Hasta</th>
                                        <th>Registrado Por</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fielCertificates as $cert): ?>
                                        <tr data-cert-id="<?php echo $cert['id']; ?>">
                                            <td><?php echo htmlspecialchars($cert['rfc']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($cert['created_at'])); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($cert['valid_to'])); ?></td>
                                            <td><?php echo htmlspecialchars($cert['created_by_name']); ?></td>
                                            <td>
                                                <button class="btn-icon" onclick="editCert(<?php echo $cert['id']; ?>)">✏️</button>
                                                <button class="btn-icon" onclick="deleteCert(<?php echo $cert['id']; ?>)">🗑️</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No hay e.Firmas registradas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
    </div>

    <script src="../../dashboard/dashboard.js"></script>
    <script src="e-firma.js"></script>
    <script>
        function showHelp() {
            document.getElementById('helpModal').style.display = 'flex';
        }

        function closeHelp() {
            document.getElementById('helpModal').style.display = 'none';
        }
    </script>
</body>

</html>