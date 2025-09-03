<?php

/**
 * ANALIZADOR COMPLETO DE TODOS LOS XMLs CFDI
 * Analiza TODOS los campos de TODOS los XMLs tanto emitidos como recibidos
 * Considera la diferencia entre CFDI 3.3 (antes 2023) y CFDI 4.0 (2023+)
 * Genera mapeo completo de campos por versión y tipo
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
ini_set('memory_limit', '2G');

class AnalizadorCompletoXML
{

    private $baseDir;
    private $campos_cfdi33_emitidos = [];
    private $campos_cfdi33_recibidos = [];
    private $campos_cfdi40_emitidos = [];
    private $campos_cfdi40_recibidos = [];

    private $complementos_cfdi33_emitidos = [];
    private $complementos_cfdi33_recibidos = [];
    private $complementos_cfdi40_emitidos = [];
    private $complementos_cfdi40_recibidos = [];

    private $contadores = [
        'cfdi33_emitidos' => 0,
        'cfdi33_recibidos' => 0,
        'cfdi40_emitidos' => 0,
        'cfdi40_recibidos' => 0,
        'total_procesados' => 0,
        'errores' => 0
    ];

    public function __construct()
    {
        $this->baseDir = __DIR__ . '/storage/sat_downloads';
        echo "🔍 ANALIZADOR COMPLETO DE XMLs CFDI - TODAS LAS VERSIONES Y TIPOS\n";
        echo "===================================================================================\n";
        echo "📁 Directorio base: {$this->baseDir}\n";
        echo "📅 Criterio: CFDI 3.3 (antes 2023) | CFDI 4.0 (2023 en adelante)\n";
        echo "===================================================================================\n\n";
    }

    public function ejecutar()
    {
        if (!is_dir($this->baseDir)) {
            echo "❌ ERROR: No existe el directorio {$this->baseDir}\n";
            return;
        }

        $this->procesarDirectorioRFC();
        $this->generarReporte();
    }

    private function procesarDirectorioRFC()
    {
        $rfcDirs = glob($this->baseDir . '/*', GLOB_ONLYDIR);

        foreach ($rfcDirs as $rfcDir) {
            $rfcName = basename($rfcDir);
            echo "🏢 Procesando RFC: $rfcName\n";

            // Procesar EMITIDAS
            $emitidosDir = $rfcDir . '/EMITIDAS';
            if (is_dir($emitidosDir)) {
                echo "   📤 Analizando EMITIDAS...\n";
                $this->procesarDirectorioTipo($emitidosDir, 'emitidos');
            }

            // Procesar RECIBIDAS
            $recibidosDir = $rfcDir . '/RECIBIDAS';
            if (is_dir($recibidosDir)) {
                echo "   📥 Analizando RECIBIDAS...\n";
                $this->procesarDirectorioTipo($recibidosDir, 'recibidos');
            }
        }
    }

    private function procesarDirectorioTipo($baseDir, $tipo)
    {
        $years = glob($baseDir . '/*', GLOB_ONLYDIR);

        foreach ($years as $yearDir) {
            $year = basename($yearDir);
            $esCfdi40 = (int)$year >= 2023;

            echo "      📅 Año: $year (" . ($esCfdi40 ? 'CFDI 4.0' : 'CFDI 3.3') . ")\n";

            $months = glob($yearDir . '/*', GLOB_ONLYDIR);
            foreach ($months as $monthDir) {
                $month = basename($monthDir);
                $xmlFiles = glob($monthDir . '/*.xml');

                if (!empty($xmlFiles)) {
                    echo "         📆 Mes $month: " . count($xmlFiles) . " XMLs\n";

                    foreach ($xmlFiles as $xmlFile) {
                        $this->procesarXML($xmlFile, $tipo, $esCfdi40);
                    }
                }
            }
        }
    }

    private function procesarXML($xmlFile, $tipo, $esCfdi40)
    {
        try {
            // Suprimir warnings de XML mal formados
            libxml_use_internal_errors(true);
            $xml = simplexml_load_file($xmlFile);
            libxml_clear_errors();

            if (!$xml) {
                $this->contadores['errores']++;
                return;
            }

            $this->contadores['total_procesados']++;

            // Determinar el array de destino según versión y tipo
            $categoria = ($esCfdi40 ? 'cfdi40' : 'cfdi33') . '_' . $tipo;
            $this->contadores[$categoria]++;

            // Extraer todos los campos
            $campos = $this->extraerTodosLosCampos($xml);
            $complementos = $this->extraerComplementos($xml);

            // Mostrar progreso cada 1000 archivos
            if ($this->contadores['total_procesados'] % 1000 == 0) {
                echo "         📊 Procesados: " . $this->contadores['total_procesados'] . " XMLs\n";
            }

            // Guardar campos según categoría
            if ($esCfdi40 && $tipo === 'emitidos') {
                $this->campos_cfdi40_emitidos = array_merge($this->campos_cfdi40_emitidos, $campos);
                $this->complementos_cfdi40_emitidos = array_merge($this->complementos_cfdi40_emitidos, $complementos);
            } elseif ($esCfdi40 && $tipo === 'recibidos') {
                $this->campos_cfdi40_recibidos = array_merge($this->campos_cfdi40_recibidos, $campos);
                $this->complementos_cfdi40_recibidos = array_merge($this->complementos_cfdi40_recibidos, $complementos);
            } elseif (!$esCfdi40 && $tipo === 'emitidos') {
                $this->campos_cfdi33_emitidos = array_merge($this->campos_cfdi33_emitidos, $campos);
                $this->complementos_cfdi33_emitidos = array_merge($this->complementos_cfdi33_emitidos, $complementos);
            } else { // cfdi33 recibidos
                $this->campos_cfdi33_recibidos = array_merge($this->campos_cfdi33_recibidos, $campos);
                $this->complementos_cfdi33_recibidos = array_merge($this->complementos_cfdi33_recibidos, $complementos);
            }
        } catch (Exception $e) {
            $this->contadores['errores']++;
            // Solo mostrar errores críticos, no warnings de XML
            if ($this->contadores['errores'] % 100 == 0) {
                echo "         ⚠️  Errores acumulados: " . $this->contadores['errores'] . "\n";
            }
        }
    }

    private function extraerTodosLosCampos($xml)
    {
        $campos = [];
        $this->extraerCamposRecursivo($xml, '', $campos);
        return $campos;
    }

    private function extraerCamposRecursivo($elemento, $prefijo, &$campos)
    {
        // Extraer atributos del elemento actual
        foreach ($elemento->attributes() as $attr => $value) {
            $nombreCampo = $prefijo . ($prefijo ? '_' : '') . $attr;
            $campos[$nombreCampo] = (string)$value;
        }

        // Procesar elementos hijos
        foreach ($elemento->children() as $nombre => $hijo) {
            $nuevoPrefijo = $prefijo . ($prefijo ? '_' : '') . strtoupper($nombre);

            // Si tiene atributos, procesarlos
            if ($hijo->attributes()->count() > 0) {
                $this->extraerCamposRecursivo($hijo, $nuevoPrefijo, $campos);
            }

            // Si tiene texto, guardarlo
            $texto = trim((string)$hijo);
            if (!empty($texto) && !$hijo->children()->count()) {
                $campos[$nuevoPrefijo] = $texto;
            }

            // Procesar hijos recursivamente
            if ($hijo->children()->count() > 0) {
                $this->extraerCamposRecursivo($hijo, $nuevoPrefijo, $campos);
            }
        }
    }

    private function extraerComplementos($xml)
    {
        $complementos = [];

        // Buscar nodo Complemento
        if (isset($xml->Complemento)) {
            foreach ($xml->Complemento->children() as $complemento) {
                $nombre = $complemento->getName();
                $namespace = $complemento->getNamespaces(true);

                $info = [
                    'nombre' => $nombre,
                    'namespace' => $namespace,
                    'atributos' => []
                ];

                // Extraer atributos del complemento
                foreach ($complemento->attributes() as $attr => $value) {
                    $info['atributos'][$attr] = (string)$value;
                }

                // Extraer atributos de espacios de nombres
                foreach ($namespace as $prefix => $uri) {
                    foreach ($complemento->attributes($uri) as $attr => $value) {
                        $info['atributos'][$prefix . ':' . $attr] = (string)$value;
                    }
                }

                $complementos[] = $info;
            }
        }

        return $complementos;
    }

    private function generarReporte()
    {
        $timestamp = date('Y-m-d_H-i-s');
        $nombreArchivo = "ANALISIS_COMPLETO_TODOS_XML_{$timestamp}.txt";

        $reporte = $this->construirReporte();
        file_put_contents($nombreArchivo, $reporte);

        echo "\n" . $reporte;
        echo "\n💾 REPORTE COMPLETO GUARDADO EN: $nombreArchivo\n";
    }

    private function construirReporte()
    {
        $reporte = "ANÁLISIS COMPLETO DE TODOS LOS XMLs CFDI\n";
        $reporte .= "Generado: " . date('Y-m-d H:i:s') . "\n";
        $reporte .= "================================================================================\n\n";

        // Estadísticas generales
        $reporte .= "📊 ESTADÍSTICAS GENERALES:\n";
        $reporte .= "Total XMLs procesados: " . $this->contadores['total_procesados'] . "\n";
        $reporte .= "Errores encontrados: " . $this->contadores['errores'] . "\n\n";

        $reporte .= "📈 DISTRIBUCIÓN POR CATEGORÍA:\n";
        $reporte .= "• CFDI 3.3 EMITIDOS: " . $this->contadores['cfdi33_emitidos'] . "\n";
        $reporte .= "• CFDI 3.3 RECIBIDOS: " . $this->contadores['cfdi33_recibidos'] . "\n";
        $reporte .= "• CFDI 4.0 EMITIDOS: " . $this->contadores['cfdi40_emitidos'] . "\n";
        $reporte .= "• CFDI 4.0 RECIBIDOS: " . $this->contadores['cfdi40_recibidos'] . "\n\n";

        // Campos únicos por categoría
        $campos_unicos_33_emit = array_unique(array_keys($this->campos_cfdi33_emitidos));
        $campos_unicos_33_recv = array_unique(array_keys($this->campos_cfdi33_recibidos));
        $campos_unicos_40_emit = array_unique(array_keys($this->campos_cfdi40_emitidos));
        $campos_unicos_40_recv = array_unique(array_keys($this->campos_cfdi40_recibidos));

        $reporte .= "🔢 CAMPOS ÚNICOS IDENTIFICADOS:\n";
        $reporte .= "• CFDI 3.3 EMITIDOS: " . count($campos_unicos_33_emit) . " campos\n";
        $reporte .= "• CFDI 3.3 RECIBIDOS: " . count($campos_unicos_33_recv) . " campos\n";
        $reporte .= "• CFDI 4.0 EMITIDOS: " . count($campos_unicos_40_emit) . " campos\n";
        $reporte .= "• CFDI 4.0 RECIBIDOS: " . count($campos_unicos_40_recv) . " campos\n\n";

        // Tabla comparativa de campos
        $todos_los_campos = array_unique(array_merge(
            $campos_unicos_33_emit,
            $campos_unicos_33_recv,
            $campos_unicos_40_emit,
            $campos_unicos_40_recv
        ));
        sort($todos_los_campos);

        $reporte .= "================================================================================\n";
        $reporte .= "📋 TABLA COMPARATIVA COMPLETA DE CAMPOS\n";
        $reporte .= "================================================================================\n\n";

        $reporte .= sprintf(
            "%-60s | %-12s | %-12s | %-12s | %-12s\n",
            "CAMPO",
            "3.3 EMIT",
            "3.3 RECV",
            "4.0 EMIT",
            "4.0 RECV"
        );
        $reporte .= str_repeat("-", 120) . "\n";

        foreach ($todos_los_campos as $campo) {
            $en_33_emit = in_array($campo, $campos_unicos_33_emit) ? "✅" : "❌";
            $en_33_recv = in_array($campo, $campos_unicos_33_recv) ? "✅" : "❌";
            $en_40_emit = in_array($campo, $campos_unicos_40_emit) ? "✅" : "❌";
            $en_40_recv = in_array($campo, $campos_unicos_40_recv) ? "✅" : "❌";

            $reporte .= sprintf(
                "%-60s | %-12s | %-12s | %-12s | %-12s\n",
                $campo,
                $en_33_emit,
                $en_33_recv,
                $en_40_emit,
                $en_40_recv
            );
        }

        // Complementos encontrados
        $reporte .= "\n================================================================================\n";
        $reporte .= "📦 COMPLEMENTOS ENCONTRADOS POR CATEGORÍA\n";
        $reporte .= "================================================================================\n\n";

        $reporte .= $this->reportarComplementos("CFDI 3.3 EMITIDOS", $this->complementos_cfdi33_emitidos);
        $reporte .= $this->reportarComplementos("CFDI 3.3 RECIBIDOS", $this->complementos_cfdi33_recibidos);
        $reporte .= $this->reportarComplementos("CFDI 4.0 EMITIDOS", $this->complementos_cfdi40_emitidos);
        $reporte .= $this->reportarComplementos("CFDI 4.0 RECIBIDOS", $this->complementos_cfdi40_recibidos);

        $reporte .= "\n✅ ANÁLISIS COMPLETO FINALIZADO\n";
        $reporte .= "Total de campos únicos en todo el sistema: " . count($todos_los_campos) . "\n";

        return $reporte;
    }

    private function reportarComplementos($categoria, $complementos)
    {
        $reporte = "🔹 $categoria:\n";

        $complementos_unicos = [];
        foreach ($complementos as $comp) {
            $nombre = $comp['nombre'];
            if (!isset($complementos_unicos[$nombre])) {
                $complementos_unicos[$nombre] = [
                    'nombre' => $nombre,
                    'count' => 0,
                    'atributos' => []
                ];
            }
            $complementos_unicos[$nombre]['count']++;
            $complementos_unicos[$nombre]['atributos'] = array_merge(
                $complementos_unicos[$nombre]['atributos'],
                array_keys($comp['atributos'])
            );
        }

        if (empty($complementos_unicos)) {
            $reporte .= "   ❌ No se encontraron complementos\n\n";
        } else {
            foreach ($complementos_unicos as $comp) {
                $atributos_unicos = array_unique($comp['atributos']);
                $reporte .= "   📦 {$comp['nombre']} (aparece {$comp['count']} veces)\n";
                $reporte .= "      Atributos: " . implode(", ", $atributos_unicos) . "\n";
            }
            $reporte .= "\n";
        }

        return $reporte;
    }
}

// Ejecutar el análisis
$analizador = new AnalizadorCompletoXML();
$analizador->ejecutar();
