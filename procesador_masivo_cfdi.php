<?php

/**
 * Procesador Masivo CFDI - Optimizado para grandes volúmenes
 * Procesa todos los XMLs en lotes de 500 con correcciones de pago
 */

require_once 'src/config/database.php';
require_once 'procesador_cfdi_completo.php';

class ProcesadorMasivoCFDI
{
    private $pdo;
    private $procesadorBase;
    private $loteSize = 500;
    private $stats = [
        'total_archivos' => 0,
        'procesados' => 0,
        'insertados' => 0,
        'errores' => 0,
        'duplicados' => 0,
        'cfdi_33' => 0,
        'cfdi_40' => 0,
        'pagos' => 0,
        'pagos_insertados' => 0,
        'conceptos' => 0,
        'impuestos' => 0,
        'timbres' => 0,
        'tiempo_inicio' => 0,
        'archivos_por_minuto' => 0
    ];

    private $directorios = [
        'storage/sat_downloads/BFM170822P38/EMITIDAS',
        'storage/sat_downloads/BFM170822P38/RECIBIDAS',
        'storage/sat_downloads/BLM1706026AA/emitidas',
        'storage/sat_downloads/BLM1706026AA/recibidas'
    ];

    public function __construct()
    {
        $this->pdo = getDatabase();
        $this->procesadorBase = new ProcesadorCFDICompleto($this->pdo);
        $this->stats['tiempo_inicio'] = time();
    }

    public function ejecutar($limpiarTablas = false, $soloNuevos = true)
    {
        echo "=== PROCESADOR MASIVO CFDI ===\n";
        echo "📅 Fecha: " . date('Y-m-d H:i:s') . "\n";
        echo "⚙️  Tamaño de lote: {$this->loteSize}\n";
        echo "🔄 Solo nuevos: " . ($soloNuevos ? 'SÍ' : 'NO') . "\n\n";

        if ($limpiarTablas) {
            $this->limpiarTablas();
        }

        // Obtener lista de archivos
        $archivos = $this->obtenerArchivosXML();
        $this->stats['total_archivos'] = count($archivos);

        echo "📁 Archivos encontrados: {$this->stats['total_archivos']}\n";

        if ($soloNuevos) {
            $archivos = $this->filtrarSoloNuevos($archivos);
            echo "📋 Archivos nuevos: " . count($archivos) . "\n";
        }

        if (empty($archivos)) {
            echo "✅ No hay archivos nuevos para procesar\n";
            return;
        }

        echo "\n🚀 Iniciando procesamiento...\n\n";

        // Procesar en lotes
        $lotes = array_chunk($archivos, $this->loteSize);
        $totalLotes = count($lotes);

        foreach ($lotes as $numeroLote => $lote) {
            $this->procesarLote($lote, $numeroLote + 1, $totalLotes);

            // Mostrar progreso cada lote
            $this->mostrarProgreso();

            // Pausa pequeña para no sobrecargar el sistema
            usleep(100000); // 0.1 segundos
        }

        echo "\n🎉 PROCESAMIENTO COMPLETADO\n";
        $this->mostrarEstadisticasFinales();
    }

    private function obtenerArchivosXML()
    {
        $archivos = [];

        foreach ($this->directorios as $directorio) {
            if (!is_dir($directorio)) {
                echo "⚠️  Directorio no encontrado: $directorio\n";
                continue;
            }

            echo "🔍 Escaneando: $directorio\n";

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directorio)
            );

            foreach ($iterator as $archivo) {
                if (
                    $archivo->isFile() &&
                    strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) === 'xml'
                ) {
                    $archivos[] = $archivo->getPathname();
                }
            }
        }

        return $archivos;
    }

    private function filtrarSoloNuevos($archivos)
    {
        echo "🔍 Filtrando archivos ya procesados...\n";

        $nuevos = [];
        $batchSize = 1000; // Consultar UUIDs en lotes
        $batches = array_chunk($archivos, $batchSize);

        foreach ($batches as $batch) {
            $uuids = [];

            // Extraer UUIDs del batch
            foreach ($batch as $archivo) {
                $contenido = file_get_contents($archivo);
                if (preg_match('/UUID="([^"]+)"/', $contenido, $matches)) {
                    $uuids[$archivo] = $matches[1];
                }
            }

            if (empty($uuids)) continue;

            // Consultar cuáles ya existen
            $placeholders = str_repeat('?,', count($uuids) - 1) . '?';
            $stmt = $this->pdo->prepare("SELECT uuid FROM cfdi WHERE uuid IN ($placeholders)");
            $stmt->execute(array_values($uuids));
            $existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Filtrar solo los nuevos
            foreach ($uuids as $archivo => $uuid) {
                if (!in_array($uuid, $existentes)) {
                    $nuevos[] = $archivo;
                }
            }
        }

        return $nuevos;
    }

    private function procesarLote($archivos, $numeroLote, $totalLotes)
    {
        echo "📦 Procesando lote $numeroLote/$totalLotes (" . count($archivos) . " archivos)\n";

        foreach ($archivos as $archivo) {
            try {
                $this->stats['procesados']++;

                if ($this->procesarArchivoIndividual($archivo)) {
                    $this->stats['insertados']++;
                } else {
                    $this->stats['errores']++;
                }
            } catch (Exception $e) {
                $this->stats['errores']++;
                echo "❌ Error en " . basename($archivo) . ": " . $e->getMessage() . "\n";
            }
        }
    }

    private function procesarArchivoIndividual($archivo)
    {
        $contenido = file_get_contents($archivo);

        if (empty($contenido)) {
            return false;
        }

        // Extraer UUID para verificar duplicados
        if (!preg_match('/UUID="([^"]+)"/', $contenido, $matches)) {
            return false;
        }

        $uuid = $matches[1];

        // Verificar si ya existe
        $stmt = $this->pdo->prepare("SELECT id FROM cfdi WHERE uuid = ?");
        $stmt->execute([$uuid]);
        if ($stmt->fetch()) {
            $this->stats['duplicados']++;
            return false;
        }

        // Detectar versión
        if (preg_match('/Version="4\.0"/', $contenido)) {
            $this->stats['cfdi_40']++;
        } else {
            $this->stats['cfdi_33']++;
        }

        // Usar reflexión para acceder al método privado del procesador base
        $reflection = new ReflectionClass($this->procesadorBase);
        $metodo = $reflection->getMethod('procesarArchivo');
        $metodo->setAccessible(true);

        try {
            $resultado = $metodo->invoke($this->procesadorBase, $archivo);

            // Obtener estadísticas del procesador base
            $statsBase = $reflection->getProperty('stats');
            $statsBase->setAccessible(true);
            $statsBaseDatos = $statsBase->getValue($this->procesadorBase);

            // Actualizar nuestras estadísticas
            if (isset($statsBaseDatos['pagos'])) {
                $this->stats['pagos'] += $statsBaseDatos['pagos'];
            }
            if (isset($statsBaseDatos['conceptos'])) {
                $this->stats['conceptos'] += $statsBaseDatos['conceptos'];
            }
            if (isset($statsBaseDatos['impuestos'])) {
                $this->stats['impuestos'] += $statsBaseDatos['impuestos'];
            }
            if (isset($statsBaseDatos['timbres'])) {
                $this->stats['timbres'] += $statsBaseDatos['timbres'];
            }

            return $resultado;
        } catch (Exception $e) {
            echo "❌ Error procesando " . basename($archivo) . ": " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function mostrarProgreso()
    {
        $tiempoTranscurrido = time() - $this->stats['tiempo_inicio'];
        $porcentaje = $this->stats['total_archivos'] > 0 ?
            ($this->stats['procesados'] / $this->stats['total_archivos']) * 100 : 0;

        if ($tiempoTranscurrido > 0) {
            $this->stats['archivos_por_minuto'] = round(($this->stats['procesados'] / $tiempoTranscurrido) * 60);
        }

        echo sprintf(
            "📊 Progreso: %d/%d (%.1f%%) | Insertados: %d | Errores: %d | Velocidad: %d arch/min\n",
            $this->stats['procesados'],
            $this->stats['total_archivos'],
            $porcentaje,
            $this->stats['insertados'],
            $this->stats['errores'],
            $this->stats['archivos_por_minuto']
        );
    }

    private function mostrarEstadisticasFinales()
    {
        $tiempoTotal = time() - $this->stats['tiempo_inicio'];
        $minutos = floor($tiempoTotal / 60);
        $segundos = $tiempoTotal % 60;

        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📋 ESTADÍSTICAS FINALES\n";
        echo str_repeat("=", 60) . "\n";
        echo "⏱️  Tiempo total: {$minutos}m {$segundos}s\n";
        echo "📁 Archivos totales: {$this->stats['total_archivos']}\n";
        echo "✅ Procesados: {$this->stats['procesados']}\n";
        echo "💾 Insertados: {$this->stats['insertados']}\n";
        echo "❌ Errores: {$this->stats['errores']}\n";
        echo "🔄 Duplicados: {$this->stats['duplicados']}\n";
        echo "📄 CFDI 3.3: {$this->stats['cfdi_33']}\n";
        echo "📄 CFDI 4.0: {$this->stats['cfdi_40']}\n";
        echo "💰 Pagos: {$this->stats['pagos']}\n";
        echo "📦 Conceptos: {$this->stats['conceptos']}\n";
        echo "💸 Impuestos: {$this->stats['impuestos']}\n";
        echo "🏷️  Timbres: {$this->stats['timbres']}\n";
        echo "⚡ Velocidad promedio: {$this->stats['archivos_por_minuto']} archivos/minuto\n";

        if ($this->stats['insertados'] > 0) {
            $tasaExito = ($this->stats['insertados'] / $this->stats['procesados']) * 100;
            echo sprintf("🎯 Tasa de éxito: %.2f%%\n", $tasaExito);
        }

        echo str_repeat("=", 60) . "\n";
    }

    private function limpiarTablas()
    {
        echo "🧹 Limpiando todas las tablas CFDI...\n";

        $tablas = [
            'cfdi_pago_documentos_relacionados',
            'cfdi_pagos',
            'cfdi_impuestos',
            'cfdi_conceptos',
            'cfdi_timbre_fiscal',
            'cfdi'
        ];

        foreach ($tablas as $tabla) {
            $this->pdo->exec("DELETE FROM $tabla");
            echo "  ✅ Tabla $tabla limpiada\n";
        }

        echo "🔄 Reiniciando AUTO_INCREMENT...\n";
        foreach ($tablas as $tabla) {
            $this->pdo->exec("ALTER TABLE $tabla AUTO_INCREMENT = 1");
        }

        echo "✅ Limpieza completada\n\n";
    }
}

// Ejecutar si se llama directamente
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $procesador = new ProcesadorMasivoCFDI();

    // Opciones de línea de comandos
    $limpiarTablas = in_array('--limpiar', $argv);
    $soloNuevos = !in_array('--todos', $argv);

    if ($limpiarTablas) {
        echo "⚠️  ADVERTENCIA: Se limpiarán TODAS las tablas CFDI\n";
        echo "Presiona ENTER para continuar o Ctrl+C para cancelar...\n";
        fgets(STDIN);
    }

    $procesador->ejecutar($limpiarTablas, $soloNuevos);
}
