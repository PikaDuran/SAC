<?php

/**
 * Verificación completa de todas las tablas CFDI y sus campos
 */

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== ANÁLISIS COMPLETO DE ESTRUCTURA Y DATOS CFDI ===\n\n";

    // Obtener todas las tablas que contienen 'cfdi'
    $stmt = $pdo->query("SHOW TABLES LIKE '%cfdi%'");
    $tablas_cfdi = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tablas_cfdi as $tabla) {
        echo "============================================================\n";
        echo "TABLA: {$tabla}\n";
        echo "============================================================\n";

        // Obtener estructura de la tabla
        $stmt = $pdo->query("DESCRIBE {$tabla}");
        $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener conteo de registros
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM {$tabla}");
        $total_registros = $stmt->fetch()['total'];

        echo "📊 Total de registros: {$total_registros}\n\n";

        echo "🔍 ESTRUCTURA DE COLUMNAS:\n";
        foreach ($columnas as $columna) {
            $field = $columna['Field'];
            $type = $columna['Type'];
            $null = $columna['Null'] == 'YES' ? 'NULL' : 'NOT NULL';
            $key = $columna['Key'] ? " [{$columna['Key']}]" : '';

            echo "  - {$field}: {$type} {$null}{$key}\n";
        }

        // Si hay registros, verificar qué campos tienen datos
        if ($total_registros > 0) {
            echo "\n📈 CAMPOS CON DATOS (no nulos):\n";

            foreach ($columnas as $columna) {
                $field = $columna['Field'];

                // Contar registros no nulos para este campo
                $stmt = $pdo->query("SELECT COUNT(*) as con_datos FROM {$tabla} WHERE {$field} IS NOT NULL AND {$field} != ''");
                $con_datos = $stmt->fetch()['con_datos'];

                $porcentaje = $total_registros > 0 ? round(($con_datos / $total_registros) * 100, 1) : 0;

                if ($con_datos > 0) {
                    echo "  ✅ {$field}: {$con_datos}/{$total_registros} ({$porcentaje}%)\n";
                } else {
                    echo "  ❌ {$field}: 0/{$total_registros} (0%) - VACÍO\n";
                }
            }

            // Mostrar una muestra de datos
            echo "\n📋 MUESTRA DE DATOS (primeros 2 registros):\n";
            $stmt = $pdo->query("SELECT * FROM {$tabla} LIMIT 2");
            $muestra = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($muestra as $i => $registro) {
                echo "  Registro " . ($i + 1) . ":\n";
                foreach ($registro as $campo => $valor) {
                    $valor_mostrar = $valor ? (strlen($valor) > 100 ? substr($valor, 0, 100) . '...' : $valor) : '[NULL]';
                    echo "    {$campo}: {$valor_mostrar}\n";
                }
                echo "\n";
            }
        } else {
            echo "\n⚠️ TABLA VACÍA - No hay datos para analizar\n";
        }

        echo "\n";
    }

    // Análisis específico de campos críticos faltantes
    echo "============================================================\n";
    echo "ANÁLISIS DE CAMPOS CRÍTICOS FALTANTES\n";
    echo "============================================================\n";

    $campos_criticos = [
        'cfdi' => [
            'regimen_fiscal_receptor',
            'complemento_tipo',
            'complemento_json',
            'rfc_consultado',
            'direccion_flujo',
            'sello_sat',
            'no_certificado_sat',
            'rfc_prov_certif',
            'estatus_sat',
            'cfdi_relacionados'
        ]
    ];

    foreach ($campos_criticos as $tabla => $campos) {
        echo "\n🔍 VERIFICANDO CAMPOS CRÍTICOS EN TABLA '{$tabla}':\n";

        // Verificar si la tabla existe
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tabla}'");
        if (!$stmt->fetch()) {
            echo "❌ TABLA '{$tabla}' NO EXISTE\n";
            continue;
        }

        // Obtener columnas existentes
        $stmt = $pdo->query("DESCRIBE {$tabla}");
        $columnas_existentes = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

        foreach ($campos as $campo) {
            if (in_array($campo, $columnas_existentes)) {
                // Campo existe, verificar si tiene datos
                $stmt = $pdo->query("SELECT COUNT(*) as con_datos FROM {$tabla} WHERE {$campo} IS NOT NULL AND {$campo} != ''");
                $con_datos = $stmt->fetch()['con_datos'];

                $stmt = $pdo->query("SELECT COUNT(*) as total FROM {$tabla}");
                $total = $stmt->fetch()['total'];

                if ($con_datos > 0) {
                    echo "  ✅ {$campo}: Existe y tiene datos ({$con_datos}/{$total})\n";
                } else {
                    echo "  ⚠️ {$campo}: Existe pero SIN DATOS (0/{$total})\n";
                }
            } else {
                echo "  ❌ {$campo}: CAMPO NO EXISTE EN LA TABLA\n";
            }
        }
    }

    echo "\n============================================================\n";
    echo "RESUMEN FINAL\n";
    echo "============================================================\n";

    foreach ($tablas_cfdi as $tabla) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM {$tabla}");
        $total = $stmt->fetch()['total'];
        echo "📊 {$tabla}: {$total} registros\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
