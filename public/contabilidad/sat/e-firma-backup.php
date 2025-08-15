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
                    try {
                        $pdo = getDatabase();

                        // Crear directorio permanente para archivos
                        $permanentDir = '../../../storage/fiel_certificates/';
                        if (!file_exists($permanentDir)) {
                            mkdir($permanentDir, 0755, true);
                        }

                        // Generar nombres únicos para archivos permanentes
                        $certPermanentPath = $permanentDir . $rfc . '_' . time() . '.cer';
                        $keyPermanentPath = $permanentDir . $rfc . '_' . time() . '.key';

                        // Mover archivos a ubicación permanente
                        copy($certTempPath, $certPermanentPath);
                        copy($keyTempPath, $keyPermanentPath);

                        // Insertar en base de datos
                        $stmt = $pdo->prepare("
                            INSERT INTO sat_fiel_certificates 
                            (rfc, legal_name, certificate_serial, certificate_path, key_path, 
                             password_hash, valid_from, valid_to, created_by, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");

                        $stmt->execute([
                            $validationResult['data']['rfc'],
                            $validationResult['data']['legal_name'] ?? '',
                            $validationResult['data']['certificate_serial'],
                            $certPermanentPath,
                            $keyPermanentPath,
                            password_hash($password, PASSWORD_DEFAULT),
                            $validationResult['data']['certificate_valid_from'],
                            $validationResult['data']['certificate_valid_to'],
                            $_SESSION['user_id']
                        ]);

                        // Registrar actividad
                        logActivity(
                            $_SESSION['user_id'],
                            'FIEL_REGISTERED',
                            "Registró e.Firma para RFC: " . $validationResult['data']['rfc']
                        );

                        $message = 'e.Firma registrada exitosamente. RFC: ' . $validationResult['data']['rfc'];
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Error al guardar en base de datos: ' . $e->getMessage();
                        $messageType = 'error';
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
        SELECT sf.*, u.username as created_by_name 
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
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="e-firma.css">
</head>

<body>
    <div class="main-container">
        <header class="header">
            <div class="header-content">
                <h1>Gestión de e.Firma</h1>
                <div class="user-info">
                    <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Usuario'); ?></span>
                    <a href="../../auth/logout.php" class="logout-link">Cerrar Sesión</a>
                </div>
            </div>
        </header>

        <div class="content">
            <div class="content-header">
                <nav class="breadcrumb">
                    <a href="../../dashboard/dashboard.php">Dashboard</a> >
                    <a href="#">Contabilidad</a> >
                    <a href="#">SAT</a> >
                    <span>e.Firma</span>
                </nav>
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
                                    <tr>
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

    <script src="e-firma.js"></script>
</body>

</html>