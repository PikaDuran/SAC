<?php

/**
 * Test independiente para identificar y corregir el problema de complementos de pago
 * Este script analiza y corrige la extracción de complementos de pago
 */

require_once 'importador_inteligente_cfdi.php';

echo "=== ANÁLISIS Y CORRECCIÓN DE COMPLEMENTOS DE PAGO ===\n\n";

try {
    $importador = new ImportadorInteligenteCFDI();

    // Usar reflexión para acceder a métodos privados
    $reflection = new ReflectionClass($importador);
    $pdoProperty = $reflection->getProperty('pdo');
    $pdoProperty->setAccessible(true);
    $pdo = $pdoProperty->getValue($importador);

    echo "✅ Conexión a base de datos establecida\n\n";

    // 1. ANALIZAR CFDI TIPO P EXISTENTE
    echo "=== 1. ANÁLISIS DE CFDI TIPO P EXISTENTE ===\n";

    $stmt = $pdo->query("SELECT uuid, rfc_emisor, archivo_xml, complemento_tipo, complemento_json FROM cfdi WHERE tipo = 'P' LIMIT 1");
    $cfdi_pago = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cfdi_pago) {
        echo "UUID encontrado: " . $cfdi_pago['uuid'] . "\n";
        echo "RFC Emisor: " . $cfdi_pago['rfc_emisor'] . "\n";
        echo "Archivo XML: " . $cfdi_pago['archivo_xml'] . "\n";
        echo "Complemento Tipo: " . ($cfdi_pago['complemento_tipo'] ?: 'VACÍO') . "\n";
        echo "Complemento JSON: " . ($cfdi_pago['complemento_json'] ?: 'VACÍO') . "\n\n";

        // 2. BUSCAR EL ARCHIVO XML REAL
        echo "=== 2. BÚSQUEDA DEL ARCHIVO XML REAL ===\n";

        $uuid = $cfdi_pago['uuid'];
        $archivos_posibles = [];

        // Buscar en toda la estructura
        $patrones = [
            "storage/sat_downloads/*/EMITIDAS/*/*/*.xml",
            "storage/sat_downloads/*/RECIBIDAS/*/*/*.xml"
        ];

        foreach ($patrones as $patron) {
            $archivos = glob($patron);
            foreach ($archivos as $archivo) {
                if (strpos(basename($archivo), str_replace('-', '', $uuid)) !== false) {
                    $archivos_posibles[] = $archivo;
                }
            }
        }

        if (!empty($archivos_posibles)) {
            $archivo_real = $archivos_posibles[0];
            echo "✅ Archivo encontrado: $archivo_real\n";

            // 3. LEER Y ANALIZAR EL XML
            echo "\n=== 3. ANÁLISIS DEL CONTENIDO XML ===\n";

            $contenido_xml = file_get_contents($archivo_real);

            // Verificar si es realmente tipo P
            if (preg_match('/TipoDeComprobante\s*=\s*["\']P["\']/', $contenido_xml)) {
                echo "✅ Confirmado: Es un CFDI tipo P (complemento de pago)\n";

                // Buscar complemento de pagos con diferentes patrones
                $patrones_complemento = [
                    '/<pago10:Pagos[^>]*>.*?<\/pago10:Pagos>/s',
                    '/<pago20:Pagos[^>]*>.*?<\/pago20:Pagos>/s',
                    '/<pagos:Pagos[^>]*>.*?<\/pagos:Pagos>/s',
                    '/<cfdi:Complemento[^>]*>.*?<\/cfdi:Complemento>/s'
                ];

                $complemento_encontrado = false;
                foreach ($patrones_complemento as $i => $patron) {
                    if (preg_match($patron, $contenido_xml, $matches)) {
                        echo "✅ Complemento encontrado con patrón " . ($i + 1) . "\n";
                        echo "Longitud del complemento: " . strlen($matches[0]) . " caracteres\n";

                        // Mostrar una muestra del complemento
                        $muestra = substr($matches[0], 0, 200) . "...";
                        echo "Muestra: $muestra\n";

                        $complemento_encontrado = true;
                        break;
                    }
                }

                if (!$complemento_encontrado) {
                    echo "❌ No se encontró complemento de pagos con ningún patrón\n";
                    echo "Mostrando inicio del XML:\n";
                    echo substr($contenido_xml, 0, 500) . "...\n";
                }

                // 4. EXTRAER DATOS DE PAGO MANUALMENTE
                echo "\n=== 4. EXTRACCIÓN MANUAL DE DATOS DE PAGO ===\n";

                // Buscar datos básicos del pago
                $datos_pago = [];

                if (preg_match('/FechaPago\s*=\s*["\']([^"\']*)["\']/', $contenido_xml, $matches)) {
                    $datos_pago['fecha_pago'] = $matches[1];
                    echo "Fecha Pago: " . $matches[1] . "\n";
                }

                if (preg_match('/FormaDePagoP\s*=\s*["\']([^"\']*)["\']/', $contenido_xml, $matches)) {
                    $datos_pago['forma_pago'] = $matches[1];
                    echo "Forma de Pago: " . $matches[1] . "\n";
                }

                if (preg_match('/MonedaP\s*=\s*["\']([^"\']*)["\']/', $contenido_xml, $matches)) {
                    $datos_pago['moneda'] = $matches[1];
                    echo "Moneda: " . $matches[1] . "\n";
                }

                if (preg_match('/Monto\s*=\s*["\']([^"\']*)["\']/', $contenido_xml, $matches)) {
                    $datos_pago['monto'] = $matches[1];
                    echo "Monto: " . $matches[1] . "\n";
                }

                if (!empty($datos_pago)) {
                    echo "\n✅ Datos de pago extraídos exitosamente\n";

                    // 5. ACTUALIZAR REGISTRO EN BASE DE DATOS
                    echo "\n=== 5. ACTUALIZANDO REGISTRO EN BASE DE DATOS ===\n";

                    $complemento_json = json_encode($datos_pago, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                    $sql = "UPDATE cfdi SET 
                            complemento_tipo = 'Pagos',
                            complemento_json = ?,
                            archivo_xml = ?
                            WHERE uuid = ?";

                    $stmt = $pdo->prepare($sql);
                    $resultado = $stmt->execute([$complemento_json, $archivo_real, $uuid]);

                    if ($resultado) {
                        echo "✅ CFDI actualizado correctamente\n";
                        echo "Complemento Tipo: Pagos\n";
                        echo "Complemento JSON: $complemento_json\n";
                        echo "Archivo XML: $archivo_real\n";
                    } else {
                        echo "❌ Error al actualizar CFDI\n";
                    }
                } else {
                    echo "❌ No se pudieron extraer datos de pago\n";
                }
            } else {
                echo "❌ El archivo no es tipo P\n";
            }
        } else {
            echo "❌ No se encontró el archivo XML físico\n";
        }
    } else {
        echo "❌ No se encontraron CFDIs tipo P en la base de datos\n";
    }

    // 6. VERIFICAR RESULTADO
    echo "\n=== 6. VERIFICACIÓN FINAL ===\n";

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P' AND complemento_tipo IS NOT NULL AND complemento_tipo != ''");
    $result = $stmt->fetch();
    echo "CFDIs tipo P con complemento_tipo: " . $result['total'] . "\n";

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P' AND complemento_json IS NOT NULL AND complemento_json != ''");
    $result = $stmt->fetch();
    echo "CFDIs tipo P con complemento_json: " . $result['total'] . "\n";

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P' AND archivo_xml IS NOT NULL AND archivo_xml != '' AND archivo_xml != '[]'");
    $result = $stmt->fetch();
    echo "CFDIs tipo P con archivo_xml válido: " . $result['total'] . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
