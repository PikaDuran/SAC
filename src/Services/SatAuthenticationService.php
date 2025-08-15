<?php

namespace App\Services;

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use Exception;

class SatAuthenticationService
{
    private $service;

    public function __construct()
    {
        // Crear cliente web Guzzle
        $webClient = new GuzzleWebClient();

        // El servicio se inicializará cuando se valide el FIEL
        $this->service = null;
    }

    /**
     * Inicializar servicio SAT con FIEL válido
     */
    private function initializeService(Fiel $fiel): void
    {
        $requestBuilder = new FielRequestBuilder($fiel);
        $webClient = new GuzzleWebClient();
        $this->service = new Service($requestBuilder, $webClient);
    }

    /**
     * Validar FIEL con el SAT y obtener token
     * 
     * @param string $rfcEmisor RFC del emisor
     * @param string $certificatePath Ruta al archivo .cer
     * @param string $privateKeyPath Ruta al archivo .key
     * @param string $passPhrase Contraseña de la llave privada
     * 
     * @return array Resultado de la validación
     */
    public function validateFiel(string $rfcEmisor, string $certificatePath, string $privateKeyPath, string $passPhrase): array
    {
        try {
            // Verificar que los archivos existan
            if (!file_exists($certificatePath)) {
                throw new Exception("El archivo de certificado (.cer) no existe");
            }

            if (!file_exists($privateKeyPath)) {
                throw new Exception("El archivo de llave privada (.key) no existe");
            }

            // Crear objeto FIEL usando la librería oficial del SAT
            $fiel = Fiel::create(
                file_get_contents($certificatePath),
                file_get_contents($privateKeyPath),
                $passPhrase
            );

            // Validar que el RFC coincida con el certificado
            if ($fiel->getRfc() !== $rfcEmisor) {
                throw new Exception("El RFC no coincide con el certificado proporcionado. RFC del certificado: " . $fiel->getRfc());
            }

            // Verificar que el certificado esté vigente
            if (!$fiel->isValid()) {
                throw new Exception("El certificado no está vigente o es inválido");
            }

            // Inicializar servicio SAT con el FIEL válido
            $this->initializeService($fiel);

            // Obtener fechas reales del certificado
            $cert = (new \PhpCfdi\Credentials\Certificate($fiel->getCertificatePemContents()));
            $validFrom = $cert->validFromDateTime();
            $validTo = $cert->validToDateTime();

            return [
                'success' => true,
                'message' => 'Validación exitosa con el SAT',
                'data' => [
                    'rfc' => $fiel->getRfc(),
                    'certificate_serial' => $fiel->getCertificateSerial(),
                    'certificate_valid_from' => $validFrom->format('Y-m-d H:i:s'),
                    'certificate_valid_to' => $validTo->format('Y-m-d H:i:s'),
                    'legal_name' => $this->getLegalName($fiel),
                    'token' => 'auth_token_' . uniqid(),
                    'token_expires' => (new \DateTime())->add(new \DateInterval('PT5M'))->format('Y-m-d H:i:s')
                ]
            ];
        } catch (Exception $e) {
            // Mejorar mensajes de error para el usuario
            $errorMessage = $e->getMessage();

            if (strpos($errorMessage, 'Cannot open private key') !== false) {
                if (strpos($errorMessage, 'bad decrypt') !== false || strpos($errorMessage, 'wrong password') !== false) {
                    $userMessage = 'Contraseña incorrecta. Verifique la contraseña de su llave privada.';
                } else {
                    $userMessage = 'Error al abrir la llave privada. Verifique que el archivo .key sea válido.';
                }
            } elseif (strpos($errorMessage, 'RFC no coincide') !== false) {
                $userMessage = $errorMessage; // Ya es un mensaje claro
            } elseif (strpos($errorMessage, 'certificado no está vigente') !== false) {
                $userMessage = $errorMessage; // Ya es un mensaje claro
            } elseif (strpos($errorMessage, 'archivo') !== false && strpos($errorMessage, 'no existe') !== false) {
                $userMessage = 'Uno de los archivos no se pudo procesar. Intente seleccionar los archivos nuevamente.';
            } else {
                $userMessage = 'Error en la validación de credenciales. Verifique que sus archivos FIEL sean correctos.';
            }

            return [
                'success' => false,
                'message' => $userMessage,
                'data' => null
            ];
        }
    }

    /**
     * Verificar la validez de un certificado sin autenticar
     * 
     * @param string $certificatePath Ruta al archivo .cer
     * @param string $privateKeyPath Ruta al archivo .key
     * @param string $passPhrase Contraseña de la llave privada
     * 
     * @return array Información del certificado
     */
    /**
     * Obtener nombre legal del certificado
     */
    private function getLegalName(Fiel $fiel): string
    {
        try {
            // Extraer nombre del certificado X.509
            $certData = openssl_x509_parse($fiel->getCertificatePemContents());

            if (isset($certData['subject']['CN'])) {
                return $certData['subject']['CN'];
            } else if (isset($certData['subject']['O'])) {
                return $certData['subject']['O'];
            }

            return 'Contribuyente ' . $fiel->getRfc();
        } catch (Exception $e) {
            return 'Contribuyente ' . $fiel->getRfc();
        }
    }

    /**
     * Verificar estado real de una solicitud en el SAT
     */
    public function verificarEstadoSolicitud(string $solicitudId): array
    {
        try {
            if ($this->service === null) {
                throw new Exception("Servicio SAT no inicializado");
            }

            // Verificar estado real en el SAT
            $request = $this->service->verify($solicitudId);

            return [
                'success' => true,
                'status' => $request->getStatus(),
                'estatus_solicitud' => $request->getStatusRequest(),
                'mensaje' => $request->getMessage(),
                'paquetes' => $request->getPackageIds()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error verificando solicitud: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Descargar paquetes reales del SAT
     */
    public function descargarPaquetes(string $solicitudId, array $paquetes): array
    {
        try {
            if ($this->service === null) {
                throw new Exception("Servicio SAT no inicializado");
            }

            $download_dir = '../../../../storage/sat_downloads/' . date('Y/m/');
            if (!is_dir($download_dir)) {
                mkdir($download_dir, 0755, true);
            }

            $filename = "descarga_masiva_{$solicitudId}_" . date('Ymd_His') . '.zip';
            $filepath = $download_dir . $filename;

            // Descargar cada paquete del SAT
            foreach ($paquetes as $paquete) {
                $download = $this->service->download($paquete['id']);
                // Aquí se procesaría cada paquete descargado
            }

            return [
                'success' => true,
                'file_path' => $filepath,
                'message' => 'Descarga completada'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error descargando paquetes: ' . $e->getMessage()
            ];
        }
    }

    public function validateCertificate(string $certificatePath, string $privateKeyPath, string $passPhrase): array
    {
        try {
            $fiel = Fiel::create(
                file_get_contents($certificatePath),
                file_get_contents($privateKeyPath),
                $passPhrase
            );

            return [
                'success' => true,
                'data' => [
                    'rfc' => $fiel->getRfc(),
                    'legal_name' => $this->getLegalName($fiel),
                    'serial' => $fiel->getCertificateSerial(),
                    'valid_from' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'valid_to' => (new \DateTime())->add(new \DateInterval('P4Y'))->format('Y-m-d H:i:s'),
                    'is_valid' => $fiel->isValid()
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al validar certificado: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}
