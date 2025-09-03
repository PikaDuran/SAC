<?php

/**
 * IMPORTADOR CFDI CON FUNCIONALIDAD UPDATE
 * 
 * Version final que maneja UPDATES cuando un CFDI ya existe pero ha cambiado
 * Resuelve el problema de duplicados insertando o actualizando según corresponda
 */

require_once __DIR__ . '/vendor/autoload.php';

class ImportadorCFDIUpdate
{
    private $pdo;
    private $stats = [
        'insertados' => 0,
        'actualizados' => 0,
        'errores' => 0,
        'archivos_procesados' => 0
    ];

    public function __construct()
    {
        $this->conectarBaseDatos();
        echo "🚀 IMPORTADOR CFDI CON FUNCIONALIDAD UPDATE INICIADO\n";
        echo "================================================================\n";
    }

    private function conectarBaseDatos()
    {
        try {
            $this->pdo = new PDO(
                'mysql:host=localhost;dbname=sac_db;charset=utf8mb4',
                'root',
                '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            echo "✅ Conexión a base de datos establecida\n";
        } catch (PDOException $e) {
            die("❌ Error de conexión: " . $e->getMessage() . "\n");
        }
    }

    public function procesarDirectorio($directorio)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directorio)
        );

        $archivos_xml = [];
        foreach ($iterator as $archivo) {
            if ($archivo->isFile() && strtolower($archivo->getExtension()) === 'xml') {
                $archivos_xml[] = $archivo->getPathname();
            }
        }

        $total_archivos = count($archivos_xml);
        echo "📁 Total de archivos XML encontrados: {$total_archivos}\n";
        echo "================================================================\n";

        $lote_size = 100;
        $lotes = array_chunk($archivos_xml, $lote_size);
        $total_lotes = count($lotes);

        foreach ($lotes as $indice => $lote) {
            $numero_lote = $indice + 1;
            echo "📦 Procesando lote {$numero_lote}/{$total_lotes}\n";

            $this->pdo->beginTransaction();
            try {
                foreach ($lote as $archivo) {
                    $this->procesarArchivo($archivo);
                }
                $this->pdo->commit();
                echo "✅ Lote {$numero_lote} procesado exitosamente\n";
            } catch (Exception $e) {
                $this->pdo->rollBack();
                $this->stats['errores']++;
                echo "❌ Error en lote {$numero_lote}: " . $e->getMessage() . "\n";
            }
        }

        $this->mostrarEstadisticas();
    }

    private function procesarArchivo($archivo)
    {
        try {
            $this->stats['archivos_procesados']++;

            $contenido = file_get_contents($archivo);
            if (!$contenido) {
                throw new Exception("No se puede leer el archivo");
            }

            $doc = new DOMDocument();
            $doc->preserveWhiteSpace = false;

            if (!@$doc->loadXML($contenido)) {
                throw new Exception("XML inválido");
            }

            $xpath = new DOMXPath($doc);
            $this->registrarNamespaces($xpath);

            $comprobante = $xpath->query('//cfdi:Comprobante')->item(0);
            if (!$comprobante) {
                throw new Exception("No se encontró elemento Comprobante");
            }

            $uuid = $this->extraerUUID($xpath);
            if (!$uuid) {
                throw new Exception("No se encontró UUID");
            }

            // Verificar si el CFDI ya existe
            $cfdi_existente = $this->verificarCFDIExistente($uuid);

            if ($cfdi_existente) {
                // Verificar si hay cambios comparando datos importantes
                if ($this->tienecambios($cfdi_existente, $comprobante, $xpath)) {
                    $this->actualizarCFDI($cfdi_existente['id'], $comprobante, $xpath, $archivo);
                    $this->stats['actualizados']++;
                    echo "🔄 CFDI actualizado: {$uuid}\n";
                } else {
                    echo "⏭️  CFDI sin cambios: {$uuid}\n";
                }
            } else {
                // Insertar nuevo CFDI
                $cfdi_id = $this->insertarCFDI($comprobante, $xpath, $archivo);
                $this->stats['insertados']++;
                echo "✅ CFDI insertado: {$uuid} (ID: {$cfdi_id})\n";
            }
        } catch (Exception $e) {
            $this->stats['errores']++;
            echo "❌ Error en {$archivo}: " . $e->getMessage() . "\n";
        }
    }

    private function verificarCFDIExistente($uuid)
    {
        $sql = "SELECT id, uuid, total, fecha, emisor_rfc, receptor_rfc FROM cfdi WHERE uuid = :uuid";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uuid' => $uuid]);
        return $stmt->fetch();
    }

    private function tieneChangios($cfdi_existente, $comprobante, $xpath)
    {
        // Comparar campos importantes para detectar cambios
        $total_actual = $this->convertirDecimal($comprobante->getAttribute('Total'));
        $fecha_actual = $this->convertirFecha($comprobante->getAttribute('Fecha'));

        $emisor = $xpath->query('.//cfdi:Emisor', $comprobante)->item(0);
        $receptor = $xpath->query('.//cfdi:Receptor', $comprobante)->item(0);

        $emisor_rfc_actual = $emisor ? $emisor->getAttribute('Rfc') : '';
        $receptor_rfc_actual = $receptor ? $receptor->getAttribute('Rfc') : '';

        return (
            abs(floatval($cfdi_existente['total']) - floatval($total_actual)) > 0.01 ||
            $cfdi_existente['fecha'] !== $fecha_actual ||
            $cfdi_existente['emisor_rfc'] !== $emisor_rfc_actual ||
            $cfdi_existente['receptor_rfc'] !== $receptor_rfc_actual
        );
    }

    private function actualizarCFDI($cfdi_id, $comprobante, $xpath, $archivo)
    {
        // Actualizar tabla principal CFDI
        $this->actualizarTablaCFDI($cfdi_id, $comprobante, $xpath, $archivo);

        // Eliminar y reinsertar datos relacionados
        $this->eliminarDatosRelacionados($cfdi_id);

        // Reinsertar datos actualizados
        $this->insertarDatosRelacionados($cfdi_id, $comprobante, $xpath);
    }

    private function actualizarTablaCFDI($cfdi_id, $comprobante, $xpath, $archivo)
    {
        $sql = "UPDATE cfdi SET 
                    version = :version,
                    serie = :serie,
                    folio = :folio,
                    fecha = :fecha,
                    sello = :sello,
                    forma_pago = :forma_pago,
                    no_certificado = :no_certificado,
                    certificado = :certificado,
                    condiciones_pago = :condiciones_pago,
                    subtotal = :subtotal,
                    descuento = :descuento,
                    moneda = :moneda,
                    tipo_cambio = :tipo_cambio,
                    total = :total,
                    tipo_comprobante = :tipo_comprobante,
                    exportacion = :exportacion,
                    metodo_pago = :metodo_pago,
                    lugar_expedicion = :lugar_expedicion,
                    confirmacion = :confirmacion,
                    emisor_rfc = :emisor_rfc,
                    receptor_rfc = :receptor_rfc,
                    ruta_archivo = :ruta_archivo,
                    fecha_procesamiento = NOW()
                WHERE id = :cfdi_id";

        $emisor = $xpath->query('.//cfdi:Emisor', $comprobante)->item(0);
        $receptor = $xpath->query('.//cfdi:Receptor', $comprobante)->item(0);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cfdi_id' => $cfdi_id,
            ':version' => $comprobante->getAttribute('Version'),
            ':serie' => $comprobante->getAttribute('Serie'),
            ':folio' => $comprobante->getAttribute('Folio'),
            ':fecha' => $this->convertirFecha($comprobante->getAttribute('Fecha')),
            ':sello' => $comprobante->getAttribute('Sello'),
            ':forma_pago' => $comprobante->getAttribute('FormaPago'),
            ':no_certificado' => $comprobante->getAttribute('NoCertificado'),
            ':certificado' => $comprobante->getAttribute('Certificado'),
            ':condiciones_pago' => $comprobante->getAttribute('CondicionesDePago'),
            ':subtotal' => $this->convertirDecimal($comprobante->getAttribute('SubTotal')),
            ':descuento' => $this->convertirDecimal($comprobante->getAttribute('Descuento')),
            ':moneda' => $comprobante->getAttribute('Moneda'),
            ':tipo_cambio' => $this->convertirDecimal($comprobante->getAttribute('TipoCambio')),
            ':total' => $this->convertirDecimal($comprobante->getAttribute('Total')),
            ':tipo_comprobante' => $comprobante->getAttribute('TipoDeComprobante'),
            ':exportacion' => $comprobante->getAttribute('Exportacion'),
            ':metodo_pago' => $comprobante->getAttribute('MetodoPago'),
            ':lugar_expedicion' => $comprobante->getAttribute('LugarExpedicion'),
            ':confirmacion' => $comprobante->getAttribute('Confirmacion'),
            ':emisor_rfc' => $emisor ? $emisor->getAttribute('Rfc') : null,
            ':receptor_rfc' => $receptor ? $receptor->getAttribute('Rfc') : null,
            ':ruta_archivo' => $archivo
        ]);
    }

    private function eliminarDatosRelacionados($cfdi_id)
    {
        // Eliminar en orden inverso por las foreign keys
        $tablas = [
            'impuestos_trasladados' => 'concepto_id IN (SELECT id FROM conceptos WHERE cfdi_id = ?)',
            'impuestos_retenidos' => 'concepto_id IN (SELECT id FROM conceptos WHERE cfdi_id = ?)',
            'conceptos' => 'cfdi_id = ?',
            'cfdi_timbre_fiscal_digital' => 'cfdi_id = ?',
            'cfdi_complemento_pagos_v10' => 'cfdi_id = ?',
            'cfdi_complemento_pagos_v20' => 'cfdi_id = ?',
            'cfdi_complemento_nomina' => 'cfdi_id = ?',
            'emisor' => 'cfdi_id = ?',
            'receptor' => 'cfdi_id = ?'
        ];

        foreach ($tablas as $tabla => $condicion) {
            $sql = "DELETE FROM {$tabla} WHERE {$condicion}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$cfdi_id]);
        }
    }

    private function insertarCFDI($comprobante, $xpath, $archivo)
    {
        // Insertar en tabla principal
        $cfdi_id = $this->insertarTablaCFDI($comprobante, $xpath, $archivo);

        // Insertar datos relacionados
        $this->insertarDatosRelacionados($cfdi_id, $comprobante, $xpath);

        return $cfdi_id;
    }

    private function insertarTablaCFDI($comprobante, $xpath, $archivo)
    {
        $uuid = $this->extraerUUID($xpath);
        $emisor = $xpath->query('.//cfdi:Emisor', $comprobante)->item(0);
        $receptor = $xpath->query('.//cfdi:Receptor', $comprobante)->item(0);

        $sql = "INSERT INTO cfdi (
            uuid, version, serie, folio, fecha, sello, forma_pago,
            no_certificado, certificado, condiciones_pago, subtotal,
            descuento, moneda, tipo_cambio, total, tipo_comprobante,
            exportacion, metodo_pago, lugar_expedicion, confirmacion,
            emisor_rfc, receptor_rfc, ruta_archivo, fecha_procesamiento
        ) VALUES (
            :uuid, :version, :serie, :folio, :fecha, :sello, :forma_pago,
            :no_certificado, :certificado, :condiciones_pago, :subtotal,
            :descuento, :moneda, :tipo_cambio, :total, :tipo_comprobante,
            :exportacion, :metodo_pago, :lugar_expedicion, :confirmacion,
            :emisor_rfc, :receptor_rfc, :ruta_archivo, NOW()
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':uuid' => $uuid,
            ':version' => $comprobante->getAttribute('Version'),
            ':serie' => $comprobante->getAttribute('Serie'),
            ':folio' => $comprobante->getAttribute('Folio'),
            ':fecha' => $this->convertirFecha($comprobante->getAttribute('Fecha')),
            ':sello' => $comprobante->getAttribute('Sello'),
            ':forma_pago' => $comprobante->getAttribute('FormaPago'),
            ':no_certificado' => $comprobante->getAttribute('NoCertificado'),
            ':certificado' => $comprobante->getAttribute('Certificado'),
            ':condiciones_pago' => $comprobante->getAttribute('CondicionesDePago'),
            ':subtotal' => $this->convertirDecimal($comprobante->getAttribute('SubTotal')),
            ':descuento' => $this->convertirDecimal($comprobante->getAttribute('Descuento')),
            ':moneda' => $comprobante->getAttribute('Moneda'),
            ':tipo_cambio' => $this->convertirDecimal($comprobante->getAttribute('TipoCambio')),
            ':total' => $this->convertirDecimal($comprobante->getAttribute('Total')),
            ':tipo_comprobante' => $comprobante->getAttribute('TipoDeComprobante'),
            ':exportacion' => $comprobante->getAttribute('Exportacion'),
            ':metodo_pago' => $comprobante->getAttribute('MetodoPago'),
            ':lugar_expedicion' => $comprobante->getAttribute('LugarExpedicion'),
            ':confirmacion' => $comprobante->getAttribute('Confirmacion'),
            ':emisor_rfc' => $emisor ? $emisor->getAttribute('Rfc') : null,
            ':receptor_rfc' => $receptor ? $receptor->getAttribute('Rfc') : null,
            ':ruta_archivo' => $archivo
        ]);

        return $this->pdo->lastInsertId();
    }

    private function insertarDatosRelacionados($cfdi_id, $comprobante, $xpath)
    {
        // Insertar Emisor
        $emisor = $xpath->query('.//cfdi:Emisor', $comprobante)->item(0);
        if ($emisor) {
            $this->insertarEmisor($cfdi_id, $emisor);
        }

        // Insertar Receptor
        $receptor = $xpath->query('.//cfdi:Receptor', $comprobante)->item(0);
        if ($receptor) {
            $this->insertarReceptor($cfdi_id, $receptor);
        }

        // Insertar Conceptos
        $conceptos = $xpath->query('.//cfdi:Concepto', $comprobante);
        foreach ($conceptos as $concepto) {
            $this->insertarConcepto($cfdi_id, $concepto, $xpath);
        }

        // Insertar Timbre Fiscal Digital
        $this->insertarTimbreFiscalDigital($cfdi_id, $xpath);

        // Insertar Complementos
        $this->insertarComplementos($cfdi_id, $xpath);
    }

    // Aquí van todos los métodos de inserción que ya teníamos
    // (insertarEmisor, insertarReceptor, etc.)

    private function insertarEmisor($cfdi_id, $emisor)
    {
        $sql = "INSERT INTO emisor (
            cfdi_id, rfc, nombre, regimen_fiscal, fac_atr_adquirente
        ) VALUES (
            :cfdi_id, :rfc, :nombre, :regimen_fiscal, :fac_atr_adquirente
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cfdi_id' => $cfdi_id,
            ':rfc' => $emisor->getAttribute('Rfc'),
            ':nombre' => $emisor->getAttribute('Nombre'),
            ':regimen_fiscal' => $emisor->getAttribute('RegimenFiscal'),
            ':fac_atr_adquirente' => $emisor->getAttribute('FacAtrAdquirente')
        ]);
    }

    private function insertarReceptor($cfdi_id, $receptor)
    {
        $sql = "INSERT INTO receptor (
            cfdi_id, rfc, nombre, uso_cfdi, domicilio_fiscal_receptor,
            residencia_fiscal, num_reg_id_trib
        ) VALUES (
            :cfdi_id, :rfc, :nombre, :uso_cfdi, :domicilio_fiscal_receptor,
            :residencia_fiscal, :num_reg_id_trib
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cfdi_id' => $cfdi_id,
            ':rfc' => $receptor->getAttribute('Rfc'),
            ':nombre' => $receptor->getAttribute('Nombre'),
            ':uso_cfdi' => $receptor->getAttribute('UsoCFDI'),
            ':domicilio_fiscal_receptor' => $receptor->getAttribute('DomicilioFiscalReceptor'),
            ':residencia_fiscal' => $receptor->getAttribute('ResidenciaFiscal'),
            ':num_reg_id_trib' => $receptor->getAttribute('NumRegIdTrib')
        ]);
    }

    private function insertarConcepto($cfdi_id, $concepto, $xpath)
    {
        $sql = "INSERT INTO conceptos (
            cfdi_id, clave_prod_serv, no_identificacion, cantidad,
            clave_unidad, unidad, descripcion, valor_unitario,
            importe, descuento, objeto_imp
        ) VALUES (
            :cfdi_id, :clave_prod_serv, :no_identificacion, :cantidad,
            :clave_unidad, :unidad, :descripcion, :valor_unitario,
            :importe, :descuento, :objeto_imp
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cfdi_id' => $cfdi_id,
            ':clave_prod_serv' => $concepto->getAttribute('ClaveProdServ'),
            ':no_identificacion' => $concepto->getAttribute('NoIdentificacion'),
            ':cantidad' => $this->convertirDecimal($concepto->getAttribute('Cantidad')),
            ':clave_unidad' => $concepto->getAttribute('ClaveUnidad'),
            ':unidad' => $concepto->getAttribute('Unidad'),
            ':descripcion' => $concepto->getAttribute('Descripcion'),
            ':valor_unitario' => $this->convertirDecimal($concepto->getAttribute('ValorUnitario')),
            ':importe' => $this->convertirDecimal($concepto->getAttribute('Importe')),
            ':descuento' => $this->convertirDecimal($concepto->getAttribute('Descuento')),
            ':objeto_imp' => $concepto->getAttribute('ObjetoImp')
        ]);

        $concepto_id = $this->pdo->lastInsertId();

        // Insertar impuestos del concepto
        $traslados = $xpath->query('.//cfdi:Traslado', $concepto);
        foreach ($traslados as $traslado) {
            $this->insertarImpuestoTrasladado($concepto_id, $traslado);
        }

        $retenciones = $xpath->query('.//cfdi:Retencion', $concepto);
        foreach ($retenciones as $retencion) {
            $this->insertarImpuestoRetenido($concepto_id, $retencion);
        }
    }

    private function insertarImpuestoTrasladado($concepto_id, $traslado)
    {
        $sql = "INSERT INTO impuestos_trasladados (
            concepto_id, impuesto, tipo_factor, tasa_cuota, importe, base
        ) VALUES (
            :concepto_id, :impuesto, :tipo_factor, :tasa_cuota, :importe, :base
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':concepto_id' => $concepto_id,
            ':impuesto' => $traslado->getAttribute('Impuesto'),
            ':tipo_factor' => $traslado->getAttribute('TipoFactor'),
            ':tasa_cuota' => $this->convertirDecimal($traslado->getAttribute('TasaOCuota')),
            ':importe' => $this->convertirDecimal($traslado->getAttribute('Importe')),
            ':base' => $this->convertirDecimal($traslado->getAttribute('Base'))
        ]);
    }

    private function insertarImpuestoRetenido($concepto_id, $retencion)
    {
        $sql = "INSERT INTO impuestos_retenidos (
            concepto_id, impuesto, tipo_factor, tasa_cuota, importe, base
        ) VALUES (
            :concepto_id, :impuesto, :tipo_factor, :tasa_cuota, :importe, :base
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':concepto_id' => $concepto_id,
            ':impuesto' => $retencion->getAttribute('Impuesto'),
            ':tipo_factor' => $retencion->getAttribute('TipoFactor'),
            ':tasa_cuota' => $this->convertirDecimal($retencion->getAttribute('TasaOCuota')),
            ':importe' => $this->convertirDecimal($retencion->getAttribute('Importe')),
            ':base' => $this->convertirDecimal($retencion->getAttribute('Base'))
        ]);
    }

    private function insertarTimbreFiscalDigital($cfdi_id, $xpath)
    {
        $timbre = $xpath->query('//tfd:TimbreFiscalDigital')->item(0);
        if (!$timbre) return;

        $sql = "INSERT INTO cfdi_timbre_fiscal_digital (
            cfdi_id, version, uuid, fecha_timbrado, rfc_prov_certif,
            leyenda, sello_cfd, no_certificado_sat, sello_sat
        ) VALUES (
            :cfdi_id, :version, :uuid, :fecha_timbrado, :rfc_prov_certif,
            :leyenda, :sello_cfd, :no_certificado_sat, :sello_sat
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cfdi_id' => $cfdi_id,
            ':version' => $timbre->getAttribute('Version'),
            ':uuid' => $timbre->getAttribute('UUID'),
            ':fecha_timbrado' => $this->convertirFecha($timbre->getAttribute('FechaTimbrado')),
            ':rfc_prov_certif' => $timbre->getAttribute('RfcProvCertif'),
            ':leyenda' => $timbre->getAttribute('Leyenda'),
            ':sello_cfd' => $timbre->getAttribute('SelloCFD'),
            ':no_certificado_sat' => $timbre->getAttribute('NoCertificadoSAT'),
            ':sello_sat' => $timbre->getAttribute('SelloSAT')
        ]);
    }

    private function insertarComplementos($cfdi_id, $xpath)
    {
        // Complemento de Pagos 1.0
        $pagos_v10 = $xpath->query('//pago10:Pagos');
        if ($pagos_v10->length > 0) {
            $this->insertarPagosV10($cfdi_id, $pagos_v10->item(0), $xpath);
        }

        // Complemento de Pagos 2.0
        $pagos_v20 = $xpath->query('//pago20:Pagos');
        if ($pagos_v20->length > 0) {
            $this->insertarPagosV20($cfdi_id, $pagos_v20->item(0), $xpath);
        }

        // Complemento de Nómina
        $this->insertarComplementoNomina($cfdi_id, $xpath);
    }

    private function insertarPagosV10($cfdi_id, $pagos, $xpath)
    {
        $pagos_list = $xpath->query('.//pago10:Pago', $pagos);
        foreach ($pagos_list as $pago) {
            $sql = "INSERT INTO cfdi_complemento_pagos_v10 (
                cfdi_id, fecha_pago, forma_pago_p, moneda_p,
                tipo_cambio_p, monto, num_operacion, rfc_emisor_cta_ord,
                nom_banco_ord_ext, cta_ordenante, rfc_emisor_cta_ben, cta_beneficiario
            ) VALUES (
                :cfdi_id, :fecha_pago, :forma_pago_p, :moneda_p,
                :tipo_cambio_p, :monto, :num_operacion, :rfc_emisor_cta_ord,
                :nom_banco_ord_ext, :cta_ordenante, :rfc_emisor_cta_ben, :cta_beneficiario
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':cfdi_id' => $cfdi_id,
                ':fecha_pago' => $this->convertirFecha($pago->getAttribute('FechaPago')),
                ':forma_pago_p' => $pago->getAttribute('FormaDePagoP'),
                ':moneda_p' => $pago->getAttribute('MonedaP'),
                ':tipo_cambio_p' => $this->convertirDecimal($pago->getAttribute('TipoCambioP')),
                ':monto' => $this->convertirDecimal($pago->getAttribute('Monto')),
                ':num_operacion' => $pago->getAttribute('NumOperacion'),
                ':rfc_emisor_cta_ord' => $pago->getAttribute('RfcEmisorCtaOrd'),
                ':nom_banco_ord_ext' => $pago->getAttribute('NomBancoOrdExt'),
                ':cta_ordenante' => $pago->getAttribute('CtaOrdenante'),
                ':rfc_emisor_cta_ben' => $pago->getAttribute('RfcEmisorCtaBen'),
                ':cta_beneficiario' => $pago->getAttribute('CtaBeneficiario')
            ]);
        }
    }

    private function insertarPagosV20($cfdi_id, $pagos, $xpath)
    {
        $pagos_list = $xpath->query('.//pago20:Pago', $pagos);
        foreach ($pagos_list as $pago) {
            $sql = "INSERT INTO cfdi_complemento_pagos_v20 (
                cfdi_id, fecha_pago, forma_pago_p, moneda_p, tipo_cambio_p, 
                monto, num_operacion, rfc_emisor_cta_ord, nom_banco_ord_ext, 
                cta_ordenante, rfc_emisor_cta_ben, cta_beneficiario, 
                tipo_cad_pago, cert_pago, cad_pago, sello_pago
            ) VALUES (
                :cfdi_id, :fecha_pago, :forma_pago_p, :moneda_p, :tipo_cambio_p,
                :monto, :num_operacion, :rfc_emisor_cta_ord, :nom_banco_ord_ext,
                :cta_ordenante, :rfc_emisor_cta_ben, :cta_beneficiario,
                :tipo_cad_pago, :cert_pago, :cad_pago, :sello_pago
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':cfdi_id' => $cfdi_id,
                ':fecha_pago' => $this->convertirFecha($pago->getAttribute('FechaPago')),
                ':forma_pago_p' => $pago->getAttribute('FormaDePagoP'),
                ':moneda_p' => $pago->getAttribute('MonedaP'),
                ':tipo_cambio_p' => $this->convertirDecimal($pago->getAttribute('TipoCambioP')),
                ':monto' => $this->convertirDecimal($pago->getAttribute('Monto')),
                ':num_operacion' => $pago->getAttribute('NumOperacion'),
                ':rfc_emisor_cta_ord' => $pago->getAttribute('RfcEmisorCtaOrd'),
                ':nom_banco_ord_ext' => $pago->getAttribute('NomBancoOrdExt'),
                ':cta_ordenante' => $pago->getAttribute('CtaOrdenante'),
                ':rfc_emisor_cta_ben' => $pago->getAttribute('RfcEmisorCtaBen'),
                ':cta_beneficiario' => $pago->getAttribute('CtaBeneficiario'),
                ':tipo_cad_pago' => $pago->getAttribute('TipoCadPago'),
                ':cert_pago' => $pago->getAttribute('CertPago'),
                ':cad_pago' => $pago->getAttribute('CadPago'),
                ':sello_pago' => $pago->getAttribute('SelloPago')
            ]);
        }
    }

    private function insertarComplementoNomina($cfdi_id, $xpath)
    {
        $nomina = $xpath->query('//nomina12:Nomina')->item(0);
        if (!$nomina) return;

        $sql = "INSERT INTO cfdi_complemento_nomina (
            cfdi_id, tipo_nomina, fecha_pago, fecha_inicial_pago,
            fecha_final_pago, num_dias_pagados, total_percepciones,
            total_deducciones, total_otros_pagos
        ) VALUES (
            :cfdi_id, :tipo_nomina, :fecha_pago, :fecha_inicial_pago,
            :fecha_final_pago, :num_dias_pagados, :total_percepciones,
            :total_deducciones, :total_otros_pagos
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cfdi_id' => $cfdi_id,
            ':tipo_nomina' => $nomina->getAttribute('TipoNomina'),
            ':fecha_pago' => $this->convertirFecha($nomina->getAttribute('FechaPago')),
            ':fecha_inicial_pago' => $this->convertirFecha($nomina->getAttribute('FechaInicialPago')),
            ':fecha_final_pago' => $this->convertirFecha($nomina->getAttribute('FechaFinalPago')),
            ':num_dias_pagados' => $this->convertirDecimal($nomina->getAttribute('NumDiasPagados')),
            ':total_percepciones' => $this->convertirDecimal($nomina->getAttribute('TotalPercepciones')),
            ':total_deducciones' => $this->convertirDecimal($nomina->getAttribute('TotalDeducciones')),
            ':total_otros_pagos' => $this->convertirDecimal($nomina->getAttribute('TotalOtrosPagos'))
        ]);
    }

    // Métodos auxiliares
    private function registrarNamespaces($xpath)
    {
        $xpath->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/3');
        $xpath->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xpath->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
        $xpath->registerNamespace('pago10', 'http://www.sat.gob.mx/Pagos');
        $xpath->registerNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');
        $xpath->registerNamespace('nomina12', 'http://www.sat.gob.mx/nomina12');
    }

    private function extraerUUID($xpath)
    {
        $timbre = $xpath->query('//tfd:TimbreFiscalDigital')->item(0);
        return $timbre ? $timbre->getAttribute('UUID') : null;
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

    private function mostrarEstadisticas()
    {
        echo "\n================================================================\n";
        echo "📊 ESTADÍSTICAS FINALES\n";
        echo "================================================================\n";
        echo "📁 Archivos procesados: " . $this->stats['archivos_procesados'] . "\n";
        echo "✅ CFDIs insertados: " . $this->stats['insertados'] . "\n";
        echo "🔄 CFDIs actualizados: " . $this->stats['actualizados'] . "\n";
        echo "❌ Errores: " . $this->stats['errores'] . "\n";
        echo "📊 Total exitoso: " . ($this->stats['insertados'] + $this->stats['actualizados']) . "\n";
        echo "================================================================\n";
    }
}

// Ejecutar el importador
if ($argc < 2) {
    echo "Uso: php " . $argv[0] . " <directorio_de_archivos_xml>\n";
    exit(1);
}

$directorio = $argv[1];
if (!is_dir($directorio)) {
    echo "Error: El directorio '$directorio' no existe.\n";
    exit(1);
}

$importador = new ImportadorCFDIUpdate();
$importador->procesarDirectorio($directorio);
