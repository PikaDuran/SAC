<?php
require_once 'vendor/autoload.php';

// Configuración de base de datos
$host = 'localhost';
$dbname = 'sac_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "===========================================\n";
    echo "  ANÁLISIS DE CFDI DUPLICADO\n";
    echo "===========================================\n\n";

    // UUID del primer CFDI rechazado por duplicado
    $uuid_rechazado = 'ECE52AD2-DB29-40AB-A924-BDE2474CC069';

    echo "🔍 Analizando UUID: $uuid_rechazado\n\n";

    // 1. Verificar si existe en la BD (UUID está en tabla cfdi_timbre_fiscal_digital)
    $stmt = $pdo->prepare("
        SELECT c.*, t.uuid, t.fecha_timbrado, e.rfc as emisor_rfc, r.rfc as receptor_rfc
        FROM cfdi c 
        INNER JOIN cfdi_timbre_fiscal_digital t ON c.id = t.cfdi_id 
        LEFT JOIN emisor e ON c.id = e.cfdi_id
        LEFT JOIN receptor r ON c.id = r.cfdi_id
        WHERE t.uuid = ?
    ");
    $stmt->execute([$uuid_rechazado]);
    $cfdi_bd = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cfdi_bd) {
        echo "✅ CFDI encontrado en base de datos:\n";
        echo "   🆔 UUID: {$cfdi_bd['uuid']}\n";
        echo "   📅 Fecha: {$cfdi_bd['fecha']}\n";
        echo "   ⏰ Fecha Timbrado: {$cfdi_bd['fecha_timbrado']}\n";
        echo "   💰 Total: {$cfdi_bd['total']}\n";
        echo "   🏢 Emisor: {$cfdi_bd['emisor_rfc']}\n";
        echo "   👤 Receptor: {$cfdi_bd['receptor_rfc']}\n";
        echo "   � Tipo: {$cfdi_bd['tipo_comprobante']}\n";
        echo "   🔄 Última actualización: {$cfdi_bd['fecha_procesamiento']}\n\n";
    } else {
        echo "❌ CFDI NO encontrado en base de datos\n\n";
        exit(1);
    }

    // 2. Buscar el archivo XML rechazado
    $archivo_rechazado = "storage/sat_downloads/BLM1706026AA/EMITIDAS/2020/10/2020_10_12_ECE52AD2-DB29-40AB-A924-BDE2474CC069.xml";

    if (!file_exists($archivo_rechazado)) {
        echo "❌ Archivo rechazado no encontrado: $archivo_rechazado\n";
        exit(1);
    }

    echo "📄 Archivo rechazado encontrado: $archivo_rechazado\n";

    // 3. Parsear el XML rechazado
    $xml_content = file_get_contents($archivo_rechazado);
    $xml = new DOMDocument();
    libxml_use_internal_errors(true);

    if (!$xml->loadXML($xml_content)) {
        echo "❌ Error al parsear XML rechazado\n";
        exit(1);
    }

    // Extraer datos del XML rechazado
    $xpath = new DOMXPath($xml);
    $xpath->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/3');
    $xpath->registerNamespace('cfdi4', 'http://www.sat.gob.mx/cfd/4');

    // Intentar ambas versiones
    $comprobante = $xpath->query('//cfdi:Comprobante')->item(0);
    if (!$comprobante) {
        $comprobante = $xpath->query('//cfdi4:Comprobante')->item(0);
    }

    if (!$comprobante) {
        echo "❌ No se pudo encontrar el nodo Comprobante en el XML\n";
        exit(1);
    }

    $datos_xml = [
        'uuid' => $uuid_rechazado,
        'fecha' => $comprobante->getAttribute('Fecha'),
        'total' => $comprobante->getAttribute('Total'),
        'emisor_rfc' => '',
        'receptor_rfc' => '',
        'moneda' => $comprobante->getAttribute('Moneda') ?: 'MXN',
        'tipo_de_comprobante' => $comprobante->getAttribute('TipoDeComprobante'),
        'metodo_pago' => $comprobante->getAttribute('MetodoPago'),
        'forma_pago' => $comprobante->getAttribute('FormaPago')
    ];

    // Extraer RFC del emisor
    $emisor = $xpath->query('//cfdi:Emisor | //cfdi4:Emisor')->item(0);
    if ($emisor) {
        $datos_xml['emisor_rfc'] = $emisor->getAttribute('Rfc');
    }

    // Extraer RFC del receptor
    $receptor = $xpath->query('//cfdi:Receptor | //cfdi4:Receptor')->item(0);
    if ($receptor) {
        $datos_xml['receptor_rfc'] = $receptor->getAttribute('Rfc');
    }

    echo "\n📊 DATOS DEL XML RECHAZADO:\n";
    echo "   📅 Fecha: {$datos_xml['fecha']}\n";
    echo "   💰 Total: {$datos_xml['total']}\n";
    echo "   🏢 Emisor: {$datos_xml['emisor_rfc']}\n";
    echo "   👤 Receptor: {$datos_xml['receptor_rfc']}\n";
    echo "   💱 Moneda: {$datos_xml['moneda']}\n";
    echo "   📋 Tipo: {$datos_xml['tipo_de_comprobante']}\n";
    echo "   💳 Método Pago: {$datos_xml['metodo_pago']}\n";
    echo "   🏦 Forma Pago: {$datos_xml['forma_pago']}\n\n";

    // 4. COMPARACIÓN DETALLADA
    echo "🔍 COMPARACIÓN DETALLADA:\n";
    echo "===========================================\n";

    $diferencias = [];
    $campos_comparar = ['fecha', 'total', 'emisor_rfc', 'receptor_rfc'];

    foreach ($campos_comparar as $campo) {
        $valor_bd = $cfdi_bd[$campo] ?? '';
        $valor_xml = $datos_xml[$campo] ?? '';

        if ($valor_bd !== $valor_xml) {
            $diferencias[] = $campo;
            echo "❌ DIFERENCIA en $campo:\n";
            echo "   🗄️ BD:  '$valor_bd'\n";
            echo "   📄 XML: '$valor_xml'\n\n";
        } else {
            echo "✅ $campo: IGUAL\n";
        }
    }

    // 5. Verificar diferencias en contenido XML completo
    echo "\n🔍 ANÁLISIS DE CONTENIDO COMPLETO:\n";
    echo "===========================================\n";

    // Obtener hash MD5 del contenido actual
    $hash_actual = md5($xml_content);
    echo "🔐 Hash MD5 del XML rechazado: $hash_actual\n";

    // Si tenemos el archivo original guardado, comparar
    if (!empty($cfdi_bd['archivo_path']) && file_exists($cfdi_bd['archivo_path'])) {
        $xml_original = file_get_contents($cfdi_bd['archivo_path']);
        $hash_original = md5($xml_original);
        echo "🔐 Hash MD5 del XML en BD: $hash_original\n";

        if ($hash_actual === $hash_original) {
            echo "✅ Los archivos XML son IDÉNTICOS (mismo hash MD5)\n";
        } else {
            echo "❌ Los archivos XML son DIFERENTES\n";
            echo "📏 Tamaño XML BD: " . strlen($xml_original) . " bytes\n";
            echo "📏 Tamaño XML rechazado: " . strlen($xml_content) . " bytes\n";
        }
    }

    // 6. CONCLUSIONES Y RECOMENDACIONES
    echo "\n📋 CONCLUSIONES:\n";
    echo "===========================================\n";

    if (empty($diferencias)) {
        echo "✅ NO hay diferencias en campos principales\n";
        echo "🔄 Recomendación: Mantener el rechazo por duplicado\n";
    } else {
        echo "❌ SE encontraron " . count($diferencias) . " diferencia(s)\n";
        echo "🔄 Campos diferentes: " . implode(', ', $diferencias) . "\n";
        echo "💡 Recomendación: Implementar sistema de actualización\n";
    }

    echo "\n📊 ESTADÍSTICAS:\n";
    echo "   📁 Archivo BD: " . ($cfdi_bd['archivo_path'] ?? 'No registrado') . "\n";
    echo "   📁 Archivo rechazado: $archivo_rechazado\n";
    echo "   🕐 Procesado en BD: {$cfdi_bd['fecha_procesamiento']}\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
