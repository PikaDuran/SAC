<?php

/**
 * ANALIZADOR DE PATRONES PARA DISEÑO DE BASE DE DATOS
 * ===================================================
 * 
 * Analiza el reporte exhaustivo para categorizar campos y crear
 * una estructura de BD optimizada
 */

class AnalizadorPatronesBD
{
    private $campos = [];
    private $complementos = [];
    private $patrones = [];

    public function __construct()
    {
        $this->cargarReporteExhaustivo();
    }

    private function cargarReporteExhaustivo()
    {
        $archivo = 'ANALISIS_EXHAUSTIVO_BD_2025-08-27_00-33-56.txt';
        if (!file_exists($archivo)) {
            throw new Exception("Archivo de análisis exhaustivo no encontrado");
        }

        $contenido = file_get_contents($archivo);
        $this->extraerCampos($contenido);
        $this->extraerComplementos($contenido);
    }

    private function extraerCampos($contenido)
    {
        // Extraer tabla de campos
        preg_match('/TABLA COMPARATIVA COMPLETA DE CAMPOS:(.*?)DETALLES DE COMPLEMENTOS/s', $contenido, $matches);
        if (!isset($matches[1])) return;

        $lineas = explode("\n", $matches[1]);
        foreach ($lineas as $linea) {
            if (preg_match('/^([^|]+)\s*\|\s*([^|]+)\s*\|\s*([^|]+)\s*\|\s*([^|]+)\s*\|\s*([^|]+)/', $linea, $match)) {
                $campo = trim($match[1]);
                if ($campo && $campo !== 'CAMPO' && !str_contains($campo, '-')) {
                    $this->campos[$campo] = [
                        'cfdi_33_emitidos' => trim($match[2]) === '✅',
                        'cfdi_33_recibidos' => trim($match[3]) === '✅',
                        'cfdi_40_emitidos' => trim($match[4]) === '✅',
                        'cfdi_40_recibidos' => trim($match[5]) === '✅'
                    ];
                }
            }
        }
    }

    private function extraerComplementos($contenido)
    {
        // Extraer información de complementos
        preg_match('/DETALLES DE COMPLEMENTOS ENCONTRADOS:(.*?)RECOMENDACIONES PARA DISEÑO/s', $contenido, $matches);
        if (!isset($matches[1])) return;

        $bloques = explode('📦 COMPLEMENTO:', $matches[1]);
        foreach ($bloques as $bloque) {
            if (trim($bloque)) {
                $this->procesarBloqueComplemento($bloque);
            }
        }
    }

    private function procesarBloqueComplemento($bloque)
    {
        $lineas = explode("\n", $bloque);
        $nombre = '';
        $version = '';
        $presencia = [];
        $campos = [];

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (preg_match('/^([^:]+)/', $linea, $match)) {
                $nombre = trim($match[1]);
            } elseif (str_contains($linea, 'Versión:')) {
                $version = str_replace('Versión:', '', $linea);
                $version = trim(str_replace('No especificada', '', $version));
            } elseif (preg_match('/CFDI \d+ (EMITIDOS|RECIBIDOS): ([✅❌])/', $linea, $match)) {
                $categoria = strtolower(str_replace(' ', '_', $match[1]));
                $presencia['cfdi_33_' . $categoria] = $match[2] === '✅';
            } elseif (str_contains($linea, 'COMPLEMENTO_')) {
                $campos[] = $linea;
            }
        }

        if ($nombre) {
            $this->complementos[$nombre] = [
                'version' => $version,
                'presencia' => $presencia,
                'campos' => $campos
            ];
        }
    }

    public function analizarPatrones()
    {
        echo "🔍 ANÁLISIS DE PATRONES PARA DISEÑO DE BASE DE DATOS\n";
        echo str_repeat("=", 80) . "\n\n";

        $this->categorizarCampos();
        $this->analizarComplementos();
        $this->generarEstructuraBD();

        return $this->patrones;
    }

    private function categorizarCampos()
    {
        echo "📊 CATEGORIZANDO CAMPOS...\n";

        $categorias = [
            'comprobante' => [],
            'emisor' => [],
            'receptor' => [],
            'conceptos' => [],
            'impuestos' => [],
            'complementos' => [],
            'otros' => []
        ];

        foreach ($this->campos as $campo => $presencia) {
            $categoria = $this->determinarCategoria($campo);
            $categorias[$categoria][] = $campo;
        }

        foreach ($categorias as $categoria => $campos) {
            echo "   📁 " . strtoupper($categoria) . ": " . count($campos) . " campos\n";
            $this->patrones['categorias'][$categoria] = $campos;
        }

        echo "\n";
    }

    private function determinarCategoria($campo)
    {
        $campo_lower = strtolower($campo);

        if (str_contains($campo_lower, 'comprobante') && !str_contains($campo_lower, 'complemento')) {
            return 'comprobante';
        } elseif (str_contains($campo_lower, 'emisor')) {
            return 'emisor';
        } elseif (str_contains($campo_lower, 'receptor')) {
            return 'receptor';
        } elseif (str_contains($campo_lower, 'concepto')) {
            return 'conceptos';
        } elseif (str_contains($campo_lower, 'impuesto') || str_contains($campo_lower, 'traslado') || str_contains($campo_lower, 'retencion')) {
            return 'impuestos';
        } elseif (str_contains($campo_lower, 'complemento')) {
            return 'complementos';
        } else {
            return 'otros';
        }
    }

    private function analizarComplementos()
    {
        echo "📦 ANALIZANDO COMPLEMENTOS...\n";

        foreach ($this->complementos as $nombre => $info) {
            $presenciaTotal = array_sum($info['presencia']);
            echo "   🔹 {$nombre}: {$presenciaTotal} contextos, " . count($info['campos']) . " campos\n";

            $this->patrones['complementos'][$nombre] = [
                'contextos' => $presenciaTotal,
                'campos' => count($info['campos']),
                'version' => $info['version'],
                'presencia' => $info['presencia']
            ];
        }

        echo "\n";
    }

    private function generarEstructuraBD()
    {
        echo "🏗️  GENERANDO ESTRUCTURA DE BASE DE DATOS...\n\n";

        // Tabla principal CFDI
        $this->generarTablaPrincipal();

        // Tablas de relación
        $this->generarTablasRelacion();

        // Tablas de complementos
        $this->generarTablasComplementos();

        // Índices y relaciones
        $this->generarIndicesRelaciones();
    }

    private function generarTablaPrincipal()
    {
        echo "1️⃣  TABLA PRINCIPAL 'cfdi':\n";

        $camposComunes = [];
        $camposOpcionales = [];

        // Analizar campos del comprobante
        foreach ($this->patrones['categorias']['comprobante'] as $campo) {
            $presencia = $this->campos[$campo];
            $total = array_sum($presencia);

            if ($total >= 2) { // Presente en al menos 2 contextos
                $camposComunes[] = $this->limpiarNombreCampo($campo);
            } else {
                $camposOpcionales[] = $this->limpiarNombreCampo($campo);
            }
        }

        // Agregar campos básicos de emisor y receptor
        foreach (['emisor', 'receptor'] as $entidad) {
            foreach ($this->patrones['categorias'][$entidad] as $campo) {
                $camposComunes[] = $this->limpiarNombreCampo($campo);
            }
        }

        echo "   ✅ Campos comunes: " . count($camposComunes) . "\n";
        echo "   ⚠️  Campos opcionales: " . count($camposOpcionales) . "\n";

        $this->patrones['tabla_principal'] = [
            'nombre' => 'cfdi',
            'campos_comunes' => $camposComunes,
            'campos_opcionales' => $camposOpcionales
        ];

        echo "\n";
    }

    private function generarTablasRelacion()
    {
        echo "2️⃣  TABLAS DE RELACIÓN:\n";

        $tablas = [
            'cfdi_conceptos' => $this->patrones['categorias']['conceptos'],
            'cfdi_impuestos' => $this->patrones['categorias']['impuestos']
        ];

        foreach ($tablas as $tabla => $campos) {
            if (!empty($campos)) {
                echo "   📋 {$tabla}: " . count($campos) . " campos\n";
                $this->patrones['tablas_relacion'][$tabla] = array_map([$this, 'limpiarNombreCampo'], $campos);
            }
        }

        echo "\n";
    }

    private function generarTablasComplementos()
    {
        echo "3️⃣  TABLAS DE COMPLEMENTOS:\n";

        foreach ($this->complementos as $nombre => $info) {
            $nombreTabla = 'cfdi_complemento_' . $this->limpiarNombreComplemento($nombre);
            echo "   📦 {$nombreTabla}: " . count($info['campos']) . " campos\n";

            $this->patrones['tablas_complementos'][$nombreTabla] = [
                'campos' => count($info['campos']),
                'version' => $info['version'],
                'presencia' => $info['presencia']
            ];
        }

        echo "\n";
    }

    private function generarIndicesRelaciones()
    {
        echo "4️⃣  ÍNDICES Y RELACIONES:\n";
        echo "   🔗 Llave primaria: id (AUTO_INCREMENT)\n";
        echo "   🔗 Índice único: uuid (TimbreFiscalDigital)\n";
        echo "   🔗 Índices: rfc_emisor, rfc_receptor, fecha, tipo_comprobante\n";
        echo "   🔗 Relaciones: FK a tablas de conceptos, impuestos y complementos\n\n";
    }

    private function limpiarNombreCampo($campo)
    {
        // Remover prefijos largos y limpiar nombre
        $campo = preg_replace('/^[A-Z:]+_/', '', $campo);
        $campo = strtolower($campo);
        $campo = preg_replace('/[^a-z0-9_]/', '_', $campo);
        $campo = preg_replace('/_+/', '_', $campo);
        return trim($campo, '_');
    }

    private function limpiarNombreComplemento($nombre)
    {
        $nombre = strtolower($nombre);
        $nombre = preg_replace('/[^a-z0-9]/', '_', $nombre);
        $nombre = preg_replace('/_+/', '_', $nombre);
        return trim($nombre, '_');
    }

    public function guardarAnalisis()
    {
        $fecha = date('Y-m-d_H-i-s');
        $archivo = "PATRONES_BD_{$fecha}.json";

        file_put_contents($archivo, json_encode($this->patrones, JSON_PRETTY_PRINT));

        echo "💾 Análisis de patrones guardado en: {$archivo}\n";
        return $archivo;
    }
}

// Ejecutar análisis
try {
    $analizador = new AnalizadorPatronesBD();
    $patrones = $analizador->analizarPatrones();
    $archivo = $analizador->guardarAnalisis();

    echo "✅ ANÁLISIS DE PATRONES COMPLETADO\n";
    echo "📊 Total categorías identificadas: " . count($patrones['categorias']) . "\n";
    echo "📦 Total complementos analizados: " . count($patrones['complementos']) . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
