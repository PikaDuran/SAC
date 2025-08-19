<?php
// Análisis completo de campos requeridos para el reporte vs campos en BD

echo "=== ANÁLISIS DE CAMPOS REQUERIDOS PARA EL REPORTE ===\n\n";

// Campos identificados en el CSV de reporte (fila 5)
$campos_reporte = [
    'Sello',
    'SAT', 
    'Estatus',
    'Ver.',
    'CFDI Relacionado',
    'Tipo',
    'UUID',
    'UUID Sustitución',
    'Serie',
    'Folio',
    'Emisión',
    'Uso CFDI Descripción',
    'Emisor RFC',
    'Emisor Nombre', 
    'Emisor Régimen Fiscal Descripción',
    'Conceptos Descripción',
    'SubTotal',
    'Base IVA 16',
    'Base IVA 0', 
    'Base IVA Exento',
    'Descuento',
    'IVA',
    'Impto. Loc. Tras.',
    'IVA Retenido',
    'ISR Retenido',
    'Total',
    'Total Original XML',
    'Tipo Cambio',
    'Moneda',
    'Forma Pago Descripción',
    'Método Pago'
];

echo "CAMPOS REQUERIDOS EN EL REPORTE (" . count($campos_reporte) . " campos):\n";
foreach ($campos_reporte as $i => $campo) {
    echo sprintf("%2d. %s\n", $i+1, $campo);
}

echo "\n=== ANÁLISIS DE DISPONIBILIDAD EN LA BD ===\n";

// Conectar a BD para verificar campos disponibles
require_once __DIR__ . '/src/config/database.php';
$pdo = getDatabase();

// Obtener estructura de la tabla cfdi
$stmt = $pdo->query("DESCRIBE cfdi");
$campos_bd = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $campos_bd[] = $row['Field'];
}

echo "CAMPOS DISPONIBLES EN TABLA CFDI (" . count($campos_bd) . " campos):\n";
foreach ($campos_bd as $i => $campo) {
    echo sprintf("%2d. %s\n", $i+1, $campo);
}

echo "\n=== MAPEO Y ANÁLISIS DE CAMPOS FALTANTES ===\n";

$mapeo = [
    'Sello' => ['tabla' => 'cfdi_complementos', 'campo' => 'datos_json->SelloCFD', 'disponible' => 'PARCIAL'],
    'SAT' => ['tabla' => 'cfdi_complementos', 'campo' => 'datos_json->SelloSAT', 'disponible' => 'PARCIAL'],
    'Estatus' => ['tabla' => 'NUEVO', 'campo' => 'estatus_sat', 'disponible' => 'NO'],
    'Ver.' => ['tabla' => 'cfdi', 'campo' => 'version', 'disponible' => 'NO'],
    'CFDI Relacionado' => ['tabla' => 'NUEVO', 'campo' => 'cfdi_relacionado', 'disponible' => 'NO'],
    'Tipo' => ['tabla' => 'cfdi', 'campo' => 'tipo', 'disponible' => 'SÍ'],
    'UUID' => ['tabla' => 'cfdi', 'campo' => 'uuid', 'disponible' => 'SÍ'],
    'UUID Sustitución' => ['tabla' => 'NUEVO', 'campo' => 'uuid_sustitucion', 'disponible' => 'NO'],
    'Serie' => ['tabla' => 'cfdi', 'campo' => 'serie', 'disponible' => 'SÍ'],
    'Folio' => ['tabla' => 'cfdi', 'campo' => 'folio', 'disponible' => 'SÍ'],
    'Emisión' => ['tabla' => 'cfdi', 'campo' => 'fecha', 'disponible' => 'SÍ'],
    'Uso CFDI Descripción' => ['tabla' => 'CATÁLOGO', 'campo' => 'uso_cfdi + descripción', 'disponible' => 'PARCIAL'],
    'Emisor RFC' => ['tabla' => 'cfdi', 'campo' => 'rfc_emisor', 'disponible' => 'SÍ'],
    'Emisor Nombre' => ['tabla' => 'cfdi', 'campo' => 'nombre_emisor', 'disponible' => 'SÍ'],
    'Emisor Régimen Fiscal Descripción' => ['tabla' => 'CATÁLOGO', 'campo' => 'regimen_fiscal_emisor + descripción', 'disponible' => 'PARCIAL'],
    'Conceptos Descripción' => ['tabla' => 'cfdi_conceptos', 'campo' => 'descripcion (concatenada)', 'disponible' => 'SÍ'],
    'SubTotal' => ['tabla' => 'cfdi', 'campo' => 'subtotal', 'disponible' => 'SÍ'],
    'Base IVA 16' => ['tabla' => 'cfdi_impuestos', 'campo' => 'datos_json->Base (IVA 16%)', 'disponible' => 'PARCIAL'],
    'Base IVA 0' => ['tabla' => 'cfdi_impuestos', 'campo' => 'datos_json->Base (IVA 0%)', 'disponible' => 'PARCIAL'],
    'Base IVA Exento' => ['tabla' => 'cfdi_impuestos', 'campo' => 'datos_json->Base (Exento)', 'disponible' => 'PARCIAL'],
    'Descuento' => ['tabla' => 'cfdi', 'campo' => 'descuento', 'disponible' => 'SÍ'],
    'IVA' => ['tabla' => 'cfdi_impuestos', 'campo' => 'datos_json->Importe (IVA)', 'disponible' => 'PARCIAL'],
    'Impto. Loc. Tras.' => ['tabla' => 'cfdi_impuestos', 'campo' => 'datos_json->Importe (Local)', 'disponible' => 'PARCIAL'],
    'IVA Retenido' => ['tabla' => 'cfdi_impuestos', 'campo' => 'datos_json->Importe (IVA Ret)', 'disponible' => 'PARCIAL'],
    'ISR Retenido' => ['tabla' => 'cfdi_impuestos', 'campo' => 'datos_json->Importe (ISR Ret)', 'disponible' => 'PARCIAL'],
    'Total' => ['tabla' => 'cfdi', 'campo' => 'total', 'disponible' => 'SÍ'],
    'Total Original XML' => ['tabla' => 'cfdi', 'campo' => 'total', 'disponible' => 'SÍ'],
    'Tipo Cambio' => ['tabla' => 'cfdi', 'campo' => 'tipo_cambio', 'disponible' => 'SÍ'],
    'Moneda' => ['tabla' => 'cfdi', 'campo' => 'moneda', 'disponible' => 'SÍ'],
    'Forma Pago Descripción' => ['tabla' => 'CATÁLOGO', 'campo' => 'forma_pago + descripción', 'disponible' => 'PARCIAL'],
    'Método Pago' => ['tabla' => 'cfdi', 'campo' => 'metodo_pago', 'disponible' => 'SÍ']
];

$disponibles = 0;
$parciales = 0;
$faltantes = 0;

foreach ($mapeo as $campo_reporte => $info) {
    $status = $info['disponible'];
    switch ($status) {
        case 'SÍ': $disponibles++; break;
        case 'PARCIAL': $parciales++; break;
        case 'NO': $faltantes++; break;
    }
    
    printf("%-30s | %-10s | %-20s | %s\n", 
        $campo_reporte, 
        $status, 
        $info['tabla'], 
        $info['campo']
    );
}

echo "\n=== RESUMEN ===\n";
echo "Total de campos requeridos: " . count($campos_reporte) . "\n";
echo "Disponibles completos: $disponibles\n";
echo "Disponibles parciales: $parciales\n";
echo "Faltantes: $faltantes\n";

echo "\n=== CAMPOS CRÍTICOS FALTANTES ===\n";
$criticos = [];
foreach ($mapeo as $campo => $info) {
    if ($info['disponible'] == 'NO') {
        $criticos[] = $campo;
    }
}

foreach ($criticos as $campo) {
    echo "- $campo\n";
}

echo "\n=== RECOMENDACIONES ===\n";
echo "1. Agregar campos faltantes a la tabla cfdi\n";
echo "2. Crear tablas de catálogos SAT para descripciones\n";
echo "3. Mejorar extracción de datos de TimbreFiscalDigital\n";
echo "4. Implementar cálculos de impuestos por tipo\n";
?>
