<?php
// Forzar zona horaria global CDMX
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/Services/SatDescargaMasivaService.php';
header('Content-Type: application/json');



// Permitir recibir id (autoincremental) o request_id (token SAT)
$id = $_POST['id'] ?? $_GET['id'] ?? null;
$requestId = $_POST['request_id'] ?? $_GET['request_id'] ?? null;

// Si llega id, buscar el request_id correspondiente
if ($id && !$requestId) {
    $pdo = getDatabase();
    $stmt = $pdo->prepare("SELECT request_id FROM sat_download_history WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !$row['request_id']) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el request_id para el id proporcionado']);
        exit;
    }
    $requestId = $row['request_id'];
}

if (!$requestId) {
    echo json_encode(['success' => false, 'message' => 'Falta el parámetro id o request_id']);
    exit;
}

// Si se solicita solo la consulta de datos actuales (sin verificación SAT)
if (isset($_GET['only_select']) && $_GET['only_select'] == '1') {
    $pdo = getDatabase();
    // Buscar por id si se recibe, o por request_id si no
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM sat_download_history WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM sat_download_history WHERE request_id = ? LIMIT 1");
        $stmt->execute([$requestId]);
    }
    $rowAfter = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode([
        'success' => true,
        'row_after_update' => $rowAfter
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    $pdo = getDatabase();
    // Buscar el registro por id si se recibe, o por request_id si no
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM sat_download_history WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM sat_download_history WHERE request_id = ? LIMIT 1");
        $stmt->execute([$requestId]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el registro para el id o request_id']);
        exit;
    }
    $certificateId = $row['certificate_id'];
    $requestId = $row['request_id'];

    // Buscar los datos del certificado
    $stmt = $pdo->prepare("SELECT rfc, certificate_path, key_path, password_plain FROM sat_fiel_certificates WHERE id = ? LIMIT 1");
    $stmt->execute([$certificateId]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cert) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el certificado FIEL']);
        exit;
    }

    // Construir rutas absolutas si es necesario
    $certBaseDir = realpath(__DIR__ . '/../../storage/fiel_certificates/');
    $certFile = basename($cert['certificate_path']);
    $keyFile = basename($cert['key_path']);
    $certPath = $certBaseDir . DIRECTORY_SEPARATOR . $certFile;
    $keyPath = $certBaseDir . DIRECTORY_SEPARATOR . $keyFile;

    // --- INTEGRACIÓN DE LÓGICA ROBUSTA DE VERIFICACIÓN REAL SAT ---
    // (Las clases use deben estar al inicio del archivo, aquí solo referencia)
    $fiel = \PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel::create(
        file_get_contents($certPath),
        file_get_contents($keyPath),
        $cert['password_plain']
    );
    if (!$fiel->isValid()) {
        throw new Exception('El certificado FIEL no es válido o está vencido');
    }
    $requestBuilder = new \PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder($fiel);
    $webClient = new \PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient();
    $service = new \PhpCfdi\SatWsDescargaMasiva\Service($requestBuilder, $webClient);
    $result = $service->verify($requestId);

    // Helper para extraer valor real de objetos tipo ValueObject
    function extractValue($obj)
    {
        if (is_object($obj)) {
            if (method_exists($obj, 'value')) {
                return $obj->value();
            } elseif (method_exists($obj, '__toString')) {
                return (string)$obj;
            } elseif (method_exists($obj, 'getValue')) {
                return $obj->getValue();
            } else {
                return json_encode($obj); // fallback
            }
        }
        return $obj;
    }

    // Extraer valores para actualizar base de datos
    $statusObj = $result ? $result->getStatus() : null;
    $statusNombreSAT = null;
    if ($statusObj) {
        if (method_exists($statusObj, 'getMessage')) {
            $statusNombreSAT = $statusObj->getMessage();
        } elseif (property_exists($statusObj, 'message')) {
            $statusNombreSAT = $statusObj->message;
        } else {
            $statusNombreSAT = extractValue($statusObj);
        }
    }

    // Obtener status request (más detallado)
    $estatusSolicitudObj = $result ? $result->getStatusRequest() : null;
    $estatusSolicitudNum = null;
    $mensajeVerificacion = null;

    if ($estatusSolicitudObj) {
        // Obtener el index (número)
        if (method_exists($estatusSolicitudObj, 'value') && is_callable([$estatusSolicitudObj, 'value'])) {
            $estatusSolicitudNum = $estatusSolicitudObj->value();
        } elseif (property_exists($estatusSolicitudObj, 'index')) {
            $estatusSolicitudNum = $estatusSolicitudObj->index;
        }

        // Obtener el mensaje descriptivo del value array
        $reflection = new ReflectionClass($estatusSolicitudObj);
        if ($reflection->hasProperty('value')) {
            $valueProperty = $reflection->getProperty('value');
            $valueProperty->setAccessible(true);
            $valueArray = $valueProperty->getValue($estatusSolicitudObj);

            if (is_array($valueArray) && isset($valueArray['message'])) {
                $mensajeVerificacion = $valueArray['message'];
            }
        }
    }

    // Mapeo de status SAT a ENUM válido basado en el status request
    $statusEnum = [
        1 => 'REQUESTED',      // Aceptada
        2 => 'PROCESSING',     // En proceso
        3 => 'COMPLETED',      // Terminada  
        4 => 'ERROR',          // Error
        5 => 'REJECTED',       // Rechazada
        6 => 'EXPIRED',        // Vencida
    ];

    $statusNombre = isset($statusEnum[$estatusSolicitudNum]) ? $statusEnum[$estatusSolicitudNum] : 'REQUESTED';

    if (!$mensajeVerificacion || $mensajeVerificacion === '' || $mensajeVerificacion === null) {
        $mensajeVerificacion = $statusNombreSAT ?: 'Estado SAT sin mensaje';
    }

    $paquetes = $result ? json_encode($result->getPackagesIds()) : '[]';
    $now = date('Y-m-d H:i:s');


    // DEBUG: Forzar guardado correcto de status y mensaje_verificacion
    $updateSql = "UPDATE sat_download_history SET status = :status, estatus_solicitud = :estatus, paquetes = :paquetes, mensaje_verificacion = :mensaje, ultima_actualizacion = :now WHERE request_id = :request_id";
    $stmt = $pdo->prepare($updateSql);
    $stmt->bindValue(':status', $statusNombre, PDO::PARAM_STR);
    $stmt->bindValue(':estatus', $estatusSolicitudNum, PDO::PARAM_STR);
    $stmt->bindValue(':paquetes', $paquetes, PDO::PARAM_STR);
    $stmt->bindValue(':mensaje', $mensajeVerificacion, PDO::PARAM_STR);
    $stmt->bindValue(':now', $now, PDO::PARAM_STR);
    $stmt->bindValue(':request_id', $requestId, PDO::PARAM_STR);
    $stmt->execute();
    $rowsAffected = $stmt->rowCount();

    // Obtener todos los datos actuales de la fila después del update
    $stmt = $pdo->prepare("SELECT * FROM sat_download_history WHERE request_id = ? LIMIT 1");
    $stmt->execute([$requestId]);
    $rowAfter = $stmt->fetch(PDO::FETCH_ASSOC);

    // Responder con el JSON profesional, filas afectadas y datos actuales completos
    echo json_encode([
        'success' => true,
        'message' => 'Estado verificado con el SAT',
        'rows_affected' => $rowsAffected,
        'data' => [
            'status' => $statusNombre,
            'estatus_solicitud' => $estatusSolicitudNum,
            'paquetes' => $result->getPackagesIds(),
            'mensaje_verificacion' => $mensajeVerificacion,
        ],
        'row_after_update' => $rowAfter
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en verificación: ' . $e->getMessage()]);
}
