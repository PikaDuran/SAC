<?php

namespace App\Services;

use Exception;

/**
 * Servicio de encriptación para contraseñas de certificados FIEL
 * Utiliza encriptación simétrica AES-256-CBC para poder recuperar las contraseñas
 */
class CertificatePasswordService
{
    private string $encryptionKey;
    private string $cipher = 'AES-256-CBC';

    public function __construct()
    {
        // Obtener clave de encriptación del entorno o generar una por defecto
        $this->encryptionKey = $_ENV['CERTIFICATE_ENCRYPTION_KEY'] ?? $this->getDefaultKey();
    }

    /**
     * Encriptar contraseña de certificado
     */
    public function encrypt(string $password): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $encrypted = openssl_encrypt($password, $this->cipher, $this->encryptionKey, 0, $iv);

        if ($encrypted === false) {
            throw new Exception('Error al encriptar contraseña del certificado');
        }

        // Combinar IV y datos encriptados, luego codificar en base64
        return base64_encode($iv . $encrypted);
    }

    /**
     * Desencriptar contraseña de certificado
     */
    public function decrypt(string $encryptedPassword): string
    {
        $data = base64_decode($encryptedPassword);
        if ($data === false) {
            throw new Exception('Datos de contraseña encriptada inválidos');
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->encryptionKey, 0, $iv);

        if ($decrypted === false) {
            throw new Exception('Error al desencriptar contraseña del certificado');
        }

        return $decrypted;
    }

    /**
     * Verificar si una contraseña encriptada puede ser desencriptada
     */
    public function canDecrypt(string $encryptedPassword): bool
    {
        try {
            $this->decrypt($encryptedPassword);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Verificar si una cadena es una contraseña encriptada válida
     */
    public function isEncrypted(string $password): bool
    {
        // Las contraseñas encriptadas son base64 y tienen un tamaño mínimo
        return strlen($password) > 50 && base64_decode($password, true) !== false;
    }

    /**
     * Migrar contraseña hash existente a encriptada
     * Requiere la contraseña original en texto plano
     */
    public function migrateFromHash(string $originalPassword): string
    {
        return $this->encrypt($originalPassword);
    }

    /**
     * Obtener clave de encriptación por defecto (INSEGURA - solo para desarrollo)
     */
    private function getDefaultKey(): string
    {
        // En producción, SIEMPRE usar una clave desde variable de entorno
        return hash('sha256', 'SAC_CERTIFICATE_KEY_2025_SECURE', true);
    }

    /**
     * Generar nueva clave de encriptación
     */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }
}
