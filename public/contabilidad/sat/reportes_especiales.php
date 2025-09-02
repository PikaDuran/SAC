<?php
session_start();
require_once '../../../src/helpers/auth.php';
checkAuth(['admin', 'contabilidad']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Especiales CFDI - SAC</title>
    <link rel="stylesheet" href="../../dashboard/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
    <style>
        .filters-container {
            background: #fff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.2s;
            background: white;
        }

        .filter-group select {
            cursor: pointer;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6,9 12,15 18,9"/></svg>');
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px;
            padding-right: 2.5rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #4285f4;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #4285f4;
            color: white;
        }

        .btn-primary:hover {
            background: #3367d6;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #34a853;
            color: white;
        }

        .btn-success:hover {
            background: #2d8a47;
            transform: translateY(-1px);
        }

        .btn-info {
            background: #1a73e8;
            color: white;
        }

        .btn-info:hover {
            background: #1557b0;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: #fbbc04;
            color: #333;
        }

        .btn-warning:hover {
            background: #ea9400;
            transform: translateY(-1px);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            text-align: center;
            border-left: 4px solid #4285f4;
        }

        .stat-card.success {
            border-left-color: #34a853;
        }

        .stat-card.warning {
            border-left-color: #fbbc04;
        }

        .stat-card.danger {
            border-left-color: #ea4335;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-weight: 500;
        }

        .results-container {
            background: #fff;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4285f4;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .advanced-filters {
            display: none;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e1e5e9;
        }

        .toggle-advanced {
            color: #4285f4;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .toggle-advanced:hover {
            text-decoration: underline;
        }

        #resultsTable_wrapper {
            margin-top: 1rem;
        }

        .dataTables_filter input {
            padding: 0.5rem;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
        }

        .export-options {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Estilos básicos para DateRangePicker */
        .daterangepicker {
            z-index: 9999 !important;
            font-family: inherit !important;
        }

        /* Estilos para campos de fecha simples */
        .date-inputs {
            display: flex;
            gap: 0.5rem;
        }

        .date-inputs input[type="date"] {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }

        .date-inputs input[type="date"]:focus {
            outline: none;
            border-color: #4285f4;
        }

        .filter-group small {
            display: inline-block;
            margin-top: 0.25rem;
            font-size: 0.8rem;
            color: #4285f4;
            cursor: pointer;
            transition: color 0.2s;
        }

        .filter-group small:hover {
            color: #3367d6;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../../src/views/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../../../src/views/header.php'; ?>

            <div class="content">
                <div class="toolbar">
                    <h1><i class="fas fa-chart-line"></i> Reportes Especiales CFDI</h1>
                    <p>Sistema avanzado de consultas y análisis de CFDIs</p>
                </div>

                <!-- Filtros Principales -->
                <div class="filters-container">
                    <h3><i class="fas fa-filter"></i> Filtros de Búsqueda</h3>

                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="tipoReporte">Tipo de Reporte</label>
                            <select id="tipoReporte">
                                <option value="emitidos">CFDIs Emitidos (como Emisor)</option>
                                <option value="recibidos">CFDIs Recibidos (como Receptor)</option>
                                <option value="impuestos">Análisis de Impuestos</option>
                                <option value="complementos">Complementos de Pago</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="rfc">RFC (Emisor/Receptor)</label>
                            <select id="rfc">
                                <option value="">Seleccionar RFC...</option>
                                <!-- Los RFCs se cargarán dinámicamente -->
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="fechas">Rango de Fechas</label>
                            <div class="date-inputs">
                                <input type="date" id="fechaInicio" placeholder="Fecha inicio">
                                <input type="date" id="fechaFin" placeholder="Fecha fin">
                            </div>
                            <input type="text" id="fechas" placeholder="Seleccionar fechas" readonly style="display: none;">
                            <small onclick="toggleDateInputs()">
                                Usar selector de rango avanzado
                            </small>
                        </div>

                        <div class="filter-group">
                            <label for="tipoComprobante">Tipo de Comprobante</label>
                            <select id="tipoComprobante">
                                <option value="">Todos</option>
                                <option value="I">Ingreso</option>
                                <option value="E">Egreso</option>
                                <option value="T">Traslado</option>
                                <option value="P">Pago</option>
                                <option value="N">Nómina</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="moneda">Moneda</label>
                            <select id="moneda">
                                <option value="">Todas</option>
                                <option value="MXN">Peso Mexicano (MXN)</option>
                                <option value="USD">Dólar (USD)</option>
                                <option value="EUR">Euro (EUR)</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="montoMin">Monto Mínimo</label>
                            <input type="number" id="montoMin" placeholder="0.00" step="0.01">
                        </div>

                        <div class="filter-group">
                            <label for="montoMax">Monto Máximo</label>
                            <input type="number" id="montoMax" placeholder="999999.99" step="0.01">
                        </div>

                        <div class="filter-group">
                            <label for="version">Versión CFDI</label>
                            <select id="version">
                                <option value="">Todas</option>
                                <option value="3.3">CFDI 3.3</option>
                                <option value="4.0">CFDI 4.0</option>
                            </select>
                        </div>
                    </div>

                    <a href="#" class="toggle-advanced" onclick="toggleAdvancedFilters()">
                        <i class="fas fa-cog"></i> Filtros Avanzados
                    </a>

                    <!-- Filtros Avanzados -->
                    <div class="advanced-filters" id="advancedFilters">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="serie">Serie</label>
                                <input type="text" id="serie" placeholder="Serie del comprobante">
                            </div>

                            <div class="filter-group">
                                <label for="formaPago">Forma de Pago</label>
                                <select id="formaPago">
                                    <option value="">Todas</option>
                                    <option value="01">Efectivo</option>
                                    <option value="02">Cheque nominativo</option>
                                    <option value="03">Transferencia electrónica</option>
                                    <option value="04">Tarjeta de crédito</option>
                                    <option value="28">Tarjeta de débito</option>
                                    <option value="99">Por definir</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="metodoPago">Método de Pago</label>
                                <select id="metodoPago">
                                    <option value="">Todos</option>
                                    <option value="PUE">Pago en una sola exhibición</option>
                                    <option value="PPD">Pago en parcialidades o diferido</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="usoCfdi">Uso de CFDI</label>
                                <select id="usoCfdi">
                                    <option value="">Todos</option>
                                    <option value="G01">Adquisición de mercancías</option>
                                    <option value="G02">Devoluciones, descuentos o bonificaciones</option>
                                    <option value="G03">Gastos en general</option>
                                    <option value="I01">Construcciones</option>
                                    <option value="P01">Por definir</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="generarReporte()">
                            <i class="fas fa-search"></i> Generar Reporte
                        </button>
                        <button class="btn btn-success" onclick="exportarExcel()">
                            <i class="fas fa-file-excel"></i> Exportar Excel
                        </button>
                        <button class="btn btn-info" onclick="exportarPDF()">
                            <i class="fas fa-file-pdf"></i> Exportar PDF
                        </button>
                        <button class="btn btn-warning" onclick="limpiarFiltros()">
                            <i class="fas fa-refresh"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Procesando consulta...</p>
                </div>

                <!-- Estadísticas -->
                <div class="stats-container" id="statsContainer" style="display: none;">
                    <div class="stat-card">
                        <div class="stat-value" id="totalCfdis">0</div>
                        <div class="stat-label">Total CFDIs</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value" id="totalFacturado">$0.00</div>
                        <div class="stat-label">Total Facturado</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value" id="totalImpuestos">$0.00</div>
                        <div class="stat-label">Total Impuestos</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-value" id="promedioTicket">$0.00</div>
                        <div class="stat-label">Promedio por CFDI</div>
                    </div>
                </div>

                <!-- Gráficas -->
                <div class="charts-container" id="chartsContainer" style="display: none;">
                    <div class="chart-card">
                        <h4>Distribución por Tipo de Comprobante</h4>
                        <canvas id="tipoComprobanteChart"></canvas>
                    </div>
                    <div class="chart-card">
                        <h4>Evolución Mensual</h4>
                        <canvas id="evolucionChart"></canvas>
                    </div>
                </div>

                <!-- Resultados -->
                <div class="results-container" id="resultsContainer" style="display: none;">
                    <div class="export-options">
                        <button class="btn btn-sm btn-success" onclick="exportarResultados('excel')">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="btn btn-sm btn-info" onclick="exportarResultados('pdf')">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="exportarResultados('csv')">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                    </div>

                    <table id="resultsTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>UUID</th>
                                <th>Fecha</th>
                                <th>RFC Emisor</th>
                                <th>Nombre Emisor</th>
                                <th>RFC Receptor</th>
                                <th>Nombre Receptor</th>
                                <th>Total</th>
                                <th>Moneda</th>
                                <th>Tipo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://unpkg.com/papaparse@5.4.1/papaparse.min.js"></script>

    <script>
        let dataTable;
        let currentData = [];

        // Debugging
        console.log('jQuery loaded:', typeof $ !== 'undefined');
        console.log('Chart.js loaded:', typeof Chart !== 'undefined');
        console.log('Moment.js loaded:', typeof moment !== 'undefined');

        // Inicializar componentes
        $(document).ready(function() {
            console.log('Document ready');
            // No inicializar daterangepicker por defecto
            initializeDataTable();
            cargarRFCs();
            configurarFechasIniciales();
        });

        function configurarFechasIniciales() {
            // Configurar valores por defecto en campos de fecha simples
            const today = new Date();
            const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));

            $('#fechaInicio').val(thirtyDaysAgo.toISOString().split('T')[0]);
            $('#fechaFin').val(today.toISOString().split('T')[0]);
        }

        function cargarRFCs() {
            const selectRFC = $('#rfc');
            selectRFC.html('<option value="">Cargando RFCs...</option>');

            $.ajax({
                url: 'api/obtener_rfcs.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('RFCs response:', response);
                    if (response.success && response.data) {
                        selectRFC.empty();
                        selectRFC.append('<option value="">Seleccionar RFC...</option>');

                        response.data.forEach(function(item) {
                            const optionText = item.legal_name ?
                                `${item.rfc} - ${item.legal_name}` :
                                item.rfc;
                            selectRFC.append(
                                `<option value="${item.rfc}">${optionText}</option>`
                            );
                        });

                        console.log('RFCs cargados:', response.data.length);
                    } else {
                        console.error('Error al cargar RFCs:', response.message);
                        selectRFC.html('<option value="">Error al cargar RFCs</option>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error al cargar RFCs:', error);
                    // En caso de error, mantener funcionalidad básica
                    selectRFC.html('<option value="">Error al cargar RFCs</option>');
                }
            });
        }

        function initializeDatePicker() {
            try {
                $('#fechas').daterangepicker({
                    locale: {
                        format: 'YYYY-MM-DD',
                        separator: ' a ',
                        applyLabel: 'Aplicar',
                        cancelLabel: 'Cancelar',
                        fromLabel: 'Desde',
                        toLabel: 'Hasta',
                        customRangeLabel: 'Personalizado',
                        weekLabel: 'S',
                        daysOfWeek: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
                        monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
                        ],
                        firstDay: 1
                    },
                    ranges: {
                        'Hoy': [moment(), moment()],
                        'Últimos 7 días': [moment().subtract(6, 'days'), moment()],
                        'Últimos 30 días': [moment().subtract(29, 'days'), moment()],
                        'Este mes': [moment().startOf('month'), moment().endOf('month')],
                        'Mes pasado': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                        '2025': [moment('2025-01-01'), moment('2025-12-31')],
                        '2024': [moment('2024-01-01'), moment('2024-12-31')],
                        '2023': [moment('2023-01-01'), moment('2023-12-31')],
                        '2022': [moment('2022-01-01'), moment('2022-12-31')],
                        '2021': [moment('2021-01-01'), moment('2021-12-31')],
                        '2020': [moment('2020-01-01'), moment('2020-12-31')]
                    },
                    startDate: moment().subtract(30, 'days'),
                    endDate: moment(),
                    showDropdowns: true,
                    minYear: 2010,
                    maxYear: 2030,
                    autoApply: false,
                    linkedCalendars: false
                });

                console.log('DateRangePicker initialized');
            } catch (error) {
                console.error('Error initializing DateRangePicker:', error);
            }
        }

        function initializeDataTable() {
            try {
                dataTable = $('#resultsTable').DataTable({
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                    },
                    pageLength: 25,
                    responsive: true,
                    order: [
                        [1, 'desc']
                    ],
                    columnDefs: [{
                            targets: [6], // Columna Total
                            render: function(data, type, row) {
                                if (type === 'display') {
                                    return '$' + parseFloat(data || 0).toLocaleString('es-MX', {
                                        minimumFractionDigits: 2
                                    });
                                }
                                return data;
                            }
                        },
                        {
                            targets: [9], // Columna Acciones
                            orderable: false,
                            render: function(data, type, row) {
                                return `
                                    <button class="btn btn-sm btn-info" onclick="verDetalle('${row[0]}')" title="Ver detalle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-success" onclick="descargarXML('${row[0]}')" title="Descargar XML">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="generarPDF('${row[0]}')" title="Generar PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                `;
                            }
                        }
                    ]
                });
                console.log('DataTable initialized');
            } catch (error) {
                console.error('Error initializing DataTable:', error);
            }
        }

        function toggleAdvancedFilters() {
            const filters = document.getElementById('advancedFilters');
            const toggle = document.querySelector('.toggle-advanced');

            if (filters.style.display === 'none' || filters.style.display === '') {
                filters.style.display = 'block';
                toggle.innerHTML = '<i class="fas fa-cog"></i> Ocultar Filtros Avanzados';
            } else {
                filters.style.display = 'none';
                toggle.innerHTML = '<i class="fas fa-cog"></i> Filtros Avanzados';
            }
        }

        function toggleDateInputs() {
            const fechasInput = $('#fechas');
            const dateInputs = $('.date-inputs');
            const toggleText = $('small[onclick="toggleDateInputs()"]');

            if (fechasInput.is(':visible')) {
                // Volver a campos simples
                fechasInput.hide();
                dateInputs.show();
                toggleText.text('Usar selector de rango avanzado');

                // Configurar valores por defecto en campos simples
                const today = new Date();
                const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));

                $('#fechaInicio').val(thirtyDaysAgo.toISOString().split('T')[0]);
                $('#fechaFin').val(today.toISOString().split('T')[0]);
            } else {
                // Mostrar daterangepicker
                dateInputs.hide();
                fechasInput.show();
                toggleText.text('Usar campos de fecha simples');

                // Inicializar daterangepicker
                if (!fechasInput.data('daterangepicker')) {
                    initializeDatePicker();
                }
            }
        }

        function generarReporte() {
            console.log('Generando reporte...');

            // VALIDACIÓN: Verificar que se haya seleccionado un RFC
            const rfcSeleccionado = $('#rfc').val();
            if (!rfcSeleccionado || rfcSeleccionado === '' || rfcSeleccionado === 'Seleccionar RFC...') {
                alert('⚠️ Error: Debe seleccionar un RFC antes de generar el reporte.');
                return;
            }

            showLoading(true);

            // Determinar qué tipo de fecha usar
            let fechasValue = '';
            if ($('#fechas').is(':visible')) {
                // Usar daterangepicker
                fechasValue = $('#fechas').val();
            } else {
                // Usar campos simples (por defecto)
                const fechaInicio = $('#fechaInicio').val();
                const fechaFin = $('#fechaFin').val();
                if (fechaInicio && fechaFin) {
                    fechasValue = fechaInicio + ' a ' + fechaFin;
                }
            }

            const filtros = {
                tipoReporte: $('#tipoReporte').val(),
                rfc: rfcSeleccionado,
                fechas: fechasValue,
                tipoComprobante: $('#tipoComprobante').val(),
                moneda: $('#moneda').val(),
                montoMin: $('#montoMin').val(),
                montoMax: $('#montoMax').val(),
                version: $('#version').val(),
                serie: $('#serie').val(),
                formaPago: $('#formaPago').val(),
                metodoPago: $('#metodoPago').val(),
                usoCfdi: $('#usoCfdi').val()
            };

            console.log('Filtros:', filtros);

            $.ajax({
                url: 'api/generar_reporte.php',
                method: 'POST',
                data: filtros,
                dataType: 'json',
                success: function(response) {
                    console.log('Response:', response);
                    if (response.success) {
                        currentData = response.data;
                        actualizarEstadisticas(response.stats);
                        actualizarTabla(response.data);
                        if (typeof Chart !== 'undefined') {
                            actualizarGraficas(response.charts);
                        }
                        mostrarResultados(true);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', {
                        xhr,
                        status,
                        error
                    });
                    alert('Error al procesar la consulta: ' + error);
                },
                complete: function() {
                    showLoading(false);
                }
            });
        }

        function actualizarEstadisticas(stats) {
            $('#totalCfdis').text((stats.total_cfdis || 0).toLocaleString());
            $('#totalFacturado').text('$' + parseFloat(stats.total_facturado || 0).toLocaleString('es-MX', {
                minimumFractionDigits: 2
            }));
            $('#totalImpuestos').text('$' + parseFloat(stats.total_impuestos || 0).toLocaleString('es-MX', {
                minimumFractionDigits: 2
            }));
            $('#promedioTicket').text('$' + parseFloat(stats.promedio_ticket || 0).toLocaleString('es-MX', {
                minimumFractionDigits: 2
            }));
        }

        function actualizarTabla(data) {
            if (dataTable) {
                dataTable.clear();
                dataTable.rows.add(data);
                dataTable.draw();
            }
        }

        function actualizarGraficas(chartsData) {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded, skipping charts');
                return;
            }

            try {
                // Gráfica de tipos de comprobante
                const ctx1 = document.getElementById('tipoComprobanteChart').getContext('2d');
                new Chart(ctx1, {
                    type: 'doughnut',
                    data: {
                        labels: chartsData.tipos.labels || [],
                        datasets: [{
                            data: chartsData.tipos.data || [],
                            backgroundColor: ['#4285f4', '#34a853', '#fbbc04', '#ea4335', '#9c27b0']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });

                // Gráfica de evolución
                const ctx2 = document.getElementById('evolucionChart').getContext('2d');
                new Chart(ctx2, {
                    type: 'line',
                    data: {
                        labels: chartsData.evolucion.labels || [],
                        datasets: [{
                            label: 'Total Facturado',
                            data: chartsData.evolucion.data || [],
                            borderColor: '#4285f4',
                            backgroundColor: 'rgba(66, 133, 244, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });

                console.log('Charts updated');
            } catch (error) {
                console.error('Error updating charts:', error);
            }
        }

        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }

        function mostrarResultados(show) {
            document.getElementById('statsContainer').style.display = show ? 'grid' : 'none';
            document.getElementById('chartsContainer').style.display = show ? 'grid' : 'none';
            document.getElementById('resultsContainer').style.display = show ? 'block' : 'none';
        }

        function limpiarFiltros() {
            $('#tipoReporte').val('general');
            $('#rfc').val('');
            $('#fechas').val('');
            $('#fechaInicio').val('');
            $('#fechaFin').val('');
            $('#tipoComprobante').val('');
            $('#moneda').val('');
            $('#montoMin').val('');
            $('#montoMax').val('');
            $('#version').val('');
            $('#serie').val('');
            $('#formaPago').val('');
            $('#metodoPago').val('');
            $('#usoCfdi').val('');

            mostrarResultados(false);
        }

        function exportarExcel() {
            // Validar RFC seleccionado
            const rfcSeleccionado = $('#rfc').val();
            if (!rfcSeleccionado || rfcSeleccionado === '' || rfcSeleccionado === 'Seleccionar RFC...') {
                alert('⚠️ Error: Debe seleccionar un RFC antes de exportar.');
                return;
            }

            // Determinar fechas
            let fechasValue = '';
            if ($('#fechas').is(':visible')) {
                fechasValue = $('#fechas').val();
            } else {
                const fechaInicio = $('#fechaInicio').val();
                const fechaFin = $('#fechaFin').val();
                if (fechaInicio && fechaFin) {
                    fechasValue = fechaInicio + ' a ' + fechaFin;
                }
            }

            // Construir filtros igual que generarReporte, pero con excel: 1
            const filtros = {
                tipoReporte: $('#tipoReporte').val(),
                rfc: rfcSeleccionado,
                fechas: fechasValue,
                tipoComprobante: $('#tipoComprobante').val(),
                moneda: $('#moneda').val(),
                montoMin: $('#montoMin').val(),
                montoMax: $('#montoMax').val(),
                version: $('#version').val(),
                serie: $('#serie').val(),
                formaPago: $('#formaPago').val(),
                metodoPago: $('#metodoPago').val(),
                usoCfdi: $('#usoCfdi').val(),
                excel: 1
            };

            showLoading(true);
            $.ajax({
                url: 'api/generar_reporte.php',
                method: 'POST',
                data: filtros,
                dataType: 'json',
                success: function(response) {
                    showLoading(false);
                    if (response.success) {
                        const data = response.data;
                        // Obtener información del encabezado
                        const rfcTexto = $('#rfc option:selected').text();
                        const tipoReporte = $('#tipoReporte option:selected').text();
                        let periodoTexto = '';
                        if ($('#fechas').is(':visible')) {
                            periodoTexto = $('#fechas').val();
                        } else {
                            const fechaInicio = $('#fechaInicio').val();
                            const fechaFin = $('#fechaFin').val();
                            if (fechaInicio && fechaFin) {
                                periodoTexto = fechaInicio + ' a ' + fechaFin;
                            } else {
                                periodoTexto = '';
                            }
                        }
                        let rfcLimpio = rfcSeleccionado;
                        let razonSocial = rfcTexto;
                        if (rfcTexto.includes(' - ')) {
                            const partes = rfcTexto.split(' - ');
                            rfcLimpio = partes[0];
                            razonSocial = partes[1];
                        }
                        const wb = XLSX.utils.book_new();
                        const ws = {};
                        ws['A1'] = {
                            v: razonSocial,
                            t: 's'
                        };
                        ws['A2'] = {
                            v: rfcLimpio,
                            t: 's'
                        };
                        ws['A3'] = {
                            v: tipoReporte,
                            t: 's'
                        };
                        ws['A4'] = {
                            v: `Período: ${periodoTexto}`,
                            t: 's'
                        };
                        const columnas = [
                            'Sello', 'SAT', 'Estatus', 'Version', 'CFDI Relacionado', 'Tipo', 'UUID', 'UUID Sistitucion', 'Serie', 'Folio', 'Emision', 'Descripcion Uso CFDI',
                            'Emisor RFC', 'Emisor Nombre', 'Receptor RFC', 'Receptor Nombre', 'Emisro Regimen', 'Conceptos', 'SubTotal', 'Base IVA 16',
                            'BASE IVA 0', 'BASE IVA Exento', 'Descuento', 'IVA', 'Impuestro Local Transaccional', 'IVA Retenido', 'ISR Retenido', 'Total',
                            'Total en XML', 'Tipo Cambio', 'Moneda', 'Forma de Pago'
                        ];
                        columnas.forEach((titulo, index) => {
                            const col = XLSX.utils.encode_col(index);
                            ws[col + '5'] = {
                                v: titulo,
                                t: 's',
                                s: {
                                    fill: {
                                        fgColor: {
                                            rgb: 'D9D9D9'
                                        }
                                    }, // fondo gris
                                    font: {
                                        color: {
                                            rgb: '000000'
                                        },
                                        bold: true
                                    }, // tipografía negra y negrita
                                    alignment: {
                                        horizontal: 'center',
                                        vertical: 'center'
                                    }
                                }
                            };
                        });

                        // Agregar autofiltro a la fila de títulos
                        ws['!autofilter'] = {
                            ref: `A5:${XLSX.utils.encode_col(columnas.length - 1)}5`
                        };
                        data.forEach((registro, rowIndex) => {
                            const fila = rowIndex + 6;
                            for (let i = 0; i < columnas.length; i++) {
                                const valor = registro[i] || '';
                                const col = XLSX.utils.encode_col(i);
                                const cellRef = col + fila;
                                if (typeof valor === 'string' && !isNaN(parseFloat(valor)) && isFinite(valor) && valor.includes('.')) {
                                    ws[cellRef] = {
                                        v: parseFloat(valor),
                                        t: 'n'
                                    };
                                } else {
                                    ws[cellRef] = {
                                        v: valor || '',
                                        t: 's'
                                    };
                                }
                            }
                        });
                        const lastRow = data.length + 5;
                        const lastCol = XLSX.utils.encode_col(columnas.length - 1);
                        ws['!ref'] = `A1:${lastCol}${lastRow}`;
                        XLSX.utils.book_append_sheet(wb, ws, "Reporte CFDI");
                        const tipoReporteLimpio = tipoReporte.replace(/[^a-zA-Z0-9]/g, '_');
                        const nombreArchivo = `reporte_cfdi_${tipoReporteLimpio}_${moment().format('YYYY-MM-DD')}.xlsx`;
                        XLSX.writeFile(wb, nombreArchivo);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    showLoading(false);
                    alert('Error al procesar la consulta: ' + error);
                }
            });
        }

        function exportarPDF() {
            alert('Funcionalidad de PDF en desarrollo');
        }

        function exportarResultados(formato) {
            switch (formato) {
                case 'excel':
                    exportarExcel();
                    break;
                case 'pdf':
                    exportarPDF();
                    break;
                case 'csv':
                    exportarCSV();
                    break;
            }
        }

        function exportarCSV() {
            if (currentData.length === 0) {
                alert('Primero genere un reporte para exportar');
                return;
            }

            if (typeof Papa === 'undefined') {
                alert('Error: Librería CSV no disponible');
                return;
            }

            const csv = Papa.unparse(currentData);
            const blob = new Blob([csv], {
                type: 'text/csv;charset=utf-8;'
            });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `reporte_cfdi_${moment().format('YYYY-MM-DD')}.csv`;
            link.click();
        }

        function verDetalle(uuid) {
            window.open(`detalle_cfdi.php?uuid=${uuid}`, '_blank');
        }

        function descargarXML(uuid) {
            window.location.href = `api/descargar_xml.php?uuid=${uuid}`;
        }

        function generarPDF(uuid) {
            window.open(`api/generar_pdf_cfdi.php?uuid=${uuid}`, '_blank');
        }
    </script>
</body>

</html>