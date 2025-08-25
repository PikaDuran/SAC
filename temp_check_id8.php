<?php
require_once 'vendor/autoload.php';
require_once 'src/config/database.php';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar la solicitud ID 8
    $stmt = $pdo->prepare('SELECT * FROM sat_download_history WHERE id = ?');
    $stmt->execute([8]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($solicitud) {
        echo "=== SOLICITUD ID 8 ===" . PHP_EOL;
        echo "Request ID: " . $solicitud['request_id'] . PHP_EOL;
        echo "RFC Emisor: " . $solicitud['rfc_emisor'] . PHP_EOL;
        echo "Status: " . $solicitud['status'] . PHP_EOL;
        echo "Estatus Solicitud: " . $solicitud['estatus_solicitud'] . PHP_EOL;
        echo "Fecha Inicial: " . $solicitud['fecha_inicial'] . PHP_EOL;
        echo "Fecha Final: " . $solicitud['fecha_final'] . PHP_EOL;
        echo "Tipo: " . $solicitud['tipo'] . PHP_EOL;
        echo "Created: " . $solicitud['created_at'] . PHP_EOL;
        echo "Updated: " . $solicitud['updated_at'] . PHP_EOL;
        
        // Buscar paquetes relacionados
        $stmt2 = $pdo->prepare('SELECT * FROM sat_packages WHERE request_id = ?');
        $stmt2->execute([$solicitud['request_id']]);
        $paquetes = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        echo PHP_EOL . "=== PAQUETES DISPONIBLES ===" . PHP_EOL;
        if (empty($paquetes)) {
            echo "No hay paquetes disponibles aún." . PHP_EOL;
        } else {
            foreach ($paquetes as $paquete) {
                echo "Package ID: " . $paquete['package_id'] . PHP_EOL;
                echo "Status: " . $paquete['status'] . PHP_EOL;
                echo "Downloaded: " . ($paquete['downloaded'] ? 'Sí' : 'No') . PHP_EOL;
                echo "Created: " . $paquete['created_at'] . PHP_EOL;
                echo "---" . PHP_EOL;
            }
        }
        
        // También verificar si podemos consultar el estado en el SAT
        echo PHP_EOL . "=== VERIFICACIÓN EN SAT ===" . PHP_EOL;
        echo "Request ID para verificar: " . $solicitud['request_id'] . PHP_EOL;
        
    } else {
        echo "No se encontró la solicitud con ID 8" . PHP_EOL;
        
        // Mostrar las últimas solicitudes disponibles
        echo PHP_EOL . "=== ÚLTIMAS SOLICITUDES DISPONIBLES ===" . PHP_EOL;
        $stmt = $pdo->query('SELECT id, request_id, rfc_emisor, status, estatus_solicitud FROM sat_download_history ORDER BY id DESC LIMIT 10');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "ID: " . $row['id'] . " | Request: " . substr($row['request_id'], 0, 8) . "... | RFC: " . $row['rfc_emisor'] . " | Status: " . $row['status'] . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
?>
