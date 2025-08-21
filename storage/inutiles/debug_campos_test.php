<?php
/*// Usar archivos disponibles
$archivosTest = [
    'storage/sat_downloads/BLM1706026AA/RECIBIDAS/2025/7/2025_07_03_3D9DA9D3-3D50-4328-A0AB-71D5E78A4D1F.xml',
    'storage/sat_downloads/BLM1706026AA/RECIBIDAS/2025/7/2025_07_03_236AF7B6-1BF5-4EFC-BA7B-7362287582FA.xml',
    'storage/sat_downloads/BLM1706026AA/RECIBIDAS/2025/7/2025_07_02_E8ED9DF6-64A5-4516-900A-7362287588F8.xml'
];

$archivoTest = null;
foreach ($archivosTest as $archivo) {
    if (file_exists($archivo)) {
        $archivoTest = $archivo;
        break;
    }
}specífico para los nuevos campos
 */

require_once 'importador_inteligente_cfdi.php';

echo "=== DEBUG CAMPOS ESPECÍFICO ===\n\n";

// Usar el archivo que funcionó en el test anterior
$archivosTest = [
    'cfdi_ejemplos_2025/2023_05_29_20259229-5F85-48B9-A3AF-7BF5764DB6DC.xml',
    'cfdi_ejemplos_2025/2025_01_08_0D157DDC-7E07-4FD8-B37A-BAE0105F3F1C.xml'
];

$archivoTest = null;
foreach ($archivosTest as $archivo) {
    if (file_exists($archivo)) {
        $archivoTest = $archivo;
        break;
    }
}

if (!file_exists($archivoTest)) {
    echo "❌ Archivo de prueba no encontrado: $archivoTest\n";
    exit;
}

$contenidoXML = file_get_contents($archivoTest);
echo "📁 Archivo: $archivoTest\n";
echo "📏 Tamaño XML: " . strlen($contenidoXML) . " bytes\n\n";

// Crear instancia del importador
$importador = new ImportadorInteligenteCFDI();

// Hacer métodos públicos usando reflexión
$reflection = new ReflectionClass($importador);

// Test método extraerComplemento
echo "🔍 TESTANDO extraerComplemento():\n";
$metodoComplemento = $reflection->getMethod('extraerComplemento');
$metodoComplemento->setAccessible(true);
$complemento = $metodoComplemento->invoke($importador, $contenidoXML);
echo "   Resultado: " . print_r($complemento, true) . "\n";

// Test método extraerCfdiRelacionados  
echo "🔍 TESTANDO extraerCfdiRelacionados():\n";
$metodoCfdiRel = $reflection->getMethod('extraerCfdiRelacionados');
$metodoCfdiRel->setAccessible(true);
$cfdiRel = $metodoCfdiRel->invoke($importador, $contenidoXML);
echo "   Resultado: " . print_r($cfdiRel, true) . "\n";

// Test método detectarDireccionFlujo
echo "🔍 TESTANDO detectarDireccionFlujo():\n";
$metodoDireccion = $reflection->getMethod('detectarDireccionFlujo');
$metodoDireccion->setAccessible(true);
$direccion = $metodoDireccion->invoke($importador, $archivoTest);
echo "   Resultado: '$direccion'\n\n";

// Test método procesarArchivo completo con debug
echo "🚀 PROCESANDO ARCHIVO COMPLETO:\n";

// Capturar conexión BD actual
$conexion = $reflection->getProperty('pdo');
$conexion->setAccessible(true);
$pdo = $conexion->getValue($importador);

if (!$pdo) {
    echo "❌ Sin conexión a BD, creando...\n";
    $config = [
        'host' => 'localhost',
        'dbname' => 'sac_db', 
        'user' => 'root',
        'pass' => ''
    ];
    
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $conexion->setValue($importador, $pdo);
        echo "✅ Conexión BD establecida\n";
    } catch (PDOException $e) {
        echo "❌ Error conexión BD: " . $e->getMessage() . "\n";
        exit;
    }
}

// Borrar registro de prueba si existe
$uuid = '20259229-5F85-48B9-A3AF-7BF5764DB6DC';
$stmt = $pdo->prepare("DELETE FROM cfdi WHERE uuid = ?");
$stmt->execute([$uuid]);
echo "🧹 Registro de prueba eliminado\n";

// Procesar el archivo usando método interno
try {
    // Usar reflexión para acceder al método procesarArchivo 
    $metodo = $reflection->getMethod('procesarArchivo');
    $metodo->setAccessible(true);
    $resultado = $metodo->invoke($importador, $archivoTest);
    echo "✅ Procesamiento exitoso: " . print_r($resultado, true) . "\n";
} catch (Exception $e) {
    echo "❌ Error procesamiento: " . $e->getMessage() . "\n";
}

// Verificar qué se insertó en BD
echo "\n📊 VERIFICANDO DATOS EN BD:\n";
$stmt = $pdo->prepare("SELECT uuid, complemento_tipo, complemento_json, direccion_flujo, sello_sat, no_certificado_sat, rfc_prov_certif, cfdi_relacionados FROM cfdi WHERE uuid = ?");
$stmt->execute([$uuid]);
$registro = $stmt->fetch();

if ($registro) {
    echo "✅ Registro encontrado:\n";
    foreach ($registro as $campo => $valor) {
        $estado = is_null($valor) ? "❌ NULL" : (empty($valor) ? "⚠️  VACÍO" : "✅ CON DATOS");
        $valorMostrar = is_null($valor) ? 'NULL' : (strlen($valor) > 50 ? substr($valor, 0, 50) . '...' : $valor);
        echo "   $campo: $estado ($valorMostrar)\n";
    }
} else {
    echo "❌ No se encontró el registro\n";
}

echo "\n=== FIN DEBUG ===\n";
?>
