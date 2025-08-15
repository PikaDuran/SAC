<?php
session_start();
require_once '../../../src/helpers/auth.php';
require_once '../../../src/config/database.php';
checkAuth(['admin', 'hr']);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes RH - SAC</title>
    <link rel="stylesheet" href="../../dashboard/dashboard.css">
    <link rel="stylesheet" href="solicitudes.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../../src/views/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../../../src/views/header.php'; ?>

            <div class="content">
                <h1>Gestión de Solicitudes RH</h1>
                <div class="module-container">
                    <div class="toolbar">
                        <button class="btn-primary" onclick="openModal('add')">+ Nueva Solicitud</button>
                        <select id="statusFilter">
                            <option value="">Todas las solicitudes</option>
                            <option value="pendiente">Pendientes</option>
                            <option value="aprobada">Aprobadas</option>
                            <option value="rechazada">Rechazadas</option>
                        </select>
                    </div>

                    <div class="table-container">
                        <table id="solicitudesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Empleado</th>
                                    <th>Tipo</th>
                                    <th>Fecha Inicio</th>
                                    <th>Fecha Fin</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Datos dinámicos -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../../dashboard/dashboard.js"></script>
    <script src="solicitudes.js"></script>
</body>

</html>