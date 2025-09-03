<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Asegúrate de que solo se acceda a este script a través de un método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Método no permitido
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// Validar que se haya proporcionado un UUID
if (!isset($_GET['uuid']) || empty($_GET['uuid'])) {
    http_response_code(400); // Solicitud incorrecta
    echo json_encode(['success' => false, 'message' => 'UUID no proporcionado.']);
    exit;
}

$uuid = $_GET['uuid'];

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

    // Preparar y ejecutar la consulta para obtener la ruta_xml
    $stmt = $pdo->prepare("SELECT ruta_xml FROM cfdi WHERE uuid = :uuid LIMIT 1");
    $stmt->execute([':uuid' => $uuid]);
    $result = $stmt->fetch();

    if ($result && !empty($result['ruta_xml'])) {
        $ruta_xml = $result['ruta_xml'];

        // Construir la ruta completa al archivo XML
        // Asumiendo que ruta_xml guarda la ruta relativa desde la raíz del proyecto o una base conocida
        // Ejemplo: si ruta_xml es 'storage/cfdi/2023/uuid.xml' y el proyecto está en c:/xampp/htdocs/SAC
        // La ruta completa sería c:/xampp/htdocs/SAC/storage/cfdi/2023/uuid.xml
        $base_path = dirname(__DIR__, 4); // Ajustado para que apunte a c:/xampp/htdocs/SAC
        $full_path = $base_path . '/' . $ruta_xml;

        // Asegurarse de que la ruta sea segura y el archivo exista
        if (file_exists($full_path) && is_file($full_path)) {
            // Forzar la descarga del archivo
            header('Content-Description: File Transfer');
            header('Content-Type: application/xml');
            header('Content-Disposition: attachment; filename="' . basename($full_path) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($full_path));
            readfile($full_path);
            exit;
        } else {
            http_response_code(404); // No encontrado
            echo json_encode(['success' => false, 'message' => 'Archivo XML no encontrado en la ruta especificada: ' . $full_path]);
            exit;
        }
    } else {
        http_response_code(404); // No encontrado
        echo json_encode(['success' => false, 'message' => 'Ruta XML no encontrada para el UUID proporcionado.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500); // Error interno del servidor
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500); // Error interno del servidor
    echo json_encode(['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()]);
    exit;
}
