<?php

/**
 * ANALIZADOR EXHAUSTIVO DE XMLs CFDI PARA DISEÑO DE BASE DE DATOS
 * ================================================================
 * 
 * Este script analiza TODOS los XMLs CFDI para mapear COMPLETAMENTE
 * todos los campos, atributos, elementos y complementos existentes.
 * 
 * CRÍTICO: De este análisis depende la creación correcta de las tablas BD
 */

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
libxml_use_internal_errors(true);

class AnalizadorExhaustivoXML
{
    private $baseDir;
    private $todosLosCampos = [];
    private $todoLosComplementos = [];
    private $estadisticas = [
        'cfdi_33_emitidos' => ['total' => 0, 'procesados' => 0, 'errores' => 0],
        'cfdi_33_recibidos' => ['total' => 0, 'procesados' => 0, 'errores' => 0],
        'cfdi_40_emitidos' => ['total' => 0, 'procesados' => 0, 'errores' => 0],
        'cfdi_40_recibidos' => ['total' => 0, 'procesados' => 0, 'errores' => 0]
    ];
    private $muestraPorCategoria = [];

    public function __construct()
    {
        $this->baseDir = __DIR__ . '/storage/sat_downloads';
    }

    public function ejecutarAnalisisCompleto()
    {
        echo "🔍 ANALIZADOR EXHAUSTIVO DE XMLs CFDI PARA BASE DE DATOS\n";
        echo str_repeat("=", 80) . "\n\n";

        $inicio = microtime(true);

        // Obtener todos los archivos XML
        $archivosXml = $this->obtenerTodosLosXMLs();
        echo "📁 Total archivos XML encontrados: " . count($archivosXml) . "\n\n";

        // Procesar cada archivo
        foreach ($archivosXml as $archivo) {
            $this->procesarArchivoXML($archivo);
        }

        // Generar reporte exhaustivo
        $this->generarReporteExhaustivo();

        $tiempo = round(microtime(true) - $inicio, 2);
        echo "\n⏱️  Tiempo total de procesamiento: {$tiempo} segundos\n";
        echo "✅ ANÁLISIS EXHAUSTIVO COMPLETADO\n";
    }

    private function obtenerTodosLosXMLs()
    {
        $archivos = [];
        $directorios = [
            'EMITIDAS' => $this->baseDir . '/*/EMITIDAS',
            'RECIBIDAS' => $this->baseDir . '/*/RECIBIDAS'
        ];

        foreach ($directorios as $tipo => $patron) {
            $rutas = glob($patron, GLOB_ONLYDIR);
            foreach ($rutas as $ruta) {
                $xmls = $this->buscarXMLsRecursivamente($ruta);
                foreach ($xmls as $xml) {
                    $archivos[] = [
                        'archivo' => $xml,
                        'tipo' => $tipo,
                        'ruta_completa' => $xml
                    ];
                }
            }
        }

        return $archivos;
    }

    private function buscarXMLsRecursivamente($directorio)
    {
        $archivos = [];
        $iterador = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directorio)
        );

        foreach ($iterador as $archivo) {
            if ($archivo->isFile() && strtolower($archivo->getExtension()) === 'xml') {
                $archivos[] = $archivo->getPathname();
            }
        }

        return $archivos;
    }

    private function procesarArchivoXML($infoArchivo)
    {
        $archivo = $infoArchivo['archivo'];
        $tipo = $infoArchivo['tipo'];

        try {
            // Validar que el archivo existe y es legible
            if (!file_exists($archivo) || !is_readable($archivo)) {
                return;
            }

            // Leer contenido del archivo
            $contenido = file_get_contents($archivo);
            if ($contenido === false || empty(trim($contenido))) {
                return;
            }

            // Cargar XML con manejo de errores
            $xml = new DOMDocument();
            $xml->preserveWhiteSpace = false;
            $xml->formatOutput = true;

            if (!@$xml->loadXML($contenido)) {
                $this->registrarError($archivo, $tipo);
                return;
            }

            // Determinar versión y categoría
            $version = $this->determinarVersion($xml);
            $categoria = $this->determinarCategoria($archivo, $tipo, $version);

            // Actualizar estadísticas
            $this->estadisticas[$categoria]['total']++;

            // Extraer TODOS los campos exhaustivamente
            $campos = $this->extraerTodosLosCamposExhaustivamente($xml);
            $complementos = $this->extraerTodosLosComplementos($xml);

            // Almacenar campos por categoría
            foreach ($campos as $campo => $valor) {
                if (!isset($this->todosLosCampos[$campo])) {
                    $this->todosLosCampos[$campo] = [
                        'cfdi_33_emitidos' => false,
                        'cfdi_33_recibidos' => false,
                        'cfdi_40_emitidos' => false,
                        'cfdi_40_recibidos' => false,
                        'valores_ejemplo' => []
                    ];
                }
                $this->todosLosCampos[$campo][$categoria] = true;

                // Guardar valores ejemplo (máximo 3 por campo)
                if (count($this->todosLosCampos[$campo]['valores_ejemplo']) < 3) {
                    $valorLimpio = is_string($valor) ? substr($valor, 0, 50) : $valor;
                    if (!in_array($valorLimpio, $this->todosLosCampos[$campo]['valores_ejemplo'])) {
                        $this->todosLosCampos[$campo]['valores_ejemplo'][] = $valorLimpio;
                    }
                }
            }

            // Almacenar complementos
            foreach ($complementos as $complemento => $info) {
                if (!isset($this->todoLosComplementos[$complemento])) {
                    $this->todoLosComplementos[$complemento] = [
                        'cfdi_33_emitidos' => false,
                        'cfdi_33_recibidos' => false,
                        'cfdi_40_emitidos' => false,
                        'cfdi_40_recibidos' => false,
                        'campos' => []
                    ];
                }
                $this->todoLosComplementos[$complemento][$categoria] = true;
                $this->todoLosComplementos[$complemento]['campos'] = array_merge(
                    $this->todoLosComplementos[$complemento]['campos'],
                    $info['campos']
                );
                $this->todoLosComplementos[$complemento]['campos'] = array_unique(
                    $this->todoLosComplementos[$complemento]['campos']
                );
            }

            $this->estadisticas[$categoria]['procesados']++;

            // Mostrar progreso cada 1000 archivos
            $totalProcesados = array_sum(array_column($this->estadisticas, 'procesados'));
            if ($totalProcesados % 1000 == 0) {
                echo "📊 Procesados: {$totalProcesados} archivos...\n";
            }
        } catch (Exception $e) {
            $this->registrarError($archivo, $tipo);
        }
    }

    private function extraerTodosLosCamposExhaustivamente($xml)
    {
        $campos = [];

        // Extraer campos del elemento raíz (Comprobante)
        $comprobante = $xml->documentElement;
        if ($comprobante) {
            $prefijo = 'COMPROBANTE_';
            foreach ($comprobante->attributes as $atributo) {
                $campos[$prefijo . $atributo->name] = $atributo->value;
            }
        }

        // Extraer TODOS los elementos y subelementos recursivamente
        $this->extraerElementosRecursivamente($xml->documentElement, '', $campos);

        return $campos;
    }

    private function extraerElementosRecursivamente($elemento, $prefijo = '', &$campos = [])
    {
        if (!$elemento) return;

        $nombreElemento = $elemento->nodeName;

        // Saltar elementos de texto y comentarios
        if ($elemento->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        // Construir prefijo
        $prefijoActual = $prefijo ? $prefijo . '_' . strtoupper($nombreElemento) : strtoupper($nombreElemento);

        // Extraer atributos del elemento actual
        if ($elemento->hasAttributes()) {
            foreach ($elemento->attributes as $atributo) {
                $nombreCampo = $prefijoActual . '_' . $atributo->name;
                $campos[$nombreCampo] = $atributo->value;
            }
        }

        // Extraer texto del elemento si no tiene hijos elementos
        if (!$this->tieneElementosHijos($elemento) && !empty(trim($elemento->textContent))) {
            $campos[$prefijoActual . '_TEXTO'] = trim($elemento->textContent);
        }

        // Contar elementos múltiples (como conceptos)
        $elementosHijos = [];
        foreach ($elemento->childNodes as $hijo) {
            if ($hijo->nodeType === XML_ELEMENT_NODE) {
                $nombreHijo = $hijo->nodeName;
                if (!isset($elementosHijos[$nombreHijo])) {
                    $elementosHijos[$nombreHijo] = 0;
                }
                $elementosHijos[$nombreHijo]++;
            }
        }

        // Agregar contadores para elementos múltiples
        foreach ($elementosHijos as $nombreHijo => $cantidad) {
            if ($cantidad > 1) {
                $campos[$prefijoActual . '_' . strtoupper($nombreHijo) . '_TOTAL'] = $cantidad;
            }
        }

        // Procesar elementos hijos recursivamente
        foreach ($elemento->childNodes as $hijo) {
            if ($hijo->nodeType === XML_ELEMENT_NODE) {
                $this->extraerElementosRecursivamente($hijo, $prefijoActual, $campos);
            }
        }
    }

    private function tieneElementosHijos($elemento)
    {
        foreach ($elemento->childNodes as $hijo) {
            if ($hijo->nodeType === XML_ELEMENT_NODE) {
                return true;
            }
        }
        return false;
    }

    private function extraerTodosLosComplementos($xml)
    {
        $complementos = [];

        // Buscar el nodo Complemento
        $complementoNodes = $xml->getElementsByTagName('Complemento');

        foreach ($complementoNodes as $complementoNode) {
            foreach ($complementoNode->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $nombreComplemento = $child->nodeName;
                    $namespace = $child->namespaceURI;

                    // Extraer versión si existe
                    $version = '';
                    if ($child->hasAttribute('Version')) {
                        $version = $child->getAttribute('Version');
                    } elseif ($child->hasAttribute('version')) {
                        $version = $child->getAttribute('version');
                    }

                    $claveComplemento = $nombreComplemento . ($version ? "_v{$version}" : '');

                    if (!isset($complementos[$claveComplemento])) {
                        $complementos[$claveComplemento] = [
                            'nombre' => $nombreComplemento,
                            'version' => $version,
                            'namespace' => $namespace,
                            'campos' => []
                        ];
                    }

                    // Extraer todos los campos del complemento recursivamente
                    $camposComplemento = [];
                    $this->extraerElementosRecursivamente($child, 'COMPLEMENTO_' . strtoupper($nombreComplemento), $camposComplemento);
                    $complementos[$claveComplemento]['campos'] = array_merge(
                        $complementos[$claveComplemento]['campos'],
                        array_keys($camposComplemento)
                    );
                }
            }
        }

        return $complementos;
    }

    private function determinarVersion($xml)
    {
        $comprobante = $xml->documentElement;
        if ($comprobante && $comprobante->hasAttribute('Version')) {
            return $comprobante->getAttribute('Version');
        }
        return 'desconocida';
    }

    private function determinarCategoria($archivo, $tipo, $version)
    {
        // Extraer año del path del archivo
        preg_match('/(\d{4})/', $archivo, $matches);
        $anio = isset($matches[1]) ? intval($matches[1]) : 2024;

        $versionNormalizada = $anio < 2023 ? '33' : '40';
        $tipoNormalizado = strtolower($tipo) === 'emitidas' ? 'emitidos' : 'recibidos';

        return "cfdi_{$versionNormalizada}_{$tipoNormalizado}";
    }

    private function registrarError($archivo, $tipo)
    {
        // Determinar categoría para estadísticas de error
        preg_match('/(\d{4})/', $archivo, $matches);
        $anio = isset($matches[1]) ? intval($matches[1]) : 2024;
        $versionNormalizada = $anio < 2023 ? '33' : '40';
        $tipoNormalizado = strtolower($tipo) === 'emitidas' ? 'emitidos' : 'recibidos';
        $categoria = "cfdi_{$versionNormalizada}_{$tipoNormalizado}";

        $this->estadisticas[$categoria]['errores']++;
    }

    private function generarReporteExhaustivo()
    {
        $fecha = date('Y-m-d_H-i-s');
        $archivo = "ANALISIS_EXHAUSTIVO_BD_{$fecha}.txt";

        $reporte = $this->construirReporteCompleto();

        file_put_contents($archivo, $reporte);

        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📊 REPORTE EXHAUSTIVO PARA BASE DE DATOS\n";
        echo str_repeat("=", 80) . "\n\n";

        // Mostrar estadísticas en pantalla
        echo "📈 ESTADÍSTICAS DE PROCESAMIENTO:\n";
        foreach ($this->estadisticas as $categoria => $stats) {
            $nombre = strtoupper(str_replace('_', ' ', $categoria));
            echo "   {$nombre}: {$stats['procesados']}/{$stats['total']} procesados, {$stats['errores']} errores\n";
        }

        echo "\n🔍 CAMPOS TOTALES IDENTIFICADOS: " . count($this->todosLosCampos) . "\n";
        echo "📦 COMPLEMENTOS TOTALES: " . count($this->todoLosComplementos) . "\n";
        echo "\n💾 REPORTE COMPLETO GUARDADO EN: {$archivo}\n";
    }

    private function construirReporteCompleto()
    {
        $reporte = "ANÁLISIS EXHAUSTIVO DE XMLs CFDI PARA DISEÑO DE BASE DE DATOS\n";
        $reporte .= "Generado: " . date('Y-m-d H:i:s') . "\n";
        $reporte .= str_repeat("=", 100) . "\n\n";

        // Estadísticas
        $reporte .= "ESTADÍSTICAS DE PROCESAMIENTO:\n";
        $reporte .= str_repeat("-", 50) . "\n";
        foreach ($this->estadisticas as $categoria => $stats) {
            $nombre = strtoupper(str_replace('_', ' ', $categoria));
            $reporte .= sprintf(
                "%-20s: %6d procesados / %6d total (%6d errores)\n",
                $nombre,
                $stats['procesados'],
                $stats['total'],
                $stats['errores']
            );
        }

        // Resumen de campos
        $reporte .= "\n\nRESUMEN DE CAMPOS IDENTIFICADOS:\n";
        $reporte .= str_repeat("-", 50) . "\n";
        $reporte .= "Total de campos únicos: " . count($this->todosLosCampos) . "\n\n";

        // Tabla comparativa de campos
        $reporte .= $this->construirTablaComparativaCampos();

        // Detalles de complementos
        $reporte .= $this->construirDetallesComplementos();

        // Recomendaciones para BD
        $reporte .= $this->construirRecomendacionesBD();

        return $reporte;
    }

    private function construirTablaComparativaCampos()
    {
        $tabla = "\nTABLA COMPARATIVA COMPLETA DE CAMPOS:\n";
        $tabla .= str_repeat("=", 100) . "\n\n";

        $header = sprintf(
            "%-60s | %-12s | %-12s | %-12s | %-12s\n",
            "CAMPO",
            "CFDI33_EMIT",
            "CFDI33_RECIB",
            "CFDI40_EMIT",
            "CFDI40_RECIB"
        );
        $tabla .= $header;
        $tabla .= str_repeat("-", 120) . "\n";

        ksort($this->todosLosCampos);

        foreach ($this->todosLosCampos as $campo => $presencia) {
            $tabla .= sprintf(
                "%-60s | %-12s | %-12s | %-12s | %-12s\n",
                substr($campo, 0, 60),
                $presencia['cfdi_33_emitidos'] ? '✅' : '❌',
                $presencia['cfdi_33_recibidos'] ? '✅' : '❌',
                $presencia['cfdi_40_emitidos'] ? '✅' : '❌',
                $presencia['cfdi_40_recibidos'] ? '✅' : '❌'
            );
        }

        return $tabla;
    }

    private function construirDetallesComplementos()
    {
        $detalles = "\n\nDETALLES DE COMPLEMENTOS ENCONTRADOS:\n";
        $detalles .= str_repeat("=", 100) . "\n\n";

        foreach ($this->todoLosComplementos as $nombreComplemento => $info) {
            $detalles .= "📦 COMPLEMENTO: {$nombreComplemento}\n";
            $detalles .= "   Versión: " . ($info['version'] ?: 'No especificada') . "\n";
            $detalles .= "   Presente en:\n";

            foreach (['cfdi_33_emitidos', 'cfdi_33_recibidos', 'cfdi_40_emitidos', 'cfdi_40_recibidos'] as $categoria) {
                $estado = $info[$categoria] ? '✅' : '❌';
                $categoriaNombre = strtoupper(str_replace('_', ' ', $categoria));
                $detalles .= "      {$categoriaNombre}: {$estado}\n";
            }

            $detalles .= "   Campos (" . count($info['campos']) . "):\n";
            foreach (array_slice($info['campos'], 0, 10) as $campo) {
                $detalles .= "      - {$campo}\n";
            }

            if (count($info['campos']) > 10) {
                $detalles .= "      ... y " . (count($info['campos']) - 10) . " campos más\n";
            }

            $detalles .= "\n";
        }

        return $detalles;
    }

    private function construirRecomendacionesBD()
    {
        $recomendaciones = "\n\nRECOMENDACIONES PARA DISEÑO DE BASE DE DATOS:\n";
        $recomendaciones .= str_repeat("=", 100) . "\n\n";

        // Análisis de campos comunes vs específicos
        $camposComunes = [];
        $camposEspecificos = [];

        foreach ($this->todosLosCampos as $campo => $presencia) {
            $presente_en = array_sum([
                $presencia['cfdi_33_emitidos'],
                $presencia['cfdi_33_recibidos'],
                $presencia['cfdi_40_emitidos'],
                $presencia['cfdi_40_recibidos']
            ]);

            if ($presente_en >= 3) {
                $camposComunes[] = $campo;
            } else {
                $camposEspecificos[] = $campo;
            }
        }

        $recomendaciones .= "🎯 CAMPOS COMUNES (presentes en 3+ categorías): " . count($camposComunes) . "\n";
        $recomendaciones .= "⚠️  CAMPOS ESPECÍFICOS (presentes en 1-2 categorías): " . count($camposEspecificos) . "\n\n";

        $recomendaciones .= "📋 TABLAS RECOMENDADAS:\n\n";
        $recomendaciones .= "1. TABLA PRINCIPAL 'cfdi'\n";
        $recomendaciones .= "   - Campos comunes a todas las versiones\n";
        $recomendaciones .= "   - " . count($camposComunes) . " campos identificados\n\n";

        $recomendaciones .= "2. TABLAS POR COMPLEMENTO:\n";
        foreach ($this->todoLosComplementos as $complemento => $info) {
            $recomendaciones .= "   - cfdi_complemento_" . strtolower(str_replace([' ', '.'], '_', $complemento)) . " (" . count($info['campos']) . " campos)\n";
        }

        $recomendaciones .= "\n3. CAMPOS ESPECÍFICOS POR VERSIÓN:\n";
        $recomendaciones .= "   - Considerar campos adicionales en tabla principal con valores NULL permitidos\n";
        $recomendaciones .= "   - Total campos específicos: " . count($camposEspecificos) . "\n\n";

        return $recomendaciones;
    }
}

// Ejecutar análisis
$analizador = new AnalizadorExhaustivoXML();
$analizador->ejecutarAnalisisCompleto();
