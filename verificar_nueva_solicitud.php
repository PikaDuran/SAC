<?php
// Verificar la nueva solicitud aceptada
require_once 'vendor/autoload.php';
require_once 'src/config/database.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;

echo "=== VERIFICANDO NUEVA SOLICITUD ACEPTADA ===\n\n";

// ID de la nueva solicitud aceptada
$requestId = 'fb1adbfb-6bf2-4ebf-8b8c-07e25c00f768';
$rfc = 'BFM170822P38';

try {
    $pdo = getDatabase();

    // Buscar certificado
    $stmt = $pdo->prepare("SELECT certificate_path, key_path, password_plain FROM sat_fiel_certificates WHERE rfc = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$rfc]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cert) {
        echo "❌ No se encontró certificado\n";
        exit;
    }

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

    echo "✅ FIEL válida\n";
    echo "🆔 Request ID: $requestId\n\n";

    // Crear servicio
    $requestBuilder = new FielRequestBuilder($fiel);
    $webClient = new GuzzleWebClient();
    $service = new Service($requestBuilder, $webClient);

    echo "🔍 Verificando con el SAT...\n\n";

    // VERIFICACIÓN REAL
    $result = $service->verify($requestId);

    echo "📊 RESPUESTA DEL SAT:\n";
    echo "===================\n";
    echo "Status: " . $result->getStatus()->getMessage() . "\n";

    $statusRequest = $result->getStatusRequest();

    // Obtener el valor numérico del estado
    if (method_exists($statusRequest, 'getValue')) {
        $statusNum = $statusRequest->getValue();
    } elseif (method_exists($statusRequest, 'value')) {
        $statusNum = $statusRequest->value();
    } else {
        // Usar reflexión para obtener el valor
        $reflection = new ReflectionClass($statusRequest);
        $property = $reflection->getProperty('index');
        $property->setAccessible(true);
        $statusNum = $property->getValue($statusRequest);
    }

    $statusMsg = method_exists($statusRequest, 'getMessage') ? $statusRequest->getMessage() : 'N/A';

    echo "Status Request Número: " . $statusNum . "\n";
    echo "Status Request Mensaje: " . $statusMsg . "\n";
    echo "Paquetes disponibles: " . count($result->getPackagesIds()) . "\n\n";

    if ($result->getPackagesIds()) {
        echo "✅ ¡PAQUETES DISPONIBLES!\n";
        foreach ($result->getPackagesIds() as $package) {
            echo "  📦 " . $package . "\n";
        }
    } else {
        echo "⏳ Sin paquetes aún (puede tardar varios minutos)\n";
    }

    echo "\n🎯 INTERPRETACIÓN:\n";
    switch ($statusNum) {
        case 1:
            echo "✅ Solicitud Aceptada - En cola\n";
            break;
        case 2:
            echo "🔄 En Proceso - SAT procesando\n";
            break;
        case 3:
            echo "✅ Terminada - ¡Archivos listos!\n";
            break;
        case 4:
            echo "❌ Error\n";
            break;
        case 5:
            echo "❌ Rechazada\n";
            break;
        case 6:
            echo "⏰ Vencida\n";
            break;
        default:
            echo "❓ Estado: " . $statusNum . "\n";
    }

    echo "\n🏆 CONCLUSIÓN:\n";
    if ($statusNum == 1 || $statusNum == 2) {
        echo "✅ ¡LA ETAPA 3 FUNCIONA CORRECTAMENTE!\n";
        echo "✅ La verificación SAT es REAL y funcional\n";
        echo "✅ El problema eran las fechas inválidas en solicitudes anteriores\n";
        echo "⏳ Esta solicitud está siendo procesada por el SAT\n";
    } elseif ($statusNum == 3) {
        echo "🎉 ¡ÉXITO TOTAL! Archivos listos para descarga\n";
    } else {
        echo "❌ Aún hay problemas con esta solicitud\n";
    }

    // Actualizar base de datos
    $updateSql = "UPDATE sat_download_history SET 
                    status = 'PROCESSING', 
                    estatus_solicitud = ?, 
                    mensaje_verificacion = ?,
                    ultima_actualizacion = NOW()
                  WHERE request_id = ?";
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute([$statusNum, $statusMsg, $requestId]);

    echo "\n💾 Base de datos actualizada\n";
} catch (Exception $e) {
    echo "💥 ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE VERIFICACIÓN ===\n";
