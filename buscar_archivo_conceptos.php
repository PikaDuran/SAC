<?php

/**
 * BUSCAR ARCHIVO CFDI CON CONCEPTOS
 */

$archivos = glob('storage/sat_downloads/BFM170822P38/EMITIDAS/2020/1/*.xml');

echo "Buscando archivos con conceptos...\n";

foreach (array_slice($archivos, 0, 10) as $archivo) {
    $contenido = file_get_contents($archivo);
    $xml = simplexml_load_string($contenido);

    if ($xml && isset($xml->Conceptos->Concepto)) {
        $numConceptos = count($xml->Conceptos->Concepto);
        $tipo = (string)$xml['TipoDeComprobante'];
        echo "✅ {$archivo} - Tipo: {$tipo} - Conceptos: {$numConceptos}\n";

        if ($numConceptos > 0) {
            echo "ARCHIVO ENCONTRADO: {$archivo}\n";
            break;
        }
    }
}
