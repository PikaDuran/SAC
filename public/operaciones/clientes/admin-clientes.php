<?php
session_start();
require_once '../../../src/helpers/auth.php';
require_once '../../../src/config/database.php';
checkAuth(['admin', 'operaciones']);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Clientes - SAC</title>
    <link rel="stylesheet" href="../../dashboard/dashboard.css">
    <link rel="stylesheet" href="clientes.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../../src/views/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../../../src/views/header.php'; ?>

            <div class="content">
                <h1>Administración de Clientes</h1>
                <div class="module-container">
                    <div class="toolbar">
                        <button class="btn-primary" onclick="openModal('add')">+ Nuevo Cliente</button>
                        <button class="btn-secondary" onclick="exportClients()">Exportar</button>
                    </div>

                    <div class="filters">
                        <input type="text" id="searchClient" placeholder="Buscar cliente...">
                        <select id="statusFilter">
                            <option value="">Todos los estados</option>
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>

                    <div class="table-container">
                        <table id="clientsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>RFC</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Los datos se cargarán dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal para agregar/editar cliente -->
    <div id="clientModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">Nuevo Cliente</h2>
            <form id="clientForm">
                <input type="hidden" id="clientId">
                <div class="form-grid">
                    <div class="input-group">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" required>
                    </div>
                    <div class="input-group">
                        <label for="rfc">RFC:</label>
                        <input type="text" id="rfc" name="rfc" required>
                    </div>
                    <div class="input-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="input-group">
                        <label for="telefono">Teléfono:</label>
                        <input type="tel" id="telefono" name="telefono">
                    </div>
                    <div class="input-group">
                        <label for="direccion">Dirección:</label>
                        <textarea id="direccion" name="direccion"></textarea>
                    </div>
                    <div class="input-group">
                        <label for="estado">Estado:</label>
                        <select id="estado" name="estado">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Guardar</button>
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../dashboard/dashboard.js"></script>
    <script src="admin-clientes.js"></script>
</body>

</html>