<?php

/**
 * Importador CFDI REAL y COMPLETO v2.0
 * Basado en el importador inteligente que SÍ funcionaba
 */

class ImportadorCFDIRealCompleto
{
    private $pdo;
    private $stats = [
        'archivos_procesados' => 0,
        'cfdi_insertados' => 0,
        'timbres_insertados' => 0,
        'conceptos_insertados' => 0,
        'impuestos_insertados' => 0,
        'pagos_insertados' => 0,
        'complementos_insertados' => 0,
        'errores' => 0,
        'versiones' => ['3.3' => 0, '4.0' => 0]
    ];

    public function __construct()
    {
        $this->pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "🔗 Conectado a la base de datos\n\n";
    }

    public function importarCompleto($limite = 100)
    {
        echo "=== IMPORTADOR CFDI REAL Y COMPLETO v2.0 ===\n\n";

        $directorio = "storage/sat_downloads";
        $archivos = $this->buscarArchivos($directorio);
        $total = count($archivos);

        echo "📁 Directorio: {$directorio}\n";
        echo "📄 Total archivos encontrados: {$total}\n";
        echo "⚠️  MODO PRUEBA: Limitando a {$limite} archivos\n\n";

        if ($total == 0) {
            echo "❌ No se encontraron archivos XML\n";
            return;
        }

        echo "🚀 Iniciando importación real...\n\n";
        $inicio = microtime(true);

        foreach ($archivos as $index => $archivo) {
            if ($index >= $limite) {
                echo "⚠️  Alcanzado límite de {$limite} archivos\n";
                break;
            }

            try {
                $this->procesarArchivo($archivo);
                $this->stats['archivos_procesados']++;

                // Progreso cada 25 archivos
                if (($index + 1) % 25 == 0) {
                    $this->mostrarProgreso($index + 1, $limite);
                }
            } catch (Exception $e) {
                $this->stats['errores']++;
                $this->registrarError($archivo, $e->getMessage());

                if ($this->stats['errores'] <= 3) {
                    echo "⚠️  Error en archivo {$archivo}: " . substr($e->getMessage(), 0, 100) . "...\n";
                }
            }
        }

        $tiempo = round(microtime(true) - $inicio, 2);
        $this->mostrarResultados($tiempo);
    }

    private function buscarArchivos($directorio)
    {
        $archivos = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio));

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                $archivos[] = $file->getPathname();
            }
        }

        return $archivos;
    }

    private function procesarArchivo($archivo)
    {
        $contenido = file_get_contents($archivo);
        if (!$contenido) {
            throw new Exception("No se pudo leer el archivo");
        }

        // Extraer UUID usando regex (más confiable)
        $uuid = $this->extraerUUID($contenido);
        if (!$uuid) {
            throw new Exception("No se pudo extraer UUID");
        }

        // Verificar si ya existe
        if ($this->existeCFDI($uuid)) {
            return; // Omitir duplicados
        }

        // Extraer todos los datos
        $datos = $this->extraerDatosCompletos($contenido, $archivo);
        $datos['uuid'] = $uuid;

        // Insertar CFDI principal
        $cfdi_id = $this->insertarCFDI($datos);

        // Insertar datos relacionados
        $this->insertarTimbreFiscal($cfdi_id, $datos, $contenido);
        $this->insertarConceptos($cfdi_id, $contenido);
        $this->insertarImpuestos($cfdi_id, $contenido);
        $this->insertarComplementoPagos($cfdi_id, $contenido);

        $this->stats['cfdi_insertados']++;
        $this->stats['versiones'][$datos['version'] ?? '3.3']++;
    }

    private function extraerUUID($contenido)
    {
        // Método múltiple para extraer UUID
        $patrones = [
            '/UUID\s*=\s*["\']([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})["\']/',
            '/uuid\s*=\s*["\']([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})["\']/',
            '/([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})/'
        ];

        foreach ($patrones as $patron) {
            if (preg_match($patron, $contenido, $matches)) {
                return strtoupper($matches[1]);
            }
        }

        return null;
    }

    private function existeCFDI($uuid)
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM cfdi WHERE uuid = ?");
        $stmt->execute([$uuid]);
        return $stmt->fetchColumn() > 0;
    }

    private function extraerDatosCompletos($contenido, $archivo)
    {
        $datos = [
            'archivo_xml' => $archivo,
            'version' => $this->extraerVersion($contenido),
            'serie' => $this->extraerAtributo($contenido, 'Serie'),
            'folio' => $this->extraerAtributo($contenido, 'Folio'),
            'fecha' => $this->convertirFecha($this->extraerAtributo($contenido, 'Fecha')),
            'tipo' => $this->extraerAtributo($contenido, 'TipoDeComprobante'),
            'forma_pago' => $this->extraerAtributo($contenido, 'FormaPago'),
            'metodo_pago' => $this->extraerAtributo($contenido, 'MetodoPago'),
            'lugar_expedicion' => $this->extraerAtributo($contenido, 'LugarExpedicion'),
            'moneda' => $this->extraerAtributo($contenido, 'Moneda'),
            'tipo_cambio' => $this->extraerAtributo($contenido, 'TipoCambio'),
            'subtotal' => $this->extraerAtributo($contenido, 'SubTotal'),
            'descuento' => $this->extraerAtributo($contenido, 'Descuento'),
            'total' => $this->extraerAtributo($contenido, 'Total'),
            'sello_cfd' => $this->extraerAtributo($contenido, 'Sello'),
            'exportacion' => $this->extraerAtributo($contenido, 'Exportacion'),

            // Emisor
            'rfc_emisor' => $this->extraerAtributoSeccion($contenido, 'Emisor', 'Rfc'),
            'nombre_emisor' => $this->extraerAtributoSeccion($contenido, 'Emisor', 'Nombre'),
            'regimen_fiscal_emisor' => $this->extraerAtributoSeccion($contenido, 'Emisor', 'RegimenFiscal'),

            // Receptor
            'rfc_receptor' => $this->extraerAtributoSeccion($contenido, 'Receptor', 'Rfc'),
            'nombre_receptor' => $this->extraerAtributoSeccion($contenido, 'Receptor', 'Nombre'),
            'regimen_fiscal_receptor' => $this->extraerAtributoSeccion($contenido, 'Receptor', 'RegimenFiscalReceptor'),
            'uso_cfdi' => $this->extraerAtributoSeccion($contenido, 'Receptor', 'UsoCFDI'),

            // Determinar tipo de complemento
            'complemento_tipo' => $this->determinarTipoComplemento($contenido),
            'direccion_flujo' => $this->determinarDireccionFlujo($archivo)
        ];

        return $datos;
    }

    private function extraerVersion($contenido)
    {
        if (preg_match('/Version\s*=\s*["\']([^"\']*)["\']/', $contenido, $matches)) {
            return $matches[1];
        }
        return '3.3'; // Asumir 3.3 por defecto
    }

    private function extraerAtributo($contenido, $atributo)
    {
        $patron = '/' . $atributo . '\s*=\s*["\']([^"\']*)["\'/';
        if (preg_match($patron, $contenido, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    private function extraerAtributoSeccion($contenido, $seccion, $atributo)
    {
        $patron = '/<[^:]*:' . $seccion . '[^>]*' . $atributo . '\s*=\s*["\']([^"\']*)["\'][^>]*>/';
        if (preg_match($patron, $contenido, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    private function convertirFecha($fecha_str)
    {
        if (!$fecha_str) return null;

        try {
            $fecha = new DateTime($fecha_str);
            return $fecha->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    private function determinarTipoComplemento($contenido)
    {
        if (strpos($contenido, 'Pagos') !== false) {
            return 'PAGOS';
        } elseif (strpos($contenido, 'Nomina') !== false) {
            return 'NOMINA';
        } elseif (strpos($contenido, 'CartaPorte') !== false) {
            return 'CARTA_PORTE';
        }
        return null;
    }

    private function determinarDireccionFlujo($archivo)
    {
        if (strpos($archivo, 'EMITIDAS') !== false) {
            return 'EMITIDAS';
        } elseif (strpos($archivo, 'RECIBIDAS') !== false) {
            return 'RECIBIDAS';
        }
        return 'DESCONOCIDO';
    }

    private function insertarCFDI($datos)
    {
        $sql = "INSERT INTO cfdi (
            uuid, version, serie, folio, fecha, tipo, forma_pago, metodo_pago,
            lugar_expedicion, moneda, tipo_cambio, subtotal, descuento, total,
            sello_cfd, exportacion, rfc_emisor, nombre_emisor, regimen_fiscal_emisor,
            rfc_receptor, nombre_receptor, regimen_fiscal_receptor, uso_cfdi,
            archivo_xml, complemento_tipo, direccion_flujo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $datos['uuid'],
            $datos['version'],
            $datos['serie'],
            $datos['folio'],
            $datos['fecha'],
            $datos['tipo'],
            $datos['forma_pago'],
            $datos['metodo_pago'],
            $datos['lugar_expedicion'],
            $datos['moneda'],
            $datos['tipo_cambio'],
            $datos['subtotal'],
            $datos['descuento'],
            $datos['total'],
            substr($datos['sello_cfd'] ?? '', 0, 500),
            $datos['exportacion'],
            $datos['rfc_emisor'],
            $datos['nombre_emisor'],
            $datos['regimen_fiscal_emisor'],
            $datos['rfc_receptor'],
            $datos['nombre_receptor'],
            $datos['regimen_fiscal_receptor'],
            $datos['uso_cfdi'],
            $datos['archivo_xml'],
            $datos['complemento_tipo'],
            $datos['direccion_flujo']
        ]);

        return $this->pdo->lastInsertId();
    }

    private function insertarTimbreFiscal($cfdi_id, $datos, $contenido)
    {
        // Extraer datos del timbre fiscal
        $timbre = [
            'version' => $this->extraerAtributoSeccion($contenido, 'TimbreFiscalDigital', 'Version'),
            'uuid' => $datos['uuid'],
            'fecha_timbrado' => $this->convertirFecha($this->extraerAtributoSeccion($contenido, 'TimbreFiscalDigital', 'FechaTimbrado')),
            'rfc_prov_certif' => $this->extraerAtributoSeccion($contenido, 'TimbreFiscalDigital', 'RfcProvCertif'),
            'sello_cfd' => $this->extraerAtributoSeccion($contenido, 'TimbreFiscalDigital', 'SelloCFD'),
            'no_certificado_sat' => $this->extraerAtributoSeccion($contenido, 'TimbreFiscalDigital', 'NoCertificadoSAT'),
            'sello_sat' => $this->extraerAtributoSeccion($contenido, 'TimbreFiscalDigital', 'SelloSAT')
        ];

        if ($timbre['version'] || $timbre['fecha_timbrado']) {
            $sql = "INSERT INTO cfdi_timbre_fiscal (
                cfdi_id, version, uuid, fecha_timbrado, rfc_prov_certif,
                sello_cfd, no_certificado_sat, sello_sat
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $cfdi_id,
                $timbre['version'],
                $timbre['uuid'],
                $timbre['fecha_timbrado'],
                $timbre['rfc_prov_certif'],
                substr($timbre['sello_cfd'] ?? '', 0, 500),
                $timbre['no_certificado_sat'],
                substr($timbre['sello_sat'] ?? '', 0, 500)
            ]);

            $this->stats['timbres_insertados']++;

            // Actualizar campos del timbre en la tabla cfdi
            $this->actualizarCamposTimbre($cfdi_id, $timbre);
        }
    }

    private function actualizarCamposTimbre($cfdi_id, $timbre)
    {
        $sql = "UPDATE cfdi SET 
                fecha_timbrado = ?, 
                sello_sat = ?, 
                no_certificado_sat = ?, 
                rfc_prov_certif = ? 
                WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $timbre['fecha_timbrado'],
            substr($timbre['sello_sat'] ?? '', 0, 500),
            $timbre['no_certificado_sat'],
            $timbre['rfc_prov_certif'],
            $cfdi_id
        ]);
    }

    private function insertarConceptos($cfdi_id, $contenido)
    {
        // Buscar conceptos
        preg_match_all('/<[^:]*:Concepto[^>]*>/', $contenido, $matches);

        foreach ($matches[0] as $concepto_tag) {
            $concepto = [
                'clave_prodserv' => $this->extraerAtributoDeTag($concepto_tag, 'ClaveProdServ'),
                'cantidad' => $this->extraerAtributoDeTag($concepto_tag, 'Cantidad'),
                'clave_unidad' => $this->extraerAtributoDeTag($concepto_tag, 'ClaveUnidad'),
                'unidad' => $this->extraerAtributoDeTag($concepto_tag, 'Unidad'),
                'descripcion' => $this->extraerAtributoDeTag($concepto_tag, 'Descripcion'),
                'valor_unitario' => $this->extraerAtributoDeTag($concepto_tag, 'ValorUnitario'),
                'importe' => $this->extraerAtributoDeTag($concepto_tag, 'Importe'),
                'descuento' => $this->extraerAtributoDeTag($concepto_tag, 'Descuento'),
                'objeto_imp' => $this->extraerAtributoDeTag($concepto_tag, 'ObjetoImp'),
                'cuenta_predial' => $this->extraerAtributoDeTag($concepto_tag, 'CuentaPredial')
            ];

            if ($concepto['descripcion'] || $concepto['importe']) {
                $sql = "INSERT INTO cfdi_conceptos (
                    cfdi_id, clave_prodserv, cantidad, clave_unidad, unidad,
                    descripcion, valor_unitario, importe, descuento, objeto_imp, cuenta_predial
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $cfdi_id,
                    $concepto['clave_prodserv'],
                    $concepto['cantidad'],
                    $concepto['clave_unidad'],
                    $concepto['unidad'],
                    $concepto['descripcion'],
                    $concepto['valor_unitario'],
                    $concepto['importe'],
                    $concepto['descuento'],
                    $concepto['objeto_imp'],
                    $concepto['cuenta_predial']
                ]);

                $this->stats['conceptos_insertados']++;
            }
        }
    }

    private function insertarImpuestos($cfdi_id, $contenido)
    {
        // Buscar impuestos trasladados y retenidos
        $patrones = [
            '/<[^:]*:Traslado[^>]*>/',
            '/<[^:]*:Retencion[^>]*>/'
        ];

        foreach ($patrones as $patron) {
            preg_match_all($patron, $contenido, $matches);

            foreach ($matches[0] as $impuesto_tag) {
                $tipo = strpos($impuesto_tag, 'Traslado') !== false ? 'TRASLADADO' : 'RETENIDO';

                $impuesto = [
                    'tipo' => $tipo,
                    'impuesto' => $this->extraerAtributoDeTag($impuesto_tag, 'Impuesto'),
                    'tipo_factor' => $this->extraerAtributoDeTag($impuesto_tag, 'TipoFactor'),
                    'tasa_cuota' => $this->extraerAtributoDeTag($impuesto_tag, 'TasaOCuota'),
                    'base' => $this->extraerAtributoDeTag($impuesto_tag, 'Base'),
                    'importe' => $this->extraerAtributoDeTag($impuesto_tag, 'Importe')
                ];

                if ($impuesto['impuesto'] || $impuesto['importe']) {
                    $sql = "INSERT INTO cfdi_impuestos (
                        cfdi_id, tipo, impuesto, tipo_factor, tasa_cuota, base, importe
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";

                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([
                        $cfdi_id,
                        $impuesto['tipo'],
                        $impuesto['impuesto'],
                        $impuesto['tipo_factor'],
                        $impuesto['tasa_cuota'],
                        $impuesto['base'],
                        $impuesto['importe']
                    ]);

                    $this->stats['impuestos_insertados']++;
                }
            }
        }
    }

    private function insertarComplementoPagos($cfdi_id, $contenido)
    {
        // Solo procesar si es un CFDI de pago
        if (strpos($contenido, 'TipoDeComprobante="P"') === false) {
            return;
        }

        // Buscar pagos
        preg_match_all('/<[^:]*:Pago[^>]*>/', $contenido, $matches);

        foreach ($matches[0] as $pago_tag) {
            $pago = [
                'fecha_pago' => $this->convertirFecha($this->extraerAtributoDeTag($pago_tag, 'FechaPago')),
                'forma_pago' => $this->extraerAtributoDeTag($pago_tag, 'FormaDePagoP'),
                'moneda' => $this->extraerAtributoDeTag($pago_tag, 'MonedaP'),
                'tipo_cambio' => $this->extraerAtributoDeTag($pago_tag, 'TipoCambioP'),
                'monto' => $this->extraerAtributoDeTag($pago_tag, 'Monto'),
                'num_operacion' => $this->extraerAtributoDeTag($pago_tag, 'NumOperacion'),
                'rfc_emisor_cuenta_ordenante' => $this->extraerAtributoDeTag($pago_tag, 'RfcEmisorCtaOrd'),
                'cuenta_ordenante' => $this->extraerAtributoDeTag($pago_tag, 'CtaOrdenante'),
                'rfc_emisor_cuenta_beneficiario' => $this->extraerAtributoDeTag($pago_tag, 'RfcEmisorCtaBen'),
                'cuenta_beneficiario' => $this->extraerAtributoDeTag($pago_tag, 'CtaBeneficiario')
            ];

            if ($pago['fecha_pago'] || $pago['monto']) {
                $sql = "INSERT INTO cfdi_pagos (
                    cfdi_id, fecha_pago, forma_pago, moneda, tipo_cambio, monto,
                    num_operacion, rfc_emisor_cuenta_ordenante, cuenta_ordenante,
                    rfc_emisor_cuenta_beneficiario, cuenta_beneficiario
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $cfdi_id,
                    $pago['fecha_pago'],
                    $pago['forma_pago'],
                    $pago['moneda'],
                    $pago['tipo_cambio'],
                    $pago['monto'],
                    $pago['num_operacion'],
                    $pago['rfc_emisor_cuenta_ordenante'],
                    $pago['cuenta_ordenante'],
                    $pago['rfc_emisor_cuenta_beneficiario'],
                    $pago['cuenta_beneficiario']
                ]);

                $this->stats['pagos_insertados']++;
            }
        }
    }

    private function extraerAtributoDeTag($tag, $atributo)
    {
        $patron = '/' . $atributo . '\s*=\s*["\']([^"\']*)["\'/';
        if (preg_match($patron, $tag, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    private function registrarError($archivo, $mensaje)
    {
        $sql = "INSERT INTO cfdi_auditoria (archivo, uuid, estado, mensaje, fecha) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$archivo, 'ERROR', 'ERROR', $mensaje]);
    }

    private function mostrarProgreso($actual, $limite)
    {
        echo "📊 Progreso: {$actual}/{$limite} archivos\n";
        echo "   CFDIs: {$this->stats['cfdi_insertados']}, ";
        echo "Timbres: {$this->stats['timbres_insertados']}, ";
        echo "Conceptos: {$this->stats['conceptos_insertados']}, ";
        echo "Errores: {$this->stats['errores']}\n\n";
    }

    private function mostrarResultados($tiempo)
    {
        echo "\n============================================================\n";
        echo "📊 ESTADÍSTICAS FINALES DE IMPORTACIÓN REAL Y COMPLETA\n";
        echo "============================================================\n";
        echo "⏱️  Tiempo: {$tiempo} segundos\n";
        echo "📄 Archivos procesados: {$this->stats['archivos_procesados']}\n";
        echo "📋 CFDIs insertados: {$this->stats['cfdi_insertados']}\n";
        echo "🏷️  Timbres fiscales: {$this->stats['timbres_insertados']}\n";
        echo "🧾 Conceptos: {$this->stats['conceptos_insertados']}\n";
        echo "💰 Impuestos: {$this->stats['impuestos_insertados']}\n";
        echo "💳 Pagos: {$this->stats['pagos_insertados']}\n";
        echo "⚠️  Errores: {$this->stats['errores']}\n";
        echo "📊 CFDI v3.3: {$this->stats['versiones']['3.3']}\n";
        echo "📊 CFDI v4.0: {$this->stats['versiones']['4.0']}\n";

        $tasa_exito = $this->stats['archivos_procesados'] > 0 ?
            round(($this->stats['cfdi_insertados'] / $this->stats['archivos_procesados']) * 100, 2) : 0;
        echo "🎯 Tasa de éxito: {$tasa_exito}%\n";
        echo "============================================================\n";
    }
}

// Configurar entorno
set_time_limit(0);
ini_set('memory_limit', '2G');

echo "⚙️  Configuración: Sin límite de tiempo, 2GB memoria\n\n";

try {
    $importador = new ImportadorCFDIRealCompleto();
    $importador->importarCompleto(100); // Prueba con 100 archivos
} catch (Exception $e) {
    echo "\n❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
}
