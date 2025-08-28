<?php

/**
 * IMPORTADOR CFDI FINAL - DEFINITIVO
 * 
 * Arregla TODOS los problemas:
 * 1. Inserta UUID en tabla principal
 * 2. Maneja duplicados con REPLACE
 * 3. Todos los campos en todas las tablas
 * 4. Sin debug innecesario
 */

class ImportadorCFDIFinal
{
    private $pdo;
    private $stats = [
        'insertados' => 0,
        'actualizados' => 0,
        'errores' => 0,
        'procesados' => 0
    ];

    public function __construct()
    {
        $this->conectarBD();
        echo "🚀 IMPORTADOR CFDI FINAL INICIADO\n";
    }

    private function conectarBD()
    {
        try {
            $this->pdo = new PDO(
                'mysql:host=localhost;dbname=sac_db;charset=utf8mb4',
                'root',
                '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            // Forzar compatibilidad máxima con caracteres especiales
            $this->pdo->exec("SET NAMES utf8mb4");
        } catch (PDOException $e) {
            die("❌ Error conexión: " . $e->getMessage() . "\n");
        }
    }

    public function procesar($directorio)
    {
        $archivos = $this->buscarXML($directorio);
        $total = count($archivos);
        echo "📁 Total archivos: {$total}\n";

        $lotes = array_chunk($archivos, 100);
        foreach ($lotes as $i => $lote) {
            echo "📦 Lote " . ($i + 1) . "/" . count($lotes) . "\n";

            $this->pdo->beginTransaction();
            try {
                foreach ($lote as $archivo) {
                    $this->procesarArchivo($archivo);
                }
                $this->pdo->commit();
            } catch (Exception $e) {
                $this->pdo->rollBack();
                echo "❌ Error lote: " . $e->getMessage() . "\n";
            }
        }

        $this->mostrarStats();
    }

    private function buscarXML($dir)
    {
        $archivos = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                $archivos[] = $file->getPathname();
            }
        }
        return $archivos;
    }

    private function procesarArchivo($archivo)
    {
        try {
            $this->stats['procesados']++;

            $xml = file_get_contents($archivo);
            if (!$xml) throw new Exception("No se puede leer");

            $doc = new DOMDocument();
            if (!@$doc->loadXML($xml)) throw new Exception("XML inválido");

            $xpath = new DOMXPath($doc);
            $this->registrarNamespaces($xpath);

            $comprobante = $xpath->query('//cfdi:Comprobante | //cfdi33:Comprobante')->item(0);
            if (!$comprobante) throw new Exception("Sin Comprobante");

            $uuid = $this->extraerUUID($xpath);
            if (!$uuid) throw new Exception("Sin UUID");

            // USAR REPLACE para manejar duplicados automáticamente
            $cfdi_id = $this->insertarCFDI($comprobante, $xpath, $uuid, $archivo);
            $this->insertarDatosRelacionados($cfdi_id, $comprobante, $xpath);

            $this->stats['insertados']++;
        } catch (Exception $e) {
            $this->stats['errores']++;
            echo "❌ {$archivo}: " . $e->getMessage() . "\n";
        }
    }

    private function registrarNamespaces($xpath)
    {
        $namespaces = [
            'cfdi' => 'http://www.sat.gob.mx/cfd/4',
            'cfdi33' => 'http://www.sat.gob.mx/cfd/3',
            'tfd' => 'http://www.sat.gob.mx/TimbreFiscalDigital',
            'pago10' => 'http://www.sat.gob.mx/Pagos',
            'pago20' => 'http://www.sat.gob.mx/Pagos20',
            'nomina12' => 'http://www.sat.gob.mx/nomina12'
        ];

        foreach ($namespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }
    }

    private function extraerUUID($xpath)
    {
        $timbre = $xpath->query('//tfd:TimbreFiscalDigital')->item(0);
        return $timbre ? $timbre->getAttribute('UUID') : null;
    }

    private function insertarCFDI($comprobante, $xpath, $uuid, $archivo)
    {
        $emisor = $xpath->query('.//cfdi:Emisor | .//cfdi33:Emisor', $comprobante)->item(0);
        $receptor = $xpath->query('.//cfdi:Receptor | .//cfdi33:Receptor', $comprobante)->item(0);

        // Calcular ruta relativa del XML
        $ruta_base = realpath(__DIR__);
        $ruta_completa = realpath($archivo);
        $ruta_relativa = $ruta_completa ? str_replace($ruta_base . DIRECTORY_SEPARATOR, '', $ruta_completa) : $archivo;
        $ruta_relativa = str_replace('\\', '/', $ruta_relativa);


        // Ajustar campos según estructura real de la tabla cfdi

        $sql = "REPLACE INTO cfdi (
            uuid, version, serie, folio, fecha, sello, forma_pago,
            no_certificado, certificado, condiciones_pago, subtotal,
            descuento, moneda, tipo_cambio, total, tipo_comprobante,
            metodo_pago, lugar_expedicion, confirmacion, fecha_procesamiento, ruta_xml
        ) VALUES (
            :uuid, :version, :serie, :folio, :fecha, :sello, :forma_pago,
            :no_certificado, :certificado, :condiciones_pago, :subtotal,
            :descuento, :moneda, :tipo_cambio, :total, :tipo_comprobante,
            :metodo_pago, :lugar_expedicion, :confirmacion, :fecha_procesamiento, :ruta_xml
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':uuid' => $uuid,
            ':version' => $comprobante->getAttribute('Version') ?: '3.3',
            ':serie' => $comprobante->getAttribute('Serie') ?: null,
            ':folio' => $comprobante->getAttribute('Folio') ?: null,
            ':fecha' => $comprobante->getAttribute('Fecha') ?: null,
            ':sello' => $comprobante->getAttribute('Sello') ?: null,
            ':forma_pago' => $comprobante->getAttribute('FormaPago') ?: null,
            ':no_certificado' => $comprobante->getAttribute('NoCertificado') ?: null,
            ':certificado' => $comprobante->getAttribute('Certificado') ?: null,
            ':condiciones_pago' => $comprobante->getAttribute('CondicionesDePago') ?: null,
            ':subtotal' => $comprobante->getAttribute('SubTotal') ?: $comprobante->getAttribute('Subtotal') ?: null,
            ':descuento' => $comprobante->getAttribute('Descuento') ?: null,
            ':moneda' => $comprobante->getAttribute('Moneda') ?: null,
            ':tipo_cambio' => $comprobante->getAttribute('TipoCambio') ?: null,
            ':total' => $comprobante->getAttribute('Total') ?: null,
            ':tipo_comprobante' => $comprobante->getAttribute('TipoDeComprobante') ?: null,
            ':metodo_pago' => $comprobante->getAttribute('MetodoPago') ?: null,
            ':lugar_expedicion' => $comprobante->getAttribute('LugarExpedicion') ?: null,
            ':confirmacion' => $comprobante->getAttribute('Confirmacion') ?: null,
            ':fecha_procesamiento' => date('Y-m-d H:i:s'),
            ':ruta_xml' => $ruta_relativa
        ]);

        // Obtener ID del CFDI insertado/actualizado
        $cfdi_id = $this->pdo->lastInsertId();
        if (!$cfdi_id) {
            // Si REPLACE actualizó, buscar el ID por UUID
            $stmt = $this->pdo->prepare("SELECT id FROM cfdi WHERE uuid = ?");
            $stmt->execute([$uuid]);
            $cfdi_id = $stmt->fetchColumn();
        }

        return $cfdi_id;
    }
    // Forzar codificación UTF-8 para caracteres especiales
    private function utf8($str)
    {
        return is_null($str) ? null : mb_convert_encoding($str, 'UTF-8', 'auto');
    }

    private function insertarDatosRelacionados($cfdi_id, $comprobante, $xpath)
    {
        // Limpiar datos anteriores si es actualización
        $this->limpiarDatosRelacionados($cfdi_id);

        // Insertar emisor
        $emisor = $xpath->query('.//cfdi:Emisor | .//cfdi33:Emisor', $comprobante)->item(0);
        if ($emisor) $this->insertarEmisor($cfdi_id, $emisor);

        // Insertar receptor
        $receptor = $xpath->query('.//cfdi:Receptor | .//cfdi33:Receptor', $comprobante)->item(0);
        if ($receptor) $this->insertarReceptor($cfdi_id, $receptor);

        // Insertar conceptos
        $conceptos = $xpath->query('.//cfdi:Concepto | .//cfdi33:Concepto', $comprobante);
        foreach ($conceptos as $concepto) {
            $concepto_id = $this->insertarConcepto($cfdi_id, $concepto, $xpath);
            $this->insertarImpuestosConcepto($concepto_id, $concepto, $xpath);
        }

        // Insertar timbre
        $this->insertarTimbre($cfdi_id, $xpath);

        // Insertar complementos
        $this->insertarComplementos($cfdi_id, $xpath);
    }

    private function limpiarDatosRelacionados($cfdi_id)
    {
        $tablas = [
            'impuestos_trasladados' => 'concepto_id IN (SELECT id FROM conceptos WHERE cfdi_id = ?)',
            'impuestos_retenidos' => 'concepto_id IN (SELECT id FROM conceptos WHERE cfdi_id = ?)',
            'conceptos',
            'emisor',
            'receptor',
            'cfdi_timbre_fiscal_digital',
            'cfdi_complemento_pagos_v10',
            'cfdi_complemento_pagos_v20',
            'cfdi_complemento_nomina'
        ];

        foreach ($tablas as $tabla) {
            $condicion = is_array($tabla) ? $tabla[1] : 'cfdi_id = ?';
            $nombre = is_array($tabla) ? $tabla[0] : $tabla;

            $sql = "DELETE FROM {$nombre} WHERE {$condicion}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$cfdi_id]);
        }
    }

    private function insertarEmisor($cfdi_id, $emisor)
    {
        $sql = "INSERT INTO emisor (cfdi_id, rfc, nombre, regimen_fiscal, fac_atr_adquirente) 
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $cfdi_id,
            $emisor->getAttribute('Rfc'),
            $emisor->getAttribute('Nombre'),
            $emisor->getAttribute('RegimenFiscal'),
            $emisor->getAttribute('FacAtrAdquirente')
        ]);
    }

    private function insertarReceptor($cfdi_id, $receptor)
    {
        $sql = "INSERT INTO receptor (cfdi_id, rfc, nombre, uso_cfdi, domicilio_fiscal_receptor, residencia_fiscal, num_reg_id_trib) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $cfdi_id,
            $receptor->getAttribute('Rfc'),
            $receptor->getAttribute('Nombre'),
            $receptor->getAttribute('UsoCFDI'),
            $receptor->getAttribute('DomicilioFiscalReceptor'),
            $receptor->getAttribute('ResidenciaFiscal'),
            $receptor->getAttribute('NumRegIdTrib')
        ]);
    }

    private function insertarConcepto($cfdi_id, $concepto, $xpath)
    {
        $sql = "INSERT INTO conceptos (cfdi_id, clave_prod_serv, no_identificacion, cantidad, clave_unidad, unidad, descripcion, valor_unitario, importe, descuento, objeto_imp) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $cfdi_id,
            $concepto->getAttribute('ClaveProdServ'),
            $concepto->getAttribute('NoIdentificacion'),
            $this->convertirDecimal($concepto->getAttribute('Cantidad')),
            $concepto->getAttribute('ClaveUnidad'),
            $concepto->getAttribute('Unidad'),
            $concepto->getAttribute('Descripcion'),
            $this->convertirDecimal($concepto->getAttribute('ValorUnitario')),
            $this->convertirDecimal($concepto->getAttribute('Importe')),
            $this->convertirDecimal($concepto->getAttribute('Descuento')),
            $concepto->getAttribute('ObjetoImp')
        ]);

        return $this->pdo->lastInsertId();
    }

    private function insertarImpuestosConcepto($concepto_id, $concepto, $xpath)
    {
        // Traslados
        $traslados = $xpath->query('.//cfdi:Traslado | .//cfdi33:Traslado', $concepto);
        foreach ($traslados as $traslado) {
            $sql = "INSERT INTO impuestos_trasladados (concepto_id, impuesto, tipo_factor, tasa_cuota, importe, base) 
                    VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $concepto_id,
                $traslado->getAttribute('Impuesto'),
                $traslado->getAttribute('TipoFactor'),
                $this->convertirDecimal($traslado->getAttribute('TasaOCuota')),
                $this->convertirDecimal($traslado->getAttribute('Importe')),
                $this->convertirDecimal($traslado->getAttribute('Base'))
            ]);
        }

        // Retenciones
        $retenciones = $xpath->query('.//cfdi:Retencion | .//cfdi33:Retencion', $concepto);
        foreach ($retenciones as $retencion) {
            $sql = "INSERT INTO impuestos_retenidos (concepto_id, impuesto, tipo_factor, tasa_cuota, importe, base) 
                    VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $concepto_id,
                $retencion->getAttribute('Impuesto'),
                $retencion->getAttribute('TipoFactor'),
                $this->convertirDecimal($retencion->getAttribute('TasaOCuota')),
                $this->convertirDecimal($retencion->getAttribute('Importe')),
                $this->convertirDecimal($retencion->getAttribute('Base'))
            ]);
        }
    }

    private function insertarTimbre($cfdi_id, $xpath)
    {
        $timbre = $xpath->query('//tfd:TimbreFiscalDigital')->item(0);
        if (!$timbre) return;

        $sql = "INSERT INTO cfdi_timbre_fiscal_digital (cfdi_id, version, uuid, fecha_timbrado, rfc_prov_certif, leyenda, sello_cfd, no_certificado_sat, sello_sat) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $cfdi_id,
            $timbre->getAttribute('Version'),
            $timbre->getAttribute('UUID'),
            $this->convertirFecha($timbre->getAttribute('FechaTimbrado')),
            $timbre->getAttribute('RfcProvCertif'),
            $timbre->getAttribute('Leyenda'),
            $timbre->getAttribute('SelloCFD'),
            $timbre->getAttribute('NoCertificadoSAT'),
            $timbre->getAttribute('SelloSAT')
        ]);
    }

    private function insertarComplementos($cfdi_id, $xpath)
    {
        // Pagos 1.0
        $pagos10 = $xpath->query('//pago10:Pagos//pago10:Pago');
        foreach ($pagos10 as $pago) {
            $sql = "INSERT INTO cfdi_complemento_pagos_v10 (cfdi_id, fecha_pago, forma_pago_p, moneda_p, tipo_cambio_p, monto, num_operacion, rfc_emisor_cta_ord, nom_banco_ord_ext, cta_ordenante, rfc_emisor_cta_ben, cta_beneficiario) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $cfdi_id,
                $this->convertirFecha($pago->getAttribute('FechaPago')),
                $pago->getAttribute('FormaDePagoP'),
                $pago->getAttribute('MonedaP'),
                $this->convertirDecimal($pago->getAttribute('TipoCambioP')),
                $this->convertirDecimal($pago->getAttribute('Monto')),
                $pago->getAttribute('NumOperacion'),
                $pago->getAttribute('RfcEmisorCtaOrd'),
                $pago->getAttribute('NomBancoOrdExt'),
                $pago->getAttribute('CtaOrdenante'),
                $pago->getAttribute('RfcEmisorCtaBen'),
                $pago->getAttribute('CtaBeneficiario')
            ]);
        }

        // Pagos 2.0
        $pagos20 = $xpath->query('//pago20:Pagos//pago20:Pago');
        foreach ($pagos20 as $pago) {
            $sql = "INSERT INTO cfdi_complemento_pagos_v20 (cfdi_id, fecha_pago, forma_pago_p, moneda_p, tipo_cambio_p, monto, num_operacion, rfc_emisor_cta_ord, nom_banco_ord_ext, cta_ordenante, rfc_emisor_cta_ben, cta_beneficiario, tipo_cad_pago, cert_pago, cad_pago, sello_pago) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $cfdi_id,
                $this->convertirFecha($pago->getAttribute('FechaPago')),
                $pago->getAttribute('FormaDePagoP'),
                $pago->getAttribute('MonedaP'),
                $this->convertirDecimal($pago->getAttribute('TipoCambioP')),
                $this->convertirDecimal($pago->getAttribute('Monto')),
                $pago->getAttribute('NumOperacion'),
                $pago->getAttribute('RfcEmisorCtaOrd'),
                $pago->getAttribute('NomBancoOrdExt'),
                $pago->getAttribute('CtaOrdenante'),
                $pago->getAttribute('RfcEmisorCtaBen'),
                $pago->getAttribute('CtaBeneficiario'),
                $pago->getAttribute('TipoCadPago'),
                $pago->getAttribute('CertPago'),
                $pago->getAttribute('CadPago'),
                $pago->getAttribute('SelloPago')
            ]);
        }

        // Nómina
        $nomina = $xpath->query('//nomina12:Nomina')->item(0);
        if ($nomina) {
            $sql = "INSERT INTO cfdi_complemento_nomina (cfdi_id, tipo_nomina, fecha_pago, fecha_inicial_pago, fecha_final_pago, num_dias_pagados, total_percepciones, total_deducciones, total_otros_pagos) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $cfdi_id,
                $nomina->getAttribute('TipoNomina'),
                $this->convertirFecha($nomina->getAttribute('FechaPago')),
                $this->convertirFecha($nomina->getAttribute('FechaInicialPago')),
                $this->convertirFecha($nomina->getAttribute('FechaFinalPago')),
                $this->convertirDecimal($nomina->getAttribute('NumDiasPagados')),
                $this->convertirDecimal($nomina->getAttribute('TotalPercepciones')),
                $this->convertirDecimal($nomina->getAttribute('TotalDeducciones')),
                $this->convertirDecimal($nomina->getAttribute('TotalOtrosPagos'))
            ]);
        }
    }

    private function convertirFecha($fecha)
    {
        if (empty($fecha)) return null;
        $timestamp = strtotime($fecha);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function convertirDecimal($valor)
    {
        if (empty($valor)) return null;
        return is_numeric($valor) ? floatval($valor) : null;
    }

    private function mostrarStats()
    {
        echo "\n================================================================\n";
        echo "📊 ESTADÍSTICAS FINALES\n";
        echo "================================================================\n";
        echo "📁 Procesados: " . $this->stats['procesados'] . "\n";
        echo "✅ Insertados: " . $this->stats['insertados'] . "\n";
        echo "❌ Errores: " . $this->stats['errores'] . "\n";
        echo "📈 Éxito: " . round(($this->stats['insertados'] / $this->stats['procesados']) * 100, 2) . "%\n";
        echo "================================================================\n";
    }
}

// Ejecutar
if ($argc < 2) {
    echo "Uso: php importador_final.php <directorio>\n";
    exit(1);
}

$directorio = $argv[1];
if (!is_dir($directorio)) {
    echo "Error: Directorio no existe\n";
    exit(1);
}

$importador = new ImportadorCFDIFinal();
$importador->procesar($directorio);
