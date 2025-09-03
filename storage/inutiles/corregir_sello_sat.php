<?php
require_once 'vendor/autoload.php';

// Configuración de base de datos
$host = 'localhost';
$dbname = 'sac_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "===========================================\n";
    echo "  CORRECCIÓN DE SELLO SAT FALTANTE\n";
    echo "===========================================\n\n";

    // 1. Verificar cuántos registros tienen sello_sat NULL
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi_timbre_fiscal_digital WHERE sello_sat IS NULL");
    $resultado = $stmt->fetch();
    $total_sin_sello = $resultado['total'];

    echo "📊 Registros sin SelloSAT: $total_sin_sello\n\n";

    if ($total_sin_sello == 0) {
        echo "✅ Todos los registros ya tienen SelloSAT\n";
        exit(0);
    }

    // 2. Función para buscar archivo XML por UUID
    function buscar_archivo_xml($uuid, $base_path = 'storage/sat_downloads')
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base_path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'xml') {
                if (strpos($file->getFilename(), $uuid) !== false) {
                    return $file->getPathname();
                }
            }
        }
        return null;
    }

    // 3. Función para extraer SelloSAT de XML
    function extraer_sello_sat($archivo_xml)
    {
        if (!file_exists($archivo_xml)) {
            return null;
        }

        $xml_content = file_get_contents($archivo_xml);
        $xml = new DOMDocument();
        libxml_use_internal_errors(true);

        if (!$xml->loadXML($xml_content)) {
            return null;
        }

        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

        $tfd = $xpath->query('//tfd:TimbreFiscalDigital')->item(0);
        if (!$tfd) {
            return null;
        }

        return $tfd->getAttribute('SelloSAT');
    }

    // 4. Procesar registros sin SelloSAT
    $stmt = $pdo->query("
        SELECT id, uuid 
        FROM cfdi_timbre_fiscal_digital 
        WHERE sello_sat IS NULL 
        LIMIT 500
    ");

    $procesados = 0;
    $actualizados = 0;
    $no_encontrados = 0;

    echo "🔄 Procesando registros...\n";
    echo "============================\n";

    while ($registro = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $uuid = $registro['uuid'];
        $id = $registro['id'];

        echo "📄 Procesando UUID: $uuid... ";

        // Buscar archivo XML
        $archivo_xml = buscar_archivo_xml($uuid);

        if (!$archivo_xml) {
            echo "❌ XML no encontrado\n";
            $no_encontrados++;
            $procesados++;
            continue;
        }

        // Extraer SelloSAT
        $sello_sat = extraer_sello_sat($archivo_xml);

        if (!$sello_sat) {
            echo "❌ SelloSAT no encontrado en XML\n";
            $no_encontrados++;
            $procesados++;
            continue;
        }

        // Actualizar registro
        try {
            $update_stmt = $pdo->prepare("
                UPDATE cfdi_timbre_fiscal_digital 
                SET sello_sat = ? 
                WHERE id = ?
            ");
            $update_stmt->execute([$sello_sat, $id]);

            echo "✅ Actualizado\n";
            $actualizados++;
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }

        $procesados++;

        // Pausa cada 10 registros para no sobrecargar
        if ($procesados % 10 == 0) {
            echo "   💤 Pausa... ($procesados procesados)\n";
            usleep(100000); // 0.1 segundo
        }
    }

    echo "\n📊 RESULTADOS FINALES:\n";
    echo "========================\n";
    echo "📁 Procesados: $procesados\n";
    echo "✅ Actualizados: $actualizados\n";
    echo "❌ No encontrados: $no_encontrados\n";

    // Verificar estado final
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi_timbre_fiscal_digital WHERE sello_sat IS NULL");
    $resultado = $stmt->fetch();
    $restantes = $resultado['total'];

    echo "🔄 Registros restantes sin SelloSAT: $restantes\n";

    if ($restantes > 0) {
        echo "\n💡 Para procesar los restantes, ejecuta el script nuevamente\n";
    } else {
        echo "\n🎉 ¡Todos los registros ya tienen SelloSAT!\n";
    }
} catch (Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
}
