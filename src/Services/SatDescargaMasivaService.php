<?php

namespace App\Services;

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\RequestBuilderInterface;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryResult;
use PhpCfdi\SatWsDescargaMasiva\Services\Verify\VerifyResult;
use PhpCfdi\SatWsDescargaMasiva\Services\Download\DownloadResult;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTime;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RequestType;
use PhpCfdi\SatWsDescargaMasiva\Shared\ServiceType;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentType;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentStatus;
use PhpCfdi\SatWsDescargaMasiva\Shared\RfcMatch;
use PhpCfdi\SatWsDescargaMasiva\Shared\RfcMatches;
use PhpCfdi\SatWsDescargaMasiva\Shared\RfcOnBehalf;
use PhpCfdi\SatWsDescargaMasiva\Shared\Uuid;
use Exception;

require_once __DIR__ . '/../helpers/fiel_factory.php';

/**
 * Servicio completo para Descarga Masiva SAT
 * Implementa los 3 tipos de solicitud según documentación oficial SAT v1.5:
 * - SolicitaDescargaEmitidos
 * - SolicitaDescargaRecibidos
 * - SolicitaDescargaFolio
 */
class SatDescargaMasivaService
{
    private Service $service;
    private Fiel $fiel;
    private RequestBuilderInterface $requestBuilder;

    /**
     * Crear servicio desde certificado en base de datos
     */
    public static function fromDatabase(string $rfc, $pdo = null): self
    {
        if (!$pdo) {
            global $pdo;
        }

        // Buscar certificado activo
        $stmt = $pdo->prepare("
            SELECT certificate_path, key_path, password_plain 
            FROM sat_fiel_certificates 
            WHERE rfc = ? AND is_active = 1 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$rfc]);
        $cert = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$cert) {
            throw new Exception("Certificado FIEL no encontrado para RFC: $rfc");
        }

        // Usar contraseña directa
        $password = $cert['password_plain'];
        if (empty($password)) {
            throw new Exception("No se encontró contraseña para el certificado RFC: $rfc");
        }

        // Si en la base solo está el nombre, anteponer storage/fiel_certificates/
        $baseDir = realpath(__DIR__ . '/../../');
        $certFolder = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'fiel_certificates' . DIRECTORY_SEPARATOR;
        $certPath = $cert['certificate_path'];
        $keyPath = $cert['key_path'];
        // Si no es ruta absoluta, forzar absoluta desde storage/fiel_certificates
        if (!preg_match('/^([a-zA-Z]:)?[\\\/]/', $certPath)) {
            $certPath = $certFolder . $certPath;
        }
        if (!preg_match('/^([a-zA-Z]:)?[\\\/]/', $keyPath)) {
            $keyPath = $certFolder . $keyPath;
        }
        return new self($certPath, $keyPath, $password);
    }

    /**
     * Inicializar servicio con certificado FIEL
     */
    public function __construct(string $certificatePath, string $privateKeyPath, string $passPhrase)
    {
        // Forzar rutas absolutas si se recibe una ruta relativa
        $baseDir = realpath(__DIR__ . '/../../');
        $certFolder = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'fiel_certificates' . DIRECTORY_SEPARATOR;
        // Corregir expresión regular para rutas absolutas en Windows y Unix
        if (!preg_match('/^([a-zA-Z]:\\\\|\/)/', $certificatePath)) {
            $certificatePath = $certFolder . basename($certificatePath);
        }
        if (!preg_match('/^([a-zA-Z]:\\\\|\/)/', $privateKeyPath)) {
            $privateKeyPath = $certFolder . basename($privateKeyPath);
        }

        // Validar archivos
        if (!file_exists($certificatePath)) {
            error_log("[SAT] No se encontró el archivo .cer: $certificatePath");
            throw new Exception("Archivo de certificado (.cer) no encontrado: $certificatePath");
        }

        if (!file_exists($privateKeyPath)) {
            error_log("[SAT] No se encontró el archivo .key: $privateKeyPath");
            throw new Exception("Archivo de llave privada (.key) no encontrado: $privateKeyPath");
        }

        // Crear FIEL usando la función helper igual que en los tests
        $this->fiel = create_fiel($certificatePath, $privateKeyPath, $passPhrase);

        // Verificar que el certificado sea válido
        if (!$this->fiel->isValid()) {
            throw new Exception("El certificado FIEL no es válido o está vencido");
        }

        // Crear request builder y servicio
        $this->requestBuilder = new FielRequestBuilder($this->fiel);
        $webClient = new GuzzleWebClient();
        $this->service = new Service($this->requestBuilder, $webClient);
    }

    /**
     * 1. SOLICITAR DESCARGA EMITIDOS
     * Permite solicitar descarga de CFDIs o Metadata de comprobantes emitidos
     * 
     * @param array $parametros [
     *   'fecha_inicial' => string (YYYY-MM-DD),
     *   'fecha_final' => string (YYYY-MM-DD),
     *   'rfc_emisor' => string (RFC obligatorio),
     *   'rfc_receptores' => array (opcional, máximo 5),
     *   'rfc_solicitante' => string (opcional),
     *   'tipo_solicitud' => string ('CFDI' o 'Metadata'),
     *   'tipo_comprobante' => string (opcional: null, 'I', 'E', 'T', 'N', 'P'),
     *   'estado_comprobante' => string (opcional: 'Todos', 'Cancelado', 'Vigente'),
     *   'rfc_a_cuenta_terceros' => string (opcional),
     *   'complemento' => string (opcional)
     * ]
     */
    public function solicitarDescargaEmitidos(array $parametros): array
    {
        try {
            // Validar parámetros obligatorios
            $this->validarParametrosEmitidos($parametros);

            // Validar formato de fecha
            $fechaInicial = $parametros['fecha_inicial'];
            $fechaFinal = $parametros['fecha_final'];

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicial) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFinal)) {
                throw new Exception("Formato de fecha inválido. Use YYYY-MM-DD");
            }

            // Crear fechas usando la clase DateTime de la librería
            $fechaInicio = DateTime::create($fechaInicial . ' 00:00:00');
            $fechaFin = DateTime::create($fechaFinal . ' 23:59:59');

            // Validar que el periodo no sea mayor a 30 días
            $inicio = new \DateTimeImmutable($fechaInicial);
            $fin = new \DateTimeImmutable($fechaFinal);
            $diferenciaDias = $fin->diff($inicio)->days;
            if ($diferenciaDias > 30) {
                throw new Exception("El periodo no puede ser mayor a 30 días");
            }

            // Crear period con DateTimePeriod de la librería
            $period = DateTimePeriod::create($fechaInicio, $fechaFin);

            // Crear QueryParameters usando el constructor estático correcto
            $queryParameters = QueryParameters::create(
                period: $period,
                downloadType: DownloadType::issued(),
                requestType: RequestType::metadata(),
                serviceType: ServiceType::cfdi()
            );

            // Configurar RFC emisor usando RfcMatch
            if (!empty($parametros['rfc_emisor'])) {
                $rfcMatch = RfcMatch::create($parametros['rfc_emisor']);
                $queryParameters = $queryParameters->withRfcMatch($rfcMatch);
            }

            // Agregar RFC receptores si se especifican
            if (!empty($parametros['rfc_receptores'])) {
                if (count($parametros['rfc_receptores']) > 5) {
                    throw new Exception("Máximo 5 RFC receptores permitidos");
                }
                $rfcMatches = RfcMatches::create();
                foreach ($parametros['rfc_receptores'] as $rfc) {
                    // $rfcMatches = $rfcMatches->withRfcMatch(RfcMatch::create($rfc));
                    $rfcMatches[] = RfcMatch::create($rfc);
                }
                $queryParameters = $queryParameters->withRfcMatches($rfcMatches);
            }

            // Agregar RFC solicitante si se especifica
            if (!empty($parametros['rfc_solicitante'])) {
                if ($parametros['rfc_solicitante'] !== $parametros['rfc_emisor']) {
                    throw new Exception("RFC solicitante debe coincidir con RFC emisor");
                }
                $rfcOnBehalf = RfcOnBehalf::create($parametros['rfc_solicitante']);
                $queryParameters = $queryParameters->withRfcOnBehalf($rfcOnBehalf);
            }

            // Agregar tipo de comprobante si se especifica
            if (!empty($parametros['tipo_comprobante'])) {
                $tiposValidos = ['I', 'E', 'T', 'N', 'P'];
                if (!in_array($parametros['tipo_comprobante'], $tiposValidos)) {
                    throw new Exception("Tipo de comprobante inválido. Valores válidos: " . implode(', ', $tiposValidos));
                }

                $documentType = match ($parametros['tipo_comprobante']) {
                    'I' => DocumentType::ingreso(),
                    'E' => DocumentType::egreso(),
                    'T' => DocumentType::traslado(),
                    'N' => DocumentType::nomina(),
                    'P' => DocumentType::pago(),
                    default => DocumentType::undefined()
                };
                $queryParameters = $queryParameters->withDocumentType($documentType);
            }

            // Agregar estado del comprobante si se especifica
            if (!empty($parametros['estado_comprobante'])) {
                $estadosValidos = ['Todos', 'Cancelado', 'Vigente'];
                if (!in_array($parametros['estado_comprobante'], $estadosValidos)) {
                    throw new Exception("Estado de comprobante inválido. Valores válidos: " . implode(', ', $estadosValidos));
                }

                $documentStatus = match ($parametros['estado_comprobante']) {
                    'Vigente' => DocumentStatus::active(),
                    'Cancelado' => DocumentStatus::cancelled(),
                    default => DocumentStatus::undefined()
                };
                $queryParameters = $queryParameters->withDocumentStatus($documentStatus);
            }

            // Realizar solicitud al SAT
            $queryResult = $this->service->query($queryParameters);

            return $this->procesarRespuestaSolicitud($queryResult, 'SolicitaDescargaEmitidos');
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error en solicitud de descarga emitidos: ' . $e->getMessage(),
                'codigo_error' => $this->obtenerCodigoError($e->getMessage())
            ];
        }
    }

    /**
     * 2. SOLICITAR DESCARGA RECIBIDOS
     * Permite solicitar descarga de CFDIs o Metadata de comprobantes recibidos
     */
    public function solicitarDescargaRecibidos(array $parametros): array
    {
        try {
            // Validar parámetros obligatorios
            $this->validarParametrosRecibidos($parametros);

            // Validar formato de fecha
            $fechaInicial = $parametros['fecha_inicial'];
            $fechaFinal = $parametros['fecha_final'];

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicial) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFinal)) {
                throw new Exception("Formato de fecha inválido. Use YYYY-MM-DD");
            }

            // Crear fechas usando la clase DateTime de la librería
            $fechaInicio = DateTime::create($fechaInicial . ' 00:00:00');
            $fechaFin = DateTime::create($fechaFinal . ' 23:59:59');

            // Validar que el periodo no sea mayor a 30 días
            $inicio = new \DateTimeImmutable($fechaInicial);
            $fin = new \DateTimeImmutable($fechaFinal);
            $diferenciaDias = $fin->diff($inicio)->days;
            if ($diferenciaDias > 30) {
                throw new Exception("El periodo no puede ser mayor a 30 días");
            }

            // Crear period con DateTimePeriod de la librería
            $period = DateTimePeriod::create($fechaInicio, $fechaFin);

            // Crear QueryParameters usando el constructor estático correcto
            $queryParameters = QueryParameters::create(
                period: $period,
                downloadType: DownloadType::received(),
                requestType: RequestType::metadata(),
                serviceType: ServiceType::cfdi()
            );

            // Configurar RFC receptor (obligatorio para recibidos)
            if (!empty($parametros['rfc_receptor'])) {
                $rfcMatch = RfcMatch::create($parametros['rfc_receptor']);
                $queryParameters = $queryParameters->withRfcMatch($rfcMatch);
            }

            // Agregar RFC emisor si se especifica
            if (!empty($parametros['rfc_emisor'])) {
                $rfcMatches = RfcMatches::create();
                // $rfcMatches = $rfcMatches->withRfcMatch(RfcMatch::create($parametros['rfc_emisor']));
                $rfcMatches[] = RfcMatch::create($parametros['rfc_emisor']);
                $queryParameters = $queryParameters->withRfcMatches($rfcMatches);
            }

            // Agregar RFC solicitante si se especifica
            if (!empty($parametros['rfc_solicitante'])) {
                if ($parametros['rfc_solicitante'] !== $parametros['rfc_receptor']) {
                    throw new Exception("RFC solicitante debe coincidir con RFC receptor");
                }
                $rfcOnBehalf = RfcOnBehalf::create($parametros['rfc_solicitante']);
                $queryParameters = $queryParameters->withRfcOnBehalf($rfcOnBehalf);
            }

            // Otros parámetros opcionales
            if (!empty($parametros['tipo_comprobante'])) {
                $tiposValidos = ['I', 'E', 'T', 'N', 'P'];
                if (!in_array($parametros['tipo_comprobante'], $tiposValidos)) {
                    throw new Exception("Tipo de comprobante inválido. Valores válidos: " . implode(', ', $tiposValidos));
                }

                $documentType = match ($parametros['tipo_comprobante']) {
                    'I' => DocumentType::ingreso(),
                    'E' => DocumentType::egreso(),
                    'T' => DocumentType::traslado(),
                    'N' => DocumentType::nomina(),
                    'P' => DocumentType::pago(),
                    default => DocumentType::undefined()
                };
                $queryParameters = $queryParameters->withDocumentType($documentType);
            }

            if (!empty($parametros['estado_comprobante'])) {
                $estadosValidos = ['Todos', 'Cancelado', 'Vigente'];
                if (!in_array($parametros['estado_comprobante'], $estadosValidos)) {
                    throw new Exception("Estado de comprobante inválido. Valores válidos: " . implode(', ', $estadosValidos));
                }

                $documentStatus = match ($parametros['estado_comprobante']) {
                    'Vigente' => DocumentStatus::active(),
                    'Cancelado' => DocumentStatus::cancelled(),
                    default => DocumentStatus::undefined()
                };
                $queryParameters = $queryParameters->withDocumentStatus($documentStatus);
            } else {
                // Por defecto: Vigente según documentación
                $queryParameters = $queryParameters->withDocumentStatus(DocumentStatus::active());
            }

            // Realizar solicitud al SAT
            $queryResult = $this->service->query($queryParameters);

            return $this->procesarRespuestaSolicitud($queryResult, 'SolicitaDescargaRecibidos');
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error en solicitud de descarga recibidos: ' . $e->getMessage(),
                'codigo_error' => $this->obtenerCodigoError($e->getMessage())
            ];
        }
    }

    /**
     * 3. SOLICITAR DESCARGA FOLIO
     * Permite solicitar descarga de un CFDI específico por folio
     */
    public function solicitarDescargaFolio(array $parametros): array
    {
        try {
            // Validar parámetros obligatorios
            if (empty($parametros['folio'])) {
                throw new Exception("El folio es obligatorio");
            }

            // Validar formato de folio UUID
            if (!$this->validarFormatoUUID($parametros['folio'])) {
                throw new Exception("Formato de folio inválido. Debe ser UUID: XXXXXXXX-XXXX-XXXX-XXXXXXXXXXXXXXXX");
            }

            // Crear QueryParameters usando el constructor estático correcto
            $queryParameters = QueryParameters::create(
                downloadType: DownloadType::issued(),
                requestType: RequestType::metadata(),
                serviceType: ServiceType::cfdi()
            );

            // Configurar UUID del folio
            $uuid = Uuid::create($parametros['folio']);
            $queryParameters = $queryParameters->withUuid($uuid);

            // Agregar RFC solicitante si se especifica
            if (!empty($parametros['rfc_solicitante'])) {
                $rfcOnBehalf = RfcOnBehalf::create($parametros['rfc_solicitante']);
                $queryParameters = $queryParameters->withRfcOnBehalf($rfcOnBehalf);
            }

            // Realizar solicitud al SAT
            $queryResult = $this->service->query($queryParameters);

            return $this->procesarRespuestaSolicitud($queryResult, 'SolicitaDescargaFolio');
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error en solicitud de descarga por folio: ' . $e->getMessage(),
                'codigo_error' => $this->obtenerCodigoError($e->getMessage())
            ];
        }
    }

    /**
     * VERIFICAR ESTADO DE SOLICITUD
     * Verificar el estado de una solicitud previamente enviada
     */
    public function verificarEstadoSolicitud(string $requestId): array
    {
        try {
            $verifyResult = $this->service->verify($requestId);

            return [
                'success' => true,
                'data' => [
                    'request_id' => $requestId,
                    'status_code' => $verifyResult->getStatus()->getCode(),
                    'status_message' => $verifyResult->getStatus()->getMessage(),
                    'code_request' => method_exists($verifyResult, 'getCodeRequest') ? $verifyResult->getCodeRequest() : null,
                    'status_request' => method_exists($verifyResult, 'getStatusRequest') ? $verifyResult->getStatusRequest() : null
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error verificando solicitud: ' . $e->getMessage()
            ];
        }
    }

    /**
     * DESCARGAR PAQUETES
     * Descargar los paquetes de una solicitud completada
     */
    public function descargarPaquetes(string $requestId, array $packageIds, string $downloadPath = null): array
    {
        try {
            $downloadedFiles = [];
            $downloadPath = $downloadPath ?? $this->obtenerRutaDescarga();

            // Crear directorio si no existe
            if (!is_dir($downloadPath)) {
                mkdir($downloadPath, 0755, true);
            }

            foreach ($packageIds as $packageId) {
                $downloadResult = $this->service->download($packageId);

                if ($downloadResult->getStatus()->isAccepted()) {
                    $filename = "paquete_{$packageId}_" . date('Ymd_His') . '.zip';
                    $filepath = $downloadPath . DIRECTORY_SEPARATOR . $filename;

                    file_put_contents($filepath, $downloadResult->getPackageContent());

                    $downloadedFiles[] = [
                        'package_id' => $packageId,
                        'filename' => $filename,
                        'filepath' => $filepath,
                        'size' => filesize($filepath)
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Descarga completada',
                'files' => $downloadedFiles,
                'total_files' => count($downloadedFiles)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error descargando paquetes: ' . $e->getMessage()
            ];
        }
    }

    /**
     * VALIDACIONES Y UTILIDADES PRIVADAS
     */

    private function validarParametrosEmitidos(array $parametros): void
    {
        $obligatorios = ['fecha_inicial', 'fecha_final', 'rfc_emisor'];

        foreach ($obligatorios as $campo) {
            if (empty($parametros[$campo])) {
                throw new Exception("El campo '$campo' es obligatorio para descarga de emitidos");
            }
        }

        $this->validarRangoFechas($parametros['fecha_inicial'], $parametros['fecha_final']);
        $this->validarRFC($parametros['rfc_emisor']);
    }

    private function validarParametrosRecibidos(array $parametros): void
    {
        $obligatorios = ['fecha_inicial', 'fecha_final', 'rfc_receptor'];

        foreach ($obligatorios as $campo) {
            if (empty($parametros[$campo])) {
                throw new Exception("El campo '$campo' es obligatorio para descarga de recibidos");
            }
        }

        $this->validarRangoFechas($parametros['fecha_inicial'], $parametros['fecha_final']);
        $this->validarRFC($parametros['rfc_receptor']);
    }

    private function validarRangoFechas(string $fechaInicial, string $fechaFinal): void
    {
        $inicio = new \DateTimeImmutable($fechaInicial);
        $fin = new \DateTimeImmutable($fechaFinal);

        if ($inicio > $fin) {
            throw new Exception("La fecha inicial no puede ser mayor que la fecha final");
        }

        // Validar que no sea mayor a 30 días
        $diferencia = $fin->diff($inicio)->days;
        if ($diferencia > 30) {
            throw new Exception("El rango de fechas no puede ser mayor a 30 días");
        }
    }

    private function validarRFC(string $rfc): void
    {
        if (!preg_match('/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $rfc)) {
            throw new Exception("Formato de RFC inválido: $rfc");
        }
    }

    private function validarFormatoUUID(string $uuid): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $uuid);
    }

    private function convertirFechaParaSAT(string $fecha): string
    {
        // Convertir fecha YYYY-MM-DD a formato SAT: YYYY-MM-DDThh:mm:ss
        $dateTime = new DateTime($fecha);
        return $dateTime->format('Y-m-d\T00:00:00');
    }

    private function procesarRespuestaSolicitud(QueryResult $queryResult, string $tipoSolicitud): array
    {
        $status = $queryResult->getStatus();

        return [
            'success' => $status->isAccepted(),
            'data' => [
                'request_id' => $queryResult->getRequestId(),
                'rfc_solicitante' => $this->fiel->getRfc(),
                'codigo_estatus' => $status->getCode(),
                'mensaje' => $status->getMessage(),
                'tipo_solicitud' => $tipoSolicitud,
                'fecha_solicitud' => (new \DateTime())->format('Y-m-d H:i:s')
            ],
            'message' => $status->isAccepted() ? 'Solicitud aceptada por el SAT' : $status->getMessage()
        ];
    }

    private function procesarPaquetes(array $packageIds): array
    {
        return array_map(function ($packageId) {
            return [
                'id' => $packageId,
                'status' => 'DISPONIBLE'
            ];
        }, $packageIds);
    }

    private function obtenerCodigoError(string $mensaje): string
    {
        // Mapear mensajes a códigos según documentación SAT
        $errores = [
            'Usuario No Válido' => '300',
            'XML Mal Formado' => '301',
            'Sello Mal Formado' => '302',
            'Certificado Revocado o Caduco' => '304',
            'Certificado Inválido' => '305',
            'Tercero no autorizado' => '5001',
            'Se han agotado las solicitudes de por vida' => '5002',
            'Ya se tiene una solicitud registrada' => '5005'
        ];

        foreach ($errores as $texto => $codigo) {
            if (stripos($mensaje, $texto) !== false) {
                return $codigo;
            }
        }

        return '404'; // Error no controlado
    }

    private function obtenerRutaDescarga(): string
    {
        $baseDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sat_downloads';
        $dateDir = date('Y') . DIRECTORY_SEPARATOR . date('m');

        return $baseDir . DIRECTORY_SEPARATOR . $dateDir;
    }

    /**
     * Obtener ruta de descarga con estructura completa: RFC/EMITIDAS O RECIBIDAS/anio/mes/
     * 
     * @param string $rfc RFC del contribuyente
     * @param string $tipoDocumento 'Emitidas' o 'Recibidas'
     * @param string|null $year Año (opcional, usa año actual si no se especifica)
     * @param string|null $month Mes (opcional, usa mes actual si no se especifica)
     * @return string Ruta completa del directorio
     */
    public function obtenerRutaDescargaCompleta(string $rfc, string $tipoDocumento, ?string $year = null, ?string $month = null): string
    {
        $baseDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sat_downloads';
        $year = $year ?? date('Y');
        $month = $month ?? date('m');
        
        return $baseDir . DIRECTORY_SEPARATOR . $rfc . DIRECTORY_SEPARATOR . $tipoDocumento . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;
    }

    /**
     * Obtener información del FIEL usado
     */
    public function obtenerInfoFiel(): array
    {
        return [
            'rfc' => $this->fiel->getRfc(),
            'certificate_serial' => $this->fiel->getCertificateSerial(),
            'is_valid' => $this->fiel->isValid(),
            'legal_name' => $this->obtenerNombreLegal()
        ];
    }

    private function obtenerNombreLegal(): string
    {
        try {
            $certData = openssl_x509_parse($this->fiel->getCertificatePemContents());
            return $certData['subject']['CN'] ?? $certData['subject']['O'] ?? 'Contribuyente ' . $this->fiel->getRfc();
        } catch (Exception $e) {
            return 'Contribuyente ' . $this->fiel->getRfc();
        }
    }
}
