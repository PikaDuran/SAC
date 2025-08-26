<?php
// Script para crear solicitudes de prueba RECIBIDAS y FOLIO
require_once 'vendor/autoload.php';
require_once 'src/config/database.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTime;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RequestType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RfcMatch;
use PhpCfdi\SatWsDescargaMasiva\Shared\RfcOnBehalf;
use PhpCfdi\SatWsDescargaMasiva\Shared\Uuid;

echo "=== CREANDO SOLICITUDES DE PRUEBA: RECIBIDAS Y FOLIO ===\n\n";

// Fechas de prueba (últimos 5 días para procesamiento más rápido)
$fechaInicial = date('Y-m-d', strtotime('-5 days'));
$fechaFinal = date('Y-m-d', strtotime('-1 day'));

echo "📅 Fechas de prueba:\n";
echo "Desde: $fechaInicial\n";
echo "Hasta: $fechaFinal\n\n";

try {
    $pdo = getDatabase();

    // Obtener certificado activo
    $stmt = $pdo->prepare("SELECT id, rfc, certificate_path, key_path, password_plain FROM sat_fiel_certificates WHERE rfc = 'BFM170822P38' AND is_active = 1");
    $stmt->execute();
    $certificado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$certificado) {
        throw new Exception("No se encontró el certificado para BFM170822P38");
    }

    echo "🔐 Certificado encontrado: {$certificado['rfc']}\n";

    // Configurar FIEL
    $certBaseDir = realpath(__DIR__ . '/storage/fiel_certificates/');
    $certFile = basename($certificado['certificate_path']);
    $keyFile = basename($certificado['key_path']);
    $certPath = $certBaseDir . DIRECTORY_SEPARATOR . $certFile;
    $keyPath = $certBaseDir . DIRECTORY_SEPARATOR . $keyFile;

    $fiel = Fiel::create(
        file_get_contents($certPath),
        file_get_contents($keyPath),
        $certificado['password_plain']
    );

    if (!$fiel->isValid()) {
        throw new Exception('El certificado FIEL no es válido');
    }

    $requestBuilder = new FielRequestBuilder($fiel);
    $webClient = new GuzzleWebClient();
    $service = new Service($requestBuilder, $webClient);

    // Crear períodos de fecha
    $period = DateTimePeriod::create(
        DateTime::create($fechaInicial . ' 00:00:00'),
        DateTime::create($fechaFinal . ' 23:59:59')
    );

    echo "\n📥 CREANDO SOLICITUD DE RECIBIDAS...\n";

    // 1. SOLICITUD RECIBIDAS
    $queryRecibidas = QueryParameters::create($period)
        ->withDownloadType(DownloadType::received()) // CFDIs recibidos
        ->withRequestType(RequestType::metadata())
        ->withRfcOnBehalf(RfcOnBehalf::create($certificado['rfc']));

    $requestRecibidas = $service->query($queryRecibidas);

    if ($requestRecibidas->getStatus()->isAccepted()) {
        $requestIdRecibidas = $requestRecibidas->getRequestId();
        echo "✅ Solicitud RECIBIDAS creada exitosamente\n";
        echo "   Request ID: $requestIdRecibidas\n";

        // Guardar en base de datos
        $stmt = $pdo->prepare("
            INSERT INTO sat_download_history (
                certificate_id, request_type, date_from, date_to, rfc_emisor, 
                request_id, status, tipo_documento, fecha_solicitud, requested_by
            ) VALUES (?, 'CFDI', ?, ?, ?, ?, 'REQUESTED', 'Recibidas', NOW(), 1)
        ");
        $stmt->execute([
            $certificado['id'],
            $fechaInicial,
            $fechaFinal,
            $certificado['rfc'],
            $requestIdRecibidas
        ]);
        echo "   💾 Guardado en BD con ID: " . $pdo->lastInsertId() . "\n\n";
    } else {
        echo "❌ Error en solicitud RECIBIDAS: " . $requestRecibidas->getStatus()->getMessage() . "\n\n";
    }

    echo "📋 CREANDO SOLICITUD POR FOLIO (UUID)...\n";

    // 2. SOLICITUD POR FOLIO/UUID
    // Usaremos un UUID de ejemplo - en producción sería un UUID real de un CFDI específico
    $uuidEjemplo = '12345678-1234-5678-9ABC-123456789012';

    $queryFolio = QueryParameters::create($period)
        ->withDownloadType(DownloadType::issued()) // CFDIs emitidos
        ->withRequestType(RequestType::metadata())
        ->withRfcOnBehalf(RfcOnBehalf::create($certificado['rfc']))
        ->withUuid(Uuid::create($uuidEjemplo));

    $requestFolio = $service->query($queryFolio);

    if ($requestFolio->getStatus()->isAccepted()) {
        $requestIdFolio = $requestFolio->getRequestId();
        echo "✅ Solicitud FOLIO creada exitosamente\n";
        echo "   Request ID: $requestIdFolio\n";
        echo "   UUID buscado: $uuidEjemplo\n";

        // Guardar en base de datos
        $stmt = $pdo->prepare("
            INSERT INTO sat_download_history (
                certificate_id, request_type, date_from, date_to, rfc_emisor, 
                request_id, status, tipo_documento, fecha_solicitud, requested_by
            ) VALUES (?, 'CFDI', ?, ?, ?, ?, 'REQUESTED', 'Folio', NOW(), 1)
        ");
        $stmt->execute([
            $certificado['id'],
            $fechaInicial,
            $fechaFinal,
            $certificado['rfc'],
            $requestIdFolio
        ]);
        echo "   💾 Guardado en BD con ID: " . $pdo->lastInsertId() . "\n\n";
    } else {
        echo "❌ Error en solicitud FOLIO: " . $requestFolio->getStatus()->getMessage() . "\n\n";
    }

    // Mostrar resumen
    echo "📊 RESUMEN DE TODAS LAS SOLICITUDES:\n";
    echo "=====================================\n";

    $stmt = $pdo->query("
        SELECT id, LEFT(request_id, 8) as req_id, tipo_documento, status, 
               DATE(fecha_solicitud) as fecha, 
               CONCAT(DATE(date_from), ' a ', DATE(date_to)) as periodo
        FROM sat_download_history 
        ORDER BY id DESC 
        LIMIT 10
    ");

    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    printf("%-3s | %-8s | %-9s | %-9s | %-10s | %s\n", 'ID', 'REQ_ID', 'TIPO', 'STATUS', 'FECHA', 'PERIODO');
    echo str_repeat('-', 80) . "\n";

    foreach ($solicitudes as $solicitud) {
        printf(
            "%-3s | %-8s | %-9s | %-9s | %-10s | %s\n",
            $solicitud['id'],
            $solicitud['req_id'],
            $solicitud['tipo_documento'],
            $solicitud['status'],
            $solicitud['fecha'],
            $solicitud['periodo']
        );
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    if ($e->getPrevious()) {
        echo "Error anterior: " . $e->getPrevious()->getMessage() . "\n";
    }
}
