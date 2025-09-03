<?php
date_default_timezone_set('America/Mexico_City');
require_once 'src/config/database.php';

echo "=== ANÁLISIS IMPACTO ACTUALIZACIONES SAT AGOSTO 2025 ===\n";

try {
    $pdo = getDatabase();

    echo "\n🔍 ANALIZANDO CFDIs CON INFORMACIÓN ADUANAL...\n";

    // Buscar CFDIs con menciones de pedimentos
    $stmt = $pdo->query("
        SELECT COUNT(*) as total_cfdis,
               COUNT(CASE WHEN archivo_xml LIKE '%PEDIMENTO%' THEN 1 END) as con_pedimentos,
               COUNT(CASE WHEN archivo_xml LIKE '%ADUANA%' THEN 1 END) as con_aduana,
               COUNT(CASE WHEN archivo_xml LIKE '%CartaPorte%' THEN 1 END) as con_carta_porte,
               COUNT(CASE WHEN archivo_xml LIKE '%ComercioExterior%' THEN 1 END) as con_comercio_exterior
        FROM cfdi 
        WHERE archivo_xml IS NOT NULL
    ");

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "📊 RESULTADOS ANÁLISIS:\n";
    echo "Total CFDIs en BD: {$resultado['total_cfdis']}\n";
    echo "CFDIs con PEDIMENTOS: {$resultado['con_pedimentos']}\n";
    echo "CFDIs con ADUANA: {$resultado['con_aduana']}\n";
    echo "CFDIs con Carta Porte: {$resultado['con_carta_porte']}\n";
    echo "CFDIs con Comercio Exterior: {$resultado['con_comercio_exterior']}\n";

    // También revisar en la tabla de conceptos
    $stmt2 = $pdo->query("
        SELECT COUNT(*) as total_conceptos,
               COUNT(CASE WHEN descripcion LIKE '%PEDIMENTO%' THEN 1 END) as conceptos_pedimentos,
               COUNT(CASE WHEN descripcion LIKE '%ADUANA%' THEN 1 END) as conceptos_aduana
        FROM cfdi_conceptos
    ");

    $conceptos = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo "\n📦 ANÁLISIS CONCEPTOS:\n";
    echo "Total conceptos: {$conceptos['total_conceptos']}\n";
    echo "Conceptos con PEDIMENTOS: {$conceptos['conceptos_pedimentos']}\n";
    echo "Conceptos con ADUANA: {$conceptos['conceptos_aduana']}\n";

    $porcentaje_pedimentos = ($resultado['con_pedimentos'] / max($resultado['total_cfdis'], 1)) * 100;
    $porcentaje_conceptos = ($conceptos['conceptos_pedimentos'] / max($conceptos['total_conceptos'], 1)) * 100;

    echo "\n📈 PORCENTAJES:\n";
    echo "CFDIs con pedimentos: " . number_format($porcentaje_pedimentos, 2) . "%\n";
    echo "Conceptos con pedimentos: " . number_format($porcentaje_conceptos, 2) . "%\n";

    if ($resultado['con_pedimentos'] > 0 || $conceptos['conceptos_pedimentos'] > 0) {
        echo "\n⚠️  IMPACTO CONFIRMADO: Las actualizaciones SAT SÍ afectan nuestro sistema\n";
        echo "🔧 ACCIÓN REQUERIDA: Actualizar catálogos c_NumPedimentoAduana y c_PatenteAduanal\n";

        // Mostrar ejemplos de pedimentos encontrados
        echo "\n📋 EJEMPLOS DE CONCEPTOS CON PEDIMENTOS:\n";
        $stmt = $pdo->query("
            SELECT descripcion
            FROM cfdi_conceptos 
            WHERE descripcion LIKE '%PEDIMENTO%' 
            LIMIT 3
        ");

        $ejemplos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($ejemplos) > 0) {
            foreach ($ejemplos as $i => $ejemplo) {
                echo "  " . ($i + 1) . ". " . substr($ejemplo['descripcion'], 0, 100) . "...\n";
            }
        } else {
            echo "  (No se encontraron ejemplos específicos en conceptos)\n";
        }
    } else {
        echo "\n✅ IMPACTO MÍNIMO: No se encontraron CFDIs con información aduanal\n";
    }

    echo "\n🔍 VERIFICANDO CATÁLOGOS SAT ACTUALES...\n";

    // Verificar si existen tablas de catálogos
    $stmt = $pdo->query("SHOW TABLES LIKE 'catalogo_sat_%'");
    $tablas_catalogo = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Tablas de catálogos encontradas: " . count($tablas_catalogo) . "\n";
    foreach ($tablas_catalogo as $tabla) {
        echo "  - $tabla\n";
    }

    echo "\n📋 RECOMENDACIONES:\n";
    if ($resultado['con_pedimentos'] > 0) {
        echo "1. ✅ ALTA PRIORIDAD: Implementar catálogos c_NumPedimentoAduana y c_PatenteAduanal\n";
        echo "2. ✅ MEDIA PRIORIDAD: Validar pedimentos en procesamiento CFDI\n";
        echo "3. ✅ BAJA PRIORIDAD: Implementar catálogo c_NumAutorizacionNaviero (Carta Porte)\n";
    } else {
        echo "1. ✅ Continuar con implementación actual\n";
        echo "2. ⏳ Monitorear futuras actualizaciones SAT\n";
    }

    echo "\n🎯 CONCLUSIÓN:\n";
    if ($porcentaje_pedimentos > 10) {
        echo "IMPACTO ALTO: Más del 10% de CFDIs contienen información aduanal\n";
        echo "ACCIÓN: Implementar catálogos actualizados inmediatamente\n";
    } elseif ($porcentaje_pedimentos > 1) {
        echo "IMPACTO MEDIO: Entre 1-10% de CFDIs contienen información aduanal\n";
        echo "ACCIÓN: Planificar implementación de catálogos\n";
    } else {
        echo "IMPACTO BAJO: Menos del 1% de CFDIs contienen información aduanal\n";
        echo "ACCIÓN: Documentar para futura implementación\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
