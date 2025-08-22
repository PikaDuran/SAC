<?php
require_once 'procesador_cfdi_completo.php';

class DebugProcesador extends ProcesadorCFDICompleto
{

    public function debugInsertarPago($cfdi_id, $pago)
    {
        echo "=== DEBUG: Datos recibidos en insertarPago ===\n";
        echo "cfdi_id: " . var_export($cfdi_id, true) . "\n";
        echo "Datos del pago:\n";
        foreach ($pago as $key => $value) {
            echo "  $key: " . var_export($value, true) . "\n";
        }
        echo "\n";

        // Mostrar los parámetros que se enviarían a la BD
        $parametros = [
            $cfdi_id,
            $pago['version'] ?? null,
            $pago['fecha_pago'] ?? null,
            $pago['forma_pago'] ?? null,
            $pago['moneda'] ?? null,
            $pago['tipo_cambio'] ?? null,
            $pago['monto'] ?? null,
            $pago['num_operacion'] ?? null,
            $pago['rfc_emisor_cuenta_ordenante'] ?? null,
            $pago['nombre_banco_extranjero'] ?? null,
            $pago['cuenta_ordenante'] ?? null,
            $pago['rfc_emisor_cuenta_beneficiario'] ?? null,
            $pago['cuenta_beneficiario'] ?? null,
            $pago['tipo_cadena_pago'] ?? null,
            $pago['certificado_pago'] ?? null,
            $pago['cadena_pago'] ?? null,
            $pago['sello_pago'] ?? null
        ];

        echo "=== Parámetros que se enviarían a SQL ===\n";
        $campos = [
            'cfdi_id',
            'version',
            'fecha_pago',
            'forma_pago',
            'moneda',
            'tipo_cambio',
            'monto',
            'num_operacion',
            'rfc_emisor_cuenta_ordenante',
            'nombre_banco_extranjero',
            'cuenta_ordenante',
            'rfc_emisor_cuenta_beneficiario',
            'cuenta_beneficiario',
            'tipo_cadena_pago',
            'certificado_pago',
            'cadena_pago',
            'sello_pago'
        ];

        for ($i = 0; $i < count($parametros); $i++) {
            echo $campos[$i] . ": " . var_export($parametros[$i], true) . "\n";
        }
        echo "\n";

        return true; // No ejecutamos realmente
    }

    public function debugExtraerComplementoPagos($contenido)
    {
        $reflection = new ReflectionClass($this);
        $metodo = $reflection->getMethod('extraerComplementoPagos');
        $metodo->setAccessible(true);

        echo "=== DEBUG: Ejecutando extraerComplementoPagos ===\n";
        $resultado = $metodo->invoke($this, $contenido);

        echo "Resultado de extracción:\n";
        if ($resultado) {
            foreach ($resultado as $i => $pago) {
                echo "Pago #" . ($i + 1) . ":\n";
                foreach ($pago as $key => $value) {
                    echo "  $key: " . var_export($value, true) . "\n";
                }
                echo "\n";
            }
        } else {
            echo "NULL - No se extrajeron pagos\n";
        }

        return $resultado;
    }
}

// Configuración de base de datos ficticia
try {
    $pdo = new PDO('sqlite::memory:');
} catch (PDOException $e) {
    $pdo = null;
}

// Buscar un archivo XML tipo P
$directorios = [
    'storage/sat_downloads/BFM170822P38/EMITIDAS',
    'storage/sat_downloads/BFM170822P38/RECIBIDAS',
    'storage/sat_downloads/BLM1706026AA/emitidas',
    'storage/sat_downloads/BLM1706026AA/recibidas'
];

$archivoPrueba = null;
foreach ($directorios as $dir) {
    if (is_dir($dir)) {
        // Buscar recursivamente en subdirectorios (año/mes)
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $archivo) {
            if ($archivo->isFile() && strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) === 'xml') {
                $contenido = file_get_contents($archivo);
                if (preg_match('/TipoDeComprobante\s*=\s*["\']P["\']/', $contenido)) {
                    $archivoPrueba = $archivo->getPathname();
                    break 2;
                }
            }
        }
    }
}

if (!$archivoPrueba) {
    echo "❌ No se encontró ningún archivo XML tipo P\n";
    exit;
}

echo "📁 Archivo de prueba: " . basename($archivoPrueba) . "\n\n";
$contenido = file_get_contents($archivoPrueba);

$processor = new DebugProcesador($pdo);

// Debug extracción
$pagos = $processor->debugExtraerComplementoPagos($contenido);

if ($pagos) {
    echo "=== SIMULANDO INSERCIÓN ===\n";
    $cfdi_id_ficticio = 999999; // ID ficticio para debug

    foreach ($pagos as $i => $pago) {
        echo "\n--- Insertando pago #" . ($i + 1) . " ---\n";
        $resultado = $processor->debugInsertarPago($cfdi_id_ficticio, $pago);
        echo "Resultado: " . ($resultado ? "OK" : "ERROR") . "\n";
    }
} else {
    echo "❌ No hay pagos para insertar\n";
}
