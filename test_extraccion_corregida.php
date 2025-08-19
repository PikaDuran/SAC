<?php
echo "=== PRUEBA DE EXTRACCIÓN CORREGIDA ===\n";

// Incluir las funciones mejoradas del importador
require_once 'importador_inteligente_cfdi.php';

$archivoXML = 'C:\xampp\htdocs\SAC/storage/sat_downloads\BFM170822P38\EMITIDAS\2020\1\2020_01_10_702888E7-16B1-4A6E-AAB5-C3F95047C4F2.xml';

if (file_exists($archivoXML)) {
    $contenido = file_get_contents($archivoXML);
    
    // Crear instancia del importador para probar funciones
    $importador = new ImportadorInteligenteCFDI();
    
    // Usar reflexión para acceder a métodos privados
    $reflection = new ReflectionClass($importador);
    $extraerConceptos = $reflection->getMethod('extraerConceptos');
    $extraerConceptos->setAccessible(true);
    
    $extraerImpuestos = $reflection->getMethod('extraerImpuestos');
    $extraerImpuestos->setAccessible(true);
    
    // Probar extracción
    $conceptos = $extraerConceptos->invoke($importador, $contenido);
    $impuestos = $extraerImpuestos->invoke($importador, $contenido);
    
    echo "Conceptos encontrados: " . count($conceptos) . "\n";
    if (!empty($conceptos)) {
        echo "Primer concepto:\n";
        print_r($conceptos[0]);
    }
    
    echo "\nImpuestos encontrados: " . count($impuestos) . "\n";
    if (!empty($impuestos)) {
        echo "Primer impuesto:\n";
        print_r($impuestos[0]);
    }
    
} else {
    echo "❌ Archivo XML no encontrado\n";
}
?>
