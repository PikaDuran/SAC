<?php
// Script para verificar la existencia de los archivos .cer y .key de todos los certificados en la base de datos
require_once 'src/config/database.php';

try {
    $pdo = getDatabase();
    $stmt = $pdo->query("SELECT id, rfc, certificate_path, key_path FROM sat_fiel_certificates");
    $certificados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $baseDir = realpath(__DIR__ . '/storage/fiel_certificates/');
    echo "Verificando archivos de certificados...\n";
    foreach ($certificados as $cert) {
        $cerPath = $cert['certificate_path'];
        $keyPath = $cert['key_path'];
        // Si la ruta ya es absoluta o ya contiene 'storage/fiel_certificates', no anteponer nada
        if (!(str_starts_with($cerPath, $baseDir) || str_contains($cerPath, 'storage/fiel_certificates'))) {
            $cerPath = $baseDir . DIRECTORY_SEPARATOR . $cerPath;
        }
        if (!(str_starts_with($keyPath, $baseDir) || str_contains($keyPath, 'storage/fiel_certificates'))) {
            $keyPath = $baseDir . DIRECTORY_SEPARATOR . $keyPath;
        }
        echo "Certificado ID {$cert['id']} ({$cert['rfc']}):\n";
        echo "  CER: $cerPath - ";
        echo file_exists($cerPath) ? "OK\n" : "NO ENCONTRADO\n";
        echo "  KEY: $keyPath - ";
        echo file_exists($keyPath) ? "OK\n" : "NO ENCONTRADO\n";
    }
} catch (Exception $e) {
    echo "💥 ERROR: " . $e->getMessage() . "\n";
}
