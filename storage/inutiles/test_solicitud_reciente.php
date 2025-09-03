<?php
// Script para crear solicitud de prueba con fechas muy recientes
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
use PhpCfdi\SatWsDescargaMasiva\Shared\RfcOnBehalf;

echo "=== SOLICITUD DE PRUEBA CON FECHAS RECIENTES ===\n\n";

// Fechas muy recientes (ayer y hoy)
$fechaInicial = date('Y-m-d', strtotime('-1 day'));
$fechaFinal = date('Y-m-d');

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

    // Crear período de fecha
    $period = DateTimePeriod::create(
        DateTime::create($fechaInicial . ' 00:00:00'),
        DateTime::create($fechaFinal . ' 23:59:59')
    );

    echo "\n📤 CREANDO SOLICITUD DE EMITIDAS (FECHAS RECIENTES)...\n";

    // SOLICITUD EMITIDAS RECIENTES
    $queryEmitidas = QueryParameters::create($period)
        ->withDownloadType(DownloadType::issued()) // CFDIs emitidos
        ->withRequestType(RequestType::metadata())
        ->withRfcOnBehalf(RfcOnBehalf::create($certificado['rfc']));

    $requestEmitidas = $service->query($queryEmitidas);

    if ($requestEmitidas->getStatus()->isAccepted()) {
        $requestIdEmitidas = $requestEmitidas->getRequestId();
        echo "✅ Solicitud EMITIDAS (recientes) creada exitosamente\n";
        echo "   Request ID: $requestIdEmitidas\n";

        // Guardar en base de datos
        $stmt = $pdo->prepare("
            INSERT INTO sat_download_history (
                certificate_id, request_type, date_from, date_to, rfc_emisor, 
                request_id, status, tipo_documento, fecha_solicitud, requested_by
            ) VALUES (?, 'CFDI', ?, ?, ?, ?, 'REQUESTED', 'Emitidas', NOW(), 1)
        ");
        $stmt->execute([
            $certificado['id'],
            $fechaInicial,
            $fechaFinal,
            $certificado['rfc'],
            $requestIdEmitidas
        ]);
        echo "   💾 Guardado en BD con ID: " . $pdo->lastInsertId() . "\n\n";

        // Verificar inmediatamente si hay paquetes
        echo "🔍 Verificando inmediatamente si hay paquetes disponibles...\n";
        $resultVerify = $service->verify($requestIdEmitidas);

        echo "Status: " . $resultVerify->getStatus()->getMessage() . "\n";
        $packages = $resultVerify->getPackagesIds();
        echo "Paquetes disponibles: " . count($packages) . "\n";

        if (count($packages) > 0) {
            echo "🎉 ¡Hay paquetes disponibles inmediatamente!\n";
            foreach ($packages as $i => $packageId) {
                echo "   Paquete " . ($i + 1) . ": $packageId\n";
            }
        } else {
            echo "⏳ No hay paquetes aún. El SAT está procesando...\n";
        }
    } else {
        echo "❌ Error en solicitud EMITIDAS: " . $requestEmitidas->getStatus()->getMessage() . "\n\n";
    }

    // Mostrar resumen actualizado
    echo "\n📊 RESUMEN DE TODAS LAS SOLICITUDES:\n";
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
}
