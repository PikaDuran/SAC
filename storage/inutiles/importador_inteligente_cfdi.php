<?php
require 'src/config/database.php';

/**
 * IMPORTADOR INTELIGENTE CFDI - VERSIONES 3.3 Y 4.0
 * Maneja automáticamente las diferencias entre versiones
 */
class ImportadorInteligenteCFDI
{

    private $pdo;
    private $stats = [
        'procesados' => 0,
        'insertados' => 0,
        'errores' => 0,
        'timbres_insertados' => 0,
        'version_33' => 0,
        'version_40' => 0,
        'complementos_pago' => 0
    ];

    public function __construct()
    {
        $this->pdo = getDatabase();

        // Configurar para mejor rendimiento
        $this->pdo->exec("SET SESSION sql_mode = ''");
        $this->pdo->exec("SET SESSION autocommit = 0");
    }

    /**
     * Detecta automáticamente la versión del CFDI
     */
    private function detectarVersion($contenidoXML)
    {
        // Buscar Version="X.X" 
        if (preg_match('/Version\s*=\s*["\']([^"\']+)["\']/', $contenidoXML, $matches)) {
            return $matches[1];
        }

        // Buscar en namespace para versiones más antiguas
        if (preg_match('/http:\/\/www\.sat\.gob\.mx\/cfd\/(\d+)/', $contenidoXML, $matches)) {
            return $matches[1] . '.0';
        }

        return null;
    }

    /**
     * Extrae UUID según la versión del CFDI
     */
    private function extraerUUID($xml, $version)
    {
        // Para ambas versiones, el UUID está en TimbreFiscalDigital
        if (preg_match('/UUID\s*=\s*["\']([^"\']+)["\']/', $xml, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        return null;
    }

    /**
     * Extrae datos del comprobante según la versión
     */
    private function extraerDatosComprobante($contenidoXML, $version)
    {
        $datos = [];

        // Campos comunes a ambas versiones
        $campos = [
            'Version' => 'version',
            'Serie' => 'serie',
            'Folio' => 'folio',
            'Fecha' => 'fecha',
            'Sello' => 'sello',
            'FormaPago' => 'forma_pago',
            'NoCertificado' => 'no_certificado',
            'Certificado' => 'certificado',
            'SubTotal' => 'subtotal',
            'Total' => 'total',
            'TipoDeComprobante' => 'tipo',
            'MetodoPago' => 'metodo_pago',
            'LugarExpedicion' => 'lugar_expedicion',
            'Moneda' => 'moneda',
            'TipoCambio' => 'tipo_cambio'
        ];

        foreach ($campos as $campo => $columna) {
            if (preg_match('/' . $campo . '\s*=\s*["\']([^"\']*)["\']/', $contenidoXML, $matches)) {
                $datos[$columna] = trim($matches[1]);
            }
        }

        // Campos específicos de CFDI 4.0
        if ($version === '4.0') {
            $campos40 = [
                'Exportacion' => 'exportacion',
                'DomicilioFiscalReceptor' => 'domicilio_fiscal_receptor',
                'RegimenFiscalReceptor' => 'regimen_fiscal_receptor'
            ];

            foreach ($campos40 as $campo => $columna) {
                if (preg_match('/' . $campo . '\s*=\s*["\']([^"\']*)["\']/', $contenidoXML, $matches)) {
                    $datos[$columna] = trim($matches[1]);
                }
            }
        }

        return $datos;
    }

    /**
     * Extrae datos del emisor
     */
    private function extraerEmisor($contenidoXML)
    {
        $emisor = [];

        // Buscar en la sección Emisor
        if (preg_match('/<cfdi:Emisor[^>]*Rfc\s*=\s*["\']([^"\']+)["\'][^>]*>/', $contenidoXML, $matches)) {
            $emisor['rfc'] = trim($matches[1]);
        }

        if (preg_match('/<cfdi:Emisor[^>]*Nombre\s*=\s*["\']([^"\']*)["\'][^>]*>/', $contenidoXML, $matches)) {
            $emisor['nombre'] = trim($matches[1]);
        }

        if (preg_match('/<cfdi:Emisor[^>]*RegimenFiscal\s*=\s*["\']([^"\']*)["\'][^>]*>/', $contenidoXML, $matches)) {
            $emisor['regimen_fiscal'] = trim($matches[1]);
        }

        return $emisor;
    }

    /**
     * Extrae datos del receptor
     */
    private function extraerReceptor($contenidoXML)
    {
        $receptor = [];

        // Buscar en la sección Receptor
        if (preg_match('/<cfdi:Receptor[^>]*Rfc\s*=\s*["\']([^"\']+)["\'][^>]*>/', $contenidoXML, $matches)) {
            $receptor['rfc'] = trim($matches[1]);
        }

        if (preg_match('/<cfdi:Receptor[^>]*Nombre\s*=\s*["\']([^"\']*)["\'][^>]*>/', $contenidoXML, $matches)) {
            $receptor['nombre'] = trim($matches[1]);
        }

        if (preg_match('/<cfdi:Receptor[^>]*UsoCFDI\s*=\s*["\']([^"\']*)["\'][^>]*>/', $contenidoXML, $matches)) {
            $receptor['uso_cfdi'] = trim($matches[1]);
        }

        return $receptor;
    }

    /**
     * Extrae datos del timbre fiscal
     */
    private function extraerTimbreFiscal($contenidoXML)
    {
        $timbre = [];

        // Buscar TimbreFiscalDigital con múltiples patrones
        $patrones = [
            '/<tfd:TimbreFiscalDigital[^>]*([^>]*)>/',
            '/<[^:]*:TimbreFiscalDigital[^>]*([^>]*)\/>/',
            '/TimbreFiscalDigital[^>]*([^>]*)\/>/'
        ];

        foreach ($patrones as $patron) {
            if (preg_match($patron, $contenidoXML, $matches)) {
                $atributos = $matches[0]; // Usar todo el tag, no solo los atributos

                $campos = [
                    'Version' => 'version',
                    'UUID' => 'uuid',
                    'FechaTimbrado' => 'fecha_timbrado',
                    'RfcProvCertif' => 'rfc_prov_certif',
                    'SelloCFD' => 'sello_cfd',
                    'NoCertificadoSAT' => 'no_certificado_sat',
                    'SelloSAT' => 'sello_sat'
                ];

                foreach ($campos as $campo => $columna) {
                    if (preg_match('/' . $campo . '="([^"]*)"/', $atributos, $campoMatches)) {
                        $timbre[$columna] = trim($campoMatches[1]);
                    }
                }

                // Si encontramos al menos un campo, salir del bucle
                if (!empty($timbre)) {
                    break;
                }
            }
        }

        return $timbre;
    }

    /**
     * Detecta y extrae complemento de pagos completo
     */
    private function extraerComplementoPagos($contenidoXML)
    {
        // Verificar si es un CFDI de pago
        if (!preg_match('/TipoDeComprobante\s*=\s*["\']P["\']/', $contenidoXML)) {
            return null;
        }

        $resultado = [];

        // Buscar complemento de pagos (puede variar según versión)
        if (preg_match('/<pago10:Pagos[^>]*>.*?<\/pago10:Pagos>/s', $contenidoXML, $matches)) {
            $complemento = $matches[0];

            // Extraer versión del complemento
            $version = '1.0'; // Por defecto
            if (preg_match('/Version\s*=\s*["\']([^"\']*)["\']/', $complemento, $versionMatch)) {
                $version = $versionMatch[1];
            }

            // Extraer pagos individuales con todo el bloque
            if (preg_match_all('/<pago10:Pago[^>]*>.*?<\/pago10:Pago>/s', $complemento, $pagosCompletos)) {
                foreach ($pagosCompletos[0] as $pagoCompleto) {
                    $pago = [];

                    // Extraer atributos del pago
                    if (preg_match('/<pago10:Pago[^>]*([^>]*)>/', $pagoCompleto, $pagoAttr)) {
                        $campos = [
                            'FechaPago' => 'fecha_pago',
                            'FormaDePagoP' => 'forma_pago',
                            'MonedaP' => 'moneda',
                            'TipoCambioP' => 'tipo_cambio',
                            'Monto' => 'monto',
                            'NumOperacion' => 'num_operacion',
                            'RfcEmisorCtaOrd' => 'rfc_emisor_cuenta_ordenante',
                            'NomBancoOrdExt' => 'nombre_banco_extranjero',
                            'CtaOrdenante' => 'cuenta_ordenante',
                            'RfcEmisorCtaBen' => 'rfc_emisor_cuenta_beneficiario',
                            'CtaBeneficiario' => 'cuenta_beneficiario',
                            'TipoCadPago' => 'tipo_cadena_pago',
                            'CertPago' => 'certificado_pago',
                            'CadPago' => 'cadena_pago',
                            'SelloPago' => 'sello_pago'
                        ];

                        foreach ($campos as $campo => $columna) {
                            if (preg_match('/' . $campo . '\s*=\s*["\']([^"\']*)["\']/', $pagoAttr[1], $matches)) {
                                $pago[$columna] = trim($matches[1]);
                            }
                        }
                    }

                    // Extraer documentos relacionados
                    $documentos = [];
                    if (preg_match_all('/<pago10:DoctoRelacionado[^>]*([^>\/]*)\/?>/s', $pagoCompleto, $docsMatches)) {
                        foreach ($docsMatches[0] as $docCompleto) {
                            $documento = [];

                            $camposDoc = [
                                'IdDocumento' => 'uuid_documento',
                                'Serie' => 'serie',
                                'Folio' => 'folio',
                                'MonedaDR' => 'moneda_dr',
                                'EquivalenciaDR' => 'equivalencia_dr',
                                'NumParcialidad' => 'num_parcialidad',
                                'ImpSaldoAnt' => 'imp_saldo_ant',
                                'ImpPagado' => 'imp_pagado',
                                'ImpSaldoInsoluto' => 'imp_saldo_insoluto',
                                'ObjetoImpDR' => 'objeto_imp_dr'
                            ];

                            foreach ($camposDoc as $campo => $columna) {
                                if (preg_match('/' . $campo . '\s*=\s*["\']([^"\']*)["\']/', $docCompleto, $matches)) {
                                    $documento[$columna] = trim($matches[1]);
                                }
                            }

                            if (!empty($documento)) {
                                $documentos[] = $documento;
                            }
                        }
                    }

                    if (!empty($pago)) {
                        $pago['version'] = $version;
                        $pago['documentos_relacionados'] = $documentos;
                        $resultado[] = $pago;
                    }
                }
            }
        }

        return !empty($resultado) ? $resultado : null;
    }

    /**
     * Extrae conceptos (productos/servicios) del CFDI
     */
    private function extraerConceptos($contenidoXML)
    {
        $conceptos = [];

        // Buscar todos los conceptos con patrón correcto
        if (preg_match_all('/<cfdi:Concepto[^>]*([^>\/]*)\/?>/s', $contenidoXML, $matches)) {
            foreach ($matches[0] as $conceptoCompleto) {
                $concepto = [];

                $campos = [
                    'ClaveProdServ' => 'clave_prodserv',
                    'Cantidad' => 'cantidad',
                    'ClaveUnidad' => 'clave_unidad',
                    'Unidad' => 'unidad',
                    'Descripcion' => 'descripcion',
                    'ValorUnitario' => 'valor_unitario',
                    'Importe' => 'importe',
                    'Descuento' => 'descuento',
                    'ObjetoImp' => 'objeto_imp'
                ];

                foreach ($campos as $campo => $columna) {
                    if (preg_match('/' . $campo . '\s*=\s*["\']([^"\']*)["\']/', $conceptoCompleto, $conceptoMatches)) {
                        $value = trim($conceptoMatches[1]);
                        if ($value !== '') {
                            $concepto[$columna] = $value;
                        }
                    }
                }

                if (!empty($concepto)) {
                    $conceptos[] = $concepto;
                }
            }
        }

        return $conceptos;
    }

    /**
     * Extrae impuestos del CFDI
     */
    private function extraerImpuestos($contenidoXML)
    {
        $impuestos = [];

        // Buscar impuestos trasladados con patrón correcto
        if (preg_match_all('/<cfdi:Traslado[^>]*([^>\/]*)\/?>/s', $contenidoXML, $matches)) {
            foreach ($matches[0] as $trasladoCompleto) {
                $impuesto = [];
                $impuesto['tipo'] = 'Traslado';

                $campos = [
                    'Impuesto' => 'impuesto',
                    'TipoFactor' => 'tipo_factor',
                    'TasaOCuota' => 'tasa_cuota',
                    'Base' => 'base',
                    'Importe' => 'importe'
                ];

                foreach ($campos as $campo => $columna) {
                    if (preg_match('/' . $campo . '\s*=\s*["\']([^"\']*)["\']/', $trasladoCompleto, $impuestoMatches)) {
                        $value = trim($impuestoMatches[1]);
                        if ($value !== '') {
                            $impuesto[$columna] = $value;
                        }
                    }
                }

                if (!empty($impuesto) && (isset($impuesto['impuesto']) || isset($impuesto['base']))) {
                    $impuestos[] = $impuesto;
                }
            }
        }

        // Buscar impuestos retenidos con patrón correcto
        if (preg_match_all('/<cfdi:Retencion[^>]*([^>\/]*)\/?>/s', $contenidoXML, $matches)) {
            foreach ($matches[0] as $retencionCompleto) {
                $impuesto = [];
                $impuesto['tipo'] = 'Retencion';

                $campos = [
                    'Impuesto' => 'impuesto',
                    'TipoFactor' => 'tipo_factor',
                    'TasaOCuota' => 'tasa_cuota',
                    'Base' => 'base',
                    'Importe' => 'importe'
                ];

                foreach ($campos as $campo => $columna) {
                    if (preg_match('/' . $campo . '\s*=\s*["\']([^"\']*)["\']/', $retencionCompleto, $impuestoMatches)) {
                        $value = trim($impuestoMatches[1]);
                        if ($value !== '') {
                            $impuesto[$columna] = $value;
                        }
                    }
                }

                if (!empty($impuesto) && (isset($impuesto['impuesto']) || isset($impuesto['base']))) {
                    $impuestos[] = $impuesto;
                }
            }
        }

        return $impuestos;
    }

    /**
     * Inserta CFDI en la base de datos
     */
    private function insertarCFDI($uuid, $datosComprobante, $emisor, $receptor, $timbre, $archivoPath, $complementoPagos = null, $conceptos = null, $impuestos = null)
    {
        try {
            // DEBUG: Verificar datos
            if ($this->stats['insertados'] < 3) {
                echo "DEBUG: Insertando CFDI con UUID: {$uuid}\n";
                echo "DEBUG: Datos comprobante: " . print_r(array_slice($datosComprobante, 0, 5), true);
                echo "DEBUG: Timbre: " . print_r($timbre, true);
                echo "DEBUG: Conceptos encontrados: " . count($conceptos) . "\n";
                echo "DEBUG: Impuestos encontrados: " . count($impuestos) . "\n";
            }

            // Insertar CFDI principal
            $sql = "INSERT INTO cfdi (
                uuid, version, serie, folio, fecha, sello_cfd, forma_pago,
                subtotal, total, tipo, metodo_pago, lugar_expedicion,
                moneda, tipo_cambio, rfc_emisor, nombre_emisor, regimen_fiscal_emisor,
                rfc_receptor, nombre_receptor, uso_cfdi, archivo_xml,
                complemento_tipo, complemento_json, exportacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $uuid,
                $datosComprobante['version'] ?? null,
                $datosComprobante['serie'] ?? null,
                $datosComprobante['folio'] ?? null,
                $datosComprobante['fecha'] ?? null,
                substr($datosComprobante['sello'] ?? '', 0, 500), // Limitar longitud
                $datosComprobante['forma_pago'] ?? null,
                $datosComprobante['subtotal'] ?? null,
                $datosComprobante['total'] ?? null,
                $datosComprobante['tipo'] ?? null,
                $datosComprobante['metodo_pago'] ?? null,
                $datosComprobante['lugar_expedicion'] ?? null,
                $datosComprobante['moneda'] ?? null,
                $datosComprobante['tipo_cambio'] ?? null,
                $emisor['rfc'] ?? null,
                $emisor['nombre'] ?? null,
                $emisor['regimen_fiscal'] ?? null,
                $receptor['rfc'] ?? null,
                $receptor['nombre'] ?? null,
                $receptor['uso_cfdi'] ?? null,
                $archivoPath,
                $complementoPagos ? 'pago' : null,
                $complementoPagos ? json_encode($complementoPagos, JSON_UNESCAPED_UNICODE) : null,
                $datosComprobante['exportacion'] ?? null
            ]);

            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                echo "ERROR SQL: " . $errorInfo[2] . "\n";
                throw new Exception("Error al insertar CFDI: " . implode(", ", $errorInfo));
            }

            $cfdi_id = $this->pdo->lastInsertId();

            if ($this->stats['insertados'] < 3) {
                echo "DEBUG: CFDI insertado con ID: {$cfdi_id}\n";
            }

            // Insertar timbre fiscal
            if (!empty($timbre)) {
                $this->insertarTimbreFiscal($cfdi_id, $timbre);
                $this->stats['timbres_insertados']++;
            } else {
                // Debug: ¿Por qué está vacío el timbre?
                if ($this->stats['insertados'] <= 3) {
                    echo "DEBUG: Timbre vacío para UUID {$uuid}\n";
                }
            }

            // Insertar complemento de pagos si existe
            if ($complementoPagos) {
                foreach ($complementoPagos as $pago) {
                    $this->insertarPago($cfdi_id, $pago);
                }
                $this->stats['complementos_pago']++;
            }

            // Insertar conceptos
            if (!empty($conceptos)) {
                $conceptos_insertados = $this->insertarConceptos($cfdi_id, $conceptos);
                if (!isset($this->stats['conceptos_insertados'])) {
                    $this->stats['conceptos_insertados'] = 0;
                }
                $this->stats['conceptos_insertados'] += $conceptos_insertados;

                if ($this->stats['insertados'] < 3) {
                    echo "DEBUG: Conceptos insertados: {$conceptos_insertados}\n";
                }
            }

            // Insertar impuestos
            if (!empty($impuestos)) {
                $impuestos_insertados = $this->insertarImpuestos($cfdi_id, $impuestos);
                if (!isset($this->stats['impuestos_insertados'])) {
                    $this->stats['impuestos_insertados'] = 0;
                }
                $this->stats['impuestos_insertados'] += $impuestos_insertados;

                if ($this->stats['insertados'] < 3) {
                    echo "DEBUG: Impuestos insertados: {$impuestos_insertados}\n";
                }
            }

            return $cfdi_id;
        } catch (Exception $e) {
            throw new Exception("Error al insertar CFDI {$uuid}: " . $e->getMessage());
        }
    }

    /**
     * Inserta timbre fiscal
     */
    private function insertarTimbreFiscal($cfdi_id, $timbre)
    {
        $sql = "INSERT INTO cfdi_timbre_fiscal (
            cfdi_id, version, uuid, fecha_timbrado, rfc_prov_certif,
            sello_cfd, no_certificado_sat, sello_sat
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $cfdi_id,
            $timbre['version'] ?? null,
            $timbre['uuid'] ?? null,
            $timbre['fecha_timbrado'] ?? null,
            $timbre['rfc_prov_certif'] ?? null,
            substr($timbre['sello_cfd'] ?? '', 0, 500),
            $timbre['no_certificado_sat'] ?? null,
            substr($timbre['sello_sat'] ?? '', 0, 500)
        ]);
    }

    /**
     * Inserta pago del complemento y documentos relacionados
     */
    private function insertarPago($cfdi_id, $pago)
    {
        // Insertar el pago principal
        $sql = "INSERT INTO cfdi_pagos (
            cfdi_id, version, fecha_pago, forma_pago, moneda, tipo_cambio, monto,
            num_operacion, rfc_emisor_cuenta_ordenante, nombre_banco_extranjero,
            cuenta_ordenante, rfc_emisor_cuenta_beneficiario, cuenta_beneficiario,
            tipo_cadena_pago, certificado_pago, cadena_pago, sello_pago
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            $cfdi_id,
            $pago['version'] ?? '1.0',
            $pago['fecha_pago'] ?? null,
            $pago['forma_pago'] ?? null,
            $pago['moneda'] ?? null,
            $pago['tipo_cambio'] ?? null,
            $pago['monto'] ?? null,
            $pago['num_operacion'] ?? null,
            $pago['rfc_emisor_cuenta_ordenante'] ?? null,
            $pago['nombre_banco_extranjero'] ?? null,
            $pago['cuenta_ordenante'] ?? null,
            $pago['rfc_emisor_cuenta_beneficiario'] ?? null,
            $pago['cuenta_beneficiario'] ?? null,
            $pago['tipo_cadena_pago'] ?? null,
            $pago['certificado_pago'] ?? null,
            $pago['cadena_pago'] ?? null,
            $pago['sello_pago'] ?? null
        ]);

        if (!$result) {
            return false;
        }

        $pago_id = $this->pdo->lastInsertId();

        // Insertar documentos relacionados
        if (!empty($pago['documentos_relacionados'])) {
            foreach ($pago['documentos_relacionados'] as $documento) {
                $this->insertarDocumentoRelacionado($pago_id, $documento);
            }
        }

        return true;
    }

    /**
     * Inserta documento relacionado del pago
     */
    private function insertarDocumentoRelacionado($pago_id, $documento)
    {
        $sql = "INSERT INTO cfdi_pago_documentos_relacionados (
            pago_id, uuid_documento, serie, folio, moneda_dr, equivalencia_dr,
            num_parcialidad, imp_saldo_ant, imp_pagado, imp_saldo_insoluto, objeto_imp_dr
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $pago_id,
            $documento['uuid_documento'] ?? null,
            $documento['serie'] ?? null,
            $documento['folio'] ?? null,
            $documento['moneda_dr'] ?? null,
            $documento['equivalencia_dr'] ?? null,
            $documento['num_parcialidad'] ?? null,
            $documento['imp_saldo_ant'] ?? null,
            $documento['imp_pagado'] ?? null,
            $documento['imp_saldo_insoluto'] ?? null,
            $documento['objeto_imp_dr'] ?? null
        ]);
    }

    /**
     * Inserta conceptos del CFDI
     */
    private function insertarConceptos($cfdi_id, $conceptos)
    {
        if (empty($conceptos)) return 0;

        $sql = "INSERT INTO cfdi_conceptos (
            cfdi_id, clave_prodserv, cantidad, clave_unidad, unidad,
            descripcion, valor_unitario, importe, descuento, objeto_imp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $insertados = 0;

        foreach ($conceptos as $concepto) {
            $result = $stmt->execute([
                $cfdi_id,
                $concepto['clave_prodserv'] ?? null,
                $concepto['cantidad'] ?? null,
                $concepto['clave_unidad'] ?? null,
                $concepto['unidad'] ?? null,
                $concepto['descripcion'] ?? null,
                $concepto['valor_unitario'] ?? null,
                $concepto['importe'] ?? null,
                $concepto['descuento'] ?? null,
                $concepto['objeto_imp'] ?? null
            ]);

            if ($result) $insertados++;
        }

        return $insertados;
    }

    /**
     * Inserta impuestos del CFDI
     */
    private function insertarImpuestos($cfdi_id, $impuestos)
    {
        if (empty($impuestos)) return 0;

        $sql = "INSERT INTO cfdi_impuestos (
            cfdi_id, tipo, impuesto, tipo_factor, tasa_cuota, base, importe
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $insertados = 0;

        foreach ($impuestos as $impuesto) {
            $result = $stmt->execute([
                $cfdi_id,
                $impuesto['tipo'] ?? null,
                $impuesto['impuesto'] ?? null,
                $impuesto['tipo_factor'] ?? null,
                $impuesto['tasa_cuota'] ?? null,
                $impuesto['base'] ?? null,
                $impuesto['importe'] ?? null
            ]);

            if ($result) $insertados++;
        }

        return $insertados;
    }

    /**
     * Registra en auditoría
     */
    private function registrarAuditoria($archivo, $uuid, $estado, $mensaje)
    {
        $sql = "INSERT INTO cfdi_auditoria (archivo, uuid, estado, mensaje, fecha) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$archivo, $uuid, $estado, $mensaje]);
    }

    /**
     * Procesa un archivo XML individual
     */
    private function procesarArchivo($archivoPath)
    {
        try {
            $this->stats['procesados']++;

            // Leer archivo
            $contenido = file_get_contents($archivoPath);
            if ($contenido === false) {
                throw new Exception("No se pudo leer el archivo");
            }

            // Limpiar contenido
            $contenido = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $contenido);

            // Detectar versión
            $version = $this->detectarVersion($contenido);
            if (!$version) {
                throw new Exception("No se pudo detectar la versión del CFDI");
            }

            // Contar por versión
            if ($version === '3.3') {
                $this->stats['version_33']++;
            } elseif ($version === '4.0') {
                $this->stats['version_40']++;
            }

            // Extraer UUID
            $uuid = $this->extraerUUID($contenido, $version);
            if (!$uuid) {
                throw new Exception("No se pudo extraer el UUID");
            }

            // DEBUG: Mostrar UUID extraído para los primeros
            if ($this->stats['insertados'] + $this->stats['errores'] < 3) {
                echo "DEBUG: UUID extraído: {$uuid}\n";
            }

            // Verificar si ya existe
            $stmt = $this->pdo->prepare("SELECT id FROM cfdi WHERE uuid = ?");
            $stmt->execute([$uuid]);
            if ($stmt->fetch()) {
                $this->registrarAuditoria($archivoPath, $uuid, 'DUPLICADO', 'CFDI ya existe en la base de datos');
                if ($this->stats['insertados'] + $this->stats['errores'] < 3) {
                    echo "DEBUG: CFDI duplicado con UUID: {$uuid}\n";
                }
                return;
            }

            if ($this->stats['insertados'] + $this->stats['errores'] < 3) {
                echo "DEBUG: CFDI no existe, procediendo a extraer datos para UUID: {$uuid}\n";
            }

            // Extraer datos
            $datosComprobante = $this->extraerDatosComprobante($contenido, $version);
            $emisor = $this->extraerEmisor($contenido);
            $receptor = $this->extraerReceptor($contenido);
            $timbre = $this->extraerTimbreFiscal($contenido);
            $complementoPagos = $this->extraerComplementoPagos($contenido);
            $conceptos = $this->extraerConceptos($contenido);
            $impuestos = $this->extraerImpuestos($contenido);

            // Insertar en BD
            $cfdi_id = $this->insertarCFDI($uuid, $datosComprobante, $emisor, $receptor, $timbre, $archivoPath, $complementoPagos, $conceptos, $impuestos);

            $this->stats['insertados']++;
            $this->registrarAuditoria($archivoPath, $uuid, 'INSERTADO', "CFDI insertado correctamente (ID: {$cfdi_id})");
        } catch (Exception $e) {
            $this->stats['errores']++;
            $this->registrarAuditoria($archivoPath, $uuid ?? 'N/A', 'ERROR', $e->getMessage());
            echo "ERROR en {$archivoPath}: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Importa todos los archivos XML del directorio
     */
    public function importar($directorio)
    {
        echo "=== IMPORTADOR INTELIGENTE CFDI 3.3 Y 4.0 ===\n\n";
        echo "Directorio: {$directorio}\n";
        echo "Iniciando importación...\n\n";

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directorio),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $totalArchivos = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                $totalArchivos++;
            }
        }

        echo "Total de archivos XML encontrados: {$totalArchivos}\n\n";

        $count = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                $count++;

                if ($count % 100 == 0) {
                    $this->pdo->commit(); // Commit cada 100 registros
                    $this->pdo->beginTransaction();

                    echo sprintf(
                        "Progreso: %d/%d (%.2f%%) - Insertados: %d, Errores: %d, V3.3: %d, V4.0: %d, Pagos: %d\n",
                        $count,
                        $totalArchivos,
                        ($count / $totalArchivos) * 100,
                        $this->stats['insertados'],
                        $this->stats['errores'],
                        $this->stats['version_33'],
                        $this->stats['version_40'],
                        $this->stats['complementos_pago']
                    );
                }

                $this->procesarArchivo($file->getPathname());

                // Limitar para prueba inicial
                if ($count >= 1000) {
                    echo "\nLimitando a 1000 archivos para prueba inicial...\n";
                    break;
                }
            }
        }

        $this->pdo->commit();
        $this->mostrarEstadisticas();
    }

    /**
     * Muestra estadísticas finales
     */
    private function mostrarEstadisticas()
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "ESTADÍSTICAS FINALES DE IMPORTACIÓN\n";
        echo str_repeat("=", 60) . "\n";
        echo "Archivos procesados: " . $this->stats['procesados'] . "\n";
        echo "CFDIs insertados: " . $this->stats['insertados'] . "\n";
        echo "Errores: " . $this->stats['errores'] . "\n";
        echo "CFDI 3.3: " . $this->stats['version_33'] . "\n";
        echo "CFDI 4.0: " . $this->stats['version_40'] . "\n";
        echo "Complementos de pago: " . $this->stats['complementos_pago'] . "\n";
        echo "Timbres fiscales: " . ($this->stats['timbres_insertados'] ?? 0) . "\n";
        echo "Conceptos insertados: " . ($this->stats['conceptos_insertados'] ?? 0) . "\n";
        echo "Impuestos insertados: " . ($this->stats['impuestos_insertados'] ?? 0) . "\n";

        if ($this->stats['procesados'] > 0) {
            $exito = ($this->stats['insertados'] / $this->stats['procesados']) * 100;
            echo "Tasa de éxito: " . number_format($exito, 2) . "%\n";
        }

        echo str_repeat("=", 60) . "\n";
    }
}

// Ejecutar importación
try {
    $directorio = __DIR__ . '/storage/sat_downloads';

    if (!is_dir($directorio)) {
        echo "Error: El directorio {$directorio} no existe\n";
        exit(1);
    }

    $importador = new ImportadorInteligenteCFDI();
    $importador->importar($directorio);
} catch (Exception $e) {
    echo "Error fatal: " . $e->getMessage() . "\n";
}
