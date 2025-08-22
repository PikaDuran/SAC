<?php

/**
 * Procesador CFDI Completo 3.3 y 4.0
 * Extrae todos los campos, complementos de pago y los inserta correctamente
 */

require_once 'src/config/database.php';

class ProcesadorCFDICompleto
{
    private $pdo;
    private $stats = [
        'procesados' => 0,
        'insertados' => 0,
        'errores' => 0,
        'cfdi_33' => 0,
        'cfdi_40' => 0,
        'pagos' => 0,
        'conceptos' => 0,
        'impuestos' => 0,
        'timbres' => 0,
        'archivos_vacios' => 0,
        'sin_uuid' => 0,
        'duplicados' => 0
    ];

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function limpiarTablas()
    {
        echo "🧹 Limpiando todas las tablas...\n";

        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        $tablas = [
            'cfdi_pago_documentos_relacionados',
            'cfdi_pagos',
            'cfdi_complementos',
            'cfdi_impuestos',
            'cfdi_conceptos',
            'cfdi_timbre_fiscal',
            'cfdi_auditoria',
            'cfdi'
        ];

        foreach ($tablas as $tabla) {
            $this->pdo->exec("DELETE FROM $tabla");
            $this->pdo->exec("ALTER TABLE $tabla AUTO_INCREMENT = 1");
        }

        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "✅ Tablas limpiadas\n\n";
    }

    public function procesarDirectorio($directorio, $limpiarTablas = true)
    {
        if ($limpiarTablas) {
            $this->limpiarTablas();
        }

        echo "📁 Procesando directorio: $directorio\n";

        $archivos = $this->buscarArchivosXML($directorio);
        $total = count($archivos);

        echo "📋 Archivos encontrados: $total\n";
        echo "🚀 Iniciando procesamiento...\n";
        echo "   └── Progreso cada 500 archivos\n";
        echo "   └── Checkpoints cada 5000 archivos\n\n";

        foreach ($archivos as $i => $archivo) {
            $this->stats['procesados']++;

            if ($this->stats['procesados'] % 500 == 0) {
                $porcentaje = round(($this->stats['procesados'] / $total) * 100, 2);
                echo "Progreso: {$this->stats['procesados']}/$total ($porcentaje%) - ";
                echo "Insertados: {$this->stats['insertados']}, Errores: {$this->stats['errores']}, ";
                echo "3.3: {$this->stats['cfdi_33']}, 4.0: {$this->stats['cfdi_40']}, Pagos: {$this->stats['pagos']}, ";
                echo "Omitidos: " . ($this->stats['archivos_vacios'] + $this->stats['sin_uuid'] + $this->stats['duplicados']) . "\n";
            }

            // Checkpoint detallado cada 5000 archivos
            if ($this->stats['procesados'] % 5000 == 0) {
                echo "🔄 CHECKPOINT - Procesados: {$this->stats['procesados']} | ";
                echo "Tiempo: " . (time() - $_SERVER['REQUEST_TIME']) . "s\n";
                echo "   └── Insertados: {$this->stats['insertados']} | Vacíos: {$this->stats['archivos_vacios']} | ";
                echo "Sin UUID: {$this->stats['sin_uuid']} | Duplicados: {$this->stats['duplicados']}\n\n";
            }

            try {
                $this->procesarArchivo($archivo);
            } catch (Exception $e) {
                $this->stats['errores']++;
                if ($this->stats['errores'] <= 5) {
                    echo "❌ Error en archivo " . basename($archivo) . ": " . $e->getMessage() . "\n";
                }
            }
        }

        $this->mostrarEstadisticas();
    }

    private function buscarArchivosXML($directorio)
    {
        $archivos = [];

        // Buscar en estructura RFC/EMITIDAS|RECIBIDAS/año/mes/
        $rfcs = glob($directorio . '/*', GLOB_ONLYDIR);

        foreach ($rfcs as $rfc) {
            foreach (['EMITIDAS', 'RECIBIDAS'] as $tipo) {
                $rutaTipo = $rfc . '/' . $tipo;
                if (is_dir($rutaTipo)) {
                    $años = glob($rutaTipo . '/*', GLOB_ONLYDIR);
                    foreach ($años as $año) {
                        $meses = glob($año . '/*', GLOB_ONLYDIR);
                        foreach ($meses as $mes) {
                            $xmls = glob($mes . '/*.xml');
                            $archivos = array_merge($archivos, $xmls);
                        }
                    }
                }
            }
        }

        return $archivos;
    }

    private function procesarArchivo($archivo)
    {
        $contenido = file_get_contents($archivo);
        if (!$contenido) {
            $this->stats['archivos_vacios']++;
            return;
        }

        // Extraer UUID
        $uuid = $this->extraerUUID($contenido);
        if (!$uuid) {
            $this->stats['sin_uuid']++;
            return;
        }

        // Verificar si ya existe
        if ($this->existeCFDI($uuid)) {
            $this->stats['duplicados']++;
            return;
        }

        // Determinar versión
        $version = $this->determinarVersion($contenido);
        if ($version == '3.3') {
            $this->stats['cfdi_33']++;
        } elseif ($version == '4.0') {
            $this->stats['cfdi_40']++;
        }

        // Extraer datos principales
        $comprobante = $this->extraerComprobante($contenido);
        $emisor = $this->extraerEmisor($contenido);
        $receptor = $this->extraerReceptor($contenido);
        $timbre = $this->extraerTimbre($contenido);
        $conceptos = $this->extraerConceptos($contenido);
        $impuestos = $this->extraerImpuestos($contenido);
        $complementoPagos = $this->extraerComplementoPagos($contenido);

        // Insertar CFDI principal
        $cfdi_id = $this->insertarCFDI([
            'uuid' => $uuid,
            'version' => $version,
            'serie' => $comprobante['serie'] ?? null,
            'folio' => $comprobante['folio'] ?? null,
            'fecha' => $comprobante['fecha'] ?? null,
            'fecha_timbrado' => $timbre['fecha_timbrado'] ?? null,
            'rfc_emisor' => $emisor['rfc'] ?? null,
            'nombre_emisor' => $emisor['nombre'] ?? null,
            'regimen_fiscal_emisor' => $emisor['regimen_fiscal'] ?? null,
            'rfc_receptor' => $receptor['rfc'] ?? null,
            'nombre_receptor' => $receptor['nombre'] ?? null,
            'regimen_fiscal_receptor' => $receptor['regimen_fiscal'] ?? null,
            'uso_cfdi' => $receptor['uso_cfdi'] ?? null,
            'lugar_expedicion' => $comprobante['lugar_expedicion'] ?? null,
            'moneda' => $comprobante['moneda'] ?? null,
            'tipo_cambio' => $comprobante['tipo_cambio'] ?? null,
            'subtotal' => $comprobante['subtotal'] ?? null,
            'descuento' => $comprobante['descuento'] ?? null,
            'total' => $comprobante['total'] ?? null,
            'metodo_pago' => $comprobante['metodo_pago'] ?? null,
            'forma_pago' => $comprobante['forma_pago'] ?? null,
            'exportacion' => $comprobante['exportacion'] ?? null,
            'tipo' => $comprobante['tipo'] ?? null,
            'archivo_xml' => $archivo,
            'complemento_tipo' => $complementoPagos ? 'pago' : null,
            'complemento_json' => $complementoPagos ? json_encode($complementoPagos, JSON_UNESCAPED_UNICODE) : null,
            'observaciones' => $comprobante['observaciones'] ?? null,
            'rfc_consultado' => $receptor['rfc'] ?? null,
            'direccion_flujo' => 'EMITIDAS', // Se puede determinar por la ruta del archivo
            'sello_cfd' => $comprobante['sello'] ?? null,
            'sello_sat' => $timbre['sello_sat'] ?? null,
            'no_certificado_sat' => $timbre['no_certificado_sat'] ?? null,
            'rfc_prov_certif' => $timbre['rfc_prov_certif'] ?? null,
            'estatus_sat' => 'VIGENTE', // Por defecto
            'cfdi_relacionados' => $this->extraerCfdiRelacionados($contenido),
            'no_certificado' => $comprobante['no_certificado'] ?? null,
            'certificado' => $comprobante['certificado'] ?? null,
            'condiciones_de_pago' => $comprobante['condiciones_de_pago'] ?? null
        ]);

        if (!$cfdi_id) return;

        $this->stats['insertados']++;

        // Insertar timbre fiscal
        if ($timbre) {
            $this->insertarTimbre($cfdi_id, $timbre);
            $this->stats['timbres']++;
        }

        // Insertar conceptos
        if ($conceptos) {
            $this->insertarConceptos($cfdi_id, $conceptos);
            $this->stats['conceptos'] += count($conceptos);
        }

        // Insertar impuestos
        if ($impuestos) {
            $this->insertarImpuestos($cfdi_id, $impuestos);
            $this->stats['impuestos'] += count($impuestos);
        }

        // Insertar complementos de pago
        if ($complementoPagos) {
            $this->insertarPagos($cfdi_id, $complementoPagos);
            $this->stats['pagos']++;
        }
    }

    private function extraerUUID($contenido)
    {
        if (preg_match('/UUID="([^"]+)"/', $contenido, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function existeCFDI($uuid)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM cfdi WHERE uuid = ?");
        $stmt->execute([$uuid]);
        return $stmt->fetchColumn() !== false;
    }

    private function determinarVersion($contenido)
    {
        if (strpos($contenido, 'Version="4.0"') !== false) {
            return '4.0';
        } elseif (strpos($contenido, 'Version="3.3"') !== false) {
            return '3.3';
        }
        return '3.3'; // Por defecto
    }

    private function extraerComprobante($contenido)
    {
        $datos = [];

        $campos = [
            'Serie' => 'serie',
            'Folio' => 'folio',
            'Fecha' => 'fecha',
            'Sello' => 'sello',
            'NoCertificado' => 'no_certificado',
            'Certificado' => 'certificado',
            'SubTotal' => 'subtotal',
            'Descuento' => 'descuento',
            'Total' => 'total',
            'Moneda' => 'moneda',
            'TipoCambio' => 'tipo_cambio',
            'TipoDeComprobante' => 'tipo',
            'MetodoPago' => 'metodo_pago',
            'FormaPago' => 'forma_pago',
            'LugarExpedicion' => 'lugar_expedicion',
            'Exportacion' => 'exportacion',
            'CondicionesDePago' => 'condiciones_de_pago'
        ];

        foreach ($campos as $campo => $columna) {
            if (preg_match('/' . $campo . '="([^"]*)"/', $contenido, $matches)) {
                $datos[$columna] = $matches[1];
            }
        }

        return $datos;
    }

    private function extraerCfdiRelacionados($contenido)
    {
        $relacionados = [];

        // Buscar el nodo CfdiRelacionados
        if (preg_match('/<cfdi:CfdiRelacionados[^>]*>.*?<\/cfdi:CfdiRelacionados>/s', $contenido, $matches)) {
            $cfdiRelacionados = $matches[0];

            // Extraer el tipo de relación
            $tipoRelacion = '';
            if (preg_match('/TipoRelacion="([^"]*)"/', $cfdiRelacionados, $tipoMatch)) {
                $tipoRelacion = $tipoMatch[1];
            }

            // Extraer todos los UUIDs relacionados
            if (preg_match_all('/<cfdi:CfdiRelacionado[^>]*UUID="([^"]*)"/', $cfdiRelacionados, $uuidMatches)) {
                foreach ($uuidMatches[1] as $uuid) {
                    $relacionados[] = [
                        'tipo_relacion' => $tipoRelacion,
                        'uuid' => $uuid
                    ];
                }
            }
        }

        return empty($relacionados) ? null : json_encode($relacionados);
    }

    private function extraerEmisor($contenido)
    {
        $datos = [];

        $campos = [
            'Rfc' => 'rfc',
            'Nombre' => 'nombre',
            'RegimenFiscal' => 'regimen_fiscal'
        ];

        if (preg_match('/<cfdi:Emisor[^>]*>/', $contenido, $match)) {
            $emisor = $match[0];
            foreach ($campos as $campo => $columna) {
                if (preg_match('/' . $campo . '="([^"]*)"/', $emisor, $matches)) {
                    $datos[$columna] = $matches[1];
                }
            }
        }

        return $datos;
    }

    private function extraerReceptor($contenido)
    {
        $datos = [];

        $campos = [
            'Rfc' => 'rfc',
            'Nombre' => 'nombre',
            'UsoCFDI' => 'uso_cfdi',
            'RegimenFiscalReceptor' => 'regimen_fiscal'
        ];

        if (preg_match('/<cfdi:Receptor[^>]*>/', $contenido, $match)) {
            $receptor = $match[0];
            foreach ($campos as $campo => $columna) {
                if (preg_match('/' . $campo . '="([^"]*)"/', $receptor, $matches)) {
                    $datos[$columna] = $matches[1];
                }
            }
        }

        return $datos;
    }

    private function extraerTimbre($contenido)
    {
        $datos = [];

        if (preg_match('/<tfd:TimbreFiscalDigital[^>]*>/', $contenido, $match)) {
            $timbre = $match[0];

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
                if (preg_match('/' . $campo . '="([^"]*)"/', $timbre, $matches)) {
                    $datos[$columna] = $matches[1];
                }
            }
        }

        return $datos;
    }

    private function extraerConceptos($contenido)
    {
        $conceptos = [];

        if (preg_match_all('/<cfdi:Concepto[^>]*([^>\/]*)\/?>/', $contenido, $matches)) {
            foreach ($matches[0] as $concepto) {
                $datos = [];

                $campos = [
                    'ClaveProdServ' => 'clave_prodserv',
                    'NoIdentificacion' => 'no_identificacion',
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
                    if (preg_match('/' . $campo . '="([^"]*)"/', $concepto, $match)) {
                        $datos[$columna] = $match[1];
                    }
                }

                if (!empty($datos)) {
                    $conceptos[] = $datos;
                }
            }
        }

        return $conceptos;
    }

    private function extraerImpuestos($contenido)
    {
        $impuestos = [];

        // Traslados
        if (preg_match_all('/<cfdi:Traslado[^>]*([^>\/]*)\/?>/', $contenido, $matches)) {
            foreach ($matches[0] as $traslado) {
                $datos = ['tipo' => 'traslado'];

                $campos = [
                    'Base' => 'base',
                    'Impuesto' => 'impuesto',
                    'TipoFactor' => 'tipo_factor',
                    'TasaOCuota' => 'tasa_cuota',
                    'Importe' => 'importe'
                ];

                foreach ($campos as $campo => $columna) {
                    if (preg_match('/' . $campo . '="([^"]*)"/', $traslado, $match)) {
                        $datos[$columna] = $match[1];
                    }
                }

                if (!empty($datos) && count($datos) > 1) {
                    $impuestos[] = $datos;
                }
            }
        }

        // Retenciones
        if (preg_match_all('/<cfdi:Retencion[^>]*([^>\/]*)\/?>/', $contenido, $matches)) {
            foreach ($matches[0] as $retencion) {
                $datos = ['tipo' => 'retencion'];

                $campos = [
                    'Base' => 'base',
                    'Impuesto' => 'impuesto',
                    'TipoFactor' => 'tipo_factor',
                    'TasaOCuota' => 'tasa_cuota',
                    'Importe' => 'importe'
                ];

                foreach ($campos as $campo => $columna) {
                    if (preg_match('/' . $campo . '="([^"]*)"/', $retencion, $match)) {
                        $datos[$columna] = $match[1];
                    }
                }

                if (!empty($datos) && count($datos) > 1) {
                    $impuestos[] = $datos;
                }
            }
        }

        return $impuestos;
    }

    private function extraerComplementoPagos($contenido)
    {
        if (!preg_match('/TipoDeComprobante\s*=\s*["\']P["\']/', $contenido)) {
            return null;
        }

        $resultado = [];

        if (preg_match('/<pago10:Pagos[^>]*>.*?<\/pago10:Pagos>/s', $contenido, $matches)) {
            $complemento = $matches[0];

            // Extraer versión
            $version = '1.0';
            if (preg_match('/Version\s*=\s*["\']([^"\']*)["\']/', $complemento, $versionMatch)) {
                $version = $versionMatch[1];
            }

            if (preg_match_all('/<pago10:Pago[^>]*>.*?<\/pago10:Pago>/s', $complemento, $pagosMatches)) {
                foreach ($pagosMatches[0] as $pagoCompleto) {
                    $pago = ['version' => $version];

                    // Extraer atributos del pago
                    if (preg_match('/<pago10:Pago\s+([^>]*)>/', $pagoCompleto, $pagoAttr)) {
                        $atributos = $pagoAttr[1];

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
                            if (preg_match('/' . $campo . '\s*=\s*["\']([^"\']*)["\']/', $atributos, $matches)) {
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
                        $pago['documentos_relacionados'] = $documentos;
                        $resultado[] = $pago;
                    }
                }
            }
        }

        return !empty($resultado) ? $resultado : null;
    }

    private function insertarCFDI($datos)
    {
        $sql = "INSERT INTO cfdi (
            uuid, tipo, serie, folio, fecha, fecha_timbrado,
            rfc_emisor, nombre_emisor, regimen_fiscal_emisor,
            rfc_receptor, nombre_receptor, regimen_fiscal_receptor, uso_cfdi,
            lugar_expedicion, moneda, tipo_cambio, subtotal, descuento, total,
            metodo_pago, forma_pago, exportacion, observaciones, archivo_xml,
            complemento_tipo, complemento_json, rfc_consultado, direccion_flujo,
            version, sello_cfd, sello_sat, no_certificado_sat, rfc_prov_certif,
            estatus_sat, cfdi_relacionados, no_certificado, certificado, condiciones_de_pago
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            $datos['uuid'],
            $datos['tipo'],
            $datos['serie'],
            $datos['folio'],
            $datos['fecha'],
            $datos['fecha_timbrado'],
            $datos['rfc_emisor'],
            $datos['nombre_emisor'],
            $datos['regimen_fiscal_emisor'],
            $datos['rfc_receptor'],
            $datos['nombre_receptor'],
            $datos['regimen_fiscal_receptor'],
            $datos['uso_cfdi'],
            $datos['lugar_expedicion'],
            $datos['moneda'],
            $datos['tipo_cambio'],
            $datos['subtotal'],
            $datos['descuento'],
            $datos['total'],
            $datos['metodo_pago'],
            $datos['forma_pago'],
            $datos['exportacion'],
            $datos['observaciones'] ?? null,
            $datos['archivo_xml'],
            $datos['complemento_tipo'],
            $datos['complemento_json'],
            $datos['rfc_consultado'] ?? null,
            $datos['direccion_flujo'] ?? null,
            $datos['version'] ?? null,
            $datos['sello_cfd'] ?? null,
            $datos['sello_sat'] ?? null,
            $datos['no_certificado_sat'] ?? null,
            $datos['rfc_prov_certif'] ?? null,
            $datos['estatus_sat'] ?? null,
            $datos['cfdi_relacionados'] ?? null,
            $datos['no_certificado'] ?? null,
            $datos['certificado'] ?? null,
            $datos['condiciones_de_pago'] ?? null
        ]);

        return $result ? $this->pdo->lastInsertId() : false;
    }

    private function insertarTimbre($cfdi_id, $timbre)
    {
        $sql = "INSERT INTO cfdi_timbre_fiscal (
            cfdi_id, version, uuid, fecha_timbrado, rfc_prov_certif,
            sello_cfd, no_certificado_sat, sello_sat
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $cfdi_id,
            $timbre['version'],
            $timbre['uuid'],
            $timbre['fecha_timbrado'],
            $timbre['rfc_prov_certif'],
            $timbre['sello_cfd'],
            $timbre['no_certificado_sat'],
            $timbre['sello_sat']
        ]);
    }

    private function insertarConceptos($cfdi_id, $conceptos)
    {
        $sql = "INSERT INTO cfdi_conceptos (
            cfdi_id, clave_prodserv, no_identificacion, cantidad, clave_unidad,
            unidad, descripcion, valor_unitario, importe, descuento, objeto_imp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);

        foreach ($conceptos as $concepto) {
            $stmt->execute([
                $cfdi_id,
                $concepto['clave_prodserv'] ?? null,
                $concepto['no_identificacion'] ?? null,
                $concepto['cantidad'] ?? null,
                $concepto['clave_unidad'] ?? null,
                $concepto['unidad'] ?? null,
                $concepto['descripcion'] ?? null,
                $concepto['valor_unitario'] ?? null,
                $concepto['importe'] ?? null,
                $concepto['descuento'] ?? null,
                $concepto['objeto_imp'] ?? null
            ]);
        }
    }

    private function insertarImpuestos($cfdi_id, $impuestos)
    {
        $sql = "INSERT INTO cfdi_impuestos (
            cfdi_id, tipo, base, impuesto, tipo_factor, tasa_cuota, importe
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);

        foreach ($impuestos as $impuesto) {
            $stmt->execute([
                $cfdi_id,
                $impuesto['tipo'],
                $impuesto['base'] ?? null,
                $impuesto['impuesto'] ?? null,
                $impuesto['tipo_factor'] ?? null,
                $impuesto['tasa_cuota'] ?? null,
                $impuesto['importe'] ?? null
            ]);
        }
    }

    private function insertarPagos($cfdi_id, $pagos)
    {
        foreach ($pagos as $pago) {
            $pago_id = $this->insertarPago($cfdi_id, $pago);

            if ($pago_id && !empty($pago['documentos_relacionados'])) {
                foreach ($pago['documentos_relacionados'] as $documento) {
                    $this->insertarDocumentoRelacionado($pago_id, $documento);
                }
            }
        }
    }

    private function insertarPago($cfdi_id, $pago)
    {
        $sql = "INSERT INTO cfdi_pagos (
            cfdi_id, version, fecha_pago, forma_pago, moneda, tipo_cambio, monto,
            num_operacion, rfc_emisor_cuenta_ordenante, nombre_banco_extranjero,
            cuenta_ordenante, rfc_emisor_cuenta_beneficiario, cuenta_beneficiario,
            tipo_cadena_pago, certificado_pago, cadena_pago, sello_pago
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            $cfdi_id,
            $pago['version'] ?? null,
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

        return $result ? $this->pdo->lastInsertId() : false;
    }

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

    private function mostrarEstadisticas()
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "ESTADÍSTICAS FINALES DE PROCESAMIENTO\n";
        echo str_repeat("=", 60) . "\n";
        echo "Archivos procesados: " . number_format($this->stats['procesados']) . "\n";
        echo "CFDIs insertados: " . number_format($this->stats['insertados']) . "\n";
        echo "Errores: " . number_format($this->stats['errores']) . "\n";
        echo "CFDI 3.3: " . number_format($this->stats['cfdi_33']) . "\n";
        echo "CFDI 4.0: " . number_format($this->stats['cfdi_40']) . "\n";
        echo "Complementos de pago: " . number_format($this->stats['pagos']) . "\n";
        echo "Timbres fiscales: " . number_format($this->stats['timbres']) . "\n";
        echo "Conceptos insertados: " . number_format($this->stats['conceptos']) . "\n";
        echo "Impuestos insertados: " . number_format($this->stats['impuestos']) . "\n";

        echo "\n--- ARCHIVOS OMITIDOS ---\n";
        echo "Archivos vacíos/corruptos: " . number_format($this->stats['archivos_vacios']) . "\n";
        echo "Archivos sin UUID: " . number_format($this->stats['sin_uuid']) . "\n";
        echo "UUIDs duplicados: " . number_format($this->stats['duplicados']) . "\n";

        $totalOmitidos = $this->stats['archivos_vacios'] + $this->stats['sin_uuid'] + $this->stats['duplicados'];
        echo "Total omitidos: " . number_format($totalOmitidos) . "\n";

        if ($this->stats['procesados'] > 0) {
            $tasaExito = round(($this->stats['insertados'] / $this->stats['procesados']) * 100, 2);
            echo "\nTasa de éxito: $tasaExito%\n";
            echo "Explicación: " . number_format($this->stats['insertados']) . " insertados + " . number_format($totalOmitidos) . " omitidos = " . number_format($this->stats['insertados'] + $totalOmitidos) . " de " . number_format($this->stats['procesados']) . " procesados\n";
        }
        echo str_repeat("=", 60) . "\n";
    }
}

/*
// CÓDIGO DE EJECUCIÓN AUTOMÁTICA COMENTADO
// Para ejecutar el procesador, usar procesador_optimizado.php o test_procesador.php

// Ejecutar procesamiento
try {
    $pdo = getDatabase();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== PROCESADOR CFDI COMPLETO 3.3 Y 4.0 ===\n\n";
    
    $procesador = new ProcesadorCFDICompleto($pdo);
    
    // Limpiar tablas
    $procesador->limpiarTablas();
    
    // Procesar archivos
    $procesador->procesarDirectorio('storage/sat_downloads');
    
    echo "\n🎉 Procesamiento completado exitosamente\n";
    
} catch (Exception $e) {
    echo "❌ Error fatal: " . $e->getMessage() . "\n";
}
*/
