<?php
// Script para analizar estructura de archivos XML problemáticos

$archivo_ejemplo = 'storage/sat_downloads/BFM170822P38/EMITIDAS/2020/1/2020_01_10_702888E7-16B1-4A6E-AAB5-C3F95047C4F2.xml';

echo "=== ANÁLISIS DE ARCHIVO XML PROBLEMÁTICO ===\n\n";
echo "Archivo: {$archivo_ejemplo}\n\n";

if (!file_exists($archivo_ejemplo)) {
    echo "❌ El archivo no existe\n";
    exit;
}

// Leer contenido crudo
$contenido = file_get_contents($archivo_ejemplo);
$primeros_1000 = substr($contenido, 0, 1000);

echo "--- PRIMEROS 1000 CARACTERES ---\n";
echo $primeros_1000 . "\n\n";

// Intentar cargar XML
libxml_use_internal_errors(true);
$xml = simplexml_load_file($archivo_ejemplo);

if ($xml === false) {
    echo "❌ Error al cargar XML:\n";
    $errores = libxml_get_errors();
    foreach ($errores as $error) {
        echo "  - " . trim($error->message) . "\n";
    }
    echo "\n";
} else {
    echo "✅ XML cargado exitosamente\n\n";

    // Mostrar estructura básica
    echo "--- ESTRUCTURA BÁSICA ---\n";
    echo "Nodo raíz: " . $xml->getName() . "\n";

    // Mostrar namespaces
    echo "\n--- NAMESPACES ---\n";
    $namespaces = $xml->getNamespaces(true);
    foreach ($namespaces as $prefix => $uri) {
        $display_prefix = $prefix ?: '[default]';
        echo "  {$display_prefix}: {$uri}\n";
    }

    // Buscar complementos
    echo "\n--- COMPLEMENTOS ---\n";
    if (isset($xml->Complemento)) {
        echo "Encontrados complementos:\n";
        foreach ($xml->Complemento->children() as $complemento) {
            echo "  - " . $complemento->getName() . "\n";

            // Mostrar atributos del complemento
            $attrs = $complemento->attributes();
            foreach ($attrs as $key => $value) {
                echo "    {$key}: " . substr((string)$value, 0, 50) . "\n";
            }
        }

        // Buscar en namespaces
        foreach ($namespaces as $prefix => $uri) {
            $complementos = $xml->Complemento->children($uri);
            if (count($complementos) > 0) {
                echo "  Complementos en namespace '{$prefix}' ({$uri}):\n";
                foreach ($complementos as $complemento) {
                    echo "    - " . $complemento->getName() . "\n";
                    $attrs = $complemento->attributes();
                    foreach ($attrs as $key => $value) {
                        if ($key === 'UUID') {
                            echo "      *** UUID ENCONTRADO: {$value} ***\n";
                        } else {
                            echo "      {$key}: " . substr((string)$value, 0, 30) . "\n";
                        }
                    }
                }
            }
        }
    } else {
        echo "No se encontraron complementos\n";
    }

    // Buscar UUID con xpath
    echo "\n--- BÚSQUEDA UUID CON XPATH ---\n";
    if (method_exists($xml, 'xpath')) {
        $uuids = $xml->xpath('//@UUID');
        if (!empty($uuids)) {
            echo "UUIDs encontrados con XPath:\n";
            foreach ($uuids as $uuid) {
                echo "  - " . (string)$uuid . "\n";
            }
        } else {
            echo "No se encontraron UUIDs con XPath\n";
        }
    }
}
