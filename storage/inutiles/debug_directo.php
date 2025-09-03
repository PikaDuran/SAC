<?php

/**
 * SCRIPT DIRECTO - PROCESAR UN XML Y MOSTRAR TODO
 */

require_once __DIR__ . '/src/config/database.php';

echo "DEBUG DIRECTO - PROCESANDO UN XML\n";
echo str_repeat("=", 50) . "\n";

$pdo = getDatabase();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Tomar el primer archivo XML que encontremos
$archivos = glob('storage/sat_downloads/*/EMITIDAS/*/1/*.xml');
if (empty($archivos)) {
    echo "ERROR: No se encontraron archivos XML\n";
    exit;
}

$archivo = $archivos[0];
echo "Procesando: $archivo\n";

// Cargar XML
$contenido = file_get_contents($archivo);
$xml = simplexml_load_string($contenido);

if (!$xml) {
    echo "ERROR: No se pudo cargar XML\n";
    exit;
}

echo "✅ XML cargado\n";
echo "Tipo: " . (string)$xml['TipoDeComprobante'] . "\n";

// VERIFICAR CONCEPTOS
echo "\n--- CONCEPTOS ---\n";
if (isset($xml->Conceptos->Concepto)) {
    echo "✅ Conceptos encontrados: " . count($xml->Conceptos->Concepto) . "\n";

    foreach ($xml->Conceptos->Concepto as $i => $concepto) {
        echo "Concepto " . ($i + 1) . ":\n";
        $attrs = $concepto->attributes();
        foreach ($attrs as $key => $value) {
            echo "  $key: $value\n";
        }
        break; // Solo mostrar el primero
    }
} else {
    echo "❌ NO hay conceptos\n";
}

// VERIFICAR COMPLEMENTOS
echo "\n--- COMPLEMENTOS ---\n";
if (isset($xml->Complemento)) {
    echo "✅ Complementos encontrados: " . count($xml->Complemento) . "\n";

    foreach ($xml->Complemento as $complemento) {
        $namespaces = $complemento->getNamespaces(true);
        echo "Namespaces: " . implode(', ', array_keys($namespaces)) . "\n";
    }
} else {
    echo "❌ NO hay complementos\n";
}

// INSERTAR EN BASE DE DATOS
echo "\n--- INSERCIÓN BD ---\n";

try {
    // Buscar si ya existe este CFDI
    $uuid = '';
    $namespaces = $xml->getNamespaces(true);
    if (isset($namespaces['tfd'])) {
        $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
        $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');
        if (!empty($timbres)) {
            $uuid = (string)$timbres[0]['UUID'];
        }
    }

    if (empty($uuid)) {
        echo "❌ NO se encontró UUID\n";
        exit;
    }

    echo "UUID: $uuid\n";

    // Verificar si ya existe
    $stmt = $pdo->prepare("SELECT id FROM cfdi WHERE uuid = ?");
    $stmt->execute([$uuid]);
    $cfdi_existente = $stmt->fetch();

    if ($cfdi_existente) {
        echo "✅ CFDI ya existe con ID: " . $cfdi_existente['id'] . "\n";
        $cfdi_id = $cfdi_existente['id'];

        // VERIFICAR TABLAS RELACIONADAS
        $tablas = [
            'cfdi_conceptos' => 'conceptos',
            'cfdi_impuestos' => 'impuestos',
            'cfdi_complementos' => 'complementos'
        ];

        foreach ($tablas as $tabla => $nombre) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM $tabla WHERE cfdi_id = ?");
            $stmt->execute([$cfdi_id]);
            $count = $stmt->fetchColumn();
            echo "$nombre en $tabla: $count registros\n";
        }
    } else {
        echo "❌ CFDI NO existe en BD\n";
    }
} catch (Exception $e) {
    echo "ERROR BD: " . $e->getMessage() . "\n";
}
