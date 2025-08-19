<?php
/**
 * Script para consultar complementos de pago específicos por RFC emisor
 * VERSIÓN ACTUALIZADA - Funciona con los datos actuales disponibles
 */

require_once 'src/config/database.php';

function consultarComplementosPagoActual($rfc_emisor = null) {
    try {
        $pdo = getDatabase();
        
        // Si no se especifica RFC, mostrar lista de RFCs con complementos de pago
        if (!$rfc_emisor) {
            echo "=== RFCs CON COMPLEMENTOS DE PAGO DISPONIBLES ===\n\n";
            
            $sql = "
                SELECT 
                    rfc_emisor, 
                    nombre_emisor, 
                    COUNT(*) as total_complementos,
                    MIN(fecha) as primera_fecha,
                    MAX(fecha) as ultima_fecha
                FROM cfdi 
                WHERE tipo = 'P' 
                GROUP BY rfc_emisor, nombre_emisor 
                ORDER BY total_complementos DESC
                LIMIT 20
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $emisores = $stmt->fetchAll();
            
            printf("%-15s %-35s %-8s %-12s %s\n", "RFC", "NOMBRE", "CFDI-P", "DESDE", "HASTA");
            echo str_repeat("-", 90) . "\n";
            
            foreach ($emisores as $emisor) {
                printf("%-15s %-35s %-8d %-12s %s\n", 
                    $emisor['rfc_emisor'],
                    substr($emisor['nombre_emisor'], 0, 35),
                    $emisor['total_complementos'],
                    substr($emisor['primera_fecha'], 0, 10),
                    substr($emisor['ultima_fecha'], 0, 10)
                );
            }
            
            echo "\n" . str_repeat("=", 90) . "\n";
            echo "Para ver detalles de un RFC específico, ejecuta:\n";
            echo "php " . basename(__FILE__) . " RFC_EMISOR\n\n";
            
            return $emisores;
        }
        
        // Consultar complementos de pago para RFC específico
        echo "=== COMPLEMENTOS DE PAGO PARA RFC: $rfc_emisor ===\n\n";
        
        $sql = "
            SELECT 
                uuid,
                serie,
                folio, 
                fecha,
                fecha_timbrado,
                rfc_emisor,
                nombre_emisor,
                rfc_receptor,
                nombre_receptor,
                total,
                moneda,
                archivo_xml,
                version,
                metodo_pago,
                forma_pago,
                lugar_expedicion
            FROM cfdi 
            WHERE tipo = 'P' AND rfc_emisor = ?
            ORDER BY fecha DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rfc_emisor]);
        $complementos = $stmt->fetchAll();
        
        if (empty($complementos)) {
            echo "No se encontraron complementos de pago para el RFC: $rfc_emisor\n";
            return [];
        }
        
        echo "TOTAL DE COMPLEMENTOS ENCONTRADOS: " . count($complementos) . "\n";
        echo str_repeat("=", 120) . "\n\n";
        
        foreach ($complementos as $index => $comp) {
            echo "COMPLEMENTO " . ($index + 1) . ":\n";
            echo str_repeat("-", 80) . "\n";
            echo sprintf("%-20s: %s\n", "UUID", $comp['uuid']);
            echo sprintf("%-20s: %s\n", "Serie/Folio", $comp['serie'] . "/" . $comp['folio']);
            echo sprintf("%-20s: %s\n", "Fecha", $comp['fecha']);
            echo sprintf("%-20s: %s\n", "Emisor", $comp['rfc_emisor'] . " - " . $comp['nombre_emisor']);
            echo sprintf("%-20s: %s\n", "Receptor", $comp['rfc_receptor'] . " - " . $comp['nombre_receptor']);
            echo sprintf("%-20s: $%s %s\n", "Total", number_format($comp['total'], 2), $comp['moneda']);
            echo sprintf("%-20s: %s\n", "Versión CFDI", $comp['version']);
            echo sprintf("%-20s: %s\n", "Lugar Expedición", $comp['lugar_expedicion']);
            
            if ($comp['archivo_xml']) {
                echo sprintf("%-20s: %s\n", "Archivo XML", $comp['archivo_xml']);
                // Verificar si el archivo existe
                $archivo_existe = file_exists($comp['archivo_xml']) ? "✓ Disponible" : "✗ No encontrado";
                echo sprintf("%-20s: %s\n", "Estado del archivo", $archivo_existe);
                
                // Si el archivo existe, ofrecer la opción de abrirlo
                if (file_exists($comp['archivo_xml'])) {
                    echo sprintf("%-20s: %s\n", "Acción disponible", "Archivo listo para abrir en interfaz");
                }
            } else {
                echo sprintf("%-20s: %s\n", "Archivo XML", "No especificado");
            }
            
            echo "\n" . str_repeat("-", 120) . "\n\n";
        }
        
        // Estadísticas adicionales
        echo "=== ESTADÍSTICAS DEL RFC ===\n";
        echo "Total de CFDIs de Pago: " . count($complementos) . "\n";
        echo "Período: " . substr($complementos[count($complementos)-1]['fecha'], 0, 10) . " a " . substr($complementos[0]['fecha'], 0, 10) . "\n";
        
        // Contar receptores únicos
        $receptores_unicos = array_unique(array_column($complementos, 'rfc_receptor'));
        echo "Receptores únicos: " . count($receptores_unicos) . "\n";
        
        // Mostrar los receptores más frecuentes
        $receptores_frecuencia = array_count_values(array_column($complementos, 'rfc_receptor'));
        arsort($receptores_frecuencia);
        echo "\nReceptores más frecuentes:\n";
        $contador = 0;
        foreach ($receptores_frecuencia as $rfc => $cantidad) {
            if ($contador >= 5) break; // Mostrar solo los primeros 5
            $nombre = '';
            foreach ($complementos as $comp) {
                if ($comp['rfc_receptor'] == $rfc) {
                    $nombre = $comp['nombre_receptor'];
                    break;
                }
            }
            echo sprintf("  %s (%s): %d pagos\n", $rfc, substr($nombre, 0, 30), $cantidad);
            $contador++;
        }
        
        return $complementos;
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        return [];
    }
}

// Verificar si se pasó un RFC como parámetro
$rfc_consultar = $argc > 1 ? $argv[1] : null;

// Ejecutar la consulta
$resultado = consultarComplementosPagoActual($rfc_consultar);

if (!$rfc_consultar && !empty($resultado)) {
    echo "\nEJEMPLO DE USO ESPECÍFICO:\n";
    echo "php " . basename(__FILE__) . " " . $resultado[0]['rfc_emisor'] . "\n\n";
    
    echo "NOTA IMPORTANTE:\n";
    echo "- Los complementos de pago están identificados correctamente (tipo 'P')\n";
    echo "- Cada CFDI tiene su archivo XML físico almacenado\n";
    echo "- Con los cambios implementados, puedes abrir los XMLs desde la interfaz\n";
    echo "- Los detalles específicos del pago están en el XML original\n";
    echo "- Para extraer automáticamente los detalles, ejecutar: php reprocesar_cfdi_completo.php\n";
}
?>
