<?php
session_start();
require_once '../../../src/helpers/auth.php';
require_once '../../../src/config/database.php';

// Solo admin puede ver los logs completos
checkAuth(['admin']);


// Obtener logs con filtros
$filters = [];
$params = [];
$whereClause = '';

if (!empty($_GET['module'])) {
    $filters[] = "module = ?";
    $params[] = $_GET['module'];
}

if (!empty($_GET['action'])) {
    $filters[] = "action = ?";
    $params[] = $_GET['action'];
}

if (!empty($_GET['user_id'])) {
    $filters[] = "al.user_id = ?";
    $params[] = $_GET['user_id'];
}

if (!empty($_GET['date_from'])) {
    $filters[] = "DATE(al.created_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $filters[] = "DATE(al.created_at) <= ?";
    $params[] = $_GET['date_to'];
}

if (!empty($filters)) {
    $whereClause = 'WHERE ' . implode(' AND ', $filters);
}

try {
    $pdo = getDatabase();

    // Obtener logs con información del usuario
    $sql = "
        SELECT al.*, u.usuario, u.nombre, u.apellido 
        FROM activity_logs al 
        LEFT JOIN usuarios u ON al.user_id = u.id 
        {$whereClause}
        ORDER BY al.created_at DESC 
        LIMIT 500
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener estadísticas
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_activities,
            COUNT(DISTINCT user_id) as active_users,
            COUNT(DISTINCT module) as modules_used,
            DATE(MAX(created_at)) as last_activity_date
        FROM activity_logs 
        WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Obtener usuarios para filtros
    $usersStmt = $pdo->query("SELECT id, usuario, nombre, apellido FROM usuarios ORDER BY nombre");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $logs = [];
    $stats = [];
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría y Logs - SAC</title>
    <link rel="stylesheet" href="../../dashboard/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <style>
        .audit-container {
            padding: 24px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 13px;
            color: #64748b;
        }

        .filters-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .logs-table {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }

        .logs-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .logs-table th {
            background: #f8fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .logs-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }

        .logs-table tr:hover {
            background: #f8fafc;
        }

        .action-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        .action-login {
            background: #dbeafe;
            color: #1e40af;
        }

        .action-logout {
            background: #fee2e2;
            color: #dc2626;
        }

        .action-create {
            background: #dcfce7;
            color: #16a34a;
        }

        .action-update {
            background: #fef3c7;
            color: #d97706;
        }

        .action-delete {
            background: #fecaca;
            color: #dc2626;
        }

        .action-view {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .action-download {
            background: #f3e8ff;
            color: #9333ea;
        }

        .module-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            background: #f1f5f9;
            color: #475569;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../../src/views/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../../../src/views/header.php'; ?>

            <div class="content">
                <div class="audit-container">
                    <div class="toolbar">
                        <h1>Auditoría y Logs del Sistema</h1>
                        <div class="toolbar-actions">
                            <button class="btn-secondary" onclick="exportLogs()">📋 Exportar</button>
                            <button class="btn-secondary" onclick="refreshLogs()">🔄 Actualizar</button>
                        </div>
                    </div>

                    <!-- Estadísticas -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo number_format($stats['total_activities'] ?? 0); ?></div>
                            <div class="stat-label">Actividades (30 días)</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['active_users'] ?? 0; ?></div>
                            <div class="stat-label">Usuarios Activos</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['modules_used'] ?? 0; ?></div>
                            <div class="stat-label">Módulos Usados</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['last_activity_date'] ?? 'N/A'; ?></div>
                            <div class="stat-label">Última Actividad</div>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="filters-section">
                        <h3>Filtros</h3>
                        <form method="GET">
                            <div class="filters-grid">
                                <div class="form-group">
                                    <label>Módulo</label>
                                    <select name="module">
                                        <option value="">Todos los módulos</option>
                                        <option value="LOGIN" <?php echo $_GET['module'] === 'LOGIN' ? 'selected' : ''; ?>>Login</option>
                                        <option value="E_FIRMA" <?php echo $_GET['module'] === 'E_FIRMA' ? 'selected' : ''; ?>>e.Firma</option>
                                        <option value="CLIENTES" <?php echo $_GET['module'] === 'CLIENTES' ? 'selected' : ''; ?>>Clientes</option>
                                        <option value="SOLICITUDES" <?php echo $_GET['module'] === 'SOLICITUDES' ? 'selected' : ''; ?>>Solicitudes RH</option>
                                        <option value="USUARIOS" <?php echo $_GET['module'] === 'USUARIOS' ? 'selected' : ''; ?>>Usuarios</option>
                                        <option value="DASHBOARD" <?php echo $_GET['module'] === 'DASHBOARD' ? 'selected' : ''; ?>>Dashboard</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Acción</label>
                                    <select name="action">
                                        <option value="">Todas las acciones</option>
                                        <option value="LOGIN" <?php echo $_GET['action'] === 'LOGIN' ? 'selected' : ''; ?>>Login</option>
                                        <option value="LOGOUT" <?php echo $_GET['action'] === 'LOGOUT' ? 'selected' : ''; ?>>Logout</option>
                                        <option value="CREATE" <?php echo $_GET['action'] === 'CREATE' ? 'selected' : ''; ?>>Crear</option>
                                        <option value="UPDATE" <?php echo $_GET['action'] === 'UPDATE' ? 'selected' : ''; ?>>Actualizar</option>
                                        <option value="DELETE" <?php echo $_GET['action'] === 'DELETE' ? 'selected' : ''; ?>>Eliminar</option>
                                        <option value="VIEW" <?php echo $_GET['action'] === 'VIEW' ? 'selected' : ''; ?>>Ver</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Usuario</label>
                                    <select name="user_id">
                                        <option value="">Todos los usuarios</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo $_GET['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellido'] . ' (' . $user['usuario'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Fecha Desde</label>
                                    <input type="date" name="date_from" value="<?php echo $_GET['date_from'] ?? ''; ?>">
                                </div>

                                <div class="form-group">
                                    <label>Fecha Hasta</label>
                                    <input type="date" name="date_to" value="<?php echo $_GET['date_to'] ?? ''; ?>">
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                                <a href="audit-logs.php" class="btn btn-secondary">Limpiar</a>
                            </div>
                        </form>
                    </div>

                    <!-- Tabla de Logs -->
                    <div class="logs-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Módulo</th>
                                    <th>Descripción</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($logs)): ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                            <td>
                                                <?php if ($log['usuario']): ?>
                                                    <?php echo htmlspecialchars($log['nombre'] . ' ' . $log['apellido']); ?>
                                                    <small>(<?php echo htmlspecialchars($log['usuario']); ?>)</small>
                                                <?php else: ?>
                                                    <em>Sistema</em>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="action-badge action-<?php echo strtolower($log['action']); ?>">
                                                    <?php echo htmlspecialchars($log['action']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($log['module']): ?>
                                                    <span class="module-badge"><?php echo htmlspecialchars($log['module']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['description']); ?></td>
                                            <td><small><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 32px;">
                                            No se encontraron actividades con los filtros aplicados.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../../dashboard/dashboard.js"></script>
    <script>
        function refreshLogs() {
            location.reload();
        }

        function exportLogs() {
            // Obtener parámetros actuales
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');

            // Crear enlace de descarga
            const link = document.createElement('a');
            link.href = 'export-logs.php?' + params.toString();
            link.download = 'audit_logs_' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>

</html>