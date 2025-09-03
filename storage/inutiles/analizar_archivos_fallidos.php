<?php

/**
 * ANALIZADOR DE ARCHIVOS FALLIDOS
 * Analiza los archivos que no pudieron ser procesados para entender por qué fallan
 */

require_once __DIR__ . '/src/config/database.php';

class AnalizadorFallidos
{

    public function analizarArchivo($rutaArchivo)
    {
        echo "🔍 ANALIZANDO: " . basename($rutaArchivo) . "\n";
        echo str_repeat("-", 60) . "\n";

        // 1. Verificar que el archivo existe
        if (!file_exists($rutaArchivo)) {
            echo "❌ El archivo no existe\n\n";
            return;
        }

        // 2. Verificar tamaño del archivo
        $tamano = filesize($rutaArchivo);
        echo "📏 Tamaño: " . number_format($tamano) . " bytes\n";

        if ($tamano == 0) {
            echo "❌ Archivo vacío\n\n";
            return;
        }

        // 3. Leer contenido
        $contenido = file_get_contents($rutaArchivo);
        if (!$contenido) {
            echo "❌ No se pudo leer el contenido\n\n";
            return;
        }

        echo "📄 Primeros 200 caracteres:\n";
        echo substr($contenido, 0, 200) . "...\n\n";

        // 4. Verificar codificación
        $encoding = mb_detect_encoding($contenido);
        echo "🔤 Codificación detectada: " . ($encoding ?: 'No detectada') . "\n";

        // 5. Intentar parsear XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contenido);

        if (!$xml) {
            echo "❌ Error al parsear XML:\n";
            $errores = libxml_get_errors();
            foreach ($errores as $error) {
                echo "  • Línea {$error->line}: {$error->message}";
            }
            libxml_clear_errors();
            echo "\n";
            return;
        }

        echo "✅ XML parseado correctamente\n";

        // 6. Verificar estructura CFDI
        $this->verificarEstructuraCFDI($xml);

        // 7. Mostrar información básica
        $this->mostrarInfoBasica($xml);

        echo "\n" . str_repeat("=", 60) . "\n\n";
    }

    private function verificarEstructuraCFDI($xml)
    {
        echo "\n🔍 VERIFICANDO ESTRUCTURA CFDI:\n";

        // Verificar atributos principales
        $atributos_requeridos = ['Version', 'TipoDeComprobante', 'Fecha', 'SubTotal', 'Total'];

        foreach ($atributos_requeridos as $attr) {
            if (isset($xml[$attr])) {
                echo "  ✅ {$attr}: " . (string)$xml[$attr] . "\n";
            } else {
                echo "  ❌ Falta atributo: {$attr}\n";
            }
        }

        // Verificar secciones
        $secciones = ['Emisor', 'Receptor', 'Conceptos'];
        foreach ($secciones as $seccion) {
            if (isset($xml->$seccion)) {
                echo "  ✅ Sección {$seccion} presente\n";
            } else {
                echo "  ❌ Falta sección: {$seccion}\n";
            }
        }

        // Verificar namespaces
        echo "\n📋 NAMESPACES:\n";
        $namespaces = $xml->getNamespaces(true);
        foreach ($namespaces as $prefix => $uri) {
            $prefixDisplay = $prefix ?: 'default';
            echo "  • {$prefixDisplay}: {$uri}\n";
        }
    }

    private function mostrarInfoBasica($xml)
    {
        echo "\n📊 INFORMACIÓN BÁSICA:\n";

        $info = [
            'Versión CFDI' => (string)$xml['Version'],
            'Tipo' => (string)$xml['TipoDeComprobante'],
            'Serie' => (string)$xml['Serie'],
            'Folio' => (string)$xml['Folio'],
            'Fecha' => (string)$xml['Fecha'],
            'RFC Emisor' => (string)$xml->Emisor['Rfc'],
            'RFC Receptor' => (string)$xml->Receptor['Rfc'],
            'Total' => (string)$xml['Total'],
            'Moneda' => (string)$xml['Moneda']
        ];

        foreach ($info as $campo => $valor) {
            echo "  • {$campo}: " . ($valor ?: 'N/A') . "\n";
        }

        // Contar conceptos
        if (isset($xml->Conceptos->Concepto)) {
            $conceptos = $xml->Conceptos->Concepto;
            $num_conceptos = is_array($conceptos) ? count($conceptos) : 1;
            echo "  • Conceptos: {$num_conceptos}\n";
        }

        // Verificar timbre fiscal
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['tfd'])) {
            $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
            $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');
            if (!empty($timbres)) {
                echo "  • UUID: " . (string)$timbres[0]['UUID'] . "\n";
            }
        }
    }

    public function analizarLista($archivo_lista)
    {
        echo "🔍 ANALIZADOR DE ARCHIVOS FALLIDOS\n";
        echo str_repeat("=", 60) . "\n\n";

        if (!file_exists($archivo_lista)) {
            echo "❌ Error: No se encontró el archivo de lista: {$archivo_lista}\n";
            return;
        }

        $archivos = file($archivo_lista, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total = count($archivos);

        echo "📁 Analizando {$total} archivos fallidos...\n\n";

        $contador = 0;
        foreach ($archivos as $archivo) {
            $contador++;
            echo "📝 Archivo {$contador}/{$total}:\n";
            $this->analizarArchivo(trim($archivo));

            // Pausa cada 10 archivos
            if ($contador % 10 == 0) {
                echo "⏸️  Pausa... (Presiona Enter para continuar)\n";
                readline();
            }
        }

        echo "✅ Análisis completado\n";
    }

    public function analizarMuestra($archivo_lista, $cantidad = 5)
    {
        echo "🔍 ANÁLISIS DE MUESTRA - ARCHIVOS FALLIDOS\n";
        echo str_repeat("=", 60) . "\n\n";

        if (!file_exists($archivo_lista)) {
            echo "❌ Error: No se encontró el archivo de lista: {$archivo_lista}\n";
            return;
        }

        $archivos = file($archivo_lista, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total = count($archivos);

        echo "📁 Analizando muestra de {$cantidad} archivos de {$total} fallidos...\n\n";

        // Tomar una muestra aleatoria
        $muestra = array_slice($archivos, 0, min($cantidad, $total));

        $contador = 0;
        foreach ($muestra as $archivo) {
            $contador++;
            echo "📝 Muestra {$contador}/{$cantidad}:\n";
            $this->analizarArchivo(trim($archivo));
        }

        echo "✅ Análisis de muestra completado\n";
    }
}

// Verificar argumentos de línea de comandos
if ($argc < 2) {
    echo "USO:\n";
    echo "  php " . basename(__FILE__) . " <archivo_lista> [muestra]\n\n";
    echo "EJEMPLOS:\n";
    echo "  php " . basename(__FILE__) . " logs/archivos_fallidos_2025-08-26_15-30-00.txt\n";
    echo "  php " . basename(__FILE__) . " logs/archivos_fallidos_2025-08-26_15-30-00.txt muestra\n\n";
    exit(1);
}

$archivo_lista = $argv[1];
$es_muestra = isset($argv[2]) && $argv[2] === 'muestra';

$analizador = new AnalizadorFallidos();

if ($es_muestra) {
    $analizador->analizarMuestra($archivo_lista, 10);
} else {
    $analizador->analizarLista($archivo_lista);
}
