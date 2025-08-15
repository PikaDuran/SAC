<?php
// Prueba de acceso a certificado y llave para cualquier RFC/certificado
require_once 'src/config/database.php';

try {
    $pdo = getDatabase();
    $stmt = $pdo->query("SELECT id, rfc, certificate_path, key_path FROM sat_fiel_certificates");
    $certificados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $certBaseDir = realpath(__DIR__ . '/storage/fiel_certificates/');
    echo "Prueba de acceso a certificados:\n";
    foreach ($certificados as $cert) {
        $cerFile = basename($cert['certificate_path']);
        $keyFile = basename($cert['key_path']);
        $cerPath = $certBaseDir . DIRECTORY_SEPARATOR . $cerFile;
        $keyPath = $certBaseDir . DIRECTORY_SEPARATOR . $keyFile;
        echo "Certificado ID {$cert['id']} ({$cert['rfc']}):\n";
        echo "  CER: $cerPath - ";
        echo file_exists($cerPath) ? "OK\n" : "NO ENCONTRADO\n";
        echo "  KEY: $keyPath - ";
        echo file_exists($keyPath) ? "OK\n" : "NO ENCONTRADO\n";
    }
} catch (Exception $e) {
    echo "💥 ERROR: " . $e->getMessage() . "\n";
}
