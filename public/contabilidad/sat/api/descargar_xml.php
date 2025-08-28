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

// Buscar el archivo XML en el directorio de storage
$directorio_xmls = "../../../../storage/sat_downloads";

function buscarArchivoXML($directorio, $uuid)
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directorio),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
            $content = file_get_contents($file->getPathname());
            if (strpos($content, $uuid) !== false) {
                return $file->getPathname();
            }
        }
    }
    return null;
}

$archivo_xml = buscarArchivoXML($directorio_xmls, $uuid);

if (!$archivo_xml || !file_exists($archivo_xml)) {
    http_response_code(404);
    die('Archivo XML no encontrado');
}

// Obtener información del CFDI para el nombre del archivo
$sql = "SELECT serie, folio, fecha FROM cfdi WHERE uuid = :uuid";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uuid' => $uuid]);
$cfdi = $stmt->fetch();

// Generar nombre del archivo
$fecha = $cfdi ? date('Y-m-d', strtotime($cfdi['fecha'])) : date('Y-m-d');
$serie = $cfdi['serie'] ?? 'SIN_SERIE';
$folio = $cfdi['folio'] ?? 'SIN_FOLIO';
$nombre_archivo = "{$fecha}_{$serie}_{$folio}_{$uuid}.xml";

// Configurar headers para descarga
header('Content-Type: application/xml');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Content-Length: ' . filesize($archivo_xml));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Enviar archivo
readfile($archivo_xml);
exit;
