<?php
// ========================================================================
// SISTEMA DE CONSULTAS Y REPORTES AVANZADOS PARA CFDIs
// ========================================================================
// Archivo: paso3_sistema_consultas.php
// Propósito: Sistema completo de consultas sobre los CFDIs importados
// Base de datos: sac_db con todos los 387 campos procesados
// ========================================================================

class SistemaConsultasCFDI
{
    private $pdo;

    public function __construct()
    {
        $this->conectarBaseDatos();
    }

    private function conectarBaseDatos()
    {
        try {
            $this->pdo = new PDO(
                "mysql:host=localhost;dbname=sac_db;charset=utf8mb4",
                "root",
                "",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            die("❌ Error de conexión: " . $e->getMessage());
        }
    }

    public function ejecutarMenuConsultas()
    {
        while (true) {
            $this->mostrarMenu();
            $opcion = $this->solicitarOpcion();

            switch ($opcion) {
                case '1':
                    $this->estadisticasGenerales();
                    break;
                case '2':
                    $this->reporteMensual();
                    break;
                case '3':
                    $this->consultarPorRFC();
                    break;
                case '4':
                    $this->consultarPorUUID();
                    break;
                case '5':
                    $this->reporteComplementos();
                    break;
                case '6':
                    $this->reporteImpuestos();
                    break;
                case '7':
                    $this->reportePagos();
                    break;
                case '8':
                    $this->exportarDatos();
                    break;
                case '9':
                    $this->consultaPersonalizada();
                    break;
                case '0':
                    echo "👋 Saliendo del sistema de consultas...\n";
                    return;
                default:
                    echo "❌ Opción inválida. Intente nuevamente.\n\n";
            }

            $this->pausar();
        }
    }

    private function mostrarMenu()
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "      SISTEMA DE CONSULTAS Y REPORTES CFDIs\n";
        echo str_repeat("=", 60) . "\n";
        echo "1. 📊 Estadísticas Generales\n";
        echo "2. 📅 Reporte Mensual\n";
        echo "3. 🏢 Consultar por RFC\n";
        echo "4. 🔍 Consultar por UUID\n";
        echo "5. 🏷️  Reporte de Complementos\n";
        echo "6. 💰 Reporte de Impuestos\n";
        echo "7. 💳 Reporte de Pagos\n";
        echo "8. 📤 Exportar Datos\n";
        echo "9. 🛠️  Consulta Personalizada\n";
        echo "0. 🚪 Salir\n";
        echo str_repeat("=", 60) . "\n";
    }

    private function solicitarOpcion()
    {
        echo "Seleccione una opción: ";
        return trim(fgets(STDIN));
    }

    private function pausar()
    {
        echo "\nPresione Enter para continuar...";
        fgets(STDIN);
    }

    private function estadisticasGenerales()
    {
        echo "\n📊 ESTADÍSTICAS GENERALES\n";
        echo str_repeat("-", 50) . "\n";

        // Total de CFDIs
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM cfdi");
        $total_cfdis = $stmt->fetch()['total'];

        // Por versión
        $stmt = $this->pdo->query("
            SELECT version, COUNT(*) as cantidad 
            FROM cfdi 
            GROUP BY version 
            ORDER BY version
        ");
        $versiones = $stmt->fetchAll();

        // Por tipo de comprobante
        $stmt = $this->pdo->query("
            SELECT tipo_comprobante, COUNT(*) as cantidad 
            FROM cfdi 
            GROUP BY tipo_comprobante 
            ORDER BY cantidad DESC
        ");
        $tipos = $stmt->fetchAll();

        // Rangos de fechas
        $stmt = $this->pdo->query("
            SELECT 
                MIN(fecha) as fecha_minima,
                MAX(fecha) as fecha_maxima,
                COUNT(DISTINCT DATE(fecha)) as dias_diferentes
            FROM cfdi
        ");
        $fechas = $stmt->fetch();

        // Totales monetarios
        $stmt = $this->pdo->query("
            SELECT 
                SUM(subtotal) as total_subtotal,
                SUM(total) as total_general,
                AVG(total) as promedio_total,
                MAX(total) as maximo_total,
                MIN(total) as minimo_total
            FROM cfdi
        ");
        $monetarios = $stmt->fetch();

        echo "Total de CFDIs: " . number_format($total_cfdis) . "\n\n";

        echo "Por Versión:\n";
        foreach ($versiones as $version) {
            $porcentaje = round(($version['cantidad'] / $total_cfdis) * 100, 2);
            echo "  • {$version['version']}: " . number_format($version['cantidad']) . " ({$porcentaje}%)\n";
        }

        echo "\nPor Tipo de Comprobante:\n";
        foreach ($tipos as $tipo) {
            $porcentaje = round(($tipo['cantidad'] / $total_cfdis) * 100, 2);
            echo "  • {$tipo['tipo_comprobante']}: " . number_format($tipo['cantidad']) . " ({$porcentaje}%)\n";
        }

        echo "\nRango de Fechas:\n";
        echo "  • Desde: {$fechas['fecha_minima']}\n";
        echo "  • Hasta: {$fechas['fecha_maxima']}\n";
        echo "  • Días con actividad: " . number_format($fechas['dias_diferentes']) . "\n";

        echo "\nTotales Monetarios:\n";
        echo "  • Subtotal: $" . number_format($monetarios['total_subtotal'], 2) . "\n";
        echo "  • Total: $" . number_format($monetarios['total_general'], 2) . "\n";
        echo "  • Promedio: $" . number_format($monetarios['promedio_total'], 2) . "\n";
        echo "  • Máximo: $" . number_format($monetarios['maximo_total'], 2) . "\n";
        echo "  • Mínimo: $" . number_format($monetarios['minimo_total'], 2) . "\n";
    }

    private function reporteMensual()
    {
        echo "\n📅 REPORTE MENSUAL\n";
        echo str_repeat("-", 50) . "\n";

        $stmt = $this->pdo->query("
            SELECT 
                YEAR(fecha) as año,
                MONTH(fecha) as mes,
                COUNT(*) as cantidad_cfdis,
                SUM(total) as total_mensual,
                AVG(total) as promedio_mensual
            FROM cfdi 
            GROUP BY YEAR(fecha), MONTH(fecha)
            ORDER BY año DESC, mes DESC
            LIMIT 12
        ");

        $reporte_mensual = $stmt->fetchAll();

        if (empty($reporte_mensual)) {
            echo "No hay datos para mostrar.\n";
            return;
        }

        $meses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];

        foreach ($reporte_mensual as $mes_data) {
            $nombre_mes = $meses[$mes_data['mes']];
            echo "{$nombre_mes} {$mes_data['año']}:\n";
            echo "  • CFDIs: " . number_format($mes_data['cantidad_cfdis']) . "\n";
            echo "  • Total: $" . number_format($mes_data['total_mensual'], 2) . "\n";
            echo "  • Promedio: $" . number_format($mes_data['promedio_mensual'], 2) . "\n\n";
        }
    }

    private function consultarPorRFC()
    {
        echo "\n🏢 CONSULTAR POR RFC\n";
        echo str_repeat("-", 50) . "\n";
        echo "Ingrese el RFC a consultar: ";
        $rfc = trim(fgets(STDIN));

        if (empty($rfc)) {
            echo "❌ RFC vacío.\n";
            return;
        }

        // Buscar como emisor
        $stmt = $this->pdo->prepare("
            SELECT c.*, e.nombre as emisor_nombre
            FROM cfdi c
            JOIN emisor e ON c.id = e.cfdi_id
            WHERE e.rfc = :rfc
            ORDER BY c.fecha DESC
            LIMIT 20
        ");
        $stmt->execute([':rfc' => $rfc]);
        $como_emisor = $stmt->fetchAll();

        // Buscar como receptor
        $stmt = $this->pdo->prepare("
            SELECT c.*, r.nombre as receptor_nombre
            FROM cfdi c
            JOIN receptor r ON c.id = r.cfdi_id
            WHERE r.rfc = :rfc
            ORDER BY c.fecha DESC
            LIMIT 20
        ");
        $stmt->execute([':rfc' => $rfc]);
        $como_receptor = $stmt->fetchAll();

        echo "RFC: {$rfc}\n\n";

        echo "Como EMISOR (" . count($como_emisor) . " registros):\n";
        foreach ($como_emisor as $cfdi) {
            echo "  • {$cfdi['fecha']} - Serie: {$cfdi['serie']} Folio: {$cfdi['folio']} - Total: $" . number_format($cfdi['total'], 2) . "\n";
        }

        echo "\nComo RECEPTOR (" . count($como_receptor) . " registros):\n";
        foreach ($como_receptor as $cfdi) {
            echo "  • {$cfdi['fecha']} - Serie: {$cfdi['serie']} Folio: {$cfdi['folio']} - Total: $" . number_format($cfdi['total'], 2) . "\n";
        }
    }

    private function consultarPorUUID()
    {
        echo "\n🔍 CONSULTAR POR UUID\n";
        echo str_repeat("-", 50) . "\n";
        echo "Ingrese el UUID a consultar: ";
        $uuid = trim(fgets(STDIN));

        if (empty($uuid)) {
            echo "❌ UUID vacío.\n";
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                c.*,
                e.rfc as emisor_rfc, e.nombre as emisor_nombre,
                r.rfc as receptor_rfc, r.nombre as receptor_nombre,
                t.fecha_timbrado, t.rfc_prov_certif
            FROM cfdi c
            LEFT JOIN emisor e ON c.id = e.cfdi_id
            LEFT JOIN receptor r ON c.id = r.cfdi_id
            LEFT JOIN cfdi_timbre_fiscal_digital t ON c.id = t.cfdi_id
            WHERE t.uuid = :uuid
        ");
        $stmt->execute([':uuid' => $uuid]);
        $cfdi = $stmt->fetch();

        if (!$cfdi) {
            echo "❌ No se encontró CFDI con UUID: {$uuid}\n";
            return;
        }

        echo "CFDI ENCONTRADO:\n";
        echo "UUID: {$uuid}\n";
        echo "Versión: {$cfdi['version']}\n";
        echo "Serie: {$cfdi['serie']} - Folio: {$cfdi['folio']}\n";
        echo "Fecha: {$cfdi['fecha']}\n";
        echo "Tipo: {$cfdi['tipo_comprobante']}\n";
        echo "Total: $" . number_format($cfdi['total'], 2) . "\n";
        echo "Moneda: {$cfdi['moneda']}\n\n";

        echo "EMISOR:\n";
        echo "RFC: {$cfdi['emisor_rfc']}\n";
        echo "Nombre: {$cfdi['emisor_nombre']}\n\n";

        echo "RECEPTOR:\n";
        echo "RFC: {$cfdi['receptor_rfc']}\n";
        echo "Nombre: {$cfdi['receptor_nombre']}\n\n";

        echo "TIMBRADO:\n";
        echo "Fecha Timbrado: {$cfdi['fecha_timbrado']}\n";
        echo "PAC: {$cfdi['rfc_prov_certif']}\n";

        // Mostrar conceptos
        $stmt = $this->pdo->prepare("
            SELECT descripcion, cantidad, valor_unitario, importe
            FROM conceptos
            WHERE cfdi_id = :cfdi_id
        ");
        $stmt->execute([':cfdi_id' => $cfdi['id']]);
        $conceptos = $stmt->fetchAll();

        if (!empty($conceptos)) {
            echo "\nCONCEPTOS:\n";
            foreach ($conceptos as $concepto) {
                echo "  • {$concepto['descripcion']}\n";
                echo "    Cantidad: {$concepto['cantidad']} - Precio: $" . number_format($concepto['valor_unitario'], 2);
                echo " - Importe: $" . number_format($concepto['importe'], 2) . "\n";
            }
        }
    }

    private function reporteComplementos()
    {
        echo "\n🏷️ REPORTE DE COMPLEMENTOS\n";
        echo str_repeat("-", 50) . "\n";

        $complementos = [
            'cfdi_timbre_fiscal_digital' => 'Timbres Fiscales',
            'cfdi_complemento_pagos_v10' => 'Pagos v1.0',
            'cfdi_complemento_pagos_v20' => 'Pagos v2.0',
            'cfdi_complemento_nomina' => 'Nóminas',
            'cfdi_complemento_carta_porte' => 'Carta Porte',
            'cfdi_complemento_comercio_exterior' => 'Comercio Exterior',
            'cfdi_complemento_impuestos_locales' => 'Impuestos Locales',
            'cfdi_otros_complementos' => 'Otros Complementos'
        ];

        foreach ($complementos as $tabla => $nombre) {
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM {$tabla}");
            $total = $stmt->fetch()['total'];
            echo "{$nombre}: " . number_format($total) . "\n";
        }

        // Detalle de otros complementos
        $stmt = $this->pdo->query("
            SELECT tipo_complemento, COUNT(*) as cantidad
            FROM cfdi_otros_complementos
            GROUP BY tipo_complemento
            ORDER BY cantidad DESC
        ");
        $otros = $stmt->fetchAll();

        if (!empty($otros)) {
            echo "\nDetalle de Otros Complementos:\n";
            foreach ($otros as $otro) {
                echo "  • {$otro['tipo_complemento']}: {$otro['cantidad']}\n";
            }
        }
    }

    private function reporteImpuestos()
    {
        echo "\n💰 REPORTE DE IMPUESTOS\n";
        echo str_repeat("-", 50) . "\n";

        // Impuestos trasladados
        $stmt = $this->pdo->query("
            SELECT 
                impuesto,
                SUM(importe) as total_importe,
                COUNT(*) as cantidad
            FROM impuestos_trasladados
            GROUP BY impuesto
            ORDER BY total_importe DESC
        ");
        $trasladados = $stmt->fetchAll();

        echo "IMPUESTOS TRASLADADOS:\n";
        foreach ($trasladados as $imp) {
            echo "  • {$imp['impuesto']}: $" . number_format($imp['total_importe'], 2);
            echo " ({$imp['cantidad']} registros)\n";
        }

        // Impuestos retenidos
        $stmt = $this->pdo->query("
            SELECT 
                impuesto,
                SUM(importe) as total_importe,
                COUNT(*) as cantidad
            FROM impuestos_retenidos
            GROUP BY impuesto
            ORDER BY total_importe DESC
        ");
        $retenidos = $stmt->fetchAll();

        echo "\nIMPUESTOS RETENIDOS:\n";
        foreach ($retenidos as $imp) {
            echo "  • {$imp['impuesto']}: $" . number_format($imp['total_importe'], 2);
            echo " ({$imp['cantidad']} registros)\n";
        }
    }

    private function reportePagos()
    {
        echo "\n💳 REPORTE DE PAGOS\n";
        echo str_repeat("-", 50) . "\n";

        // Formas de pago más usadas
        $stmt = $this->pdo->query("
            SELECT 
                forma_pago,
                COUNT(*) as cantidad,
                SUM(total) as total_monto
            FROM cfdi
            WHERE forma_pago IS NOT NULL
            GROUP BY forma_pago
            ORDER BY cantidad DESC
            LIMIT 10
        ");
        $formas_pago = $stmt->fetchAll();

        echo "FORMAS DE PAGO MÁS USADAS:\n";
        foreach ($formas_pago as $forma) {
            echo "  • {$forma['forma_pago']}: {$forma['cantidad']} CFDIs - $" . number_format($forma['total_monto'], 2) . "\n";
        }

        // Complementos de pago
        $stmt = $this->pdo->query("
            SELECT 
                'Pagos v1.0' as version,
                COUNT(*) as cantidad,
                SUM(monto) as total_monto
            FROM cfdi_complemento_pagos_v10
            UNION ALL
            SELECT 
                'Pagos v2.0' as version,
                COUNT(*) as cantidad,
                SUM(monto) as total_monto
            FROM cfdi_complemento_pagos_v20
        ");
        $complementos_pago = $stmt->fetchAll();

        echo "\nCOMPLEMENTOS DE PAGO:\n";
        foreach ($complementos_pago as $comp) {
            echo "  • {$comp['version']}: {$comp['cantidad']} registros - $" . number_format($comp['total_monto'], 2) . "\n";
        }
    }

    private function exportarDatos()
    {
        echo "\n📤 EXPORTAR DATOS\n";
        echo str_repeat("-", 50) . "\n";
        echo "1. Exportar CFDIs completos (CSV)\n";
        echo "2. Exportar resumen mensual (CSV)\n";
        echo "3. Exportar por RFC específico (CSV)\n";
        echo "4. Exportar complementos de pago (CSV)\n";
        echo "Seleccione opción: ";

        $opcion = trim(fgets(STDIN));

        switch ($opcion) {
            case '1':
                $this->exportarCFDIsCompletos();
                break;
            case '2':
                $this->exportarResumenMensual();
                break;
            case '3':
                $this->exportarPorRFC();
                break;
            case '4':
                $this->exportarComplementosPago();
                break;
            default:
                echo "❌ Opción inválida.\n";
        }
    }

    private function exportarCFDIsCompletos()
    {
        $archivo = "exportaciones/cfdis_completos_" . date('Y-m-d_H-i-s') . ".csv";
        if (!is_dir('exportaciones')) {
            mkdir('exportaciones', 0777, true);
        }

        $stmt = $this->pdo->query("
            SELECT 
                c.version, c.serie, c.folio, c.fecha, c.total, c.tipo_comprobante,
                e.rfc as emisor_rfc, e.nombre as emisor_nombre,
                r.rfc as receptor_rfc, r.nombre as receptor_nombre,
                t.uuid, t.fecha_timbrado
            FROM cfdi c
            LEFT JOIN emisor e ON c.id = e.cfdi_id
            LEFT JOIN receptor r ON c.id = r.cfdi_id
            LEFT JOIN cfdi_timbre_fiscal_digital t ON c.id = t.cfdi_id
            ORDER BY c.fecha DESC
        ");

        $fp = fopen($archivo, 'w');

        // Encabezados
        fputcsv($fp, [
            'Version',
            'Serie',
            'Folio',
            'Fecha',
            'Total',
            'Tipo',
            'Emisor RFC',
            'Emisor Nombre',
            'Receptor RFC',
            'Receptor Nombre',
            'UUID',
            'Fecha Timbrado'
        ]);

        while ($row = $stmt->fetch()) {
            fputcsv($fp, $row);
        }

        fclose($fp);
        echo "✅ Archivo exportado: {$archivo}\n";
    }

    private function exportarResumenMensual()
    {
        $archivo = "exportaciones/resumen_mensual_" . date('Y-m-d_H-i-s') . ".csv";
        if (!is_dir('exportaciones')) {
            mkdir('exportaciones', 0777, true);
        }

        $stmt = $this->pdo->query("
            SELECT 
                YEAR(fecha) as año,
                MONTH(fecha) as mes,
                COUNT(*) as cantidad_cfdis,
                SUM(total) as total_mensual,
                AVG(total) as promedio_mensual
            FROM cfdi 
            GROUP BY YEAR(fecha), MONTH(fecha)
            ORDER BY año DESC, mes DESC
        ");

        $fp = fopen($archivo, 'w');

        fputcsv($fp, ['Año', 'Mes', 'Cantidad CFDIs', 'Total Mensual', 'Promedio']);

        while ($row = $stmt->fetch()) {
            fputcsv($fp, $row);
        }

        fclose($fp);
        echo "✅ Archivo exportado: {$archivo}\n";
    }

    private function exportarPorRFC()
    {
        echo "Ingrese el RFC: ";
        $rfc = trim(fgets(STDIN));

        if (empty($rfc)) {
            echo "❌ RFC vacío.\n";
            return;
        }

        $archivo = "exportaciones/rfc_{$rfc}_" . date('Y-m-d_H-i-s') . ".csv";
        if (!is_dir('exportaciones')) {
            mkdir('exportaciones', 0777, true);
        }

        $stmt = $this->pdo->prepare("
            (SELECT 
                'EMISOR' as rol, c.fecha, c.serie, c.folio, c.total, 
                e.nombre, r.rfc as contraparte_rfc, r.nombre as contraparte_nombre,
                t.uuid
            FROM cfdi c
            JOIN emisor e ON c.id = e.cfdi_id
            LEFT JOIN receptor r ON c.id = r.cfdi_id
            LEFT JOIN cfdi_timbre_fiscal_digital t ON c.id = t.cfdi_id
            WHERE e.rfc = :rfc)
            UNION ALL
            (SELECT 
                'RECEPTOR' as rol, c.fecha, c.serie, c.folio, c.total, 
                r.nombre, e.rfc as contraparte_rfc, e.nombre as contraparte_nombre,
                t.uuid
            FROM cfdi c
            JOIN receptor r ON c.id = r.cfdi_id
            LEFT JOIN emisor e ON c.id = e.cfdi_id
            LEFT JOIN cfdi_timbre_fiscal_digital t ON c.id = t.cfdi_id
            WHERE r.rfc = :rfc)
            ORDER BY fecha DESC
        ");
        $stmt->execute([':rfc' => $rfc]);

        $fp = fopen($archivo, 'w');

        fputcsv($fp, [
            'Rol',
            'Fecha',
            'Serie',
            'Folio',
            'Total',
            'Nombre',
            'Contraparte RFC',
            'Contraparte Nombre',
            'UUID'
        ]);

        while ($row = $stmt->fetch()) {
            fputcsv($fp, $row);
        }

        fclose($fp);
        echo "✅ Archivo exportado: {$archivo}\n";
    }

    private function exportarComplementosPago()
    {
        $archivo = "exportaciones/complementos_pago_" . date('Y-m-d_H-i-s') . ".csv";
        if (!is_dir('exportaciones')) {
            mkdir('exportaciones', 0777, true);
        }

        $stmt = $this->pdo->query("
            SELECT 
                'v1.0' as version, p.fecha_pago, p.forma_pago_p, p.moneda_p, p.monto,
                c.serie, c.folio, t.uuid
            FROM cfdi_complemento_pagos_v10 p
            JOIN cfdi c ON p.cfdi_id = c.id
            LEFT JOIN cfdi_timbre_fiscal_digital t ON c.id = t.cfdi_id
            UNION ALL
            SELECT 
                'v2.0' as version, p.fecha_pago, p.forma_pago_p, p.moneda_p, p.monto,
                c.serie, c.folio, t.uuid
            FROM cfdi_complemento_pagos_v20 p
            JOIN cfdi c ON p.cfdi_id = c.id
            LEFT JOIN cfdi_timbre_fiscal_digital t ON c.id = t.cfdi_id
            ORDER BY fecha_pago DESC
        ");

        $fp = fopen($archivo, 'w');

        fputcsv($fp, [
            'Version',
            'Fecha Pago',
            'Forma Pago',
            'Moneda',
            'Monto',
            'Serie',
            'Folio',
            'UUID'
        ]);

        while ($row = $stmt->fetch()) {
            fputcsv($fp, $row);
        }

        fclose($fp);
        echo "✅ Archivo exportado: {$archivo}\n";
    }

    private function consultaPersonalizada()
    {
        echo "\n🛠️ CONSULTA PERSONALIZADA\n";
        echo str_repeat("-", 50) . "\n";
        echo "Ingrese su consulta SQL (o 'salir' para regresar):\n";

        while (true) {
            echo "SQL> ";
            $sql = trim(fgets(STDIN));

            if (strtolower($sql) === 'salir') {
                break;
            }

            if (empty($sql)) {
                continue;
            }

            try {
                $stmt = $this->pdo->query($sql);
                $resultados = $stmt->fetchAll();

                if (empty($resultados)) {
                    echo "No se encontraron resultados.\n";
                    continue;
                }

                // Mostrar encabezados
                $columnas = array_keys($resultados[0]);
                echo implode("\t", $columnas) . "\n";
                echo str_repeat("-", count($columnas) * 15) . "\n";

                // Mostrar resultados (máximo 20 filas)
                foreach (array_slice($resultados, 0, 20) as $fila) {
                    echo implode("\t", $fila) . "\n";
                }

                if (count($resultados) > 20) {
                    echo "... y " . (count($resultados) - 20) . " filas más.\n";
                }

                echo "\nTotal de resultados: " . count($resultados) . "\n\n";
            } catch (PDOException $e) {
                echo "❌ Error en consulta: " . $e->getMessage() . "\n\n";
            }
        }
    }
}

// ========================================================================
// EJECUCIÓN
// ========================================================================

echo "========================================================================\n";
echo "        SISTEMA DE CONSULTAS Y REPORTES CFDIs - PASO 3B\n";
echo "========================================================================\n";
echo "Base de datos: sac_db con todos los CFDIs importados\n";
echo "Funcionalidades: Consultas, reportes, exportaciones y análisis\n";
echo "========================================================================\n";

try {
    $sistema = new SistemaConsultasCFDI();
    $sistema->ejecutarMenuConsultas();
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
