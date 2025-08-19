<?php
require 'src/config/database.php';

class ImportadorCFDICompleto
{
    private $pdo;
    private $stats = [
        'archivos_procesados' => 0,
        'cfdi_insertados' => 0,
        'pagos_insertados' => 0,
        'conceptos_insertados' => 0,
        'complementos_insertados' => 0,
        'timbres_insertados' => 0,
        'errores' => 0,
        'archivos_omitidos' => 0
    ];

    public function __construct()
    {
        $this->pdo = getDatabase();
        echo "🔗 Conectado a la base de datos\n\n";
    }

    public function importarArchivosReales()
    {
        echo "=== IMPORTACIÓN COMPLETA DE ARCHIVOS XML REALES ===\n\n";

        $directorio = 'storage/sat_downloads';
        if (!is_dir($directorio)) {
            throw new Exception("Directorio no encontrado: {$directorio}");
        }

        // Obtener todos los archivos XML recursivamente
        $archivos = $this->buscarArchivosXML($directorio);
        $total_archivos = count($archivos);

        echo "📁 Directorio base: {$directorio}\n";
        echo "📄 Total archivos XML encontrados: {$total_archivos}\n\n";

        if ($total_archivos == 0) {
            echo "❌ No se encontraron archivos XML en el directorio.\n";
            return;
        }

        echo "🚀 Iniciando procesamiento de archivos reales...\n";
        echo "⚠️  MODO PRUEBA: Limitando a 100 archivos\n\n";

        $inicio = microtime(true);

        foreach ($archivos as $index => $ruta_archivo) {
            // LIMITAR A 100 ARCHIVOS PARA PRUEBA
            if ($index >= 100) {
                echo "⚠️  Alcanzado límite de prueba (100 archivos)\n";
                break;
            }
            
            try {
                $this->procesarArchivoXMLReal($ruta_archivo);
                $this->stats['archivos_procesados']++;

                // Mostrar progreso cada 25 archivos en modo prueba
                if (($index + 1) % 25 == 0) {
                    $actual = $index + 1;
                    echo "📊 Progreso: {$actual}/100 archivos de prueba\n";
                    echo "   CFDIs: " . $this->stats['cfdi_insertados'] . ", ";
                    echo "Pagos: " . $this->stats['pagos_insertados'] . ", ";
                    echo "Errores: " . $this->stats['errores'] . "\n\n";
                }
            } catch (Exception $e) {
                $this->stats['errores']++;
                $this->registrarError($ruta_archivo, $e->getMessage());

                // Mostrar algunos errores para debugging
                if ($this->stats['errores'] <= 5) {
                    $mensaje_corto = substr($e->getMessage(), 0, 100) . "...";
                    echo "⚠️  Error en archivo {$ruta_archivo}: {$mensaje_corto}\n";
                }
            }
        }

        $tiempo_total = round(microtime(true) - $inicio, 2);
        $this->mostrarEstadisticasFinales($tiempo_total);
    }

    private function buscarArchivosXML($directorio)
    {
        $archivos = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directorio, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $archivo) {
            if ($archivo->isFile() && strtolower($archivo->getExtension()) === 'xml') {
                $archivos[] = $archivo->getPathname();
            }
        }

        return $archivos;
    }

    private function procesarArchivoXMLReal($ruta_archivo)
    {
        // Verificar que el archivo existe y es legible
        if (!file_exists($ruta_archivo) || !is_readable($ruta_archivo)) {
            throw new Exception("Archivo no existe o no es legible");
        }

        // Cargar XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($ruta_archivo);

        if ($xml === false) {
            $errores = libxml_get_errors();
            $mensaje_error = !empty($errores) ? $errores[0]->message : "XML malformado";
            throw new Exception("Error al parsear XML: " . trim($mensaje_error));
        }

        // Extraer UUID del timbre fiscal
        $uuid = $this->extraerUUIDReal($xml);
        if (!$uuid) {
            throw new Exception("No se pudo extraer UUID del archivo");
        }

        // Verificar si ya existe
        if ($this->existeCFDI($uuid)) {
            $this->stats['archivos_omitidos']++;
            return; // Ya existe, omitir
        }

        // Extraer datos del comprobante
        $datos_cfdi = $this->extraerDatosComprobanteReal($xml, $ruta_archivo);
        $datos_cfdi['uuid'] = $uuid;

        // Insertar CFDI principal
        $cfdi_id = $this->insertarCFDIReal($datos_cfdi);

        // Procesar elementos relacionados
        $this->procesarTimbreFiscalReal($xml, $cfdi_id);
        $this->procesarConceptosReales($xml, $cfdi_id);
        $this->procesarComplementosReales($xml, $cfdi_id);

        // Registrar en auditoría
        $this->registrarEnAuditoria($ruta_archivo, $uuid, 'PROCESADO_REAL');
    }

    private function extraerUUIDReal($xml)
    {
        $uuid = null;

        // Método 1: Buscar con XPath (más confiable)
        if (method_exists($xml, 'xpath')) {
            $uuids = $xml->xpath('//@UUID');
            if (!empty($uuids)) {
                $uuid = (string)$uuids[0];
                if ($uuid && strlen($uuid) >= 32) {
                    return $uuid;
                }
            }
        }

        // Método 2: Buscar en complementos TimbreFiscalDigital (CFDI 4.0)
        if (isset($xml->Complemento)) {
            foreach ($xml->Complemento->children() as $complemento) {
                if ($complemento->getName() === 'TimbreFiscalDigital') {
                    $uuid = (string)$complemento->attributes()->UUID;
                    if ($uuid) return $uuid;
                }
            }
        }

        // Método 3: Buscar en namespaces específicos
        $namespaces = $xml->getNamespaces(true);
        foreach ($namespaces as $prefix => $uri) {
            // Buscar namespaces de timbre fiscal
            if (
                strpos($uri, 'TimbreFiscalDigital') !== false ||
                strpos($uri, 'tfd') !== false ||
                strpos($prefix, 'tfd') !== false
            ) {

                if (isset($xml->Complemento)) {
                    $complementos = $xml->Complemento->children($uri);
                    foreach ($complementos as $complemento) {
                        $attrs = $complemento->attributes();
                        if (isset($attrs->UUID)) {
                            $uuid = (string)$attrs->UUID;
                            if ($uuid) return $uuid;
                        }
                    }
                }
            }
        }

        // Método 4: Buscar recursivamente en toda la estructura
        $uuid = $this->buscarUUIDRecursivo($xml);
        if ($uuid) return $uuid;

        return null;
    }

    private function buscarUUIDRecursivo($node)
    {
        // Verificar atributos del nodo actual
        if (method_exists($node, 'attributes')) {
            $attrs = $node->attributes();
            if (isset($attrs->UUID)) {
                $uuid = (string)$attrs->UUID;
                if ($uuid && strlen($uuid) >= 32) {
                    return $uuid;
                }
            }
        }

        // Buscar en hijos
        if (method_exists($node, 'children')) {
            foreach ($node->children() as $child) {
                $uuid = $this->buscarUUIDRecursivo($child);
                if ($uuid) return $uuid;
            }

            // Buscar en todos los namespaces de los hijos
            $namespaces = $node->getNamespaces(true);
            foreach ($namespaces as $uri) {
                foreach ($node->children($uri) as $child) {
                    $uuid = $this->buscarUUIDRecursivo($child);
                    if ($uuid) return $uuid;
                }
            }
        }

        return null;
    }

    private function existeCFDI($uuid)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM cfdi WHERE uuid = ? LIMIT 1");
        $stmt->execute([$uuid]);
        return $stmt->fetch() !== false;
    }

    private function extraerDatosComprobanteReal($xml, $archivo)
    {
        $comp = $xml->attributes();

        // Extraer datos del emisor
        $emisor_attrs = $xml->Emisor->attributes();
        $rfc_emisor = (string)$emisor_attrs->Rfc;
        $nombre_emisor = (string)$emisor_attrs->Nombre;

        // Extraer datos del receptor
        $receptor_attrs = $xml->Receptor->attributes();
        $rfc_receptor = (string)$receptor_attrs->Rfc;
        $nombre_receptor = (string)$receptor_attrs->Nombre;
        $uso_cfdi = (string)$receptor_attrs->UsoCFDI;

        return [
            'version' => (string)$comp->Version ?: '4.0',
            'serie' => (string)$comp->Serie,
            'folio' => (string)$comp->Folio,
            'fecha' => $this->convertirFechaReal((string)$comp->Fecha),
            'sello' => (string)$comp->Sello,
            'forma_pago' => (string)$comp->FormaPago,
            'no_certificado' => (string)$comp->NoCertificado,
            'certificado' => substr((string)$comp->Certificado, 0, 1000), // Limitar tamaño
            'subtotal' => (float)$comp->SubTotal,
            'descuento' => (float)$comp->Descuento,
            'moneda' => (string)$comp->Moneda ?: 'MXN',
            'tipo_cambio' => (float)$comp->TipoCambio ?: 1.0,
            'total' => (float)$comp->Total,
            'tipo_comprobante' => (string)$comp->TipoDeComprobante,
            'metodo_pago' => (string)$comp->MetodoPago,
            'lugar_expedicion' => (string)$comp->LugarExpedicion,
            'rfc_emisor' => $rfc_emisor,
            'nombre_emisor' => $nombre_emisor,
            'rfc_receptor' => $rfc_receptor,
            'nombre_receptor' => $nombre_receptor,
            'uso_cfdi' => $uso_cfdi,
            'archivo_xml' => $archivo,
            'tipo' => (string)$comp->TipoDeComprobante
        ];
    }

    private function convertirFechaReal($fecha_str)
    {
        if (empty($fecha_str)) return null;

        // Intentar diferentes formatos
        $formatos = [
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:s.u',
            DateTime::ISO8601,
            DateTime::ATOM
        ];

        foreach ($formatos as $formato) {
            $fecha = DateTime::createFromFormat($formato, $fecha_str);
            if ($fecha !== false) {
                return $fecha->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    private function insertarCFDIReal($datos)
    {
        $sql = "INSERT INTO cfdi (
            uuid, version, serie, folio, fecha, sello, forma_pago, no_certificado,
            certificado, subtotal, descuento, moneda, tipo_cambio, total,
            tipo_comprobante, metodo_pago, lugar_expedicion, rfc_emisor, nombre_emisor,
            rfc_receptor, nombre_receptor, uso_cfdi, archivo_xml, tipo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $resultado = $stmt->execute([
            $datos['uuid'],
            $datos['version'],
            $datos['serie'],
            $datos['folio'],
            $datos['fecha'],
            $datos['sello'],
            $datos['forma_pago'],
            $datos['no_certificado'],
            $datos['certificado'],
            $datos['subtotal'],
            $datos['descuento'],
            $datos['moneda'],
            $datos['tipo_cambio'],
            $datos['total'],
            $datos['tipo_comprobante'],
            $datos['metodo_pago'],
            $datos['lugar_expedicion'],
            $datos['rfc_emisor'],
            $datos['nombre_emisor'],
            $datos['rfc_receptor'],
            $datos['nombre_receptor'],
            $datos['uso_cfdi'],
            $datos['archivo_xml'],
            $datos['tipo']
        ]);

        if ($resultado) {
            $this->stats['cfdi_insertados']++;
            return $this->pdo->lastInsertId();
        }

        throw new Exception("Error al insertar CFDI en base de datos");
    }

    private function procesarTimbreFiscalReal($xml, $cfdi_id)
    {
        $timbre_insertado = false;

        // Método 1: Buscar en complementos (CFDI 4.0)
        if (isset($xml->Complemento)) {
            foreach ($xml->Complemento->children() as $complemento) {
                if ($complemento->getName() === 'TimbreFiscalDigital') {
                    $this->insertarTimbreFiscal($complemento->attributes(), $cfdi_id);
                    $timbre_insertado = true;
                    break;
                }
            }
        }

        // Método 2: Buscar en namespaces específicos
        if (!$timbre_insertado) {
            $namespaces = $xml->getNamespaces(true);
            foreach ($namespaces as $prefix => $uri) {
                if (
                    strpos($uri, 'TimbreFiscalDigital') !== false ||
                    strpos($prefix, 'tfd') !== false
                ) {

                    if (isset($xml->Complemento)) {
                        $complementos = $xml->Complemento->children($uri);
                        foreach ($complementos as $complemento) {
                            if ($complemento->getName() === 'TimbreFiscalDigital') {
                                $this->insertarTimbreFiscal($complemento->attributes(), $cfdi_id);
                                $timbre_insertado = true;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        // Método 3: Buscar con XPath para casos especiales
        if (!$timbre_insertado && method_exists($xml, 'xpath')) {
            $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');
            if (!empty($timbres)) {
                $this->insertarTimbreFiscal($timbres[0]->attributes(), $cfdi_id);
                $timbre_insertado = true;
            }
        }

        return $timbre_insertado;
    }

    private function insertarTimbreFiscal($attrs, $cfdi_id)
    {
        $sql = "INSERT INTO cfdi_timbre_fiscal (
            cfdi_id, uuid, fecha_timbrado, rfc_prov_certif, selloCFD,
            noCertificadoSAT, selloSAT, version
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $cfdi_id,
            (string)$attrs->UUID,
            $this->convertirFechaReal((string)$attrs->FechaTimbrado),
            (string)$attrs->RfcProvCertif,
            (string)$attrs->SelloCFD,
            (string)$attrs->NoCertificadoSAT,
            (string)$attrs->SelloSAT,
            (string)$attrs->Version
        ]);

        $this->stats['timbres_insertados']++;
    }

    private function procesarConceptosReales($xml, $cfdi_id)
    {
        if (!isset($xml->Conceptos)) return;

        foreach ($xml->Conceptos->Concepto as $concepto) {
            $attrs = $concepto->attributes();

            $sql = "INSERT INTO cfdi_conceptos (
                cfdi_id, clave_prodserv, cantidad, clave_unidad, unidad, descripcion,
                valor_unitario, importe, descuento, objeto_imp
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $cfdi_id,
                (string)$attrs->ClaveProdServ,
                (float)$attrs->Cantidad,
                (string)$attrs->ClaveUnidad,
                (string)$attrs->Unidad,
                substr((string)$attrs->Descripcion, 0, 255), // Limitar tamaño
                (float)$attrs->ValorUnitario,
                (float)$attrs->Importe,
                (float)$attrs->Descuento,
                (string)$attrs->ObjetoImp
            ]);

            $this->stats['conceptos_insertados']++;
        }
    }

    private function procesarComplementosReales($xml, $cfdi_id)
    {
        if (!isset($xml->Complemento)) return;

        foreach ($xml->Complemento->children() as $complemento) {
            $nombre_complemento = $complemento->getName();

            // Procesar complementos de pago específicamente
            if ($nombre_complemento === 'Pagos') {
                $this->procesarPagosReales($complemento, $cfdi_id);
            }

            // Guardar todos los complementos como JSON
            $json_data = json_encode($this->xmlToArray($complemento), JSON_UNESCAPED_UNICODE);

            $sql = "INSERT INTO cfdi_complementos (cfdi_id, tipo, datos_json) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$cfdi_id, $nombre_complemento, $json_data]);

            $this->stats['complementos_insertados']++;
        }
    }

    private function procesarPagosReales($pagos_complemento, $cfdi_id)
    {
        if (!isset($pagos_complemento->Pago)) return;

        foreach ($pagos_complemento->Pago as $pago) {
            $attrs = $pago->attributes();

            $sql = "INSERT INTO cfdi_pagos (
                cfdi_id, fecha_pago, forma_pago_p, moneda_p, tipo_cambio_p, monto
            ) VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $cfdi_id,
                $this->convertirFechaReal((string)$attrs->FechaPago),
                (string)$attrs->FormaDePagoP,
                (string)$attrs->MonedaP,
                (float)$attrs->TipoCambioP,
                (float)$attrs->Monto
            ]);

            $this->stats['pagos_insertados']++;
        }
    }

    private function xmlToArray($xmlNode)
    {
        $array = [];

        // Atributos
        foreach ($xmlNode->attributes() as $key => $value) {
            $array['@attributes'][$key] = (string)$value;
        }

        // Elementos hijos
        foreach ($xmlNode->children() as $child) {
            $childArray = $this->xmlToArray($child);
            $array[$child->getName()][] = $childArray;
        }

        // Valor del nodo si no tiene hijos
        if (empty($array) && (string)$xmlNode !== '') {
            return (string)$xmlNode;
        }

        return $array;
    }

    private function registrarEnAuditoria($archivo, $uuid, $estado)
    {
        $sql = "INSERT INTO cfdi_auditoria (archivo, uuid, estado, fecha, mensaje) VALUES (?, ?, ?, NOW(), ?)";
        $stmt = $this->pdo->prepare($sql);
        $mensaje = date('Y-m-d H:i:s') . " | {$estado} | {$archivo}";
        $stmt->execute([$archivo, $uuid, $estado, $mensaje]);
    }

    private function registrarError($archivo, $error)
    {
        $sql = "INSERT INTO cfdi_auditoria (archivo, uuid, estado, fecha, mensaje) VALUES (?, ?, ?, NOW(), ?)";
        $stmt = $this->pdo->prepare($sql);
        $mensaje = date('Y-m-d H:i:s') . " | ERROR | {$error}";
        $stmt->execute([$archivo, 'ERROR', 'ERROR', $mensaje]);
    }

    private function mostrarEstadisticasFinales($tiempo)
    {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "📊 ESTADÍSTICAS FINALES DE IMPORTACIÓN REAL\n";
        echo str_repeat("=", 70) . "\n\n";

        echo "⏱️  Tiempo total de procesamiento: {$tiempo} segundos\n";
        echo "📄 Archivos XML procesados: {$this->stats['archivos_procesados']}\n";
        echo "📋 CFDIs insertados: {$this->stats['cfdi_insertados']}\n";
        echo "🏷️  Timbres fiscales procesados: {$this->stats['timbres_insertados']}\n";
        echo "🧾 Conceptos insertados: {$this->stats['conceptos_insertados']}\n";
        echo "📎 Complementos insertados: {$this->stats['complementos_insertados']}\n";
        echo "💰 Pagos procesados: {$this->stats['pagos_insertados']}\n";
        echo "⏭️  Archivos omitidos (duplicados): {$this->stats['archivos_omitidos']}\n";
        echo "⚠️  Errores encontrados: {$this->stats['errores']}\n\n";

        if ($this->stats['cfdi_insertados'] > 0) {
            echo "✅ IMPORTACIÓN REAL COMPLETADA EXITOSAMENTE\n";
            $velocidad = $tiempo > 0 ? round($this->stats['archivos_procesados'] / $tiempo, 2) : 0;
            echo "📈 Velocidad de procesamiento: {$velocidad} archivos/segundo\n";

            $porcentaje_exito = round(($this->stats['cfdi_insertados'] / ($this->stats['archivos_procesados'] + $this->stats['archivos_omitidos'])) * 100, 2);
            echo "📊 Tasa de éxito: {$porcentaje_exito}%\n";
        } else {
            echo "❌ NO SE IMPORTARON DATOS - REVISAR ERRORES\n";
        }

        echo "\n🎯 Datos 100% reales importados desde archivos XML físicos\n";
    }
}

// Configurar entorno para procesamiento pesado
set_time_limit(0);
ini_set('memory_limit', '2048M');

echo "🚀 INICIANDO IMPORTACIÓN REAL DE ARCHIVOS XML\n";
echo "⚙️  Configuración: Sin límite de tiempo, 2GB memoria\n\n";

try {
    $importador = new ImportadorCFDICompleto();
    $importador->importarArchivosReales();
} catch (Exception $e) {
    echo "\n❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . "\n";
    echo "📍 Línea: " . $e->getLine() . "\n";
}
