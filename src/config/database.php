<?php
// Configuración de base de datos

// Cargar variables de entorno desde .env
function loadEnv($path)
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Cargar .env desde la raíz del proyecto
loadEnv(__DIR__ . '/../../.env');

// Configuración de base de datos con valores por defecto
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'sac_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// Configuración adicional para SAT
define('SAT_ENVIRONMENT', $_ENV['SAT_ENVIRONMENT'] ?? 'testing'); // testing o production
define('SAT_CERTIFICATE_STORAGE', $_ENV['SAT_CERTIFICATE_STORAGE'] ?? __DIR__ . '/../../storage/fiel_certificates/');
define('SAT_DOWNLOAD_STORAGE', $_ENV['SAT_DOWNLOAD_STORAGE'] ?? __DIR__ . '/../../storage/sat_downloads/');

// URLs del SAT según el ambiente
if (SAT_ENVIRONMENT === 'production') {
    define('SAT_AUTH_URL', 'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/Autenticacion/Autenticacion.svc');
    define('SAT_SOLICITUD_URL', 'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/SolicitaDescargaService.svc');
    define('SAT_VERIFICACION_URL', 'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/VerificaSolicitudDescargaService.svc');
    define('SAT_DESCARGA_URL', 'https://cfdidescargamasiva.clouda.sat.gob.mx/DescargaMasivaService.svc');
} else {
    // URLs de testing/desarrollo
    define('SAT_AUTH_URL', 'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/Autenticacion/Autenticacion.svc');
    define('SAT_SOLICITUD_URL', 'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/SolicitaDescargaService.svc');
    define('SAT_VERIFICACION_URL', 'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/VerificaSolicitudDescargaService.svc');
    define('SAT_DESCARGA_URL', 'https://cfdidescargamasiva.clouda.sat.gob.mx/DescargaMasivaService.svc');
}

// Crear directorios necesarios si no existen
if (!file_exists(SAT_CERTIFICATE_STORAGE)) {
    mkdir(SAT_CERTIFICATE_STORAGE, 0755, true);
}

if (!file_exists(SAT_DOWNLOAD_STORAGE)) {
    mkdir(SAT_DOWNLOAD_STORAGE, 0755, true);
}

// Función para obtener conexión PDO
function getDatabase()
{
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Error de conexión a base de datos: " . $e->getMessage());
        throw new Exception("Error de conexión a la base de datos");
    }
}

// Función para registrar actividad - Sistema de auditoría completo
function logActivity($userId, $action, $description, $ipAddress = null, $module = null, $recordId = null)
{
    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, module, record_id, created_at, is_read)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0)
        ");

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $ipAddress = $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt->execute([$userId, $action, $description, $ipAddress, $userAgent, $module, $recordId]);
        return true;
    } catch (Exception $e) {
        error_log("Error al registrar actividad: " . $e->getMessage());
        return false;
    }
}

// Constantes para acciones de auditoría
define('LOG_LOGIN', 'LOGIN');
define('LOG_LOGOUT', 'LOGOUT');
define('LOG_CREATE', 'CREATE');
define('LOG_UPDATE', 'UPDATE');
define('LOG_DELETE', 'DELETE');
define('LOG_VIEW', 'VIEW');
define('LOG_DOWNLOAD', 'DOWNLOAD');
define('LOG_UPLOAD', 'UPLOAD');
define('LOG_EXPORT', 'EXPORT');
define('LOG_IMPORT', 'IMPORT');

// Constantes para módulos
define('MODULE_LOGIN', 'LOGIN');
define('MODULE_SAT', 'SAT');
define('MODULE_EFIRMA', 'E_FIRMA');
define('MODULE_XML_DOWNLOAD', 'XML_DOWNLOAD');
define('MODULE_CLIENTES', 'CLIENTES');
define('MODULE_RH', 'RH');
define('MODULE_SOLICITUDES', 'SOLICITUDES');
define('MODULE_HORARIOS', 'HORARIOS');
define('MODULE_IT', 'IT');
define('MODULE_USUARIOS', 'USUARIOS');
define('MODULE_BURO_CREDITO', 'BURO_CREDITO');
define('MODULE_RIBC', 'RIBC');
define('MODULE_DASHBOARD', 'DASHBOARD');

// Función helper para log específicos de cada módulo
function logUserActivity($action, $description, $module = null, $recordId = null)
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    return logActivity($_SESSION['user_id'], $action, $description, null, $module, $recordId);
}
