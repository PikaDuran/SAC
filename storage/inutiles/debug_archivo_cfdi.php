<?php

/**
 * SCRIPT DE DEBUG - PROCESAR UN SOLO ARCHIVO CFDI
 * Para verificar que todas las funciones de inserción funcionen correctamente
 */

require_once __DIR__ . '/src/config/database.php';

class DebugImportador
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = getDatabase();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function procesarArchivoDebug($rutaArchivo)
    {
        echo "🔍 DEBUG: Procesando archivo específico\n";
        echo "Archivo: {$rutaArchivo}\n";
        echo str_repeat("-", 60) . "\n";

        if (!file_exists($rutaArchivo)) {
            echo "❌ ERROR: Archivo no encontrado\n";
            return;
        }

        try {
            // Cargar XML
            $contenido = file_get_contents($rutaArchivo);
            $xml = simplexml_load_string($contenido);

            if (!$xml) {
                echo "❌ ERROR: No se pudo cargar el XML\n";
                return;
            }

            // Mostrar información básica
            echo "✅ XML cargado correctamente\n";
            echo "Tipo: " . (string)$xml['TipoDeComprobante'] . "\n";
            echo "Serie: " . (string)$xml['Serie'] . "\n";
            echo "Folio: " . (string)$xml['Folio'] . "\n";

            // Verificar conceptos
            if (isset($xml->Conceptos->Concepto)) {
                $numConceptos = count($xml->Conceptos->Concepto);
                echo "📄 Conceptos encontrados: {$numConceptos}\n";

                foreach ($xml->Conceptos->Concepto as $i => $concepto) {
                    $attrs = $concepto->attributes();
                    echo "  - Concepto " . ($i + 1) . ": " . substr((string)$attrs->Descripcion, 0, 50) . "...\n";

                    // Verificar impuestos del concepto
                    if (isset($concepto->Impuestos)) {
                        if (isset($concepto->Impuestos->Traslados->Traslado)) {
                            echo "    📊 Traslados: " . count($concepto->Impuestos->Traslados->Traslado) . "\n";
                        }
                        if (isset($concepto->Impuestos->Retenciones->Retencion)) {
                            echo "    📊 Retenciones: " . count($concepto->Impuestos->Retenciones->Retencion) . "\n";
                        }
                    }
                }
            } else {
                echo "⚠️  No se encontraron conceptos\n";
            }

            // Verificar complementos
            if (isset($xml->Complemento)) {
                $numComplementos = count($xml->Complemento);
                echo "🔧 Complementos encontrados: {$numComplementos}\n";

                foreach ($xml->Complemento as $i => $complemento) {
                    $namespaces = $complemento->getNamespaces(true);
                    echo "  - Complemento " . ($i + 1) . " namespaces: " . implode(', ', array_keys($namespaces)) . "\n";

                    // Si es complemento de pago, verificar detalles
                    if (isset($namespaces['pago20']) || isset($namespaces['pago10'])) {
                        echo "    💰 Es complemento de PAGO\n";

                        // Verificar documentos relacionados
                        $xml->registerXPathNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');
                        $xml->registerXPathNamespace('pago10', 'http://www.sat.gob.mx/Pagos');

                        $pagos = $xml->xpath('//pago20:Pago | //pago10:Pago');
                        foreach ($pagos as $pago) {
                            if (isset($pago->DoctoRelacionado)) {
                                echo "    📋 Documentos relacionados: " . count($pago->DoctoRelacionado) . "\n";
                            }
                            if (isset($pago->Totales)) {
                                echo "    💯 Tiene totales de pago\n";
                            }
                        }
                    }
                }
            } else {
                echo "⚠️  No se encontraron complementos\n";
            }
        } catch (Exception $e) {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
        }
    }
}

// Ejecutar debug con un archivo específico
$debug = new DebugImportador();

// Buscar un archivo CFDI de ejemplo
$rutasEjemplo = [
    'storage/sat_downloads/BFM170822P38/EMITIDAS/2020/1',
    'storage/sat_downloads/BLM1706026AA/EMITIDAS/2020/1',
    'storage/sat_downloads/BFM170822P38/RECIBIDAS/2020/1',
    'storage/sat_downloads/BLM1706026AA/RECIBIDAS/2020/1'
];

$archivoEncontrado = null;
foreach ($rutasEjemplo as $ruta) {
    if (is_dir($ruta)) {
        $archivos = glob($ruta . '/*.xml');
        if (!empty($archivos)) {
            $archivoEncontrado = $archivos[0];
            break;
        }
    }
}

if ($archivoEncontrado) {
    $debug->procesarArchivoDebug($archivoEncontrado);
} else {
    // Usar un archivo específico que sabemos que existe
    $archivoEspecifico = 'storage/sat_downloads/BFM170822P38/EMITIDAS/2020/1/2020_01_10_702888E7-16B1-4A6E-AAB5-C3F95047C4F2.xml';
    if (file_exists($archivoEspecifico)) {
        $debug->procesarArchivoDebug($archivoEspecifico);
    } else {
        echo "❌ No se encontraron archivos XML de ejemplo\n";
    }
}
