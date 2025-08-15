<?php

/**
 * Script de migración para convertir contraseñas hasheadas a encriptadas
 * IMPORTANTE: Este script debe ejecutarse una sola vez y requiere las contraseñas originales
 */

require_once __DIR__ . '/src/Services/CertificatePasswordService.php';
require_once __DIR__ . '/config/database.php';

use App\Services\CertificatePasswordService;

class CertificatePasswordMigration
{
    private $pdo;
    private CertificatePasswordService $passwordService;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->passwordService = new CertificatePasswordService();
    }

    /**
     * Migrar contraseña específica proporcionando RFC y contraseña original
     */
    public function migratePassword(string $rfc, string $originalPassword): bool
    {
        try {
            // Verificar que el RFC existe
            $stmt = $this->pdo->prepare("
                SELECT id, password_hash 
                FROM sat_fiel_certificates 
                WHERE rfc = ? AND is_active = 1
            ");
            $stmt->execute([$rfc]);
            $certificate = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$certificate) {
                echo "❌ RFC $rfc no encontrado en la base de datos\n";
                return false;
            }

            // Verificar que la contraseña original sea correcta validando con los archivos del certificado
            if (!$this->validateOriginalPassword($rfc, $originalPassword)) {
                echo "❌ Contraseña incorrecta para RFC $rfc\n";
                return false;
            }

            // Encriptar la contraseña nueva
            $encryptedPassword = $this->passwordService->encrypt($originalPassword);

            // Actualizar en la base de datos
            $updateStmt = $this->pdo->prepare("
                UPDATE sat_fiel_certificates 
                SET password_encrypted = ?, 
                    migrated_at = NOW(),
                    migration_status = 'completed'
                WHERE id = ?
            ");

            $success = $updateStmt->execute([$encryptedPassword, $certificate['id']]);

            if ($success) {
                echo "✅ RFC $rfc migrado exitosamente\n";
                return true;
            } else {
                echo "❌ Error al actualizar RFC $rfc en la base de datos\n";
                return false;
            }
        } catch (Exception $e) {
            echo "❌ Error migrando RFC $rfc: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Validar contraseña original cargando el certificado real
     */
    private function validateOriginalPassword(string $rfc, string $password): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT certificate_path, key_path 
                FROM sat_fiel_certificates 
                WHERE rfc = ? AND is_active = 1
            ");
            $stmt->execute([$rfc]);
            $cert = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cert) {
                return false;
            }

            $certPath = __DIR__ . '/' . $cert['certificate_path'];
            $keyPath = __DIR__ . '/' . $cert['key_path'];

            if (!file_exists($certPath) || !file_exists($keyPath)) {
                echo "⚠️  Archivos de certificado no encontrados para RFC $rfc\n";
                return false;
            }

            // Intentar crear FIEL con la contraseña - si funciona, la contraseña es correcta
            require_once __DIR__ . '/vendor/autoload.php';

            $fiel = \PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel::create(
                file_get_contents($certPath),
                file_get_contents($keyPath),
                $password
            );

            return $fiel->isValid();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Migrar múltiples certificados de una vez
     */
    public function migrateBatch(array $certificates): array
    {
        $results = [];

        foreach ($certificates as $cert) {
            if (!isset($cert['rfc']) || !isset($cert['password'])) {
                echo "❌ RFC o contraseña faltante en batch\n";
                continue;
            }

            $results[$cert['rfc']] = $this->migratePassword($cert['rfc'], $cert['password']);
        }

        return $results;
    }

    /**
     * Listar certificados que necesitan migración
     */
    public function listPendingMigrations(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT rfc, legal_name, certificate_serial, created_at,
                   (password_encrypted IS NULL OR password_encrypted = '') as needs_migration
            FROM sat_fiel_certificates 
            WHERE is_active = 1
            ORDER BY created_at DESC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Agregar columna de migración a la tabla si no existe
     */
    public function prepareDatabase(): bool
    {
        try {
            // Agregar columnas para contraseñas encriptadas
            $this->pdo->exec("
                ALTER TABLE sat_fiel_certificates 
                ADD COLUMN IF NOT EXISTS password_encrypted TEXT NULL,
                ADD COLUMN IF NOT EXISTS migrated_at DATETIME NULL,
                ADD COLUMN IF NOT EXISTS migration_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending'
            ");

            echo "✅ Base de datos preparada para migración\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Error preparando base de datos: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Ejemplo de uso del script
if (php_sapi_name() === 'cli') {
    echo "=== MIGRACIÓN DE CONTRASEÑAS DE CERTIFICADOS FIEL ===\n\n";

    $migration = new CertificatePasswordMigration();

    // Preparar base de datos
    $migration->prepareDatabase();

    // Listar certificados pendientes
    echo "\n--- Certificados registrados ---\n";
    $pending = $migration->listPendingMigrations();

    foreach ($pending as $cert) {
        $status = $cert['needs_migration'] ? '❌ PENDIENTE' : '✅ MIGRADO';
        echo "RFC: {$cert['rfc']} | {$cert['legal_name']} | $status\n";
    }

    echo "\n--- Migración por lotes ---\n";
    echo "Para migrar certificados, agregue las contraseñas conocidas:\n\n";

    // Ejemplo de migración con contraseñas conocidas
    $knownPasswords = [
        ['rfc' => 'BFM170822P38', 'password' => 'BOTFM2025'],
        // Agregar más RFCs y contraseñas aquí según sea necesario
    ];

    if (!empty($knownPasswords)) {
        echo "Migrando certificados con contraseñas conocidas...\n";
        $results = $migration->migrateBatch($knownPasswords);

        $success = array_filter($results);
        echo "\n✅ Migrados exitosamente: " . count($success) . " certificados\n";
        echo "❌ Fallos: " . (count($results) - count($success)) . " certificados\n";
    }

    echo "\n=== MIGRACIÓN COMPLETADA ===\n";
    echo "Para certificados nuevos, use el nuevo sistema que ya guarda contraseñas encriptadas.\n";
}
