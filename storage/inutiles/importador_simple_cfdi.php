<?php
/**
 * IMPORTADOR SIMPLE Y FUNCIONAL CFDI
 * ==================================
 * Versión limpia desde 0 - Sin errores
 * Basado en análisis de mapeo XML-BD completado
 */

// Cargar configuración de base de datos
require_once 'src/config/database.php';

// Configuración
$LIMITE_ARCHIVOS = 50; // Procesar de a 50 archivos
$DIRECTORIO_XML = 'storage/sat_downloads';
$DEBUG = true;

// Estadísticas
$stats = [
    'procesados' => 0,
    'exitosos' => 0,
    'errores' => 0,
    'duplicados' => 0
];

// Función de log
function log_mensaje($mensaje) {
    global $DEBUG;
    if ($DEBUG) {
        echo "[" . date('H:i:s') . "] " . $mensaje . "\n";
    }
}

// Función principal
function importar_cfdi() {
    global $DIRECTORIO_XML, $LIMITE_ARCHIVOS, $stats;
    
    log_mensaje("🚀 INICIANDO IMPORTACIÓN CFDI");
    
    // Obtener conexión a base de datos
    try {
        $pdo = getDatabase();
        log_mensaje("✅ Conexión a base de datos OK");
    } catch (Exception $e) {
        log_mensaje("❌ Error conexión BD: " . $e->getMessage());
        return false;
    }
    
    // Buscar archivos XML
    $archivos = buscar_archivos_xml();
    log_mensaje("📁 Encontrados: " . count($archivos) . " archivos XML");
    
    if (empty($archivos)) {
        log_mensaje("❌ No hay archivos para procesar");
        return false;
    }
    
    // Procesar archivos
    $archivos_limitados = array_slice($archivos, 0, $LIMITE_ARCHIVOS);
    log_mensaje("📦 Procesando primeros " . count($archivos_limitados) . " archivos");
    
    foreach ($archivos_limitados as $archivo) {
        procesar_archivo_xml($archivo, $pdo);
    }
    
    // Mostrar resultados
    mostrar_estadisticas();
    
    return true;
}

// Buscar archivos XML
function buscar_archivos_xml() {
    global $DIRECTORIO_XML;
    
    $archivos = [];
    
    // Buscar en estructura RFC/EMITIDAS|RECIBIDAS/año/mes/
    $patron1 = $DIRECTORIO_XML . '/*/*/*/*/*.xml';
    $archivos1 = glob($patron1);
    
    // Buscar en directorio raíz
    $patron2 = $DIRECTORIO_XML . '/*.xml';
    $archivos2 = glob($patron2);
    
    return array_merge($archivos1, $archivos2);
}

// Procesar un archivo XML
function procesar_archivo_xml($archivo, $pdo) {
    global $stats;
    
    $stats['procesados']++;
    
    try {
        // Leer XML
        $contenido = file_get_contents($archivo);
        if (!$contenido) {
            throw new Exception("No se pudo leer archivo");
        }
        
        // Parsear XML
        $xml = simplexml_load_string($contenido);
        if (!$xml) {
            throw new Exception("XML inválido");
        }
        
        // Extraer UUID
        $uuid = extraer_uuid($xml);
        if (!$uuid) {
            throw new Exception("UUID no encontrado");
        }
        
        // Verificar si ya existe
        if (existe_cfdi($pdo, $uuid)) {
            $stats['duplicados']++;
            log_mensaje("⚠️ Duplicado: " . $uuid);
            return;
        }
        
        // Extraer datos del CFDI
        $datos = extraer_datos_cfdi($xml, $archivo);
        
        // Insertar en base de datos
        insertar_cfdi($pdo, $datos);
        
        $stats['exitosos']++;
        log_mensaje("✅ Procesado: " . $uuid);
        
    } catch (Exception $e) {
        $stats['errores']++;
        log_mensaje("❌ Error en " . basename($archivo) . ": " . $e->getMessage());
    }
}

// Extraer UUID del XML
function extraer_uuid($xml) {
    // Buscar en TimbreFiscalDigital
    if (isset($xml->Complemento->TimbreFiscalDigital)) {
        return (string)$xml->Complemento->TimbreFiscalDigital['UUID'];
    }
    
    // Buscar en complementos con namespaces
    $namespaces = $xml->getNamespaces(true);
    foreach ($namespaces as $prefix => $uri) {
        if (strpos($uri, 'TimbreFiscalDigital') !== false) {
            $xml->registerXPathNamespace('tfd', $uri);
            $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');
            if (!empty($timbres)) {
                return (string)$timbres[0]['UUID'];
            }
        }
    }
    
    return null;
}

// Verificar si el CFDI ya existe
function existe_cfdi($pdo, $uuid) {
    $stmt = $pdo->prepare("SELECT id FROM cfdi WHERE uuid = ? LIMIT 1");
    $stmt->execute([$uuid]);
    return $stmt->fetchColumn() !== false;
}

// Extraer datos principales del CFDI
function extraer_datos_cfdi($xml, $archivo) {
    $datos = [];
    
    // UUID
    $datos['uuid'] = extraer_uuid($xml);
    
    // Datos básicos del comprobante
    $datos['tipo'] = (string)$xml['TipoDeComprobante'] ?: null;
    $datos['serie'] = (string)$xml['Serie'] ?: null;
    $datos['folio'] = (string)$xml['Folio'] ?: null;
    $datos['fecha'] = convertir_fecha((string)$xml['Fecha']);
    $datos['version'] = (string)$xml['Version'] ?: '3.3';
    
    // Emisor
    $emisor = $xml->Emisor;
    $datos['rfc_emisor'] = (string)$emisor['Rfc'] ?: null;
    $datos['nombre_emisor'] = (string)$emisor['Nombre'] ?: null;
    $datos['regimen_fiscal_emisor'] = (string)$emisor['RegimenFiscal'] ?: null;
    
    // Receptor
    $receptor = $xml->Receptor;
    $datos['rfc_receptor'] = (string)$receptor['Rfc'] ?: null;
    $datos['nombre_receptor'] = (string)$receptor['Nombre'] ?: null;
    $datos['uso_cfdi'] = (string)$receptor['UsoCFDI'] ?: null;
    
    // Montos
    $datos['lugar_expedicion'] = (string)$xml['LugarExpedicion'] ?: null;
    $datos['moneda'] = (string)$xml['Moneda'] ?: 'MXN';
    $datos['tipo_cambio'] = !empty($xml['TipoCambio']) ? (float)$xml['TipoCambio'] : null;
    $datos['subtotal'] = !empty($xml['SubTotal']) ? (float)$xml['SubTotal'] : 0;
    $datos['descuento'] = !empty($xml['Descuento']) ? (float)$xml['Descuento'] : null;
    $datos['total'] = !empty($xml['Total']) ? (float)$xml['Total'] : 0;
    
    // Métodos de pago
    $datos['metodo_pago'] = (string)$xml['MetodoPago'] ?: null;
    $datos['forma_pago'] = (string)$xml['FormaPago'] ?: null;
    
    // CFDI 4.0 - Campo exportación
    $datos['exportacion'] = (string)$xml['Exportacion'] ?: null;
    
    // Certificado
    $datos['no_certificado'] = (string)$xml['NoCertificado'] ?: null;
    $datos['sello_cfd'] = (string)$xml['Sello'] ?: null;
    
    // Timbre fiscal
    $timbre = extraer_datos_timbre($xml);
    $datos['fecha_timbrado'] = $timbre['fecha_timbrado'] ?? null;
    $datos['sello_sat'] = $timbre['sello_sat'] ?? null;
    $datos['no_certificado_sat'] = $timbre['no_certificado_sat'] ?? null;
    $datos['rfc_prov_certif'] = $timbre['rfc_prov_certif'] ?? null;
    
    // Archivo
    $datos['archivo_xml'] = $archivo;
    
    // Dirección de flujo
    if (strpos($archivo, 'EMITIDAS') !== false) {
        $datos['direccion_flujo'] = 'EMITIDA';
        $datos['rfc_consultado'] = $datos['rfc_emisor'];
    } elseif (strpos($archivo, 'RECIBIDAS') !== false) {
        $datos['direccion_flujo'] = 'RECIBIDA';
        $datos['rfc_consultado'] = $datos['rfc_receptor'];
    } else {
        $datos['direccion_flujo'] = 'DESCONOCIDA';
        $datos['rfc_consultado'] = $datos['rfc_emisor'];
    }
    
    return $datos;
}

// Extraer datos del timbre fiscal
function extraer_datos_timbre($xml) {
    $timbre = [];
    
    // Buscar TimbreFiscalDigital
    if (isset($xml->Complemento->TimbreFiscalDigital)) {
        $tfd = $xml->Complemento->TimbreFiscalDigital;
        
        $timbre['fecha_timbrado'] = convertir_fecha((string)$tfd['FechaTimbrado']);
        $timbre['sello_sat'] = (string)$tfd['SelloSAT'] ?: null;
        $timbre['no_certificado_sat'] = (string)$tfd['NoCertificadoSAT'] ?: null;
        $timbre['rfc_prov_certif'] = (string)$tfd['RfcProvCertif'] ?: null;
    }
    
    return $timbre;
}

// Convertir fecha a formato MySQL
function convertir_fecha($fecha_str) {
    if (empty($fecha_str)) return null;
    
    try {
        $fecha = new DateTime($fecha_str);
        return $fecha->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

// Insertar CFDI en base de datos
function insertar_cfdi($pdo, $datos) {
    $sql = "INSERT INTO cfdi (
        uuid, tipo, serie, folio, fecha, fecha_timbrado,
        rfc_emisor, nombre_emisor, regimen_fiscal_emisor,
        rfc_receptor, nombre_receptor, uso_cfdi,
        lugar_expedicion, moneda, tipo_cambio, subtotal, descuento, total,
        metodo_pago, forma_pago, exportacion,
        archivo_xml, direccion_flujo, rfc_consultado, version,
        sello_cfd, sello_sat, no_certificado_sat, rfc_prov_certif, no_certificado
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?
    )";
    
    $valores = [
        $datos['uuid'], $datos['tipo'], $datos['serie'], $datos['folio'], 
        $datos['fecha'], $datos['fecha_timbrado'],
        $datos['rfc_emisor'], $datos['nombre_emisor'], $datos['regimen_fiscal_emisor'],
        $datos['rfc_receptor'], $datos['nombre_receptor'], $datos['uso_cfdi'],
        $datos['lugar_expedicion'], $datos['moneda'], $datos['tipo_cambio'], 
        $datos['subtotal'], $datos['descuento'], $datos['total'],
        $datos['metodo_pago'], $datos['forma_pago'], $datos['exportacion'],
        $datos['archivo_xml'], $datos['direccion_flujo'], $datos['rfc_consultado'], $datos['version'],
        $datos['sello_cfd'], $datos['sello_sat'], $datos['no_certificado_sat'], 
        $datos['rfc_prov_certif'], $datos['no_certificado']
    ];
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($valores);
}

// Mostrar estadísticas
function mostrar_estadisticas() {
    global $stats;
    
    echo "\n";
    echo "=====================================\n";
    echo "     ESTADÍSTICAS DE IMPORTACIÓN     \n";
    echo "=====================================\n";
    echo "Procesados: " . $stats['procesados'] . "\n";
    echo "Exitosos: " . $stats['exitosos'] . "\n";
    echo "Errores: " . $stats['errores'] . "\n";
    echo "Duplicados: " . $stats['duplicados'] . "\n";
    
    if ($stats['procesados'] > 0) {
        $tasa_exito = round(($stats['exitosos'] / $stats['procesados']) * 100, 2);
        echo "Tasa de éxito: " . $tasa_exito . "%\n";
    }
    
    echo "=====================================\n";
}

// EJECUCIÓN
if (php_sapi_name() === 'cli' || isset($_GET['ejecutar'])) {
    try {
        $resultado = importar_cfdi();
        
        if ($resultado) {
            echo "\n✅ IMPORTACIÓN COMPLETADA\n";
        } else {
            echo "\n❌ Error en la importación\n";
        }
        
    } catch (Exception $e) {
        echo "\n💥 ERROR: " . $e->getMessage() . "\n";
    }
} else {
    // Interfaz web simple
    echo "<!DOCTYPE html>";
    echo "<html><head><meta charset='UTF-8'><title>Importador CFDI Simple</title></head>";
    echo "<body style='font-family: Arial; margin: 40px;'>";
    echo "<h1>📥 Importador CFDI Simple</h1>";
    echo "<div style='background: #e8f5e9; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>✅ Características:</h3>";
    echo "<ul>";
    echo "<li>Procesa " . $LIMITE_ARCHIVOS . " archivos por ejecución</li>";
    echo "<li>Evita duplicados automáticamente</li>";
    echo "<li>Soporte CFDI 3.3 y 4.0</li>";
    echo "<li>Extrae datos principales del comprobante</li>";
    echo "</ul>";
    echo "</div>";
    echo "<p><a href='?ejecutar=1' style='background: #4caf50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>🚀 EJECUTAR IMPORTACIÓN</a></p>";
    echo "</body></html>";
}
?>
