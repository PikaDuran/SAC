<?php
require_once 'vendor/autoload.php';

class ImportadorCFDI_Fixed {
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
        echo "=== INICIANDO IMPORTACIÓN CORREGIDA CFDI ===\n";
        
        // Limpiar tabla primero
        $this->limpiarTablas();
        
        // Buscar todos los XMLs
        $archivos = $this->buscarTodosLosXML();
        
        echo "Total de archivos encontrados: " . count($archivos) . "\n";
        
        if (empty($archivos)) {
            echo "No se encontraron archivos XML para procesar.\n";
            return;
        }
        
        $procesados = 0;
        $errores = 0;
        
        // Procesar solo los primeros 50 para probar
        $archivos = array_slice($archivos, 0, 50);
        
        foreach ($archivos as $archivo) {
            try {
                if ($this->procesarXML($archivo)) {
                    $procesados++;
                } else {
                    $errores++;
                }
                
                if (($procesados + $errores) % 10 == 0) {
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
        echo "Total archivos procesados: " . count($archivos) . "\n";
        
        // Verificar registros insertados
        $this->verificarInsercion();
    }
    
    private function limpiarTablas() {
        echo "🧹 Limpiando tablas...\n";
        
        try {
            // Desactivar foreign key checks temporalmente
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Limpiar tabla principal
            $this->pdo->exec("TRUNCATE TABLE cfdi");
            
            // Reactivar foreign key checks
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            echo "✅ Tablas limpiadas\n";
        } catch (Exception $e) {
            echo "❌ Error limpiando tablas: " . $e->getMessage() . "\n";
        }
    }
    
    private function buscarTodosLosXML() {
        $ruta = "C:\\xampp\\htdocs\\SAC\\storage\\sat_downloads\\*\\*\\*\\*\\*.xml";
        return glob($ruta);
    }
    
    private function procesarXML($rutaArchivo) {
        try {
            // Leer archivo
            $contenido = file_get_contents($rutaArchivo);
            if (!$contenido) {
                echo "❌ No se pudo leer: $rutaArchivo\n";
                return false;
            }
            
            // Parsear XML
            $xml = simplexml_load_string($contenido);
            if (!$xml) {
                echo "❌ XML inválido: $rutaArchivo\n";
                return false;
            }
            
            // Registrar TODOS los namespaces correctamente
            $namespaces = $xml->getNamespaces(true);
            foreach ($namespaces as $prefix => $uri) {
                if (empty($prefix)) {
                    $xml->registerXPathNamespace('cfdi', $uri);
                } else {
                    $xml->registerXPathNamespace($prefix, $uri);
                }
            }
            
            // Extraer información del archivo
            $info = $this->extraerInfoRuta($rutaArchivo);
            
            // Determinar versión
            $version = (string)$xml['Version'];
            
            // Extraer datos según la versión
            $datos = $this->extraerDatosCFDI($xml, $version, $info, $rutaArchivo);
            
            // Insertar en BD
            return $this->insertarCFDI($datos);
            
        } catch (Exception $e) {
            echo "❌ Error procesando $rutaArchivo: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function extraerInfoRuta($rutaArchivo) {
        // Estructura: RFC/EMITIDAS|RECIBIDAS/AÑO/MES/archivo.xml
        $partes = explode(DIRECTORY_SEPARATOR, str_replace('/', DIRECTORY_SEPARATOR, $rutaArchivo));
        
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
        $datos['serie'] = $this->getAttributeValue($xml, 'Serie');
        $datos['folio'] = $this->getAttributeValue($xml, 'Folio');
        $datos['fecha'] = (string)$xml['Fecha'];
        $datos['sello_cfd'] = $this->getAttributeValue($xml, 'Sello');
        $datos['no_certificado'] = $this->getAttributeValue($xml, 'NoCertificado');
        $datos['certificado'] = $this->getAttributeValue($xml, 'Certificado');
        $datos['subtotal'] = (float)$xml['SubTotal'];
        $datos['descuento'] = $this->getAttributeFloatValue($xml, 'Descuento');
        $datos['moneda'] = (string)$xml['Moneda'];
        $datos['tipo_cambio'] = $this->getAttributeFloatValue($xml, 'TipoCambio');
        $datos['total'] = (float)$xml['Total'];
        $datos['tipo'] = (string)$xml['TipoDeComprobante'];
        $datos['metodo_pago'] = $this->getAttributeValue($xml, 'MetodoPago');
        $datos['forma_pago'] = $this->getAttributeValue($xml, 'FormaPago');
        $datos['condiciones_de_pago'] = $this->getAttributeValue($xml, 'CondicionesDePago');
        $datos['lugar_expedicion'] = (string)$xml['LugarExpedicion'];
        
        // CFDI 4.0 tiene Exportacion
        if ($version === '4.0') {
            $datos['exportacion'] = $this->getAttributeValue($xml, 'Exportacion');
        } else {
            $datos['exportacion'] = null;
        }
        
        // Emisor
        $this->extraerDatosEmisor($xml, $version, $datos);
        
        // Receptor
        $this->extraerDatosReceptor($xml, $version, $datos);
        
        // Timbre fiscal
        $this->extraerTimbreFiscal($xml, $datos);
        
        // Información de ruta
        $datos['rfc_consultado'] = $info['rfc'];
        $datos['direccion_flujo'] = $info['tipo'];
        $datos['archivo_xml'] = str_replace('C:\\xampp\\htdocs\\SAC\\', '', $rutaArchivo);
        
        // CFDI relacionados
        $this->extraerCFDIRelacionados($xml, $datos);
        
        // Determinar tipo de complemento
        $datos['complemento_tipo'] = $this->determinarTipoComplemento($xml);
        
        // Estatus SAT - se debe consultar al SAT (por ahora null)
        $datos['estatus_sat'] = null; // TODO: Implementar consulta al SAT
        
        // Complemento JSON - almacenar complemento como JSON (por ahora null)
        $datos['complemento_json'] = null; // TODO: Implementar serialización de complemento
        
        $datos['observaciones'] = null;
        
        return $datos;
    }
    
    private function getAttributeValue($xml, $attribute) {
        return isset($xml[$attribute]) ? (string)$xml[$attribute] : null;
    }
    
    private function getAttributeFloatValue($xml, $attribute) {
        return isset($xml[$attribute]) ? (float)$xml[$attribute] : null;
    }
    
    private function extraerDatosEmisor($xml, $version, &$datos) {
        $emisor = $xml->xpath('//cfdi:Emisor')[0] ?? null;
        if ($emisor) {
            $datos['rfc_emisor'] = (string)$emisor['Rfc'];
            $datos['nombre_emisor'] = $this->getAttributeValue($emisor, 'Nombre');
            
            // Régimen fiscal emisor
            if ($version === '4.0') {
                $datos['regimen_fiscal_emisor'] = $this->getAttributeValue($emisor, 'RegimenFiscal');
            } else {
                // En CFDI 3.3 el régimen está en RegimenFiscal dentro de Emisor
                $regimenes = $emisor->xpath('.//cfdi:RegimenFiscal');
                if (!empty($regimenes)) {
                    $datos['regimen_fiscal_emisor'] = $this->getAttributeValue($regimenes[0], 'Regimen');
                } else {
                    $datos['regimen_fiscal_emisor'] = null;
                }
            }
        } else {
            $datos['rfc_emisor'] = null;
            $datos['nombre_emisor'] = null;
            $datos['regimen_fiscal_emisor'] = null;
        }
    }
    
    private function extraerDatosReceptor($xml, $version, &$datos) {
        $receptor = $xml->xpath('//cfdi:Receptor')[0] ?? null;
        if ($receptor) {
            $datos['rfc_receptor'] = (string)$receptor['Rfc'];
            $datos['nombre_receptor'] = $this->getAttributeValue($receptor, 'Nombre');
            $datos['uso_cfdi'] = $this->getAttributeValue($receptor, 'UsoCFDI');
            
            // Régimen fiscal receptor (solo CFDI 4.0)
            if ($version === '4.0') {
                $datos['regimen_fiscal_receptor'] = $this->getAttributeValue($receptor, 'RegimenFiscalReceptor');
            } else {
                $datos['regimen_fiscal_receptor'] = null;
            }
        } else {
            $datos['rfc_receptor'] = null;
            $datos['nombre_receptor'] = null;
            $datos['uso_cfdi'] = null;
            $datos['regimen_fiscal_receptor'] = null;
        }
    }
    
    private function extraerTimbreFiscal($xml, &$datos) {
        $timbre = $xml->xpath('//tfd:TimbreFiscalDigital')[0] ?? null;
        if ($timbre) {
            $datos['uuid'] = (string)$timbre['UUID'];
            $datos['fecha_timbrado'] = (string)$timbre['FechaTimbrado'];
            $datos['rfc_prov_certif'] = (string)$timbre['RfcProvCertif'];
            $datos['sello_sat'] = $this->getAttributeValue($timbre, 'SelloSAT');
            $datos['no_certificado_sat'] = $this->getAttributeValue($timbre, 'NoCertificadoSAT');
        } else {
            $datos['uuid'] = null;
            $datos['fecha_timbrado'] = null;
            $datos['rfc_prov_certif'] = null;
            $datos['sello_sat'] = null;
            $datos['no_certificado_sat'] = null;
        }
    }
    
    private function extraerCFDIRelacionados($xml, &$datos) {
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
            uuid, tipo, serie, folio, fecha, fecha_timbrado, 
            rfc_emisor, nombre_emisor, regimen_fiscal_emisor,
            rfc_receptor, nombre_receptor, regimen_fiscal_receptor, uso_cfdi,
            lugar_expedicion, moneda, tipo_cambio, subtotal, descuento, total,
            metodo_pago, forma_pago, exportacion, observaciones, archivo_xml,
            complemento_tipo, rfc_consultado, direccion_flujo, version,
            sello_cfd, sello_sat, no_certificado_sat, rfc_prov_certif,
            cfdi_relacionados, no_certificado, certificado, condiciones_de_pago
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?
        )";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            
            $valores = [
                $datos['uuid'], $datos['tipo'], $datos['serie'], $datos['folio'], 
                $datos['fecha'], $datos['fecha_timbrado'],
                $datos['rfc_emisor'], $datos['nombre_emisor'], $datos['regimen_fiscal_emisor'],
                $datos['rfc_receptor'], $datos['nombre_receptor'], $datos['regimen_fiscal_receptor'], 
                $datos['uso_cfdi'],
                $datos['lugar_expedicion'], $datos['moneda'], $datos['tipo_cambio'], 
                $datos['subtotal'], $datos['descuento'], $datos['total'],
                $datos['metodo_pago'], $datos['forma_pago'], $datos['exportacion'], 
                $datos['observaciones'], $datos['archivo_xml'],
                $datos['complemento_tipo'], $datos['rfc_consultado'], $datos['direccion_flujo'], 
                $datos['version'],
                $datos['sello_cfd'], $datos['sello_sat'], $datos['no_certificado_sat'], 
                $datos['rfc_prov_certif'],
                $datos['cfdi_relacionados'], $datos['no_certificado'], $datos['certificado'], 
                $datos['condiciones_de_pago']
            ];
            
            return $stmt->execute($valores);
            
        } catch (PDOException $e) {
            echo "❌ Error SQL: " . $e->getMessage() . "\n";
            echo "Archivo: " . $datos['archivo_xml'] . "\n";
            return false;
        }
    }
    
    private function verificarInsercion() {
        echo "\n=== VERIFICACIÓN DE INSERCIÓN ===\n";
        
        try {
            // Contar total
            $total = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi")->fetch()['total'];
            echo "Total registros CFDI: $total\n";
            
            if ($total == 0) {
                echo "❌ NO SE INSERTARON REGISTROS\n";
                return;
            }
            
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
                'rfc_emisor' => 'RFC Emisor',
                'rfc_receptor' => 'RFC Receptor',
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
                SELECT version, rfc_emisor, rfc_receptor, tipo_de_comprobante, total, LEFT(uuid, 20) as uuid_short
                FROM cfdi 
                LIMIT 3
            ")->fetchAll();
            
            foreach ($ejemplos as $ej) {
                echo "  {$ej['version']} - {$ej['rfc_emisor']} -> {$ej['rfc_receptor']} - Tipo: {$ej['tipo_de_comprobante']} - Total: {$ej['total']} - UUID: {$ej['uuid_short']}...\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Error en verificación: " . $e->getMessage() . "\n";
        }
    }
}

// Ejecutar importación
echo "Iniciando importación corregida de tabla CFDI...\n";
$importador = new ImportadorCFDI_Fixed();
$importador->procesarTodosLosXML();
