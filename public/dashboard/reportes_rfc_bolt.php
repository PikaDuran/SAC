<?php
// --- BACKEND AJAX JSON ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET) && !isset($_GET['export'])) {
    // Desactivar warnings y errores para evitar salida accidental
    error_reporting(0);
    ini_set('display_errors', 0);
    // Devolver JSON para DataTable si ajax=1
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        require_once '../../src/config/database.php';
        $rfc = isset($_GET['rfc']) ? $_GET['rfc'] : '';
        $fecha_inicial = isset($_GET['fecha_inicial']) ? $_GET['fecha_inicial'] : '';
        $fecha_final = isset($_GET['fecha_final']) ? $_GET['fecha_final'] : '';
        // Convertir fechas a formato MySQL
        if ($fecha_inicial) {
            $fecha_inicial = date('Y-m-d', strtotime($fecha_inicial)) . ' 00:00:00';
        }
        if ($fecha_final) {
            $fecha_final = date('Y-m-d', strtotime($fecha_final)) . ' 23:59:59';
        }
        $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
        $tipo_comprobante = isset($_GET['tipo_comprobante']) ? $_GET['tipo_comprobante'] : '';
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                throw new Exception('Error de conexión: ' . $conn->connect_error);
            }
            $sql = "SELECT * FROM cfdi WHERE 1=1";
            $params = [];
            if ($rfc) {
                if ($tipo === 'emitidas') {
                    $sql .= " AND rfc_emisor = ?";
                    $params[] = $rfc;
                } elseif ($tipo === 'recibidas') {
                    $sql .= " AND rfc_emisor != ?";
                    $params[] = $rfc;
                }
            }
            if ($fecha_inicial) {
                $sql .= " AND fecha >= ?";
                $params[] = $fecha_inicial;
            }
            if ($fecha_final) {
                $sql .= " AND fecha <= ?";
                $params[] = $fecha_final;
            }
            if ($tipo_comprobante) {
                $sql .= " AND tipo = ?";
                $params[] = $tipo_comprobante;
            }
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Error en la consulta: ' . $conn->error);
            }
            if ($params) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = [];
            $emitidos = 0;
            $recibidos = 0;
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
                if ($rfc) {
                    if ($tipo === 'emitidas' && $row['rfc_emisor'] === $rfc) {
                        $emitidos++;
                    } elseif ($tipo === 'recibidas' && $row['rfc_emisor'] !== $rfc) {
                        $recibidos++;
                    }
                }
            }
            $total = count($rows);
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            // Siempre imprimir un JSON válido
            echo json_encode([
                'total' => $total,
                'emitidos' => $emitidos,
                'recibidos' => $recibidos,
                'data' => $rows
            ]);
            if ($stmt) $stmt->close();
            if ($conn) $conn->close();
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
}
require_once '../../vendor/autoload.php';
session_start();
require_once '../../vendor/autoload.php';
require_once '../../src/config/database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
// Mantener menú y header para coherencia visual
include '../../src/views/sidebar.php';
include '../../src/views/header.php';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes RFC BOLT</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../dashboard/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        .report-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
            margin-bottom: 1.5rem;
            background: #f6f8fa;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }

        .report-toolbar .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 140px;
        }

        .report-toolbar label {
            font-weight: 500;
            margin-bottom: 0.2rem;
            color: #217346;
            font-size: 0.98rem;
        }

        .report-toolbar select,
        .report-toolbar input[type=date] {
            padding: 0.3rem 0.6rem;
            border-radius: 5px;
            border: 1px solid #d0d7de;
            font-size: 0.98rem;
            margin-bottom: 0.1rem;
            background: #fff;
        }

        .report-toolbar .button-group {
            display: flex;
            gap: 0.6rem;
            align-items: flex-end;
        }

        .report-toolbar button {
            background: #217346;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 0.38rem 1rem;
            font-size: 0.98rem;
            font-weight: 500;
            cursor: pointer;
            margin-top: 0.2rem;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        }

        .report-toolbar button:hover {
            background: #145c2a;
        }

        .datatable-container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
            padding: 1.2rem;
            overflow-x: auto;
        }

        table.dataTable {
            width: 100% !important;
            min-width: 1200px;
            table-layout: auto;
            white-space: nowrap;
        }

        @media (max-width: 900px) {
            .report-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .report-toolbar .button-group {
                justify-content: flex-start;
            }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            let pruebaCount = 10;
            // Obtener fecha actual y primer día del mes en zona horaria CDMX
            function getCDMXDate(offsetDays = 0) {
                let now = new Date();
                let utc = now.getTime() + (now.getTimezoneOffset() * 60000);
                let cdmxOffset = -6;
                let cdmxDate = new Date(utc + (3600000 * cdmxOffset));
                if (offsetDays !== 0) {
                    cdmxDate.setDate(cdmxDate.getDate() + offsetDays);
                }
                return cdmxDate;
            }

            function pad(n) {
                return String(n).padStart(2, '0');
            }
            let todayCDMX = getCDMXDate();
            let yyyy = todayCDMX.getFullYear();
            let mm = pad(todayCDMX.getMonth() + 1);
            let dd = pad(todayCDMX.getDate());
            let firstDay = yyyy + '-' + mm + '-01';
            let currentDay = yyyy + '-' + mm + '-' + dd;
            $('#fecha_inicial').val(firstDay);
            $('#fecha_final').val(currentDay);

            flatpickr('#fecha_inicial', {
                dateFormat: 'Y-m-d',
                allowInput: true,
                defaultDate: firstDay,
                altInput: false,
                locale: 'es',
                monthSelectorType: 'dropdown',
                yearSelectorType: 'dropdown',
                timeZone: 'America/Mexico_City'
            });
            flatpickr('#fecha_final', {
                dateFormat: 'Y-m-d',
                allowInput: true,
                defaultDate: currentDay,
                altInput: false,
                locale: 'es',
                monthSelectorType: 'dropdown',
                yearSelectorType: 'dropdown',
                timeZone: 'America/Mexico_City'
            });

            // Botones rápidos de rango
            $('.quick-range').on('click', function() {
                let range = $(this).data('range');
                let now = getCDMXDate();
                let yyyy = now.getFullYear();
                let mm = pad(now.getMonth() + 1);
                let dd = pad(now.getDate());
                if (range === 'mes') {
                    $('#fecha_inicio').val(yyyy + '-' + mm + '-01');
                    $('#fecha_fin').val(yyyy + '-' + mm + '-' + dd);
                } else if (range === 'anio') {
                    $('#fecha_inicio').val(yyyy + '-01-01');
                    $('#fecha_fin').val(yyyy + '-' + mm + '-' + dd);
                } else if (range === 'hoy') {
                    $('#fecha_inicio').val(yyyy + '-' + mm + '-' + dd);
                    $('#fecha_fin').val(yyyy + '-' + mm + '-' + dd);
                }
            });

            var table = null;
            $('#filtros').on('submit', function(e) {
                e.preventDefault();
                var queryString = $(this).serialize();
                var sql = 'SELECT * FROM cfdi';
                var filtros = $('#filtros').serializeArray();
                var where = [];
                filtros.forEach(function(f) {
                    if (f.value) {
                        if (f.name === 'rfc') {
                            let tipo = filtros.find(x => x.name === 'tipo')?.value;
                            if (tipo === 'emitidas') {
                                where.push("rfc_emisor = '" + f.value + "'");
                            } else if (tipo === 'recibidas') {
                                where.push("rfc_emisor != '" + f.value + "'");
                            } else {
                                where.push("(rfc_emisor = '" + f.value + "' OR rfc_receptor = '" + f.value + "')");
                            }
                        } else if (f.name === 'fecha_inicial') {
                            where.push("fecha >= '" + f.value + " 00:00:00'");
                        } else if (f.name === 'fecha_final') {
                            where.push("fecha <= '" + f.value + " 23:59:59'");
                        } else if (f.name === 'tipo_comprobante') {
                            where.push("tipo = '" + f.value + "'");
                        }
                    }
                });
                if (where.length > 0) {
                    sql += ' WHERE ' + where.join(' AND ');
                }
                // Ocultar el SELECT y mostrar leyenda de resultados
                $('#sql-preview').hide();
                // Forzar parámetro ajax=1 para que el backend devuelva solo JSON
                $.get('reportes_rfc_bolt.php?' + queryString + '&ajax=1', function(data) {
                    var headers = [];
                    // Si la respuesta es string, validar si está vacía
                    if (typeof data === 'string') {
                        if (!data || data.trim() === '') {
                            alert('No se recibió respuesta del servidor.');
                            return;
                        }
                        try {
                            var json = JSON.parse(data);
                        } catch (e) {
                            alert('Error al procesar los datos.\n' + e);
                            return;
                        }
                    } else {
                        // Si ya es objeto (por ejemplo, jQuery lo parsea), usarlo directo
                        var json = data;
                    }
                    if (json.error) {
                        alert('Error: ' + json.error);
                        return;
                    }
                    // Mostrar los totales recibidos del backend
                    $('#cfdi-legend').html(
                        '<span style="font-weight:600;color:#217346;">No. de CFDIs encontrados: ' + (json.total || 0) + '</span>' +
                        ' &nbsp; <span style="color:#145c2a;">Emitidos: ' + (json.emitidos || 0) + '</span>' +
                        ' &nbsp; <span style="color:#145c2a;">Recibidos: ' + (json.recibidos || 0) + '</span>'
                    ).show();
                    if (Array.isArray(json.data) && json.data.length > 0) {
                        window.ultimaConsultaCFDI = json.data;
                        headers = Object.keys(json.data[0]).filter(h => h !== 'complemento_json');
                        $('#thead-row').html(headers.map(h => '<th>' + h + '</th>').join(''));
                        if (table) {
                            table.clear();
                            table.destroy();
                        }
                        table = $('#resultados').DataTable({
                            destroy: true,
                            columns: headers.map(h => ({
                                title: h
                            })),
                            data: json.data.map(row => headers.map(h => row[h])),
                            scrollX: true
                        });
                        $('#exportar').prop('disabled', false);
                    } else {
                        window.ultimaConsultaCFDI = [];
                        $('#thead-row').html('');
                        if (table) {
                            table.clear().draw();
                        }
                        // UX/UI: Modal con color naranja y icono de warning junto a la leyenda
                        if ($('#no-results-modal').length === 0) {
                            $('body').append(`
                                <div id="no-results-modal" class="modal-overlay" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.18);z-index:9999;display:flex;align-items:center;justify-content:center;">
                                    <div class="modal-content" style="background:#fff;padding:0;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,0.12);max-width:400px;text-align:center;overflow:hidden;">
                                        <div style="background:#ff9800;padding:1.1rem 2rem 1rem 2rem;display:flex;align-items:center;gap:0.7rem;justify-content:left;">
                                            <i class="fa fa-search" style="font-size:1.7rem;color:#fff;"></i>
                                            <span style="font-size:1.25rem;font-weight:600;color:#fff;">Búsqueda de registros</span>
                                        </div>
                                        <div style="height:1px;background:#e0e0e0;width:100%;"></div>
                                        <div style="margin:1.5rem 2rem 1.2rem 2rem;color:#ff9800;font-size:1.15rem;font-weight:500;display:flex;align-items:center;justify-content:center;gap:0.6rem;">
                                            <i class='fa fa-exclamation-triangle' style='color:#ff9800;font-size:1.3rem;'></i>
                                            <span>No se encontraron registros</span>
                                        </div>
                                        <div style="margin-bottom:1.2rem;color:#555;font-size:1.05rem;">Intenta con otros filtros o verifica los datos ingresados.</div>
                                        <button id="close-no-results" style="background:#ff9800;color:#fff;border:none;border-radius:6px;padding:0.6rem 1.5rem;font-size:1rem;font-weight:500;cursor:pointer;margin-bottom:1.5rem;">Cerrar</button>
                                    </div>
                                </div>
                            `);
                            $('#close-no-results').on('click', function() {
                                $('#no-results-modal').remove();
                            });
                        }
                    }
                });
                // Imprimir el SELECT generado arriba de la tabla
                //$('#sql-preview').html('<b>Consulta SQL:</b> <pre style="white-space:pre-wrap;word-break:break-all;">' + sql + '</pre>').show();
            });
            $('#exportar').on('click', function() {
                // Exportar solo si hay datos
                if (!Array.isArray(window.ultimaConsultaCFDI) || window.ultimaConsultaCFDI.length === 0) return;
                // Excluir la columna 'id' de los encabezados y datos
                const allHeaders = Object.keys(window.ultimaConsultaCFDI[0]);
                const headers = allHeaders.filter(h => h.toLowerCase() !== 'id');
                const rows = window.ultimaConsultaCFDI.map(row => headers.map(h => row[h]));
                let wb = XLSX.utils.book_new();
                let ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
                // Formato: encabezados fondo azul y letras blancas
                headers.forEach((h, idx) => {
                    const cell = XLSX.utils.encode_cell({
                        c: idx,
                        r: 0
                    });
                    if (!ws[cell]) ws[cell] = {
                        t: 's',
                        v: h
                    };
                    ws[cell].s = {
                        fill: {
                            fgColor: {
                                rgb: '217346'
                            }
                        }, // azul
                        font: {
                            color: {
                                rgb: 'FFFFFF'
                            },
                            bold: true
                        }, // blanco y negrita
                        alignment: {
                            horizontal: 'center',
                            vertical: 'center'
                        }
                    };
                });
                XLSX.utils.book_append_sheet(wb, ws, 'Reporte CFDI');
                // Obtener fecha y hora actual en formato _aniomesdiahoramin
                const now = new Date();
                const yyyy = now.getFullYear();
                const mm = String(now.getMonth() + 1).padStart(2, '0');
                const dd = String(now.getDate()).padStart(2, '0');
                const hh = String(now.getHours()).padStart(2, '0');
                const min = String(now.getMinutes()).padStart(2, '0');
                const fechaHora = `_${yyyy}${mm}${dd}${hh}${min}`;
                XLSX.writeFile(wb, `Reporte_CFDI${fechaHora}.xlsx`);
            });
            // Desactivar el botón Exportar al cargar la página (forzado)
            document.getElementById('exportar').disabled = true;
            // Al cargar la página, asegúrate que no hay datos previos
            window.ultimaConsultaCFDI = [];

            // Eliminar o comentar cualquier bloque que muestre la consulta SQL
            document.querySelectorAll('.consulta-sql, #consulta-sql, .sql-block').forEach(e => e.style.display = 'none');

            // Eliminar duplicidad y asegurar que el botón Exportar se active correctamente
            function actualizarBotonExportar() {
                var btnExportar = document.getElementById('exportar');
                var tieneDatos = Array.isArray(window.ultimaConsultaCFDI) && window.ultimaConsultaCFDI.length > 0;
                btnExportar.disabled = !tieneDatos;
                btnExportar.style.pointerEvents = tieneDatos ? 'auto' : 'none';
                btnExportar.style.opacity = tieneDatos ? '1' : '0.5';
            }
            // Llamar después de cargar/buscar datos
            $(document).on('ajaxComplete', function() {
                actualizarBotonExportar();
            });
            // También llamar después de cada búsqueda exitosa
            $('#filtros').on('submit', function() {
                setTimeout(actualizarBotonExportar, 500);
            });
        });
    </script>
</head>

<body>
    <main class="main-content">
        <div class="content">
            <h1 style="margin-bottom:1.5rem;font-size:2rem;font-weight:700;color:#217346;">Reportes RFC BOLT</h1>
            <form id="filtros" class="report-toolbar">
                <div class="filter-group">
                    <label for="rfc">RFC:</label>
                    <select name="rfc" id="rfc">
                        <option value="">Todos</option>
                        <?php
                        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                        $rfcRes = $conn->query("SELECT rfc FROM sat_fiel_certificates");
                        while ($row = $rfcRes->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($row['rfc']) . '">' . htmlspecialchars($row['rfc']) . '</option>';
                        }
                        $rfcRes->free();
                        $tipoCompRes = $conn->query("SELECT clave, descripcion FROM catalogo_sat_tipo_comprobante");
                        $tipoComprobantes = [];
                        while ($row = $tipoCompRes->fetch_assoc()) {
                            $tipoComprobantes[] = $row;
                        }
                        $tipoCompRes->free();
                        $conn->close();
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="fecha_inicio">Fecha inicial:</label>
                    <div style="display:flex;align-items:center;gap:0.4rem;">
                        <input type="text" id="fecha_inicial" name="fecha_inicial" class="form-control" value="<?php echo $primer_dia; ?>">
                        <i class="fa fa-calendar" style="font-size:1.2rem;color:#217346;"></i>
                    </div>
                </div>
                <div class="filter-group">
                    <label for="fecha_fin">Fecha final:</label>
                    <div style="display:flex;align-items:center;gap:0.4rem;">
                        <input type="text" id="fecha_final" name="fecha_final" class="form-control" value="<?php echo $hoy; ?>">
                        <i class="fa fa-calendar" style="font-size:1.2rem;color:#217346;"></i>
                    </div>
                </div>
                <div class="filter-group">
                    <label for="tipo">Tipo:</label>
                    <select name="tipo" id="tipo">
                        <option value="">Todos</option>
                        <option value="emitidas">Emitidas</option>
                        <option value="recibidas">Recibidas</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="tipo_comprobante">Tipo Comprobante:</label>
                    <select name="tipo_comprobante" id="tipo_comprobante">
                        <option value="">Todos</option>
                        <?php
                        foreach ($tipoComprobantes as $comp) {
                            echo '<option value="' . htmlspecialchars($comp['clave']) . '">' . htmlspecialchars($comp['descripcion']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="button-group">
                    <button type="submit"><i class="fa fa-search"></i> Buscar</button>
                    <button type="button" id="exportar" disabled style="pointer-events:none;opacity:0.5;"><i class="fa fa-file-excel"></i> Exportar</button>
                </div>
            </form>
            <div id="cfdi-legend" style="margin: 1rem 0; padding: 0.7rem 1rem; background: #f6f8fa; border-radius: 7px; font-size: 1.05rem; color: #217346; display: none;"></div>
            <div id="sql-preview" style="margin: 1rem 0; padding: 0.7rem 1rem; background: #f6f8fa; border-radius: 7px; font-size: 1rem; color: #333; display: none;"></div>
            <div class="datatable-container">
                <table id="resultados" class="display" style="width:100%">
                    <thead>
                        <tr id="thead-row"></tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </main>
    <?php
    // --- BACKEND ---
    if (isset($_GET['export_excel'])) {
        require_once '../../vendor/autoload.php';
        require_once '../../src/config/database.php';
        $rfc = isset($_GET['rfc']) ? $_GET['rfc'] : '';
        $fecha_inicial = isset($_GET['fecha_inicial']) ? $_GET['fecha_inicial'] : '';
        $fecha_final = isset($_GET['fecha_final']) ? $_GET['fecha_final'] : '';
        if ($fecha_inicial) {
            $fecha_inicial = date('Y-m-d', strtotime($fecha_inicial)) . ' 00:00:00';
        }
        if ($fecha_final) {
            $fecha_final = date('Y-m-d', strtotime($fecha_final)) . ' 23:59:59';
        }
        $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
        $tipo_comprobante = isset($_GET['tipo_comprobante']) ? $_GET['tipo_comprobante'] : '';
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $sql = "SELECT * FROM cfdi WHERE 1=1";
        $params = [];
        if ($rfc) {
            if ($tipo === 'emitidas') {
                $sql .= " AND rfc_emisor = ?";
                $params[] = $rfc;
            } elseif ($tipo === 'recibidas') {
                $sql .= " AND rfc_emisor != ?";
                $params[] = $rfc;
            }
        }
        if ($fecha_inicial) {
            $sql .= " AND fecha >= ?";
            $params[] = $fecha_inicial;
        }
        if ($fecha_final) {
            $sql .= " AND fecha <= ?";
            $params[] = $fecha_final;
        }
        if ($tipo_comprobante) {
            $sql .= " AND tipo = ?";
            $params[] = $tipo_comprobante;
        }
        $stmt = $conn->prepare($sql);
        if ($params) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        $headers = [];
        while ($row = $result->fetch_assoc()) {
            unset($row['id']); // Eliminar columna ID
            $rows[] = $row;
        }
        if (count($rows) > 0) {
            $headers = array_keys($rows[0]);
        }
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        // Encabezados y formato celda por celda
        // Encabezados y formato
        $headers = array_keys($rows[0] ?? []);
        $colStart = 'A';
        $colEnd = Coordinate::stringFromColumnIndex(count($headers));
        foreach ($headers as $i => $header) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . '1', $header);
        }
        $headerRange = "{$colStart}1:{$colEnd}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF217346');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(28);
        // Datos
        foreach ($rows as $r => $row) {
            foreach ($headers as $c => $header) {
                $col = Coordinate::stringFromColumnIndex($c + 1);
                $sheet->setCellValue($col . ($r + 2), $row[$header]);
            }
        }
        $now = date('YmdHi');
        $filename = "Reporte_CFDI_{$now}.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename={$filename}");
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    if (isset($_GET['export'])) {
        // Exportar CSV
        require_once '../../src/config/database.php';
        $rfc = isset($_GET['rfc']) ? $_GET['rfc'] : '';
        $fecha_inicial = isset($_GET['fecha_inicial']) ? $_GET['fecha_inicial'] : '';
        $fecha_final = isset($_GET['fecha_final']) ? $_GET['fecha_final'] : '';
        // Convertir fechas a formato MySQL
        if ($fecha_inicial) {
            $fecha_inicial = date('Y-m-d', strtotime($fecha_inicial)) . ' 00:00:00';
        }
        if ($fecha_final) {
            $fecha_final = date('Y-m-d', strtotime($fecha_final)) . ' 23:59:59';
        }
        $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
        $tipo_comprobante = isset($_GET['tipo_comprobante']) ? $_GET['tipo_comprobante'] : '';
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $sql = "SELECT * FROM cfdi WHERE 1=1";
        $params = [];
        if ($rfc) {
            if ($tipo === 'emitidas') {
                $sql .= " AND rfc_emisor = ?";
                $params[] = $rfc;
            } elseif ($tipo === 'recibidas') {
                $sql .= " AND rfc_emisor != ?";
                $params[] = $rfc;
            }
        }
        if ($fecha_inicial) {
            $sql .= " AND fecha >= ?";
            $params[] = $fecha_inicial;
        }
        if ($fecha_final) {
            $sql .= " AND fecha <= ?";
            $params[] = $fecha_final;
        }
        if ($tipo_comprobante) {
            $sql .= " AND tipo = ?";
            $params[] = $tipo_comprobante;
        }
        $stmt = $conn->prepare($sql);
        if ($params) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=reportes_rfc_bolt.csv');
        $output = fopen('php://output', 'w');
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            fputcsv($output, array_keys($row));
            fputcsv($output, array_values($row));
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, array_values($row));
            }
        }
        fclose($output);
        $stmt->close();
        $conn->close();
        exit;
    }
    // ...existing code...
    ?>
</body>

</html>