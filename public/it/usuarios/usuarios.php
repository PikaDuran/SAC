<?php
session_start();
require_once '../../../src/helpers/auth.php';
// Solo admin puede acceder a este módulo
checkAuth(['admin']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Usuarios - SAC</title>
    <link rel="stylesheet" href="../../dashboard/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Estilos para SweetAlert2 con z-index alto */
        .swal-high-z {
            z-index: 2147483648 !important;
        }

        .swal-high-z .swal2-container {
            z-index: 2147483648 !important;
        }

        /* Forzar z-index alto para todas las alertas de SweetAlert2 */
        .swal2-container {
            z-index: 2147483648 !important;
        }

        .swal2-popup {
            z-index: 2147483649 !important;
        }

        <style>.usuarios-container {
            padding: 20px;
        }

        .usuarios-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #007cba;
        }

        .usuarios-title {
            color: #007cba;
            font-size: 2rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-nuevo-usuario {
            background: #28a745;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }

        .btn-nuevo-usuario:hover {
            background: #218838;
        }

        .usuarios-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .usuarios-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .usuarios-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }

        .usuarios-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .usuarios-table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-admin {
            background: #dc3545;
            color: white;
        }

        .badge-contabilidad {
            background: #007cba;
            color: white;
        }

        .badge-operaciones {
            background: #28a745;
            color: white;
        }

        .badge-hr {
            background: #ffc107;
            color: #212529;
        }

        .badge-activo {
            background: #28a745;
            color: white;
        }

        .badge-inactivo {
            background: #6c757d;
            color: white;
        }

        .acciones-btn {
            display: flex;
            gap: 5px;
        }

        .btn-accion {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            transition: opacity 0.3s;
        }

        .btn-editar {
            background: #007cba;
            color: white;
        }

        .btn-editar:hover {
            background: #005a86;
        }

        .btn-eliminar {
            background: #dc3545;
            color: white;
        }

        .btn-eliminar:hover {
            background: #c82333;
        }

        .btn-toggle-estado {
            background: #6c757d;
            color: white;
        }

        .btn-toggle-estado:hover {
            background: #545b62;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #007cba;
        }

        /* Estilos para modal de usuario */
        .modal-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(0, 0, 0, 0.8) !important;
            display: none !important;
            justify-content: center !important;
            align-items: center !important;
            z-index: 2147483647 !important;
            /* Máximo z-index posible */
            opacity: 0 !important;
            transition: opacity 0.3s ease !important;
            pointer-events: auto !important;
        }

        .modal-overlay.show {
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        .modal {
            background: white !important;
            border-radius: 8px !important;
            padding: 0 !important;
            max-width: 500px !important;
            width: 90% !important;
            max-height: 90vh !important;
            overflow-y: auto !important;
            position: relative !important;
            z-index: 2147483647 !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
            margin: 0 !important;
            transform: none !important;
            pointer-events: auto !important;
        }

        .modal-header {
            background: #007cba;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
        }

        .modal-header h3 {
            margin: 0;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-primary {
            background: #007cba;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: #005a86;
        }

        .estadisticas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007cba;
        }

        .stat-label {
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../../src/views/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../../../src/views/header.php'; ?>

            <div class="usuarios-container">
                <div class="usuarios-header">
                    <h1 class="usuarios-title">
                        <i class="fa-solid fa-users-gear"></i>
                        Administración de Usuarios
                    </h1>
                    <button type="button" class="btn-nuevo-usuario" onclick="abrirModalUsuario()">
                        <i class="fa-solid fa-user-plus"></i>
                        Nuevo Usuario
                    </button>
                </div>

                <!-- Estadísticas -->
                <div class="estadisticas" id="estadisticas">
                    <div class="stat-card">
                        <div class="stat-number" id="total-usuarios">-</div>
                        <div class="stat-label">Total Usuarios</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="usuarios-activos">-</div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="usuarios-admin">-</div>
                        <div class="stat-label">Administradores</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="usuarios-inactivos">-</div>
                        <div class="stat-label">Usuarios Inactivos</div>
                    </div>
                </div>

                <!-- Tabla de usuarios -->
                <div class="usuarios-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre Completo</th>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Último Acceso</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-usuarios">
                            <tr>
                                <td colspan="8" class="loading">
                                    <i class="fa-solid fa-spinner fa-spin"></i> Cargando usuarios...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal para crear/editar usuario -->
    <div class="modal-overlay" id="modal-usuario">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modal-title">Nuevo Usuario</h3>
            </div>
            <div class="modal-body">
                <form id="form-usuario">
                    <input type="hidden" id="usuario-id" name="id">

                    <div class="form-group">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="apellido">Apellido:</label>
                        <input type="text" id="apellido" name="apellido" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="usuario">Usuario:</label>
                        <input type="text" id="usuario" name="usuario" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña:</label>
                        <input type="password" id="password" name="password" class="form-control">
                        <small id="password-help" style="color: #6c757d; font-size: 0.875rem;">
                            Deja en blanco para mantener la contraseña actual (solo al editar)
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="rol">Rol:</label>
                        <select id="rol" name="rol" class="form-control" required>
                            <option value="">Seleccionar rol...</option>
                            <option value="admin">Administrador</option>
                            <option value="contabilidad">Contabilidad</option>
                            <option value="operaciones">Operaciones</option>
                            <option value="hr">Recursos Humanos</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado:</label>
                        <select id="estado" name="estado" class="form-control" required>
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="cerrarModalUsuario()">
                    Cancelar
                </button>
                <button type="button" class="btn-primary" onclick="guardarUsuario()">
                    <i class="fa-solid fa-save"></i> Guardar
                </button>
            </div>
        </div>
    </div>

    <script src="../../dashboard/dashboard.js"></script>
    <script src="usuarios.js"></script>
</body>

</html>