<?php
// Test de nueva solicitud SAT con fechas válidas
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

echo "=== NUEVA SOLICITUD SAT CON FECHAS VÁLIDAS ===\n\n";

try {
    $pdo = getDatabase();
    $rfc = 'BFM170822P38';

    // FECHAS VÁLIDAS: Últimos 30 días
    $fechaFinal = new \DateTime('2025-08-24'); // Ayer
    $fechaInicial = new \DateTime('2025-08-01'); // Hace 23 días

    echo "📅 FECHAS DE SOLICITUD:\n";
    echo "Fecha inicial: " . $fechaInicial->format('Y-m-d') . "\n";
    echo "Fecha final: " . $fechaFinal->format('Y-m-d') . "\n";
    echo "Días de diferencia: " . $fechaInicial->diff($fechaFinal)->days . "\n\n";

    // Buscar certificado
    $stmt = $pdo->prepare("SELECT * FROM sat_fiel_certificates WHERE rfc = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$rfc]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cert) {
        echo "❌ No se encontró certificado activo para RFC: $rfc\n";
        exit;
    }

    echo "🔐 CERTIFICADO ENCONTRADO:\n";
    echo "RFC: " . $cert['rfc'] . "\n";
    echo "Expira: " . $cert['expires_at'] . "\n\n";

    // Cargar certificado
    $certPath = __DIR__ . '/storage/fiel_certificates/' . basename($cert['certificate_path']);
    $keyPath = __DIR__ . '/storage/fiel_certificates/' . basename($cert['key_path']);

    $fiel = Fiel::create(
        file_get_contents($certPath),
        file_get_contents($keyPath),
        $cert['password_plain']
    );

    if (!$fiel->isValid()) {
        echo "❌ FIEL no válida\n";
        exit;
    }

    echo "✅ FIEL válida\n\n";

    // Crear servicio
    $requestBuilder = new FielRequestBuilder($fiel);
    $webClient = new GuzzleWebClient();
    $service = new Service($requestBuilder, $webClient);

    // Parámetros de solicitud
    $satFechaInicial = DateTime::create($fechaInicial->format('Y-m-d H:i:s'));
    $satFechaFinal = DateTime::create($fechaFinal->format('Y-m-d H:i:s'));

    $parameters = QueryParameters::create(
        DateTimePeriod::create($satFechaInicial, $satFechaFinal),
        DownloadType::issued(), // Emitidas 
        RequestType::xml()      // XML
    );

    echo "🌐 ENVIANDO SOLICITUD AL SAT...\n";
    echo "Tipo: CFDIs Emitidos\n";
    echo "RFC: $rfc\n\n";

    // SOLICITUD REAL AL SAT
    $queryResult = $service->query($parameters);

    echo "📊 RESPUESTA DEL SAT:\n";
    echo "===================\n";
    echo "Status: " . $queryResult->getStatus()->getMessage() . "\n";
    echo "Request ID: " . $queryResult->getRequestId() . "\n";

    if ($queryResult->getStatus()->isAccepted()) {
        echo "✅ SOLICITUD ACEPTADA!\n";

        // Guardar en base de datos
        $insertSql = "INSERT INTO sat_download_history (
            certificate_id, request_type, date_from, date_to, rfc_emisor, 
            request_id, status, estatus_solicitud, fecha_inicial, fecha_final, 
            tipo_documento, fecha_solicitud, codigo_estado_solicitud, 
            mensaje_verificacion, requested_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";

        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([
            $cert['id'],                    // certificate_id
            'CFDI',                        // request_type
            $fechaInicial->format('Y-m-d'), // date_from
            $fechaFinal->format('Y-m-d'),   // date_final
            $rfc,                          // rfc_emisor
            $queryResult->getRequestId(),   // request_id
            'REQUESTED',                   // status
            '1',                           // estatus_solicitud (Aceptada)
            $fechaInicial->format('Y-m-d'), // fecha_inicial
            $fechaFinal->format('Y-m-d'),   // fecha_final
            'Emitidas',                    // tipo_documento
            '5000',                        // codigo_estado_solicitud
            'Solicitud aceptada por el SAT', // mensaje_verificacion
            1                              // requested_by (admin)
        ]);

        echo "💾 Solicitud guardada en base de datos\n";
        echo "🆔 ID en BD: " . $pdo->lastInsertId() . "\n\n";

        // Esperar 5 segundos y verificar estado
        echo "⏳ Esperando 5 segundos antes de verificar...\n";
        sleep(5);

        echo "🔍 VERIFICANDO ESTADO DE LA SOLICITUD...\n";
        $verifyResult = $service->verify($queryResult->getRequestId());

        echo "📋 ESTADO ACTUAL:\n";
        echo "Status: " . $verifyResult->getStatus()->getMessage() . "\n";

        $statusRequest = $verifyResult->getStatusRequest();
        $statusRequestNum = $statusRequest->value();
        echo "Status Request Número: " . $statusRequestNum . "\n";
        echo "Status Request Mensaje: " . $statusRequest->getMessage() . "\n";
        echo "Paquetes disponibles: " . count($verifyResult->getPackagesIds()) . "\n";

        if ($verifyResult->getPackagesIds()) {
            echo "✅ ¡HAY PAQUETES DISPONIBLES!\n";
            foreach ($verifyResult->getPackagesIds() as $package) {
                echo "  📦 " . $package . "\n";
            }
        } else {
            echo "⏳ Aún no hay paquetes (puede tardar varios minutos)\n";
        }

        $statusRequestNum = $statusRequest->value();
        echo "\n🎯 INTERPRETACIÓN DEL ESTADO:\n";
        switch ($statusRequestNum) {
            case '1':
                echo "✅ Solicitud Aceptada - En cola de procesamiento\n";
                break;
            case '2':
                echo "🔄 En Proceso - SAT está preparando los archivos\n";
                break;
            case '3':
                echo "✅ Terminada - Archivos listos para descarga\n";
                break;
            case '4':
                echo "❌ Error en el procesamiento\n";
                break;
            case '5':
                echo "❌ Rechazada por el SAT\n";
                break;
            case '6':
                echo "⏰ Vencida (72 horas)\n";
                break;
            default:
                echo "❓ Estado desconocido: " . $statusRequestNum . "\n";
        }
    } else {
        echo "❌ SOLICITUD RECHAZADA\n";
        echo "Motivo: " . $queryResult->getStatus()->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "💥 ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";
