<?php
// Test con acceso directo a la conexión PDO del importador
require_once 'importador_inteligente_cfdi.php';

try {
    echo "=== TEST CON ACCESO DIRECTO A PDO DEL IMPORTADOR ===\n\n";

    $importador = new ImportadorInteligenteCFDI();

    // Usar reflexión para acceder a la conexión PDO privada del importador
    $reflection = new ReflectionClass($importador);
    $pdoProperty = $reflection->getProperty('pdo');
    $pdoProperty->setAccessible(true);
    $pdo = $pdoProperty->getValue($importador);

    echo "✅ Acceso a PDO del importador obtenido\n";

    // Iniciar transacción en el PDO del importador
    $pdo->beginTransaction();
    echo "🔄 Transacción iniciada en PDO del importador\n\n";

    // Buscar archivos del 2025
    $directorio2025 = 'storage/sat_downloads/BFM170822P38/EMITIDAS/2025';
    $archivos = glob("$directorio2025/**/*.xml");

    echo "📁 Archivos encontrados: " . count($archivos) . "\n";
    echo "🎯 Procesando los primeros 3 archivos...\n\n";

    $exitosos = 0;
    $errores = 0;

    for ($i = 0; $i < min(3, count($archivos)); $i++) {
        $archivo = $archivos[$i];
        $nombreArchivo = basename($archivo);

        echo "--- ARCHIVO " . ($i + 1) . " ---\n";
        echo "📁 $nombreArchivo\n";

        try {
            // Leer y verificar versión
            $contenido = file_get_contents($archivo);
            if (preg_match('/Version="([^"]+)"/', $contenido, $matches)) {
                $version = $matches[1];
                echo "📋 Versión: $version\n";

                if ($version != '4.0') {
                    echo "⚠️ No es CFDI 4.0, saltando...\n\n";
                    continue;
                }
            }

            // Procesar archivo usando reflexión
            $metodoProcesar = $reflection->getMethod('procesarArchivo');
            $metodoProcesar->setAccessible(true);

            $resultado = $metodoProcesar->invoke($importador, $archivo);

            echo "✅ Archivo procesado\n";
            $exitosos++;
        } catch (Exception $e) {
            echo "❌ EXCEPCIÓN: " . $e->getMessage() . "\n";
            $errores++;
        }

        echo "\n";
    }

    // HACER COMMIT EN EL PDO DEL IMPORTADOR
    echo "🔄 Haciendo commit en PDO del importador...\n";
    $pdo->commit();
    echo "✅ Commit realizado en PDO del importador\n\n";

    echo "============================================================\n";
    echo "RESULTADOS:\n";
    echo "Archivos procesados: " . ($exitosos + $errores) . "\n";
    echo "Éxitos: $exitosos\n";
    echo "Errores: $errores\n";
    echo "============================================================\n";

    // AHORA verificar con una nueva conexión PDO para confirmar que los datos están persistidos
    $nuevoPdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $stmt = $nuevoPdo->query('SELECT COUNT(*) as total FROM cfdi WHERE version = "4.0"');
    $total_40 = $stmt->fetchColumn();

    echo "\n🎯 Total CFDI 4.0 en BD después del commit (nueva conexión): $total_40\n";

    if ($total_40 > 0) {
        echo "🎉 ¡ÉXITO! Los CFDI 4.0 se insertaron correctamente.\n\n";

        // Mostrar ejemplos
        echo "📋 EJEMPLOS DE CFDI 4.0 INSERTADOS:\n";
        $stmt = $nuevoPdo->query('SELECT uuid, version, exportacion, regimen_fiscal_receptor, fecha FROM cfdi WHERE version = "4.0" LIMIT 5');
        $ejemplos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ejemplos as $i => $e) {
            echo "\n--- CFDI 4.0 #" . ($i + 1) . " ---\n";
            echo "UUID: {$e['uuid']}\n";
            echo "Versión: {$e['version']}\n";
            echo "Fecha: {$e['fecha']}\n";
            echo "Exportación: " . ($e['exportacion'] ?? 'NULL') . "\n";
            echo "Régimen Fiscal Receptor: " . ($e['regimen_fiscal_receptor'] ?? 'NULL') . "\n";
        }
    } else {
        echo "❌ Los CFDI 4.0 no se persistieron correctamente\n";
    }
} catch (Exception $e) {
    echo "Error general: " . $e->getMessage() . "\n";
    if (isset($pdo)) {
        $pdo->rollback();
    }
}
