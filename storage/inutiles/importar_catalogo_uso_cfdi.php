<?php
/**
 * IMPORTADOR DE CATÁLOGO SAT - USO CFDI
 * Importa los datos del CSV del catálogo de Uso CFDI del SAT
 */

require_once __DIR__ . '/src/config/database.php';

try {
    $pdo = getDatabase();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🚀 IMPORTANDO CATÁLOGO SAT - USO CFDI\n";
    echo str_repeat("=", 50) . "\n\n";
    
    // Limpiar tabla existente
    echo "🗑️  Limpiando tabla existente...\n";
    $pdo->exec("DELETE FROM catalogo_sat_uso_cfdi");
    
    // Datos del catálogo extraídos del CSV
    $usosCFDI = [
        ['G01', 'Adquisición de mercancías.', 'Sí', 'Sí', '2022-01-01', null, '601, 603, 606, 612, 620, 621, 622, 623, 624, 625,626'],
        ['G02', 'Devoluciones, descuentos o bonificaciones.', 'Sí', 'Sí', '2022-01-01', null, '601, 603, 606, 612, 620, 621, 622, 623, 624, 625,626'],
        ['G03', 'Gastos en general.', 'Sí', 'Sí', '2022-01-01', null, '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'],
        ['I01', 'Construcciones.', 'Sí', 'Sí', '2022-01-01', null, '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'],
        ['I02', 'Mobiliario y equipo de oficina por inversiones.', 'Sí', 'Sí', '2022-01-01', null, '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'],
        ['I03', 'Equipo de transporte.', 'Sí', 'Sí', '2022-01-01', null, '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'],
        ['I04', 'Equipo de computo y accesorios.', 'Sí', 'Sí', '2022-01-01', null, '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'],
        ['I05', 'Dados, troqueles, moldes, matrices y herramental.', 'Sí', 'Sí', '2022-01-01', null, '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'],
        ['I06', 'Comunicaciones telefónicas.', 'Sí', 'Sí', '2022-01-01', null, '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'],
        ['I07', 'Comunicaciones satelitales.', 'Sí', 'Sí', '2022-01-01', null, '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'],
        ['I08', 'Otra maquinaria y equipo.', 'Sí', 'Sí', '2022-01-01', null, '601, 603, 606, 612, 620, 621, 622, 623, 624, 625, 626'],
        ['D01', 'Honorarios médicos, dentales y gastos hospitalarios.', 'Sí', 'No', '2022-01-01', null, '605, 606, 608, 611, 612, 614, 607, 615, 625'],
        ['D02', 'Gastos médicos por incapacidad o discapacidad.', 'Sí', 'No', '2022-01-01', null, '605, 606, 608, 611, 612, 614, 607, 615, 625'],
        ['D03', 'Gastos funerales.', 'Sí', 'No', '2022-01-01', null, '605, 606, 608, 611, 612, 614, 607, 615, 625'],
        ['D04', 'Donativos.', 'Sí', 'No', '2022-01-01', null, '605, 606, 608, 611, 612, 614, 607, 615, 625'],
        ['D05', 'Intereses reales efectivamente pagados por créditos hipotecarios (casa habitación).', 'Sí', 'No', '2022-01-01', null, '605, 606, 608, 611, 612, 614, 607, 615, 625'],
        ['D06', 'Aportaciones voluntarias al SAR.', 'Sí', 'No', '2022-01-01', null, '605, 606, 608, 611, 612, 614, 607, 615, 625'],
        ['D07', 'Primas por seguros de gastos médicos.', 'Sí', 'No', '2022-01-01', null, '605, 606, 608, 611, 612, 614, 607, 615, 625'],
        ['D08', 'Gastos de transportación escolar obligatoria.', 'Sí', 'No', '2022-01-01', null, '605, 606, 608, 611, 612, 614, 607, 615, 625'],
        ['D09', 'Depósitos en cuentas para el ahorro, primas que tengan como base planes de pensiones.', 'Sí', 'No', '2022-01-01', null, '605, 606, 608, 611, 612, 614, 607, 615, 625'],
        ['D10', 'Pagos por servicios educativos (colegiaturas).', 'Sí', 'No', '2022-01-01', null, '605, 606, 608, 611, 612, 614, 607, 615, 625'],
        ['S01', 'Sin efectos fiscales.', 'Sí', 'Sí', '2022-01-01', null, '601, 603, 605, 606, 608, 610, 611, 612, 614, 616, 620, 621, 622, 623, 624, 607, 615, 625, 626'],
        ['CP01', 'Pagos', 'Sí', 'Sí', '2022-01-01', null, '601, 603, 605, 606, 608, 610, 611, 612, 614, 616, 620, 621, 622, 623, 624, 607, 615, 625, 626'],
        ['CN01', 'Nómina', 'Sí', 'No', '2022-01-01', null, '605']
    ];
    
    // Preparar statement para inserción
    $stmt = $pdo->prepare("
        INSERT INTO catalogo_sat_uso_cfdi (
            clave, descripcion, aplica_fisica, aplica_moral, 
            fecha_inicio, fecha_fin, regimenes
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $insertados = 0;
    
    echo "📋 Insertando registros...\n";
    foreach ($usosCFDI as $uso) {
        try {
            $stmt->execute([
                $uso[0], // clave
                $uso[1], // descripcion
                $uso[2], // aplica_fisica
                $uso[3], // aplica_moral
                $uso[4], // fecha_inicio
                $uso[5], // fecha_fin (null)
                $uso[6]  // regimenes
            ]);
            $insertados++;
            echo "  ✅ {$uso[0]} - {$uso[1]}\n";
        } catch (Exception $e) {
            echo "  ❌ Error en {$uso[0]}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n📊 RESUMEN:\n";
    echo str_repeat("-", 30) . "\n";
    echo "Registros insertados: $insertados\n";
    echo "Total esperado: " . count($usosCFDI) . "\n";
    
    // Verificar inserción
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM catalogo_sat_uso_cfdi");
    $total = $stmt->fetch()['total'];
    echo "Registros en tabla: $total\n";
    
    if ($total == count($usosCFDI)) {
        echo "\n🎉 ¡CATÁLOGO IMPORTADO EXITOSAMENTE!\n";
    } else {
        echo "\n⚠️  Advertencia: No coinciden los totales\n";
    }
    
    // Mostrar algunos ejemplos
    echo "\n📋 EJEMPLOS DE REGISTROS INSERTADOS:\n";
    echo str_repeat("-", 60) . "\n";
    $stmt = $pdo->query("SELECT clave, descripcion, aplica_fisica, aplica_moral FROM catalogo_sat_uso_cfdi ORDER BY clave LIMIT 5");
    while ($row = $stmt->fetch()) {
        echo sprintf("%-6s %-40s Física:%-3s Moral:%-3s\n", 
            $row['clave'], 
            substr($row['descripcion'], 0, 40) . '...', 
            $row['aplica_fisica'], 
            $row['aplica_moral']
        );
    }
    
    echo "\n✅ PROCESO COMPLETADO\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
