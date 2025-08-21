<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'importador_inteligente_cfdi.php';

echo "=== TEST FINAL DE INSERCIÓN CFDI ===\n\n";

try {
    $importador = new ImportadorInteligenteCFDI();
    
    echo "1. Vaciar tabla para test limpio...\n";
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db;charset=utf8mb4', 'root', '');
    $pdo->exec("DELETE FROM cfdi WHERE uuid LIKE 'TEST-%'");
    echo "✅ Tabla limpiada\n\n";
    
    echo "2. Buscar un archivo CFDI para probar...\n";
    $directorio = 'C:/xampp/htdocs/SAC/cfdi/RECIBIDAS';
    $archivos = glob($directorio . '/*.xml');
    
    if (!empty($archivos)) {
        $archivo = $archivos[0];
        echo "📁 Archivo seleccionado: " . basename($archivo) . "\n\n";
        
        echo "3. Procesar archivo usando reflexión...\n";
        
        // Usar reflexión para acceder al método privado
        $reflection = new ReflectionClass($importador);
        $metodo = $reflection->getMethod('procesarArchivo');
        $metodo->setAccessible(true);
        
        $resultado = $metodo->invoke($importador, $archivo);
        
        echo "4. Resultado:\n";
        if ($resultado) {
            echo "✅ CFDI procesado exitosamente\n";
            
            // Verificar que se insertó correctamente
            $stmt = $pdo->prepare("SELECT * FROM cfdi ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($registro) {
                echo "\n=== REGISTRO INSERTADO ===\n";
                echo "UUID: " . $registro['uuid'] . "\n";
                echo "Versión: " . $registro['version'] . "\n";
                echo "RFC Emisor: " . $registro['rfc_emisor'] . "\n";
                echo "RFC Receptor: " . $registro['rfc_receptor'] . "\n";
                
                // Verificar los nuevos campos
                echo "\n=== NUEVOS CAMPOS ===\n";
                echo "Complemento Tipo: " . ($registro['complemento_tipo'] ?? 'NULL') . "\n";
                echo "Complemento JSON: " . (strlen($registro['complemento_json'] ?? '') > 0 ? 'Sí (' . strlen($registro['complemento_json']) . ' chars)' : 'NULL') . "\n";
                echo "RFC Consultado: " . ($registro['rfc_consultado'] ?? 'NULL') . "\n";
                echo "Dirección Flujo: " . ($registro['direccion_flujo'] ?? 'NULL') . "\n";
                echo "Sello SAT: " . (strlen($registro['sello_sat'] ?? '') > 0 ? 'Sí (' . strlen($registro['sello_sat']) . ' chars)' : 'NULL') . "\n";
                echo "No. Certificado SAT: " . ($registro['no_certificado_sat'] ?? 'NULL') . "\n";
                echo "RFC Prov. Certif: " . ($registro['rfc_prov_certif'] ?? 'NULL') . "\n";
                echo "Estatus SAT: " . ($registro['estatus_sat'] ?? 'NULL') . "\n";
                echo "CFDI Relacionados: " . (strlen($registro['cfdi_relacionados'] ?? '') > 0 ? 'Sí (' . strlen($registro['cfdi_relacionados']) . ' chars)' : 'NULL') . "\n";
                
            } else {
                echo "❌ No se encontró el registro insertado\n";
            }
            
        } else {
            echo "❌ Error al procesar CFDI\n";
        }
        
    } else {
        echo "❌ No se encontraron archivos XML en el directorio\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN DEL TEST ===\n";
?>
