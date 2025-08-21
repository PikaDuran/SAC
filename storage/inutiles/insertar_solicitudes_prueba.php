<?php
require_once 'src/config/database.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );


    // Limpiar la tabla de historial de descargas
    $pdo->exec("TRUNCATE TABLE sat_download_history");

    // Datos de prueba para Emitidas
    $emitidas = [
        'certificate_id' => 2, // ID real del certificado BLM1706026AA
        'request_type' => 'CFDI',
        'date_from' => '2025-01-01',
        'date_to' => '2025-01-31',
        'rfc_emisor' => 'BLM1706026AA',
        'request_id' => '920b6635-238d-429d-b445-909605d3713c',
        'status' => 'REQUESTED',
        'estatus_solicitud' => 'Solicitud Aceptada',
        'fecha_inicial' => '2025-01-01',
        'fecha_final' => '2025-01-31',
        'tipo_documento' => 'Emitidas',
        'fecha_solicitud' => '2025-08-11 23:20:10',
        'codigo_estado_solicitud' => '5000',
        'mensaje_verificacion' => 'Solicitud aceptada por el SAT',
        'requested_by' => 1 // ID real del usuario admin
    ];

    // Datos de prueba para Recibidas
    $recibidas = [
        'certificate_id' => 2, // ID real del certificado BLM1706026AA
        'request_type' => 'CFDI',
        'date_from' => '2025-01-01',
        'date_to' => '2025-01-31',
        'rfc_emisor' => 'BLM1706026AA',
        'request_id' => '2cbe38fe-9069-48f6-a118-5d8bc8ca8a72',
        'status' => 'REQUESTED',
        'estatus_solicitud' => 'Solicitud Aceptada',
        'fecha_inicial' => '2025-01-01',
        'fecha_final' => '2025-01-31',
        'tipo_documento' => 'Recibidas',
        'fecha_solicitud' => '2025-08-11 23:23:26',
        'codigo_estado_solicitud' => '5000',
        'mensaje_verificacion' => 'Solicitud aceptada por el SAT',
        'requested_by' => 1 // ID real del usuario admin
    ];

    $sql = "INSERT INTO sat_download_history (certificate_id, request_type, date_from, date_to, rfc_emisor, request_id, status, estatus_solicitud, fecha_inicial, fecha_final, tipo_documento, fecha_solicitud, codigo_estado_solicitud, mensaje_verificacion, requested_by) VALUES (:certificate_id, :request_type, :date_from, :date_to, :rfc_emisor, :request_id, :status, :estatus_solicitud, :fecha_inicial, :fecha_final, :tipo_documento, :fecha_solicitud, :codigo_estado_solicitud, :mensaje_verificacion, :requested_by)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($emitidas);
    $stmt->execute($recibidas);

    echo "✅ Solicitudes de prueba agregadas a la base de datos.\n";
} catch (Exception $e) {
    echo "💥 ERROR: " . $e->getMessage() . "\n";
}
