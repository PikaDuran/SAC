<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Log para debug
file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Solicitud recibida\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Conectar a base de datos
    $pdo = new PDO(
        "mysql:host=localhost;dbname=sac_db;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );

    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Conexión BD OK\n", FILE_APPEND);

    // Obtener filtros (GET o POST)
    $filtros = array_merge($_GET, $_POST);
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Filtros: " . json_encode($filtros) . "\n", FILE_APPEND);

    // Obtener parámetros individuales (formato nuevo - para llamadas directas)
    $tipoReporte = $filtros['tipo'] ?? $filtros['tipoReporte'] ?? 'emitidos';
    $fechaInicio = $filtros['fecha_inicio'] ?? '';
    $fechaFin = $filtros['fecha_fin'] ?? '';
    $emisorRfc = $filtros['emisor_rfc'] ?? '';
    $receptorRfc = $filtros['receptor_rfc'] ?? '';

    // Manejar formato original del frontend (rfc único + tipoReporte)
    if (!empty($filtros['rfc']) && empty($emisorRfc) && empty($receptorRfc)) {
        // El frontend envía un RFC único, determinar si es emisor o receptor
        if ($tipoReporte === 'emitidos') {
            $emisorRfc = $filtros['rfc'];
        } elseif ($tipoReporte === 'recibidos') {
            $receptorRfc = $filtros['rfc'];
        } elseif ($tipoReporte === 'complementos') {
            // Para complementos, puede ser cualquiera
            $emisorRfc = $filtros['rfc'];
            $receptorRfc = $filtros['rfc'];
        }
    }

    // Manejar fechas del formato original (campo 'fechas' con rango)
    if (!empty($filtros['fechas']) && empty($fechaInicio) && empty($fechaFin)) {
        if (strpos($filtros['fechas'], ' a ') !== false) {
            $fechas = explode(' a ', $filtros['fechas']);
            if (count($fechas) === 2) {
                $fechaInicio = trim($fechas[0]);
                $fechaFin = trim($fechas[1]);
            }
        }
    }

    // Para compatibilidad con código anterior, establecer rfc principal
    $filtros['rfc'] = $filtros['rfc'] ?? '';
    if (empty($filtros['rfc'])) {
        if ($tipoReporte === 'emitidos' && !empty($emisorRfc)) {
            $filtros['rfc'] = $emisorRfc;
        } elseif ($tipoReporte === 'recibidos' && !empty($receptorRfc)) {
            $filtros['rfc'] = $receptorRfc;
        } elseif ($tipoReporte === 'complementos' && (!empty($emisorRfc) || !empty($receptorRfc))) {
            $filtros['rfc'] = $emisorRfc ?: $receptorRfc;
        }
    }

    // VALIDACIÓN: RFC es obligatorio
    if (empty($filtros['rfc']) || trim($filtros['rfc']) === '' || trim($filtros['rfc']) === 'Seleccionar RFC...') {
        echo json_encode([
            'success' => false,
            'message' => 'Error: Debe seleccionar un RFC para generar el reporte.',
            'error_type' => 'validation'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Extraer solo el RFC del valor seleccionado (formato: "RFC - NOMBRE EMPRESA")
    $rfcLimpio = $filtros['rfc'];
    if (strpos($rfcLimpio, ' - ') !== false) {
        $rfcLimpio = trim(explode(' - ', $rfcLimpio)[0]);
    }
    $filtros['rfc'] = strtoupper($rfcLimpio);

    // Verificar si es solicitud para Excel
    $esParaExcel = isset($_GET['excel']) || isset($_POST['excel']) || (isset($_GET['formato']) && $_GET['formato'] === 'excel');

    // Agregar debug SQL al response
    $debugSQL = "";

    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Es para Excel: " . ($esParaExcel ? 'SI' : 'NO') . "\n", FILE_APPEND);

    // Definir consultas base según el tipo de reporte
    $filtros['tipoReporte'] = $tipoReporte; // Asegurar que esté disponible para el resto del código

    if ($tipoReporte === 'complementos') {
        // Consulta especializada para complementos de pago
        $sqlStats = "SELECT 
                        COUNT(*) as total_cfdis,
                        SUM(CASE WHEN cp.monto IS NOT NULL THEN cp.monto ELSE 0 END) as total_facturado,
                        AVG(CASE WHEN cp.monto IS NOT NULL THEN cp.monto ELSE 0 END) as promedio_ticket,
                        'P' as tipo,
                        COUNT(*) as cantidad_por_tipo
                    FROM v_complementos_pago cp
                    INNER JOIN cfdi c ON cp.cfdi_id = c.id
                    LEFT JOIN emisor e ON c.id = e.cfdi_id
                    LEFT JOIN receptor r ON c.id = r.cfdi_id
                    WHERE cp.uuid IS NOT NULL";

        $sqlData = "SELECT 
                    cp.uuid,
                    c.fecha as emision,
                    IFNULL(e.rfc, 'N/A') as emisor_rfc,
                    IFNULL(e.nombre, 'N/A') as emisor_nombre,
                    IFNULL(r.rfc, 'N/A') as receptor_rfc,
                    IFNULL(r.nombre, 'N/A') as receptor_nombre,
                    cp.monto as total,
                    cp.moneda_p as moneda,
                    'P' as tipo,
                    cp.forma_pago_p as forma_pago,
                    cp.version_pago
                FROM v_complementos_pago cp
                INNER JOIN cfdi c ON cp.cfdi_id = c.id
                LEFT JOIN emisor e ON c.id = e.cfdi_id
                LEFT JOIN receptor r ON c.id = r.cfdi_id
                WHERE cp.uuid IS NOT NULL";
    } else {
        // Usar v_reporte_excel para emitidos y recibidos
        $sqlStats = "SELECT 
                        COUNT(*) as total_cfdis,
                        SUM(CASE WHEN total IS NOT NULL THEN total ELSE 0 END) as total_facturado,
                        AVG(CASE WHEN total IS NOT NULL THEN total ELSE 0 END) as promedio_ticket,
                        tipo,
                        COUNT(*) as cantidad_por_tipo
                    FROM v_reporte_excel
                    WHERE uuid IS NOT NULL";

        if ($esParaExcel) {
            // ...consulta para Excel...
            $sqlData = "SELECT 
                        'Válido' as sello, 'Existe' as sat, 
                        CASE 
                            WHEN estatus LIKE '%lido%' THEN 'Válido'
                            WHEN estatus LIKE '%cancelado%' THEN 'Cancelado'
                            ELSE REPLACE(REPLACE(REPLACE(estatus, 'ß', 'á'), 'Ã', 'í'), '??', 'é')
                        END as estatus, 
                        ver, cfdi_relacionado, tipo, uuid, uuid_sustitucion, 
                        serie, folio, emision, 
                        REPLACE(REPLACE(REPLACE(uso_cfdi_descripcion, 'ß', 'á'), 'Ã', 'í'), '??', 'é') as uso_cfdi_descripcion, 
                        emisor_rfc, 
                        REPLACE(REPLACE(REPLACE(emisor_nombre, 'ß', 'á'), 'Ã', 'í'), '??', 'é') as emisor_nombre,
                        receptor_rfc, 
                        REPLACE(REPLACE(REPLACE(receptor_nombre, 'ß', 'á'), 'Ã', 'í'), '??', 'é') as receptor_nombre, 
                        REPLACE(REPLACE(REPLACE(emisor_regimen_fiscal_descripcion, 'ß', 'á'), 'Ã', 'í'), '??', 'é') as emisor_regimen_fiscal_descripcion, 
                        REPLACE(REPLACE(REPLACE(conceptos_descripcion, 'ß', 'á'), 'Ã', 'í'), '??', 'é') as conceptos_descripcion, 
                        subtotal, base_iva_16, base_iva_0, base_iva_exento, descuento, iva,
                        impto_loc_tras, iva_retenido, isr_retenido, total, total_original_xml,
                        tipo_cambio, 
                        REPLACE(REPLACE(REPLACE(moneda, 'ß', 'á'), 'Ã', 'í'), '??', 'é') as moneda, 
                        REPLACE(REPLACE(REPLACE(forma_pago_descripcion, 'ß', 'á'), 'Ã', 'í'), '??', 'é') as forma_pago_descripcion, 
                        REPLACE(REPLACE(REPLACE(metodo_pago, 'ß', 'á'), 'Ã', 'í'), '??', 'é') as metodo_pago
                    FROM v_reporte_excel
                    WHERE uuid IS NOT NULL";
        } else {
            // Para tabla web: solo campos básicos, asegurando que el campo de fecha sea 'emision'
            $sqlData = "SELECT 
                        uuid, emision, emisor_rfc, emisor_nombre, receptor_rfc, receptor_nombre, 
                        total, moneda, tipo
                    FROM v_reporte_excel
                    WHERE uuid IS NOT NULL";
        }
    }

    $params = [];

    // Aplicar filtros según el tipo de reporte
    $whereConditions = "";
    if (!empty($filtros['rfc'])) {
        // Determinar el tipo de filtro según el tipo de reporte
        switch ($filtros['tipoReporte']) {
            case 'emitidos':
                if ($tipoReporte === 'complementos') {
                    $whereConditions .= " AND e.rfc = :rfc";
                } else {
                    $whereConditions .= " AND emisor_rfc = :rfc";
                }
                break;
            case 'recibidos':
                if ($tipoReporte === 'complementos') {
                    $whereConditions .= " AND r.rfc = :rfc";
                } else {
                    $whereConditions .= " AND receptor_rfc = :rfc";
                }
                break;
            case 'impuestos':
            case 'complementos':
            default:
                if ($tipoReporte === 'complementos') {
                    $whereConditions .= " AND (e.rfc = :rfc OR r.rfc = :rfc)";
                } else {
                    $whereConditions .= " AND (emisor_rfc = :rfc OR receptor_rfc = :rfc)";
                }
                break;
        }
        $params[':rfc'] = strtoupper(trim($filtros['rfc']));
    }

    if (!empty($filtros['fechas'])) {
        $fechas = explode(' a ', $filtros['fechas']);
        if (count($fechas) == 2) {
            $whereConditions .= " AND DATE(emision) BETWEEN :fecha_inicio AND :fecha_fin";
            $params[':fecha_inicio'] = trim($fechas[0]);
            $params[':fecha_fin'] = trim($fechas[1]);
        }
    } elseif (!empty($fechaInicio) && !empty($fechaFin)) {
        // Usar fechas individuales cuando no hay campo 'fechas'
        $whereConditions .= " AND DATE(emision) BETWEEN :fecha_inicio AND :fecha_fin";
        $params[':fecha_inicio'] = $fechaInicio;
        $params[':fecha_fin'] = $fechaFin;
    }

    if (!empty($filtros['tipoComprobante'])) {
        $whereConditions .= " AND tipo = :tipo_comprobante";
        $params[':tipo_comprobante'] = $filtros['tipoComprobante'];
    }

    if (!empty($filtros['moneda'])) {
        $whereConditions .= " AND moneda = :moneda";
        $params[':moneda'] = $filtros['moneda'];
    }

    // Agregar condiciones a las consultas
    $sqlStats .= $whereConditions . " GROUP BY tipo";
    $sqlData .= $whereConditions . " ORDER BY emision DESC LIMIT 500";

    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - SQL Stats: " . $sqlStats . "\n", FILE_APPEND);
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - SQL Data: " . $sqlData . "\n", FILE_APPEND);
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Params: " . json_encode($params) . "\n", FILE_APPEND);

    // Agregar el SELECT completo como respuesta adicional para debug
    $response['debug_sql'] = $sqlData;
    $response['debug_params'] = $params;

    // DEBUG: Agregar información de consulta al response para mostrar en alert
    $debugInfo = [
        'sql_data' => $sqlData,
        'sql_stats' => $sqlStats,
        'params' => $params,
        'es_para_excel' => $esParaExcel,
        'tipo_reporte' => $tipoReporte
    ];

    // Ejecutar consulta de estadísticas
    $stmtStats = $pdo->prepare($sqlStats);
    $stmtStats->execute($params);
    $statsData = $stmtStats->fetchAll();

    // Ejecutar consulta de datos para tabla
    $stmtData = $pdo->prepare($sqlData);
    $stmtData->execute($params);
    $data = $stmtData->fetchAll();

    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Resultados tabla: " . count($data) . "\n", FILE_APPEND);
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Resultados stats: " . count($statsData) . "\n", FILE_APPEND);

    // Calcular estadísticas reales
    $total_cfdis = 0;
    $total_facturado = 0;
    $tipos_distribucion = ['I' => 0, 'E' => 0, 'T' => 0, 'P' => 0, 'N' => 0];

    foreach ($statsData as $stat) {
        $total_cfdis += $stat['cantidad_por_tipo'] ?? 0;
        $total_facturado += $stat['total_facturado'] ?? 0;
        $tipoKey = $stat['tipo'] ?? 'I';
        if (isset($tipos_distribucion[$tipoKey])) {
            $tipos_distribucion[$tipoKey] = $stat['cantidad_por_tipo'] ?? 0;
        }
    }

    $promedio_ticket = $total_cfdis > 0 ? $total_facturado / $total_cfdis : 0;

    // Obtener impuestos reales si existe la tabla
    $total_impuestos = 0;
    try {
        if ($tipoReporte === 'complementos') {
            $sqlImpuestos = "SELECT SUM(CASE WHEN cp.monto IS NOT NULL THEN cp.monto * 0.16 ELSE 0 END) as total_impuestos 
                            FROM v_complementos_pago cp
                            INNER JOIN cfdi c ON cp.cfdi_id = c.id
                            LEFT JOIN emisor e ON c.id = e.cfdi_id
                            LEFT JOIN receptor r ON c.id = r.cfdi_id
                            WHERE cp.uuid IS NOT NULL" . $whereConditions;
        } elseif ($tipoReporte === 'recibidos') {
            $sqlImpuestos = "SELECT SUM(CASE WHEN c.total IS NOT NULL THEN c.total * 0.16 ELSE 0 END) as total_impuestos 
                            FROM cfdi c
                            LEFT JOIN emisor e ON c.id = e.cfdi_id
                            LEFT JOIN receptor r ON c.id = r.cfdi_id
                            WHERE c.uuid IS NOT NULL" . $whereConditions;
        } else {
            $sqlImpuestos = "SELECT SUM(CASE WHEN iva IS NOT NULL THEN iva ELSE 0 END) as total_impuestos 
                            FROM v_reporte_excel
                            WHERE uuid IS NOT NULL" . $whereConditions;
        }

        $stmtImpuestos = $pdo->prepare($sqlImpuestos);
        $stmtImpuestos->execute($params);
        $impuestosResult = $stmtImpuestos->fetch();
        $total_impuestos = $impuestosResult['total_impuestos'] ?? ($total_facturado * 0.16);
    } catch (Exception $e) {
        // Si no existe la tabla de impuestos, usar estimación
        $total_impuestos = $total_facturado * 0.16;
    }

    // Formatear datos para la tabla
    $formattedData = [];
    foreach ($data as $row) {
        if ($esParaExcel) {
            // Filtrado de fechas igual que la web
            // Orden exacto de campos para Excel (A5-AE5)
            $formattedData[] = [
                $row['sello'] ?? '',                                // r0
                $row['sat'] ?? '',                                 // r1
                $row['estatus'] ?? '',                                     // r2
                $row['ver'] ?? '',                                      // r3
                $row['cfdi_relacionado'] ?? '',                        // r4
                getTipoComprobanteNombre($row['tipo'] ?? ''),          // r5
                $row['uuid'] ?? '',                                    // r6
                $row['uuid_sustitucion'] ?? '',                        // r7
                $row['serie'] ?? '',                                   // r8
                $row['folio'] ?? '',                                   // r9
                $row['emision'] ?? '',                                 // r10
                $row['uso_cfdi_descripcion'] ?? '',                    // r11
                $row['emisor_rfc'] ?? '',                              // r12
                $row['emisor_nombre'] ?? '',                           // r13
                $row['receptor_rfc'] ?? '',                            // r14
                $row['receptor_nombre'] ?? '',                         // r15
                $row['emisor_regimen_fiscal_descripcion'] ?? '',       // r16
                $row['conceptos_descripcion'] ?? '',                   // r17
                floatval($row['subtotal'] ?? 0),                       // r18
                floatval($row['base_iva_16'] ?? 0),                    // r19
                floatval($row['base_iva_0'] ?? 0),                     // r20
                floatval($row['base_iva_exento'] ?? 0),                // r21
                floatval($row['descuento'] ?? 0),                      // r22
                floatval($row['iva'] ?? 0),                            // r23
                floatval($row['impto_loc_tras'] ?? 0),                 // r24
                floatval($row['iva_retenido'] ?? 0),                   // r25
                floatval($row['isr_retenido'] ?? 0),                   // r26
                floatval($row['total'] ?? 0),                          // r27
                floatval($row['total_original_xml'] ?? 0),             // r28
                floatval($row['tipo_cambio'] ?? 1),                    // r29
                $row['moneda'] ?? 'MXN',                               // r30
                $row['forma_pago_descripcion'] ?? '',                   // r31
                $row['metodo_pago'] ?? ''                                // r32
            ];
        } else {
            // Para tabla web: formato simple de 10 campos
            $formattedData[] = [
                $row['uuid'] ?? '',
                $row['emision'] ?? '',
                $row['emisor_rfc'] ?? '',
                $row['emisor_nombre'] ?? '',
                $row['receptor_rfc'] ?? '',
                $row['receptor_nombre'] ?? '',
                floatval($row['total'] ?? 0),
                $row['moneda'] ?: 'MXN',
                getTipoComprobanteNombre($row['tipo'] ?? ''),
                ''
            ];
        }
    }

    // Generar estadísticas reales
    $stats = [
        'total_cfdis' => $total_cfdis,
        'total_facturado' => $total_facturado,
        'promedio_ticket' => $promedio_ticket,
        'total_impuestos' => $total_impuestos
    ];

    // Crear condiciones WHERE para evolución (usando tablas con JOIN)
    $whereEvolucion = '';
    $paramsEvolucion = [];

    if (!empty($fechaInicio)) {
        if ($tipoReporte === 'complementos') {
            $whereEvolucion .= " AND cp.fecha_pago >= ?";
        } elseif ($tipoReporte === 'recibidos') {
            $whereEvolucion .= " AND c.fecha >= ?";
        } else {
            $whereEvolucion .= " AND emision >= ?";
        }
        $paramsEvolucion[] = $fechaInicio;
    }

    if (!empty($fechaFin)) {
        if ($tipoReporte === 'complementos') {
            $whereEvolucion .= " AND cp.fecha_pago <= ?";
        } elseif ($tipoReporte === 'recibidos') {
            $whereEvolucion .= " AND c.fecha <= ?";
        } else {
            $whereEvolucion .= " AND emision <= ?";
        }
        $paramsEvolucion[] = $fechaFin;
    }

    if (!empty($emisorRfc)) {
        if ($tipoReporte === 'complementos') {
            $whereEvolucion .= " AND e.rfc = ?";
        } else {
            $whereEvolucion .= " AND emisor_rfc = ?";
        }
        $paramsEvolucion[] = $emisorRfc;
    }

    if (!empty($receptorRfc)) {
        if ($tipoReporte === 'complementos') {
            $whereEvolucion .= " AND r.rfc = ?";
        } elseif ($tipoReporte === 'recibidos') {
            $whereEvolucion .= " AND r.rfc = ?";
        } else {
            $whereEvolucion .= " AND emisor_rfc = ?"; // Para vista v_reporte_excel solo tenemos emisor
        }
        $paramsEvolucion[] = $receptorRfc;
    }

    // Obtener evolución mensual real
    if ($tipoReporte === 'complementos') {
        $sqlEvolucion = "SELECT 
                            DATE_FORMAT(cp.fecha_pago, '%Y-%m') as mes,
                            SUM(CASE WHEN cp.monto IS NOT NULL THEN cp.monto ELSE 0 END) as total_mes,
                            COUNT(*) as cantidad_mes
                        FROM v_complementos_pago cp
                        INNER JOIN cfdi c ON cp.cfdi_id = c.id
                        LEFT JOIN emisor e ON c.id = e.cfdi_id
                        LEFT JOIN receptor r ON c.id = r.cfdi_id
                        WHERE cp.uuid IS NOT NULL" . $whereEvolucion . "
                        GROUP BY DATE_FORMAT(cp.fecha_pago, '%Y-%m')
                        ORDER BY mes DESC
                        LIMIT 12";
    } elseif ($tipoReporte === 'recibidos') {
        $sqlEvolucion = "SELECT 
                            DATE_FORMAT(c.fecha, '%Y-%m') as mes,
                            SUM(CASE WHEN c.total IS NOT NULL THEN c.total ELSE 0 END) as total_mes,
                            COUNT(*) as cantidad_mes
                        FROM cfdi c
                        LEFT JOIN emisor e ON c.id = e.cfdi_id
                        LEFT JOIN receptor r ON c.id = r.cfdi_id
                        WHERE c.uuid IS NOT NULL" . $whereEvolucion . "
                        GROUP BY DATE_FORMAT(c.fecha, '%Y-%m')
                        ORDER BY mes DESC
                        LIMIT 12";
    } else {
        $sqlEvolucion = "SELECT 
                            DATE_FORMAT(emision, '%Y-%m') as mes,
                            SUM(CASE WHEN total IS NOT NULL THEN total ELSE 0 END) as total_mes,
                            COUNT(*) as cantidad_mes
                        FROM v_reporte_excel
                        WHERE uuid IS NOT NULL" . $whereEvolucion . "
                        GROUP BY DATE_FORMAT(emision, '%Y-%m')
                        ORDER BY mes DESC
                        LIMIT 12";
    }

    $stmtEvolucion = $pdo->prepare($sqlEvolucion);
    $stmtEvolucion->execute($paramsEvolucion);
    $evolucionData = $stmtEvolucion->fetchAll();

    // Preparar datos para gráficas
    $labels_evolucion = [];
    $data_evolucion = [];

    foreach (array_reverse($evolucionData) as $mes) {
        $labels_evolucion[] = date('M Y', strtotime($mes['mes'] . '-01'));
        $data_evolucion[] = floatval($mes['total_mes']);
    }

    // Datos para gráficas reales
    $charts = [
        'tipos' => [
            'labels' => ['Ingreso', 'Egreso', 'Traslado', 'Pago', 'Nómina'],
            'data' => [
                $tipos_distribucion['I'],
                $tipos_distribucion['E'],
                $tipos_distribucion['T'],
                $tipos_distribucion['P'],
                $tipos_distribucion['N']
            ]
        ],
        'evolucion' => [
            'labels' => $labels_evolucion,
            'data' => $data_evolucion
        ]
    ];

    $response = [
        'success' => true,
        'data' => $formattedData,
        'stats' => $stats,
        'charts' => $charts,
        'total_records' => $total_cfdis, // Total real, no solo los mostrados
        'displayed_records' => count($formattedData) // Los que se muestran en la tabla
    ];

    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Response preparado\n", FILE_APPEND);

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function getTipoComprobanteNombre($tipo)
{
    $tipos = [
        'I' => 'Ingreso',
        'E' => 'Egreso',
        'T' => 'Traslado',
        'P' => 'Pago',
        'N' => 'Nómina'
    ];
    return $tipos[$tipo] ?? $tipo;
}

function getEstatusCompleto($row)
{
    $estatus = $row['estatus'] ?? '';
    $sat = $row['sat'] ?? '';
    $sello_sat = $row['sello_sat'] ?? '';
    $uuid = $row['uuid'] ?? '';

    // 1. Validar si es válido
    $esValido = (strtolower($estatus) === 'válido' || strtolower($estatus) === 'valido');

    // 2. Validar si existe en SAT (tiene sello SAT y RFC SAT)
    $existeEnSAT = !empty($sello_sat) && !empty($sat) && $sat === 'SAT970701NN3';

    // 3. Validar si está vigente (no cancelado y válido)
    $estaVigente = $esValido && !in_array(strtolower($estatus), ['cancelado', 'cancelled']);

    // Construir mensaje descriptivo
    $status = [];

    if ($esValido) {
        $status[] = 'Válido';
    } else {
        $status[] = 'No Válido';
    }

    if ($existeEnSAT) {
        $status[] = 'Existe SAT';
    } else {
        $status[] = 'No en SAT';
    }

    if ($estaVigente) {
        $status[] = 'Vigente';
    } else {
        $status[] = 'No Vigente';
    }

    return implode(' | ', $status);
}
