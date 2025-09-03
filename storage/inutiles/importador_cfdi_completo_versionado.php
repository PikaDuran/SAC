<?php
require_once 'vendor/autoload.php';

class ImportadorCFDICompleto {
    private $pdo;
    
    public function __construct() {
        $this->conectarBD();
    }
    
    private function conectarBD() {
        try {
            $this->pdo = new PDO(
                "mysql:host=localhost;dbname=sac_db;charset=utf8mb4",
                "root",
                "",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            echo "✅ Conexión a BD establecida\n";
        } catch (PDOException $e) {
            die("❌ Error de conexión: " . $e->getMessage() . "\n");
        }
    }
    
    public function procesarTodosLosXML() {
        echo "=== INICIANDO IMPORTACIÓN COMPLETA CFDI ===\n";
        
        // Limpiar tabla primero
        $this->limpiarTabla();
        
        // Buscar todos los XMLs
        $archivos = $this->buscarTodosLosXML();
        
        echo "Total de archivos encontrados: " . count($archivos) . "\n";
        
        $procesados = 0;
        $errores = 0;
        
        foreach ($archivos as $archivo) {
            try {
                if ($this->procesarXML($archivo)) {
                    $procesados++;
                } else {
                    $errores++;
                }
                
                if ($procesados % 100 == 0) {
                    echo "Procesados: $procesados / Errores: $errores\n";
                }
                
            } catch (Exception $e) {
                $errores++;
                echo "❌ Error en $archivo: " . $e->getMessage() . "\n";
            }
        }
        
        echo "=== RESUMEN FINAL ===\n";
        echo "Procesados exitosamente: $procesados\n";
        echo "Errores: $errores\n";
        echo "Total archivos: " . count($archivos) . "\n";
        
        // Verificar registros insertados
        $this->verificarInsercion();
    }
    
    private function limpiarTabla() {
        echo "🧹 Limpiando tabla cfdi y relacionadas...\n";
        
        // Desactivar foreign key checks temporalmente
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Limpiar tablas en orden
        $this->pdo->exec("TRUNCATE TABLE cfdi_complementos");
        $this->pdo->exec("TRUNCATE TABLE cfdi_timbre_fiscal");
        $this->pdo->exec("TRUNCATE TABLE cfdi_conceptos");
        $this->pdo->exec("TRUNCATE TABLE cfdi_impuestos");
        $this->pdo->exec("TRUNCATE TABLE cfdi_pagos");
        $this->pdo->exec("TRUNCATE TABLE cfdi_pago_documentos_relacionados");
        $this->pdo->exec("TRUNCATE TABLE cfdi_pago_impuestos_dr");
        $this->pdo->exec("TRUNCATE TABLE cfdi_pago_totales");
        $this->pdo->exec("TRUNCATE TABLE cfdi");
        
        // Reactivar foreign key checks
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        echo "✅ Tablas limpiadas\n";
    }
    
    private function buscarTodosLosXML() {
        $ruta = "C:\\xampp\\htdocs\\SAC\\storage\\sat_downloads\\*\\*\\*\\*\\*.xml";
        return glob($ruta);
    }
    
    private function procesarXML($rutaArchivo) {
        // Leer archivo
        $contenido = file_get_contents($rutaArchivo);
        if (!$contenido) {
            return false;
        }
        
        // Parsear XML
        $xml = simplexml_load_string($contenido);
        if (!$xml) {
            return false;
        }
        
        // Registrar namespaces para XPath
        $xml->registerXPathNamespace('cfdi', $xml->getNamespaces()['cfdi']);
        if (isset($xml->getNamespaces()['tfd'])) {
            $xml->registerXPathNamespace('tfd', $xml->getNamespaces()['tfd']);
        }
        
        // Extraer información del archivo
        $info = $this->extraerInfoRuta($rutaArchivo);
        
        // Determinar versión
        $version = (string)$xml['Version'];
        
        // Extraer datos según la versión
        $datos = $this->extraerDatosCFDI($xml, $version, $info, $rutaArchivo);
        
        // Insertar en BD
        return $this->insertarCFDI($datos);
    }
    
    private function extraerInfoRuta($rutaArchivo) {
        // Estructura: RFC/EMITIDAS|RECIBIDAS/AÑO/MES/archivo.xml
        $partes = explode('\\', $rutaArchivo);
        
        $info = [
            'rfc' => '',
            'tipo' => '',
            'ano' => '',
            'mes' => ''
        ];
        
        // Buscar RFC, tipo, año, mes en la ruta
        foreach ($partes as $i => $parte) {
            if (preg_match('/^[A-Z&]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $parte)) {
                $info['rfc'] = $parte;
                if (isset($partes[$i+1])) {
                    $info['tipo'] = $partes[$i+1]; // EMITIDAS o RECIBIDAS
                    if (isset($partes[$i+2])) {
                        $info['ano'] = $partes[$i+2];
                        if (isset($partes[$i+3])) {
                            $info['mes'] = $partes[$i+3];
                        }
                    }
                }
                break;
            }
        }
        
        return $info;
    }
    
    private function extraerDatosCFDI($xml, $version, $info, $rutaArchivo) {
        $datos = [];
        
        // Datos básicos del CFDI
        $datos['version'] = $version;
        $datos['serie'] = (string)$xml['Serie'] ?: null;
        $datos['folio'] = (string)$xml['Folio'] ?: null;
        $datos['fecha'] = (string)$xml['Fecha'];
        $datos['sello'] = (string)$xml['Sello'] ?: null;
        $datos['no_certificado'] = (string)$xml['NoCertificado'] ?: null;
        $datos['certificado'] = (string)$xml['Certificado'] ?: null;
        $datos['subtotal'] = (float)$xml['SubTotal'];
        $datos['descuento'] = isset($xml['Descuento']) ? (float)$xml['Descuento'] : null;
        $datos['moneda'] = (string)$xml['Moneda'];
        $datos['tipo_cambio'] = isset($xml['TipoCambio']) ? (float)$xml['TipoCambio'] : null;
        $datos['total'] = (float)$xml['Total'];
        $datos['tipo_de_comprobante'] = (string)$xml['TipoDeComprobante'];
        $datos['metodo_pago'] = (string)$xml['MetodoPago'] ?: null;
        $datos['forma_pago'] = (string)$xml['FormaPago'] ?: null;
        $datos['condiciones_de_pago'] = (string)$xml['CondicionesDePago'] ?: null;
        $datos['lugar_expedicion'] = (string)$xml['LugarExpedicion'];
        
        // CFDI 4.0 tiene Exportacion
        if ($version === '4.0') {
            $datos['exportacion'] = (string)$xml['Exportacion'] ?: null;
        } else {
            $datos['exportacion'] = null;
        }
        
        // Emisor
        $emisor = $xml->xpath('//cfdi:Emisor')[0];
        if ($emisor) {
            $datos['rfc_emisor'] = (string)$emisor['Rfc'];
            $datos['nombre_emisor'] = (string)$emisor['Nombre'] ?: null;
            
            // Régimen fiscal emisor
            if ($version === '4.0') {
                $datos['regimen_fiscal_emisor'] = (string)$emisor['RegimenFiscal'] ?: null;
            } else {
                // En CFDI 3.3 el régimen está en RegimenFiscal dentro de Emisor
                $regimenes = $emisor->xpath('.//cfdi:RegimenFiscal');
                if (!empty($regimenes)) {
                    $datos['regimen_fiscal_emisor'] = (string)$regimenes[0]['Regimen'];
                } else {
                    $datos['regimen_fiscal_emisor'] = null;
                }
            }
        }
        
        // Receptor
        $receptor = $xml->xpath('//cfdi:Receptor')[0];
        if ($receptor) {
            $datos['rfc_receptor'] = (string)$receptor['Rfc'];
            $datos['nombre_receptor'] = (string)$receptor['Nombre'] ?: null;
            $datos['uso_cfdi'] = (string)$receptor['UsoCFDI'] ?: null;
            
            // Régimen fiscal receptor (solo CFDI 4.0)
            if ($version === '4.0') {
                $datos['regimen_fiscal_receptor'] = (string)$receptor['RegimenFiscalReceptor'] ?: null;
            } else {
                $datos['regimen_fiscal_receptor'] = null;
            }
        }
        
        // Timbre fiscal
        $timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0];
        if ($timbre) {
            $datos['uuid'] = (string)$timbre['UUID'];
            $datos['fecha_timbrado'] = (string)$timbre['FechaTimbrado'];
            $datos['rfc_prov_certif'] = (string)$timbre['RfcProvCertif'];
            $datos['sello_sat'] = (string)$timbre['SelloSAT'] ?: null;
            $datos['no_certificado_sat'] = (string)$timbre['NoCertificadoSAT'] ?: null;
        }
        
        // Información de ruta
        $datos['rfc_archivo'] = $info['rfc'];
        $datos['tipo_archivo'] = $info['tipo'];
        $datos['ano_archivo'] = $info['ano'];
        $datos['mes_archivo'] = $info['mes'];
        $datos['archivo_xml'] = str_replace('C:\\xampp\\htdocs\\SAC\\', '', $rutaArchivo);
        
        // CFDI relacionados
        $relacionados = $xml->xpath('//cfdi:CfdiRelacionados');
        if (!empty($relacionados)) {
            $uuids = [];
            foreach ($relacionados[0]->xpath('.//cfdi:CfdiRelacionado') as $rel) {
                $uuids[] = (string)$rel['UUID'];
            }
            $datos['cfdi_relacionados'] = implode(',', $uuids);
        } else {
            $datos['cfdi_relacionados'] = null;
        }
        
        // Determinar tipo de complemento
        $datos['complemento_tipo'] = $this->determinarTipoComplemento($xml);
        
        $datos['observaciones'] = null; // Campo para uso futuro
        
        return $datos;
    }
    
    private function determinarTipoComplemento($xml) {
        $complementos = $xml->xpath('//cfdi:Complemento')[0] ?? null;
        if (!$complementos) {
            return null;
        }
        
        $namespaces = $complementos->getNamespaces(true);
        
        // Pagos
        if (isset($namespaces['pago10']) || isset($namespaces['pago20'])) {
            return 'PAGO';
        }
        
        // Nómina
        if (isset($namespaces['nomina12']) || isset($namespaces['nomina11'])) {
            return 'NOMINA';
        }
        
        // Comercio exterior
        if (isset($namespaces['cce11']) || isset($namespaces['cce20'])) {
            return 'COMERCIO_EXTERIOR';
        }
        
        return 'OTRO';
    }
    
    private function insertarCFDI($datos) {
        $sql = "INSERT INTO cfdi (
            version, serie, folio, fecha, sello, no_certificado, certificado,
            subtotal, descuento, moneda, tipo_cambio, total, tipo_de_comprobante,
            metodo_pago, forma_pago, condiciones_de_pago, lugar_expedicion,
            exportacion, rfc_emisor, nombre_emisor, regimen_fiscal_emisor,
            rfc_receptor, nombre_receptor, regimen_fiscal_receptor, uso_cfdi,
            uuid, fecha_timbrado, rfc_prov_certif, sello_sat, no_certificado_sat,
            rfc_archivo, tipo_archivo, ano_archivo, mes_archivo, archivo_xml,
            cfdi_relacionados, complemento_tipo, observaciones
        ) VALUES (
            :version, :serie, :folio, :fecha, :sello, :no_certificado, :certificado,
            :subtotal, :descuento, :moneda, :tipo_cambio, :total, :tipo_de_comprobante,
            :metodo_pago, :forma_pago, :condiciones_de_pago, :lugar_expedicion,
            :exportacion, :rfc_emisor, :nombre_emisor, :regimen_fiscal_emisor,
            :rfc_receptor, :nombre_receptor, :regimen_fiscal_receptor, :uso_cfdi,
            :uuid, :fecha_timbrado, :rfc_prov_certif, :sello_sat, :no_certificado_sat,
            :rfc_archivo, :tipo_archivo, :ano_archivo, :mes_archivo, :archivo_xml,
            :cfdi_relacionados, :complemento_tipo, :observaciones
        )";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($datos);
        } catch (PDOException $e) {
            echo "❌ Error al insertar CFDI: " . $e->getMessage() . "\n";
            echo "Datos que causaron error: " . print_r($datos, true) . "\n";
            return false;
        }
    }
    
    private function verificarInsercion() {
        echo "\n=== VERIFICACIÓN DE INSERCIÓN ===\n";
        
        // Contar total
        $total = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi")->fetch()['total'];
        echo "Total registros CFDI: $total\n";
        
        // Contar por versión
        $versiones = $this->pdo->query("
            SELECT version, COUNT(*) as cantidad 
            FROM cfdi 
            GROUP BY version 
            ORDER BY version
        ")->fetchAll();
        
        echo "\nPor versión:\n";
        foreach ($versiones as $v) {
            echo "  {$v['version']}: {$v['cantidad']} registros\n";
        }
        
        // Verificar campos críticos llenos
        echo "\nVerificación de campos críticos:\n";
        
        $campos = [
            'uuid' => 'UUID (timbre)',
            'fecha_timbrado' => 'Fecha timbrado',
            'rfc_emisor' => 'RFC Emisor',
            'nombre_emisor' => 'Nombre Emisor',
            'rfc_receptor' => 'RFC Receptor',
            'nombre_receptor' => 'Nombre Receptor',
            'uso_cfdi' => 'Uso CFDI',
            'regimen_fiscal_emisor' => 'Régimen Fiscal Emisor',
            'archivo_xml' => 'Ruta archivo XML'
        ];
        
        foreach ($campos as $campo => $descripcion) {
            $llenos = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE $campo IS NOT NULL AND $campo != ''")->fetch()['total'];
            $porcentaje = $total > 0 ? round(($llenos / $total) * 100, 2) : 0;
            
            if ($porcentaje >= 90) {
                echo "  ✅ $descripcion: $llenos/$total ($porcentaje%)\n";
            } else {
                echo "  ⚠️  $descripcion: $llenos/$total ($porcentaje%)\n";
            }
        }
        
        // Mostrar algunos ejemplos
        echo "\nEjemplos de registros insertados:\n";
        $ejemplos = $this->pdo->query("
            SELECT version, rfc_emisor, rfc_receptor, tipo_de_comprobante, total, uuid 
            FROM cfdi 
            LIMIT 3
        ")->fetchAll();
        
        foreach ($ejemplos as $ej) {
            echo "  {$ej['version']} - {$ej['rfc_emisor']} -> {$ej['rfc_receptor']} - Tipo: {$ej['tipo_de_comprobante']} - Total: {$ej['total']} - UUID: {$ej['uuid']}\n";
        }
    }
}

// Ejecutar importación
echo "Iniciando importación completa de tabla CFDI...\n";
$importador = new ImportadorCFDICompleto();
$importador->procesarTodosLosXML();
