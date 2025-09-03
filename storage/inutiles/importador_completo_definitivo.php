<?php

/**
 * IMPORTADOR COMPLETO SAT - VERSIÓN DEFINITIVA
 * Extrae TODOS los datos de XMLs CFDI para regenerar PDFs y reportes completos
 * Maneja CFDI 3.3 y 4.0 con todas sus diferencias
 */

require_once __DIR__ . '/src/config/database.php';

class ImportadorCompletoSAT
{
    private $pdo;
    private $stats = [
        'total_archivos' => 0,
        'cfdi_importados' => 0,
        'conceptos_insertados' => 0,
        'impuestos_insertados' => 0,
        'timbres_insertados' => 0,
        'pagos_procesados' => 0,
        'documentos_relacionados' => 0,
        'impuestos_dr_insertados' => 0,
        'totales_pagos_insertados' => 0,
        'errores' => 0,
        'tipos_cfdi' => []
    ];

    private $archivos_fallidos = [];
    private $log_errores = [];
    private $log_archivo;

    public function __construct()
    {
        $this->pdo = getDatabase();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function ejecutar()
    {
        echo "🚀 IMPORTADOR COMPLETO SAT - VERSIÓN DEFINITIVA\n";
        echo str_repeat("=", 60) . "\n\n";

        $this->escanearArchivos();
        $this->generarReporteErrores();
        $this->mostrarResumen();
    }

    private function escanearArchivos()
    {
        echo "📁 ESCANEANDO ARCHIVOS XML...\n";

        $directorio = 'storage/sat_downloads';
        $archivos = $this->obtenerArchivosXML($directorio);

        $this->stats['total_archivos'] = count($archivos);
        echo "📊 Total de archivos encontrados: " . number_format($this->stats['total_archivos']) . "\n\n";

        $procesados = 0;
        $lote = 0;

        foreach ($archivos as $archivo) {
            $procesados++;
            $lote++;

            if ($lote % 100 == 0) {
                echo "📈 Procesados: " . number_format($procesados) . " / " . number_format($this->stats['total_archivos']) .
                    " (" . round(($procesados / $this->stats['total_archivos']) * 100, 2) . "%)\n";
            }

            $this->procesarArchivo($archivo);

            // Pausa cada 1000 archivos
            if ($lote % 1000 == 0) {
                echo "⏸️  Pausa técnica...\n";
                usleep(100000); // 0.1 segundos
            }
        }

        echo "\n✅ PROCESAMIENTO DE ARCHIVOS COMPLETADO\n\n";
    }

    private function obtenerArchivosXML($directorio)
    {
        $archivos = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directorio)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                $archivos[] = $file->getPathname();
            }
        }

        return $archivos;
    }

    private function procesarArchivo($rutaArchivo)
    {
        try {
            // Iniciar transacción para integridad
            $this->pdo->beginTransaction();

            // Extraer información de la ruta
            $info = $this->extraerInfoRuta($rutaArchivo);

            // Leer y parsear XML
            $xmlContent = file_get_contents($rutaArchivo);
            if (!$xmlContent) {
                $this->pdo->rollback();
                $this->registrarError($rutaArchivo, 'No se pudo leer el archivo XML');
                return;
            }

            $xml = simplexml_load_string($xmlContent);
            if (!$xml) {
                $this->pdo->rollback();
                $this->registrarError($rutaArchivo, 'Error al parsear XML - formato inválido');
                return;
            }

            // Verificar que sea un CFDI válido
            if (!isset($xml['Version'])) {
                $this->pdo->rollback();
                $this->registrarError($rutaArchivo, 'No es un CFDI válido - falta atributo Version');
                return;
            }

            // Insertar CFDI principal
            $cfdi_id = $this->insertarCFDI($xml, $info, $rutaArchivo);

            if (!$cfdi_id) {
                $this->pdo->rollback();
                $this->registrarError($rutaArchivo, 'Error al insertar CFDI principal');
                return;
            }

            $this->stats['cfdi_importados']++;

            // Procesar conceptos e impuestos
            $this->procesarConceptos($xml, $cfdi_id);

            // Procesar complementos específicos en tabla cfdi_complementos
            $this->procesarComplementosEspecificos($xml, $cfdi_id);

            // Procesar timbre fiscal
            $this->procesarTimbreFiscal($xml, $cfdi_id);

            // Si es tipo P, procesar complemento de pago
            $tipoComprobante = (string)$xml['TipoDeComprobante'];
            if ($tipoComprobante === 'P') {
                $this->procesarComplementoPago($xml, $cfdi_id);
            }

            // Contar tipos de CFDI
            if (!isset($this->stats['tipos_cfdi'][$tipoComprobante])) {
                $this->stats['tipos_cfdi'][$tipoComprobante] = 0;
            }
            $this->stats['tipos_cfdi'][$tipoComprobante]++;

            // Confirmar transacción
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollback();
            $this->registrarError($rutaArchivo, 'Error de base de datos: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->pdo->rollback();
            $this->registrarError($rutaArchivo, 'Error general: ' . $e->getMessage());
        }
    }

    private function extraerInfoRuta($rutaArchivo)
    {
        // Formato: storage/sat_downloads/RFC/TIPO/AÑO/MES/archivo.xml
        $partes = explode(DIRECTORY_SEPARATOR, str_replace('/', DIRECTORY_SEPARATOR, $rutaArchivo));

        return [
            'rfc' => $partes[count($partes) - 5] ?? '',
            'tipo' => $partes[count($partes) - 4] ?? '',
            'año' => $partes[count($partes) - 3] ?? '',
            'mes' => $partes[count($partes) - 2] ?? '',
            'archivo' => basename($rutaArchivo)
        ];
    }

    private function insertarCFDI($xml, $info, $rutaArchivo)
    {
        try {
            $comprobante = $xml->attributes();

            // Detectar versión CFDI para manejar campos específicos
            $version = (string)($comprobante->Version ?? '3.3');
            $esCFDI40 = version_compare($version, '4.0', '>=');

            // Extraer UUID para verificar duplicados
            $uuid = $this->extraerUUID($xml);

            // Verificar si ya existe este CFDI
            $checkStmt = $this->pdo->prepare("SELECT id FROM cfdi WHERE uuid = ? AND archivo_xml = ?");
            $checkStmt->execute([$uuid, $rutaArchivo]);
            $existente = $checkStmt->fetch();

            if ($existente) {
                // Ya existe, actualizar en lugar de insertar
                return $this->actualizarCFDI($existente['id'], $xml, $info, $rutaArchivo);
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO cfdi (
                    uuid, tipo, serie, folio, fecha, fecha_timbrado,
                    rfc_emisor, nombre_emisor, regimen_fiscal_emisor,
                    rfc_receptor, nombre_receptor, regimen_fiscal_receptor,
                    uso_cfdi, lugar_expedicion, moneda, tipo_cambio,
                    subtotal, descuento, total, metodo_pago, forma_pago,
                    exportacion, observaciones, archivo_xml, complemento_tipo, complemento_json,
                    rfc_consultado, direccion_flujo, version, sello_cfd,
                    sello_sat, no_certificado_sat, rfc_prov_certif,
                    estatus_sat, cfdi_relacionados, no_certificado, certificado, condiciones_de_pago
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            // Extraer datos necesarios
            $uuid = $this->extraerUUID($xml);
            $datosTimbre = $this->extraerDatosTimbre($xml);
            $complementoTipo = $this->detectarTipoComplemento($xml);
            $complementoJson = $this->extraerComplementoJson($xml);
            $cfdiRelacionados = $this->extraerCfdiRelacionados($xml);

            // Determinar dirección del flujo basado en RFC consultado
            $rfcConsultado = $info['rfc'] ?? '';
            $rfcEmisor = (string)$xml->Emisor['Rfc'] ?? '';
            $direccionFlujo = ($rfcConsultado === $rfcEmisor) ? 'EMITIDA' : 'RECIBIDA';

            $stmt->execute([
                $uuid,                                         // uuid
                (string)$comprobante->TipoDeComprobante ?? '', // tipo
                (string)$comprobante->Serie ?? null,          // serie
                (string)$comprobante->Folio ?? null,          // folio
                $this->formatearFecha((string)$comprobante->Fecha), // fecha
                $this->formatearFecha($datosTimbre['fecha_timbrado']), // fecha_timbrado
                (string)$xml->Emisor['Rfc'] ?? '',            // rfc_emisor
                (string)$xml->Emisor['Nombre'] ?? '',         // nombre_emisor
                (string)$xml->Emisor['RegimenFiscal'] ?? '',  // regimen_fiscal_emisor
                (string)$xml->Receptor['Rfc'] ?? '',          // rfc_receptor
                (string)$xml->Receptor['Nombre'] ?? '',       // nombre_receptor
                (string)$xml->Receptor['RegimenFiscal'] ?? '', // regimen_fiscal_receptor (CFDI 4.0)
                (string)$xml->Receptor['UsoCFDI'] ?? '',      // uso_cfdi
                (string)$comprobante->LugarExpedicion ?? '',  // lugar_expedicion
                (string)$comprobante->Moneda ?? 'MXN',        // moneda
                (float)($comprobante->TipoCambio ?? 1.0),     // tipo_cambio
                (float)($comprobante->SubTotal ?? 0.0),       // subtotal
                (float)($comprobante->Descuento ?? 0.0),      // descuento
                (float)($comprobante->Total ?? 0.0),          // total
                (string)$comprobante->MetodoPago ?? null,     // metodo_pago
                (string)$comprobante->FormaPago ?? null,      // forma_pago
                $esCFDI40 ? (string)$comprobante->Exportacion ?? '01' : '01', // exportacion
                (string)$comprobante->Observaciones ?? null, // observaciones
                $rutaArchivo,                                 // archivo_xml
                $complementoTipo,                             // complemento_tipo
                $complementoJson,                             // complemento_json
                $rfcConsultado,                               // rfc_consultado
                $direccionFlujo,                              // direccion_flujo
                $version,                                     // version
                $datosTimbre['sello_cfd'],                    // sello_cfd
                $datosTimbre['sello_sat'],                    // sello_sat
                $datosTimbre['no_certificado_sat'],           // no_certificado_sat
                $datosTimbre['rfc_prov_certif'],              // rfc_prov_certif
                'Vigente',                                    // estatus_sat (por defecto)
                $cfdiRelacionados,                            // cfdi_relacionados
                (string)$comprobante->NoCertificado ?? null,  // no_certificado
                (string)$comprobante->Certificado ?? null,    // certificado
                (string)$comprobante->CondicionesDePago ?? null // condiciones_de_pago
            ]);

            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error SQL en insertarCFDI: " . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log("Error general en insertarCFDI: " . $e->getMessage());
            return null;
        }
    }

    private function actualizarCFDI($cfdi_id, $xml, $info, $rutaArchivo)
    {
        try {
            $comprobante = $xml->attributes();
            $version = (string)($comprobante->Version ?? '3.3');
            $esCFDI40 = version_compare($version, '4.0', '>=');

            // Extraer datos necesarios
            $uuid = $this->extraerUUID($xml);
            $datosTimbre = $this->extraerDatosTimbre($xml);
            $complementoTipo = $this->detectarTipoComplemento($xml);
            $complementoJson = $this->extraerComplementoJson($xml);
            $cfdiRelacionados = $this->extraerCfdiRelacionados($xml);

            $rfcConsultado = $info['rfc'] ?? '';
            $rfcEmisor = (string)$xml->Emisor['Rfc'] ?? '';
            $direccionFlujo = ($rfcConsultado === $rfcEmisor) ? 'EMITIDA' : 'RECIBIDA';

            $stmt = $this->pdo->prepare("
                UPDATE cfdi SET
                    uuid = ?, tipo = ?, serie = ?, folio = ?, fecha = ?, fecha_timbrado = ?,
                    rfc_emisor = ?, nombre_emisor = ?, regimen_fiscal_emisor = ?,
                    rfc_receptor = ?, nombre_receptor = ?, regimen_fiscal_receptor = ?,
                    uso_cfdi = ?, lugar_expedicion = ?, moneda = ?, tipo_cambio = ?,
                    subtotal = ?, descuento = ?, total = ?, metodo_pago = ?, forma_pago = ?,
                    exportacion = ?, observaciones = ?, archivo_xml = ?, complemento_tipo = ?, complemento_json = ?,
                    rfc_consultado = ?, direccion_flujo = ?, version = ?, sello_cfd = ?,
                    sello_sat = ?, no_certificado_sat = ?, rfc_prov_certif = ?,
                    estatus_sat = ?, cfdi_relacionados = ?, no_certificado = ?, certificado = ?, condiciones_de_pago = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $uuid,
                (string)$comprobante->TipoDeComprobante ?? '',
                (string)$comprobante->Serie ?? null,
                (string)$comprobante->Folio ?? null,
                $this->formatearFecha((string)$comprobante->Fecha),
                $this->formatearFecha($datosTimbre['fecha_timbrado']),
                (string)$xml->Emisor['Rfc'] ?? '',
                (string)$xml->Emisor['Nombre'] ?? '',
                (string)$xml->Emisor['RegimenFiscal'] ?? '',
                (string)$xml->Receptor['Rfc'] ?? '',
                (string)$xml->Receptor['Nombre'] ?? '',
                (string)$xml->Receptor['RegimenFiscal'] ?? '',
                (string)$xml->Receptor['UsoCFDI'] ?? '',
                (string)$comprobante->LugarExpedicion ?? '',
                (string)$comprobante->Moneda ?? 'MXN',
                (float)($comprobante->TipoCambio ?? 1.0),
                (float)($comprobante->SubTotal ?? 0.0),
                (float)($comprobante->Descuento ?? 0.0),
                (float)($comprobante->Total ?? 0.0),
                (string)$comprobante->MetodoPago ?? null,
                (string)$comprobante->FormaPago ?? null,
                $esCFDI40 ? (string)$comprobante->Exportacion ?? '01' : '01',
                (string)$comprobante->Observaciones ?? null,
                $rutaArchivo,
                $complementoTipo,
                $complementoJson,
                $rfcConsultado,
                $direccionFlujo,
                $version,
                $datosTimbre['sello_cfd'],
                $datosTimbre['sello_sat'],
                $datosTimbre['no_certificado_sat'],
                $datosTimbre['rfc_prov_certif'],
                'Vigente',
                $cfdiRelacionados,
                (string)$comprobante->NoCertificado ?? null,
                (string)$comprobante->Certificado ?? null,
                (string)$comprobante->CondicionesDePago ?? null,
                $cfdi_id
            ]);

            return $cfdi_id;
        } catch (PDOException $e) {
            error_log("Error SQL en actualizarCFDI: " . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log("Error general en actualizarCFDI: " . $e->getMessage());
            return null;
        }
    }

    private function procesarConceptos($xml, $cfdi_id)
    {
        if (!isset($xml->Conceptos->Concepto)) {
            return;
        }

        // Limpiar conceptos existentes para evitar duplicados
        $this->pdo->prepare("DELETE FROM cfdi_conceptos WHERE cfdi_id = ?")->execute([$cfdi_id]);
        $this->pdo->prepare("DELETE FROM cfdi_impuestos WHERE cfdi_id = ?")->execute([$cfdi_id]);

        foreach ($xml->Conceptos->Concepto as $concepto) {
            try {
                $attrs = $concepto->attributes();

                // Detectar si es CFDI 4.0 para campo ObjetoImp
                $version = (string)($xml['Version'] ?? '3.3');
                $esCFDI40 = version_compare($version, '4.0', '>=');

                $stmt = $this->pdo->prepare("
                    INSERT INTO cfdi_conceptos (
                        cfdi_id, clave_prodserv, no_identificacion, cantidad, clave_unidad,
                        unidad, descripcion, valor_unitario, importe,
                        descuento, objeto_imp, cuenta_predial
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $cfdi_id,
                    (string)($attrs->ClaveProdServ ?? ''),
                    !empty($attrs->NoIdentificacion) ? (string)$attrs->NoIdentificacion : null,
                    (float)($attrs->Cantidad ?? 0.0),
                    (string)($attrs->ClaveUnidad ?? ''),
                    !empty($attrs->Unidad) ? (string)$attrs->Unidad : null,
                    (string)($attrs->Descripcion ?? ''),
                    (float)($attrs->ValorUnitario ?? 0.0),
                    (float)($attrs->Importe ?? 0.0),
                    (float)($attrs->Descuento ?? 0.0),
                    // Campo específico CFDI 4.0
                    $esCFDI40 && !empty($attrs->ObjetoImp) ? (string)$attrs->ObjetoImp : null,
                    // Campo cuenta predial (opcional en ambas versiones)
                    !empty($attrs->CuentaPredial) ? (string)$attrs->CuentaPredial : null
                ]);

                $concepto_id = $this->pdo->lastInsertId();
                $this->stats['conceptos_insertados']++;

                // Procesar impuestos del concepto
                $this->procesarImpuestosConcepto($concepto, $cfdi_id, $concepto_id);
            } catch (PDOException $e) {
                error_log("Error insertando concepto: " . $e->getMessage());
                $this->registrarError("Concepto CFDI ID: $cfdi_id", 'Error SQL en concepto: ' . $e->getMessage());
            }
        }
    }

    private function procesarImpuestosConcepto($concepto, $cfdi_id, $concepto_id)
    {
        if (!isset($concepto->Impuestos)) {
            return;
        }

        // Procesar traslados (IVA que se cobra)
        if (isset($concepto->Impuestos->Traslados->Traslado)) {
            foreach ($concepto->Impuestos->Traslados->Traslado as $traslado) {
                $this->insertarImpuesto($cfdi_id, $traslado, 'traslado', $concepto_id);
            }
        }

        // Procesar retenciones (ISR que se retiene)
        if (isset($concepto->Impuestos->Retenciones->Retencion)) {
            foreach ($concepto->Impuestos->Retenciones->Retencion as $retencion) {
                $this->insertarImpuesto($cfdi_id, $retencion, 'retencion', $concepto_id);
            }
        }
    }

    private function insertarImpuesto($cfdi_id, $impuesto, $tipo, $concepto_id = null)
    {
        try {
            $attrs = $impuesto->attributes();

            $stmt = $this->pdo->prepare("
                INSERT INTO cfdi_impuestos (
                    cfdi_id, concepto_id, tipo, impuesto, tipo_factor,
                    tasa_cuota, base, importe
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $cfdi_id,
                $concepto_id,
                $tipo,
                (string)($attrs->Impuesto ?? ''),
                !empty($attrs->TipoFactor) ? (string)$attrs->TipoFactor : null,
                !empty($attrs->TasaOCuota) ? (float)$attrs->TasaOCuota : null,
                !empty($attrs->Base) ? (float)$attrs->Base : null,
                !empty($attrs->Importe) ? (float)$attrs->Importe : null
            ]);

            $this->stats['impuestos_insertados']++;
        } catch (PDOException $e) {
            error_log("Error insertando impuesto: " . $e->getMessage());
            $this->registrarError("Impuesto CFDI ID: $cfdi_id", 'Error SQL en impuesto: ' . $e->getMessage());
        }
    }

    private function procesarTimbreFiscal($xml, $cfdi_id)
    {
        $namespaces = $xml->getNamespaces(true);
        if (!isset($namespaces['tfd'])) {
            return;
        }

        // Limpiar timbres existentes para evitar duplicados
        $this->pdo->prepare("DELETE FROM cfdi_timbre_fiscal WHERE cfdi_id = ?")->execute([$cfdi_id]);

        $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
        $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');

        if (empty($timbres)) {
            return;
        }

        try {
            $timbre = $timbres[0];
            $attrs = $timbre->attributes();

            $stmt = $this->pdo->prepare("
                INSERT INTO cfdi_timbre_fiscal (
                    cfdi_id, uuid, fecha_timbrado, sello_cfd, sello_sat,
                    no_certificado_sat, rfc_prov_certif, version
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $cfdi_id,
                (string)($attrs->UUID ?? ''),
                $this->formatearFecha((string)$attrs->FechaTimbrado),
                !empty($attrs->SelloCFD) ? (string)$attrs->SelloCFD : null,
                !empty($attrs->SelloSAT) ? (string)$attrs->SelloSAT : null,
                !empty($attrs->NoCertificadoSAT) ? (string)$attrs->NoCertificadoSAT : null,
                !empty($attrs->RfcProvCertif) ? (string)$attrs->RfcProvCertif : null,
                !empty($attrs->Version) ? (string)$attrs->Version : null
            ]);

            $this->stats['timbres_insertados']++;
        } catch (PDOException $e) {
            error_log("Error insertando timbre fiscal: " . $e->getMessage());
            $this->registrarError("Timbre CFDI ID: $cfdi_id", 'Error SQL en timbre: ' . $e->getMessage());
        }
    }

    private function procesarComplementosEspecificos($xml, $cfdi_id)
    {
        if (!isset($xml->Complemento)) {
            return;
        }

        foreach ($xml->Complemento as $complemento) {
            $namespaces = $complemento->getNamespaces(true);

            foreach ($namespaces as $prefix => $uri) {
                if (empty($prefix)) continue;

                $tipoComplemento = $this->detectarTipoComplementoPorURI($uri);
                if ($tipoComplemento) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO cfdi_complementos (cfdi_id, tipo, datos_json)
                        VALUES (?, ?, ?)
                    ");

                    $stmt->execute([
                        $cfdi_id,
                        $tipoComplemento,
                        json_encode([
                            'namespace' => $uri,
                            'prefix' => $prefix,
                            'xml' => $complemento->asXML()
                        ])
                    ]);
                }
            }
        }
    }

    private function detectarTipoComplementoPorURI($uri)
    {
        $tipos = [
            'http://www.sat.gob.mx/Pagos' => 'Pagos',
            'http://www.sat.gob.mx/Pagos20' => 'Pagos20',
            'http://www.sat.gob.mx/nomina12' => 'Nomina',
            'http://www.sat.gob.mx/CartaPorte20' => 'CartaPorte',
            'http://www.sat.gob.mx/iedu' => 'InstitucionesEducativas',
            'http://www.sat.gob.mx/donat' => 'Donatarias',
            'http://www.sat.gob.mx/divisas' => 'Divisas',
            'http://www.sat.gob.mx/implocal' => 'ImpuestosLocales'
        ];

        return $tipos[$uri] ?? null;
    }

    private function procesarComplementoPago($xml, $cfdi_id)
    {
        $namespaces = $xml->getNamespaces(true);
        $pagos_encontrados = [];

        // Limpiar pagos existentes para evitar duplicados
        $this->pdo->prepare("DELETE FROM cfdi_pago_documentos_relacionados WHERE pago_id IN (SELECT id FROM cfdi_pagos WHERE cfdi_id = ?)")->execute([$cfdi_id]);
        $this->pdo->prepare("DELETE FROM cfdi_pagos WHERE cfdi_id = ?")->execute([$cfdi_id]);

        // CFDI 4.0 - pago20
        if (isset($namespaces['pago20'])) {
            $xml->registerXPathNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');
            $pagos_encontrados = $xml->xpath('//pago20:Pago');
        }
        // CFDI 3.3 - pago10
        elseif (isset($namespaces['pago10'])) {
            $xml->registerXPathNamespace('pago10', 'http://www.sat.gob.mx/Pagos');
            $pagos_encontrados = $xml->xpath('//pago10:Pago');
        }

        foreach ($pagos_encontrados as $pago) {
            $this->insertarPago($pago, $cfdi_id);
        }
    }

    private function insertarPago($pago, $cfdi_id)
    {
        try {
            $attrs = $pago->attributes();

            $stmt = $this->pdo->prepare("
                INSERT INTO cfdi_pagos (
                    cfdi_id, version, fecha_pago, forma_pago, moneda, 
                    tipo_cambio, monto, num_operacion, rfc_emisor_cuenta_ordenante,
                    nombre_banco_extranjero, cuenta_ordenante, rfc_emisor_cuenta_beneficiario,
                    cuenta_beneficiario, tipo_cadena_pago, certificado_pago,
                    cadena_pago, sello_pago
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $fechaPago = isset($attrs->FechaPago) ?
                $this->formatearFecha((string)$attrs->FechaPago) : null;

            $stmt->execute([
                $cfdi_id,
                '2.0', // Version por defecto
                $fechaPago,
                !empty($attrs->FormaDePagoP) ? (string)$attrs->FormaDePagoP : null,
                !empty($attrs->MonedaP) ? (string)$attrs->MonedaP : 'MXN',
                !empty($attrs->TipoCambioP) ? (float)$attrs->TipoCambioP : 1.0,
                !empty($attrs->Monto) ? (float)$attrs->Monto : null,
                !empty($attrs->NumOperacion) ? (string)$attrs->NumOperacion : null,
                !empty($attrs->RfcEmisorCtaOrd) ? (string)$attrs->RfcEmisorCtaOrd : null,
                !empty($attrs->NomBancoOrdExt) ? (string)$attrs->NomBancoOrdExt : null,
                !empty($attrs->CtaOrdenante) ? (string)$attrs->CtaOrdenante : null,
                !empty($attrs->RfcEmisorCtaBen) ? (string)$attrs->RfcEmisorCtaBen : null,
                !empty($attrs->CtaBeneficiario) ? (string)$attrs->CtaBeneficiario : null,
                !empty($attrs->TipoCadPago) ? (string)$attrs->TipoCadPago : null,
                !empty($attrs->CertPago) ? (string)$attrs->CertPago : null,
                !empty($attrs->CadPago) ? (string)$attrs->CadPago : null,
                !empty($attrs->SelloPago) ? (string)$attrs->SelloPago : null
            ]);

            $pago_id = $this->pdo->lastInsertId();
            $this->stats['pagos_procesados']++;

            // Procesar totales del pago si existen directamente en el pago
            if (isset($pago->Totales)) {
                $this->procesarTotalesPago($pago->Totales, $pago_id);
            }

            // Procesar documentos relacionados
            if (isset($pago->DoctoRelacionado)) {
                foreach ($pago->DoctoRelacionado as $doc) {
                    $this->insertarDocumentoRelacionado($doc, $pago_id);
                }
            }
        } catch (Exception $e) {
            // Continuar con el siguiente pago
            error_log("Error procesando pago: " . $e->getMessage());
        }
    }

    private function insertarDocumentoRelacionado($doc, $pago_id)
    {
        try {
            $attrs = $doc->attributes();

            $stmt = $this->pdo->prepare("
                INSERT INTO cfdi_pago_documentos_relacionados (
                    pago_id, uuid_documento, serie, folio, moneda_dr,
                    equivalencia_dr, num_parcialidad, imp_saldo_ant,
                    imp_pagado, imp_saldo_insoluto, objeto_imp_dr
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $pago_id,
                (string)$attrs->IdDocumento,
                (string)$attrs->Serie ?? null,
                (string)$attrs->Folio ?? null,
                (string)$attrs->MonedaDR ?? 'MXN',
                (float)($attrs->EquivalenciaDR ?? 1.0),
                (int)($attrs->NumParcialidad ?? 1),
                (float)($attrs->ImpSaldoAnt ?? 0.0),
                (float)($attrs->ImpPagado ?? 0.0),
                (float)($attrs->ImpSaldoInsoluto ?? 0.0),
                (string)$attrs->ObjetoImpDR ?? null
            ]);

            $documento_id = $this->pdo->lastInsertId();
            $this->stats['documentos_relacionados']++;

            // Procesar impuestos del documento relacionado
            if (isset($doc->ImpuestosDR)) {
                $this->procesarImpuestosDR($doc->ImpuestosDR, $documento_id);
            }

            // Procesar totales del pago si existen en el documento
            if (isset($doc->TotalesP)) {
                $this->procesarTotalesPago($doc->TotalesP, $pago_id);
            }
        } catch (Exception $e) {
            // Continuar con el siguiente documento
            error_log("Error procesando documento relacionado: " . $e->getMessage());
        }
    }

    private function procesarImpuestosDR($impuestosDR, $documento_id)
    {
        try {
            // Procesar impuestos retenidos
            if (isset($impuestosDR->RetencionesDR)) {
                foreach ($impuestosDR->RetencionesDR->RetencionDR as $retencion) {
                    $this->insertarImpuestoDR($retencion, $documento_id, 'Retencion');
                }
            }

            // Procesar impuestos trasladados
            if (isset($impuestosDR->TrasladosDR)) {
                foreach ($impuestosDR->TrasladosDR->TrasladoDR as $traslado) {
                    $this->insertarImpuestoDR($traslado, $documento_id, 'Traslado');
                }
            }
        } catch (Exception $e) {
            error_log("Error procesando impuestos DR: " . $e->getMessage());
        }
    }

    private function insertarImpuestoDR($impuesto, $documento_id, $tipo)
    {
        try {
            $attrs = $impuesto->attributes();

            $stmt = $this->pdo->prepare("
                INSERT INTO cfdi_pago_impuestos_dr (
                    documento_relacionado_id, tipo_impuesto, codigo_impuesto,
                    base_dr, tipo_factor_dr, tasa_cuota_dr, importe_dr
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $documento_id,
                $tipo,
                (string)$attrs->ImpuestoDR ?? '',
                (float)($attrs->BaseDR ?? 0.0),
                (string)$attrs->TipoFactorDR ?? null,
                (float)($attrs->TasaOCuotaDR ?? 0.0),
                (float)($attrs->ImporteDR ?? 0.0)
            ]);

            $this->stats['impuestos_dr_insertados']++;
        } catch (Exception $e) {
            error_log("Error insertando impuesto DR: " . $e->getMessage());
        }
    }

    private function procesarTotalesPago($totalesP, $pago_id)
    {
        try {
            $attrs = $totalesP->attributes();

            $stmt = $this->pdo->prepare("
                INSERT INTO cfdi_pago_totales (
                    pago_id, total_retenciones_iva, total_retenciones_ieps,
                    total_retenciones_isr, total_traslados_base_iva16,
                    total_traslados_impuesto_iva16, total_traslados_base_iva8,
                    total_traslados_impuesto_iva8, total_traslados_base_iva0,
                    total_traslados_base_iva_exento, total_traslados_base_ieps,
                    total_traslados_impuesto_ieps, monto_total_pagos
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $pago_id,
                (float)($attrs->TotalRetencionesIVA ?? 0.0),
                (float)($attrs->TotalRetencionesIEPS ?? 0.0),
                (float)($attrs->TotalRetencionesISR ?? 0.0),
                (float)($attrs->TotalTrasladosBaseIVA16 ?? 0.0),
                (float)($attrs->TotalTrasladosImpuestoIVA16 ?? 0.0),
                (float)($attrs->TotalTrasladosBaseIVA8 ?? 0.0),
                (float)($attrs->TotalTrasladosImpuestoIVA8 ?? 0.0),
                (float)($attrs->TotalTrasladosBaseIVA0 ?? 0.0),
                (float)($attrs->TotalTrasladosBaseIVAExento ?? 0.0),
                (float)($attrs->TotalTrasladosBaseIEPS ?? 0.0),
                (float)($attrs->TotalTrasladosImpuestoIEPS ?? 0.0),
                (float)($attrs->MontoTotalPagos ?? 0.0)
            ]);

            $this->stats['totales_pagos_insertados']++;
        } catch (Exception $e) {
            error_log("Error insertando totales de pago: " . $e->getMessage());
        }
    }

    private function extraerUUID($xml)
    {
        // Buscar en TimbreFiscalDigital
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['tfd'])) {
            $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
            $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');
            if (!empty($timbres)) {
                return (string)$timbres[0]['UUID'];
            }
        }

        return '';
    }

    private function extraerFechaTimbrado($xml)
    {
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['tfd'])) {
            $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
            $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');
            if (!empty($timbres)) {
                return (string)$timbres[0]['FechaTimbrado'];
            }
        }
        return null;
    }

    private function registrarError($archivo, $error)
    {
        $this->stats['errores']++;
        $this->archivos_fallidos[] = $archivo;
        $this->log_errores[] = [
            'archivo' => $archivo,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s'),
            'info_ruta' => $this->extraerInfoRuta($archivo)
        ];
    }

    private function generarReporteErrores()
    {
        if (empty($this->archivos_fallidos)) {
            echo "✅ Todos los archivos fueron procesados exitosamente!\n\n";
            return;
        }

        echo "📋 GENERANDO REPORTE DE ARCHIVOS FALLIDOS...\n";

        // Crear archivo de log detallado
        $timestamp = date('Y-m-d_H-i-s');
        $archivo_log = "logs/importacion_errores_{$timestamp}.txt";

        // Crear directorio si no existe
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }

        $contenido_log = "REPORTE DE ERRORES - IMPORTACIÓN SAT\n";
        $contenido_log .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
        $contenido_log .= "Total archivos fallidos: " . count($this->archivos_fallidos) . "\n";
        $contenido_log .= str_repeat("=", 80) . "\n\n";

        // Agrupar errores por tipo
        $errores_agrupados = [];
        foreach ($this->log_errores as $error) {
            $tipo_error = $error['error'];
            if (!isset($errores_agrupados[$tipo_error])) {
                $errores_agrupados[$tipo_error] = [];
            }
            $errores_agrupados[$tipo_error][] = $error;
        }

        foreach ($errores_agrupados as $tipo_error => $errores) {
            $contenido_log .= "ERROR: {$tipo_error}\n";
            $contenido_log .= "Cantidad: " . count($errores) . "\n";
            $contenido_log .= str_repeat("-", 40) . "\n";

            foreach ($errores as $error) {
                $info = $error['info_ruta'];
                $contenido_log .= "RFC: {$info['rfc']} | Tipo: {$info['tipo']} | Año: {$info['año']} | Mes: {$info['mes']}\n";
                $contenido_log .= "Archivo: {$error['archivo']}\n";
                $contenido_log .= "Hora: {$error['timestamp']}\n\n";
            }
            $contenido_log .= "\n";
        }

        // Crear archivo de lista simple para reprocesamiento
        $archivo_lista = "logs/archivos_fallidos_{$timestamp}.txt";
        $lista_archivos = implode("\n", $this->archivos_fallidos);

        file_put_contents($archivo_log, $contenido_log);
        file_put_contents($archivo_lista, $lista_archivos);

        echo "📄 Reporte detallado guardado en: {$archivo_log}\n";
        echo "📄 Lista de archivos fallidos: {$archivo_lista}\n\n";

        // Mostrar resumen de errores en pantalla
        echo "❌ RESUMEN DE ERRORES:\n";
        foreach ($errores_agrupados as $tipo_error => $errores) {
            echo "  • {$tipo_error}: " . count($errores) . " archivos\n";
        }
        echo "\n";
    }

    private function formatearFecha($fecha)
    {
        if (empty($fecha)) return null;

        try {
            $dt = new DateTime($fecha);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    private function mostrarResumen()
    {
        echo "📊 RESUMEN FINAL:\n";
        echo str_repeat("=", 40) . "\n";
        echo "Total archivos procesados: " . number_format($this->stats['total_archivos']) . "\n";
        echo "CFDIs importados: " . number_format($this->stats['cfdi_importados']) . "\n";
        echo "Conceptos insertados: " . number_format($this->stats['conceptos_insertados']) . "\n";
        echo "Impuestos procesados: " . number_format($this->stats['impuestos_insertados']) . "\n";
        echo "Timbres fiscales: " . number_format($this->stats['timbres_insertados']) . "\n";
        echo "Pagos procesados: " . number_format($this->stats['pagos_procesados']) . "\n";
        echo "Documentos relacionados: " . number_format($this->stats['documentos_relacionados']) . "\n";
        echo "Impuestos DR insertados: " . number_format($this->stats['impuestos_dr_insertados']) . "\n";
        echo "Totales de pagos insertados: " . number_format($this->stats['totales_pagos_insertados']) . "\n";
        echo "Errores: " . number_format($this->stats['errores']) . "\n";

        echo "\n📋 TIPOS DE CFDI PROCESADOS:\n";
        foreach ($this->stats['tipos_cfdi'] as $tipo => $cantidad) {
            $descripcion = $this->obtenerDescripcionTipo($tipo);
            echo "  $tipo ($descripcion): " . number_format($cantidad) . "\n";
        }

        echo "\n✅ IMPORTACIÓN COMPLETA FINALIZADA\n";

        // Verificación final
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi");
        $total_cfdi = $stmt->fetch()['total'];

        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi_conceptos");
        $total_conceptos = $stmt->fetch()['total'];

        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi_impuestos");
        $total_impuestos = $stmt->fetch()['total'];

        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi_pagos");
        $total_pagos = $stmt->fetch()['total'];

        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi_pago_documentos_relacionados");
        $total_doc_relacionados = $stmt->fetch()['total'];

        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi_pago_impuestos_dr");
        $total_impuestos_dr = $stmt->fetch()['total'];

        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi_pago_totales");
        $total_totales_pago = $stmt->fetch()['total'];

        echo "\n🔍 VERIFICACIÓN FINAL:\n";
        echo "CFDIs en base de datos: " . number_format($total_cfdi) . "\n";
        echo "Conceptos en base de datos: " . number_format($total_conceptos) . "\n";
        echo "Impuestos en base de datos: " . number_format($total_impuestos) . "\n";
        echo "Pagos en base de datos: " . number_format($total_pagos) . "\n";
        echo "Documentos relacionados en base de datos: " . number_format($total_doc_relacionados) . "\n";
        echo "Impuestos DR en base de datos: " . number_format($total_impuestos_dr) . "\n";
        echo "Totales de pagos en base de datos: " . number_format($total_totales_pago) . "\n";
    }

    private function obtenerDescripcionTipo($tipo)
    {
        $tipos = [
            'I' => 'Ingreso',
            'E' => 'Egreso',
            'P' => 'Pago',
            'N' => 'Nómina',
            'T' => 'Traslado'
        ];

        return $tipos[$tipo] ?? 'Desconocido';
    }

    public function reprocesarFallidos($archivo_lista)
    {
        echo "🔄 REPROCESANDO ARCHIVOS FALLIDOS...\n";
        echo str_repeat("=", 50) . "\n\n";

        if (!file_exists($archivo_lista)) {
            echo "❌ Error: No se encontró el archivo de lista: {$archivo_lista}\n";
            return;
        }

        $archivos = file($archivo_lista, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total = count($archivos);

        echo "📁 Archivos a reprocesar: {$total}\n\n";

        // Resetear contadores para el reprocesamiento
        $this->archivos_fallidos = [];
        $this->log_errores = [];
        $stats_anteriores = $this->stats;

        $procesados = 0;
        foreach ($archivos as $archivo) {
            $procesados++;
            echo "📝 Reprocesando ({$procesados}/{$total}): " . basename($archivo) . "\n";
            $this->procesarArchivo(trim($archivo));
        }

        echo "\n✅ REPROCESAMIENTO COMPLETADO\n";
        echo "Archivos procesados exitosamente: " . ($this->stats['cfdi_importados'] - $stats_anteriores['cfdi_importados']) . "\n";
        echo "Archivos que siguen fallando: " . count($this->archivos_fallidos) . "\n";

        if (!empty($this->archivos_fallidos)) {
            $this->generarReporteErrores();
        }
    }

    private function extraerDatosTimbre($xml)
    {
        $datos = [
            'fecha_timbrado' => null,
            'sello_cfd' => '',
            'sello_sat' => '',
            'no_certificado_sat' => '',
            'rfc_prov_certif' => ''
        ];

        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['tfd'])) {
            $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
            $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');
            if (!empty($timbres)) {
                $timbre = $timbres[0];
                $attrs = $timbre->attributes();
                $datos['fecha_timbrado'] = (string)$attrs->FechaTimbrado;
                $datos['sello_cfd'] = (string)$attrs->SelloCFD;
                $datos['sello_sat'] = (string)$attrs->SelloSAT;
                $datos['no_certificado_sat'] = (string)$attrs->NoCertificadoSAT;
                $datos['rfc_prov_certif'] = (string)$attrs->RfcProvCertif;
            }
        }

        return $datos;
    }

    private function detectarTipoComplemento($xml)
    {
        if (!isset($xml->Complemento)) {
            return null;
        }

        foreach ($xml->Complemento as $complemento) {
            $namespaces = $complemento->getNamespaces(true);
            foreach ($namespaces as $prefix => $uri) {
                if (empty($prefix)) continue;

                $tipos = [
                    'http://www.sat.gob.mx/Pagos' => 'Pagos',
                    'http://www.sat.gob.mx/Pagos20' => 'Pagos20',
                    'http://www.sat.gob.mx/nomina12' => 'Nomina',
                    'http://www.sat.gob.mx/CartaPorte20' => 'CartaPorte'
                ];

                if (isset($tipos[$uri])) {
                    return $tipos[$uri];
                }
            }
        }

        return null;
    }

    private function extraerComplementoJson($xml)
    {
        if (!isset($xml->Complemento)) {
            return null;
        }

        $complementos = [];
        foreach ($xml->Complemento as $complemento) {
            $namespaces = $complemento->getNamespaces(true);
            $complementos[] = [
                'namespaces' => $namespaces,
                'xml' => $complemento->asXML()
            ];
        }

        return !empty($complementos) ? json_encode($complementos) : null;
    }

    private function extraerCfdiRelacionados($xml)
    {
        if (!isset($xml->CfdiRelacionados)) {
            return null;
        }

        $relacionados = [];
        foreach ($xml->CfdiRelacionados as $rel) {
            $attrs = $rel->attributes();
            $uuids = [];

            if (isset($rel->CfdiRelacionado)) {
                foreach ($rel->CfdiRelacionado as $cfdiRel) {
                    $uuids[] = (string)$cfdiRel['UUID'];
                }
            }

            $relacionados[] = [
                'tipo_relacion' => (string)$attrs->TipoRelacion,
                'uuids' => $uuids
            ];
        }

        return !empty($relacionados) ? json_encode($relacionados) : null;
    }
}

// Ejecutar importación
try {
    $importador = new ImportadorCompletoSAT();
    $importador->ejecutar();
} catch (Exception $e) {
    echo "❌ Error crítico: " . $e->getMessage() . "\n";
    exit(1);
}
