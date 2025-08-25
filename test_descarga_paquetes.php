<?php
date_default_timezone_set('America/Mexico_City');
require_once 'vendor/autoload.php';
require_once 'src/config/database.php';
require_once 'src/Services/SatDescargaMasivaService.php';

use App\Services\SatDescargaMasivaService;

echo "=== TEST DESCARGA PAQUETES SAT ===\n";

try {
    $pdo = getDatabase();

    // Buscar solicitud con paquetes disponibles (estatus 3 = terminada)
    $stmt = $pdo->query("
        SELECT h.*, c.rfc, c.certificate_path, c.key_path, c.password_plain
        FROM sat_download_history h
        INNER JOIN sat_fiel_certificates c ON h.certificate_id = c.id
        WHERE h.estatus_solicitud = '3'
        AND h.paquetes IS NOT NULL 
        AND h.paquetes != '[]'
        ORDER BY h.id DESC
        LIMIT 1
    ");

    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        echo "❌ No hay solicitudes terminadas con paquetes disponibles\n";
        echo "📋 Estado actual de solicitudes:\n";

        $stmt = $pdo->query("
            SELECT id, rfc_emisor, status, estatus_solicitud, 
                   CASE WHEN paquetes IS NULL OR paquetes = '[]' THEN 'Sin paquetes' ELSE 'Con paquetes' END as paquetes_estado
            FROM sat_download_history 
            ORDER BY id DESC
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "ID: {$row['id']} | RFC: {$row['rfc_emisor']} | Status: {$row['status']} | Estatus: {$row['estatus_solicitud']} | Paquetes: {$row['paquetes_estado']}\n";
        }
        exit;
    }

    echo "✅ Solicitud encontrada:\n";
    echo "Request ID: {$solicitud['request_id']}\n";
    echo "RFC: {$solicitud['rfc']}\n";
    echo "Status: {$solicitud['status']}\n";
    echo "Estatus SAT: {$solicitud['estatus_solicitud']}\n";

    $paquetes = json_decode($solicitud['paquetes'], true);
    echo "Paquetes disponibles: " . count($paquetes) . "\n";

    if (empty($paquetes)) {
        echo "❌ Array de paquetes está vacío\n";
        exit;
    }

    echo "📦 Paquetes a descargar:\n";
    foreach ($paquetes as $i => $paquete) {
        echo "  " . ($i + 1) . ". $paquete\n";
    }

    // Crear servicio SAT
    $certBaseDir = realpath('storage/fiel_certificates/');
    $certFile = basename($solicitud['certificate_path']);
    $keyFile = basename($solicitud['key_path']);
    $certPath = $certBaseDir . DIRECTORY_SEPARATOR . $certFile;
    $keyPath = $certBaseDir . DIRECTORY_SEPARATOR . $keyFile;

    echo "\n🔐 Creando servicio SAT...\n";
    $service = SatDescargaMasivaService::fromCertificateFiles(
        $certPath,
        $keyPath,
        $solicitud['password_plain']
    );

    // Crear directorio de descarga
    $tipo_documento = $solicitud['tipo_documento'] ?? 'Emitidas';
    $year = date('Y', strtotime($solicitud['date_from']));
    $month = date('m', strtotime($solicitud['date_from']));
    $downloadPath = realpath('storage/sat_downloads/') .
        DIRECTORY_SEPARATOR . $solicitud['rfc'] .
        DIRECTORY_SEPARATOR . strtoupper($tipo_documento) .
        DIRECTORY_SEPARATOR . $year .
        DIRECTORY_SEPARATOR . $month;

    echo "📁 Directorio descarga: $downloadPath\n";

    echo "\n🔄 Iniciando descarga real del SAT...\n";
    $resultado = $service->descargarPaquetes(
        $solicitud['request_id'],
        $paquetes,
        $downloadPath
    );

    if ($resultado['success']) {
        echo "✅ Descarga completada!\n";
        echo "Archivos descargados: {$resultado['total_files']}\n";

        foreach ($resultado['files'] as $file) {
            echo "  📄 {$file['filename']} ({$file['size']} bytes)\n";
        }

        // Actualizar base de datos
        $totalSize = array_sum(array_column($resultado['files'], 'size'));
        $stmt = $pdo->prepare("
            UPDATE sat_download_history 
            SET status = 'COMPLETED',
                download_path = ?,
                completed_at = NOW(),
                files_count = ?,
                total_size_bytes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $downloadPath,
            $resultado['total_files'],
            $totalSize,
            $solicitud['id']
        ]);

        echo "\n📊 Base de datos actualizada\n";
    } else {
        echo "❌ Error en descarga: {$resultado['message']}\n";
    }
} catch (Exception $e) {
    echo "💥 Error: " . $e->getMessage() . "\n";
    echo "📍 Trace: " . $e->getTraceAsString() . "\n";
}
