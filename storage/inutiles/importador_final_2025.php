<?php
require_once 'vendor/autoload.php';

class ImportadorCFDIFinal2025
{
    private $pdo;
    private $contadores = [];
    private $errores = [];

    public function __construct()
    {
        $host = 'localhost';
        $dbname = 'sac_db';
        $username = 'root';
        $password = '';

        $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->inicializarContadores();
    }

    private function inicializarContadores()
    {
        $this->contadores = [
            'archivos_procesados' => 0,
            'cfdi_insertados' => 0,
            'cfdi_actualizados' => 0,
            'cfdi_sin_cambios' => 0,
            'emisores' => 0,
            'receptores' => 0,
            'conceptos' => 0,
            'timbres_fiscales' => 0,
            'complementos_pagos_v20' => 0,
            'errores' => 0
        ];
    }

    public function importarDesdeDirectorio($directorio)
    {
        echo "🚀 IMPORTADOR CFDI FINAL 2025 - CON TODAS LAS CORRECCIONES\n";
        echo "============================================================\n";
        echo "✅ SelloSAT incluido en TimbreFiscalDigital\n";
        echo "✅ Complementos Pagos v2.0 con todos los campos\n";
        echo "✅ UPDATE inteligente para duplicados\n\n";

        $archivos = $this->obtenerArchivosXML($directorio);
        $total_archivos = count($archivos);

        echo "📁 Archivos encontrados: $total_archivos\n\n";

        foreach ($archivos as $index => $archivo) {
            $progreso = $index + 1;
            $porcentaje = round(($progreso / $total_archivos) * 100, 1);

            if ($progreso % 500 === 0 || $progreso <= 10) {
                echo "📄 [$progreso/$total_archivos] ($porcentaje%) " . basename($archivo) . "\n";
            }

            try {
                $resultado = $this->procesarArchivoXML($archivo);

                if ($resultado['accion'] === 'insertado') {
                    if ($progreso % 500 === 0 || $progreso <= 10) {
                        echo "   ✅ Nuevo CFDI insertado\n";
                    }
                } elseif ($resultado['accion'] === 'actualizado') {
                    echo "   🔄 CFDI actualizado: " . implode(', ', $resultado['cambios']) . "\n";
                }
            } catch (Exception $e) {
                if ($this->contadores['errores'] < 10) {
                    echo "   ❌ Error en " . basename($archivo) . ": " . $e->getMessage() . "\n";
                }
                $this->contadores['errores']++;
                $this->errores[] = [
                    'archivo' => $archivo,
                    'error' => $e->getMessage()
                ];
            }

            $this->contadores['archivos_procesados']++;

            // Estadísticas cada 2000 archivos
            if ($progreso % 2000 === 0) {
                echo "📊 Progreso: $progreso/$total_archivos - Insertados: {$this->contadores['cfdi_insertados']} - Actualizados: {$this->contadores['cfdi_actualizados']} - Errores: {$this->contadores['errores']}\n";
            }
        }

        $this->mostrarResumenFinal();
    }

    private function procesarArchivoXML($archivo_path)
    {
        $xml_content = file_get_contents($archivo_path);
        $xml = new DOMDocument();
        libxml_use_internal_errors(true);

        if (!$xml->loadXML($xml_content)) {
            throw new Exception("Error al parsear XML");
        }

        // Configurar namespaces
        $xpath = new DOMXPath($xml);
        $this->configurarNamespaces($xpath);

        // Extraer UUID del TimbreFiscalDigital
        $tfd = $xpath->query('//tfd:TimbreFiscalDigital')->item(0);
        if (!$tfd) {
            throw new Exception("TimbreFiscalDigital no encontrado");
        }

        $uuid = $tfd->getAttribute('UUID');
        if (!$uuid) {
            throw new Exception("UUID no encontrado");
        }

        // Verificar si ya existe
        $cfdi_existente = $this->buscarCFDIPorUUID($uuid);

        if ($cfdi_existente) {
            // Ya existe, verificar cambios
            return $this->actualizarCFDISiEsNecesario($cfdi_existente, $xpath, $archivo_path);
        } else {
            // No existe, insertar nuevo
            return $this->insertarNuevoCFDI($xpath, $archivo_path);
        }
    }

    private function configurarNamespaces($xpath)
    {
        $namespaces = [
            'cfdi' => 'http://www.sat.gob.mx/cfd/3',
            'cfdi4' => 'http://www.sat.gob.mx/cfd/4',
            'tfd' => 'http://www.sat.gob.mx/TimbreFiscalDigital',
            'pago20' => 'http://www.sat.gob.mx/Pagos20',
            'pago10' => 'http://www.sat.gob.mx/Pagos'
        ];

        foreach ($namespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }
    }

    private function buscarCFDIPorUUID($uuid)
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, t.uuid
            FROM cfdi c 
            INNER JOIN cfdi_timbre_fiscal_digital t ON c.id = t.cfdi_id 
            WHERE t.uuid = ?
        ");
        $stmt->execute([$uuid]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function actualizarCFDISiEsNecesario($cfdi_existente, $xpath, $archivo_path)
    {
        // Extraer datos del XML
        $comprobante = $xpath->query('//cfdi:Comprobante | //cfdi4:Comprobante')->item(0);
        if (!$comprobante) {
            throw new Exception("Comprobante no encontrado");
        }

        $datos_xml = [
            'fecha' => $comprobante->getAttribute('Fecha'),
            'total' => $comprobante->getAttribute('Total') ?: '0'
        ];

        // Verificar cambios críticos
        $cambios = $this->detectarCambiosCriticos($datos_xml, $cfdi_existente);

        if (empty($cambios)) {
            $this->contadores['cfdi_sin_cambios']++;
            return ['accion' => 'sin_cambios'];
        }

        // Hay cambios, actualizar
        $this->pdo->beginTransaction();
        try {
            $this->actualizarCFDI($cfdi_existente['id'], $datos_xml);
            $this->pdo->commit();

            $this->contadores['cfdi_actualizados']++;
            return ['accion' => 'actualizado', 'cambios' => array_keys($cambios)];
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }

    private function detectarCambiosCriticos($datos_xml, $datos_bd)
    {
        $cambios = [];

        // Normalizar fecha
        $fecha_xml = str_replace('T', ' ', $datos_xml['fecha']);
        $fecha_bd = $datos_bd['fecha'];

        if ($fecha_xml !== $fecha_bd) {
            $cambios['fecha'] = true;
        }

        // Comparar totales (convertir a decimal con 6 decimales)
        $total_xml = number_format((float)$datos_xml['total'], 6, '.', '');
        $total_bd = number_format((float)$datos_bd['total'], 6, '.', '');

        if ($total_xml !== $total_bd) {
            $cambios['total'] = true;
        }

        return $cambios;
    }

    private function insertarNuevoCFDI($xpath, $archivo_path)
    {
        $this->pdo->beginTransaction();

        try {
            // 1. Insertar CFDI principal
            $cfdi_id = $this->insertarCFDIPrincipal($xpath, $archivo_path);

            // 2. Insertar TimbreFiscalDigital (CON SelloSAT)
            $this->insertarTimbreFiscalCompleto($cfdi_id, $xpath);

            // 3. Insertar Emisor
            $this->insertarEmisor($cfdi_id, $xpath);

            // 4. Insertar Receptor
            $this->insertarReceptor($cfdi_id, $xpath);

            // 5. Insertar Conceptos
            $this->insertarConceptos($cfdi_id, $xpath);

            // 6. Insertar Complementos de Pagos v2.0 (CORREGIDOS)
            $this->insertarComplementosCorregidos($cfdi_id, $xpath);

            $this->pdo->commit();
            $this->contadores['cfdi_insertados']++;

            return ['accion' => 'insertado', 'cfdi_id' => $cfdi_id];
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }

    private function insertarCFDIPrincipal($xpath, $archivo_path)
    {
        $comprobante = $xpath->query('//cfdi:Comprobante | //cfdi4:Comprobante')->item(0);
        if (!$comprobante) {
            throw new Exception("Comprobante no encontrado");
        }

        $sql = "INSERT INTO cfdi (
            version, serie, folio, fecha, sello, forma_pago, no_certificado,
            certificado, condiciones_pago, subtotal, descuento, moneda,
            tipo_cambio, total, tipo_comprobante, metodo_pago, lugar_expedicion,
            confirmacion, fecha_procesamiento, ruta_xml
        ) VALUES (
            :version, :serie, :folio, :fecha, :sello, :forma_pago, :no_certificado,
            :certificado, :condiciones_pago, :subtotal, :descuento, :moneda,
            :tipo_cambio, :total, :tipo_comprobante, :metodo_pago, :lugar_expedicion,
            :confirmacion, NOW(), :ruta_xml
        )";

        $stmt = $this->pdo->prepare($sql);

        // Generar ruta relativa del archivo XML
        $ruta_relativa = $this->generarRutaRelativa($archivo_path);

        $stmt->execute([
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
            ':moneda' => $comprobante->getAttribute('Moneda') ?: 'MXN',
            ':tipo_cambio' => $this->convertirDecimal($comprobante->getAttribute('TipoCambio')) ?: 1.0,
            ':total' => $this->convertirDecimal($comprobante->getAttribute('Total')),
            ':tipo_comprobante' => $comprobante->getAttribute('TipoDeComprobante'),
            ':metodo_pago' => $comprobante->getAttribute('MetodoPago'),
            ':lugar_expedicion' => $comprobante->getAttribute('LugarExpedicion'),
            ':confirmacion' => $comprobante->getAttribute('Confirmacion'),
            ':ruta_xml' => $ruta_relativa
        ]);

        return $this->pdo->lastInsertId();
    }

    private function insertarTimbreFiscalCompleto($cfdi_id, $xpath)
    {
        $tfd = $xpath->query('//tfd:TimbreFiscalDigital')->item(0);
        if (!$tfd) return;

        $sql = "INSERT INTO cfdi_timbre_fiscal_digital (
            cfdi_id, uuid, fecha_timbrado, rfc_prov_certif,
            sello_cfd, no_certificado_sat, sello_sat
        ) VALUES (
            :cfdi_id, :uuid, :fecha_timbrado, :rfc_prov_certif,
            :sello_cfd, :no_certificado_sat, :sello_sat
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cfdi_id' => $cfdi_id,
            ':uuid' => $tfd->getAttribute('UUID'),
            ':fecha_timbrado' => $this->convertirFecha($tfd->getAttribute('FechaTimbrado')),
            ':rfc_prov_certif' => $tfd->getAttribute('RfcProvCertif'),
            ':sello_cfd' => $tfd->getAttribute('SelloCFD'),
            ':no_certificado_sat' => $tfd->getAttribute('NoCertificadoSAT'),
            ':sello_sat' => $tfd->getAttribute('SelloSAT') // ✅ CAMPO CORREGIDO
        ]);

        $this->contadores['timbres_fiscales']++;
    }

    private function insertarEmisor($cfdi_id, $xpath)
    {
        $emisor = $xpath->query('//cfdi:Emisor | //cfdi4:Emisor')->item(0);
        if (!$emisor) return;

        $sql = "INSERT INTO emisor (
            cfdi_id, rfc, nombre, regimen_fiscal
        ) VALUES (
            :cfdi_id, :rfc, :nombre, :regimen_fiscal
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cfdi_id' => $cfdi_id,
            ':rfc' => $emisor->getAttribute('Rfc'),
            ':nombre' => $emisor->getAttribute('Nombre'),
            ':regimen_fiscal' => $emisor->getAttribute('RegimenFiscal')
        ]);

        $this->contadores['emisores']++;
    }

    private function insertarReceptor($cfdi_id, $xpath)
    {
        $receptor = $xpath->query('//cfdi:Receptor | //cfdi4:Receptor')->item(0);
        if (!$receptor) return;

        $sql = "INSERT INTO receptor (
            cfdi_id, rfc, nombre, uso_cfdi
        ) VALUES (
            :cfdi_id, :rfc, :nombre, :uso_cfdi
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cfdi_id' => $cfdi_id,
            ':rfc' => $receptor->getAttribute('Rfc'),
            ':nombre' => $receptor->getAttribute('Nombre'),
            ':uso_cfdi' => $receptor->getAttribute('UsoCFDI')
        ]);

        $this->contadores['receptores']++;
    }

    private function insertarConceptos($cfdi_id, $xpath)
    {
        $conceptos = $xpath->query('//cfdi:Concepto | //cfdi4:Concepto');

        foreach ($conceptos as $concepto) {
            $sql = "INSERT INTO conceptos (
                cfdi_id, clave_prod_serv, no_identificacion, cantidad,
                clave_unidad, unidad, descripcion, valor_unitario, importe,
                descuento
            ) VALUES (
                :cfdi_id, :clave_prod_serv, :no_identificacion, :cantidad,
                :clave_unidad, :unidad, :descripcion, :valor_unitario, :importe,
                :descuento
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
                ':descuento' => $this->convertirDecimal($concepto->getAttribute('Descuento'))
            ]);

            $this->contadores['conceptos']++;
        }
    }

    private function insertarComplementosCorregidos($cfdi_id, $xpath)
    {
        // Complementos de Pagos v2.0 (TODOS LOS CAMPOS)
        $pagos20 = $xpath->query('//pago20:Pagos');
        if ($pagos20->length > 0) {
            $this->insertarComplementosPagosV20Completo($cfdi_id, $pagos20->item(0), $xpath);
        }
    }

    private function insertarComplementosPagosV20Completo($cfdi_id, $pagos, $xpath)
    {
        $pagos_list = $xpath->query('.//pago20:Pago', $pagos);

        foreach ($pagos_list as $pago) {
            $sql = "INSERT INTO cfdi_complemento_pagos_v20 (
                cfdi_id, fecha_pago, forma_pago_p, moneda_p, tipo_cambio_p, monto,
                num_operacion, rfc_emisor_cta_ord, nom_banco_ord_ext, cta_ordenante,
                rfc_emisor_cta_ben, cta_beneficiario, tipo_cad_pago, cert_pago,
                cad_pago, sello_pago
            ) VALUES (
                :cfdi_id, :fecha_pago, :forma_pago_p, :moneda_p, :tipo_cambio_p, :monto,
                :num_operacion, :rfc_emisor_cta_ord, :nom_banco_ord_ext, :cta_ordenante,
                :rfc_emisor_cta_ben, :cta_beneficiario, :tipo_cad_pago, :cert_pago,
                :cad_pago, :sello_pago
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':cfdi_id' => $cfdi_id,
                ':fecha_pago' => $this->convertirFecha($pago->getAttribute('FechaPago')),
                ':forma_pago_p' => $pago->getAttribute('FormaDePagoP'),
                ':moneda_p' => $pago->getAttribute('MonedaP') ?: 'MXN',
                ':tipo_cambio_p' => $this->convertirDecimal($pago->getAttribute('TipoCambioP')),
                ':monto' => $this->convertirDecimal($pago->getAttribute('Monto')),
                ':num_operacion' => $pago->getAttribute('NumOperacion'),           // ✅ CORREGIDO
                ':rfc_emisor_cta_ord' => $pago->getAttribute('RfcEmisorCtaOrd'),   // ✅ CORREGIDO
                ':nom_banco_ord_ext' => $pago->getAttribute('NomBancoOrdExt'),     // ✅ CORREGIDO
                ':cta_ordenante' => $pago->getAttribute('CtaOrdenante'),           // ✅ CORREGIDO
                ':rfc_emisor_cta_ben' => $pago->getAttribute('RfcEmisorCtaBen'),   // ✅ CORREGIDO
                ':cta_beneficiario' => $pago->getAttribute('CtaBeneficiario'),     // ✅ CORREGIDO
                ':tipo_cad_pago' => $pago->getAttribute('TipoCadPago'),            // ✅ CORREGIDO
                ':cert_pago' => $pago->getAttribute('CertPago'),                   // ✅ CORREGIDO
                ':cad_pago' => $pago->getAttribute('CadPago'),                     // ✅ CORREGIDO
                ':sello_pago' => $pago->getAttribute('SelloPago')                  // ✅ CORREGIDO
            ]);

            $this->contadores['complementos_pagos_v20']++;
        }
    }

    private function actualizarCFDI($cfdi_id, $datos_xml)
    {
        $sql = "UPDATE cfdi SET 
            fecha = ?, total = ?, fecha_procesamiento = NOW()
            WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            str_replace('T', ' ', $datos_xml['fecha']),
            $this->convertirDecimal($datos_xml['total']),
            $cfdi_id
        ]);
    }

    // Métodos auxiliares...
    private function convertirFecha($fecha)
    {
        if (empty($fecha)) return null;
        try {
            $datetime = new DateTime($fecha);
            return $datetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    private function convertirDecimal($valor)
    {
        return empty($valor) ? 0 : (float)$valor;
    }

    private function obtenerArchivosXML($directorio)
    {
        $archivos = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directorio)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'xml') {
                $archivos[] = $file->getPathname();
            }
        }

        return $archivos;
    }

    private function generarRutaRelativa($archivo_path)
    {
        // Obtener la ruta base del proyecto
        $ruta_base = realpath(__DIR__);

        // Obtener la ruta completa del archivo
        $ruta_completa = realpath($archivo_path);

        // Si no se puede resolver la ruta, usar la original
        if (!$ruta_completa) {
            return $archivo_path;
        }

        // Generar ruta relativa desde la base del proyecto
        $ruta_relativa = str_replace($ruta_base . DIRECTORY_SEPARATOR, '', $ruta_completa);

        // Normalizar separadores para compatibilidad cross-platform
        $ruta_relativa = str_replace('\\', '/', $ruta_relativa);

        return $ruta_relativa;
    }

    private function mostrarResumenFinal()
    {
        echo "\n📊 RESUMEN FINAL\n";
        echo "================\n";
        foreach ($this->contadores as $key => $valor) {
            echo "📈 " . ucfirst(str_replace('_', ' ', $key)) . ": $valor\n";
        }

        $tasa_exito = round((($this->contadores['cfdi_insertados'] + $this->contadores['cfdi_actualizados']) / $this->contadores['archivos_procesados']) * 100, 2);
        echo "\n🎯 Tasa de éxito: $tasa_exito%\n";

        if (!empty($this->errores)) {
            echo "\n❌ PRIMEROS ERRORES:\n";
            foreach (array_slice($this->errores, 0, 5) as $error) {
                echo "   📄 " . basename($error['archivo']) . ": " . $error['error'] . "\n";
            }
        }
    }
}

// Uso del importador
try {
    $importador = new ImportadorCFDIFinal2025();
    $importador->importarDesdeDirectorio('storage/sat_downloads');
} catch (Exception $e) {
    echo "❌ Error fatal: " . $e->getMessage() . "\n";
}
