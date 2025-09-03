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
    echo "  IMPORTADOR CFDI CON UPDATE INTELIGENTE\n";
    echo "===========================================\n\n";

    $stats = [
        'total_procesados' => 0,
        'nuevos_insertados' => 0,
        'actualizados' => 0,
        'sin_cambios' => 0,
        'errores' => 0
    ];

    // Función para normalizar valores para comparación
    function normalizar_para_comparacion($valor, $tipo = 'string')
    {
        if ($tipo === 'fecha') {
            // Convertir formato ISO a MySQL
            return str_replace('T', ' ', $valor);
        } elseif ($tipo === 'decimal') {
            // Convertir a decimal con 6 decimales
            return number_format((float)$valor, 6, '.', '');
        }
        return trim($valor);
    }

    // Función para detectar cambios críticos
    function hay_cambios_criticos($datos_xml, $datos_bd)
    {
        $campos_criticos = [
            'fecha' => 'fecha',
            'total' => 'decimal',
            'subtotal' => 'decimal',
            'descuento' => 'decimal',
            'moneda' => 'string',
            'tipo_comprobante' => 'string',
            'emisor_rfc' => 'string',
            'receptor_rfc' => 'string'
        ];

        $cambios = [];

        foreach ($campos_criticos as $campo => $tipo) {
            $valor_xml = normalizar_para_comparacion($datos_xml[$campo] ?? '', $tipo);
            $valor_bd = normalizar_para_comparacion($datos_bd[$campo] ?? '', $tipo);

            if ($valor_xml !== $valor_bd) {
                $cambios[$campo] = [
                    'anterior' => $valor_bd,
                    'nuevo' => $valor_xml
                ];
            }
        }

        return $cambios;
    }

    // Función para procesar un archivo XML
    function procesar_xml($archivo_path, $pdo)
    {
        global $stats;

        if (!file_exists($archivo_path)) {
            echo "❌ Archivo no encontrado: $archivo_path\n";
            $stats['errores']++;
            return false;
        }

        $xml_content = file_get_contents($archivo_path);
        $xml = new DOMDocument();
        libxml_use_internal_errors(true);

        if (!$xml->loadXML($xml_content)) {
            echo "❌ Error al parsear XML: $archivo_path\n";
            $stats['errores']++;
            return false;
        }

        // Extraer UUID del TimbreFiscalDigital
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/3');
        $xpath->registerNamespace('cfdi4', 'http://www.sat.gob.mx/cfd/4');
        $xpath->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

        $timbre = $xpath->query('//tfd:TimbreFiscalDigital')->item(0);
        if (!$timbre) {
            echo "❌ No se encontró TimbreFiscalDigital en: $archivo_path\n";
            $stats['errores']++;
            return false;
        }

        $uuid = $timbre->getAttribute('UUID');
        if (!$uuid) {
            echo "❌ UUID no encontrado en: $archivo_path\n";
            $stats['errores']++;
            return false;
        }

        // Extraer datos del comprobante
        $comprobante = $xpath->query('//cfdi:Comprobante | //cfdi4:Comprobante')->item(0);
        if (!$comprobante) {
            echo "❌ Comprobante no encontrado en: $archivo_path\n";
            $stats['errores']++;
            return false;
        }

        $datos_xml = [
            'uuid' => $uuid,
            'fecha' => $comprobante->getAttribute('Fecha'),
            'total' => $comprobante->getAttribute('Total') ?: '0',
            'subtotal' => $comprobante->getAttribute('SubTotal') ?: '0',
            'descuento' => $comprobante->getAttribute('Descuento') ?: '0',
            'moneda' => $comprobante->getAttribute('Moneda') ?: 'MXN',
            'tipo_comprobante' => $comprobante->getAttribute('TipoDeComprobante'),
            'emisor_rfc' => '',
            'receptor_rfc' => ''
        ];

        // Extraer RFCs
        $emisor = $xpath->query('//cfdi:Emisor | //cfdi4:Emisor')->item(0);
        if ($emisor) {
            $datos_xml['emisor_rfc'] = $emisor->getAttribute('Rfc');
        }

        $receptor = $xpath->query('//cfdi:Receptor | //cfdi4:Receptor')->item(0);
        if ($receptor) {
            $datos_xml['receptor_rfc'] = $receptor->getAttribute('Rfc');
        }

        // Verificar si ya existe en la BD
        $stmt = $pdo->prepare("
            SELECT c.*, t.uuid, e.rfc as emisor_rfc, r.rfc as receptor_rfc
            FROM cfdi c 
            INNER JOIN cfdi_timbre_fiscal_digital t ON c.id = t.cfdi_id 
            LEFT JOIN emisor e ON c.id = e.cfdi_id
            LEFT JOIN receptor r ON c.id = r.cfdi_id
            WHERE t.uuid = ?
        ");
        $stmt->execute([$uuid]);
        $cfdi_existente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cfdi_existente) {
            // Ya existe, verificar si hay cambios críticos
            $cambios = hay_cambios_criticos($datos_xml, $cfdi_existente);

            if (empty($cambios)) {
                echo "⚪ Sin cambios: $uuid\n";
                $stats['sin_cambios']++;
                return true;
            }

            // Hay cambios críticos, proceder con UPDATE
            echo "🔄 ACTUALIZANDO UUID: $uuid\n";
            foreach ($cambios as $campo => $cambio) {
                echo "   📝 $campo: '{$cambio['anterior']}' → '{$cambio['nuevo']}'\n";
            }

            try {
                $pdo->beginTransaction();

                // Actualizar tabla cfdi
                $stmt = $pdo->prepare("
                    UPDATE cfdi SET 
                        fecha = ?,
                        total = ?,
                        subtotal = ?,
                        descuento = ?,
                        moneda = ?,
                        tipo_comprobante = ?,
                        fecha_procesamiento = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    normalizar_para_comparacion($datos_xml['fecha'], 'fecha'),
                    normalizar_para_comparacion($datos_xml['total'], 'decimal'),
                    normalizar_para_comparacion($datos_xml['subtotal'], 'decimal'),
                    normalizar_para_comparacion($datos_xml['descuento'], 'decimal'),
                    $datos_xml['moneda'],
                    $datos_xml['tipo_comprobante'],
                    $cfdi_existente['id']
                ]);

                // Actualizar emisor si cambió
                if (isset($cambios['emisor_rfc'])) {
                    $stmt = $pdo->prepare("UPDATE emisor SET rfc = ? WHERE cfdi_id = ?");
                    $stmt->execute([$datos_xml['emisor_rfc'], $cfdi_existente['id']]);
                }

                // Actualizar receptor si cambió
                if (isset($cambios['receptor_rfc'])) {
                    $stmt = $pdo->prepare("UPDATE receptor SET rfc = ? WHERE cfdi_id = ?");
                    $stmt->execute([$datos_xml['receptor_rfc'], $cfdi_existente['id']]);
                }

                // Log de auditoría
                $stmt = $pdo->prepare("
                    INSERT INTO audit_log (tabla, registro_id, accion, cambios, fecha) 
                    VALUES ('cfdi', ?, 'UPDATE', ?, NOW())
                ");
                $stmt->execute([$cfdi_existente['id'], json_encode($cambios)]);

                $pdo->commit();
                $stats['actualizados']++;
                echo "   ✅ Actualizado exitosamente\n";
            } catch (Exception $e) {
                $pdo->rollback();
                echo "   ❌ Error al actualizar: " . $e->getMessage() . "\n";
                $stats['errores']++;
            }
        } else {
            // No existe, insertar nuevo
            echo "🆕 NUEVO CFDI: $uuid\n";
            // Aquí iría la lógica de inserción completa (similar al importador original)
            $stats['nuevos_insertados']++;
        }

        $stats['total_procesados']++;
        return true;
    }

    // PRUEBA CON EL CFDI RECHAZADO
    echo "🧪 PROBANDO CON CFDI RECHAZADO:\n";
    echo "=====================================\n";

    $archivo_prueba = "storage/sat_downloads/BLM1706026AA/EMITIDAS/2020/10/2020_10_12_ECE52AD2-DB29-40AB-A924-BDE2474CC069.xml";

    if (file_exists($archivo_prueba)) {
        procesar_xml($archivo_prueba, $pdo);
    } else {
        echo "❌ Archivo de prueba no encontrado\n";
    }

    echo "\n📊 ESTADÍSTICAS FINALES:\n";
    echo "================================\n";
    echo "📁 Total procesados: {$stats['total_procesados']}\n";
    echo "🆕 Nuevos insertados: {$stats['nuevos_insertados']}\n";
    echo "🔄 Actualizados: {$stats['actualizados']}\n";
    echo "⚪ Sin cambios: {$stats['sin_cambios']}\n";
    echo "❌ Errores: {$stats['errores']}\n";
} catch (Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
}
